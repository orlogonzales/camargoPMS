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

// Rutas de Disponibilidad e Inventario Diario (DISPONIBILIDAD-1)
$enrutador->get('/disponibilidad', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);
$enrutador->get('/disponibilidad/consultar', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'consultar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);
$enrutador->post('/disponibilidad/consultar', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'consultar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);
$enrutador->post('/disponibilidad/bloquear', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'bloquear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.bloquear'),
]);
$enrutador->post('/disponibilidad/liberar', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'liberar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.liberar'),
]);
$enrutador->get('/disponibilidad/bloqueos', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'bloqueosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);
$enrutador->get('/disponibilidad/matriz', [\CamargoPMS\Controladores\DisponibilidadControlador::class, 'matrizJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);

// Rutas de Reservas Directas (RESERVAS-1)
$enrutador->get('/reservas', [\CamargoPMS\Controladores\ReservaControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.ver'),
]);
$enrutador->get('/reservas/datos', [\CamargoPMS\Controladores\ReservaControlador::class, 'datos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.ver'),
]);
$enrutador->get('/reservas/auxiliares', [\CamargoPMS\Controladores\ReservaControlador::class, 'auxiliares'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.ver'),
]);
$enrutador->post('/reservas/expirar', [\CamargoPMS\Controladores\ReservaControlador::class, 'expirar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.expirar'),
]);
$enrutador->post('/reservas', [\CamargoPMS\Controladores\ReservaControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.crear'),
]);
$enrutador->get('/reservas/{id}', [\CamargoPMS\Controladores\ReservaControlador::class, 'detalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.ver'),
]);
$enrutador->post('/reservas/{id}/confirmar', [\CamargoPMS\Controladores\ReservaControlador::class, 'confirmar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.confirmar'),
]);
$enrutador->post('/reservas/{id}/cancelar', [\CamargoPMS\Controladores\ReservaControlador::class, 'cancelar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reservas.cancelar'),
]);

// Rutas de Estadías y Ocupación Física (ESTADÍAS-1)
$enrutador->get('/estadias', [\CamargoPMS\Controladores\EstadiaControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.ver'),
]);
$enrutador->get('/estadias/datos', [\CamargoPMS\Controladores\EstadiaControlador::class, 'datos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.ver'),
]);
$enrutador->get('/estadias/auxiliares', [\CamargoPMS\Controladores\EstadiaControlador::class, 'auxiliares'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.ver'),
]);
$enrutador->get('/estadias/elegibles/{reservaId}', [\CamargoPMS\Controladores\EstadiaControlador::class, 'elegibles'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.ver'),
]);
$enrutador->get('/estadias/resumen-reserva/{reservaId}', [\CamargoPMS\Controladores\EstadiaControlador::class, 'resumenReserva'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.ver'),
]);
$enrutador->post('/estadias/checkin', [\CamargoPMS\Controladores\EstadiaControlador::class, 'checkin'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.checkin'),
]);
$enrutador->get('/estadias/{id}', [\CamargoPMS\Controladores\EstadiaControlador::class, 'detalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.ver'),
]);
$enrutador->post('/estadias/{id}/checkout', [\CamargoPMS\Controladores\EstadiaControlador::class, 'checkout'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.checkout'),
]);
$enrutador->post('/estadias/{id}/anular', [\CamargoPMS\Controladores\EstadiaControlador::class, 'anular'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.anular'),
]);
$enrutador->post('/estadias/{id}/huespedes', [\CamargoPMS\Controladores\EstadiaControlador::class, 'actualizarHuespedes'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('estadias.huespedes'),
]);

// ----------------------------------------------------------------------------
// Rutas de Catálogo de Servicios, Proveedores, Consumos y Traslados (SERVICIOS-1)
// ----------------------------------------------------------------------------
$enrutador->get('/servicios', [\CamargoPMS\Controladores\ServicioControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->get('/servicios/auxiliares', [\CamargoPMS\Controladores\ServicioControlador::class, 'auxiliares'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->get('/servicios/categorias', [\CamargoPMS\Controladores\ServicioControlador::class, 'listarCategorias'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->get('/servicios/modalidades', [\CamargoPMS\Controladores\ServicioControlador::class, 'listarModalidades'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);

// Consumos y Contrataciones
$enrutador->get('/servicios/contratados', [\CamargoPMS\Controladores\ServicioControlador::class, 'listarContratados'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->get('/servicios/contratados/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'obtenerContratado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->post('/servicios/contratados', [\CamargoPMS\Controladores\ServicioControlador::class, 'contratar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.contratar'),
]);
$enrutador->post('/servicios/contratados/{id}/confirmar', [\CamargoPMS\Controladores\ServicioControlador::class, 'confirmar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.contratar'),
]);
$enrutador->post('/servicios/contratados/{id}/ejecutar', [\CamargoPMS\Controladores\ServicioControlador::class, 'ejecutar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ejecutar'),
]);
$enrutador->post('/servicios/contratados/{id}/cancelar', [\CamargoPMS\Controladores\ServicioControlador::class, 'cancelar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.cancelar'),
]);

// Catálogo Maestro
$enrutador->get('/servicios/catalogo', [\CamargoPMS\Controladores\ServicioControlador::class, 'listarCatalogo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->get('/servicios/catalogo/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'obtenerServicio'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->post('/servicios/catalogo', [\CamargoPMS\Controladores\ServicioControlador::class, 'crearServicio'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->post('/servicios/catalogo/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'actualizarServicio'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->put('/servicios/catalogo/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'actualizarServicio'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->post('/servicios/catalogo/{id}/estado', [\CamargoPMS\Controladores\ServicioControlador::class, 'cambiarEstadoServicio'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->post('/servicios/catalogo/{id}/proveedores', [\CamargoPMS\Controladores\ServicioControlador::class, 'homologarProveedor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);

// Proveedores
$enrutador->get('/servicios/proveedores', [\CamargoPMS\Controladores\ServicioControlador::class, 'listarProveedores'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->get('/servicios/proveedores/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'obtenerProveedor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.ver'),
]);
$enrutador->post('/servicios/proveedores', [\CamargoPMS\Controladores\ServicioControlador::class, 'crearProveedor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->post('/servicios/proveedores/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'actualizarProveedor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->put('/servicios/proveedores/{id}', [\CamargoPMS\Controladores\ServicioControlador::class, 'actualizarProveedor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);
$enrutador->post('/servicios/proveedores/{id}/estado', [\CamargoPMS\Controladores\ServicioControlador::class, 'cambiarEstadoProveedor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('servicios.gestionar'),
]);

// =========================================================================
// Rutas de Caja, Cuentas y Cobros (FINANCIERO-2)
// =========================================================================
$enrutador->get('/caja', [\CamargoPMS\Controladores\CajaControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);
$enrutador->get('/caja/auxiliares', [\CamargoPMS\Controladores\CajaControlador::class, 'auxiliares'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);

// Cuentas y Folios
$enrutador->get('/caja/folios', [\CamargoPMS\Controladores\CajaControlador::class, 'listarFolios'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);
$enrutador->get('/caja/folios/{id}', [\CamargoPMS\Controladores\CajaControlador::class, 'obtenerEstadoCuenta'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);
$enrutador->get('/caja/folios/por-reserva/{id}', [\CamargoPMS\Controladores\CajaControlador::class, 'obtenerFolioPorReserva'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);

// Sesión de Caja y Arqueo
$enrutador->get('/caja/sesion-activa', [\CamargoPMS\Controladores\CajaControlador::class, 'obtenerSesionActiva'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);
$enrutador->get('/caja/sesion/{id}', [\CamargoPMS\Controladores\CajaControlador::class, 'obtenerSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.ver'),
]);
$enrutador->post('/caja/sesion/abrir', [\CamargoPMS\Controladores\CajaControlador::class, 'aperturarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.aperturar'),
]);
$enrutador->post('/caja/aperturar', [\CamargoPMS\Controladores\CajaControlador::class, 'aperturarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.aperturar'),
]);
$enrutador->post('/caja/sesion/cerrar', [\CamargoPMS\Controladores\CajaControlador::class, 'cerrarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.cerrar'),
]);
$enrutador->post('/caja/cerrar', [\CamargoPMS\Controladores\CajaControlador::class, 'cerrarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.cerrar'),
]);
$enrutador->post('/caja/movimientos', [\CamargoPMS\Controladores\CajaControlador::class, 'registrarMovimiento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.movimientos'),
]);

// Cobros / Pagos y Aplicaciones
$enrutador->post('/caja/cobros', [\CamargoPMS\Controladores\CajaControlador::class, 'registrarPago'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.cobrar'),
]);
$enrutador->post('/caja/pagos', [\CamargoPMS\Controladores\CajaControlador::class, 'registrarPago'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.cobrar'),
]);
$enrutador->post('/caja/aplicaciones', [\CamargoPMS\Controladores\CajaControlador::class, 'aplicarPago'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.aplicar'),
]);
$enrutador->post('/caja/pagos/{id}/reversar', [\CamargoPMS\Controladores\CajaControlador::class, 'reversarPago'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.reversar'),
]);
$enrutador->post('/caja/devoluciones', [\CamargoPMS\Controladores\CajaControlador::class, 'registrarDevolucion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('caja.devolver'),
]);

// ============================================================================
// Rutas de Arrendamientos de Mediana y Larga Estancia (ARRENDAMIENTOS-1 / D-076)
// ============================================================================
$enrutador->get('/arrendamientos', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.ver'),
]);
$enrutador->get('/arrendamientos/listar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'listar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.ver'),
]);
$enrutador->get('/api/arrendamientos', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'listar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.ver'),
]);
$enrutador->post('/api/arrendamientos', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'crear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.crear'),
]);
$enrutador->get('/api/arrendamientos/{id}', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'obtenerDetalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.ver'),
]);
$enrutador->post('/api/arrendamientos/{id}/activar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'activar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.activar'),
]);
$enrutador->post('/api/arrendamientos/{id}/personas', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'agregarPersona'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.gestionar'),
]);
$enrutador->post('/api/arrendamientos/{id}/personas/{personaId}/eliminar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'quitarPersona'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.gestionar'),
]);
$enrutador->post('/api/arrendamientos/{id}/prorrogar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'prorrogar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.gestionar'),
]);
$enrutador->post('/api/arrendamientos/{id}/rescindir', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'rescindir'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.rescindir'),
]);
$enrutador->post('/api/arrendamientos/{id}/finalizar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'finalizar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.rescindir'),
]);
$enrutador->post('/api/arrendamientos/{id}/cancelar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'cancelarBorrador'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.crear'),
]);
$enrutador->post('/api/arrendamientos/{id}/cuotas', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'generarCuota'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.generar_cargos'),
]);
$enrutador->post('/api/arrendamientos/{id}/garantia/recibir', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'recibirGarantia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.generar_cargos'),
]);
$enrutador->post('/api/arrendamientos/{id}/garantia/compensar', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'compensarGarantia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.generar_cargos'),
]);
$enrutador->post('/api/arrendamientos/{id}/garantia/devolver', [\CamargoPMS\Controladores\ArrendamientoControlador::class, 'devolverGarantia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('arrendamientos.generar_cargos'),
]);

// ============================================================================
// Rutas de Mantenimiento e Incidencias Técnicas (MANTENIMIENTO-1 / D-077)
// ============================================================================
$enrutador->get('/mantenimiento', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);

// Incidencias
$enrutador->get('/api/mantenimiento/incidencias', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'listarIncidencias'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);
$enrutador->get('/api/mantenimiento/incidencias/{id}', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'obtenerIncidencia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);
$enrutador->post('/api/mantenimiento/incidencias', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'reportarIncidencia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.incidencias.reportar'),
]);
$enrutador->post('/api/mantenimiento/incidencias/{id}/evaluar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'evaluarIncidencia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.incidencias.gestionar'),
]);
$enrutador->post('/api/mantenimiento/incidencias/{id}/resolver-directa', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'resolverIncidenciaDirecta'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.incidencias.gestionar'),
]);
$enrutador->post('/api/mantenimiento/incidencias/{id}/desestimar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'desestimarIncidencia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.incidencias.gestionar'),
]);

// Órdenes de Trabajo
$enrutador->get('/api/mantenimiento/ordenes', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'listarOrdenes'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);
$enrutador->get('/api/mantenimiento/ordenes/{id}', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'obtenerOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);
$enrutador->post('/api/mantenimiento/ordenes', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'crearOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.crear'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/programar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'programarOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.programar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/iniciar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'iniciarEjecucion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.ejecutar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/estado', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'iniciarEjecucion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.ejecutar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/costos', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'registrarCostos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.ejecutar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/completar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'completarOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.cerrar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/cancelar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'cancelarOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.cancelar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/prorrogar', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'prorrogarBloqueo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.programar'),
]);
$enrutador->post('/api/mantenimiento/ordenes/{id}/prorrogar-bloqueo', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'prorrogarBloqueo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ordenes.programar'),
]);

// Estadísticas e Historial
$enrutador->get('/api/mantenimiento/estadisticas', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'obtenerEstadisticas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);
$enrutador->get('/api/mantenimiento/{tipo}/{id}/historial', [\CamargoPMS\Controladores\MantenimientoControlador::class, 'obtenerHistorial'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('mantenimiento.ver'),
]);

// =========================================================================
// Rutas de Inventario Físico, Existencias, Kardex y Activos (INVENTARIO-1 / D-078)
// =========================================================================
$enrutador->get('/inventario', [\CamargoPMS\Controladores\InventarioControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);

// Artículos
$enrutador->get('/api/inventario/articulos', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiListarArticulos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);
$enrutador->post('/api/inventario/articulos', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiCrearArticulo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.articulos.gestionar'),
]);
$enrutador->put('/api/inventario/articulos/{id}', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiActualizarArticulo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.articulos.gestionar'),
]);
$enrutador->post('/api/inventario/articulos/{id}', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiActualizarArticulo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.articulos.gestionar'),
]);

// Ubicaciones
$enrutador->get('/api/inventario/ubicaciones', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiListarUbicaciones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);
$enrutador->post('/api/inventario/ubicaciones', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiCrearUbicacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ubicaciones.gestionar'),
]);
$enrutador->put('/api/inventario/ubicaciones/{id}', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiActualizarUbicacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ubicaciones.gestionar'),
]);
$enrutador->post('/api/inventario/ubicaciones/{id}', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiActualizarUbicacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ubicaciones.gestionar'),
]);

// Existencias
$enrutador->get('/api/inventario/existencias', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiListarExistencias'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);

// Movimientos y Kardex
$enrutador->get('/api/inventario/movimientos', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiListarMovimientos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);
$enrutador->post('/api/inventario/movimientos/saldo-inicial', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiRegistrarSaldoInicial'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.movimientos.registrar'),
]);
$enrutador->post('/api/inventario/movimientos/entrada', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiRegistrarEntrada'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.movimientos.registrar'),
]);
$enrutador->post('/api/inventario/movimientos/consumo', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiRegistrarConsumo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.movimientos.registrar'),
]);
$enrutador->post('/api/inventario/movimientos/mantenimiento', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiRegistrarMantenimiento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.movimientos.registrar'),
]);
$enrutador->post('/api/inventario/movimientos/traslado', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiRegistrarTraslado'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.traslados.ejecutar'),
]);
$enrutador->post('/api/inventario/movimientos/ajuste', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiRegistrarAjuste'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.movimientos.registrar'),
]);
$enrutador->post('/api/inventario/movimientos/{id}/reverso', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiReversarMovimiento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.movimientos.registrar'),
]);

// Activos Serializados
$enrutador->get('/api/inventario/activos', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiListarActivos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);
$enrutador->post('/api/inventario/activos', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiCrearActivo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.activos.gestionar'),
]);
$enrutador->post('/api/inventario/activos/{id}/asignar', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiAsignarActivo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.activos.gestionar'),
]);
$enrutador->post('/api/inventario/activos/{id}/transferir', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiTransferirActivo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.activos.gestionar'),
]);
$enrutador->post('/api/inventario/activos/{id}/baja', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiDarDeBajaActivo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.activos.gestionar'),
]);

// Dotaciones Estándar
$enrutador->get('/api/inventario/dotaciones', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiListarDotaciones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);
$enrutador->post('/api/inventario/dotaciones', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiGuardarDotacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.dotaciones.gestionar'),
]);
$enrutador->delete('/api/inventario/dotaciones/{id}', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiEliminarDotacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.dotaciones.gestionar'),
]);
$enrutador->post('/api/inventario/dotaciones/{id}/eliminar', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiEliminarDotacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.dotaciones.gestionar'),
]);
$enrutador->get('/api/inventario/dotaciones/auditoria/{unidadId}', [\CamargoPMS\Controladores\InventarioControlador::class, 'apiAuditarDotacionUnidad'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('inventario.ver'),
]);

// =========================================================================
// Rutas de Motor Documental, Plantillas y Generación PDF (DOCUMENTOS-1 / D-079)
// =========================================================================

// Vista principal Alina D-075
$enrutador->get('/documentos', [\CamargoPMS\Controladores\DocumentoControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.ver'),
]);

// API: Documentos emitidos
$enrutador->get('/api/documentos', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiListarDocumentos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.ver'),
]);
$enrutador->get('/api/documentos/incidencias', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiListarIncidencias'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.ver'),
]);

// API: Plantillas y Versiones
$enrutador->get('/api/documentos/plantillas', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiListarPlantillas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.ver'),
]);
$enrutador->post('/api/documentos/plantillas', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiCrearPlantilla'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.plantillas.gestionar'),
]);
$enrutador->get('/api/documentos/plantillas/{id}/versiones', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiListarVersiones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.ver'),
]);
$enrutador->post('/api/documentos/plantillas/{id}/versiones', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiCrearVersion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.plantillas.gestionar'),
]);
$enrutador->post('/api/documentos/plantillas/{id}/versiones/{versionId}/activar', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiActivarVersion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.plantillas.gestionar'),
]);

// API: Emisión de Contratos
$enrutador->post('/api/arrendamientos/{id}/emitir-contrato', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiEmitirContratoArrendamiento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.emitir'),
]);

// API: Operaciones sobre Documentos Emitidos
$enrutador->get('/api/documentos/{id}/descargar', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiDescargarDocumento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.descargar'),
]);
$enrutador->get('/api/documentos/{id}/verificar', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiVerificarDocumento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.ver'),
]);
$enrutador->post('/api/documentos/{id}/regenerar', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiRegenerarDocumento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.regenerar'),
]);
$enrutador->post('/api/documentos/{id}/anular', [\CamargoPMS\Controladores\DocumentoControlador::class, 'apiAnularDocumento'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('documentos.anular'),
]);

// =========================================================================
// Rutas de Abastecimiento, Compras y Cuentas por Pagar (COMPRAS-1 / D-080)
// =========================================================================

// Vista principal Alina D-075
$enrutador->get('/compras', [\CamargoPMS\Controladores\CompraControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);

// API: Catálogos
$enrutador->get('/api/compras/catalogos', [\CamargoPMS\Controladores\CompraControlador::class, 'apiCatalogos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);

// API: Solicitudes de Compra
$enrutador->get('/api/compras/solicitudes', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarSolicitudes'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/solicitudes', [\CamargoPMS\Controladores\CompraControlador::class, 'apiCrearSolicitud'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.solicitudes.crear'),
]);
$enrutador->get('/api/compras/solicitudes/{id}', [\CamargoPMS\Controladores\CompraControlador::class, 'apiObtenerSolicitud'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/solicitudes/{id}/aprobar', [\CamargoPMS\Controladores\CompraControlador::class, 'apiAprobarSolicitud'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.solicitudes.aprobar'),
]);
$enrutador->post('/api/compras/solicitudes/{id}/rechazar', [\CamargoPMS\Controladores\CompraControlador::class, 'apiRechazarSolicitud'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.solicitudes.aprobar'),
]);

// API: Órdenes de Compra
$enrutador->get('/api/compras/ordenes', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarOrdenes'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/ordenes', [\CamargoPMS\Controladores\CompraControlador::class, 'apiCrearOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ordenes.crear'),
]);
$enrutador->get('/api/compras/ordenes/{id}', [\CamargoPMS\Controladores\CompraControlador::class, 'apiObtenerOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/ordenes/{id}/aprobar', [\CamargoPMS\Controladores\CompraControlador::class, 'apiAprobarOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ordenes.aprobar'),
]);
$enrutador->post('/api/compras/ordenes/{id}/cancelar', [\CamargoPMS\Controladores\CompraControlador::class, 'apiCancelarOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.anular'),
]);
$enrutador->get('/api/compras/ordenes/{id}/pdf', [\CamargoPMS\Controladores\CompraControlador::class, 'descargarPdfOrden'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);

// API: Recepciones Físicas en Almacén
$enrutador->get('/api/compras/ordenes/{id}/recepciones', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarRecepciones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/recepciones', [\CamargoPMS\Controladores\CompraControlador::class, 'apiRegistrarRecepcion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.recepciones.registrar'),
]);
$enrutador->get('/api/compras/recepciones/{id}', [\CamargoPMS\Controladores\CompraControlador::class, 'apiObtenerRecepcion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);

// API: Conformidades de Servicio
$enrutador->get('/api/compras/ordenes/{id}/conformidades', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarConformidades'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/conformidades', [\CamargoPMS\Controladores\CompraControlador::class, 'apiRegistrarConformidad'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.conformidad.registrar'),
]);

// API: Comprobantes y Matching
$enrutador->get('/api/compras/ordenes/{id}/comprobantes', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarComprobantes'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.ver'),
]);
$enrutador->post('/api/compras/comprobantes', [\CamargoPMS\Controladores\CompraControlador::class, 'apiRegistrarComprobante'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.comprobantes.registrar'),
]);

// API: Cuentas por Pagar y Pagos
$enrutador->get('/api/compras/cuentas-por-pagar', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarCuentasPorPagar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.cuentas_pagar.ver'),
]);
$enrutador->get('/api/compras/cuentas-por-pagar/{id}', [\CamargoPMS\Controladores\CompraControlador::class, 'apiObtenerCuentaPorPagar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.cuentas_pagar.ver'),
]);
$enrutador->get('/api/compras/cuentas-por-pagar/{id}/pagos', [\CamargoPMS\Controladores\CompraControlador::class, 'apiListarPagosCxp'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.cuentas_pagar.ver'),
]);
$enrutador->post('/api/compras/cuentas-por-pagar/{id}/pagos', [\CamargoPMS\Controladores\CompraControlador::class, 'apiRegistrarPagoCxp'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('compras.pagos.registrar'),
]);

// =========================================================================
// Rutas de Suministros y Servicios Periódicos (SUMINISTROS-1 / D-081)
// =========================================================================
$enrutador->get('/suministros', [\CamargoPMS\Controladores\SuministroControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->get('/api/suministros/catalogos', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiCatalogos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->get('/api/suministros', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiListarSuministros'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->post('/api/suministros', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiCrearSuministro'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.gestionar'),
]);
$enrutador->get('/api/suministros/{id}', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiObtenerSuministro'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->post('/api/suministros/{id}', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiActualizarSuministro'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.gestionar'),
]);
$enrutador->put('/api/suministros/{id}', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiActualizarSuministro'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.gestionar'),
]);

// Tarifas
$enrutador->get('/api/suministros/{id}/tarifas', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiListarTarifas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->post('/api/suministros/tarifas', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiCrearTarifa'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.tarifas.gestionar'),
]);

// Medidores
$enrutador->get('/api/suministros/medidores', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiListarMedidores'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->post('/api/suministros/medidores', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiInstalarMedidor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.medidores.gestionar'),
]);
$enrutador->post('/api/suministros/medidores/{id}/reemplazar', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiReemplazarMedidor'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.medidores.gestionar'),
]);

// Lecturas
$enrutador->get('/api/suministros/medidores/{id}/lecturas', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiListarLecturas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->post('/api/suministros/lecturas', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiRegistrarLectura'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.lecturas.registrar'),
]);
$enrutador->post('/api/suministros/lecturas/{id}/corregir', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiCorregirLectura'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.lecturas.corregir'),
]);

// Liquidaciones
$enrutador->get('/api/suministros/liquidaciones', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiListarLiquidaciones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->get('/api/suministros/liquidaciones/{id}', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiObtenerLiquidacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.ver'),
]);
$enrutador->post('/api/suministros/liquidaciones', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiLiquidarPeriodo'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.liquidar'),
]);
$enrutador->post('/api/suministros/liquidaciones/{id}/anular', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiAnularLiquidacion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.anular'),
]);
$enrutador->post('/api/suministros/liquidaciones/{id}/reliquidar', [\CamargoPMS\Controladores\SuministroControlador::class, 'apiReliquidar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('suministros.liquidar'),
]);

// =========================================================================
// Rutas de Recibos de Cobranza y Snapshots Históricos (RECIBOS-1 / D-082)
// =========================================================================
$enrutador->get('/recibos', [\CamargoPMS\Controladores\ReciboControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.ver'),
]);
$enrutador->get('/api/recibos/catalogos', [\CamargoPMS\Controladores\ReciboControlador::class, 'apiCatalogos'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.ver'),
]);
$enrutador->get('/api/recibos', [\CamargoPMS\Controladores\ReciboControlador::class, 'apiListar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.ver'),
]);
$enrutador->get('/api/recibos/{id}', [\CamargoPMS\Controladores\ReciboControlador::class, 'apiObtener'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.ver'),
]);
$enrutador->post('/api/recibos/emitir', [\CamargoPMS\Controladores\ReciboControlador::class, 'apiEmitir'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.emitir'),
]);
$enrutador->post('/api/recibos/{id}/anular', [\CamargoPMS\Controladores\ReciboControlador::class, 'apiAnular'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.anular'),
]);
$enrutador->get('/api/recibos/{id}/pdf', [\CamargoPMS\Controladores\ReciboControlador::class, 'descargarPdf'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.descargar'),
]);
$enrutador->get('/recibos/{id}/pdf', [\CamargoPMS\Controladores\ReciboControlador::class, 'descargarPdf'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.descargar'),
]);
$enrutador->get('/api/recibos/{id}/verificar-hash', [\CamargoPMS\Controladores\ReciboControlador::class, 'apiVerificarHash'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('recibos.ver'),
]);

// =========================================================================
// Rutas de Housekeeping, Pisos y Control de Lencería (HOUSEKEEPING-1 / D-083)
// =========================================================================
$enrutador->get('/housekeeping', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.ver'),
]);
$enrutador->get('/api/housekeeping/rack', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiRackOperacional'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.ver'),
]);
$enrutador->get('/api/housekeeping/tareas', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiListarTareas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.ver'),
]);
$enrutador->get('/api/housekeeping/tareas/{id}', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiDetalleTarea'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.ver'),
]);
$enrutador->post('/api/housekeeping/tareas', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiCrearTarea'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.tareas.gestionar'),
]);
$enrutador->post('/api/housekeeping/tareas/{id}/asignar', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiAsignarTarea'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.tareas.gestionar'),
]);
$enrutador->post('/api/housekeeping/tareas/{id}/iniciar', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiIniciarLimpieza'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.limpieza.ejecutar'),
]);
$enrutador->post('/api/housekeeping/tareas/{id}/finalizar', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiFinalizarLimpieza'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.limpieza.ejecutar'),
]);
$enrutador->post('/api/housekeeping/tareas/{id}/inspeccionar', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiInspeccionarTarea'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.inspeccion.ejecutar'),
]);
$enrutador->post('/api/housekeeping/tareas/{id}/desperfecto', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiReportarDesperfecto'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.limpieza.ejecutar'),
]);
$enrutador->get('/api/housekeeping/lavanderia/lotes', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiListarLotesLavanderia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.lavanderia.gestionar'),
]);
$enrutador->get('/api/housekeeping/lavanderia/lotes/{id}', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiDetalleLoteLavanderia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.lavanderia.gestionar'),
]);
$enrutador->post('/api/housekeeping/lavanderia/despachar', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiDespacharLoteLavanderia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.lavanderia.gestionar'),
]);
$enrutador->post('/api/housekeeping/lavanderia/lotes/{id}/recibir', [\CamargoPMS\Controladores\HousekeepingControlador::class, 'apiRecibirLoteLavanderia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('housekeeping.lavanderia.gestionar'),
]);

// =========================================================================
// Rutas de Centro Operacional de Recepción / Tape Chart (TAPE-CHART-1 / D-084)
// =========================================================================
$enrutador->get('/tape-chart', [\CamargoPMS\Controladores\TapeChartControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);
$enrutador->get('/tape-chart/datos', [\CamargoPMS\Controladores\TapeChartControlador::class, 'datosJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);
$enrutador->get('/tape-chart/rack-hoy', [\CamargoPMS\Controladores\TapeChartControlador::class, 'rackHoyJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('disponibilidad.ver'),
]);

// =========================================================================
// Rutas del Módulo de Gastos Operativos y Egresos (GASTOS-1 / D-086)
// =========================================================================
$enrutador->get('/gastos', [\CamargoPMS\Controladores\GastoControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.ver'),
]);
$enrutador->get('/api/gastos', [\CamargoPMS\Controladores\GastoControlador::class, 'apiListar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.ver'),
]);
$enrutador->get('/api/gastos/categorias', [\CamargoPMS\Controladores\GastoControlador::class, 'apiCategorias'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.ver'),
]);
$enrutador->get('/api/gastos/{id}', [\CamargoPMS\Controladores\GastoControlador::class, 'apiDetalle'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.ver'),
]);
$enrutador->post('/api/gastos', [\CamargoPMS\Controladores\GastoControlador::class, 'apiCrear'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.crear'),
]);
$enrutador->post('/api/gastos/{id}/aprobar', [\CamargoPMS\Controladores\GastoControlador::class, 'apiAprobar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.aprobar'),
]);
$enrutador->post('/api/gastos/{id}/anular', [\CamargoPMS\Controladores\GastoControlador::class, 'apiAnular'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.anular'),
]);
$enrutador->post('/api/gastos/{id}/pagar', [\CamargoPMS\Controladores\GastoControlador::class, 'apiPagar'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.pagar'),
]);
$enrutador->post('/api/gastos/{id}/evidencias', [\CamargoPMS\Controladores\GastoControlador::class, 'apiAdjuntarEvidencia'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.crear'),
]);
$enrutador->post('/api/gastos/pagos/{id}/reversar', [\CamargoPMS\Controladores\GastoControlador::class, 'apiReversarPago'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('gastos.pagar'),
]);

// Rutas de Reportes Analíticos y Gerenciales (REPORTES-1 / D-087)
$enrutador->get('/reportes', [\CamargoPMS\Controladores\ReporteControlador::class, 'index'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.ver'),
]);
$enrutador->get('/api/reportes/diario', [\CamargoPMS\Controladores\ReporteControlador::class, 'diarioJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.operaciones'),
]);
$enrutador->get('/api/reportes/flujo-caja', [\CamargoPMS\Controladores\ReporteControlador::class, 'flujoCajaJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.finanzas'),
]);
$enrutador->get('/api/reportes/aging-cxc', [\CamargoPMS\Controladores\ReporteControlador::class, 'agingCxcJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.morosidad'),
]);
$enrutador->get('/api/reportes/aging-cxp', [\CamargoPMS\Controladores\ReporteControlador::class, 'agingCxpJson'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.morosidad'),
]);
$enrutador->get('/reportes/exportar/csv', [\CamargoPMS\Controladores\ReporteControlador::class, 'exportarCsv'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.exportar'),
]);
$enrutador->get('/reportes/exportar/pdf', [\CamargoPMS\Controladores\ReporteControlador::class, 'exportarPdf'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('reportes.exportar'),
]);

// Rutas de Monitoreo y Revocación Administrativa de Sesiones (SESIONES-1 / D-088)
$enrutador->get('/seguridad/sesiones', [\CamargoPMS\Controladores\SesionControlador::class, 'mostrarConsolaSesiones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('sesiones.ver'),
]);
$enrutador->get('/api/seguridad/sesiones', [\CamargoPMS\Controladores\SesionControlador::class, 'apiListarSesiones'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('sesiones.ver'),
]);
$enrutador->get('/api/seguridad/sesiones/metricas', [\CamargoPMS\Controladores\SesionControlador::class, 'apiMetricas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('sesiones.ver'),
]);
$enrutador->post('/api/seguridad/sesiones/{id}/revocar', [\CamargoPMS\Controladores\SesionControlador::class, 'apiRevocarSesion'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('sesiones.revocar'),
]);
$enrutador->post('/api/seguridad/sesiones/usuario/{usuarioId}/revocar-todas', [\CamargoPMS\Controladores\SesionControlador::class, 'apiRevocarUsuario'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('sesiones.revocar'),
]);
$enrutador->post('/api/seguridad/sesiones/purgar-expiradas', [\CamargoPMS\Controladores\SesionControlador::class, 'apiPurgarExpiradas'], [
    new \CamargoPMS\Intermediarios\AutorizacionIntermediario('sesiones.revocar'),
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
