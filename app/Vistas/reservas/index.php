<?php

declare(strict_types=1);

/**
 * Vista del Módulo de Gestión de Reservas Directas — Camargo PMS (RESERVAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO != PERSONA.
 * - Intervalo semiabierto [fecha_entrada, fecha_salida) (D-066).
 * - Multiunidad: 1 Reserva : N Unidades asignadas (D-067 / D-069).
 * - Contrato monetario D-069: PEN, DECIMAL(15,2), redondeo comercial.
 *
 * @var array{puede_ver: bool, puede_crear: bool, puede_confirmar: bool, puede_cancelar: bool, puede_expirar: bool} $capacidades
 * @var array<int, array{id: int, codigo: string, nombre: string}> $propiedades
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Reservas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-calendar-check f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Gestión Comercial de Reservas Directas</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Núcleo transaccional de reservas multiunidad. Principio: <strong>RESERVA ≠ DISPONIBILIDAD ≠ INVENTARIO ≠ ESTANCIA</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_expirar'])): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btn-expirar-holds" title="Verificar y liberar holds vencidos">
                            <i class="fa-solid fa-clock me-1"></i> Expirar Holds
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-reserva">
                            <i class="fa-solid fa-plus me-1"></i> Nueva Reserva Directa
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barra de Filtros y Búsqueda -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-reserva"
                                   placeholder="Código, titular, documento..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado-reserva">
                            <option value="">Todos los estados</option>
                            <option value="PENDIENTE">PENDIENTE (Hold activo)</option>
                            <option value="CONFIRMADA">CONFIRMADA</option>
                            <option value="CANCELADA">CANCELADA</option>
                            <option value="EXPIRADA">EXPIRADA</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-propiedad-reserva">
                            <option value="">Todas las propiedades</option>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-calendar-days text-primary"></i></span>
                            <input type="text" class="form-control" id="filtro-rango-fechas"
                                   placeholder="Filtrar por rango de fechas..."
                                   data-provider="rangepicker"
                                   data-target-inicio="#filtro-fecha-desde"
                                   data-target-fin="#filtro-fecha-hasta"
                                   autocomplete="off" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-filtro-fechas" title="Limpiar fechas">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <input type="hidden" id="filtro-fecha-desde">
                        <input type="hidden" id="filtro-fecha-hasta">
                    </div>
                    <div class="col-md-1 col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-recargar-reservas" title="Refrescar catálogo">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Reservas -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-reservas">
                        <thead class="table-light">
                            <tr class="f-s-12 text-uppercase text-secondary">
                                <th class="ps-3">Código</th>
                                <th>Titular</th>
                                <th>Fechas [Entrada — Salida)</th>
                                <th>Noches</th>
                                <th>Unidades</th>
                                <th class="text-end">Total (S/)</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-reservas">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando reservas...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="card-footer bg-white border-top py-2 d-flex flex-wrap justify-content-between align-items-center">
                    <span class="f-s-12 text-secondary" id="paginacion-resumen-reservas">Mostrando 0 de 0 reservas</span>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacion-control-reservas">
                            <!-- Inyectado por JS -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: NUEVA RESERVA DIRECTA MULTIUNIDAD -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-crear-reserva" tabindex="-1" aria-labelledby="modal-crear-reserva-label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h5 class="modal-title f-s-15 f-w-700" id="modal-crear-reserva-label">
                    <i class="fa-solid fa-calendar-plus me-1 text-primary"></i> Registrar Nueva Reserva Directa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-crear-reserva" novalidate>
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <!-- Titular de la Reserva -->
                        <div class="col-md-12">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="crear-titular-id">
                                Persona Titular de la Reserva <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="crear-titular-id" name="persona_titular_id" required>
                                <option value="">Seleccione o busque una persona registrada...</option>
                            </select>
                            <div class="form-text f-s-11">
                                Las reservas deben estar vinculadas a una Persona Natural (fuente central de identidades).
                            </div>
                        </div>

                        <!-- Intervalo Temporal Semiabierto [entrada, salida) con Alina Range Picker -->
                        <div class="col-md-10">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="crear-rango-fechas">
                                <i class="fa-solid fa-calendar-days me-1 text-primary"></i> Intervalo de Estadía (Check-in → Check-out) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-calendar-days text-primary"></i></span>
                                <input type="text" class="form-control" id="crear-rango-fechas"
                                       placeholder="Seleccione fecha de entrada y salida..."
                                       data-provider="rangepicker"
                                       data-target-inicio="#crear-fecha-entrada"
                                       data-target-fin="#crear-fecha-salida"
                                       data-target-noches="#crear-noches-calculadas"
                                       autocomplete="off" readonly required>
                            </div>
                            <input type="hidden" id="crear-fecha-entrada" name="fecha_entrada" required>
                            <input type="hidden" id="crear-fecha-salida" name="fecha_salida" required>
                            <div class="form-text f-s-11">
                                Contrato D-066: Intervalo semiabierto [entrada, salida). La noche de salida queda liberada para check-in.
                            </div>
                        </div>
                        <div class="col-md-2 text-center d-flex flex-column justify-content-center">
                            <span class="f-s-11 text-secondary">Noches:</span>
                            <span class="badge bg-secondary-subtle text-secondary f-s-14" id="crear-noches-calculadas">0</span>
                        </div>

                        <!-- Asignación Multiunidad y Snapshot Económico -->
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label f-s-12 f-w-600 mb-0">
                                    Unidades Alojables Asignadas (1 Reserva : N Unidades) <span class="text-danger">*</span>
                                </label>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 f-s-11" id="btn-agregar-fila-unidad">
                                    <i class="fa-solid fa-plus me-1"></i> Añadir Unidad
                                </button>
                            </div>
                            <div class="border b-r-8 p-2 bg-light-subtle">
                                <div id="contenedor-unidades-reserva">
                                    <!-- Filas dinámicas de unidades -->
                                </div>
                            </div>
                        </div>

                        <!-- Resumen Financiero D-069 -->
                        <div class="col-md-6">
                            <div class="card bg-white border p-2">
                                <div class="d-flex justify-content-between f-s-12 mb-1">
                                    <span class="text-secondary">Moneda:</span>
                                    <strong>PEN (S/)</strong>
                                </div>
                                <div class="d-flex justify-content-between f-s-12 mb-1">
                                    <span class="text-secondary">Subtotal Neto:</span>
                                    <span id="resumen-subtotal">S/ 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between f-s-12 mb-1">
                                    <span class="text-secondary">Impuestos:</span>
                                    <span id="resumen-impuestos">S/ 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between f-s-14 f-w-700 border-top pt-1 text-primary">
                                    <span>Total Reserva:</span>
                                    <span id="resumen-total">S/ 0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Estado Inicial y Canal -->
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label f-s-12 f-w-600 mb-1" for="crear-estado">
                                    Estado Inicial de la Reserva
                                </label>
                                <select class="form-select form-select-sm" id="crear-estado" name="estado">
                                    <option value="PENDIENTE" selected>PENDIENTE (Retiene hold de inventario)</option>
                                    <option value="CONFIRMADA">CONFIRMADA (Reserva garantizada)</option>
                                </select>
                                <div class="form-text f-s-11" id="ayuda-hold-minutos">
                                    Si es PENDIENTE, retendrá el inventario por 30 minutos antes de expirar automáticamente.
                                </div>
                            </div>
                            <div>
                                <label class="form-label f-s-12 f-w-600 mb-1" for="crear-canal">Canal de Venta</label>
                                <select class="form-select form-select-sm" id="crear-canal" name="canal">
                                    <option value="PMS" selected>PMS (Recepción / Mostrador)</option>
                                    <option value="WEB">Web Directa</option>
                                    <option value="APP">App Móvil</option>
                                    <option value="OTA">OTA / Agencia Externa</option>
                                </select>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="col-12">
                            <label class="form-label f-s-12 f-w-600 mb-1" for="crear-observaciones">Observaciones / Notas Internas</label>
                            <textarea class="form-control form-control-sm" id="crear-observaciones" name="observaciones" rows="2"
                                      placeholder="Peticiones especiales, horario estimado de llegada, notas comerciales..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-reserva">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-guardar-reserva"></span>
                        Crear Reserva Directa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: DETALLE COMPLETO DE RESERVA -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-detalle-reserva" tabindex="-1" aria-labelledby="modal-detalle-reserva-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="modal-title f-s-15 f-w-700" id="modal-detalle-reserva-label">
                        Reserva <span id="detalle-codigo" class="text-primary"></span>
                    </h5>
                    <span id="detalle-estado-badge"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3">
                    <!-- Datos del Titular -->
                    <div class="col-md-6">
                        <div class="card border p-2 h-100 bg-light-subtle">
                            <h6 class="f-s-12 f-w-700 text-uppercase text-secondary mb-2">
                                <i class="fa-solid fa-user me-1"></i> Persona Titular
                            </h6>
                            <p class="f-s-14 f-w-600 mb-1" id="detalle-titular-nombre"></p>
                            <p class="f-s-12 text-secondary mb-1">
                                Documento: <span id="detalle-titular-documento" class="text-dark"></span>
                            </p>
                            <p class="f-s-12 text-secondary mb-0">
                                Contacto: <span id="detalle-titular-contacto" class="text-dark"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Datos Temporales y Operacionales -->
                    <div class="col-md-6">
                        <div class="card border p-2 h-100 bg-light-subtle">
                            <h6 class="f-s-12 f-w-700 text-uppercase text-secondary mb-2">
                                <i class="fa-solid fa-calendar-days me-1"></i> Período Hotelero
                            </h6>
                            <p class="f-s-13 mb-1">
                                Entrada: <strong id="detalle-fecha-entrada"></strong>
                            </p>
                            <p class="f-s-13 mb-1">
                                Salida: <strong id="detalle-fecha-salida"></strong>
                            </p>
                            <p class="f-s-12 text-secondary mb-0">
                                Total noches: <strong id="detalle-noches" class="text-dark"></strong>
                                | Canal: <strong id="detalle-canal" class="text-dark"></strong>
                            </p>
                            <div id="detalle-aviso-hold" class="mt-2 f-s-11 d-none"></div>
                        </div>
                    </div>

                    <!-- Desglose de Unidades Asignadas -->
                    <div class="col-12">
                        <h6 class="f-s-12 f-w-700 text-uppercase text-secondary mb-2">
                            <i class="fa-solid fa-door-open me-1"></i> Unidades Asignadas y Snapshot Económico (D-069)
                        </h6>
                        <div class="table-responsive border b-r-8">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light f-s-11 text-uppercase text-secondary">
                                    <tr>
                                        <th class="ps-2">Unidad</th>
                                        <th>Inmueble / Tipo</th>
                                        <th class="text-end">Precio Noche (S/)</th>
                                        <th class="text-center">Noches</th>
                                        <th class="text-end pe-2">Subtotal (S/)</th>
                                    </tr>
                                </thead>
                                <tbody id="detalle-tbody-unidades" class="f-s-12">
                                    <!-- Inyectado por JS -->
                                </tbody>
                                <tfoot class="table-light f-s-12">
                                    <tr>
                                        <td colspan="4" class="text-end f-w-700">Total Snapshot:</td>
                                        <td class="text-end f-w-700 pe-2 text-primary" id="detalle-total-snapshot">S/ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Notas y Trazabilidad -->
                    <div class="col-12">
                        <div class="p-2 border b-r-8 bg-white">
                            <div class="f-s-12 text-secondary mb-1">
                                <strong>Observaciones:</strong> <span id="detalle-observaciones" class="text-dark">Ninguna</span>
                            </div>
                            <div class="f-s-11 text-muted border-top pt-1 mt-2" id="detalle-trazabilidad">
                                <!-- Trazabilidad de actores y fechas -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btn-detalle-cancelar">
                        <i class="fa-solid fa-xmark me-1"></i> Cancelar Reserva
                    </button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="btn-detalle-confirmar">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Reserva
                    </button>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CANCELACIÓN DE RESERVA -->
<!-- ======================================================== -->
<div class="modal fade" id="modal-cancelar-reserva" tabindex="-1" aria-labelledby="modal-cancelar-reserva-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-2 bg-light">
                <h6 class="modal-title f-s-14 f-w-700 text-danger" id="modal-cancelar-reserva-label">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Cancelar Reserva
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-cancelar-reserva">
                <input type="hidden" id="cancelar-reserva-id" value="">
                <div class="modal-body p-3">
                    <p class="f-s-13 text-secondary mb-2">
                        ¿Confirma la cancelación de la reserva <strong id="cancelar-reserva-codigo" class="text-dark"></strong>?
                    </p>
                    <p class="f-s-11 text-danger mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i> Las noches retenidas en el inventario diario serán liberadas inmediatamente.
                    </p>
                    <div>
                        <label class="form-label f-s-12 f-w-600 mb-1" for="cancelar-motivo">
                            Motivo de Cancelación <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control form-control-sm" id="cancelar-motivo" rows="2"
                                  placeholder="Indique la causa de la cancelación..." required maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-cancelacion">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="spinner-cancelar-reserva"></span>
                        Confirmar Cancelación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_ruta('/assets/vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_ruta('/assets/js/gestion-reservas.js') ?>"></script>
