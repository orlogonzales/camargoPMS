<?php

declare(strict_types=1);

/**
 * Vista principal de Gestión de Inventario, Existencias, Kardex y Activos — Camargo PMS (INVENTARIO-1 / D-078).
 *
 * Principios vinculantes:
 * - ARTÍCULO != EXISTENCIA != MOVIMIENTO != ACTIVO INDIVIDUAL.
 * - Formulario con geometría nativa Alina (app-form app-icon-form, border-radius 20px, select2 42px).
 * - D-071: Font Awesome 6.3.0 exclusivo, variantes suaves de badge Alina (bg-light-*).
 * - Kardex inmutable append-only con trazabilidad de reverso.
 * - Dotación de unidades: estándar vs realidad física.
 *
 * @var array<int, mixed> $propiedades
 * @var array<int, mixed> $unidades
 * @var array<int, mixed> $tiposUnidad
 * @var array<int, mixed> $colaboradores
 * @var array<int, mixed> $proveedores
 * @var array<int, mixed> $unidadesMedida
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\Usuario|null $usuario
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Inventario -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-boxes-stacked f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Inventario Físico, Existencias y Activos</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Catálogo de artículos, existencias por ubicación, bitácora de movimientos (Kardex), activos fijos y dotaciones.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-modal-movimiento">
                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Registrar Movimiento
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btn-abrir-modal-articulo">
                        <i class="fa-solid fa-plus me-1"></i> Nuevo Artículo
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-activo">
                        <i class="fa-solid fa-tv me-1"></i> Registrar Activo
                    </button>
                </div>
            </div>

            <!-- KPIs de Inventario -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Catálogo de Artículos</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-total-articulos">0</h3>
                                    <span class="f-s-11 text-muted">Bienes registrados</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-box-open f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Existencias Activas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-total-existencias">0</h3>
                                    <span class="f-s-11 text-muted">Líneas de stock</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-cubes-stacked f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Activos Fijos</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1" id="kpi-total-activos">0</h3>
                                    <span class="f-s-11 text-muted">Ejemplares serializados</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-barcode f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Ubicaciones Físicas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-secondary mt-1" id="kpi-total-ubicaciones">0</h3>
                                    <span class="f-s-11 text-muted">Almacenes y habitaciones</span>
                                </div>
                                <div class="bg-light-secondary text-secondary p-3 b-r-8">
                                    <i class="fa-solid fa-warehouse f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación por Pestañas del Dominio -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-2 border-bottom-0" id="tabs-inventario" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-s-14 f-w-600 py-3" id="tab-existencias-btn" data-bs-toggle="tab" data-bs-target="#tab-existencias" type="button" role="tab">
                            <i class="fa-solid fa-boxes-stacked me-2"></i> Existencias por Ubicación
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-articulos-btn" data-bs-toggle="tab" data-bs-target="#tab-articulos" type="button" role="tab">
                            <i class="fa-solid fa-tags me-2"></i> Catálogo de Artículos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-kardex-btn" data-bs-toggle="tab" data-bs-target="#tab-kardex" type="button" role="tab">
                            <i class="fa-solid fa-receipt me-2"></i> Kardex / Movimientos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-activos-btn" data-bs-toggle="tab" data-bs-target="#tab-activos" type="button" role="tab">
                            <i class="fa-solid fa-tv me-2"></i> Activos Serializables
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-ubicaciones-btn" data-bs-toggle="tab" data-bs-target="#tab-ubicaciones" type="button" role="tab">
                            <i class="fa-solid fa-warehouse me-2"></i> Almacenes y Ubicaciones
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-dotaciones-btn" data-bs-toggle="tab" data-bs-target="#tab-dotaciones" type="button" role="tab">
                            <i class="fa-solid fa-clipboard-check me-2"></i> Dotaciones de Unidades
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="tabs-inventario-contenido">

                    <!-- PESTAÑA 1: EXISTENCIAS -->
                    <div class="tab-pane fade show active" id="tab-existencias" role="tabpanel">
                        <div class="row mb-3 g-2 align-items-center">
                            <div class="col-md-3 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-existencias-termino" placeholder="Buscar por SKU o nombre..." style="border-radius: 20px;">
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select form-select-sm" id="filtro-existencias-ubicacion" style="border-radius: 20px;">
                                    <option value="">Todas las ubicaciones</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select form-select-sm" id="filtro-existencias-categoria" style="border-radius: 20px;">
                                    <option value="">Todas las categorías</option>
                                    <option value="CONSUMIBLE_OPERATIVO">Consumible / Amenities</option>
                                    <option value="LENCERIA_BLANCOS">Lencería y Blancos</option>
                                    <option value="REPUESTO_MANTENIMIENTO">Repuestos Técnicos</option>
                                    <option value="HERRAMIENTA">Herramientas</option>
                                    <option value="OTRO">Otros</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-12 text-md-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-existencias">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-existencias">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">SKU / Artículo</th>
                                        <th class="f-s-12 text-uppercase">Categoría</th>
                                        <th class="f-s-12 text-uppercase">Ubicación / Propiedad</th>
                                        <th class="f-s-12 text-uppercase text-end">Cantidad Actual</th>
                                        <th class="f-s-12 text-uppercase text-end">Reservado</th>
                                        <th class="f-s-12 text-uppercase text-end">Disponible</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando existencias...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: CATÁLOGO DE ARTÍCULOS -->
                    <div class="tab-pane fade" id="tab-articulos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" id="filtro-articulos-termino" placeholder="Buscar por SKU o nombre..." style="width: 250px; border-radius: 20px;">
                                <select class="form-select form-select-sm" id="filtro-articulos-categoria" style="width: 200px; border-radius: 20px;">
                                    <option value="">Todas las categorías</option>
                                    <option value="CONSUMIBLE_OPERATIVO">Consumible / Amenities</option>
                                    <option value="LENCERIA_BLANCOS">Lencería y Blancos</option>
                                    <option value="REPUESTO_MANTENIMIENTO">Repuestos Técnicos</option>
                                    <option value="ACTIVO_SERIALIZABLE">Activo Serializable</option>
                                    <option value="HERRAMIENTA">Herramientas</option>
                                    <option value="OTRO">Otros</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-success btn-sm" id="btn-crear-articulo-tab">
                                <i class="fa-solid fa-plus me-1"></i> Registrar Artículo
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-articulos">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">SKU</th>
                                        <th class="f-s-12 text-uppercase">Nombre</th>
                                        <th class="f-s-12 text-uppercase">Categoría</th>
                                        <th class="f-s-12 text-uppercase">U. Medida</th>
                                        <th class="f-s-12 text-uppercase text-end">Costo Ref.</th>
                                        <th class="f-s-12 text-uppercase text-end">Stock Total</th>
                                        <th class="f-s-12 text-uppercase text-center">Estado</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Cargando artículos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: KARDEX / MOVIMIENTOS -->
                    <div class="tab-pane fade" id="tab-kardex" role="tabpanel">
                        <div class="row mb-3 g-2">
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="filtro-kardex-tipo" style="border-radius: 20px;">
                                    <option value="">Todos los tipos de movimiento</option>
                                    <option value="SALDO_INICIAL">Saldo Inicial</option>
                                    <option value="ENTRADA_COMPRA">Entrada por Compra</option>
                                    <option value="SALIDA_CONSUMO">Salida por Consumo</option>
                                    <option value="SALIDA_MANTENIMIENTO">Salida para Mantenimiento</option>
                                    <option value="TRASLADO_SALIDA">Traslado Salida</option>
                                    <option value="TRASLADO_ENTRADA">Traslado Entrada</option>
                                    <option value="AJUSTE_POSITIVO">Ajuste Positivo</option>
                                    <option value="AJUSTE_NEGATIVO">Ajuste Negativo</option>
                                    <option value="REVERSO">Reverso</option>
                                </select>
                            </div>
                            <div class="col-md-9 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-kardex">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar Kardex
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-kardex">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Fecha / Código</th>
                                        <th class="f-s-12 text-uppercase">Tipo</th>
                                        <th class="f-s-12 text-uppercase">Artículo / SKU</th>
                                        <th class="f-s-12 text-uppercase">Ubicación</th>
                                        <th class="f-s-12 text-uppercase text-end">Cantidad</th>
                                        <th class="f-s-12 text-uppercase text-end">Costo Total</th>
                                        <th class="f-s-12 text-uppercase">Motivo / Correlativo</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Cargando movimientos de Kardex...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 4: ACTIVOS SERIALIZABLES -->
                    <div class="tab-pane fade" id="tab-activos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="filtro-activos-estado" style="width: 180px; border-radius: 20px;">
                                    <option value="">Todos los estados</option>
                                    <option value="DISPONIBLE">Disponible</option>
                                    <option value="ASIGNADO">Asignado a Unidad</option>
                                    <option value="EN_MANTENIMIENTO">En Mantenimiento</option>
                                    <option value="DE_BAJA">Dado de Baja</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="btn-crear-activo-tab">
                                <i class="fa-solid fa-plus me-1"></i> Registrar Activo
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-activos">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Placa / Serie</th>
                                        <th class="f-s-12 text-uppercase">Artículo</th>
                                        <th class="f-s-12 text-uppercase">Marca / Modelo</th>
                                        <th class="f-s-12 text-uppercase">Propiedad / Ubicación</th>
                                        <th class="f-s-12 text-uppercase">Estado</th>
                                        <th class="f-s-12 text-uppercase text-end">Costo Adq.</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando activos serializados...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 5: ALMACENES Y UBICACIONES -->
                    <div class="tab-pane fade" id="tab-ubicaciones" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="f-s-14 f-w-700 mb-0">Ubicaciones Polimórficas (Almacenes, Habitaciones y Custodias Externas)</h6>
                            <button type="button" class="btn btn-primary btn-sm" id="btn-crear-ubicacion-modal">
                                <i class="fa-solid fa-plus me-1"></i> Nueva Ubicación
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-ubicaciones">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Código</th>
                                        <th class="f-s-12 text-uppercase">Nombre</th>
                                        <th class="f-s-12 text-uppercase">Tipo</th>
                                        <th class="f-s-12 text-uppercase">Propiedad</th>
                                        <th class="f-s-12 text-uppercase">Referencia (Unidad / Proveedor)</th>
                                        <th class="f-s-12 text-uppercase text-center">Estado</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando ubicaciones...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 6: DOTACIONES DE UNIDADES -->
                    <div class="tab-pane fade" id="tab-dotaciones" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="card border p-3 b-r-8">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="f-s-14 f-w-700 mb-0">Dotaciones Estándar Reglamentarias</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-nueva-dotacion-modal">
                                            <i class="fa-solid fa-plus me-1"></i> Configurar
                                        </button>
                                    </div>
                                    <p class="f-s-12 text-muted mb-3">
                                        Define el estándar de lencería, amenities o equipamiento que una unidad o tipo de unidad debe mantener.
                                    </p>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0" id="tabla-dotaciones-estandar">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="f-s-11">Destino</th>
                                                    <th class="f-s-11">Artículo</th>
                                                    <th class="f-s-11 text-end">Estándar</th>
                                                    <th class="f-s-11 text-center">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">Cargando estándares...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="card border p-3 b-r-8">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="f-s-14 f-w-700 mb-0">Auditoría en Tiempo Real: Estándar vs. Realidad</h6>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-8">
                                            <select class="form-select form-select-sm" id="auditoria-unidad-select" style="border-radius: 20px;">
                                                <option value="">Seleccione una unidad habitacional...</option>
                                                <?php foreach ($unidades as $u): ?>
                                                    <option value="<?= $u->obtenerId() ?>">Habitación <?= e($u->obtenerNombre()) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <button type="button" class="btn btn-info btn-sm w-100" id="btn-auditar-unidad">
                                                <i class="fa-solid fa-magnifying-glass me-1"></i> Auditar
                                            </button>
                                        </div>
                                    </div>
                                    <div id="resultado-auditoria-dotacion">
                                        <div class="alert alert-light text-center py-4 text-muted f-s-13">
                                            Seleccione una unidad habitacional para contrastar el estándar contra los activos asignados y el stock real.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODALES OPERACIONALES (CONFORME A D-075)
========================================================================= -->

<!-- Modal 1: Registrar / Editar Artículo -->
<div class="modal fade" id="modal-articulo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-articulo-titulo">
                    <i class="fa-solid fa-box text-primary me-2"></i> Registrar Artículo de Inventario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-articulo" class="app-form">
                <input type="hidden" id="articulo-id" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Código SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="codigo_sku" id="articulo-sku" placeholder="Ej: SKU-AMN-001" style="border-radius: 20px;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Nombre del Artículo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="articulo-nombre" placeholder="Ej: Jabón de Tocador 30g" style="border-radius: 20px;" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" name="categoria" id="articulo-categoria" style="border-radius: 20px;" required>
                                <option value="CONSUMIBLE_OPERATIVO">Consumible / Amenities</option>
                                <option value="LENCERIA_BLANCOS">Lencería y Blancos</option>
                                <option value="REPUESTO_MANTENIMIENTO">Repuesto Mantenimiento</option>
                                <option value="ACTIVO_SERIALIZABLE">Activo Serializable</option>
                                <option value="HERRAMIENTA">Herramienta</option>
                                <option value="OTRO">Otro</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Unidad de Medida <span class="text-danger">*</span></label>
                            <select class="form-select" name="unidad_medida_id" id="articulo-unidad-medida" style="border-radius: 20px;" required>
                                <?php foreach ($unidadesMedida as $um): ?>
                                    <option value="<?= $um->obtenerId() ?>"><?= e($um->obtenerNombre()) ?> (<?= e($um->obtenerSimbolo()) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Costo Ref. (S/)</label>
                            <input type="number" step="0.0001" min="0" class="form-control" name="costo_referencial" id="articulo-costo" value="0.0000" style="border-radius: 20px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Stock Mínimo Alerta</label>
                            <input type="number" step="0.0001" min="0" class="form-control" name="stock_minimo_alerta" id="articulo-stock-min" value="0.0000" style="border-radius: 20px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Descripción / Especificaciones</label>
                        <textarea class="form-control" name="descripcion" id="articulo-descripcion" rows="2" style="border-radius: 12px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-articulo">Guardar Artículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Registrar Movimiento de Stock -->
<div class="modal fade" id="modal-movimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-arrow-right-arrow-left text-primary me-2"></i> Registrar Movimiento de Inventario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-movimiento" class="app-form">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Tipo de Movimiento <span class="text-danger">*</span></label>
                        <select class="form-select" id="mov-tipo" style="border-radius: 20px;" required>
                            <option value="ENTRADA">Entrada / Recepción de Compra</option>
                            <option value="CONSUMO">Salida por Consumo Operativo</option>
                            <option value="TRASLADO">Traslado entre Ubicaciones (Dos Patas)</option>
                            <option value="AJUSTE">Ajuste Físico / Merma</option>
                            <option value="SALDO_INICIAL">Saldo Inicial</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Artículo <span class="text-danger">*</span></label>
                        <select class="form-select" id="mov-articulo-id" style="border-radius: 20px;" required>
                            <option value="">Seleccione un artículo...</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3" id="bloque-ubicaciones">
                        <div class="col-12" id="bloque-ubicacion-origen">
                            <label class="form-label f-s-13 f-w-600" id="label-ubicacion-principal">Ubicación / Almacén <span class="text-danger">*</span></label>
                            <select class="form-select" id="mov-ubicacion-id" style="border-radius: 20px;" required>
                                <option value="">Seleccione ubicación...</option>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="bloque-ubicacion-destino">
                            <label class="form-label f-s-13 f-w-600">Ubicación Destino <span class="text-danger">*</span></label>
                            <select class="form-select" id="mov-ubicacion-destino-id" style="border-radius: 20px;">
                                <option value="">Seleccione destino...</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control" id="mov-cantidad" placeholder="0.0000" style="border-radius: 20px;" required>
                        </div>
                        <div class="col-6" id="bloque-costo-unitario">
                            <label class="form-label f-s-13 f-w-600">Costo Unitario (S/)</label>
                            <input type="number" step="0.0001" min="0" class="form-control" id="mov-costo-unitario" value="0.0000" style="border-radius: 20px;">
                        </div>
                        <div class="col-6 d-none" id="bloque-tipo-ajuste">
                            <label class="form-label f-s-13 f-w-600">Sentido del Ajuste <span class="text-danger">*</span></label>
                            <select class="form-select" id="mov-subtipo-ajuste" style="border-radius: 20px;">
                                <option value="AJUSTE_POSITIVO">Positivo (+ Stock)</option>
                                <option value="AJUSTE_NEGATIVO">Negativo (- Stock / Merma)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Motivo / Justificación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mov-motivo" placeholder="Explique la razón del movimiento..." style="border-radius: 20px;" required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-procesar-movimiento">Registrar Movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Registrar Activo Serializable -->
<div class="modal fade" id="modal-activo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-tv text-primary me-2"></i> Registrar Activo Serializable
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-activo" class="app-form">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Artículo Base <span class="text-danger">*</span></label>
                        <select class="form-select" name="articulo_id" id="activo-articulo-id" style="border-radius: 20px;" required>
                            <option value="">Seleccione artículo...</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Placa Patrimonial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo_placa" id="activo-placa" placeholder="Ej: ACT-00123" style="border-radius: 20px;" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Nro. de Serie Fabricante</label>
                            <input type="text" class="form-control" name="numero_serie_fabricante" id="activo-serie" placeholder="Ej: SN-998234-X" style="border-radius: 20px;">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Marca</label>
                            <input type="text" class="form-control" name="marca" id="activo-marca" placeholder="Ej: Samsung" style="border-radius: 20px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Modelo</label>
                            <input type="text" class="form-control" name="modelo" id="activo-modelo" placeholder="Ej: Crystal UHD 55" style="border-radius: 20px;">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Propiedad <span class="text-danger">*</span></label>
                            <select class="form-select" name="propiedad_id" id="activo-propiedad-id" style="border-radius: 20px;" required>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Ubicación Inicial <span class="text-danger">*</span></label>
                            <select class="form-select" name="ubicacion_id" id="activo-ubicacion-id" style="border-radius: 20px;" required>
                                <option value="">Seleccione ubicación...</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Costo Adquisición (S/)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="costo_adquisicion" id="activo-costo" value="0.00" style="border-radius: 20px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Adquisición</label>
                            <input type="date" class="form-control" name="fecha_adquisicion" id="activo-fecha-adq" style="border-radius: 20px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-activo">Guardar Activo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Transferir / Asignar Activo -->
<div class="modal fade" id="modal-transferir-activo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-arrows-split-up-and-left text-primary me-2"></i> Cambiar Ubicación de Activo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-transferir-activo" class="app-form">
                <input type="hidden" id="transf-activo-id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Activo Seleccionado</label>
                        <input type="text" class="form-control bg-light" id="transf-activo-placa" readonly style="border-radius: 20px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Nueva Ubicación de Destino <span class="text-danger">*</span></label>
                        <select class="form-select" id="transf-nueva-ubicacion-id" style="border-radius: 20px;" required>
                            <option value="">Seleccione nueva ubicación...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirmar-transferencia">Confirmar Transferencia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 5: Dar de Baja Activo -->
<div class="modal fade" id="modal-baja-activo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-danger">
                    <i class="fa-solid fa-ban me-2"></i> Dar de Baja Activo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-baja-activo" class="app-form">
                <input type="hidden" id="baja-activo-id">
                <div class="modal-body p-4">
                    <div class="alert alert-danger f-s-12 py-2 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Esta acción registrará la baja histórica del activo patrimonial. Cero eliminación física.
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Motivo Obligatorio de la Baja <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="baja-motivo" rows="3" placeholder="Describa el desperfecto irreversible, obsolescencia o pérdida..." style="border-radius: 12px;" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-baja">Confirmar Baja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 6: Crear Ubicación -->
<div class="modal fade" id="modal-ubicacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-warehouse text-primary me-2"></i> Registrar Ubicación Física
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-ubicacion" class="app-form">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Propiedad Contenedora <span class="text-danger">*</span></label>
                        <select class="form-select" name="propiedad_id" id="ubi-propiedad-id" style="border-radius: 20px;" required>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" id="ubi-codigo" placeholder="Ej: UBI-BOD-01" style="border-radius: 20px;" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Tipo de Ubicación <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo" id="ubi-tipo" style="border-radius: 20px;" required>
                                <option value="ALMACEN">Almacén / Bodega</option>
                                <option value="UNIDAD">Habitación / Unidad</option>
                                <option value="CUSTODIA_EXTERNA">Custodia Externa (Taller/Lavandería)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Nombre de la Ubicación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" id="ubi-nombre" placeholder="Ej: Bodega Central de Piso 1" style="border-radius: 20px;" required>
                    </div>
                    <div class="mb-3 d-none" id="bloque-ubi-unidad">
                        <label class="form-label f-s-13 f-w-600">Habitación Vinculada <span class="text-danger">*</span></label>
                        <select class="form-select" name="unidad_id" id="ubi-unidad-id" style="border-radius: 20px;">
                            <option value="">Seleccione unidad...</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u->obtenerId() ?>">Habitación <?= e($u->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="bloque-ubi-proveedor">
                        <label class="form-label f-s-13 f-w-600">Proveedor de Custodia Externa</label>
                        <select class="form-select" name="proveedor_id" id="ubi-proveedor-id" style="border-radius: 20px;">
                            <option value="">Seleccione proveedor...</option>
                            <?php foreach ($proveedores as $pr): ?>
                                <option value="<?= $pr->obtenerId() ?>"><?= e($pr->obtenerRazonSocial()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-ubicacion">Guardar Ubicación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 7: Configurar Dotación Estándar -->
<div class="modal fade" id="modal-dotacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-clipboard-check text-primary me-2"></i> Configurar Dotación Estándar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-dotacion" class="app-form">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Asignar a <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="dotacion_alcance" id="radio-tipo-unidad" value="tipo_unidad" checked>
                                <label class="form-check-label f-s-13" for="radio-tipo-unidad">Tipo de Unidad</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="dotacion_alcance" id="radio-unidad-especifica" value="unidad_especifica">
                                <label class="form-check-label f-s-13" for="radio-unidad-especifica">Unidad Específica</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3" id="bloque-dot-tipo-unidad">
                        <label class="form-label f-s-13 f-w-600">Tipo de Unidad <span class="text-danger">*</span></label>
                        <select class="form-select" id="dot-tipo-unidad-id" style="border-radius: 20px;">
                            <?php foreach ($tiposUnidad as $tu): ?>
                                <option value="<?= $tu->obtenerId() ?>"><?= e($tu->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="bloque-dot-unidad">
                        <label class="form-label f-s-13 f-w-600">Unidad Específica <span class="text-danger">*</span></label>
                        <select class="form-select" id="dot-unidad-id" style="border-radius: 20px;">
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u->obtenerId() ?>">Habitación <?= e($u->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Artículo de la Dotación <span class="text-danger">*</span></label>
                        <select class="form-select" id="dot-articulo-id" style="border-radius: 20px;" required>
                            <option value="">Seleccione artículo...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Cantidad Estándar Esperada <span class="text-danger">*</span></label>
                        <input type="number" step="0.0001" min="0.0001" class="form-control" id="dot-cantidad" placeholder="Ej: 2.0000" style="border-radius: 20px;" required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-dotacion">Guardar Estándar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-inventario.js') ?>"></script>
