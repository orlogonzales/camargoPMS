<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReporteRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ConfiguracionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ReporteServicio;
use CamargoPMS\Servicios\SesionServicio;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Controlador de la Capa Analítica y Reportes Ejecutivos.
 * Gobernanza: D-087 / REPORTES-1.
 * Solo lectura y exportaciones analíticas oficiales.
 */
class ReporteControlador
{
    private ReporteServicio $reporteServicio;
    private PropiedadRepositorio $propiedadRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private ConfiguracionServicio $configuracionServicio;
    private CsrfServicio $csrfServicio;
    private Vista $vista;

    public function __construct(
        ?ReporteServicio $reporteServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?ConfiguracionServicio $configuracionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?Vista $vista = null
    ) {
        $pdo = BaseDatos::conexion();
        $this->reporteServicio = $reporteServicio ?? new ReporteServicio(new ReporteRepositorio($pdo));
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($pdo);
        $this->configuracionServicio = $configuracionServicio ?? new ConfiguracionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la vista principal del Centro de Reportes Gerenciales (GET /reportes).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de reportes analíticos.',
            ], 'error'), 403);
        }

        // Listar propiedades para el filtro
        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO', null, 100, 0);
        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
                'zona_horaria' => $p->obtenerZonaHoraria(),
            ];
        }, $propiedades);

        // Fecha de hoy por zona horaria de la primera propiedad o central
        $tzCentral = (string) $this->configuracionServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
        $zonaHoraria = (!empty($propiedades) && $propiedades[0]->obtenerZonaHoraria())
            ? $propiedades[0]->obtenerZonaHoraria()
            : $tzCentral;
        $dtHoy = new DateTimeImmutable('now', new DateTimeZone($zonaHoraria));
        $fechaHoy = $dtHoy->format('Y-m-d');
        $fechaPrimerDiaMes = $dtHoy->format('Y-m-01');

        $capacidades = [
            'puede_ver' => true,
            'puede_operaciones' => $this->autorizacionServicio->puede($usuarioActualId, 'reportes.operaciones')
                || $this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver'),
            'puede_finanzas' => $this->autorizacionServicio->puede($usuarioActualId, 'reportes.finanzas')
                || $this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver'),
            'puede_morosidad' => $this->autorizacionServicio->puede($usuarioActualId, 'reportes.morosidad')
                || $this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver'),
            'puede_exportar' => $this->autorizacionServicio->puede($usuarioActualId, 'reportes.exportar')
                || $this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver'),
        ];

        $datos = [
            'titulo' => 'Camargo PMS — Reportes Analíticos y Gerenciales',
            'categoriaActiva' => 'reportes',
            'subcategoriaActiva' => 'reportes_panel',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Reportes', 'url' => url_ruta('/reportes'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'propiedades' => $propiedadesFormateadas,
            'fechaHoy' => $fechaHoy,
            'fechaPrimerDiaMes' => $fechaPrimerDiaMes,
            'zonaHoraria' => $zonaHoraria,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('reportes/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint API JSON: Reporte Diario Gerencial (GET /api/reportes/diario).
     */
    public function diarioJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.operaciones')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.operaciones.'], 403);
        }

        $fecha = isset($_GET['fecha']) && trim((string) $_GET['fecha']) !== ''
            ? trim((string) $_GET['fecha'])
            : date('Y-m-d');
        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        try {
            $dto = $this->reporteServicio->generarReporteDiario($fecha, $propiedadId);

            return Respuesta::json([
                'ok' => true,
                'datos' => $dto->aArreglo(),
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al generar el Reporte Diario Gerencial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API JSON: Flujo de Caja Consolidado (GET /api/reportes/flujo-caja).
     */
    public function flujoCajaJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.finanzas')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.finanzas.'], 403);
        }

        $fechaDesde = isset($_GET['fecha_desde']) && trim((string) $_GET['fecha_desde']) !== ''
            ? trim((string) $_GET['fecha_desde'])
            : date('Y-m-01');
        $fechaHasta = isset($_GET['fecha_hasta']) && trim((string) $_GET['fecha_hasta']) !== ''
            ? trim((string) $_GET['fecha_hasta'])
            : date('Y-m-d');
        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        try {
            $dto = $this->reporteServicio->generarFlujoCaja($fechaDesde, $fechaHasta, $propiedadId);

            return Respuesta::json([
                'ok' => true,
                'datos' => $dto->aArreglo(),
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al generar el Flujo de Caja: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API JSON: Aging CxC (GET /api/reportes/aging-cxc).
     */
    public function agingCxcJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.morosidad')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.morosidad.'], 403);
        }

        $fechaCorte = isset($_GET['fecha_corte']) && trim((string) $_GET['fecha_corte']) !== ''
            ? trim((string) $_GET['fecha_corte'])
            : date('Y-m-d');
        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        try {
            $dto = $this->reporteServicio->generarAgingCxC($fechaCorte, $propiedadId);

            return Respuesta::json([
                'ok' => true,
                'datos' => $dto->aArreglo(),
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al generar el Aging CxC: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint API JSON: Aging CxP (GET /api/reportes/aging-cxp).
     */
    public function agingCxpJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.morosidad')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.morosidad.'], 403);
        }

        $fechaCorte = isset($_GET['fecha_corte']) && trim((string) $_GET['fecha_corte']) !== ''
            ? trim((string) $_GET['fecha_corte'])
            : date('Y-m-d');
        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        try {
            $dto = $this->reporteServicio->generarAgingCxP($fechaCorte, $propiedadId);

            return Respuesta::json([
                'ok' => true,
                'datos' => $dto->aArreglo(),
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al generar el Aging CxP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga de reporte en formato CSV con anti-injection y UTF-8 BOM (GET /reportes/exportar/csv).
     */
    public function exportarCsv(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.exportar')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.exportar.'], 403);
        }

        $tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'DIARIO')));
        $nombreBase = strtolower(str_replace('_', '-', $tipo));
        $fechaSufijo = date('Ymd_His');
        $nombreArchivo = "reporte_{$nombreBase}_{$fechaSufijo}.csv";

        try {
            $csv = $this->reporteServicio->exportarCSV($tipo, $_GET);

            return new Respuesta(
                $csv,
                200,
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
                    'Content-Length' => (string) strlen($csv),
                ]
            );
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al exportar CSV: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Descarga o visualización de reporte en formato PDF institucional (GET /reportes/exportar/pdf).
     */
    public function exportarPdf(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.exportar')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.exportar.'], 403);
        }

        $tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'DIARIO')));
        $nombreBase = strtolower(str_replace('_', '-', $tipo));
        $fechaSufijo = date('Ymd_His');
        $nombreArchivo = "reporte_{$nombreBase}_{$fechaSufijo}.pdf";

        try {
            $res = $this->reporteServicio->exportarPDF($tipo, $_GET);
            $binarioPdf = $res['binario_pdf'];

            $inline = isset($_GET['inline']) && $_GET['inline'] === '1';
            $disposition = $inline ? 'inline' : 'attachment';

            return new Respuesta(
                $binarioPdf,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "{$disposition}; filename=\"{$nombreArchivo}\"",
                    'Content-Length' => (string) strlen($binarioPdf),
                ]
            );
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al exportar PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Renderiza la vista del Panel de Analítica Gerencial y Rendimiento por Canal (GET /reportes/analitica).
     */
    public function analitica(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de analítica gerencial.',
            ], 'error'), 403);
        }

        // Listar propiedades para el filtro
        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO', null, 100, 0);
        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
                'zona_horaria' => $p->obtenerZonaHoraria(),
            ];
        }, $propiedades);

        $tzCentral = (string) $this->configuracionServicio->obtener('operacion.zona_horaria_predeterminada', 'America/Lima');
        $zonaHoraria = (!empty($propiedades) && $propiedades[0]->obtenerZonaHoraria())
            ? $propiedades[0]->obtenerZonaHoraria()
            : $tzCentral;
        $dtHoy = new DateTimeImmutable('now', new DateTimeZone($zonaHoraria));
        $fechaHasta = isset($_GET['fecha_hasta']) && trim((string) $_GET['fecha_hasta']) !== ''
            ? trim((string) $_GET['fecha_hasta'])
            : $dtHoy->format('Y-m-d');
        $fechaDesde = isset($_GET['fecha_desde']) && trim((string) $_GET['fecha_desde']) !== ''
            ? trim((string) $_GET['fecha_desde'])
            : $dtHoy->format('Y-m-01');
        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        // Generar reporte analítico inicial precargado (soberanía backend)
        try {
            $reporteAnalitico = $this->reporteServicio->generarReporteAnalitico($fechaDesde, $fechaHasta, $propiedadId);
        } catch (Throwable $e) {
            $fechaDesde = $dtHoy->format('Y-m-01');
            $fechaHasta = $dtHoy->format('Y-m-d');
            $reporteAnalitico = $this->reporteServicio->generarReporteAnalitico($fechaDesde, $fechaHasta, $propiedadId);
        }

        $capacidades = [
            'puede_ver' => true,
            'puede_exportar' => $this->autorizacionServicio->puede($usuarioActualId, 'reportes.exportar')
                || $this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver'),
        ];

        $datos = [
            'titulo' => 'Camargo PMS — Analítica Gerencial & Rendimiento por Canal',
            'categoriaActiva' => 'reportes',
            'subcategoriaActiva' => 'reportes_analitica',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Reportes', 'url' => url_ruta('/reportes'), 'activo' => false],
                ['etiqueta' => 'Analítica & Canales', 'url' => url_ruta('/reportes/analitica'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'propiedades' => $propiedadesFormateadas,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'propiedadId' => $propiedadId,
            'reporteAnalitico' => $reporteAnalitico,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('reportes/analitica', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Endpoint API JSON: Analítica Gerencial y Rendimiento por Canal (GET /api/reportes/analitica).
     */
    public function analiticaJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reportes.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado: permiso requerido reportes.ver.'], 403);
        }

        $fechaDesde = isset($_GET['fecha_desde']) && trim((string) $_GET['fecha_desde']) !== ''
            ? trim((string) $_GET['fecha_desde'])
            : date('Y-m-01');
        $fechaHasta = isset($_GET['fecha_hasta']) && trim((string) $_GET['fecha_hasta']) !== ''
            ? trim((string) $_GET['fecha_hasta'])
            : date('Y-m-d');
        $propiedadId = isset($_GET['propiedad_id']) && is_numeric($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0
            ? (int) $_GET['propiedad_id']
            : null;

        try {
            $dto = $this->reporteServicio->generarReporteAnalitico($fechaDesde, $fechaHasta, $propiedadId);

            return Respuesta::json([
                'ok' => true,
                'datos' => $dto->aArreglo(),
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al generar la Analítica Gerencial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga del reporte analítico en formato CSV (GET /reportes/analitica/exportar/csv).
     */
    public function analiticaExportarCsv(): Respuesta
    {
        $_GET['tipo'] = 'ANALITICA';
        return $this->exportarCsv();
    }
}
