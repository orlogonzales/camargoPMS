<?php

declare(strict_types=1);

/**
 * Vista del Maestro de Empresas y Emisores Legales — Camargo PMS (EMPRESA-1).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var \CamargoPMS\Modelos\Empresa[] $empresas
 * @var array<int, array<string, mixed>> $personas
 * @var array<int, array<string, mixed>> $propiedades
 * @var array<int, array<string, mixed>> $paises
 * @var array<int, array<string, mixed>> $tiposDocumento
 * @var array{puede_crear: bool, puede_editar: bool, puede_cambiar_estado: bool} $permisos
 * @var string $csrf_token
 * @var string $titulo
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Empresas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-building f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Empresas y Emisores Legales</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Maestro corporativo de personas jurídicas y negocios emisores. Principio: <strong>EMPRESA/EMISOR ≠ PROPIEDAD ≠ UNIDAD</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if ($permisos['puede_crear']): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nueva-empresa">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Empresa
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-empresas">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Filtros Rápidos -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-6 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-empresa"
                                   placeholder="Buscar por código, RUC, razón social o nombre comercial..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado-empresa">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtro-principal-empresa">
                            <option value="">Todas (Principales y secundarias)</option>
                            <option value="1">Solo Empresa Principal</option>
                            <option value="0">Solo Empresas Secundarias</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla de Empresas -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-empresas">
                        <thead class="bg-light text-secondary f-s-12 text-uppercase">
                            <tr>
                                <th class="ps-4">Empresa / Emisor</th>
                                <th>RUC / Identificación</th>
                                <th>Domicilio Fiscal</th>
                                <th>Representante Legal</th>
                                <th class="text-center">Propiedades</th>
                                <th class="text-center">Rol</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-empresas">
                            <?php if (empty($empresas)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-building-circle-exclamation f-s-32 d-block mb-2 text-secondary"></i>
                                    No se encontraron empresas registradas. Utilice el botón superior para agregar la primera entidad.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($empresas as $emp): ?>
                            <tr data-empresa-id="<?= $emp->obtenerId() ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-light rounded d-flex align-items-center justify-content-center border" style="width: 42px; height: 42px; min-width: 42px; overflow: hidden;">
                                            <?php if ($emp->obtenerLogoUrl()): ?>
                                                <img src="/empresas/<?= (int) $emp->obtenerId() ?>/logo" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                            <?php else: ?>
                                                <i class="fa-solid fa-building text-primary f-s-18"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="f-w-700 f-s-14 text-dark d-block"><?= e($emp->obtenerRazonSocial()) ?></span>
                                            <?php if ($emp->obtenerNombreComercial()): ?>
                                                <span class="text-secondary f-s-12 d-block"><i class="fa-solid fa-tag me-1"></i><?= e($emp->obtenerNombreComercial()) ?></span>
                                            <?php endif; ?>
                                            <span class="badge bg-light text-secondary border f-s-11 mt-1"><?= e($emp->obtenerCodigo()) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle f-s-12">
                                        <?= e($emp->obtenerTipoDocumentoCodigo() ?: 'RUC') ?>: <?= e($emp->obtenerNumeroDocumento()) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-secondary f-s-12">
                                        <div><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= e($emp->obtenerDireccionFiscal()) ?></div>
                                        <?php if ($emp->obtenerDistrito() || $emp->obtenerProvincia()): ?>
                                            <small class="text-muted"><?= e(implode(', ', array_filter([$emp->obtenerDistrito(), $emp->obtenerProvincia(), $emp->obtenerDepartamento()]))) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($emp->obtenerRepresentanteNombreCompleto()): ?>
                                        <div class="f-s-13 f-w-600 text-dark"><?= e($emp->obtenerRepresentanteNombreCompleto()) ?></div>
                                        <span class="text-secondary f-s-11 d-block"><?= e($emp->obtenerRepresentanteCargo() ?: 'Gerente General') ?></span>
                                        <?php if ($emp->obtenerRepresentanteDocumento()): ?>
                                            <span class="text-muted f-s-11">Doc: <?= e($emp->obtenerRepresentanteDocumento()) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted f-s-12 f-italic">Sin representante asignado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 btn-ver-propiedades"
                                            data-id="<?= $emp->obtenerId() ?>" data-codigo="<?= e($emp->obtenerCodigo()) ?>"
                                            title="Ver propiedades administradas">
                                        <i class="fa-solid fa-hotel me-1 text-primary"></i> <?= $emp->obtenerTotalPropiedades() ?>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <?php if ($emp->esPrincipal()): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle f-s-12">
                                            <i class="fa-solid fa-star me-1"></i> Principal
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border f-s-12">Secundaria</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($emp->estaActivo()): ?>
                                        <span class="badge bg-success">ACTIVO</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">INACTIVO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($permisos['puede_editar']): ?>
                                        <button type="button" class="btn btn-outline-primary btn-editar-empresa"
                                                data-id="<?= $emp->obtenerId() ?>" title="Editar datos corporativos">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-asignar-propiedades"
                                                data-id="<?= $emp->obtenerId() ?>" title="Vincular propiedades">
                                            <i class="fa-solid fa-link"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($permisos['puede_cambiar_estado']): ?>
                                        <button type="button" class="btn <?= $emp->estaActivo() ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-estado-empresa"
                                                data-id="<?= $emp->obtenerId() ?>" data-estado="<?= $emp->obtenerEstado() ?>"
                                                title="<?= $emp->estaActivo() ? 'Desactivar empresa' : 'Activar empresa' ?>">
                                            <i class="fa-solid <?= $emp->estaActivo() ? 'fa-ban' : 'fa-check' ?>"></i>
                                        </button>
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

<!-- MODAL: Crear / Editar Empresa -->
<div class="modal fade" id="modal-empresa" tabindex="-1" aria-labelledby="modalEmpresaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalEmpresaTitulo">
                    <i class="fa-solid fa-building text-primary me-2"></i> Registrar Empresa / Emisor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-empresa" class="app-form app-icon-form" enctype="multipart/form-data" novalidate>
                <input type="hidden" id="empresa-id" name="id" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Código y RUC -->
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-codigo">Código Técnico <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-tag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5 text-uppercase" id="empresa-codigo" name="codigo"
                                       placeholder="Ej. CAMARGO-HOSTELERIA" required maxlength="50">
                            </div>
                            <div class="form-text f-s-11">Identificador estable único del emisor.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-tipo-documento">Tipo de Documento <span class="text-danger">*</span></label>
                            <select class="form-select basic-select2" id="empresa-tipo-documento" name="tipo_documento_id" required>
                                <?php foreach ($tiposDocumento as $td): ?>
                                    <option value="<?= (int) $td['id'] ?>" <?= $td['codigo'] === 'RUC' ? 'selected' : '' ?>>
                                        <?= e($td['codigo']) ?> — <?= e($td['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-num-documento">RUC / Número Documento <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-id-card position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-num-documento" name="numero_documento"
                                       placeholder="Ej. 20600000005" required maxlength="30">
                            </div>
                            <div class="form-text f-s-11">Validación estructural Modulo 11 para Perú.</div>
                        </div>

                        <!-- Razón Social y Nombre Comercial -->
                        <div class="col-md-7">
                            <label class="form-label f-s-13 f-w-600" for="empresa-razon-social">Razón Social Legal <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-building position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-razon-social" name="razon_social"
                                       placeholder="Nombre formal en registros públicos" required maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label f-s-13 f-w-600" for="empresa-nombre-comercial">Nombre Comercial / Marca</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-shop position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-nombre-comercial" name="nombre_comercial"
                                       placeholder="Nombre de fantasía o marca visible" maxlength="255">
                            </div>
                        </div>

                        <!-- Domicilio Fiscal y Ubicación -->
                        <div class="col-md-8">
                            <label class="form-label f-s-13 f-w-600" for="empresa-direccion-fiscal">Domicilio Fiscal <span class="text-danger">*</span></label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-location-dot position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-direccion-fiscal" name="direccion_fiscal"
                                       placeholder="Dirección legal completa según ficha RUC" required maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-ubigeo">Código de Ubigeo (6 dígitos)</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-map-pin position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-ubigeo" name="ubigeo"
                                       placeholder="Ej. 150101" maxlength="6">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-departamento">Departamento</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-map position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-departamento" name="departamento" placeholder="Ej. Lima">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-provincia">Provincia</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-map position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-provincia" name="provincia" placeholder="Ej. Lima">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-distrito">Distrito</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-map position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-distrito" name="distrito" placeholder="Ej. Miraflores">
                            </div>
                        </div>

                        <!-- Contacto -->
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-telefono">Teléfono Corporativo</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-phone position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-telefono" name="telefono" placeholder="+51 987 654 321">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-email">Correo Electrónico</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-envelope position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="email" class="form-control ps-5" id="empresa-email" name="email" placeholder="contacto@empresa.pe">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="empresa-sitio-web">Sitio Web</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-globe position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="url" class="form-control ps-5" id="empresa-sitio-web" name="sitio_web" placeholder="https://camargohosteleria.pe">
                            </div>
                        </div>

                        <!-- Representante Legal -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <h6 class="f-w-700 text-primary mb-3">
                                <i class="fa-solid fa-user-tie me-2"></i> Representación Legal (Reutiliza Maestro de Personas)
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="empresa-representante-id">Representante Legal (Persona Natural)</label>
                            <select class="form-select basic-select2" id="empresa-representante-id" name="representante_persona_id">
                                <option value="">-- Sin representante asignado --</option>
                                <?php foreach ($personas as $per): ?>
                                    <option value="<?= (int) $per['id'] ?>">
                                        <?= e(trim($per['nombres'] . ' ' . ($per['apellido_paterno'] ?? '') . ' ' . ($per['apellido_materno'] ?? ''))) ?>
                                        <?= $per['numero_documento'] ? ' (' . e($per['numero_documento']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-s-13 f-w-600" for="empresa-representante-cargo">Cargo de Representación</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-briefcase position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-representante-cargo" name="representante_cargo"
                                       placeholder="Gerente General" value="Gerente General">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label f-s-13 f-w-600" for="empresa-representante-partida">Poder / Partida Registral</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-file-lines position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="empresa-representante-partida" name="representante_poder_partida"
                                       placeholder="Ej. Partida 12345678">
                            </div>
                        </div>

                        <!-- Logotipo y Configuración -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <h6 class="f-w-700 text-primary mb-3">
                                <i class="fa-solid fa-image me-2"></i> Logotipo Corporativo e Identidad
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="empresa-logo-file">Archivo de Logotipo (PNG, JPG, WEBP, SVG máx 2MB)</label>
                            <input type="file" class="form-control" id="empresa-logo-file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                            <div class="form-text f-s-11">Se utilizará en encabezados y documentos oficiales.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch app-switch mt-3 d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="checkbox" id="empresa-es-principal" name="es_principal" value="1">
                                <label class="form-check-label f-s-13 f-w-600 mb-0" for="empresa-es-principal">
                                    Establecer como Empresa Operadora Principal del Sistema
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-guardar-empresa">
                        <i class="fa-solid fa-save me-1"></i> Guardar Empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Vincular Propiedades -->
<div class="modal fade" id="modal-vincular-propiedades" tabindex="-1" aria-labelledby="modalVincularTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalVincularTitulo">
                    <i class="fa-solid fa-link text-primary me-2"></i> Vincular Propiedades a Empresa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-vincular-propiedades" class="app-form">
                <input type="hidden" id="vincular-empresa-id" value="">
                <div class="modal-body p-4">
                    <p class="text-secondary f-s-13 mb-3">
                        Seleccione las propiedades inmobiliarias cuya operación legal y documental estará a cargo de esta empresa:
                    </p>
                    <div id="lista-propiedades-checkbox" class="list-group list-group-flush border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($propiedades as $pr): ?>
                        <label class="list-group-item d-flex align-items-center gap-2">
                            <input class="form-check-input me-1 prop-check-item" type="checkbox"
                                   value="<?= (int) $pr['id'] ?>" data-actual-empresa="<?= $pr['empresa_id'] ?? '' ?>">
                            <div class="d-flex flex-column">
                                <span class="f-w-600 f-s-13 text-dark"><?= e($pr['nombre']) ?></span>
                                <span class="text-muted f-s-11"><?= e($pr['codigo']) ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-guardar-vinculacion">
                        <i class="fa-solid fa-check me-1"></i> Guardar Asignación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript del Módulo Empresa -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global').value;
    const modalEmpresaEl = document.getElementById('modal-empresa');
    const modalEmpresa = new bootstrap.Modal(modalEmpresaEl);
    const formEmpresa = document.getElementById('form-empresa');

    const modalVincularEl = document.getElementById('modal-vincular-propiedades');
    const modalVincular = new bootstrap.Modal(modalVincularEl);
    const formVincular = document.getElementById('form-vincular-propiedades');

    // 1. Abrir Modal Crear
    const btnNueva = document.getElementById('btn-nueva-empresa');
    if (btnNueva) {
        btnNueva.addEventListener('click', function () {
            formEmpresa.reset();
            document.getElementById('empresa-id').value = '';
            document.getElementById('modalEmpresaTitulo').innerHTML = '<i class="fa-solid fa-building text-primary me-2"></i> Registrar Empresa / Emisor';
            modalEmpresa.show();
        });
    }

    // 2. Abrir Modal Editar
    document.querySelectorAll('.btn-editar-empresa').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.getAttribute('data-id');
            try {
                const res = await fetch(`/api/empresas/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (!json.exito) {
                    Swal.fire('Error', json.mensaje || 'No se pudo cargar la empresa.', 'error');
                    return;
                }

                const d = json.datos;
                formEmpresa.reset();
                document.getElementById('empresa-id').value = d.id;
                document.getElementById('empresa-codigo').value = d.codigo || '';
                document.getElementById('empresa-tipo-documento').value = d.tipo_documento_id || '';
                document.getElementById('empresa-num-documento').value = d.numero_documento || '';
                document.getElementById('empresa-razon-social').value = d.razon_social || '';
                document.getElementById('empresa-nombre-comercial').value = d.nombre_comercial || '';
                document.getElementById('empresa-direccion-fiscal').value = d.direccion_fiscal || '';
                document.getElementById('empresa-ubigeo').value = d.ubigeo || '';
                document.getElementById('empresa-departamento').value = d.departamento || '';
                document.getElementById('empresa-provincia').value = d.provincia || '';
                document.getElementById('empresa-distrito').value = d.distrito || '';
                document.getElementById('empresa-telefono').value = d.telefono || '';
                document.getElementById('empresa-email').value = d.email || '';
                document.getElementById('empresa-sitio-web').value = d.sitio_web || '';
                document.getElementById('empresa-representante-id').value = d.representante_persona_id || '';
                document.getElementById('empresa-representante-cargo').value = d.representante_cargo || 'Gerente General';
                document.getElementById('empresa-representante-partida').value = d.representante_poder_partida || '';
                document.getElementById('empresa-es-principal').checked = !!d.es_principal;

                document.getElementById('modalEmpresaTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Empresa / Emisor';
                modalEmpresa.show();
            } catch (err) {
                Swal.fire('Error', 'Falla de comunicación con el servidor.', 'error');
            }
        });
    });

    // 3. Guardar Empresa (Crear / Editar)
    formEmpresa.addEventListener('submit', async function (e) {
        e.preventDefault();

        const id = document.getElementById('empresa-id').value;
        const url = id ? `/api/empresas/${id}` : '/api/empresas';
        const formData = new FormData(formEmpresa);

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const json = await res.json();
            if (json.exito) {
                modalEmpresa.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Operación Exitosa',
                    text: json.mensaje,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Validación', json.mensaje || 'Revise los campos del formulario.', 'warning');
            }
        } catch (err) {
            Swal.fire('Error', 'Error inesperado al guardar la empresa.', 'error');
        }
    });

    // 4. Cambiar Estado
    document.querySelectorAll('.btn-estado-empresa').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const estadoActual = this.getAttribute('data-estado');
            const nuevoEstado = estadoActual === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
            const accionTexto = nuevoEstado === 'ACTIVO' ? 'activar' : 'desactivar';

            Swal.fire({
                title: `¿Desea ${accionTexto} esta empresa?`,
                text: nuevoEstado === 'INACTIVO' ? 'La empresa no podrá emitir nuevos contratos hasta ser reactivada.' : '',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: nuevoEstado === 'ACTIVO' ? '#198754' : '#dc3545',
                confirmButtonText: `Sí, ${accionTexto}`,
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const res = await fetch(`/api/empresas/${id}/estado`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ estado: nuevoEstado, motivo: 'Acción administrativa desde UI' })
                        });
                        const json = await res.json();
                        if (json.exito) {
                            Swal.fire('Actualizado', json.mensaje, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('No permitido', json.mensaje, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                    }
                }
            });
        });
    });

    // 5. Vincular Propiedades Modal
    document.querySelectorAll('.btn-asignar-propiedades').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.getAttribute('data-id');
            document.getElementById('vincular-empresa-id').value = id;

            try {
                const res = await fetch(`/api/empresas/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (!json.exito) {
                    Swal.fire('Error', 'No se pudo cargar la vinculación de propiedades.', 'error');
                    return;
                }

                const vinculadasIds = (json.datos.propiedades || []).map(p => parseInt(p.id, 10));

                document.querySelectorAll('.prop-check-item').forEach(chk => {
                    const pId = parseInt(chk.value, 10);
                    chk.checked = vinculadasIds.includes(pId);
                });

                modalVincular.show();
            } catch (err) {
                Swal.fire('Error', 'Error al consultar propiedades.', 'error');
            }
        });
    });

    // 6. Guardar Vinculación de Propiedades
    formVincular.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('vincular-empresa-id').value;

        const seleccionadas = [];
        document.querySelectorAll('.prop-check-item:checked').forEach(chk => {
            seleccionadas.push(parseInt(chk.value, 10));
        });

        try {
            const res = await fetch(`/api/empresas/${id}/propiedades`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ propiedad_ids: seleccionadas })
            });
            const json = await res.json();
            if (json.exito) {
                modalVincular.hide();
                Swal.fire('Vinculadas', json.mensaje, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', json.mensaje, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Falla de red al asignar propiedades.', 'error');
        }
    });

    // 7. Filtros en caliente
    const inputBusqueda = document.getElementById('filtro-busqueda-empresa');
    const selectEstado = document.getElementById('filtro-estado-empresa');
    const selectPrincipal = document.getElementById('filtro-principal-empresa');

    function aplicarFiltros() {
        const q = inputBusqueda.value.toLowerCase().trim();
        const est = selectEstado.value;
        const princ = selectPrincipal.value;

        document.querySelectorAll('#tbody-empresas tr[data-empresa-id]').forEach(tr => {
            const texto = tr.textContent.toLowerCase();
            const coincideTexto = !q || texto.includes(q);

            const esActivo = tr.querySelector('.badge.bg-success') !== null;
            const coincideEstado = !est || (est === 'ACTIVO' && esActivo) || (est === 'INACTIVO' && !esActivo);

            const esPrinc = tr.querySelector('.fa-star') !== null;
            const coincidePrinc = princ === '' || (princ === '1' && esPrinc) || (princ === '0' && !esPrinc);

            if (coincideTexto && coincideEstado && coincidePrinc) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
        });
    }

    if (inputBusqueda) inputBusqueda.addEventListener('input', aplicarFiltros);
    if (selectEstado) selectEstado.addEventListener('change', aplicarFiltros);
    if (selectPrincipal) selectPrincipal.addEventListener('change', aplicarFiltros);

    const btnLimpiar = document.getElementById('btn-limpiar-busqueda');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            inputBusqueda.value = '';
            aplicarFiltros();
        });
    }

    const btnRecargar = document.getElementById('btn-recargar-empresas');
    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => location.reload());
    }
});
</script>
