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
                        <i class="fa-solid fa-bars f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Gestión de Menú y Navegación Dinámica</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Jerarquía de hasta 3 niveles (Dominio → Módulo → Submódulo) conforme al sistema de diseño Alina y visibilidad gobernada por permisos RBAC. Principio: <strong>MENÚ ≠ AUTORIZACIÓN</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirModalCrearOpcion(null, 1)">
                        <i class="fa-solid fa-folder-plus me-1"></i> Nuevo Dominio (Nivel 1)
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalCrearOpcion(undefined, 2)">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Opción
                    </button>
                </div>
            </div>

            <div class="card-body p-3">
                <div class="table-responsive" id="tabla-gestion-menu">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0">
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
                                    <!-- Fila Nivel 1: Dominio Principal -->
                                    <tr class="table-light border-top border-2 border-primary-subtle"
                                        data-item-id="<?= (int) $principal['id'] ?>"
                                        data-item-padre="null"
                                        data-item-nivel="1">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="bg-primary-subtle text-primary p-1 b-r-6 me-2 d-flex-center">
                                                    <i class="<?= e($principal['icono'] ?? 'fa-solid fa-folder') ?> f-s-16"></i>
                                                </span>
                                                <strong class="f-s-14 text-dark"><?= e($principal['nombre']) ?></strong>
                                                <?= insignia_chip('Nivel 1', 'primary', null, 'ms-2 f-s-10') ?>
                                                <?php if (!empty($principal['es_sistema'])): ?>
                                                    <?= insignia_chip('Sistema', 'info', null, 'ms-1 f-s-10') ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><code class="text-primary f-s-12"><?= e($principal['clave']) ?></code></td>
                                        <td><span class="text-muted f-s-12">—</span></td>
                                        <td>
                                            <?php if (!empty($principal['permiso_codigo'])): ?>
                                                <span class="badge bg-light-secondary font-monospace f-s-11">
                                                    <i class="fa-solid fa-key f-s-10 me-1"></i><?= e($principal['permiso_codigo']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted f-s-11">Abierto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= insignia_badge((string) (int) $principal['orden'], 'secondary') ?>
                                        </td>
                                        <td class="text-center">
                                            <?= insignia_estado($principal['estado'], false, 'f-s-11') ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" title="Mover arriba"
                                                        onclick="moverOrdenOpcion(<?= (int) $principal['id'] ?>, null, 'arriba')">
                                                    <i class="fa-solid fa-chevron-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" title="Mover abajo"
                                                        onclick="moverOrdenOpcion(<?= (int) $principal['id'] ?>, null, 'abajo')">
                                                    <i class="fa-solid fa-chevron-down"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success" title="Agregar módulo (Nivel 2)"
                                                        onclick="abrirModalCrearOpcion(<?= (int) $principal['id'] ?>, 2)">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary" title="Editar dominio"
                                                        onclick='abrirModalEditarOpcion(<?= json_encode($principal, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    <i class="fa-solid fa-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" title="Alternar Estado"
                                                        onclick="alternarEstadoOpcion(<?= (int) $principal['id'] ?>, '<?= e($principal['nombre']) ?>', '<?= e($principal['estado']) ?>')">
                                                    <i class="fa-solid fa-power-off"></i>
                                                </button>
                                                <?php if (empty($principal['es_sistema'])): ?>
                                                    <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                            onclick="eliminarOpcionMenu(<?= (int) $principal['id'] ?>, '<?= e($principal['nombre']) ?>')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Filas Nivel 2: Módulos -->
                                    <?php if (!empty($principal['hijos'])): ?>
                                        <?php foreach ($principal['hijos'] as $hijo): ?>
                                            <tr data-item-id="<?= (int) $hijo['id'] ?>"
                                                data-item-padre="<?= (int) $principal['id'] ?>"
                                                data-item-nivel="2">
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center ps-3">
                                                        <span class="text-secondary me-2">└─</span>
                                                        <span class="text-muted me-2"><i class="<?= e($hijo['icono'] ?? 'fa-solid fa-circle-dot') ?> f-s-14"></i></span>
                                                        <span class="f-s-13 f-w-600"><?= e($hijo['nombre']) ?></span>
                                                        <?= insignia_chip('Nivel 2', 'secondary', null, 'ms-2 f-s-10') ?>
                                                        <?php if (!empty($hijo['es_sistema'])): ?>
                                                            <?= insignia_chip('Sistema', 'info', null, 'ms-1 f-s-10') ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><code class="text-secondary f-s-11"><?= e($hijo['clave']) ?></code></td>
                                                <td><code class="text-dark f-s-12"><?= e($hijo['ruta'] ?? '#') ?></code></td>
                                                <td>
                                                    <?php if (!empty($hijo['permiso_codigo'])): ?>
                                                        <span class="badge bg-light-secondary font-monospace f-s-11" title="<?= e($hijo['permiso_nombre'] ?? '') ?>">
                                                            <i class="fa-solid fa-key f-s-10 me-1"></i><?= e($hijo['permiso_codigo']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted f-s-11">Público</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= insignia_badge((string) (int) $hijo['orden'], 'secondary') ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= insignia_estado($hijo['estado'], false, 'f-s-11') ?>
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-secondary" title="Mover arriba"
                                                                onclick="moverOrdenOpcion(<?= (int) $hijo['id'] ?>, <?= (int) $principal['id'] ?>, 'arriba')">
                                                            <i class="fa-solid fa-chevron-up"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" title="Mover abajo"
                                                                onclick="moverOrdenOpcion(<?= (int) $hijo['id'] ?>, <?= (int) $principal['id'] ?>, 'abajo')">
                                                            <i class="fa-solid fa-chevron-down"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-success" title="Agregar submódulo (Nivel 3)"
                                                                onclick="abrirModalCrearOpcion(<?= (int) $hijo['id'] ?>, 3)">
                                                            <i class="fa-solid fa-plus"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary" title="Editar módulo"
                                                                onclick='abrirModalEditarOpcion(<?= json_encode($hijo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                            <i class="fa-solid fa-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" title="Alternar Estado"
                                                                onclick="alternarEstadoOpcion(<?= (int) $hijo['id'] ?>, '<?= e($hijo['nombre']) ?>', '<?= e($hijo['estado']) ?>')">
                                                            <i class="fa-solid fa-power-off"></i>
                                                        </button>
                                                        <?php if (empty($hijo['es_sistema'])): ?>
                                                            <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                                    onclick="eliminarOpcionMenu(<?= (int) $hijo['id'] ?>, '<?= e($hijo['nombre']) ?>')">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Filas Nivel 3: Submódulos / Funciones -->
                                            <?php if (!empty($hijo['hijos'])): ?>
                                                <?php foreach ($hijo['hijos'] as $sub): ?>
                                                    <tr data-item-id="<?= (int) $sub['id'] ?>"
                                                        data-item-padre="<?= (int) $hijo['id'] ?>"
                                                        data-item-nivel="3">
                                                        <td class="ps-5">
                                                            <div class="d-flex align-items-center ps-4">
                                                                <span class="text-secondary me-2">└── └─</span>
                                                                <span class="text-muted me-2"><i class="<?= e($sub['icono'] ?? 'fa-regular fa-circle') ?> f-s-12"></i></span>
                                                                <span class="f-s-12 text-secondary"><?= e($sub['nombre']) ?></span>
                                                                <?= insignia_chip('Nivel 3', 'info', null, 'ms-2 f-s-10') ?>
                                                                <?php if (!empty($sub['es_sistema'])): ?>
                                                                    <?= insignia_chip('Sistema', 'info', null, 'ms-1 f-s-10') ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td><code class="text-secondary f-s-11"><?= e($sub['clave']) ?></code></td>
                                                        <td><code class="text-dark f-s-12"><?= e($sub['ruta'] ?? '#') ?></code></td>
                                                        <td>
                                                            <?php if (!empty($sub['permiso_codigo'])): ?>
                                                                <span class="badge bg-light-secondary font-monospace f-s-11" title="<?= e($sub['permiso_nombre'] ?? '') ?>">
                                                                    <i class="fa-solid fa-key f-s-10 me-1"></i><?= e($sub['permiso_codigo']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted f-s-11">Público</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= insignia_badge((string) (int) $sub['orden'], 'secondary') ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= insignia_estado($sub['estado'], false, 'f-s-11') ?>
                                                        </td>
                                                        <td class="text-end text-nowrap">
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-secondary" title="Mover arriba"
                                                                        onclick="moverOrdenOpcion(<?= (int) $sub['id'] ?>, <?= (int) $hijo['id'] ?>, 'arriba')">
                                                                    <i class="fa-solid fa-chevron-up"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary" title="Mover abajo"
                                                                        onclick="moverOrdenOpcion(<?= (int) $sub['id'] ?>, <?= (int) $hijo['id'] ?>, 'abajo')">
                                                                    <i class="fa-solid fa-chevron-down"></i>
                                                                </button>
                                                                <!-- Nivel 3 NO permite agregar hijos (máximo 3 niveles) -->
                                                                <button type="button" class="btn btn-outline-primary" title="Editar submódulo"
                                                                        onclick='abrirModalEditarOpcion(<?= json_encode($sub, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                                    <i class="fa-solid fa-pencil"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-warning" title="Alternar Estado"
                                                                        onclick="alternarEstadoOpcion(<?= (int) $sub['id'] ?>, '<?= e($sub['nombre']) ?>', '<?= e($sub['estado']) ?>')">
                                                                    <i class="fa-solid fa-power-off"></i>
                                                                </button>
                                                                <?php if (empty($sub['es_sistema'])): ?>
                                                                    <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                                            onclick="eliminarOpcionMenu(<?= (int) $sub['id'] ?>, '<?= e($sub['nombre']) ?>')">
                                                                        <i class="fa-solid fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="ps-5 text-muted f-s-12 py-2">
                                                <i class="fa-solid fa-circle-info me-1 text-warning"></i>
                                                Este dominio no tiene módulos asignados. No se mostrará en la barra superior hasta que tenga al menos un módulo visible.
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
            <form id="form-opcion-menu" class="app-form app-icon-form" autocomplete="off" novalidate>
                <input type="hidden" id="opcion-id" name="id" value="">
                <?= csrf_campo() ?>

                <div class="modal-body p-4">
                    <!-- Nivel / Categoría Padre -->
                    <div class="mb-3">
                        <label for="opcion-padre-id" class="form-label f-s-13 f-w-600">Nivel y Dependencia Jerárquica</label>
                        <select class="form-select basic-select2" id="opcion-padre-id" name="padre_id"
                                data-placeholder="Dominio Principal (Nivel 1 — Icono Superior)">
                            <option value="">Dominio Principal (Nivel 1 — Icono Superior)</option>
                            <optgroup label="Como Módulo (Nivel 2) bajo Dominio (Nivel 1):">
                                <?php foreach ($principales as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" data-nivel-padre="1">
                                        <?= e($p['nombre']) ?> (<?= e($p['clave']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Como Submódulo (Nivel 3) bajo Módulo (Nivel 2):">
                                <?php foreach ($principales as $p): ?>
                                    <?php if (!empty($p['hijos'])): ?>
                                        <?php foreach ($p['hijos'] as $h): ?>
                                            <option value="<?= (int) $h['id'] ?>" data-nivel-padre="2">
                                                <?= e($p['nombre']) ?> → <?= e($h['nombre']) ?> (<?= e($h['clave']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <div class="form-text f-s-11">
                            Camargo PMS soporta hasta 3 niveles estrictos (Dominio → Módulo → Submódulo) conforme al sistema de diseño Alina. Las opciones de Nivel 3 no admiten subopciones.
                        </div>
                    </div>

                    <div class="row">
                        <!-- Clave Técnica -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-clave" class="form-label f-s-13 f-w-600">Clave Técnica <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-key position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="opcion-clave" name="clave"
                                       placeholder="ej. config_menu" required pattern="/^[a-z0-9_\-]+$/" maxlength="50"
                                       data-pristine-required-message="La clave técnica es obligatoria."
                                       data-pristine-pattern-message="Solo se admiten letras minúsculas, números, guiones y barras bajas."
                                       data-pristine-maxlength-message="La clave técnica no puede superar los 50 caracteres.">
                            </div>
                            <div class="form-text f-s-11">Minúsculas, números y guiones. Estable y única.</div>
                        </div>

                        <!-- Nombre Visible -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-nombre" class="form-label f-s-13 f-w-600">Nombre Visible <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-tag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="opcion-nombre" name="nombre"
                                       placeholder="ej. Gestión de menú" required maxlength="100"
                                       data-pristine-required-message="El nombre visible es obligatorio."
                                       data-pristine-maxlength-message="El nombre visible no puede superar los 100 caracteres.">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Icono Font Awesome -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-icono" class="form-label f-s-13 f-w-600">Icono (Font Awesome)</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-icons position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="opcion-icono" name="icono"
                                       placeholder="fa-solid fa-bars" maxlength="100">
                            </div>
                        </div>

                        <!-- Orden -->
                        <div class="col-md-6 mb-3">
                            <label for="opcion-orden" class="form-label f-s-13 f-w-600">Posición de Orden</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-arrow-down-1-9 position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" class="form-control ps-5" id="opcion-orden" name="orden"
                                       value="1" min="1" max="999"
                                       data-pristine-min-message="La posición de orden mínima es 1."
                                       data-pristine-max-message="La posición de orden máxima es 999.">
                            </div>
                        </div>
                    </div>

                    <!-- Ruta Interna (Solo secundarias) -->
                    <div class="mb-3" id="grupo-campo-ruta" style="display: none;">
                        <label for="opcion-ruta" class="form-label f-s-13 f-w-600">Ruta Local Interna</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-link position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="text" class="form-control ps-5" id="opcion-ruta" name="ruta"
                                   placeholder="/configuracion/menu" maxlength="255">
                        </div>
                        <div class="form-text f-s-11">Debe ser una ruta local (ej. <code>/usuarios</code>). No se aceptan URLs externas ni esquemas script.</div>
                    </div>

                    <div class="row">
                        <!-- Permiso RBAC -->
                        <div class="col-md-8 mb-3">
                            <label for="opcion-permiso-id" class="form-label f-s-13 f-w-600">Permiso RBAC de Visibilidad</label>
                            <select class="form-select basic-select2" id="opcion-permiso-id" name="permiso_id"
                                    data-placeholder="Sin restricción específica (Visible a autenticados)">
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
                            <select class="form-select basic-select2" id="opcion-estado" name="estado">
                                <option value="ACTIVO" selected>ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-opcion">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts específicos del módulo de Gestión de Menú -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-menu.js') ?>"></script>
