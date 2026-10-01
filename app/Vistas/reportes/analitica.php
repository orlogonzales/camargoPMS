<?php

declare(strict_types=1);

/**
 * Vista de Analítica Gerencial y Rendimiento por Canal.
 * Gobernanza: REPORTES-1B / D-110 / D-087.
 *
 * @var array<string, bool> $capacidades
 * @var array<int, array{id: int, codigo: string, nombre: string, zona_horaria: string|null}> $propiedades
 * @var string $fechaDesde
 * @var string $fechaHasta
 * @var int|null $propiedadId
 * @var \CamargoPMS\Modelos\ReporteAnaliticaDTO|null $reporteAnalitico
 * @var string $csrf_token
 */

$kpis = $reporteAnalitico !== null ? $reporteAnalitico->obtenerResumenKpis() : [
    'ocupacion_media_porcentaje' => 0.00,
    'adr_promedio' => '0.00',
    'revpar_promedio' => '0.00',
    'trevpar_promedio' => '0.00',
    'total_noches_disponibles' => 0,
    'total_noches_ocupadas' => 0,
    'total_habitaciones_vendidas' => 0,
    'total_habitaciones_cortesia' => 0,
    'total_unidades_ooo' => 0,
    'total_ingreso_alojamiento_neto' => '0.00',
    'total_ingreso_alojamiento_bruto' => '0.00',
];

$devengados = $reporteAnalitico !== null ? $reporteAnalitico->obtenerDesgloseIngresosDevengados() : [
    'alojamiento_neto' => '0.00',
    'alojamiento_impuestos' => '0.00',
    'alojamiento_total' => '0.00',
    'servicios_extras' => '0.00',
    'arrendamientos' => '0.00',
    'suministros_consumos' => '0.00',
    'penalidades' => '0.00',
    'total_devengado' => '0.00',
];

$percibidos = $reporteAnalitico !== null ? $reporteAnalitico->obtenerDesgloseIngresosPercibidos() : [
    'efectivo_caja' => '0.00',
    'transferencias_banco' => '0.00',
    'tarjetas_pos' => '0.00',
    'pasarelas_neto' => '0.00',
    'total_percibido' => '0.00',
    'brecha_recaudacion' => '0.00',
];

$canales = $reporteAnalitico !== null ? $reporteAnalitico->obtenerRendimientoCanales() : [];
$serie = $reporteAnalitico !== null ? $reporteAnalitico->obtenerSerieTemporal() : [];
?>

<!-- Estilos específicos de ApexCharts -->
<link rel="stylesheet" href="<?= url_asset('vendor/apexcharts/apexcharts.css') ?>">

<input type="hidden" id="csrf-token-analitica" value="<?= e($csrf_token) ?>">

<!-- Datos iniciales serializados para renderizado instantáneo de ApexCharts sin recálculo JS -->
<script>
    window.__DATOS_ANALITICA__ = <?= json_encode($reporteAnalitico !== null ? $reporteAnalitico->aArreglo() : null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- Encabezado del Módulo de Analítica Gerencial -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-chart-line f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Analítica Gerencial & Rendimiento por Canal</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Autoridad analítica soberana de Camargo PMS: Ocupación, ADR, RevPAR, TRevPAR, serie temporal y cuota de mercado por canal.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <a href="<?= url_ruta('/reportes/analitica/exportar/csv') ?>?fecha_desde=<?= e($fechaDesde) ?>&fecha_hasta=<?= e($fechaHasta) ?><?= $propiedadId ? '&propiedad_id=' . $propiedadId : '' ?>"
                       id="btn-exportar-csv" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="fa-solid fa-file-csv me-1 text-success"></i> Exportar CSV
                    </a>
                    <a href="<?= url_ruta('/reportes') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i> Centro de Reportes
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-actualizar-analitica">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Barra de Filtros Multidimensionales Alina -->
            <div class="card-body bg-light border-bottom py-3">
                <form id="form-filtros-analitica" class="row g-2 align-items-end">
                    <!-- Selector de Rango Flatpickr Alina Obligatorio -->
                    <div class="col-md-5 col-sm-6">
                        <label class="form-label f-s-12 mb-1 f-w-600">Rango de Fechas (Periodo Analítico):</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-2 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-4" id="filtro-rango-analitica"
                                   placeholder="Seleccionar rango..."
                                   data-provider="rangepicker"
                                   data-target-inicio="#filtro-analitica-desde"
                                   data-target-fin="#filtro-analitica-hasta"
                                   value="<?= e($fechaDesde) ?> a <?= e($fechaHasta) ?>"
                                   readonly>
                        </div>
                        <input type="hidden" id="filtro-analitica-desde" name="fecha_desde" value="<?= e($fechaDesde) ?>">
                        <input type="hidden" id="filtro-analitica-hasta" name="fecha_hasta" value="<?= e($fechaHasta) ?>">
                    </div>

                    <!-- Selector de Propiedad Físico -->
                    <div class="col-md-4 col-sm-6">
                        <label for="filtro-analitica-propiedad" class="form-label f-s-12 mb-1 f-w-600">Propiedad:</label>
                        <select class="form-select form-select-sm" id="filtro-analitica-propiedad" name="propiedad_id">
                            <option value="">Todas las propiedades (Consolidado)</option>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= $propiedadId === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['nombre']) ?> (<?= e($p['codigo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="col-md-3 col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1" id="btn-aplicar-filtros-analitica">
                            <i class="fa-solid fa-filter me-1"></i> Filtrar Analítica
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-limpiar-filtros-analitica" title="Restablecer al mes corriente">
                            <i class="fa-solid fa-eraser"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. FILA DE 5 KPIS PRINCIPALES (TARJETAS EQUAL-CARD ALINA) -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- KPI 1: Ocupación Media -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary f-s-12 f-w-600 text-uppercase">Ocupación Media</span>
                <span class="bg-light-primary text-primary p-2 b-r-8">
                    <i class="fa-solid fa-bed f-s-14"></i>
                </span>
            </div>
            <h3 class="f-s-22 f-w-700 mb-1 text-primary" id="kpi-ocupacion">
                <?= number_format($kpis['ocupacion_media_porcentaje'], 2, '.', '') ?>%
            </h3>
            <span class="text-secondary f-s-12" id="kpi-ocupacion-sub">
                <strong id="kpi-noches-ocupadas"><?= (int) $kpis['total_noches_ocupadas'] ?></strong> de <span id="kpi-noches-disponibles"><?= (int) $kpis['total_noches_disponibles'] ?></span> noches vendibles
            </span>
        </div>
    </div>

    <!-- KPI 2: ADR Promedio -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary f-s-12 f-w-600 text-uppercase">ADR Promedio</span>
                <span class="bg-light-success text-success p-2 b-r-8">
                    <i class="fa-solid fa-tag f-s-14"></i>
                </span>
            </div>
            <h3 class="f-s-22 f-w-700 mb-1 text-success" id="kpi-adr">
                S/ <?= e($kpis['adr_promedio']) ?>
            </h3>
            <span class="text-secondary f-s-12" id="kpi-adr-sub">
                Por hab. vendida <span class="badge bg-light text-muted f-s-10 ms-1">Excluye cortesías</span>
            </span>
        </div>
    </div>

    <!-- KPI 3: RevPAR Promedio -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary f-s-12 f-w-600 text-uppercase">RevPAR Promedio</span>
                <span class="bg-light-info text-info p-2 b-r-8">
                    <i class="fa-solid fa-coins f-s-14"></i>
                </span>
            </div>
            <h3 class="f-s-22 f-w-700 mb-1 text-info" id="kpi-revpar">
                S/ <?= e($kpis['revpar_promedio']) ?>
            </h3>
            <span class="text-secondary f-s-12" id="kpi-revpar-sub">
                Por hab. vendible neta (deduce OOO)
            </span>
        </div>
    </div>

    <!-- KPI 4: TRevPAR Promedio -->
    <div class="col-xl-3 col-md-6 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary f-s-12 f-w-600 text-uppercase">TRevPAR Operacional</span>
                <span class="bg-light-warning text-warning p-2 b-r-8">
                    <i class="fa-solid fa-chart-pie f-s-14"></i>
                </span>
            </div>
            <h3 class="f-s-22 f-w-700 mb-1 text-warning" id="kpi-trevpar">
                S/ <?= e($kpis['trevpar_promedio']) ?>
            </h3>
            <span class="text-secondary f-s-12" id="kpi-trevpar-sub">
                Total devengado: <strong id="kpi-total-devengado">S/ <?= e($devengados['total_devengado']) ?></strong>
            </span>
        </div>
    </div>

    <!-- KPI 5: Brecha de Recaudación (Devengado vs Percibido) -->
    <div class="col-xl-3 col-md-6 col-sm-12">
        <div class="card equal-card shadow-sm border-0 b-r-16 p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-secondary f-s-12 f-w-600 text-uppercase">Brecha Recaudación</span>
                <span class="bg-light-secondary text-secondary p-2 b-r-8">
                    <i class="fa-solid fa-scale-balanced f-s-14"></i>
                </span>
            </div>
            <h3 class="f-s-22 f-w-700 mb-1 text-dark" id="kpi-brecha">
                S/ <?= e($percibidos['brecha_recaudacion']) ?>
            </h3>
            <span class="text-secondary f-s-12" id="kpi-brecha-sub">
                Percibido en Tesorería: <strong id="kpi-total-percibido" class="text-success">S/ <?= e($percibidos['total_percibido']) ?></strong>
            </span>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. FILA DE GRÁFICOS APEXCHARTS ALINA -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- Gráfico 1: Serie Temporal de Ocupación, ADR y RevPAR -->
    <div class="col-lg-8 col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-chart-line f-s-16"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-15 f-w-700">Evolución Diaria: Ocupación, ADR & RevPAR</h5>
                        <span class="text-secondary f-s-12">Tendencia diaria continua con marcado de auditoría nocturna</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary f-s-11">
                        <i class="fa-solid fa-circle text-success me-1 f-s-8"></i> Auditado
                    </span>
                    <span class="badge bg-light text-secondary f-s-11">
                        <i class="fa-solid fa-circle text-primary me-1 f-s-8"></i> Proyección
                    </span>
                </div>
            </div>
            <div class="card-body p-3">
                <div id="contenedor-chart-temporal" style="min-height: 330px;">
                    <div id="chart-serie-temporal"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico 2: Cuota de Mercado por Canal de Distribución -->
    <div class="col-lg-4 col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-success text-success p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-chart-pie f-s-16"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-15 f-w-700">Cuota por Canal de Venta</h5>
                        <span class="text-secondary f-s-12">Participación % sobre ingresos comerciales</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
                <div id="contenedor-chart-canales" style="min-height: 330px;">
                    <div id="chart-distribucion-canales"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. TABLA: RENDIMIENTO POR CANAL Y SALVAGUARDA ICAL -->
<!-- ========================================================================= -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-network-wired f-s-18"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-16 f-w-700">Rendimiento por Canal de Distribución</h5>
                        <span class="text-secondary f-s-12">
                            Producción comercial demostrable en PMS/Web vs retiros operativos de feeds iCalendar externos.
                        </span>
                    </div>
                </div>
                <span class="badge bg-light-primary text-primary f-s-12 px-3 py-2 b-r-8" id="badge-total-canales">
                    <?= count($canales) ?> canales detectados
                </span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-rendimiento-canales">
                        <thead class="bg-light text-uppercase f-s-11 text-secondary">
                            <tr>
                                <th scope="col" class="py-3 px-3">Canal</th>
                                <th scope="col" class="py-3 px-3">Clasificación Soberana</th>
                                <th scope="col" class="py-3 px-3 text-center">Reservas / Eventos</th>
                                <th scope="col" class="py-3 px-3 text-center">Noches Vendidas</th>
                                <th scope="col" class="py-3 px-3 text-center">Cuota Noches</th>
                                <th scope="col" class="py-3 px-3 text-end">Ingresos Totales</th>
                                <th scope="col" class="py-3 px-3 text-center">Cuota Ingresos</th>
                                <th scope="col" class="py-3 px-3 text-end">ADR Medio</th>
                                <th scope="col" class="py-3 px-3 text-center">ALOS (Estadía)</th>
                                <th scope="col" class="py-3 px-3 text-center">Lead Time</th>
                                <th scope="col" class="py-3 px-3 text-center">Cancelación</th>
                                <th scope="col" class="py-3 px-3 text-center">Noches iCal</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-canales">
                            <?php if (empty($canales)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-inbox f-s-24 d-block mb-2 text-secondary"></i>
                                        No se registran movimientos ni eventos de distribución en el periodo consultado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($canales as $c): ?>
                                    <tr>
                                        <td class="px-3">
                                            <span class="f-w-700 d-block"><?= e($c->obtenerNombreCanal()) ?></span>
                                            <span class="text-muted f-s-11"><?= e($c->obtenerCodigoCanal()) ?></span>
                                        </td>
                                        <td class="px-3">
                                            <?php if ($c->esProduccionDemostrable()): ?>
                                                <span class="badge bg-light-success text-success f-s-11">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Producción Demostrable
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light-warning text-warning f-s-11">
                                                    <i class="fa-solid fa-calendar-xmark me-1"></i> Bloqueo iCal Externo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= (int) $c->obtenerReservasTotales() ?></strong>
                                            <?php if ($c->obtenerReservasCanceladas() > 0): ?>
                                                <span class="text-danger f-s-11 d-block">(<?= (int) $c->obtenerReservasCanceladas() ?> canc.)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center f-w-600">
                                            <?= $c->esProduccionDemostrable() ? (int) $c->obtenerNochesVendidas() : '—' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $c->esProduccionDemostrable() ? number_format($c->obtenerCuotaNochesPorcentaje(), 2, '.', '') . '%' : '—' ?>
                                        </td>
                                        <td class="text-end f-w-700 <?= $c->esProduccionDemostrable() ? 'text-success' : 'text-muted' ?>">
                                            <?= $c->esProduccionDemostrable() ? 'S/ ' . e($c->obtenerIngresosTotales()) : 'S/ 0.00' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $c->esProduccionDemostrable() ? number_format($c->obtenerCuotaIngresosPorcentaje(), 2, '.', '') . '%' : '—' ?>
                                        </td>
                                        <td class="text-end f-w-600">
                                            <?= $c->esProduccionDemostrable() ? 'S/ ' . e($c->obtenerAdrMedio()) : '—' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $c->esProduccionDemostrable() ? number_format($c->obtenerAlosNoches(), 1, '.', '') . ' n' : '—' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $c->esProduccionDemostrable() ? number_format($c->obtenerLeadTimeDias(), 1, '.', '') . ' d' : '—' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $c->esProduccionDemostrable() ? number_format($c->obtenerTasaCancelacionPorcentaje(), 1, '.', '') . '%' : '—' ?>
                                        </td>
                                        <td class="text-center text-muted">
                                            <?php if ($c->obtenerNochesBloqueadasIcal() > 0): ?>
                                                <span class="badge bg-light-secondary text-dark f-s-11">
                                                    <?= (int) $c->obtenerNochesBloqueadasIcal() ?> noches
                                                </span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Banner de Salvaguarda de Gobernanza iCal -->
            <div class="card-footer bg-white border-top p-3">
                <div class="alert alert-light-warning d-flex align-items-center mb-0 b-r-12 border-warning" id="alerta-ical-salvaguarda">
                    <span class="bg-warning text-white p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-shield-halved f-s-18"></i>
                    </span>
                    <div class="f-s-12 text-secondary">
                        <strong class="text-dark">Salvaguarda Inviolable de Gobernanza (D-110.4):</strong>
                        Los eventos procedentes de feeds iCalendar externos (Airbnb, Booking.com, VRBO, etc.) operan estrictamente como retiros de inventario físico y bloqueos operativos. Al no contar con tarifa ni folio transaccional sustentado en el PMS, <strong>no acreditan ingresos comerciales ni alteran el ADR promedio</strong> (0.00 PEN). Solo las reservas canalizadas mediante el PMS Directo y la Web Oficial WordPress son auditadas como producción demostrable.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. SECCIÓN DUAL CONTABLE: DEVENGADO (ACCRUAL) VS PERCIBIDO (CASH) -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- Tarjeta Izquierda: Ingresos Devengados (Accrual Accounting) -->
    <div class="col-lg-6 col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-file-invoice f-s-16"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-15 f-w-700">Ingresos Devengados (Accrual Basis)</h5>
                        <span class="text-secondary f-s-12">Generación económica efectiva independientemente del cobro</span>
                    </div>
                </div>
                <span class="badge bg-light-primary text-primary f-s-11">Contabilidad Devengada</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover table-sm mb-0 align-middle" id="tabla-devengado-conceptos">
                        <tbody>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Alojamiento Puro (Neto Noche a Noche)</td>
                                <td class="px-3 py-2 text-end f-w-600" id="dev-alojamiento-neto">S/ <?= e($devengados['alojamiento_neto']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Impuestos Devengados de Alojamiento</td>
                                <td class="px-3 py-2 text-end f-w-600" id="dev-alojamiento-impuestos">S/ <?= e($devengados['alojamiento_impuestos']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Servicios Complementarios Contratados</td>
                                <td class="px-3 py-2 text-end f-w-600" id="dev-servicios-extras">S/ <?= e($devengados['servicios_extras']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Rentas de Arrendamiento Devengadas</td>
                                <td class="px-3 py-2 text-end f-w-600" id="dev-arrendamientos">S/ <?= e($devengados['arrendamientos']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Suministros y Consumos Registrados</td>
                                <td class="px-3 py-2 text-end f-w-600" id="dev-suministros">S/ <?= e($devengados['suministros_consumos']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Penalidades Aplicadas</td>
                                <td class="px-3 py-2 text-end f-w-600" id="dev-penalidades">S/ <?= e($devengados['penalidades']) ?></td>
                            </tr>
                            <tr class="bg-light-primary text-primary">
                                <td class="px-3 py-3 f-w-700 f-s-14">TOTAL INGRESOS DEVENGADOS</td>
                                <td class="px-3 py-3 text-end f-w-700 f-s-16 text-primary" id="dev-total-devengado">S/ <?= e($devengados['total_devengado']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta Derecha: Ingresos Percibidos (Cash Accounting / Tesorería) -->
    <div class="col-lg-6 col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="bg-light-success text-success p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-money-bill-transfer f-s-16"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-15 f-w-700">Ingresos Percibidos (Cash Basis)</h5>
                        <span class="text-secondary f-s-12">Fondos reales recaudados y acreditados en tesorería</span>
                    </div>
                </div>
                <span class="badge bg-light-success text-success f-s-11">Flujo de Caja Real</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover table-sm mb-0 align-middle" id="tabla-percibido-medios">
                        <tbody>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Efectivo en Caja Chica</td>
                                <td class="px-3 py-2 text-end f-w-600" id="perc-efectivo">S/ <?= e($percibidos['efectivo_caja']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Transferencias y Depósitos Bancarios</td>
                                <td class="px-3 py-2 text-end f-w-600" id="perc-transferencias">S/ <?= e($percibidos['transferencias_banco']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Tarjetas de Crédito / Débito (POS)</td>
                                <td class="px-3 py-2 text-end f-w-600" id="perc-pos">S/ <?= e($percibidos['tarjetas_pos']) ?></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-secondary">Pasarelas de Pago Netas (Cobrado - Reembolsos)</td>
                                <td class="px-3 py-2 text-end f-w-600" id="perc-pasarelas">S/ <?= e($percibidos['pasarelas_neto']) ?></td>
                            </tr>
                            <tr class="bg-light-success text-success">
                                <td class="px-3 py-2 f-w-700">TOTAL INGRESOS PERCIBIDOS</td>
                                <td class="px-3 py-2 text-end f-w-700 f-s-15 text-success" id="perc-total-percibido">S/ <?= e($percibidos['total_percibido']) ?></td>
                            </tr>
                            <tr class="bg-light-dark text-dark border-top-2">
                                <td class="px-3 py-3 f-w-700 f-s-14">
                                    BRECHA DE RECAUDACIÓN
                                    <span class="text-muted f-s-11 d-block">Diferencial Devengado - Percibido</span>
                                </td>
                                <td class="px-3 py-3 text-end f-w-700 f-s-16 text-dark" id="perc-brecha">S/ <?= e($percibidos['brecha_recaudacion']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 5. TABLA: SERIE TEMPORAL DIARIA COMPLETA -->
<!-- ========================================================================= -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="bg-light-info text-info p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-calendar-days f-s-18"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-16 f-w-700">Serie Temporal Diaria Detallada</h5>
                        <span class="text-secondary f-s-12">
                            Métricas día por día: unidades físicas, OOO, vendibles netas, cortesías aisladas y fuente de auditoría.
                        </span>
                    </div>
                </div>
                <span class="badge bg-light-secondary text-secondary f-s-12 px-3 py-2 b-r-8" id="badge-total-dias">
                    <?= count($serie) ?> días en periodo
                </span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-serie-temporal">
                        <thead class="bg-light text-uppercase f-s-11 text-secondary">
                            <tr>
                                <th scope="col" class="py-3 px-3">Fecha</th>
                                <th scope="col" class="py-3 px-3 text-center">Físicas</th>
                                <th scope="col" class="py-3 px-3 text-center">OOO</th>
                                <th scope="col" class="py-3 px-3 text-center">Vendibles</th>
                                <th scope="col" class="py-3 px-3 text-center">Ocupadas</th>
                                <th scope="col" class="py-3 px-3 text-center">Vendidas</th>
                                <th scope="col" class="py-3 px-3 text-center">Cortesías</th>
                                <th scope="col" class="py-3 px-3 text-center">Ocupación %</th>
                                <th scope="col" class="py-3 px-3 text-end">ADR (PEN)</th>
                                <th scope="col" class="py-3 px-3 text-end">RevPAR (PEN)</th>
                                <th scope="col" class="py-3 px-3 text-end">Ingreso Neto</th>
                                <th scope="col" class="py-3 px-3 text-center">Fuente</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-serie-temporal">
                            <?php if (empty($serie)): ?>
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        No hay datos diarios disponibles para el rango consultado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($serie as $p): ?>
                                    <tr>
                                        <td class="px-3 f-w-600"><?= e($p->obtenerFecha()) ?></td>
                                        <td class="text-center"><?= (int) $p->obtenerUnidadesTotales() ?></td>
                                        <td class="text-center text-muted"><?= (int) $p->obtenerUnidadesOoo() ?></td>
                                        <td class="text-center f-w-600 text-primary"><?= (int) $p->obtenerUnidadesVendibles() ?></td>
                                        <td class="text-center"><?= (int) $p->obtenerUnidadesOcupadas() ?></td>
                                        <td class="text-center f-w-600 text-success"><?= (int) $p->obtenerHabitacionesVendidas() ?></td>
                                        <td class="text-center text-muted"><?= (int) $p->obtenerHabitacionesCortesia() ?></td>
                                        <td class="text-center f-w-700"><?= number_format($p->obtenerOcupacionPorcentaje(), 2, '.', '') ?>%</td>
                                        <td class="text-end f-w-600">S/ <?= e($p->obtenerAdr()) ?></td>
                                        <td class="text-end f-w-600">S/ <?= e($p->obtenerRevpar()) ?></td>
                                        <td class="text-end f-w-700 text-success">S/ <?= e($p->obtenerIngresoAlojamientoNeto()) ?></td>
                                        <td class="text-center">
                                            <?php if ($p->esAuditado()): ?>
                                                <span class="badge bg-light-success text-success f-s-10">
                                                    <i class="fa-solid fa-stamp me-1"></i> Night Audit
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light-secondary text-muted f-s-10">
                                                    <i class="fa-solid fa-clock me-1"></i> En Curso
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts del Módulo: ApexCharts oficial de Alina + Controlador Frontend Propio -->
<script src="<?= url_asset('vendor/apexcharts/apexcharts.min.js') ?>"></script>
<script src="<?= url_asset('js/camargo-reportes-analitica.js') ?>"></script>
