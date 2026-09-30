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

$opciones = getopt('', ['conexion:', 'todas', 'solo-debidas', 'quiet', 'silencioso', 'ayuda']);
$esSilencioso = isset($opciones['quiet']) || isset($opciones['silencioso']);

if (isset($opciones['ayuda']) || (empty($opciones['conexion']) && !isset($opciones['todas']) && !isset($opciones['solo-debidas']))) {
    echo "====================================================================\n";
    echo " Camargo PMS — Sincronizador iCalendar Multicanal (CLI / Scheduler)\n";
    echo "====================================================================\n";
    echo "Uso:\n";
    echo "  php bin/sincronizar-ical.php --conexion=<ID>    Sincroniza una conexión específica puntual.\n";
    echo "  php bin/sincronizar-ical.php --solo-debidas     Sincroniza conexiones cuya frecuencia esté vencida (CRON).\n";
    echo "  php bin/sincronizar-ical.php --todas            Fuerza la sincronización de todas las conexiones activas.\n";
    echo "  php bin/sincronizar-ical.php --quiet            Modo silencioso (suprime banners, ideal para cron).\n";
    echo "  php bin/sincronizar-ical.php --ayuda            Muestra esta ayuda.\n\n";
    echo "Códigos de salida:\n";
    echo "  0 = Ejecución exitosa (todas las conexiones procesadas OK o ninguna debida).\n";
    echo "  1 = Error fatal de bootstrap, configuración o argumentos inválidos.\n";
    echo "  2 = Ejecución completada con fallos técnicos parciales en una o más conexiones.\n";
    exit(isset($opciones['ayuda']) ? 0 : 1);
}

if (!$esSilencioso) {
    echo "====================================================================\n";
    echo " Camargo PMS — Sincronizador iCalendar Multicanal (CLI / Scheduler)\n";
    echo " Fecha y hora: " . date('Y-m-d H:i:s') . "\n";
    echo "====================================================================\n\n";
}

$servicio = new \CamargoPMS\Servicios\SincronizacionIcalServicio();
$conexionRepo = new \CamargoPMS\Repositorios\ConexionIcalRepositorio();

if (!empty($opciones['conexion'])) {
    $conexionId = (int) $opciones['conexion'];
    if (!$esSilencioso) {
        echo "Iniciando sincronización para conexión #$conexionId...\n";
    }

    try {
        $res = $servicio->sincronizarConexion(
            conexionId: $conexionId,
            origenEjecucion: \CamargoPMS\Modelos\SincronizacionIcalLog::ORIGEN_CLI
        );
        if (!$esSilencioso) {
            echo "[OK] Resultado: {$res['resultado']} — {$res['mensaje']}\n";
        }
        exit(0);
    } catch (\CamargoPMS\Excepciones\ConexionIcalEnSincronizacionExcepcion $e) {
        if (!$esSilencioso) {
            echo "[OMITIDO] Conexión #$conexionId: {$e->getMessage()}\n";
        }
        exit(0);
    } catch (\Throwable $e) {
        echo "[ERROR] Falló la sincronización para conexión #$conexionId: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$soloDebidas = isset($opciones['solo-debidas']);
$origen = $soloDebidas
    ? \CamargoPMS\Modelos\SincronizacionIcalLog::ORIGEN_CRON
    : \CamargoPMS\Modelos\SincronizacionIcalLog::ORIGEN_CLI;

if (!$esSilencioso) {
    echo $soloDebidas
        ? "Buscando conexiones activas cuya frecuencia de sondeo esté vencida...\n"
        : "Buscando todas las conexiones activas habilitadas para importación...\n";
}

$conexiones = $soloDebidas
    ? $conexionRepo->listarDebidasParaSondeo()
    : $conexionRepo->listarHabilitadasParaSondeo();

if (empty($conexiones)) {
    if (!$esSilencioso) {
        echo $soloDebidas
            ? "No hay conexiones debidas para sincronización en este ciclo.\n"
            : "No hay conexiones activas con importación habilitada.\n";
    }
    exit(0);
}

if (!$esSilencioso) {
    echo "Total conexiones a procesar: " . count($conexiones) . "\n\n";
}

$exitosas = 0;
$omitidas = 0;
$errores = 0;

foreach ($conexiones as $c) {
    $cId = $c->obtenerId();
    if (!$esSilencioso) {
        echo "--- Sincronizando #$cId ({$c->obtenerNombre()} - Canal: {$c->obtenerCanalCodigo()}) ---\n";
    }

    try {
        $res = $servicio->sincronizarConexion(
            conexionId: $cId,
            origenEjecucion: $origen
        );
        $exitosas++;
        if (!$esSilencioso) {
            echo "  [OK] {$res['resultado']}: {$res['mensaje']}\n";
        }
    } catch (\CamargoPMS\Excepciones\ConexionIcalEnSincronizacionExcepcion $e) {
        $omitidas++;
        if (!$esSilencioso) {
            echo "  [OMITIDO] Conexión #$cId: {$e->getMessage()}\n";
        }
    } catch (\Throwable $e) {
        $errores++;
        echo "  [ERROR] Conexión #$cId: " . $e->getMessage() . "\n";
    }
}

if (!$esSilencioso || $errores > 0) {
    echo "\nProceso finalizado. Total: " . count($conexiones) . ", Exitosas: $exitosas, Omitidas: $omitidas, Errores: $errores.\n";
}

exit($errores > 0 ? 2 : 0);
