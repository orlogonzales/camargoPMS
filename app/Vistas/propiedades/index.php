<?php

declare(strict_types=1);

/**
 * Vista de Catálogo Maestro de Propiedades e Inmuebles Físicos — Camargo PMS.
 *
 * Principio: PROPIEDAD ≠ UNIDAD (inmueble físico contenedor, no unidad arrendable).
 * Principio: PROPIEDAD ≠ REGISTRO DESECHABLE (no DELETE físico, ciclo ACTIVO / INACTIVO).
 * Principio: MENÚ ≠ AUTORIZACIÓN (el backend valida permisos de forma independiente).
 *
 * @var array{puede_crear: bool, puede_editar: bool, puede_cambiar_estado: bool} $capacidades
 * @var array<int, array{id: int, codigo_iso2: string, codigo_iso3: string, nombre: string}> $paises
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
                        <i class="ti ti-building f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Maestro Central de Propiedades</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Registro maestro de inmuebles físicos y contenedores arquitectónicos. Principio: <strong>PROPIEDAD ≠ UNIDAD</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-propiedad">
                            <i class="ti ti-plus me-1"></i> Nueva Propiedad
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-propiedad"
                                   placeholder="Buscar por código, nombre, dirección, distrito..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda-propiedad" title="Limpiar búsqueda">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtro-pais-propiedad">
                            <option value="">Todos los países</option>
                            <?php foreach ($paises as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> (<?= e($p['codigo_iso2']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado-propiedad">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-recargar-propiedades" title="Refrescar datos">
                            <i class="ti ti-refresh me-1"></i> Recargar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Propiedades -->
            <div class="card-body p-0">
                <div class="table-responsive" id="contenedor-tabla-propiedades">
                    <table class="table table-hover align-middle mb-0" id="tabla-propiedades">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th style="width: 15%;">Código</th>
                                <th style="width: 25%;">Nombre / Inmueble</th>
                                <th style="width: 25%;">Ubicación</th>
                                <th style="width: 15%;">Georreferenciación</th>
                                <th style="width: 8%;" class="text-center">Estado</th>
                                <th style="width: 12%;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-propiedades">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Cargando catálogo de propiedades...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pie de Tabla y Paginación -->
            <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center border-top">
                <div class="f-s-12 text-secondary mb-2 mb-md-0" id="info-paginacion-propiedades">
                    Mostrando propiedades...
                </div>
                <nav aria-label="Paginación de propiedades">
                    <ul class="pagination pagination-sm mb-0" id="paginacion-propiedades">
                        <!-- Paginación dinámica -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES ADMINISTRATIVOS -->
<!-- ========================================================================= -->

<!-- 1. Modal: Crear / Editar Propiedad -->
<div class="modal fade" id="modal-propiedad" tabindex="-1" aria-labelledby="modal-propiedad-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-propiedad-titulo">
                    <i class="ti ti-building me-1"></i> <span id="modal-propiedad-accion">Nueva</span> Propiedad Física
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-propiedad" novalidate>
                <input type="hidden" id="propiedad-id" name="id" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Identificación Básica -->
                        <div class="col-md-4">
                            <label for="propiedad-codigo" class="form-label f-s-13 f-w-600">
                                Código Inmueble <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="propiedad-codigo" name="codigo"
                                   required minlength="2" maxlength="30" placeholder="Ej: AYUDA-MUTUA"
                                   data-pristine-required-message="El código es obligatorio."
                                   data-pristine-minlength-message="Mínimo 2 caracteres."
                                   data-pristine-maxlength-message="Máximo 30 caracteres.">
                            <div class="form-text f-s-11 text-muted">Clave técnica única del inmueble.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="propiedad-nombre" class="form-label f-s-13 f-w-600">
                                Nombre del Inmueble / Edificio <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-nombre" name="nombre"
                                   required minlength="3" maxlength="150" placeholder="Ej: Edificio Ayuda Mutua"
                                   data-pristine-required-message="El nombre es obligatorio."
                                   data-pristine-minlength-message="Mínimo 3 caracteres."
                                   data-pristine-maxlength-message="Máximo 150 caracteres.">
                        </div>

                        <!-- País y Localización Política -->
                        <div class="col-md-3">
                            <label for="propiedad-pais-id" class="form-label f-s-13 f-w-600">
                                País <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="propiedad-pais-id" name="pais_id" required
                                    data-pristine-required-message="Debe seleccionar un país.">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-departamento" class="form-label f-s-13 f-w-600">Departamento / Región</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-departamento" name="departamento"
                                   maxlength="100" placeholder="Ej: Cusco">
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-provincia" class="form-label f-s-13 f-w-600">Provincia</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-provincia" name="provincia"
                                   maxlength="100" placeholder="Ej: La Convención">
                        </div>
                        <div class="col-md-3">
                            <label for="propiedad-distrito" class="form-label f-s-13 f-w-600">Distrito</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-distrito" name="distrito"
                                   maxlength="100" placeholder="Ej: Santa Ana">
                        </div>

                        <!-- Dirección Física y Referencia -->
                        <div class="col-md-7">
                            <label for="propiedad-direccion" class="form-label f-s-13 f-w-600">Dirección Física</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-direccion" name="direccion"
                                   maxlength="255" placeholder="Ej: Jirón Independencia 245">
                        </div>
                        <div class="col-md-5">
                            <label for="propiedad-referencia" class="form-label f-s-13 f-w-600">Referencia de Acceso</label>
                            <input type="text" class="form-control form-control-sm" id="propiedad-referencia" name="referencia"
                                   maxlength="255" placeholder="Ej: Frente a la plaza de armas">
                        </div>

                        <!-- Georreferenciación GPS -->
                        <div class="col-md-6">
                            <label for="propiedad-latitud" class="form-label f-s-13 f-w-600">Latitud GPS</label>
                            <input type="number" step="0.0000001" min="-90" max="90" class="form-control form-control-sm"
                                   id="propiedad-latitud" name="latitud" placeholder="Ej: -12.8661234">
                            <div class="form-text f-s-11 text-muted">Coordenada decimal entre -90.0 y 90.0</div>
                        </div>
                        <div class="col-md-6">
                            <label for="propiedad-longitud" class="form-label f-s-13 f-w-600">Longitud GPS</label>
                            <input type="number" step="0.0000001" min="-180" max="180" class="form-control form-control-sm"
                                   id="propiedad-longitud" name="longitud" placeholder="Ej: -72.6951234">
                            <div class="form-text f-s-11 text-muted">Coordenada decimal entre -180.0 y 180.0</div>
                        </div>

                        <!-- Descripción e Información Adicional -->
                        <div class="col-12">
                            <label for="propiedad-descripcion" class="form-label f-s-13 f-w-600">Descripción General</label>
                            <textarea class="form-control form-control-sm" id="propiedad-descripcion" name="descripcion"
                                      rows="2" placeholder="Resumen arquitectónico o características del inmueble..."></textarea>
                        </div>
                        <div class="col-12">
                            <label for="propiedad-observaciones" class="form-label f-s-13 f-w-600">Observaciones Internas</label>
                            <textarea class="form-control form-control-sm" id="propiedad-observaciones" name="observaciones"
                                      rows="2" placeholder="Notas operativas privadas de administración..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-propiedad">
                        <i class="ti ti-check me-1"></i> Guardar Propiedad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SCRIPTS Y ASSETS VINCULADOS -->
<!-- ========================================================================= -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-propiedades.js') ?>"></script>
