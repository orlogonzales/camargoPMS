<?php

declare(strict_types=1);

/**
 * Vista principal de Abastecimiento, Compras y Cuentas por Pagar — Camargo PMS (COMPRAS-1 / D-080).
 *
 * Principios vinculantes:
 * - SOLICITUD != ORDEN != RECEPCION/CONFORMIDAD != COMPROBANTE != CUENTA POR PAGAR != PAGO.
 * - ORDEN != MOVIMIENTO DE INVENTARIO != GASTO != PAGO.
 * - Líneas fuertemente tipadas: BIEN (Kardex) vs SERVICIO (Cero Kardex, Acta de Conformidad).
 * - Moneda funcional en PEN.
 * - 3-Way Matching: CONFORME, CON_DIFERENCIA, OBSERVADO.
 * - Geometría nativa Alina: app-form app-icon-form, border-radius 20px, select2 42px, Font Awesome 6.3.0.
 *
 * @var array<string, mixed> $kpis
 * @var array<int, string> $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\Usuario|null $usuario
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Compras -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-cart-flatbed f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Abastecimiento, Compras y Cuentas por Pagar</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Órdenes de compra oficiales, recepciones en almacén, conformidad de servicios, 3-way matching y pasivos comerciales.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (in_array('compras.solicitudes.crear', $permisos, true)): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-modal-solicitud">
                        <i class="fa-solid fa-clipboard-list me-1"></i> Nueva Solicitud
                    </button>
                    <?php endif; ?>
                    <?php if (in_array('compras.ordenes.crear', $permisos, true)): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-orden">
                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Formular Orden
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Compras y Cuentas por Pagar -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Órdenes Aprobadas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-ordenes-activas"><?= (int) ($kpis['ordenes_activas'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Condiciones congeladas</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-file-signature f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Pendientes de Recepción</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-warning mt-1" id="kpi-por-recibir"><?= (int) ($kpis['por_recibir'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Bienes por ingresar a almacén</span>
                                </div>
                                <div class="bg-light-warning text-warning p-3 b-r-8">
                                    <i class="fa-solid fa-boxes-packing f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Deuda CxP Pendiente</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-deuda-total">S/ <?= e((string) ($kpis['deuda_total_pen'] ?? '0.00')) ?></h3>
                                    <span class="f-s-11 text-muted"><?= (int) ($kpis['cxp_pendientes'] ?? 0) ?> comprobantes por liquidar</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-money-bill-wave f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Requerimientos Internos</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1" id="kpi-solicitudes-pendientes"><?= (int) ($kpis['solicitudes_pendientes'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Solicitudes pendientes</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-list-check f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas del Módulo -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-2 border-bottom-0" id="tabs-compras" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-s-14 f-w-600 py-3" id="tab-ordenes-btn" data-bs-toggle="tab" data-bs-target="#tab-ordenes" type="button" role="tab">
                            <i class="fa-solid fa-file-invoice me-2"></i> Órdenes de Compra
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-solicitudes-btn" data-bs-toggle="tab" data-bs-target="#tab-solicitudes" type="button" role="tab">
                            <i class="fa-solid fa-clipboard-question me-2"></i> Solicitudes Internas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-recepciones-btn" data-bs-toggle="tab" data-bs-target="#tab-recepciones" type="button" role="tab">
                            <i class="fa-solid fa-truck-ramp-box me-2"></i> Recepciones & Conformidades
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-comprobantes-btn" data-bs-toggle="tab" data-bs-target="#tab-comprobantes" type="button" role="tab">
                            <i class="fa-solid fa-receipt me-2"></i> Comprobantes & 3-Way Matching
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-cxp-btn" data-bs-toggle="tab" data-bs-target="#tab-cxp" type="button" role="tab">
                            <i class="fa-solid fa-scale-balanced me-2"></i> Cuentas por Pagar & Pagos
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="tabs-compras-contenido">

                    <!-- PESTAÑA 1: ÓRDENES DE COMPRA -->
                    <div class="tab-pane fade show active" id="tab-ordenes" role="tabpanel">
                        <div class="row mb-3 g-2 align-items-center">
                            <div class="col-md-3 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-oc-termino" placeholder="Buscar por código OC o proveedor..." style="border-radius: 20px;">
                            </div>
                            <div class="col-md-2 col-6">
                                <select class="form-select form-select-sm" id="filtro-oc-comercial" style="border-radius: 20px;">
                                    <option value="">Estado Comercial</option>
                                    <option value="BORRADOR">Borrador</option>
                                    <option value="APROBADA">Aprobada</option>
                                    <option value="CERRADA">Cerrada</option>
                                    <option value="CANCELADA">Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-6">
                                <select class="form-select form-select-sm" id="filtro-oc-recepcion" style="border-radius: 20px;">
                                    <option value="">Estado Recepción</option>
                                    <option value="SIN_RECEPCION">Sin Recepción</option>
                                    <option value="RECEPCION_PARCIAL">Parcial</option>
                                    <option value="RECEPCION_TOTAL">Total</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-6">
                                <select class="form-select form-select-sm" id="filtro-oc-pago" style="border-radius: 20px;">
                                    <option value="">Estado Pago</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                    <option value="PAGADO_PARCIAL">Parcial</option>
                                    <option value="PAGADO_TOTAL">Total</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6 text-md-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-ordenes">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-ordenes">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Código OC</th>
                                        <th class="f-s-12 text-uppercase">Proveedor</th>
                                        <th class="f-s-12 text-uppercase">Fecha Emisión</th>
                                        <th class="f-s-12 text-uppercase text-end">Total (PEN)</th>
                                        <th class="f-s-12 text-uppercase text-center">Comercial</th>
                                        <th class="f-s-12 text-uppercase text-center">Recepción</th>
                                        <th class="f-s-12 text-uppercase text-center">Facturación</th>
                                        <th class="f-s-12 text-uppercase text-center">Pago</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">Cargando órdenes de compra...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: SOLICITUDES DE COMPRA -->
                    <div class="tab-pane fade" id="tab-solicitudes" role="tabpanel">
                        <div class="row mb-3 g-2 align-items-center">
                            <div class="col-md-4 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-sol-area" placeholder="Filtrar por departamento o área..." style="border-radius: 20px;">
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select form-select-sm" id="filtro-sol-estado" style="border-radius: 20px;">
                                    <option value="">Todos los estados</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                    <option value="APROBADA">Aprobada</option>
                                    <option value="RECHAZADA">Rechazada</option>
                                </select>
                            </div>
                            <div class="col-md-5 col-6 text-md-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-solicitudes">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-solicitudes">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Código</th>
                                        <th class="f-s-12 text-uppercase">Área Solicitante</th>
                                        <th class="f-s-12 text-uppercase">Fecha Límite</th>
                                        <th class="f-s-12 text-uppercase">Justificación</th>
                                        <th class="f-s-12 text-uppercase text-center">Estado</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Cargando solicitudes...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: RECEPCIONES & CONFORMIDADES -->
                    <div class="tab-pane fade" id="tab-recepciones" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-md-6 col-12">
                                <h5 class="f-s-15 f-w-700 mb-1">Recepciones Físicas en Almacén (Kardex)</h5>
                                <p class="f-s-12 text-muted mb-0">Solo las unidades aceptadas incrementan existencias y registran ENTRADA_COMPRA.</p>
                            </div>
                            <div class="col-md-6 col-12 text-md-end mt-2 mt-md-0">
                                <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-recepcion">
                                    <i class="fa-solid fa-dolly me-1"></i> Registrar Recepción Física
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm ms-1" id="btn-abrir-modal-conformidad">
                                    <i class="fa-solid fa-handshake me-1"></i> Emitir Acta Conformidad
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle mb-0" id="tabla-recepciones">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Folio Recepción</th>
                                        <th class="f-s-12 text-uppercase">Orden Ref.</th>
                                        <th class="f-s-12 text-uppercase">Almacén Destino</th>
                                        <th class="f-s-12 text-uppercase">Guía Remisión</th>
                                        <th class="f-s-12 text-uppercase">Fecha Ingreso</th>
                                        <th class="f-s-12 text-uppercase text-center">Líneas Afectadas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Seleccione una orden o consulte recepciones...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 4: COMPROBANTES & 3-WAY MATCHING -->
                    <div class="tab-pane fade" id="tab-comprobantes" role="tabpanel">
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-6 col-12">
                                <h5 class="f-s-15 f-w-700 mb-1">Comprobantes Fiscales y Cotejo Tripartito</h5>
                                <p class="f-s-12 text-muted mb-0">3-Way Matching: Orden de Compra ↔ Recepción/Conformidad ↔ Comprobante del Proveedor.</p>
                            </div>
                            <div class="col-md-6 col-12 text-md-end mt-2 mt-md-0">
                                <button type="button" class="btn btn-success btn-sm" id="btn-abrir-modal-comprobante">
                                    <i class="fa-solid fa-file-circle-check me-1"></i> Registrar Comprobante
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-comprobantes">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Comprobante Fiscal</th>
                                        <th class="f-s-12 text-uppercase">Proveedor</th>
                                        <th class="f-s-12 text-uppercase">OC Referencia</th>
                                        <th class="f-s-12 text-uppercase">Emisión / Vcto</th>
                                        <th class="f-s-12 text-uppercase text-end">Total (PEN)</th>
                                        <th class="f-s-12 text-uppercase text-center">3-Way Matching</th>
                                        <th class="f-s-12 text-uppercase">Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando comprobantes registrados...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 5: CUENTAS POR PAGAR & PAGOS -->
                    <div class="tab-pane fade" id="tab-cxp" role="tabpanel">
                        <div class="row mb-3 g-2 align-items-center">
                            <div class="col-md-4 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-cxp-termino" placeholder="Buscar por código CxP o proveedor..." style="border-radius: 20px;">
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select form-select-sm" id="filtro-cxp-estado" style="border-radius: 20px;">
                                    <option value="">Todos los estados</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                    <option value="AMORTIZADA_PARCIAL">Amortizada Parcial</option>
                                    <option value="LIQUIDADA">Liquidada</option>
                                </select>
                            </div>
                            <div class="col-md-5 col-6 text-md-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-cxp">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-cxp">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Código CxP</th>
                                        <th class="f-s-12 text-uppercase">Proveedor</th>
                                        <th class="f-s-12 text-uppercase">Vencimiento</th>
                                        <th class="f-s-12 text-uppercase text-end">Monto Total</th>
                                        <th class="f-s-12 text-uppercase text-end">Amortizado</th>
                                        <th class="f-s-12 text-uppercase text-end">Saldo Pendiente</th>
                                        <th class="f-s-12 text-uppercase text-center">Estado</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Cargando cuentas por pagar...</td>
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
<!-- MODALES DEL SISTEMA (GEOMETRÍA ALINA D-075 / D-076) -->
<!-- ========================================================================= -->

<!-- MODAL: FORMULAR ORDEN DE COMPRA -->
<div class="modal fade" id="modal-crear-orden" tabindex="-1" aria-labelledby="modalCrearOrdenLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content b-r-20 shadow-lg border-0">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalCrearOrdenLabel">
                    <i class="fa-solid fa-file-invoice text-primary me-2"></i> Formular Orden de Compra
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-orden" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5 col-12">
                            <label class="form-label f-s-13 f-w-600">Proveedor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                                <select class="form-select select2-modal" id="oc-proveedor-id" name="proveedor_id" required style="border-radius: 0 20px 20px 0;">
                                    <option value="">Seleccione proveedor...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-13 f-w-600">Almacén de Entrega</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-warehouse"></i></span>
                                <select class="form-select select2-modal" id="oc-almacen-id" name="almacen_entrega_id" style="border-radius: 0 20px 20px 0;">
                                    <option value="">Seleccione almacén...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-12">
                            <label class="form-label f-s-13 f-w-600">Condición de Pago</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-credit-card"></i></span>
                                <select class="form-select" id="oc-condicion-pago" name="condicion_pago" style="border-radius: 0 20px 20px 0;">
                                    <option value="CONTADO">Contado</option>
                                    <option value="CREDITO_15_DIAS">Crédito 15 días</option>
                                    <option value="CREDITO_30_DIAS">Crédito 30 días</option>
                                    <option value="CREDITO_60_DIAS">Crédito 60 días</option>
                                    <option value="ANTICIPADO">Anticipado</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-13 f-w-600">Fecha de Entrega Esperada</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                                <input type="date" class="form-control" id="oc-fecha-entrega" name="fecha_entrega_esperada" style="border-radius: 0 20px 20px 0;">
                            </div>
                        </div>
                        <div class="col-md-8 col-12">
                            <label class="form-label f-s-13 f-w-600">Notas u Observaciones Comerciales</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-comment-dots"></i></span>
                                <input type="text" class="form-control" id="oc-notas" name="notas_comerciales" placeholder="Términos pactados, lugar de entrega o condiciones..." style="border-radius: 0 20px 20px 0;">
                            </div>
                        </div>
                    </div>

                    <!-- Detalle de Líneas de la Orden -->
                    <div class="card border mb-3 b-r-8">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <span class="f-s-13 f-w-700">Líneas de Compra (Bienes y Servicios Tipados)</span>
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm py-1" id="btn-agregar-linea-bien">
                                    <i class="fa-solid fa-box me-1"></i> + Agregar Bien
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm py-1 ms-1" id="btn-agregar-linea-servicio">
                                    <i class="fa-solid fa-wrench me-1"></i> + Agregar Servicio
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-2">
                            <table class="table table-sm align-middle mb-0" id="tabla-lineas-orden">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">Tipo</th>
                                        <th style="width: 40%;">Artículo / Descripción del Servicio</th>
                                        <th style="width: 15%;" class="text-end">Cantidad</th>
                                        <th style="width: 15%;" class="text-end">P. Unitario (PEN)</th>
                                        <th style="width: 15%;" class="text-end">Subtotal</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="contenedor-lineas-orden">
                                    <!-- Las filas se inyectan dinámicamente con JS -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end f-w-700">Total Estimado (PEN):</td>
                                        <td class="text-end f-w-700 text-primary" id="oc-resumen-total">S/ 0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-orden">
                        <i class="fa-solid fa-check me-1"></i> Guardar Orden (Borrador)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: NUEVA SOLICITUD DE COMPRA -->
<div class="modal fade" id="modal-crear-solicitud" tabindex="-1" aria-labelledby="modalCrearSolLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-20 shadow-lg border-0">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalCrearSolLabel">
                    <i class="fa-solid fa-clipboard-list text-primary me-2"></i> Nuevo Requerimiento Interno
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-solicitud" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Departamento o Área <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-sitemap"></i></span>
                                <input type="text" class="form-control" name="departamento_area" required placeholder="Ej: Housekeeping, Mantenimiento, Cocina..." style="border-radius: 0 20px 20px 0;">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Fecha Límite Requerida</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-calendar-day"></i></span>
                                <input type="date" class="form-control" name="fecha_limite_requerida" style="border-radius: 0 20px 20px 0;">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Justificación Operacional</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-align-left"></i></span>
                            <textarea class="form-control" name="justificacion" rows="2" placeholder="Motivo o necesidad operativa de la solicitud..." style="border-radius: 0 20px 20px 0;"></textarea>
                        </div>
                    </div>
                    <div class="card border mb-3 b-r-8">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <span class="f-s-13 f-w-700">Ítems Solicitados</span>
                            <button type="button" class="btn btn-outline-primary btn-sm py-1" id="btn-sol-agregar-linea">
                                <i class="fa-solid fa-plus me-1"></i> Agregar Ítem
                            </button>
                        </div>
                        <div class="table-responsive p-2">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Tipo</th>
                                        <th style="width: 55%;">Artículo o Descripción</th>
                                        <th style="width: 20%;" class="text-end">Cantidad</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="contenedor-lineas-solicitud"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i> Registrar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REGISTRAR RECEPCIÓN FÍSICA -->
<div class="modal fade" id="modal-registrar-recepcion" tabindex="-1" aria-labelledby="modalRecLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-20 shadow-lg border-0">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalRecLabel">
                    <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i> Registrar Recepción Física en Almacén
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-registrar-recepcion" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Orden de Compra <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-file-invoice"></i></span>
                                <select class="form-select select2-modal" id="rec-orden-id" name="orden_compra_id" required style="border-radius: 0 20px 20px 0;">
                                    <option value="">Seleccione OC aprobada...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Almacén Destino <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-warehouse"></i></span>
                                <select class="form-select select2-modal" id="rec-almacen-id" name="almacen_id" required style="border-radius: 0 20px 20px 0;">
                                    <option value="">Seleccione almacén...</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">N° Guía de Remisión Proveedor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                <input type="text" class="form-control" name="numero_guia_remision" placeholder="T001-000123" style="border-radius: 0 20px 20px 0;">
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Observaciones</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-comment"></i></span>
                                <input type="text" class="form-control" name="observaciones" placeholder="Estado del embalaje, precintos..." style="border-radius: 0 20px 20px 0;">
                            </div>
                        </div>
                    </div>
                    <div class="card border mb-3 b-r-8">
                        <div class="card-header bg-light py-2">
                            <span class="f-s-13 f-w-700">Conteo Físico: Aceptado (Kardex) vs Rechazado</span>
                        </div>
                        <div class="table-responsive p-2">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Artículo</th>
                                        <th class="text-end">Pendiente</th>
                                        <th class="text-end" style="width: 20%;">Aceptado</th>
                                        <th class="text-end" style="width: 20%;">Rechazado</th>
                                        <th style="width: 25%;">Motivo Rechazo</th>
                                    </tr>
                                </thead>
                                <tbody id="contenedor-lineas-recepcion">
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">Seleccione una orden para cargar sus bienes pendientes...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Confirmar Entrada en Kardex
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REGISTRAR COMPROBANTE PROVEEDOR Y 3-WAY MATCHING -->
<div class="modal fade" id="modal-registrar-comprobante" tabindex="-1" aria-labelledby="modalCompLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content b-r-20 shadow-lg border-0">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalCompLabel">
                    <i class="fa-solid fa-receipt text-success me-2"></i> Registrar Comprobante de Pago del Proveedor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-registrar-comprobante" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Orden de Compra Asociada <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-file-invoice"></i></span>
                                <select class="form-select select2-modal" id="comp-orden-id" name="orden_compra_id" required style="border-radius: 0 20px 20px 0;">
                                    <option value="">Seleccione OC...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Tipo de Comprobante <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-file-lines"></i></span>
                                <select class="form-select" id="comp-tipo" name="tipo_comprobante" required style="border-radius: 0 20px 20px 0;">
                                    <option value="FACTURA">Factura Electrónica</option>
                                    <option value="BOLETA">Boleta de Venta</option>
                                    <option value="RECIBO_HONORARIOS">Recibo por Honorarios</option>
                                    <option value="NOTA_CREDITO">Nota de Crédito</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <label class="form-label f-s-13 f-w-600">Serie <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="serie" required placeholder="F001" style="border-radius: 20px;">
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label f-s-13 f-w-600">Número <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="numero" required placeholder="00012345" style="border-radius: 20px;">
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Emisión <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_emision" required style="border-radius: 20px;">
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Vencimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_vencimiento" required style="border-radius: 20px;">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-13 f-w-600">Subtotal (PEN) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="comp-subtotal" name="subtotal" required placeholder="0.00" style="border-radius: 20px;">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-13 f-w-600">Impuestos / IGV (PEN)</label>
                            <input type="number" step="0.01" class="form-control" id="comp-impuesto" name="impuesto" placeholder="0.00" style="border-radius: 20px;">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-13 f-w-600">Total Comprobante (PEN) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control f-w-700 text-success" id="comp-total" name="total" required placeholder="0.00" style="border-radius: 20px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fa-solid fa-file-circle-check me-1"></i> Validar 3-Way Matching y Devengar CxP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REGISTRAR PAGO A PROVEEDOR (CXP) -->
<div class="modal fade" id="modal-registrar-pago" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content b-r-20 shadow-lg border-0">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalPagoLabel">
                    <i class="fa-solid fa-money-bill-transfer text-danger me-2"></i> Registrar Pago a Proveedor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-registrar-pago" class="app-form app-icon-form">
                <input type="hidden" id="pago-cxp-id" name="cuenta_pagar_id">
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3 b-r-8">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="f-s-12 text-muted">Pasivo Ref:</span>
                            <span class="f-s-12 f-w-700" id="pago-cxp-codigo">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="f-s-12 text-muted">Saldo Pendiente:</span>
                            <span class="f-s-14 f-w-700 text-danger" id="pago-cxp-saldo">S/ 0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Medio de Pago <span class="text-danger">*</span></label>
                        <select class="form-select" id="pago-medio" name="medio_pago" required style="border-radius: 20px;">
                            <option value="TRANSFERENCIA_BANCARIA">Transferencia Bancaria</option>
                            <option value="EFECTIVO_CAJA">Efectivo de Caja Chica</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="BILLETERA_DIGITAL">Billetera Digital</option>
                        </select>
                    </div>

                    <div class="mb-3" id="bloque-pago-sesion-caja" style="display: none;">
                        <label class="form-label f-s-13 f-w-600">Sesión de Caja Chica Abierta</label>
                        <select class="form-select" id="pago-sesion-caja-id" name="sesion_caja_id" style="border-radius: 20px;">
                            <option value="">Seleccione turno de caja...</option>
                        </select>
                    </div>

                    <div class="mb-3" id="bloque-pago-op-bancaria">
                        <label class="form-label f-s-13 f-w-600">N° de Operación Bancaria</label>
                        <input type="text" class="form-control" name="numero_operacion_bancaria" placeholder="Ej: 987654321" style="border-radius: 20px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Monto a Amortizar (PEN) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control f-w-700" id="pago-monto" name="monto" required placeholder="0.00" style="border-radius: 20px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Notas de Tesorería</label>
                        <textarea class="form-control" name="notas" rows="2" placeholder="Detalle adicional del desembolso..." style="border-radius: 12px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-check me-1"></i> Aplicar Amortización
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script específico del módulo -->
<script src="/assets/js/gestion-compras.js"></script>
