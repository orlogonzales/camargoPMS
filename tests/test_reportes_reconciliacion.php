<?php

declare(strict_types=1);

/**
 * Suite de Verificación REPORTES-1 — Reconciliación y No-Divergencia (10 Casos)
 *
 * Verifica la coherencia estricta entre la proyección analítica de REPORTES-1
 * y las fuentes de verdad soberanas:
 * - Tape Chart (D-084)
 * - FINANCIERO-2 (D-069, D-071)
 * - GASTOS-1 (D-086)
 * - COMPRAS-1 (D-080)
 * - DOCUMENTOS-1 (D-079)
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ReporteRepositorio;
use CamargoPMS\Servicios\Documentos\GeneradorPdf;
use CamargoPMS\Servicios\ReporteServicio;
use CamargoPMS\Servicios\TapeChartServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$reporteRepo = new ReporteRepositorio($pdo);
$reporteServicio = new ReporteServicio($reporteRepo);
$tapeChartServicio = new TapeChartServicio();

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
echo " CAMARGO PMS — PRUEBAS REPORTES-1: RECONCILIACIÓN (10 CASOS)\n";
echo " Decisión Vinculante: D-087\n";
echo "====================================================================\n\n";

$fechaHoy = date('Y-m-d');
$stmtProp = $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();

// Caso 1: No divergencia Tape Chart vs MDR en total de habitaciones físicas
$unidadesReporte = $reporteRepo->obtenerUnidadesInventario($propiedadId);
$proyeccionTape = $tapeChartServicio->obtenerProyeccion($propiedadId, $fechaHoy, date('Y-m-d', strtotime('+1 day')));
$arrTape = $proyeccionTape->aArreglo();
afirmar(count($unidadesReporte) === count($arrTape['filas_unidades']),
    'Caso 1: No divergencia en número de unidades físicas entre Tape Chart y MDR');

// Caso 2: No divergencia Tape Chart vs MDR en semántica de bloqueo OOO
$dtoDiario = $reporteServicio->generarReporteDiario($fechaHoy, $propiedadId);
$op = $dtoDiario->obtenerOperacionHotelera();
$ordenesBloqueo = $reporteRepo->obtenerOrdenesBloqueantesFecha($fechaHoy, $propiedadId);
$oooEsperado = count(array_unique(array_column($ordenesBloqueo, 'unidad_id')));
afirmar($op['unidades_ooo'] === $oooEsperado,
    'Caso 2: No divergencia en unidades OOO bloqueantes derivadas de mantenimiento_ordenes');

// Caso 3: Reconciliación FINANCIERO-2 vs Flujo de Caja en movimientos de caja
$movsCajaBD = $reporteRepo->obtenerMovimientosCajaRango($fechaHoy, $fechaHoy, $propiedadId);
$dtoFlujo = $reporteServicio->generarFlujoCaja($fechaHoy, $fechaHoy, $propiedadId);
$movsDetalle = $dtoFlujo->obtenerMovimientosDetalle();
$movsCajaFlujo = array_filter($movsDetalle, fn($m) => $m['medio'] === 'EFECTIVO');
afirmar(count($movsCajaBD) === count($movsCajaFlujo),
    'Caso 3: Reconciliación exacta de movimientos de efectivo entre FINANCIERO-2 y Flujo de Caja');

// Caso 4: Reconciliación FINANCIERO-2 vs Flujo de Caja en saldo inicial acumulado
$saldoInicialDirecto = $reporteRepo->obtenerSaldoInicialCaja($fechaHoy, $propiedadId);
$efeTotales = $dtoFlujo->obtenerTotalesEfectivo();
afirmar(bccomp($saldoInicialDirecto, (string)$efeTotales['saldo_inicial'], 2) === 0,
    'Caso 4: Reconciliación no divergente de saldo inicial de caja en fecha de corte');

// Caso 5: Reconciliación GASTOS-1 vs Aging CxP: saldo reconstructible coincide con partidas
$dtoAgingCxp = $reporteServicio->generarAgingCxP($fechaHoy, $propiedadId);
$partidasGastos = array_filter($dtoAgingCxp->obtenerPartidas(), fn($p) => $p['origen'] === 'GASTO_OPERATIVO');
$coherenciaGastos = true;
foreach ($partidasGastos as $pg) {
    $saldoEsperado = bcsub((string)$pg['monto_original'], (string)$pg['monto_amortizado'], 2);
    if (bccomp((string)$pg['saldo_pendiente'], $saldoEsperado, 2) !== 0) {
        $coherenciaGastos = false;
        break;
    }
}
afirmar($coherenciaGastos,
    'Caso 5: Reconciliación GASTOS-1: Saldo pendiente en Aging CxP coincide con Total - Aplicado');

// Caso 6: Reconciliación COMPRAS-1 vs Aging CxP: cuentas por pagar no divergentes
$partidasCompras = array_filter($dtoAgingCxp->obtenerPartidas(), fn($p) => $p['origen'] === 'COMPRA_PROVEEDOR');
$coherenciaCompras = true;
foreach ($partidasCompras as $pc) {
    $saldoEsperado = bcsub((string)$pc['monto_original'], (string)$pc['monto_amortizado'], 2);
    if (bccomp((string)$pc['saldo_pendiente'], $saldoEsperado, 2) !== 0) {
        $coherenciaCompras = false;
        break;
    }
}
afirmar($coherenciaCompras,
    'Caso 6: Reconciliación COMPRAS-1: Saldo de cuentas_por_pagar coincide con Total - Amortizado');

// Caso 7: Reconciliación ARRENDAMIENTOS vs Aging CxC: cuotas pendientes coinciden con saldo de cargo
$dtoAgingCxc = $reporteServicio->generarAgingCxC($fechaHoy, $propiedadId);
$partidasArr = array_filter($dtoAgingCxc->obtenerPartidas(), fn($p) => $p['origen'] === 'ARRENDAMIENTO');
$coherenciaArr = true;
foreach ($partidasArr as $pa) {
    $saldoEsperado = bcsub((string)$pa['monto_original'], (string)$pa['monto_amortizado'], 2);
    if (bccomp((string)$pa['saldo_pendiente'], $saldoEsperado, 2) !== 0) {
        $coherenciaArr = false;
        break;
    }
}
afirmar($coherenciaArr,
    'Caso 7: Reconciliación Arrendamientos: Saldo de cuotas en Aging CxC coincide con Total - Pagado');

// Caso 8: Reconciliación DOCUMENTOS-1: GeneradorPdf produce firma criptográfica SHA-256 válida
$genPdf = new GeneradorPdf();
$htmlTest = '<html><body><h1>Reconciliacion Dompdf Camargo PMS</h1></body></html>';
$renderTest = $genPdf->renderizar($htmlTest, false);
$hashReal = hash('sha256', $renderTest['binario_pdf']);
afirmar($renderTest['hash_pdf_sha256'] === $hashReal && strlen($hashReal) === 64,
    'Caso 8: Reconciliación DOCUMENTOS-1: Hash criptográfico SHA-256 coincide con el binario Dompdf');

// Caso 9: Reconciliación Exportación PDF Reportes con motor DOCUMENTOS-1
$pdfMdr = $reporteServicio->exportarPDF('DIARIO', ['fecha' => $fechaHoy, 'propiedad_id' => $propiedadId]);
$hashMdrReal = hash('sha256', $pdfMdr['binario_pdf']);
afirmar($pdfMdr['hash_pdf_sha256'] === $hashMdrReal,
    'Caso 9: Reconciliación PDF Reportes: Reutilización íntegra de GeneradorPdf y verificación de integridad');

// Caso 10: Invariante de No Divergencia Patrimonial: Consolidado == Efectivo + Bancos
$totalesEfe = $dtoFlujo->obtenerTotalesEfectivo();
$totalesBan = $dtoFlujo->obtenerTotalesBanco();
$totalesCon = $dtoFlujo->obtenerTotalesConsolidado();
$sumaSaldosFinales = bcadd((string)$totalesEfe['saldo_final'], (string)$totalesBan['saldo_final'], 2);
afirmar(bccomp((string)$totalesCon['saldo_final'], $sumaSaldosFinales, 2) === 0,
    'Caso 10: No divergencia patrimonial: Saldo Consolidado Final coincide con la suma de cajas y bancos');

echo "\n====================================================================\n";
echo " RESULTADOS RECONCILIACIÓN REPORTES-1: {$pasadas}/{$total} PASADAS\n";
if ($fallidas > 0) {
    echo " FALLIDAS: {$fallidas}\n";
    foreach ($errores as $err) {
        echo "  - {$err}\n";
    }
}
echo "====================================================================\n";

if ($fallidas > 0) {
    exit(1);
}
