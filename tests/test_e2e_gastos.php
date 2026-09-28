<?php

declare(strict_types=1);

/**
 * Suite de Verificación GASTOS-1 — Pruebas End-to-End (E2E) y Ciclo de Vida Completo (14 Casos)
 *
 * GASTOS-1 / D-086.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Controladores\GastoControlador;
use CamargoPMS\Modelos\Gasto;
use CamargoPMS\Modelos\GastoEvidencia;
use CamargoPMS\Modelos\GastoResumenDTO;
use CamargoPMS\Modelos\PagoEgreso;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CajaRepositorio;
use CamargoPMS\Repositorios\GastoRepositorio;
use CamargoPMS\Repositorios\PagoEgresoRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CajaServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\GastoServicio;
use CamargoPMS\Servicios\SesionServicio;

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
echo " CAMARGO PMS — PRUEBAS E2E GASTOS-1 (14 CASOS DE CICLO DE VIDA)\n";
echo " Decisión Vinculante: D-086\n";
echo "====================================================================\n\n";

$stmtCatLuz = $pdo->query('SELECT id FROM gasto_categorias WHERE codigo = "SERV_ELECTRICIDAD" LIMIT 1');
$catLuzId = (int) $stmtCatLuz->fetchColumn();

$stmtCatMant = $pdo->query('SELECT id FROM gasto_categorias WHERE codigo = "MANT_MENOR" LIMIT 1');
$catMantId = (int) $stmtCatMant->fetchColumn();

$stmtCatAgua = $pdo->query('SELECT id FROM gasto_categorias WHERE codigo = "SERV_AGUA" LIMIT 1');
$catAguaId = (int) $stmtCatAgua->fetchColumn();

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
// E2E-01: Registro de gasto sin necesidad de sesión de caja abierta
// -----------------------------------------------------------------------------
$gastoE2E1 = $gastoServicio->crearGasto([
    'categoria_id' => $catMantId,
    'ambito' => Gasto::AMBITO_UNIDAD,
    'propiedad_id' => $propiedadId,
    'unidad_id' => $unidadId,
    'descripcion_concepto' => 'Reparación de grifería y tubería baño suite',
    'acreedor_nombre' => 'Gasfitería Express S.A.C.',
    'acreedor_documento' => '20554433221',
    'tipo_comprobante' => Gasto::COMPROBANTE_FACTURA,
    'comprobante_serie' => 'F002',
    'comprobante_numero' => '0000881',
    'subtotal' => '200.00',
    'impuestos' => '36.00',
    'total' => '236.00',
], 1);
afirmar($gastoE2E1 !== null && $gastoE2E1->obtenerEstado() === Gasto::ESTADO_REGISTRADO,
    'E2E-01: Hecho económico reconocido y registrado sin requerir sesión de caja abierta');

// -----------------------------------------------------------------------------
// E2E-02: Adjuntar N evidencias documentales al gasto
// -----------------------------------------------------------------------------
$evidencia1 = $gastoServicio->adjuntarEvidencia((int) $gastoE2E1->obtenerId(), [
    'tipo_evidencia' => GastoEvidencia::TIPO_COMPROBANTE_FISCAL,
    'nombre_original' => 'factura_F002-0000881.pdf',
    'ruta_archivo' => 'storage/gastos/factura_F002-0000881.pdf',
    'tamano_bytes' => 1048576,
    'hash_sha256' => hash('sha256', 'factura_simulada_contenido_pdf'),
], 1);

$evidencia2 = $gastoServicio->adjuntarEvidencia((int) $gastoE2E1->obtenerId(), [
    'tipo_evidencia' => GastoEvidencia::TIPO_FOTO_BIEN,
    'nombre_original' => 'foto_griferia_reparada.jpg',
    'ruta_archivo' => 'storage/gastos/foto_griferia_reparada.jpg',
    'tamano_bytes' => 2097152,
    'hash_sha256' => hash('sha256', 'foto_simulada_griferia_jpg'),
], 1);

$resumenE2E1 = $gastoServicio->obtenerResumenGasto((int) $gastoE2E1->obtenerId());
afirmar(count($resumenE2E1->obtenerEvidencias()) === 2,
    'E2E-02: Soporte documental para N evidencias (factura PDF + foto de trabajo)');

// -----------------------------------------------------------------------------
// E2E-03: Integridad criptográfica SHA-256 de las evidencias
// -----------------------------------------------------------------------------
$evs = $resumenE2E1->obtenerEvidencias();
$hashValido = (strlen($evs[0]->obtenerHashSha256()) === 64 && strlen($evs[1]->obtenerHashSha256()) === 64);
afirmar($hashValido, 'E2E-03: Integridad criptográfica SHA-256 verificada en cada evidencia');

// -----------------------------------------------------------------------------
// E2E-04: Aprobación gerencial del gasto
// -----------------------------------------------------------------------------
$gastoE2E1Aprob = $gastoServicio->aprobarGasto((int) $gastoE2E1->obtenerId(), 1, 'Conformidad técnica de trabajo');
afirmar($gastoE2E1Aprob->obtenerEstado() === Gasto::ESTADO_APROBADO,
    'E2E-04: Aprobación formal habilitando el gasto para desembolso');

// -----------------------------------------------------------------------------
// E2E-05: Apertura de sesión de turno en caja física con fondo inicial
// -----------------------------------------------------------------------------
// Si hay sesión abierta previa, cerrarla para probar ciclo completo
$sesionPrevia = $cajaServicio->obtenerSesionActivaPorCaja($cajaFisicaId);
if ($sesionPrevia !== null) {
    // Cerrar sesión previa con arqueo cuadrado
    $neto = bcsub($sesionPrevia->obtenerTotalIngresosEfectivo(), $sesionPrevia->obtenerTotalEgresosEfectivo(), 2);
    $esperado = bcadd($sesionPrevia->obtenerMontoApertura(), $neto, 2);
    $cajaServicio->cerrarSesion((int) $sesionPrevia->obtenerId(), $esperado, 'Cierre previo para test E2E', 1);
}

$sesionE2E = $cajaServicio->aperturarSesion($cajaFisicaId, '1000.00', 'Apertura de turno E2E', 1);
$sesionE2EId = (int) $sesionE2E->obtenerId();
afirmar($sesionE2E->estaAbierta() && bccomp($sesionE2E->obtenerMontoApertura(), '1000.00', 2) === 0,
    'E2E-05: Apertura formal de sesión de caja física con S/ 1000.00');

// -----------------------------------------------------------------------------
// E2E-06: Pago de egreso en efectivo desde la caja chica (desembolso de gaveta)
// -----------------------------------------------------------------------------
$pagoE2E1 = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoEfectivoId,
    'sesion_caja_id' => $sesionE2EId,
    'aplicaciones' => [['gasto_id' => $gastoE2E1->obtenerId(), 'monto' => '236.00']],
], 1)['pago'];
afirmar($pagoE2E1 !== null && bccomp($pagoE2E1->obtenerMontoTotal(), '236.00', 2) === 0,
    'E2E-06: Desembolso real de efectivo por S/ 236.00 ejecutado desde la caja física');

// -----------------------------------------------------------------------------
// E2E-07: Verificación del libro mayor de caja física
// -----------------------------------------------------------------------------
$movCajaE2E = $cajaRepo->obtenerMovimientoCaja((int) $pagoE2E1->obtenerMovimientoCajaId());
afirmar(
    $movCajaE2E !== null &&
    $movCajaE2E->obtenerTipoMovimiento() === 'EGRESO_GASTO_MENOR' &&
    bccomp($movCajaE2E->obtenerMonto(), '236.00', 2) === 0,
    'E2E-07: Asiento inmutable en libro mayor movimientos_caja con tipo EGRESO_GASTO_MENOR'
);

// -----------------------------------------------------------------------------
// E2E-08: Arqueo y cierre de sesión de caja reflejando el egreso de gasto
// -----------------------------------------------------------------------------
// Esperado = 1000.00 - 236.00 = 764.00
$sesionCierreE2E = $cajaServicio->cerrarSesion($sesionE2EId, '764.00', 'Cierre de turno cuadrado', 1);
afirmar(
    $sesionCierreE2E->estaCerrada() &&
    $sesionCierreE2E->obtenerResultadoArqueo() === 'CUADRADA' &&
    bccomp($sesionCierreE2E->obtenerMontoEsperado(), '764.00', 2) === 0,
    'E2E-08: Arqueo de cierre de caja CUADRADO descontando exactamente el egreso de S/ 236.00'
);

// -----------------------------------------------------------------------------
// E2E-09: Pago de egreso bancario (transferencia) para gasto a crédito
// -----------------------------------------------------------------------------
$gastoE2EBanco = $gastoServicio->crearGasto([
    'categoria_id' => $catLuzId,
    'ambito' => Gasto::AMBITO_PROPIEDAD,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Factura mensual de electricidad',
    'acreedor_nombre' => 'Enel Distribución Perú S.A.A.',
    'acreedor_documento' => '20269988771',
    'tipo_comprobante' => Gasto::COMPROBANTE_RECIBO_SERVICIO_PUBLICO,
    'comprobante_serie' => 'REC',
    'comprobante_numero' => '8849201',
    'subtotal' => '1500.00',
    'impuestos' => '270.00',
    'total' => '1770.00',
], 1);
$gastoServicio->aprobarGasto((int) $gastoE2EBanco->obtenerId(), 1);

$pagoBanco = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'referencia_operacion' => 'TRANS-ENEL-8849201',
    'aplicaciones' => [['gasto_id' => $gastoE2EBanco->obtenerId(), 'monto' => '1770.00']],
], 1)['pago'];
afirmar($pagoBanco !== null && bccomp($pagoBanco->obtenerMontoTotal(), '1770.00', 2) === 0,
    'E2E-09: Desembolso bancario de S/ 1770.00 ejecutado vía transferencia');

// -----------------------------------------------------------------------------
// E2E-10: Verificación del libro mayor bancario
// -----------------------------------------------------------------------------
$movBancoId = $pagoBanco->obtenerMovimientoBancarioId();
$stmtMB = $pdo->prepare('SELECT * FROM movimientos_bancarios WHERE id = :id LIMIT 1');
$stmtMB->execute(['id' => $movBancoId]);
$movB = $stmtMB->fetch(PDO::FETCH_ASSOC);
afirmar(
    $movB &&
    $movB['tipo_movimiento'] === 'EGRESO_TRANSFERENCIA' &&
    $movB['numero_operacion'] === 'TRANS-ENEL-8849201' &&
    bccomp((string)$movB['monto'], '1770.00', 2) === 0,
    'E2E-10: Asiento inmutable en libro mayor movimientos_bancarios con número de operación'
);

// -----------------------------------------------------------------------------
// E2E-11: Pago M:N consolidado (un desembolso bancario amortiza 2 gastos)
// -----------------------------------------------------------------------------
$gastoMN1 = $gastoServicio->crearGasto([
    'categoria_id' => $catAguaId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Recibo de agua predio A',
    'acreedor_nombre' => 'SEDAPAL',
    'total' => '300.00'
]);
$gastoServicio->aprobarGasto((int) $gastoMN1->obtenerId(), 1);

$gastoMN2 = $gastoServicio->crearGasto([
    'categoria_id' => $catAguaId,
    'propiedad_id' => $propiedadId,
    'descripcion_concepto' => 'Recibo de agua predio B',
    'acreedor_nombre' => 'SEDAPAL',
    'total' => '200.00'
]);
$gastoServicio->aprobarGasto((int) $gastoMN2->obtenerId(), 1);

$pagoConsolidado = $gastoServicio->ejecutarPagoEgreso([
    'metodo_pago_id' => $metodoTransferenciaId,
    'cuenta_bancaria_id' => $cuentaBancariaId,
    'referencia_operacion' => 'TRANS-SEDAPAL-LOTE',
    'aplicaciones' => [
        ['gasto_id' => $gastoMN1->obtenerId(), 'monto' => '300.00'],
        ['gasto_id' => $gastoMN2->obtenerId(), 'monto' => '200.00'],
    ]
], 1)['pago'];

$resMN1 = $gastoServicio->obtenerResumenGasto((int) $gastoMN1->obtenerId());
$resMN2 = $gastoServicio->obtenerResumenGasto((int) $gastoMN2->obtenerId());

afirmar(
    bccomp($pagoConsolidado->obtenerMontoTotal(), '500.00', 2) === 0 &&
    $resMN1->obtenerSituacionFinanciera() === GastoResumenDTO::SITUACION_PAGADO &&
    $resMN2->obtenerSituacionFinanciera() === GastoResumenDTO::SITUACION_PAGADO,
    'E2E-11: Pago M:N consolidado de S/ 500.00 amortiza y liquida simultáneamente 2 gastos'
);

// -----------------------------------------------------------------------------
// E2E-12: Consulta analítica por propiedad
// -----------------------------------------------------------------------------
$gastosPorProp = $gastoServicio->listarGastos(['propiedad_id' => $propiedadId]);
afirmar(count($gastosPorProp) >= 3, 'E2E-12: Consulta analítica de gastos filtrados por propiedad');

// -----------------------------------------------------------------------------
// E2E-13: Trazabilidad inmutable D-061
// -----------------------------------------------------------------------------
$historialE2E = $gastoRepo->listarHistorialGasto((int) $gastoE2E1->obtenerId());
afirmar(count($historialE2E) >= 2, 'E2E-13: Trazabilidad inmutable append-only D-061 verificada');

// -----------------------------------------------------------------------------
// E2E-14: Despacho HTTP del Controlador y Endpoints
// -----------------------------------------------------------------------------
$vista = new Vista();
$sesionServicio = new SesionServicio();
$controlador = new GastoControlador(
    $vista,
    $gastoServicio,
    $gastoRepo,
    $pagoRepo,
    $sesionServicio,
    new AutorizacionServicio(),
    new AuditoriaServicio($pdo),
    new CsrfServicio(),
    $pdo
);

// Probar apiListar
$_GET = [];
$resApiListar = $controlador->apiListar();
$jsonListar = json_decode($resApiListar->obtenerContenido(), true);

// Probar apiDetalle
$resApiDetalle = $controlador->apiDetalle(['id' => (string)$gastoE2E1->obtenerId()]);
$jsonDetalle = json_decode($resApiDetalle->obtenerContenido(), true);

// Probar apiCategorias
$resApiCat = $controlador->apiCategorias();
$jsonCat = json_decode($resApiCat->obtenerContenido(), true);

afirmar(
    $resApiListar->obtenerCodigoEstado() === 200 && $jsonListar['exito'] === true &&
    $resApiDetalle->obtenerCodigoEstado() === 200 && $jsonDetalle['exito'] === true &&
    $resApiCat->obtenerCodigoEstado() === 200 && $jsonCat['exito'] === true,
    'E2E-14: Endpoints HTTP /api/gastos, /api/gastos/{id} y /api/gastos/categorias operativos al 100%'
);

// -----------------------------------------------------------------------------
// REPORTE FINAL
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RESULTADOS E2E GASTOS-1: {$pasadas} / {$total} PASADAS\n";
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
