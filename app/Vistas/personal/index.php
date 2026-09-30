<?php

declare(strict_types=1);

/**
 * Vista de Gestión Administrativa de Personal y RR.HH. — Camargo PMS (PERSONAL-1A).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array<int, array<string, mixed>> $colaboradores
 * @var array<int, array<string, mixed>> $cargos
 * @var array<int, array<string, mixed>> $tiposDocumento
 * @var array<int, array<string, mixed>> $paises
 * @var array<string, mixed> $paisDefault
 * @var array<int, array<string, mixed>> $departamentos
 * @var \CamargoPMS\Modelos\Empresa|null $empresaPrincipal
 * @var array{total: int, activos: int, inactivos: int, con_usuario: int} $resumen
 * @var array{puede_gestionar: bool} $permisos
 * @var string $csrf_token
 * @var string $titulo
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">
<input type="hidden" id="default-pais-id" value="<?= (int) $paisDefault['id'] ?>">
<input type="hidden" id="default-pais-iso" value="<?= e($paisDefault['codigo_iso2']) ?>">

<!-- Banner de Gobernanza y Axioma de Dominio -->
<div class="alert alert-info border-0 shadow-sm b-r-12 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center">
        <span class="bg-light-info text-info p-2 b-r-8 me-3 d-flex-center">
            <i class="fa-solid fa-users f-s-20"></i>
        </span>
        <div>
            <div class="f-w-700 f-s-14 text-dark">Principio Rector de Dominio Laboral</div>
            <div class="f-s-12 text-secondary">
                <code>PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL</code>. La identidad humana es central e independiente del vínculo laboral y de las credenciales de acceso.
            </div>
        </div>
    </div>
    <?php if ($empresaPrincipal !== null): ?>
    <div class="badge bg-white text-dark border px-3 py-2 text-end">
        <span class="text-secondary d-block f-s-11">Entidad Empleadora Principal:</span>
        <strong class="f-s-12"><i class="fa-solid fa-building me-1 text-primary"></i> <?= e($empresaPrincipal->obtenerRazonSocial()) ?></strong>
        <span class="text-muted f-s-11 ms-1">(RUC <?= e($empresaPrincipal->obtenerNumeroDocumento()) ?>)</span>
    </div>
    <?php endif; ?>
</div>

<!-- Tarjetas de Métricas / KPIs -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="avatar-md bg-light-primary text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-users f-s-22"></i>
                </div>
                <div>
                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Total Colaboradores</span>
                    <h3 class="mb-0 f-w-700 f-s-22 text-dark" id="kpi-total"><?= (int) $resumen['total'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="avatar-md bg-light-success text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-user-check f-s-22"></i>
                </div>
                <div>
                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Activos en Nómina</span>
                    <h3 class="mb-0 f-w-700 f-s-22 text-success" id="kpi-activos"><?= (int) $resumen['activos'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="avatar-md bg-light-secondary text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-user-xmark f-s-22"></i>
                </div>
                <div>
                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Inactivos / Cesados</span>
                    <h3 class="mb-0 f-w-700 f-s-22 text-secondary" id="kpi-inactivos"><?= (int) $resumen['inactivos'] ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card equal-card shadow-sm border-0 b-r-16">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="avatar-md bg-light-info text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-key f-s-22"></i>
                </div>
                <div>
                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Con Acceso al Sistema</span>
                    <h3 class="mb-0 f-w-700 f-s-22 text-info" id="kpi-con-usuario"><?= (int) $resumen['con_usuario'] ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Principal de Personal -->
<div class="row">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-address-card f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Maestro de Personal y Legajo Laboral</h4>
                        <p class="text-secondary f-s-13 mb-0">Gestión de colaboradores, historial de cargos, ceses y reingresos sin duplicidad de identidad.</p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if ($permisos['puede_gestionar']): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nuevo-colaborador">
                        <i class="fa-solid fa-user-plus me-1"></i> Alta de Colaborador
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-personal">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Filtros Rápidos -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-personal"
                                   placeholder="Buscar por código, documento, nombres o usuario..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado-personal">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">Solo Activos</option>
                            <option value="INACTIVO">Solo Inactivos / Cesados</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-cargo-personal">
                            <option value="">Todos los cargos</option>
                            <?php foreach ($cargos as $cg): ?>
                                <option value="<?= (int) $cg['id'] ?>"><?= e($cg['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-12">
                        <select class="form-select form-select-sm" id="filtro-usuario-personal">
                            <option value="">Acceso Sistema</option>
                            <option value="con_usuario">Con Usuario</option>
                            <option value="sin_usuario">Sin Usuario</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla de Colaboradores -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-personal">
                        <thead class="bg-light text-secondary f-s-12 text-uppercase">
                            <tr>
                                <th class="ps-4">Colaborador</th>
                                <th>Documento</th>
                                <th>Cargo Actual</th>
                                <th>Contacto</th>
                                <th class="text-center">Acceso Sistema</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-personal">
                            <?php if (empty($colaboradores)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-users-slash f-s-32 d-block mb-2 text-secondary"></i>
                                    No se encontraron colaboradores registrados en el sistema.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($colaboradores as $col): ?>
                            <?php 
                                $esActivo = ($col['estado'] ?? '') === 'ACTIVO';
                                $tieneUsuario = !empty($col['usuario_id']);
                                $nombreCompleto = trim(($col['nombres'] ?? '') . ' ' . ($col['apellidos'] ?? ''));
                            ?>
                            <tr data-colaborador-id="<?= (int) $col['id'] ?>"
                                data-persona-id="<?= (int) $col['persona_id'] ?>"
                                data-cargo-id="<?= (int) ($col['cargo_id'] ?? 0) ?>"
                                data-tiene-usuario="<?= $tieneUsuario ? '1' : '0' ?>"
                                data-estado="<?= e($col['estado'] ?? 'INACTIVO') ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center border" style="width: 40px; height: 40px; min-width: 40px;">
                                            <i class="fa-solid fa-user-tie <?= $esActivo ? 'text-primary' : 'text-secondary' ?> f-s-18"></i>
                                        </div>
                                        <div>
                                            <span class="f-w-700 f-s-14 text-dark d-block"><?= e($nombreCompleto) ?></span>
                                            <span class="badge bg-light text-primary border f-s-11 mt-1"><?= e($col['codigo'] ?? 'S/C') ?></span>
                                            <?php if (!empty($col['fecha_inicio_episodio'])): ?>
                                                <span class="text-muted f-s-11 ms-1" title="Inicio episodio laboral">
                                                    <i class="fa-regular fa-calendar me-1"></i><?= e($col['fecha_inicio_episodio']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($col['numero_documento'])): ?>
                                        <span class="badge bg-light-primary text-primary f-s-12">
                                            <?= e($col['tipo_documento'] ?: 'DOC') ?>: <?= e($col['numero_documento']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted f-s-12">Sin documento</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($col['cargo_nombre'])): ?>
                                        <div class="f-s-13 f-w-600 text-dark"><?= e($col['cargo_nombre']) ?></div>
                                        <?php if (!empty($col['cargo_departamento'])): ?>
                                            <span class="text-secondary f-s-11 d-block"><i class="fa-solid fa-briefcase me-1"></i><?= e($col['cargo_departamento']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light-secondary text-secondary f-s-11">Sin cargo activo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-secondary f-s-12">
                                        <?php if (!empty($col['email'])): ?>
                                            <div><i class="fa-regular fa-envelope me-1 text-primary"></i><?= e($col['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($col['telefono'])): ?>
                                            <div><i class="fa-solid fa-phone me-1 text-success"></i><?= e($col['telefono']) ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($col['email']) && empty($col['telefono'])): ?>
                                            <span class="text-muted f-italic">Sin contacto</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($tieneUsuario): ?>
                                        <span class="badge bg-light-success text-success f-s-12" title="Usuario: <?= e($col['username'] ?? '') ?>">
                                            <i class="fa-solid fa-user-shield me-1"></i> <?= e($col['username'] ?? 'Usuario') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border f-s-11" title="Colaborador operativo sin credenciales de acceso">
                                            Sin usuario
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($esActivo): ?>
                                        <span class="badge bg-success">ACTIVO</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">INACTIVO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info btn-ver-ficha"
                                                data-id="<?= (int) $col['id'] ?>" title="Ver legajo laboral completo">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <?php if ($permisos['puede_gestionar']): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-editar-persona"
                                                data-id="<?= (int) $col['id'] ?>"
                                                data-persona-id="<?= (int) $col['persona_id'] ?>"
                                                data-nombres="<?= e($col['nombres'] ?? '') ?>"
                                                data-apellidos="<?= e($col['apellidos'] ?? '') ?>"
                                                data-telefono="<?= e($col['telefono'] ?? '') ?>"
                                                data-email="<?= e($col['email'] ?? '') ?>"
                                                title="Editar datos de persona">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <?php if ($esActivo): ?>
                                        <button type="button" class="btn btn-outline-primary btn-cambiar-cargo"
                                                data-id="<?= (int) $col['id'] ?>"
                                                data-nombre="<?= e($nombreCompleto) ?>"
                                                data-cargo-id="<?= (int) ($col['cargo_id'] ?? 0) ?>"
                                                data-cargo-nombre="<?= e($col['cargo_nombre'] ?? '') ?>"
                                                title="Cambiar cargo laboral">
                                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-cesar-colaborador"
                                                data-id="<?= (int) $col['id'] ?>"
                                                data-nombre="<?= e($nombreCompleto) ?>"
                                                title="Registrar cese laboral">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-outline-success btn-reingresar-colaborador"
                                                data-id="<?= (int) $col['id'] ?>"
                                                data-nombre="<?= e($nombreCompleto) ?>"
                                                title="Registrar reingreso laboral">
                                            <i class="fa-solid fa-user-plus"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- MODALES DEL MÓDULO PERSONAL                                                              -->
<!-- ========================================================================================= -->

<!-- 1. MODAL: ALTA DE COLABORADOR -->
<div class="modal fade" id="modal-alta-colaborador" tabindex="-1" aria-labelledby="modalAltaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalAltaTitulo">
                    <i class="fa-solid fa-user-plus text-primary me-2"></i> Alta de Colaborador
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-alta-colaborador" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <!-- Paso 1: Búsqueda o Reutilización de Persona -->
                    <div class="card bg-light border mb-4">
                        <div class="card-body p-3">
                            <h6 class="f-w-700 f-s-13 text-primary mb-2">
                                <i class="fa-solid fa-address-book me-1"></i> Paso 1: Verificación de Identidad Humana (PERSONA)
                            </h6>
                            <p class="text-secondary f-s-12 mb-3">
                                Ingrese el número de documento para buscar si la persona ya existe en el sistema. Si existe, se reutilizará evitando duplicidad humana.
                            </p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label f-s-12 f-w-600" for="alta-buscar-doc">Número de Documento</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" id="alta-buscar-doc"
                                               placeholder="Ej. 12345678" maxlength="30">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-verificar-persona">
                                        <i class="fa-solid fa-magnifying-glass me-1"></i> Verificar Documento
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-limpiar-persona">
                                        <i class="fa-solid fa-rotate-left me-1"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                            <div id="alta-persona-resultado" class="mt-2 d-none">
                                <div class="alert alert-success py-2 px-3 mb-0 f-s-12 d-flex align-items-center justify-content-between">
                                    <div>
                                        <i class="fa-solid fa-circle-check me-2 text-success"></i>
                                        <span id="alta-persona-encontrada-txt">Persona encontrada</span>
                                    </div>
                                    <span class="badge bg-success" id="alta-persona-encontrada-badge">Existente</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="alta-persona-id" name="persona_id" value="">

                    <!-- Paso 2: Datos de Identidad Humana (PERSONA) -->
                    <h6 class="f-w-700 f-s-13 text-secondary mb-3">
                        <i class="fa-regular fa-id-card me-1"></i> Datos de Identidad Humana (PERSONA)
                    </h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-nombres">Nombres <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="alta-nombres" name="nombres" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-apellido-paterno">Apellido Paterno <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="alta-apellido-paterno" name="apellido_paterno" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-apellido-materno">Apellido Materno</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="alta-apellido-materno" name="apellido_materno" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-s-13 f-w-600" for="alta-genero">Género</label>
                            <select class="form-select basic-select2" id="alta-genero" name="genero">
                                <option value="">Seleccione...</option>
                                <option value="MASCULINO">Masculino</option>
                                <option value="FEMENINO">Femenino</option>
                                <option value="OTRO">Otro</option>
                                <option value="NO_ESPECIFICADO">No especificado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-s-13 f-w-600" for="alta-fecha-nacimiento">Fecha de Nacimiento</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-cake-candles position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" id="alta-fecha-nacimiento" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-s-13 f-w-600" for="alta-pais-nacionalidad-id">País Nacionalidad</label>
                            <select class="form-select basic-select2" id="alta-pais-nacionalidad-id" name="pais_nacionalidad_id">
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= ($p['id'] == $paisDefault['id']) ? 'selected' : '' ?>>
                                        <?= e($p['nombre']) ?> (<?= e($p['nacionalidad'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-s-13 f-w-600" for="alta-pais-emisor-id">País Emisor Doc.</label>
                            <select class="form-select basic-select2" id="alta-pais-emisor-id" name="pais_emisor_id">
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= ($p['id'] == $paisDefault['id']) ? 'selected' : '' ?>>
                                        <?= e($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-tipo-doc">Tipo de Documento</label>
                            <select class="form-select basic-select2" id="alta-tipo-doc" name="tipo_documento_id">
                                <?php foreach ($tiposDocumento as $td): ?>
                                    <option value="<?= (int) $td['id'] ?>"><?= e($td['codigo']) ?> — <?= e($td['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-num-doc">Número Documento <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-id-card position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="alta-num-doc" name="numero_documento" maxlength="30" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-telefono">Teléfono / Celular</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-phone position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="alta-telefono" name="telefono" maxlength="30" placeholder="Ej. 987654321">
                            </div>
                            <div class="form-check form-switch app-switch mt-1 d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="checkbox" id="alta-es-whatsapp" name="es_whatsapp" value="1">
                                <label class="form-check-label f-s-11 text-secondary" for="alta-es-whatsapp">
                                    <i class="fa-brands fa-whatsapp text-success me-1"></i> WhatsApp
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="alta-email">Correo Electrónico</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-envelope position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="email" class="form-control ps-5" id="alta-email" name="email" maxlength="150" placeholder="nombre@correo.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="alta-direccion">Dirección Residencial</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-location-dot position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="alta-direccion" name="direccion" maxlength="255" placeholder="Av. Principal 123">
                            </div>
                        </div>
                    </div>

                    <!-- Paso 3: Residencia y Ubicación Geográfica Normalizada -->
                    <h6 class="f-w-700 f-s-13 text-secondary mb-3">
                        <i class="fa-solid fa-map-location-dot me-1"></i> Residencia y Ubicación Geográfica
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="alta-pais-residencia-id">País de Residencia <span class="text-danger">*</span></label>
                            <select class="form-select select-pais-residencia" id="alta-pais-residencia-id" name="pais_residencia_id" data-target-peru="#alta-seccion-peru" data-target-extranjero="#alta-seccion-extranjero">
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" data-iso="<?= e($p['codigo_iso2']) ?>" <?= ($p['id'] == $paisDefault['id']) ? 'selected' : '' ?>>
                                        <?= e($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sección Perú (INEI Dpto -> Prov -> Dist) -->
                        <div class="col-md-8" id="alta-seccion-peru">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label f-s-13 f-w-600" for="alta-departamento-id">Departamento</label>
                                    <select class="form-select select-departamento" id="alta-departamento-id" name="departamento_id" data-target-prov="#alta-provincia-id" data-target-dist="#alta-distrito-id">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($departamentos as $dep): ?>
                                            <option value="<?= (int) $dep['id'] ?>"><?= e($dep['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label f-s-13 f-w-600" for="alta-provincia-id">Provincia</label>
                                    <select class="form-select select-provincia" id="alta-provincia-id" name="provincia_id" data-target-dist="#alta-distrito-id" disabled>
                                        <option value="">Seleccione dpto...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label f-s-13 f-w-600" for="alta-distrito-id">Distrito / Ciudad</label>
                                    <select class="form-select select-distrito" id="alta-distrito-id" name="distrito_id" disabled>
                                        <option value="">Seleccione prov...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Sección Extranjero -->
                        <div class="col-md-8 d-none" id="alta-seccion-extranjero">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label f-s-13 f-w-600" for="alta-region-extranjera">Estado / Región Extranjera</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-earth-americas position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control ps-5" id="alta-region-extranjera" name="region_residencia_extranjera" maxlength="100" placeholder="Ej. California, Antioquia, CABA...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label f-s-13 f-w-600" for="alta-ciudad-extranjera">Ciudad Extranjera</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-city position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control ps-5" id="alta-ciudad-extranjera" name="ciudad_residencia_extranjera" maxlength="100" placeholder="Ej. Los Ángeles, Medellín, Buenos Aires...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 3: Vínculo Laboral y Cargo -->
                    <h6 class="f-w-700 f-s-13 text-secondary mb-3">
                        <i class="fa-solid fa-briefcase me-1"></i> Asignación Laboral Inicial (COLABORADOR)
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="alta-cargo-id">Cargo a Desempeñar <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" id="alta-cargo-id" name="cargo_id" required>
                                <option value="">Seleccione cargo...</option>
                                <?php foreach ($cargos as $cg): ?>
                                    <option value="<?= (int) $cg['id'] ?>"><?= e($cg['nombre']) ?> (<?= e($cg['departamento'] ?? 'Operaciones') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text f-s-11">El cargo define responsabilidades operativas. No otorga roles ni permisos en el sistema.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="alta-fecha-inicio">Fecha de Ingreso / Inicio <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-check position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" id="alta-fecha-inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600" for="alta-observaciones">Observaciones del Registro</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" id="alta-observaciones" name="observaciones" rows="2" placeholder="Notas sobre el alta o contratación..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-alta">
                        <i class="fa-solid fa-check me-1"></i> Registrar Colaborador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. MODAL: FICHA COMPLETA / LEGAJO LABORAL -->
<div class="modal fade" id="modal-ficha-completa" tabindex="-1" aria-labelledby="modalFichaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title f-w-700" id="modalFichaTitulo">
                    <i class="fa-solid fa-folder-open me-2"></i> Ficha y Legajo Laboral de Colaborador
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4" id="ficha-body-loading">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-secondary mb-0">Cargando legajo completo desde el repositorio central...</p>
                </div>
            </div>
            <div class="modal-body p-4 d-none" id="ficha-body-content">
                <!-- Cabecera de la Ficha -->
                <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 mb-4 flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-lg bg-white rounded-circle d-flex align-items-center justify-content-center border me-3 shadow-sm" style="width: 56px; height: 56px;">
                            <i class="fa-solid fa-user-tie f-s-28 text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 f-w-700 text-dark" id="ficha-nombre-completo">Nombre Completo</h5>
                            <span class="badge bg-primary text-white f-s-12 me-2" id="ficha-codigo">COL-0000</span>
                            <span class="badge" id="ficha-badge-estado">ACTIVO</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-secondary f-s-12">Empresa Empleadora:</div>
                        <div class="f-w-700 text-dark" id="ficha-empresa-empleadora">Camargo Hostelería</div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Tarjeta 1: Identidad Humana -->
                    <div class="col-md-6">
                        <div class="card h-100 border shadow-none bg-light">
                            <div class="card-header bg-white py-2 f-w-700 f-s-13 text-secondary border-bottom">
                                <i class="fa-regular fa-id-card me-1 text-primary"></i> Identidad Humana (PERSONA)
                            </div>
                            <div class="card-body p-3">
                                <table class="table table-sm table-borderless mb-0 f-s-13">
                                    <tr>
                                        <td class="text-secondary" style="width: 40%;">ID Persona:</td>
                                        <td class="f-w-600" id="ficha-persona-id">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Documento:</td>
                                        <td class="f-w-600" id="ficha-documento">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Género:</td>
                                        <td class="f-w-600" id="ficha-genero">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Fecha Nacimiento:</td>
                                        <td class="f-w-600" id="ficha-fecha-nacimiento">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Nacionalidad:</td>
                                        <td class="f-w-600" id="ficha-nacionalidad">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Ubicación / Residencia:</td>
                                        <td class="f-w-600" id="ficha-ubicacion">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Teléfono:</td>
                                        <td class="f-w-600" id="ficha-telefono">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Correo Personal:</td>
                                        <td class="f-w-600" id="ficha-email">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-secondary">Dirección:</td>
                                        <td class="f-w-600" id="ficha-direccion">-</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Vínculo de Acceso / Usuario -->
                    <div class="col-md-6">
                        <div class="card h-100 border shadow-none bg-light">
                            <div class="card-header bg-white py-2 f-w-700 f-s-13 text-secondary border-bottom">
                                <i class="fa-solid fa-key me-1 text-info"></i> Acceso al Sistema (USUARIO & ROLES)
                            </div>
                            <div class="card-body p-3">
                                <div id="ficha-usuario-info">
                                    <table class="table table-sm table-borderless mb-0 f-s-13">
                                        <tr>
                                            <td class="text-secondary" style="width: 40%;">Tiene Usuario:</td>
                                            <td class="f-w-600" id="ficha-usuario-estado">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary">Nombre de Usuario:</td>
                                            <td class="f-w-600" id="ficha-usuario-username">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary">Correo Acceso:</td>
                                            <td class="f-w-600" id="ficha-usuario-email">-</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary">Roles de Seguridad:</td>
                                            <td id="ficha-usuario-roles">-</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="form-text f-s-11 mt-2">
                                    <i class="fa-solid fa-circle-info me-1"></i> Las credenciales y roles se administran en el módulo de Usuarios y Roles.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 3: Historial Laboral Completo -->
                    <div class="col-12">
                        <div class="card border shadow-none">
                            <div class="card-header bg-white py-2 f-w-700 f-s-13 text-secondary border-bottom d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-timeline me-1 text-success"></i> Legajo Histórico de Episodios y Cargos</span>
                                <span class="badge bg-light text-secondary border" id="ficha-total-episodios">0 episodios</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 align-middle f-s-13">
                                        <thead class="bg-light text-secondary">
                                            <tr>
                                                <th class="ps-3">Episodio</th>
                                                <th>Periodo Laboral</th>
                                                <th>Motivo Cese</th>
                                                <th>Cargos Ejercidos en el Episodio</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ficha-tbody-historial">
                                            <!-- Se rellena dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar Legajo</button>
            </div>
        </div>
    </div>
</div>

<!-- 3. MODAL: CAMBIO DE CARGO -->
<div class="modal fade" id="modal-cambio-cargo" tabindex="-1" aria-labelledby="modalCambioCargoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalCambioCargoTitulo">
                    <i class="fa-solid fa-arrow-right-arrow-left text-primary me-2"></i> Transición de Cargo Laboral
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-cambio-cargo" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="cambio-cargo-colaborador-id" name="colaborador_id">
                <div class="modal-body p-4">
                    <div class="alert alert-light border f-s-12 mb-3">
                        <div>Colaborador: <strong id="cambio-cargo-nombre" class="text-dark">-</strong></div>
                        <div>Cargo actual: <span class="badge bg-secondary" id="cambio-cargo-actual-txt">-</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="cambio-cargo-nuevo-id">Nuevo Cargo a Asignar <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="cambio-cargo-nuevo-id" name="nuevo_cargo_id" required>
                            <option value="">Seleccione nuevo cargo...</option>
                            <?php foreach ($cargos as $cg): ?>
                                <option value="<?= (int) $cg['id'] ?>"><?= e($cg['nombre']) ?> (<?= e($cg['departamento'] ?? 'Operaciones') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="cambio-cargo-fecha">Fecha Efectiva del Cambio <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="date" class="form-control ps-5" id="cambio-cargo-fecha" name="fecha_cambio" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-text f-s-11">
                            El cargo anterior se cerrará con fecha efectiva del día anterior ($D-1$), garantizando continuidad histórica sin solapamiento.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="cambio-cargo-observaciones">Observaciones del Cambio</label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="cambio-cargo-observaciones" name="observaciones" rows="2" placeholder="Motivo de ascenso, rotación o traslado..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-cambio-cargo">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Cambio de Cargo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. MODAL: CESE LABORAL -->
<div class="modal fade" id="modal-cese-laboral" tabindex="-1" aria-labelledby="modalCeseTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title f-w-700" id="modalCeseTitulo">
                    <i class="fa-solid fa-user-slash me-2"></i> Registrar Cese Laboral
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-cese-laboral" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="cese-colaborador-id" name="colaborador_id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border f-s-12 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Colaborador a cesar: <strong id="cese-nombre" class="text-dark">-</strong>.
                        <div class="mt-1">El estado pasará a <code>INACTIVO</code>. Se preservará íntegro el historial y la identidad humana de la persona.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="cese-fecha">Fecha de Cese <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-xmark position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="date" class="form-control ps-5" id="cese-fecha" name="fecha_cese" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="cese-motivo">Motivo del Cese <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="cese-motivo" name="motivo_cese" required>
                            <option value="">Seleccione motivo formal...</option>
                            <option value="RENUNCIA">Renuncia voluntaria</option>
                            <option value="FIN_CONTRATO">Fin de contrato</option>
                            <option value="MUTUO_ACUERDO">Mutuo acuerdo</option>
                            <option value="DESPIDO">Despido</option>
                            <option value="JUBILACION">Jubilación</option>
                            <option value="OTRO">Otro motivo</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="cese-observaciones">Observaciones Detalladas</label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="cese-observaciones" name="observaciones" rows="2" placeholder="Detalles de liquidación o término de relación laboral..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btn-submit-cese">
                        <i class="fa-solid fa-user-slash me-1"></i> Ejecutar Cese Laboral
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. MODAL: REINGRESO LABORAL -->
<div class="modal fade" id="modal-reingreso-laboral" tabindex="-1" aria-labelledby="modalReingresoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title f-w-700" id="modalReingresoTitulo">
                    <i class="fa-solid fa-user-plus me-2"></i> Registrar Reingreso Laboral
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-reingreso-laboral" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="reingreso-colaborador-id" name="colaborador_id">
                <div class="modal-body p-4">
                    <div class="alert alert-light-success border f-s-12 mb-3">
                        <i class="fa-solid fa-rotate-right me-1"></i>
                        Colaborador cesado: <strong id="reingreso-nombre" class="text-dark">-</strong>.
                        <div class="mt-1">Se abrirá un nuevo episodio laboral sin duplicar la persona ni el registro de colaborador existente.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="reingreso-fecha">Fecha de Reingreso <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-check position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="date" class="form-control ps-5" id="reingreso-fecha" name="fecha_reingreso" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="reingreso-cargo-id">Cargo para el Nuevo Episodio <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="reingreso-cargo-id" name="cargo_id" required>
                            <option value="">Seleccione cargo...</option>
                            <?php foreach ($cargos as $cg): ?>
                                <option value="<?= (int) $cg['id'] ?>"><?= e($cg['nombre']) ?> (<?= e($cg['departamento'] ?? 'Operaciones') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600" for="reingreso-observaciones">Observaciones del Reingreso</label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="reingreso-observaciones" name="observaciones" rows="2" placeholder="Condiciones del reingreso..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btn-submit-reingreso">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Reingreso
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 6. MODAL: EDITAR PERSONA -->
<div class="modal fade" id="modal-editar-persona" tabindex="-1" aria-labelledby="modalEditarPersonaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalEditarPersonaTitulo">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Datos de Persona
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-editar-persona" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="edit-colaborador-id" name="colaborador_id">
                <input type="hidden" id="edit-persona-id" name="persona_id">
                <div class="modal-body p-4">
                    <h6 class="f-w-700 f-s-13 text-secondary mb-3">
                        <i class="fa-regular fa-id-card me-1"></i> Identidad y Filiación (PERSONA)
                    </h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-nombres">Nombres <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="edit-nombres" name="nombres" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-apellido-paterno">Apellido Paterno <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="edit-apellido-paterno" name="apellido_paterno" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-apellido-materno">Apellido Materno</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="edit-apellido-materno" name="apellido_materno" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-genero">Género</label>
                            <select class="form-select basic-select2" id="edit-genero" name="genero">
                                <option value="">Seleccione...</option>
                                <option value="MASCULINO">Masculino</option>
                                <option value="FEMENINO">Femenino</option>
                                <option value="OTRO">Otro</option>
                                <option value="NO_ESPECIFICADO">No especificado</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-fecha-nacimiento">Fecha de Nacimiento</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-cake-candles position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control ps-5" id="edit-fecha-nacimiento" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-pais-nacionalidad-id">País de Nacionalidad</label>
                            <select class="form-select basic-select2" id="edit-pais-nacionalidad-id" name="pais_nacionalidad_id">
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">
                                        <?= e($p['nombre']) ?> (<?= e($p['nacionalidad'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="edit-telefono">Teléfono / Celular</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-phone position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="edit-telefono" name="telefono" maxlength="30">
                            </div>
                            <div class="form-check form-switch app-switch mt-1 d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="checkbox" id="edit-es-whatsapp" name="es_whatsapp" value="1">
                                <label class="form-check-label f-s-11 text-secondary" for="edit-es-whatsapp">
                                    <i class="fa-brands fa-whatsapp text-success me-1"></i> WhatsApp
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="edit-email">Correo Electrónico</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-envelope position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="email" class="form-control ps-5" id="edit-email" name="email" maxlength="150">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600" for="edit-direccion">Dirección Residencial</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-location-dot position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="edit-direccion" name="direccion" maxlength="255">
                            </div>
                        </div>
                    </div>

                    <h6 class="f-w-700 f-s-13 text-secondary mb-3">
                        <i class="fa-solid fa-map-location-dot me-1"></i> Residencia y Ubicación Geográfica
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="edit-pais-residencia-id">País de Residencia</label>
                            <select class="form-select select-pais-residencia" id="edit-pais-residencia-id" name="pais_residencia_id" data-target-peru="#edit-seccion-peru" data-target-extranjero="#edit-seccion-extranjero">
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" data-iso="<?= e($p['codigo_iso2']) ?>">
                                        <?= e($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sección Perú -->
                        <div class="col-md-8" id="edit-seccion-peru">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label f-s-13 f-w-600" for="edit-departamento-id">Departamento</label>
                                    <select class="form-select select-departamento" id="edit-departamento-id" name="departamento_id" data-target-prov="#edit-provincia-id" data-target-dist="#edit-distrito-id">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($departamentos as $dep): ?>
                                            <option value="<?= (int) $dep['id'] ?>"><?= e($dep['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label f-s-13 f-w-600" for="edit-provincia-id">Provincia</label>
                                    <select class="form-select select-provincia" id="edit-provincia-id" name="provincia_id" data-target-dist="#edit-distrito-id">
                                        <option value="">Seleccione dpto...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label f-s-13 f-w-600" for="edit-distrito-id">Distrito / Ciudad</label>
                                    <select class="form-select select-distrito" id="edit-distrito-id" name="distrito_id">
                                        <option value="">Seleccione prov...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Sección Extranjero -->
                        <div class="col-md-8 d-none" id="edit-seccion-extranjero">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label f-s-13 f-w-600" for="edit-region-extranjera">Estado / Región Extranjera</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-earth-americas position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control ps-5" id="edit-region-extranjera" name="region_residencia_extranjera" maxlength="100">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label f-s-13 f-w-600" for="edit-ciudad-extranjera">Ciudad Extranjera</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-city position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control ps-5" id="edit-ciudad-extranjera" name="ciudad_residencia_extranjera" maxlength="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-edit-persona">
                        <i class="fa-solid fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- JAVASCRIPT DEL MÓDULO PERSONAL (Vanilla JS + SweetAlert2)                                -->
<!-- ========================================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global').value;
    const defaultPaisId = parseInt(document.getElementById('default-pais-id').value, 10);
    const defaultPaisIso = document.getElementById('default-pais-iso').value;

    // Modales Bootstrap
    const modalAlta = new bootstrap.Modal(document.getElementById('modal-alta-colaborador'));
    const modalFicha = new bootstrap.Modal(document.getElementById('modal-ficha-completa'));
    const modalCambioCargo = new bootstrap.Modal(document.getElementById('modal-cambio-cargo'));
    const modalCese = new bootstrap.Modal(document.getElementById('modal-cese-laboral'));
    const modalReingreso = new bootstrap.Modal(document.getElementById('modal-reingreso-laboral'));
    const modalEditarPersona = new bootstrap.Modal(document.getElementById('modal-editar-persona'));

    // Funciones auxiliares de Cascada Geográfica
    async function cargarProvincias(departamentoId, selectProv, selectedProvId = null) {
        selectProv.disabled = true;
        selectProv.innerHTML = '<option value="">Cargando provincias...</option>';

        if (!departamentoId) {
            selectProv.innerHTML = '<option value="">Seleccione dpto...</option>';
            return;
        }

        try {
            const res = await fetch(`/api/geografia/departamentos/${departamentoId}/provincias`);
            const json = await res.json();
            if (json.exito && json.datos) {
                let options = '<option value="">Seleccione provincia...</option>';
                json.datos.forEach(p => {
                    const sel = (selectedProvId && parseInt(selectedProvId, 10) === parseInt(p.id, 10)) ? 'selected' : '';
                    options += `<option value="${p.id}" ${sel}>${p.nombre}</option>`;
                });
                selectProv.innerHTML = options;
                selectProv.disabled = false;
            } else {
                selectProv.innerHTML = '<option value="">Sin provincias</option>';
            }
        } catch (e) {
            selectProv.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    async function cargarDistritos(provinciaId, selectDist, selectedDistId = null) {
        selectDist.disabled = true;
        selectDist.innerHTML = '<option value="">Cargando distritos...</option>';

        if (!provinciaId) {
            selectDist.innerHTML = '<option value="">Seleccione prov...</option>';
            return;
        }

        try {
            const res = await fetch(`/api/geografia/provincias/${provinciaId}/distritos`);
            const json = await res.json();
            if (json.exito && json.datos) {
                let options = '<option value="">Seleccione distrito...</option>';
                json.datos.forEach(d => {
                    const sel = (selectedDistId && parseInt(selectedDistId, 10) === parseInt(d.id, 10)) ? 'selected' : '';
                    options += `<option value="${d.id}" ${sel}>${d.nombre} (${d.codigo_ubigeo})</option>`;
                });
                selectDist.innerHTML = options;
                selectDist.disabled = false;
            } else {
                selectDist.innerHTML = '<option value="">Sin distritos</option>';
            }
        } catch (e) {
            selectDist.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    function configurarCascadaGeografica(prefijo) {
        const selectPais = document.getElementById(`${prefijo}-pais-residencia-id`);
        const seccionPeru = document.getElementById(`${prefijo}-seccion-peru`);
        const seccionExtranjero = document.getElementById(`${prefijo}-seccion-extranjero`);
        const selectDep = document.getElementById(`${prefijo}-departamento-id`);
        const selectProv = document.getElementById(`${prefijo}-provincia-id`);
        const selectDist = document.getElementById(`${prefijo}-distrito-id`);

        if (selectPais) {
            selectPais.addEventListener('change', function () {
                const opt = selectPais.options[selectPais.selectedIndex];
                const iso = opt ? (opt.dataset.iso || '') : '';
                const paisId = parseInt(selectPais.value, 10);
                const esPeru = (iso === 'PE' || paisId === defaultPaisId);

                if (esPeru) {
                    seccionPeru.classList.remove('d-none');
                    seccionExtranjero.classList.add('d-none');
                    const regExt = document.getElementById(`${prefijo}-region-extranjera`);
                    const ciuExt = document.getElementById(`${prefijo}-ciudad-extranjera`);
                    if (regExt) regExt.value = '';
                    if (ciuExt) ciuExt.value = '';
                } else {
                    seccionPeru.classList.add('d-none');
                    seccionExtranjero.classList.remove('d-none');
                    if (selectDep) selectDep.value = '';
                    if (selectProv) {
                        selectProv.innerHTML = '<option value="">Seleccione dpto...</option>';
                        selectProv.disabled = true;
                    }
                    if (selectDist) {
                        selectDist.innerHTML = '<option value="">Seleccione prov...</option>';
                        selectDist.disabled = true;
                    }
                }
            });
        }

        if (selectDep) {
            selectDep.addEventListener('change', function () {
                const depId = this.value;
                if (selectDist) {
                    selectDist.innerHTML = '<option value="">Seleccione prov...</option>';
                    selectDist.disabled = true;
                }
                cargarProvincias(depId, selectProv);
            });
        }

        if (selectProv) {
            selectProv.addEventListener('change', function () {
                const provId = this.value;
                cargarDistritos(provId, selectDist);
            });
        }
    }

    configurarCascadaGeografica('alta');
    configurarCascadaGeografica('edit');

    // Botones globales
    const btnNuevoColaborador = document.getElementById('btn-nuevo-colaborador');
    if (btnNuevoColaborador) {
        btnNuevoColaborador.addEventListener('click', () => {
            const form = document.getElementById('form-alta-colaborador');
            form.reset();
            document.getElementById('alta-persona-id').value = '';
            document.getElementById('alta-persona-resultado').classList.add('d-none');
            document.getElementById('alta-nombres').readOnly = false;
            document.getElementById('alta-apellido-paterno').readOnly = false;
            document.getElementById('alta-apellido-materno').readOnly = false;
            document.getElementById('alta-pais-nacionalidad-id').value = defaultPaisId;
            document.getElementById('alta-pais-emisor-id').value = defaultPaisId;
            document.getElementById('alta-pais-residencia-id').value = defaultPaisId;
            document.getElementById('alta-pais-residencia-id').dispatchEvent(new Event('change'));
            modalAlta.show();
        });
    }

    const btnRecargar = document.getElementById('btn-recargar-personal');
    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => location.reload());
    }

    // 1. Verificación de Persona en Alta
    const btnVerificarPersona = document.getElementById('btn-verificar-persona');
    if (btnVerificarPersona) {
        btnVerificarPersona.addEventListener('click', async function () {
            const doc = document.getElementById('alta-buscar-doc').value.trim();
            if (!doc) {
                Swal.fire('Atención', 'Ingrese un número de documento para buscar.', 'warning');
                return;
            }

            try {
                const res = await fetch(`/api/personal/buscar-persona?q=${encodeURIComponent(doc)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();

                const resultadoDiv = document.getElementById('alta-persona-resultado');
                const txt = document.getElementById('alta-persona-encontrada-txt');

                if (json.exito && json.datos && json.datos.length > 0) {
                    const p = json.datos[0];
                    document.getElementById('alta-persona-id').value = p.id;
                    document.getElementById('alta-nombres').value = p.nombres || '';
                    document.getElementById('alta-apellido-paterno').value = p.apellido_paterno || '';
                    document.getElementById('alta-apellido-materno').value = p.apellido_materno || '';
                    document.getElementById('alta-nombres').readOnly = true;
                    document.getElementById('alta-apellido-paterno').readOnly = true;
                    document.getElementById('alta-apellido-materno').readOnly = true;

                    if (p.genero) document.getElementById('alta-genero').value = p.genero;
                    if (p.fecha_nacimiento) document.getElementById('alta-fecha-nacimiento').value = p.fecha_nacimiento;
                    if (p.pais_nacionalidad_id) document.getElementById('alta-pais-nacionalidad-id').value = p.pais_nacionalidad_id;
                    if (p.tipo_documento_id) document.getElementById('alta-tipo-doc').value = p.tipo_documento_id;

                    document.getElementById('alta-num-doc').value = p.numero_documento || doc;
                    document.getElementById('alta-telefono').value = p.telefono || '';
                    document.getElementById('alta-es-whatsapp').checked = (p.es_whatsapp == 1 || p.es_whatsapp === true);
                    document.getElementById('alta-email').value = p.email || '';
                    document.getElementById('alta-direccion').value = p.direccion || '';

                    // Residencia
                    if (p.pais_residencia_id) {
                        document.getElementById('alta-pais-residencia-id').value = p.pais_residencia_id;
                    } else {
                        document.getElementById('alta-pais-residencia-id').value = defaultPaisId;
                    }
                    document.getElementById('alta-pais-residencia-id').dispatchEvent(new Event('change'));

                    if (p.departamento_id) {
                        document.getElementById('alta-departamento-id').value = p.departamento_id;
                        await cargarProvincias(p.departamento_id, document.getElementById('alta-provincia-id'), p.provincia_id);
                        if (p.provincia_id) {
                            await cargarDistritos(p.provincia_id, document.getElementById('alta-distrito-id'), p.distrito_id);
                        }
                    }

                    if (p.region_residencia_extranjera) {
                        document.getElementById('alta-region-extranjera').value = p.region_residencia_extranjera;
                    }
                    if (p.ciudad_residencia_extranjera) {
                        document.getElementById('alta-ciudad-extranjera').value = p.ciudad_residencia_extranjera;
                    }

                    txt.textContent = `Persona encontrada: ${p.nombre_completo || p.nombres} (ID: ${p.id}). Se vinculará como colaborador sin duplicidad humana.`;
                    resultadoDiv.classList.remove('d-none');
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Persona vinculable encontrada',
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else {
                    document.getElementById('alta-persona-id').value = '';
                    document.getElementById('alta-nombres').readOnly = false;
                    document.getElementById('alta-apellido-paterno').readOnly = false;
                    document.getElementById('alta-apellido-materno').readOnly = false;
                    document.getElementById('alta-num-doc').value = doc;
                    txt.textContent = 'Persona no encontrada en el sistema. Se creará una nueva persona física al guardar.';
                    resultadoDiv.classList.remove('d-none');
                }
            } catch (err) {
                Swal.fire('Error', 'Falla de conexión al consultar el registro de personas.', 'error');
            }
        });
    }

    const btnLimpiarPersona = document.getElementById('btn-limpiar-persona');
    if (btnLimpiarPersona) {
        btnLimpiarPersona.addEventListener('click', function () {
            document.getElementById('alta-buscar-doc').value = '';
            document.getElementById('alta-persona-id').value = '';
            document.getElementById('alta-nombres').value = '';
            document.getElementById('alta-apellido-paterno').value = '';
            document.getElementById('alta-apellido-materno').value = '';
            document.getElementById('alta-nombres').readOnly = false;
            document.getElementById('alta-apellido-paterno').readOnly = false;
            document.getElementById('alta-apellido-materno').readOnly = false;
            document.getElementById('alta-genero').value = '';
            document.getElementById('alta-fecha-nacimiento').value = '';
            document.getElementById('alta-num-doc').value = '';
            document.getElementById('alta-telefono').value = '';
            document.getElementById('alta-es-whatsapp').checked = false;
            document.getElementById('alta-email').value = '';
            document.getElementById('alta-direccion').value = '';
            document.getElementById('alta-pais-residencia-id').value = defaultPaisId;
            document.getElementById('alta-pais-residencia-id').dispatchEvent(new Event('change'));
            document.getElementById('alta-persona-resultado').classList.add('d-none');
        });
    }

    // 2. Submit Alta Colaborador
    const formAlta = document.getElementById('form-alta-colaborador');
    if (formAlta) {
        formAlta.addEventListener('submit', async function (e) {
            e.preventDefault();

            const nombres = document.getElementById('alta-nombres').value.trim();
            const apellidoPaterno = document.getElementById('alta-apellido-paterno').value.trim();
            const apellidoMaterno = document.getElementById('alta-apellido-materno').value.trim();
            const cargoId = document.getElementById('alta-cargo-id').value;
            const fechaInicio = document.getElementById('alta-fecha-inicio').value;

            if (!nombres || !apellidoPaterno || !cargoId || !fechaInicio) {
                Swal.fire('Atención', 'Complete todos los campos obligatorios (*).', 'warning');
                return;
            }

            const payload = {
                persona_id: document.getElementById('alta-persona-id').value || null,
                nombres: nombres,
                apellido_paterno: apellidoPaterno,
                apellido_materno: apellidoMaterno || null,
                genero: document.getElementById('alta-genero').value || null,
                fecha_nacimiento: document.getElementById('alta-fecha-nacimiento').value || null,
                pais_nacionalidad_id: document.getElementById('alta-pais-nacionalidad-id').value || null,
                pais_emisor_id: document.getElementById('alta-pais-emisor-id').value || null,
                tipo_documento_id: document.getElementById('alta-tipo-doc').value || null,
                numero_documento: document.getElementById('alta-num-doc').value.trim() || null,
                telefono: document.getElementById('alta-telefono').value.trim() || null,
                es_whatsapp: document.getElementById('alta-es-whatsapp').checked ? 1 : 0,
                email: document.getElementById('alta-email').value.trim() || null,
                direccion: document.getElementById('alta-direccion').value.trim() || null,
                pais_residencia_id: document.getElementById('alta-pais-residencia-id').value || null,
                departamento_id: document.getElementById('alta-departamento-id').value || null,
                provincia_id: document.getElementById('alta-provincia-id').value || null,
                distrito_id: document.getElementById('alta-distrito-id').value || null,
                region_residencia_extranjera: document.getElementById('alta-region-extranjera').value.trim() || null,
                ciudad_residencia_extranjera: document.getElementById('alta-ciudad-extranjera').value.trim() || null,
                cargo_id: parseInt(cargoId, 10),
                fecha_inicio: fechaInicio,
                observaciones: document.getElementById('alta-observaciones').value.trim() || null
            };

            try {
                const btnSubmit = document.getElementById('btn-submit-alta');
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando...';

                const res = await fetch('/api/personal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fa-solid fa-check me-1"></i> Registrar Colaborador';

                if (json.exito) {
                    modalAlta.hide();
                    Swal.fire('Éxito', json.mensaje, 'success').then(() => location.reload());
                } else {
                    let errMsg = json.mensaje || 'No se pudo registrar el colaborador.';
                    if (json.errores) {
                        errMsg += '<br><small class="text-danger">' + Object.values(json.errores).join('<br>') + '</small>';
                    }
                    Swal.fire('Error', errMsg, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Falla de red al procesar el alta.', 'error');
            }
        });
    }

    // 3. Ver Ficha Completa / Legajo
    document.querySelectorAll('.btn-ver-ficha').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            modalFicha.show();

            const loadingDiv = document.getElementById('ficha-body-loading');
            const contentDiv = document.getElementById('ficha-body-content');
            loadingDiv.classList.remove('d-none');
            contentDiv.classList.add('d-none');

            try {
                const res = await fetch(`/api/personal/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();

                if (!json.exito || !json.datos) {
                    modalFicha.hide();
                    Swal.fire('Error', json.mensaje || 'No se encontró la ficha solicitada.', 'error');
                    return;
                }

                const d = json.datos;
                const col = d.colaborador || {};
                const p = d.persona || {};
                const u = d.usuario;
                const emp = d.empresa_empleadora;
                const episodios = d.episodios || [];

                // Header
                const nombreCompleto = p.nombre_completo || `${p.nombres || ''} ${p.apellido_paterno || ''}`.trim();
                document.getElementById('ficha-nombre-completo').textContent = nombreCompleto || 'Colaborador';
                document.getElementById('ficha-codigo').textContent = col.codigo || 'S/C';

                const badgeEstado = document.getElementById('ficha-badge-estado');
                badgeEstado.textContent = col.estado || 'INACTIVO';
                badgeEstado.className = `badge ${col.estado === 'ACTIVO' ? 'bg-success' : 'bg-secondary'}`;

                document.getElementById('ficha-empresa-empleadora').textContent = emp ? `${emp.razon_social} (RUC: ${emp.numero_documento})` : 'Camargo Hostelería';

                // Persona
                document.getElementById('ficha-persona-id').textContent = p.id || '-';
                document.getElementById('ficha-documento').textContent = p.numero_documento ? `${p.tipo_documento || 'DOC'}: ${p.numero_documento}` : 'Sin documento';
                document.getElementById('ficha-genero').textContent = p.genero || 'No especificado';
                document.getElementById('ficha-fecha-nacimiento').textContent = p.fecha_nacimiento || 'No registrada';
                document.getElementById('ficha-nacionalidad').textContent = p.nacionalidad || (p.pais_nacionalidad ? p.pais_nacionalidad.nombre : 'Peruana');

                // Ubicación consolidada
                let ubicacionTxt = 'Sin ubicación registrada';
                if (p.distrito) {
                    ubicacionTxt = `${p.distrito}, ${p.provincia}, ${p.departamento} (UBIGEO: ${p.ubigeo || '-'})`;
                } else if (p.ciudad_residencia_extranjera) {
                    ubicacionTxt = `${p.ciudad_residencia_extranjera}${p.region_residencia_extranjera ? ', ' + p.region_residencia_extranjera : ''}`;
                }
                document.getElementById('ficha-ubicacion').textContent = ubicacionTxt;

                document.getElementById('ficha-telefono').innerHTML = p.telefono
                    ? (p.es_whatsapp ? `<i class="fa-brands fa-whatsapp text-success me-1"></i> ${p.telefono}` : p.telefono)
                    : 'Sin teléfono';
                document.getElementById('ficha-email').textContent = p.email || 'Sin correo';
                document.getElementById('ficha-direccion').textContent = p.direccion || 'Sin dirección';

                // Usuario
                if (u && u.id) {
                    document.getElementById('ficha-usuario-estado').innerHTML = '<span class="badge bg-success">Activo</span>';
                    document.getElementById('ficha-usuario-username').textContent = u.username || '-';
                    document.getElementById('ficha-usuario-email').textContent = u.email || '-';

                    const rolesTxt = (u.roles || []).map(r => `<span class="badge bg-light-info text-info me-1">${r.nombre || r}</span>`).join('');
                    document.getElementById('ficha-usuario-roles').innerHTML = rolesTxt || '<span class="text-muted">Sin roles</span>';
                } else {
                    document.getElementById('ficha-usuario-estado').innerHTML = '<span class="badge bg-secondary">Sin credenciales de acceso</span>';
                    document.getElementById('ficha-usuario-username').textContent = 'N/A';
                    document.getElementById('ficha-usuario-email').textContent = 'N/A';
                    document.getElementById('ficha-usuario-roles').innerHTML = '<span class="text-muted">N/A</span>';
                }

                // Historial de Episodios y Cargos
                document.getElementById('ficha-total-episodios').textContent = `${episodios.length} episodio(s)`;
                const tbodyHistorial = document.getElementById('ficha-tbody-historial');
                tbodyHistorial.innerHTML = '';

                if (episodios.length === 0) {
                    tbodyHistorial.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Sin episodios registrados.</td></tr>';
                } else {
                    episodios.forEach(ep => {
                        const tr = document.createElement('tr');
                        const esEpActivo = !ep.fecha_fin;

                        let cargosHtml = '';
                        if (ep.cargos && ep.cargos.length > 0) {
                            cargosHtml = '<ul class="list-unstyled mb-0 f-s-12">';
                            ep.cargos.forEach(cg => {
                                const vigencia = cg.fecha_fin ? `${cg.fecha_inicio} al ${cg.fecha_fin}` : `Desde ${cg.fecha_inicio} (Vigente)`;
                                cargosHtml += `<li><strong>${cg.cargo_nombre || 'Cargo'}</strong> <span class="text-muted">(${vigencia})</span></li>`;
                            });
                            cargosHtml += '</ul>';
                        } else {
                            cargosHtml = '<span class="text-muted">Sin cargos asignados</span>';
                        }

                        tr.innerHTML = `
                            <td class="ps-3"><span class="badge ${esEpActivo ? 'bg-light-success text-success' : 'bg-light text-secondary border'}">#${ep.id}</span></td>
                            <td>
                                <div><strong>${ep.fecha_inicio}</strong> al <strong>${ep.fecha_fin || 'Vigente'}</strong></div>
                                <small class="text-muted">${esEpActivo ? 'Episodio laboral en curso' : 'Episodio concluido'}</small>
                            </td>
                            <td>${ep.motivo_cese ? `<span class="badge bg-light-danger text-danger">${ep.motivo_cese}</span>` : '<span class="text-muted">-</span>'}</td>
                            <td>${cargosHtml}</td>
                            <td>${ep.observaciones || '<span class="text-muted">-</span>'}</td>
                        `;
                        tbodyHistorial.appendChild(tr);
                    });
                }

                loadingDiv.classList.add('d-none');
                contentDiv.classList.remove('d-none');
            } catch (err) {
                modalFicha.hide();
                Swal.fire('Error', 'Falla de conexión al obtener el legajo.', 'error');
            }
        });
    });

    // 4. Cambio de Cargo
    document.querySelectorAll('.btn-cambiar-cargo').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('cambio-cargo-colaborador-id').value = this.dataset.id;
            document.getElementById('cambio-cargo-nombre').textContent = this.dataset.nombre;
            document.getElementById('cambio-cargo-actual-txt').textContent = this.dataset.cargoNombre || 'Sin cargo actual';
            document.getElementById('cambio-cargo-nuevo-id').value = '';
            document.getElementById('cambio-cargo-observaciones').value = '';
            modalCambioCargo.show();
        });
    });

    const formCambioCargo = document.getElementById('form-cambio-cargo');
    if (formCambioCargo) {
        formCambioCargo.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('cambio-cargo-colaborador-id').value;
            const nuevoCargoId = document.getElementById('cambio-cargo-nuevo-id').value;
            const fechaCambio = document.getElementById('cambio-cargo-fecha').value;

            if (!nuevoCargoId || !fechaCambio) {
                Swal.fire('Atención', 'Seleccione el nuevo cargo y la fecha efectiva.', 'warning');
                return;
            }

            try {
                const res = await fetch(`/api/personal/${id}/cargo`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        nuevo_cargo_id: parseInt(nuevoCargoId, 10),
                        fecha_cambio: fechaCambio,
                        observaciones: document.getElementById('cambio-cargo-observaciones').value.trim() || null
                    })
                });
                const json = await res.json();
                if (json.exito) {
                    modalCambioCargo.hide();
                    Swal.fire('Transición Registrada', json.mensaje, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', json.mensaje, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Falla de red al cambiar de cargo.', 'error');
            }
        });
    }

    // 5. Cese Laboral
    document.querySelectorAll('.btn-cesar-colaborador').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('cese-colaborador-id').value = this.dataset.id;
            document.getElementById('cese-nombre').textContent = this.dataset.nombre;
            document.getElementById('cese-motivo').value = '';
            document.getElementById('cese-observaciones').value = '';
            modalCese.show();
        });
    });

    const formCese = document.getElementById('form-cese-laboral');
    if (formCese) {
        formCese.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('cese-colaborador-id').value;
            const fechaCese = document.getElementById('cese-fecha').value;
            const motivoCese = document.getElementById('cese-motivo').value;

            if (!fechaCese || !motivoCese) {
                Swal.fire('Atención', 'Complete la fecha y el motivo del cese.', 'warning');
                return;
            }

            const confirmacion = await Swal.fire({
                title: '¿Confirmar cese laboral?',
                text: 'El colaborador pasará a estado INACTIVO pero su legajo histórico permanecerá intacto.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, registrar cese',
                cancelButtonText: 'Cancelar'
            });

            if (!confirmacion.isConfirmed) return;

            try {
                const res = await fetch(`/api/personal/${id}/cesar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        fecha_cese: fechaCese,
                        motivo_cese: motivoCese,
                        observaciones: document.getElementById('cese-observaciones').value.trim() || null
                    })
                });
                const json = await res.json();
                if (json.exito) {
                    modalCese.hide();
                    Swal.fire('Cese Registrado', json.mensaje, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', json.mensaje, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Falla de red al registrar cese.', 'error');
            }
        });
    }

    // 6. Reingreso Laboral
    document.querySelectorAll('.btn-reingresar-colaborador').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('reingreso-colaborador-id').value = this.dataset.id;
            document.getElementById('reingreso-nombre').textContent = this.dataset.nombre;
            document.getElementById('reingreso-cargo-id').value = '';
            document.getElementById('reingreso-observaciones').value = '';
            modalReingreso.show();
        });
    });

    const formReingreso = document.getElementById('form-reingreso-laboral');
    if (formReingreso) {
        formReingreso.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('reingreso-colaborador-id').value;
            const fechaReingreso = document.getElementById('reingreso-fecha').value;
            const cargoId = document.getElementById('reingreso-cargo-id').value;

            if (!fechaReingreso || !cargoId) {
                Swal.fire('Atención', 'Complete la fecha de reingreso y el nuevo cargo.', 'warning');
                return;
            }

            try {
                const res = await fetch(`/api/personal/${id}/reingresar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        fecha_reingreso: fechaReingreso,
                        cargo_id: parseInt(cargoId, 10),
                        observaciones: document.getElementById('reingreso-observaciones').value.trim() || null
                    })
                });
                const json = await res.json();
                if (json.exito) {
                    modalReingreso.hide();
                    Swal.fire('Reingreso Registrado', json.mensaje, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', json.mensaje, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Falla de red al registrar reingreso.', 'error');
            }
        });
    }

    // 7. Editar Persona (Carga la ficha completa para pre-llenar los datos soberanos)
    document.querySelectorAll('.btn-editar-persona').forEach(btn => {
        btn.addEventListener('click', async function () {
            const colabId = this.dataset.id;
            try {
                const res = await fetch(`/api/personal/${colabId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();

                if (!json.exito || !json.datos || !json.datos.persona) {
                    Swal.fire('Error', 'No se pudieron recuperar los datos de la persona.', 'error');
                    return;
                }

                const p = json.datos.persona;
                document.getElementById('edit-colaborador-id').value = colabId;
                document.getElementById('edit-persona-id').value = p.id;
                document.getElementById('edit-nombres').value = p.nombres || '';
                document.getElementById('edit-apellido-paterno').value = p.apellido_paterno || '';
                document.getElementById('edit-apellido-materno').value = p.apellido_materno || '';
                document.getElementById('edit-genero').value = p.genero || '';
                document.getElementById('edit-fecha-nacimiento').value = p.fecha_nacimiento || '';
                if (p.pais_nacionalidad_id) {
                    document.getElementById('edit-pais-nacionalidad-id').value = p.pais_nacionalidad_id;
                }
                document.getElementById('edit-telefono').value = p.telefono || '';
                document.getElementById('edit-es-whatsapp').checked = (p.es_whatsapp == 1 || p.es_whatsapp === true);
                document.getElementById('edit-email').value = p.email || '';
                document.getElementById('edit-direccion').value = p.direccion || '';

                // Residencia
                const editPaisSelect = document.getElementById('edit-pais-residencia-id');
                editPaisSelect.value = p.pais_residencia_id || defaultPaisId;
                editPaisSelect.dispatchEvent(new Event('change'));

                if (p.departamento_id) {
                    document.getElementById('edit-departamento-id').value = p.departamento_id;
                    await cargarProvincias(p.departamento_id, document.getElementById('edit-provincia-id'), p.provincia_id);
                    if (p.provincia_id) {
                        await cargarDistritos(p.provincia_id, document.getElementById('edit-distrito-id'), p.distrito_id);
                    }
                }

                if (p.region_residencia_extranjera) {
                    document.getElementById('edit-region-extranjera').value = p.region_residencia_extranjera;
                }
                if (p.ciudad_residencia_extranjera) {
                    document.getElementById('edit-ciudad-extranjera').value = p.ciudad_residencia_extranjera;
                }

                modalEditarPersona.show();
            } catch (err) {
                Swal.fire('Error', 'Falla de red al preparar el formulario de edición.', 'error');
            }
        });
    });

    const formEditarPersona = document.getElementById('form-editar-persona');
    if (formEditarPersona) {
        formEditarPersona.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('edit-colaborador-id').value;
            const nombres = document.getElementById('edit-nombres').value.trim();
            const apellidoPaterno = document.getElementById('edit-apellido-paterno').value.trim();
            const apellidoMaterno = document.getElementById('edit-apellido-materno').value.trim();

            if (!nombres || !apellidoPaterno) {
                Swal.fire('Atención', 'Nombres y apellido paterno son requeridos.', 'warning');
                return;
            }

            const payload = {
                nombres: nombres,
                apellido_paterno: apellidoPaterno,
                apellido_materno: apellidoMaterno || null,
                genero: document.getElementById('edit-genero').value || null,
                fecha_nacimiento: document.getElementById('edit-fecha-nacimiento').value || null,
                pais_nacionalidad_id: document.getElementById('edit-pais-nacionalidad-id').value || null,
                telefono: document.getElementById('edit-telefono').value.trim() || null,
                es_whatsapp: document.getElementById('edit-es-whatsapp').checked ? 1 : 0,
                email: document.getElementById('edit-email').value.trim() || null,
                direccion: document.getElementById('edit-direccion').value.trim() || null,
                pais_residencia_id: document.getElementById('edit-pais-residencia-id').value || null,
                departamento_id: document.getElementById('edit-departamento-id').value || null,
                provincia_id: document.getElementById('edit-provincia-id').value || null,
                distrito_id: document.getElementById('edit-distrito-id').value || null,
                region_residencia_extranjera: document.getElementById('edit-region-extranjera').value.trim() || null,
                ciudad_residencia_extranjera: document.getElementById('edit-ciudad-extranjera').value.trim() || null
            };

            try {
                const res = await fetch(`/api/personal/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.exito) {
                    modalEditarPersona.hide();
                    Swal.fire('Guardado', json.mensaje, 'success').then(() => location.reload());
                } else {
                    let errMsg = json.mensaje || 'Error al actualizar.';
                    if (json.errores) {
                        errMsg += '<br><small class="text-danger">' + Object.values(json.errores).join('<br>') + '</small>';
                    }
                    Swal.fire('Error', errMsg, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Falla de red al actualizar datos.', 'error');
            }
        });
    }

    // 8. Filtros en cliente en tiempo real
    const inputBusqueda = document.getElementById('filtro-busqueda-personal');
    const selectEstado = document.getElementById('filtro-estado-personal');
    const selectCargo = document.getElementById('filtro-cargo-personal');
    const selectUsuario = document.getElementById('filtro-usuario-personal');

    function aplicarFiltros() {
        const q = inputBusqueda.value.toLowerCase().trim();
        const est = selectEstado.value;
        const cargo = selectCargo.value;
        const usuarioFiltro = selectUsuario.value;

        document.querySelectorAll('#tbody-personal tr[data-colaborador-id]').forEach(tr => {
            const texto = tr.textContent.toLowerCase();
            const coincideTexto = !q || texto.includes(q);

            const filaEstado = tr.dataset.estado;
            const coincideEstado = !est || filaEstado === est;

            const filaCargoId = tr.dataset.cargoId;
            const coincideCargo = !cargo || filaCargoId === cargo;

            const tieneUsuario = tr.dataset.tieneUsuario === '1';
            const coincideUsuario = !usuarioFiltro ||
                (usuarioFiltro === 'con_usuario' && tieneUsuario) ||
                (usuarioFiltro === 'sin_usuario' && !tieneUsuario);

            if (coincideTexto && coincideEstado && coincideCargo && coincideUsuario) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
        });
    }

    if (inputBusqueda) inputBusqueda.addEventListener('input', aplicarFiltros);
    if (selectEstado) selectEstado.addEventListener('change', aplicarFiltros);
    if (selectCargo) selectCargo.addEventListener('change', aplicarFiltros);
    if (selectUsuario) selectUsuario.addEventListener('change', aplicarFiltros);

    const btnLimpiar = document.getElementById('btn-limpiar-busqueda');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            inputBusqueda.value = '';
            aplicarFiltros();
        });
    }
});
</script>
