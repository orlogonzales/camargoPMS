<?php

declare(strict_types=1);

/**
 * Vista de Gestión Operativa de Canales y Conexiones iCalendar — Camargo PMS (AIRBNB-ICAL-1C).
 *
 * Principios vinculantes:
 * - ALINA DESIGN SYSTEM: Cards equal-card, tablas bordeadas/striped, badges bg-light-* con bordes continuos sólidos sin clases obsoletas.
 * - PROTECCIÓN RADICAL DE SECRETOS: Ni URLs de importación ni tokens se imprimen en el HTML inicial o DOM.
 * - SOBERANÍA: Evento iCal ≠ Reserva PMS. Preservación absoluta de reservas locales ante conflictos.
 * - ZERO ALERT/CONFIRM: SweetAlert2 exclusivo para diálogos y confirmaciones.
 * - ZERO JQUERY CRUD: Vanilla JS y Fetch nativo para comunicación con el backend.
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array{puede_gestionar: bool, puede_sincronizar: bool} $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\CanalDistribucion[] $canales
 * @var array<array<string, mixed>> $propiedades
 * @var array<array<string, mixed>> $unidades
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Canales iCal -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-arrows-rotate f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Canales de Distribución y Conexiones iCalendar</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión soberana multicanal hotelera, sincronización manual, feeds de exportación y resolución de conflictos. Principio: <strong>EVENTO EXTERNO ≠ RESERVA</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if ($permisos['puede_gestionar']): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nueva-conexion">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Conexión
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-ver-conflictos-globales">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Conflictos Activos
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Indicadores Operativos (KPIs) -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border">
                            <span class="bg-light-primary text-primary p-2 rounded-circle me-3">
                                <i class="fa-solid fa-plug f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Total Conexiones</span>
                                <h5 class="mb-0 f-w-700" id="kpi-total-conexiones">0</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border">
                            <span class="bg-light-success text-success p-2 rounded-circle me-3">
                                <i class="fa-solid fa-circle-check f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Conexiones Activas</span>
                                <h5 class="mb-0 f-w-700 text-success" id="kpi-activas-conexiones">0</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border">
                            <span class="bg-light-danger text-danger p-2 rounded-circle me-3">
                                <i class="fa-solid fa-triangle-exclamation f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Errores de Sync</span>
                                <h5 class="mb-0 f-w-700 text-danger" id="kpi-errores-conexiones">0</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border">
                            <span class="bg-light-warning text-warning p-2 rounded-circle me-3">
                                <i class="fa-solid fa-handshake-slash f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Conflictos de Inventario</span>
                                <h5 class="mb-0 f-w-700 text-warning" id="kpi-conflictos-pendientes">0</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros Operativos -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-12">
                        <label for="filtro-propiedad" class="form-label f-s-12 text-secondary mb-1">Propiedad</label>
                        <select id="filtro-propiedad" class="form-select form-select-sm select2-filtro">
                            <option value="">Todas las propiedades</option>
                            <?php foreach ($propiedades as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-12">
                        <label for="filtro-canal" class="form-label f-s-12 text-secondary mb-1">Canal de Distribución</label>
                        <select id="filtro-canal" class="form-select form-select-sm select2-filtro">
                            <option value="">Todos los canales</option>
                            <?php foreach ($canales as $c): ?>
                            <option value="<?= (int) $c->obtenerId() ?>"><?= e($c->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-12">
                        <label for="filtro-estado" class="form-label f-s-12 text-secondary mb-1">Estado</label>
                        <select id="filtro-estado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">Activo</option>
                            <option value="PAUSADO">Pausado</option>
                            <option value="REVOCADO">Revocado</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <label for="filtro-busqueda" class="form-label f-s-12 text-secondary mb-1">Búsqueda rápida</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda"
                                   placeholder="Buscar por unidad, canal o nombre..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal Alina (Bordered With Striped + Hoverable) -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-conexiones-ical">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th style="width: 22%;">Unidad / Propiedad</th>
                                <th style="width: 14%;">Canal</th>
                                <th style="width: 18%;">Conexión</th>
                                <th style="width: 12%;">Modos</th>
                                <th style="width: 10%;">Estado</th>
                                <th style="width: 14%;">Última Sincronización</th>
                                <th style="width: 10%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-conexiones-ical">
                            <!-- Skeleton Loader Inicial -->
                            <tr class="skeleton-row">
                                <td colspan="7">
                                    <div class="placeholder-glow py-3">
                                        <span class="placeholder col-4 d-block mb-2"></span>
                                        <span class="placeholder col-6 d-block mb-2"></span>
                                        <span class="placeholder col-3 d-block"></span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mensaje de Tabla Vacía -->
                <div id="mensaje-vacio-conexiones" class="text-center py-5 d-none">
                    <div class="text-muted mb-2">
                        <i class="fa-solid fa-calendar-xmark f-s-40 text-secondary"></i>
                    </div>
                    <h6 class="f-w-600 text-secondary">No se encontraron conexiones iCalendar</h6>
                    <p class="text-muted f-s-13 mb-3">No existen registros que coincidan con los filtros aplicados.</p>
                    <?php if ($permisos['puede_gestionar']): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nueva-conexion-vacia">
                        <i class="fa-solid fa-plus me-1"></i> Configurar Primera Conexión
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES ALINA (Default Modal, Centered)                                  -->
<!-- ========================================================================= -->

<!-- Modal Crear Conexión iCal -->
<div class="modal fade" id="modal-crear-conexion" tabindex="-1" aria-labelledby="modalCrearConexionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalCrearConexionLabel">
                    <i class="fa-solid fa-plug me-2"></i> Nueva Conexión iCalendar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-conexion" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="crear-unidad-id" class="form-label f-s-13 f-w-600">Unidad de Alojamiento <span class="text-danger">*</span></label>
                        <select id="crear-unidad-id" name="unidad_id" class="form-select select2-modal" required data-pristine-required-message="Debe seleccionar una unidad.">
                            <option value="">Seleccione una unidad...</option>
                            <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['propiedad_nombre']) ?> — <?= e($u['nombre']) ?> (<?= e($u['codigo']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="crear-canal-id" class="form-label f-s-13 f-w-600">Canal de Distribución <span class="text-danger">*</span></label>
                        <select id="crear-canal-id" name="canal_id" class="form-select select2-modal" required data-pristine-required-message="Debe seleccionar un canal.">
                            <option value="">Seleccione un canal...</option>
                            <?php foreach ($canales as $c): ?>
                            <option value="<?= (int) $c->obtenerId() ?>" data-color="<?= e($c->obtenerColorBadge()) ?>">
                                <?= e($c->obtenerNombre()) ?> (<?= e($c->obtenerCodigo()) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="crear-nombre" class="form-label f-s-13 f-w-600">Nombre Descriptivo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="crear-nombre" name="nombre"
                               placeholder="Ej. Airbnb - Habitación 101 Principal"
                               required minlength="3" maxlength="150"
                               data-pristine-required-message="El nombre es obligatorio."
                               data-pristine-minlength-message="Debe contener al menos 3 caracteres.">
                    </div>

                    <hr class="my-3">

                    <!-- Importación -->
                    <div class="mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="crear-importacion-habilitada" name="importacion_habilitada" value="1" checked>
                            <label class="form-check-label f-s-13 f-w-600" for="crear-importacion-habilitada">
                                Habilitar Importación de Disponibilidad (OTA → PMS)
                            </label>
                        </div>
                        <div id="crear-contenedor-url-importacion">
                            <label for="crear-url-importacion" class="form-label f-s-12 text-secondary">
                                URL iCal Privada del Canal <span class="text-danger">*</span>
                            </label>
                            <input type="url" class="form-control form-control-sm" id="crear-url-importacion" name="url_importacion"
                                   placeholder="https://www.airbnb.com/calendar/ical/...ics"
                                   autocomplete="off">
                            <div class="form-text f-s-11 text-muted">
                                <i class="fa-solid fa-lock text-success me-1"></i> La URL se cifrará de inmediato con AES-256-GCM. Nunca se almacenará ni se mostrará en texto plano.
                            </div>
                        </div>
                    </div>

                    <!-- Exportación -->
                    <div class="mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="crear-exportacion-habilitada" name="exportacion_habilitada" value="1" checked>
                            <label class="form-check-label f-s-13 f-w-600" for="crear-exportacion-habilitada">
                                Habilitar Feed de Exportación (PMS → OTA)
                            </label>
                        </div>
                        <div class="form-text f-s-11 text-muted">
                            Se generará un token criptográfico único con filtro anti-echo y privacidad de huéspedes.
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 col-12">
                            <label for="crear-frecuencia" class="form-label f-s-13 f-w-600">Frecuencia de Sondeo</label>
                            <select id="crear-frecuencia" name="frecuencia_minutos" class="form-select form-select-sm">
                                <option value="15">Cada 15 minutos</option>
                                <option value="30">Cada 30 minutos</option>
                                <option value="60" selected>Cada 1 hora</option>
                                <option value="120">Cada 2 horas</option>
                                <option value="360">Cada 6 horas</option>
                                <option value="1440">Cada 24 horas</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label for="crear-estado" class="form-label f-s-13 f-w-600">Estado Inicial</label>
                            <select id="crear-estado" name="estado" class="form-select form-select-sm">
                                <option value="ACTIVO" selected>ACTIVO</option>
                                <option value="PAUSADO">PAUSADO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-crear">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Conexión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Conexión iCal -->
<div class="modal fade" id="modal-editar-conexion" tabindex="-1" aria-labelledby="modalEditarConexionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalEditarConexionLabel">
                    <i class="fa-solid fa-pen-to-square me-2"></i> Editar Conexión iCalendar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-editar-conexion" novalidate>
                <input type="hidden" id="editar-id" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-12 text-secondary mb-1">Unidad y Canal</label>
                        <div class="p-2 bg-light rounded border text-muted f-s-13" id="editar-contexto-fijo">
                            <!-- Se llena dinámicamente -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editar-nombre" class="form-label f-s-13 f-w-600">Nombre Descriptivo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editar-nombre" name="nombre"
                               required minlength="3" maxlength="150"
                               data-pristine-required-message="El nombre es obligatorio."
                               data-pristine-minlength-message="Debe contener al menos 3 caracteres.">
                    </div>

                    <hr class="my-3">

                    <!-- Importación -->
                    <div class="mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="editar-importacion-habilitada" name="importacion_habilitada" value="1">
                            <label class="form-check-label f-s-13 f-w-600" for="editar-importacion-habilitada">
                                Habilitar Importación de Disponibilidad
                            </label>
                        </div>
                        <div id="editar-contenedor-url">
                            <div class="d-flex align-items-center mb-1">
                                <span class="f-s-12 text-secondary me-2">Estado de URL:</span>
                                <span id="editar-badge-url-actual" class="badge bg-light-success text-success">Configurada (Cifrada AES-256)</span>
                            </div>
                            <label for="editar-nueva-url" class="form-label f-s-12 text-secondary">
                                Reemplazar URL iCal Privada
                            </label>
                            <input type="url" class="form-control form-control-sm" id="editar-nueva-url" name="nueva_url_importacion"
                                   placeholder="Dejar en blanco para conservar la URL actual"
                                   autocomplete="off">
                            <div class="form-text f-s-11 text-muted">
                                Dejar vacío para conservar la URL cifrada existente. Si ingresa una nueva, se validará y reemplazará con cifrado AES-256.
                            </div>
                        </div>
                    </div>

                    <!-- Exportación -->
                    <div class="mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="editar-exportacion-habilitada" name="exportacion_habilitada" value="1">
                            <label class="form-check-label f-s-13 f-w-600" for="editar-exportacion-habilitada">
                                Habilitar Feed de Exportación
                            </label>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 col-12">
                            <label for="editar-frecuencia" class="form-label f-s-13 f-w-600">Frecuencia de Sondeo</label>
                            <select id="editar-frecuencia" name="frecuencia_minutos" class="form-select form-select-sm">
                                <option value="15">Cada 15 minutos</option>
                                <option value="30">Cada 30 minutos</option>
                                <option value="60">Cada 1 hora</option>
                                <option value="120">Cada 2 horas</option>
                                <option value="360">Cada 6 horas</option>
                                <option value="1440">Cada 24 horas</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label for="editar-estado" class="form-label f-s-13 f-w-600">Estado</label>
                            <select id="editar-estado" name="estado" class="form-select form-select-sm">
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="PAUSADO">PAUSADO</option>
                                <option value="REVOCADO">REVOCADO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-editar">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Conexión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Historial de Sincronizaciones -->
<div class="modal fade" id="modal-historial" tabindex="-1" aria-labelledby="modalHistorialLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <div>
                    <h5 class="modal-title f-s-16 f-w-700 text-dark mb-0" id="modalHistorialLabel">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Historial de Sincronizaciones
                    </h5>
                    <span class="text-secondary f-s-12" id="historial-subtitulo"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 f-s-12">
                        <thead class="bg-light text-secondary sticky-top">
                            <tr>
                                <th>Fecha / Hora</th>
                                <th>Origen</th>
                                <th>Duración</th>
                                <th>HTTP</th>
                                <th class="text-center">Recibidos</th>
                                <th class="text-center">Nuevos</th>
                                <th class="text-center">Actualiz.</th>
                                <th class="text-center">Cancel.</th>
                                <th class="text-center">Conflictos</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-historial">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Conflictos de Inventario -->
<div class="modal fade" id="modal-conflictos" tabindex="-1" aria-labelledby="modalConflictosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <div>
                    <h5 class="modal-title f-s-16 f-w-700 text-dark mb-0" id="modalConflictosLabel">
                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Conflictos de Inventario
                    </h5>
                    <span class="text-secondary f-s-12" id="conflictos-subtitulo">Eventos externos colisionando con disponibilidad soberana</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-warning d-flex align-items-center py-2 px-3 mb-3 f-s-12" role="alert">
                    <i class="fa-solid fa-shield-halved f-s-16 me-2"></i>
                    <div>
                        <strong>Preservación Soberana:</strong> Las reservas locales NUNCA se sobreescriben ni cancelan automáticamente ante un feed externo. Un conflicto es una alerta para intervención operativa humana.
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 f-s-12">
                        <thead class="bg-light text-secondary sticky-top">
                            <tr>
                                <th>Unidad / Canal</th>
                                <th>Intervalo Noches</th>
                                <th>Resumen Externo</th>
                                <th>Detalle del Conflicto</th>
                                <th>Detección</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-conflictos">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Copiar Feed de Exportación -->
<div class="modal fade" id="modal-copiar-feed" tabindex="-1" aria-labelledby="modalCopiarFeedLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalCopiarFeedLabel">
                    <i class="fa-solid fa-cloud-arrow-down me-2"></i> URL Feed de Exportación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary f-s-13 mb-3">
                    Copie y pegue esta URL en el canal receptor (Airbnb, Booking, VRBO, etc.). El feed anonimiza los datos de huéspedes y aplica filtro anti-echo para evitar bloqueos duplicados.
                </p>

                <div class="input-group mb-3">
                    <input type="text" class="form-control f-s-12" id="input-feed-url" readonly autocomplete="off">
                    <button class="btn btn-primary" type="button" id="btn-copiar-clipboard">
                        <i class="fa-regular fa-copy me-1"></i> Copiar
                    </button>
                </div>

                <div class="alert alert-info py-2 px-3 mb-0 f-s-11" role="alert">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Esta URL privada contiene un token único con cifrado fail-closed. No la comparta públicamente. Si sospecha filtración, use la opción <strong>Rotar Token</strong>.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias de Scripts Específicas del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('js/gestion-canales-ical.js') ?>"></script>
