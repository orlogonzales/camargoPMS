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

// Carga y registro del autocargador PSR-4 propio
require_once RUTA_APP . '/Nucleo/Autocargador.php';
$autocargador = new \CamargoPMS\Nucleo\Autocargador(RUTA_APP);
$autocargador->registrar();

// Carga de utilidades y funciones auxiliares globales
require_once RUTA_APP . '/Nucleo/Ayudante.php';
require_once RUTA_APP . '/Nucleo/Funciones.php';

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

// Registro de rutas mínimas de UI-0
$enrutador->get('/', [\CamargoPMS\Controladores\PanelControlador::class, 'inicio']);
$enrutador->definir404([\CamargoPMS\Controladores\PanelControlador::class, 'paginaNoEncontrada']);

// Despacho de la petición HTTP
try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    $respuesta = $enrutador->despachar($metodo, $uri);
    $respuesta->enviar();
} catch (\Throwable $error) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Error del Servidor — Camargo PMS</title></head><body>';
    echo '<div style="font-family:sans-serif;padding:2rem;text-align:center;">';
    echo '<h2>Error del Servidor</h2>';
    echo '<p>Ha ocurrido un problema al procesar la solicitud en Camargo PMS.</p>';
    if (ini_get('display_errors')) {
        echo '<pre style="text-align:left;background:#f8f9fa;padding:1rem;border-radius:4px;border:1px solid #ddd;max-width:800px;margin:1rem auto;overflow:auto;">';
        echo htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '</pre>';
    }
    echo '</div></body></html>';
}
