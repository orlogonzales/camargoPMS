<?php

declare(strict_types=1);

/**
 * Worker CLI para pruebas de concurrencia real sobre CpeCorrelativoServicio (SUNAT-1C2).
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeLinea;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\CPE\CpeCorrelativoServicio;

Configuracion::cargar(dirname(__DIR__, 2));
$pdo = BaseDatos::conexion();

// Parsear opciones CLI
$options = getopt('', [
    'estab-id:',
    'serie-id:',
    'tipo:',
    'serie:',
    'idemp:',
    'user-id:',
    'delay-ms:',
]);

$estabId = isset($options['estab-id']) ? (int) $options['estab-id'] : 0;
$serieId = isset($options['serie-id']) ? (int) $options['serie-id'] : 0;
$tipo = (string) ($options['tipo'] ?? 'FACTURA');
$serie = (string) ($options['serie'] ?? 'F001');
$idemp = (string) ($options['idemp'] ?? ('IDEMP_' . bin2hex(random_bytes(8))));
$userId = isset($options['user-id']) ? (int) $options['user-id'] : 1;
$delayMs = isset($options['delay-ms']) ? (int) $options['delay-ms'] : 0;

if ($delayMs > 0) {
    usleep($delayMs * 1000);
}

try {
    $linea = new CpeLinea(
        null,
        null,
        1,
        'SERV-CONC',
        '90101501',
        'Consumo Concurrente Test',
        'ZZ',
        '1.0000',
        '100.0000',
        '118.0000',
        '0.00',
        '100.00',
        '10',
        '18.00',
        '18.00',
        '118.00'
    );

    $comprobante = new CpeComprobante(
        null,
        $estabId,
        $serieId,
        null,
        $tipo,
        $serie,
        0, // correlativo provisional
        '',
        $idemp,
        '20600000001',
        'Razón Social Emisor Test Concurrente',
        'Nombre Comercial',
        'Av. Concurrencia 123',
        '150101',
        '0000',
        'Lima',
        'Lima',
        'Lima',
        '6',
        '20609999999',
        'Cliente Concurrente SAC',
        'Av. Cliente 456',
        '150101',
        'conc@cliente.com',
        'PE',
        false,
        null,
        null,
        null,
        null,
        'PEN',
        null,
        '100.00',
        '0.00',
        '0.00',
        '0.00',
        '0.00',
        '18.00',
        '0.00',
        '118.00',
        'BORRADOR',
        'NO_INICIADO',
        'PENDIENTE_ENVIO',
        'ORIGINAL',
        null,
        null,
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        null,
        $userId,
        null,
        null,
        [$linea]
    );

    $servicio = new CpeCorrelativoServicio($pdo);
    $cpeEmitido = $servicio->emitirBorradorOAsignarCorrelativo($comprobante, $userId);

    echo json_encode([
        'success' => true,
        'cpe_id' => $cpeEmitido->obtenerId(),
        'correlativo' => $cpeEmitido->obtenerCorrelativo(),
        'folio' => $cpeEmitido->obtenerCodigoFolioCompleto(),
        'clave_idempotencia' => $cpeEmitido->obtenerClaveIdempotencia(),
    ]);
    exit(0);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'class' => get_class($e),
    ]);
    exit(1);
}
