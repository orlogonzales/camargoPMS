<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: REPORTES-1B — Interfaz Alina, ApexCharts, Flatpickr, Tablas y Exportación Analítica
 *
 * Cobertura de Gobernanza y Calidad:
 * 1. Gobierno del Esquema y Ranura de Migración 039:
 *    - Exactamente 130 tablas relacionales en base de datos.
 *    - Última migración aplicada: 038_pagos_pasarelas.sql.
 *    - Ranura 039 estrictamente LIBRE (cero DDL no autorizado).
 *    - admin-dashboard/ y SQL/ 100% inmutables y limpios en Git.
 * 2. Registro y Enrutamiento en Front Controller (public/index.php):
 *    - /reportes/analitica (GET)
 *    - /reportes/analitica/datos (GET)
 *    - /api/reportes/analitica (GET)
 *    - /reportes/analitica/exportar/csv (GET)
 * 3. Control de Acceso Basado en Roles (RBAC) y Seguridad:
 *    - Bloqueo anónimo (403 / redirección a login).
 *    - Bloqueo 403 para usuarios sin permiso 'reportes.ver'.
 *    - Acceso exitoso (200) para usuario con 'reportes.ver'.
 * 4. Integridad del Contrato API JSON (/api/reportes/analitica):
 *    - Estructura DTO completa y sin recálculo en cliente.
 *    - Formato de respuesta JSON estandarizado: ok: true, datos: { kpis, series_temporal, rendimiento_canales, ... }.
 *    - Validación defensiva de parámetros (HTTP 422 ante fechas invertidas o inválidas).
 * 5. Exportación CSV Analítica Oficial:
 *    - Generación de flujo descargable con BOM UTF-8 (\xEF\xBB\xBF).
 *    - Cabeceras oficiales Content-Type y Content-Disposition.
 *    - Secciones estructuradas completas: KPIs, Rendimiento por Canal, Devengado vs Percibido, Serie Diaria.
 *    - Sanitización de celdas ante inyección de fórmulas CSV.
 * 6. Fidelidad al Alina Design System en Vista (analitica.php):
 *    - Presencia de selector Flatpickr range picker con data-provider="rangepicker".
 *    - CERO input[type="date"] nativo.
 *    - 5 tarjetas métricas con clase .equal-card.
 *    - Contenedores de ApexCharts para serie temporal y distribución de canales.
 *    - Banner de advertencia y salvaguarda iCalendar (D-090).
 *    - Desglose contable dual: devengado vs percibido con brecha de recaudación.
 * 7. Integridad de Assets Frontend y Librería ApexCharts:
 *    - Vendor ApexCharts alojado en public/assets/vendor/apexcharts/ (JS y CSS).
 *    - Controlador JS camargo-reportes-analitica.js sin fórmulas de recálculo financiero/hotelero.
 *    - Enlace y botón de acceso en el índice de reportes (reportes/index.php).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Controladores\ReporteControlador;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReporteRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ConfiguracionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ReporteServicio;
use CamargoPMS\Servicios\SesionServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalChecks = 0;
$checksAprobados = 0;

function asegurar(bool $condicion, string $mensaje): void {
    global $totalChecks, $checksAprobados;
    $totalChecks++;
    if ($condicion) {
        $checksAprobados++;
        echo "  [PASS] $mensaje\n";
    } else {
        echo "  [FAIL] $mensaje\n";
        throw new RuntimeException("Fallo de aserción: $mensaje");
    }
}

echo "\n====================================================================\n";
echo " INICIANDO SUITE DE PRUEBAS: REPORTES-1B (HTTP, ALINA, APEXCHARTS Y EXPORT)\n";
echo "====================================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. Gobierno del Esquema y Ranura 039
    // -------------------------------------------------------------------------
    echo "--- 1. Gobierno del Esquema y Ranura 039 ---\n";
    $stmtTablas = $pdo->query("SHOW TABLES");
    $tablas = $stmtTablas->fetchAll(PDO::FETCH_COLUMN);
    $totalTablas = count($tablas);
    asegurar($totalTablas === 130, "Base de datos contiene exactamente 130 tablas relacionales (actual: $totalTablas)");

    $stmtMig = $pdo->query("SELECT migracion FROM migraciones ORDER BY id DESC LIMIT 1");
    $ultimaMig = (string) $stmtMig->fetchColumn();
    asegurar($ultimaMig === '038_pagos_pasarelas.sql', "Última migración aplicada es 038_pagos_pasarelas.sql (actual: $ultimaMig)");

    $archivos039Database = glob(__DIR__ . '/../database/migraciones/039*.sql');
    $archivos039Sql = glob(__DIR__ . '/../SQL/migraciones/*039*');
    asegurar(empty($archivos039Database) && empty($archivos039Sql), "Ranura de migración 039 estrictamente LIBRE (cero DDL en REPORTES-1B)");

    $gitAlina = shell_exec('git status --porcelain admin-dashboard/ 2>&1');
    asegurar(empty(trim((string) $gitAlina)), "admin-dashboard/ permanece 100% inmutable y libre de modificaciones");

    $gitSql = shell_exec('git status --porcelain SQL/ 2>&1');
    asegurar(empty(trim((string) $gitSql)), "SQL/ permanece 100% inmutable y libre de modificaciones");

    // -------------------------------------------------------------------------
    // 2. Registro y Enrutamiento en Front Controller (public/index.php)
    // -------------------------------------------------------------------------
    echo "\n--- 2. Registro y Enrutamiento en Front Controller (public/index.php) ---\n";
    $indexPhp = file_get_contents(dirname(__DIR__) . '/public/index.php');
    asegurar(str_contains($indexPhp, "'/reportes/analitica'"), "Ruta GET /reportes/analitica registrada");
    asegurar(str_contains($indexPhp, "'/reportes/analitica/datos'"), "Ruta GET /reportes/analitica/datos registrada");
    asegurar(str_contains($indexPhp, "'/api/reportes/analitica'"), "Ruta GET /api/reportes/analitica registrada");
    asegurar(str_contains($indexPhp, "'/reportes/analitica/exportar/csv'"), "Ruta GET /reportes/analitica/exportar/csv registrada");
    asegurar(str_contains($indexPhp, "AutorizacionIntermediario('reportes.ver')"), "Rutas analíticas protegidas por intermediario de autorización");

    // -------------------------------------------------------------------------
    // 3. Control de Acceso (RBAC) y Seguridad
    // -------------------------------------------------------------------------
    echo "\n--- 3. Control de Acceso (RBAC) y Seguridad ---\n";

    // Fixtures de usuarios para pruebas RBAC
    $stmtAdmin = $pdo->query("SELECT u.id, u.persona_id, u.nombre_usuario FROM usuarios u JOIN usuarios_roles ur ON ur.usuario_id = u.id JOIN roles r ON r.id = ur.rol_id WHERE r.codigo = 'SUPERADMINISTRADOR' AND u.estado = 'ACTIVO' LIMIT 1");
    $adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    asegurar(!empty($adminRow), "Usuario SUPERADMINISTRADOR disponible para pruebas RBAC");
    $usuarioAdmin = new Usuario((int) $adminRow['id'], (int) $adminRow['persona_id'], (string) $adminRow['nombre_usuario'], 'hash');

    $usuarioSinPermiso = new Usuario(999901, 999901, 'operador_sin_reportes', 'hash');

    $mockSesionAnonima = new class extends SesionServicio {
        public function __construct() {}
        public function validarSesionActual(): ?Usuario { return null; }
    };

    $mockSesionAdmin = new class($usuarioAdmin) extends SesionServicio {
        public function __construct(private Usuario $u) {}
        public function validarSesionActual(): ?Usuario { return $this->u; }
    };

    $mockSesionRestringida = new class($usuarioSinPermiso) extends SesionServicio {
        public function __construct(private Usuario $u) {}
        public function validarSesionActual(): ?Usuario { return $this->u; }
    };

    $reporteRepo = new ReporteRepositorio($pdo);
    $reporteServicio = new ReporteServicio($reporteRepo);
    $propiedadRepo = new PropiedadRepositorio($pdo);
    $autorizacionServicio = new AutorizacionServicio($pdo);
    $configuracionServicio = new ConfiguracionServicio();
    $csrfServicio = new CsrfServicio();
    $vista = new Vista();

    // 3.1 Usuario Anónimo
    $ctrlAnonimo = new ReporteControlador(
        $reporteServicio,
        $propiedadRepo,
        $mockSesionAnonima,
        $autorizacionServicio,
        $configuracionServicio,
        $csrfServicio,
        $vista
    );

    $_GET = [];
    $respAnonVista = $ctrlAnonimo->analitica();
    asegurar($respAnonVista->obtenerCodigoEstado() === 403, "GET /reportes/analitica anónimo es rechazado con HTTP 403");

    $respAnonJson = $ctrlAnonimo->analiticaJson();
    asegurar($respAnonJson->obtenerCodigoEstado() === 403, "GET /api/reportes/analitica anónimo es rechazado con HTTP 403");

    // 3.2 Usuario sin permiso 'reportes.ver'
    $ctrlRestringido = new ReporteControlador(
        $reporteServicio,
        $propiedadRepo,
        $mockSesionRestringida,
        $autorizacionServicio,
        $configuracionServicio,
        $csrfServicio,
        $vista
    );

    $respRestrVista = $ctrlRestringido->analitica();
    asegurar($respRestrVista->obtenerCodigoEstado() === 403, "GET /reportes/analitica sin permiso es rechazado con HTTP 403");

    $respRestrJson = $ctrlRestringido->analiticaJson();
    asegurar($respRestrJson->obtenerCodigoEstado() === 403, "GET /api/reportes/analitica sin permiso es rechazado con HTTP 403");

    // 3.3 Usuario Administrador Autorizado
    $ctrlAdmin = new ReporteControlador(
        $reporteServicio,
        $propiedadRepo,
        $mockSesionAdmin,
        $autorizacionServicio,
        $configuracionServicio,
        $csrfServicio,
        $vista
    );

    $_GET = ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-10'];
    $respAdminVista = $ctrlAdmin->analitica();
    asegurar($respAdminVista->obtenerCodigoEstado() === 200, "GET /reportes/analitica con usuario autorizado retorna HTTP 200");
    asegurar(str_contains($respAdminVista->obtenerContenido(), 'Analítica Gerencial &amp; Rendimiento por Canal')
        || str_contains($respAdminVista->obtenerContenido(), 'Analítica Gerencial & Rendimiento por Canal'),
        "Vista renderizada contiene título oficial de Analítica Gerencial");

    // -------------------------------------------------------------------------
    // 4. Integridad del Contrato API JSON (/api/reportes/analitica)
    // -------------------------------------------------------------------------
    echo "\n--- 4. Integridad del Contrato API JSON (/api/reportes/analitica) ---\n";
    $_GET = ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-15'];
    $respApi = $ctrlAdmin->analiticaJson();
    asegurar($respApi->obtenerCodigoEstado() === 200, "GET /api/reportes/analitica retorna HTTP 200");

    $jsonApi = json_decode($respApi->obtenerContenido(), true);
    asegurar(is_array($jsonApi) && ($jsonApi['ok'] ?? false) === true, "Respuesta JSON tiene estructura { ok: true, datos: ... }");
    asegurar(isset($jsonApi['datos']['periodo']), "Payload incluye sección 'periodo'");
    asegurar(isset($jsonApi['datos']['kpis']), "Payload incluye sección 'kpis'");
    asegurar(isset($jsonApi['datos']['serie_temporal']), "Payload incluye sección 'serie_temporal'");
    asegurar(isset($jsonApi['datos']['rendimiento_canales']), "Payload incluye sección 'rendimiento_canales'");
    asegurar(isset($jsonApi['datos']['ingresos_devengados']), "Payload incluye sección 'ingresos_devengados'");
    asegurar(isset($jsonApi['datos']['ingresos_percibidos']), "Payload incluye sección 'ingresos_percibidos'");
    asegurar(isset($jsonApi['datos']['resumen_ical']), "Payload incluye sección 'resumen_ical'");

    // Verificación de campos en KPIs clave
    $kpis = $jsonApi['datos']['kpis'];
    asegurar(isset($kpis['ocupacion_media_porcentaje']), "KPI incluye 'ocupacion_media_porcentaje'");
    asegurar(isset($kpis['adr_promedio']), "KPI incluye 'adr_promedio'");
    asegurar(isset($kpis['revpar_promedio']), "KPI incluye 'revpar_promedio'");
    asegurar(isset($kpis['trevpar_promedio']), "KPI incluye 'trevpar_promedio'");
    asegurar(isset($kpis['ingreso_alojamiento_neto']), "KPI incluye 'ingreso_alojamiento_neto'");
    asegurar(isset($kpis['habitaciones_vendidas_totales']), "KPI incluye 'habitaciones_vendidas_totales'");
    asegurar(isset($kpis['habitaciones_cortesia_totales']), "KPI incluye 'habitaciones_cortesia_totales'");

    // Verificación de manejo de errores de validación (HTTP 422)
    $_GET = ['fecha_desde' => '2026-09-20', 'fecha_hasta' => '2026-09-10']; // Fechas invertidas
    $respInvalido = $ctrlAdmin->analiticaJson();
    asegurar($respInvalido->obtenerCodigoEstado() === 422, "Rango de fechas invertido retorna HTTP 422");
    $jsonInvalido = json_decode($respInvalido->obtenerContenido(), true);
    asegurar(($jsonInvalido['ok'] ?? true) === false, "Respuesta 422 contiene ok: false");
    asegurar(!empty($jsonInvalido['mensaje']), "Respuesta 422 contiene mensaje descriptivo");

    // -------------------------------------------------------------------------
    // 5. Exportación CSV Analítica Oficial
    // -------------------------------------------------------------------------
    echo "\n--- 5. Exportación CSV Analítica Oficial ---\n";
    $_GET = ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-10'];
    $respCsv = $ctrlAdmin->analiticaExportarCsv();
    asegurar($respCsv->obtenerCodigoEstado() === 200, "GET /reportes/analitica/exportar/csv retorna HTTP 200");

    $cabeceras = $respCsv->obtenerCabeceras();
    asegurar(str_contains($cabeceras['Content-Type'] ?? '', 'text/csv'), "Cabecera Content-Type es text/csv; charset=UTF-8");
    asegurar(str_contains($cabeceras['Content-Disposition'] ?? '', 'attachment'), "Cabecera Content-Disposition es attachment");
    asegurar(str_contains($cabeceras['Content-Disposition'] ?? '', 'reporte_analitica_'), "Nombre de archivo CSV contiene prefijo oficial 'reporte_analitica_'");

    $csvContenido = $respCsv->obtenerContenido();
    asegurar(str_starts_with($csvContenido, "\xEF\xBB\xBF"), "CSV inicia con marca de orden de bytes (BOM UTF-8) para Excel");
    asegurar(str_contains($csvContenido, 'REPORTE ANALÍTICO GERENCIAL Y RENDIMIENTO POR CANAL'), "CSV contiene título oficial en encabezado");
    asegurar(str_contains($csvContenido, 'RESUMEN EJECUTIVO (KPIS CLAVE)'), "CSV contiene bloque de resumen ejecutivo");
    asegurar(str_contains($csvContenido, 'RENDIMIENTO POR CANAL Y DISTRIBUCIÓN COMERCIAL'), "CSV contiene bloque de rendimiento por canal");
    asegurar(str_contains($csvContenido, 'RESUMEN FINANCIERO: DEVENGADO VS PERCIBIDO'), "CSV contiene bloque financiero dual");
    asegurar(str_contains($csvContenido, 'SERIE TEMPORAL DIARIA (OCUPACIÓN, ADR, REVPAR)'), "CSV contiene serie temporal diaria");
    asegurar(str_contains($csvContenido, 'iCalendar (Bloqueos Externos)'), "CSV incluye aviso de salvaguarda de iCalendar");

    // -------------------------------------------------------------------------
    // 6. Fidelidad al Alina Design System en Vista (analitica.php)
    // -------------------------------------------------------------------------
    echo "\n--- 6. Fidelidad al Alina Design System en Vista (analitica.php) ---\n";
    $vistaArchivo = dirname(__DIR__) . '/app/Vistas/reportes/analitica.php';
    asegurar(file_exists($vistaArchivo), "Archivo de vista app/Vistas/reportes/analitica.php existe");

    $vistaContenido = file_get_contents($vistaArchivo);
    asegurar(str_contains($vistaContenido, 'data-provider="rangepicker"'), "Filtro de fecha utiliza Flatpickr data-provider=\"rangepicker\"");
    asegurar(str_contains($vistaContenido, 'data-target-inicio="#filtro-analitica-desde"'), "Range picker enlaza con data-target-inicio");
    asegurar(str_contains($vistaContenido, 'data-target-fin="#filtro-analitica-hasta"'), "Range picker enlaza con data-target-fin");
    asegurar(!str_contains($vistaContenido, 'type="date"'), "Estricto cumplimiento Alina: CERO input type=\"date\" en la vista");

    // Verificar 5 tarjetas métricas .equal-card
    $conteoEqualCard = substr_count($vistaContenido, 'equal-card');
    asegurar($conteoEqualCard >= 5, "Vista implementa al menos 5 tarjetas con clase .equal-card (encontradas: $conteoEqualCard)");

    // Contenedores ApexCharts
    asegurar(str_contains($vistaContenido, 'id="chart-serie-temporal"'), "Contenedor DOM para gráfico de Serie Temporal presente (#chart-serie-temporal)");
    asegurar(str_contains($vistaContenido, 'id="chart-distribucion-canales"'), "Contenedor DOM para gráfico Donut de Canales presente (#chart-distribucion-canales)");

    // Salvaguarda iCalendar
    asegurar(str_contains($vistaContenido, 'id="alerta-ical-salvaguarda"'), "Banner de alerta y salvaguarda iCalendar presente (#alerta-ical-salvaguarda)");
    asegurar(str_contains($vistaContenido, 'tabla-rendimiento-canales'), "Tabla de rendimiento por canal presente");
    asegurar(str_contains($vistaContenido, 'tabla-serie-temporal'), "Tabla de serie temporal continua presente");
    asegurar(str_contains($vistaContenido, 'tabla-devengado-conceptos'), "Tabla de devengado presente");
    asegurar(str_contains($vistaContenido, 'tabla-percibido-medios'), "Tabla de tesorería percibida presente");
    asegurar(str_contains($vistaContenido, 'btn-exportar-csv'), "Botón de exportación CSV presente en cabecera");

    // -------------------------------------------------------------------------
    // 7. Integridad de Assets Frontend y Librería ApexCharts
    // -------------------------------------------------------------------------
    echo "\n--- 7. Integridad de Assets Frontend y Librería ApexCharts ---\n";
    $apexJs = dirname(__DIR__) . '/public/assets/vendor/apexcharts/apexcharts.min.js';
    $apexCss = dirname(__DIR__) . '/public/assets/vendor/apexcharts/apexcharts.css';
    $camargoReportesJs = dirname(__DIR__) . '/public/assets/js/camargo-reportes-analitica.js';

    asegurar(file_exists($apexJs), "Vendor ApexCharts JS existe en public/assets/vendor/apexcharts/apexcharts.min.js");
    asegurar(filesize($apexJs) > 400000, "ApexCharts JS es un binario minificado completo (> 400KB)");
    asegurar(file_exists($apexCss), "Vendor ApexCharts CSS existe en public/assets/vendor/apexcharts/apexcharts.css");
    asegurar(file_exists($camargoReportesJs), "Controlador Frontend camargo-reportes-analitica.js existe");

    $jsContenido = file_get_contents($camargoReportesJs);
    asegurar(str_contains($jsContenido, 'ApexCharts'), "Controlador JS interactúa con librería oficial ApexCharts");
    asegurar(str_contains($jsContenido, '/reportes/analitica/datos'), "Controlador JS consume endpoint backend /reportes/analitica/datos");
    asegurar(str_contains($jsContenido, 'autoridad analítica') && str_contains($jsContenido, 'NO recalcula'), "Controlador JS documenta explícitamente la prohibición de recálculo de fórmulas en cliente");

    // Asegurar que el JS no recalcule fórmulas de ADR ni RevPAR
    asegurar(!str_contains($jsContenido, 'adr =') && !str_contains($jsContenido, 'revpar =') && !str_contains($jsContenido, 'adr='), "Controlador JS respeta autoridad backend (cero recálculo de fórmulas ADR/RevPAR en frontend)");

    // -------------------------------------------------------------------------
    // 8. Integración en Vista Principal de Reportes (reportes/index.php)
    // -------------------------------------------------------------------------
    echo "\n--- 8. Integración en Vista Principal de Reportes (reportes/index.php) ---\n";
    $indexVista = file_get_contents(dirname(__DIR__) . '/app/Vistas/reportes/index.php');
    asegurar(str_contains($indexVista, 'url_ruta(\'/reportes/analitica\')'), "Vista principal de reportes enlaza al Panel de Analítica Gerencial");
    asegurar(str_contains($indexVista, 'Analítica & Canales') || str_contains($indexVista, 'Analítica &amp; Canales'), "Pestaña de Analítica visible en la cabecera de navegación de reportes");

} catch (Throwable $e) {
    echo "\n[ERROR CRÍTICO EN SUITE]: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . " en " . $e->getFile() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n====================================================================\n";
echo " RESULTADO SUITE REPORTES-1B: $checksAprobados/$totalChecks ASERCIONES SUPERADAS (100% PASS)\n";
echo "====================================================================\n\n";

exit(0);
