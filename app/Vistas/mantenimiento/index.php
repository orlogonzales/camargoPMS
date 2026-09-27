<?php

declare(strict_types=1);

/**
 * Vista principal de Mantenimiento Preventivo, Correctivo e Incidencias Técnicas — Camargo PMS (MANTENIMIENTO-1 / D-077).
 *
 * Principios vinculantes:
 * - INCIDENCIA != ORDEN DE TRABAJO != BLOQUEO OPERATIVO.
 * - Formulario con geometría nativa Alina (app-form app-icon-form, border-radius 20px, select2 42px).
 * - D-071: Font Awesome 6.3.0 exclusivo, variantes suaves de badge Alina (bg-light-*).
 * - Cero degradados, Flatpickr para rangos de fechas, PristineJS para validación síncrona.
 *
 * @var array<int, mixed> $propiedades
 * @var array<int, mixed> $unidades
 * @var array<int, mixed> $personas
 * @var array<int, mixed> $colaboradores
 * @var array<int, mixed> $proveedores
 * @var string $csrf_token
 * @var array<string, bool> $capacidades
 * @var \CamargoPMS\Modelos\Usuario|null $usuario_actual
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Mantenimiento -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-screwdriver-wrench f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Mantenimiento e Incidencias Técnicas</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión de desperfectos, órdenes de trabajo preventivas y correctivas, costeo y bloqueo físico de disponibilidad.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_reportar'])): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btn-abrir-reportar-incidencia">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Reportar Incidencia
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($capacidades['puede_crear_ordenes'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-orden">
                            <i class="fa-solid fa-plus me-1"></i> Nueva Orden de Trabajo
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Mantenimiento -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Incidencias Abiertas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-warning mt-1" id="kpi-incidencias-abiertas">0</h3>
                                    <span class="f-s-11 text-muted">Pendientes de atención</span>
                                </div>
                                <div class="bg-light-warning text-warning p-3 b-r-8">
                                    <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Órdenes en Proceso</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1" id="kpi-ordenes-proceso">0</h3>
                                    <span class="f-s-11 text-muted">Trabajos en ejecución</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-person-digging f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Unidades Bloqueadas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-unidades-bloqueadas">0</h3>
                                    <span class="f-s-11 text-muted">Fuera de servicio</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-ban f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Preventivos del Mes</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-preventivos-mes">0</h3>
                                    <span class="f-s-11 text-muted">Mantenimiento programado</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-calendar-check f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de Navegación Operativa -->
            <div class="card-body p-3">
                <ul class="nav nav-tabs nav-tabs-bottom border-bottom mb-3" id="mantenimientoTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-w-600" id="tab-ordenes" data-bs-toggle="tab" data-bs-target="#panel-ordenes" type="button" role="tab">
                            <i class="fa-solid fa-list-check me-1"></i> Órdenes de Trabajo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-w-600" id="tab-incidencias" data-bs-toggle="tab" data-bs-target="#panel-incidencias" type="button" role="tab">
                            <i class="fa-solid fa-ticket me-1"></i> Incidencias Técnicas
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="mantenimientoTabsContent">
                    <!-- ========================================================= -->
                    <!-- TAB 1: ÓRDENES DE TRABAJO -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade show active" id="panel-ordenes" role="tabpanel">
                        <!-- Filtros de Órdenes -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-3 col-sm-6">
                                <div class="icon-control position-relative">
                                    <i class="fa-solid fa-magnifying-glass ms-3"></i>
                                    <input type="text" class="form-control ps-5" id="filtro-ot-busqueda" placeholder="Buscar por código, título...">
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <select class="form-select basic-select2" id="filtro-ot-estado">
                                    <option value="">Todos los Estados</option>
                                    <option value="BORRADOR">Borrador</option>
                                    <option value="PROGRAMADA">Programada</option>
                                    <option value="EN_PROCESO">En Proceso</option>
                                    <option value="COMPLETADA">Completada</option>
                                    <option value="CANCELADA">Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <select class="form-select basic-select2" id="filtro-ot-tipo">
                                    <option value="">Todos los Tipos</option>
                                    <option value="CORRECTIVO">Correctivo</option>
                                    <option value="PREVENTIVO">Preventivo</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <select class="form-select basic-select2" id="filtro-ot-propiedad">
                                    <option value="">Todas las Propiedades</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-12 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100 h-100" id="btn-recargar-ordenes">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Órdenes -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-ordenes">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Tipo / Prioridad</th>
                                        <th>Propiedad / Unidad</th>
                                        <th>Título y Alcance</th>
                                        <th>Asignación</th>
                                        <th>Programación</th>
                                        <th>Bloqueo</th>
                                        <th>Costos</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-ordenes">
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando órdenes de trabajo...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 2: INCIDENCIAS TÉCNICAS -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade" id="panel-incidencias" role="tabpanel">
                        <!-- Filtros de Incidencias -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-3 col-sm-6">
                                <div class="icon-control position-relative">
                                    <i class="fa-solid fa-magnifying-glass ms-3"></i>
                                    <input type="text" class="form-control ps-5" id="filtro-inc-busqueda" placeholder="Buscar por ticket, título...">
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <select class="form-select basic-select2" id="filtro-inc-estado">
                                    <option value="">Todos los Estados</option>
                                    <option value="REPORTADA">Reportada</option>
                                    <option value="EN_EVALUACION">En Evaluación</option>
                                    <option value="CONVERTIDA_A_ORDEN">Convertida a OT</option>
                                    <option value="RESUELTA_DIRECTA">Resuelta Directa</option>
                                    <option value="DESESTIMADA">Desestimada</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <select class="form-select basic-select2" id="filtro-inc-severidad">
                                    <option value="">Todas las Severidades</option>
                                    <option value="BAJA">Baja</option>
                                    <option value="MEDIA">Media</option>
                                    <option value="ALTA">Alta</option>
                                    <option value="CRITICA">Crítica</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <select class="form-select basic-select2" id="filtro-inc-categoria">
                                    <option value="">Todas las Categorías</option>
                                    <option value="PLOMERIA">Plomería / Gasfitería</option>
                                    <option value="ELECTRICIDAD">Electricidad</option>
                                    <option value="CERRAJERIA">Cerrajería</option>
                                    <option value="CLIMATIZACION">Climatización / AC</option>
                                    <option value="PINTURA">Pintura y Acabados</option>
                                    <option value="MOBILIARIO">Mobiliario</option>
                                    <option value="LIMPIEZA_PROFUNDA">Limpieza Profunda</option>
                                    <option value="ESTRUCTURAL">Estructural</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-12 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100 h-100" id="btn-recargar-incidencias">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Incidencias -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tabla-incidencias">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Severidad</th>
                                        <th>Categoría</th>
                                        <th>Propiedad / Unidad</th>
                                        <th>Título y Reporte</th>
                                        <th>Reportado Por</th>
                                        <th>Fecha Reporte</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-incidencias">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando incidencias técnicas...
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
<!-- MODALES OPERATIVOS (GEOMETRÍA NATIVA ALINA D-075) -->
<!-- ========================================================================= -->

<!-- Modal 1: Reportar Incidencia -->
<div class="modal fade" id="modal-reportar-incidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Reportar Incidencia Técnica
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-reportar-incidencia" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Propiedad Contenedora <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="propiedad_id" id="inc-propiedad-id" required>
                                <option value="">Seleccione una propiedad...</option>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= (int) $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Unidad Específica (Opcional)</label>
                            <select class="form-select basic-select2" name="unidad_id" id="inc-unidad-id">
                                <option value="">Áreas comunes / Infraestructura general</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?= (int) $u->obtenerId() ?>" data-propiedad="<?= (int) $u->obtenerPropiedadId() ?>">
                                        <?= e($u->obtenerCodigo()) ?> — <?= e($u->obtenerNombre()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Categoría Técnica <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="categoria" id="inc-categoria" required>
                                <option value="PLOMERIA">Plomería / Gasfitería</option>
                                <option value="ELECTRICIDAD">Electricidad</option>
                                <option value="CERRAJERIA">Cerrajería</option>
                                <option value="CLIMATIZACION">Climatización / AC</option>
                                <option value="PINTURA">Pintura y Acabados</option>
                                <option value="MOBILIARIO">Mobiliario</option>
                                <option value="LIMPIEZA_PROFUNDA">Limpieza Profunda</option>
                                <option value="ESTRUCTURAL">Estructural</option>
                                <option value="OTRO" selected>Otro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Severidad del Desperfecto <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="severidad" id="inc-severidad" required>
                                <option value="BAJA">Baja — Desperfecto estético menor</option>
                                <option value="MEDIA" selected>Media — Afectación parcial tolerable</option>
                                <option value="ALTA">Alta — Pérdida de confort relevante</option>
                                <option value="CRITICA">Crítica — Inhabitable / Fuga / Riesgo eléctrico</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Reportado Por <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="reportado_por_persona_id" id="inc-reportado-por" required>
                                <option value="">Seleccione al reportador...</option>
                                <?php foreach ($personas as $per): ?>
                                    <option value="<?= (int) $per->obtenerId() ?>">
                                        <?= e($per->obtenerNombreCompleto()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Título Breve del Síntoma <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-heading ms-3"></i>
                                <input type="text" class="form-control ps-5" name="titulo" id="inc-titulo" required minlength="3" placeholder="Ej. Fuga en grifo monomando de baño principal">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Descripción Detallada del Desperfecto <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="descripcion" id="inc-descripcion" rows="3" required minlength="5" placeholder="Detalle observaciones, indicios y hallazgos..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Ubicación Precisa (Opcional)</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-location-dot ms-3"></i>
                                <input type="text" class="form-control ps-5" name="ubicacion_detallada" id="inc-ubicacion" placeholder="Ej. Debajo del lavabo, unión con llave angular">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-incidencia">
                        <i class="fa-solid fa-paper-plane me-1"></i> Registrar Reporte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Formular Nueva Orden de Trabajo -->
<div class="modal fade" id="modal-crear-orden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-screwdriver-wrench text-primary me-2"></i> Formular Orden de Trabajo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-crear-orden" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Tipo de Mantenimiento <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="tipo" id="ot-tipo" required>
                                <option value="CORRECTIVO" selected>Correctivo (Atención a fallas o daños)</option>
                                <option value="PREVENTIVO">Preventivo (Inspección / Mantenimiento periódico)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Prioridad Operacional <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="prioridad" id="ot-prioridad" required>
                                <option value="BAJA">Baja</option>
                                <option value="MEDIA" selected>Media</option>
                                <option value="ALTA">Alta</option>
                                <option value="URGENTE">Urgente</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Propiedad Contenedora <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="propiedad_id" id="ot-propiedad-id" required>
                                <option value="">Seleccione una propiedad...</option>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= (int) $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Unidad Específica</label>
                            <select class="form-select basic-select2" name="unidad_id" id="ot-unidad-id">
                                <option value="">Áreas comunes / Infraestructura general</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?= (int) $u->obtenerId() ?>" data-propiedad="<?= (int) $u->obtenerPropiedadId() ?>">
                                        <?= e($u->obtenerCodigo()) ?> — <?= e($u->obtenerNombre()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Switch Toggle de Bloqueo Operativo (D-077 Ajuste 1) -->
                        <div class="col-12">
                            <div class="p-3 bg-light b-r-8 border">
                                <div class="form-check form-switch d-flex align-items-center gap-2 mb-0">
                                    <input class="form-check-input f-s-18" type="checkbox" name="requiere_bloqueo" id="ot-requiere-bloqueo" value="1">
                                    <label class="form-check-label f-s-13 f-w-600 mb-0" for="ot-requiere-bloqueo">
                                        ¿Requiere inhabilitar la unidad para la venta (Bloqueo en Inventario Diario)?
                                    </label>
                                </div>
                                <small class="text-secondary d-block mt-1">
                                    Al programar la orden, las noches dentro del intervalo semiabierto quedarán bloqueadas de inmediato en el inventario diario.
                                </small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Programada Inicio <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-day ms-3"></i>
                                <input type="date" class="form-control ps-5" name="fecha_programada_inicio" id="ot-fecha-prog-inicio" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Fecha Programada Fin <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-check ms-3"></i>
                                <input type="date" class="form-control ps-5" name="fecha_programada_fin" id="ot-fecha-prog-fin" required>
                            </div>
                        </div>

                        <!-- Fechas de Bloqueo Físico (Visibles solo si requiere_bloqueo) -->
                        <div id="contenedor-fechas-bloqueo" class="col-12 d-none">
                            <div class="row g-2 p-3 bg-light-danger border border-danger-subtle b-r-8">
                                <div class="col-12 mb-1">
                                    <span class="f-s-12 f-w-700 text-danger text-uppercase">
                                        <i class="fa-solid fa-shield-halved me-1"></i> Intervalo de Inhabilitación Física en Inventario
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label f-s-12 f-w-600 text-danger">Fecha Inicio Bloqueo <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="fecha_bloqueo_inicio" id="ot-fecha-bloq-inicio">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label f-s-12 f-w-600 text-danger">Fecha Fin Bloqueo (Exclusivo) <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="fecha_bloqueo_fin" id="ot-fecha-bloq-fin">
                                    <small class="text-muted f-s-11">La fecha de fin queda disponible para check-in.</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Título de la Orden <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-heading ms-3"></i>
                                <input type="text" class="form-control ps-5" name="titulo" id="ot-titulo" required minlength="3" placeholder="Ej. Cambio de mezcladora y prueba hidráulica">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Descripción y Especificaciones <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="descripcion" id="ot-descripcion" rows="3" required minlength="5" placeholder="Instrucciones técnicas, materiales requeridos..."></textarea>
                        </div>

                        <!-- Asignación de Responsable -->
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Modalidad de Asignación <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" name="tipo_asignacion" id="ot-tipo-asignacion" required>
                                <option value="INTERNO" selected>Personal Interno</option>
                                <option value="EXTERNO">Proveedor Externo</option>
                                <option value="MIXTO">Mixto (Interno + Externo)</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="grp-colaborador">
                            <label class="form-label f-s-13 f-w-600">Colaborador Responsable</label>
                            <select class="form-select basic-select2" name="colaborador_asignado_id" id="ot-colaborador-id">
                                <option value="">Sin asignar aún...</option>
                                <?php foreach ($colaboradores as $c): ?>
                                    <option value="<?= (int) $c->obtenerId() ?>"><?= e($c->obtenerNombreCompleto()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="grp-proveedor">
                            <label class="form-label f-s-13 f-w-600">Proveedor Contratado</label>
                            <select class="form-select basic-select2" name="proveedor_id" id="ot-proveedor-id">
                                <option value="">Sin proveedor externo...</option>
                                <?php foreach ($proveedores as $pv): ?>
                                    <option value="<?= (int) $pv->obtenerId() ?>"><?= e($pv->obtenerRazonSocial()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600">Costo Estimado (S/)</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-coins ms-3"></i>
                                <input type="number" step="0.01" class="form-control ps-5" name="costo_estimado" id="ot-costo-estimado" value="0.00" min="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-orden">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Borrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Asentar Costos Reales -->
<div class="modal fade" id="modal-costos-orden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-calculator text-success me-2"></i> Asentar Costos Reales
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-costos-orden" class="app-form app-icon-form">
                <input type="hidden" id="costos-orden-id" name="orden_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Materiales y Repuestos (S/) <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-boxes-stacked ms-3"></i>
                                <input type="number" step="0.01" class="form-control ps-5" name="costo_materiales" id="costos-materiales" value="0.00" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600">Mano de Obra / Honorarios (S/) <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-hand-holding-dollar ms-3"></i>
                                <input type="number" step="0.01" class="form-control ps-5" name="costo_mano_obra" id="costos-mano-obra" value="0.00" min="0" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light b-r-8 border d-flex justify-content-between align-items-center">
                                <span class="f-s-14 f-w-600">Costo Total Calculado (BCMath):</span>
                                <span class="f-s-16 f-w-700 text-primary" id="costos-total-calculado">S/ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-guardar-costos">
                        <i class="fa-solid fa-check me-1"></i> Asentar Costos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Prorrogar Bloqueo Operativo -->
<div class="modal fade" id="modal-prorrogar-orden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-calendar-plus text-warning me-2"></i> Prorrogar Bloqueo Operativo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-prorrogar-orden" class="app-form app-icon-form">
                <input type="hidden" id="prorroga-orden-id" name="orden_id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 mb-3 f-s-12">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        Se validará atómicamente que el nuevo intervalo no colisione con reservas de huéspedes ni contratos de arrendamiento.
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Fecha Fin Actual del Bloqueo</label>
                        <input type="date" class="form-control bg-light" id="prorroga-fecha-actual" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Nueva Fecha Fin (Exclusiva) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-check ms-3"></i>
                            <input type="date" class="form-control ps-5" name="nueva_fecha_fin" id="prorroga-nueva-fecha" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-confirmar-prorroga">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Confirmar Prórroga
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 5: Inspección de Detalle y Trazabilidad Histórica -->
<div class="modal fade" id="modal-detalle-orden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700" id="detalle-ot-titulo-modal">
                    <i class="fa-solid fa-clipboard-check text-primary me-2"></i> Inspección de Orden de Trabajo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detalle-ot-cuerpo">
                <!-- Se inyecta reactivamente -->
            </div>
            <div class="modal-footer bg-light py-2 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-mantenimiento.js') ?>"></script>
