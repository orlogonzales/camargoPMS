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
ini_set('display_errors', '0');

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

// Rutas protegidas por autenticación y autorización
$enrutador->get('/', [\CamargoPMS\Controladores\PanelControlador::class, 'inicio'], [
    \CamargoPMS\Intermediarios\AutenticacionIntermediario::class,
]);

// Rutas de Gestión de Usuarios (USUARIOS-1)
$enrutador->get('/usuarios', [\CamargoPMS\Controladores\UsuarioControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.ver'),
]);
$enrutador->get('/usuarios/datos', [\CamargoPMS\Controladores\UsuarioControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.ver'),
]);
$enrutador->get('/usuarios/personas-disponibles', [\CamargoPMS\Controladores\UsuarioControlador::class, 'personasDisponibles'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.crear'),
]);
$enrutador->post('/usuarios', [\CamargoPMS\Controladores\UsuarioControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.crear'),
]);
$enrutador->get('/usuarios/{id}', [\CamargoPMS\Controladores\UsuarioControlador::class, 'detalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.ver'),
]);
$enrutador->patch('/usuarios/{id}/estado', [\CamargoPMS\Controladores\UsuarioControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.bloquear'),
]);
$enrutador->post('/usuarios/{id}/estado', [\CamargoPMS\Controladores\UsuarioControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.bloquear'),
]);
$enrutador->post('/usuarios/{id}/restablecer-clave', [\CamargoPMS\Controladores\UsuarioControlador::class, 'restablecerClave'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.editar'),
]);
$enrutador->post('/usuarios/{id}/roles', [\CamargoPMS\Controladores\UsuarioControlador::class, 'asignarRol'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.asignar'),
]);
$enrutador->delete('/usuarios/{id}/roles/{rolId}', [\CamargoPMS\Controladores\UsuarioControlador::class, 'revocarRol'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.revocar'),
]);
$enrutador->post('/usuarios/{id}/roles/{rolId}/eliminar', [\CamargoPMS\Controladores\UsuarioControlador::class, 'revocarRol'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.revocar'),
]);
$enrutador->post('/usuarios/{id}/roles/revocar', [\CamargoPMS\Controladores\UsuarioControlador::class, 'revocarRol'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.revocar'),
]);
$enrutador->get('/usuarios/{id}/sesiones', [\CamargoPMS\Controladores\UsuarioControlador::class, 'listarSesiones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.ver'),
]);
$enrutador->delete('/usuarios/{id}/sesiones/{sesionId}', [\CamargoPMS\Controladores\UsuarioControlador::class, 'cerrarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.editar'),
]);
$enrutador->post('/usuarios/{id}/sesiones/{sesionId}/cerrar', [\CamargoPMS\Controladores\UsuarioControlador::class, 'cerrarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.editar'),
]);
$enrutador->post('/usuarios/{id}/sesiones/cerrar-todas', [\CamargoPMS\Controladores\UsuarioControlador::class, 'cerrarTodasSesiones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('usuarios.editar'),
]);

// Rutas de Gestión de Menú (MENÚ-1)
$enrutador->get('/configuracion/menu', [\CamargoPMS\Controladores\MenuControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.ver'),
]);
$enrutador->get('/configuracion/menu/datos', [\CamargoPMS\Controladores\MenuControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.ver'),
]);
$enrutador->post('/configuracion/menu', [\CamargoPMS\Controladores\MenuControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->put('/configuracion/menu/{id}', [\CamargoPMS\Controladores\MenuControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->post('/configuracion/menu/{id}', [\CamargoPMS\Controladores\MenuControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->patch('/configuracion/menu/{id}/estado', [\CamargoPMS\Controladores\MenuControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->post('/configuracion/menu/{id}/estado', [\CamargoPMS\Controladores\MenuControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->post('/configuracion/menu/orden', [\CamargoPMS\Controladores\MenuControlador::class, 'actualizarOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->delete('/configuracion/menu/{id}', [\CamargoPMS\Controladores\MenuControlador::class, 'eliminar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);
$enrutador->post('/configuracion/menu/{id}/eliminar', [\CamargoPMS\Controladores\MenuControlador::class, 'eliminar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('menu.gestionar'),
]);

// Rutas de Administración de Roles y Permisos (ROLES-2)
$enrutador->get('/configuracion/roles', [\CamargoPMS\Controladores\RolControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.ver'),
]);
$enrutador->get('/configuracion/roles/datos', [\CamargoPMS\Controladores\RolControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.ver'),
]);
$enrutador->get('/configuracion/roles/permisos-catalogo', [\CamargoPMS\Controladores\RolControlador::class, 'catalogoPermisosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.ver'),
]);
$enrutador->post('/configuracion/roles', [\CamargoPMS\Controladores\RolControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.crear'),
]);
$enrutador->get('/configuracion/roles/{id}', [\CamargoPMS\Controladores\RolControlador::class, 'detalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.ver'),
]);
$enrutador->put('/configuracion/roles/{id}', [\CamargoPMS\Controladores\RolControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.editar'),
]);
$enrutador->post('/configuracion/roles/{id}', [\CamargoPMS\Controladores\RolControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.editar'),
]);
$enrutador->patch('/configuracion/roles/{id}/estado', [\CamargoPMS\Controladores\RolControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.editar'),
]);
$enrutador->post('/configuracion/roles/{id}/estado', [\CamargoPMS\Controladores\RolControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.editar'),
]);
$enrutador->put('/configuracion/roles/{id}/permisos', [\CamargoPMS\Controladores\RolControlador::class, 'guardarPermisos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.editar'),
]);
$enrutador->post('/configuracion/roles/{id}/permisos', [\CamargoPMS\Controladores\RolControlador::class, 'guardarPermisos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('roles.editar'),
]);

// Rutas de Configuración General del Sistema (CONFIGURACIÓN-1)
$enrutador->get('/configuracion/sistema', [\CamargoPMS\Controladores\ConfiguracionControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('configuracion.ver'),
]);
$enrutador->get('/configuracion/sistema/datos', [\CamargoPMS\Controladores\ConfiguracionControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('configuracion.ver'),
]);
$enrutador->post('/configuracion/sistema', [\CamargoPMS\Controladores\ConfiguracionControlador::class, 'guardar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('configuracion.editar'),
]);
$enrutador->put('/configuracion/sistema', [\CamargoPMS\Controladores\ConfiguracionControlador::class, 'guardar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('configuracion.editar'),
]);
$enrutador->post('/configuracion/sistema/restaurar', [\CamargoPMS\Controladores\ConfiguracionControlador::class, 'restaurar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('configuracion.editar'),
]);
$enrutador->post('/configuracion/sistema/{clave}/restaurar', [\CamargoPMS\Controladores\ConfiguracionControlador::class, 'restaurar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('configuracion.editar'),
]);

// Rutas de Maestro Central de Propiedades (PROPIEDADES-1)
$enrutador->get('/propiedades', [\CamargoPMS\Controladores\PropiedadControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.ver'),
]);
$enrutador->get('/propiedades/datos', [\CamargoPMS\Controladores\PropiedadControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.ver'),
]);
$enrutador->post('/propiedades', [\CamargoPMS\Controladores\PropiedadControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.crear'),
]);
$enrutador->get('/propiedades/{id}', [\CamargoPMS\Controladores\PropiedadControlador::class, 'detalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.ver'),
]);
$enrutador->get('/propiedades/{id}/perfil', [\CamargoPMS\Controladores\PropiedadControlador::class, 'perfil'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.ver'),
]);
$enrutador->put('/propiedades/{id}', [\CamargoPMS\Controladores\PropiedadControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.editar'),
]);
$enrutador->post('/propiedades/{id}', [\CamargoPMS\Controladores\PropiedadControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.editar'),
]);
$enrutador->patch('/propiedades/{id}/estado', [\CamargoPMS\Controladores\PropiedadControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.cambiar_estado'),
]);
$enrutador->post('/propiedades/{id}/estado', [\CamargoPMS\Controladores\PropiedadControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('propiedades.cambiar_estado'),
]);

// Rutas de Maestro Central de Unidades (UNIDADES-1)
$enrutador->get('/unidades', [\CamargoPMS\Controladores\UnidadControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.ver'),
]);
$enrutador->get('/unidades/datos', [\CamargoPMS\Controladores\UnidadControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.ver'),
]);
$enrutador->post('/unidades', [\CamargoPMS\Controladores\UnidadControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.crear'),
]);
$enrutador->get('/unidades/{id}', [\CamargoPMS\Controladores\UnidadControlador::class, 'detalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.ver'),
]);
$enrutador->get('/unidades/{id}/perfil', [\CamargoPMS\Controladores\UnidadControlador::class, 'perfil'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.ver'),
]);
$enrutador->put('/unidades/{id}', [\CamargoPMS\Controladores\UnidadControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.editar'),
]);
$enrutador->post('/unidades/{id}', [\CamargoPMS\Controladores\UnidadControlador::class, 'actualizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.editar'),
]);
$enrutador->patch('/unidades/{id}/estado', [\CamargoPMS\Controladores\UnidadControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.cambiar_estado'),
]);
$enrutador->post('/unidades/{id}/estado', [\CamargoPMS\Controladores\UnidadControlador::class, 'cambiarEstado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('unidades.cambiar_estado'),
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

    // Registro seguro en log del sistema sin exponer detalles al usuario
    error_log((string) $error);

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
}
