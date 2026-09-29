<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CierreHoteleroInvalidoExcepcion;
use CamargoPMS\Modelos\CierreHotelero;
use CamargoPMS\Modelos\DevengoAlojamiento;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CierreHoteleroRepositorio;
use CamargoPMS\Repositorios\DevengoAlojamientoRepositorio;
use CamargoPMS\Repositorios\EstadiaRepositorio;
use CamargoPMS\Repositorios\ReporteRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de orquestación operacional para la Auditoría Nocturna (Night Audit) y Cierre de Fecha Hotelera (D-090).
 *
 * Contratos:
 * - El cierre tiene identidad fuerte: PROPIEDAD + FECHA_HOTELERA.
 * - Estados: EN_PROCESO -> CERRADO | FALLIDO.
 * - Congela snapshots de inventario vendible (unidades totales, OOO, vendibles, ocupadas).
 * - Calcula y congela de forma soberana ADR y RevPAR para la fecha hotelera.
 */
class NightAuditServicio
{
    private CierreHoteleroRepositorio $cierreRepo;
    private DevengoServicio $devengoServicio;
    private DevengoAlojamientoRepositorio $devengoRepo;
    private EstadiaRepositorio $estadiaRepo;
    private ReporteRepositorio $reporteRepo;
    private AuditoriaServicio $auditoriaServicio;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(
        private PDO $pdo,
        ?CierreHoteleroRepositorio $cierreRepo = null,
        ?DevengoServicio $devengoServicio = null,
        ?DevengoAlojamientoRepositorio $devengoRepo = null,
        ?EstadiaRepositorio $estadiaRepo = null,
        ?ReporteRepositorio $reporteRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ActorAuditoriaRepositorio $actorRepo = null
    ) {
        $this->cierreRepo = $cierreRepo ?? new CierreHoteleroRepositorio($pdo);
        $this->devengoServicio = $devengoServicio ?? new DevengoServicio($pdo);
        $this->devengoRepo = $devengoRepo ?? new DevengoAlojamientoRepositorio($pdo);
        $this->estadiaRepo = $estadiaRepo ?? new EstadiaRepositorio($pdo);
        $this->reporteRepo = $reporteRepo ?? new ReporteRepositorio($pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($pdo);
    }

    private function resolverActorId(?int $actorOUsuarioId): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $actor = $this->actorRepo->buscarPorId($actorOUsuarioId);
            if ($actor !== null) {
                return (int) $actor->obtenerId();
            }
            $actorHumano = $this->actorRepo->buscarPorUsuarioId($actorOUsuarioId);
            if ($actorHumano !== null) {
                return (int) $actorHumano->obtenerId();
            }
        }

        $sistema = $this->actorRepo->buscarPorCodigo('CAMARGO_PMS');
        if ($sistema !== null) {
            return (int) $sistema->obtenerId();
        }

        return 1;
    }

    /**
     * Ejecuta el proceso de Auditoría Nocturna (Night Audit) para una propiedad y fecha hotelera.
     */
    public function ejecutarCierre(
        int $propiedadId,
        string $fechaHotelera,
        int $actorId,
        ?string $observaciones = null,
        string $timezone = 'America/Lima'
    ): CierreHotelero {
        $actorIdFinal = $this->resolverActorId($actorId);

        // Validar formato de fecha YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHotelera)) {
            throw new CierreHoteleroInvalidoExcepcion("Formato de fecha hotelera inválido: {$fechaHotelera}.");
        }

        $this->pdo->beginTransaction();
        try {
            // 1. Verificar si ya existe un cierre para esa propiedad y fecha
            $cierreExistente = $this->cierreRepo->buscarPorPropiedadYFecha($propiedadId, $fechaHotelera, true);

            if ($cierreExistente !== null && $cierreExistente->estaCerrado()) {
                throw new CierreHoteleroInvalidoExcepcion(
                    "La fecha hotelera {$fechaHotelera} para la propiedad ID {$propiedadId} ya se encuentra CERRADA."
                );
            }

            $ahoraUtc = gmdate('Y-m-d H:i:s');

            if ($cierreExistente === null) {
                $cierre = new CierreHotelero(
                    null,
                    $propiedadId,
                    $fechaHotelera,
                    $timezone,
                    CierreHotelero::ESTADO_EN_PROCESO,
                    0, 0, '0.00', '0.00', '0.00',
                    0, 0, 0, 0, 0, '0.00', '0.00', '0.00',
                    $ahoraUtc,
                    null,
                    $actorIdFinal,
                    $observaciones,
                    null
                );
                $cierreId = $this->cierreRepo->crear($cierre);
            } else {
                $cierreId = (int) $cierreExistente->obtenerId();
                // Reintentar sobre cierre en proceso o fallido
            }

            // Registrar inicio en auditoría
            $this->auditoriaServicio->registrar(
                'NIGHT_AUDIT_INICIADO',
                'operaciones',
                'cierres_hoteleros',
                (string) $cierreId,
                "Auditoría nocturna iniciada propiedad {$propiedadId} fecha {$fechaHotelera}",
                null,
                [
                    'propiedad_id' => $propiedadId,
                    'fecha_hotelera' => $fechaHotelera,
                    'timezone' => $timezone,
                ],
                null,
                $actorIdFinal
            );

            // 2. Obtener estadías activas en esa fecha hotelera: [fecha_entrada, fecha_salida)
            $sqlEstadias = 'SELECT e.id, e.unidad_id, e.fecha_entrada, e.fecha_salida_prevista
                            FROM estadias e
                            JOIN unidades u ON u.id = e.unidad_id
                            WHERE u.propiedad_id = :propiedad_id
                              AND e.estado = "EN_CURSO"
                              AND e.fecha_entrada <= :fecha_e1
                              AND e.fecha_salida_prevista > :fecha_e2
                            ORDER BY e.id ASC';

            $stmtEstadias = $this->pdo->prepare($sqlEstadias);
            $stmtEstadias->execute([
                'propiedad_id' => $propiedadId,
                'fecha_e1' => $fechaHotelera,
                'fecha_e2' => $fechaHotelera,
            ]);
            $estadiasActivas = $stmtEstadias->fetchAll(PDO::FETCH_ASSOC);

            // 3. Devengar la noche de cada estadía activa
            $totalEstadiasProcesadas = count($estadiasActivas);
            $totalNochesDevengadas = 0;
            $ingresoNeto = '0.00';
            $ingresoImpuestos = '0.00';
            $ingresoTotal = '0.00';
            $habitacionesVendidasIds = [];
            $habitacionesCortesiaIds = [];

            foreach ($estadiasActivas as $est) {
                $estId = (int) $est['id'];
                $uId = (int) $est['unidad_id'];

                $devengo = $this->devengoServicio->devengarNoche(
                    $estId,
                    $fechaHotelera,
                    $actorIdFinal,
                    DevengoAlojamiento::METODO_DEV_NIGHT_AUDIT,
                    $cierreId,
                    $timezone
                );

                $totalNochesDevengadas++;
                $ingresoNeto = bcadd($ingresoNeto, $devengo->obtenerImporteNeto(), 2);
                $ingresoImpuestos = bcadd($ingresoImpuestos, $devengo->obtenerImpuestoMonto(), 2);
                $ingresoTotal = bcadd($ingresoTotal, $devengo->obtenerImporteTotal(), 2);

                if ($devengo->esCortesia() || bccomp($devengo->obtenerImporteNeto(), '0.00', 2) === 0) {
                    $habitacionesCortesiaIds[$uId] = true;
                } else {
                    $habitacionesVendidasIds[$uId] = true;
                }
            }

            $habitacionesVendidas = count($habitacionesVendidasIds);
            $habitacionesCortesia = count($habitacionesCortesiaIds);

            // 4. Capturar snapshot de inventario vendible en esa fecha hotelera
            $unidadesInventario = $this->reporteRepo->obtenerUnidadesInventario($propiedadId);
            $unidadesTotales = count($unidadesInventario);

            $ordenesBloqueo = $this->reporteRepo->obtenerOrdenesBloqueantesFecha($fechaHotelera, $propiedadId);
            $unidadesInventarioIds = array_column($unidadesInventario, 'id');
            $unidadesOooIds = array_values(array_unique(array_column($ordenesBloqueo, 'unidad_id')));
            $unidadesOooValidas = array_intersect($unidadesOooIds, $unidadesInventarioIds);
            $unidadesOoo = count($unidadesOooValidas);

            $unidadesVendibles = max(0, $unidadesTotales - $unidadesOoo);
            $unidadesOcupadas = $habitacionesVendidas + $habitacionesCortesia;

            // Ocupación neta %
            $ocupacionPorcentaje = $unidadesVendibles > 0
                ? round(($unidadesOcupadas / $unidadesVendibles) * 100, 2)
                : 0.00;
            $ocupacionStr = number_format($ocupacionPorcentaje, 2, '.', '');

            // ADR: Ingreso Neto de Alojamiento / Habitaciones Vendidas
            $adr = $habitacionesVendidas > 0
                ? bcdiv($ingresoNeto, (string) $habitacionesVendidas, 2)
                : '0.00';

            // RevPAR: Ingreso Neto de Alojamiento / Habitaciones Vendibles
            $revpar = $unidadesVendibles > 0
                ? bcdiv($ingresoNeto, (string) $unidadesVendibles, 2)
                : '0.00';

            // 5. Actualizar el Cierre a CERRADO
            $cierreActualizado = new CierreHotelero(
                $cierreId,
                $propiedadId,
                $fechaHotelera,
                $timezone,
                CierreHotelero::ESTADO_CERRADO,
                $totalEstadiasProcesadas,
                $totalNochesDevengadas,
                $ingresoNeto,
                $ingresoImpuestos,
                $ingresoTotal,
                $unidadesTotales,
                $unidadesOoo,
                $unidadesVendibles,
                $habitacionesVendidas,
                $habitacionesCortesia,
                $ocupacionStr,
                $adr,
                $revpar,
                $ahoraUtc,
                gmdate('Y-m-d H:i:s'),
                $actorIdFinal,
                $observaciones,
                null
            );

            $this->cierreRepo->actualizar($cierreActualizado);

            // 6. Auditoría D-061 de Cierre Exitoso
            $this->auditoriaServicio->registrar(
                'NIGHT_AUDIT_COMPLETADO',
                'operaciones',
                'cierres_hoteleros',
                (string) $cierreId,
                "Auditoría nocturna completada propiedad {$propiedadId} fecha {$fechaHotelera}",
                null,
                [
                    'propiedad_id' => $propiedadId,
                    'fecha_hotelera' => $fechaHotelera,
                    'estadias_procesadas' => $totalEstadiasProcesadas,
                    'noches_devengadas' => $totalNochesDevengadas,
                    'ingreso_neto' => $ingresoNeto,
                    'ocupacion_porcentaje' => $ocupacionStr,
                    'adr' => $adr,
                    'revpar' => $revpar,
                ],
                null,
                $actorIdFinal
            );

            $this->pdo->commit();

            return $this->cierreRepo->buscarPorId($cierreId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Registrar fallo en cierre si se creó el registro
            if (isset($cierreId) && $cierreId > 0) {
                try {
                    $cierreFallido = new CierreHotelero(
                        $cierreId,
                        $propiedadId,
                        $fechaHotelera,
                        $timezone,
                        CierreHotelero::ESTADO_FALLIDO,
                        0, 0, '0.00', '0.00', '0.00',
                        0, 0, 0, 0, 0, '0.00', '0.00', '0.00',
                        gmdate('Y-m-d H:i:s'),
                        null,
                        $actorIdFinal,
                        $observaciones,
                        $e->getMessage()
                    );
                    $this->cierreRepo->actualizar($cierreFallido);
                } catch (Throwable) {
                    // Ignorar error de logging de fallo
                }
            }

            throw $e;
        }
    }

    public function obtenerCierrePorFecha(int $propiedadId, string $fechaHotelera): ?CierreHotelero
    {
        return $this->cierreRepo->buscarPorPropiedadYFecha($propiedadId, $fechaHotelera);
    }

    public function obtenerUltimoCierre(int $propiedadId): ?CierreHotelero
    {
        return $this->cierreRepo->obtenerUltimoCierre($propiedadId);
    }

    /**
     * @return array<CierreHotelero>
     */
    public function listarCierres(int $propiedadId, int $limite = 30): array
    {
        return $this->cierreRepo->listarPorPropiedad($propiedadId, $limite);
    }
}
