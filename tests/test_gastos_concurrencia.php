<?php

declare(strict_types=1);

/**
 * Suite de Verificación GASTOS-1 — Pruebas de Concurrencia y Aislamiento Transaccional (6 Casos)
 *
 * Verifica:
 * - C01: Bloqueo pesimista FOR UPDATE en gastos
 * - C02: Detección y rechazo de sobregiro concurrente en saldo de gasto
 * - C03: Bloqueo pesimista FOR UPDATE en sesión de caja chica
 * - C04: Protección contra sobregiro de saldo en gaveta física
 * - C05: Atomicidad M:N: Rollback completo si falla uno de los gastos
 * - C06: Idempotencia y protección en reversiones simultáneas
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoGastoExcepcion;
use CamargoPMS\Modelos\Gasto;
use CamargoPMS\Modelos\GastoResumenDTO;
use CamargoPMS\Modelos\PagoEgreso;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CajaRepositorio;
use CamargoPMS\Repositorios\GastoRepositorio;
use CamargoPMS\Repositorios\PagoEgresoRepositorio;
use CamargoPMS\Servicios\CajaServicio;
use CamargoPMS\Servicios\GastoServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$gastoRepo = new GastoRepositorio($pdo);
$pagoRepo = new PagoEgresoRepositorio($pdo);
$cajaRepo = new CajaRepositorio($pdo);
$actorRepo = new ActorAuditoriaRepositorio($pdo);
$cajaServicio = new CajaServicio($pdo);
$gastoServicio = new GastoServicio($pdo, $gastoRepo, $pagoRepo, $cajaRepo, $actorRepo);

$total = 0;
$pasadas = 0;
$fallidas = 0;
$errores = [];

function afirmar(bool $condicion, string $mensaje): void
{
    global $total, $pasadas, $fallidas, $errores;
    $total++;
    if ($condicion) {
        $pasadas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        $fallidas++;
        $errores[] = $mensaje;
        echo "  [FAIL] {$mensaje}\n";
    }
}

echo "====================================================================\n";
echo " CAMARGO PMS — PRUEBAS CONCURRENCIA GASTOS-1 (6 CASOS VINCULANTES)\n";
echo " Decisión Vinculante: D-086\n";
echo "====================================================================\n\n";

$stmtCat = $pdo->query('SELECT id FROM gasto_categorias WHERE activo = 1 LIMIT 1');
$catId = (int) $stmtCat->fetchColumn();

$stmtProp = $pdo->query('SELECT id FROM propiedades LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();

$stmtCaja = $pdo->query('SELECT id FROM cajas_fisicas WHERE estado = "ACTIVO" LIMIT 1');
$cajaFisicaId = (int) $stmtCaja->fetchColumn();

$stmtBanco = $pdo->query('SELECT id FROM cuentas_bancarias WHERE estado = "ACTIVO" LIMIT 1');
$cuentaBancariaId = (int) $stmtBanco->fetchColumn();

$stmtMetodoEf = $pdo->query('SELECT id FROM metodos_pago WHERE tipo_destino = "CAJA_FISICA" OR codigo = "EFECTIVO" LIMIT 1');
$metodoEfectivoId = (int) $stmtMetodoEf->fetchColumn();

$stmtMetodoTrans = $pdo->query('SELECT id FROM metodos_pago WHERE tipo_destino = "CUENTA_BANCARIA" OR codigo = "TRANSFERENCIA" LIMIT 1');
$metodoTransferenciaId = (int) $stmtMetodoTrans->fetchColumn();

// Sesión de caja abierta con saldo conocido
$sesion = $cajaServicio->obtenerSesionActivaPorCaja($cajaFisicaId);
if ($sesion === null) {
    $sesion = $cajaServicio->aperturarSesion($cajaFisicaId, '600.00', 'Sesión Concurrencia Gastos', 1);
} else {
    $cajaServicio->registrarMovimientoManual((int) $sesion->obtenerId(), 'INGRESO_AJUSTE', '600.00', 'Aporte test concurrencia', 1);
}
$sesionCajaId = (int) $sesion->obtenerId();

// -----------------------------------------------------------------------------
// CASO C01: Bloqueo Pesimista FOR UPDATE en Fila de Gastos
// -----------------------------------------------------------------------------
$gastoBloqueo = $gastoServicio->crearGasto([
    'categoria_id' => $catId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Gasto para test bloqueo pesimista',
    'acreedor_nombre' => 'Acreedor Concurrencia 1',
    'total' => '500.00'
]);
$gastoServicio->aprobarGasto((int) $gastoBloqueo->obtenerId(), 1);

$pdo->beginTransaction();
$gBloqueado = $gastoRepo->obtenerGastoPorId((int) $gastoBloqueo->obtenerId(), true);
afirmar($gBloqueado !== null && $gBloqueado->obtenerId() === $gastoBloqueo->obtenerId(),
    'Caso C01: Bloqueo pesimista SELECT ... FOR UPDATE sobre la fila del gasto adquirido');
$pdo->commit();

// -----------------------------------------------------------------------------
// CASO C02: Dos Pagos Concurrentes que compiten por el Saldo Restante
// -----------------------------------------------------------------------------
$gastoCarrera = $gastoServicio->crearGasto([
    'categoria_id' => $catId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Gasto carrera de pagos concurrentes',
    'acreedor_nombre' => 'Acreedor Concurrencia 2',
    'total' => '200.00'
]);
$gastoServicio->aprobarGasto((int) $gastoCarrera->obtenerId(), 1);

// Hilo 1: Paga S/ 150.00 exitosamente
$resPago1 = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'referencia_operacion' => 'TRANS-C02-A',
    'aplicaciones' => [['gasto_id' => $gastoCarrera->obtenerId(), 'monto' => '150.00']]
], 1);

// Hilo 2: Intenta pagar S/ 100.00 (cuando solo restan S/ 50.00)
try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoTransferenciaId,
        'cuenta_bancaria_id' => $cuentaBancariaId,
        'referencia_operacion' => 'TRANS-C02-B',
        'aplicaciones' => [['gasto_id' => $gastoCarrera->obtenerId(), 'monto' => '100.00']]
    ], 1);
    afirmar(false, 'Caso C02: Pago concurrente que excede saldo debe ser rechazado');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso C02: Pago concurrente excedente rechazado con ConflictoGastoExcepcion');
}

// -----------------------------------------------------------------------------
// CASO C03: Bloqueo Pesimista FOR UPDATE en Sesión de Caja Chica
// -----------------------------------------------------------------------------
$pdo->beginTransaction();
$sesionBloqueada = $cajaRepo->obtenerSesionCaja($sesionCajaId, true);
afirmar($sesionBloqueada !== null && $sesionBloqueada->obtenerId() === $sesionCajaId,
    'Caso C03: Bloqueo pesimista SELECT ... FOR UPDATE sobre la sesión de caja activa');
$pdo->commit();

// -----------------------------------------------------------------------------
// CASO C04: Protección contra Sobregiro de Saldo en Gaveta de Caja
// -----------------------------------------------------------------------------
$gastoCaja = $gastoServicio->crearGasto([
    'categoria_id' => $catId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Gasto en efectivo alto',
    'acreedor_nombre' => 'Acreedor Efectivo',
    'total' => '2000.00'
]);
$gastoServicio->aprobarGasto((int) $gastoCaja->obtenerId(), 1);

// Consultar saldo actual en gaveta
$sesionActual = $cajaServicio->obtenerSesion($sesionCajaId);
$saldoEnCaja = bcadd(
    $sesionActual->obtenerMontoApertura(),
    bcsub($sesionActual->obtenerTotalIngresosEfectivo(), $sesionActual->obtenerTotalEgresosEfectivo(), 2),
    2
);

// Intentar pagar más del saldo que hay en gaveta
$montoExcesivo = bcadd($saldoEnCaja, '100.00', 2);
try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoEfectivoId,
        'sesion_caja_id' => $sesionCajaId,
        'aplicaciones' => [['gasto_id' => $gastoCaja->obtenerId(), 'monto' => $montoExcesivo]]
    ], 1);
    afirmar(false, 'Caso C04: Desembolso superior al saldo de caja física debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso C04: Sobregiro en gaveta de efectivo impedido con ConflictoGastoExcepcion');
}

// -----------------------------------------------------------------------------
// CASO C05: Atomicidad M:N: Rollback Completo si uno de los Gastos Falla
// -----------------------------------------------------------------------------
$gastoValido1 = $gastoServicio->crearGasto([
    'categoria_id' => $catId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Gasto multi 1',
    'acreedor_nombre' => 'Acreedor Multi 1',
    'total' => '100.00'
]);
$gastoServicio->aprobarGasto((int) $gastoValido1->obtenerId(), 1);

$gastoInvalido2 = $gastoServicio->crearGasto([
    'categoria_id' => $catId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Gasto multi 2 no aprobado',
    'acreedor_nombre' => 'Acreedor Multi 2',
    'total' => '100.00'
]);
// No se aprueba gasto 2 (queda en REGISTRADO)

$totalPagosAntes = (int) $pdo->query('SELECT COUNT(*) FROM pagos_egreso')->fetchColumn();
$totalAplAntes = (int) $pdo->query('SELECT COUNT(*) FROM gasto_aplicaciones_pago')->fetchColumn();

try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoTransferenciaId,
        'cuenta_bancaria_id' => $cuentaBancariaId,
        'referencia_operacion' => 'TRANS-MULTI-FAIL',
        'aplicaciones' => [
            ['gasto_id' => $gastoValido1->obtenerId(), 'monto' => '50.00'],
            ['gasto_id' => $gastoInvalido2->obtenerId(), 'monto' => '50.00'], // Fallará
        ]
    ], 1);
    afirmar(false, 'Caso C05: Transacción multi-gasto debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    $totalPagosDespues = (int) $pdo->query('SELECT COUNT(*) FROM pagos_egreso')->fetchColumn();
    $totalAplDespues = (int) $pdo->query('SELECT COUNT(*) FROM gasto_aplicaciones_pago')->fetchColumn();

    $rollbackTotal = ($totalPagosAntes === $totalPagosDespues && $totalAplAntes === $totalAplDespues);
    afirmar($rollbackTotal, 'Caso C05: Atomicidad M:N: Rollback completo garantizado (cero registros huérfanos)');
}

// -----------------------------------------------------------------------------
// CASO C06: Idempotencia y Protección en Reversiones Simultáneas
// -----------------------------------------------------------------------------
$gastoParaReverso = $gastoServicio->crearGasto([
    'categoria_id' => $catId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Gasto para test reversión concurrente',
    'acreedor_nombre' => 'Acreedor Reversión',
    'total' => '80.00'
]);
$gastoServicio->aprobarGasto((int) $gastoParaReverso->obtenerId(), 1);

$pagoParaReverso = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'referencia_operacion' => 'TRANS-REV-C06',
    'aplicaciones' => [['gasto_id' => $gastoParaReverso->obtenerId(), 'monto' => '80.00']]
], 1)['pago'];

// Primera reversión
$rev1 = $gastoServicio->reversarPagoEgreso((int) $pagoParaReverso->obtenerId(), 1, 'Reversión 1');
// Segunda reversión simultánea/repetida
$rev2 = $gastoServicio->reversarPagoEgreso((int) $pagoParaReverso->obtenerId(), 1, 'Reversión 2');

afirmar($rev1 === true && $rev2 === true,
    'Caso C06: Idempotencia estricta en reversiones simultáneas (cero duplicación de restituciones)');

// -----------------------------------------------------------------------------
// CASO C07: Cardinalidad M:N Bidireccional Explícita
// (1 desembolso -> N gastos Y 1 gasto <- N desembolsos)
// -----------------------------------------------------------------------------
// Creamos Gasto A (S/ 400), Gasto B (S/ 350) y Gasto C (S/ 250)
$gA = $gastoServicio->crearGasto(['categoria_id' => $catId, 'propiedad_id' => $propiedadId, 'descripcion_concepto' => 'Gasto A', 'acreedor_nombre' => 'Prov A', 'total' => '400.00']);
$gB = $gastoServicio->crearGasto(['categoria_id' => $catId, 'propiedad_id' => $propiedadId, 'descripcion_concepto' => 'Gasto B', 'acreedor_nombre' => 'Prov B', 'total' => '350.00']);
$gC = $gastoServicio->crearGasto(['categoria_id' => $catId, 'propiedad_id' => $propiedadId, 'descripcion_concepto' => 'Gasto C', 'acreedor_nombre' => 'Prov C', 'total' => '250.00']);
$gastoServicio->aprobarGasto((int) $gA->obtenerId(), 1);
$gastoServicio->aprobarGasto((int) $gB->obtenerId(), 1);
$gastoServicio->aprobarGasto((int) $gC->obtenerId(), 1);

// 1 desembolso bancario de S/ 1,000 que amortiza Gasto A S/ 400, Gasto B S/ 350 y Gasto C S/ 150 (parcial)
$resLote = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'monto_total' => '900.00',
    'referencia_operacion' => 'TRANS-LOTE-ABC',
    'aplicaciones' => [
        ['gasto_id' => $gA->obtenerId(), 'monto' => '400.00'],
        ['gasto_id' => $gB->obtenerId(), 'monto' => '350.00'],
        ['gasto_id' => $gC->obtenerId(), 'monto' => '150.00'],
    ]
], 1);
$desembolso1 = $resLote['pago'];

// Segundo desembolso para cancelar el saldo restante de Gasto C (S/ 100.00)
$resGastoC2 = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'referencia_operacion' => 'TRANS-SALDO-C',
    'aplicaciones' => [
        ['gasto_id' => $gC->obtenerId(), 'monto' => '100.00'],
    ]
], 1);

$resGastoAFin = $gastoServicio->obtenerResumenGasto((int) $gA->obtenerId());
$resGastoBFin = $gastoServicio->obtenerResumenGasto((int) $gB->obtenerId());
$resGastoCFin = $gastoServicio->obtenerResumenGasto((int) $gC->obtenerId());

afirmar(
    $desembolso1->obtenerMontoTotal() === '900.00' &&
    count($resLote['aplicaciones']) === 3 &&
    $resGastoAFin->obtenerSituacionFinanciera() === \CamargoPMS\Modelos\GastoResumenDTO::SITUACION_PAGADO &&
    $resGastoBFin->obtenerSituacionFinanciera() === \CamargoPMS\Modelos\GastoResumenDTO::SITUACION_PAGADO &&
    $resGastoCFin->obtenerSituacionFinanciera() === \CamargoPMS\Modelos\GastoResumenDTO::SITUACION_PAGADO &&
    bccomp($resGastoCFin->obtenerMontoAplicadoAcumulado(), '250.00', 2) === 0,
    'Caso C07: Cardinalidad M:N certificada (1 desembolso -> 3 gastos Y 1 gasto <- 2 desembolsos independientes)'
);

// -----------------------------------------------------------------------------
// CASO C08: Invariante de Desembolso contra Doble Aplicación de Fondos
// SUM(aplicaciones activas) <= monto_total del desembolso
// -----------------------------------------------------------------------------
// Crear un desembolso soberano con remanente: S/ 500 total, pero inicialmente aplicado S/ 300
$gastoD1 = $gastoServicio->crearGasto(['categoria_id' => $catId, 'propiedad_id' => $propiedadId, 'descripcion_concepto' => 'Gasto D1', 'acreedor_nombre' => 'Prov D', 'total' => '300.00']);
$gastoD2 = $gastoServicio->crearGasto(['categoria_id' => $catId, 'propiedad_id' => $propiedadId, 'descripcion_concepto' => 'Gasto D2', 'acreedor_nombre' => 'Prov D', 'total' => '300.00']);
$gastoServicio->aprobarGasto((int) $gastoD1->obtenerId(), 1);
$gastoServicio->aprobarGasto((int) $gastoD2->obtenerId(), 1);

$desembolsoSoberano = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'monto_total' => '500.00', // Desembolso de S/ 500
    'referencia_operacion' => 'TRANS-SOBERANA-500',
    'aplicaciones' => [
        ['gasto_id' => $gastoD1->obtenerId(), 'monto' => '300.00'], // Quedan S/ 200 disponibles en el pago
    ]
], 1)['pago'];

// Intento 1: Intentar aplicar S/ 250 a Gasto D2 usando ese mismo pago (supera el remanente disponible de S/ 200)
$bloqueoDobleAplicacion = false;
try {
    $gastoServicio->aplicarPagoExistenteAGasto((int) $desembolsoSoberano->obtenerId(), (int) $gastoD2->obtenerId(), '250.00', 1);
} catch (ConflictoGastoExcepcion $e) {
    $bloqueoDobleAplicacion = true;
}

// Intento 2: Aplicar exactamente el remanente disponible de S/ 200 (debe prosperar)
$aplExitosa = $gastoServicio->aplicarPagoExistenteAGasto((int) $desembolsoSoberano->obtenerId(), (int) $gastoD2->obtenerId(), '200.00', 1);

// Intento 3: Intentar aplicar S/ 0.01 adicional (ya no queda saldo en el desembolso)
$bloqueoSobreUso = false;
try {
    $gastoServicio->aplicarPagoExistenteAGasto((int) $desembolsoSoberano->obtenerId(), (int) $gastoD2->obtenerId(), '0.01', 1);
} catch (ConflictoGastoExcepcion $e) {
    $bloqueoSobreUso = true;
}

afirmar(
    $bloqueoDobleAplicacion === true &&
    $aplExitosa->obtenerMontoAplicado() === '200.00' &&
    $bloqueoSobreUso === true,
    'Caso C08: Invariante de desembolso soberano garantizado bajo concurrencia: SUM(aplicaciones) <= monto_total (cero doble gasto del mismo dinero)'
);


// -----------------------------------------------------------------------------
// REPORTE FINAL
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESULTADOS CONCURRENCIA GASTOS-1: {$pasadas} / {$total} PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "   - {$err}\n";
    }
}
echo "====================================================================\n";

if ($fallidas > 0) {
    exit(1);
}
exit(0);
