<?php

declare(strict_types=1);

/**
 * Offcanvas Alina para Inspección de Payload de Webhooks (PAGOS-1D).
 *
 * Principios vinculantes:
 * - OFFCANVAS DERECHO: offcanvas-end conforme a Alina.
 * - SANITIZACIÓN: Payloads presentados con redacción de claves y secretos.
 * - VISOR PRE/CODE: Visualización estructurada JSON con resaltado o bloque preformateado.
 * - CERO JQUERY CRUD: Fetch nativo disparado desde camargo-pagos.js.
 */
?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvas-webhook-payload" aria-labelledby="offcanvasWebhookLabel" style="min-width: 480px; max-width: 90vw;">
    <div class="offcanvas-header bg-light border-bottom py-3">
        <div class="d-flex align-items-center">
            <span class="bg-light-primary text-primary p-2 b-r-8 me-2 d-flex-center">
                <i class="fa-solid fa-code f-s-18"></i>
            </span>
            <div>
                <h5 class="offcanvas-title f-s-16 f-w-700 mb-0" id="offcanvasWebhookLabel">Detalle de Webhook Externo</h5>
                <span class="text-secondary f-s-12" id="webhook-offcanvas-subtitulo">Evento #—</span>
            </div>
        </div>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body p-4 position-relative">
        <!-- Indicador de carga -->
        <div id="webhook-loading" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="text-secondary f-s-13 mt-2">Obteniendo payload sanitizado del webhook...</p>
        </div>

        <!-- Contenido del webhook -->
        <div id="webhook-contenido">
            <!-- Metadatos de la Entrega HTTP -->
            <div class="card border mb-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="f-w-600 f-s-13 text-dark"><i class="fa-solid fa-circle-info me-1 text-primary"></i> Metadatos de Entrega</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 f-s-13">
                        <div class="col-6">
                            <span class="text-secondary d-block">Proveedor:</span>
                            <span id="webhook-meta-proveedor" class="badge bg-light-primary text-primary f-s-12">CULQI</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block">Tipo de Evento:</span>
                            <strong id="webhook-meta-tipo" class="text-dark">—</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block">ID Evento Externo:</span>
                            <code id="webhook-meta-evento-id" class="text-primary f-s-12">—</code>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block">Respuesta HTTP Retornada:</span>
                            <span id="webhook-meta-http-code" class="badge bg-light-success text-success f-s-12">200 OK</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block">Fecha Recepción:</span>
                            <span id="webhook-meta-recibido" class="text-dark">—</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block">IP de Origen:</span>
                            <span id="webhook-meta-ip" class="text-dark">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error de procesamiento si ocurrió -->
            <div id="webhook-contenedor-error" class="alert alert-light-danger border border-danger p-3 mb-3 d-none">
                <div class="d-flex align-items-start">
                    <i class="fa-solid fa-triangle-exclamation text-danger mt-1 me-2"></i>
                    <div>
                        <strong class="f-s-13 text-danger d-block">Error de Procesamiento Registrado:</strong>
                        <span id="webhook-error-texto" class="f-s-12 text-secondary"></span>
                    </div>
                </div>
            </div>

            <!-- Visor de Payload JSON -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label f-s-13 f-w-600 mb-0">Payload Crudo (Sanitizado)</label>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-copiar-payload">
                        <i class="fa-regular fa-copy me-1"></i> Copiar JSON
                    </button>
                </div>
                <div class="position-relative">
                    <pre id="webhook-payload-json" class="bg-dark text-light p-3 rounded f-s-12 mb-0 overflow-auto" style="max-height: 400px; font-family: 'Consolas', 'Monaco', monospace; line-height: 1.4;"></pre>
                </div>
                <div class="d-flex align-items-center mt-2 text-muted f-s-11">
                    <i class="fa-solid fa-shield-halved text-success me-1"></i>
                    <span>Cero-Trust: credenciales, tokens, CVV y claves privadas han sido enmascarados o excluidos de la vista.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas-footer bg-light p-3 border-top d-flex justify-content-end">
        <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="offcanvas">
            <i class="fa-solid fa-xmark me-1"></i> Cerrar
        </button>
    </div>
</div>
