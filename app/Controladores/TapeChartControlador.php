<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ConfiguracionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\TapeChartServicio;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/**
 * Controlador para el Centro Operacional de Recepción (Tape Chart y Rack de Hoy).
 * TAPE-CHART-1 / D-084.
 */
class TapeChartControlador
{
    private TapeChartServicio $tapeChartServicio;
    private PropiedadRepositorio $propiedadRepo;
    private TipoUnidadRepositorio $tipoUnidadRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private ConfiguracionServicio $configuracionServicio;
    private CsrfServicio $csrfServicio;
    private Vista $vista;

    public function __construct(
        ?TapeChartServicio $tapeChartServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?TipoUnidadRepositorio $tipoUnidadRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?ConfiguracionServicio $configuracionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?Vista $vista = null
    ) {
        $this->tapeChartServicio = $tapeChartServicio ?? new TapeChartServicio();
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio();
        $this->tipoUnidadRepo = $tipoUnidadRepo ?? new TipoUnidadRepositorio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->configuracionServicio = $configuracionServicio ?? new ConfiguracionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la interfaz principal del Tape Chart (GET /tape-chart).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el Tape Chart operacional.',
            ], 'error'), 403);
        }

        // Propiedades activas
        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO', null, 100, 0);
        if (empty($propiedades)) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '404 — Sin propiedades activas',
                'codigo' => 404,
                'mensaje' => 'No se encontraron inmuebles o propiedades activas para operar el Tape Chart.',
            ], 'error'), 404);
        }

        $propiedadSeleccionadaId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id'])
            ? (int) $_GET['propiedad_id']
            : (int) $propiedades[0]->obtenerId();

        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
                'zona_horaria' => $p->obtenerZonaHoraria(),
            ];
        }, $propiedades);

        // Tipos de unidad activos
        $tiposUnidad = $this->tipoUnidadRepo->listarActivos();
        $tiposFormateados = array_map(static function ($t) {
            return [
                'id' => (int) $t->obtenerId(),
                'codigo' => $t->obtenerCodigo(),
                'nombre' => $t->obtenerNombre(),
            ];
        }, $tiposUnidad);

        // Zona horaria y fecha hotelera hoy
        $propiedadObj = $this->propiedadRepo->buscarPorId($propiedadSeleccionadaId);
        $tzCentral = (string) $this->configuracionServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
        $zonaHoraria = ($propiedadObj && $propiedadObj->obtenerZonaHoraria()) ? $propiedadObj->obtenerZonaHoraria() : $tzCentral;
        $tz = new DateTimeZone($zonaHoraria);
        $dtHoy = new DateTimeImmutable('now', $tz);
        $fechaHoy = $dtHoy->format('Y-m-d');
        $fechaFin14 = $dtHoy->modify('+14 days')->format('Y-m-d');

        $capacidades = [
            'puede_ver' => true,
            'puede_crear_estadia' => $this->autorizacionServicio->puede($usuarioActualId, 'estadias.crear'),
            'puede_checkout' => $this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkout'),
            'puede_bloquear' => $this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.bloquear'),
            'puede_liberar' => $this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.liberar'),
            'puede_ver_reserva' => $this->autorizacionServicio->puede($usuarioActualId, 'reservas.ver'),
            'puede_crear_reserva' => $this->autorizacionServicio->puede($usuarioActualId, 'reservas.crear'),
            'puede_ver_mantenimiento' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ver'),
            'puede_gestionar_housekeeping' => $this->autorizacionServicio->puede($usuarioActualId, 'housekeeping.gestionar'),
        ];

        $datos = [
            'titulo' => 'Camargo PMS — Centro Operacional de Recepción (Tape Chart)',
            'categoriaActiva' => 'operaciones',
            'subcategoriaActiva' => 'tape_chart',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Operaciones', 'url' => url_ruta('/estadias'), 'activo' => false],
                ['etiqueta' => 'Tape Chart / Rack', 'url' => url_ruta('/tape-chart'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'propiedades' => $propiedadesFormateadas,
            'propiedadSeleccionadaId' => $propiedadSeleccionadaId,
            'tiposUnidad' => $tiposFormateados,
            'fechaHoteleraHoy' => $fechaHoy,
            'fechaDefectoDesde' => $fechaHoy,
            'fechaDefectoHasta' => $fechaFin14,
            'zonaHoraria' => $zonaHoraria,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('tape-chart/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint API para consultar la matriz de proyección (GET /tape-chart/datos).
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.ver.'], 403);
        }

        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : 0;
        $fechaDesde = isset($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : '';
        $fechaHasta = isset($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : '';
        $tipoUnidadId = isset($_GET['tipo_unidad_id']) && is_numeric($_GET['tipo_unidad_id']) ? (int) $_GET['tipo_unidad_id'] : null;
        $pisoNivel = isset($_GET['piso_nivel']) && trim((string) $_GET['piso_nivel']) !== '' ? trim((string) $_GET['piso_nivel']) : null;

        if ($propiedadId <= 0) {
            $primeras = $this->propiedadRepo->listar(null, 'ACTIVO', null, 1, 0);
            if (!empty($primeras)) {
                $propiedadId = (int) $primeras[0]->obtenerId();
            } else {
                return Respuesta::json(['ok' => false, 'mensaje' => 'No hay propiedades activas disponibles.'], 422);
            }
        }

        if ($fechaDesde === '' || $fechaHasta === '') {
            $propiedadObj = $this->propiedadRepo->buscarPorId($propiedadId);
            $tzCentral = (string) $this->configuracionServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
            $zonaHoraria = ($propiedadObj && $propiedadObj->obtenerZonaHoraria()) ? $propiedadObj->obtenerZonaHoraria() : $tzCentral;
            $dtHoy = new DateTimeImmutable('now', new DateTimeZone($zonaHoraria));
            $fechaDesde = $dtHoy->format('Y-m-d');
            $fechaHasta = $dtHoy->modify('+14 days')->format('Y-m-d');
        }

        try {
            $proyeccion = $this->tapeChartServicio->obtenerProyeccion(
                $propiedadId,
                $fechaDesde,
                $fechaHasta,
                $tipoUnidadId,
                $pisoNivel,
                $usuarioActualId
            );

            return Respuesta::json([
                'ok' => true,
                'datos' => $proyeccion->aArreglo(),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'codigo_error' => 'PARAMETRO_INVALIDO',
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al procesar la proyección del Tape Chart: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API para consultar el Rack Operacional de Hoy (GET /tape-chart/rack-hoy).
     */
    public function rackHoyJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'disponibilidad.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido disponibilidad.ver.'], 403);
        }

        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : 0;

        if ($propiedadId <= 0) {
            $primeras = $this->propiedadRepo->listar(null, 'ACTIVO', null, 1, 0);
            if (!empty($primeras)) {
                $propiedadId = (int) $primeras[0]->obtenerId();
            } else {
                return Respuesta::json(['ok' => false, 'mensaje' => 'No hay propiedades activas disponibles.'], 422);
            }
        }

        $propiedadObj = $this->propiedadRepo->buscarPorId($propiedadId);
        $tzCentral = (string) $this->configuracionServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
        $zonaHoraria = ($propiedadObj && $propiedadObj->obtenerZonaHoraria()) ? $propiedadObj->obtenerZonaHoraria() : $tzCentral;
        $dtHoy = new DateTimeImmutable('now', new DateTimeZone($zonaHoraria));
        $fechaHoy = $dtHoy->format('Y-m-d');
        $fechaManana = $dtHoy->modify('+1 day')->format('Y-m-d');

        try {
            $proyeccion = $this->tapeChartServicio->obtenerProyeccion(
                $propiedadId,
                $fechaHoy,
                $fechaManana,
                null,
                null,
                $usuarioActualId
            );

            $arreglo = $proyeccion->aArreglo();

            return Respuesta::json([
                'ok' => true,
                'datos' => [
                    'propiedad' => $arreglo['propiedad'],
                    'kpis_hoy' => $arreglo['kpis_hoy'],
                    'unidades' => $arreglo['filas_unidades'],
                ],
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al obtener el Rack Operacional de Hoy: ' . $e->getMessage(),
            ], 500);
        }
    }
}
