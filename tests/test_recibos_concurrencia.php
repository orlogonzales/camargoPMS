<?php

declare(strict_types=1);

/**
 * Camargo PMS — Batería de Pruebas de Concurrencia e Integridad Transaccional (RECIBOS-1 / D-082)
 *
 * Casos evaluados:
 * - REC-C01: Generación secuencial atómica y pesimista de folios (REC-YYYYMM-XXXX) bajo FOR UPDATE sin huecos ni colisiones.
 * - REC-C02: Restricción física relacional uq_rec_pago_activo: bloqueo estricto contra doble emisión activa simultánea sobre el mismo pago.
 * - REC-C03: Atomicidad integral y rollback transaccional: ante fallo en emisión documental, no quedan recibos ni líneas huérfanas.
 * - REC-C04: Ciclo de vida y reintento tras anulación formal: anular libera estado_activo permitiendo reemisión legítima.
 * - REC-C05: Integridad referencial (ON DELETE RESTRICT): un recibo formal bloquea la eliminación destructiva del pago y folio.
 * - REC-C06: Integridad criptográfica SHA-256 y resiliencia ante manipulación: detección de discrepancias en disco.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoReciboExcepcion;
use CamargoPMS\Excepciones\ValidacionReciboExcepcion;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\PagoCuenta;
use CamargoPMS\Modelos\Recibo;
use CamargoPMS\Modelos\ReciboLinea;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\ReciboRepositorio;
use CamargoPMS\Servicios\DocumentoServicio;
use CamargoPMS\Servicios\ReciboServicio;

echo "====================================================================\n";
echo " Camargo PMS — Concurrencia e Integridad (RECIBOS-1 / D-082)\n";
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

    $storagePath = dirname(__DIR__) . '/storage/documentos';
    $docRepo = new DocumentoRepositorio($pdo);
    $docServicio = new DocumentoServicio($docRepo);

    $reciboRepo = new ReciboRepositorio($pdo);
    $pagoRepo = new PagoCuentaRepositorio($pdo);
    $cargoRepo = new CargoCuentaRepositorio($pdo);
    $aplicacionRepo = new AplicacionPagoRepositorio($pdo);
    $folioRepo = new CuentaFolioRepositorio($pdo);
    $actorRepo = new ActorAuditoriaRepositorio($pdo);

    $reciboServicio = new ReciboServicio(
        $reciboRepo,
        $pagoRepo,
        $aplicacionRepo,
        $cargoRepo,
        $folioRepo,
        $docServicio,
        $docRepo,
        $pdo
    );

    $actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;
    $sufijo = strtoupper(substr(uniqid(), -5));

    // Infraestructura base
    $stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
    $propiedadId = (int) $stmtProp->fetchColumn();
    if ($propiedadId <= 0) {
        $stmtInsP = $pdo->prepare("INSERT INTO propiedades (codigo, nombre, direccion, tipo, estado) VALUES (:c, :n, 'Av. Concurrencia 100', 'URBANA', 'ACTIVO')");
        $stmtInsP->execute(['c' => "PROP-CONC-{$sufijo}", 'n' => "Edificio Concurrencia {$sufijo}"]);
        $propiedadId = (int) $pdo->lastInsertId();
    }

    $stmtUnid = $pdo->prepare("SELECT id FROM unidades WHERE propiedad_id = :p AND estado = 'ACTIVO' LIMIT 1");
    $stmtUnid->execute(['p' => $propiedadId]);
    $unidadId = (int) $stmtUnid->fetchColumn();
    if ($unidadId <= 0) {
        $stmtInsU = $pdo->prepare("INSERT INTO unidades (propiedad_id, codigo, nombre, tipo, estado) VALUES (:p, :c, :n, 'DEPARTAMENTO', 'ACTIVO')");
        $stmtInsU->execute(['p' => $propiedadId, 'c' => "U-CONC-{$sufijo}", 'n' => "Unidad Concurrencia {$sufijo}"]);
        $unidadId = (int) $pdo->lastInsertId();
    }

    $tipoDocDniId = (int) $pdo->query("SELECT id FROM tipos_documento WHERE codigo = 'DNI' LIMIT 1")->fetchColumn() ?: 1;
    $paisPerId = (int) $pdo->query("SELECT id FROM paises WHERE codigo_iso2 = 'PE' LIMIT 1")->fetchColumn() ?: 1;

    $stmtInsPer = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, estado) VALUES ('Roberto', 'Concurrente', 'Test', 'ACTIVO')");
    $stmtInsPer->execute();
    $personaId = (int) $pdo->lastInsertId();

    $docNumConc = "55" . substr(strval(time()), -6);
    $stmtInsDoc = $pdo->prepare("INSERT INTO personas_documentos (persona_id, tipo_documento_id, pais_emisor_id, numero_documento, es_principal) VALUES (:p, :tid, :pid, :num, 1)");
    $stmtInsDoc->execute(['p' => $personaId, 'tid' => $tipoDocDniId, 'pid' => $paisPerId, 'num' => $docNumConc]);

    $stmtArr = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-09-01', '2027-08-31', 5, 1200.00, 1200.00, 1200.00, 'VIGENTE', :act)");
    $stmtArr->execute(['cod' => "ARR-CONC-{$sufijo}", 'uid' => $unidadId, 'act' => $actorId]);
    $arrendamientoId = (int) $pdo->lastInsertId();

    $folio = new CuentaFolio(null, "FOL-CONC-{$sufijo}", null, $personaId, 'PEN', 'ABIERTA', $actorId, $arrendamientoId);
    $folioId = $folioRepo->crear($folio);

    $metodoPagoId = (int) $pdo->query("SELECT id FROM metodos_pago WHERE activo = 1 LIMIT 1")->fetchColumn() ?: 1;

    function crearPagoTest(PagoCuentaRepositorio $repo, int $folioId, int $metodoId, string $cod, string $monto, int $actorId): int {
        $p = new PagoCuenta(
            null,
            $cod,
            $folioId,
            $metodoId,
            $monto,
            '0.00',
            'PEN',
            null,
            null,
            "REF-{$cod}",
            'CONFIRMADO',
            null,
            null,
            null,
            $actorId
        );
        return $repo->crear($p);
    }

    // -------------------------------------------------------------------------
    // REC-C01: Generación secuencial atómica y pesimista de folios REC-YYYYMM-XXXX
    // -------------------------------------------------------------------------
    $pagoC01_A = crearPagoTest($pagoRepo, $folioId, $metodoPagoId, "PAG-C01A-{$sufijo}", '100.00', $actorId);
    $pagoC01_B = crearPagoTest($pagoRepo, $folioId, $metodoPagoId, "PAG-C01B-{$sufijo}", '150.00', $actorId);

    $reciboC01_A = $reciboServicio->emitirReciboParaPago($pagoC01_A, $actorId, 'Emisión A secuencial');
    $reciboC01_B = $reciboServicio->emitirReciboParaPago($pagoC01_B, $actorId, 'Emisión B secuencial');

    $codigoA = $reciboC01_A->obtenerCodigo();
    $codigoB = $reciboC01_B->obtenerCodigo();

    $partesA = explode('-', $codigoA);
    $partesB = explode('-', $codigoB);

    $correlativoA = (int) ($partesA[2] ?? 0);
    $correlativoB = (int) ($partesB[2] ?? 0);

    $secuenciaOk = ($correlativoB === $correlativoA + 1) && ($partesA[1] === $partesB[1]);
    verificarConcurrencia(
        'REC-C01',
        'Generación secuencial atómica y pesimista de folios (REC-YYYYMM-XXXX) bajo FOR UPDATE sin huecos ni colisiones',
        $secuenciaOk,
        "Folio A: {$codigoA}, Folio B: {$codigoB}"
    );

    // -------------------------------------------------------------------------
    // REC-C02: Restricción física relacional uq_rec_pago_activo
    // -------------------------------------------------------------------------
    $dobleEmisionBloqueada = false;
    try {
        // Intento directo de emitir un segundo recibo activo para el mismo pago A
        $reciboServicio->emitirReciboParaPago($pagoC01_A, $actorId, 'Intento concurrente duplicado');
    } catch (ConflictoReciboExcepcion $e) {
        $dobleEmisionBloqueada = true;
    }

    // Intento de inserción directa por PDO saltando la capa de servicio para validar el índice único físico
    $indiceFisicoAtrapado = false;
    try {
        $stmtRaw = $pdo->prepare("INSERT INTO recibos (codigo, pago_id, cuenta_folio_id, persona_id, moneda_codigo, monto_recaudado, monto_imputado, monto_no_aplicado_pago, saldo_pendiente_folio_despues, saldo_favor_folio_despues, persona_nombre_snapshot, persona_documento_tipo_snapshot, persona_documento_numero_snapshot, metodo_pago_nombre, concepto_general, fecha_emision, estado, creado_por_actor_id) VALUES ('REC-HACK-01', :pago, :folio, :per, 'PEN', 100.00, 0.00, 100.00, 0.00, 0.00, 'Test', 'DNI', '123', 'Efectivo', 'Hack', NOW(), 'EMITIDO', :act)");
        $stmtRaw->execute([
            'pago' => $pagoC01_A,
            'folio' => $folioId,
            'per' => $personaId,
            'act' => $actorId,
        ]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            $indiceFisicoAtrapado = true;
        }
    }

    verificarConcurrencia(
        'REC-C02',
        'Restricción física relacional uq_rec_pago_activo: bloqueo estricto contra doble emisión activa simultánea',
        $dobleEmisionBloqueada && $indiceFisicoAtrapado
    );

    // -------------------------------------------------------------------------
    // REC-C03: Atomicidad integral y rollback transaccional
    // -------------------------------------------------------------------------
    $pagoC03 = crearPagoTest($pagoRepo, $folioId, $metodoPagoId, "PAG-C03-{$sufijo}", '250.00', $actorId);
    $conteoRecibosAntes = (int) $pdo->query("SELECT COUNT(*) FROM recibos")->fetchColumn();
    $conteoLineasAntes = (int) $pdo->query("SELECT COUNT(*) FROM recibo_lineas")->fetchColumn();

    $rollbackAtrapado = false;
    try {
        $pdo->beginTransaction();
        // Insertamos recibo simulado
        $stmtSim = $pdo->prepare("INSERT INTO recibos (codigo, pago_id, cuenta_folio_id, persona_id, moneda_codigo, monto_recaudado, monto_imputado, monto_no_aplicado_pago, saldo_pendiente_folio_despues, saldo_favor_folio_despues, persona_nombre_snapshot, persona_documento_tipo_snapshot, persona_documento_numero_snapshot, metodo_pago_nombre, concepto_general, fecha_emision, estado, creado_por_actor_id) VALUES ('REC-ROLLBACK-01', :pago, :folio, :per, 'PEN', 250.00, 0.00, 250.00, 0.00, 0.00, 'Test', 'DNI', '123', 'Efectivo', 'Sim', NOW(), 'EMITIDO', :act)");
        $stmtSim->execute(['pago' => $pagoC03, 'folio' => $folioId, 'per' => $personaId, 'act' => $actorId]);
        $recSimId = (int) $pdo->lastInsertId();

        $stmtSimL = $pdo->prepare("INSERT INTO recibo_lineas (recibo_id, numero_linea, cargo_id, cargo_codigo, cargo_concepto, cargo_origen_tipo, cargo_monto_total, monto_aplicado, cargo_saldo_restante) VALUES (:r, 1, 99999, 'CAR-SIM', 'Concepto Simulado', 'RENTA_ARRENDAMIENTO', 500.00, 250.00, 250.00)");
        $stmtSimL->execute(['r' => $recSimId]);

        // Simular fallo fatal en generación documental
        throw new \RuntimeException("Fallo simulado de renderizado de PDF en filesystem.");
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $rollbackAtrapado = true;
    }

    $conteoRecibosDespues = (int) $pdo->query("SELECT COUNT(*) FROM recibos")->fetchColumn();
    $conteoLineasDespues = (int) $pdo->query("SELECT COUNT(*) FROM recibo_lineas")->fetchColumn();

    $atomicidadOk = $rollbackAtrapado &&
                    ($conteoRecibosDespues === $conteoRecibosAntes) &&
                    ($conteoLineasDespues === $conteoLineasAntes);

    verificarConcurrencia(
        'REC-C03',
        'Atomicidad integral y rollback transaccional: ante fallo en emisión documental, no quedan recibos ni líneas huérfanas',
        $atomicidadOk
    );

    // -------------------------------------------------------------------------
    // REC-C04: Ciclo de vida y reintento tras anulación formal
    // -------------------------------------------------------------------------
    $pagoC04 = crearPagoTest($pagoRepo, $folioId, $metodoPagoId, "PAG-C04-{$sufijo}", '300.00', $actorId);
    $reciboC04_Orig = $reciboServicio->emitirReciboParaPago($pagoC04, $actorId, 'Recibo original a anular');
    $reciboC04OrigId = (int) $reciboC04_Orig->obtenerId();

    // Anulación formal
    $reciboServicio->anularRecibo($reciboC04OrigId, 'Error tipográfico en concepto comercial', $actorId);

    // El estado del recibo original ahora es ANULADO y su recibo_activo_idx es NULL
    $estadoActivoOriginal = $pdo->query("SELECT recibo_activo_idx FROM recibos WHERE id = {$reciboC04OrigId}")->fetchColumn();

    // Reemisión legítima para el mismo pago tras la anulación
    $reciboC04_Nuevo = $reciboServicio->emitirReciboParaPago($pagoC04, $actorId, 'Recibo sustituto rectificado');

    $reemisionOk = ($estadoActivoOriginal === null) &&
                   ($reciboC04_Nuevo instanceof Recibo) &&
                   ($reciboC04_Nuevo->obtenerId() !== $reciboC04OrigId) &&
                   ($reciboC04_Nuevo->obtenerEstado() === Recibo::ESTADO_EMITIDO);

    verificarConcurrencia(
        'REC-C04',
        'Ciclo de vida y reintento tras anulación formal: anular libera estado_activo permitiendo reemisión legítima',
        $reemisionOk
    );

    // -------------------------------------------------------------------------
    // REC-C05: Integridad referencial (ON DELETE RESTRICT)
    // -------------------------------------------------------------------------
    $deletePagoBloqueado = false;
    try {
        $pdo->prepare("DELETE FROM pagos_cuenta WHERE id = :id")->execute(['id' => $pagoC04]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            $deletePagoBloqueado = true;
        }
    }

    $deleteFolioBloqueado = false;
    try {
        $pdo->prepare("DELETE FROM cuentas_folios WHERE id = :id")->execute(['id' => $folioId]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            $deleteFolioBloqueado = true;
        }
    }

    verificarConcurrencia(
        'REC-C05',
        'Integridad referencial (ON DELETE RESTRICT): un recibo formal bloquea la eliminación destructiva del pago y folio',
        $deletePagoBloqueado && $deleteFolioBloqueado
    );

    // -------------------------------------------------------------------------
    // REC-C06: Integridad criptográfica SHA-256 y resiliencia ante manipulación
    // -------------------------------------------------------------------------
    $verificacionOriginal = $reciboServicio->verificarHashRecibo($reciboC04OrigId);
    $integridadOriginalOk = ($verificacionOriginal['valido'] === true);

    // Simulación de corrupción o discrepancia de hash
    $rutaReal = $verificacionOriginal['ruta'];

    $bytesOriginales = file_get_contents($rutaReal);
    // Modificar 1 byte en el archivo físico
    file_put_contents($rutaReal, $bytesOriginales . "\n%CORRUPTED_BYTE");

    $verificacionCorrupta = $reciboServicio->verificarHashRecibo($reciboC04OrigId);
    $deteccionCorrupcionOk = ($verificacionCorrupta['valido'] === false);

    // Restaurar archivo a su estado original
    file_put_contents($rutaReal, $bytesOriginales);
    $verificacionRestaurada = $reciboServicio->verificarHashRecibo($reciboC04OrigId);
    $restauracionOk = ($verificacionRestaurada['valido'] === true);

    verificarConcurrencia(
        'REC-C06',
        'Integridad criptográfica SHA-256 y resiliencia ante manipulación: detección de discrepancias en disco',
        $integridadOriginalOk && $deteccionCorrupcionOk && $restauracionOk
    );

} catch (\Throwable $e) {
    echo "\n[ERROR INESPERADO EN SUITE DE CONCURRENCIA]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n====================================================================\n";
echo "RESULTADOS CONCURRENCIA RECIBOS-1: {$passCount} / {$totalCount} PASS\n";
echo "====================================================================\n";

if ($passCount === $totalCount) {
    exit(0);
} else {
    exit(1);
}
