<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Adaptadores\IcalAdaptador;
use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Modelos\EventoIcalDTO;
use CamargoPMS\Modelos\EventoIcalExterno;
use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ConexionIcalRepositorio;
use CamargoPMS\Excepciones\ConexionIcalEnSincronizacionExcepcion;
use CamargoPMS\Repositorios\EventoIcalExternoRepositorio;
use CamargoPMS\Repositorios\SincronizacionIcalLogRepositorio;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Servicio Central de Sincronización e Importación de Feeds iCalendar.
 *
 * Principios vinculantes certificados:
 * 1. EVENTO ICAL EXTERNO ≠ RESERVA PMS: Los eventos se importan como bloqueos de calendario,
 *    sin crear Persona, Cliente, Folio, Reserva, ni Contrato.
 * 2. CERO ALTER DDL en inventario:
 *    tipo_bloqueo = 'BLOQUEO_MANUAL', origen_tipo = 'EVENTO_ICAL_EXTERNO', origen_id = evento.id.
 * 3. IDENTIDAD E IDEMPOTENCIA: UNIQUE(conexion_ical_id, uid_externo).
 * 4. RECONCILIACIÓN DETERMINISTA MULTI-OTA:
 *    Disponibilidad física = UNIÓN de todos los orígenes activos.
 *    La cancelación de una OTA no libera fechas si otra OTA continúa bloqueándolas.
 * 5. PRESERVACIÓN SOBERANA DE RESERVAS LOCALES:
 *    Colisiones con reservas locales marcan el evento como EN_CONFLICTO sin sobreescribir la reserva.
 * 6. SALVAGUARDA ANTE FEEDS VACÍOS (0 VEVENTs):
 *    No libera inventario si existían eventos previos.
 * 7. PRESERVACIÓN DEL HISTÓRICO: Eventos pasados (fecha_fin < hoy) nunca se eliminan ni liberan.
 */
class SincronizacionIcalServicio
{
    private PDO $pdo;
    private ConexionIcalRepositorio $conexionRepo;
    private EventoIcalExternoRepositorio $eventoRepo;
    private SincronizacionIcalLogRepositorio $logRepo;
    private IcalCriptografiaServicio $criptoServicio;
    private ClienteHttpIcalSeguro $clienteHttp;
    private IcalAdaptador $adaptador;

    public function __construct(
        ?PDO $pdo = null,
        ?ConexionIcalRepositorio $conexionRepo = null,
        ?EventoIcalExternoRepositorio $eventoRepo = null,
        ?SincronizacionIcalLogRepositorio $logRepo = null,
        ?IcalCriptografiaServicio $criptoServicio = null,
        ?ClienteHttpIcalSeguro $clienteHttp = null,
        ?IcalAdaptador $adaptador = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->conexionRepo = $conexionRepo ?? new ConexionIcalRepositorio($this->pdo);
        $this->eventoRepo = $eventoRepo ?? new EventoIcalExternoRepositorio($this->pdo);
        $this->logRepo = $logRepo ?? new SincronizacionIcalLogRepositorio($this->pdo);
        $this->criptoServicio = $criptoServicio ?? new IcalCriptografiaServicio();
        $this->clienteHttp = $clienteHttp ?? new ClienteHttpIcalSeguro();
        $this->adaptador = $adaptador ?? new IcalAdaptador();
    }

    /**
     * Ejecuta la sincronización completa de una conexión iCal a partir de un payload en crudo
     * o descargándolo desde su URL externa cifrada.
     *
     * @param int $conexionId
     * @param string|null $payloadIcsOpcional Si se provee, se utiliza directamente sin HTTP (ideal para tests y mocks).
     * @param string $origenEjecucion 'MANUAL', 'CLI', 'CRON', 'WEBHOOK'
     * @param int|null $actorId
     * @return array<string, mixed> Resumen de resultados de la corrida.
     */
    public function sincronizarConexion(
        int $conexionId,
        ?string $payloadIcsOpcional = null,
        string $origenEjecucion = SincronizacionIcalLog::ORIGEN_MANUAL,
        ?int $actorId = null
    ): array {
        $conexion = $this->conexionRepo->buscarPorId($conexionId);
        if (!$conexion) {
            throw new InvalidArgumentException("Conexión iCal #$conexionId no encontrada.");
        }

        if (!$conexion->estaActiva()) {
            throw new RuntimeException("La conexión iCal #$conexionId no se encuentra en estado ACTIVO.");
        }

        if (!$conexion->importacionHabilitada()) {
            throw new RuntimeException("La importación está deshabilitada para la conexión #$conexionId.");
        }

        // 1. Limpieza preventiva y normalización de stale runs (> 120s incompletos)
        $this->logRepo->limpiarLogsHuerfanos($conexionId, 120);

        // 2. Control de concurrencia de ciclo de vida (log activo)
        if ($this->logRepo->haySincronizacionEnCurso($conexionId, 120)) {
            throw new ConexionIcalEnSincronizacionExcepcion(
                'Ya existe una sincronización en curso para esta conexión. Por favor espere a que finalice.',
                'CONEXION_EN_SINCRONIZACION'
            );
        }

        // 3. Control atómico de concurrencia MySQL (GET_LOCK no bloqueante por conexión)
        $lockName = "camargo_ical_sync_{$conexionId}";
        $stmtLock = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $stmtLock->execute(['lock_name' => $lockName]);
        $lockAdquirido = (int) $stmtLock->fetchColumn() === 1;

        if (!$lockAdquirido) {
            throw new ConexionIcalEnSincronizacionExcepcion(
                'Ya existe una sincronización en curso para esta conexión (bloqueo concurrente activo).',
                'CONEXION_BLOQUEADA'
            );
        }

        try {
            $tiempoInicio = microtime(true);
            $logId = $this->logRepo->iniciarLog(
                conexionId: $conexionId,
                tipoOperacion: SincronizacionIcalLog::TIPO_IMPORTACION,
                origenEjecucion: $origenEjecucion,
                actorId: $actorId
            );

            $httpCodigo = 200;
            $duracionDescargaMs = 0;
            $contenidoIcs = '';

        try {
            // 1. Obtención del contenido ICS
            if ($payloadIcsOpcional !== null) {
                $contenidoIcs = $payloadIcsOpcional;
            } else {
                $urlCifrada = $conexion->obtenerUrlImportacionCifrada();
                if (!$urlCifrada) {
                    throw new RuntimeException("La conexión no tiene URL de importación configurada.");
                }

                $urlPlana = $this->criptoServicio->descifrar($urlCifrada);
                if (!$urlPlana) {
                    throw new RuntimeException("Fallo al descifrar la URL de importación (integridad o clave inválida).");
                }

                $descarga = $this->clienteHttp->descargar($urlPlana);
                $httpCodigo = $descarga['codigo_http'];
                $duracionDescargaMs = $descarga['duracion_ms'];
                $contenidoIcs = $descarga['contenido'];

                if ($httpCodigo !== 200) {
                    throw new RuntimeException("El servidor externo respondió con código HTTP $httpCodigo.");
                }
            }

            // 2. Parsing con IcalAdaptador (Sabre/VObject encapsulado)
            $eventosDto = $this->adaptador->parsear($contenidoIcs);

            // 3. Salvaguarda de Feed Vacío Sospechoso
            $previosActivos = $this->eventoRepo->contarActivosPorConexion($conexionId);
            if ($previosActivos > 0 && count($eventosDto) === 0) {
                $msgAdvertencia = "FEED_VACIO_SOSPECHOSO: El canal devolvió 0 eventos VEVENT pero existían $previosActivos eventos activos previamente. Se preservó el inventario intacto.";
                $duracionTotalMs = (int) round((microtime(true) - $tiempoInicio) * 1000);

                $this->logRepo->finalizarLog(
                    logId: $logId,
                    httpCodigo: $httpCodigo,
                    resultado: SincronizacionIcalLog::RESULTADO_CON_ADVERTENCIA,
                    duracionMs: $duracionTotalMs,
                    recibidos: 0,
                    creados: 0,
                    actualizados: 0,
                    cancelados: 0,
                    ausentes: 0,
                    conflictos: 0,
                    mensaje: $msgAdvertencia
                );

                $this->conexionRepo->actualizarUltimoResultado($conexionId, ConexionIcal::RESULTADO_CON_ADVERTENCIA, $msgAdvertencia);

                return [
                    'resultado' => ConexionIcal::RESULTADO_CON_ADVERTENCIA,
                    'mensaje' => $msgAdvertencia,
                    'eventos_recibidos' => 0,
                    'eventos_creados' => 0,
                    'eventos_actualizados' => 0,
                    'eventos_cancelados' => 0,
                    'eventos_ausentes' => 0,
                    'conflictos' => 0,
                ];
            }

            // 4. Procesamiento de eventos en BD
            $tzNombre = (string) Configuracion::obtener('APP_TIMEZONE', 'America/Lima');
            $hoyHotelero = (new DateTimeImmutable('now', new DateTimeZone($tzNombre)))->format('Y-m-d');
            $unidadId = $conexion->obtenerUnidadId();

            $creados = 0;
            $actualizados = 0;
            $cancelados = 0;
            $conflictos = 0;

            foreach ($eventosDto as $dto) {
                $resultadoEvento = $this->procesarEventoIndividual(
                    conexionId: $conexionId,
                    unidadId: $unidadId,
                    dto: $dto,
                    syncRunId: $logId,
                    fechaHoteleraHoy: $hoyHotelero
                );

                if ($resultadoEvento['accion'] === 'CREADO') {
                    $creados++;
                } elseif ($resultadoEvento['accion'] === 'ACTUALIZADO') {
                    $actualizados++;
                } elseif ($resultadoEvento['accion'] === 'CANCELADO') {
                    $cancelados++;
                }

                if ($resultadoEvento['conflicto']) {
                    $conflictos++;
                }
            }

            // 5. Conciliación de Ausencias (Eventos ausentes en el feed)
            $ausentes = $this->conciliarAusencias(
                conexionId: $conexionId,
                unidadId: $unidadId,
                syncRunId: $logId,
                fechaHoteleraHoy: $hoyHotelero
            );

            $duracionTotalMs = (int) round((microtime(true) - $tiempoInicio) * 1000);
            $resultadoFinal = ($conflictos > 0) ? SincronizacionIcalLog::RESULTADO_CON_ADVERTENCIA : SincronizacionIcalLog::RESULTADO_EXITO;
            $mensajeFinal = "Sincronización finalizada: " . count($eventosDto) . " recibidos, $creados creados, $actualizados actualizados, $cancelados cancelados, $ausentes ausentes, $conflictos conflictos.";

            $this->logRepo->finalizarLog(
                logId: $logId,
                httpCodigo: $httpCodigo,
                resultado: $resultadoFinal,
                duracionMs: $duracionTotalMs,
                recibidos: count($eventosDto),
                creados: $creados,
                actualizados: $actualizados,
                cancelados: $cancelados,
                ausentes: $ausentes,
                conflictos: $conflictos,
                mensaje: $mensajeFinal
            );

            $this->conexionRepo->actualizarUltimoResultado(
                $conexionId,
                $resultadoFinal,
                ($conflictos > 0) ? "$conflictos colisiones con reservas locales detectadas." : null
            );

            return [
                'resultado' => $resultadoFinal,
                'mensaje' => $mensajeFinal,
                'eventos_recibidos' => count($eventosDto),
                'eventos_creados' => $creados,
                'eventos_actualizados' => $actualizados,
                'eventos_cancelados' => $cancelados,
                'eventos_ausentes' => $ausentes,
                'conflictos' => $conflictos,
            ];

        } catch (Throwable $e) {
            $duracionTotalMs = (int) round((microtime(true) - $tiempoInicio) * 1000);
            $msgError = $e->getMessage();

            $this->logRepo->finalizarLog(
                logId: $logId,
                httpCodigo: $httpCodigo,
                resultado: SincronizacionIcalLog::RESULTADO_ERROR,
                duracionMs: $duracionTotalMs,
                recibidos: 0,
                creados: 0,
                actualizados: 0,
                cancelados: 0,
                ausentes: 0,
                conflictos: 0,
                mensaje: $msgError
            );

            $this->conexionRepo->actualizarUltimoResultado($conexionId, ConexionIcal::RESULTADO_ERROR, $msgError);

            throw $e;
        }
    } finally {
        try {
            $stmtRelease = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $stmtRelease->execute(['lock_name' => $lockName]);
        } catch (Throwable) {
            // Silencioso ante desconexión o fallo residual
        }
    }
}

    /**
     * Procesa un evento individual respetando las reglas de soberanía, conflicto y solapamiento.
     *
     * @return array{accion: string, conflicto: bool}
     */
    private function procesarEventoIndividual(
        int $conexionId,
        int $unidadId,
        EventoIcalDTO $dto,
        int $syncRunId,
        string $fechaHoteleraHoy
    ): array {
        $eventoExistente = $this->eventoRepo->buscarPorConexionYUid($conexionId, $dto->uid);

        // Caso 1: Evento con STATUS:CANCELLED
        if ($dto->estadoEvento === 'CANCELADO') {
            if ($eventoExistente !== null && $eventoExistente->obtenerEstadoEvento() !== EventoIcalExterno::ESTADO_EVENTO_CANCELADO) {
                $this->pdo->beginTransaction();
                try {
                    $this->eventoRepo->marcarEstado(
                        $eventoExistente->obtenerId(),
                        EventoIcalExterno::ESTADO_EVENTO_CANCELADO,
                        EventoIcalExterno::ESTADO_BLOQUEO_LIBERADO,
                        'Cancelado explícitamente en feed externo'
                    );

                    // Reconciliación de inventario: si otra OTA solapaba las fechas, mantener el bloqueo
                    $this->liberarOReconciliarFechas(
                        unidadId: $unidadId,
                        eventoLiberadoId: $eventoExistente->obtenerId(),
                        fechaInicio: $eventoExistente->obtenerFechaInicio(),
                        fechaFin: $eventoExistente->obtenerFechaFin(),
                        fechaHoteleraHoy: $fechaHoteleraHoy
                    );

                    $this->pdo->commit();
                    return ['accion' => 'CANCELADO', 'conflicto' => false];
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }
            }
            return ['accion' => 'IGNORADO', 'conflicto' => false];
        }

        // Caso 2: Evento ACTIVO. Verificar colisiones con reservas locales soberanas
        $fechasNoches = $this->generarSecuenciaNoches($dto->fechaInicio, $dto->fechaFin);
        $conflictoLocal = $this->detectarConflictoLocal($unidadId, $fechasNoches);

        $this->pdo->beginTransaction();
        try {
            $estadoEvento = EventoIcalExterno::ESTADO_EVENTO_ACTIVO;
            $estadoBloqueo = EventoIcalExterno::ESTADO_BLOQUEO_APLICADO;
            $detalleConflicto = null;
            $hayConflicto = false;

            if ($conflictoLocal !== null) {
                // Preservación absoluta de reservas locales (Regla 36)
                $hayConflicto = true;
                $estadoBloqueo = EventoIcalExterno::ESTADO_BLOQUEO_EN_CONFLICTO;
                $detalleConflicto = $conflictoLocal;
            }

            $eventoId = null;
            $accion = '';

            if ($eventoExistente === null) {
                // Inserción de nuevo evento
                $nuevoEvento = new EventoIcalExterno(
                    id: null,
                    conexionIcalId: $conexionId,
                    uidExterno: $dto->uid,
                    fechaInicio: $dto->fechaInicio,
                    fechaFin: $dto->fechaFin,
                    noches: $dto->noches,
                    resumen: $dto->resumen,
                    descripcion: $dto->descripcion,
                    estadoEvento: $estadoEvento,
                    estadoBloqueo: $estadoBloqueo,
                    detalleConflicto: $detalleConflicto,
                    ultimaModificacionExterna: $dto->ultimaModificacion,
                    secuenciaExterna: $dto->secuencia,
                    esRecurrente: $dto->esRecurrente,
                    recurrenciaRrule: $dto->recurrenciaRrule,
                    ultimoSyncRunId: $syncRunId
                );

                $eventoId = $this->eventoRepo->crear($nuevoEvento);
                $accion = 'CREADO';
            } else {
                // Actualización idempotente
                $eventoActualizado = new EventoIcalExterno(
                    id: $eventoExistente->obtenerId(),
                    conexionIcalId: $conexionId,
                    uidExterno: $dto->uid,
                    fechaInicio: $dto->fechaInicio,
                    fechaFin: $dto->fechaFin,
                    noches: $dto->noches,
                    resumen: $dto->resumen,
                    descripcion: $dto->descripcion,
                    estadoEvento: $estadoEvento,
                    estadoBloqueo: $estadoBloqueo,
                    detalleConflicto: $detalleConflicto,
                    ultimaModificacionExterna: $dto->ultimaModificacion,
                    secuenciaExterna: $dto->secuencia,
                    esRecurrente: $dto->esRecurrente,
                    recurrenciaRrule: $dto->recurrenciaRrule,
                    ultimoSyncRunId: $syncRunId
                );

                $this->eventoRepo->actualizar($eventoActualizado);
                $eventoId = $eventoExistente->obtenerId();
                $accion = 'ACTUALIZADO';
            }

            // Materialización en inventario_diario_unidades si NO hay conflicto local
            if (!$hayConflicto) {
                $tieneSolapamiento = $this->materializarInventario(
                    unidadId: $unidadId,
                    eventoId: $eventoId,
                    fechasNoches: $fechasNoches
                );

                if ($tieneSolapamiento) {
                    $this->eventoRepo->marcarEstado(
                        $eventoId,
                        EventoIcalExterno::ESTADO_EVENTO_ACTIVO,
                        EventoIcalExterno::ESTADO_BLOQUEO_APLICADO_CON_SOLAPAMIENTO,
                        'Fechas compartidas con otro canal externo'
                    );
                }
            }

            $this->pdo->commit();
            return ['accion' => $accion, 'conflicto' => $hayConflicto];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Concilia eventos ausentes que no aparecieron en el feed durante esta corrida.
     *
     * @return int Cantidad de eventos marcados como ausentes.
     */
    private function conciliarAusencias(
        int $conexionId,
        int $unidadId,
        int $syncRunId,
        string $fechaHoteleraHoy
    ): int {
        $candidatos = $this->eventoRepo->obtenerEventosCandidatosAusencia(
            conexionId: $conexionId,
            syncRunId: $syncRunId,
            fechaHoteleraHoy: $fechaHoteleraHoy
        );

        $ausentesCount = 0;

        foreach ($candidatos as $ausente) {
            $this->pdo->beginTransaction();
            try {
                $this->eventoRepo->marcarEstado(
                    $ausente->obtenerId(),
                    EventoIcalExterno::ESTADO_EVENTO_AUSENTE,
                    EventoIcalExterno::ESTADO_BLOQUEO_LIBERADO,
                    'Ausente en última sincronización exitosa'
                );

                // Liberar o reasignar a otra OTA solapada
                $this->liberarOReconciliarFechas(
                    unidadId: $unidadId,
                    eventoLiberadoId: $ausente->obtenerId(),
                    fechaInicio: $ausente->obtenerFechaInicio(),
                    fechaFin: $ausente->obtenerFechaFin(),
                    fechaHoteleraHoy: $fechaHoteleraHoy
                );

                $this->pdo->commit();
                $ausentesCount++;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        return $ausentesCount;
    }

    /**
     * Materializa las noches en inventario_diario_unidades respetando UNIQUE(unidad_id, fecha).
     *
     * @return bool True si hubo solapamiento con otro canal externo que ya bloqueaba la noche.
     */
    private function materializarInventario(int $unidadId, int $eventoId, array $fechasNoches): bool
    {
        $huboSolapamiento = false;

        foreach ($fechasNoches as $fecha) {
            // Inspeccionar si ya existe registro para esta unidad y fecha
            $sqlCheck = 'SELECT id, tipo_bloqueo, origen_tipo, origen_id
                         FROM inventario_diario_unidades
                         WHERE unidad_id = :unidad_id AND fecha = :fecha
                         FOR UPDATE';

            $stmtCheck = $this->pdo->prepare($sqlCheck);
            $stmtCheck->execute(['unidad_id' => $unidadId, 'fecha' => $fecha]);
            $fila = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                if ($fila['origen_tipo'] === 'EVENTO_ICAL_EXTERNO' && (int) $fila['origen_id'] === $eventoId) {
                    // Ya está asignada a este mismo evento, no hacer nada
                    continue;
                }

                // La noche ya está físicamente ocupada por otro evento o bloqueo
                $huboSolapamiento = true;
            } else {
                // Fecha libre: insertar bloqueo manual de canal externo
                $sqlInsert = 'INSERT INTO inventario_diario_unidades (
                                unidad_id, fecha, tipo_bloqueo, origen_tipo, origen_id, creado_en
                              ) VALUES (
                                :unidad_id, :fecha, :tipo_bloqueo, :origen_tipo, :origen_id, NOW()
                              )';

                $stmtInsert = $this->pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    'unidad_id' => $unidadId,
                    'fecha' => $fecha,
                    'tipo_bloqueo' => 'BLOQUEO_MANUAL',
                    'origen_tipo' => 'EVENTO_ICAL_EXTERNO',
                    'origen_id' => $eventoId,
                ]);
            }
        }

        return $huboSolapamiento;
    }

    /**
     * Libera las fechas de un evento cancelado o ausente, garantizando que si OTRO evento externo
     * (de otra OTA) también cubre la fecha, la noche se transfiera deterministamente a ese evento
     * en lugar de liberarse por error.
     */
    private function liberarOReconciliarFechas(
        int $unidadId,
        int $eventoLiberadoId,
        string $fechaInicio,
        string $fechaFin,
        string $fechaHoteleraHoy
    ): void {
        $noches = $this->generarSecuenciaNoches($fechaInicio, $fechaFin);

        foreach ($noches as $fecha) {
            // Los días del pasado nunca se tocan (preservación inmutable del histórico)
            if ($fecha < $fechaHoteleraHoy) {
                continue;
            }

            // Verificar si el registro físico actual en inventario pertenece a este evento
            $sqlFila = "SELECT id, origen_tipo, origen_id FROM inventario_diario_unidades
                        WHERE unidad_id = :u AND fecha = :f AND origen_tipo = 'EVENTO_ICAL_EXTERNO' AND origen_id = :e_id
                        FOR UPDATE";
            $stmtFila = $this->pdo->prepare($sqlFila);
            $stmtFila->execute([
                'u' => $unidadId,
                'f' => $fecha,
                'e_id' => $eventoLiberadoId,
            ]);
            $filaActual = $stmtFila->fetch(PDO::FETCH_ASSOC);

            if (!$filaActual) {
                // Si la noche no estaba asignada a este evento (ej. la tenía otra OTA), no hay nada que borrar
                continue;
            }

            // Buscar si existe OTRO evento externo activo que solape esta fecha (Reconciliación Multi-OTA)
            $otroEvento = $this->eventoRepo->buscarOtroEventoActivoEnFecha($unidadId, $fecha, $eventoLiberadoId);

            if ($otroEvento !== null) {
                // Transferir la noche al otro evento activo (UNIÓN de orígenes preservada)
                $sqlUpdate = "UPDATE inventario_diario_unidades
                              SET origen_id = :nuevo_origen_id
                              WHERE id = :inv_id";
                $stmtUpdate = $this->pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    'nuevo_origen_id' => $otroEvento->obtenerId(),
                    'inv_id' => $filaActual['id'],
                ]);

                // Actualizar el estado del otro evento a APLICADO si estaba con solapamiento
                $this->eventoRepo->marcarEstado(
                    $otroEvento->obtenerId(),
                    EventoIcalExterno::ESTADO_EVENTO_ACTIVO,
                    EventoIcalExterno::ESTADO_BLOQUEO_APLICADO,
                    null
                );
            } else {
                // Ningún otro origen bloquea la noche: se libera físicamente eliminando la fila
                $sqlDelete = "DELETE FROM inventario_diario_unidades WHERE id = :inv_id";
                $stmtDel = $this->pdo->prepare($sqlDelete);
                $stmtDel->execute(['inv_id' => $filaActual['id']]);
            }
        }
    }

    /**
     * Detecta si existe colisión con una Reserva, Estadía o Arrendamiento local.
     */
    private function detectarConflictoLocal(int $unidadId, array $fechasNoches): ?string
    {
        if (empty($fechasNoches)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($fechasNoches), '?'));
        $sql = "SELECT fecha, tipo_bloqueo, origen_tipo, origen_id
                FROM inventario_diario_unidades
                WHERE unidad_id = ?
                  AND fecha IN ($placeholders)
                  AND tipo_bloqueo IN ('RESERVA', 'ARRENDAMIENTO')
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$unidadId], $fechasNoches);
        $stmt->execute($params);
        $colision = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($colision) {
            return "Colisión con ocupación local soberana ({$colision['tipo_bloqueo']} #{$colision['origen_id']}) en fecha {$colision['fecha']}.";
        }

        return null;
    }

    /**
     * Genera la lista de fechas [inicio, fin) en formato Y-m-d.
     *
     * @return array<string>
     */
    private function generarSecuenciaNoches(string $fechaInicio, string $fechaFin): array
    {
        $dias = [];
        $cursor = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaInicio);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaFin);

        if (!$cursor || !$fin || $cursor >= $fin) {
            return [];
        }

        while ($cursor < $fin) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dias;
    }
}
