<?php

declare(strict_types=1);

/**
 * Suite de Verificación ARRENDAMIENTOS-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 * 
 * Verifica las 6 decisiones vinculantes de D-076:
 * - Separación ontológica: RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - Temporalidad a plazo cerrado (fecha_fin NOT NULL).
 * - Semántica hotelera semiabierta [fecha_inicio, fecha_fin).
 * - Materialización sparse en inventario_diario_unidades (tipo_bloqueo = 'ARRENDAMIENTO').
 * - Idempotencia recurrente en cuotas de renta.
 * - Custodia segregada de garantía con saldo reconstructible.
 * - Extensión compatible de folios y FINANCIERO-2.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Nucleo/Ayudante.php';

use CamargoPMS\Excepciones\ArrendamientoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoArrendamientoInvalidoExcepcion;
use CamargoPMS\Excepciones\GarantiaInvalidaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Arrendamiento;
use CamargoPMS\Modelos\ArrendamientoCuota;
use CamargoPMS\Modelos\ArrendamientoGarantia;
use CamargoPMS\Modelos\ArrendamientoHistorialEstado;
use CamargoPMS\Modelos\ArrendamientoPersona;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Servicios\ArrendamientoServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$totalPruebas = 0;
$pruebasExitosas = 0;
$errores = [];

function assertTest(bool $condicion, string $codigo, string $descripcion): void {
    global $totalPruebas, $pruebasExitosas, $errores;
    $totalPruebas++;
    if ($condicion) {
        $pruebasExitosas++;
        echo "  [PASS] {$codigo}: {$descripcion}\n";
    } else {
        $errores[] = "{$codigo}: {$descripcion}";
        echo "  [FAIL] {$codigo}: {$descripcion}\n";
    }
}

echo "=====================================================================\n";
echo "CAMARGO PMS — SUITE FORMAL ARRENDAMIENTOS-1 (MATRIZ 40 CASOS)\n";
echo "=====================================================================\n\n";

// Asegurar datos base
$stmt = $pdo->query("SELECT id FROM unidades WHERE estado = 'ACTIVO' LIMIT 3");
$unidadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$unidadId = (int) ($unidadIds[0] ?? 1);
$unidad2Id = (int) ($unidadIds[1] ?? $unidadId);
$unidad3Id = (int) ($unidadIds[2] ?? $unidadId);

$stmt = $pdo->query("SELECT id FROM personas LIMIT 2");
$personaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$titularId = (int) ($personaIds[0] ?? 1);
$cotitularId = (int) ($personaIds[1] ?? 2);

$actorId = 1;
$servicio = new ArrendamientoServicio($pdo);

// -----------------------------------------------------------------------------
// BLOQUE 1: Modelos de Dominio y Excepciones Tipadas (ARR-01 a ARR-03)
// -----------------------------------------------------------------------------
echo "[1/7] Modelos de Dominio y Excepciones Tipadas...\n";

$arrModelo = new Arrendamiento(
    10, 'ARR-20260927-0001', $unidadId, null, '2026-10-01', '2027-03-31', 5,
    '2500.00', '2500.00', '2500.00', false, 'PEN', 'BORRADOR', null, null, null, null, 1
);
assertTest(
    $arrModelo->obtenerCodigo() === 'ARR-20260927-0001' && $arrModelo->esBorrador(),
    'ARR-01',
    'Modelo Arrendamiento instanciable con getters e invariantes'
);

$exNoEnc = new ArrendamientoNoEncontradoExcepcion();
$exEstado = new EstadoArrendamientoInvalidoExcepcion('FINALIZADO', 'ACTIVAR');
$exGarantia = new GarantiaInvalidaExcepcion('Monto supera saldo retenido');
assertTest(
    $exNoEnc->getCode() === 404 && $exEstado->getCode() === 422 && $exGarantia->getCode() === 422,
    'ARR-02',
    'Excepciones tipadas con códigos HTTP canónicos (404, 422)'
);

$arrRepo = new \CamargoPMS\Repositorios\ArrendamientoRepositorio($pdo);
$cod1 = $arrRepo->generarSiguienteCodigo();
assertTest(
    str_starts_with($cod1, 'ARR-' . date('Ymd') . '-'),
    'ARR-03',
    "Generación correlativa de código con formato ARR-YYYYMMDD-XXXX ({$cod1})"
);

// -----------------------------------------------------------------------------
// BLOQUE 2: Creación, Validaciones y Prorrateo Inicial (ARR-04 a ARR-10)
// -----------------------------------------------------------------------------
echo "\n[2/7] Creación, Validaciones y Prorrateo Inicial...\n";

$arrendamientoBorrador = $servicio->crearArrendamiento([
    'unidad_id' => $unidadId,
    'titular_persona_id' => $titularId,
    'fecha_inicio' => '2026-10-01',
    'fecha_fin' => '2027-03-31',
    'dia_vencimiento' => 5,
    'renta_mensual' => 2000.00,
    'deposito_garantia' => 2000.00,
], $actorId);
assertTest(
    $arrendamientoBorrador->obtenerId() > 0 && $arrendamientoBorrador->esBorrador(),
    'ARR-04',
    'Creación exitosa de contrato en estado BORRADOR'
);

$errorFecha = false;
try {
    $servicio->crearArrendamiento([
        'unidad_id' => $unidadId,
        'titular_persona_id' => $titularId,
        'fecha_inicio' => '2026-10-15',
        'fecha_fin' => '2026-10-10',
        'renta_mensual' => 1500,
    ], $actorId);
} catch (ValidacionExcepcion) {
    $errorFecha = true;
}
assertTest($errorFecha, 'ARR-05', 'Rechazo estricto de contrato con fecha_fin <= fecha_inicio');

$errorRenta = false;
try {
    $servicio->crearArrendamiento([
        'unidad_id' => $unidadId,
        'titular_persona_id' => $titularId,
        'fecha_inicio' => '2026-10-01',
        'fecha_fin' => '2027-03-31',
        'renta_mensual' => 0,
    ], $actorId);
} catch (ValidacionExcepcion) {
    $errorRenta = true;
}
assertTest($errorRenta, 'ARR-06', 'Rechazo estricto de contrato con renta_mensual <= 0');

$errorDia = false;
try {
    $servicio->crearArrendamiento([
        'unidad_id' => $unidadId,
        'titular_persona_id' => $titularId,
        'fecha_inicio' => '2026-10-01',
        'fecha_fin' => '2027-03-31',
        'dia_vencimiento' => 32,
        'renta_mensual' => 1500,
    ], $actorId);
} catch (ValidacionExcepcion) {
    $errorDia = true;
}
assertTest($errorDia, 'ARR-07', 'Rechazo de contrato con dia_vencimiento fuera de rango (1..31)');

// Contrato prorrateado (inicio día 16 de octubre, 31 días total en octubre => 16 días restantes)
$arrProrrateado = $servicio->crearArrendamiento([
    'unidad_id' => $unidadId,
    'titular_persona_id' => $titularId,
    'fecha_inicio' => '2026-10-16',
    'fecha_fin' => '2027-04-15',
    'renta_mensual' => 3100.00,
    'deposito_garantia' => 3100.00,
], $actorId);
// 3100 / 31 = 100 por día * 16 días = 1600.00
assertTest(
    $arrProrrateado->esPrimerMesProrrateado() && bccomp($arrProrrateado->obtenerMontoPrimerPeriodo(), '1600.00', 2) === 0,
    'ARR-08',
    "Cálculo exacto de prorrateo mensual con BCMath (esperado 1600.00, obtenido {$arrProrrateado->obtenerMontoPrimerPeriodo()})"
);

assertTest(
    !$arrendamientoBorrador->esPrimerMesProrrateado() && bccomp($arrendamientoBorrador->obtenerMontoPrimerPeriodo(), '2000.00', 2) === 0,
    'ARR-09',
    'Ausencia de prorrateo e importe completo cuando inicia el 1er día de mes'
);

$arrCongelado = $servicio->crearArrendamiento([
    'unidad_id' => $unidadId,
    'titular_persona_id' => $titularId,
    'fecha_inicio' => '2026-10-16',
    'fecha_fin' => '2027-04-15',
    'renta_mensual' => 3100.00,
    'monto_primer_periodo' => 1750.50,
], $actorId);
assertTest(
    bccomp($arrCongelado->obtenerMontoPrimerPeriodo(), '1750.50', 2) === 0,
    'ARR-10',
    'Congelamiento inmutable de monto_primer_periodo pactado explícitamente'
);

// -----------------------------------------------------------------------------
// BLOQUE 3: Sujetos Contractuales y Unicidad de Titular (ARR-11 a ARR-14)
// -----------------------------------------------------------------------------
echo "\n[3/7] Sujetos Contractuales y Unicidad de Titular...\n";

$servicio->agregarPersona((int) $arrendamientoBorrador->obtenerId(), $cotitularId, 'COTITULAR', 'Cotitular contractual', $actorId);
$persRepo = new \CamargoPMS\Repositorios\ArrendamientoPersonaRepositorio($pdo);
$sujetos = $persRepo->listarPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    count($sujetos) === 2,
    'ARR-11',
    'Incorporación exitosa de cotitular al contrato'
);

$errorDobleTitular = false;
try {
    // Intentar violar la unicidad en BD directamente
    $stmt = $pdo->prepare("INSERT INTO arrendamiento_personas (arrendamiento_id, persona_id, tipo_relacion) VALUES (?, ?, 'TITULAR')");
    $stmt->execute([$arrendamientoBorrador->obtenerId(), $cotitularId]);
} catch (\PDOException) {
    $errorDobleTitular = true;
}
assertTest(
    $errorDobleTitular,
    'ARR-12',
    'Blindaje en BD de unicidad de titular mediante columna virtual uq_arrp_titular_unico'
);

$errorRemoverTitular = false;
try {
    $servicio->quitarPersona((int) $arrendamientoBorrador->obtenerId(), $titularId, $actorId);
} catch (ValidacionExcepcion) {
    $errorRemoverTitular = true;
}
assertTest($errorRemoverTitular, 'ARR-13', 'Prohibición estricta de remover al titular principal del contrato');

$servicio->quitarPersona((int) $arrendamientoBorrador->obtenerId(), $cotitularId, $actorId);
$sujetosPost = $persRepo->listarPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    count($sujetosPost) === 1 && $sujetosPost[0]->esTitular(),
    'ARR-14',
    'Desvinculación exitosa de cotitular manteniendo intacto al titular'
);

// -----------------------------------------------------------------------------
// BLOQUE 4: Activación y Materialización de Inventario (ARR-15 a ARR-19)
// -----------------------------------------------------------------------------
echo "\n[4/7] Activación y Materialización de Inventario...\n";

// Asegurar que no haya bloqueos en fechas de prueba
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidadId} AND fecha >= '2026-10-01'");

$resultadoAct = $servicio->activarArrendamiento((int) $arrendamientoBorrador->obtenerId(), $actorId);
$arrAct = $arrRepo->obtenerPorId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    $arrAct->esVigente() && $resultadoAct['estado'] === 'VIGENTE',
    'ARR-15',
    'Activación de contrato con transición BORRADOR -> VIGENTE'
);

// Octubre (31) + Noviembre (30) + Diciembre (31) + Enero (31) + Febrero (28) + Marzo (31) = 182 días
// Intervalo semiabierto [2026-10-01, 2027-03-31): Noches materializadas = 181 noches.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_tipo = 'ARRENDAMIENTO' AND origen_id = ?");
$stmt->execute([$arrendamientoBorrador->obtenerId()]);
$nochesEnBd = (int) $stmt->fetchColumn();
assertTest(
    $nochesEnBd === 181,
    'ARR-16',
    "Materialización sparse total de noches en inventario_diario_unidades ({$nochesEnBd} noches)"
);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE unidad_id = ? AND fecha = '2027-03-31'");
$stmt->execute([$unidadId]);
$nocheFinOcupada = (int) $stmt->fetchColumn();
assertTest(
    $nocheFinOcupada === 0,
    'ARR-17',
    'Semántica semiabierta respetada: fecha_fin (2027-03-31) queda libre y disponible'
);

$stmt = $pdo->prepare("SELECT tipo_bloqueo FROM inventario_diario_unidades WHERE origen_id = ? LIMIT 1");
$stmt->execute([$arrendamientoBorrador->obtenerId()]);
$tipoBloqueo = (string) $stmt->fetchColumn();
assertTest(
    $tipoBloqueo === 'ARRENDAMIENTO',
    'ARR-18',
    "Noches etiquetadas con tipo_bloqueo = 'ARRENDAMIENTO' en el inventario"
);

// Intento de colisión con otro contrato en fechas superpuestas
$arrColision = $servicio->crearArrendamiento([
    'unidad_id' => $unidadId,
    'titular_persona_id' => $titularId,
    'fecha_inicio' => '2026-11-01',
    'fecha_fin' => '2026-12-31',
    'renta_mensual' => 1500,
], $actorId);

$colisionDetectada = false;
try {
    $servicio->activarArrendamiento((int) $arrColision->obtenerId(), $actorId);
} catch (ConflictoDisponibilidadExcepcion) {
    $colisionDetectada = true;
}
assertTest(
    $colisionDetectada,
    'ARR-19',
    'Rechazo estricto con ConflictoDisponibilidadExcepcion ante colisión de noches materializadas'
);

// -----------------------------------------------------------------------------
// BLOQUE 5: Folios, Cargos y Cuotas Idempotentes (ARR-20 a ARR-25)
// -----------------------------------------------------------------------------
echo "\n[5/7] Folios, Cargos y Cuotas Idempotentes...\n";

$ctafRepo = new \CamargoPMS\Repositorios\CuentaFolioRepositorio($pdo);
$folio = $ctafRepo->obtenerPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    $folio !== null && $folio->obtenerReservaId() === null && $folio->obtenerArrendamientoId() === $arrendamientoBorrador->obtenerId(),
    'ARR-20',
    'Creación de CuentaFolio financiera con reserva_id NULL y arrendamiento_id NOT NULL'
);

$errorXor = false;
try {
    $stmt = $pdo->prepare("INSERT INTO cuentas_folios (codigo, reserva_id, arrendamiento_id, persona_titular_id, moneda_codigo, creado_por_actor_id) VALUES ('FOL-ERR-XOR', 1, 1, 1, 'PEN', 1)");
    $stmt->execute();
} catch (\PDOException) {
    $errorXor = true;
}
assertTest($errorXor, 'ARR-21', 'Cumplimiento del CHECK XOR chk_ctaf_sujeto_exclusivo en cuentas_folios');

$crgRepo = new \CamargoPMS\Repositorios\CargoCuentaRepositorio($pdo);
$stmt = $pdo->prepare("SELECT * FROM cargos_cuenta WHERE cuenta_folio_id = ? AND origen_tipo = 'RENTA_ARRENDAMIENTO'");
$stmt->execute([$folio->obtenerId()]);
$cargoRenta = $stmt->fetch(PDO::FETCH_ASSOC);
assertTest(
    $cargoRenta && $cargoRenta['estado'] === 'DEVENGADO' && bccomp((string) $cargoRenta['total'], '2000.00', 2) === 0,
    'ARR-22',
    'Generación de cargo devengado de primer período en el folio financiero'
);

$cuotaRepo = new \CamargoPMS\Repositorios\ArrendamientoCuotaRepositorio($pdo);
$cuotas = $cuotaRepo->listarPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    count($cuotas) === 1 && $cuotas[0]->obtenerPeriodoCodigo() === '2026-10',
    'ARR-23',
    'Generación de primera cuota vinculada 1:1 al cargo en arrendamiento_cuotas'
);

$stmt = $pdo->prepare("SELECT * FROM cargos_cuenta WHERE cuenta_folio_id = ? AND origen_tipo = 'DEPOSITO_GARANTIA'");
$stmt->execute([$folio->obtenerId()]);
$cargoGarantia = $stmt->fetch(PDO::FETCH_ASSOC);
assertTest(
    $cargoGarantia && bccomp((string) $cargoGarantia['total'], '2000.00', 2) === 0,
    'ARR-24',
    'Generación de cargo por depósito en garantía devengado en el folio'
);

$garRepo = new \CamargoPMS\Repositorios\ArrendamientoGarantiaRepositorio($pdo);
$garantia = $garRepo->obtenerPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    $garantia !== null && bccomp($garantia->obtenerMontoPactado(), '2000.00', 2) === 0 && $garantia->esPendiente(),
    'ARR-25',
    'Creación de entidad arrendamiento_garantias en estado PENDIENTE'
);

// -----------------------------------------------------------------------------
// BLOQUE 6: Custodia Segregada de Garantía (ARR-26 a ARR-32)
// -----------------------------------------------------------------------------
echo "\n[6/7] Custodia Segregada de Garantía con Saldo Reconstructible...\n";

$servicio->registrarRecepcionGarantia((int) $arrendamientoBorrador->obtenerId(), '2000.00', $actorId);
$garActualizada = $garRepo->obtenerPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    bccomp($garActualizada->obtenerMontoRecibido(), '2000.00', 2) === 0 && bccomp($garActualizada->obtenerMontoRetenidoActual(), '2000.00', 2) === 0,
    'ARR-26',
    'Registro de recepción de garantía: saldos recibido y retenido actualizados'
);

assertTest(
    $garActualizada->esCustodiada(),
    'ARR-27',
    'Transición de custodia de garantía a estado CUSTODIADA'
);

$servicio->compensarGarantia((int) $arrendamientoBorrador->obtenerId(), '350.00', 'DANOS', 'Reparación de cerradura y pared', $actorId);
$garDanos = $garRepo->obtenerPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    bccomp($garDanos->obtenerMontoCompensadoDanos(), '350.00', 2) === 0 && bccomp($garDanos->obtenerMontoRetenidoActual(), '1650.00', 2) === 0,
    'ARR-28',
    'Compensación por daños físicos reduce saldo retenido e incrementa compensado_danos'
);

$servicio->compensarGarantia((int) $arrendamientoBorrador->obtenerId(), '650.00', 'RENTA', 'Imputación por días adeudados', $actorId);
$garRenta = $garRepo->obtenerPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    bccomp($garRenta->obtenerMontoCompensadoRenta(), '650.00', 2) === 0 && bccomp($garRenta->obtenerMontoRetenidoActual(), '1000.00', 2) === 0,
    'ARR-29',
    'Compensación por renta insoluta reduce saldo retenido e incrementa compensado_renta'
);

$servicio->devolverGarantia((int) $arrendamientoBorrador->obtenerId(), '1000.00', 'Devolución de remanente final', $actorId);
$garDev = $garRepo->obtenerPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    bccomp($garDev->obtenerMontoDevuelto(), '1000.00', 2) === 0 && bccomp($garDev->obtenerMontoRetenidoActual(), '0.00', 2) === 0 && $garDev->esLiquidada(),
    'ARR-30',
    'Devolución de fondos liquida la custodia y actualiza estado a LIQUIDADA'
);

// Invariante: monto_recibido = retenido + daños + renta + devuelto
$sumaReconstructible = bcadd(
    $garDev->obtenerMontoRetenidoActual(),
    bcadd(
        $garDev->obtenerMontoCompensadoDanos(),
        bcadd($garDev->obtenerMontoCompensadoRenta(), $garDev->obtenerMontoDevuelto(), 2),
        2
    ),
    2
);
assertTest(
    bccomp($garDev->obtenerMontoRecibido(), $sumaReconstructible, 2) === 0,
    'ARR-31',
    "Invariante contable reconstructible verificado (Recibido {$garDev->obtenerMontoRecibido()} == Suma {$sumaReconstructible})"
);

$errorExcesoGarantia = false;
try {
    $servicio->devolverGarantia((int) $arrendamientoBorrador->obtenerId(), '50.00', 'Exceso', $actorId);
} catch (GarantiaInvalidaExcepcion) {
    $errorExcesoGarantia = true;
}
assertTest($errorExcesoGarantia, 'ARR-32', 'Rechazo estricto de devolución o compensación superior al saldo retenido');

// -----------------------------------------------------------------------------
// BLOQUE 7: Idempotencia, Prórrogas, Rescisión e Historial (ARR-33 a ARR-40)
// -----------------------------------------------------------------------------
echo "\n[7/7] Idempotencia, Prórrogas, Rescisión e Historial...\n";

// Emisión cuota mes 2 (noviembre 2026)
$cuotaNov = $servicio->generarCuotaMensual((int) $arrendamientoBorrador->obtenerId(), 2026, 11, $actorId);
assertTest(
    $cuotaNov->obtenerPeriodoCodigo() === '2026-11' && bccomp($cuotaNov->obtenerMontoRenta(), '2000.00', 2) === 0,
    'ARR-33',
    'Emisión exitosa de cuota mensual recurrente de renta'
);

// Emisión cuota mes corto (febrero 2027 con día de vencimiento 31 ajustado a 28)
$arrDia31 = $servicio->crearArrendamiento([
    'unidad_id' => $unidad2Id,
    'titular_persona_id' => $titularId,
    'fecha_inicio' => '2027-04-01',
    'fecha_fin' => '2027-08-31',
    'dia_vencimiento' => 31,
    'renta_mensual' => 1800.00,
], $actorId);
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidad2Id} AND fecha >= '2027-04-01'");
$servicio->activarArrendamiento((int) $arrDia31->obtenerId(), $actorId);
$cuotaAbr = $servicio->generarCuotaMensual((int) $arrDia31->obtenerId(), 2027, 4, $actorId);
assertTest(
    $cuotaAbr->obtenerFechaVencimiento() === '2027-04-30',
    'ARR-34',
    "Ajuste automático de vencimiento para meses cortos (día 31 ajustado al 2027-04-30)"
);

// Idempotencia: segunda llamada con los mismos parámetros
$cuotaNov2 = $servicio->generarCuotaMensual((int) $arrendamientoBorrador->obtenerId(), 2026, 11, $actorId);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM arrendamiento_cuotas WHERE arrendamiento_id = ? AND periodo_codigo = '2026-11'");
$stmt->execute([$arrendamientoBorrador->obtenerId()]);
$conteoNov = (int) $stmt->fetchColumn();
assertTest(
    $cuotaNov->obtenerId() === $cuotaNov2->obtenerId() && $conteoNov === 1,
    'ARR-35',
    'Idempotencia estricta en emisión de cuotas: segunda invocación no duplica registros'
);

// Prórroga de contrato
$servicio->prorrogarArrendamiento((int) $arrendamientoBorrador->obtenerId(), '2027-04-30', $actorId);
$arrProrrogado = $arrRepo->obtenerPorId((int) $arrendamientoBorrador->obtenerId());
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ?");
$stmt->execute([$arrendamientoBorrador->obtenerId()]);
$nochesPostProrroga = (int) $stmt->fetchColumn();
assertTest(
    $arrProrrogado->obtenerFechaFin() === '2027-04-30' && $nochesPostProrroga === (181 + 30),
    'ARR-36',
    "Prórroga contractual materializa automáticamente las 30 noches adicionales en inventario"
);

// Rescisión anticipada al 2026-12-15
$servicio->rescindirArrendamiento((int) $arrendamientoBorrador->obtenerId(), '2026-12-15', 'Incumplimiento grave de convivencia', $actorId);
$arrRescindido = $arrRepo->obtenerPorId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    $arrRescindido->esRescindido() && $arrRescindido->obtenerFechaFin() === '2026-12-15',
    'ARR-37',
    'Rescisión anticipada: contrato pasa a estado RESCINDIDO y recorta fecha_fin'
);

// Noches pasadas (< 2026-12-15) preservadas vs noches futuras (>= 2026-12-15) eliminadas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ? AND fecha >= '2026-12-15'");
$stmt->execute([$arrendamientoBorrador->obtenerId()]);
$nochesFuturasLiberadas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_diario_unidades WHERE origen_id = ? AND fecha < '2026-12-15'");
$stmt->execute([$arrendamientoBorrador->obtenerId()]);
$nochesPasadasPreservadas = (int) $stmt->fetchColumn();
assertTest(
    $nochesFuturasLiberadas === 0 && $nochesPasadasPreservadas > 0,
    'ARR-38',
    "Rescisión con noches futuras liberadas ({$nochesFuturasLiberadas}) y pasadas preservadas ({$nochesPasadasPreservadas})"
);

// Finalización regular
$arrFin = $servicio->crearArrendamiento([
    'unidad_id' => $unidad3Id,
    'titular_persona_id' => $titularId,
    'fecha_inicio' => '2027-09-01',
    'fecha_fin' => '2027-09-30',
    'renta_mensual' => 1500,
], $actorId);
$pdo->exec("DELETE FROM inventario_diario_unidades WHERE unidad_id = {$unidad3Id} AND fecha >= '2027-09-01'");
$servicio->activarArrendamiento((int) $arrFin->obtenerId(), $actorId);
$servicio->finalizarArrendamiento((int) $arrFin->obtenerId(), $actorId);
$arrFinCheck = $arrRepo->obtenerPorId((int) $arrFin->obtenerId());
assertTest(
    $arrFinCheck->esFinalizado(),
    'ARR-39',
    'Finalización regular de contrato: transición VIGENTE -> FINALIZADO'
);

// Historial y auditoría inmutable
$histRepo = new \CamargoPMS\Repositorios\ArrendamientoHistorialEstadoRepositorio($pdo);
$historial = $histRepo->listarPorArrendamientoId((int) $arrendamientoBorrador->obtenerId());
assertTest(
    count($historial) >= 4,
    'ARR-40',
    "Trazabilidad inmutable verificada en historial_estados (" . count($historial) . " eventos registrados)"
);

echo "\n=====================================================================\n";
echo "RESULTADOS MATRIZ ARRENDAMIENTOS-1: {$pruebasExitosas}/{$totalPruebas} PASS\n";
echo "=====================================================================\n";

if ($totalPruebas === $pruebasExitosas) {
    echo "DICTAMEN: PASS (100% Éxito en Dominio de Arrendamientos)\n";
    exit(0);
} else {
    echo "DICTAMEN: FAIL ({$pruebasExitosas}/{$totalPruebas})\n";
    exit(1);
}
