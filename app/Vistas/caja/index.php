<?php

declare(strict_types=1);

/**
 * Vista principal del módulo de Caja, Cuentas de Folios y Tesorería — Camargo PMS (FINANCIERO-2).
 *
 * Principios vinculantes:
 * - Tríada Financiera: CARGO ≠ PAGO ≠ MOVIMIENTO DE CAJA.
 * - Desacoplamiento de cobro y deuda: PAGO ≠ APLICACIÓN.
 * - D-071: Font Awesome 6.3.0 exclusivo, variantes Alina (bg-light-*), sin bordes discontinuos, Vanilla JS, PristineJS, SweetAlert2.
 *
 * @var array<int, \CamargoPMS\Modelos\CajaFisica> $cajas_fisicas
 * @var array<int, \CamargoPMS\Modelos\MetodoPago> $metodos_pago
 * @var array<int, \CamargoPMS\Modelos\CuentaBancaria> $cuentas_bancarias
 * @var array<string, mixed>|null $sesion_activa
 * @var string $csrf_token
 * @var array<string, bool> $capacidades
 * @var \CamargoPMS\Modelos\Usuario|null $usuario_actual
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Caja y Finanzas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-cash-register f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Caja y Cuentas de Reservas</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión integral de folios, cargos devengados, cobros, imputaciones de pago, devoluciones y arqueo de turnos de caja.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2 align-items-center" id="contenedor-acciones-caja">
                    <!-- Dinámico por JS o inicial por PHP -->
                    <?php if ($sesion_activa !== null): ?>
                        <span class="badge bg-light-success text-success p-2 f-s-12">
                            <i class="fa-solid fa-circle me-1 f-s-9"></i> Turno #<?= e((string) $sesion_activa['id']) ?> Abierto
                        </span>
                        <?php if (!empty($capacidades['puede_movimiento'])): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-movimiento">
                                <i class="fa-solid fa-money-bill-transfer me-1"></i> Movimiento Efectivo
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($capacidades['puede_cerrar'])): ?>
                            <button type="button" class="btn btn-warning btn-sm" id="btn-abrir-cierre-caja" data-sesion-id="<?= e((string) $sesion_activa['id']) ?>">
                                <i class="fa-solid fa-lock me-1"></i> Arqueo y Cierre
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-light-secondary text-secondary p-2 f-s-12">
                            <i class="fa-solid fa-circle me-1 f-s-9"></i> Sin Turno Abierto
                        </span>
                        <?php if (!empty($capacidades['puede_abrir'])): ?>
                            <button type="button" class="btn btn-success btn-sm" id="btn-abrir-apertura-caja">
                                <i class="fa-solid fa-key me-1"></i> Abrir Turno de Caja
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tarjetas Resumen / KPIs -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Efectivo en Caja</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-efectivo-caja">S/ 0.00</h3>
                                    <span class="f-s-11 text-muted" id="kpi-efectivo-caja-sub">Fondo + Ingresos - Egresos</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-money-bill-wave f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Cuentas con Deuda</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-warning mt-1" id="kpi-cuentas-pendientes">0</h3>
                                    <span class="f-s-11 text-muted">Folios con saldo pendiente</span>
                                </div>
                                <div class="bg-light-warning text-warning p-3 b-r-8">
                                    <i class="fa-solid fa-receipt f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Cobros Registrados</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-total-cobrado">S/ 0.00</h3>
                                    <span class="f-s-11 text-muted">En folios activos</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-hand-holding-dollar f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Devoluciones Efectuadas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-total-devuelto">S/ 0.00</h3>
                                    <span class="f-s-11 text-muted">Reembolsos a huéspedes</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-arrow-rotate-left f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación por Pestañas (Tabs) -->
            <div class="card-header bg-white p-0 border-bottom">
                <ul class="nav nav-tabs tab-primary border-bottom-0 px-3" id="cajaTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-3 f-w-600" id="tab-folios-btn" data-bs-toggle="tab" data-bs-target="#tab-folios" type="button" role="tab">
                            <i class="fa-solid fa-file-invoice-dollar me-2"></i> Cuentas / Folios de Reservas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 f-w-600" id="tab-sesion-btn" data-bs-toggle="tab" data-bs-target="#tab-sesion" type="button" role="tab">
                            <i class="fa-solid fa-wallet me-2"></i> Turno Actual y Movimientos de Caja
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="cajaTabsContent">
                    
                    <!-- TAB 1: Folios de Reservas -->
                    <div class="tab-pane fade show active" id="tab-folios" role="tabpanel">
                        <!-- Filtros del Listado -->
                        <div class="row g-2 mb-3 align-items-center">
                            <div class="col-md-5 col-12">
                                <div class="icon-control position-relative">
                                    <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                                    <input type="text" class="form-control ps-5" id="filtro-q" placeholder="Buscar por código de folio, reserva, nombre o documento del huésped...">
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select basic-select2" id="filtro-estado" data-placeholder="Todos los Estados">
                                    <option value="">Todos los Estados</option>
                                    <option value="ABIERTA">Folio ABIERTO</option>
                                    <option value="CERRADA">Folio CERRADO</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-6 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-folios">
                                    <i class="fa-solid fa-arrows-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Folios -->
                        <div class="table-responsive border b-r-8">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-folios">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase f-w-600">Código Folio</th>
                                        <th class="f-s-12 text-uppercase f-w-600">Reserva / Titular</th>
                                        <th class="f-s-12 text-uppercase f-w-600">Fechas Estancia</th>
                                        <th class="f-s-12 text-uppercase f-w-600 text-end">Cargos Devengados</th>
                                        <th class="f-s-12 text-uppercase f-w-600 text-end">Pagos Confirmados</th>
                                        <th class="f-s-12 text-uppercase f-w-600 text-end">Saldo Exigible</th>
                                        <th class="f-s-12 text-uppercase f-w-600 text-center">Estado Financiero</th>
                                        <th class="f-s-12 text-uppercase f-w-600 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-folios">
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando cuentas de folios...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 2: Turno Actual y Movimientos de Caja -->
                    <div class="tab-pane fade" id="tab-sesion" role="tabpanel">
                        <div id="contenedor-detalle-sesion">
                            <!-- Inyectado dinámicamente según sesión activa -->
                            <div class="text-center py-5 text-muted">
                                <i class="fa-solid fa-spinner fa-spin f-s-24 mb-2"></i>
                                <p>Cargando información del turno de caja...</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES DEL SUBSISTEMA FINANCIERO -->
<!-- ========================================================================= -->

<!-- Modal 1: Apertura de Caja -->
<div class="modal fade" id="modal-apertura-caja" tabindex="-1" aria-labelledby="modalAperturaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modalAperturaLabel">
                    <i class="fa-solid fa-key text-success me-2"></i> Apertura de Turno de Caja
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-apertura-caja" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="apertura-caja-id" class="form-label f-s-12 f-w-600">Caja Física <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="apertura-caja-id" name="caja_fisica_id" required>
                            <?php foreach ($cajas_fisicas as $cf): ?>
                                <option value="<?= e((string) $cf->obtenerId()) ?>"><?= e($cf->obtenerCodigo()) ?> — <?= e($cf->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="apertura-monto" class="form-label f-s-12 f-w-600">Monto de Apertura / Fondo de Cambio (PEN) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-coins position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="number" step="0.01" min="0" class="form-control ps-5" id="apertura-monto" name="monto_apertura" value="0.00" required>
                        </div>
                        <small class="text-muted f-s-11 mt-1 d-block">Efectivo inicial disponible en gaveta para cambio a huéspedes.</small>
                    </div>
                    <div class="mb-3">
                        <label for="apertura-observaciones" class="form-label f-s-12 f-w-600">Observaciones de Apertura</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3"></i>
                            <textarea class="form-control ps-5" id="apertura-observaciones" name="observaciones" rows="2" placeholder="Notas sobre el estado físico de la gaveta o turno..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-confirmar-apertura">
                        <i class="fa-solid fa-lock-open me-1"></i> Confirmar Apertura
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Arqueo y Cierre de Caja -->
<div class="modal fade" id="modal-cierre-caja" tabindex="-1" aria-labelledby="modalCierreLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modalCierreLabel">
                    <i class="fa-solid fa-calculator text-warning me-2"></i> Arqueo y Cierre de Turno de Caja
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-cierre-caja" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="cierre-sesion-id" name="sesion_id" value="">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 p-3 mb-3 b-r-8 f-s-12">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> El cierre de caja es una operación <strong>irreversible</strong>. Una vez cerrada la sesión, no se podrán registrar cobros en efectivo en este turno.
                    </div>

                    <!-- Resumen del Arqueo -->
                    <div class="bg-light p-3 b-r-8 mb-3 border">
                        <div class="d-flex justify-content-between mb-1 f-s-12">
                            <span class="text-secondary">Fondo de Apertura:</span>
                            <span class="f-w-600" id="resumen-apertura">S/ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 f-s-12">
                            <span class="text-secondary">(+) Ingresos de Efectivo:</span>
                            <span class="text-success f-w-600" id="resumen-ingresos">+S/ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 f-s-12">
                            <span class="text-secondary">(-) Egresos / Devoluciones:</span>
                            <span class="text-danger f-w-600" id="resumen-egresos">-S/ 0.00</span>
                        </div>
                        <hr class="my-2 border-secondary">
                        <div class="d-flex justify-content-between f-s-14">
                            <span class="f-w-700">(=) Monto Esperado en Gaveta:</span>
                            <span class="f-w-700 text-primary" id="resumen-esperado">S/ 0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cierre-monto-declarado" class="form-label f-s-12 f-w-600">Efectivo Contado Declarado (Real en Gaveta) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-coins position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="number" step="0.01" min="0" class="form-control ps-5" id="cierre-monto-declarado" name="monto_contado_declarado" required>
                        </div>
                    </div>

                    <!-- Diferencia de Arqueo en Tiempo Real -->
                    <div class="p-3 b-r-8 mb-3 d-flex justify-content-between align-items-center" id="box-resultado-arqueo" style="background:#f8f9fa;">
                        <div>
                            <span class="f-s-11 text-uppercase text-secondary f-w-600">Resultado de Arqueo</span>
                            <h5 class="mb-0 f-s-15 f-w-700 mt-1" id="texto-resultado-arqueo">Ingrese el monto contado</h5>
                        </div>
                        <div class="text-end">
                            <span class="f-s-11 text-uppercase text-secondary f-w-600">Diferencia</span>
                            <h5 class="mb-0 f-s-16 f-w-700 mt-1" id="valor-diferencia-arqueo">S/ 0.00</h5>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cierre-observaciones" class="form-label f-s-12 f-w-600" id="label-cierre-obs">Observaciones / Justificación</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3"></i>
                            <textarea class="form-control ps-5" id="cierre-observaciones" name="observaciones_cierre" rows="2" placeholder="Si hay sobrante o faltante, la justificación es obligatoria..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-confirmar-cierre">
                        <i class="fa-solid fa-lock me-1"></i> Cerrar Turno Definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Movimiento Manual de Efectivo -->
<div class="modal fade" id="modal-movimiento-caja" tabindex="-1" aria-labelledby="modalMovimientoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modalMovimientoLabel">
                    <i class="fa-solid fa-money-bill-transfer text-primary me-2"></i> Movimiento Manual de Efectivo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-movimiento-caja" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="mov-tipo" class="form-label f-s-12 f-w-600">Tipo de Movimiento <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="mov-tipo" name="tipo" required>
                            <option value="INGRESO_MANUAL">Ingreso Extraordinario de Efectivo (+)</option>
                            <option value="EGRESO_MANUAL">Egreso / Gasto Menor de Efectivo (-)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="mov-monto" class="form-label f-s-12 f-w-600">Monto (PEN) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-money-bill-wave position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="number" step="0.01" min="0.01" class="form-control ps-5" id="mov-monto" name="monto" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="mov-concepto" class="form-label f-s-12 f-w-600">Concepto Justificado <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-file-lines position-absolute top-0 start-0 mt-3 ms-3"></i>
                            <textarea class="form-control ps-5" id="mov-concepto" name="concepto" rows="2" placeholder="Motivo o detalle del movimiento..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-movimiento">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Registrar Movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Estado de Cuenta y Gestión Integral de Folio -->
<div class="modal fade" id="modal-folio-detalle" tabindex="-1" aria-labelledby="modalFolioLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-file-invoice-dollar f-s-18"></i>
                    </span>
                    <div>
                        <h5 class="modal-title f-s-16 f-w-700 text-dark mb-0" id="modalFolioLabel">Estado de Cuenta — <span id="folio-codigo-header">FOL-...</span></h5>
                        <span class="text-muted f-s-12" id="folio-sub-header">Cargando datos...</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if (!empty($capacidades['puede_cobrar'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-cobro-folio">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Registrar Cobro
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Panel de Balance y Estado Financiero -->
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="bg-white p-3 b-r-8 border">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Cargos Devengados</span>
                            <h5 class="f-s-16 f-w-700 mb-0 mt-1 text-dark" id="bal-cargos-devengados">S/ 0.00</h5>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="bg-white p-3 b-r-8 border">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Cargos Provisionales</span>
                            <h5 class="f-s-16 f-w-700 mb-0 mt-1 text-secondary" id="bal-cargos-provisionales">S/ 0.00</h5>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="bg-white p-3 b-r-8 border">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Total Pagos</span>
                            <h5 class="f-s-16 f-w-700 mb-0 mt-1 text-success" id="bal-total-pagado">S/ 0.00</h5>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="bg-white p-3 b-r-8 border">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Pagos Aplicados</span>
                            <h5 class="f-s-16 f-w-700 mb-0 mt-1 text-info" id="bal-pagos-aplicados">S/ 0.00</h5>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="bg-white p-3 b-r-8 border">
                            <span class="f-s-11 text-muted text-uppercase f-w-600">Saldo a Favor</span>
                            <h5 class="f-s-16 f-w-700 mb-0 mt-1 text-primary" id="bal-saldo-disponible">S/ 0.00</h5>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="bg-white p-3 b-r-8 border border-warning" style="background:#fffcf0;">
                            <span class="f-s-11 text-warning text-uppercase f-w-600">Saldo Exigible</span>
                            <h5 class="f-s-16 f-w-700 mb-0 mt-1 text-danger" id="bal-saldo-exigible">S/ 0.00</h5>
                        </div>
                    </div>
                </div>

                <!-- Sección: Cargos en Cuenta -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 f-s-13 f-w-700"><i class="fa-solid fa-list-check text-secondary me-2"></i> Cargos Imputados a la Cuenta</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle f-s-12 mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Concepto / Detalle</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-end">P. Unit</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Aplicado</th>
                                    <th class="text-end">Saldo Pendiente</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-folio-cargos">
                                <tr><td colspan="9" class="text-center py-3 text-muted">Sin cargos registrados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección: Pagos y Cobros Reconocidos -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 f-s-13 f-w-700"><i class="fa-solid fa-money-check-dollar text-success me-2"></i> Pagos y Cobros Reconocidos</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle f-s-12 mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Método de Pago</th>
                                    <th>Referencia / Destino</th>
                                    <th class="text-end">Monto Total</th>
                                    <th class="text-end">Monto Aplicado</th>
                                    <th class="text-end">Saldo Disponible</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-folio-pagos">
                                <tr><td colspan="8" class="text-center py-3 text-muted">Sin pagos registrados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección: Devoluciones -->
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 f-s-13 f-w-700"><i class="fa-solid fa-arrow-rotate-left text-danger me-2"></i> Devoluciones y Reembolsos</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle f-s-12 mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Pago Origen</th>
                                    <th>Método</th>
                                    <th>Motivo</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-folio-devoluciones">
                                <tr><td colspan="6" class="text-center py-3 text-muted">Sin devoluciones registradas.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-2 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 5: Registrar Cobro / Pago -->
<div class="modal fade" id="modal-registrar-cobro" tabindex="-1" aria-labelledby="modalCobroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modalCobroLabel">
                    <i class="fa-solid fa-hand-holding-dollar text-primary me-2"></i> Registrar Cobro / Pago
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-registrar-cobro" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="cobro-folio-id" name="cuenta_folio_id" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="cobro-metodo-id" class="form-label f-s-12 f-w-600">Método de Pago <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="cobro-metodo-id" name="metodo_pago_id" data-placeholder="Seleccione un método..." required>
                            <option value="">Seleccione un método...</option>
                            <?php foreach ($metodos_pago as $mp): ?>
                                <option value="<?= e((string) $mp->obtenerId()) ?>" 
                                        data-tipo-destino="<?= e($mp->obtenerTipoDestino()) ?>">
                                    <?= e($mp->obtenerNombre()) ?> (<?= e($mp->obtenerTipoDestino()) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="cobro-monto" class="form-label f-s-12 f-w-600">Monto del Cobro (PEN) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-money-bill-wave position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="number" step="0.01" min="0.01" class="form-control ps-5" id="cobro-monto" name="monto_total" required>
                        </div>
                    </div>

                    <!-- Si el método es transferencia bancaria -->
                    <div class="mb-3 d-none" id="grupo-cuenta-bancaria">
                        <label for="cobro-cuenta-bancaria-id" class="form-label f-s-12 f-w-600">Cuenta Bancaria de Destino <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="cobro-cuenta-bancaria-id" name="cuenta_bancaria_id" data-placeholder="Seleccione la cuenta receptora...">
                            <option value="">Seleccione la cuenta receptora...</option>
                            <?php foreach ($cuentas_bancarias as $cb): ?>
                                <option value="<?= e((string) $cb->obtenerId()) ?>"><?= e($cb->obtenerBancoNombre()) ?> — <?= e($cb->obtenerNumeroCuenta()) ?> (<?= e($cb->obtenerMonedaCodigo()) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Si el método es caja física -->
                    <div class="mb-3 d-none" id="grupo-sesion-caja">
                        <div class="alert alert-info border-0 p-2 f-s-12 mb-0 b-r-8">
                            <i class="fa-solid fa-circle-info me-1"></i> Se imputará al <strong>Turno Actual de Caja</strong>.
                            <input type="hidden" id="cobro-sesion-caja-id" name="sesion_caja_id" value="<?= e((string) ($sesion_activa['id'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cobro-referencia" class="form-label f-s-12 f-w-600">Número de Operación / Voucher / Referencia</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-receipt position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="text" class="form-control ps-5" id="cobro-referencia" name="referencia_operacion" placeholder="Ej: OP-839218 o voucher POS...">
                        </div>
                    </div>

                    <!-- Auto-aplicación opcional a cargo pendiente -->
                    <div class="mb-3">
                        <label for="cobro-cargo-autoaplica" class="form-label f-s-12 f-w-600">Aplicar Inmediatamente a Cargo (Opcional)</label>
                        <select class="form-select select-clear" id="cobro-cargo-autoaplica" name="cargo_id_autoaplica" data-placeholder="No aplicar ahora (dejar como saldo a favor)">
                            <option value="">No aplicar ahora (dejar como saldo a favor disponible)</option>
                        </select>
                        <small class="text-muted f-s-11 mt-1 d-block">Si selecciona un cargo, el cobro amortizará su saldo pendiente en la misma transacción.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirmar-cobro">
                        <i class="fa-solid fa-check me-1"></i> Registrar Cobro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 6: Aplicar Pago a Cargo -->
<div class="modal fade" id="modal-aplicar-pago" tabindex="-1" aria-labelledby="modalAplicarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modalAplicarLabel">
                    <i class="fa-solid fa-link text-info me-2"></i> Aplicar Saldo de Pago a Cargo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-aplicar-pago" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="aplicar-cargo-id" name="cargo_id" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600">Cargo a Amortizar</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-file-invoice position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="text" class="form-control ps-5 bg-light" id="aplicar-cargo-info" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="aplicar-pago-id" class="form-label f-s-12 f-w-600">Seleccionar Pago con Saldo Disponible <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="aplicar-pago-id" name="pago_id" data-placeholder="Seleccione el pago..." required>
                            <option value="">Seleccione el pago...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="aplicar-monto" class="form-label f-s-12 f-w-600">Monto a Imputar (PEN) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-hand-holding-dollar position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="number" step="0.01" min="0.01" class="form-control ps-5" id="aplicar-monto" name="monto_aplicar" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info btn-sm text-white" id="btn-confirmar-aplicacion">
                        <i class="fa-solid fa-check me-1"></i> Imputar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 7: Registrar Devolución -->
<div class="modal fade" id="modal-registrar-devolucion" tabindex="-1" aria-labelledby="modalDevLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-dark" id="modalDevLabel">
                    <i class="fa-solid fa-arrow-rotate-left text-danger me-2"></i> Registrar Devolución / Reembolso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-registrar-devolucion" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="dev-folio-id" name="cuenta_folio_id" value="">
                <input type="hidden" id="dev-pago-id" name="pago_id" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600">Pago Origen del Reembolso</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-receipt position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="text" class="form-control ps-5 bg-light" id="dev-pago-info" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="dev-metodo-id" class="form-label f-s-12 f-w-600">Método de Devolución / Egreso <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" id="dev-metodo-id" name="metodo_pago_id" data-placeholder="Seleccione método..." required>
                            <option value="">Seleccione método...</option>
                            <?php foreach ($metodos_pago as $mp): ?>
                                <option value="<?= e((string) $mp->obtenerId()) ?>" data-tipo-destino="<?= e($mp->obtenerTipoDestino()) ?>">
                                    <?= e($mp->obtenerNombre()) ?> (<?= e($mp->obtenerTipoDestino()) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dev-monto" class="form-label f-s-12 f-w-600">Monto a Devolver (PEN) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-arrow-rotate-left position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                            <input type="number" step="0.01" min="0.01" class="form-control ps-5" id="dev-monto" name="monto_devolucion" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="dev-motivo" class="form-label f-s-12 f-w-600">Motivo de Devolución Justificado <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3"></i>
                            <textarea class="form-control ps-5" id="dev-motivo" name="motivo" rows="2" placeholder="Explicación detallada del reembolso..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-devolucion">
                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Confirmar Devolución
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-caja.js') ?>"></script>
