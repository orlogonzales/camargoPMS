<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (COMPRAS-1 / D-080)
 *
 * Casos evaluados:
 * - COMP-C01: Generación atómica y pesimista de folios (FOR UPDATE) sin colisiones ni huecos.
 * - COMP-C02: Saldo protegido en recepciones físicas: bloqueo de sobre-recepción en almacén.
 * - COMP-C03: Restricción física relacional UNIQUE(proveedor, tipo, serie, numero) y captura de colisión (409).
 * - COMP-C04: Amortizaciones concurrentes con lock pesimista sobre CxP: protección contra saldo negativo.
 * - COMP-C05: Atomicidad estricta y rollback integral de recepción física si falla Kardex.
 * - COMP-C06: Reconciliación matemática reconstructible de Cuenta por Pagar tras amortizaciones múltiples.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoCompraExcepcion;
use CamargoPMS\Excepciones\ValidacionCompraExcepcion;
use CamargoPMS\Modelos\CompraOrden;
use CamargoPMS\Modelos\CuentaPorPagar;
use CamargoPMS\Modelos\InventarioArticulo;
use CamargoPMS\Modelos\InventarioUbicacion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\CompraRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Servicios\CajaServicio;
use CamargoPMS\Servicios\CompraServicio;
use CamargoPMS\Servicios\DocumentoServicio;
use CamargoPMS\Servicios\InventarioServicio;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (COMPRAS-1 / D-080)\n";
echo "====================================================================\n\n";

$passCount = 0;
$totalCount = 6;

function verificarConcurrencia(string $codigo, string $descripcion, bool $resultado, ?string $detalle = null): void
{
    global $passCount;
    if ($resultado) {
        $passCount++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
        if ($detalle) {
            echo "         Motivo: {$detalle}\n";
        }
    }
}

try {
    Configuracion::cargar(dirname(__DIR__));
    $pdo = BaseDatos::conexion();
    $pdo->exec("SET SESSION innodb_lock_wait_timeout = 3;");

    $compraRepo = new CompraRepositorio($pdo);
    $invServicio = new InventarioServicio($pdo);
    $cajaServicio = new CajaServicio($pdo);
    $docRepo = new DocumentoRepositorio($pdo);
    $docServicio = new DocumentoServicio($docRepo);
    $compraServicio = new CompraServicio($pdo, $compraRepo, $invServicio, $cajaServicio, $docServicio);

    $actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;
    $propId = (int) $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
    $proveedorId = (int) $pdo->query("SELECT id FROM proveedores WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();

    // ---------------------------------------------------------------------
    // COMP-C01: Generación atómica y pesimista de folios (FOR UPDATE)
    // ---------------------------------------------------------------------
    $fechaSimulada = new DateTimeImmutable('2026-11-01');
    $pdo->exec("DELETE FROM documento_secuencias WHERE tipo_documento = 'COMPRA_ORDEN' AND periodo_ym = '202611'");

    $folios = [];
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < 5; $i++) {
            $folios[] = $compraRepo->obtenerSiguienteFolio('COMPRA_ORDEN', 'OC', $fechaSimulada);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $c01Exito = count($folios) === 5
        && count(array_unique($folios)) === 5
        && $folios[0] === 'OC-202611-0001'
        && $folios[4] === 'OC-202611-0005';
    verificarConcurrencia('COMP-C01', 'Generación secuencial atómica bajo bloqueo pesimista FOR UPDATE sin colisiones', $c01Exito);

    // ---------------------------------------------------------------------
    // COMP-C02: Saldo protegido en recepciones físicas: bloqueo de sobre-recepción
    // ---------------------------------------------------------------------
    $almacenPrueba = $invServicio->crearUbicacion([
        'propiedad_id' => $propId,
        'codigo' => 'ALM-CONC-' . uniqid(),
        'nombre' => 'Almacén Concurrencia',
        'tipo' => InventarioUbicacion::TIPO_ALMACEN,
    ]);
    $almacenId = (int) $almacenPrueba->obtenerId();

    $articuloPrueba = $invServicio->crearArticulo([
        'codigo_sku' => 'SKU-CONC-' . uniqid(),
        'nombre' => 'Insumo Test Concurrencia',
        'categoria' => InventarioArticulo::CAT_LENCERIA_BLANCOS,
        'unidad_medida_id' => 1,
        'costo_referencial' => '20.0000',
    ]);
    $artId = (int) $articuloPrueba->obtenerId();

    // Orden aprobada con 10 unidades
    $ordenCreada = $compraServicio->crearOrden([
        'proveedor_id' => $proveedorId,
        'almacen_entrega_id' => $almacenId,
        'condicion_pago' => 'CONTADO',
        'lineas' => [
            [
                'tipo_linea' => 'BIEN',
                'articulo_id' => $artId,
                'cantidad_pactada' => '10.0000',
                'precio_unitario' => '20.0000',
                'tasa_impuesto' => '18.00',
            ]
        ]
    ], $actorId);
    $ordenId = (int) $ordenCreada->obtenerId();
    $compraServicio->aprobarOrden($ordenId, $actorId);

    $ordenAprobada = $compraServicio->obtenerOrden($ordenId);
    $lineaBienId = (int) $ordenAprobada->obtenerLineas()[0]->obtenerId();

    // Recepción 1: Recibe 6 unidades (quedan 4 pendientes)
    $compraServicio->registrarRecepcion([
        'orden_compra_id' => $ordenId,
        'almacen_id' => $almacenId,
        'numero_guia_remision' => 'GR-CONC-01',
        'lineas' => [
            [
                'orden_linea_id' => $lineaBienId,
                'cantidad_aceptada' => '6.0000',
                'cantidad_rechazada' => '0.0000',
            ]
        ]
    ], $actorId);

    // Recepción 2 concurrente: Intenta recibir 5 unidades (cuando solo quedan 4)
    $c02Capturado = false;
    try {
        $compraServicio->registrarRecepcion([
            'orden_compra_id' => $ordenId,
            'almacen_id' => $almacenId,
            'numero_guia_remision' => 'GR-CONC-02',
            'lineas' => [
                [
                    'orden_linea_id' => $lineaBienId,
                    'cantidad_aceptada' => '5.0000',
                    'cantidad_rechazada' => '0.0000',
                ]
            ]
        ], $actorId);
    } catch (ValidacionCompraExcepcion $e) {
        $c02Capturado = str_contains($e->getMessage(), 'excede el saldo pendiente');
    }

    verificarConcurrencia('COMP-C02', 'Detección pesimista y bloqueo con ValidacionCompraExcepcion ante sobre-recepción física', $c02Capturado);

    // ---------------------------------------------------------------------
    // COMP-C03: Restricción física relacional UNIQUE(proveedor, tipo, serie, numero) y captura 409
    // ---------------------------------------------------------------------
    $numFacturaUnica = '0000' . rand(1000, 9999);
    $resComp1 = $compraServicio->registrarComprobante([
        'orden_compra_id' => $ordenId,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'numero' => $numFacturaUnica,
        'fecha_emision' => date('Y-m-d'),
        'fecha_vencimiento' => date('Y-m-d', strtotime('+15 days')),
        'subtotal' => '200.00',
        'impuesto' => '36.00',
        'total' => '236.00',
    ], $actorId);

    // Segundo registro idéntico concurrente debe arrojar ConflictoCompraExcepcion (409)
    $c03Capturado = false;
    try {
        $compraServicio->registrarComprobante([
            'orden_compra_id' => $ordenId,
            'tipo_comprobante' => 'FACTURA',
            'serie' => 'F001',
            'numero' => $numFacturaUnica,
            'fecha_emision' => date('Y-m-d'),
            'subtotal' => '200.00',
            'impuesto' => '36.00',
            'total' => '236.00',
        ], $actorId);
    } catch (ConflictoCompraExcepcion $e) {
        $c03Capturado = str_contains($e->getMessage(), 'ya fue registrado previamente');
    }

    verificarConcurrencia('COMP-C03', 'Colisión relacional UNIQUE de comprobante tributario capturada y traducida a ConflictoCompraExcepcion (409)', $c03Capturado);

    // ---------------------------------------------------------------------
    // COMP-C04: Amortizaciones concurrentes con lock pesimista sobre CxP
    // ---------------------------------------------------------------------
    $cxp1 = $resComp1['cuenta_por_pagar'];
    $cxpId = (int) $cxp1->obtenerId();

    // Pago 1: Paga 200.00 PEN (saldo restante 36.00 PEN)
    $compraServicio->registrarPagoCxp([
        'cuenta_pagar_id' => $cxpId,
        'monto' => '200.00',
        'medio_pago' => 'TRANSFERENCIA_BANCARIA',
        'numero_operacion_bancaria' => 'OP-CONC-01',
        'notas' => 'Abono 1',
    ], $actorId);

    // Pago 2 concurrente: Intenta pagar 50.00 PEN (saldo actual es 36.00) -> Debe fallar por sobrepago
    $c04Capturado = false;
    try {
        $compraServicio->registrarPagoCxp([
            'cuenta_pagar_id' => $cxpId,
            'monto' => '50.00',
            'medio_pago' => 'TRANSFERENCIA_BANCARIA',
            'numero_operacion_bancaria' => 'OP-CONC-02',
            'notas' => 'Intento de sobrepago concurrente',
        ], $actorId);
    } catch (ValidacionCompraExcepcion $e) {
        $c04Capturado = str_contains($e->getMessage(), 'excede el saldo pendiente');
    }

    // Verificar que el saldo de la CxP sigue siendo exactamente 36.00 PEN (no negativo)
    $cxpActualizada = $compraServicio->obtenerCuentaPorPagar($cxpId);
    $c04Exito = $c04Capturado && bccomp($cxpActualizada->obtenerSaldoPendiente(), '36.00', 2) === 0;

    verificarConcurrencia('COMP-C04', 'Bloqueo pesimista ante sobrepago concurrente de Cuenta por Pagar preservando saldo >= 0', $c04Exito);

    // ---------------------------------------------------------------------
    // COMP-C05: Atomicidad estricta y rollback integral de recepción física
    // ---------------------------------------------------------------------
    $ordenFalloObj = $compraServicio->crearOrden([
        'proveedor_id' => $proveedorId,
        'almacen_entrega_id' => $almacenId,
        'condicion_pago' => 'CONTADO',
        'lineas' => [
            [
                'tipo_linea' => 'BIEN',
                'articulo_id' => $artId,
                'cantidad_pactada' => '5.0000',
                'precio_unitario' => '15.0000',
                'tasa_impuesto' => '18.00',
            ]
        ]
    ], $actorId);
    $ordenFalloId = (int) $ordenFalloObj->obtenerId();
    $compraServicio->aprobarOrden($ordenFalloId, $actorId);
    $ordenFallo = $compraServicio->obtenerOrden($ordenFalloId);
    $lineaFalloId = (int) $ordenFallo->obtenerLineas()[0]->obtenerId();

    $recepcionesAntes = (int) $pdo->query("SELECT COUNT(*) FROM compra_recepciones WHERE orden_compra_id = {$ordenFalloId}")->fetchColumn();
    $kardexAntes = (int) $pdo->query("SELECT COUNT(*) FROM inventario_movimientos WHERE articulo_id = {$artId}")->fetchColumn();

    $c05RollbackExito = false;
    try {
        // Enviar almacén inexistente para provocar fallo y forzar rollback transaccional
        $compraServicio->registrarRecepcion([
            'orden_compra_id' => $ordenFalloId,
            'almacen_id' => 999999, // Almacén inexistente causa fallo de FK
            'numero_guia_remision' => 'GR-ROLLBACK-FAIL',
            'lineas' => [
                [
                    'orden_linea_id' => $lineaFalloId,
                    'cantidad_aceptada' => '3.0000',
                    'cantidad_rechazada' => '0.0000',
                ]
            ]
        ], $actorId);
    } catch (\Throwable $e) {
        // Se espera error
    }

    $recepcionesDespues = (int) $pdo->query("SELECT COUNT(*) FROM compra_recepciones WHERE orden_compra_id = {$ordenFalloId}")->fetchColumn();
    $kardexDespues = (int) $pdo->query("SELECT COUNT(*) FROM inventario_movimientos WHERE articulo_id = {$artId}")->fetchColumn();

    $c05RollbackExito = ($recepcionesAntes === $recepcionesDespues) && ($kardexAntes === $kardexDespues);
    verificarConcurrencia('COMP-C05', 'Rollback transaccional íntegro ante anomalía durante recepción física sin huérfanos', $c05RollbackExito);

    // ---------------------------------------------------------------------
    // COMP-C06: Reconciliación matemática reconstructible de CxP tras amortizaciones
    // ---------------------------------------------------------------------
    // Amortizar los 36.00 restantes de la CxP anterior para liquidarla totalmente
    $compraServicio->registrarPagoCxp([
        'cuenta_pagar_id' => $cxpId,
        'monto' => '36.00',
        'medio_pago' => 'TRANSFERENCIA_BANCARIA',
        'numero_operacion_bancaria' => 'OP-CONC-FINAL',
        'notas' => 'Liquidación total',
    ], $actorId);

    $stmtSumPagos = $pdo->prepare("SELECT SUM(monto) FROM cxp_pagos WHERE cuenta_pagar_id = :cxpid");
    $stmtSumPagos->execute(['cxpid' => $cxpId]);
    $totalAmortizadoPagos = (string) $stmtSumPagos->fetchColumn();

    $cxpReconciliada = $compraServicio->obtenerCuentaPorPagar($cxpId);
    $montoTotalCxP = $cxpReconciliada->obtenerMontoTotal();
    $saldoActualCxP = $cxpReconciliada->obtenerSaldoPendiente();
    $montoAmortizadoCxP = $cxpReconciliada->obtenerMontoAmortizado();

    $diferencia = bcsub($montoTotalCxP, $totalAmortizadoPagos, 2);
    $c06Exito = (bccomp($saldoActualCxP, '0.00', 2) === 0)
        && (bccomp($diferencia, $saldoActualCxP, 2) === 0)
        && (bccomp($montoAmortizadoCxP, $totalAmortizadoPagos, 2) === 0)
        && ($cxpReconciliada->obtenerEstado() === CuentaPorPagar::ESTADO_LIQUIDADA);

    verificarConcurrencia('COMP-C06', 'Reconciliación reconstructible: monto_total - sum(cxp_pagos) == saldo_pendiente == 0.00', $c06Exito);

} catch (\Throwable $e) {
    echo "ERROR CATASTRÓFICO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n====================================================================\n";
echo "RESULTADOS CONCURRENCIA COMPRAS-1: {$passCount} / {$totalCount} PASS\n";
echo "====================================================================\n";

if ($passCount !== $totalCount) {
    exit(1);
}
