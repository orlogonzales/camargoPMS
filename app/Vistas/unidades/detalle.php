<?php

declare(strict_types=1);

/**
 * Vista de Ficha Técnica y Perfil de Unidad — Camargo PMS.
 *
 * Principio: PROPIEDAD ≠ UNIDAD (la unidad pertenece a una propiedad física).
 * Principio: UNIDAD ≠ REGISTRO DESECHABLE (no DELETE físico, ciclo ACTIVO / INACTIVO).
 *
 * @var \CamargoPMS\Modelos\Unidad $unidad
 * @var \CamargoPMS\Modelos\Propiedad|null $propiedad
 * @var array<int, array{id: int, codigo: string, nombre: string}> $tiposUnidad
 * @var array{puede_editar: bool, puede_cambiar_estado: bool} $capacidades
 * @var array<int, array<string, mixed>> $historialAuditoria
 * @var string $csrf_token
 */

$esActiva = $unidad->estaActiva();
$estadoClase = $esActiva ? 'bg-light-success' : 'bg-light-danger';
$estadoTexto = $esActiva ? 'ACTIVO' : 'INACTIVO';
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado de la Ficha Técnica -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <span class="bg-primary-subtle text-primary p-3 b-r-10 me-3 d-flex-center">
                            <i class="fa-solid fa-door-open f-s-28"></i>
                        </span>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="chip bg-dark text-white f-s-12 f-w-700">
                                    <?= e($unidad->obtenerCodigo()) ?>
                                </span>
                                <span class="badge <?= $estadoClase ?> f-s-11" id="badge-estado-perfil">
                                    <i class="<?= $esActiva ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark' ?> me-1"></i><?= $estadoTexto ?>
                                </span>
                                <?php if ($unidad->obtenerTipoUnidadNombre()): ?>
                                    <?= insignia_chip($unidad->obtenerTipoUnidadNombre(), 'info', null, 'f-s-11') ?>
                                <?php endif; ?>
                            </div>
                            <h3 class="f-s-22 f-w-700 mb-0 text-dark"><?= e($unidad->obtenerNombre()) ?></h3>
                            <p class="text-secondary f-s-13 mb-0">
                                Inmueble raíz: 
                                <?php if ($propiedad !== null): ?>
                                    <a href="<?= url_ruta("/propiedades/{$propiedad->obtenerId()}/perfil") ?>" class="fw-semibold text-primary text-decoration-none">
                                        <i class="fa-solid fa-building me-1"></i><?= e($propiedad->obtenerNombre()) ?> (<?= e($propiedad->obtenerCodigo()) ?>)
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Propiedad ID: <?= $unidad->obtenerPropiedadId() ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= url_ruta('/unidades') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-arrow-left me-1"></i> Volver a Unidades
                        </a>
                        <?php if ($propiedad !== null): ?>
                            <a href="<?= url_ruta("/propiedades/{$propiedad->obtenerId()}/perfil") ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-building me-1"></i> Ver Propiedad
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($capacidades['puede_editar'])): ?>
                            <button type="button" class="btn btn-primary btn-sm" id="btn-editar-unidad-perfil" data-id="<?= (int)$unidad->obtenerId() ?>">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($capacidades['puede_cambiar_estado'])): ?>
                            <?php if ($esActiva): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-cambiar-estado-perfil"
                                        data-id="<?= (int)$unidad->obtenerId() ?>" data-nuevo-estado="INACTIVO">
                                    <i class="fa-solid fa-ban me-1"></i> Desactivar
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-success btn-sm" id="btn-cambiar-estado-perfil"
                                        data-id="<?= (int)$unidad->obtenerId() ?>" data-nuevo-estado="ACTIVO">
                                    <i class="fa-solid fa-check me-1"></i> Activar
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Columna Principal: Especificaciones y Detalles -->
    <div class="col-lg-8 col-12">
        <!-- Tarjeta: Especificaciones Físicas y Ocupacionales -->
        <div class="card equal-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="fa-solid fa-ruler-combined text-primary me-2 f-s-18"></i>
                <h5 class="card-title mb-0 f-s-16 f-w-700">Especificaciones Físicas y Ocupacionales</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4 col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 text-uppercase d-block mb-1">Capacidad Máxima</span>
                            <span class="f-s-20 f-w-700 text-dark">
                                <i class="fa-solid fa-users me-1 text-primary"></i><?= $unidad->obtenerCapacidadPersonas() ?>
                            </span>
                            <small class="text-muted d-block f-s-11">personas</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 text-uppercase d-block mb-1">Dormitorios</span>
                            <span class="f-s-20 f-w-700 text-dark">
                                <i class="fa-solid fa-bed me-1 text-primary"></i><?= $unidad->obtenerDormitorios() ?>
                            </span>
                            <small class="text-muted d-block f-s-11"><?= $unidad->obtenerDormitorios() === 0 ? 'tipo estudio' : 'habitaciones' ?></small>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 text-uppercase d-block mb-1">Baños</span>
                            <span class="f-s-20 f-w-700 text-dark">
                                <i class="fa-solid fa-bath me-1 text-primary"></i><?= $unidad->obtenerBanos() ?>
                            </span>
                            <small class="text-muted d-block f-s-11">completos / medios</small>
                        </div>
                    </div>
                    <div class="col-md-6 col-6">
                        <div class="p-3 bg-light rounded">
                            <span class="text-secondary f-s-12 text-uppercase d-block mb-1">Área Construida</span>
                            <span class="f-s-18 f-w-700 text-dark">
                                <i class="fa-solid fa-vector-square me-1 text-primary"></i>
                                <?= $unidad->obtenerAreaM2() !== null ? e((string)$unidad->obtenerAreaM2()) . ' m²' : 'No registrada' ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="p-3 bg-light rounded">
                            <span class="text-secondary f-s-12 text-uppercase d-block mb-1">Piso / Nivel</span>
                            <span class="f-s-18 f-w-700 text-dark">
                                <i class="fa-solid fa-layer-group me-1 text-primary"></i>
                                <?= $unidad->obtenerPisoNivel() ? e($unidad->obtenerPisoNivel()) : 'No especificado' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Descripción y Observaciones -->
        <div class="card equal-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="fa-solid fa-clipboard text-primary me-2 f-s-18"></i>
                <h5 class="card-title mb-0 f-s-16 f-w-700">Descripción y Notas</h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label f-s-12 text-uppercase text-secondary f-w-600">Descripción de la Unidad</label>
                    <p class="f-s-14 text-dark mb-0 bg-light p-3 rounded">
                        <?= $unidad->obtenerDescripcion() ? nl2br(e($unidad->obtenerDescripcion())) : '<em class="text-muted">Sin descripción detallada.</em>' ?>
                    </p>
                </div>
                <div>
                    <label class="form-label f-s-12 text-uppercase text-secondary f-w-600">Observaciones Internas</label>
                    <p class="f-s-13 text-secondary mb-0 bg-light p-3 rounded">
                        <?= $unidad->obtenerObservaciones() ? nl2br(e($unidad->obtenerObservaciones())) : '<em class="text-muted">Sin observaciones internas registradas.</em>' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Ámbito Operativo Futuro (Placeholder Arquitectónico) -->
        <div class="card equal-card shadow-sm border-0 mb-4 bg-light-subtle border-start border-4 border-info">
            <div class="card-body p-4">
                <div class="d-flex align-items-start">
                    <span class="bg-info-subtle text-info p-2 rounded me-3 mt-1">
                        <i class="fa-solid fa-calendar-day f-s-24"></i>
                    </span>
                    <div>
                        <h6 class="f-s-15 f-w-700 text-dark mb-1">Operativa Inmobiliaria y Comercial (Fases Futuras)</h6>
                        <p class="f-s-13 text-secondary mb-2">
                            En estricto cumplimiento de los principios arquitectónicos de gobernanza:
                        </p>
                        <ul class="f-s-12 text-muted mb-0 ps-3">
                            <li><strong>UNIDAD ≠ DISPONIBILIDAD:</strong> El calendario, las noches ocupadas y los bloqueos por mantenimiento se gestionarán en la fase <code>DISPONIBILIDAD</code> tras resolver P-004 y P-006.</li>
                            <li><strong>UNIDAD ≠ TARIFA:</strong> Las tarifas por noche, mes, temporadas e impuestos se gestionarán en la fase <code>TARIFAS</code> tras resolver P-005.</li>
                            <li><strong>UNIDAD ≠ RESERVA / CONTRATO:</strong> Las transacciones de hospedaje y arrendamiento se modelarán en sus respectivos módulos de operaciones.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Lateral: Estado, Propiedad y Auditoría -->
    <div class="col-lg-4 col-12">
        <!-- Tarjeta: Inmueble Contenedor -->
        <div class="card equal-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="fa-solid fa-building text-primary me-2 f-s-18"></i>
                <h5 class="card-title mb-0 f-s-15 f-w-700">Propiedad Contenedora</h5>
            </div>
            <div class="card-body p-3">
                <?php if ($propiedad !== null): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white p-2 rounded me-2">
                            <i class="fa-solid fa-building f-s-20"></i>
                        </div>
                        <div>
                            <h6 class="f-s-14 f-w-700 mb-0"><?= e($propiedad->obtenerNombre()) ?></h6>
                            <?= insignia_chip($propiedad->obtenerCodigo(), 'secondary', null, 'f-s-11') ?>
                        </div>
                    </div>
                    <ul class="list-unstyled f-s-12 text-secondary mb-3">
                        <li class="mb-1"><i class="fa-solid fa-location-dot me-1 text-muted"></i><?= e($propiedad->obtenerDireccion()) ?></li>
                        <li class="mb-1"><i class="fa-solid fa-globe me-1 text-muted"></i><?= e($propiedad->obtenerUbicacionCompleta()) ?></li>
                        <li><i class="fa-solid fa-wave-square me-1 text-muted"></i>Estado: <?= insignia_estado($propiedad->obtenerEstado(), false, 'f-s-10') ?></li>
                    </ul>
                    <a href="<?= url_ruta("/propiedades/{$propiedad->obtenerId()}/perfil") ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir Ficha de Propiedad
                    </a>
                <?php else: ?>
                    <p class="text-muted f-s-13 mb-0">Información de propiedad no disponible.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tarjeta: Trazabilidad y Auditoría Transversal -->
        <div class="card equal-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2 f-s-18"></i>
                <h5 class="card-title mb-0 f-s-15 f-w-700">Trazabilidad y Auditoría (D-061)</h5>
            </div>
            <div class="card-body p-3">
                <div class="mb-3 f-s-12 text-secondary">
                    <div><strong>Fecha de Creación:</strong> <?= e((string)$unidad->obtenerCreadoEn()) ?></div>
                    <div><strong>Última Actualización:</strong> <?= e((string)$unidad->obtenerActualizadoEn()) ?></div>
                </div>

                <h6 class="f-s-12 text-uppercase text-secondary border-bottom pb-1 mb-2">Últimos Eventos Auditados</h6>
                <?php if (!empty($historialAuditoria)): ?>
                    <div class="timeline-feed f-s-12">
                        <?php foreach ($historialAuditoria as $ev): ?>
                            <div class="border-bottom py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <?= insignia_badge((string)$ev['accion'], 'secondary', null, 'f-s-10 f-w-700 font-monospace') ?>
                                    <span class="text-muted f-s-11"><?= e((string)$ev['creado_en']) ?></span>
                                </div>
                                <div class="text-dark mt-1"><?= e((string)($ev['descripcion'] ?? 'Evento registrado')) ?></div>
                                <?php if (!empty($ev['actor_codigo'])): ?>
                                    <small class="text-muted">Actor: <?= e((string)$ev['actor_codigo']) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted f-s-12 mb-0">No se registran eventos previos de auditoría.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Unidad (en el perfil) -->
<div class="modal fade" id="modal-unidad" tabindex="-1" aria-labelledby="modal-unidad-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modal-unidad-titulo">
                    <i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Editar Unidad
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-unidad" novalidate>
                <input type="hidden" id="unidad-id" name="id" value="<?= (int)$unidad->obtenerId() ?>">
                <input type="hidden" id="unidad-propiedad-id" name="propiedad_id" value="<?= (int)$unidad->obtenerPropiedadId() ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Propiedad Física</label>
                            <input type="text" class="form-control form-control-sm bg-light" 
                                   value="<?= $propiedad ? e($propiedad->obtenerNombre()) : 'ID ' . $unidad->obtenerPropiedadId() ?>" readonly>
                        </div>
                        <div class="col-md-6 col-12">
                            <label for="unidad-tipo-id" class="form-label f-s-13 f-w-600">
                                Tipo de Unidad <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="unidad-tipo-id" name="tipo_unidad_id" required>
                                <?php foreach ($tiposUnidad as $tu): ?>
                                    <option value="<?= (int)$tu['id'] ?>" <?= $unidad->obtenerTipoUnidadId() === (int)$tu['id'] ? 'selected' : '' ?>>
                                        <?= e($tu['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 col-12">
                            <label for="unidad-codigo" class="form-label f-s-13 f-w-600">
                                Código / Identificador <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="unidad-codigo" name="codigo"
                                   value="<?= e($unidad->obtenerCodigo()) ?>" required maxlength="50">
                        </div>
                        <div class="col-md-5 col-12">
                            <label for="unidad-nombre" class="form-label f-s-13 f-w-600">
                                Nombre Descriptivo <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="unidad-nombre" name="nombre"
                                   value="<?= e($unidad->obtenerNombre()) ?>" required maxlength="150" minlength="2">
                        </div>
                        <div class="col-md-3 col-12">
                            <label for="unidad-piso-nivel" class="form-label f-s-13 f-w-600">Piso / Nivel</label>
                            <input type="text" class="form-control form-control-sm" id="unidad-piso-nivel" name="piso_nivel"
                                   value="<?= e((string)$unidad->obtenerPisoNivel()) ?>" maxlength="30">
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="unidad-capacidad" class="form-label f-s-13 f-w-600">Capacidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="unidad-capacidad" name="capacidad_personas"
                                   value="<?= $unidad->obtenerCapacidadPersonas() ?>" min="1" max="100" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="unidad-dormitorios" class="form-label f-s-13 f-w-600">Dormitorios <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="unidad-dormitorios" name="dormitorios"
                                   value="<?= $unidad->obtenerDormitorios() ?>" min="0" max="50" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="unidad-banos" class="form-label f-s-13 f-w-600">Baños <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" class="form-control form-control-sm" id="unidad-banos" name="banos"
                                   value="<?= $unidad->obtenerBanos() ?>" min="0" max="50" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="unidad-area-m2" class="form-label f-s-13 f-w-600">Área (m²)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="unidad-area-m2" name="area_m2"
                                   value="<?= $unidad->obtenerAreaM2() !== null ? e((string)$unidad->obtenerAreaM2()) : '' ?>" min="0.01" max="99999.99">
                        </div>
                        <div class="col-12">
                            <label for="unidad-descripcion" class="form-label f-s-13 f-w-600">Descripción</label>
                            <textarea class="form-control form-control-sm" id="unidad-descripcion" name="descripcion" rows="2"><?= e((string)$unidad->obtenerDescripcion()) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="unidad-observaciones" class="form-label f-s-13 f-w-600">Observaciones</label>
                            <textarea class="form-control form-control-sm" id="unidad-observaciones" name="observaciones" rows="1"><?= e((string)$unidad->obtenerObservaciones()) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-unidad">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
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
                <input type="hidden" id="estado-unidad-id" value="<?= (int)$unidad->obtenerId() ?>">
                <input type="hidden" id="estado-unidad-nuevo" value="<?= $esActiva ? 'INACTIVO' : 'ACTIVO' ?>">
                <div class="modal-body p-3">
                    <p class="f-s-13 mb-3 text-secondary" id="mensaje-confirmacion-estado-unidad">
                        ¿Confirma <?= $esActiva ? 'desactivar' : 'activar' ?> esta unidad habitacional?
                    </p>
                    <div class="mb-2">
                        <label for="motivo-cambio-estado-unidad" class="form-label f-s-12 f-w-600">Motivo (opcional)</label>
                        <textarea class="form-control form-control-sm" id="motivo-cambio-estado-unidad" rows="2"
                                  placeholder="Justificación del cambio de estado..."></textarea>
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

<script src="<?= url_ruta('/assets/vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_ruta('/assets/js/gestion-unidades.js') ?>"></script>
