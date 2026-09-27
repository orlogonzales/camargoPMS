<?php

declare(strict_types=1);

/**
 * Vista del Motor Central de Disponibilidad e Inventario Diario — Camargo PMS (DISPONIBILIDAD-1).
 *
 * Principios vinculantes:
 * - D-066: INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL. Intervalo semiabierto [entrada, salida).
 * - D-067: Modelo Híbrido Sparse con UNIQUE(unidad_id, fecha) en InnoDB.
 * - P-005 PENDIENTE: Cero monedas, precios, tarifas o impuestos en esta fase.
 *
 * @var array{puede_ver: bool, puede_bloquear: bool, puede_liberar: bool} $capacidades
 * @var array<int, array{id: int, codigo: string, nombre: string, zona_horaria: ?string}> $propiedades
 * @var array<int, array{id: int, codigo: string, nombre: string}> $tiposUnidad
 * @var string $fechaEntradaDefecto
 * @var string $fechaSalidaDefecto
 * @var string $mesDefecto
 * @var string $zonaHorariaPMS
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-calendar-days f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Motor Central de Disponibilidad</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Inventario diario hotelero sparse y control de ocupación. Zona horaria PMS: <strong><?= e($zonaHorariaPMS) ?></strong> (D-066 / D-067).
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_bloquear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-nuevo-bloqueo">
                            <i class="fa-solid fa-lock me-1"></i> Bloquear Unidad
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Inventario en Tiempo Real -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white b-r-8 border d-flex align-items-center">
                            <div class="bg-primary-subtle text-primary p-2 b-r-6 me-3">
                                <i class="fa-solid fa-door-open f-s-20"></i>
                            </div>
                            <div>
                                <span class="text-secondary f-s-12 text-uppercase d-block">Unidades Totales</span>
                                <span class="f-s-18 f-w-700 text-dark" id="kpi-total-unidades">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white b-r-8 border d-flex align-items-center">
                            <div class="bg-success-subtle text-success p-2 b-r-6 me-3">
                                <i class="fa-solid fa-circle-check f-s-20"></i>
                            </div>
                            <div>
                                <span class="text-secondary f-s-12 text-uppercase d-block">Disponibles</span>
                                <span class="f-s-18 f-w-700 text-success" id="kpi-unidades-disponibles">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white b-r-8 border d-flex align-items-center">
                            <div class="bg-danger-subtle text-danger p-2 b-r-6 me-3">
                                <i class="fa-solid fa-lock f-s-20"></i>
                            </div>
                            <div>
                                <span class="text-secondary f-s-12 text-uppercase d-block">Bloqueadas / Ocupadas</span>
                                <span class="f-s-18 f-w-700 text-danger" id="kpi-unidades-bloqueadas">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-white b-r-8 border d-flex align-items-center">
                            <div class="bg-info-subtle text-info p-2 b-r-6 me-3">
                                <i class="fa-solid fa-percent f-s-20"></i>
                            </div>
                            <div>
                                <span class="text-secondary f-s-12 text-uppercase d-block">Tasa de Disponibilidad</span>
                                <span class="f-s-18 f-w-700 text-dark" id="kpi-tasa-disponibilidad">-%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de Navegación del Módulo -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-2 bg-white" id="pms-disponibilidad-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-s-14 f-w-600" id="tab-consulta-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-consulta" type="button" role="tab">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Consulta de Disponibilidad
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600" id="tab-matriz-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-matriz" type="button" role="tab">
                            <i class="fa-solid fa-calendar-days me-1"></i> Matriz Mensual (Rack)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600" id="tab-bloqueos-btn" data-bs-toggle="tab"
                                data-bs-target="#tab-bloqueos" type="button" role="tab">
                            <i class="fa-solid fa-lock me-1"></i> Bloqueos Activos
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-3" id="pms-disponibilidad-content">
                    <!-- ======================================================== -->
                    <!-- TAB 1: CONSULTA DE DISPONIBILIDAD -->
                    <!-- ======================================================== -->
                    <div class="tab-pane fade show active" id="tab-consulta" role="tabpanel">
                        <!-- Barra de Consulta Hotelera -->
                        <div class="card border mb-3">
                            <div class="card-body p-3 bg-light-subtle">
                                <form id="form-consulta-disponibilidad" class="app-form app-icon-form row g-2 align-items-end">
                                    <div class="col-md-4 col-12">
                                        <label for="consulta-rango-fechas" class="form-label f-s-12 f-w-600 mb-1">
                                            <i class="fa-solid fa-calendar-days me-1 text-primary"></i> Intervalo de Estadía (Check-in → Check-out)
                                        </label>
                                        <div class="icon-control position-relative">
                                            <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-3 text-primary"></i>
                                            <input type="text" class="form-control ps-5" id="consulta-rango-fechas"
                                                   placeholder="Seleccione intervalo..."
                                                   data-provider="rangepicker"
                                                   data-target-inicio="#consulta-fecha-entrada"
                                                   data-target-fin="#consulta-fecha-salida"
                                                   autocomplete="off" readonly>
                                        </div>
                                        <input type="hidden" id="consulta-fecha-entrada" name="fecha_entrada" value="<?= e($fechaEntradaDefecto) ?>">
                                        <input type="hidden" id="consulta-fecha-salida" name="fecha_salida" value="<?= e($fechaSalidaDefecto) ?>">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <label for="consulta-propiedad-id" class="form-label f-s-12 f-w-600 mb-1">Propiedad</label>
                                        <select class="form-select basic-select2" id="consulta-propiedad-id" name="propiedad_id" data-placeholder="Todas las propiedades">
                                            <option value="">Todas las propiedades</option>
                                            <?php foreach ($propiedades as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> (<?= e($p['codigo']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <label for="consulta-tipo-unidad-id" class="form-label f-s-12 f-w-600 mb-1">Tipología</label>
                                        <select class="form-select basic-select2" id="consulta-tipo-unidad-id" name="tipo_unidad_id" data-placeholder="Todos los tipos">
                                            <option value="">Todos los tipos</option>
                                            <?php foreach ($tiposUnidad as $t): ?>
                                                <option value="<?= (int) $t['id'] ?>"><?= e($t['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 col-12 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1" id="btn-ejecutar-consulta">
                                            <i class="fa-solid fa-magnifying-glass me-1"></i> Consultar
                                        </button>
                                        <div class="form-check d-flex align-items-center gap-1 pt-1">
                                            <input class="form-check-input f-s-18 mb-1" type="checkbox" id="check-solo-disponibles">
                                            <label class="form-check-label f-s-12 ms-1 text-nowrap" for="check-solo-disponibles">Solo libres</label>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Banner Informativo del Intervalo -->
                        <div class="alert alert-info py-2 px-3 f-s-13 mb-3 d-flex align-items-center justify-content-between" id="banner-intervalo-info">
                            <div>
                                <i class="fa-solid fa-circle-info me-1"></i>
                                Intervalo consultado: <strong id="info-rango-texto"><?= e($fechaEntradaDefecto) ?> al <?= e($fechaSalidaDefecto) ?></strong>
                                (<span id="info-noches-texto">1 noche</span>).
                                Modelo: <strong>[entrada, salida)</strong>. La noche de salida queda liberada para check-in.
                            </div>
                            <span class="badge bg-light-primary" id="badge-total-consultadas">0 unidades</span>
                        </div>

                        <!-- Tabla de Resultados de Disponibilidad -->
                        <div class="table-responsive border b-r-8">
                            <table class="table table-hover align-middle mb-0" id="tabla-disponibilidad">
                                <thead class="table-light">
                                    <tr class="f-s-12 text-uppercase text-secondary">
                                        <th style="width: 14%;">Código Unidad</th>
                                        <th style="width: 22%;">Nombre</th>
                                        <th style="width: 18%;">Propiedad</th>
                                        <th style="width: 14%;">Tipología</th>
                                        <th style="width: 12%;">Capacidad</th>
                                        <th style="width: 10%;">Estado</th>
                                        <th style="width: 10%;" class="text-end pe-3">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-disponibilidad">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <span class="spinner-border spinner-border-sm me-2"></span> Cargando disponibilidad del inventario...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- TAB 2: MATRIZ MENSUAL (RACK DE OCUPACIÓN) -->
                    <!-- ======================================================== -->
                    <div class="tab-pane fade" id="tab-matriz" role="tabpanel">
                        <div class="row g-2 mb-3 align-items-center bg-light-subtle p-2 b-r-8 border">
                            <div class="col-md-4 col-12">
                                <label for="matriz-propiedad-id" class="form-label f-s-12 f-w-600 mb-1">Propiedad a Visualizar</label>
                                <select class="form-select basic-select2" id="matriz-propiedad-id">
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> (<?= e($p['codigo']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="matriz-mes-selector" class="form-label f-s-12 f-w-600 mb-1">Mes Operacional</label>
                                <div class="icon-control position-relative">
                                    <i class="fa-solid fa-calendar position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                    <input type="month" class="form-control ps-5" id="matriz-mes-selector" value="<?= e($mesDefecto) ?>">
                                </div>
                            </div>
                            <div class="col-md-2 col-6 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-recargar-matriz">
                                    <i class="fa-solid fa-arrows-rotate me-1"></i> Cargar Matriz
                                </button>
                            </div>
                            <div class="col-md-3 col-12 d-flex justify-content-md-end align-items-center gap-2 pt-2 pt-md-0">
                                <span class="badge bg-light-success f-s-11">
                                    <i class="fa-solid fa-circle me-1"></i> Libre
                                </span>
                                <span class="badge bg-light-danger f-s-11">
                                    <i class="fa-solid fa-circle me-1"></i> Bloqueado
                                </span>
                                <span class="badge bg-light-warning f-s-11">
                                    <i class="fa-solid fa-circle me-1"></i> Mantenimiento
                                </span>
                            </div>
                        </div>

                        <!-- Contenedor con scroll horizontal para la matriz de 30/31 días -->
                        <div class="table-responsive border b-r-8" id="contenedor-rack-calendario" style="max-height: 520px;">
                            <table class="table table-bordered table-sm align-middle mb-0 text-center" id="tabla-rack">
                                <thead class="table-light sticky-top" id="thead-rack">
                                    <!-- Inyectado dinámicamente -->
                                </thead>
                                <tbody id="tbody-rack">
                                    <tr>
                                        <td class="py-4 text-muted">Seleccione una propiedad para visualizar el rack.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================================================== -->
                    <!-- TAB 3: MAESTRO DE BLOQUEOS -->
                    <!-- ======================================================== -->
                    <div class="tab-pane fade" id="tab-bloqueos" role="tabpanel">
                        <div class="row g-2 mb-3 bg-light-subtle p-2 b-r-8 border align-items-center">
                            <div class="col-md-3 col-6">
                                <select class="form-select basic-select2" id="filtro-bloqueos-propiedad" data-placeholder="Todas las propiedades">
                                    <option value="">Todas las propiedades</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select basic-select2" id="filtro-bloqueos-estado" data-placeholder="Todos los estados">
                                    <option value="">Todos los estados</option>
                                    <option value="ACTIVO" selected>ACTIVO (Vigentes)</option>
                                    <option value="LIBERADO">LIBERADO (Históricos)</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select basic-select2" id="filtro-bloqueos-tipo" data-placeholder="Todos los tipos">
                                    <option value="">Todos los tipos</option>
                                    <option value="BLOQUEO_MANUAL">Bloqueo Manual</option>
                                    <option value="MANTENIMIENTO">Mantenimiento</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-bloqueos">
                                    <i class="fa-solid fa-arrows-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive border b-r-8">
                            <table class="table table-hover align-middle mb-0" id="tabla-bloqueos-maestro">
                                <thead class="table-light">
                                    <tr class="f-s-12 text-uppercase text-secondary">
                                        <th style="width: 8%;">ID</th>
                                        <th style="width: 14%;">Unidad</th>
                                        <th style="width: 16%;">Propiedad</th>
                                        <th style="width: 22%;">Intervalo de Bloqueo</th>
                                        <th style="width: 10%;">Tipo</th>
                                        <th style="width: 10%;">Estado</th>
                                        <th style="width: 10%;">Creador</th>
                                        <th style="width: 10%;" class="text-end pe-3">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-bloqueos-maestro">
                                    <!-- Inyectado dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3" id="bloqueos-paginacion-bar">
                            <span class="f-s-13 text-secondary" id="bloqueos-info-paginacion">Mostrando 0 de 0</span>
                            <div class="btn-group btn-group-sm" id="bloqueos-btn-paginacion"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CREAR BLOQUEO DE UNIDAD -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-crear-bloqueo" tabindex="-1" aria-labelledby="modal-crear-bloqueo-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-crear-bloqueo-label">
                    <i class="fa-solid fa-lock text-primary me-1"></i> Bloquear Unidad en Inventario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-bloqueo" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-3">
                    <div class="alert alert-warning py-2 px-3 f-s-12 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        El bloqueo inhabilita comercialmente la unidad para el rango semiabierto <strong>[fecha_inicio, fecha_fin)</strong>.
                    </div>

                    <!-- Selector de Propiedad para filtrar unidades -->
                    <div class="mb-3">
                        <label for="bloqueo-propiedad-id" class="form-label f-s-13 f-w-600">Propiedad <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="bloqueo-propiedad-id" data-placeholder="Seleccione una propiedad..." required>
                            <option value="">Seleccione una propiedad...</option>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> (<?= e($p['codigo']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selector de Unidad -->
                    <div class="mb-3">
                        <label for="bloqueo-unidad-id" class="form-label f-s-13 f-w-600">Unidad Física <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="bloqueo-unidad-id" name="unidad_id" data-placeholder="Primero seleccione propiedad..." required disabled>
                            <option value="">Primero seleccione una propiedad...</option>
                        </select>
                    </div>

                    <!-- Fechas Rango con Alina Range Picker -->
                    <div class="mb-3">
                        <label for="bloqueo-rango-fechas" class="form-label f-s-13 f-w-600">
                            <i class="fa-solid fa-calendar-days me-1 text-primary"></i> Intervalo del Bloqueo <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-3 text-primary"></i>
                            <input type="text" class="form-control ps-5" id="bloqueo-rango-fechas"
                                   placeholder="Seleccione fecha de inicio y fin..."
                                   data-provider="rangepicker"
                                   data-target-inicio="#bloqueo-fecha-inicio"
                                   data-target-fin="#bloqueo-fecha-fin"
                                   autocomplete="off" readonly required>
                        </div>
                        <input type="hidden" id="bloqueo-fecha-inicio" name="fecha_inicio" required>
                        <input type="hidden" id="bloqueo-fecha-fin" name="fecha_fin" required>
                        <div class="form-text f-s-11 text-muted">Intervalo semiabierto [entrada, salida). La noche de salida queda liberada para check-in.</div>
                    </div>

                    <!-- Tipo de Bloqueo -->
                    <div class="mb-3">
                        <label for="bloqueo-tipo" class="form-label f-s-13 f-w-600">Tipo de Bloqueo <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="bloqueo-tipo" name="tipo" required>
                            <option value="BLOQUEO_MANUAL">Bloqueo Manual / Administrativo</option>
                            <option value="MANTENIMIENTO">Mantenimiento Técnico o Reparación</option>
                        </select>
                    </div>

                    <!-- Motivo -->
                    <div class="mb-2">
                        <label for="bloqueo-motivo" class="form-label f-s-13 f-w-600">Motivo del Bloqueo <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="bloqueo-motivo" name="motivo" rows="2"
                                      placeholder="Detalle la razón del bloqueo para auditoría y trazabilidad..." required maxlength="255"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-bloqueo">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-guardar-bloqueo"></span>
                        <i class="fa-solid fa-lock me-1"></i> Confirmar Bloqueo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CONFIRMAR LIBERACIÓN DE BLOQUEO -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-liberar-bloqueo" tabindex="-1" aria-labelledby="modal-liberar-bloqueo-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h6 class="modal-title f-s-14 f-w-700 text-danger" id="modal-liberar-bloqueo-label">
                    <i class="fa-solid fa-lock-open me-1"></i> Liberar Bloqueo
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-liberar-bloqueo" class="app-form app-icon-form">
                <input type="hidden" id="liberar-bloqueo-id" value="">
                <div class="modal-body p-3">
                    <p class="f-s-13 text-secondary mb-2" id="mensaje-confirmacion-liberar">
                        ¿Confirma la liberación del bloqueo? Las fechas ocupadas volverán a quedar inmediatamente disponibles.
                    </p>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-liberacion">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-liberar-bloqueo"></span>
                        Liberar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts de Interfaz -->
<script src="<?= url_ruta('/assets/vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_ruta('/assets/js/gestion-disponibilidad.js') ?>"></script>
