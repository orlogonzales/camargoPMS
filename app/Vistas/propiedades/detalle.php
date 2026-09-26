<?php

declare(strict_types=1);

use CamargoPMS\Modelos\Propiedad;

/**
 * Vista de Perfil y Ficha Técnica de Propiedad Física — Camargo PMS.
 *
 * Principio: PROPIEDAD ≠ UNIDAD (inmueble físico contenedor, no unidad arrendable).
 * Principio: PROPIEDAD ≠ REGISTRO DESECHABLE (no DELETE físico, ciclo ACTIVO / INACTIVO).
 *
 * @var Propiedad $propiedad
 * @var array<int, array{id: int, codigo_iso2: string, codigo_iso3: string, nombre: string}> $paises
 * @var array{puede_editar: bool, puede_cambiar_estado: bool} $capacidades
 * @var string $csrf_token
 */

$coords = $propiedad->obtenerCoordenadas();
$lat = $coords['latitud'];
$lng = $coords['longitud'];
$tieneCoordenadas = $lat !== null && $lng !== null;
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado de la Ficha -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="bg-primary-subtle text-primary p-3 b-r-12 me-3 d-flex-center">
                            <i class="ti ti-building f-s-32"></i>
                        </span>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h3 class="mb-0 f-s-22 f-w-700"><?= e($propiedad->obtenerNombre()) ?></h3>
                                <span class="badge bg-secondary-subtle text-secondary f-s-12">
                                    <?= e($propiedad->obtenerCodigo()) ?>
                                </span>
                                <?php if ($propiedad->estaActiva()): ?>
                                    <span class="badge bg-success-subtle text-success f-s-12">ACTIVO</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger f-s-12">INACTIVO</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-secondary f-s-13 mb-0">
                                <i class="ti ti-map-pin me-1"></i> <?= e($propiedad->obtenerUbicacionCompleta()) ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 mt-md-0 d-flex gap-2">
                        <a href="<?= url_ruta('/propiedades') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Volver al Catálogo
                        </a>
                        <?php if (!empty($capacidades['puede_editar'])): ?>
                            <button type="button" class="btn btn-primary btn-sm" id="btn-perfil-editar-propiedad"
                                    data-id="<?= (int) $propiedad->obtenerId() ?>">
                                <i class="ti ti-edit me-1"></i> Editar Inmueble
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($capacidades['puede_cambiar_estado'])): ?>
                            <button type="button" class="btn btn-outline-<?= $propiedad->estaActiva() ? 'warning' : 'success' ?> btn-sm"
                                    id="btn-perfil-cambiar-estado"
                                    data-id="<?= (int) $propiedad->obtenerId() ?>"
                                    data-nombre="<?= e($propiedad->obtenerNombre()) ?>"
                                    data-estado="<?= e($propiedad->obtenerEstado()) ?>">
                                <i class="ti ti-power me-1"></i> <?= $propiedad->estaActiva() ? 'Desactivar' : 'Activar' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Columna Izquierda: Información Técnica y Georreferenciación -->
    <div class="col-lg-8 col-12">
        <!-- Tarjeta de Identificación y Dirección -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 f-s-15 f-w-700 text-dark">
                    <i class="ti ti-info-circle me-1 text-primary"></i> Información del Inmueble y Localización
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted f-s-12 d-block">Código del Inmueble</span>
                        <strong class="f-s-14 text-dark"><?= e($propiedad->obtenerCodigo()) ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted f-s-12 d-block">Nombre Oficial</span>
                        <strong class="f-s-14 text-dark"><?= e($propiedad->obtenerNombre()) ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted f-s-12 d-block">País</span>
                        <span class="f-s-14 text-dark"><?= e($propiedad->obtenerPaisNombre() ?? 'No especificado') ?></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted f-s-12 d-block">Departamento / Región</span>
                        <span class="f-s-14 text-dark"><?= e($propiedad->obtenerDepartamento() ?? '—') ?></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted f-s-12 d-block">Provincia</span>
                        <span class="f-s-14 text-dark"><?= e($propiedad->obtenerProvincia() ?? '—') ?></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted f-s-12 d-block">Distrito</span>
                        <span class="f-s-14 text-dark"><?= e($propiedad->obtenerDistrito() ?? '—') ?></span>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted f-s-12 d-block">Dirección Física</span>
                        <span class="f-s-14 text-dark"><?= e($propiedad->obtenerDireccion() ?? 'Sin dirección registrada') ?></span>
                    </div>
                    <div class="col-md-12">
                        <span class="text-muted f-s-12 d-block">Referencia de Acceso</span>
                        <span class="f-s-14 text-dark"><?= e($propiedad->obtenerReferencia() ?? 'Sin referencia') ?></span>
                    </div>
                    <div class="col-12">
                        <span class="text-muted f-s-12 d-block">Descripción General</span>
                        <p class="f-s-13 text-secondary mb-0">
                            <?= nl2br(e($propiedad->obtenerDescripcion() ?? 'Sin descripción adicional.')) ?>
                        </p>
                    </div>
                    <?php if ($propiedad->obtenerObservaciones() !== null && $propiedad->obtenerObservaciones() !== ''): ?>
                        <div class="col-12">
                            <span class="text-muted f-s-12 d-block">Observaciones de Administración</span>
                            <div class="alert alert-light border p-3 f-s-13 mb-0">
                                <?= nl2br(e($propiedad->obtenerObservaciones())) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Contenedor Arquitectónico (Principio PROPIEDAD ≠ UNIDAD) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 f-s-15 f-w-700 text-dark">
                    <i class="ti ti-layout-grid me-1 text-primary"></i> Contenedor de Unidades Arrendables
                </h5>
                <span class="badge bg-primary-subtle text-primary f-s-11">Arquitectura Estructural</span>
            </div>
            <div class="card-body p-4 text-center py-5">
                <div class="p-3 b-r-12 bg-light-subtle d-inline-block mb-3">
                    <i class="ti ti-door f-s-36 text-muted"></i>
                </div>
                <h6 class="f-w-700 f-s-16 mb-2">Principio de Dominio: PROPIEDAD ≠ UNIDAD</h6>
                <p class="text-secondary f-s-13 mx-auto" style="max-width: 580px;">
                    Este inmueble físico actúa exclusivamente como contenedor estructural. Las unidades arrendables
                    (departamentos, habitaciones, oficinas) pertenecen a esta propiedad física y se gestionarán en la fase posterior
                    <strong>UNIDADES-1</strong>, conservando la estricta separación de dominio.
                </p>
                <div class="mt-3">
                    <span class="badge bg-secondary f-s-12 px-3 py-2">
                        <i class="ti ti-clock me-1"></i> Gestión de unidades habilitada en UNIDADES-1
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Georreferenciación y Metadatos de Auditoría -->
    <div class="col-lg-4 col-12">
        <!-- Georreferenciación GPS -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 f-s-15 f-w-700 text-dark">
                    <i class="ti ti-compass me-1 text-primary"></i> Georreferenciación GPS
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if ($tieneCoordenadas): ?>
                    <div class="mb-3">
                        <span class="text-muted f-s-12 d-block">Latitud</span>
                        <strong class="f-s-14 text-dark"><?= number_format((float) $lat, 7) ?></strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted f-s-12 d-block">Longitud</span>
                        <strong class="f-s-14 text-dark"><?= number_format((float) $lng, 7) ?></strong>
                    </div>
                    <div class="d-grid mt-3">
                        <a href="https://www.google.com/maps?q=<?= (float) $lat ?>,<?= (float) $lng ?>"
                           target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                            <i class="ti ti-map-2 me-1"></i> Ver en Google Maps
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <i class="ti ti-map-off f-s-32 d-block mb-2"></i>
                        <span class="f-s-13">Sin coordenadas GPS registradas.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Trazabilidad y Auditoría Transversal (D-061) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 f-s-15 f-w-700 text-dark">
                    <i class="ti ti-history me-1 text-primary"></i> Auditoría y Trazabilidad
                </h5>
            </div>
            <div class="card-body p-4">
                <ul class="list-unstyled mb-0 f-s-13">
                    <li class="mb-3">
                        <span class="text-muted d-block f-s-12">Estado Operativo</span>
                        <span class="badge bg-<?= $propiedad->estaActiva() ? 'success' : 'danger' ?>-subtle text-<?= $propiedad->estaActiva() ? 'success' : 'danger' ?> f-s-12">
                            <?= e($propiedad->obtenerEstado()) ?>
                        </span>
                    </li>
                    <li class="mb-3">
                        <span class="text-muted d-block f-s-12">Fecha de Registro</span>
                        <strong class="text-dark"><?= e($propiedad->obtenerCreadoEn() ?? '—') ?></strong>
                    </li>
                    <li>
                        <span class="text-muted d-block f-s-12">Última Actualización</span>
                        <strong class="text-dark"><?= e($propiedad->obtenerActualizadoEn() ?? '—') ?></strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL DE EDICIÓN RÁPIDA -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-propiedad" tabindex="-1" aria-labelledby="modal-propiedad-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-propiedad-titulo">
                    <i class="ti ti-building me-1"></i> <span id="modal-propiedad-accion">Editar</span> Propiedad Física
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-propiedad" novalidate>
                <input type="hidden" id="propiedad-id" name="id" value="<?= (int) $propiedad->obtenerId() ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="propiedad-codigo" class="form-label f-s-13 f-w-600">
                                Código Inmueble <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="propiedad-codigo" name="codigo"
                                   required minlength="2" maxlength="30" value="<?= e($propiedad->obtenerCodigo()) ?>">
                        </div>
                        <div class="col-md-8">
                            <label for="propiedad-nombre" class="form-label f-s-13 f-w-600">
                                Nombre del Inmueble / Edificio <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-nombre" name="nombre"
                                   required minlength="3" maxlength="150" value="<?= e($propiedad->obtenerNombre()) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-pais-id" class="form-label f-s-13 f-w-600">
                                País <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="propiedad-pais-id" name="pais_id" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= $p['id'] === $propiedad->obtenerPaisId() ? 'selected' : '' ?>>
                                        <?= e($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-departamento" class="form-label f-s-13 f-w-600">Departamento / Región</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-departamento" name="departamento"
                                   maxlength="100" value="<?= e($propiedad->obtenerDepartamento() ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-provincia" class="form-label f-s-13 f-w-600">Provincia</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-provincia" name="provincia"
                                   maxlength="100" value="<?= e($propiedad->obtenerProvincia() ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-distrito" class="form-label f-s-13 f-w-600">Distrito</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-distrito" name="distrito"
                                   maxlength="100" value="<?= e($propiedad->obtenerDistrito() ?? '') ?>">
                        </div>
                        <div class="col-md-7">
                            <label for="propiedad-direccion" class="form-label f-s-13 f-w-600">Dirección Física</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-direccion" name="direccion"
                                   maxlength="255" value="<?= e($propiedad->obtenerDireccion() ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="propiedad-referencia" class="form-label f-s-13 f-w-600">Referencia de Acceso</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-referencia" name="referencia"
                                   maxlength="255" value="<?= e($propiedad->obtenerReferencia() ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="propiedad-latitud" class="form-label f-s-13 f-w-600">Latitud GPS</label>
                            <input type="number" step="0.0000001" min="-90" max="90" class="form-control form-control-sm"
                                   id="propiedad-latitud" name="latitud" value="<?= $lat !== null ? (float) $lat : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="propiedad-longitud" class="form-label f-s-13 f-w-600">Longitud GPS</label>
                            <input type="number" step="0.0000001" min="-180" max="180" class="form-control form-control-sm"
                                   id="propiedad-longitud" name="longitud" value="<?= $lng !== null ? (float) $lng : '' ?>">
                        </div>
                        <div class="col-12">
                            <label for="propiedad-descripcion" class="form-label f-s-13 f-w-600">Descripción General</label>
                            <textarea class="form-control form-control-sm" id="propiedad-descripcion" name="descripcion"
                                      rows="2"><?= e($propiedad->obtenerDescripcion() ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="propiedad-observaciones" class="form-label f-s-13 f-w-600">Observaciones Internas</label>
                            <textarea class="form-control form-control-sm" id="propiedad-observaciones" name="observaciones"
                                      rows="2"><?= e($propiedad->obtenerObservaciones() ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-propiedad">
                        <i class="ti ti-check me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-propiedades.js') ?>"></script>
