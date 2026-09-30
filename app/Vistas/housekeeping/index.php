<?php

declare(strict_types=1);

/**
 * Vista principal de Housekeeping, Pisos y Control de Lencería — Camargo PMS (HOUSEKEEPING-1 / D-083).
 *
 * Principios vinculantes D-083:
 * - ESTADO COMERCIAL != ESTADO DE OCUPACIÓN != ESTADO DE LIMPIEZA != DISPONIBILIDAD != MANTENIMIENTO.
 * - UNIDAD LISTA PARA CHECK-IN = Condición derivada dinámica (VR - Vacant Ready).
 * - Cero duplicación de verdad en base de datos.
 * - Checklists congelados e inmutables con evaluación tri-valente (CONFORME, NO_CONFORME, NO_APLICA).
 * - Consumo de amenities vinculado a Kardex (INVENTARIO-1).
 * - Preservación de stock y diferencias explícitas en lavandería textil.
 *
 * @var array<int, array<string, mixed>> $propiedades
 * @var array<int, string> $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\Usuario|null $usuario
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Housekeeping -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-broom f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Housekeeping, Pisos y Control de Lencería</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Rack operacional derivado en vivo, inspección hotelera con checklist inmutable y gestión textil de lavandería.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (in_array('housekeeping.tareas.gestionar', $permisos, true)): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nueva-tarea">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Tarea Manual
                    </button>
                    <?php endif; ?>
                    <?php if (in_array('housekeeping.lavanderia.gestionar', $permisos, true)): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-despachar-lote">
                        <i class="fa-solid fa-shirt me-1"></i> Despacho Lavandería
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs Operativos de Habitaciones (Ajuste Vinculante D-083) -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">VR — Listas Check-in</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-vr">0</h3>
                                    <span class="f-s-11 text-muted">Vacant Ready (Aptas)</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-circle-check f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">VD — Sucias / En Limpieza</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-vd">0</h3>
                                    <span class="f-s-11 text-muted">Vacant Dirty</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-soap f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">VCL — Por Inspeccionar</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-warning mt-1" id="kpi-vcl">0</h3>
                                    <span class="f-s-11 text-muted">Vacant Clean</span>
                                </div>
                                <div class="bg-light-warning text-warning p-3 b-r-8">
                                    <i class="fa-solid fa-clipboard-check f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">OOO — Mantenimiento</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-secondary mt-1" id="kpi-ooo">0</h3>
                                    <span class="f-s-11 text-muted">Out of Order</span>
                                </div>
                                <div class="bg-light text-secondary p-3 b-r-8 border">
                                    <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación por Pestañas -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom border-bottom px-3 pt-2" id="hkTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-w-600 f-s-14" id="tab-rack-btn" data-bs-toggle="tab" data-bs-target="#tab-rack" type="button" role="tab">
                            <i class="fa-solid fa-table-cells me-2"></i>Rack Operacional de Pisos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600 f-s-14" id="tab-tareas-btn" data-bs-toggle="tab" data-bs-target="#tab-tareas" type="button" role="tab">
                            <i class="fa-solid fa-list-check me-2"></i>Tareas de Limpieza
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600 f-s-14" id="tab-lavanderia-btn" data-bs-toggle="tab" data-bs-target="#tab-lavanderia" type="button" role="tab">
                            <i class="fa-solid fa-shirt me-2"></i>Control de Lencería y Lavandería
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-3" id="hkTabsContent">
                    <!-- PANEL 1: RACK OPERACIONAL DE PISOS -->
                    <div class="tab-pane fade show active" id="tab-rack" role="tabpanel">
                        <!-- Filtros del Rack -->
                        <div class="row g-2 mb-3 align-items-center">
                            <div class="col-md-4 col-sm-6">
                                <select class="form-select form-select-sm" id="filtro-rack-propiedad">
                                    <option value="">Todas las Propiedades</option>
                                    <?php foreach ($propiedades as $prop): ?>
                                    <option value="<?= (int) $prop['id'] ?>"><?= e($prop['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <select class="form-select form-select-sm" id="filtro-rack-piso">
                                    <option value="">Todos los Pisos</option>
                                    <option value="1">Piso 1</option>
                                    <option value="2">Piso 2</option>
                                    <option value="3">Piso 3</option>
                                    <option value="4">Piso 4</option>
                                    <option value="5">Piso 5</option>
                                </select>
                            </div>
                            <div class="col-md-5 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-rack">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar Rack
                                </button>
                            </div>
                        </div>

                        <!-- Contenedor del Rack de Pisos Grid -->
                        <div id="contenedor-rack-pisos" class="row g-3">
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="text-muted f-s-13 mt-2">Cargando estado operacional en vivo de unidades...</p>
                            </div>
                        </div>
                    </div>

                    <!-- PANEL 2: TABLERO DE TAREAS -->
                    <div class="tab-pane fade" id="tab-tareas" role="tabpanel">
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <input type="date" class="form-control form-control-sm" id="filtro-tarea-fecha" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="filtro-tarea-estado">
                                    <option value="">Todos los Estados</option>
                                    <option value="PENDIENTE">PENDIENTE</option>
                                    <option value="ASIGNADA">ASIGNADA</option>
                                    <option value="EN_PROCESO">EN_PROCESO</option>
                                    <option value="POR_INSPECCIONAR">POR_INSPECCIONAR</option>
                                    <option value="RECHAZADA">RECHAZADA</option>
                                    <option value="COMPLETADA">COMPLETADA</option>
                                    <option value="CANCELADA">CANCELADA</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="filtro-tarea-tipo">
                                    <option value="">Todos los Tipos</option>
                                    <option value="SALIDA">SALIDA (Check-out)</option>
                                    <option value="ESTADIA">ESTADÍA (Stay-over)</option>
                                    <option value="PROFUNDA">PROFUNDA</option>
                                    <option value="RETOQUE">RETOQUE</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-tareas">
                                    <i class="fa-solid fa-filter me-1"></i> Filtrar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Tareas -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-tareas">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Habitación</th>
                                        <th>Tipo</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <th>Camarera</th>
                                        <th>Fecha Prog.</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-tareas">
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Cargando tareas...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PANEL 3: CONTROL DE LENCERÍA Y LAVANDERÍA -->
                    <div class="tab-pane fade" id="tab-lavanderia" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="f-s-16 f-w-700 mb-0">Lotes de Despacho y Retorno Textil</h5>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-lavanderia">
                                <i class="fa-solid fa-rotate me-1"></i> Actualizar
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-lotes">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código Lote</th>
                                        <th>Propiedad</th>
                                        <th>Almacén Origen</th>
                                        <th>Destino Lavandería</th>
                                        <th>Fecha Despacho</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-lotes">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando lotes de lavandería...</td>
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
<!-- MODALES OPERACIONALES DE HOUSEKEEPING (ALINA THEME)                       -->
<!-- ========================================================================= -->

<!-- Modal: Crear Tarea Manual -->
<div class="modal fade" id="modal-crear-tarea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">Nueva Tarea de Limpieza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-crear-tarea" class="app-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Habitación / Unidad <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="tarea-unidad-id" required>
                            <option value="">Seleccione una habitación...</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Tipo de Intervención</label>
                            <select class="form-select form-select-sm" id="tarea-tipo" required>
                                <option value="SALIDA">SALIDA (Check-out)</option>
                                <option value="ESTADIA">ESTADÍA (Stay-over)</option>
                                <option value="PROFUNDA">LIMPIEZA PROFUNDA</option>
                                <option value="RETOQUE">RETOQUE RÁPIDO</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label f-s-13 f-w-600">Prioridad</label>
                            <select class="form-select form-select-sm" id="tarea-prioridad">
                                <option value="BAJA">BAJA</option>
                                <option value="MEDIA" selected>MEDIA</option>
                                <option value="ALTA">ALTA</option>
                                <option value="URGENTE">URGENTE</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Fecha Programada</label>
                        <input type="date" class="form-control form-control-sm" id="tarea-fecha" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Observaciones / Instrucciones</label>
                        <textarea class="form-control form-control-sm" id="tarea-notas" rows="3" placeholder="Instrucciones específicas para la camarera..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-tarea">
                        <i class="fa-solid fa-check me-1"></i> Guardar Tarea
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Inspeccionar Tarea (Checklist y Calificación) -->
<div class="modal fade" id="modal-inspeccionar-tarea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <div>
                    <h5 class="modal-title f-s-16 f-w-700 mb-0">Inspección de Habitación</h5>
                    <span class="f-s-12 text-muted" id="insp-subtitulo">Evaluación de checklist de control de calidad</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="insp-tarea-id">
                <div class="alert alert-info py-2 px-3 f-s-12 mb-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    <strong>Regla vinculante D-083:</strong> Para calificar como <em>LIMPIA_INSPECCIONADA (VR)</em>, todos los puntos de control marcados como <strong>CRÍTICOS</strong> deben encontrarse en estado <strong>CONFORME</strong>. Ante cualquier no conformidad crítica, la habitación se rechazará para retoque.
                </div>

                <!-- Tabla de Puntos de Control Checklist -->
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 15%;">Categoría</th>
                                <th style="width: 35%;">Punto de Control</th>
                                <th style="width: 10%;" class="text-center">Crítico</th>
                                <th style="width: 25%;">Calificación</th>
                                <th style="width: 15%;">Observación</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-checklist-items">
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600">Notas de Supervisión / Motivo de Rechazo</label>
                    <textarea class="form-control form-control-sm" id="insp-notas" rows="2" placeholder="Detalle de observaciones o razones si no aprueba..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-top d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-light-danger btn-sm" id="btn-rechazar-inspeccion">
                        <i class="fa-solid fa-xmark me-1"></i> Rechazar (Retoque)
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-light-secondary btn-sm me-2" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-aprobar-inspeccion">
                        <i class="fa-solid fa-check me-1"></i> Aprobar Habitación (VR)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Finalizar Limpieza y Registro de Consumos -->
<div class="modal fade" id="modal-finalizar-limpieza" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">Finalizar Limpieza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fin-tarea-id">
                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600">Condición Operacional Encontrada</label>
                    <select class="form-select form-select-sm" id="fin-condicion">
                        <option value="NINGUNA" selected>Normal / Ninguna Incidencia</option>
                        <option value="DND">No Molestar (DND)</option>
                        <option value="SIN_ACCESO">Sin Acceso / Cerrada</option>
                        <option value="RECHAZO_HUESPED">Rechazo por Huésped</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600">Notas de Limpieza</label>
                    <textarea class="form-control form-control-sm" id="fin-notas" rows="2" placeholder="Observaciones de la camarera..."></textarea>
                </div>
                <p class="f-s-12 text-muted mb-0">
                    Al confirmar, la tarea pasará a <strong>POR_INSPECCIONAR</strong> y la habitación a <strong>LIMPIA_POR_INSPECCIONAR (VCL)</strong>.
                </p>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-confirmar-finalizacion">
                    <i class="fa-solid fa-paper-plane me-1"></i> Enviar a Inspección
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reportar Desperfecto Físico a Mantenimiento -->
<div class="modal fade" id="modal-reportar-desperfecto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">Levantar Incidencia a Mantenimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="desp-tarea-id">
                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600">Título del Desperfecto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="desp-titulo" placeholder="Ej: Fuga en grifería de lavamanos" required>
                </div>
                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600">Prioridad</label>
                    <select class="form-select form-select-sm" id="desp-prioridad">
                        <option value="BAJA">BAJA</option>
                        <option value="MEDIA" selected>MEDIA</option>
                        <option value="ALTA">ALTA</option>
                        <option value="URGENTE">URGENTE</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label f-s-13 f-w-600">Descripción Detallada <span class="text-danger">*</span></label>
                    <textarea class="form-control form-control-sm" id="desp-descripcion" rows="3" placeholder="Explique la falla o daño físico detectado..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-enviar-desperfecto">
                    <i class="fa-solid fa-wrench me-1"></i> Reportar a Mantenimiento
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/gestion-housekeeping.js"></script>
