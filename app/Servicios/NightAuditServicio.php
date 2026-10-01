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
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReporteRepositorio;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Servicio de orquestación operacional para la Auditoría Nocturna (Night Audit) y Cierre de Fecha Hotelera (D-090, D-112).
 *
 * Contratos:
 * - El cierre tiene identidad fuerte: PROPIEDAD + FECHA_HOTELERA.
 * - Estados: EN_PROCESO -> CERRADO | FALLIDO.
 * - Congela snapshots de inventario vendible (unidades totales, OOO, vendibles, ocupadas).
 * - Calcula y congela de forma soberana ADR y RevPAR para la fecha hotelera.
 * - Concurrencia distribuida vía advisory locks MySQL (GET_LOCK / RELEASE_LOCK).
 * - Procesamiento secuencial cronológico para fechas pendientes.
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
    private PropiedadRepositorio $propiedadRepo;
    private ConfiguracionServicio $configServicio;

    public function __construct(
        private PDO $pdo,
        ?CierreHoteleroRepositorio $cierreRepo = null,
        ?DevengoServicio $devengoServicio = null,
        ?DevengoAlojamientoRepositorio $devengoRepo = null,
        ?EstadiaRepositorio $estadiaRepo = null,
        ?ReporteRepositorio $reporteRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?ActorAuditoriaRepositorio $actorRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?ConfiguracionServicio $configServicio = null
    ) {
        $this->cierreRepo = $cierreRepo ?? new CierreHoteleroRepositorio($pdo);
        $this->devengoServicio = $devengoServicio ?? new DevengoServicio($pdo);
        $this->devengoRepo = $devengoRepo ?? new DevengoAlojamientoRepositorio($pdo);
        $this->estadiaRepo = $estadiaRepo ?? new EstadiaRepositorio($pdo);
        $this->reporteRepo = $reporteRepo ?? new ReporteRepositorio($pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($pdo);
        $this->actorRepo = $actorRepo ?? new ActorAuditoriaRepositorio($pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($pdo);
        $this->configServicio = $configServicio ?? new ConfiguracionServicio($pdo);
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
     * Resuelve la zona horaria IANA efectiva para una propiedad o el PMS (D-066).
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
     * Adquiere un bloqueo pesimista a nivel de motor de base de datos para la propiedad.
     * Impide colisiones concurrentes entre ejecuciones CLI y peticiones Web (D-112).
     */
    public function adquirirBloqueoPropiedad(int $propiedadId): bool
    {
        $nombreBloqueo = 'camargo_pms_night_audit_prop_' . $propiedadId;
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:nombre, 0)');
        $stmt->execute(['nombre' => $nombreBloqueo]);
        $resultado = $stmt->fetchColumn();

        return $resultado === 1 || $resultado === '1';
    }

    /**
     * Libera el bloqueo pesimista a nivel de base de datos para la propiedad (D-112).
     */
    public function liberarBloqueoPropiedad(int $propiedadId): bool
    {
        $nombreBloqueo = 'camargo_pms_night_audit_prop_' . $propiedadId;
        $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
        $stmt->execute(['nombre' => $nombreBloqueo]);
        $resultado = $stmt->fetchColumn();

        return $resultado === 1 || $resultado === '1';
    }

    /**
     * Detecta reservas confirmadas para la propiedad en la fecha hotelera sin estadías en curso o finalizadas (no-shows potenciales).
     * CERO mutación de estado en reservas. CERO cancelación automática (D-112).
     *
     * @param int $propiedadId
     * @param string $fechaHotelera YYYY-MM-DD
     * @return array<array{id: int, codigo: string, fecha_entrada: string, fecha_salida: string}>
     */
    public function detectarNoShowsPotenciales(int $propiedadId, string $fechaHotelera): array
    {
        $sql = 'SELECT DISTINCT r.id, r.codigo, r.fecha_entrada, r.fecha_salida
                FROM reservas r
                JOIN reserva_unidades ru ON ru.reserva_id = r.id
                JOIN unidades u ON u.id = ru.unidad_id
                LEFT JOIN estadias e ON e.reserva_unidad_id = ru.id AND e.estado IN ("EN_CURSO", "FINALIZADA")
                WHERE u.propiedad_id = :propiedad_id
                  AND r.estado = "CONFIRMADA"
                  AND r.fecha_entrada <= :fecha1
                  AND r.fecha_salida > :fecha2
                  AND e.id IS NULL
                ORDER BY r.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'fecha1' => $fechaHotelera,
            'fecha2' => $fechaHotelera,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida que una fecha hotelera sea elegible para cierre.
     * Solo pueden cerrarse fechas estrictamente anteriores a la fecha local en curso (ayer o anterior).
     * El día en curso nunca se cierra automáticamente ni de forma prematura (D-112).
     *
     * @param int $propiedadId
     * @param string $fechaHotelera YYYY-MM-DD
     * @throws CierreHoteleroInvalidoExcepcion
     */
    public function validarFechaCerrable(int $propiedadId, string $fechaHotelera): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHotelera)) {
            throw new CierreHoteleroInvalidoExcepcion("Formato de fecha hotelera inválido: {$fechaHotelera}. Formato requerido: YYYY-MM-DD.");
        }

        $tz = $this->resolverZonaHorariaPropiedad($propiedadId);
        $ahoraLocal = new DateTimeImmutable('now', new DateTimeZone($tz));
        $fechaHoyLocal = $ahoraLocal->format('Y-m-d');

        if ($fechaHotelera >= $fechaHoyLocal) {
            throw new CierreHoteleroInvalidoExcepcion(
                "No se puede cerrar la fecha hotelera {$fechaHotelera} porque es igual o posterior a la fecha local en curso ({$fechaHoyLocal}) de la propiedad ID {$propiedadId} (Zona: {$tz})."
            );
        }
    }

    /**
     * Calcula secuencialmente la lista de fechas hoteleras pendientes de cierre para una propiedad.
     * Rango: [último_cierre + 1 día ó primera estadía, ayer_local].
     *
     * @param int $propiedadId
     * @return array<string> Lista cronológica de fechas pendientes YYYY-MM-DD
     */
    public function obtenerFechasDebidasPropiedad(int $propiedadId): array
    {
        $tz = $this->resolverZonaHorariaPropiedad($propiedadId);
        $ahoraLocal = new DateTimeImmutable('now', new DateTimeZone($tz));
        $ayerLocal = $ahoraLocal->modify('-1 day')->format('Y-m-d');

        $ultimoCierre = $this->cierreRepo->obtenerUltimoCierre($propiedadId);

        if ($ultimoCierre !== null) {
            $fechaSiguiente = (new DateTimeImmutable($ultimoCierre->obtenerFechaHotelera()))->modify('+1 day')->format('Y-m-d');
        } else {
            $sqlPrimeraEntrada = 'SELECT MIN(e.fecha_entrada)
                                  FROM estadias e
                                  JOIN unidades u ON u.id = e.unidad_id
                                  WHERE u.propiedad_id = :propiedad_id';
            $stmt = $this->pdo->prepare($sqlPrimeraEntrada);
            $stmt->execute(['propiedad_id' => $propiedadId]);
            $primeraEntrada = $stmt->fetchColumn();

            $fechaSiguiente = ($primeraEntrada && is_string($primeraEntrada)) ? $primeraEntrada : $ayerLocal;
        }

        if ($fechaSiguiente > $ayerLocal) {
            return [];
        }

        $fechasDebidas = [];
        $iterador = new DateTimeImmutable($fechaSiguiente);
        $limite = new DateTimeImmutable($ayerLocal);

        while ($iterador <= $limite) {
            $fechasDebidas[] = $iterador->format('Y-m-d');
            $iterador = $iterador->modify('+1 day');
        }

        return $fechasDebidas;
    }

    /**
     * Ejecuta de forma secuencial y cronológica todos los cierres debidos para una propiedad.
     * Cada fecha se ejecuta en su propia transacción ACID independiente.
     * Si una fecha falla, detiene la secuencia para evitar lagunas históricas (D-112).
     *
     * @param int $propiedadId
     * @param int $actorId
     * @param string|null $observaciones
     * @return array<CierreHotelero>
     */
    public function ejecutarCierresDebidosPropiedad(
        int $propiedadId,
        int $actorId = 1,
        ?string $observaciones = null
    ): array {
        if (!$this->adquirirBloqueoPropiedad($propiedadId)) {
            throw new CierreHoteleroInvalidoExcepcion(
                "No se pudo adquirir el bloqueo de concurrencia para la propiedad ID {$propiedadId}. Hay otro proceso de Night Audit en ejecución."
            );
        }

        try {
            $actorIdFinal = $this->resolverActorId($actorId);
            $fechasDebidas = $this->obtenerFechasDebidasPropiedad($propiedadId);
            $cierres = [];
            $tz = $this->resolverZonaHorariaPropiedad($propiedadId);

            foreach ($fechasDebidas as $fecha) {
                $this->validarFechaCerrable($propiedadId, $fecha);

                $cierre = $this->ejecutarCierre(
                    $propiedadId,
                    $fecha,
                    $actorIdFinal,
                    $observaciones,
                    $tz,
                    false, // el bloqueo ya está adquirido a nivel de propiedad
                    true   // validarFechaPasada
                );
                $cierres[] = $cierre;
            }

            return $cierres;
        } finally {
            $this->liberarBloqueoPropiedad($propiedadId);
        }
    }

    /**
     * Ejecuta los cierres debidos de todas las propiedades activas del sistema (D-112).
     *
     * @param int $actorId
     * @param string|null $observaciones
     * @return array<int, array{propiedad_id: int, nombre: string, codigo: string, total_procesados: int, cierres: array<CierreHotelero>, error: ?string}>
     */
    public function ejecutarCierresDebidosTodas(
        int $actorId = 1,
        ?string $observaciones = null
    ): array {
        $actorIdFinal = $this->resolverActorId($actorId);
        $propiedades = $this->propiedadRepo->listar(estado: 'ACTIVO', limite: 500);
        $resumen = [];

        foreach ($propiedades as $propiedad) {
            $pId = (int) $propiedad->obtenerId();
            try {
                $cierres = $this->ejecutarCierresDebidosPropiedad($pId, $actorIdFinal, $observaciones);
                $resumen[$pId] = [
                    'propiedad_id' => $pId,
                    'nombre' => $propiedad->obtenerNombre(),
                    'codigo' => $propiedad->obtenerCodigo(),
                    'total_procesados' => count($cierres),
                    'cierres' => $cierres,
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $resumen[$pId] = [
                    'propiedad_id' => $pId,
                    'nombre' => $propiedad->obtenerNombre(),
                    'codigo' => $propiedad->obtenerCodigo(),
                    'total_procesados' => 0,
                    'cierres' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resumen;
    }

    /**
     * Ejecuta el proceso de Auditoría Nocturna (Night Audit) para una propiedad y fecha hotelera.
     */
    public function ejecutarCierre(
        int $propiedadId,
        string $fechaHotelera,
        int $actorId,
        ?string $observaciones = null,
        ?string $timezone = null,
        bool $gestionarBloqueo = true,
        bool $validarFechaPasada = false
    ): CierreHotelero {
        $actorIdFinal = $this->resolverActorId($actorId);

        // Validar formato de fecha YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHotelera)) {
            throw new CierreHoteleroInvalidoExcepcion("Formato de fecha hotelera inválido: {$fechaHotelera}.");
        }

        $timezoneFinal = ($timezone !== null && trim($timezone) !== '')
            ? $timezone
            : $this->resolverZonaHorariaPropiedad($propiedadId);

        if ($validarFechaPasada) {
            $this->validarFechaCerrable($propiedadId, $fechaHotelera);
        }

        $bloqueoAdquirido = false;
        if ($gestionarBloqueo) {
            if (!$this->adquirirBloqueoPropiedad($propiedadId)) {
                throw new CierreHoteleroInvalidoExcepcion(
                    "No se pudo adquirir el bloqueo de concurrencia para la propiedad ID {$propiedadId}. Hay otro proceso de Night Audit en ejecución."
                );
            }
            $bloqueoAdquirido = true;
        }

        try {
            // Detección de no-shows potenciales (sin mutación de estado ni cancelación automática)
            $noShows = $this->detectarNoShowsPotenciales($propiedadId, $fechaHotelera);
            if (!empty($noShows)) {
                $codigosNoShows = implode(', ', array_map(fn($ns) => (string) ($ns['codigo'] ?? ('RES-' . $ns['id'])), $noShows));
                $avisoNoShow = "[NO_SHOW_POTENCIAL: {$codigosNoShows}]";
                $observaciones = $observaciones !== null && trim($observaciones) !== ''
                    ? ($observaciones . ' ' . $avisoNoShow)
                    : $avisoNoShow;
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
                        $timezoneFinal,
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
                        'timezone' => $timezoneFinal,
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
                        $timezoneFinal
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
                    $timezoneFinal,
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
                        'no_shows_potenciales' => count($noShows),
                    ],
                    null,
                    $actorIdFinal
                );

                if (!empty($noShows)) {
                    $this->auditoriaServicio->registrar(
                        'NIGHT_AUDIT_ADVERTENCIA_NO_SHOW',
                        'operaciones',
                        'cierres_hoteleros',
                        (string) $cierreId,
                        "Advertencia de no-shows potenciales en fecha {$fechaHotelera}: " . count($noShows) . " reserva(s) confirmada(s) sin check-in",
                        null,
                        [
                            'propiedad_id' => $propiedadId,
                            'fecha_hotelera' => $fechaHotelera,
                            'reservas' => $noShows,
                        ],
                        null,
                        $actorIdFinal
                    );
                }

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
                            $timezoneFinal,
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
        } finally {
            if ($bloqueoAdquirido) {
                $this->liberarBloqueoPropiedad($propiedadId);
            }
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
