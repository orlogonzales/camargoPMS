<?php

declare(strict_types=1);

/**
 * Camargo PMS — Front Controller
 *
 * Punto de entrada único para las peticiones HTTP del sistema.
 * Inicializa el autocargador, configura el entorno de forma segura,
 * registra las rutas base y delega la ejecución en el enrutador.
 */

define('CAMARGO_INICIO', microtime(true));
define('RUTA_RAIZ', dirname(__DIR__));
define('RUTA_APP', RUTA_RAIZ . DIRECTORY_SEPARATOR . 'app');
define('RUTA_PUBLIC', __DIR__);

// Configuración defensiva de errores
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Carga del autocargador PSR-4 (Composer con fallback nativo)
$archivoAutoload = RUTA_RAIZ . '/vendor/autoload.php';
if (file_exists($archivoAutoload)) {
    require_once $archivoAutoload;
} else {
    require_once RUTA_APP . '/Nucleo/Autocargador.php';
    $autocargador = new \CamargoPMS\Nucleo\Autocargador(RUTA_APP);
    $autocargador->registrar();
    require_once RUTA_APP . '/Nucleo/Ayudante.php';
    require_once RUTA_APP . '/Nucleo/Funciones.php';
}

// Carga centralizada de variables de entorno y configuración
\CamargoPMS\Nucleo\Configuracion::cargar(RUTA_RAIZ);

// Detección dinámica y robusta de la URL base
$uriPeticion = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uriPeticion, $scriptDir)) {
    // Acceso directo a través de subcarpeta incluyendo /public
    $urlBase = $scriptDir;
} elseif (str_ends_with($scriptDir, '/public') && str_starts_with($uriPeticion, substr($scriptDir, 0, -7))) {
    // Acceso reescrito por .htaccess desde la raíz del proyecto (ej. /app.camargo-pms/)
    $urlBase = substr($scriptDir, 0, -7);
} else {
    // Entorno VirtualHost con DocumentRoot en public (ej. https://app.camargo-pms.test/)
    $urlBase = '';
}

$urlBase = rtrim($urlBase, '/');
\CamargoPMS\Nucleo\Ayudante::definirUrlBase($urlBase);

// Inicialización del Enrutador
$enrutador = new \CamargoPMS\Nucleo\Enrutador();

// Registro de rutas del sistema
$enrutador->get('/login', [\CamargoPMS\Controladores\AutenticacionControlador::class, 'mostrarLogin']);
$enrutador->post('/login', [\CamargoPMS\Controladores\AutenticacionControlador::class, 'procesarLogin']);
$enrutador->post('/logout', [\CamargoPMS\Controladores\AutenticacionControlador::class, 'cerrarSesion']);

// Rutas protegidas por autenticación
$enrutador->get('/', [\CamargoPMS\Controladores\PanelControlador::class, 'inicio'], [
    \CamargoPMS\Intermediarios\AutenticacionIntermediario::class,
]);

$enrutador->definir404([\CamargoPMS\Controladores\PanelControlador::class, 'paginaNoEncontrada']);

// Despacho de la petición HTTP
try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    $respuesta = $enrutador->despachar($metodo, $uri);
    $respuesta->enviar();
} catch (\Throwable $error) {
    // Manejo defensivo de error 500 separando vista segura de diagnóstico técnico
    http_response_code(500);

    try {
        $controlador = new \CamargoPMS\Controladores\PanelControlador();
        $respuesta500 = $controlador->error(500);
        $respuesta500->enviar();
    } catch (\Throwable $errorVista) {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Error 500 — Camargo PMS</title></head><body>';
        echo '<div style="font-family:sans-serif;padding:3rem;text-align:center;">';
        echo '<h2>Error del Servidor (500)</h2>';
        echo '<p>Ha ocurrido una falla inesperada en Camargo PMS.</p>';
        echo '</div></body></html>';
    }

    if (ini_get('display_errors')) {
        echo '<div style="max-width:850px;margin:2rem auto;padding:1rem;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;font-family:monospace;font-size:13px;color:#856404;">';
        echo '<strong>Detalle de diagnóstico (visible solo en desarrollo local):</strong><br>';
        echo htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '</div>';
    }
}
