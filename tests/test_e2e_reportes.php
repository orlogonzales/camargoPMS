<?php

declare(strict_types=1);

/**
 * Suite de Verificación REPORTES-1 — Pruebas End-to-End (E2E) y Ciclo de Vida HTTP (10 Casos)
 *
 * REPORTES-1 / D-087.
 * Verifica la navegación, middleware de autorización, endpoints JSON y descargas CSV/PDF.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\ReporteControlador;
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

$reporteRepo = new ReporteRepositorio($pdo);
$reporteServicio = new ReporteServicio($reporteRepo);
$propiedadRepo = new PropiedadRepositorio($pdo);
$sesionServicio = new SesionServicio($pdo);
$autorizacionServicio = new AutorizacionServicio($pdo);
$configuracionServicio = new ConfiguracionServicio();
$csrfServicio = new CsrfServicio();
$vista = new Vista();
$usuarioRepo = new UsuarioRepositorio($pdo);

$controlador = new ReporteControlador(
    $reporteServicio,
    $propiedadRepo,
    $sesionServicio,
    $autorizacionServicio,
    $configuracionServicio,
    $csrfServicio,
    $vista
);

$total = 0;
$pasadas = 0;
$fallidas = 0;
$errores = [];

function afirmar(bool $condicion, string $mensaje): void
{
    global $total, $pasadas, $fallidas, $errores;
    $total++;
    if ($condicion) {
        $pasadas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = $mensaje;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS E2E REPORTES-1 (10 CASOS HTTP/API)\n";
echo " Decisión Vinculante: D-087\n";
echo "====================================================================\n\n";

// Caso 1: Sin sesión: GET /reportes bloquea con 403 Forbidden
SesionServicio::iniciarSesionPhp();
$_SESSION = []; // limpiar sesión
$resSinSesion = $controlador->index();
afirmar($resSinSesion->obtenerCodigoEstado() === 403,
    'Caso 1: GET /reportes sin sesión retorna HTTP 403 Forbidden');

// Caso 2: Sin sesión: GET /api/reportes/diario bloquea con 403 Forbidden
$resJsonSinSesion = $controlador->diarioJson();
$bodyJsonSinSesion = json_decode($resJsonSinSesion->obtenerContenido(), true);
afirmar($resJsonSinSesion->obtenerCodigoEstado() === 403 && $bodyJsonSinSesion['ok'] === false,
    'Caso 2: GET /api/reportes/diario sin sesión retorna HTTP 403 Forbidden');

// Iniciar sesión con usuario Superadministrador (ID 1)
$usuarioAdmin = $usuarioRepo->buscarPorId(1);
if ($usuarioAdmin === null) {
    // Buscar primer usuario activo
    $stmtU = $pdo->query('SELECT id FROM usuarios WHERE estado = "ACTIVO" LIMIT 1');
    $uId = (int) $stmtU->fetchColumn();
    $usuarioAdmin = $usuarioRepo->buscarPorId($uId);
}
$sesionServicio->crearSesion($usuarioAdmin);

// Caso 3: Con sesión autorizada: GET /reportes retorna HTTP 200 y HTML de la vista
$resIndex = $controlador->index();
$htmlIndex = $resIndex->obtenerContenido();
afirmar(
    $resIndex->obtenerCodigoEstado() === 200
    && str_contains($htmlIndex, 'Centro de Reportes Analíticos y Gerenciales')
    && str_contains($htmlIndex, 'camargo.css'),
    'Caso 3: GET /reportes con sesión válida retorna HTTP 200 y vista Alina con camargo.css'
);

// Caso 4: Con sesión: GET /api/reportes/diario retorna HTTP 200, ok: true y estructura MDR
$_GET = [];
$resDiario = $controlador->diarioJson();
$jsonDiario = json_decode($resDiario->obtenerContenido(), true);
afirmar(
    $resDiario->obtenerCodigoEstado() === 200
    && $jsonDiario['ok'] === true
    && isset($jsonDiario['datos']['operacion_hotelera']['unidades_totales']),
    'Caso 4: GET /api/reportes/diario retorna HTTP 200 y estructura analítica completa'
);

// Caso 5: Parámetro fecha: GET /api/reportes/diario?fecha=2026-09-01 respeta la fecha solicitada
$_GET = ['fecha' => '2026-09-01'];
$resDiarioFecha = $controlador->diarioJson();
$jsonDiarioFecha = json_decode($resDiarioFecha->obtenerContenido(), true);
afirmar(
    $resDiarioFecha->obtenerCodigoEstado() === 200
    && $jsonDiarioFecha['datos']['fecha_corte'] === '2026-09-01',
    'Caso 5: GET /api/reportes/diario proyecta con exactitud la fecha de corte requerida'
);

// Caso 6: Con sesión: GET /api/reportes/flujo-caja retorna HTTP 200 y cuadre algebraico
$_GET = ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-28'];
$resFlujo = $controlador->flujoCajaJson();
$jsonFlujo = json_decode($resFlujo->obtenerContenido(), true);
afirmar(
    $resFlujo->obtenerCodigoEstado() === 200
    && $jsonFlujo['ok'] === true
    && $jsonFlujo['datos']['totales_consolidado']['cuadre_algebraico_valido'] === true,
    'Caso 6: GET /api/reportes/flujo-caja retorna HTTP 200 y certificación de cuadre patrimonial'
);

// Caso 7: Con sesión: GET /api/reportes/aging-cxc retorna HTTP 200 y tipo CXC
$_GET = ['fecha_corte' => '2026-09-28'];
$resCxc = $controlador->agingCxcJson();
$jsonCxc = json_decode($resCxc->obtenerContenido(), true);
afirmar(
    $resCxc->obtenerCodigoEstado() === 200
    && $jsonCxc['ok'] === true
    && $jsonCxc['datos']['tipo'] === 'CXC',
    'Caso 7: GET /api/reportes/aging-cxc retorna HTTP 200 con cartera segregada de clientes'
);

// Caso 8: Con sesión: GET /api/reportes/aging-cxp retorna HTTP 200 y tipo CXP
$_GET = ['fecha_corte' => '2026-09-28'];
$resCxp = $controlador->agingCxpJson();
$jsonCxp = json_decode($resCxp->obtenerContenido(), true);
afirmar(
    $resCxp->obtenerCodigoEstado() === 200
    && $jsonCxp['ok'] === true
    && $jsonCxp['datos']['tipo'] === 'CXP',
    'Caso 8: GET /api/reportes/aging-cxp retorna HTTP 200 con cartera segregada de proveedores'
);

// Caso 9: Descarga CSV: GET /reportes/exportar/csv?tipo=DIARIO retorna Content-Type CSV y BOM
$_GET = ['tipo' => 'DIARIO', 'fecha' => '2026-09-28'];
$resCsv = $controlador->exportarCsv();
$csvContenido = $resCsv->obtenerContenido();
afirmar(
    $resCsv->obtenerCodigoEstado() === 200
    && str_starts_with($csvContenido, "\xEF\xBB\xBF")
    && str_contains($csvContenido, 'REPORTE DIARIO GERENCIAL'),
    'Caso 9: GET /reportes/exportar/csv genera flujo descargable con BOM UTF-8 y sanitización'
);

// Caso 10: Descarga PDF: GET /reportes/exportar/pdf?tipo=DIARIO retorna application/pdf
$_GET = ['tipo' => 'DIARIO', 'fecha' => '2026-09-28'];
$resPdf = $controlador->exportarPdf();
$pdfContenido = $resPdf->obtenerContenido();
afirmar(
    $resPdf->obtenerCodigoEstado() === 200
    && str_starts_with($pdfContenido, '%PDF-'),
    'Caso 10: GET /reportes/exportar/pdf genera binario PDF oficial mediante DOCUMENTOS-1'
);

echo "\n====================================================================\n";
echo " RESULTADOS E2E REPORTES-1: {$pasadas}/{$total} PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
}
echo "====================================================================\n";

if ($fallidas > 0) {
    exit(1);
}
