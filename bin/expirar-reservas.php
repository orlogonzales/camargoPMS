#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Camargo PMS — Script CLI para Expiración Automática de Reservas Pendientes con Hold Vencido (RESERVAS-1).
 *
 * Propósito:
 * Ejecutar de manera periódica (Cron / Tarea programada) o bajo demanda la liberación
 * atómica de inventario y transición a estado 'EXPIRADA' para reservas cuyo tiempo de
 * retención (reservas.duracion_hold_minutos) haya sido superado.
 *
 * Restricciones vinculantes:
 * - Ejecutable exclusivamente bajo CLI (PHP_SAPI === 'cli').
 * - Idempotente: puede ejecutarse concurrentemente o múltiples veces sin efectos colaterales.
 * - Registra trazabilidad de auditoría D-061 con el actor del sistema ('CAMARGO_PMS').
 * - Preserva el registro maestro de la reserva sin aplicar DELETE físico.
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

echo "====================================================================\n";
echo " Camargo PMS — Proceso de Expiración de Holds de Reservas Pendientes\n";
echo " Fecha y hora: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$tiempoInicio = microtime(true);

try {
    $reservaServicio = new \CamargoPMS\Servicios\ReservaServicio();
    $resultado = $reservaServicio->expirarReservasPendientes();

    $tiempoTotal = round((microtime(true) - $tiempoInicio) * 1000, 2);

    echo "Proceso finalizado exitosamente.\n";
    echo "Total de reservas expiradas: {$resultado['total_expiradas']}\n";

    if (!empty($resultado['codigos'])) {
        echo "Códigos procesados:\n";
        foreach ($resultado['codigos'] as $codigo) {
            echo "  - {$codigo}\n";
        }
    } else {
        echo "No se encontraron reservas pendientes con tiempo de hold vencido.\n";
    }

    echo "\nTiempo de ejecución: {$tiempoTotal} ms\n";
    exit(0);
} catch (\Throwable $e) {
    $tiempoTotal = round((microtime(true) - $tiempoInicio) * 1000, 2);
    echo "ERROR al ejecutar la expiración de reservas: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Tiempo transcurrido: {$tiempoTotal} ms\n";
    exit(1);
}
