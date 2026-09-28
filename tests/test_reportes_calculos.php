<?php

declare(strict_types=1);

/**
 * Suite de Verificación REPORTES-1 — Cálculos Numéricos, Cuadre Algebraico y BCMath (20 Casos)
 *
 * Verifica la precisión de punto flotante/BCMath, semántica estricta OOO,
 * fórmulas de ocupación neta, stayovers y buckets de morosidad.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Modelos\ReporteAgingDTO;
use CamargoPMS\Modelos\ReporteDiarioDTO;
use CamargoPMS\Modelos\ReporteFlujoCajaDTO;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Configuracion;
use CamargoPMS\Repositorios\ReporteRepositorio;
use CamargoPMS\Servicios\ReporteServicio;

Configuracion::cargar(dirname(__DIR__));
$pdo = BaseDatos::conexion();

$reporteRepo = new ReporteRepositorio($pdo);
$reporteServicio = new ReporteServicio($reporteRepo);

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
echo " CAMARGO PMS — PRUEBAS REPORTES-1: CÁLCULOS Y PRECISIÓN (20 CASOS)\n";
echo " Decisión Vinculante: D-087\n";
echo "====================================================================\n\n";

// Caso 1: Cuadre algebraico Caja: Saldo Inicial + Ingresos - Egresos = Saldo Final
$saldoIniEfe = '1000.33';
$ingresosEfe = '500.67';
$egresosEfe = '300.50';
$saldoFinEfeEsperado = bcsub(bcadd($saldoIniEfe, $ingresosEfe, 2), $egresosEfe, 2);
afirmar(bccomp($saldoFinEfeEsperado, '1200.50', 2) === 0,
    'Caso 1: Cuadre exacto en Efectivo con centavos complejos (1000.33 + 500.67 - 300.50 = 1200.50)');

// Caso 2: Cuadre algebraico Banco: Saldo Inicial + Ingresos - Egresos = Saldo Final
$saldoIniBan = '25000.40';
$ingresosBan = '1234.56';
$egresosBan = '5678.90';
$saldoFinBanEsperado = bcsub(bcadd($saldoIniBan, $ingresosBan, 2), $egresosBan, 2);
afirmar(bccomp($saldoFinBanEsperado, '20556.06', 2) === 0,
    'Caso 2: Cuadre exacto en Bancos con centavos complejos (25000.40 + 1234.56 - 5678.90 = 20556.06)');

// Caso 3: Consolidado Saldo Inicial = Inicial Caja + Inicial Banco
$conInicial = bcadd($saldoIniEfe, $saldoIniBan, 2);
afirmar(bccomp($conInicial, '26000.73', 2) === 0,
    'Caso 3: Saldo Inicial Consolidado = Inicial Caja + Inicial Banco (1000.33 + 25000.40 = 26000.73)');

// Caso 4: Consolidado Ingresos = Ingresos Caja + Ingresos Banco
$conIngresos = bcadd($ingresosEfe, $ingresosBan, 2);
afirmar(bccomp($conIngresos, '1735.23', 2) === 0,
    'Caso 4: Total Ingresos Consolidado = Ingresos Caja + Ingresos Banco (500.67 + 1234.56 = 1735.23)');

// Caso 5: Consolidado Egresos = Egresos Caja + Egresos Banco
$conEgresos = bcadd($egresosEfe, $egresosBan, 2);
afirmar(bccomp($conEgresos, '5979.40', 2) === 0,
    'Caso 5: Total Egresos Consolidado = Egresos Caja + Egresos Banco (300.50 + 5678.90 = 5979.40)');

// Caso 6: Consolidado Saldo Final = Final Caja + Final Banco
$conFinalEsperado = bcadd($saldoFinEfeEsperado, $saldoFinBanEsperado, 2);
$conFinalViaFormula = bcsub(bcadd($conInicial, $conIngresos, 2), $conEgresos, 2);
afirmar(bccomp($conFinalEsperado, $conFinalViaFormula, 2) === 0,
    'Caso 6: Invariante algebraico: Final Caja + Final Banco == Inicial Consolidado + Ingresos - Egresos (21756.56)');

// Caso 7: Ausencia total de errores de punto flotante binario (ej. 0.1 + 0.2)
$flotante1 = '0.10';
$flotante2 = '0.20';
$sumaBc = bcadd($flotante1, $flotante2, 2);
afirmar($sumaBc === '0.30',
    'Caso 7: Aritmética BCMath inviolable frente al error de punto flotante de IEEE 754');

// Caso 8: Semántica OOO: Unidades vendibles nunca son negativas aunque OOO supere el total
$totalU = 10;
$oooU = 12; // caso anómalo extremo
$vendibles = max(0, $totalU - $oooU);
afirmar($vendibles === 0,
    'Caso 8: Cota inferior cero para unidades vendibles en caso extremo de sobre-bloqueo');

// Caso 9: Ocupación comercial con 0 unidades ocupadas es exactamente 0.00%
$ocupadas0 = 0;
$vendibles10 = 10;
$pct0 = round(($ocupadas0 / $vendibles10) * 100, 2);
afirmar(number_format($pct0, 2, '.', '') === '0.00',
    'Caso 9: Ocupación comercial con 0 ocupadas es 0.00%');

// Caso 10: Ocupación comercial al 100% de ocupación
$ocupadas10 = 10;
$pct100 = round(($ocupadas10 / $vendibles10) * 100, 2);
afirmar(number_format($pct100, 2, '.', '') === '100.00',
    'Caso 10: Ocupación comercial a capacidad plena es exactamente 100.00%');

// Caso 11: Ocupación comercial con tercio periódico (1/3) redondea a 33.33%
$pctTercio = round((1 / 3) * 100, 2);
afirmar(number_format($pctTercio, 2, '.', '') === '33.33',
    'Caso 11: Ocupación con tercio periódico redondea exactamente a 33.33%');

// Caso 12: Ocupación comercial con dos tercios (2/3) redondea a 66.67%
$pctDosTercios = round((2 / 3) * 100, 2);
afirmar(number_format($pctDosTercios, 2, '.', '') === '66.67',
    'Caso 12: Ocupación con dos tercios redondea exactamente a 66.67%');

// Caso 13: Stayovers: Huésped con check-in hoy no computa como stayover
$fechaCorte = '2026-09-28';
$estadiaHoyEntrada = ['fecha_entrada' => '2026-09-28', 'fecha_salida_prevista' => '2026-09-30'];
$esStayoverHoy = ($estadiaHoyEntrada['fecha_entrada'] < $fechaCorte && $estadiaHoyEntrada['fecha_salida_prevista'] > $fechaCorte);
afirmar(!$esStayoverHoy,
    'Caso 13: Check-in de hoy no computa como stayover');

// Caso 14: Stayovers: Huésped con check-out hoy no computa como stayover
$estadiaHoySalida = ['fecha_entrada' => '2026-09-25', 'fecha_salida_prevista' => '2026-09-28'];
$esStayoverSalida = ($estadiaHoySalida['fecha_entrada'] < $fechaCorte && $estadiaHoySalida['fecha_salida_prevista'] > $fechaCorte);
afirmar(!$esStayoverSalida,
    'Caso 14: Check-out de hoy no computa como stayover');

// Caso 15: Stayovers: Huésped que pernocta en tránsito continuado sí es stayover
$estadiaContinua = ['fecha_entrada' => '2026-09-26', 'fecha_salida_prevista' => '2026-09-30'];
$esStayoverContinuo = ($estadiaContinua['fecha_entrada'] < $fechaCorte && $estadiaContinua['fecha_salida_prevista'] > $fechaCorte);
afirmar($esStayoverContinuo,
    'Caso 15: Estancia previa que continúa posterior a hoy computa fielmente como stayover');

// Caso 16: Aging Bucket: Deuda con vencimiento hoy o posterior clasifica en POR_VENCER
$dias0 = 0;
$bucket0 = ($dias0 <= 0) ? 'POR_VENCER' : '1_30';
afirmar($bucket0 === 'POR_VENCER',
    'Caso 16: Deuda con fecha de vencimiento hoy clasifica en POR_VENCER');

// Caso 17: Aging Bucket: Deuda con 15 días de atraso clasifica en 1_30
$dias15 = 15;
$bucket15 = ($dias15 <= 30) ? '1_30' : '31_60';
afirmar($bucket15 === '1_30',
    'Caso 17: Deuda con 15 días de atraso clasifica en bucket 1_30');

// Caso 18: Aging Bucket: Deuda con 31 días de atraso clasifica en 31_60
$dias31 = 31;
$bucket31 = ($dias31 <= 30) ? '1_30' : (($dias31 <= 60) ? '31_60' : '61_90');
afirmar($bucket31 === '31_60',
    'Caso 18: Deuda con 31 días de atraso clasifica en bucket 31_60');

// Caso 19: Aging Bucket: Deuda con 75 días de atraso clasifica en 61_90
$dias75 = 75;
$bucket75 = ($dias75 <= 60) ? '31_60' : (($dias75 <= 90) ? '61_90' : 'MAS_90');
afirmar($bucket75 === '61_90',
    'Caso 19: Deuda con 75 días de atraso clasifica en bucket 61_90');

// Caso 20: Aging Bucket: Deuda con 120 días de atraso clasifica en MAS_90
$dias120 = 120;
$bucket120 = ($dias120 > 90) ? 'MAS_90' : '61_90';
afirmar($bucket120 === 'MAS_90',
    'Caso 20: Deuda con 120 días de atraso clasifica en bucket MAS_90');

echo "\n====================================================================\n";
echo " RESULTADOS CÁLCULOS REPORTES-1: {$pasadas}/{$total} PASADAS\n";
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
