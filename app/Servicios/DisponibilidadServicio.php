<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\BloqueoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Modelos\BloqueoUnidad;
use CamargoPMS\Modelos\InventarioDiario;
use CamargoPMS\Modelos\Unidad;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\DisponibilidadRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio Central de Disponibilidad e Inventario Diario de Camargo PMS (DISPONIBILIDAD-1).
 *
 * Principios vinculantes:
 * - D-066: INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL. Intervalo semiabierto [entrada, salida).
 *          Timezone IANA de propiedad o default PMS ('operacion.zona_horaria_predeterminada').
 * - D-067: Modelo Híbrido Sparse con UNIQUE(unidad_id, fecha).
 *          Orden determinista ORDER BY unidad_id ASC, fecha ASC.
 *          Rollback integral ante colisión 1062 o conflictos de concurrencia 1205/1213.
 *          Liberación atómica de noches sin residuos lógicos.
 *          Camargo PMS como ÚNICA FUENTE CENTRAL DE VERDAD AUTORITATIVA para todos los canales.
 * - P-005: Cero tarifas, precios, monedas, impuestos o redondeo en esta fase.
 * - UNIDAD ≠ DISPONIBILIDAD ≠ RESERVA ≠ TARIFA.
 */
class DisponibilidadServicio
{
    private DisponibilidadRepositorio $disponibilidadRepo;
    private UnidadRepositorio $unidadRepo;
    private PropiedadRepositorio $propiedadRepo;
    private ConfiguracionServicio $configServicio;
    private AuditoriaServicio $auditoriaServicio;
    private PDO $pdo;

    public function __construct(
        ?PDO $pdo = null,
        ?DisponibilidadRepositorio $disponibilidadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?ConfiguracionServicio $configServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->disponibilidadRepo = $disponibilidadRepo ?? new DisponibilidadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->configServicio = $configServicio ?? new ConfiguracionServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Resuelve la zona horaria IANA efectiva para una propiedad o el PMS (D-066).
     * Si la propiedad tiene zona_horaria asignada se usa esa; de lo contrario se hereda
     * el parámetro central 'operacion.zona_horaria_predeterminada' (fallback: 'America/Lima').
     *
     * @param int|null $propiedadId
     * @return string Identificador IANA válido
     */
    public function resolverZonaHorariaPropiedad(?int $propiedadId = null): string
    {
        $zonaCandidata = null;

        if ($propiedadId !== null && $propiedadId > 0) {
            $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
            if ($propiedad !== null && $propiedad->obtenerZonaHoraria() !== null) {
                $zonaCandidata = $propiedad->obtenerZonaHoraria();
            }
        }

        if ($zonaCandidata === null || trim($zonaCandidata) === '') {
            $zonaCandidata = (string) $this->configServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
        }

        if (!in_array($zonaCandidata, DateTimeZone::listIdentifiers(), true)) {
            $zonaCandidata = 'America/Lima';
        }

        return $zonaCandidata;
    }

    /**
     * Valida un intervalo de fechas hoteleras y calcula la secuencia de noches (D-066).
     *
     * Reglas vinculantes:
     * - Formato estricto Y-m-d.
     * - Fecha real válida en calendario gregoriano.
     * - Intervalo semiabierto [fecha_entrada, fecha_salida).
     * - Restricción de dominio: fecha_salida > fecha_entrada (noches >= 1).
     *
     * @param string $fechaEntrada Formato Y-m-d
     * @param string $fechaSalida Formato Y-m-d
     * @return array{fecha_entrada: string, fecha_salida: string, noches: int, dias: array<string>}
     * @throws IntervaloInvalidoExcepcion
     */
    public function validarIntervaloHotelero(string $fechaEntrada, string $fechaSalida): array
    {
        $fechaEntrada = trim($fechaEntrada);
        $fechaSalida = trim($fechaSalida);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrada) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSalida)) {
            throw new IntervaloInvalidoExcepcion($fechaEntrada, $fechaSalida, 'El formato de las fechas debe ser YYYY-MM-DD.');
        }

        [$aE, $mE, $dE] = explode('-', $fechaEntrada);
        if (!checkdate((int) $mE, (int) $dE, (int) $aE)) {
            throw new IntervaloInvalidoExcepcion($fechaEntrada, $fechaSalida, "La fecha de entrada '{$fechaEntrada}' no es una fecha válida en el calendario.");
        }

        [$aS, $mS, $dS] = explode('-', $fechaSalida);
        if (!checkdate((int) $mS, (int) $dS, (int) $aS)) {
            throw new IntervaloInvalidoExcepcion($fechaEntrada, $fechaSalida, "La fecha de salida '{$fechaSalida}' no es una fecha válida en el calendario.");
        }

        $dtEntrada = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaEntrada);
        $dtSalida = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaSalida);

        if ($dtSalida <= $dtEntrada) {
            throw new IntervaloInvalidoExcepcion(
                $fechaEntrada,
                $fechaSalida,
                "La fecha de salida ({$fechaSalida}) debe ser estrictamente posterior a la fecha de entrada ({$fechaEntrada}). Estancia mínima: 1 noche."
            );
        }

        $dias = [];
        $cursor = $dtEntrada;
        while ($cursor < $dtSalida) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $noches = count($dias);

        return [
            'fecha_entrada' => $fechaEntrada,
            'fecha_salida' => $fechaSalida,
            'noches' => $noches,
            'dias' => $dias,
        ];
    }

    /**
     * Consulta la disponibilidad física e inventario para un intervalo hotelero (D-066 / D-067).
     *
     * @param string $fechaEntrada
     * @param string $fechaSalida
     * @param int|null $propiedadId
     * @param int|null $tipoUnidadId
     * @param bool $soloDisponibles
     * @return array<string, mixed>
     * @throws IntervaloInvalidoExcepcion
     */
    public function consultarDisponibilidad(
        string $fechaEntrada,
        string $fechaSalida,
        ?int $propiedadId = null,
        ?int $tipoUnidadId = null,
        bool $soloDisponibles = false
    ): array {
        $intervalo = $this->validarIntervaloHotelero($fechaEntrada, $fechaSalida);

        // Obtener unidades candidatas
        $filtrosUnidades = ['estado' => 'ACTIVO'];
        if ($propiedadId !== null && $propiedadId > 0) {
            $filtrosUnidades['propiedad_id'] = $propiedadId;
        }
        if ($tipoUnidadId !== null && $tipoUnidadId > 0) {
            $filtrosUnidades['tipo_unidad_id'] = $tipoUnidadId;
        }

        $todasUnidades = $this->unidadRepo->listar($filtrosUnidades, 1, 1000);
        $unidadesIds = array_map(static fn(Unidad $u) => $u->obtenerId(), $todasUnidades);

        // Obtener mapa de ocupación en inventario diario sparse
        $mapaOcupacion = $this->disponibilidadRepo->obtenerNochesOcupadasPorUnidades(
            $unidadesIds,
            $intervalo['fecha_entrada'],
            $intervalo['fechaSalida'] ?? $intervalo['fecha_salida']
        );

        $resultadoUnidades = [];
        $disponiblesCount = 0;

        foreach ($todasUnidades as $unidad) {
            $uId = $unidad->obtenerId();
            $nochesOcupadas = $mapaOcupacion[$uId] ?? [];
            $ocupadasFechas = array_keys($nochesOcupadas);

            // Una unidad activa en una propiedad inactiva no es disponible
            $propiedadActiva = $unidad->obtenerPropiedadEstado() === 'ACTIVO';
            $estaDisponible = empty($ocupadasFechas) && $propiedadActiva;

            if ($estaDisponible) {
                $disponiblesCount++;
            } elseif ($soloDisponibles) {
                continue;
            }

            $motivoNoDisponible = null;
            if (!$propiedadActiva) {
                $motivoNoDisponible = 'Propiedad inactiva';
            } elseif (!empty($ocupadasFechas)) {
                $primerBloqueo = reset($nochesOcupadas);
                $tipoB = $primerBloqueo['tipo_bloqueo'] ?? 'BLOQUEO_MANUAL';
                $motivoNoDisponible = $tipoB === 'MANTENIMIENTO' ? 'En mantenimiento técnico' : 'Ocupada o bloqueada';
            }

            $resultadoUnidades[] = [
                'id' => $unidad->obtenerId(),
                'codigo' => $unidad->obtenerCodigo(),
                'nombre' => $unidad->obtenerNombre(),
                'propiedad_id' => $unidad->obtenerPropiedadId(),
                'propiedad_nombre' => $unidad->obtenerPropiedadNombre(),
                'propiedad_codigo' => $unidad->obtenerPropiedadCodigo(),
                'tipo_unidad_id' => $unidad->obtenerTipoUnidadId(),
                'tipo_unidad_nombre' => $unidad->obtenerTipoUnidadNombre(),
                'capacidad_personas' => $unidad->obtenerCapacidadPersonas(),
                'dormitorios' => $unidad->obtenerDormitorios(),
                'banos' => $unidad->obtenerBanos(),
                'disponible' => $estaDisponible,
                'motivo_no_disponible' => $motivoNoDisponible,
                'noches_ocupadas' => $ocupadasFechas,
                'noches_solicitadas' => $intervalo['noches'],
            ];
        }

        return [
            'intervalo' => [
                'fecha_entrada' => $intervalo['fecha_entrada'],
                'fecha_salida' => $intervalo['fecha_salida'],
                'noches' => $intervalo['noches'],
                'dias' => $intervalo['dias'],
            ],
            'unidades_totales' => count($todasUnidades),
            'unidades_disponibles' => $disponiblesCount,
            'unidades_bloqueadas' => count($todasUnidades) - $disponiblesCount,
            'tasa_disponibilidad' => count($todasUnidades) > 0 ? round(($disponiblesCount / count($todasUnidades)) * 100, 1) : 0,
            'unidades' => $resultadoUnidades,
        ];
    }

    /**
     * Aplica un bloqueo de inventario sobre una unidad física (DISPONIBILIDAD-1 / D-067).
     *
     * Garantías transaccionales D-067:
     * - Validación de existencia de unidad y propiedad activa.
     * - Intervalo semiabierto [fecha_inicio, fecha_fin).
     * - Orden determinista ORDER BY unidad_id ASC, fecha ASC en inserción de inventario.
     * - Restricción UNIQUE(unidad_id, fecha) en InnoDB como última línea inviolable.
     * - Captura de PDOException (1062, 1205, 1213) con ROLLBACK total y emisión de ConflictoDisponibilidadExcepcion (409).
     * - Registro de auditoría transversal (D-061).
     *
     * @param int $unidadId
     * @param string $fechaInicio Inclusive
     * @param string $fechaFin Exclusive
     * @param string $motivo Razón administrativa o técnica
     * @param string $tipo 'BLOQUEO_MANUAL' o 'MANTENIMIENTO'
     * @param int|null $ejecutadoPorUsuarioId
     * @return BloqueoUnidad
     * @throws UnidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     * @throws IntervaloInvalidoExcepcion
     * @throws ConflictoDisponibilidadExcepcion
     */
    public function bloquearUnidad(
        int $unidadId,
        string $fechaInicio,
        string $fechaFin,
        string $motivo,
        string $tipo = 'BLOQUEO_MANUAL',
        ?int $ejecutadoPorUsuarioId = null
    ): BloqueoUnidad {
        // 1. Validar unidad
        $unidad = $this->unidadRepo->buscarPorId($unidadId);
        if ($unidad === null) {
            throw new UnidadNoEncontradaExcepcion("No se encontró la unidad especificada (ID: {$unidadId}).");
        }
        if (!$unidad->estaActiva()) {
            throw new ValidacionExcepcion("La unidad '{$unidad->obtenerCodigo()}' se encuentra inactiva y no admite operaciones de bloqueo.");
        }

        // 2. Validar propiedad
        $propiedad = $this->propiedadRepo->buscarPorId($unidad->obtenerPropiedadId());
        if ($propiedad === null || !$propiedad->estaActiva()) {
            throw new ValidacionExcepcion("La propiedad asociada a la unidad se encuentra inactiva.");
        }

        // 3. Validar intervalo hotelero
        $intervalo = $this->validarIntervaloHotelero($fechaInicio, $fechaFin);

        // 4. Validar motivo y tipo
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new ValidacionExcepcion('El motivo del bloqueo es obligatorio.');
        }
        if (mb_strlen($motivo) > 255) {
            throw new ValidacionExcepcion('El motivo no puede exceder los 255 caracteres.');
        }

        $tipo = strtoupper(trim($tipo));
        if (!in_array($tipo, ['BLOQUEO_MANUAL', 'MANTENIMIENTO'], true)) {
            $tipo = 'BLOQUEO_MANUAL';
        }

        // 5. Resolver actor humano ejecutor (D-061)
        $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
        $actorId = $actor?->obtenerId();

        // 6. Transacción ACID relacional
        $this->pdo->beginTransaction();
        try {
            // A. Verificar colisiones previas en rango (fail-fast)
            $nochesOcupadas = $this->disponibilidadRepo->obtenerNochesOcupadas($unidadId, $intervalo['fecha_entrada'], $intervalo['fecha_salida']);
            if (!empty($nochesOcupadas)) {
                $primeraFecha = $nochesOcupadas[0]['fecha'];
                throw new ConflictoDisponibilidadExcepcion(
                    $unidadId,
                    $primeraFecha,
                    "La unidad '{$unidad->obtenerCodigo()}' ya cuenta con ocupación o bloqueo para la fecha {$primeraFecha}."
                );
            }

            // B. Crear registro maestro de bloqueo
            $nuevoBloqueo = new BloqueoUnidad(
                null,
                $unidadId,
                $intervalo['fecha_entrada'],
                $intervalo['fecha_salida'],
                $intervalo['noches'],
                $motivo,
                $tipo,
                'ACTIVO',
                $actorId
            );
            $bloqueoPersistido = $this->disponibilidadRepo->crearBloqueo($nuevoBloqueo);
            $bloqueoId = $bloqueoPersistido->obtenerId();

            // C. Insertar noches en orden determinista ORDER BY unidad_id ASC, fecha ASC
            // Como es una sola unidad, el orden de fechas es estrictamente ascendente
            $diasOrdenados = $intervalo['dias'];
            sort($diasOrdenados, SORT_STRING);

            foreach ($diasOrdenados as $fechaNoche) {
                $this->disponibilidadRepo->insertarInventarioNoche(
                    $unidadId,
                    $fechaNoche,
                    $tipo,
                    'BLOQUEO_MANUAL',
                    $bloqueoId
                );
            }

            // D. Registrar auditoría transversal
            $this->auditoriaServicio->registrar(
                accion: 'REGISTRAR',
                modulo: 'disponibilidad',
                entidad: 'bloqueos_unidad',
                entidadId: (string) $bloqueoId,
                descripcion: "Bloqueo {$tipo} creado para unidad '{$unidad->obtenerCodigo()}' del {$intervalo['fecha_entrada']} al {$intervalo['fecha_salida']} ({$intervalo['noches']} noches). Motivo: {$motivo}",
                valoresAnteriores: null,
                valoresNuevos: [
                    'unidad_id' => $unidadId,
                    'unidad_codigo' => $unidad->obtenerCodigo(),
                    'fecha_inicio' => $intervalo['fecha_entrada'],
                    'fecha_fin' => $intervalo['fecha_salida'],
                    'noches' => $intervalo['noches'],
                    'motivo' => $motivo,
                    'tipo' => $tipo,
                ],
                contexto: null,
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $bloqueoPersistido;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Captura de error 1062 (Duplicate entry - UNIQUE violación)
            $errorCode = (string) $e->getCode();
            $errorInfo = $e->errorInfo[1] ?? 0;

            if ($errorCode === '23000' || $errorInfo === 1062) {
                throw new ConflictoDisponibilidadExcepcion(
                    $unidadId,
                    null,
                    "Conflicto de disponibilidad: una o más noches en el intervalo solicitado ya fueron reservadas o bloqueadas simultáneamente.",
                    409,
                    $e
                );
            }

            // Captura de lock wait timeout (1205) o deadlock (1213)
            if ($errorInfo === 1205 || $errorInfo === 1213) {
                throw new ConflictoDisponibilidadExcepcion(
                    $unidadId,
                    null,
                    "Conflicto temporal de concurrencia en la base de datos (timeout/deadlock). Por favor reintente la operación.",
                    409,
                    $e
                );
            }

            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Libera un bloqueo de unidad previamente aplicado (D-067).
     * Elimina atómicamente sus noches del inventario diario y actualiza el estado a LIBERADO.
     *
     * @param int $bloqueoId
     * @param int|null $ejecutadoPorUsuarioId
     * @return BloqueoUnidad
     * @throws BloqueoNoEncontradoExcepcion
     * @throws ValidacionExcepcion
     */
    public function liberarBloqueo(int $bloqueoId, ?int $ejecutadoPorUsuarioId = null): BloqueoUnidad
    {
        $bloqueo = $this->disponibilidadRepo->buscarBloqueoPorId($bloqueoId);
        if ($bloqueo === null) {
            throw new BloqueoNoEncontradoExcepcion($bloqueoId);
        }

        if (!$bloqueo->estaActivo()) {
            throw new ValidacionExcepcion("El bloqueo ID {$bloqueoId} ya se encuentra liberado.");
        }

        $actor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
        $actorId = $actor?->obtenerId();

        $this->pdo->beginTransaction();
        try {
            // A. Actualizar estado del registro maestro
            $this->disponibilidadRepo->actualizarEstadoBloqueo($bloqueoId, 'LIBERADO', $actorId);

            // B. Liberación atómica de noches en inventario diario sparse
            $nochesLiberadas = $this->disponibilidadRepo->eliminarInventarioPorOrigen('BLOQUEO_MANUAL', $bloqueoId);

            // C. Registrar auditoría transversal
            $this->auditoriaServicio->registrar(
                accion: 'LIBERAR',
                modulo: 'disponibilidad',
                entidad: 'bloqueos_unidad',
                entidadId: (string) $bloqueoId,
                descripcion: "Bloqueo ID {$bloqueoId} liberado. Se restablecieron {$nochesLiberadas} noches de inventario.",
                valoresAnteriores: ['estado' => 'ACTIVO'],
                valoresNuevos: ['estado' => 'LIBERADO', 'noches_liberadas' => $nochesLiberadas],
                contexto: null,
                actor: $actor,
                usuarioId: $ejecutadoPorUsuarioId,
                correlacionId: null,
                pdoTransaccional: $this->pdo
            );

            $this->pdo->commit();

            return $this->disponibilidadRepo->buscarBloqueoPorId($bloqueoId) ?? $bloqueo;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lista bloqueos de unidades con filtros y paginación.
     *
     * @param array<string, mixed> $filtros
     * @param int $pagina
     * @param int $limite
     * @return array{bloqueos: array<int, BloqueoUnidad>, total: int, pagina: int, limite: int, paginas_totales: int}
     */
    public function listarBloqueos(array $filtros = [], int $pagina = 1, int $limite = 20): array
    {
        $pagina = max(1, $pagina);
        $limite = max(1, min(100, $limite));
        $offset = ($pagina - 1) * $limite;

        $total = $this->disponibilidadRepo->contarBloqueos($filtros);
        $bloqueos = $this->disponibilidadRepo->listarBloqueos($filtros, $limite, $offset);

        return [
            'bloqueos' => $bloqueos,
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'paginas_totales' => $total > 0 ? (int) ceil($total / $limite) : 1,
        ];
    }

    /**
     * Obtiene un bloqueo de unidad por su identificador primario.
     *
     * @param int $id
     * @return BloqueoUnidad
     * @throws BloqueoNoEncontradoExcepcion
     */
    public function obtenerBloqueoPorId(int $id): BloqueoUnidad
    {
        $bloqueo = $this->disponibilidadRepo->buscarBloqueoPorId($id);
        if ($bloqueo === null) {
            throw new BloqueoNoEncontradoExcepcion($id);
        }

        return $bloqueo;
    }

    /**
     * Genera la matriz mensual de ocupación (rack de disponibilidad) para una propiedad (DISPONIBILIDAD-1).
     *
     * @param int $propiedadId
     * @param string $mes Formato YYYY-MM (ej. '2026-10')
     * @return array<string, mixed>
     * @throws ValidacionExcepcion
     */
    public function obtenerMatrizCalendario(int $propiedadId, string $mes): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = date('Y-m');
        }

        $propiedad = $this->propiedadRepo->buscarPorId($propiedadId);
        if ($propiedad === null) {
            throw new ValidacionExcepcion("No se encontró la propiedad con ID {$propiedadId}.");
        }

        [$anio, $mesNum] = explode('-', $mes);
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, (int) $mesNum, (int) $anio);

        $fechaInicio = "{$mes}-01";
        // fechaFin exclusiva: primer día del mes siguiente
        $dtInicio = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaInicio);
        $dtFin = $dtInicio->modify('+1 month');
        $fechaFin = $dtFin->format('Y-m-d');

        // Obtener unidades de la propiedad
        $unidades = $this->unidadRepo->listar(['propiedad_id' => $propiedadId, 'estado' => 'ACTIVO'], 1, 500);

        // Obtener inventario del rango
        $filasInventario = $this->disponibilidadRepo->obtenerInventarioPorPropiedadYRango($propiedadId, $fechaInicio, $fechaFin);

        // Mapear por [unidad_id][fecha] => fila
        $mapaOcupacion = [];
        foreach ($filasInventario as $inv) {
            $uId = (int) $inv['unidad_id'];
            $f = (string) $inv['fecha'];
            $mapaOcupacion[$uId][$f] = $inv;
        }

        // Construir columnas de días
        $columnasDias = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $diaStr = sprintf('%04d-%02d-%02d', (int) $anio, (int) $mesNum, $d);
            $dtDia = DateTimeImmutable::createFromFormat('!Y-m-d', $diaStr);
            $columnasDias[] = [
                'dia' => $d,
                'fecha' => $diaStr,
                'nombre_dia' => $dtDia ? $dtDia->format('D') : '',
                'es_fin_de_semana' => $dtDia && in_array($dtDia->format('N'), ['6', '7'], true),
            ];
        }

        // Construir filas por unidad
        $filasUnidades = [];
        $totalCeldas = count($unidades) * $diasEnMes;
        $celdasOcupadas = 0;

        foreach ($unidades as $u) {
            $uId = $u->obtenerId();
            $diasEstado = [];

            foreach ($columnasDias as $col) {
                $f = $col['fecha'];
                $ocupado = isset($mapaOcupacion[$uId][$f]);
                if ($ocupado) {
                    $celdasOcupadas++;
                    $inv = $mapaOcupacion[$uId][$f];
                    $diasEstado[$f] = [
                        'estado' => 'OCUPADO',
                        'tipo' => $inv['tipo_bloqueo'],
                        'origen_tipo' => $inv['origen_tipo'],
                        'origen_id' => $inv['origen_id'],
                    ];
                } else {
                    $diasEstado[$f] = [
                        'estado' => 'DISPONIBLE',
                        'tipo' => null,
                        'origen_tipo' => null,
                        'origen_id' => null,
                    ];
                }
            }

            $filasUnidades[] = [
                'unidad_id' => $uId,
                'codigo' => $u->obtenerCodigo(),
                'nombre' => $u->obtenerNombre(),
                'tipo_unidad' => $u->obtenerTipoUnidadNombre(),
                'dias' => $diasEstado,
            ];
        }

        $tasaOcupacion = $totalCeldas > 0 ? round(($celdasOcupadas / $totalCeldas) * 100, 1) : 0;

        return [
            'propiedad' => [
                'id' => $propiedad->obtenerId(),
                'codigo' => $propiedad->obtenerCodigo(),
                'nombre' => $propiedad->obtenerNombre(),
                'zona_horaria' => $this->resolverZonaHorariaPropiedad($propiedadId),
            ],
            'mes' => $mes,
            'dias_en_mes' => $diasEnMes,
            'columnas_dias' => $columnasDias,
            'unidades' => $filasUnidades,
            'estadisticas' => [
                'total_unidades' => count($unidades),
                'total_noches_posibles' => $totalCeldas,
                'total_noches_ocupadas' => $celdasOcupadas,
                'tasa_ocupacion' => $tasaOcupacion,
            ],
        ];
    }

    /**
     * Resuelve semánticamente el ActorAuditoria ejecutor garantizando el cumplimiento de D-061.
     *
     * @param int|null $usuarioId
     * @return ActorAuditoria|null
     */
    private function resolverActorEjecutor(?int $usuarioId): ?ActorAuditoria
    {
        if ($usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        return $this->auditoriaServicio->obtenerOAsegurarActorUsuario($usuarioId, $this->pdo);
    }
}
