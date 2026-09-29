#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Camargo PMS — Script CLI de Restablecimiento Administrativo de Contraseñas de Usuario.
 *
 * Propósito:
 * Permitir la recuperación y cambio de contraseñas de cuentas humanas de acceso mediante
 * ejecución administrativa local en servidor (CLI), preservando todas las garantías de seguridad
 * y auditoría inmutable de Camargo PMS (D-061, AUTH-1).
 *
 * Restricciones Vinculantes:
 * - Ejecutable exclusivamente bajo CLI (PHP_SAPI === 'cli').
 * - Prohibida cualquier invocación vía protocolo HTTP (retorna 403 y termina).
 * - NUNCA imprime contraseñas ni hashes en pantalla o flujos de salida estándar.
 * - NUNCA persiste contraseñas ni hashes en claro ni en tablas de auditoría.
 * - Valida estrictamente la política oficial de contraseñas (mínimo 12 caracteres, máximo 1024).
 * - Exige que el usuario exista y esté en estado ACTIVO.
 * - Revoca inmediatamente todas las sesiones previas del usuario por seguridad.
 * - Registra la operación atómicamente en la bitácora append-only D-061 con el actor del sistema (CAMARGO_PMS).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado: este script solo puede ejecutarse desde la línea de comandos (CLI).\n";
    exit(1);
}

define('RUTA_RAIZ', dirname(__DIR__));
define('RUTA_APP', RUTA_RAIZ . DIRECTORY_SEPARATOR . 'app');

// Autocargador PSR-4 (Composer con fallback nativo)
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

\CamargoPMS\Nucleo\Configuracion::cargar(RUTA_RAIZ);

// Parsear opciones de línea de comandos
$opciones = getopt('u:p:h', [
    'usuario:',
    'password:',
    'ayuda',
    'help',
]);

if (isset($opciones['ayuda']) || isset($opciones['help']) || isset($opciones['h'])) {
    echo "====================================================================\n";
    echo " Camargo PMS — Restablecimiento Administrativo de Contraseña (CLI)\n";
    echo "====================================================================\n\n";
    echo "Uso:\n";
    echo "  php bin/restablecer-contrasena-usuario.php [opciones]\n\n";
    echo "Opciones:\n";
    echo "  -u, --usuario=NOMBRE     Nombre de usuario a recuperar (ej. orlando)\n";
    echo "  -p, --password=PASS      Nueva contraseña segura (mínimo 12 caracteres)\n";
    echo "  -h, --ayuda, --help      Muestra este mensaje de ayuda\n\n";
    echo "Nota:\n";
    echo "  Si no se especifica la contraseña por parámetro, el script la solicitará\n";
    echo "  de forma interactiva en la consola.\n";
    exit(0);
}

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Servicios\UsuarioServicio;

$pdo = BaseDatos::conexion();
$usuarioServicio = new UsuarioServicio($pdo);

// 1. Obtención del nombre de usuario
$nombreUsuario = $opciones['usuario'] ?? $opciones['u'] ?? null;
if (empty($nombreUsuario)) {
    echo "Ingrese el nombre de usuario a restablecer: ";
    $lineaUsuario = fgets(STDIN);
    $nombreUsuario = $lineaUsuario !== false ? trim($lineaUsuario) : '';
}

$nombreUsuario = trim((string) $nombreUsuario);
if ($nombreUsuario === '') {
    fwrite(STDERR, "[ERROR] Debe especificar un nombre de usuario válido.\n");
    exit(1);
}

// 2. Verificar existencia y estado del usuario
$usuario = $usuarioServicio->buscarPorNombreUsuario($nombreUsuario, false);
if ($usuario === null) {
    fwrite(STDERR, "[ERROR] El usuario '{$nombreUsuario}' no existe en la base de datos.\n");
    exit(1);
}

if (!$usuario->esActivo()) {
    fwrite(STDERR, "[ERROR] El usuario '{$nombreUsuario}' se encuentra en estado '{$usuario->obtenerEstado()}'. No se puede restablecer su contraseña mientras no esté ACTIVO.\n");
    exit(1);
}

// 3. Obtención de la nueva contraseña
$nuevaContrasena = $opciones['password'] ?? $opciones['p'] ?? null;
if (empty($nuevaContrasena)) {
    echo "Ingrese la nueva contraseña para '{$usuario->obtenerNombreUsuario()}' (mínimo 12 caracteres): ";
    $lineaPassword = fgets(STDIN);
    $nuevaContrasena = $lineaPassword !== false ? rtrim($lineaPassword, "\r\n") : '';
}

$nuevaContrasena = (string) $nuevaContrasena;

// 4. Validar política antes de procesar
try {
    $usuarioServicio->validarPoliticaContrasena($nuevaContrasena);
} catch (ValidacionExcepcion $e) {
    fwrite(STDERR, "[ERROR] Política de seguridad no cumplida: " . $e->getMessage() . "\n");
    exit(1);
}

// 5. Ejecutar restablecimiento mediante el servicio de dominio
try {
    $usuarioId = (int) $usuario->obtenerId();
    $exito = $usuarioServicio->restablecerContrasenaAdministrativa(
        $usuarioId,
        $nuevaContrasena,
        'RECUPERACION_ADMINISTRATIVA_CLI'
    );

    if ($exito) {
        echo "====================================================================\n";
        echo " [ÉXITO] Contraseña restablecida correctamente.\n";
        echo " Usuario:       " . $usuario->obtenerNombreUsuario() . " (ID: {$usuarioId})\n";
        echo " Algoritmo:     PASSWORD_DEFAULT (bcrypt/argon2 oficial)\n";
        echo " Sesiones:      Todas las sesiones activas previas han sido revocadas.\n";
        echo " Auditoría:     Evento registrado bajo actor sistema (CAMARGO_PMS).\n";
        echo "====================================================================\n";
        exit(0);
    }

    fwrite(STDERR, "[ERROR] No se pudo completar el restablecimiento de contraseña.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR CRÍTICO] Falla durante el restablecimiento: " . $e->getMessage() . "\n");
    exit(1);
}
