<?php

declare(strict_types=1);

/**
 * Modal Centrado Alina para Solicitud de Reembolso de Pasarela (PAGOS-1D).
 *
 * Principios vinculantes:
 * - DIÁLOGO CENTRADO: modal-dialog-centered conforme a D-097.
 * - VALIDACIÓN FORMULARIO: PristineJS / CamargoForms.
 * - CONFIRMACIÓN: SweetAlert2 con paleta corporativa Alina antes de emitir la llamada financiera.
 * - CERO JQUERY CRUD: Fetch nativo.
 */
?>
<div class="modal fade" id="modal-reembolso" tabindex="-1" aria-labelledby="modalReembolsoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg b-r-16">
            <div class="modal-header bg-light py-3 border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-danger text-danger p-2 b-r-8 me-2 d-flex-center">
                        <i class="fa-solid fa-arrow-rotate-left f-s-18"></i>
                    </span>
                    <div>
                        <h5 class="modal-title f-s-16 f-w-700 mb-0" id="modalReembolsoLabel">Solicitar Reembolso de Pasarela</h5>
                        <span class="text-secondary f-s-12" id="reembolso-subtitulo">Transacción</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="form-reembolso-pasarela" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="reembolso-transaccion-id" name="transaccion_id" value="">
                
                <div class="modal-body p-4">
                    <!-- Resumen Financiero de la Transacción -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="row g-2 f-s-13">
                            <div class="col-6">
                                <span class="text-secondary d-block">Monto Cobrado:</span>
                                <strong class="text-dark f-s-14" id="reembolso-info-cobrado">S/ 0.00</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-secondary d-block">Monto Ya Reembolsado:</span>
                                <strong class="text-danger f-s-14" id="reembolso-info-reembolsado">S/ 0.00</strong>
                            </div>
                            <div class="col-12 pt-2 border-top">
                                <span class="text-secondary d-block">Saldo Máximo Reembolsable:</span>
                                <strong class="text-success f-s-16" id="reembolso-info-disponible">S/ 0.00</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Tipo de Reembolso -->
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Tipo de Reembolso <span class="text-danger">*</span></label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_reembolso" id="reembolso-tipo-total" value="TOTAL" checked>
                                <label class="form-check-label f-s-13" for="reembolso-tipo-total">
                                    Reembolso Total (Saldo Completo)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_reembolso" id="reembolso-tipo-parcial" value="PARCIAL">
                                <label class="form-check-label f-s-13" for="reembolso-tipo-parcial">
                                    Reembolso Parcial
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Campo Monto Parcial -->
                    <div class="mb-3 d-none" id="contenedor-monto-parcial">
                        <label for="reembolso-monto" class="form-label f-s-13 f-w-600">Monto Parcial a Reembolsar (PEN) <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-money-bill-wave position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                            <input type="number" step="0.01" min="0.01" class="form-control ps-5" id="reembolso-monto" name="monto" placeholder="0.00">
                        </div>
                        <div class="form-text f-s-12">El monto no puede superar el saldo reembolsable disponible.</div>
                    </div>

                    <!-- Motivo de Devolución -->
                    <div class="mb-2">
                        <label for="reembolso-motivo" class="form-label f-s-13 f-w-600">Motivo de Devolución <span class="text-danger">*</span></label>
                        <div class="icon-control icon-textarea position-relative">
                            <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5 pt-3" id="reembolso-motivo" name="motivo" rows="3" 
                                      placeholder="Explique el motivo justificado de la devolución (mínimo 10 caracteres)..." required minlength="10"></textarea>
                        </div>
                        <div class="form-text f-s-12 text-secondary">Esta justificación quedará registrada en auditoría y enviada a la pasarela externa.</div>
                    </div>

                    <div class="alert alert-light-warning border border-warning p-2 mt-3 mb-0 f-s-12">
                        <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>
                        <strong>Aviso importante:</strong> La solicitud de reembolso enviará una instrucción a la pasarela externa. Esta acción es de impacto financiero directo.
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-reembolso">
                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Procesar Reembolso
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
