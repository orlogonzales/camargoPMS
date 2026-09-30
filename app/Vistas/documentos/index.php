<?php

declare(strict_types=1);

/**
 * Vista principal del Motor Documental, Plantillas y Generación PDF — Camargo PMS (DOCUMENTOS-1 / D-079).
 *
 * Principios vinculantes:
 * - PLANTILLA != VERSIÓN != SNAPSHOT != DOCUMENTO EMITIDO != PDF BINARIO.
 * - Snapshots inmutables congelados.
 * - Hash SHA-256 verificado en cada descarga; cero regeneración silenciosa.
 * - Formulario con geometría nativa Alina (app-form app-icon-form, border-radius 20px, select2 42px).
 * - D-071: Font Awesome 6.3.0 exclusivo, variantes suaves de badge Alina (bg-light-*).
 *
 * @var array<string, int> $kpis
 * @var array<int, mixed> $plantillas
 * @var array<int, mixed> $documentos
 * @var array<int, string> $permisos
 * @var string $csrf_token
 * @var \CamargoPMS\Modelos\Usuario|null $usuario
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Documentos -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-file-shield f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Motor Documental y Generación PDF</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Plantillas versionadas, compilación determinista, contratos de arrendamiento y archivo inmutable SHA-256.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (in_array('documentos.emitir', $permisos, true)): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-emitir">
                        <i class="fa-solid fa-file-contract me-1"></i> Emitir Contrato
                    </button>
                    <?php endif; ?>
                    <?php if (in_array('documentos.plantillas.gestionar', $permisos, true)): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-modal-version">
                        <i class="fa-solid fa-code-branch me-1"></i> Nueva Versión
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btn-abrir-modal-plantilla">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Plantilla
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Documentos -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Documentos Emitidos</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-total-emitidos"><?= (int) ($kpis['total_emitidos'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted">Folios oficiales registrados</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-file-lines f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Plantillas Activas</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-total-plantillas"><?= (int) ($kpis['total_plantillas'] ?? 0) ?></h3>
                                    <span class="f-s-11 text-muted"><?= (int) ($kpis['total_versiones'] ?? 0) ?> versiones publicadas</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-layer-group f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Integridad Criptográfica</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-info mt-1">100%</h3>
                                    <span class="f-s-11 text-muted">Hash SHA-256 auditado</span>
                                </div>
                                <div class="bg-light-info text-info p-3 b-r-8">
                                    <i class="fa-solid fa-fingerprint f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Incidencias de Integridad</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 <?= ((int) ($kpis['incidencias_abiertas'] ?? 0) > 0) ? 'text-danger' : 'text-secondary' ?> mt-1" id="kpi-total-incidencias">
                                        <?= (int) ($kpis['incidencias_abiertas'] ?? 0) ?>
                                    </h3>
                                    <span class="f-s-11 text-muted">Anomalías pendientes</span>
                                </div>
                                <div class="bg-light-secondary text-secondary p-3 b-r-8">
                                    <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas del Módulo -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom px-3 pt-2 border-bottom-0" id="tabs-documentos" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-s-14 f-w-600 py-3" id="tab-emitidos-btn" data-bs-toggle="tab" data-bs-target="#tab-emitidos" type="button" role="tab">
                            <i class="fa-solid fa-file-signature me-2"></i> Documentos Emitidos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-plantillas-btn" data-bs-toggle="tab" data-bs-target="#tab-plantillas" type="button" role="tab">
                            <i class="fa-solid fa-file-code me-2"></i> Plantillas y Versiones
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-14 f-w-600 py-3" id="tab-incidencias-btn" data-bs-toggle="tab" data-bs-target="#tab-incidencias" type="button" role="tab">
                            <i class="fa-solid fa-shield-halved me-2"></i> Auditoría de Integridad
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="tabs-documentos-contenido">

                    <!-- PESTAÑA 1: DOCUMENTOS EMITIDOS -->
                    <div class="tab-pane fade show active" id="tab-emitidos" role="tabpanel">
                        <div class="row mb-3 g-2 align-items-center">
                            <div class="col-md-4 col-12">
                                <input type="text" class="form-control form-control-sm" id="filtro-docs-termino" placeholder="Buscar por folio oficial o emisor..." style="border-radius: 20px;">
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select form-select-sm" id="filtro-docs-origen" style="border-radius: 20px;">
                                    <option value="">Todos los orígenes</option>
                                    <option value="ARRENDAMIENTO">Arrendamiento Inmobiliario</option>
                                    <option value="RESERVA">Reserva Hotelera</option>
                                    <option value="PAGO">Comprobante de Pago</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <select class="form-select form-select-sm" id="filtro-docs-estado" style="border-radius: 20px;">
                                    <option value="">Todos los estados</option>
                                    <option value="VALIDO">Válido</option>
                                    <option value="ANULADO">Anulado</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-12 text-md-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-docs">
                                    <i class="fa-solid fa-rotate me-1"></i> Actualizar
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-documentos">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">Folio Oficial</th>
                                        <th class="f-s-12 text-uppercase">Documento / Versión</th>
                                        <th class="f-s-12 text-uppercase">Origen / Referencia</th>
                                        <th class="f-s-12 text-uppercase">Fecha Emisión</th>
                                        <th class="f-s-12 text-uppercase">Integridad SHA-256</th>
                                        <th class="f-s-12 text-uppercase text-center">Estado</th>
                                        <th class="f-s-12 text-uppercase text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando documentos emitidos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: PLANTILLAS Y VERSIONES -->
                    <div class="tab-pane fade" id="tab-plantillas" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 f-s-15 f-w-700">Catálogo de Plantillas Documentales</h5>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-plantillas">
                                <i class="fa-solid fa-rotate me-1"></i> Actualizar
                            </button>
                        </div>

                        <div class="row g-3" id="contenedor-plantillas-tarjetas">
                            <!-- Inyectado por JS -->
                        </div>
                    </div>

                    <!-- PESTAÑA 3: AUDITORÍA DE INTEGRIDAD -->
                    <div class="tab-pane fade" id="tab-incidencias" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0 f-s-15 f-w-700">Bitácora de Auditoría e Incidencias</h5>
                                <p class="text-secondary f-s-12 mb-0">Trazabilidad de verificaciones SHA-256, discrepancias físicas y eventos de regeneración.</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-incidencias">
                                <i class="fa-solid fa-rotate me-1"></i> Actualizar
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-incidencias">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 text-uppercase">ID</th>
                                        <th class="f-s-12 text-uppercase">Doc ID</th>
                                        <th class="f-s-12 text-uppercase">Tipo de Incidencia</th>
                                        <th class="f-s-12 text-uppercase">Descripción Técnica</th>
                                        <th class="f-s-12 text-uppercase">Detectado En</th>
                                        <th class="f-s-12 text-uppercase text-center">Estado</th>
                                        <th class="f-s-12 text-uppercase">Resolución</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Cargando bitácora de integridad...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES DEL DOMINIO DOCUMENTAL (ALINA D-075)                              -->
<!-- ========================================================================= -->

<!-- Modal 1: Emitir Contrato de Arrendamiento -->
<div class="modal fade" id="modal-emitir-contrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-20">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-file-contract text-primary me-2"></i> Emitir Contrato de Arrendamiento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-emitir-contrato" class="app-form">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">ID del Contrato de Arrendamiento <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" id="emitir-arrendamiento-id" placeholder="Ej: 1" style="border-radius: 20px;" required>
                        <div class="form-text f-s-11">Debe corresponder a un arrendamiento existente en el PMS.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Versión de Plantilla a Compilar</label>
                        <select class="form-select" id="emitir-plantilla-version-id" style="border-radius: 20px;">
                            <option value="">Usar versión activa oficial (Recomendado)</option>
                        </select>
                        <div class="form-text f-s-11">Por defecto compila la versión canónica activa en la base de datos.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="emitir-es-borrador">
                        <label class="form-check-label f-s-13 f-w-600" for="emitir-es-borrador">
                            Modo Borrador (Marca de agua 'BORRADOR NO VÁLIDO')
                        </label>
                        <div class="form-text f-s-11 text-muted">
                            Si está marcado, se previsualiza el PDF sin consumir folio oficial ni registrar documento emitido.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-confirmar-emision">
                        <i class="fa-solid fa-stamp me-1"></i> Generar Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Crear Nueva Versión de Plantilla -->
<div class="modal fade" id="modal-nueva-version" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-20">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-code-branch text-primary me-2"></i> Crear Nueva Versión de Plantilla
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-nueva-version" class="app-form">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Plantilla Base <span class="text-danger">*</span></label>
                            <select class="form-select" id="version-plantilla-id" style="border-radius: 20px;" required>
                                <?php foreach ($plantillas as $p): ?>
                                    <option value="<?= $p->obtenerId() ?>"><?= e($p->obtenerNombre()) ?> (<?= e($p->obtenerCodigo()) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600">Título Formal del Documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="version-titulo-doc" placeholder="Ej: CONTRATO DE ARRENDAMIENTO DE VIVIENDA URBANA" style="border-radius: 20px;" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Notas del Cambio / Motivo de la Versión</label>
                            <input type="text" class="form-control" id="version-notas" placeholder="Ej: Ajuste de cláusula quinta conforme a nueva regulación" style="border-radius: 20px;">
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Cuerpo HTML (Variables admitidas: <code>{{ARRENDADOR_NOMBRE}}</code>, <code>{{ARRENDATARIO_NOMBRE}}</code>, <code>{{INMUEBLE_DIRECCION}}</code>, <code>{{CANON_LETRAS}}</code>, <code>{{BLOQUE_DOTACION_FISICA}}</code>, etc.) <span class="text-danger">*</span></label>
                            <textarea class="form-control font-monospace f-s-12" id="version-cuerpo-html" rows="12" style="border-radius: 12px;" required></textarea>
                            <div class="form-text f-s-11 text-muted">
                                Las variables son validadas estrictamente contra el catálogo tipado. Cualquier etiqueta insegura (&lt;script&gt;, &lt;iframe&gt;, etc.) será rechazada.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600">Estilos CSS Específicos (Opcional)</label>
                            <textarea class="form-control font-monospace f-s-12" id="version-estilos-css" rows="3" style="border-radius: 12px;" placeholder=".clausula-especial { font-weight: bold; }"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="version-activar-inmediata">
                                <label class="form-check-label f-s-13 f-w-600" for="version-activar-inmediata">
                                    Activar inmediatamente como versión oficial
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-version">
                        <i class="fa-solid fa-save me-1"></i> Publicar Versión
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Anular Documento -->
<div class="modal fade" id="modal-anular-documento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow b-r-20">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title f-s-16 f-w-700 text-danger">
                    <i class="fa-solid fa-ban me-2"></i> Anulación Formal de Documento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-anular-documento" class="app-form">
                <input type="hidden" id="anular-doc-id" value="">
                <div class="modal-body p-4">
                    <p class="f-s-13 text-secondary mb-3">
                        Está a punto de anular el documento oficial con folio <strong id="anular-doc-folio" class="text-dark"></strong>. Esta acción preserva el histórico pero revoca la validez legal del documento en el PMS.
                    </p>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600">Motivo Obligatorio de Anulación <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="anular-doc-motivo" rows="3" placeholder="Explique la causa formal de la anulación..." style="border-radius: 12px;" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-anulacion">
                        <i class="fa-solid fa-ban me-1"></i> Confirmar Anulación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Visor Técnico / Previsualización -->
<div class="modal fade" id="modal-visor-documento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow b-r-20">
            <div class="modal-header bg-light py-3 border-bottom d-flex justify-content-between">
                <div>
                    <h5 class="modal-title f-s-16 f-w-700" id="visor-titulo">Visor de Documento</h5>
                    <span class="f-s-12 text-muted" id="visor-subtitulo"></span>
                </div>
                <div class="d-flex gap-2">
                    <a href="#" id="visor-btn-descarga-directa" class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="fa-solid fa-download me-1"></i> Descargar PDF
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="height: 70vh;">
                <iframe id="visor-iframe-pdf" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
            <div class="modal-footer bg-light py-2 border-top d-flex justify-content-between">
                <div class="f-s-11 text-muted" id="visor-metadatos-sha256">
                    <!-- SHA-256 inyectado -->
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-documentos.js') ?>"></script>
