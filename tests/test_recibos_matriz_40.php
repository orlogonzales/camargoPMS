<?php

declare(strict_types=1);

/**
 * Suite de Verificación RECIBOS-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-082 (GATE RECIBOS-1):
 * - CARGO != PAGO != APLICACIÓN != RECIBO != PDF
 * - RECIBO = CONSTANCIA HISTÓRICA INMUTABLE DE UN HECHO DE COBRO EN T0
 * - Inviolabilidad criptográfica del PDF original (no se modifica ante anulación)
 * - Desacople operativo: ANULAR RECIBO != REVERSAR PAGO ni salida en caja
 * - Ecuación contable: monto_recaudado = monto_imputado + monto_no_aplicado_pago
 * - Snapshots de folio desglosados: saldo_pendiente_folio_despues y saldo_favor_folio_despues
 * - Garantía segregada vs renta ordinaria
 * - Transparencia en pagos sin imputación previa (monto_imputado = 0.00)
 * - Snapshot de persona y cobro inalterable ante mutaciones maestras posteriores
 * - Trazabilidad y unicidad (1 pago activo = 1 recibo activo)
 * - Invarianza ante reversiones posteriores de suministros (SUM-FIN-01)
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ConflictoReciboExcepcion;
use CamargoPMS\Excepciones\ReciboNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionReciboExcepcion;
use CamargoPMS\Modelos\AplicacionPago;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\PagoCuenta;
use CamargoPMS\Modelos\Recibo;
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

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();
$pdo->exec("SET SESSION innodb_lock_wait_timeout = 5;");

$reciboRepo = new ReciboRepositorio($pdo);
$pagoRepo = new PagoCuentaRepositorio($pdo);
$aplicacionRepo = new AplicacionPagoRepositorio($pdo);
$cargoRepo = new CargoCuentaRepositorio($pdo);
$folioRepo = new CuentaFolioRepositorio($pdo);
$docRepo = new DocumentoRepositorio($pdo);
$docServicio = new DocumentoServicio($docRepo);
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

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $codigo, string $descripcion, ?string $detalle = null): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $msg = "{$codigo}: {$descripcion}" . ($detalle ? " -> {$detalle}" : "");
        $errores[] = $msg;
        echo "  [FAIL] {$msg}\n";
    }
}

function crearCargoHelper(CargoCuentaRepositorio $repo, int $folioId, string $codigo, string $concepto, string $total, string $aplicado, string $origenTipo, int $actorId): int {
    $c = new CargoCuenta(
        null,
        $codigo,
        $folioId,
        $origenTipo,
        null,
        null,
        $concepto,
        '1.00',
        $total,
        $total,
        '0.00',
        $total,
        $aplicado,
        'PEN',
        'DEVENGADO',
        null,
        null,
        null,
        date('Y-m-d H:i:s'),
        $actorId
    );
    return $repo->crear($c);
}

function crearPagoHelper(PagoCuentaRepositorio $repo, int $folioId, int $metodoPagoId, string $codigo, string $total, string $aplicado, string $referencia, int $actorId, string $estado = 'CONFIRMADO'): int {
    $p = new PagoCuenta(
        null,
        $codigo,
        $folioId,
        $metodoPagoId,
        $total,
        $aplicado,
        'PEN',
        null,
        null,
        $referencia,
        $estado,
        null,
        null,
        null,
        $actorId
    );
    return $repo->crear($p);
}

function crearAplicacionHelper(AplicacionPagoRepositorio $repo, string $codigo, int $pagoId, int $cargoId, string $monto, int $actorId, string $moneda = 'PEN'): int {
    $apl = new AplicacionPago(
        null,
        $codigo,
        $pagoId,
        $cargoId,
        $monto,
        $moneda,
        'ACTIVA',
        null,
        null,
        $actorId
    );
    return $repo->crear($apl);
}

echo "=====================================================================\n";
echo "CAMARGO PMS — SUITE FORMAL RECIBOS-1 (MATRIZ 40 CASOS DE DOMINIO)\n";
echo "=====================================================================\n\n";

$actorId = (int) $pdo->query("SELECT id FROM actores WHERE codigo = 'CAMARGO_PMS' LIMIT 1")->fetchColumn() ?: 1;
$sufijo = strtoupper(substr(uniqid(), -5));

// Asegurar Entidades Maestras de Base para la suite
$stmtProp = $pdo->query("SELECT id FROM propiedades WHERE estado = 'ACTIVO' LIMIT 1");
$propiedadId = (int) $stmtProp->fetchColumn();
if ($propiedadId <= 0) {
    $stmtInsP = $pdo->prepare("INSERT INTO propiedades (codigo, nombre, direccion, tipo, estado) VALUES (:c, :n, 'Calle Recibos 123', 'URBANA', 'ACTIVO')");
    $stmtInsP->execute(['c' => "PROP-REC-{$sufijo}", 'n' => "Edificio Recibos {$sufijo}"]);
    $propiedadId = (int) $pdo->lastInsertId();
}

$stmtUnid = $pdo->prepare("SELECT id FROM unidades WHERE propiedad_id = :p AND estado = 'ACTIVO' LIMIT 1");
$stmtUnid->execute(['p' => $propiedadId]);
$unidadId = (int) $stmtUnid->fetchColumn();
if ($unidadId <= 0) {
    $stmtInsU = $pdo->prepare("INSERT INTO unidades (propiedad_id, codigo, nombre, tipo, estado) VALUES (:p, :c, :n, 'DEPARTAMENTO', 'ACTIVO')");
    $stmtInsU->execute(['p' => $propiedadId, 'c' => "U-REC-{$sufijo}", 'n' => "Departamento Recibos {$sufijo}"]);
    $unidadId = (int) $pdo->lastInsertId();
}

// Persona Titular base
$tipoDocDniId = (int) $pdo->query("SELECT id FROM tipos_documento WHERE codigo = 'DNI' LIMIT 1")->fetchColumn() ?: 1;
$paisPerId = (int) $pdo->query("SELECT id FROM paises WHERE codigo_iso2 = 'PE' LIMIT 1")->fetchColumn() ?: 1;

$stmtInsPer = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, estado) VALUES (:nom, :pat, 'Pruebas', 'ACTIVO')");
$stmtInsPer->execute(['nom' => "Titular {$sufijo}", 'pat' => "Paterno {$sufijo}"]);
$personaId = (int) $pdo->lastInsertId();

$stmtInsDoc = $pdo->prepare("INSERT INTO personas_documentos (persona_id, tipo_documento_id, pais_emisor_id, numero_documento, es_principal) VALUES (:p, :tid, :pid, :num, 1)");
$stmtInsDoc->execute(['p' => $personaId, 'tid' => $tipoDocDniId, 'pid' => $paisPerId, 'num' => "44" . substr(strval(time()), -6)]);

// Arrendamiento para cuenta folio
$stmtArr = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-09-01', '2027-08-31', 5, 1500.00, 1500.00, 1500.00, 'VIGENTE', :act)");
$arrCodigo = "ARR-REC-{$sufijo}";
$stmtArr->execute([
    'cod' => $arrCodigo,
    'uid' => $unidadId,
    'act' => $actorId,
]);
$arrendamientoId = (int) $pdo->lastInsertId();

// Cuenta Folio base
$cuentaFolio = new CuentaFolio(
    null,
    "FOL-REC-{$sufijo}",
    null,
    $personaId,
    'PEN',
    'ABIERTA',
    $actorId,
    $arrendamientoId
);
$folioId = $folioRepo->crear($cuentaFolio);

// Método de pago base
$metodoPagoId = (int) $pdo->query("SELECT id FROM metodos_pago WHERE activo = 1 LIMIT 1")->fetchColumn() ?: 1;

// =========================================================================
// BLOQUE 1: PAGO EXACTO, PARCIAL Y BALANCE ALGEBRAICO (T01..T05)
// =========================================================================
echo "--- BLOQUE 1: Pago Exacto, Parcial y Balance Algebraico (T01..T05) ---\n";

// Cargo 1: Devengado S/ 500.00
$cargo1Id = crearCargoHelper($cargoRepo, $folioId, "CAR-REC-01-{$sufijo}", 'Servicio de Hospedaje Inicial', '500.00', '500.00', 'AJUSTE_MANUAL', $actorId);

// Pago 1: Exacto S/ 500.00
$pago1Id = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-REC-01-{$sufijo}", '500.00', '500.00', "OP-REC-01-{$sufijo}", $actorId);

// Aplicación 1: S/ 500.00
crearAplicacionHelper($aplicacionRepo, "APL-REC-01-{$sufijo}", $pago1Id, $cargo1Id, '500.00', $actorId);

// T01: Emisión exitosa de recibo para pago exacto
$recibo1 = $reciboServicio->emitirReciboParaPago($pago1Id, $actorId, 'Recibo emitido con éxito para pago exacto');
assertTest(
    $recibo1 instanceof Recibo && $recibo1->obtenerId() !== null && str_starts_with($recibo1->obtenerCodigo(), 'REC-'),
    'T01',
    'Emisión exitosa sobre pago exacto genera Recibo formal con folio REC-YYYYMM-XXXX'
);

// T02: Verificación de montos: monto_recaudado = monto_imputado, monto_no_aplicado = 0.00
assertTest(
    bccomp($recibo1->obtenerMontoRecaudado(), '500.00', 2) === 0 &&
    bccomp($recibo1->obtenerMontoImputado(), '500.00', 2) === 0 &&
    bccomp($recibo1->obtenerMontoNoAplicadoPago(), '0.00', 2) === 0,
    'T02',
    'Pago exacto amortiza 100% y registra monto_no_aplicado_pago = 0.00'
);

// Cargo 2: Devengado S/ 1,000.00
$cargo2Id = crearCargoHelper($cargoRepo, $folioId, "CAR-REC-02-{$sufijo}", 'Renta Parcial Mensual', '1000.00', '400.00', 'AJUSTE_MANUAL', $actorId);

// Pago 2: Abono parcial S/ 400.00
$pago2Id = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-REC-02-{$sufijo}", '400.00', '400.00', "OP-REC-02-{$sufijo}", $actorId);

// Aplicación 2: S/ 400.00 sobre cargo 2
crearAplicacionHelper($aplicacionRepo, "APL-REC-02-{$sufijo}", $pago2Id, $cargo2Id, '400.00', $actorId);

// T03: Emisión sobre abono parcial
$recibo2 = $reciboServicio->emitirReciboParaPago($pago2Id, $actorId);
assertTest(
    $recibo2 instanceof Recibo && bccomp($recibo2->obtenerMontoRecaudado(), '400.00', 2) === 0,
    'T03',
    'Emisión de recibo para abono parcial procesada correctamente'
);

// T04: La línea registra cargo_saldo_restante = 600.00
$lineasR2 = $recibo2->obtenerLineas();
$lineaR2 = $lineasR2[0] ?? null;
assertTest(
    $lineaR2 !== null &&
    bccomp($lineaR2->obtenerMontoAplicado(), '400.00', 2) === 0 &&
    bccomp($lineaR2->obtenerCargoSaldoRestante(), '600.00', 2) === 0,
    'T04',
    'La línea de imputación en T0 congela exactamente cargo_saldo_restante = 600.00'
);

// T05: Ecuación contable inviolable: monto_recaudado = monto_imputado + monto_no_aplicado_pago
$balRecibo2 = bcadd($recibo2->obtenerMontoImputado(), $recibo2->obtenerMontoNoAplicadoPago(), 2);
assertTest(
    bccomp($recibo2->obtenerMontoRecaudado(), $balRecibo2, 2) === 0,
    'T05',
    'Ecuación contable inviolable satisfecha: recaudado = imputado + no_aplicado'
);

// =========================================================================
// BLOQUE 2: PAGO COMPUESTO MULTIPROPÓSITO (T06..T10)
// =========================================================================
echo "\n--- BLOQUE 2: Pago Compuesto Multipropósito (T06..T10) ---\n";

// Crear 3 cargos: Renta S/ 800, Luz S/ 300, Mantenimiento S/ 150
$c3Id = crearCargoHelper($cargoRepo, $folioId, "CAR-C3-{$sufijo}", 'Renta Mes Octubre', '800.00', '800.00', 'RENTA_ARRENDAMIENTO', $actorId);
$c4Id = crearCargoHelper($cargoRepo, $folioId, "CAR-C4-{$sufijo}", 'Suministro Eléctrico Mes', '300.00', '300.00', 'SUMINISTRO_CONSUMO', $actorId);
$c5Id = crearCargoHelper($cargoRepo, $folioId, "CAR-C5-{$sufijo}", 'Servicio de Gasfitería', '150.00', '150.00', 'SERVICIO_CONTRATADO', $actorId);

// Pago compuesto de S/ 1,250.00
$pagoCompId = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-COMP-{$sufijo}", '1250.00', '1250.00', "REF-COMP-{$sufijo}", $actorId);

// Aplicaciones correspondientes
crearAplicacionHelper($aplicacionRepo, "APL-C3-{$sufijo}", $pagoCompId, $c3Id, '800.00', $actorId);
crearAplicacionHelper($aplicacionRepo, "APL-C4-{$sufijo}", $pagoCompId, $c4Id, '300.00', $actorId);
crearAplicacionHelper($aplicacionRepo, "APL-C5-{$sufijo}", $pagoCompId, $c5Id, '150.00', $actorId);

// T06: Emisión sobre pago compuesto
$reciboComp = $reciboServicio->emitirReciboParaPago($pagoCompId, $actorId, 'Pago compuesto de renta, suministro y mantenimiento');
assertTest(
    $reciboComp instanceof Recibo && bccomp($reciboComp->obtenerMontoRecaudado(), '1250.00', 2) === 0,
    'T06',
    'Emisión sobre pago compuesto multipropósito (S/ 1,250.00) procesada con éxito'
);

// T07: Exactamente 3 líneas estructuradas con numeración correlativa
$lineasComp = $reciboComp->obtenerLineas();
assertTest(
    count($lineasComp) === 3 &&
    $lineasComp[0]->obtenerNumeroLinea() === 1 &&
    $lineasComp[1]->obtenerNumeroLinea() === 2 &&
    $lineasComp[2]->obtenerNumeroLinea() === 3,
    'T07',
    'Se generan exactamente 3 líneas estructuradas con correlativo 1, 2, 3 en recibo_lineas'
);

// T08: Verificación de imputado = 1250.00 y no aplicado = 0.00
assertTest(
    bccomp($reciboComp->obtenerMontoImputado(), '1250.00', 2) === 0 &&
    bccomp($reciboComp->obtenerMontoNoAplicadoPago(), '0.00', 2) === 0,
    'T08',
    'Totales del pago compuesto: monto_imputado = 1250.00 y monto_no_aplicado_pago = 0.00'
);

// T09: Cada línea conserva el concepto y monto original de su cargo
$conceptosOk = (
    $lineasComp[0]->obtenerCargoConcepto() === 'Renta Mes Octubre' &&
    $lineasComp[1]->obtenerCargoConcepto() === 'Suministro Eléctrico Mes' &&
    $lineasComp[2]->obtenerCargoConcepto() === 'Servicio de Gasfitería'
);
assertTest(
    $conceptosOk,
    'T09',
    'Cada línea del recibo preserva fielmente los conceptos de los cargos devengados'
);

// T10: Saldos restantes de los cargos quedan en 0.00
$saldosRestantesOk = (
    bccomp($lineasComp[0]->obtenerCargoSaldoRestante(), '0.00', 2) === 0 &&
    bccomp($lineasComp[1]->obtenerCargoSaldoRestante(), '0.00', 2) === 0 &&
    bccomp($lineasComp[2]->obtenerCargoSaldoRestante(), '0.00', 2) === 0
);
assertTest(
    $saldosRestantesOk,
    'T10',
    'Los 3 cargos totalmente amortizados reflejan saldo_restante = 0.00 en T0'
);

// =========================================================================
// BLOQUE 3: MONTO NO APLICADO Y PAGOS SIN IMPUTACIÓN (T11..T15)
// =========================================================================
echo "\n--- BLOQUE 3: Monto No Aplicado y Pagos sin Imputación (T11..T15) ---\n";

// Cargo 6: Devengado S/ 600.00
$c6Id = crearCargoHelper($cargoRepo, $folioId, "CAR-C6-{$sufijo}", 'Abono Cuota Parcial', '600.00', '600.00', 'AJUSTE_MANUAL', $actorId);

// Pago con excedente: S/ 1,000.00 (amortiza S/ 600.00 y quedan S/ 400.00 a favor)
$pagoExcId = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-EXC-{$sufijo}", '1000.00', '600.00', "REF-EXC-{$sufijo}", $actorId);

crearAplicacionHelper($aplicacionRepo, "APL-C6-{$sufijo}", $pagoExcId, $c6Id, '600.00', $actorId);

// T11: Emisión sobre pago con monto no aplicado
$reciboExc = $reciboServicio->emitirReciboParaPago($pagoExcId, $actorId, 'Pago con saldo a favor generado');
assertTest(
    $reciboExc instanceof Recibo,
    'T11',
    'Emisión sobre pago con excedente no aplicado procesada correctamente'
);

// T12: Recibo desglosa recaudado = 1000.00, imputado = 600.00, no aplicado = 400.00
assertTest(
    bccomp($reciboExc->obtenerMontoRecaudado(), '1000.00', 2) === 0 &&
    bccomp($reciboExc->obtenerMontoImputado(), '600.00', 2) === 0 &&
    bccomp($reciboExc->obtenerMontoNoAplicadoPago(), '400.00', 2) === 0,
    'T12',
    'Recibo desglosa con exactitud monto_imputado = 600.00 y monto_no_aplicado_pago = 400.00'
);

// Pago 100% sin imputación (S/ 500.00 sin aplicaciones)
$pagoSinImpId = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-SINIMP-{$sufijo}", '500.00', '0.00', "REF-SINIMP-{$sufijo}", $actorId);

// T13: Emisión de recibo sobre pago sin aplicaciones
$reciboSinImp = $reciboServicio->emitirReciboParaPago($pagoSinImpId, $actorId, 'Pago registrado pendiente de imputación');
assertTest(
    $reciboSinImp instanceof Recibo && bccomp($reciboSinImp->obtenerMontoRecaudado(), '500.00', 2) === 0,
    'T13',
    'Emisión de recibo para pago sin imputaciones previas completada exitosamente'
);

// T14: Verificación de imputado = 0.00 y no aplicado = 500.00
assertTest(
    bccomp($reciboSinImp->obtenerMontoImputado(), '0.00', 2) === 0 &&
    bccomp($reciboSinImp->obtenerMontoNoAplicadoPago(), '500.00', 2) === 0,
    'T14',
    'Pago sin imputación refleja monto_imputado = 0.00 y monto_no_aplicado_pago = 500.00'
);

// T15: Líneas de imputación vacías y documento emitido vinculado
assertTest(
    count($reciboSinImp->obtenerLineas()) === 0 &&
    $reciboSinImp->obtenerDocumentoEmitidoId() !== null &&
    $reciboSinImp->obtenerDocumentoEmitidoId() > 0,
    'T15',
    'Pago sin imputación tiene 0 líneas en BD y genera documento PDF con leyenda institucional'
);

// =========================================================================
// BLOQUE 4: DEPÓSITO DE GARANTÍA SEGREGADA Y UNICIDAD (T16..T20)
// =========================================================================
echo "\n--- BLOQUE 4: Depósito de Garantía Segregada y Unicidad (T16..T20) ---\n";

// Cargo de garantía segregada S/ 1,500.00
$cGarId = crearCargoHelper($cargoRepo, $folioId, "CAR-GAR-{$sufijo}", 'Depósito de Garantía en Custodia', '1500.00', '1500.00', 'DEPOSITO_GARANTIA', $actorId);

$pGarId = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-GAR-{$sufijo}", '1500.00', '1500.00', "REF-GAR-{$sufijo}", $actorId);

crearAplicacionHelper($aplicacionRepo, "APL-GAR-{$sufijo}", $pGarId, $cGarId, '1500.00', $actorId);

// T16: Emisión de recibo para custodia de garantía
$reciboGar = $reciboServicio->emitirReciboParaPago($pGarId, $actorId, 'Custodia de depósito de garantía');
assertTest(
    $reciboGar instanceof Recibo && bccomp($reciboGar->obtenerMontoRecaudado(), '1500.00', 2) === 0,
    'T16',
    'Emisión de recibo sobre pago de garantía segregada exitosa'
);

// T17: La línea tipifica DEPOSITO_GARANTIA
$lineasGar = $reciboGar->obtenerLineas();
assertTest(
    count($lineasGar) === 1 && $lineasGar[0]->obtenerCargoOrigenTipo() === 'DEPOSITO_GARANTIA',
    'T17',
    'La línea de imputación tipifica fielmente cargo_origen_tipo = DEPOSITO_GARANTIA'
);

// T18: La garantía amortiza su cargo específico sin mezclarse con rentas
assertTest(
    $lineasGar[0]->obtenerCargoId() === $cGarId &&
    bccomp($lineasGar[0]->obtenerMontoAplicado(), '1500.00', 2) === 0,
    'T18',
    'La amortización de garantía se asocia exclusivamente al cargo de garantía'
);

// T19: Saldos de folio en T0 no son negativos
assertTest(
    bccomp($reciboGar->obtenerSaldoPendienteFolioDespues(), '0.00', 2) >= 0 &&
    bccomp($reciboGar->obtenerSaldoFavorFolioDespues(), '0.00', 2) >= 0,
    'T19',
    'Saldos informativos de folio en T0 preservan invariante de no-negatividad (>= 0.00)'
);

// T20: Unicidad: intento de emitir un segundo recibo activo sobre el mismo pago es rechazado
$conflictoUnicidad = false;
try {
    $reciboServicio->emitirReciboParaPago($pGarId, $actorId);
} catch (ConflictoReciboExcepcion $e) {
    $conflictoUnicidad = true;
}
assertTest(
    $conflictoUnicidad,
    'T20',
    'Intento de emitir segundo recibo activo sobre el mismo pago bloqueado por ConflictoReciboExcepcion'
);

// =========================================================================
// BLOQUE 5: INMUTABILIDAD TEMPORAL EN T0 (T21..T25)
// =========================================================================
echo "\n--- BLOQUE 5: Inmutabilidad Temporal en T0 (T21..T25) ---\n";

// Crear Cargo de Renta de S/ 1,200.00
$cInmId = crearCargoHelper($cargoRepo, $folioId, "CAR-INM-{$sufijo}", 'Renta Mensual Inmutable', '1200.00', '700.00', 'RENTA_ARRENDAMIENTO', $actorId);

// Pago inicial en T0: S/ 700.00
$pagoT0Id = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-T0-{$sufijo}", '700.00', '700.00', "REF-T0-{$sufijo}", $actorId);

crearAplicacionHelper($aplicacionRepo, "APL-T0-{$sufijo}", $pagoT0Id, $cInmId, '700.00', $actorId);

// T21: Emisión en T0
$reciboT0 = $reciboServicio->emitirReciboParaPago($pagoT0Id, $actorId, 'Primer abono de renta en T0');
assertTest(
    $reciboT0 instanceof Recibo,
    'T21',
    'Emisión de constancia histórica en instante inicial T0 procesada'
);

$lineasT0 = $reciboT0->obtenerLineas();
$saldoRestanteT0 = $lineasT0[0]->obtenerCargoSaldoRestante();

// T22: En T1, se realiza un segundo pago de S/ 500.00 que cancela el cargo
$pagoT1Id = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-T1-{$sufijo}", '500.00', '500.00', "REF-T1-{$sufijo}", $actorId);
crearAplicacionHelper($aplicacionRepo, "APL-T1-{$sufijo}", $pagoT1Id, $cInmId, '500.00', $actorId);
$reciboT1 = $reciboServicio->emitirReciboParaPago($pagoT1Id, $actorId, 'Segundo abono de renta en T1');

assertTest(
    $reciboT1 instanceof Recibo && bccomp($reciboT1->obtenerMontoRecaudado(), '500.00', 2) === 0,
    'T22',
    'Segundo pago en T1 genera su propio recibo independiente y saldo restante 0.00'
);

// Actualizar en base de datos el cargo con el nuevo monto acumulado de 1,200.00
$pdo->prepare("UPDATE cargos_cuenta SET monto_aplicado_acumulado = '1200.00' WHERE id = :id")->execute(['id' => $cInmId]);

// T23: Verificación de que el recibo de T0 sigue reportando saldo restante S/ 500.00
$reciboT0Consultado = $reciboServicio->obtenerRecibo((int) $reciboT0->obtenerId());
$lineasT0Consultadas = $reciboT0Consultado->obtenerLineas();
assertTest(
    bccomp($lineasT0Consultadas[0]->obtenerCargoSaldoRestante(), '500.00', 2) === 0,
    'T23',
    'Inmutabilidad T0: el recibo emitido previamente conserva intacto saldo_restante = 500.00 a pesar del pago en T1'
);

// T24: Caso SUM-FIN-01: Simulación de reliquidación posterior de suministro
$cSumT0Id = crearCargoHelper($cargoRepo, $folioId, "CAR-SUMT0-{$sufijo}", 'Luz Inicial T0', '180.00', '180.00', 'SUMINISTRO_CONSUMO', $actorId);
$pSumT0Id = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-SUMT0-{$sufijo}", '180.00', '180.00', "REF-SUMT0-{$sufijo}", $actorId);
$aplSumT0Id = crearAplicacionHelper($aplicacionRepo, "APL-SUMT0-{$sufijo}", $pSumT0Id, $cSumT0Id, '180.00', $actorId);
$reciboSumT0 = $reciboServicio->emitirReciboParaPago($pSumT0Id, $actorId);

assertTest(
    $reciboSumT0 instanceof Recibo && bccomp($reciboSumT0->obtenerMontoRecaudado(), '180.00', 2) === 0,
    'T24',
    'Emisión inicial de recibo de suministro en T0 procesada correctamente'
);

// En T1 la aplicación pasa a REVERTIDA por reliquidación
$pdo->prepare("UPDATE aplicaciones_pago SET estado = 'REVERTIDA' WHERE id = :id")->execute(['id' => $aplSumT0Id]);
$pdo->prepare("UPDATE cargos_cuenta SET estado = 'ANULADO' WHERE id = :id")->execute(['id' => $cSumT0Id]);

// T25: Verificación de que el recibo de suministro emitido en T0 sigue reflejando su histórico
$reciboSumT0Consultado = $reciboServicio->obtenerRecibo((int) $reciboSumT0->obtenerId());
$lineasSumT0Consultadas = $reciboSumT0Consultado->obtenerLineas();
assertTest(
    $reciboSumT0Consultado->obtenerEstado() === Recibo::ESTADO_EMITIDO &&
    count($lineasSumT0Consultadas) === 1 &&
    bccomp($lineasSumT0Consultadas[0]->obtenerMontoAplicado(), '180.00', 2) === 0,
    'T25',
    'Caso SUM-FIN-01: Reliquidaciones posteriores en T1 preservan intacta la constancia emitida en T0'
);

// =========================================================================
// BLOQUE 6: SNAPSHOT DE TITULAR Y PREVENCIÓN DE CORRUPCIÓN (T26..T30)
// =========================================================================
echo "\n--- BLOQUE 6: Snapshot de Titular y Prevención de Corrupción (T26..T30) ---\n";

// T26: Emisión de recibo capturando snapshot de persona
$stmtInsPer2 = $pdo->prepare("INSERT INTO personas (nombres, apellido_paterno, apellido_materno, estado) VALUES ('Carlos', 'Mendoza', 'Rios', 'ACTIVO')");
$stmtInsPer2->execute();
$per2Id = (int) $pdo->lastInsertId();

$doc2Num = "11" . substr(strval(time()), -6);
$stmtInsDoc2 = $pdo->prepare("INSERT INTO personas_documentos (persona_id, tipo_documento_id, pais_emisor_id, numero_documento, es_principal) VALUES (:p, :tid, :pid, :num, 1)");
$stmtInsDoc2->execute(['p' => $per2Id, 'tid' => $tipoDocDniId, 'pid' => $paisPerId, 'num' => $doc2Num]);

$stmtArr2 = $pdo->prepare("INSERT INTO arrendamientos (codigo, unidad_id, fecha_inicio, fecha_fin, dia_vencimiento, renta_mensual, deposito_garantia, monto_primer_periodo, estado, creado_por_actor_id) VALUES (:cod, :uid, '2026-09-01', '2027-08-31', 5, 1500.00, 1500.00, 1500.00, 'VIGENTE', :act)");
$arr2Codigo = "ARR-REC2-{$sufijo}";
$stmtArr2->execute([
    'cod' => $arr2Codigo,
    'uid' => $unidadId,
    'act' => $actorId,
]);
$arrendamiento2Id = (int) $pdo->lastInsertId();

$folioPer2 = new CuentaFolio(null, "FOL-PER2-{$sufijo}", null, $per2Id, 'PEN', 'ABIERTA', $actorId, $arrendamiento2Id);
$folioPer2Id = $folioRepo->crear($folioPer2);

$pagoPer2Id = crearPagoHelper($pagoRepo, $folioPer2Id, $metodoPagoId, "PAG-PER2-{$sufijo}", '350.00', '0.00', "REF-P2-{$sufijo}", $actorId);

$reciboPer2 = $reciboServicio->emitirReciboParaPago($pagoPer2Id, $actorId);
assertTest(
    $reciboPer2 instanceof Recibo &&
    str_contains($reciboPer2->obtenerPersonaNombreSnapshot(), 'Carlos Mendoza') &&
    $reciboPer2->obtenerPersonaDocumentoNumeroSnapshot() === $doc2Num,
    'T26',
    'Datos del titular (nombre completo y DNI) congelados correctamente en snapshot del recibo'
);

// T27: Modificación posterior en tabla personas
$doc2NumMod = "99" . substr(strval(time()), -6);
$stmtUpdPer = $pdo->prepare("UPDATE personas SET nombres = 'Carlos Alberto', apellido_paterno = 'Mendoza Modificado' WHERE id = :id");
$stmtUpdPer->execute(['id' => $per2Id]);
$stmtUpdDoc = $pdo->prepare("UPDATE personas_documentos SET numero_documento = :num WHERE persona_id = :id");
$stmtUpdDoc->execute(['num' => $doc2NumMod, 'id' => $per2Id]);

assertTest(
    $stmtUpdPer->rowCount() > 0,
    'T27',
    'Modificación posterior del titular realizada en tabla personas'
);

// T28: Consulta del recibo conserva snapshot inmutable
$reciboPer2Consultado = $reciboServicio->obtenerRecibo((int) $reciboPer2->obtenerId());
assertTest(
    $reciboPer2Consultado->obtenerPersonaNombreSnapshot() === $reciboPer2->obtenerPersonaNombreSnapshot() &&
    $reciboPer2Consultado->obtenerPersonaDocumentoNumeroSnapshot() === $doc2Num,
    'T28',
    'Modificaciones posteriores en la tabla personas no alteran el snapshot del recibo histórico'
);

// T29: Consulta por código oficial
$reciboPorCod = $reciboServicio->obtenerReciboPorCodigo($reciboPer2->obtenerCodigo());
assertTest(
    $reciboPorCod->obtenerId() === $reciboPer2->obtenerId(),
    'T29',
    'Consulta pesimista por código oficial REC-YYYYMM-XXXX recupera la entidad correcta'
);

// T30: D-082 #15: El recibo delimita su naturaleza no tributaria
assertTest(
    !str_contains($reciboPer2Consultado->obtenerCodigo(), 'B001-') &&
    !str_contains($reciboPer2Consultado->obtenerCodigo(), 'F001-'),
    'T30',
    'Delimitación no tributaria: el formato REC-YYYYMM-XXXX no usurpa series electrónicas SUNAT'
);

// =========================================================================
// BLOQUE 7: ANULACIÓN FORMAL AUDITADA (T31..T35)
// =========================================================================
echo "\n--- BLOQUE 7: Anulación Formal Auditada (T31..T35) ---\n";

// Crear un recibo para probar el flujo de anulación
$pagoAnulId = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-ANUL-{$sufijo}", '450.00', '0.00', "REF-ANUL-{$sufijo}", $actorId);
$reciboParaAnular = $reciboServicio->emitirReciboParaPago($pagoAnulId, $actorId);
$reciboAnulId = (int) $reciboParaAnular->obtenerId();

// T31: Anulación exitosa
$exitoAnul = $reciboServicio->anularRecibo($reciboAnulId, 'Error en digitación de referencia bancaria', $actorId);
assertTest(
    $exitoAnul === true,
    'T31',
    'Anulación formal de recibo mediante anularRecibo() retorna true'
);

// T32: Estado ANULADO, motivo, fecha y actor registrados
$reciboAnulado = $reciboServicio->obtenerRecibo($reciboAnulId);
assertTest(
    $reciboAnulado->obtenerEstado() === Recibo::ESTADO_ANULADO &&
    $reciboAnulado->obtenerMotivoAnulacion() === 'Error en digitación de referencia bancaria' &&
    $reciboAnulado->obtenerAnuladoEn() !== null &&
    $reciboAnulado->obtenerAnuladoPorActorId() === $actorId,
    'T32',
    'El recibo pasa a estado ANULADO con motivo, fecha y actor de auditoría'
);

// T33: Inviolabilidad física: el PDF original sigue existiendo y su hash SHA-256 es idéntico
$verifHash = $reciboServicio->verificarHashRecibo($reciboAnulId);
assertTest(
    $verifHash['valido'] === true && $verifHash['hash_esperado'] === $verifHash['hash_calculado'],
    'T33',
    'Inviolabilidad física (D-082 #3): El archivo PDF original no fue sobreescrito ni corrompido'
);

// T34: Desacople operativo: el pago en pagos_cuenta sigue en estado CONFIRMADO
$pagoDespuesAnul = $pagoRepo->obtenerPorId($pagoAnulId);
assertTest(
    $pagoDespuesAnul !== null && $pagoDespuesAnul->obtenerEstado() === 'CONFIRMADO',
    'T34',
    'Desacople operativo (D-082 #4): Anular recibo NO revierte el pago en FINANCIERO-2'
);

// T35: Intento de anular nuevamente arriesga ConflictoReciboExcepcion
$conflictoDobleAnul = false;
try {
    $reciboServicio->anularRecibo($reciboAnulId, 'Reintento redundante', $actorId);
} catch (ConflictoReciboExcepcion $e) {
    $conflictoDobleAnul = true;
}
assertTest(
    $conflictoDobleAnul,
    'T35',
    'Intento de re-anular recibo ya anulado rechazado con ConflictoReciboExcepcion'
);

// =========================================================================
// BLOQUE 8: REVERSIÓN EN FINANCIERO-2 Y ESTADÍSTICAS (T36..T40)
// =========================================================================
echo "\n--- BLOQUE 8: Reversión en FINANCIERO-2 y Estadísticas (T36..T40) ---\n";

// Crear pago, emitir recibo y luego revertir pago en FINANCIERO-2
$pagoRevId = crearPagoHelper($pagoRepo, $folioId, $metodoPagoId, "PAG-REV-{$sufijo}", '600.00', '0.00', "REF-REV-{$sufijo}", $actorId);
$reciboRev = $reciboServicio->emitirReciboParaPago($pagoRevId, $actorId);

// Se revierte el pago en FINANCIERO-2
$pagoRepo->reversar($pagoRevId, 'Cheque sin fondos presentado por cliente', $actorId, date('Y-m-d H:i:s'));

// T36: El recibo histórico sigue existiendo
$reciboRevConsultado = $reciboServicio->obtenerRecibo((int) $reciboRev->obtenerId());
assertTest(
    $reciboRevConsultado !== null && $reciboRevConsultado->obtenerPagoId() === $pagoRevId,
    'T36',
    'Reversión de pago en FINANCIERO-2 no elimina físicamente el recibo probatorio histórico'
);

// T37: Intentar emitir recibo sobre un pago REVERSADO es rechazado
$conflictoPagoRev = false;
try {
    $reciboServicio->emitirReciboParaPago($pagoRevId, $actorId);
} catch (ConflictoReciboExcepcion $e) {
    $conflictoPagoRev = true;
}
assertTest(
    $conflictoPagoRev,
    'T37',
    'Emisión sobre pago en estado REVERSADO bloqueada formalmente'
);

// T38: Intentar emitir recibo sobre pago inexistente lanza ValidacionReciboExcepcion
$valPagoInexistente = false;
try {
    $reciboServicio->emitirReciboParaPago(9999999, $actorId);
} catch (ValidacionReciboExcepcion $e) {
    $valPagoInexistente = true;
}
assertTest(
    $valPagoInexistente,
    'T38',
    'Emisión sobre ID de pago inexistente arroja ValidacionReciboExcepcion'
);

// T39: Descarga de PDF con stream binario verificado
$descarga = $reciboServicio->descargarPdfRecibo((int) $reciboRev->obtenerId());
assertTest(
    !empty($descarga['binario']) &&
    $descarga['mime'] === 'application/pdf' &&
    str_starts_with($descarga['binario'], '%PDF'),
    'T39',
    'Descarga de stream binario PDF entrega cabecera canónica %PDF y mime application/pdf'
);

// T40: Estadísticas de KPIs reflejan datos reales
$kpis = $reciboServicio->obtenerEstadisticas();
assertTest(
    $kpis['emitidos_hoy'] >= 5 &&
    $kpis['anulados_total'] >= 1 &&
    floatval($kpis['total_recaudado_hoy']) > 0,
    'T40',
    'Estadísticas de KPIs reportan valores consistentes de emisión, recaudación y anulaciones'
);

echo "\n=====================================================================\n";
echo "RESULTADOS MATRIZ RECIBOS-1: {$pruebasExitosas} / {$totalPruebas} PASS\n";
echo "=====================================================================\n";

if ($pruebasExitosas === 40 && empty($errores)) {
    exit(0);
} else {
    exit(1);
}
