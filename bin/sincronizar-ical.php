#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Camargo PMS — Comando CLI de Sincronización Manual/Técnica iCalendar (AIRBNB-ICAL-1B).
 *
 * Permite ejecutar la sincronización de una conexión específica o de todas las conexiones
 * activas habilitadas para sondeo desde la línea de comandos, útil para pruebas y diagnóstico.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado: este script solo puede ejecutarse desde la línea de comandos (CLI).\n";
    exit(1);
}

define('RUTA_RAIZ', dirname(__DIR__));
define('RUTA_APP', RUTA_RAIZ . DIRECTORY_SEPARATOR . 'app');

require_once RUTA_RAIZ . '/vendor/autoload.php';
\CamargoPMS\Nucleo\Configuracion::cargar(RUTA_RAIZ);

echo "====================================================================\n";
echo " Camargo PMS — Sincronizador iCalendar Multicanal (CLI)\n";
echo " Fecha y hora: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$opciones = getopt('', ['conexion:', 'todas', 'ayuda']);

if (isset($opciones['ayuda']) || (empty($opciones['conexion']) && !isset($opciones['todas']))) {
    echo "Uso:\n";
    echo "  php bin/sincronizar-ical.php --conexion=<ID>    Sincroniza una conexión específica.\n";
    echo "  php bin/sincronizar-ical.php --todas            Sincroniza todas las conexiones activas.\n";
    echo "  php bin/sincronizar-ical.php --ayuda            Muestra esta ayuda.\n";
    exit(0);
}

$servicio = new \CamargoPMS\Servicios\SincronizacionIcalServicio();
$conexionRepo = new \CamargoPMS\Repositorios\ConexionIcalRepositorio();

if (!empty($opciones['conexion'])) {
    $conexionId = (int) $opciones['conexion'];
    echo "Iniciando sincronización para conexión #$conexionId...\n";

    try {
        $res = $servicio->sincronizarConexion(
            conexionId: $conexionId,
            origenEjecucion: \CamargoPMS\Modelos\SincronizacionIcalLog::ORIGEN_CLI
        );
        echo "[OK] Resultado: {$res['resultado']} — {$res['mensaje']}\n";
    } catch (\Throwable $e) {
        echo "[ERROR] Falló la sincronización: " . $e->getMessage() . "\n";
        exit(1);
    }
} elseif (isset($opciones['todas'])) {
    echo "Buscando conexiones activas habilitadas para importación...\n";
    $conexiones = $conexionRepo->listarHabilitadasParaSondeo();

    if (empty($conexiones)) {
        echo "No hay conexiones activas con importación habilitada.\n";
        exit(0);
    }

    echo "Total conexiones encontradas: " . count($conexiones) . "\n\n";
    $errores = 0;

    foreach ($conexiones as $c) {
        $cId = $c->obtenerId();
        echo "--- Sincronizando #$cId ({$c->obtenerNombre()} - Canal: {$c->obtenerCanalCodigo()}) ---\n";
        try {
            $res = $servicio->sincronizarConexion(
                conexionId: $cId,
                origenEjecucion: \CamargoPMS\Modelos\SincronizacionIcalLog::ORIGEN_CLI
            );
            echo "  [OK] {$res['resultado']}: {$res['mensaje']}\n";
        } catch (\Throwable $e) {
            echo "  [ERROR] " . $e->getMessage() . "\n";
            $errores++;
        }
    }

    echo "\nProceso finalizado. Total: " . count($conexiones) . ", Errores: $errores.\n";
    exit($errores > 0 ? 1 : 0);
}
