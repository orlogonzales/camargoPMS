<?php

declare(strict_types=1);

/**
 * Camargo PMS — Ejecutor de Migraciones CLI
 *
 * Comando de consola para aplicar migraciones versionadas de base de datos.
 * Uso: php migrar.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado: este comando solo puede ejecutarse desde la interfaz de línea de comandos (CLI).\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Migrador;

try {
    Configuracion::cargar(__DIR__);

    echo "====================================================================\n";
    echo " Camargo PMS — Ejecutor de Migraciones de Base de Datos\n";
    echo "====================================================================\n";
    echo "Entorno:       " . Configuracion::obtener('APP_ENV') . "\n";
    echo "Base de datos: " . Configuracion::obtener('DB_DATABASE') . "\n";
    echo "Host:          " . Configuracion::obtener('DB_HOST') . ":" . Configuracion::obtener('DB_PORT') . "\n";
    echo "--------------------------------------------------------------------\n";

    $migrador = new Migrador();
    $resultado = $migrador->ejecutar();

    if (!empty($resultado['aplicadas'])) {
        echo "Migraciones ejecutadas:\n";
        foreach ($resultado['aplicadas'] as $migracion) {
            echo "  [OK] " . $migracion . "\n";
        }
        echo "--------------------------------------------------------------------\n";
    }

    echo $resultado['mensaje'] . "\n";
    echo "====================================================================\n";
    exit(0);
} catch (\Throwable $error) {
    echo "\n[ERROR]: " . $error->getMessage() . "\n";
    echo "====================================================================\n";
    exit(1);
}
