<?php

declare(strict_types=1);

/**
 * Vista de Catálogo Maestro de Unidades Físicas y Alojables — Camargo PMS.
 *
 * Principio: PROPIEDAD ≠ UNIDAD (la unidad es la división alojable de la propiedad).
 * Principio: UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD (fechas, precios y bloqueos diferidos).
 * Principio: UNIDAD ≠ REGISTRO DESECHABLE (no DELETE físico, ciclo ACTIVO / INACTIVO).
 *
 * @var array{puede_crear: bool, puede_editar: bool, puede_cambiar_estado: bool} $capacidades
 * @var array<int, array{id: int, codigo: string, nombre: string}> $propiedades
 * @var array<int, array{id: int, codigo: string, nombre: string}> $tiposUnidad
 * @var int|null $propiedadIdFiltro
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
                        <i class="fa-solid fa-door-open f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Maestro Central de Unidades</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Registro de divisiones físicas y unidades habitacionales por propiedad. Principio: <strong>PROPIEDAD 1 ─── N UNIDADES</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-unidad">
                            <i class="fa-solid fa-plus me-1"></i> Nueva Unidad
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-unidad"
                                   placeholder="Buscar por código, nombre o propiedad..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda-unidad" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm basic-select2 select-clear" id="filtro-propiedad-unidad" data-placeholder="Todas las propiedades">
                            <option value="">Todas las propiedades</option>
                            <?php foreach ($propiedades as $pr): ?>
                                <option value="<?= (int)$pr['id'] ?>" <?= $propiedadIdFiltro === (int)$pr['id'] ? 'selected' : '' ?>>
                                    <?= e($pr['nombre']) ?> (<?= e($pr['codigo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm basic-select2 select-clear" id="filtro-tipo-unidad" data-placeholder="Todos los tipos">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tiposUnidad as $tu): ?>
                                <option value="<?= (int)$tu['id'] ?>"><?= e($tu['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm basic-select2 select-clear" id="filtro-estado-unidad" data-placeholder="Todos los estados">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-6 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-recargar-unidades" title="Refrescar datos">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Unidades -->
            <div class="card-body p-0">
                <div class="table-responsive" id="contenedor-tabla-unidades">
                    <table class="table table-hover align-middle mb-0" id="tabla-unidades">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th style="width: 12%;">Código</th>
                                <th style="width: 20%;">Nombre / Unidad</th>
                                <th style="width: 18%;">Propiedad</th>
                                <th style="width: 12%;">Tipo</th>
                                <th style="width: 20%;">Especificaciones Físicas</th>
                                <th style="width: 8%;" class="text-center">Estado</th>
                                <th style="width: 10%;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-unidades">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Cargando catálogo de unidades...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Estado vacío -->
                <div id="estado-vacio-unidades" class="text-center py-5 d-none">
                    <div class="text-muted mb-2">
                        <i class="fa-solid fa-door-closed f-s-48 text-secondary opacity-50"></i>
                    </div>
                    <h5 class="f-s-15 text-secondary mb-1">No se encontraron unidades físicas</h5>
                    <p class="f-s-13 text-muted mb-3">Intente ajustar los filtros de búsqueda o registre una nueva unidad.</p>
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-crear-unidad-vacio">
                            <i class="fa-solid fa-plus me-1"></i> Registrar Primera Unidad
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Paginación -->
            <div class="card-footer bg-white py-2 px-3 border-top d-flex flex-wrap justify-content-between align-items-center">
                <div class="text-muted f-s-12" id="info-paginacion-unidades">
                    Mostrando 0 registros
                </div>
                <nav aria-label="Navegación de unidades">
                    <ul class="pagination pagination-sm mb-0 gap-1" id="paginacion-unidades">
                        <!-- Botones dinámicos generados por JS -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear / Editar Unidad -->
<div class="modal fade" id="modal-unidad" tabindex="-1" aria-labelledby="modal-unidad-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-unidad-titulo">
                    <i class="fa-solid fa-door-open me-2 text-primary"></i>Nueva Unidad
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-unidad" novalidate>
                <input type="hidden" id="unidad-id" name="id" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Relación con Propiedad -->
                        <div class="col-md-6 col-12">
                            <label for="unidad-propiedad-id" class="form-label f-s-13 f-w-600">
                                Propiedad Física <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm basic-select2" id="unidad-propiedad-id" name="propiedad_id" required
                                    data-placeholder="Seleccione una propiedad..."
                                    data-pristine-required-message="Debe seleccionar una propiedad física.">
                                <option value="">Seleccione una propiedad...</option>
                                <?php foreach ($propiedades as $pr): ?>
                                    <option value="<?= (int)$pr['id'] ?>"><?= e($pr['nombre']) ?> (<?= e($pr['codigo']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tipo de Unidad -->
                        <div class="col-md-6 col-12">
                            <label for="unidad-tipo-id" class="form-label f-s-13 f-w-600">
                                Tipo de Unidad <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm basic-select2" id="unidad-tipo-id" name="tipo_unidad_id" required
                                    data-placeholder="Seleccione un tipo..."
                                    data-pristine-required-message="Debe seleccionar el tipo de unidad.">
                                <option value="">Seleccione un tipo...</option>
                                <?php foreach ($tiposUnidad as $tu): ?>
                                    <option value="<?= (int)$tu['id'] ?>"><?= e($tu['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Código de Unidad -->
                        <div class="col-md-4 col-12">
                            <label for="unidad-codigo" class="form-label f-s-13 f-w-600">
                                Código / Identificador <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-barcode position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5 text-uppercase" id="unidad-codigo" name="codigo"
                                       placeholder="Ej: DPTO-101, HAB-01" maxlength="50" required
                                       data-pristine-required-message="El código es obligatorio."
                                       data-pristine-pattern="/^[A-Za-z0-9\-_.\/ ]+$/"
                                       data-pristine-pattern-message="Solo letras, números, guiones y barras.">
                            </div>
                            <div class="form-text f-s-11">Único dentro de la propiedad seleccionada.</div>
                        </div>

                        <!-- Nombre de Unidad -->
                        <div class="col-md-5 col-12">
                            <label for="unidad-nombre" class="form-label f-s-13 f-w-600">
                                Nombre Descriptivo <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-door-open position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="unidad-nombre" name="nombre"
                                       placeholder="Ej: Departamento 101 Vista Mar" maxlength="150" required minlength="2"
                                       data-pristine-required-message="El nombre es obligatorio."
                                       data-pristine-minlength-message="Mínimo 2 caracteres.">
                            </div>
                        </div>

                        <!-- Piso / Nivel -->
                        <div class="col-md-3 col-12">
                            <label for="unidad-piso-nivel" class="form-label f-s-13 f-w-600">
                                Piso / Nivel
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-layer-group position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="unidad-piso-nivel" name="piso_nivel"
                                       placeholder="Ej: 1, 2, PB, Azotea" maxlength="30">
                            </div>
                        </div>

                        <!-- Sección de Características Físicas -->
                        <div class="col-12 mt-3">
                            <h6 class="f-s-12 text-uppercase text-secondary border-bottom pb-2 mb-2">
                                <i class="fa-solid fa-ruler-combined me-1"></i> Características Físicas y Ocupacionales
                            </h6>
                        </div>

                        <!-- Capacidad de Personas -->
                        <div class="col-md-3 col-6">
                            <label for="unidad-capacidad" class="form-label f-s-13 f-w-600">
                                Capacidad (personas) <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user-group position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" class="form-control form-control-sm ps-5" id="unidad-capacidad" name="capacidad_personas"
                                       value="1" min="1" max="100" required
                                       data-pristine-required-message="Capacidad requerida."
                                       data-pristine-min-message="Mínimo 1 persona.">
                            </div>
                        </div>

                        <!-- Dormitorios -->
                        <div class="col-md-3 col-6">
                            <label for="unidad-dormitorios" class="form-label f-s-13 f-w-600">
                                Dormitorios <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-bed position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" class="form-control form-control-sm ps-5" id="unidad-dormitorios" name="dormitorios"
                                       value="1" min="0" max="50" required
                                       data-pristine-required-message="Dormitorios requeridos."
                                       data-pristine-min-message="No puede ser negativo.">
                            </div>
                            <div class="form-text f-s-11">0 para estudios o lofts.</div>
                        </div>

                        <!-- Baños -->
                        <div class="col-md-3 col-6">
                            <label for="unidad-banos" class="form-label f-s-13 f-w-600">
                                Baños <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-bath position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.5" class="form-control form-control-sm ps-5" id="unidad-banos" name="banos"
                                       value="1.0" min="0" max="50" required
                                       data-pristine-required-message="Baños requeridos."
                                       data-pristine-min-message="No puede ser negativo.">
                            </div>
                        </div>

                        <!-- Área m2 -->
                        <div class="col-md-3 col-6">
                            <label for="unidad-area-m2" class="form-label f-s-13 f-w-600">
                                Área (m²)
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-ruler-combined position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.01" class="form-control form-control-sm ps-5" id="unidad-area-m2" name="area_m2"
                                       placeholder="Ej: 65.50" min="0.01" max="99999.99"
                                       data-pristine-min-message="Debe ser mayor a 0 m².">
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label for="unidad-descripcion" class="form-label f-s-13 f-w-600">Descripción / Detalles</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-align-left position-absolute top-0 start-0 mt-2 ms-3 text-secondary"></i>
                                <textarea class="form-control form-control-sm ps-5" id="unidad-descripcion" name="descripcion" rows="2"
                                          placeholder="Descripción de la distribución, vista o características físicas de la unidad..."></textarea>
                            </div>
                        </div>

                        <!-- Observaciones internas -->
                        <div class="col-12">
                            <label for="unidad-observaciones" class="form-label f-s-13 f-w-600">Observaciones Internas</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-2 ms-3 text-secondary"></i>
                                <textarea class="form-control form-control-sm ps-5" id="unidad-observaciones" name="observaciones" rows="1"
                                          placeholder="Notas administrativas o de mantenimiento interno..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-unidad">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-guardar-unidad"></span>
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Unidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Alternar Estado de Unidad -->
<div class="modal fade" id="modal-estado-unidad" tabindex="-1" aria-labelledby="modal-estado-unidad-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h6 class="modal-title f-s-14 f-w-700" id="modal-estado-unidad-titulo">Cambiar Estado</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-estado-unidad">
                <input type="hidden" id="estado-unidad-id" value="">
                <input type="hidden" id="estado-unidad-nuevo" value="">
                <div class="modal-body p-3">
                    <p class="f-s-13 mb-3 text-secondary" id="mensaje-confirmacion-estado-unidad">
                        ¿Confirma el cambio de estado de la unidad?
                    </p>
                    <div class="mb-2">
                        <label for="motivo-cambio-estado-unidad" class="form-label f-s-12 f-w-600">Motivo (opcional)</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-pen-to-square position-absolute top-0 start-0 mt-2 ms-3 text-secondary"></i>
                            <textarea class="form-control form-control-sm ps-5" id="motivo-cambio-estado-unidad" rows="2"
                                      placeholder="Justificación del cambio de estado para trazabilidad..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirmar-estado-unidad">
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Librerías de Validación y Script Modular Propio -->
<script src="<?= url_ruta('/assets/vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_ruta('/assets/js/gestion-unidades.js') ?>"></script>
