<?php

declare(strict_types=1);

/**
 * Ficha Integral de Detalle de Transacción de Pasarela — Camargo PMS (PAGOS-1D).
 *
 * Principios vinculantes:
 * - ALINA DESIGN SYSTEM: Cards equal-card, línea de tiempo nativa .app-side-timeline, badges Alina bg-light-*.
 * - INVARIANTE C1/C2: Señalización taxativa de pagos tardíos (DISCREPANCIA_HOLD_EXPIRADO). Prohibición estricta de reasignación a nueva reserva.
 * - SANITIZACIÓN: Zero-Trust. Cero llaves privadas, cero webhook secrets, cero PAN/CVV.
 * - CERO JQUERY CRUD: Vanilla JS y SweetAlert2.
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array{puede_ver: bool, puede_reembolsar: bool, puede_conciliar: bool} $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\PagoTransaccionPasarela $transaccion
 * @var \CamargoPMS\Modelos\Reserva|null $reserva
 * @var \CamargoPMS\Modelos\CuentaFolio|null $folio
 * @var \CamargoPMS\Modelos\PagoWebhookEvento[] $webhooks
 * @var array<string, mixed> $metadatos
 * @var array<int, array{titulo: string, fecha: string, tipo: string, icono: string, descripcion: string}> $lineaTiempo
 */

use CamargoPMS\Nucleo\Ayudante;

$tx = $transaccion;
$txId = (int) $tx->obtenerId();
$formatearDinero = static function (mixed $monto): string {
    return 'S/ ' . number_format((float) ($monto ?? 0), 2, '.', ',');
};

$esAprobado = $tx->estaAprobado();
$saldoDisponible = (float) $tx->obtenerSaldoReembolsable();
$permiteReembolso = $permisos['puede_reembolsar'] && $esAprobado && $saldoDisponible > 0.00;
$esTardio = $tx->obtenerSubtipoDiscrepancia() === 'DISCREPANCIA_HOLD_EXPIRADO';
$esDiscrepancia = $tx->obtenerEstadoConciliacion() === 'DISCREPANCIA' || $esTardio;
?>
<input type="hidden" id="csrf-token-global" value="<?= Ayudante::escapar($csrf_token) ?>">

<!-- Encabezado de la Transacción -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-receipt f-s-22"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="card-title mb-0 f-s-18 f-w-700"><?= Ayudante::escapar($tx->obtenerCodigo()) ?></h4>
                            <span class="badge bg-light-primary text-primary f-s-12"><?= Ayudante::escapar($tx->obtenerProveedor()) ?></span>
                            <?php if ($esAprobado): ?>
                                <span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i> Aprobado</span>
                            <?php else: ?>
                                <span class="badge bg-light-secondary text-secondary"><?= Ayudante::escapar($tx->obtenerEstadoPago()) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-secondary f-s-13 mb-0">
                            Registrado el <?= Ayudante::escapar($tx->obtenerCreadoEn()) ?> | Última actualización: <?= Ayudante::escapar($tx->obtenerActualizadoEn()) ?>
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <a href="<?= Ayudante::ruta('/pagos') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i> Volver al Monitor
                    </a>
                    <?php if ($permiteReembolso): ?>
                    <button type="button" class="btn btn-danger btn-sm btn-abrir-reembolso"
                            data-id="<?= $txId ?>"
                            data-codigo="<?= Ayudante::escapar($tx->obtenerCodigo()) ?>"
                            data-cobrado="<?= (float) $tx->obtenerMontoCobrado() ?>"
                            data-reembolsado="<?= (float) $tx->obtenerMontoReembolsado() ?>"
                            data-disponible="<?= $saldoDisponible ?>">
                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Emitir Reembolso
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alerta Invariante Hotelero C1/C2 (Pagos Tardíos) -->
            <?php if ($esTardio): ?>
            <div class="p-3 bg-light-danger border-bottom border-danger">
                <div class="d-flex align-items-start">
                    <span class="bg-danger text-white p-2 rounded-circle me-3 mt-1">
                        <i class="fa-solid fa-triangle-exclamation f-s-18"></i>
                    </span>
                    <div>
                        <h6 class="text-danger f-w-700 mb-1">
                            PAGO RECIBIDO CON RESERVA EXPIRADA — EN CUARENTENA OPERATIVA (INVARIANTE C1/C2)
                        </h6>
                        <p class="text-dark f-s-13 mb-2">
                            El cliente completó el pago en la pasarela <strong><?= Ayudante::escapar($tx->obtenerProveedor()) ?></strong> tras haber vencido la ventana de reserva preventiva (Hold).
                            <strong>El PMS protegió la soberanía hotelera:</strong> la reserva no fue reactivada automáticamente, el inventario permanece libre y no se generó folio contable de contingencia.
                        </p>
                        <div class="d-flex gap-2">
                            <?php if ($permiteReembolso): ?>
                            <button type="button" class="btn btn-danger btn-xs btn-abrir-reembolso"
                                    data-id="<?= $txId ?>"
                                    data-codigo="<?= Ayudante::escapar($tx->obtenerCodigo()) ?>"
                                    data-cobrado="<?= (float) $tx->obtenerMontoCobrado() ?>"
                                    data-reembolsado="<?= (float) $tx->obtenerMontoReembolsado() ?>"
                                    data-disponible="<?= $saldoDisponible ?>">
                                <i class="fa-solid fa-arrow-rotate-left me-1"></i> Proceder con Reembolso Total
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-dark btn-xs" id="btn-observacion-seguimiento" data-id="<?= $txId ?>">
                                <i class="fa-solid fa-note-sticky me-1"></i> Añadir Observación Administrativa
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Columna Izquierda: Desglose Financiero, Huésped, Reserva y Metadatos -->
    <div class="col-lg-7 col-12">
        <!-- 1. Desglose Financiero y de Pasarela -->
        <div class="card equal-card shadow-sm border-0 b-r-20 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="f-s-16 f-w-700 mb-0 text-dark">
                    <i class="fa-solid fa-money-bill-transfer text-primary me-2"></i> Desglose Financiero y de Pasarela
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-4 col-12">
                        <div class="p-3 bg-light rounded border text-center">
                            <span class="text-secondary f-s-12 d-block mb-1">Monto Esperado</span>
                            <h5 class="f-w-700 text-dark mb-0"><?= $formatearDinero($tx->obtenerMontoEsperado()) ?></h5>
                            <span class="text-muted f-s-11"><?= Ayudante::escapar($tx->obtenerMoneda()) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="p-3 bg-light-success rounded border border-success text-center">
                            <span class="text-success f-s-12 d-block mb-1">Monto Cobrado</span>
                            <h5 class="f-w-700 text-success mb-0"><?= $formatearDinero($tx->obtenerMontoCobrado()) ?></h5>
                            <span class="text-success f-s-11">Aprobado</span>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="p-3 bg-light-danger rounded border border-danger text-center">
                            <span class="text-danger f-s-12 d-block mb-1">Monto Reembolsado</span>
                            <h5 class="f-w-700 text-danger mb-0"><?= $formatearDinero($tx->obtenerMontoReembolsado()) ?></h5>
                            <span class="text-danger f-s-11">Disponible: <?= $formatearDinero($tx->obtenerSaldoReembolsable()) ?></span>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-2 f-s-13">
                    <div class="col-6">
                        <span class="text-secondary">ID Orden Pasarela:</span>
                        <div class="f-w-600 text-dark"><code><?= Ayudante::escapar($tx->obtenerProveedorOrdenId()) ?></code></div>
                    </div>
                    <div class="col-6">
                        <span class="text-secondary">ID Transacción Pasarela:</span>
                        <div class="f-w-600 text-dark"><code><?= Ayudante::escapar($tx->obtenerTransaccionIdExterno() ?: '—') ?></code></div>
                    </div>
                    <div class="col-6 mt-3">
                        <span class="text-secondary">Estado Conciliación:</span>
                        <div>
                            <?php if ($esTardio): ?>
                                <span class="badge bg-light-danger text-danger border border-danger">PAGO RECIBIDO / RES. EXPIRADA</span>
                            <?php elseif ($tx->obtenerEstadoConciliacion() === 'CONCILIADO'): ?>
                                <span class="badge bg-light-success text-success"><i class="fa-solid fa-check-double me-1"></i> Conciliado</span>
                            <?php else: ?>
                                <span class="badge bg-light-warning text-warning"><?= Ayudante::escapar($tx->obtenerEstadoConciliacion()) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6 mt-3">
                        <span class="text-secondary">Estado Reembolso:</span>
                        <div>
                            <?php if ($tx->obtenerEstadoReembolso() === 'NO_APLICA'): ?>
                                <span class="badge bg-light-secondary text-muted">Sin Reembolso</span>
                            <?php elseif ($tx->obtenerEstadoReembolso() === 'REEMBOLSADO_TOTAL'): ?>
                                <span class="badge bg-light-danger text-danger">Reembolsado Total</span>
                            <?php elseif ($tx->obtenerEstadoReembolso() === 'REEMBOLSADO_PARCIAL'): ?>
                                <span class="badge bg-light-primary text-primary">Reembolsado Parcial</span>
                            <?php else: ?>
                                <span class="badge bg-light-warning text-warning"><?= Ayudante::escapar($tx->obtenerEstadoReembolso()) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Vinculación con Reserva y Folio Hotelero -->
        <div class="card equal-card shadow-sm border-0 b-r-20 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="f-s-16 f-w-700 mb-0 text-dark">
                    <i class="fa-solid fa-hotel text-primary me-2"></i> Vinculación Operativa Hotelera
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if ($reserva !== null): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded border">
                        <div>
                            <span class="text-secondary f-s-12 d-block">Reserva Asociada</span>
                            <a href="<?= Ayudante::ruta('/reservas/' . $reserva->obtenerId()) ?>" class="f-s-16 f-w-700 text-primary text-decoration-none">
                                <i class="fa-solid fa-bookmark me-1"></i> <?= Ayudante::escapar($reserva->obtenerCodigo()) ?>
                            </a>
                            <div class="f-s-12 text-muted mt-1">
                                Check-in: <?= Ayudante::escapar($reserva->obtenerFechaEntrada()) ?> | Check-out: <?= Ayudante::escapar($reserva->obtenerFechaSalida()) ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light-secondary text-dark f-s-12 d-block mb-1">
                                <?= Ayudante::escapar($reserva->obtenerEstado()) ?>
                            </span>
                            <span class="f-s-12 text-secondary">Total: <?= $formatearDinero($reserva->obtenerTotal()) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light-secondary p-3 mb-3 f-s-13">
                        <i class="fa-solid fa-circle-info me-1"></i> No existe reserva hotelera vinculada a esta intención de pago.
                    </div>
                <?php endif; ?>

                <?php if ($folio !== null): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded border">
                        <div>
                            <span class="text-secondary f-s-12 d-block">Cuenta Folio Contable</span>
                            <strong class="text-dark f-s-14">
                                <i class="fa-solid fa-file-invoice me-1 text-success"></i> <?= Ayudante::escapar($folio->obtenerCodigo()) ?>
                            </strong>
                            <div class="f-s-12 text-muted">ID Folio: #<?= $folio->obtenerId() ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light-success text-success">Imputado Contablemente</span>
                            <?php if ($tx->obtenerPagoCuentaId()): ?>
                            <div class="f-s-11 text-muted mt-1">Pago Cuenta: #<?= (int) $tx->obtenerPagoCuentaId() ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-light rounded border text-muted f-s-13">
                        <i class="fa-solid fa-ban me-1 text-secondary"></i> Sin folio contable asignado (Transacción en cuarentena o pendiente).
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. Datos del Pagador y Medio de Pago (Sanitizado) -->
        <div class="card equal-card shadow-sm border-0 b-r-20 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="f-s-16 f-w-700 mb-0 text-dark">
                    <i class="fa-solid fa-user-shield text-primary me-2"></i> Datos del Pagador y Tarjeta (Zero-Trust)
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 f-s-13">
                    <div class="col-sm-6 col-12">
                        <span class="text-secondary d-block">Nombre del Pagador:</span>
                        <strong class="text-dark"><?= Ayudante::escapar($tx->obtenerPagadorNombre() ?: 'No especificado') ?></strong>
                    </div>
                    <div class="col-sm-6 col-12">
                        <span class="text-secondary d-block">Correo Electrónico:</span>
                        <strong class="text-dark"><?= Ayudante::escapar($tx->obtenerPagadorEmail() ?: 'No especificado') ?></strong>
                    </div>
                    <div class="col-sm-6 col-12">
                        <span class="text-secondary d-block">Teléfono:</span>
                        <span class="text-dark"><?= Ayudante::escapar($tx->obtenerPagadorTelefono() ?: 'No especificado') ?></span>
                    </div>
                    <div class="col-sm-6 col-12">
                        <span class="text-secondary d-block">Documento de Identidad:</span>
                        <span class="text-dark"><?= Ayudante::escapar($tx->obtenerPagadorNumeroDocumento() ?: 'No especificado') ?></span>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <div class="col-sm-4 col-6">
                        <span class="text-secondary d-block">Tarjeta / Marca:</span>
                        <strong class="text-dark">
                            <i class="fa-regular fa-credit-card me-1 text-primary"></i> <?= Ayudante::escapar($tx->obtenerTarjetaMarca() ?: 'N/A') ?>
                        </strong>
                    </div>
                    <div class="col-sm-4 col-6">
                        <span class="text-secondary d-block">Últimos 4 Dígitos:</span>
                        <strong class="text-dark">**** <?= Ayudante::escapar($tx->obtenerTarjetaUltimos4() ?: '****') ?></strong>
                    </div>
                    <div class="col-sm-4 col-12">
                        <span class="text-secondary d-block">Banco Emisor:</span>
                        <span class="text-dark"><?= Ayudante::escapar($tx->obtenerTarjetaBanco() ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Metadatos Sanitizados del Proveedor -->
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="f-s-16 f-w-700 mb-0 text-dark">
                    <i class="fa-solid fa-code text-primary me-2"></i> Metadatos de la Pasarela
                </h5>
                <span class="badge bg-light-secondary text-muted f-s-11">Sanitizado Zero-Trust</span>
            </div>
            <div class="card-body p-4">
                <pre class="bg-dark text-light p-3 rounded f-s-12 mb-0 overflow-auto" style="max-height: 250px; font-family: monospace;"><?= Ayudante::escapar(json_encode($metadatos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Trazabilidad / Timeline y Webhooks Recibidos -->
    <div class="col-lg-5 col-12">
        <!-- 1. Ciclo de Vida y Trazabilidad (Timeline Alina) -->
        <div class="card equal-card shadow-sm border-0 b-r-20 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="f-s-16 f-w-700 mb-0 text-dark">
                    <i class="fa-solid fa-timeline text-primary me-2"></i> Línea de Tiempo del Cobro
                </h5>
            </div>
            <div class="card-body p-4">
                <ul class="app-side-timeline shipping-timeline">
                    <?php foreach ($lineaTiempo as $index => $hito): ?>
                    <li class="side-timeline-section w-100 right-side complete-step mb-3">
                        <div class="side-timeline-icon">
                            <span class="bg-<?= Ayudante::escapar($hito['tipo']) ?> h-35 w-35 d-flex-center b-r-50">
                                <i class="<?= Ayudante::escapar($hito['icono']) ?> f-s-16 text-white"></i>
                            </span>
                        </div>
                        <div class="timeline-content p-0 ms-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="f-s-14 f-w-700 mb-1"><?= Ayudante::escapar($hito['titulo']) ?></h6>
                            </div>
                            <span class="text-muted f-s-11 d-block mb-1">
                                <i class="fa-regular fa-clock me-1"></i> <?= Ayudante::escapar($hito['fecha']) ?>
                            </span>
                            <p class="text-secondary f-s-12 mb-0">
                                <?= Ayudante::escapar($hito['descripcion']) ?>
                            </p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- 2. Webhooks y Notificaciones Externas Recibidas -->
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="f-s-16 f-w-700 mb-0 text-dark">
                    <i class="fa-solid fa-satellite-dish text-primary me-2"></i> Webhooks Recibidos
                </h5>
                <span class="badge bg-light-primary text-primary"><?= count($webhooks) ?> eventos</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 f-s-12">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="py-2 px-3">Evento / Fecha</th>
                                <th class="py-2 px-3 text-center">HTTP</th>
                                <th class="py-2 px-3 text-end">Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($webhooks)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-secondary">
                                    No se han recibido eventos de webhook para esta transacción.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($webhooks as $w): ?>
                                <tr>
                                    <td class="py-2 px-3">
                                        <strong class="text-dark d-block"><?= Ayudante::escapar($w->obtenerTipoEvento()) ?></strong>
                                        <span class="text-muted f-s-11"><?= Ayudante::escapar($w->obtenerCreadoEn()) ?></span>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <?php if ($w->obtenerCodigoHttpRespuesta() === 200): ?>
                                            <span class="badge bg-light-success text-success">200 OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-light-danger text-danger"><?= (int) $w->obtenerCodigoHttpRespuesta() ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-3 text-end">
                                        <button type="button" class="btn btn-outline-primary btn-xs btn-ver-webhook"
                                                data-id="<?= (int) $w->obtenerId() ?>"
                                                title="Inspeccionar Payload Sanitizado">
                                            <i class="fa-solid fa-code me-1"></i> Ver
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Observación Administrativa (Sin Reasignación) -->
<div class="modal fade" id="modal-observacion-seguimiento" tabindex="-1" aria-labelledby="modalObservacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg b-r-16">
            <div class="modal-header bg-light py-3 border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-dark text-dark p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-note-sticky f-s-18"></i>
                    </span>
                    <div>
                        <h5 class="modal-title f-s-16 f-w-700 mb-0" id="modalObservacionLabel">Seguimiento Administrativo</h5>
                        <span class="text-secondary f-s-12"><?= Ayudante::escapar($tx->obtenerCodigo()) ?></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="form-observacion-seguimiento" class="app-form" novalidate>
                <input type="hidden" name="transaccion_id" value="<?= $txId ?>">
                
                <div class="modal-body p-4">
                    <div class="alert alert-light-info border border-info p-2 mb-3 f-s-12">
                        <i class="fa-solid fa-circle-info text-info me-1"></i>
                        Esta acción registrará una nota de seguimiento en la bitácora de auditoría. <strong>No modifica inventario ni reactiva reservas expiradas</strong> conforme a la gobernanza C1/C2.
                    </div>

                    <div class="mb-3">
                        <label for="observacion-texto" class="form-label f-s-13 f-w-600">Observación / Acción Tomada <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="observacion-texto" name="observacion" rows="4"
                                  placeholder="Detalle las gestiones realizadas (contacto con el huésped, validación bancaria, etc.). Mínimo 10 caracteres..." required minlength="10"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-observacion">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Observación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inclusión de Componentes Modales y Offcanvas -->
<?php
include __DIR__ . '/componentes/modal_reembolso.php';
include __DIR__ . '/componentes/offcanvas_webhooks.php';
?>

<!-- Dependencias de Scripts Específicas del Módulo -->
<script src="<?= Ayudante::asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= Ayudante::asset('js/camargo-pagos.js') ?>"></script>
