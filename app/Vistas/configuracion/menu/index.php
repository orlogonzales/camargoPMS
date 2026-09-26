<?php

declare(strict_types=1);

/**
 * Vista de Gestión de Menú Dinámico — Camargo PMS.
 *
 * Permite visualizar la jerarquía autorizada de 2 niveles, crear/editar opciones,
 * alternar estados de activación y reordenar transaccionalmente.
 *
 * @var array{principales: array<int, array<string, mixed>>, permisos: array<int, array{id: int, codigo: string, nombre: string, modulo: string}>} $datosGestion
 */

$principales = $datosGestion['principales'] ?? [];
$permisos = $datosGestion['permisos'] ?? [];
?>
<div class="row">
    <div class="col-12">
        <div class="card equal-card mb-4 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="ti ti-menu-2 f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Gestión de Menú y Navegación Dinámica</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Jerarquía de 2 niveles (Alina) y visibilidad gobernada por permisos RBAC. Principio: <strong>MENÚ ≠ AUTORIZACIÓN</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirModalCrearOpcion(null)">
                        <i class="ti ti-folder-plus me-1"></i> Nueva Categoría
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalCrearOpcion()">
                        <i class="ti ti-plus me-1"></i> Nueva Opción
                    </button>
                </div>
            </div>

            <div class="card-body p-3">
                <div class="table-responsive" id="tabla-gestion-menu">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th style="width: 35%;">Opción / Jerarquía</th>
                                <th style="width: 15%;">Clave Técnica</th>
                                <th style="width: 15%;">Ruta Local</th>
                                <th style="width: 15%;">Permiso RBAC</th>
                                <th style="width: 5%;" class="text-center">Orden</th>
                                <th style="width: 5%;" class="text-center">Estado</th>
                                <th style="width: 10%;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($principales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No se encontraron opciones de menú registradas en el sistema.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($principales as $principal): ?>
                                    <!-- Fila Nivel 1: Categoría Principal -->
                                    <tr class="table-light border-top border-2 border-primary-subtle"
                                        data-item-id="<?= (int) $principal['id'] ?>"
                                        data-item-padre="null">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="bg-secondary-subtle text-dark p-1 b-r-6 me-2 d-flex-center">
                                                    <i class="<?= e($principal['icono'] ?? 'ti ti-folder') ?> f-s-16"></i>
                                                </span>
                                                <strong class="f-s-14 text-dark"><?= e($principal['nombre']) ?></strong>
                                                <?php if (!empty($principal['es_sistema'])): ?>
                                                    <span class="badge bg-info-subtle text-info ms-2 f-s-10">Sistema</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><code class="text-primary f-s-12"><?= e($principal['clave']) ?></code></td>
                                        <td><span class="text-muted f-s-12">—</span></td>
                                        <td>
                                            <?php if (!empty($principal['permiso_codigo'])): ?>
                                                <span class="badge bg-light text-dark border f-s-11">
                                                    <i class="ti ti-key f-s-10 me-1"></i><?= e($principal['permiso_codigo']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted f-s-11">Abierto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-dark"><?= (int) $principal['orden'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $principal['estado'] === 'ACTIVO' ? 'success' : 'danger' ?>-subtle text-<?= $principal['estado'] === 'ACTIVO' ? 'success' : 'danger' ?>">
                                                <?= e($principal['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" title="Mover arriba"
                                                        onclick="moverOrdenOpcion(<?= (int) $principal['id'] ?>, null, 'arriba')">
                                                    <i class="ti ti-chevron-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" title="Mover abajo"
                                                        onclick="moverOrdenOpcion(<?= (int) $principal['id'] ?>, null, 'abajo')">
                                                    <i class="ti ti-chevron-down"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success" title="Agregar opción secundaria"
                                                        onclick="abrirModalCrearOpcion(<?= (int) $principal['id'] ?>)">
                                                    <i class="ti ti-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary" title="Editar categoría"
                                                        onclick='abrirModalEditarOpcion(<?= json_encode($principal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="ti ti-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" title="Alternar Estado"
                                                        onclick="alternarEstadoOpcion(<?= (int) $principal['id'] ?>, '<?= e($principal['nombre']) ?>', '<?= e($principal['estado']) ?>')">
                                                    <i class="ti ti-power"></i>
                                                </button>
                                                <?php if (empty($principal['es_sistema'])): ?>
                                                    <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                            onclick="eliminarOpcionMenu(<?= (int) $principal['id'] ?>, '<?= e($principal['nombre']) ?>')">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Filas Nivel 2: Opciones Secundarias -->
                                    <?php if (!empty($principal['hijos'])): ?>
                                        <?php foreach ($principal['hijos'] as $hijo): ?>
                                            <tr data-item-id="<?= (int) $hijo['id'] ?>"
                                                data-item-padre="<?= (int) $principal['id'] ?>">
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center ps-3">
                                                        <span class="text-secondary me-2">└─</span>
                                                        <span class="text-muted me-2"><i class="<?= e($hijo['icono'] ?? 'ti ti-point') ?> f-s-14"></i></span>
                                                        <span class="f-s-13"><?= e($hijo['nombre']) ?></span>
                                                        <?php if (!empty($hijo['es_sistema'])): ?>
                                                            <span class="badge bg-info-subtle text-info ms-2 f-s-10">Sistema</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><code class="text-secondary f-s-11"><?= e($hijo['clave']) ?></code></td>
                                                <td><code class="text-dark f-s-12"><?= e($hijo['ruta'] ?? '#') ?></code></td>
                                                <td>
                                                    <?php if (!empty($hijo['permiso_codigo'])): ?>
                                                        <span class="badge bg-light text-dark border f-s-11" title="<?= e($hijo['permiso_nombre'] ?? '') ?>">
                                                            <i class="ti ti-key f-s-10 me-1"></i><?= e($hijo['permiso_codigo']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted f-s-11">Público</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-secondary border"><?= (int) $hijo['orden'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?= $hijo['estado'] === 'ACTIVO' ? 'success' : 'danger' ?>-subtle text-<?= $hijo['estado'] === 'ACTIVO' ? 'success' : 'danger' ?>">
                                                        <?= e($hijo['estado']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-secondary" title="Mover arriba"
                                                                onclick="moverOrdenOpcion(<?= (int) $hijo['id'] ?>, <?= (int) $principal['id'] ?>, 'arriba')">
                                                            <i class="ti ti-chevron-up"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" title="Mover abajo"
                                                                onclick="moverOrdenOpcion(<?= (int) $hijo['id'] ?>, <?= (int) $principal['id'] ?>, 'abajo')">
                                                            <i class="ti ti-chevron-down"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary" title="Editar opción"
                                                                onclick='abrirModalEditarOpcion(<?= json_encode($hijo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                            <i class="ti ti-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" title="Alternar Estado"
                                                                onclick="alternarEstadoOpcion(<?= (int) $hijo['id'] ?>, '<?= e($hijo['nombre']) ?>', '<?= e($hijo['estado']) ?>')">
                                                            <i class="ti ti-power"></i>
                                                        </button>
                                                        <?php if (empty($hijo['es_sistema'])): ?>
                                                            <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                                    onclick="eliminarOpcionMenu(<?= (int) $hijo['id'] ?>, '<?= e($hijo['nombre']) ?>')">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="ps-5 text-muted f-s-12 py-2">
                                                <i class="ti ti-info-circle me-1 text-warning"></i>
                                                Esta categoría no tiene opciones secundarias asignadas. No se mostrará en la navegación hasta que tenga al menos una opción secundaria visible.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Creación / Edición de Opción de Menú -->
<div class="modal fade" id="modal-opcion-menu" tabindex="-1" aria-labelledby="modal-opcion-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title f-w-700" id="modal-opcion-titulo">Opción de Menú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-opcion-menu" autocomplete="off">
                <input type="hidden" id="opcion-id" name="id" value="">
                <?= csrf_campo() ?>

                <div class="modal-body p-4">
                    <!-- Nivel / Categoría Padre -->
                    <div class="mb-3">
                        <label for="opcion-padre-id" class="form-label f-s-13 f-w-600">Nivel de Menú</label>
                        <select class="form-select form-select-sm" id="opcion-padre-id" name="padre_id">
                            <option value="">Categoría Principal (Nivel 1 — Icono Superior)</option>
                            <optgroup label="Asignar como Opción Secundaria (Nivel 2) bajo:">
                                <?php foreach ($principales as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">
                                        <?= e($p['nombre']) ?> (<?= e($p['clave']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <div class="form-text f-s-11">
                            Camargo PMS maneja un máximo estricto de dos niveles conforme al contrato de navegación Alina.
                        </div>
                    </div>

                    <div class="row">
                        <!-- Clave Técnica -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-clave" class="form-label f-s-13 f-w-600">Clave Técnica <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="opcion-clave" name="clave"
                                   placeholder="ej. config_menu" required pattern="[a-z0-9_\-]+" maxlength="50">
                            <div class="form-text f-s-11">Minúsculas, números y guiones. Estable y única.</div>
                        </div>

                        <!-- Nombre Visible -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-nombre" class="form-label f-s-13 f-w-600">Nombre Visible <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="opcion-nombre" name="nombre"
                                   placeholder="ej. Gestión de menú" required maxlength="100">
                        </div>
                    </div>

                    <div class="row">
                        <!-- Icono Tabler -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-icono" class="form-label f-s-13 f-w-600">Icono (Tabler)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="ti ti-icons"></i></span>
                                <input type="text" class="form-control" id="opcion-icono" name="icono"
                                       placeholder="ti ti-menu-2" maxlength="100">
                            </div>
                        </div>

                        <!-- Orden -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-orden" class="form-label f-s-13 f-w-600">Posición de Orden</label>
                            <input type="number" class="form-control form-control-sm" id="opcion-orden" name="orden"
                                   value="1" min="1" max="999">
                        </div>
                    </div>

                    <!-- Ruta Interna (Solo secundarias) -->
                    <div class="mb-3" id="grupo-campo-ruta" style="display: none;">
                        <label for="opcion-ruta" class="form-label f-s-13 f-w-600">Ruta Local Interna</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">/</span>
                            <input type="text" class="form-control" id="opcion-ruta" name="ruta"
                                   placeholder="configuracion/menu" maxlength="255">
                        </div>
                        <div class="form-text f-s-11">Debe ser una ruta local (ej. <code>/usuarios</code>). No se aceptan URLs externas ni esquemas script.</div>
                    </div>

                    <div class="row">
                        <!-- Permiso RBAC -->
                        <div class="col-md-8 mb-3">
                            <label for="opcion-permiso-id" class="form-label f-s-13 f-w-600">Permiso RBAC de Visibilidad</label>
                            <select class="form-select form-select-sm" id="opcion-permiso-id" name="permiso_id">
                                <option value="">Sin restricción específica (Visible a autenticados)</option>
                                <?php foreach ($permisos as $perm): ?>
                                    <option value="<?= (int) $perm['id'] ?>">
                                        <?= e($perm['codigo']) ?> — <?= e($perm['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text f-s-11">El Superadministrador ve todas las opciones activas sin restricción.</div>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-4 mb-3">
                            <label for="opcion-estado" class="form-label f-s-13 f-w-600">Estado</label>
                            <select class="form-select form-select-sm" id="opcion-estado" name="estado">
                                <option value="ACTIVO" selected>ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-opcion">
                        <i class="ti ti-device-floppy me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts específicos del módulo de Gestión de Menú -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('js/gestion-menu.js') ?>"></script>
