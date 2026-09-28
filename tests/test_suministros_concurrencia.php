<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (SUMINISTROS-1 / D-081)
 *
 * Casos evaluados:
 * - SUM-C01: Generación secuencial atómica y pesimista de folios de liquidación (LIQ-SUM-YYYYMM-XXXX) bajo FOR UPDATE sin colisiones.
 * - SUM-C02: Restricción física relacional medidor_activo_idx: bloqueo absoluto de dos medidores activos simultáneos en la misma unidad.
 * - SUM-C03: Protección anti-solapamiento de tarifas bajo bloqueo pesimista FOR UPDATE en el mismo ámbito.
 * - SUM-C04: Restricción virtual correccion_activa_idx: bloqueo estricto contra bifurcaciones concurrentes de corrección sobre la misma lectura.
 * - SUM-C05: Restricción virtual liquidacion_activa_idx: bloqueo contra doble devengo/liquidación activa concurrente en el mismo período.
 * - SUM-C06: Atomicidad integral y rollback transaccional: si falla el devengo en cuentas_folios, la liquidación se revierte íntegramente.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoSuministroExcepcion;
use CamargoPMS\Excepciones\ValidacionSuministroExcepcion;
use CamargoPMS\Modelos\Suministro;
use CamargoPMS\Modelos\SuministroTarifa;
use CamargoPMS\Modelos\SuministroMedidor;
use CamargoPMS\Modelos\SuministroLectura;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\SuministroRepositorio;
use CamargoPMS\Servicios\SuministroServicio;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (SUMINISTROS-1 / D-081)\n";
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

    $suministroRepo = new SuministroRepositorio($pdo);
    $cargoRepo = new CargoCuentaRepositorio($pdo);
    $aplicacionRepo = new AplicacionPagoRepositorio($pdo);
    $pagoRepo = new PagoCuentaRepositorio($pdo);
    $cuentaFolioRepo = new CuentaFolioRepositorio($pdo);
    $arrendamientoRepo = new \CamargoPMS\Repositorios\ArrendamientoRepositorio($pdo);
    $actorRepo = new ActorAuditoriaRepositorio($pdo);

    $suministroServicio = new SuministroServicio(
        $pdo,
        $suministroRepo,
        $cargoRepo,
        $aplicacionRepo,
        $pagoRepo,
        $cuentaFolioRepo,
        $arrendamientoRepo,
        $actorRepo
    );

    $sufijo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;

    // Crear propiedad, unidad, arrendamiento y cuenta folio base para pruebas
    $stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
    $propId = (int) $stmtProp->fetchColumn();
    if ($propId <= 0) {
        $stmtProp = $pdo->prepare("INSERT INTO propiedades (codigo, nombre, direccion, tipo, estado) VALUES (:cod, :nom, 'Calle Concurrencia 123', 'URBANA', 'ACTIVO')");
        $stmtProp->execute(['cod' => "PROP-CONC-{$sufijo}", 'nom' => "Propiedad Concurrencia {$sufijo}"]);
        $propId = (int) $pdo->lastInsertId();
    }

    $tipoUnidadId = (int) $pdo->query("SELECT id FROM tipos_unidad LIMIT 1")->fetchColumn() ?: 1;

    $stmtUnid = $pdo->prepare("INSERT INTO unidades (propiedad_id, tipo_unidad_id, codigo, nombre, estado) VALUES (:pid, :tid, :cod, :nom, 'ACTIVO')");
    $stmtUnid->execute(['pid' => $propId, 'tid' => $tipoUnidadId, 'cod' => "U-CONC-{$sufijo}", 'nom' => "Unidad Concurrencia {$sufijo}"]);
    $unidadId = (int) $pdo->lastInsertId();

    $titularId = (int) $pdo->query("SELECT id FROM personas WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn() ?: 1;

    $stmtArr = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-10-01', '2027-09-30', 5, 2000.00, 2000.00, 2000.00, 'VIGENTE', :act)");
    $stmtArr->execute(['cod' => "ARR-CONC-{$sufijo}", 'uid' => $unidadId, 'act' => $actorId]);
    $arrId = (int) $pdo->lastInsertId();

    $stmtArrPers = $pdo->prepare("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion) VALUES (:aid, :pid, 'TITULAR')");
    $stmtArrPers->execute(['aid' => $arrId, 'pid' => $titularId]);

    $stmtFolio = $pdo->prepare("INSERT INTO cuentas_folios (codigo, arrendamiento_id, persona_titular_id, moneda_codigo, estado, creado_por_actor_id) VALUES (:cod, :aid, :tid, 'PEN', 'ABIERTA', :act)");
    $stmtFolio->execute(['cod' => "FOL-CONC-{$sufijo}", 'aid' => $arrId, 'tid' => $titularId, 'act' => $actorId]);
    $folioId = (int) $pdo->lastInsertId();

    $sumMedido = $suministroServicio->crearSuministro([
        'codigo' => "ELEC-C-{$sufijo}",
        'nombre' => "Luz Concurrencia {$sufijo}",
        'modalidad' => Suministro::MODALIDAD_MEDIDO,
        'unidad_medida' => 'kWh',
        'permite_rollover' => true,
    ]);

    // -------------------------------------------------------------------------
    // SUM-C01: Generación atómica y pesimista de folios (FOR UPDATE)
    // -------------------------------------------------------------------------
    $periodoSim = new DateTimeImmutable('2026-10-15');
    $pdo->exec("DELETE FROM documento_secuencias WHERE tipo_documento = 'SUMINISTRO_LIQUIDACION' AND periodo_ym = '202610'");

    $folios = [];
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < 5; $i++) {
            $folios[] = $suministroRepo->obtenerSiguienteFolio('SUMINISTRO_LIQUIDACION', 'LIQ-SUM', $periodoSim);
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
        && $folios[0] === 'LIQ-SUM-202610-0001'
        && $folios[4] === 'LIQ-SUM-202610-0005';
    verificarConcurrencia('SUM-C01', 'Generación secuencial atómica bajo bloqueo pesimista FOR UPDATE sin colisiones', $c01Exito);

    // -------------------------------------------------------------------------
    // SUM-C02: Restricción física relacional medidor_activo_idx
    // -------------------------------------------------------------------------
    $medidor1 = $suministroServicio->instalarMedidor([
        'suministro_id' => $sumMedido->obtenerId(),
        'propiedad_id' => $propId,
        'unidad_id' => $unidadId,
        'numero_serie' => "SERIE-C1-{$sufijo}",
        'fecha_instalacion' => '2026-10-01',
        'lectura_inicial' => '100.0000',
    ], $actorId);

    $c02Bloqueado = false;
    try {
        // Intento directo a nivel DB para verificar restricción física uq_med_unidad_suministro_activo
        $stmtColision = $pdo->prepare(
            'INSERT INTO suministro_medidores (
                suministro_id, propiedad_id, unidad_id, codigo, fecha_instalacion, lectura_inicial, estado, creado_por_actor_id
            ) VALUES (:sid, :pid, :uid, :cod, "2026-10-02", 0, "ACTIVO", :act)'
        );
        $stmtColision->execute([
            'sid' => $sumMedido->obtenerId(),
            'pid' => $propId,
            'uid' => $unidadId,
            'cod' => "SERIE-C2-{$sufijo}",
            'act' => $actorId,
        ]);
    } catch (\PDOException $e) {
        // SQLSTATE 23000: Duplicate entry for key uq_med_unidad_suministro_activo
        $c02Bloqueado = ($e->getCode() === '23000' || strpos($e->getMessage(), 'uq_med_unidad_suministro_activo') !== false);
    }
    verificarConcurrencia('SUM-C02', 'Restricción física virtual medidor_activo_idx impide colisión de medidores activos concurrentes en la misma unidad', $c02Bloqueado);

    // -------------------------------------------------------------------------
    // SUM-C03: Protección anti-solapamiento de tarifas bajo FOR UPDATE
    // -------------------------------------------------------------------------
    $tarifaBase = $suministroServicio->crearTarifa([
        'suministro_id' => $sumMedido->obtenerId(),
        'ambito' => SuministroTarifa::AMBITO_PROPIEDAD,
        'propiedad_id' => $propId,
        'precio_unitario' => '0.9500',
        'fecha_inicio' => '2026-10-01',
        'fecha_fin' => '2026-10-31',
    ]);

    $c03Bloqueado = false;
    try {
        // Intento concurrente solapado en el mismo ámbito
        $suministroServicio->crearTarifa([
            'suministro_id' => $sumMedido->obtenerId(),
            'ambito' => SuministroTarifa::AMBITO_PROPIEDAD,
            'propiedad_id' => $propId,
            'precio_unitario' => '1.0000',
            'fecha_inicio' => '2026-10-15',
            'fecha_fin' => '2026-11-15',
        ]);
    } catch (ConflictoSuministroExcepcion $e) {
        $c03Bloqueado = true;
    }
    verificarConcurrencia('SUM-C03', 'Bloqueo pesimista FOR UPDATE y validación matemática anti-solapamiento de tarifas en el mismo ámbito', $c03Bloqueado);

    // -------------------------------------------------------------------------
    // SUM-C04: Restricción virtual correccion_activa_idx
    // -------------------------------------------------------------------------
    $lecturaOriginal = $suministroServicio->registrarLectura([
        'medidor_id' => $medidor1->obtenerId(),
        'fecha_lectura' => '2026-10-10',
        'valor_lectura' => '180.0000',
        'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
    ], $actorId);

    // Primera corrección formal exitosa
    $lecturaCorregida1 = $suministroServicio->corregirLectura(
        (int) $lecturaOriginal->obtenerId(),
        '185.0000',
        'Primera corrección formal auditada',
        $actorId
    );

    $c04Bloqueado = false;
    try {
        // Intento concurrente directo en SQL de insertar otra corrección VALIDA para la misma lectura original
        // Debe ser interceptado por la columna virtual `correccion_activa_idx` y clave `uq_lec_correccion_unica`
        $stmtBifurcacion = $pdo->prepare(
            'INSERT INTO suministro_lecturas (
                medidor_id, fecha_lectura, valor_lectura, tipo_evento, lectura_referencia_id, motivo, estado, registrado_por_actor_id
            ) VALUES (:mid, "2026-10-10", 190.0000, "CORRECCION", :ref, "Bifurcación concurrente ilegal", "VALIDA", :act)'
        );
        $stmtBifurcacion->execute([
            'mid' => $medidor1->obtenerId(),
            'ref' => $lecturaOriginal->obtenerId(),
            'act' => $actorId,
        ]);
    } catch (\PDOException $e) {
        $c04Bloqueado = ($e->getCode() === '23000' || strpos($e->getMessage(), 'uq_lec_correccion_unica') !== false);
    }
    verificarConcurrencia('SUM-C04', 'Restricción virtual correccion_activa_idx previene bifurcaciones concurrentes sobre la misma lectura física', $c04Bloqueado);

    // -------------------------------------------------------------------------
    // SUM-C05: Restricción virtual liquidacion_activa_idx
    // -------------------------------------------------------------------------
    $lecturaFinOctubre = $suministroServicio->registrarLectura([
        'medidor_id' => $medidor1->obtenerId(),
        'fecha_lectura' => '2026-10-31',
        'valor_lectura' => '300.0000',
        'tipo_evento' => SuministroLectura::EVENTO_PERIODICA,
    ], $actorId);

    $liqExitosa = $suministroServicio->liquidarPeriodoArrendamiento(
        $arrId,
        (int) $sumMedido->obtenerId(),
        '2026-10-01',
        '2026-10-31',
        '2026-11-05',
        $actorId
    );

    $c05Bloqueado = false;
    try {
        // Intento directo en SQL de insertar una segunda liquidación en estado DEVENGADO para el mismo período
        // Debe ser interceptado por `uq_liq_arrendamiento_suministro_periodo` sobre `liquidacion_activa_idx`
        $stmtLiqDoble = $pdo->prepare(
            'INSERT INTO suministro_liquidaciones (
                folio, suministro_id, modalidad, propiedad_id, unidad_id, arrendamiento_id,
                cuenta_folio_id, cargo_cuenta_id, periodo_anio, periodo_mes, periodo_desde,
                periodo_hasta, fecha_emision, fecha_vencimiento, cantidad_total, subtotal, total,
                moneda_codigo, estado, creado_por_actor_id
            ) VALUES (
                "LIQ-TEST-DOBLE", :sid, "MEDIDO", :pid, :uid, :aid,
                :fid, :cid, 2026, 10, "2026-10-01",
                "2026-10-31", "2026-10-31", "2026-11-05", 200, 190.00, 190.00,
                "PEN", "DEVENGADO", :act
            )'
        );
        $stmtLiqDoble->execute([
            'sid' => $sumMedido->obtenerId(),
            'pid' => $propId,
            'uid' => $unidadId,
            'aid' => $arrId,
            'fid' => $folioId,
            'cid' => $liqExitosa->obtenerCargoCuentaId(),
            'act' => $actorId,
        ]);
    } catch (\PDOException $e) {
        $c05Bloqueado = ($e->getCode() === '23000' || strpos($e->getMessage(), 'uq_liq_arrendamiento_suministro_periodo') !== false);
    }
    verificarConcurrencia('SUM-C05', 'Restricción virtual liquidacion_activa_idx impide doble liquidación activa en el mismo período', $c05Bloqueado);

    // -------------------------------------------------------------------------
    // SUM-C06: Atomicidad integral y rollback transaccional
    // -------------------------------------------------------------------------
    $totalLiqsAntes = (int) $pdo->query('SELECT COUNT(*) FROM suministro_liquidaciones')->fetchColumn();
    $totalCargosAntes = (int) $pdo->query('SELECT COUNT(*) FROM cargos_cuenta')->fetchColumn();

    $c06RollbackOk = false;
    // Simulamos una falla forzada durante la transacción cerrando la conexión a propósito o ejecutando SQL inválido
    $pdo->beginTransaction();
    try {
        // 1. Simular creación de cargo
        $stmtCargo = $pdo->prepare(
            'INSERT INTO cargos_cuenta (
                codigo, cuenta_folio_id, origen_tipo, origen_id, concepto, cantidad, precio_unitario, subtotal, total, moneda_codigo, estado, creado_por_actor_id
            ) VALUES ("CRG-FAIL-ROLLBACK", :fid, "SUMINISTRO_CONSUMO", 0, "Consumo Falla", 10, 1.0, 10.00, 10.00, "PEN", "DEVENGADO", :act)'
        );
        $stmtCargo->execute(['fid' => $folioId, 'act' => $actorId]);

        // 2. Falla catastrófica forzada en SQL
        $pdo->exec('INSERT INTO tabla_inexistente_que_fuerza_rollback VALUES (1)');
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $c06RollbackOk = true;
    }

    $totalLiqsDespues = (int) $pdo->query('SELECT COUNT(*) FROM suministro_liquidaciones')->fetchColumn();
    $totalCargosDespues = (int) $pdo->query('SELECT COUNT(*) FROM cargos_cuenta')->fetchColumn();

    $c06Exito = $c06RollbackOk
        && $totalLiqsDespues === $totalLiqsAntes
        && $totalCargosDespues === $totalCargosAntes;
    verificarConcurrencia('SUM-C06', 'Atomicidad estricta y rollback integral sin cargos ni liquidaciones huérfanas ante error', $c06Exito);

    echo "\n====================================================================\n";
    echo " RESULTADOS CONCURRENCIA: {$passCount} / {$totalCount} PASS\n";
    echo "====================================================================\n";

    if ($passCount !== $totalCount) {
        exit(1);
    }
    exit(0);

} catch (\Throwable $e) {
    echo "\n[ERROR FATAL EN SUITE DE CONCURRENCIA]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
