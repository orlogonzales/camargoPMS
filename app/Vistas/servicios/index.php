<?php

declare(strict_types=1);

/**
 * Vista principal de Servicios, Consumos, Proveedores y Traslados — Camargo PMS (SERVICIOS-1).
 *
 * Principios vinculantes:
 * - RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO ≠ SERVICIO ≠ PROVEEDOR.
 * - Snapshots inmutables de catálogo al contratar (D-010 / D-069).
 * - Cero DELETE físico. Cancelación justificada con motivo obligatorio; prohibida en EJECUTADO.
 * - D-071: Font Awesome 6.3.0 exclusivo, Variants of badge & chip Alina, Vanilla JS, PristineJS, SweetAlert2.
 *
 * @var array<int, \CamargoPMS\Modelos\CategoriaServicio> $categorias
 * @var array<int, \CamargoPMS\Modelos\ModalidadCobroServicio> $modalidades
 * @var array<int, \CamargoPMS\Modelos\Propiedad> $propiedades
 * @var string $csrf_token
 * @var bool $puede_gestionar
 * @var bool $puede_contratar
 * @var bool $puede_ejecutar
 * @var bool $puede_cancelar
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Servicios -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-concierge-bell f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Catálogo de Servicios, Consumos y Proveedores</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión comercial de conceptos adicionales, proveedores externos, consumos imputados a reservas y traslados logísticos.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($puede_contratar)): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-contratar">
                            <i class="fa-solid fa-cart-plus me-1"></i> Imputar Consumo / Servicio
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($puede_gestionar)): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-crear-servicio">
                            <i class="fa-solid fa-plus me-1"></i> Nuevo en Catálogo
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-abrir-crear-proveedor">
                            <i class="fa-solid fa-truck-field me-1"></i> Nuevo Proveedor
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tarjetas Resumen / KPIs -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Servicios Solicitados</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-warning mt-1" id="kpi-solicitados">0</h3>
                                    <span class="f-s-11 text-muted">Pendientes de confirmación</span>
                                </div>
                                <div class="bg-light-warning text-warning p-3 b-r-8">
                                    <i class="fa-solid fa-clock f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Confirmados / En Curso</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1" id="kpi-confirmados">0</h3>
                                    <span class="f-s-11 text-muted">Listos para ejecución</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-circle-check f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Ejecutados / Entregados</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-ejecutados">0</h3>
                                    <span class="f-s-11 text-muted">Prestaciones cerradas</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-handshake f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Catálogo Activo</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-catalogo-activo">0</h3>
                                    <span class="f-s-11 text-muted">Conceptos disponibles</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-layer-group f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de Navegación del Módulo -->
            <div class="card-body p-0 bg-white">
                <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-3 border-bottom" id="tabsServicios" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-w-600 f-s-14" id="tab-contratados-btn" data-bs-toggle="tab" data-bs-target="#tab-contratados" type="button" role="tab" aria-selected="true">
                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Consumos y Servicios Contratados
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600 f-s-14" id="tab-catalogo-btn" data-bs-toggle="tab" data-bs-target="#tab-catalogo" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-list-check me-1"></i> Catálogo Maestro
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600 f-s-14" id="tab-proveedores-btn" data-bs-toggle="tab" data-bs-target="#tab-proveedores" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-truck me-1"></i> Proveedores Homologados
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600 f-s-14" id="tab-traslados-btn" data-bs-toggle="tab" data-bs-target="#tab-traslados" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-van-shuttle me-1"></i> Traslados y Transfers
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-3" id="tabsServiciosContent">
                    <!-- ======================================================= -->
                    <!-- TAB 1: CONSUMOS Y SERVICIOS CONTRATADOS                -->
                    <!-- ======================================================= -->
                    <div class="tab-pane fade show active" id="tab-contratados" role="tabpanel">
                        <!-- Filtros de Contratados -->
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-3 col-12">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" class="form-control" id="filtro-contratados-q" placeholder="Buscar código, servicio, reserva o titular...">
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6 col-12">
                                <select class="form-select form-select-sm" id="filtro-contratados-estado">
                                    <option value="">Todos los Estados</option>
                                    <option value="SOLICITADO">SOLICITADO</option>
                                    <option value="CONFIRMADO">CONFIRMADO</option>
                                    <option value="EJECUTADO">EJECUTADO</option>
                                    <option value="CANCELADO">CANCELADO</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 col-12">
                                <select class="form-select form-select-sm" id="filtro-contratados-proveedor">
                                    <option value="">Operación / Proveedor</option>
                                    <option value="interno">Operación Interna (Camargo)</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-contratados-fecha-desde" placeholder="Fecha desde...">
                            </div>
                            <div class="col-md-2 col-sm-6 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-contratados-fecha-hasta" placeholder="Fecha hasta...">
                            </div>
                            <div class="col-md-1 col-12 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-limpiar-filtros-contratados" title="Limpiar filtros">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Servicios Contratados -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-servicios-contratados">
                                <thead class="table-light">
                                    <tr class="f-s-12 text-uppercase text-secondary">
                                        <th class="ps-3">Código</th>
                                        <th>Reserva / Estadía</th>
                                        <th>Servicio Contratado</th>
                                        <th>Modalidad & Cantidad</th>
                                        <th class="text-end">Importe Total</th>
                                        <th>Operador / Proveedor</th>
                                        <th>Fecha Servicio</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-servicios-contratados">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando consumos imputados...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================================================= -->
                    <!-- TAB 2: CATÁLOGO MAESTRO DE SERVICIOS                   -->
                    <!-- ======================================================= -->
                    <div class="tab-pane fade" id="tab-catalogo" role="tabpanel">
                        <!-- Filtros del Catálogo -->
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-4 col-12">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" class="form-control" id="filtro-catalogo-q" placeholder="Buscar código o nombre del servicio...">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 col-12">
                                <select class="form-select form-select-sm" id="filtro-catalogo-categoria">
                                    <option value="">Todas las Categorías</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= $cat->obtenerId() ?>"><?= e($cat->obtenerNombre()) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 col-12">
                                <select class="form-select form-select-sm" id="filtro-catalogo-estado">
                                    <option value="">Todos los Estados</option>
                                    <option value="ACTIVO">ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-12 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-limpiar-filtros-catalogo">
                                    <i class="fa-solid fa-rotate-left me-1"></i> Limpiar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Catálogo -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-catalogo-servicios">
                                <thead class="table-light">
                                    <tr class="f-s-12 text-uppercase text-secondary">
                                        <th class="ps-3">Código</th>
                                        <th>Categoría</th>
                                        <th>Nombre del Servicio</th>
                                        <th>Modalidad de Cobro</th>
                                        <th class="text-end">Precio Ref.</th>
                                        <th class="text-end">Costo Ref.</th>
                                        <th class="text-center">Operación</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-catalogo-servicios">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando catálogo de servicios...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================================================= -->
                    <!-- TAB 3: PROVEEDORES HOMOLOGADOS                         -->
                    <!-- ======================================================= -->
                    <div class="tab-pane fade" id="tab-proveedores" role="tabpanel">
                        <!-- Filtros Proveedores -->
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-5 col-12">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" class="form-control" id="filtro-proveedor-q" placeholder="Buscar razón social, nombre comercial o RUC...">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 col-12">
                                <select class="form-select form-select-sm" id="filtro-proveedor-tipo">
                                    <option value="">Todos los Tipos</option>
                                    <option value="EMPRESA">EMPRESA</option>
                                    <option value="PERSONA_NATURAL">PERSONA NATURAL</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6 col-12">
                                <select class="form-select form-select-sm" id="filtro-proveedor-estado">
                                    <option value="">Todos los Estados</option>
                                    <option value="ACTIVO">ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-12 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-limpiar-filtros-proveedores">
                                    <i class="fa-solid fa-rotate-left me-1"></i> Limpiar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Proveedores -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-proveedores">
                                <thead class="table-light">
                                    <tr class="f-s-12 text-uppercase text-secondary">
                                        <th class="ps-3">Código</th>
                                        <th>Razón Social / Nombre Legal</th>
                                        <th>Tipo</th>
                                        <th>Documento / RUC</th>
                                        <th>Contacto (Email / Teléfono)</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-proveedores">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando proveedores...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================================================= -->
                    <!-- TAB 4: TRASLADOS Y TRANSFERS                           -->
                    <!-- ======================================================= -->
                    <div class="tab-pane fade" id="tab-traslados" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="f-s-13 text-secondary">
                                Monitoreo especializado de transfers de llegada y salida para recepción y logística.
                            </span>
                            <span class="badge bg-light-primary" id="conteo-traslados-badge">0 traslados programados</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-traslados">
                                <thead class="table-light">
                                    <tr class="f-s-12 text-uppercase text-secondary">
                                        <th class="ps-3">Código SC</th>
                                        <th>Tipo Traslado</th>
                                        <th>Origen ➔ Destino</th>
                                        <th>Fecha y Hora Recogida</th>
                                        <th>Vuelo / Empresa</th>
                                        <th>Pax & Maletas</th>
                                        <th>Conductor / Vehículo</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-traslados">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando traslados programados...
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

<!-- =========================================================================== -->
<!-- MODAL: Contratar / Imputar Consumo de Servicio                             -->
<!-- =========================================================================== -->
<div class="modal fade" id="modal-contratar-servicio" tabindex="-1" aria-labelledby="modalContratarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalContratarTitulo">
                    <i class="fa-solid fa-cart-plus text-primary me-2"></i> Contratar / Imputar Consumo de Servicio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-contratar-servicio" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Reserva Comercial Titular (Obligatoria) -->
                        <div class="col-md-7 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Reserva Comercial Titular <span class="text-danger">*</span></label>
                            <select class="form-select" id="contratar-reserva-id" name="reserva_id" required>
                                <option value="">Seleccione reserva comercial...</option>
                            </select>
                            <div class="form-text f-s-11 text-muted">La reserva comercial es la fuente de verdad del folio de cobro.</div>
                        </div>

                        <!-- Estadía Física Opcional -->
                        <div class="col-md-5 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Estadía / Habitación (Opcional)</label>
                            <select class="form-select" id="contratar-estadia-id" name="estadia_id">
                                <option value="">Sin imputar a habitación específica</option>
                            </select>
                            <div class="form-text f-s-11 text-muted">Seleccione si fue consumido in situ en una unidad ocupada.</div>
                        </div>

                        <!-- Concepto del Catálogo -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Concepto de Servicio <span class="text-danger">*</span></label>
                            <select class="form-select" id="contratar-servicio-id" name="servicio_id" required>
                                <option value="">Seleccione servicio del catálogo...</option>
                            </select>
                        </div>

                        <!-- Modalidad y Operación Interna -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Modalidad Operativa <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 pt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_operacion" id="op-interna" value="interna" checked>
                                    <label class="form-check-label f-s-13" for="op-interna">
                                        <i class="fa-solid fa-house-chimney-user me-1 text-primary"></i> Operación Interna
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_operacion" id="op-externa" value="externa">
                                    <label class="form-check-label f-s-13" for="op-externa">
                                        <i class="fa-solid fa-truck me-1 text-secondary"></i> Proveedor Externo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Proveedor Asignado (Visible si es externo) -->
                        <div class="col-12 d-none" id="bloque-proveedor-externo">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Proveedor Asignado <span class="text-danger">*</span></label>
                            <select class="form-select" id="contratar-proveedor-id" name="proveedor_id">
                                <option value="">Seleccione proveedor homologado...</option>
                            </select>
                        </div>

                        <!-- Valores Económicos -->
                        <div class="col-md-3 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="contratar-cantidad" name="cantidad" value="1.00" required>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Precio Unit. (PEN) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.00" class="form-control" id="contratar-precio-unitario" name="precio_unitario" value="0.00" required>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Costo Unit. (PEN)</label>
                            <input type="number" step="0.01" min="0.00" class="form-control" id="contratar-costo-unitario" name="costo_unitario" value="0.00">
                        </div>
                        <div class="col-md-3 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Total Calculado</label>
                            <input type="text" class="form-control bg-light f-w-700 text-primary" id="contratar-total-calculado" value="PEN 0.00" readonly>
                        </div>

                        <!-- Fechas Operativas -->
                        <div class="col-md-4 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Fecha Prevista <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contratar-fecha-servicio" name="fecha_servicio" required>
                        </div>
                        <div class="col-md-4 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Hora Prevista</label>
                            <input type="time" class="form-control" id="contratar-hora-servicio" name="hora_servicio">
                        </div>
                        <div class="col-md-4 col-sm-12 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Estado Inicial</label>
                            <select class="form-select" id="contratar-estado-inicial" name="estado">
                                <option value="SOLICITADO">SOLICITADO</option>
                                <option value="CONFIRMADO">CONFIRMADO</option>
                            </select>
                        </div>

                        <!-- SECCIÓN CONDICIONAL: DETALLE DE TRASLADO -->
                        <div class="col-12 d-none" id="bloque-detalle-traslado">
                            <div class="card border p-3 bg-light b-r-8">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa-solid fa-van-shuttle text-primary me-2"></i>
                                    <h6 class="f-s-13 f-w-700 text-uppercase mb-0">Detalles Logísticos del Traslado</h6>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Tipo de Traslado <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="traslado-tipo" name="traslado[tipo_traslado]">
                                            <option value="LLEGADA">LLEGADA (Al predio)</option>
                                            <option value="SALIDA">SALIDA (Hacia terminal/aeropuerto)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Origen <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-origen" name="traslado[origen]" placeholder="Ej. Aeropuerto Jorge Chávez">
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Destino <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-destino" name="traslado[destino]" placeholder="Ej. Propiedad / Hotel">
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Fecha y Hora Recogida <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-fecha-hora" name="traslado[fecha_hora_recogida]" placeholder="YYYY-MM-DD HH:MM">
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Aerolínea / Empresa</label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-aerolinea" name="traslado[aerolinea_empresa]" placeholder="Ej. LATAM">
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">N° Vuelo / Viaje</label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-vuelo" name="traslado[numero_vuelo_viaje]" placeholder="Ej. LA2045">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">N° Pasajeros <span class="text-danger">*</span></label>
                                        <input type="number" min="1" class="form-control form-control-sm" id="traslado-pasajeros" name="traslado[cantidad_pasajeros]" value="1">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">N° Maletas</label>
                                        <input type="number" min="0" class="form-control form-control-sm" id="traslado-maletas" name="traslado[cantidad_maletas]" value="0">
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Conductor / Móvil</label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-conductor" name="traslado[datos_conductor_vehiculo]" placeholder="Chofer, teléfono y placa">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label f-s-11 text-secondary text-uppercase">Instrucciones de Encuentro</label>
                                        <input type="text" class="form-control form-control-sm" id="traslado-instrucciones" name="traslado[instrucciones_recogida]" placeholder="Ej. Cartel con apellido en puerta 4">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones generales -->
                        <div class="col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Observaciones / Notas Internas</label>
                            <textarea class="form-control" id="contratar-observaciones" name="observaciones" rows="2" placeholder="Indicaciones para staff o recepción..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-contratacion">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Contratación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- MODAL: Crear / Editar Servicio en Catálogo                                 -->
<!-- =========================================================================== -->
<div class="modal fade" id="modal-crear-servicio" tabindex="-1" aria-labelledby="modalServicioTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalServicioTitulo">
                    <i class="fa-solid fa-layer-group text-primary me-2"></i> Registrar Servicio en Catálogo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-crear-servicio" novalidate>
                <input type="hidden" id="servicio-id" name="id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Código del Servicio</label>
                            <input type="text" class="form-control" id="servicio-codigo" name="codigo" placeholder="Autogenerado si está vacío">
                        </div>
                        <div class="col-md-8 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Nombre del Servicio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="servicio-nombre" name="nombre" placeholder="Ej. Traslado Aeropuerto - Hotel (Van)" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" id="servicio-categoria-id" name="categoria_id" required>
                                <option value="">Seleccione categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat->obtenerId() ?>" data-codigo="<?= e($cat->obtenerCodigo()) ?>">
                                        <?= e($cat->obtenerNombre()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Modalidad de Cobro <span class="text-danger">*</span></label>
                            <select class="form-select" id="servicio-modalidad-id" name="modalidad_cobro_id" required>
                                <option value="">Seleccione modalidad...</option>
                                <?php foreach ($modalidades as $mod): ?>
                                    <option value="<?= $mod->obtenerId() ?>"><?= e($mod->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Precio Referencial (PEN) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.00" class="form-control" id="servicio-precio" name="precio_venta_referencial" value="0.00" required>
                        </div>
                        <div class="col-md-4 col-sm-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Costo Referencial (PEN)</label>
                            <input type="number" step="0.01" min="0.00" class="form-control" id="servicio-costo" name="costo_referencial" value="0.00">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Propiedad Específica</label>
                            <select class="form-select" id="servicio-propiedad-id" name="propiedad_id">
                                <option value="">Aplica a todas las propiedades</option>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="servicio-es-interna" name="es_operacion_interna_habitual" value="1">
                                <label class="form-check-label f-s-13" for="servicio-es-interna">
                                    Prestado habitualmente con personal interno (Camargo)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="servicio-requiere-traslado" name="requiere_traslado_detalle" value="1">
                                <label class="form-check-label f-s-13" for="servicio-requiere-traslado">
                                    Exige captura logística de traslado (origen, destino, vuelo)
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Descripción</label>
                            <textarea class="form-control" id="servicio-descripcion" name="descripcion" rows="2" placeholder="Detalles comerciales del servicio..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-servicio">
                        <i class="fa-solid fa-save me-1"></i> Guardar Servicio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- MODAL: Crear / Editar Proveedor                                            -->
<!-- =========================================================================== -->
<div class="modal fade" id="modal-crear-proveedor" tabindex="-1" aria-labelledby="modalProveedorTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalProveedorTitulo">
                    <i class="fa-solid fa-truck-field text-primary me-2"></i> Registrar Proveedor Externo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-crear-proveedor" novalidate>
                <input type="hidden" id="proveedor-id" name="id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Tipo de Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select" id="proveedor-tipo" name="tipo" required>
                                <option value="EMPRESA">EMPRESA</option>
                                <option value="PERSONA_NATURAL">PERSONA NATURAL</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Código del Proveedor</label>
                            <input type="text" class="form-control" id="proveedor-codigo" name="codigo" placeholder="Autogenerado si está vacío">
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">RUC / N° Documento</label>
                            <input type="text" class="form-control" id="proveedor-documento" name="numero_documento" placeholder="RUC de 11 dígitos u otro">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Razón Social / Nombre Legal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="proveedor-razon-social" name="razon_social" placeholder="Nombre legal completo" required>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Nombre Comercial / Marca</label>
                            <input type="text" class="form-control" id="proveedor-nombre-comercial" name="nombre_comercial" placeholder="Nombre comercial de fantasía">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Correo Electrónico</label>
                            <input type="email" class="form-control" id="proveedor-email" name="email" placeholder="contacto@proveedor.com">
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Teléfono / WhatsApp</label>
                            <input type="text" class="form-control" id="proveedor-telefono" name="telefono" placeholder="+51 999 999 999">
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Dirección Física</label>
                            <input type="text" class="form-control" id="proveedor-direccion" name="direccion" placeholder="Calle, número, distrito, ciudad">
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Observaciones</label>
                            <textarea class="form-control" id="proveedor-observaciones" name="observaciones" rows="2" placeholder="Condiciones de pago, horarios o notas..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-proveedor">
                        <i class="fa-solid fa-save me-1"></i> Guardar Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- MODAL: Homologar Proveedor en Servicio (N:M)                               -->
<!-- =========================================================================== -->
<div class="modal fade" id="modal-homologar-proveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-handshake text-primary me-2"></i> Homologar Proveedor para Servicio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-homologar-proveedor" novalidate>
                <input type="hidden" id="homologar-servicio-id" name="servicio_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Servicio</label>
                        <input type="text" class="form-control bg-light f-w-600" id="homologar-servicio-nombre" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Proveedor <span class="text-danger">*</span></label>
                        <select class="form-select" id="homologar-proveedor-id" name="proveedor_id" required>
                            <option value="">Seleccione proveedor...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Costo Pactado de Compra (PEN)</label>
                        <input type="number" step="0.01" min="0.00" class="form-control" id="homologar-costo-pactado" name="costo_pactado" placeholder="0.00">
                        <div class="form-text f-s-11 text-muted">Costo acordado específicamente con este proveedor.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Horas Mínimas de Anticipación</label>
                        <input type="number" min="0" class="form-control" id="homologar-preaviso" name="tiempo_anticipacion_horas" value="0">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="homologar-es-preferente" name="es_preferente" value="1">
                        <label class="form-check-label f-s-13" for="homologar-es-preferente">
                            Marcar como Proveedor Preferente (sugerido por defecto)
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-link me-1"></i> Guardar Asociación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- MODAL: Cancelar Servicio Contratado (con Motivo Justificado)                -->
<!-- =========================================================================== -->
<div class="modal fade" id="modal-cancelar-servicio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700 text-danger">
                    <i class="fa-solid fa-ban me-2"></i> Cancelar Contratación de Servicio
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-cancelar-servicio" novalidate>
                <input type="hidden" id="cancelar-contratado-id" name="id">
                <div class="modal-body p-4">
                    <p class="text-secondary f-s-13 mb-3">
                        Está a punto de anular el servicio contratado <strong id="cancelar-codigo-servicio" class="text-dark"></strong>.
                        Esta acción registrará auditoría inmutable.
                    </p>
                    <div class="mb-3">
                        <label class="form-label f-s-12 text-uppercase f-w-600 text-secondary">Motivo Obligatorio de Cancelación <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cancelar-motivo" name="motivo" rows="3" placeholder="Explique la causa de la cancelación..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-cancelacion">
                        <i class="fa-solid fa-ban me-1"></i> Proceder con Cancelación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- MODAL: Detalle Completo de Servicio Contratado                             -->
<!-- =========================================================================== -->
<div class="modal fade" id="modal-detalle-servicio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-file-invoice text-primary me-2"></i> Ficha de Consumo Contratado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6 col-12">
                        <div class="p-3 bg-light b-r-8 border">
                            <span class="f-s-11 text-uppercase text-secondary f-w-600">Servicio & Código</span>
                            <h5 class="f-s-16 f-w-700 mt-1 mb-1" id="detalle-sc-nombre">-</h5>
                            <span class="badge bg-light-primary" id="detalle-sc-codigo">-</span>
                            <span class="badge bg-light-info ms-1" id="detalle-sc-categoria">-</span>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="p-3 bg-light b-r-8 border">
                            <span class="f-s-11 text-uppercase text-secondary f-w-600">Reserva & Unidad</span>
                            <div class="f-s-14 f-w-700 mt-1 mb-1 text-dark" id="detalle-sc-reserva">-</div>
                            <div class="f-s-12 text-secondary" id="detalle-sc-titular">-</div>
                            <div class="f-s-12 text-muted" id="detalle-sc-estadia">-</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card border p-3 mb-0 b-r-8">
                            <h6 class="f-s-12 text-uppercase text-secondary f-w-700 mb-2">Desglose Económico (Snapshot Inmutable D-010)</h6>
                            <div class="row g-2 text-center">
                                <div class="col-3 p-2 border-end">
                                    <span class="f-s-11 text-muted">Cantidad</span>
                                    <div class="f-s-15 f-w-700" id="detalle-sc-cantidad">0.00</div>
                                </div>
                                <div class="col-3 p-2 border-end">
                                    <span class="f-s-11 text-muted">Precio Unitario</span>
                                    <div class="f-s-15 f-w-700" id="detalle-sc-precio">PEN 0.00</div>
                                </div>
                                <div class="col-3 p-2 border-end">
                                    <span class="f-s-11 text-muted">Modalidad</span>
                                    <div class="f-s-15 f-w-700" id="detalle-sc-modalidad">-</div>
                                </div>
                                <div class="col-3 p-2">
                                    <span class="f-s-11 text-muted">Total Folio</span>
                                    <div class="f-s-15 f-w-700 text-primary" id="detalle-sc-total">PEN 0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Proveedor y Operador -->
                    <div class="col-md-6 col-12">
                        <div class="p-2 border b-r-6">
                            <span class="f-s-11 text-secondary">Operación:</span>
                            <strong id="detalle-sc-operador">-</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="p-2 border b-r-6">
                            <span class="f-s-11 text-secondary">Estado Actual:</span>
                            <span id="detalle-sc-estado-badge">-</span>
                        </div>
                    </div>
                    <!-- Desglose de Traslado si existe -->
                    <div class="col-12 d-none" id="detalle-sc-bloque-traslado">
                        <div class="card border border-primary p-3 bg-light b-r-8">
                            <h6 class="f-s-12 text-uppercase text-primary f-w-700 mb-2">
                                <i class="fa-solid fa-van-shuttle me-1"></i> Detalles del Traslado Asociado
                            </h6>
                            <div class="row g-2 f-s-12">
                                <div class="col-md-6"><strong>Ruta:</strong> <span id="detalle-tr-ruta">-</span></div>
                                <div class="col-md-6"><strong>Fecha/Hora:</strong> <span id="detalle-tr-horario">-</span></div>
                                <div class="col-md-6"><strong>Vuelo/Empresa:</strong> <span id="detalle-tr-vuelo">-</span></div>
                                <div class="col-md-6"><strong>Pax / Maletas:</strong> <span id="detalle-tr-pax">-</span></div>
                                <div class="col-md-6"><strong>Chofer/Móvil:</strong> <span id="detalle-tr-conductor">-</span></div>
                                <div class="col-md-6"><strong>Instrucciones:</strong> <span id="detalle-tr-instrucciones">-</span></div>
                            </div>
                        </div>
                    </div>
                    <!-- Cancelación / Observaciones -->
                    <div class="col-12 d-none" id="detalle-sc-bloque-cancelacion">
                        <div class="p-2 bg-light-danger text-danger b-r-6 border border-danger f-s-12">
                            <strong>Cancelado en:</strong> <span id="detalle-sc-cancelado-en">-</span><br>
                            <strong>Motivo:</strong> <span id="detalle-sc-cancelado-motivo">-</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="f-s-12 text-secondary">
                            <strong>Observaciones:</strong> <span id="detalle-sc-observaciones">Ninguna</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-servicios.js') ?>"></script>
