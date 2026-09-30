<?php

declare(strict_types=1);

/**
 * Vista principal del Módulo de Gastos Operativos, Egresos Administrativos y Tesorería.
 * GASTOS-1 / D-086.
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var \CamargoPMS\Modelos\GastoCategoria[] $categorias
 * @var array<int, array<string, mixed>> $propiedades
 * @var array<int, array<string, mixed>> $unidades
 * @var array<int, array<string, mixed>> $metodosPago
 * @var array<int, array<string, mixed>> $cuentasBancarias
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Gastos -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-file-invoice-dollar f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Gastos Operativos, Egresos y Tesorería</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Reconocimiento del hecho económico, imputación analítica y articulación con Tesorería.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nuevo-gasto">
                        <i class="fa-solid fa-plus me-1"></i> Registrar Gasto
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- KPIs Operativos Financieros -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted f-s-12 mb-1 text-uppercase f-w-600">Total Gastos (Mes)</p>
                                    <h4 class="mb-0 f-s-20 f-w-700 text-dark" id="kpi-total-gastos">S/ 0.00</h4>
                                </div>
                                <span class="badge bg-light-primary text-primary p-2 b-r-8">
                                    <i class="fa-solid fa-receipt f-s-18"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted f-s-12 mb-1 text-uppercase f-w-600">Saldo Por Pagar</p>
                                    <h4 class="mb-0 f-s-20 f-w-700 text-warning" id="kpi-saldo-pendiente">S/ 0.00</h4>
                                </div>
                                <span class="badge bg-light-warning text-warning p-2 b-r-8">
                                    <i class="fa-solid fa-clock f-s-18"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted f-s-12 mb-1 text-uppercase f-w-600">Pagado / Liquidado</p>
                                    <h4 class="mb-0 f-s-20 f-w-700 text-success" id="kpi-total-pagado">S/ 0.00</h4>
                                </div>
                                <span class="badge bg-light-success text-success p-2 b-r-8">
                                    <i class="fa-solid fa-circle-check f-s-18"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted f-s-12 mb-1 text-uppercase f-w-600">Pendientes Aprobación</p>
                                    <h4 class="mb-0 f-s-20 f-w-700 text-info" id="kpi-pendientes-aprobacion">0</h4>
                                </div>
                                <span class="badge bg-light-info text-info p-2 b-r-8">
                                    <i class="fa-solid fa-hourglass-half f-s-18"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card-body p-3 border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-12">
                        <select class="form-select form-select-sm" id="filtro-categoria">
                            <option value="">Todas las Categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= e((string) $cat->obtenerId()) ?>"><?= e($cat->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-ambito">
                            <option value="">Todos los Ámbitos</option>
                            <option value="CORPORATIVO">Corporativo</option>
                            <option value="PROPIEDAD">Propiedad</option>
                            <option value="UNIDAD">Habitación / Unidad</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-propiedad">
                            <option value="">Todas las Propiedades</option>
                            <?php foreach ($propiedades as $p): ?>
                            <option value="<?= e((string) $p['id']) ?>"><?= e($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado">
                            <option value="">Todos los Estados</option>
                            <option value="REGISTRADO">Registrado</option>
                            <option value="APROBADO">Aprobado</option>
                            <option value="ANULADO">Anulado</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6 text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-aplicar-filtros">
                            <i class="fa-solid fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Gastos -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-gastos">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Código</th>
                                <th>Fecha</th>
                                <th>Categoría</th>
                                <th>Ámbito / Destino</th>
                                <th>Acreedor / Proveedor</th>
                                <th>Comprobante</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Saldo Pendiente</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Situación</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-gastos">
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando gastos...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Nuevo Gasto -->
<div class="modal fade" id="modal-nuevo-gasto" tabindex="-1" aria-labelledby="modalNuevoGastoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-12">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-w-700" id="modalNuevoGastoLabel">
                    <i class="fa-solid fa-file-circle-plus text-primary me-2"></i>Registrar Gasto Operativo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-nuevo-gasto" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="input-categoria">Categoría de Gasto *</label>
                            <select class="form-select basic-select2" name="categoria_id" id="input-categoria" required>
                                <option value="">Seleccione una categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= e((string) $cat->obtenerId()) ?>"><?= e($cat->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="input-ambito">Ámbito de Imputación *</label>
                            <select class="form-select form-select-sm" name="ambito" id="input-ambito" required>
                                <option value="PROPIEDAD" selected>Propiedad / Predio Específico</option>
                                <option value="CORPORATIVO">Corporativo / Sede Central</option>
                                <option value="UNIDAD">Habitación / Unidad Específica</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="div-propiedad">
                            <label class="form-label f-s-13 f-w-600" for="input-propiedad">Propiedad</label>
                            <select class="form-select basic-select2" name="propiedad_id" id="input-propiedad">
                                <option value="">Seleccione propiedad...</option>
                                <?php foreach ($propiedades as $p): ?>
                                <option value="<?= e((string) $p['id']) ?>"><?= e($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="div-unidad" style="display: none;">
                            <label class="form-label f-s-13 f-w-600" for="input-unidad">Unidad / Habitación</label>
                            <select class="form-select form-select-sm" name="unidad_id" id="input-unidad">
                                <option value="">Seleccione unidad...</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?= e((string) $u['id']) ?>" data-propiedad="<?= e((string) $u['propiedad_id']) ?>">
                                    <?= e($u['codigo']) ?> - <?= e($u['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600" for="input-descripcion-concepto">Descripción del Concepto *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-align-left position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="input-descripcion-concepto" name="descripcion_concepto" placeholder="Ej. Facturación de luz mes de septiembre - Medidor N° 8872" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label f-s-13 f-w-600" for="input-acreedor-nombre">Nombre / Razón Social del Acreedor *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user-tag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="input-acreedor-nombre" name="acreedor_nombre" placeholder="Ej. Luz del Sur S.A.A. / Cerrajería El Rápido" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-acreedor-doc">RUC / DNI Acreedor</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-id-card position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="input-acreedor-doc" name="acreedor_documento" placeholder="Ej. 20100035121">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-tipo-comprobante">Tipo de Comprobante *</label>
                            <select class="form-select basic-select2" id="input-tipo-comprobante" name="tipo_comprobante" required>
                                <option value="FACTURA">Factura Electrónica</option>
                                <option value="RECIBO_SERVICIO_PUBLICO">Recibo de Servicio Público</option>
                                <option value="RECIBO_HONORARIOS">Recibo por Honorarios (RxH)</option>
                                <option value="BOLETA">Boleta de Venta</option>
                                <option value="DECLARACION_JURADA_CAJA_CHICA">Declaración Jurada Caja Chica</option>
                                <option value="TICKET_MAQUINA">Ticket / Máquina Registradora</option>
                                <option value="OTRO_NO_TRIBUTARIO">Otro No Tributario</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-comprobante-serie">Serie Comprobante</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-receipt position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="input-comprobante-serie" name="comprobante_serie" placeholder="Ej. F001">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-comprobante-numero">Número Comprobante</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-hashtag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control form-control-sm ps-5" id="input-comprobante-numero" name="comprobante_numero" placeholder="Ej. 00049281">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="input-fecha-emision">Fecha de Emisión *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control form-control-sm ps-5" id="input-fecha-emision" name="fecha_emision" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-s-13 f-w-600" for="input-fecha-vencimiento">Fecha de Vencimiento *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-calendar-check position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="date" class="form-control form-control-sm ps-5" id="input-fecha-vencimiento" name="fecha_vencimiento" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-subtotal">Subtotal (S/)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">S/</span>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end" name="subtotal" id="input-subtotal" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-impuestos">Impuestos / IGV (S/)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">S/</span>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end" name="impuestos" id="input-impuestos" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600" for="input-total">Total (S/) *</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-primary text-white">S/</span>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end f-w-700 bg-light" name="total" id="input-total" value="0.00" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-gasto">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Gasto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Gasto y Evidencias -->
<div class="modal fade" id="modal-detalle-gasto" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-12">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-w-700" id="modalDetalleLabel">
                    <i class="fa-solid fa-circle-info text-primary me-2"></i>Detalle de Gasto <span id="detalle-codigo" class="text-secondary f-s-14"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3" id="detalle-contenido">
                    <!-- Dinámico -->
                </div>
            </div>
            <div class="modal-footer border-top py-2 d-flex justify-content-between">
                <div id="detalle-acciones-izquierda">
                    <!-- Dinámico -->
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Pago de Egreso (Tesorería) -->
<div class="modal fade" id="modal-pago-gasto" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-12">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-w-700" id="modalPagoLabel">
                    <i class="fa-solid fa-money-bill-transfer text-success me-2"></i>Pagar Gasto (Tesorería)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-pago-gasto" class="app-form app-icon-form" novalidate>
                <input type="hidden" name="gasto_id" id="pago-gasto-id">
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 mb-3 f-s-13">
                        Gasto: <strong id="pago-gasto-codigo"></strong><br>
                        Acreedor: <span id="pago-gasto-acreedor"></span><br>
                        Saldo Pendiente: <strong class="text-danger" id="pago-gasto-saldo">S/ 0.00</strong>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Método de Pago *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-credit-card position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <select class="form-select ps-5" name="metodo_pago_id" id="pago-metodo-id" required>
                                    <option value="">Seleccione método...</option>
                                    <?php foreach ($metodosPago as $m): ?>
                                    <option value="<?= e((string) $m['id']) ?>" data-destino="<?= e($m['tipo_destino']) ?>" data-codigo="<?= e($m['codigo']) ?>">
                                        <?= e($m['nombre']) ?> (<?= e($m['tipo_destino']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12" id="div-pago-caja" style="display: none;">
                            <label class="form-label f-s-13 f-w-600">Sesión de Caja Chica (Efectivo) *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-cash-register position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" class="form-control ps-5" name="sesion_caja_id" id="pago-sesion-caja-id" placeholder="ID de sesión abierta en recepción">
                            </div>
                            <small class="text-muted f-s-11">Requiere sesión de caja en estado ABIERTA con saldo suficiente.</small>
                        </div>
                        <div class="col-12" id="div-pago-banco" style="display: none;">
                            <label class="form-label f-s-13 f-w-600">Cuenta Bancaria de Origen *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-building-columns position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <select class="form-select ps-5" name="cuenta_bancaria_id" id="pago-cuenta-bancaria-id">
                                    <option value="">Seleccione cuenta...</option>
                                    <?php foreach ($cuentasBancarias as $b): ?>
                                    <option value="<?= e((string) $b['id']) ?>">
                                        <?= e($b['banco_nombre']) ?> - <?= e($b['numero_cuenta']) ?> (<?= e($b['moneda_codigo']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">N° Operación / Voucher / Referencia</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-receipt position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="referencia_operacion" placeholder="Ej. OP-994821">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Monto a Pagar (S/) *</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-money-bill-wave position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.01" class="form-control ps-5 text-end f-w-700" name="monto" id="pago-monto" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-confirmar-pago">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Desembolso
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global').value;
    const tbody = document.getElementById('tbody-gastos');

    // Cargar Gastos
    function cargarGastos() {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando gastos...</td></tr>';

        const params = new URLSearchParams();
        const cat = document.getElementById('filtro-categoria').value;
        const amb = document.getElementById('filtro-ambito').value;
        const prop = document.getElementById('filtro-propiedad').value;
        const est = document.getElementById('filtro-estado').value;

        if (cat) params.append('categoria_id', cat);
        if (amb) params.append('ambito', amb);
        if (prop) params.append('propiedad_id', prop);
        if (est) params.append('estado', est);

        fetch('/api/gastos?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.exito) {
                    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">${data.mensaje}</td></tr>`;
                    return;
                }
                renderizarGastos(data.datos);
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">Error de conexión al cargar gastos.</td></tr>`;
            });
    }

    function renderizarGastos(gastos) {
        if (!gastos || gastos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted">No se encontraron gastos registrados.</td></tr>';
            actualizarKpis([]);
            return;
        }

        actualizarKpis(gastos);

        let html = '';
        gastos.forEach(g => {
            const badgeEstado = obtenerBadgeEstado(g.estado);
            const badgeSituacion = obtenerBadgeSituacion(g.situacion_financiera);

            let destinoTexto = g.ambito;
            if (g.ambito === 'PROPIEDAD') destinoTexto = g.propiedad_nombre || 'Propiedad';
            if (g.ambito === 'UNIDAD') destinoTexto = `${g.propiedad_nombre || ''} - Hab. ${g.unidad_numero || ''}`;
            if (g.ambito === 'CORPORATIVO') destinoTexto = '<span class="text-muted">Corporativo</span>';

            const puedeAprobar = (g.estado === 'REGISTRADO' || g.estado === 'BORRADOR');
            const puedePagar = (g.estado === 'APROBADO' && parseFloat(g.saldo_pendiente) > 0);
            const puedeAnular = (g.estado !== 'ANULADO' && parseFloat(g.monto_aplicado_acumulado) === 0);

            html += `
                <tr>
                    <td class="ps-3"><strong class="f-w-700">${g.codigo}</strong></td>
                    <td class="f-s-13">${g.fecha_emision}</td>
                    <td class="f-s-13">${g.categoria_nombre || '—'}</td>
                    <td class="f-s-13">${destinoTexto}</td>
                    <td class="f-s-13">
                        <strong>${g.acreedor_nombre}</strong>
                        ${g.acreedor_documento ? `<br><small class="text-muted">RUC/DNI: ${g.acreedor_documento}</small>` : ''}
                    </td>
                    <td class="f-s-13">
                        <span class="badge bg-light text-dark border">${g.tipo_comprobante}</span>
                        ${g.comprobante_serie && g.comprobante_numero ? `<br><small class="text-muted">${g.comprobante_serie}-${g.comprobante_numero}</small>` : ''}
                    </td>
                    <td class="text-end f-w-700">S/ ${parseFloat(g.total).toFixed(2)}</td>
                    <td class="text-end f-w-700 ${parseFloat(g.saldo_pendiente) > 0 ? 'text-danger' : 'text-success'}">S/ ${parseFloat(g.saldo_pendiente).toFixed(2)}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-center">${badgeSituacion}</td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary btn-ver" data-id="${g.id}" title="Ver Detalle">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            ${puedeAprobar ? `
                            <button type="button" class="btn btn-outline-success btn-aprobar" data-id="${g.id}" title="Aprobar Gasto">
                                <i class="fa-solid fa-check"></i>
                            </button>` : ''}
                            ${puedePagar ? `
                            <button type="button" class="btn btn-outline-primary btn-pagar" data-id="${g.id}" data-codigo="${g.codigo}" data-acreedor="${g.acreedor_nombre}" data-saldo="${g.saldo_pendiente}" title="Registrar Pago">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </button>` : ''}
                            ${puedeAnular ? `
                            <button type="button" class="btn btn-outline-danger btn-anular" data-id="${g.id}" data-codigo="${g.codigo}" title="Anular Gasto">
                                <i class="fa-solid fa-ban"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        adjuntarEventosTabla();
    }

    function actualizarKpis(gastos) {
        let total = 0;
        let saldo = 0;
        let pagado = 0;
        let pendientes = 0;

        gastos.forEach(g => {
            if (g.estado !== 'ANULADO') {
                total += parseFloat(g.total) || 0;
                saldo += parseFloat(g.saldo_pendiente) || 0;
                pagado += parseFloat(g.monto_aplicado_acumulado) || 0;
                if (g.estado === 'REGISTRADO' || g.estado === 'BORRADOR') {
                    pendientes++;
                }
            }
        });

        document.getElementById('kpi-total-gastos').innerText = 'S/ ' + total.toFixed(2);
        document.getElementById('kpi-saldo-pendiente').innerText = 'S/ ' + saldo.toFixed(2);
        document.getElementById('kpi-total-pagado').innerText = 'S/ ' + pagado.toFixed(2);
        document.getElementById('kpi-pendientes-aprobacion').innerText = pendientes;
    }

    function obtenerBadgeEstado(estado) {
        switch (estado) {
            case 'APROBADO': return '<span class="badge bg-light-success text-success">APROBADO</span>';
            case 'REGISTRADO': return '<span class="badge bg-light-primary text-primary">REGISTRADO</span>';
            case 'BORRADOR': return '<span class="badge bg-light-secondary text-secondary">BORRADOR</span>';
            case 'ANULADO': return '<span class="badge bg-light-danger text-danger">ANULADO</span>';
            default: return `<span class="badge bg-light text-dark">${estado}</span>`;
        }
    }

    function obtenerBadgeSituacion(situacion) {
        switch (situacion) {
            case 'PAGADO': return '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>PAGADO</span>';
            case 'PARCIAL': return '<span class="badge bg-info text-dark"><i class="fa-solid fa-clock-rotate-left me-1"></i>PARCIAL</span>';
            case 'PENDIENTE': return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>PENDIENTE</span>';
            default: return `<span class="badge bg-light text-dark">${situacion}</span>`;
        }
    }

    // Modal Control: Ámbito y cambio de selects
    const selectAmbito = document.getElementById('input-ambito');
    const divPropiedad = document.getElementById('div-propiedad');
    const divUnidad = document.getElementById('div-unidad');
    const selectPropiedad = document.getElementById('input-propiedad');
    const selectUnidad = document.getElementById('input-unidad');

    selectAmbito.addEventListener('change', function () {
        const val = this.value;
        if (val === 'CORPORATIVO') {
            divPropiedad.style.display = 'none';
            divUnidad.style.display = 'none';
            selectPropiedad.value = '';
            selectUnidad.value = '';
        } else if (val === 'PROPIEDAD') {
            divPropiedad.style.display = 'block';
            divUnidad.style.display = 'none';
            selectUnidad.value = '';
        } else if (val === 'UNIDAD') {
            divPropiedad.style.display = 'block';
            divUnidad.style.display = 'block';
            filtrarUnidadesPorPropiedad();
        }
    });

    selectPropiedad.addEventListener('change', function () {
        if (selectAmbito.value === 'UNIDAD') {
            filtrarUnidadesPorPropiedad();
        }
    });

    function filtrarUnidadesPorPropiedad() {
        const propId = selectPropiedad.value;
        Array.from(selectUnidad.options).forEach(opt => {
            if (!opt.value) return;
            opt.style.display = (opt.dataset.propiedad === propId) ? 'block' : 'none';
        });
    }

    // Cálculo automático de Total en Modal
    const inSubtotal = document.getElementById('input-subtotal');
    const inImpuestos = document.getElementById('input-impuestos');
    const inTotal = document.getElementById('input-total');

    function recalcularTotal() {
        const sub = parseFloat(inSubtotal.value) || 0;
        const imp = parseFloat(inImpuestos.value) || 0;
        inTotal.value = (sub + imp).toFixed(2);
    }
    inSubtotal.addEventListener('input', recalcularTotal);
    inImpuestos.addEventListener('input', recalcularTotal);

    // Abrir Modal Nuevo Gasto
    document.getElementById('btn-nuevo-gasto').addEventListener('click', function () {
        document.getElementById('form-nuevo-gasto').reset();
        selectAmbito.dispatchEvent(new Event('change'));
        recalcularTotal();
        const modal = new bootstrap.Modal(document.getElementById('modal-nuevo-gasto'));
        modal.show();
    });

    // Guardar Nuevo Gasto
    document.getElementById('form-nuevo-gasto').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const json = Object.fromEntries(formData.entries());

        fetch('/api/gastos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(json),
        })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modal-nuevo-gasto')).hide();
                Swal.fire('Éxito', data.mensaje, 'success');
                cargarGastos();
            } else {
                Swal.fire('Error', data.mensaje, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Fallo de conexión al registrar gasto.', 'error'));
    });

    // Control de Método de Pago en Modal de Tesorería
    const selectMetodoPago = document.getElementById('pago-metodo-id');
    const divPagoCaja = document.getElementById('div-pago-caja');
    const divPagoBanco = document.getElementById('div-pago-banco');

    selectMetodoPago.addEventListener('change', function () {
        const sel = this.options[this.selectedIndex];
        const destino = sel.dataset.destino;
        const codigo = sel.dataset.codigo;

        if (destino === 'CAJA_FISICA' || codigo === 'EFECTIVO') {
            divPagoCaja.style.display = 'block';
            divPagoBanco.style.display = 'none';
        } else if (destino === 'CUENTA_BANCARIA' || codigo === 'TRANSFERENCIA') {
            divPagoCaja.style.display = 'none';
            divPagoBanco.style.display = 'block';
        } else {
            divPagoCaja.style.display = 'none';
            divPagoBanco.style.display = 'none';
        }
    });

    // Acciones de Tabla
    function adjuntarEventosTabla() {
        // Ver Detalle
        document.querySelectorAll('.btn-ver').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                fetch(`/api/gastos/${id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.exito) {
                            Swal.fire('Error', data.mensaje, 'error');
                            return;
                        }
                        mostrarDetalleGasto(data.datos);
                    });
            });
        });

        // Aprobar
        document.querySelectorAll('.btn-aprobar').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                Swal.fire({
                    title: '¿Aprobar Gasto?',
                    text: 'Habilitará el gasto para desembolso y pago en tesorería.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, aprobar',
                    cancelButtonText: 'Cancelar'
                }).then(res => {
                    if (res.isConfirmed) {
                        fetch(`/api/gastos/${id}/aprobar`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({ motivo: 'Aprobación autorizada desde UI' })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.exito) {
                                Swal.fire('Aprobado', data.mensaje, 'success');
                                cargarGastos();
                            } else {
                                Swal.fire('Error', data.mensaje, 'error');
                            }
                        });
                    }
                });
            });
        });

        // Anular
        document.querySelectorAll('.btn-anular').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const cod = this.dataset.codigo;
                Swal.fire({
                    title: `¿Anular Gasto ${cod}?`,
                    text: 'Ingrese el motivo de anulación:',
                    input: 'text',
                    inputPlaceholder: 'Motivo obligatorio...',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, anular',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (val) => {
                        if (!val || !val.trim()) return 'Debe ingresar un motivo.';
                    }
                }).then(res => {
                    if (res.isConfirmed) {
                        fetch(`/api/gastos/${id}/anular`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({ motivo: res.value })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.exito) {
                                Swal.fire('Anulado', data.mensaje, 'success');
                                cargarGastos();
                            } else {
                                Swal.fire('Error', data.mensaje, 'error');
                            }
                        });
                    }
                });
            });
        });

        // Registrar Pago
        document.querySelectorAll('.btn-pagar').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const cod = this.dataset.codigo;
                const acreedor = this.dataset.acreedor;
                const saldo = this.dataset.saldo;

                document.getElementById('form-pago-gasto').reset();
                document.getElementById('pago-gasto-id').value = id;
                document.getElementById('pago-gasto-codigo').innerText = cod;
                document.getElementById('pago-gasto-acreedor').innerText = acreedor;
                document.getElementById('pago-gasto-saldo').innerText = 'S/ ' + parseFloat(saldo).toFixed(2);
                document.getElementById('pago-monto').value = parseFloat(saldo).toFixed(2);
                selectMetodoPago.dispatchEvent(new Event('change'));

                const modal = new bootstrap.Modal(document.getElementById('modal-pago-gasto'));
                modal.show();
            });
        });
    }

    // Confirmar Pago
    document.getElementById('form-pago-gasto').addEventListener('submit', function (e) {
        e.preventDefault();
        const id = document.getElementById('pago-gasto-id').value;
        const formData = new FormData(this);
        const json = Object.fromEntries(formData.entries());

        fetch(`/api/gastos/${id}/pagar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(json),
        })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                bootstrap.Modal.getInstance(document.getElementById('modal-pago-gasto')).hide();
                Swal.fire('Pago Exitoso', data.mensaje, 'success');
                cargarGastos();
            } else {
                Swal.fire('Error al Pagar', data.mensaje, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Fallo de conexión al ejecutar el pago.', 'error'));
    });

    function mostrarDetalleGasto(g) {
        document.getElementById('detalle-codigo').innerText = `[${g.codigo}]`;
        const c = document.getElementById('detalle-contenido');

        let evidenciasHtml = '';
        if (g.evidencias_lista && g.evidencias_lista.length > 0) {
            evidenciasHtml = '<ul class="list-group list-group-flush mb-0">';
            g.evidencias_lista.forEach(ev => {
                evidenciasHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fa-solid fa-file-pdf text-danger me-2"></i>
                            <strong>${ev.nombre_original}</strong> (${ev.tipo_evidencia})
                        </div>
                        <span class="badge bg-light text-muted border">${(ev.tamano_bytes / 1024).toFixed(1)} KB</span>
                    </li>
                `;
            });
            evidenciasHtml += '</ul>';
        } else {
            evidenciasHtml = '<p class="text-muted f-s-13 mb-0">Sin evidencias adjuntas.</p>';
        }

        let aplicacionesHtml = '';
        if (g.aplicaciones_lista && g.aplicaciones_lista.length > 0) {
            aplicacionesHtml = '<ul class="list-group list-group-flush mb-0">';
            g.aplicaciones_lista.forEach(ap => {
                aplicacionesHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <i class="fa-solid fa-money-bill-transfer text-success me-2"></i>
                            Aplicación <strong>${ap.codigo}</strong>
                            <span class="badge ${ap.estado === 'ACTIVO' ? 'bg-light-success text-success' : 'bg-light-danger text-danger'} ms-2">${ap.estado}</span>
                        </div>
                        <strong>S/ ${parseFloat(ap.monto_aplicado).toFixed(2)}</strong>
                    </li>
                `;
            });
            aplicacionesHtml += '</ul>';
        } else {
            aplicacionesHtml = '<p class="text-muted f-s-13 mb-0">Sin pagos aplicados aún.</p>';
        }

        c.innerHTML = `
            <div class="col-md-6">
                <p class="mb-1 text-muted f-s-12">CONCEPTO</p>
                <p class="f-w-600 mb-2">${g.descripcion_concepto}</p>
                <p class="mb-1 text-muted f-s-12">ACREEDOR / PROVEEDOR</p>
                <p class="f-w-600 mb-2">${g.acreedor_nombre} ${g.acreedor_documento ? `(RUC/DNI: ${g.acreedor_documento})` : ''}</p>
                <p class="mb-1 text-muted f-s-12">CATEGORÍA</p>
                <p class="f-w-600 mb-2">${g.categoria_nombre || '—'}</p>
            </div>
            <div class="col-md-6 bg-light p-3 b-r-8">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Total Reconocido:</span>
                    <strong class="f-w-700">S/ ${parseFloat(g.total).toFixed(2)}</strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Monto Pagado:</span>
                    <strong class="text-success">S/ ${parseFloat(g.monto_aplicado_acumulado).toFixed(2)}</strong>
                </div>
                <div class="d-flex justify-content-between border-top pt-2">
                    <span class="f-w-700">Saldo Pendiente:</span>
                    <strong class="f-w-700 text-danger">S/ ${parseFloat(g.saldo_pendiente).toFixed(2)}</strong>
                </div>
            </div>
            <div class="col-md-6 border-top pt-3">
                <h6 class="f-w-700 f-s-14"><i class="fa-solid fa-paperclip me-1"></i> Evidencias Documentales</h6>
                ${evidenciasHtml}
            </div>
            <div class="col-md-6 border-top pt-3">
                <h6 class="f-w-700 f-s-14"><i class="fa-solid fa-receipt me-1"></i> Historial de Pagos de Tesorería</h6>
                ${aplicacionesHtml}
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('modal-detalle-gasto'));
        modal.show();
    }

    // Filtros y Recargar
    document.getElementById('btn-aplicar-filtros').addEventListener('click', cargarGastos);
    document.getElementById('btn-recargar').addEventListener('click', cargarGastos);

    // Carga Inicial
    cargarGastos();
});
</script>
