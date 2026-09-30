<?php

declare(strict_types=1);

/**
 * Vista principal de Suministros y Servicios Periódicos — Camargo PMS (SUMINISTROS-1 / D-081).
 *
 * Principios vinculantes:
 * - SUMINISTRO != MEDIDOR != LECTURA != TARIFA != CONSUMO VALORIZADO != CARGO != PAGO.
 * - Desacoplamiento estricto entre ubicación física (unidad) y responsabilidad económica (arrendatario).
 * - Tarifas con vigencia histórica y precedencia UNIDAD > PROPIEDAD > GLOBAL.
 * - Lecturas inmutables append-only y devengo atómico en cuentas folios de arrendamiento.
 * - Geometría nativa Alina: app-form app-icon-form, border-radius 20px, select2 42px, Font Awesome 6.3.0.
 *
 * @var array<string, mixed> $kpis
 * @var array<int, string> $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\Usuario|null $usuario
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Suministros -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-bolt f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Suministros y Servicios Periódicos</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Medidores físicos, lecturas inmutables, tarifas con vigencia histórica e imputación formal a folios de arrendamiento.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (in_array('suministros.lecturas.registrar', $permisos, true)): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-modal-lectura">
                        <i class="fa-solid fa-gauge-high me-1"></i> Registrar Lectura
                    </button>
                    <?php endif; ?>
                    <?php if (in_array('suministros.medidores.gestionar', $permisos, true)): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-abrir-modal-medidor">
                        <i class="fa-solid fa-wrench me-1"></i> Instalar Medidor
                    </button>
                    <?php endif; ?>
                    <?php if (in_array('suministros.liquidar', $permisos, true)): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-liquidar">
                        <i class="fa-solid fa-calculator me-1"></i> Liquidar Suministro
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Suministros -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Suministros Activos</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-suministros-activos"><?= (int) ($kpis['suministros_activos'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Catálogo maestro configurado</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-layer-group f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Medidores Instalados</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1" id="kpi-medidores-activos"><?= (int) ($kpis['medidores_activos'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Aparatos físicos en unidades</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-clock-rotate-left f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Liquidaciones del Mes</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-liquidaciones-mes"><?= (int) ($kpis['liquidaciones_mes'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Períodos cerrados y devengados</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-file-invoice-dollar f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Devengado en Mes</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-monto-devengado">S/ <?= e((string) ($kpis['monto_devengado_pen'] ?? '0.00')) ?></h3>
                                    <span class="f-s-11 text-muted">Cargos generados en folios</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-hand-holding-dollar f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas del Módulo -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-2 border-bottom-0" id="tabs-suministros" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-s-14 f-w-600 py-3" id="tab-liquidaciones-btn" data-bs-toggle="tab" data-bs-target="#tab-liquidaciones" type="button" role="tab">
                            <i class="fa-solid fa-file-invoice-dollar me-2"></i> Liquidaciones a Folio
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-medidores-btn" data-bs-toggle="tab" data-bs-target="#tab-medidores" type="button" role="tab">
                            <i class="fa-solid fa-gauge me-2"></i> Medidores Físicos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-lecturas-btn" data-bs-toggle="tab" data-bs-target="#tab-lecturas" type="button" role="tab">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i> Historial de Lecturas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-tarifas-btn" data-bs-toggle="tab" data-bs-target="#tab-tarifas" type="button" role="tab">
                            <i class="fa-solid fa-tags me-2"></i> Catálogo y Tarifas
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Contenido de las Pestañas -->
<div class="tab-content" id="tabs-suministros-content">

    <!-- 1. TAB: LIQUIDACIONES A FOLIO -->
    <div class="tab-pane fade show active" id="tab-liquidaciones" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-20">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-s-16 f-w-700 mb-0">Consumos Valorizados e Imputación Financiera</h5>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-refrescar-liquidaciones">
                        <i class="fa-solid fa-rotate-right me-1"></i> Refrescar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabla-liquidaciones">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Arrendamiento</th>
                                <th>Unidad / Propiedad</th>
                                <th>Suministro</th>
                                <th>Período</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Total (PEN)</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-liquidaciones">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando liquidaciones...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. TAB: MEDIDORES FÍSICOS -->
    <div class="tab-pane fade" id="tab-medidores" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-20">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-s-16 f-w-700 mb-0">Parque de Medidores en Unidades Físicas</h5>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-refrescar-medidores">
                        <i class="fa-solid fa-rotate-right me-1"></i> Refrescar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabla-medidores">
                        <thead class="table-light">
                            <tr>
                                <th>N° Serie</th>
                                <th>Suministro</th>
                                <th>Unidad</th>
                                <th>Propiedad</th>
                                <th>F. Instalación</th>
                                <th class="text-end">Lec. Inicial</th>
                                <th class="text-center">Rollover</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-medidores">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando medidores...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. TAB: HISTORIAL DE LECTURAS -->
    <div class="tab-pane fade" id="tab-lecturas" role="tabpanel">
        <div class="card border-0 shadow-sm b-r-20">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-5">
                        <label class="form-label f-s-13 f-w-600">Filtrar por Medidor</label>
                        <select class="form-select b-r-20" id="filtro-lecturas-medidor">
                            <option value="">Seleccione un medidor...</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary btn-sm" id="btn-refrescar-lecturas">
                            <i class="fa-solid fa-rotate-right me-1"></i> Cargar Lecturas
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabla-lecturas">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha Lectura</th>
                                <th>Tipo Evento</th>
                                <th class="text-end">Valor Lectura</th>
                                <th>Motivo / Referencia</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-lecturas">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Seleccione un medidor para consultar su bitácora inmutable de lecturas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. TAB: CATÁLOGO Y TARIFAS -->
    <div class="tab-pane fade" id="tab-tarifas" role="tabpanel">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="card border-0 shadow-sm b-r-20 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="f-s-16 f-w-700 mb-0">Suministros Configurados</h5>
                            <?php if (in_array('suministros.gestionar', $permisos, true)): ?>
                            <button class="btn btn-sm btn-outline-primary" id="btn-abrir-modal-nuevo-suministro">
                                <i class="fa-solid fa-plus me-1"></i> Nuevo Suministro
                            </button>
                            <?php endif; ?>
                        </div>
                        <ul class="list-group list-group-flush" id="lista-suministros">
                            <li class="list-group-item py-3 placeholder-glow">
                                <span class="placeholder col-7 mb-1"></span>
                                <span class="placeholder col-4 d-block"></span>
                            </li>
                            <li class="list-group-item py-3 placeholder-glow">
                                <span class="placeholder col-6 mb-1"></span>
                                <span class="placeholder col-3 d-block"></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card border-0 shadow-sm b-r-20 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="f-s-16 f-w-700 mb-0">Historial Jerárquico de Tarifas</h5>
                            <?php if (in_array('suministros.tarifas.gestionar', $permisos, true)): ?>
                            <button class="btn btn-sm btn-outline-success" id="btn-abrir-modal-tarifa">
                                <i class="fa-solid fa-tag me-1"></i> Nueva Tarifa
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tabla-tarifas">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ámbito</th>
                                        <th>Detalle Ámbito</th>
                                        <th class="text-end">Tarifa (PEN)</th>
                                        <th>Vigencia Desde</th>
                                        <th>Vigencia Hasta</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-tarifas">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            Seleccione un suministro de la izquierda para ver sus tarifas.
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

<!-- ========================================================================= -->
<!-- MODALES DEL MÓDULO                                                       -->
<!-- ========================================================================= -->

<!-- Modal: Liquidar Suministro -->
<div class="modal fade" id="modal-liquidar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-calculator text-primary me-2"></i> Computar Liquidación de Suministro
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-liquidar" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Contrato de Arrendamiento *</label>
                            <select class="form-select basic-select2" name="arrendamiento_id" id="liq-arrendamiento-id" required>
                                <option value="">Seleccione contrato...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Suministro a Liquidar *</label>
                            <select class="form-select basic-select2" name="suministro_id" id="liq-suministro-id" required>
                                <option value="">Seleccione suministro...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Período Desde *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="periodo_desde" id="liq-periodo-desde" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Período Hasta *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="periodo_hasta" id="liq-periodo-hasta" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Fecha Vencimiento Cargo</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-check position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="fecha_vencimiento" id="liq-fecha-vencimiento">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0 b-r-8 f-s-12">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        En suministros <strong>MEDIDOS</strong>, el sistema verifica las lecturas físicas en los extremos del período y aplica las tarifas según la jerarquía (Unidad > Propiedad > Global). El cargo devengado se imputa automáticamente a la cuenta folio del contrato de arrendamiento.
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-liquidar">
                        <i class="fa-solid fa-check me-1"></i> Computar y Devengar Cargo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Instalar Medidor -->
<div class="modal fade" id="modal-medidor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-wrench text-info me-2"></i> Instalar Medidor Físico en Unidad
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-medidor" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Suministro *</label>
                            <select class="form-select basic-select2" name="suministro_id" id="med-suministro-id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Unidad Física *</label>
                            <select class="form-select basic-select2" name="unidad_id" id="med-unidad-id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">N° de Serie / Placa *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-barcode position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="numero_serie" required placeholder="Ej. MED-ELEC-401">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Código Interno</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-hashtag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="codigo_interno" placeholder="Ej. ACT-0023">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Marca</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-industry position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="marca" placeholder="Ej. General Electric">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Modelo</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-microchip position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="modelo" placeholder="Ej. I-210+">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Instalación *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-day position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="fecha_instalacion" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Lectura Inicial *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-gauge-high position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.0001" class="form-control ps-5" name="lectura_inicial" value="0.0000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Lectura Máxima (Dial)</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-gauge position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.0001" class="form-control ps-5" name="lectura_maxima" placeholder="Ej. 99999.0000">
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch app-switch d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="checkbox" name="permite_rollover" value="1" id="med-permite-rollover">
                                <label class="form-check-label f-s-13 f-w-600 mb-0" for="med-permite-rollover">
                                    Permite Rollover (Dial cíclico)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-medidor">
                        <i class="fa-solid fa-save me-1"></i> Guardar e Instalar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Registrar Lectura -->
<div class="modal fade" id="modal-lectura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-gauge-high text-primary me-2"></i> Registrar Lectura Física
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-lectura" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Medidor Activo *</label>
                            <select class="form-select basic-select2" name="medidor_id" id="lec-medidor-id" required>
                                <option value="">Seleccione medidor...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha de Lectura *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-day position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="fecha_lectura" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Valor de Lectura *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-gauge-high position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.0001" class="form-control ps-5" name="valor_lectura" required placeholder="0.0000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Tipo de Evento *</label>
                            <select class="form-select basic-select2" name="tipo_evento" required>
                                <option value="PERIODICA">Periódica Regular</option>
                                <option value="CORTE_TARIFARIO">Corte por Cambio de Tarifa</option>
                                <option value="CORTE_CONTRATO">Corte por Entrada/Salida Arrendatario</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Arrendamiento Vinculado</label>
                            <select class="form-select basic-select2" name="arrendamiento_id" id="lec-arrendamiento-id">
                                <option value="">Ninguno / Opcional</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Motivo u Observación</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-comment position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="motivo" placeholder="Ej. Lectura de cierre mensual de consumo">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-lectura">
                        <i class="fa-solid fa-save me-1"></i> Asentar Lectura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Corregir Lectura Auditada -->
<div class="modal fade" id="modal-corregir-lectura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i> Corrección Auditada de Lectura
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-corregir-lectura" class="app-form app-icon-form" novalidate>
                <input type="hidden" name="lectura_id" id="corr-lectura-id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning b-r-8 f-s-12">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Esta acción <strong>NO modifica</strong> el registro original. Deja la lectura previa marcada como <code>CORREGIDA</code> y genera una nueva lectura inmutable vinculada al registro antecesor (D-081).
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Valor Original</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-gauge-simple position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control ps-5 bg-light" id="corr-valor-original" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Nuevo Valor Corregido *</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-gauge-high position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="number" step="0.0001" class="form-control ps-5" name="nuevo_valor" id="corr-nuevo-valor" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Motivo Obligatorio de la Corrección *</label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-pen-to-square position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" name="motivo" id="corr-motivo" rows="3" required placeholder="Explique la causa del error en la lectura original..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-submit-corregir-lectura">
                        <i class="fa-solid fa-check me-1"></i> Asentar Corrección
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Liquidación y Tramos Multitramo -->
<div class="modal fade" id="modal-detalle-liquidacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-detalle-liq-titulo">
                    <i class="fa-solid fa-file-lines text-primary me-2"></i> Detalle de Liquidación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <span class="text-secondary f-s-12">Folio</span>
                        <div class="f-w-700 f-s-15" id="det-liq-folio">-</div>
                    </div>
                    <div class="col-md-3">
                        <span class="text-secondary f-s-12">Modalidad</span>
                        <div class="f-w-600" id="det-liq-modalidad">-</div>
                    </div>
                    <div class="col-md-3">
                        <span class="text-secondary f-s-12">Período</span>
                        <div class="f-w-600" id="det-liq-periodo">-</div>
                    </div>
                    <div class="col-md-3">
                        <span class="text-secondary f-s-12">Total Devengado</span>
                        <div class="f-w-700 f-s-16 text-primary" id="det-liq-total">-</div>
                    </div>
                </div>

                <h6 class="f-s-14 f-w-700 mb-2">Desglose de Tramos y Tarifas Aplicadas</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tramo</th>
                                <th>Intervalo</th>
                                <th class="text-end">Lec. Anterior</th>
                                <th class="text-end">Lec. Actual</th>
                                <th class="text-end">Consumo</th>
                                <th class="text-end">Tarifa</th>
                                <th class="text-end">Total (PEN)</th>
                            </tr>
                        </thead>
                        <tbody id="det-liq-tramos-tbody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nueva Tarifa -->
<div class="modal fade" id="modal-tarifa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-tag text-success me-2"></i> Registrar Tarifa Histórica
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-tarifa" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Suministro *</label>
                            <select class="form-select basic-select2" name="suministro_id" id="tar-suministro-id" required>
                                <option value="">Seleccione suministro...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Ámbito de Aplicación *</label>
                            <select class="form-select basic-select2" name="ambito" id="tar-ambito" required>
                                <option value="GLOBAL">Global (Todas las unidades)</option>
                                <option value="PROPIEDAD">Por Propiedad / Inmueble</option>
                                <option value="UNIDAD">Por Unidad Específica</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="tar-col-propiedad" style="display:none;">
                            <label class="form-label f-s-13 f-w-600">Propiedad</label>
                            <select class="form-select basic-select2" name="propiedad_id" id="tar-propiedad-id">
                                <option value="">Seleccione propiedad...</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="tar-col-unidad" style="display:none;">
                            <label class="form-label f-s-13 f-w-600">Unidad</label>
                            <select class="form-select basic-select2" name="unidad_id" id="tar-unidad-id">
                                <option value="">Seleccione unidad...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Precio Unitario (PEN) *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-money-bill-wave position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.0001" class="form-control ps-5" name="precio_unitario" required placeholder="0.0000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Inicio Vigencia *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-day position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="fecha_inicio" required value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Fin Vigencia</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-check position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" name="fecha_fin" placeholder="Abierta si queda indefinida">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-submit-tarifa">
                        <i class="fa-solid fa-save me-1"></i> Guardar Tarifa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/gestion-suministros.js?v=<?= time() ?>"></script>
