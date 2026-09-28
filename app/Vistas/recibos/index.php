<?php

declare(strict_types=1);

/**
 * Vista principal de Recibos de Cobranza y Snapshots Históricos — Camargo PMS (RECIBOS-1 / D-082).
 *
 * Principios vinculantes:
 * - CARGO != PAGO != APLICACIÓN != RECIBO != PDF.
 * - RECIBO = Constancia probatoria histórica de un hecho de cobro en T0.
 * - monto_recaudado = monto_imputado + monto_no_aplicado_pago.
 * - Inviolabilidad criptográfica del PDF soberano (no se sobreescribe ante anulación).
 * - Desacople operativo: anular recibo != reversar pago ni generar salidas ficticias de caja.
 *
 * @var array<string, mixed> $kpis
 * @var array<int, string> $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\Usuario|null $usuario
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Recibos -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-receipt f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Recibos de Cobranza y Snapshots Históricos</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Constancias institucionales probatorias en T0, imputación de pagos, saldos de cuenta folio y preservación documental inmutable.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (in_array('recibos.emitir', $permisos, true)): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-emitir">
                        <i class="fa-solid fa-file-circle-plus me-1"></i> Emitir Recibo Oficial
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Recibos y Cobranzas -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Emitidos Hoy</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-emitidos-hoy"><?= (int) ($kpis['emitidos_hoy'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Constancias probatorias</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-receipt f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Recaudación Hoy</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-recaudado-hoy">S/ <?= e((string) ($kpis['total_recaudado_hoy'] ?? '0.00')) ?></h3>
                                    <span class="f-s-11 text-muted">Monto total percibido</span>
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
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Recibos Anulados</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-danger mt-1" id="kpi-anulados"><?= (int) ($kpis['anulados_total'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">PDF soberano preservado</span>
                                </div>
                                <div class="bg-light-danger text-danger p-3 b-r-8">
                                    <i class="fa-solid fa-ban f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Con Saldo a Favor</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1" id="kpi-saldo-favor"><?= (int) ($kpis['con_saldo_favor'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Monto no aplicado en T0</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-wallet f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros de Búsqueda -->
            <div class="card-body p-3 border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4 col-sm-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda" placeholder="Buscar por código, titular, documento o referencia...">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <select class="form-select form-select-sm" id="filtro-estado">
                            <option value="">Todos los Estados</option>
                            <option value="EMITIDO">EMITIDO (Activo)</option>
                            <option value="ANULADO">ANULADO (Histórico)</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <select class="form-select form-select-sm" id="filtro-folio">
                            <option value="">Todas las Cuentas Folio</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-refrescar-tabla">
                            <i class="fa-solid fa-rotate me-1"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal de Recibos -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-recibos">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 12%;">Folio Recibo</th>
                                <th style="width: 13%;">Fecha Emisión</th>
                                <th style="width: 20%;">Titular / Pagador</th>
                                <th style="width: 10%;">Cuenta Folio</th>
                                <th class="text-end" style="width: 11%;">Recaudado</th>
                                <th class="text-end" style="width: 11%;">Imputado</th>
                                <th class="text-end" style="width: 11%;">Saldo Favor T0</th>
                                <th class="text-center" style="width: 6%;">Estado</th>
                                <th class="text-center" style="width: 6%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-recibos">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando recibos...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EMITIR RECIBO OFICIAL (ALINA D-075 / D-076)                        -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-emitir-recibo" tabindex="-1" aria-labelledby="modalEmitirReciboLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700 text-primary" id="modalEmitirReciboLabel">
                    <i class="fa-solid fa-receipt me-2"></i> Emitir Recibo Oficial de Cobranza (T0)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-emitir-recibo" class="app-form app-icon-form">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 b-r-8 mb-4 d-flex align-items-center">
                        <i class="fa-solid fa-circle-info f-s-20 me-3"></i>
                        <div class="f-s-12">
                            <strong>Axioma D-082:</strong> La emisión del recibo captura de forma estricta las amortizaciones aplicadas y los saldos del folio en este instante preciso ($T_0$). Se compilará el documento PDF oficial con preservación criptográfica inmutable.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Seleccionar Pago Confirmado <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-money-check-dollar"></i></span>
                                <select class="form-select" id="emitir-pago-id" name="pago_id" required>
                                    <option value="">Seleccione un pago confirmado elegible...</option>
                                </select>
                            </div>
                            <small class="text-muted f-s-11">Solo se listan pagos confirmados que no cuentan previamente con un recibo activo emitido.</small>
                        </div>

                        <!-- Card de detalle del pago seleccionado -->
                        <div class="col-12 d-none" id="card-detalle-pago-seleccionado">
                            <div class="card bg-light border-0 b-r-8 p-3">
                                <div class="row g-2 f-s-12">
                                    <div class="col-md-4">
                                        <span class="text-muted">Titular:</span>
                                        <p class="mb-0 f-w-600" id="prev-titular">-</p>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted">Cuenta Folio:</span>
                                        <p class="mb-0 f-w-600" id="prev-folio">-</p>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted">Monto Recaudado:</span>
                                        <p class="mb-0 f-w-700 text-primary" id="prev-monto">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-muted">Método de Pago:</span>
                                        <p class="mb-0" id="prev-metodo">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="text-muted">Referencia / Operación:</span>
                                        <p class="mb-0" id="prev-referencia">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Concepto General Descriptivo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-align-left"></i></span>
                                <input type="text" class="form-control" id="emitir-concepto" name="concepto_general" placeholder="Dejar vacío para generación automática a partir de cargos cubiertos...">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Notas Administrativas Adicionales</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-comment-dots"></i></span>
                                <textarea class="form-control" id="emitir-notas" name="notas" rows="2" placeholder="Observaciones o notas visibles en el expediente del recibo..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirmar-emision">
                        <i class="fa-solid fa-check me-1"></i> Emitir Recibo Oficial y Compilar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: DETALLE DE RECIBO Y AMORTIZACIONES T0 (ALINA D-075)                 -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-detalle-recibo" tabindex="-1" aria-labelledby="modalDetalleReciboLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center">
                    <h5 class="modal-title f-s-16 f-w-700 text-primary mb-0" id="modalDetalleReciboLabel">
                        <i class="fa-solid fa-receipt me-2"></i> Recibo de Cobranza <span id="det-folio-codigo"></span>
                    </h5>
                    <span class="badge ms-3" id="det-estado-badge">EMITIDO</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Información General y Titular -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light p-3 b-r-8 h-100">
                            <h6 class="f-s-13 f-w-700 text-secondary border-bottom pb-2 mb-2">Datos del Titular y Folio</h6>
                            <table class="table table-sm table-borderless mb-0 f-s-12">
                                <tr>
                                    <td class="text-muted" style="width: 35%;">Titular Recibo:</td>
                                    <td class="f-w-600" id="det-titular-nombre">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Documento Identidad:</td>
                                    <td id="det-titular-documento">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Cuenta Folio:</td>
                                    <td><code id="det-folio-asociado">-</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Fecha Emisión:</td>
                                    <td id="det-fecha-emision">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light p-3 b-r-8 h-100">
                            <h6 class="f-s-13 f-w-700 text-secondary border-bottom pb-2 mb-2">Hecho Económico de Cobro</h6>
                            <table class="table table-sm table-borderless mb-0 f-s-12">
                                <tr>
                                    <td class="text-muted" style="width: 35%;">Pago Registrado:</td>
                                    <td><code id="det-pago-codigo">-</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Método de Pago:</td>
                                    <td id="det-metodo-pago">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Referencia Operación:</td>
                                    <td id="det-referencia-cobro">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Monto Recaudado:</td>
                                    <td class="f-w-700 text-primary f-s-14" id="det-monto-recaudado">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Imputaciones en T0 -->
                <div class="card border-0 shadow-none mb-4">
                    <div class="card-header bg-white px-0 py-2 border-bottom">
                        <h6 class="f-s-14 f-w-700 mb-0 text-dark">
                            <i class="fa-solid fa-list-check me-2 text-primary"></i> Amortizaciones Imputadas en T0
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 f-s-12">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;">#</th>
                                    <th style="width: 18%;">Código Cargo</th>
                                    <th>Concepto de la Obligación</th>
                                    <th class="text-end" style="width: 15%;">Total Cargo</th>
                                    <th class="text-end" style="width: 15%;">Monto Imputado</th>
                                    <th class="text-end" style="width: 15%;">Saldo Restante Cargo</th>
                                </tr>
                            </thead>
                            <tbody id="det-tbody-lineas">
                                <!-- Filas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Resumen de Balance y Saldos Resultantes de Folio -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 bg-light p-3 b-r-8">
                            <h6 class="f-s-13 f-w-700 text-secondary border-bottom pb-2 mb-2">Preservación Criptográfica</h6>
                            <table class="table table-sm table-borderless mb-0 f-s-12">
                                <tr>
                                    <td class="text-muted" style="width: 35%;">Documento ID:</td>
                                    <td id="det-doc-id">-</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Hash SHA-256:</td>
                                    <td><code class="f-s-10 word-break-all" id="det-doc-hash">-</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Integridad Física:</td>
                                    <td id="det-doc-integridad"><span class="badge bg-secondary">Sin validar</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light p-3 b-r-8">
                            <h6 class="f-s-13 f-w-700 text-secondary border-bottom pb-2 mb-2">Balance Contable y Saldos de Folio T0</h6>
                            <table class="table table-sm table-borderless mb-0 f-s-12">
                                <tr>
                                    <td class="text-muted">Total Recaudado en Pago:</td>
                                    <td class="text-end f-w-700" id="det-res-recaudado">S/ 0.00</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Imputado a Cargos:</td>
                                    <td class="text-end text-primary" id="det-res-imputado">S/ 0.00</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Monto No Aplicado (Saldo Favor):</td>
                                    <td class="text-end text-success f-w-600" id="det-res-no-aplicado">S/ 0.00</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="text-muted f-w-600">Saldo Pendiente Exigible del Folio:</td>
                                    <td class="text-end text-danger f-w-700" id="det-res-saldo-pendiente">S/ 0.00</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Saldo a Favor Acumulado del Folio:</td>
                                    <td class="text-end text-info f-w-600" id="det-res-saldo-favor">S/ 0.00</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Trazabilidad de Anulación si aplica -->
                <div class="alert alert-danger border-0 b-r-8 mt-3 mb-0 d-none" id="det-alerta-anulacion">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation f-s-20 me-3 text-danger"></i>
                        <div class="f-s-12">
                            <strong>Recibo Administrativamente Anulado:</strong>
                            <div class="mt-1" id="det-anulacion-motivo">-</div>
                            <div class="text-muted f-s-11 mt-1" id="det-anulacion-meta">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-info btn-sm" id="btn-verificar-hash-modal">
                        <i class="fa-solid fa-shield-halved me-1"></i> Verificar Integridad SHA-256
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-descargar-pdf-modal">
                        <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF Oficial
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ANULACIÓN FORMAL DE RECIBO (ALINA D-075)                           -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-anular-recibo" tabindex="-1" aria-labelledby="modalAnularReciboLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-20 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title f-s-16 f-w-700 text-danger" id="modalAnularReciboLabel">
                    <i class="fa-solid fa-ban me-2"></i> Anulación Formal de Recibo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-anular-recibo" class="app-form app-icon-form">
                <input type="hidden" id="anular-recibo-id" name="recibo_id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 b-r-8 mb-3 d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation f-s-20 me-3 text-warning"></i>
                        <div class="f-s-12">
                            <strong>Principio D-082 #3 y #4:</strong> La anulación invalida probatoriamente el recibo en base de datos, pero <strong>no borra ni altera el PDF original en disco</strong> ni revierte el pago en caja.
                        </div>
                    </div>

                    <p class="f-s-13 mb-3">¿Está seguro de anular el recibo <strong id="anular-folio-texto">-</strong>?</p>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Motivo Obligatorio de Anulación <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-pen-to-square"></i></span>
                            <textarea class="form-control" id="anular-motivo" name="motivo" rows="3" required placeholder="Explique la justificación formal de la anulación..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-anulacion">
                        <i class="fa-solid fa-ban me-1"></i> Proceder con Anulación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assets específicos de Recibos de Cobranza -->
<script src="/assets/js/gestion-recibos.js"></script>
