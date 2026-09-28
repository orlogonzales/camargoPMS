<?php

declare(strict_types=1);

/**
 * Gate de Certificación Financiera SUM-FIN-01 — Corrección de Suministro con Cargo Ya Pagado (D-081 / FINANCIERO-2)
 *
 * Certifica los 5 invariantes obligatorios y los 3 escenarios de reliquidación:
 * - SUM-FIN-01A: Cargo S/ 100, Pago S/ 100, Corrección a S/ 80  -> Saldo a favor S/ 20
 * - SUM-FIN-01B: Cargo S/ 100, Pago S/ 60,  Corrección a S/ 80  -> Saldo pendiente S/ 20
 * - SUM-FIN-01C: Cargo S/ 100, Pago S/ 100, Corrección a S/ 120 -> Saldo pendiente S/ 20
 *
 * Invariantes demostrados:
 * 1. Movimiento de caja original -> INTACTO (cero alteraciones en movimientos_caja).
 * 2. Pago original -> INTACTO (monto_total, id y confirmación preservados).
 * 3. Monto histórico aplicado originalmente -> RECUPERABLE Y DEMOSTRABLE (aplicaciones_pago > 0).
 * 4. Reversión/desaplicación -> TRAZABLE (estado REVERTIDA, fecha y actor de reversión).
 * 5. Saldo final -> RECONSTRUIBLE ALGEBRAICAMENTE:
 *    SUM(aplicaciones activas) + SUM(reversiones) = total aplicaciones emitidas.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\AplicacionPago;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\PagoCuenta;
use CamargoPMS\Modelos\Suministro;
use CamargoPMS\Modelos\SuministroLectura;
use CamargoPMS\Modelos\SuministroLiquidacion;
use CamargoPMS\Modelos\SuministroMedidor;
use CamargoPMS\Modelos\SuministroTarifa;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\SuministroRepositorio;
use CamargoPMS\Servicios\SuministroServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$suministroRepo = new SuministroRepositorio($pdo);
$cargoRepo = new CargoCuentaRepositorio($pdo);
$aplicacionRepo = new AplicacionPagoRepositorio($pdo);
$pagoRepo = new PagoCuentaRepositorio($pdo);
$cuentaFolioRepo = new CuentaFolioRepositorio($pdo);
$arrendamientoRepo = new ArrendamientoRepositorio($pdo);
$actorRepo = new ActorAuditoriaRepositorio($pdo);

$sumServicio = new SuministroServicio(
    $pdo,
    $suministroRepo,
    $cargoRepo,
    $aplicacionRepo,
    $pagoRepo,
    $cuentaFolioRepo,
    $arrendamientoRepo,
    $actorRepo
);

$pass = 0;
$total = 0;

function assertFin(bool $condicion, string $codigo, string $descripcion): void
{
    global $pass, $total;
    $total++;
    if ($condicion) {
        $pass++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

echo "====================================================================\n";
echo " GATE FINANCIERO SUM-FIN-01 — CORRECCIÓN DE CARGO YA PAGADO (D-081)\n";
echo "====================================================================\n\n";

try {
    $sufijo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    // 1. Setup Maestro Común
    $pdo->exec("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, pais_nacionalidad_id, creado_en) VALUES ('TitularFin', 'Gate01', '{$sufijo}', 1, NOW())");
    $personaId = (int) $pdo->lastInsertId();

    $stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
    $propiedadId = (int) $stmtProp->fetchColumn();
    if ($propiedadId <= 0) {
        $pdo->exec("INSERT INTO propiedades (codigo, nombre, direccion, tipo, estado) VALUES ('PROP-FIN-{$sufijo}', 'Edificio Fin {$sufijo}', 'Av Central 100', 'URBANA', 'ACTIVO')");
        $propiedadId = (int) $pdo->lastInsertId();
    }

    $stmtTipo = $pdo->query("SELECT id FROM tipos_unidad WHERE activo = 1 LIMIT 1");
    $tipoUnidadId = (int) $stmtTipo->fetchColumn();
    if ($tipoUnidadId <= 0) {
        $tipoUnidadId = 1;
    }

    $pdo->exec("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado, creado_en) VALUES ({$propiedadId}, {$tipoUnidadId}, 'UNI-FIN-{$sufijo}', 'Unidad Fin {$sufijo}', 'ACTIVO', NOW())");
    $unidadId = (int) $pdo->lastInsertId();

    $actorSistema = $actorRepo->buscarPorCodigo('CAMARGO_PMS');
    $actorId = $actorSistema !== null ? (int) $actorSistema->obtenerId() : 1;

    // Caja física y sesión de caja (FINANCIERO-2)
    $stmtCaja = $pdo->prepare("SELECT id FROM cajas_fisicas WHERE propiedad_id = ? AND estado = 'ACTIVO' LIMIT 1");
    $stmtCaja->execute([$propiedadId]);
    $cajaFisicaId = (int) $stmtCaja->fetchColumn();
    if ($cajaFisicaId <= 0) {
        $pdo->exec("INSERT INTO cajas_fisicas (codigo, nombre, propiedad_id, moneda_codigo, estado) VALUES ('CAJA-FIN-{$sufijo}', 'Caja Recepción {$sufijo}', {$propiedadId}, 'PEN', 'ACTIVO')");
        $cajaFisicaId = (int) $pdo->lastInsertId();
    }

    $stmtSes = $pdo->prepare("SELECT id FROM sesiones_caja WHERE caja_fisica_id = ? AND estado = 'ABIERTA' LIMIT 1");
    $stmtSes->execute([$cajaFisicaId]);
    $sesionCajaId = (int) $stmtSes->fetchColumn();
    if ($sesionCajaId <= 0) {
        $pdo->exec("INSERT INTO sesiones_caja (caja_fisica_id, actor_apertura_id, monto_apertura, total_ingresos_efectivo, total_egresos_efectivo, estado, abierta_en) VALUES ({$cajaFisicaId}, {$actorId}, 500.00, 0.00, 0.00, 'ABIERTA', NOW())");
        $sesionCajaId = (int) $pdo->lastInsertId();
    }

    // Método de pago EFECTIVO
    $stmtMetodo = $pdo->query("SELECT id FROM metodos_pago WHERE codigo = 'EFECTIVO' LIMIT 1");
    $metodoPagoId = (int) $stmtMetodo->fetchColumn();
    if ($metodoPagoId <= 0) {
        $pdo->exec("INSERT INTO metodos_pago (codigo, nombre, tipo, activo) VALUES ('EFECTIVO', 'Efectivo en Caja', 'EFECTIVO', 1)");
        $metodoPagoId = (int) $pdo->lastInsertId();
    }

    // Crear suministro MEDIDO para pruebas
    $sumEnt = $sumServicio->crearSuministro([
        'codigo' => "ELEC-GATE-{$sufijo}",
        'nombre' => "Electricidad Gate Fin {$sufijo}",
        'modalidad' => 'MEDIDO',
        'unidad_medida' => 'kWh',
        'permite_rollover' => true,
    ]);
    $sumId = (int) $sumEnt->obtenerId();

    // Tarifa: 1.00 PEN por kWh
    $sumServicio->crearTarifa([
        'suministro_id' => $sumId,
        'ambito' => 'GLOBAL',
        'precio_unitario' => '1.0000',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-12-31',
    ], $actorId);

    // =========================================================================
    // ESCENARIO SUM-FIN-01A: Cargo S/ 100, Pago S/ 100, Corrección a S/ 80
    // Resultado: Pago S/ 100, Cargo S/ 80, Saldo disponible a favor S/ 20
    // =========================================================================
    echo "--- ESCENARIO A: Reducción de consumo (S/ 100 -> S/ 80 con pago de S/ 100) ---\n";

    // Contrato A
    $pdo->exec("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES ('ARR-FIN-A-{$sufijo}', {$unidadId}, '2026-10-01', '2027-09-30', 5, 1000.00, 1000.00, 1000.00, 'VIGENTE', {$actorId})");
    $arrAId = (int) $pdo->lastInsertId();

    $cuentaFolioARepo = new CuentaFolio(null, "FOL-FIN-A-{$sufijo}", null, $personaId, 'PEN', 'ABIERTA', $actorId, $arrAId);
    $folioAId = $cuentaFolioRepo->crear($cuentaFolioARepo);

    // Medidor A
    $medA = $sumServicio->instalarMedidor([
        'suministro_id' => $sumId,
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadId,
        'numero_serie' => "MED-A-{$sufijo}",
        'fecha_instalacion' => '2026-10-01',
        'lectura_inicial' => '100.0000',
    ], $actorId);

    // Lectura errónea de fin de período: 200.0000 (Consumo = 100 kWh -> S/ 100.00)
    $lecErrA = $sumServicio->registrarLectura([
        'medidor_id' => $medA->obtenerId(),
        'fecha_lectura' => '2026-10-31',
        'valor_lectura' => '200.0000',
        'tipo_evento' => 'ORDINARIA',
    ], $actorId);

    // Liquidar período inicial (Rev 1) -> Cargo S/ 100.00
    $liqA1 = $sumServicio->liquidarPeriodoArrendamiento($arrAId, $sumId, '2026-10-01', '2026-10-31', '2026-11-05', $actorId);
    $cargoA1Id = $liqA1->obtenerCargoCuentaId();
    $cargoA1 = $cargoRepo->obtenerPorId($cargoA1Id);

    // Registrar Pago Real de S/ 100.00 con movimiento en caja
    $stmtCajaCountAntes = $pdo->prepare("SELECT COUNT(*) FROM movimientos_caja WHERE sesion_caja_id = ?");
    $stmtCajaCountAntes->execute([$sesionCajaId]);
    $conteoCajaAntesA = (int) $stmtCajaCountAntes->fetchColumn();

    $pagoA = new PagoCuenta(
        null,
        "PAG-A-{$sufijo}",
        $folioAId,
        $metodoPagoId,
        '100.00',
        '0.00',
        'PEN',
        $sesionCajaId,
        null,
        'REC-A-001',
        'CONFIRMADO',
        null,
        null,
        null,
        $actorId
    );
    $pagoAId = $pagoRepo->crear($pagoA);

    // Movimiento real en caja
    $pdo->exec("INSERT INTO movimientos_caja (sesion_caja_id, tipo_movimiento, pago_id, monto, moneda_codigo, concepto, actor_id, creado_en) VALUES ({$sesionCajaId}, 'INGRESO_COBRO', {$pagoAId}, 100.00, 'PEN', 'Cobro suministro A', {$actorId}, NOW())");
    $movCajaAId = (int) $pdo->lastInsertId();

    // Aplicar formalmente los S/ 100.00 del pago al cargo A1
    $codAplA1 = $aplicacionRepo->generarSiguienteCodigo();
    $aplA1 = new AplicacionPago(null, $codAplA1, $pagoAId, $cargoA1Id, '100.00', 'PEN', 'ACTIVA', null, null, $actorId);
    $aplA1Id = $aplicacionRepo->crear($aplA1);
    $pagoRepo->actualizarMontoAplicado($pagoAId, '100.00');
    $cargoRepo->actualizarMontoAplicado($cargoA1Id, '100.00');

    // Estado antes de corrección: Pago aplicado 100, Cargo cubierto 100
    // Ahora: El técnico detecta error de lectura. La lectura real al 31 de octubre fue 180.0000 (Consumo = 80 kWh -> S/ 80.00)
    $sumServicio->corregirLectura((int) $lecErrA->obtenerId(), '180.0000', 'Corrección de digitación en dial', $actorId);

    // Ejecutar reliquidación
    $liqA2 = $sumServicio->reliquidarPorCorreccion((int) $liqA1->obtenerId(), 'Ajuste de consumo según lectura corregida', $actorId);
    $cargoA2Id = $liqA2->obtenerCargoCuentaId();
    $cargoA2 = $cargoRepo->obtenerPorId($cargoA2Id);

    // Inspección post-reliquidación
    $pagoAPost = $pagoRepo->obtenerPorId($pagoAId);
    $cargoA1Post = $cargoRepo->obtenerPorId($cargoA1Id);
    $cargoA2Post = $cargoRepo->obtenerPorId($cargoA2Id);

    $aplA1Post = $aplicacionRepo->obtenerPorId($aplA1Id);
    $aplsCargoA2 = $aplicacionRepo->listarPorCargo($cargoA2Id);

    $stmtCajaCountDespuesA = $pdo->prepare("SELECT COUNT(*) FROM movimientos_caja WHERE sesion_caja_id = ?");
    $stmtCajaCountDespuesA->execute([$sesionCajaId]);
    $conteoCajaDespuesA = (int) $stmtCajaCountDespuesA->fetchColumn();

    $stmtMovCajaA = $pdo->prepare("SELECT * FROM movimientos_caja WHERE id = ?");
    $stmtMovCajaA->execute([$movCajaAId]);
    $movCajaAFila = $stmtMovCajaA->fetch(PDO::FETCH_ASSOC);

    // Verificaciones A:
    assertFin(
        $conteoCajaAntesA + 1 === $conteoCajaDespuesA &&
        (float) $movCajaAFila['monto'] === 100.00 &&
        $movCajaAFila['tipo_movimiento'] === 'INGRESO_COBRO',
        'SUM-FIN-01A-INV1',
        'Invariante 1: Movimiento de caja original permanece estrictamente INTACTO (S/ 100.00 ingreso)'
    );

    assertFin(
        $pagoAPost->obtenerId() === $pagoAId &&
        $pagoAPost->obtenerMontoTotal() === '100.00' &&
        $pagoAPost->estaConfirmado(),
        'SUM-FIN-01A-INV2',
        'Invariante 2: Pago original permanece INTACTO (monto total S/ 100.00 confirmado)'
    );

    assertFin(
        $aplA1Post !== null &&
        $aplA1Post->obtenerMontoAplicado() === '100.00' &&
        $aplA1Post->obtenerEstado() === 'REVERTIDA' &&
        !empty($aplA1Post->obtenerRevertidaEn()) &&
        $aplA1Post->obtenerRevertidaPorActorId() > 0,
        'SUM-FIN-01A-INV3-4',
        'Invariantes 3 y 4: Aplicación histórica original S/ 100.00 recuperable (monto > 0 preservado, estado REVERTIDA con fecha y actor)'
    );

    $montoReaplicadoA = count($aplsCargoA2) === 1 ? $aplsCargoA2[0]->obtenerMontoAplicado() : '0.00';
    $saldoDisponiblePagoA = $pagoAPost->calcularSaldoDisponible();
    $saldoPendienteCargoA2 = $cargoA2Post->calcularSaldoPendiente();

    assertFin(
        $cargoA2Post->obtenerTotal() === '80.00' &&
        $montoReaplicadoA === '80.00' &&
        $saldoPendienteCargoA2 === '0.00' &&
        $pagoAPost->obtenerMontoAplicado() === '80.00' &&
        $saldoDisponiblePagoA === '20.00',
        'SUM-FIN-01A-RESULTADO',
        'Resultado económico Escenario A: Cargo corregido S/ 80.00 cubierto al 100% y Saldo Favorable disponible = S/ 20.00'
    );

    // Reconciliación algebraica
    $stmtSumaAppsA = $pdo->prepare("SELECT SUM(CASE WHEN estado = 'ACTIVA' THEN monto_aplicado ELSE 0 END) as netas, SUM(CASE WHEN estado = 'REVERTIDA' THEN monto_aplicado ELSE 0 END) as revertidas, SUM(monto_aplicado) as total_emitidas FROM aplicaciones_pago WHERE pago_id = ?");
    $stmtSumaAppsA->execute([$pagoAId]);
    $sumasA = $stmtSumaAppsA->fetch(PDO::FETCH_ASSOC);

    assertFin(
        (float) $sumasA['netas'] === 80.00 &&
        (float) $sumasA['revertidas'] === 100.00 &&
        (float) $sumasA['total_emitidas'] === 180.00 &&
        bccomp(bcsub((string) $sumasA['total_emitidas'], (string) $sumasA['revertidas'], 2), (string) $sumasA['netas'], 2) === 0,
        'SUM-FIN-01A-INV5',
        'Invariante 5: Reconstrucción algebraica estricta: Total emitidas (180) - Revertidas (100) = Aplicación neta (80)'
    );

    // =========================================================================
    // ESCENARIO SUM-FIN-01B: Cargo S/ 100, Pago S/ 60, Corrección a S/ 80
    // Resultado: Pago S/ 60, Cargo S/ 80, Saldo pendiente = S/ 20
    // =========================================================================
    echo "\n--- ESCENARIO B: Pago parcial (Cargo S/ 100, Pago S/ 60, Corrección a S/ 80) ---\n";

    $pdo->exec("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado, creado_en) VALUES ({$propiedadId}, {$tipoUnidadId}, 'UNI-FIN-B-{$sufijo}', 'Unidad Fin B {$sufijo}', 'ACTIVO', NOW())");
    $unidadBId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES ('ARR-FIN-B-{$sufijo}', {$unidadBId}, '2026-10-01', '2027-09-30', 5, 1000.00, 1000.00, 1000.00, 'VIGENTE', {$actorId})");
    $arrBId = (int) $pdo->lastInsertId();

    $cuentaFolioBRepo = new CuentaFolio(null, "FOL-FIN-B-{$sufijo}", null, $personaId, 'PEN', 'ABIERTA', $actorId, $arrBId);
    $folioBId = $cuentaFolioRepo->crear($cuentaFolioBRepo);

    $medB = $sumServicio->instalarMedidor([
        'suministro_id' => $sumId,
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadBId,
        'numero_serie' => "MED-B-{$sufijo}",
        'fecha_instalacion' => '2026-10-01',
        'lectura_inicial' => '100.0000',
    ], $actorId);

    $lecErrB = $sumServicio->registrarLectura([
        'medidor_id' => $medB->obtenerId(),
        'fecha_lectura' => '2026-10-31',
        'valor_lectura' => '200.0000',
        'tipo_evento' => 'ORDINARIA',
    ], $actorId);

    // Liquidar período inicial -> S/ 100.00
    $liqB1 = $sumServicio->liquidarPeriodoArrendamiento($arrBId, $sumId, '2026-10-01', '2026-10-31', '2026-11-05', $actorId);
    $cargoB1Id = $liqB1->obtenerCargoCuentaId();

    // Pago parcial de S/ 60.00
    $pagoB = new PagoCuenta(
        null,
        "PAG-B-{$sufijo}",
        $folioBId,
        $metodoPagoId,
        '60.00',
        '0.00',
        'PEN',
        $sesionCajaId,
        null,
        'REC-B-001',
        'CONFIRMADO',
        null,
        null,
        null,
        $actorId
    );
    $pagoBId = $pagoRepo->crear($pagoB);

    $pdo->exec("INSERT INTO movimientos_caja (sesion_caja_id, tipo_movimiento, pago_id, monto, moneda_codigo, concepto, actor_id, creado_en) VALUES ({$sesionCajaId}, 'INGRESO_COBRO', {$pagoBId}, 60.00, 'PEN', 'Cobro parcial suministro B', {$actorId}, NOW())");
    $movCajaBId = (int) $pdo->lastInsertId();

    // Aplicar S/ 60.00 al cargo B1
    $codAplB1 = $aplicacionRepo->generarSiguienteCodigo();
    $aplB1 = new AplicacionPago(null, $codAplB1, $pagoBId, $cargoB1Id, '60.00', 'PEN', 'ACTIVA', null, null, $actorId);
    $aplB1Id = $aplicacionRepo->crear($aplB1);
    $pagoRepo->actualizarMontoAplicado($pagoBId, '60.00');
    $cargoRepo->actualizarMontoAplicado($cargoB1Id, '60.00');

    // Corregir lectura a 180.0000 (Consumo = 80 kWh -> S/ 80.00)
    $sumServicio->corregirLectura((int) $lecErrB->obtenerId(), '180.0000', 'Corrección de digitación en dial B', $actorId);

    // Reliquidar
    $liqB2 = $sumServicio->reliquidarPorCorreccion((int) $liqB1->obtenerId(), 'Ajuste de consumo según lectura B corregida', $actorId);
    $cargoB2Id = $liqB2->obtenerCargoCuentaId();

    $pagoBPost = $pagoRepo->obtenerPorId($pagoBId);
    $cargoB2Post = $cargoRepo->obtenerPorId($cargoB2Id);
    $aplB1Post = $aplicacionRepo->obtenerPorId($aplB1Id);
    $aplsCargoB2 = $aplicacionRepo->listarPorCargo($cargoB2Id);

    assertFin(
        $pagoBPost->obtenerMontoTotal() === '60.00' &&
        $pagoBPost->estaConfirmado(),
        'SUM-FIN-01B-PAGO',
        'Pago original de S/ 60.00 permanece INTACTO'
    );

    assertFin(
        $aplB1Post->obtenerMontoAplicado() === '60.00' &&
        $aplB1Post->obtenerEstado() === 'REVERTIDA',
        'SUM-FIN-01B-HISTORICO',
        'Aplicación histórica de S/ 60.00 preservada con estado REVERTIDA'
    );

    $montoReaplicadoB = count($aplsCargoB2) === 1 ? $aplsCargoB2[0]->obtenerMontoAplicado() : '0.00';
    $saldoPendienteCargoB2 = $cargoB2Post->calcularSaldoPendiente();

    assertFin(
        $cargoB2Post->obtenerTotal() === '80.00' &&
        $montoReaplicadoB === '60.00' &&
        $saldoPendienteCargoB2 === '20.00' &&
        $pagoBPost->obtenerMontoAplicado() === '60.00' &&
        $pagoBPost->calcularSaldoDisponible() === '0.00',
        'SUM-FIN-01B-RESULTADO',
        'Resultado económico Escenario B: Pago aplicado S/ 60.00, Cargo corregido S/ 80.00 -> Saldo pendiente exigible = S/ 20.00'
    );

    // =========================================================================
    // ESCENARIO SUM-FIN-01C: Cargo S/ 100, Pago S/ 100, Corrección a S/ 120
    // Resultado: Pago S/ 100, Cargo S/ 120, Saldo pendiente = S/ 20
    // =========================================================================
    echo "\n--- ESCENARIO C: Incremento de consumo (Cargo S/ 100, Pago S/ 100, Corrección a S/ 120) ---\n";

    $pdo->exec("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado, creado_en) VALUES ({$propiedadId}, {$tipoUnidadId}, 'UNI-FIN-C-{$sufijo}', 'Unidad Fin C {$sufijo}', 'ACTIVO', NOW())");
    $unidadCId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES ('ARR-FIN-C-{$sufijo}', {$unidadCId}, '2026-10-01', '2027-09-30', 5, 1000.00, 1000.00, 1000.00, 'VIGENTE', {$actorId})");
    $arrCId = (int) $pdo->lastInsertId();

    $cuentaFolioCRepo = new CuentaFolio(null, "FOL-FIN-C-{$sufijo}", null, $personaId, 'PEN', 'ABIERTA', $actorId, $arrCId);
    $folioCId = $cuentaFolioRepo->crear($cuentaFolioCRepo);

    $medC = $sumServicio->instalarMedidor([
        'suministro_id' => $sumId,
        'propiedad_id' => $propiedadId,
        'unidad_id' => $unidadCId,
        'numero_serie' => "MED-C-{$sufijo}",
        'fecha_instalacion' => '2026-10-01',
        'lectura_inicial' => '100.0000',
    ], $actorId);

    $lecErrC = $sumServicio->registrarLectura([
        'medidor_id' => $medC->obtenerId(),
        'fecha_lectura' => '2026-10-31',
        'valor_lectura' => '200.0000',
        'tipo_evento' => 'ORDINARIA',
    ], $actorId);

    // Liquidar período inicial -> S/ 100.00
    $liqC1 = $sumServicio->liquidarPeriodoArrendamiento($arrCId, $sumId, '2026-10-01', '2026-10-31', '2026-11-05', $actorId);
    $cargoC1Id = $liqC1->obtenerCargoCuentaId();

    // Pago total de S/ 100.00
    $pagoC = new PagoCuenta(
        null,
        "PAG-C-{$sufijo}",
        $folioCId,
        $metodoPagoId,
        '100.00',
        '0.00',
        'PEN',
        $sesionCajaId,
        null,
        'REC-C-001',
        'CONFIRMADO',
        null,
        null,
        null,
        $actorId
    );
    $pagoCId = $pagoRepo->crear($pagoC);

    $pdo->exec("INSERT INTO movimientos_caja (sesion_caja_id, tipo_movimiento, pago_id, monto, moneda_codigo, concepto, actor_id, creado_en) VALUES ({$sesionCajaId}, 'INGRESO_COBRO', {$pagoCId}, 100.00, 'PEN', 'Cobro suministro C', {$actorId}, NOW())");
    $movCajaCId = (int) $pdo->lastInsertId();

    // Aplicar S/ 100.00 al cargo C1
    $codAplC1 = $aplicacionRepo->generarSiguienteCodigo();
    $aplC1 = new AplicacionPago(null, $codAplC1, $pagoCId, $cargoC1Id, '100.00', 'PEN', 'ACTIVA', null, null, $actorId);
    $aplC1Id = $aplicacionRepo->crear($aplC1);
    $pagoRepo->actualizarMontoAplicado($pagoCId, '100.00');
    $cargoRepo->actualizarMontoAplicado($cargoC1Id, '100.00');

    // Corregir lectura hacia arriba: 220.0000 (Consumo = 120 kWh -> S/ 120.00)
    $sumServicio->corregirLectura((int) $lecErrC->obtenerId(), '220.0000', 'Corrección dial por subregistro inicial', $actorId);

    // Reliquidar
    $liqC2 = $sumServicio->reliquidarPorCorreccion((int) $liqC1->obtenerId(), 'Ajuste de consumo según lectura C corregida', $actorId);
    $cargoC2Id = $liqC2->obtenerCargoCuentaId();

    $pagoCPost = $pagoRepo->obtenerPorId($pagoCId);
    $cargoC2Post = $cargoRepo->obtenerPorId($cargoC2Id);
    $aplC1Post = $aplicacionRepo->obtenerPorId($aplC1Id);
    $aplsCargoC2 = $aplicacionRepo->listarPorCargo($cargoC2Id);

    assertFin(
        $pagoCPost->obtenerMontoTotal() === '100.00' &&
        $pagoCPost->estaConfirmado(),
        'SUM-FIN-01C-PAGO',
        'Pago original de S/ 100.00 permanece INTACTO'
    );

    assertFin(
        $aplC1Post->obtenerMontoAplicado() === '100.00' &&
        $aplC1Post->obtenerEstado() === 'REVERTIDA',
        'SUM-FIN-01C-HISTORICO',
        'Aplicación histórica de S/ 100.00 preservada con estado REVERTIDA'
    );

    $montoReaplicadoC = count($aplsCargoC2) === 1 ? $aplsCargoC2[0]->obtenerMontoAplicado() : '0.00';
    $saldoPendienteCargoC2 = $cargoC2Post->calcularSaldoPendiente();

    assertFin(
        $cargoC2Post->obtenerTotal() === '120.00' &&
        $montoReaplicadoC === '100.00' &&
        $saldoPendienteCargoC2 === '20.00' &&
        $pagoCPost->obtenerMontoAplicado() === '100.00' &&
        $pagoCPost->calcularSaldoDisponible() === '0.00',
        'SUM-FIN-01C-RESULTADO',
        'Resultado económico Escenario C: Pago aplicado S/ 100.00, Cargo corregido S/ 120.00 -> Saldo pendiente exigible = S/ 20.00'
    );

    echo "\n====================================================================\n";
    echo " RESULTADOS GATE FINANCIERO SUM-FIN-01: {$pass} / {$total} PASS\n";
    echo "====================================================================\n";

    if ($pass !== $total) {
        exit(1);
    }
    exit(0);

} catch (\Throwable $e) {
    echo "\n[ERROR FATAL GATE SUM-FIN-01]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
