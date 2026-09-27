<?php

declare(strict_types=1);

/**
 * Vista de Administración de Roles y Permisos — Camargo PMS.
 *
 * Principio: ROL DE AUTORIZACIÓN ≠ CARGO LABORAL
 * Principio: MENÚ ≠ AUTORIZACIÓN
 * Gestión visual e interactiva del catálogo de roles, asignación matricial de permisos
 * agrupados por módulo funcional y consulta de usuarios vinculados.
 *
 * @var array{puede_crear: bool, puede_editar: bool, puede_asignar: bool, puede_revocar: bool, puede_ver_permisos: bool} $capacidades
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
                        <i class="fa-solid fa-shield-halved f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Roles y Permisos</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Catálogo de roles, asignación matricial de permisos por módulo y usuarios vinculados. Principio: <strong>ROL ≠ CARGO LABORAL</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-rol">
                            <i class="fa-solid fa-plus me-1"></i> Nuevo Rol
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-7 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-rol"
                                   placeholder="Buscar por nombre, clave técnica o descripción..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda-rol" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-8">
                        <select class="form-select form-select-sm basic-select2 select-clear" id="filtro-estado-rol" data-placeholder="Todos los estados">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-4 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-recargar-roles" title="Refrescar datos">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Recargar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Roles -->
            <div class="card-body p-0">
                <div class="table-responsive" id="contenedor-tabla-roles">
                    <table class="table table-hover align-middle mb-0" id="tabla-roles">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th style="width: 25%;">Rol / Clave</th>
                                <th style="width: 15%;">Tipo</th>
                                <th style="width: 10%;" class="text-center">Estado</th>
                                <th style="width: 15%;" class="text-center">Permisos</th>
                                <th style="width: 15%;" class="text-center">Usuarios</th>
                                <th style="width: 20%;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-roles">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Cargando listado de roles...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white border-top py-2 px-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="conteo-roles-info">Mostrando roles registrados</small>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Crear Nuevo Rol -->
<div class="modal fade" id="modal-crear-rol" tabindex="-1" aria-labelledby="modal-crear-rol-titulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title f-s-16 f-w-600 text-white" id="modal-crear-rol-titulo">
                    <i class="fa-solid fa-shield-halved me-2"></i> Crear Nuevo Rol
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-rol" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="crear-rol-codigo" class="form-label f-s-13 f-w-600">
                            Clave Técnica <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-key position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-5 text-uppercase" id="crear-rol-codigo" name="codigo"
                                   placeholder="EJ: AUDITOR_FINANCIERO" required
                                   pattern="^[A-Za-z0-9_]{3,50}$"
                                   data-pristine-pattern-message="La clave debe tener 3 a 50 letras mayúsculas, números o guiones bajos."
                                   data-pristine-required-message="La clave técnica es obligatoria." autocomplete="off">
                        </div>
                        <div class="form-text f-s-11 text-muted">Identificador único inmutable en mayúsculas (letras, números y guión bajo).</div>
                    </div>

                    <div class="mb-3">
                        <label for="crear-rol-nombre" class="form-label f-s-13 f-w-600">
                            Nombre del Rol <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-shield-halved position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-5" id="crear-rol-nombre" name="nombre"
                                   placeholder="EJ: Auditor Financiero" required minlength="2" maxlength="100"
                                   data-pristine-required-message="El nombre del rol es obligatorio."
                                   data-pristine-minlength-message="El nombre debe tener al menos 2 caracteres."
                                   data-pristine-maxlength-message="El nombre no puede exceder 100 caracteres." autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="crear-rol-descripcion" class="form-label f-s-13 f-w-600">Descripción</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-align-left position-absolute top-0 start-0 mt-2 ms-3 text-secondary"></i>
                            <textarea class="form-control form-control-sm ps-5" id="crear-rol-descripcion" name="descripcion" rows="3"
                                      maxlength="255" placeholder="Propósito funcional del rol y alcance de sus atribuciones..."></textarea>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="crear-rol-estado" class="form-label f-s-13 f-w-600">Estado Inicial</label>
                        <select class="form-select form-select-sm basic-select2" id="crear-rol-estado" name="estado">
                            <option value="ACTIVO" selected>ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-crear-rol">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Editar Rol -->
<div class="modal fade" id="modal-editar-rol" tabindex="-1" aria-labelledby="modal-editar-rol-titulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title f-s-16 f-w-600 text-white" id="modal-editar-rol-titulo">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Editar Rol
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-editar-rol" novalidate>
                <input type="hidden" id="editar-rol-id" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editar-rol-codigo" class="form-label f-s-13 f-w-600">Clave Técnica</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-key position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-5 text-uppercase" id="editar-rol-codigo" name="codigo" readonly>
                        </div>
                        <div class="form-text f-s-11 text-muted" id="editar-rol-codigo-ayuda">La clave técnica identifica las comprobaciones de código.</div>
                    </div>

                    <div class="mb-3">
                        <label for="editar-rol-nombre" class="form-label f-s-13 f-w-600">
                            Nombre del Rol <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-shield-halved position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-5" id="editar-rol-nombre" name="nombre" required minlength="2" maxlength="100"
                                   data-pristine-required-message="El nombre del rol es obligatorio."
                                   data-pristine-minlength-message="El nombre debe tener al menos 2 caracteres."
                                   data-pristine-maxlength-message="El nombre no puede exceder 100 caracteres." autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editar-rol-descripcion" class="form-label f-s-13 f-w-600">Descripción</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-align-left position-absolute top-0 start-0 mt-2 ms-3 text-secondary"></i>
                            <textarea class="form-control form-control-sm ps-5" id="editar-rol-descripcion" name="descripcion" rows="3" maxlength="255"></textarea>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="editar-rol-estado" class="form-label f-s-13 f-w-600">Estado Administrativo</label>
                        <select class="form-select form-select-sm basic-select2" id="editar-rol-estado" name="estado">
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                        <div class="form-text f-s-11 text-muted" id="editar-rol-estado-ayuda"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-editar-rol">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Matriz de Permisos por Módulo -->
<div class="modal fade" id="modal-gestionar-permisos" tabindex="-1" aria-labelledby="modal-gestionar-permisos-titulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div>
                    <h5 class="modal-title f-s-16 f-w-600 text-white mb-0" id="modal-gestionar-permisos-titulo">
                        <i class="fa-solid fa-key me-2"></i> Matriz de Permisos
                    </h5>
                    <div class="f-s-12 text-light-subtle mt-1" id="subtitulo-permisos-rol">
                        Configurando permisos del rol
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="permisos-modal-rol-id">

                <!-- Alerta informativa para Superadministrador -->
                <div id="alerta-superadmin-permisos" class="alert alert-warning d-none py-2 px-3 mb-3 f-s-13">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation f-s-20 me-2 text-warning"></i>
                        <div>
                            <strong>Rol Estructural SUPERADMINISTRADOR:</strong>
                            Los permisos críticos de administración no pueden revocarse para preservar la integridad del sistema.
                        </div>
                    </div>
                </div>

                <!-- Barra de control rápido -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-2 bg-light rounded border">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-marcar-todos-permisos">
                            <i class="fa-solid fa-check-double me-1"></i> Marcar Todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-desmarcar-todos-permisos">
                            <i class="fa-solid fa-square-xmark me-1"></i> Desmarcar Todos
                        </button>
                    </div>
                    <div class="text-secondary f-s-12">
                        <span id="contador-permisos-seleccionados" class="badge bg-light-primary">0</span> permisos seleccionados
                    </div>
                </div>

                <!-- Contenedor dinámico de permisos agrupados por módulo -->
                <div id="contenedor-modulos-permisos">
                    <div class="text-center py-4 text-muted">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Cargando catálogo de permisos...
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
                <span class="text-muted f-s-12">
                    <i class="fa-solid fa-circle-info me-1"></i> Los cambios se aplican de inmediato en tiempo real sin requerir re-login.
                </span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-permisos-rol">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 4: Usuarios con este Rol (Solo Lectura) -->
<div class="modal fade" id="modal-usuarios-rol" tabindex="-1" aria-labelledby="modal-usuarios-rol-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3 px-4 border-bottom">
                <div>
                    <h5 class="modal-title f-s-16 f-w-600 mb-0" id="modal-usuarios-rol-titulo">
                        <i class="fa-solid fa-users me-2 text-primary"></i> Usuarios Vinculados
                    </h5>
                    <div class="f-s-12 text-muted mt-1" id="subtitulo-usuarios-rol">
                        Listado de cuentas que ostentan este rol
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light f-s-12 text-uppercase text-secondary">
                            <tr>
                                <th>Usuario</th>
                                <th>Persona Asociada</th>
                                <th class="text-center">Estado Usuario</th>
                                <th>Asignado En</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-usuarios-rol">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Cargando usuarios...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-4">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias y Módulo JavaScript -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-roles.js') ?>"></script>
