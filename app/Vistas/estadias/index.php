<?php

declare(strict_types=1);

/**
 * Vista del Módulo de Recepción, Estadías y Control de Huéspedes — Camargo PMS (ESTADÍAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - 1 Reserva : N Estadías físicas independientes (una por reserva_unidad).
 * - UNIQUE(reserva_unidad_id): una unidad de reserva genera como máximo una estadía histórica.
 * - Bloqueo estricto de capacidad: 1 <= count(huéspedes) <= unidad.capacidad_personas.
 * - Exactamente 1 huésped responsable por estadía.
 * - Inmutabilidad histórica: CHECK-OUT != DELETE | ANULACIÓN != DELETE.
 * - D-071: Font Awesome 6.3.0 exclusivo, Flatpickr Date Picker, Variants of badge & chip Alina.
 *
 * @var array{puede_ver: bool, puede_checkin: bool, puede_checkout: bool, puede_huespedes: bool, puede_anular: bool} $capacidades
 * @var array<int, array{id: int, codigo: string, nombre: string}> $propiedades
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Estadías -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-success text-success p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-bell-concierge f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Recepción, Estadías y Control Operativo</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión física de check-in, llaves, lista de ocupantes y check-out. Principio: <strong>RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_checkin'])): ?>
                        <button type="button" class="btn btn-success btn-sm" id="btn-abrir-checkin">
                            <i class="fa-solid fa-key me-1"></i> Realizar Check-in
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tarjetas Resumen / KPIs de Recepción -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Estadías En Curso</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-estadias-activas">0</h3>
                                    <span class="f-s-11 text-muted">Unidades ocupadas actualmente</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-bed f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Finalizadas (Check-out)</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-secondary mt-1" id="kpi-estadias-finalizadas">0</h3>
                                    <span class="f-s-11 text-muted">Salidas registradas</span>
                                </div>
                                <div class="bg-light-secondary text-secondary p-3 b-r-8">
                                    <i class="fa-solid fa-flag-checkered f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Check-in Anulados</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-estadias-anuladas">0</h3>
                                    <span class="f-s-11 text-muted">Excepciones auditadas</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-ban f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <div class="card-body p-3 bg-white border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-12">
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="text" class="form-control ps-5" id="filtro-busqueda-estadia"
                                   placeholder="Estadía, reserva, unidad, huésped..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select basic-select2" id="filtro-estado-estadia" data-placeholder="Todos los estados">
                            <option value="">Todos los estados</option>
                            <option value="EN_CURSO">EN CURSO (Huésped en unidad)</option>
                            <option value="FINALIZADA">FINALIZADA (Check-out realizado)</option>
                            <option value="ANULADA">ANULADA</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select basic-select2" id="filtro-propiedad-estadia" data-placeholder="Todas las propiedades">
                            <option value="">Todas las propiedades</option>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-3 text-primary"></i>
                            <input type="text" class="form-control ps-5" id="filtro-rango-fechas"
                                   placeholder="Filtrar por rango de fechas..."
                                   data-provider="rangepicker"
                                   data-target-inicio="#filtro-fecha-desde"
                                   data-target-fin="#filtro-fecha-hasta"
                                   autocomplete="off" readonly>
                        </div>
                        <input type="hidden" id="filtro-fecha-desde">
                        <input type="hidden" id="filtro-fecha-hasta">
                    </div>
                    <div class="col-md-1 col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-recargar-estadias" title="Refrescar catálogo">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Estadías -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-estadias">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th class="ps-3">Estadía</th>
                                <th>Unidad / Propiedad</th>
                                <th>Reserva</th>
                                <th>Huésped Responsable</th>
                                <th>Ocupantes</th>
                                <th>Entrada (Check-in)</th>
                                <th>Salida Prevista</th>
                                <th>Llave</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-estadias">
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando estadías...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="card-footer bg-white border-top py-2 d-flex flex-wrap justify-content-between align-items-center">
                    <span class="f-s-12 text-secondary" id="paginacion-resumen-estadias">Mostrando 0 de 0 estadías</span>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacion-control-estadias">
                            <!-- Inyectado por JS -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: REALIZAR CHECK-IN FÍSICO DE UNIDAD (ESTADÍAS-1) -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-checkin" tabindex="-1" aria-labelledby="modal-checkin-label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h5 class="modal-title f-s-15 f-w-700" id="modal-checkin-label">
                    <i class="fa-solid fa-key me-1 text-success"></i> Check-in Operativo de Unidad
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-checkin" novalidate>
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <!-- Selección de Reserva Confirmada -->
                        <div class="col-md-7 col-12">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="checkin-reserva-id">
                                Reserva Confirmada Comercial <span class="text-danger">*</span>
                            </label>
                            <select class="form-select basic-select2" id="checkin-reserva-id" name="reserva_id" data-placeholder="Seleccione una reserva confirmada..." required>
                                <option value="">Seleccione una reserva confirmada...</option>
                            </select>
                            <div class="form-text f-s-11">
                                Solo reservas en estado CONFIRMADA con unidades pendientes de check-in.
                            </div>
                        </div>

                        <!-- Selección de Unidad de Reserva Elegible (Multiunidad) -->
                        <div class="col-md-5 col-12">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="checkin-reserva-unidad-id">
                                Unidad Alojable <span class="text-danger">*</span>
                            </label>
                            <select class="form-select basic-select2" id="checkin-reserva-unidad-id" name="reserva_unidad_id" data-placeholder="Primero seleccione reserva..." required disabled>
                                <option value="">Primero seleccione una reserva...</option>
                            </select>
                            <div class="form-text f-s-11" id="ayuda-capacidad-unidad">
                                Capacidad máxima determinada por la unidad física.
                            </div>
                        </div>

                        <!-- Información y Fechas de la Estadía -->
                        <div class="col-md-4 col-6">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="checkin-fecha-entrada">
                                Fecha Entrada (Hotelera) <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-check position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="checkin-fecha-entrada" name="fecha_entrada" readonly required>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="checkin-fecha-salida">
                                Fecha Salida Prevista <span class="text-danger">*</span>
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-xmark position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="checkin-fecha-salida" name="fecha_salida_prevista" readonly required>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="checkin-identificador-llave">
                                <i class="fa-solid fa-key me-1 text-secondary"></i> Identificador de Llave / Tarjeta
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-key position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" id="checkin-identificador-llave"
                                       name="identificador_llave" placeholder="Ej. Tarjeta #104 o Llave A-2" maxlength="50">
                            </div>
                        </div>

                        <!-- Panel de Huéspedes y Capacidad Física -->
                        <div class="col-12">
                            <div class="card border bg-light p-3 mb-0 b-r-8">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0 f-s-13 f-w-700">
                                            <i class="fa-solid fa-users me-1 text-primary"></i> Registro de Ocupantes y Huésped Responsable
                                        </h6>
                                        <span class="badge bg-light-info text-info f-s-11" id="badge-capacidad-unidad">
                                            Capacidad: - personas
                                        </span>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2" id="btn-agregar-huesped">
                                        <i class="fa-solid fa-user-plus me-1"></i> Añadir Ocupante
                                    </button>
                                </div>
                                <p class="f-s-11 text-secondary mb-2">
                                    Regla estricta: <strong>1 ≤ huéspedes ≤ capacidad de la unidad</strong>. Se debe designar <strong>exactamente un huésped responsable</strong>.
                                </p>

                                <div class="table-responsive bg-white b-r-6 border">
                                    <table class="table table-sm table-hover align-middle mb-0" id="tabla-huespedes-checkin">
                                        <thead class="table-light">
                                            <tr class="f-s-11 text-uppercase text-secondary">
                                                <th class="ps-2">Persona Registrada Central</th>
                                                <th class="text-center" style="width: 140px;">¿Es Responsable?</th>
                                                <th class="text-center" style="width: 70px;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-huespedes-checkin">
                                            <!-- Filas dinámicas inyectadas por JS -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <span class="f-s-11 text-muted" id="resumen-conteo-huespedes">0 ocupantes registrados</span>
                                    <div class="text-danger f-s-11 d-none" id="alerta-error-huespedes"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones de Check-in -->
                        <div class="col-12">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="checkin-observaciones">
                                Observaciones de Recepción (Check-in)
                            </label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" id="checkin-observaciones" name="observaciones_checkin"
                                          rows="2" placeholder="Equipaje, peticiones especiales, hora estimada de llegada, etc..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-guardar-checkin">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-guardar-checkin"></span>
                        <i class="fa-solid fa-check me-1"></i> Completar Check-in
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CHECK-OUT OPERATIVO (ESTADÍAS-1) -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-checkout" tabindex="-1" aria-labelledby="modal-checkout-label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h5 class="modal-title f-s-15 f-w-700" id="modal-checkout-label">
                    <i class="fa-solid fa-flag-checkered me-1 text-secondary"></i> Check-out Operativo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-checkout" novalidate>
                <input type="hidden" id="checkout-estadia-id">
                <div class="modal-body p-3">
                    <div class="p-3 bg-light b-r-8 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="f-s-12 text-secondary">Estadía:</span>
                            <strong class="f-s-12" id="checkout-texto-codigo">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="f-s-12 text-secondary">Unidad:</span>
                            <strong class="f-s-12 text-primary" id="checkout-texto-unidad">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="f-s-12 text-secondary">Huésped Responsable:</span>
                            <strong class="f-s-12" id="checkout-texto-responsable">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="f-s-12 text-secondary">Llave asignada:</span>
                            <span class="badge bg-light-secondary" id="checkout-texto-llave">-</span>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 f-s-12 mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Se registrará el instante técnico real de check-out (D-066). La reserva comercial y sus snapshots permanecen inmutables.
                    </div>
                    <div>
                        <label class="form-label f-s-12 f-w-600 mb-1" for="checkout-observaciones">
                            Observaciones de Salida y Estado de Unidad
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="checkout-observaciones" rows="2"
                                      placeholder="Devolución de llave, estado físico de la unidad, observaciones finales..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-secondary btn-sm" id="btn-confirmar-checkout">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-checkout"></span>
                        <i class="fa-solid fa-flag-checkered me-1"></i> Confirmar Salida (Check-out)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ANULAR CHECK-IN / ESTADÍA (ESTADÍAS-1) -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-anular-estadia" tabindex="-1" aria-labelledby="modal-anular-label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h5 class="modal-title f-s-15 f-w-700 text-danger" id="modal-anular-label">
                    <i class="fa-solid fa-ban me-1"></i> Anular Registro de Estadía
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-anular-estadia" novalidate>
                <input type="hidden" id="anular-estadia-id">
                <div class="modal-body p-3">
                    <p class="f-s-13 mb-2">
                        ¿Confirma la anulación operativa del check-in para la estadía <strong id="anular-texto-codigo">-</strong>?
                    </p>
                    <p class="f-s-11 text-danger mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Esta acción es excepcional, auditable e inmutable (ANULACIÓN ≠ DELETE).
                    </p>
                    <div>
                        <label class="form-label f-s-12 f-w-600 mb-1" for="anular-motivo">
                            Motivo Justificado de Anulación <span class="text-danger">*</span>
                        </label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="anular-motivo" rows="2"
                                      placeholder="Indique con claridad la causa de la anulación..." required maxlength="255"></textarea>
                        </div>
                        <div class="form-text f-s-11">Máximo 255 caracteres obligatorios.</div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-anulacion">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-anular"></span>
                        <i class="fa-solid fa-ban me-1"></i> Confirmar Anulación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: DETALLES DE ESTADÍA Y GESTIÓN DE HUÉSPEDES -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-detalle-estadia" tabindex="-1" aria-labelledby="modal-detalle-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title f-s-15 f-w-700 mb-0" id="modal-detalle-label">
                        <i class="fa-solid fa-circle-info me-1 text-primary"></i> Detalle de Estadía: <span id="detalle-codigo">-</span>
                    </h5>
                    <span id="detalle-estado-badge"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3">
                    <!-- Ficha Resumen -->
                    <div class="col-md-6 col-12">
                        <div class="card border p-3 mb-0 h-100 b-r-8">
                            <h6 class="f-s-12 text-uppercase text-secondary f-w-700 mb-2 border-bottom pb-1">
                                <i class="fa-solid fa-hotel me-1"></i> Alojamiento y Reserva
                            </h6>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Unidad:</span> <strong id="detalle-unidad">-</strong>
                            </div>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Propiedad:</span> <span id="detalle-propiedad">-</span>
                            </div>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Reserva Comercial:</span> <strong class="text-primary" id="detalle-reserva">-</strong>
                            </div>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Titular Reserva:</span> <span id="detalle-titular">-</span>
                            </div>
                            <div class="f-s-12">
                                <span class="text-secondary">Llave / Identificador:</span> <span class="badge bg-light-secondary" id="detalle-llave">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Ficha Fechas y Trazabilidad -->
                    <div class="col-md-6 col-12">
                        <div class="card border p-3 mb-0 h-100 b-r-8">
                            <h6 class="f-s-12 text-uppercase text-secondary f-w-700 mb-2 border-bottom pb-1">
                                <i class="fa-solid fa-clock me-1"></i> Trazabilidad Operativa (D-066 / D-061)
                            </h6>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Fecha Entrada (Hotelera):</span> <strong id="detalle-fecha-entrada">-</strong>
                            </div>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Salida Prevista (Hotelera):</span> <strong id="detalle-fecha-salida">-</strong>
                            </div>
                            <div class="f-s-12 mb-1">
                                <span class="text-secondary">Check-in Real (UTC):</span> <span id="detalle-checkin-en">-</span>
                                <span class="text-muted f-s-11" id="detalle-checkin-actor"></span>
                            </div>
                            <div class="f-s-12 mb-1" id="fila-detalle-checkout">
                                <span class="text-secondary">Check-out Real (UTC):</span> <span id="detalle-checkout-en">-</span>
                                <span class="text-muted f-s-11" id="detalle-checkout-actor"></span>
                            </div>
                            <div class="f-s-12 text-danger d-none" id="fila-detalle-anulada">
                                <span class="text-secondary">Anulada en (UTC):</span> <span id="detalle-anulada-en">-</span><br>
                                <span class="text-secondary">Motivo:</span> <em id="detalle-motivo-anulacion">-</em>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Huéspedes Alojados -->
                    <div class="col-12">
                        <div class="card border p-3 mb-0 b-r-8">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="f-s-12 text-uppercase text-secondary f-w-700 mb-0">
                                    <i class="fa-solid fa-users me-1"></i> Huéspedes y Acompañantes Alojados
                                </h6>
                                <span class="badge bg-light-primary" id="detalle-conteo-huespedes">0 personas</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tabla-detalle-huespedes">
                                    <thead class="table-light">
                                        <tr class="f-s-11 text-uppercase text-secondary">
                                            <th class="ps-2">Nombre Completo</th>
                                            <th>Documento</th>
                                            <th>Contacto</th>
                                            <th class="text-center">Rol en Estadía</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-detalle-huespedes">
                                        <!-- Filas dinámicas -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="col-12">
                        <div class="p-2 bg-light b-r-6 border">
                            <div class="f-s-11 text-secondary mb-1"><strong>Observaciones de Entrada:</strong> <span id="detalle-obs-checkin">Ninguna</span></div>
                            <div class="f-s-11 text-secondary"><strong>Observaciones de Salida:</strong> <span id="detalle-obs-checkout">Ninguna</span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-estadias.js') ?>"></script>
