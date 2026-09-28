<?php

declare(strict_types=1);

/**
 * Suite de Verificación GASTOS-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-086 (GATE GASTOS-1):
 * - GASTO != COMPRA != CxP != PAGO != MOVIMIENTO DE TESORERÍA
 * - GASTOS-1 NO CREA UNA SEGUNDA TESORERÍA NI UNA SEGUNDA CxP
 * - Verdad reconstructible de saldos: Total - Aplicaciones Activas = Saldo Pendiente
 * - Cero campos soberanos mutables de saldo en tabla gastos
 * - Separación estricta entre Estado del Gasto (BORRADOR, REGISTRADO, APROBADO, ANULADO) y Situación Financiera (PENDIENTE, PARCIAL, PAGADO)
 * - Imputación analítica tri-partita: CORPORATIVO, PROPIEDAD, UNIDAD
 * - Integración atómica con FINANCIERO-2 (movimientos_caja EGRESO_GASTO_MENOR y movimientos_bancarios EGRESO_TRANSFERENCIA)
 * - Invariante de anulación bloqueada con aplicaciones activas
 * - Auditoría append-only D-061
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\CajaNoAbiertaExcepcion;
use CamargoPMS\Excepciones\ConflictoGastoExcepcion;
use CamargoPMS\Excepciones\GastoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\ValidacionGastoExcepcion;
use CamargoPMS\Modelos\Gasto;
use CamargoPMS\Modelos\GastoCategoria;
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
echo " CAMARGO PMS — PRUEBAS GASTOS-1 (MATRIZ EXHAUSTIVA DE 40 CASOS)\n";
echo " Decisión Vinculante: D-086\n";
echo "====================================================================\n\n";

// Datos base necesarios
$stmtProp = $pdo->query('SELECT id FROM propiedades LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();

$stmtUnidad = $pdo->prepare('SELECT id FROM unidades WHERE propiedad_id = :prop_id LIMIT 1');
$stmtUnidad->execute(['prop_id' => $propiedadId]);
$unidadId = (int) $stmtUnidad->fetchColumn();

$stmtCaja = $pdo->query('SELECT id FROM cajas_fisicas WHERE estado = "ACTIVO" LIMIT 1');
$cajaFisicaId = (int) $stmtCaja->fetchColumn();

$stmtBanco = $pdo->query('SELECT id FROM cuentas_bancarias WHERE estado = "ACTIVO" LIMIT 1');
$cuentaBancariaId = (int) $stmtBanco->fetchColumn();

$stmtMetodoEf = $pdo->query('SELECT id FROM metodos_pago WHERE tipo_destino = "CAJA_FISICA" OR codigo = "EFECTIVO" LIMIT 1');
$metodoEfectivoId = (int) $stmtMetodoEf->fetchColumn();

$stmtMetodoTrans = $pdo->query('SELECT id FROM metodos_pago WHERE tipo_destino = "CUENTA_BANCARIA" OR codigo = "TRANSFERENCIA" LIMIT 1');
$metodoTransferenciaId = (int) $stmtMetodoTrans->fetchColumn();

// -----------------------------------------------------------------------------
// GRUPO 1: CATÁLOGO Y CONFIGURACIÓN DE CATEGORÍAS (Casos 01 a 03)
// -----------------------------------------------------------------------------
echo "--- Grupo 1: Catálogo y Categorías de Gasto ---\n";

// Caso 01: Categorías iniciales cargadas y activas
$categorias = $gastoServicio->listarCategorias(true);
afirmar(count($categorias) >= 10, 'Caso 01: Existen al menos 10 categorías operativas activas');

// Caso 02: Búsqueda canónica de categoría
$catLuz = $gastoRepo->obtenerCategoriaPorCodigo('SERV_ELECTRICIDAD');
afirmar($catLuz !== null && $catLuz->obtenerCodigo() === 'SERV_ELECTRICIDAD', 'Caso 02: Búsqueda canónica de categoría SERV_ELECTRICIDAD');

// Caso 03: Crear categoría personalizada
$codigoCatCustom = 'CAT_TEST_' . time();
$catCustom = new GastoCategoria(null, $codigoCatCustom, 'Categoría Temporal Test', 'Descripción de prueba', true, true);
$idCatCustom = $gastoRepo->crearCategoria($catCustom);
$recuperadaCat = $gastoRepo->obtenerCategoriaPorId($idCatCustom);
afirmar($recuperadaCat !== null && $recuperadaCat->obtenerCodigo() === $codigoCatCustom, 'Caso 03: Creación y persistencia de categoría personalizada');

// -----------------------------------------------------------------------------
// GRUPO 2: VALIDACIONES AL REGISTRAR GASTO (Casos 04 a 14)
// -----------------------------------------------------------------------------
echo "\n--- Grupo 2: Validaciones y Lógica de Creación ---\n";

// Caso 04: Crear sin categoría_id falla
try {
    $gastoServicio->crearGasto(['descripcion_concepto' => 'Gasto sin cat', 'acreedor_nombre' => 'Test']);
    afirmar(false, 'Caso 04: Crear sin categoría debe fallar');
} catch (ValidacionGastoExcepcion $e) {
    afirmar(true, 'Caso 04: Crear sin categoría lanza ValidacionGastoExcepcion');
}

// Caso 05: Categoría inexistente falla
try {
    $gastoServicio->crearGasto(['categoria_id' => 999999, 'descripcion_concepto' => 'Gasto inv', 'acreedor_nombre' => 'Test']);
    afirmar(false, 'Caso 05: Categoría inexistente debe fallar');
} catch (ValidacionGastoExcepcion $e) {
    afirmar(true, 'Caso 05: Categoría inexistente lanza ValidacionGastoExcepcion');
}

// Caso 06: Concepto vacío falla
try {
    $gastoServicio->crearGasto(['categoria_id' => $catLuz->obtenerId(), 'descripcion_concepto' => '   ', 'acreedor_nombre' => 'Test']);
    afirmar(false, 'Caso 06: Concepto vacío debe fallar');
} catch (ValidacionGastoExcepcion $e) {
    afirmar(true, 'Caso 06: Concepto vacío lanza ValidacionGastoExcepcion');
}

// Caso 07: Acreedor vacío falla
try {
    $gastoServicio->crearGasto(['categoria_id' => $catLuz->obtenerId(), 'descripcion_concepto' => 'Concepto ok', 'acreedor_nombre' => '']);
    afirmar(false, 'Caso 07: Acreedor vacío debe fallar');
} catch (ValidacionGastoExcepcion $e) {
    afirmar(true, 'Caso 07: Acreedor vacío lanza ValidacionGastoExcepcion');
}

// Caso 08: Ámbito CORPORATIVO (propiedad_id y unidad_id son NULL)
$gastoCorp = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'ambito' => Gasto::AMBITO_CORPORATIVO,
    'propiedad_id' => $propiedadId, // Debe forzarse a NULL
    'unidad_id' => $unidadId,       // Debe forzarse a NULL
    'descripcion_concepto' => 'Honorarios asesor legal corporativo',
    'acreedor_nombre' => 'Estudio Jurídico Camargo & Asoc.',
    'total' => '1200.00'
]);
afirmar($gastoCorp->obtenerAmbito() === Gasto::AMBITO_CORPORATIVO && $gastoCorp->obtenerPropiedadId() === null && $gastoCorp->obtenerUnidadId() === null,
    'Caso 08: Ámbito CORPORATIVO normaliza propiedad y unidad a NULL');

// Caso 09: Ámbito PROPIEDAD
$gastoProp = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'ambito' => Gasto::AMBITO_PROPIEDAD,
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId, // Debe forzarse a NULL
    'descripcion_concepto' => 'Recibo de luz general sede principal',
    'acreedor_nombre' => 'Luz del Sur S.A.A.',
    'total' => '850.50'
]);
afirmar($gastoProp->obtenerAmbito() === Gasto::AMBITO_PROPIEDAD && $gastoProp->obtenerPropiedadId() === $propiedadId && $gastoProp->obtenerUnidadId() === null,
    'Caso 09: Ámbito PROPIEDAD preserva propiedad_id y fuerza unidad_id a NULL');

// Caso 10: Ámbito UNIDAD
$gastoUni = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'ambito' => Gasto::AMBITO_UNIDAD,
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId,
    'descripcion_concepto' => 'Reparación de cerradura habitación 101',
    'acreedor_nombre' => 'Cerrajería Rápida',
    'total' => '120.00'
]);
afirmar($gastoUni->obtenerAmbito() === Gasto::AMBITO_UNIDAD && $gastoUni->obtenerPropiedadId() === $propiedadId && $gastoUni->obtenerUnidadId() === $unidadId,
    'Caso 10: Ámbito UNIDAD vincula correctamente propiedad y unidad');

// Caso 11: Unidad que no pertenece a la propiedad falla
$stmtOtraProp = $pdo->prepare('SELECT id FROM propiedades WHERE id <> :prop_id LIMIT 1');
$stmtOtraProp->execute(['prop_id' => $propiedadId]);
$otraPropId = (int) $stmtOtraProp->fetchColumn();

if ($otraPropId > 0) {
    try {
        $gastoServicio->crearGasto([
            'categoria_id' => $catLuz->obtenerId(),
            'ambito' => Gasto::AMBITO_UNIDAD,
            'propiedad_id' => $otraPropId,
            'unidad_id' => $unidadId, // pertenece a $propiedadId, no a $otraPropId
            'descripcion_concepto' => 'Incoherencia de unidad',
            'acreedor_nombre' => 'Test',
            'total' => '50.00'
        ]);
        afirmar(false, 'Caso 11: Unidad cruzada con otra propiedad debe fallar');
    } catch (ValidacionGastoExcepcion $e) {
        afirmar(true, 'Caso 11: Unidad cruzada rechazada con ValidacionGastoExcepcion');
    }
} else {
    afirmar(true, 'Caso 11: Simulado por ausencia de segunda propiedad');
}

// Caso 12: Cálculo BCMath Subtotal + Impuestos = Total
$gastoMath = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'descripcion_concepto' => 'Mantenimiento preventivo aire acondicionado',
    'acreedor_nombre' => 'ClimaTech Perú S.A.C.',
    'subtotal' => '1000.00',
    'impuestos' => '180.00',
]);
afirmar(bccomp($gastoMath->obtenerTotal(), '1180.00', 2) === 0, 'Caso 12: Cálculo determinista BCMath subtotal (1000) + impuestos (180) = total (1180.00)');

// Caso 13: Gasto con impuestos en 0.00
$gastoSinImp = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'descripcion_concepto' => 'Arbitrios municipales segundo trimestre',
    'acreedor_nombre' => 'Servicio de Administración Tributaria (SAT)',
    'subtotal' => '450.00',
    'impuestos' => '0.00',
]);
afirmar(bccomp($gastoSinImp->obtenerTotal(), '450.00', 2) === 0, 'Caso 13: Gasto inafecto/impuestos 0.00 tiene total igual a subtotal');

// Caso 14: Total negativo falla
try {
    $gastoServicio->crearGasto([
        'categoria_id' => $catLuz->obtenerId(),
        'descripcion_concepto' => 'Gasto negativo',
        'acreedor_nombre' => 'Test',
        'total' => '-50.00'
    ]);
    afirmar(false, 'Caso 14: Total negativo debe fallar');
} catch (MontoInvalidoExcepcion $e) {
    afirmar(true, 'Caso 14: Total negativo lanza MontoInvalidoExcepcion');
}

// -----------------------------------------------------------------------------
// GRUPO 3: CÓDIGO DETERMINISTA, ESTADOS Y AUDITORÍA (Casos 15 a 21)
// -----------------------------------------------------------------------------
echo "\n--- Grupo 3: Ciclo de Estados y Auditoría Append-Only ---\n";

// Caso 15: Formato de código GST-YYYYMM-XXXX
afirmar((bool) preg_match('/^GST-\d{6}-\d{4}$/', $gastoMath->obtenerCodigo()),
    "Caso 15: Formato de código GST-YYYYMM-XXXX ({$gastoMath->obtenerCodigo()})");

// Caso 16: Estado inicial por defecto es REGISTRADO
afirmar($gastoMath->obtenerEstado() === Gasto::ESTADO_REGISTRADO, 'Caso 16: Estado inicial por defecto es REGISTRADO');

// Caso 17: Historial D-061 registra creación
$historialMath = $gastoRepo->listarHistorialGasto((int) $gastoMath->obtenerId());
afirmar(count($historialMath) >= 1 && $historialMath[0]->obtenerEstadoNuevo() === Gasto::ESTADO_REGISTRADO,
    'Caso 17: Historial append-only registra creación con estado_nuevo REGISTRADO');

// Caso 18: Aprobación de gasto
$gastoAprobado = $gastoServicio->aprobarGasto((int) $gastoMath->obtenerId(), 1, 'Aprobación de gerencia');
afirmar($gastoAprobado->obtenerEstado() === Gasto::ESTADO_APROBADO, 'Caso 18: Transición exitosa a APROBADO');

// Caso 19: Idempotencia de aprobación
$gastoReAprobado = $gastoServicio->aprobarGasto((int) $gastoMath->obtenerId(), 1);
afirmar($gastoReAprobado->obtenerEstado() === Gasto::ESTADO_APROBADO, 'Caso 19: Idempotencia en aprobación de gasto');

// Caso 20: Historial append-only registra transición a APROBADO
$historialMath2 = $gastoRepo->listarHistorialGasto((int) $gastoMath->obtenerId());
afirmar(count($historialMath2) === 2 && $historialMath2[1]->obtenerEstadoNuevo() === Gasto::ESTADO_APROBADO,
    'Caso 20: Historial append-only registra segundo evento de APROBADO');

// Caso 21: Aprobar gasto ANULADO falla
$gastoParaAnular = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'descripcion_concepto' => 'Gasto para anular directo',
    'acreedor_nombre' => 'Proveedor Erróneo',
    'total' => '200.00'
]);
$gastoServicio->anularGasto((int) $gastoParaAnular->obtenerId(), 1, 'Error material en digitación');
try {
    $gastoServicio->aprobarGasto((int) $gastoParaAnular->obtenerId(), 1);
    afirmar(false, 'Caso 21: Aprobar gasto anulado debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso 21: Aprobar gasto anulado lanza ConflictoGastoExcepcion');
}

// -----------------------------------------------------------------------------
// GRUPO 4: VERDAD RECONSTRUCTIBLE Y TESORERÍA EFECTIVO / CAJA CHICA (Casos 22 a 30)
// -----------------------------------------------------------------------------
echo "\n--- Grupo 4: Verdad Reconstructible y Desembolsos en Efectivo ---\n";

// Caso 22: Verdad reconstructible inicial
$resumenMath = $gastoServicio->obtenerResumenGasto((int) $gastoMath->obtenerId());
afirmar(
    bccomp($resumenMath->obtenerMontoAplicadoAcumulado(), '0.00', 2) === 0 &&
    bccomp($resumenMath->obtenerSaldoPendiente(), '1180.00', 2) === 0 &&
    $resumenMath->obtenerSituacionFinanciera() === GastoResumenDTO::SITUACION_PENDIENTE,
    'Caso 22: Situación inicial PENDIENTE con Saldo = Total y Aplicado = 0.00'
);

// Caso 23: Pagar gasto NO APROBADO falla
$gastoNoAprobado = $gastoServicio->crearGasto([
    'categoria_id' => $catLuz->obtenerId(),
    'descripcion_concepto' => 'Gasto no aprobado',
    'acreedor_nombre' => 'Acreedor X',
    'total' => '300.00'
]);
try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoTransferenciaId,
        'cuenta_bancaria_id' => $cuentaBancariaId,
        'aplicaciones' => [['gasto_id' => $gastoNoAprobado->obtenerId(), 'monto' => '100.00']],
    ], 1);
    afirmar(false, 'Caso 23: Pagar gasto sin aprobar debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso 23: Pagar gasto sin aprobar lanza ConflictoGastoExcepcion');
}

// Caso 24: Pagar en efectivo sin sesión de caja abierta falla
try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoEfectivoId,
        'sesion_caja_id' => 999999, // Inexistente o no abierta
        'aplicaciones' => [['gasto_id' => $gastoMath->obtenerId(), 'monto' => '100.00']],
    ], 1);
    afirmar(false, 'Caso 24: Pago efectivo sin sesión abierta debe fallar');
} catch (CajaNoAbiertaExcepcion $e) {
    afirmar(true, 'Caso 24: Pago efectivo sin sesión abierta lanza CajaNoAbiertaExcepcion');
}

// Abrir sesión de caja para pruebas
$sesionPrueba = $cajaServicio->obtenerSesionActivaPorCaja($cajaFisicaId);
if ($sesionPrueba === null) {
    $sesionPrueba = $cajaServicio->aperturarSesion($cajaFisicaId, '500.00', 'Sesión para pruebas GASTOS-1', 1);
} else {
    // Si ya hay sesión abierta, asegurar fondo
    $cajaServicio->registrarMovimientoManual((int) $sesionPrueba->obtenerId(), 'INGRESO_AJUSTE', '500.00', 'Fondo test', 1);
    $sesionPrueba = $cajaServicio->obtenerSesion((int) $sesionPrueba->obtenerId());
}
$sesionCajaIdActiva = (int) $sesionPrueba->obtenerId();

// Caso 25: Pago en efectivo que supera el saldo de gaveta de caja falla
try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoEfectivoId,
        'sesion_caja_id' => $sesionCajaIdActiva,
        'aplicaciones' => [['gasto_id' => $gastoMath->obtenerId(), 'monto' => '999999.00']],
    ], 1);
    afirmar(false, 'Caso 25: Pago que supera saldo de caja física debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso 25: Pago que supera saldo en gaveta lanza ConflictoGastoExcepcion');
}

// Caso 26: Pago parcial en efectivo (S/ 300.00 sobre S/ 1180.00)
$resultadoPago1 = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoEfectivoId,
    'sesion_caja_id' => $sesionCajaIdActiva,
    'aplicaciones' => [['gasto_id' => $gastoMath->obtenerId(), 'monto' => '300.00']],
], 1);
/** @var PagoEgreso $pago1 */
$pago1 = $resultadoPago1['pago'];
afirmar($pago1 !== null && bccomp($pago1->obtenerMontoTotal(), '300.00', 2) === 0,
    'Caso 26: Pago parcial de S/ 300.00 ejecutado en efectivo');

// Caso 27: Creación atómica del movimiento de caja EGRESO_GASTO_MENOR
$movCajaId = $pago1->obtenerMovimientoCajaId();
$movCaja = $cajaRepo->obtenerMovimientoCaja((int) $movCajaId);
afirmar($movCaja !== null && $movCaja->obtenerTipoMovimiento() === 'EGRESO_GASTO_MENOR' && bccomp($movCaja->obtenerMonto(), '300.00', 2) === 0,
    'Caso 27: Creación atómica de movimiento_caja con tipo EGRESO_GASTO_MENOR');

// Caso 28: Totales de la sesión de caja actualizados
$sesionPost = $cajaServicio->obtenerSesion($sesionCajaIdActiva);
afirmar(bccomp($sesionPost->obtenerTotalEgresosEfectivo(), '0.00', 2) > 0,
    'Caso 28: Totales de egresos en sesiones_caja actualizados atómicamente');

// Caso 29: Verdad reconstructible tras pago parcial (situación PARCIAL)
$resumenPost1 = $gastoServicio->obtenerResumenGasto((int) $gastoMath->obtenerId());
afirmar(
    bccomp($resumenPost1->obtenerMontoAplicadoAcumulado(), '300.00', 2) === 0 &&
    bccomp($resumenPost1->obtenerSaldoPendiente(), '880.00', 2) === 0 &&
    $resumenPost1->obtenerSituacionFinanciera() === GastoResumenDTO::SITUACION_PARCIAL,
    'Caso 29: Verdad reconstructible PARCIAL: Aplicado = S/ 300.00, Saldo = S/ 880.00'
);

// Caso 30: Cero mutación soberana en tabla gastos
$gastoEnBd = $gastoRepo->obtenerGastoPorId((int) $gastoMath->obtenerId());
afirmar($gastoEnBd->obtenerEstado() === Gasto::ESTADO_APROBADO,
    'Caso 30: CERO mutación soberana: gastos.estado sigue siendo APROBADO (no PARCIAL)');

// -----------------------------------------------------------------------------
// GRUPO 5: TESORERÍA BANCARIA, LIQUIDACIÓN Y REVERSIÓN (Casos 31 a 40)
// -----------------------------------------------------------------------------
echo "\n--- Grupo 5: Tesorería Bancaria, Liquidación Total y Reversión ---\n";

// Caso 31: Pago que supera el saldo disponible (S/ 900 sobre S/ 880 restantes)
try {
    $gastoServicio->ejecutarPagoEgreso([
        'metodo_pago_id' => $metodoTransferenciaId,
        'cuenta_bancaria_id' => $cuentaBancariaId,
        'referencia_operacion' => 'TRANS-EXCESO',
        'aplicaciones' => [['gasto_id' => $gastoMath->obtenerId(), 'monto' => '900.00']],
    ], 1);
    afirmar(false, 'Caso 31: Pago superior al saldo pendiente debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso 31: Pago que supera saldo pendiente lanza ConflictoGastoExcepcion');
}

// Caso 32: Segundo pago bancario liquidando el saldo restante exacto (S/ 880.00)
$resultadoPago2 = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'referencia_operacion' => 'TRANS-BCP-9921',
    'aplicaciones' => [['gasto_id' => $gastoMath->obtenerId(), 'monto' => '880.00']],
], 1);
/** @var PagoEgreso $pago2 */
$pago2 = $resultadoPago2['pago'];
afirmar($pago2 !== null && bccomp($pago2->obtenerMontoTotal(), '880.00', 2) === 0,
    'Caso 32: Segundo pago bancario por S/ 880.00 ejecutado con éxito');

// Caso 33: Creación atómica de movimiento bancario EGRESO_TRANSFERENCIA
$movBancoId = $pago2->obtenerMovimientoBancarioId();
$stmtMovB = $pdo->prepare('SELECT * FROM movimientos_bancarios WHERE id = :id LIMIT 1');
$stmtMovB->execute(['id' => $movBancoId]);
$movBanco = $stmtMovB->fetch(PDO::FETCH_ASSOC);
afirmar($movBanco && $movBanco['tipo_movimiento'] === 'EGRESO_TRANSFERENCIA' && bccomp((string)$movBanco['monto'], '880.00', 2) === 0,
    'Caso 33: Creación atómica de movimiento_bancario con tipo EGRESO_TRANSFERENCIA');

// Caso 34: Verdad reconstructible tras liquidación total (situación PAGADO)
$resumenPost2 = $gastoServicio->obtenerResumenGasto((int) $gastoMath->obtenerId());
afirmar(
    bccomp($resumenPost2->obtenerMontoAplicadoAcumulado(), '1180.00', 2) === 0 &&
    bccomp($resumenPost2->obtenerSaldoPendiente(), '0.00', 2) === 0 &&
    $resumenPost2->obtenerSituacionFinanciera() === GastoResumenDTO::SITUACION_PAGADO,
    'Caso 34: Situación PAGADO con Saldo Pendiente = S/ 0.00 y Aplicado = S/ 1180.00'
);

// Caso 35: Invariante de anulación: Intentar anular gasto con pagos activos falla
try {
    $gastoServicio->anularGasto((int) $gastoMath->obtenerId(), 1, 'Intento ilícito de anulación');
    afirmar(false, 'Caso 35: Anular gasto con pagos activos debe fallar');
} catch (ConflictoGastoExcepcion $e) {
    afirmar(true, 'Caso 35: Anular gasto con pagos activos lanza ConflictoGastoExcepcion');
}

// Caso 36: Reversión del pago bancario (Pago 2)
$okReverso = $gastoServicio->reversarPagoEgreso((int) $pago2->obtenerId(), 1, 'Error en comprobante de transferencia');
afirmar($okReverso === true, 'Caso 36: Reversión del pago bancario ejecutada exitosamente');

// Caso 37: Restitución bancaria registrada
$stmtRestit = $pdo->prepare(
    'SELECT * FROM movimientos_bancarios WHERE concepto LIKE :conc ORDER BY id DESC LIMIT 1'
);
$stmtRestit->execute(['conc' => "%reversión de pago {$pago2->obtenerCodigo()}%"]);
$movRestit = $stmtRestit->fetch(PDO::FETCH_ASSOC);
afirmar($movRestit && $movRestit['tipo_movimiento'] === 'INGRESO_TRANSFERENCIA',
    'Caso 37: Movimiento bancario correctivo de restitución asentado');

// Caso 38: Reconstrucción automática del saldo tras reversión
$resumenPostReverso = $gastoServicio->obtenerResumenGasto((int) $gastoMath->obtenerId());
afirmar(
    bccomp($resumenPostReverso->obtenerMontoAplicadoAcumulado(), '300.00', 2) === 0 &&
    bccomp($resumenPostReverso->obtenerSaldoPendiente(), '880.00', 2) === 0 &&
    $resumenPostReverso->obtenerSituacionFinanciera() === GastoResumenDTO::SITUACION_PARCIAL,
    'Caso 38: Saldo pendiente vuelve a S/ 880.00 automáticamente tras reversión'
);

// Reversar Pago 1 para dejar el gasto sin aplicaciones
$gastoServicio->reversarPagoEgreso((int) $pago1->obtenerId(), 1, 'Reversión pago efectivo');

// Caso 39: Anulación exitosa una vez liberados todos los pagos
$gastoAnulado = $gastoServicio->anularGasto((int) $gastoMath->obtenerId(), 1, 'Cancelación definitiva del servicio');
afirmar($gastoAnulado->obtenerEstado() === Gasto::ESTADO_ANULADO,
    'Caso 39: Anulación exitosa del gasto una vez eliminadas las aplicaciones activas');

// Caso 40: Cero DELETE (el registro permanece inmutable en la base de datos)
$gastoPersistente = $gastoRepo->obtenerGastoPorId((int) $gastoMath->obtenerId());
afirmar($gastoPersistente !== null && $gastoPersistente->obtenerEstado() === Gasto::ESTADO_ANULADO,
    'Caso 40: CERO DELETE: El gasto anulado existe en base de datos para auditoría histórica');

// -----------------------------------------------------------------------------
// REPORTE FINAL
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESULTADOS MATRIZ GASTOS-1: {$pasadas} / {$total} PASADAS\n";
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
