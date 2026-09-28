<?php

declare(strict_types=1);

/**
 * Vista principal del Módulo de Reportes Analíticos y Gerenciales.
 * REPORTES-1 / D-087.
 *
 * @var array<string, bool> $capacidades
 * @var array<int, array<string, mixed>> $propiedades
 * @var string $fechaHoy
 * @var string $fechaPrimerDiaMes
 * @var string $zonaHoraria
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Reportes -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-chart-line f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Centro de Reportes Analíticos y Gerenciales</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Proyección analítica de solo lectura: Reporte Diario Gerencial (MDR), Flujo de Caja y Aging Segregado.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-exportar-csv-activo">
                        <i class="fa-solid fa-file-csv me-1 text-success"></i> Exportar CSV
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-exportar-pdf-activo">
                        <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Exportar PDF
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-actualizar-reporte">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Navegación por Pestañas (Tabs) -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" id="reportesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-w-600" id="tab-diario-btn" data-bs-toggle="tab" data-bs-target="#tab-diario" type="button" role="tab">
                            <i class="fa-solid fa-calendar-day me-1 text-primary"></i> Reporte Diario Gerencial (MDR)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-flujo-caja-btn" data-bs-toggle="tab" data-bs-target="#tab-flujo-caja" type="button" role="tab">
                            <i class="fa-solid fa-money-bill-transfer me-1 text-success"></i> Flujo de Caja Consolidado
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-aging-cxc-btn" data-bs-toggle="tab" data-bs-target="#tab-aging-cxc" type="button" role="tab">
                            <i class="fa-solid fa-hand-holding-dollar me-1 text-info"></i> Aging — Cuentas por Cobrar (CxC)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-aging-cxp-btn" data-bs-toggle="tab" data-bs-target="#tab-aging-cxp" type="button" role="tab">
                            <i class="fa-solid fa-file-invoice-dollar me-1 text-warning"></i> Aging — Cuentas por Pagar (CxP)
                        </button>
                    </li>
                </ul>

                <!-- Contenido de Pestañas -->
                <div class="tab-content p-3" id="reportesTabsContent">

                    <!-- ========================================================================= -->
                    <!-- TAB 1: REPORTE DIARIO GERENCIAL (MDR) -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade show active" id="tab-diario" role="tabpanel">
                        <!-- Filtros MDR -->
                        <div class="row g-2 mb-3 align-items-center bg-light p-2 b-r-8">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Fecha Hotelera de Corte:</label>
                                <input type="date" class="form-control form-control-sm" id="filtro-mdr-fecha" value="<?= e($fechaHoy) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Propiedad:</label>
                                <select class="form-select form-select-sm" id="filtro-mdr-propiedad">
                                    <option value="">Todas las propiedades</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btn-cargar-mdr">
                                    <i class="fa-solid fa-filter me-1"></i> Consultar
                                </button>
                            </div>
                        </div>

                        <!-- Panel de Alerta D-087 ADR/RevPAR -->
                        <div class="alert alert-info py-2 px-3 mb-3 b-r-8 f-s-12 d-flex align-items-center justify-content-between">
                            <div>
                                <i class="fa-solid fa-circle-info me-2"></i>
                                <strong>Gobernanza D-087:</strong> ADR y RevPAR históricos no son reconstructibles con fidelidad debido a que los cargos de alojamiento se registran consolidados por estancia y no por devengo nocturno. Quedan formalmente diferidos a la fase <code>DEVENGO-ALOJAMIENTO-1</code>.
                            </div>
                            <span class="badge bg-secondary">DIFERIDO</span>
                        </div>

                        <!-- KPIs MDR -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Unidades Físicas</span>
                                    <h4 class="mb-0 f-s-18 f-w-700 text-dark" id="mdr-kpi-unidades-total">0</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Fuera de Orden (OOO)</span>
                                    <h4 class="mb-0 f-s-18 f-w-700 text-danger" id="mdr-kpi-unidades-ooo">0</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Vendibles Netas</span>
                                    <h4 class="mb-0 f-s-18 f-w-700 text-primary" id="mdr-kpi-unidades-vendibles">0</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Ocupadas</span>
                                    <h4 class="mb-0 f-s-18 f-w-700 text-success" id="mdr-kpi-unidades-ocupadas">0</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Ocupación Neta</span>
                                    <h4 class="mb-0 f-s-18 f-w-700 text-dark" id="mdr-kpi-ocupacion-porcentaje">0.00%</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">En Casa (Pax)</span>
                                    <h4 class="mb-0 f-s-18 f-w-700 text-info" id="mdr-kpi-huespedes-casa">0</h4>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen Movimientos y Tesorería del Día -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card border shadow-none b-r-8">
                                    <div class="card-header bg-white py-2 f-w-600 f-s-13 border-bottom">
                                        <i class="fa-solid fa-arrow-right-arrow-left me-1 text-primary"></i> Tránsito Operacional de Huéspedes
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Llegadas Previstas / Realizadas:</span>
                                            <span class="f-w-600" id="mdr-llegadas">0 / 0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Salidas Previstas / Realizadas:</span>
                                            <span class="f-w-600" id="mdr-salidas">0 / 0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Stayovers (Pernoctaciones en curso):</span>
                                            <span class="f-w-600" id="mdr-stayovers">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Incidencias Técnicas Abiertas:</span>
                                            <span class="badge bg-warning text-dark" id="mdr-incidencias">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border shadow-none b-r-8">
                                    <div class="card-header bg-white py-2 f-w-600 f-s-13 border-bottom">
                                        <i class="fa-solid fa-coins me-1 text-success"></i> Tesorería y Flujo Monetario del Día
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Ingresos Recaudados:</span>
                                            <span class="f-w-700 text-success" id="mdr-tesoreria-ingresos">S/ 0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Egresos Desembolsados:</span>
                                            <span class="f-w-700 text-danger" id="mdr-tesoreria-egresos">S/ 0.00</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="f-w-600">Saldo Neto Operativo del Día:</span>
                                            <span class="f-w-700 f-s-15 text-primary" id="mdr-tesoreria-neto">S/ 0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle por Habitación / Unidad -->
                        <div class="card border shadow-none b-r-8">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                                <span class="f-w-600 f-s-13">
                                    <i class="fa-solid fa-door-open me-1 text-primary"></i> Estado Detallado por Habitación / Unidad
                                </span>
                                <span class="badge bg-light text-dark border" id="mdr-total-unidades-badge">0 unidades</span>
                            </div>
                            <div class="table-responsive p-0">
                                <table class="table table-hover table-striped align-middle mb-0 f-s-13" id="tabla-mdr-unidades">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre / Tipo</th>
                                            <th class="text-center">Piso</th>
                                            <th>Propiedad</th>
                                            <th class="text-center">Estado Ocupación</th>
                                            <th>Titular / Motivo Bloqueo</th>
                                            <th class="text-center">Limpieza</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-mdr-unidades">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando reporte diario...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB 2: FLUJO DE CAJA CONSOLIDADO (CASH FLOW) -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade" id="tab-flujo-caja" role="tabpanel">
                        <!-- Filtros Flujo de Caja -->
                        <div class="row g-2 mb-3 align-items-center bg-light p-2 b-r-8">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Fecha Inicial:</label>
                                <input type="date" class="form-control form-control-sm" id="filtro-fc-desde" value="<?= e($fechaPrimerDiaMes) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Fecha Final:</label>
                                <input type="date" class="form-control form-control-sm" id="filtro-fc-hasta" value="<?= e($fechaHoy) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Propiedad:</label>
                                <select class="form-select form-select-sm" id="filtro-fc-propiedad">
                                    <option value="">Todas las propiedades</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btn-cargar-fc">
                                    <i class="fa-solid fa-filter me-1"></i> Consultar
                                </button>
                            </div>
                        </div>

                        <!-- Matriz de Cuadre Algebraico de Tesorería -->
                        <div class="card border shadow-none b-r-8 mb-4">
                            <div class="card-header bg-white py-2 f-w-600 f-s-13 border-bottom d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-scale-balanced me-1 text-primary"></i> Cuadre Patrimonial de Tesorería</span>
                                <span class="badge bg-success" id="fc-estado-cuadre">CUADRADO EXACTO</span>
                            </div>
                            <div class="table-responsive p-0">
                                <table class="table table-bordered align-middle mb-0 f-s-13 text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Medio de Tesorería</th>
                                            <th class="text-end">Saldo Inicial</th>
                                            <th class="text-end text-success">(+) Ingresos Recaudados</th>
                                            <th class="text-end text-danger">(-) Egresos Desembolsados</th>
                                            <th class="text-end">(=) Saldo Final</th>
                                            <th>Cuadre Algebraico</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-start f-w-600"><i class="fa-solid fa-cash-register me-1 text-muted"></i> EFECTIVO (Cajas Físicas)</td>
                                            <td class="text-end" id="fc-efe-inicial">S/ 0.00</td>
                                            <td class="text-end text-success" id="fc-efe-ingresos">S/ 0.00</td>
                                            <td class="text-end text-danger" id="fc-efe-egresos">S/ 0.00</td>
                                            <td class="text-end f-w-700" id="fc-efe-final">S/ 0.00</td>
                                            <td><span class="badge bg-light-success text-success" id="fc-efe-cuadre">EXACTO</span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-start f-w-600"><i class="fa-solid fa-building-columns me-1 text-muted"></i> BANCOS (Cuentas Bancarias)</td>
                                            <td class="text-end" id="fc-ban-inicial">S/ 0.00</td>
                                            <td class="text-end text-success" id="fc-ban-ingresos">S/ 0.00</td>
                                            <td class="text-end text-danger" id="fc-ban-egresos">S/ 0.00</td>
                                            <td class="text-end f-w-700" id="fc-ban-final">S/ 0.00</td>
                                            <td><span class="badge bg-light-success text-success" id="fc-ban-cuadre">EXACTO</span></td>
                                        </tr>
                                        <tr class="table-light f-w-700">
                                            <td class="text-start"><i class="fa-solid fa-vault me-1 text-primary"></i> CONSOLIDADO PATRIMONIAL</td>
                                            <td class="text-end" id="fc-con-inicial">S/ 0.00</td>
                                            <td class="text-end text-success" id="fc-con-ingresos">S/ 0.00</td>
                                            <td class="text-end text-danger" id="fc-con-egresos">S/ 0.00</td>
                                            <td class="text-end text-primary f-s-14" id="fc-con-final">S/ 0.00</td>
                                            <td><span class="badge bg-success" id="fc-con-cuadre">INMUTABLE</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Detalle de Movimientos -->
                        <div class="card border shadow-none b-r-8">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                                <span class="f-w-600 f-s-13">
                                    <i class="fa-solid fa-list me-1 text-primary"></i> Movimientos Monetarios del Período
                                </span>
                                <span class="badge bg-light text-dark border" id="fc-total-movs-badge">0 movimientos</span>
                            </div>
                            <div class="table-responsive p-0">
                                <table class="table table-hover table-striped align-middle mb-0 f-s-13">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Medio / Origen</th>
                                            <th>Tipo Movimiento</th>
                                            <th class="text-center">Sentido</th>
                                            <th class="text-end">Monto (PEN)</th>
                                            <th>Concepto</th>
                                            <th>Fecha / Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-fc-movimientos">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                Seleccione el intervalo de fechas y presione Consultar.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB 3: AGING — CUENTAS POR COBRAR (CxC) -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade" id="tab-aging-cxc" role="tabpanel">
                        <!-- Filtros Aging CxC -->
                        <div class="row g-2 mb-3 align-items-center bg-light p-2 b-r-8">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Fecha de Corte (*as-of date*):</label>
                                <input type="date" class="form-control form-control-sm" id="filtro-cxc-corte" value="<?= e($fechaHoy) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Propiedad:</label>
                                <select class="form-select form-select-sm" id="filtro-cxc-propiedad">
                                    <option value="">Todas las propiedades</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btn-cargar-cxc">
                                    <i class="fa-solid fa-filter me-1"></i> Consultar
                                </button>
                            </div>
                        </div>

                        <!-- Buckets de Morosidad CxC -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Por Vencer</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-success" id="cxc-bucket-por-vencer">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">1 – 30 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-info" id="cxc-bucket-1-30">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">31 – 60 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-warning" id="cxc-bucket-31-60">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">61 – 90 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-danger" id="cxc-bucket-61-90">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">&gt; 90 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-dark" id="cxc-bucket-mas-90">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-light shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase f-w-700">Total CxC</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-primary" id="cxc-monto-total">S/ 0.00</h4>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle Partidas CxC -->
                        <div class="card border shadow-none b-r-8">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                                <span class="f-w-600 f-s-13">
                                    <i class="fa-solid fa-users me-1 text-primary"></i> Partidas Exigibles a Clientes e Inquilinos
                                </span>
                                <span class="badge bg-light text-dark border" id="cxc-total-partidas-badge">0 partidas</span>
                            </div>
                            <div class="table-responsive p-0">
                                <table class="table table-hover table-striped align-middle mb-0 f-s-13">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Origen</th>
                                            <th>Referencia / Doc</th>
                                            <th>Titular / Cliente</th>
                                            <th>Unidad / Folio</th>
                                            <th>Vencimiento</th>
                                            <th class="text-end">Monto Original</th>
                                            <th class="text-end">Amortizado</th>
                                            <th class="text-end">Saldo Pendiente</th>
                                            <th class="text-center">Atraso</th>
                                            <th class="text-center">Bucket</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-cxc-partidas">
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                Presione Consultar para cargar la antigüedad de cuentas por cobrar.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB 4: AGING — CUENTAS POR PAGAR (CxP) -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade" id="tab-aging-cxp" role="tabpanel">
                        <!-- Filtros Aging CxP -->
                        <div class="row g-2 mb-3 align-items-center bg-light p-2 b-r-8">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Fecha de Corte (*as-of date*):</label>
                                <input type="date" class="form-control form-control-sm" id="filtro-cxp-corte" value="<?= e($fechaHoy) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label f-s-12 mb-1 f-w-600">Propiedad:</label>
                                <select class="form-select form-select-sm" id="filtro-cxp-propiedad">
                                    <option value="">Todas las propiedades</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="btn-cargar-cxp">
                                    <i class="fa-solid fa-filter me-1"></i> Consultar
                                </button>
                            </div>
                        </div>

                        <!-- Buckets de Morosidad CxP -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">Por Vencer</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-success" id="cxp-bucket-por-vencer">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">1 – 30 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-info" id="cxp-bucket-1-30">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">31 – 60 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-warning" id="cxp-bucket-31-60">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">61 – 90 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-danger" id="cxp-bucket-61-90">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-white shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase">&gt; 90 Días</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-dark" id="cxp-bucket-mas-90">S/ 0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card border p-2 text-center bg-light shadow-none b-r-8">
                                    <span class="text-muted f-s-11 text-uppercase f-w-700">Total CxP</span>
                                    <h4 class="mb-0 f-s-16 f-w-700 text-danger" id="cxp-monto-total">S/ 0.00</h4>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle Partidas CxP -->
                        <div class="card border shadow-none b-r-8">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                                <span class="f-w-600 f-s-13">
                                    <i class="fa-solid fa-truck me-1 text-primary"></i> Obligaciones Pendientes con Proveedores y Acreedores
                                </span>
                                <span class="badge bg-light text-dark border" id="cxp-total-partidas-badge">0 partidas</span>
                            </div>
                            <div class="table-responsive p-0">
                                <table class="table table-hover table-striped align-middle mb-0 f-s-13">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Origen</th>
                                            <th>Código / Comprobante</th>
                                            <th>Proveedor / Acreedor</th>
                                            <th>Concepto</th>
                                            <th>Vencimiento</th>
                                            <th class="text-end">Monto Total</th>
                                            <th class="text-end">Amortizado</th>
                                            <th class="text-end">Saldo Pendiente</th>
                                            <th class="text-center">Atraso</th>
                                            <th class="text-center">Bucket</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-cxp-partidas">
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                Presione Consultar para cargar la antigüedad de cuentas por pagar.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lógica JavaScript del Módulo de Reportes -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Determinar pestaña activa
    function obtenerTipoReporteActivo() {
        const activeTab = document.querySelector('#reportesTabs .nav-link.active');
        if (activeTab.id === 'tab-diario-btn') return 'DIARIO';
        if (activeTab.id === 'tab-flujo-caja-btn') return 'FLUJO_CAJA';
        if (activeTab.id === 'tab-aging-cxc-btn') return 'AGING_CXC';
        if (activeTab.id === 'tab-aging-cxp-btn') return 'AGING_CXP';
        return 'DIARIO';
    }

    // Exportar CSV
    document.getElementById('btn-exportar-csv-activo')?.addEventListener('click', function () {
        const tipo = obtenerTipoReporteActivo();
        let params = new URLSearchParams({ tipo: tipo });

        if (tipo === 'DIARIO') {
            params.append('fecha', document.getElementById('filtro-mdr-fecha').value);
            params.append('propiedad_id', document.getElementById('filtro-mdr-propiedad').value);
        } else if (tipo === 'FLUJO_CAJA') {
            params.append('fecha_desde', document.getElementById('filtro-fc-desde').value);
            params.append('fecha_hasta', document.getElementById('filtro-fc-hasta').value);
            params.append('propiedad_id', document.getElementById('filtro-fc-propiedad').value);
        } else if (tipo === 'AGING_CXC') {
            params.append('fecha_corte', document.getElementById('filtro-cxc-corte').value);
            params.append('propiedad_id', document.getElementById('filtro-cxc-propiedad').value);
        } else if (tipo === 'AGING_CXP') {
            params.append('fecha_corte', document.getElementById('filtro-cxp-corte').value);
            params.append('propiedad_id', document.getElementById('filtro-cxp-propiedad').value);
        }

        window.open('/reportes/exportar/csv?' + params.toString(), '_blank');
    });

    // Exportar PDF
    document.getElementById('btn-exportar-pdf-activo')?.addEventListener('click', function () {
        const tipo = obtenerTipoReporteActivo();
        let params = new URLSearchParams({ tipo: tipo, inline: '1' });

        if (tipo === 'DIARIO') {
            params.append('fecha', document.getElementById('filtro-mdr-fecha').value);
            params.append('propiedad_id', document.getElementById('filtro-mdr-propiedad').value);
        } else if (tipo === 'FLUJO_CAJA') {
            params.append('fecha_desde', document.getElementById('filtro-fc-desde').value);
            params.append('fecha_hasta', document.getElementById('filtro-fc-hasta').value);
            params.append('propiedad_id', document.getElementById('filtro-fc-propiedad').value);
        } else if (tipo === 'AGING_CXC') {
            params.append('fecha_corte', document.getElementById('filtro-cxc-corte').value);
            params.append('propiedad_id', document.getElementById('filtro-cxc-propiedad').value);
        } else if (tipo === 'AGING_CXP') {
            params.append('fecha_corte', document.getElementById('filtro-cxp-corte').value);
            params.append('propiedad_id', document.getElementById('filtro-cxp-propiedad').value);
        }

        window.open('/reportes/exportar/pdf?' + params.toString(), '_blank');
    });

    // 1. Cargar MDR
    function cargarMDR() {
        const fecha = document.getElementById('filtro-mdr-fecha').value;
        const propId = document.getElementById('filtro-mdr-propiedad').value;
        const tbody = document.getElementById('tbody-mdr-unidades');

        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando reporte diario...</td></tr>';

        const params = new URLSearchParams({ fecha: fecha });
        if (propId) params.append('propiedad_id', propId);

        fetch('/api/reportes/diario?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${data.mensaje || 'Error al cargar reporte.'}</td></tr>`;
                    return;
                }
                const d = data.datos;
                const op = d.operacion_hotelera;
                const tes = d.tesoreria;

                // Actualizar KPIs
                document.getElementById('mdr-kpi-unidades-total').textContent = op.unidades_totales;
                document.getElementById('mdr-kpi-unidades-ooo').textContent = op.unidades_ooo;
                document.getElementById('mdr-kpi-unidades-vendibles').textContent = op.unidades_vendibles;
                document.getElementById('mdr-kpi-unidades-ocupadas').textContent = op.unidades_ocupadas;
                document.getElementById('mdr-kpi-ocupacion-porcentaje').textContent = op.ocupacion_neta_porcentaje + '%';
                document.getElementById('mdr-kpi-huespedes-casa').textContent = op.huespedes_en_casa;

                // Tránsito y Tesorería
                document.getElementById('mdr-llegadas').textContent = `${op.llegadas_previstas} prev. / ${op.llegadas_realizadas} efect.`;
                document.getElementById('mdr-salidas').textContent = `${op.salidas_previstas} prev. / ${op.salidas_realizadas} efect.`;
                document.getElementById('mdr-stayovers').textContent = op.stayovers;
                document.getElementById('mdr-incidencias').textContent = d.control_operativo.incidencias_abiertas_total;

                document.getElementById('mdr-tesoreria-ingresos').textContent = 'S/ ' + tes.total_ingresos;
                document.getElementById('mdr-tesoreria-egresos').textContent = 'S/ ' + tes.total_egresos;
                document.getElementById('mdr-tesoreria-neto').textContent = 'S/ ' + tes.saldo_neto_operativo;

                document.getElementById('mdr-total-unidades-badge').textContent = d.detalle_unidades.length + ' unidades';

                // Renderizar tabla
                if (!d.detalle_unidades.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron unidades registradas.</td></tr>';
                    return;
                }

                let html = '';
                d.detalle_unidades.forEach(u => {
                    let badgeOcup = 'bg-secondary';
                    if (u.estado_ocupacion === 'DISPONIBLE') badgeOcup = 'bg-light-success text-success';
                    else if (u.estado_ocupacion === 'BLOQUEADA_OOO') badgeOcup = 'bg-light-danger text-danger';
                    else badgeOcup = 'bg-light-primary text-primary';

                    let badgeLimp = 'bg-light text-dark';
                    if (u.estado_limpieza === 'LIMPIA' || u.estado_limpieza === 'INSPECCIONADA') badgeLimp = 'bg-light-success text-success';
                    else if (u.estado_limpieza === 'SUCIA') badgeLimp = 'bg-light-danger text-danger';
                    else if (u.estado_limpieza === 'EN_LIMPIEZA') badgeLimp = 'bg-light-warning text-warning';

                    html += `<tr>
                        <td class="f-w-600">${u.codigo}</td>
                        <td>${u.nombre} <span class="text-muted f-s-11">(${u.tipo_unidad})</span></td>
                        <td class="text-center">${u.piso_nivel}</td>
                        <td>${u.propiedad_nombre}</td>
                        <td class="text-center"><span class="badge ${badgeOcup}">${u.estado_ocupacion}</span></td>
                        <td>${u.titular || u.motivo_bloqueo || '-'}</td>
                        <td class="text-center"><span class="badge ${badgeLimp}">${u.estado_limpieza}</span></td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error de comunicación: ${err.message}</td></tr>`;
            });
    }

    // 2. Cargar Flujo de Caja
    function cargarFlujoCaja() {
        const desde = document.getElementById('filtro-fc-desde').value;
        const hasta = document.getElementById('filtro-fc-hasta').value;
        const propId = document.getElementById('filtro-fc-propiedad').value;
        const tbody = document.getElementById('tbody-fc-movimientos');

        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Calculando flujo de caja consolidado...</td></tr>';

        const params = new URLSearchParams({ fecha_desde: desde, fecha_hasta: hasta });
        if (propId) params.append('propiedad_id', propId);

        fetch('/api/reportes/flujo-caja?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${data.mensaje || 'Error al cargar flujo de caja.'}</td></tr>`;
                    return;
                }
                const d = data.datos;
                const efe = d.totales_efectivo;
                const ban = d.totales_banco;
                const con = d.totales_consolidado;

                // Matriz Cuadre
                document.getElementById('fc-efe-inicial').textContent = 'S/ ' + efe.saldo_inicial;
                document.getElementById('fc-efe-ingresos').textContent = 'S/ ' + efe.ingresos;
                document.getElementById('fc-efe-egresos').textContent = 'S/ ' + efe.egresos;
                document.getElementById('fc-efe-final').textContent = 'S/ ' + efe.saldo_final;

                document.getElementById('fc-ban-inicial').textContent = 'S/ ' + ban.saldo_inicial;
                document.getElementById('fc-ban-ingresos').textContent = 'S/ ' + ban.ingresos;
                document.getElementById('fc-ban-egresos').textContent = 'S/ ' + ban.egresos;
                document.getElementById('fc-ban-final').textContent = 'S/ ' + ban.saldo_final;

                document.getElementById('fc-con-inicial').textContent = 'S/ ' + con.saldo_inicial;
                document.getElementById('fc-con-ingresos').textContent = 'S/ ' + con.ingresos;
                document.getElementById('fc-con-egresos').textContent = 'S/ ' + con.egresos;
                document.getElementById('fc-con-final').textContent = 'S/ ' + con.saldo_final;

                document.getElementById('fc-total-movs-badge').textContent = d.movimientos_detalle.length + ' movimientos';

                // Movimientos
                if (!d.movimientos_detalle.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se registraron movimientos en el período.</td></tr>';
                    return;
                }

                let html = '';
                d.movimientos_detalle.forEach(m => {
                    const badgeClass = m.sentido === 'INGRESO' ? 'bg-light-success text-success' : 'bg-light-danger text-danger';
                    html += `<tr>
                        <td class="text-muted f-s-11">#${m.id}</td>
                        <td><span class="f-w-600">${m.medio}</span> <span class="text-muted f-s-11">(${m.origen})</span></td>
                        <td>${m.tipo_movimiento}</td>
                        <td class="text-center"><span class="badge ${badgeClass}">${m.sentido}</span></td>
                        <td class="text-end f-w-700">S/ ${m.monto}</td>
                        <td>${m.concepto || '-'}</td>
                        <td class="text-muted f-s-11">${m.fecha_hora}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error de comunicación: ${err.message}</td></tr>`;
            });
    }

    // 3. Cargar Aging CxC
    function cargarAgingCxC() {
        const corte = document.getElementById('filtro-cxc-corte').value;
        const propId = document.getElementById('filtro-cxc-propiedad').value;
        const tbody = document.getElementById('tbody-cxc-partidas');

        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando antigüedad de CxC...</td></tr>';

        const params = new URLSearchParams({ fecha_corte: corte });
        if (propId) params.append('propiedad_id', propId);

        fetch('/api/reportes/aging-cxc?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${data.mensaje || 'Error al cargar aging CxC.'}</td></tr>`;
                    return;
                }
                const d = data.datos;
                const b = d.totales_por_bucket;

                document.getElementById('cxc-bucket-por-vencer').textContent = 'S/ ' + b.POR_VENCER;
                document.getElementById('cxc-bucket-1-30').textContent = 'S/ ' + b['1_30'];
                document.getElementById('cxc-bucket-31-60').textContent = 'S/ ' + b['31_60'];
                document.getElementById('cxc-bucket-61-90').textContent = 'S/ ' + b['61_90'];
                document.getElementById('cxc-bucket-mas-90').textContent = 'S/ ' + b.MAS_90;
                document.getElementById('cxc-monto-total').textContent = 'S/ ' + d.monto_total;

                document.getElementById('cxc-total-partidas-badge').textContent = d.partidas.length + ' partidas';

                if (!d.partidas.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No existen cuentas por cobrar pendientes a la fecha de corte.</td></tr>';
                    return;
                }

                let html = '';
                d.partidas.forEach(p => {
                    let badgeClass = 'bg-light-success text-success';
                    if (p.bucket === '1_30') badgeClass = 'bg-light-info text-info';
                    else if (p.bucket === '31_60') badgeClass = 'bg-light-warning text-warning';
                    else if (p.bucket === '61_90' || p.bucket === 'MAS_90') badgeClass = 'bg-light-danger text-danger';

                    html += `<tr>
                        <td><span class="badge bg-light text-dark">${p.origen}</span></td>
                        <td class="f-w-600">${p.referencia_codigo} <span class="text-muted f-s-11">(${p.documento_codigo})</span></td>
                        <td>${p.titular_nombre}</td>
                        <td>${p.unidad_codigo}</td>
                        <td class="text-muted">${p.fecha_vencimiento}</td>
                        <td class="text-end">S/ ${p.monto_original}</td>
                        <td class="text-end text-success">S/ ${p.monto_amortizado}</td>
                        <td class="text-end f-w-700 text-danger">S/ ${p.saldo_pendiente}</td>
                        <td class="text-center">${p.dias_vencido} d</td>
                        <td class="text-center"><span class="badge ${badgeClass}">${p.bucket}</span></td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error de comunicación: ${err.message}</td></tr>`;
            });
    }

    // 4. Cargar Aging CxP
    function cargarAgingCxP() {
        const corte = document.getElementById('filtro-cxp-corte').value;
        const propId = document.getElementById('filtro-cxp-propiedad').value;
        const tbody = document.getElementById('tbody-cxp-partidas');

        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando antigüedad de CxP...</td></tr>';

        const params = new URLSearchParams({ fecha_corte: corte });
        if (propId) params.append('propiedad_id', propId);

        fetch('/api/reportes/aging-cxp?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${data.mensaje || 'Error al cargar aging CxP.'}</td></tr>`;
                    return;
                }
                const d = data.datos;
                const b = d.totales_por_bucket;

                document.getElementById('cxp-bucket-por-vencer').textContent = 'S/ ' + b.POR_VENCER;
                document.getElementById('cxp-bucket-1-30').textContent = 'S/ ' + b['1_30'];
                document.getElementById('cxp-bucket-31-60').textContent = 'S/ ' + b['31_60'];
                document.getElementById('cxp-bucket-61-90').textContent = 'S/ ' + b['61_90'];
                document.getElementById('cxp-bucket-mas-90').textContent = 'S/ ' + b.MAS_90;
                document.getElementById('cxp-monto-total').textContent = 'S/ ' + d.monto_total;

                document.getElementById('cxp-total-partidas-badge').textContent = d.partidas.length + ' partidas';

                if (!d.partidas.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No existen cuentas por pagar pendientes a la fecha de corte.</td></tr>';
                    return;
                }

                let html = '';
                d.partidas.forEach(p => {
                    let badgeClass = 'bg-light-success text-success';
                    if (p.bucket === '1_30') badgeClass = 'bg-light-info text-info';
                    else if (p.bucket === '31_60') badgeClass = 'bg-light-warning text-warning';
                    else if (p.bucket === '61_90' || p.bucket === 'MAS_90') badgeClass = 'bg-light-danger text-danger';

                    html += `<tr>
                        <td><span class="badge bg-light text-dark">${p.origen}</span></td>
                        <td class="f-w-600">${p.referencia_codigo} <span class="text-muted f-s-11">(${p.documento_codigo})</span></td>
                        <td>${p.acreedor_nombre}</td>
                        <td>${p.concepto || '-'}</td>
                        <td class="text-muted">${p.fecha_vencimiento}</td>
                        <td class="text-end">S/ ${p.monto_original}</td>
                        <td class="text-end text-success">S/ ${p.monto_amortizado}</td>
                        <td class="text-end f-w-700 text-danger">S/ ${p.saldo_pendiente}</td>
                        <td class="text-center">${p.dias_vencido} d</td>
                        <td class="text-center"><span class="badge ${badgeClass}">${p.bucket}</span></td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error de comunicación: ${err.message}</td></tr>`;
            });
    }

    // Eventos de botones
    document.getElementById('btn-cargar-mdr')?.addEventListener('click', cargarMDR);
    document.getElementById('btn-cargar-fc')?.addEventListener('click', cargarFlujoCaja);
    document.getElementById('btn-cargar-cxc')?.addEventListener('click', cargarAgingCxC);
    document.getElementById('btn-cargar-cxp')?.addEventListener('click', cargarAgingCxP);

    document.getElementById('btn-actualizar-reporte')?.addEventListener('click', function () {
        const tipo = obtenerTipoReporteActivo();
        if (tipo === 'DIARIO') cargarMDR();
        else if (tipo === 'FLUJO_CAJA') cargarFlujoCaja();
        else if (tipo === 'AGING_CXC') cargarAgingCxC();
        else if (tipo === 'AGING_CXP') cargarAgingCxP();
    });

    // Cargar reporte de la pestaña activa al cambiar de tab
    document.querySelectorAll('#reportesTabs button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (e) {
            const id = e.target.id;
            if (id === 'tab-diario-btn') cargarMDR();
            else if (id === 'tab-flujo-caja-btn') cargarFlujoCaja();
            else if (id === 'tab-aging-cxc-btn') cargarAgingCxC();
            else if (id === 'tab-aging-cxp-btn') cargarAgingCxP();
        });
    });

    // Inicializar primera pestaña
    cargarMDR();
});
</script>
