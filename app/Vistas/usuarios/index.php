<?php

declare(strict_types=1);

/**
 * Vista de Administración de Usuarios — Camargo PMS.
 *
 * Principio: PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL
 * Gestión visual e interactiva de cuentas humanas: listado, filtros, creación,
 * cambio de estado, restablecimiento de contraseña, roles y sesiones activas.
 *
 * @var array<int, array{id: int, codigo: string, nombre: string, es_superadministrador: bool}> $roles
 * @var array{puede_crear: bool, puede_editar: bool, puede_bloquear: bool, puede_asignar_roles: bool, puede_revocar_roles: bool} $capacidades
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
                        <i class="fa-solid fa-users f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Administración de Usuarios</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Cuentas humanas de acceso vinculadas a Personas. Principio: <strong>PERSONA ≠ USUARIO ≠ ROL</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-usuario">
                            <i class="fa-solid fa-user-plus me-1"></i> Nuevo Usuario
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-usuario"
                                   placeholder="Buscar por usuario, persona o documento..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select basic-select2 select-clear" id="filtro-estado-usuario" data-placeholder="Todos los estados">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                            <option value="BLOQUEADO">BLOQUEADO</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select basic-select2 select-clear" id="filtro-rol-usuario" data-placeholder="Todos los roles">
                            <option value="">Todos los roles</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-recargar-usuarios" title="Refrescar datos">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Usuarios -->
            <div class="card-body p-0">
                <div class="table-responsive" id="contenedor-tabla-usuarios">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-usuarios">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th style="width: 18%;">Usuario</th>
                                <th style="width: 25%;">Persona Asociada</th>
                                <th style="width: 12%;">Roles</th>
                                <th style="width: 10%;" class="text-center">Estado</th>
                                <th style="width: 13%;">Último Acceso</th>
                                <th style="width: 8%;" class="text-center">Sesiones</th>
                                <th style="width: 14%;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-usuarios">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Cargando listado de usuarios...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pie de Tabla y Paginación -->
            <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center border-top">
                <div class="f-s-12 text-secondary mb-2 mb-md-0" id="info-paginacion-usuarios">
                    Mostrando usuarios...
                </div>
                <nav aria-label="Paginación de usuarios">
                    <ul class="pagination pagination-sm mb-0" id="paginacion-usuarios">
                        <!-- Paginación dinámica -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES ADMINISTRATIVOS REUTILIZABLES -->
<!-- ========================================================================= -->

<!-- 1. Modal: Nuevo Usuario -->
<div class="modal fade" id="modal-crear-usuario" tabindex="-1" aria-labelledby="modal-crear-usuario-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-crear-usuario-titulo">
                    <i class="fa-solid fa-user-plus me-1"></i> Alta de Nueva Cuenta de Usuario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-usuario" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <!-- Selector de Persona -->
                    <div class="mb-3">
                        <label for="crear-persona-id" class="form-label f-s-13 f-w-600">
                            Persona Natural <span class="text-danger">*</span>
                        </label>
                        <select class="form-select basic-select2" id="crear-persona-id" name="persona_id" required
                                data-placeholder="Seleccionar Persona disponible...">
                            <option value="">-- Seleccionar Persona disponible --</option>
                        </select>
                        <div class="form-text f-s-11 text-muted">
                            Solo se listan personas activas que aún no cuentan con una cuenta de usuario asignada. Si la persona no existe, debe registrarse previamente en el maestro de Personas.
                        </div>
                    </div>

                    <!-- Nombre de Usuario -->
                    <div class="mb-3">
                        <label for="crear-nombre-usuario" class="form-label f-s-13 f-w-600">
                            Nombre de Usuario <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control ps-5" id="crear-nombre-usuario" name="nombre_usuario"
                                   required minlength="3" maxlength="50" pattern="^[a-z0-9._-]+$"
                                   placeholder="ej. juan.perez" autocomplete="off">
                        </div>
                        <div class="form-text f-s-11 text-muted">
                            De 3 a 50 caracteres (letras minúsculas, números, puntos, guiones y guiones bajos).
                        </div>
                    </div>

                    <!-- Contraseña Inicial -->
                    <div class="mb-3">
                        <label for="crear-contrasena" class="form-label f-s-13 f-w-600">
                            Contraseña Inicial <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-lock position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="password" class="form-control ps-5" id="crear-contrasena" name="contrasena"
                                   required minlength="12" maxlength="1024"
                                   placeholder="Mínimo 12 caracteres" autocomplete="new-password">
                        </div>
                        <div class="form-text f-s-11 text-muted">
                            Longitud mínima: 12 caracteres (se admiten espacios y símbolos UTF-8; hash PASSWORD_DEFAULT).
                        </div>
                    </div>

                    <!-- Confirmar Contraseña -->
                    <div class="mb-3">
                        <label for="crear-confirmar-contrasena" class="form-label f-s-13 f-w-600">
                            Confirmar Contraseña <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-shield-halved position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="password" class="form-control ps-5" id="crear-confirmar-contrasena" name="confirmar_contrasena"
                                   required minlength="12" maxlength="1024"
                                   placeholder="Reingrese la contraseña" autocomplete="new-password">
                        </div>
                    </div>

                    <!-- Rol Inicial Opcional -->
                    <div class="mb-3">
                        <label for="crear-rol-inicial" class="form-label f-s-13 f-w-600">
                            Rol Inicial (Opcional)
                        </label>
                        <select class="form-select basic-select2" id="crear-rol-inicial" name="rol_id"
                                data-placeholder="Sin rol inicial (Asignar posteriormente)">
                            <option value="">-- Sin rol inicial (Asignar posteriormente) --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Estado Inicial -->
                    <div class="mb-2">
                        <label for="crear-estado" class="form-label f-s-13 f-w-600">Estado Inicial</label>
                        <select class="form-select basic-select2" id="crear-estado" name="estado">
                            <option value="ACTIVO" selected>ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-2 px-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-crear-usuario">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Modal: Restablecer Contraseña Administrativa -->
<div class="modal fade" id="modal-restablecer-clave" tabindex="-1" aria-labelledby="modal-reset-clave-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-reset-clave-titulo">
                    <i class="fa-solid fa-key me-1"></i> Restablecer Contraseña Administrativa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-restablecer-clave" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="reset-usuario-id" name="usuario_id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning f-s-12 py-2 px-3 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Esta acción generará una nueva contraseña para <strong id="reset-nombre-usuario-txt">usuario</strong>.
                        <strong>Todas sus sesiones activas serán revocadas</strong> inmediatamente por seguridad.
                    </div>

                    <!-- Nueva Contraseña -->
                    <div class="mb-3">
                        <label for="reset-nueva-contrasena" class="form-label f-s-13 f-w-600">
                            Nueva Contraseña <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-lock position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="password" class="form-control ps-5" id="reset-nueva-contrasena" name="nueva_contrasena"
                                   required minlength="12" maxlength="1024"
                                   placeholder="Mínimo 12 caracteres" autocomplete="new-password">
                        </div>
                        <div class="form-text f-s-11 text-muted">
                            Mínimo 12 caracteres (Unicode y espacios preservados; PASSWORD_DEFAULT).
                        </div>
                    </div>

                    <!-- Confirmar Nueva Contraseña -->
                    <div class="mb-3">
                        <label for="reset-confirmar-contrasena" class="form-label f-s-13 f-w-600">
                            Confirmar Nueva Contraseña <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-shield-halved position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="password" class="form-control ps-5" id="reset-confirmar-contrasena" name="confirmar_contrasena"
                                   required minlength="12" maxlength="1024"
                                   placeholder="Reingrese la nueva contraseña" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-2 px-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark" id="btn-guardar-reset-clave">
                        <i class="fa-solid fa-check me-1"></i> Restablecer Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Modal: Gestión de Roles del Usuario -->
<div class="modal fade" id="modal-roles-usuario" tabindex="-1" aria-labelledby="modal-roles-usuario-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-roles-usuario-titulo">
                    <i class="fa-solid fa-shield-halved me-1"></i> Roles del Usuario: <span id="roles-modal-usuario-txt"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="roles-modal-usuario-id">

                <!-- Roles Asignados -->
                <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-2">Roles Actuales</h6>
                <div id="roles-modal-lista-asignados" class="mb-4">
                    <p class="text-muted f-s-12 mb-0">Cargando roles...</p>
                </div>

                <!-- Asignar Nuevo Rol -->
                <?php if (!empty($capacidades['puede_asignar_roles'])): ?>
                    <hr class="my-3">
                    <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-2">Asignar Rol Adicional</h6>
                    <form id="form-asignar-rol" class="app-form app-icon-form row g-2 align-items-center">
                        <div class="col-8">
                            <select class="form-select basic-select2" id="select-nuevo-rol" required
                                    data-placeholder="Seleccionar Rol...">
                                <option value="">-- Seleccionar Rol --</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100" id="btn-ejecutar-asignar-rol">
                                <i class="fa-solid fa-plus me-1"></i> Asignar
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- 4. Modal: Sesiones Activas del Usuario -->
<div class="modal fade" id="modal-sesiones-usuario" tabindex="-1" aria-labelledby="modal-sesiones-usuario-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-info-subtle py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modal-sesiones-usuario-titulo">
                    <i class="fa-solid fa-laptop me-1"></i> Sesiones Activas: <span id="sesiones-modal-usuario-txt"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="sesiones-modal-usuario-id">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-secondary f-s-12 mb-0">
                        Historial de sesiones vigentes. <strong>Nunca se exponen hashes de tokens ni cookies.</strong>
                    </p>
                    <?php if (!empty($capacidades['puede_editar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-cerrar-todas-sesiones">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Cerrar Todas las Sesiones
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped table-hover align-middle mb-0">
                        <thead class="table-light f-s-11 text-uppercase text-secondary">
                            <tr>
                                <th>ID</th>
                                <th>Dirección IP</th>
                                <th>Navegador / Dispositivo</th>
                                <th>Iniciada</th>
                                <th>Última Actividad</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-sesiones-usuario">
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">Cargando sesiones...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos del módulo de Gestión de Usuarios -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-usuarios.js') ?>"></script>
