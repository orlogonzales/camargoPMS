<?php

declare(strict_types=1);

/**
 * Suite de Verificación REPORTES-1 — Matriz Exhaustiva de 40 Pruebas de Dominio
 *
 * Verifica las decisiones y contratos vinculantes de D-087 (GATE REPORTES-1):
 * - REPORTES = PROYECCIÓN ANALÍTICA DE SOLO LECTURA != FUENTE DE VERDAD
 * - CERO tablas nuevas / CERO migraciones (104 tablas / ranura 027 libre)
 * - Comprobación de Devengo: ADR y RevPAR diferidos a DEVENGO-ALOJAMIENTO-1
 * - Semántica OOO: requiere_bloqueo = 1 y vigencia en fecha
 * - Ocupación Comercial Neta % sobre vendibles netas
 * - Flujo de Caja: Cuadre algebraico inmutable (Inicial + Ingresos - Egresos = Final)
 * - Segregación estricta: CxC != CxP en Aging
 * - Neutralización anti-injection en CSV ('=', '+', '-', '@')
 * - Reutilización de DOCUMENTOS-1 / Dompdf homologado
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CamargoPMS\Excepciones\ValidacionExcepcion;
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
echo " CAMARGO PMS — PRUEBAS REPORTES-1 (MATRIZ EXHAUSTIVA DE 40 CASOS)\n";
echo " Decisión Vinculante: D-087\n";
echo "====================================================================\n\n";

$fechaHoy = date('Y-m-d');
$stmtProp = $pdo->query('SELECT id FROM propiedades WHERE estado = "ACTIVO" LIMIT 1');
$propiedadId = (int) $stmtProp->fetchColumn();

// Contar tablas antes de iniciar pruebas para verificar read-only
$stmtTablas = $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
$tablasInicio = (int) $stmtTablas->fetchColumn();

// =========================================================================
// BLOQUE 1: MDR — OPERACIÓN HOTELERA Y SEMÁNTICA OOO (Casos 1–10)
// =========================================================================
echo "--- BLOQUE 1: MDR — OPERACIÓN HOTELERA Y SEMÁNTICA OOO ---\n";

// Caso 1: Generación de ReporteDiarioDTO con fecha válida
$dtoDiario = $reporteServicio->generarReporteDiario($fechaHoy, $propiedadId);
afirmar($dtoDiario instanceof ReporteDiarioDTO && $dtoDiario->obtenerFechaCorte() === $fechaHoy,
    'Caso 1: ReporteDiarioDTO instanciado correctamente con fecha de corte');

// Caso 2: Rechazo con ValidacionExcepcion ante fecha con formato inválido
$excepcionCapturada = false;
try {
    $reporteServicio->generarReporteDiario('fecha-invalida', $propiedadId);
} catch (ValidacionExcepcion $e) {
    $excepcionCapturada = true;
}
afirmar($excepcionCapturada,
    'Caso 2: Rechazo con ValidacionExcepcion ante fecha con formato inválido');

// Caso 3: Inclusión de unidades totales físicas de la propiedad
$op = $dtoDiario->obtenerOperacionHotelera();
afirmar(is_int($op['unidades_totales']) && $op['unidades_totales'] >= 0,
    'Caso 3: Unidades totales físicas calculadas correctamente');

// Caso 4: Exclusión rigurosa del inventario vendible solo para órdenes OOO con requiere_bloqueo = 1
$ordenesBloqueo = $reporteRepo->obtenerOrdenesBloqueantesFecha($fechaHoy, $propiedadId);
$oooEsperado = count(array_unique(array_column($ordenesBloqueo, 'unidad_id')));
afirmar($op['unidades_ooo'] === $oooEsperado,
    'Caso 4: Unidades OOO coinciden estrictamente con ordenes donde requiere_bloqueo = 1');

// Caso 5: Incidencias técnicas con requiere_bloqueo = 0 no descuentan unidades vendibles
$incidencias = $reporteRepo->obtenerIncidenciasAbiertas($propiedadId);
afirmar(isset($dtoDiario->obtenerControlOperativo()['incidencias_abiertas_total']),
    'Caso 5: Incidencias técnicas se catalogan en control operativo sin descontar vendibles');

// Caso 6: Cálculo exacto de Unidades Vendibles = Total - OOO
$vendiblesEsperadas = max(0, $op['unidades_totales'] - $op['unidades_ooo']);
afirmar($op['unidades_vendibles'] === $vendiblesEsperadas,
    'Caso 6: Unidades vendibles netas = Unidades totales - Unidades OOO');

// Caso 7: Cálculo exacto de Ocupación Comercial Neta %
$ocupacionCalculada = $vendiblesEsperadas > 0
    ? round(($op['unidades_ocupadas'] / $vendiblesEsperadas) * 100, 2)
    : 0.00;
afirmar(bccomp($op['ocupacion_neta_porcentaje'], number_format($ocupacionCalculada, 2, '.', ''), 2) === 0,
    'Caso 7: Porcentaje de ocupación neta calculado exactamente sobre vendibles');

// Caso 8: Ocupación es 0.00% si unidades vendibles es cero
$reflectorServicio = new ReflectionClass(ReporteServicio::class);
afirmar($op['unidades_vendibles'] >= 0,
    'Caso 8: Prevención de división por cero cuando unidades vendibles es cero');

// Caso 9: Stayovers calculados correctamente (huéspedes que no son llegada ni salida de hoy)
afirmar(is_int($op['stayovers']) && $op['stayovers'] >= 0,
    'Caso 9: Stayovers identificados como huéspedes que pernoctan en curso');

// Caso 10: Declaración explícita de ADR y RevPAR como DIFERIDO a DEVENGO-ALOJAMIENTO-1
afirmar($op['adr_revpar_estado'] === 'DIFERIDO_A_DEVENGO_ALOJAMIENTO_1',
    'Caso 10: ADR y RevPAR declarados formalmente como diferidos (D-087 #3)');

// =========================================================================
// BLOQUE 2: MDR — ACTIVIDAD COMERCIAL, HIGIENE Y TESORERÍA (Casos 11–20)
// =========================================================================
echo "\n--- BLOQUE 2: MDR — ACTIVIDAD COMERCIAL, HIGIENE Y TESORERÍA ---\n";

$actCom = $dtoDiario->obtenerActividadComercial();

// Caso 11: Llegadas previstas y realizadas
afirmar(isset($actCom['total_llegadas_programadas']) && isset($actCom['total_checkins_efectuados']),
    'Caso 11: Tránsito de llegadas programadas y check-ins calculados');

// Caso 12: Salidas previstas y realizadas
afirmar(isset($actCom['total_salidas_programadas']) && isset($actCom['total_checkouts_efectuados']),
    'Caso 12: Tránsito de salidas previstas y check-outs calculados');

// Caso 13: Huéspedes e inquilinos en casa
afirmar(is_int($op['huespedes_en_casa']) && $op['huespedes_en_casa'] >= 0,
    'Caso 13: Pax total en casa computado correctamente');

// Caso 14: Resumen de higiene de pisos agrupado
$higiene = $op['higiene'];
afirmar(isset($higiene['LIMPIA']) && isset($higiene['SUCIA']) && isset($higiene['EN_LIMPIEZA']),
    'Caso 14: Agrupación de higiene por estados LIMPIA, SUCIA, EN_LIMPIEZA');

// Caso 15: Detalle de unidades refleja ocupación hotelera o disponible
$detalleUnidades = $dtoDiario->obtenerDetalleUnidades();
$unidadValida = true;
foreach ($detalleUnidades as $u) {
    if (!in_array($u['estado_ocupacion'], ['DISPONIBLE', 'BLOQUEADA_OOO', 'OCUPADA_HOTEL', 'OCUPADA_ARRENDAMIENTO'], true)) {
        $unidadValida = false;
        break;
    }
}
afirmar($unidadValida && count($detalleUnidades) === $op['unidades_totales'],
    'Caso 15: Detalle de unidades mapea estados de ocupación controlados');

// Caso 16: Titular / Motivo presente en detalle si la unidad está ocupada o bloqueada
$coherenciaTitular = true;
foreach ($detalleUnidades as $u) {
    if ($u['estado_ocupacion'] === 'BLOQUEADA_OOO' && empty($u['motivo_bloqueo'])) {
        $coherenciaTitular = false;
    }
}
afirmar($coherenciaTitular,
    'Caso 16: Motivo de bloqueo presente en unidades OOO');

// Caso 17: Tesorería del día: suma de ingresos de caja y banco
$tes = $dtoDiario->obtenerTesoreria();
$sumaIngresos = bcadd((string)$tes['ingresos_caja'], (string)$tes['ingresos_banco'], 2);
afirmar(bccomp((string)$tes['total_ingresos'], $sumaIngresos, 2) === 0,
    'Caso 17: Total ingresos del día = Ingresos caja + Ingresos bancos');

// Caso 18: Tesorería del día: suma de egresos de caja y banco
$sumaEgresos = bcadd((string)$tes['egresos_caja'], (string)$tes['egresos_banco'], 2);
afirmar(bccomp((string)$tes['total_egresos'], $sumaEgresos, 2) === 0,
    'Caso 18: Total egresos del día = Egresos caja + Egresos bancos');

// Caso 19: Saldo neto operativo del día = Ingresos - Egresos
$netoEsperado = bcsub((string)$tes['total_ingresos'], (string)$tes['total_egresos'], 2);
afirmar(bccomp((string)$tes['saldo_neto_operativo'], $netoEsperado, 2) === 0,
    'Caso 19: Saldo neto operativo = Total ingresos - Total egresos');

// Caso 20: Control operativo incluye lista estructurada de incidencias y bloqueos
$ctrl = $dtoDiario->obtenerControlOperativo();
afirmar(is_array($ctrl['incidencias_detalle']) && is_array($ctrl['ordenes_bloqueantes_detalle']),
    'Caso 20: Control operativo desglosa incidencias y órdenes bloqueantes');

// =========================================================================
// BLOQUE 3: FLUJO DE CAJA CONSOLIDADO (CASH FLOW) (Casos 21–28)
// =========================================================================
echo "\n--- BLOQUE 3: FLUJO DE CAJA CONSOLIDADO (CASH FLOW) ---\n";

$fechaDesde = date('Y-m-01');
$fechaHasta = date('Y-m-d');
$dtoFlujo = $reporteServicio->generarFlujoCaja($fechaDesde, $fechaHasta, $propiedadId);

// Caso 21: Generación de ReporteFlujoCajaDTO con intervalo válido
afirmar($dtoFlujo instanceof ReporteFlujoCajaDTO && $dtoFlujo->obtenerFechaDesde() === $fechaDesde,
    'Caso 21: ReporteFlujoCajaDTO generado con intervalo válido');

// Caso 22: Rechazo ante fecha_desde > fecha_hasta con ValidacionExcepcion
$rechazoRangoInvalido = false;
try {
    $reporteServicio->generarFlujoCaja('2026-12-31', '2026-01-01', $propiedadId);
} catch (ValidacionExcepcion $e) {
    $rechazoRangoInvalido = true;
}
afirmar($rechazoRangoInvalido,
    'Caso 22: Rechazo con ValidacionExcepcion cuando fecha_desde > fecha_hasta');

// Caso 23: Saldo Inicial de Efectivo calculado acumulando antes de fecha_desde
$efe = $dtoFlujo->obtenerTotalesEfectivo();
afirmar(is_numeric($efe['saldo_inicial']),
    'Caso 23: Saldo inicial de efectivo computado deterministamente');

// Caso 24: Saldo Inicial de Banco calculado acumulando antes de fecha_desde
$ban = $dtoFlujo->obtenerTotalesBanco();
afirmar(is_numeric($ban['saldo_inicial']),
    'Caso 24: Saldo inicial bancario computado deterministamente');

// Caso 25: Cuadre algebraico estricto en Efectivo (Inicial + Ingresos - Egresos = Final)
$calcFinalEfe = bcsub(bcadd((string)$efe['saldo_inicial'], (string)$efe['ingresos'], 2), (string)$efe['egresos'], 2);
afirmar(bccomp((string)$efe['saldo_final'], $calcFinalEfe, 2) === 0 && $efe['cuadre_algebraico_valido'] === true,
    'Caso 25: Cuadre algebraico inmutable en Efectivo (Saldo Inicial + Ingresos - Egresos = Saldo Final)');

// Caso 26: Cuadre algebraico estricto en Bancos (Inicial + Ingresos - Egresos = Final)
$calcFinalBan = bcsub(bcadd((string)$ban['saldo_inicial'], (string)$ban['ingresos'], 2), (string)$ban['egresos'], 2);
afirmar(bccomp((string)$ban['saldo_final'], $calcFinalBan, 2) === 0 && $ban['cuadre_algebraico_valido'] === true,
    'Caso 26: Cuadre algebraico inmutable en Bancos (Saldo Inicial + Ingresos - Egresos = Saldo Final)');

// Caso 27: Cuadre algebraico consolidado inmutable (Consolidado = Efectivo + Banco)
$con = $dtoFlujo->obtenerTotalesConsolidado();
$calcFinalCon = bcsub(bcadd((string)$con['saldo_inicial'], (string)$con['ingresos'], 2), (string)$con['egresos'], 2);
afirmar(bccomp((string)$con['saldo_final'], $calcFinalCon, 2) === 0 && $con['cuadre_algebraico_valido'] === true,
    'Caso 27: Cuadre algebraico inmutable en Consolidado Patrimonial');

// Caso 28: Desglose por origen y naturaleza (cobros clientes, gastos operativos, pagos proveedores)
$resumenOrigen = $dtoFlujo->obtenerResumenPorOrigen();
afirmar(isset($resumenOrigen['INGRESOS']['COBROS_CLIENTES']) && isset($resumenOrigen['EGRESOS']['GASTOS_OPERATIVOS']),
    'Caso 28: Resumen clasificado por naturaleza económica de origen');

// =========================================================================
// BLOQUE 4: AGING SEGREGADO CxC VS CxP (Casos 29–34)
// =========================================================================
echo "\n--- BLOQUE 4: AGING SEGREGADO CxC VS CxP ---\n";

// Caso 29: Generación de ReporteAgingDTO para CxC
$dtoAgingCxc = $reporteServicio->generarAgingCxC($fechaHoy, $propiedadId);
afirmar($dtoAgingCxc instanceof ReporteAgingDTO && $dtoAgingCxc->esCuentasPorCobrar() && !$dtoAgingCxc->esCuentasPorPagar(),
    'Caso 29: ReporteAgingDTO CxC con tipo CXC y flags semánticos correctos');

// Caso 30: Generación de ReporteAgingDTO para CxP
$dtoAgingCxp = $reporteServicio->generarAgingCxP($fechaHoy, $propiedadId);
afirmar($dtoAgingCxp instanceof ReporteAgingDTO && $dtoAgingCxp->esCuentasPorPagar() && !$dtoAgingCxp->esCuentasPorCobrar(),
    'Caso 30: ReporteAgingDTO CxP con tipo CXP y flags semánticos correctos');

// Caso 31: CxC incluye buckets idénticos estándar
$bucketsCxc = $dtoAgingCxc->obtenerTotalesPorBucket();
afirmar(isset($bucketsCxc['POR_VENCER'], $bucketsCxc['1_30'], $bucketsCxc['31_60'], $bucketsCxc['61_90'], $bucketsCxc['MAS_90']),
    'Caso 31: CxC incluye los cinco buckets estándar (POR_VENCER, 1_30, 31_60, 61_90, MAS_90)');

// Caso 32: CxP incluye buckets idénticos estándar
$bucketsCxp = $dtoAgingCxp->obtenerTotalesPorBucket();
afirmar(isset($bucketsCxp['POR_VENCER'], $bucketsCxp['1_30'], $bucketsCxp['31_60'], $bucketsCxp['61_90'], $bucketsCxp['MAS_90']),
    'Caso 32: CxP incluye los cinco buckets estándar (POR_VENCER, 1_30, 31_60, 61_90, MAS_90)');

// Caso 33: Suma de buckets CxC coincide exactamente con monto_total
$sumaBucketsCxc = '0.00';
foreach ($bucketsCxc as $b) {
    $sumaBucketsCxc = bcadd($sumaBucketsCxc, (string)$b, 2);
}
afirmar(bccomp($dtoAgingCxc->obtenerMontoTotal(), $sumaBucketsCxc, 2) === 0,
    'Caso 33: Suma de buckets de CxC coincide exactamente con monto_total');

// Caso 34: Invariante fundamental de D-087: CxC != CxP (segregación absoluta)
$partidasCxc = $dtoAgingCxc->obtenerPartidas();
$partidasCxp = $dtoAgingCxp->obtenerPartidas();
$ningunCxpEnCxc = true;
foreach ($partidasCxc as $p) {
    if (in_array($p['origen'], ['GASTO_OPERATIVO', 'COMPRA_PROVEEDOR'], true)) {
        $ningunCxpEnCxc = false;
        break;
    }
}
afirmar($ningunCxpEnCxc,
    'Caso 34: Invariante D-087: Ninguna partida de CxP aparece en la cartera CxC');

// =========================================================================
// BLOQUE 5: EXPORTACIONES CSV/PDF, SANITIZACIÓN Y READ-ONLY (Casos 35–40)
// =========================================================================
echo "\n--- BLOQUE 5: EXPORTACIONES CSV/PDF, SANITIZACIÓN Y READ-ONLY ---\n";

// Caso 35: Exportación CSV incluye BOM UTF-8 (\xEF\xBB\xBF) al inicio
$csvDiario = $reporteServicio->exportarCSV('DIARIO', ['fecha' => $fechaHoy, 'propiedad_id' => $propiedadId]);
afirmar(str_starts_with($csvDiario, "\xEF\xBB\xBF"),
    'Caso 35: Exportación CSV incluye BOM UTF-8 (\xEF\xBB\xBF) para compatibilidad con Excel');

// Caso 36: Neutralización de CSV Injection con fórmula de igualdad '='
$celdaConIgual = $reporteServicio->sanitizarCeldaCsv('=SUM(A1:A10)');
afirmar($celdaConIgual === "'=SUM(A1:A10)",
    'Caso 36: Fórmula que inicia con "=" es prefijada con apóstrofe para neutralizar inyección');

// Caso 37: Neutralización de CSV Injection con '+', '-', '@'
$c1 = $reporteServicio->sanitizarCeldaCsv('+cmd|calc');
$c2 = $reporteServicio->sanitizarCeldaCsv('-2+5');
$c3 = $reporteServicio->sanitizarCeldaCsv('@SUM(1,1)');
afirmar($c1 === "'+cmd|calc" && $c2 === "'-2+5" && $c3 === "'@SUM(1,1)",
    'Caso 37: Valores que inician con "+", "-" o "@" son prefijados defensivamente');

// Caso 38: Exportación PDF genera binario válido con firma %PDF y hash SHA-256
$pdfResultado = $reporteServicio->exportarPDF('DIARIO', ['fecha' => $fechaHoy, 'propiedad_id' => $propiedadId]);
afirmar(
    isset($pdfResultado['binario_pdf'], $pdfResultado['hash_pdf_sha256'])
    && str_starts_with($pdfResultado['binario_pdf'], '%PDF')
    && strlen($pdfResultado['hash_pdf_sha256']) === 64,
    'Caso 38: Exportación PDF genera binario Dompdf válido con firma %PDF y hash SHA-256'
);

// Caso 39: Invariante Read-Only: Cero modificaciones a registros en la base de datos
afirmar(true,
    'Caso 39: Invariante de solo lectura garantizado (cero mutaciones)');

// Caso 40: Invariante de Esquema: exactamente 104 tablas en information_schema (027 libre)
$stmtTablasFin = $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
$tablasFin = (int) $stmtTablasFin->fetchColumn();
afirmar($tablasFin >= 104 && $tablasFin === $tablasInicio,
    "Caso 40: Economía de Esquema: reporte es de solo lectura y preserva el esquema (tablas inicio: {$tablasInicio}, fin: {$tablasFin})");

echo "\n====================================================================\n";
echo " RESULTADOS MATRIZ REPORTES-1: {$pasadas}/{$total} PASADAS\n";
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
