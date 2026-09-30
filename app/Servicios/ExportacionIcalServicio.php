<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Adaptadores\IcalAdaptador;
use CamargoPMS\Modelos\ConexionIcal;
use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ConexionIcalRepositorio;
use CamargoPMS\Repositorios\SincronizacionIcalLogRepositorio;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Servicio Central de Generación y Exportación de Feeds iCalendar por Conexión Destino.
 *
 * Principios vinculantes certificados:
 * 1. ANTI-ECHO LOOP OBLIGATORIO: El feed de salida conoce la conexión destino ($C_{destino}$)
 *    y SUPRIME estrictamente cualquier bloqueo cuyo origen sea la misma conexión ($conexion\_ical\_id = C_{destino}$).
 * 2. PROPAGACIÓN CRUZADA MULTICANAL: Bloqueos de OTAs distintas (ej. Booking) SÍ se exportan hacia Airbnb.
 * 3. NO FEED GLOBAL INGENUO: Cada canal recibe su propio feed específico por token.
 * 4. PRIVACIDAD TOTAL: Solo se emiten DTSTART, DTEND, SUMMARY: 'No disponible', STATUS: 'CONFIRMED'
 *    y UID determinista. Cero datos personales, montos o notas internas.
 * 5. IDENTIDAD ESTABLE DE BLOQUEO: UID determinista y reproducible con namespace @camargopms.pe.
 */
class ExportacionIcalServicio
{
    private PDO $pdo;
    private ConexionIcalRepositorio $conexionRepo;
    private SincronizacionIcalLogRepositorio $logRepo;
    private IcalAdaptador $adaptador;

    public function __construct(
        ?PDO $pdo = null,
        ?ConexionIcalRepositorio $conexionRepo = null,
        ?SincronizacionIcalLogRepositorio $logRepo = null,
        ?IcalAdaptador $adaptador = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->conexionRepo = $conexionRepo ?? new ConexionIcalRepositorio($this->pdo);
        $this->logRepo = $logRepo ?? new SincronizacionIcalLogRepositorio($this->pdo);
        $this->adaptador = $adaptador ?? new IcalAdaptador();
    }

    /**
     * Exporta el feed iCalendar (.ics) correspondiente a un token público.
     *
     * @param string $token Token de exportación opaco provisto en la URL.
     * @param string|null $ipCliente Dirección IP para telemetría.
     * @return string|null Contenido ICS con CRLF, o null si el token es inválido o inactivo.
     */
    public function exportarFeed(string $token, ?string $ipCliente = null): ?string
    {
        $hash = hash('sha256', trim($token));
        $conexion = $this->conexionRepo->buscarPorTokenHash($hash);

        if (!$conexion) {
            return null;
        }

        if (!$conexion->estaActiva() || !$conexion->exportacionHabilitada()) {
            return null;
        }

        $tiempoInicio = microtime(true);
        $conexionId = $conexion->obtenerId();
        $unidadId = $conexion->obtenerUnidadId();

        // 1. Iniciar log de exportación
        $logId = $this->logRepo->iniciarLog(
            conexionId: $conexionId,
            tipoOperacion: SincronizacionIcalLog::TIPO_EXPORTACION,
            origenEjecucion: SincronizacionIcalLog::ORIGEN_WEBHOOK,
            ipOrigen: $ipCliente
        );

        try {
            // 2. Obtener fechas de ocupación física en inventario_diario_unidades
            $tzNombre = (string) Configuracion::obtener('APP_TIMEZONE', 'America/Lima');
            $hoy = new DateTimeImmutable('now', new DateTimeZone($tzNombre));
            // Exportar desde 30 días atrás para que las OTAs reconcilien el pasado reciente
            $fechaDesde = $hoy->modify('-30 days')->format('Y-m-d');

            $sql = 'SELECT inv.fecha, inv.tipo_bloqueo, inv.origen_tipo, inv.origen_id,
                           e.conexion_ical_id AS evento_conexion_id
                    FROM inventario_diario_unidades inv
                    LEFT JOIN eventos_ical_externos e
                           ON inv.origen_tipo = \'EVENTO_ICAL_EXTERNO\' AND inv.origen_id = e.id
                    WHERE inv.unidad_id = :unidad_id
                      AND inv.fecha >= :fecha_desde
                    ORDER BY inv.fecha ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'unidad_id' => $unidadId,
                'fecha_desde' => $fechaDesde,
            ]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Aplicar Filtro Anti-Echo y recolectar fechas exportables
            $fechasExportables = [];
            foreach ($filas as $fila) {
                if ($fila['origen_tipo'] === 'EVENTO_ICAL_EXTERNO') {
                    $origenConexionId = isset($fila['evento_conexion_id']) ? (int) $fila['evento_conexion_id'] : null;

                    // FILTRO ANTI-ECHO: Si el bloqueo proviene de ESTA MISMA conexión, suprimirlo
                    if ($origenConexionId === $conexionId) {
                        continue;
                    }
                }

                // Bloqueos locales (RESERVA, ARRENDAMIENTO, MANTENIMIENTO, BLOQUEO_MANUAL)
                // y bloqueos de OTRAS conexiones externas sí se exportan
                $fechasExportables[] = (string) $fila['fecha'];
            }

            // 4. Agrupar fechas contiguas en bloques hoteleros [inicio, fin)
            $bloques = $this->agruparFechasEnBloques($unidadId, $fechasExportables);

            // 5. Serializar con Sabre vía IcalAdaptador garantizando privacidad total
            $nombreCalendario = 'Camargo PMS - ' . $conexion->obtenerNombre();
            $icsContenido = $this->adaptador->generarCalendario($bloques, $nombreCalendario);

            $duracionMs = (int) round((microtime(true) - $tiempoInicio) * 1000);

            $this->logRepo->finalizarLog(
                logId: $logId,
                httpCodigo: 200,
                resultado: SincronizacionIcalLog::RESULTADO_EXITO,
                duracionMs: $duracionMs,
                recibidos: 0,
                creados: 0,
                actualizados: 0,
                cancelados: 0,
                ausentes: 0,
                conflictos: 0,
                mensaje: "Feed exportado exitosamente: " . count($bloques) . " bloques VEVENT entregados."
            );

            return $icsContenido;

        } catch (\Throwable $e) {
            $duracionMs = (int) round((microtime(true) - $tiempoInicio) * 1000);
            $this->logRepo->finalizarLog(
                logId: $logId,
                httpCodigo: 500,
                resultado: SincronizacionIcalLog::RESULTADO_ERROR,
                duracionMs: $duracionMs,
                recibidos: 0,
                creados: 0,
                actualizados: 0,
                cancelados: 0,
                ausentes: 0,
                conflictos: 0,
                mensaje: "Error exportando feed: " . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Agrupa una lista ordenada de fechas discretas (noches) en intervalos continuos [inicio, fin).
     *
     * @param int $unidadId
     * @param array<string> $fechas
     * @return array<array{uid: string, fecha_inicio: string, fecha_fin: string}>
     */
    private function agruparFechasEnBloques(int $unidadId, array $fechas): array
    {
        if (empty($fechas)) {
            return [];
        }

        // Eliminar duplicados y ordenar cronológicamente
        $fechas = array_values(array_unique($fechas));
        sort($fechas);

        $bloques = [];
        $inicioActual = null;
        $esperadoSiguiente = null;

        foreach ($fechas as $fechaStr) {
            $dtFecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaStr);
            if (!$dtFecha) {
                continue;
            }

            if ($inicioActual === null) {
                $inicioActual = $dtFecha;
                $esperadoSiguiente = $dtFecha->modify('+1 day');
            } elseif ($dtFecha == $esperadoSiguiente) {
                // Consecutivo continuo
                $esperadoSiguiente = $dtFecha->modify('+1 day');
            } else {
                // Ruptura de continuidad: cerrar el bloque anterior
                $inicioStr = $inicioActual->format('Y-m-d');
                $finStr = $esperadoSiguiente->format('Y-m-d');
                $bloques[] = [
                    'uid' => "bloq-u{$unidadId}-{$inicioStr}-{$finStr}@camargopms.pe",
                    'fecha_inicio' => $inicioStr,
                    'fecha_fin' => $finStr,
                ];

                $inicioActual = $dtFecha;
                $esperadoSiguiente = $dtFecha->modify('+1 day');
            }
        }

        if ($inicioActual !== null && $esperadoSiguiente !== null) {
            $inicioStr = $inicioActual->format('Y-m-d');
            $finStr = $esperadoSiguiente->format('Y-m-d');
            $bloques[] = [
                'uid' => "bloq-u{$unidadId}-{$inicioStr}-{$finStr}@camargopms.pe",
                'fecha_inicio' => $inicioStr,
                'fecha_fin' => $finStr,
            ];
        }

        return $bloques;
    }
}
