<?php

declare(strict_types=1);

/**
 * Vista de Detalle 360° del Expediente de Reclamación — Camargo PMS (RECLAMACIONES-1).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var \CamargoPMS\Modelos\Reclamacion $reclamacion
 * @var array{codigo: string, color: string, etiqueta: string, dias_restantes_habiles: int, vencido: bool} $semaforo
 * @var array<string, bool> $permisos
 * @var string $csrf_token
 * @var string $titulo
 */

$snapConsumidor = $reclamacion->obtenerSnapshotConsumidor();
$snapProveedor = $reclamacion->obtenerSnapshotProveedor();
$actuaciones = $reclamacion->obtenerActuaciones();
$estado = $reclamacion->obtenerEstado();

$estaFinalizado = in_array($estado, [
    \CamargoPMS\Modelos\Reclamacion::ESTADO_ATENDIDO,
    \CamargoPMS\Modelos\Reclamacion::ESTADO_CONCLUIDO_POR_ACUERDO,
    \CamargoPMS\Modelos\Reclamacion::ESTADO_ANULADO,
], true);

$estaSuspendido = ($estado === \CamargoPMS\Modelos\Reclamacion::ESTADO_SUSPENDIDO_OFRECIMIENTO);
?>
<input type="hidden" id="csrf-token-global" value="<?= htmlspecialchars($csrf_token) ?>">
<input type="hidden" id="reclamacion-id" value="<?= htmlspecialchars((string) $reclamacion->obtenerId()) ?>">

<!-- Encabezado de la Ficha -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-file-shield f-s-22"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="card-title mb-0 f-s-18 f-w-700">Hoja N° <?= htmlspecialchars($reclamacion->obtenerCodigoHoja()) ?></h4>
                            <span class="badge bg-secondary f-s-11"><?= htmlspecialchars($reclamacion->obtenerCodigoInterno()) ?></span>
                            <span class="badge bg-<?= htmlspecialchars($semaforo['color'] ?? 'secondary') ?> f-s-11">
                                <?= htmlspecialchars($semaforo['etiqueta'] ?? '') ?>
                            </span>
                        </div>
                        <p class="text-secondary f-s-13 mb-0">
                            <?= htmlspecialchars($reclamacion->obtenerTipo()) ?> &bull; Sede: <strong><?= htmlspecialchars((string) ($snapProveedor['sede_nombre'] ?? 'Principal')) ?></strong> &bull; Registrado el <?= date('d/m/Y H:i', strtotime($reclamacion->obtenerFechaInterposicion())) ?>
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <a href="<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/pdf') ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF
                    </a>
                    <?php if (!empty($permisos['puede_gestionar'])): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-regenerar-pdf">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Regenerar PDF
                    </button>
                    <?php endif; ?>
                    <a href="<?= url_ruta('/reclamaciones') ?>" class="btn btn-light btn-sm border">
                        <i class="fa-solid fa-arrow-left me-1"></i> Volver al Directorio
                    </a>
                </div>
            </div>

            <!-- Barra de Acciones de Gestión Legal -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-secondary f-s-12 fw-bold me-2"><i class="fa-solid fa-gavel me-1"></i> Acciones del Expediente:</span>

                    <?php if (!empty($permisos['puede_actuar']) && !$estaFinalizado): ?>
                    <button type="button" class="btn btn-white btn-sm border shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-nota-interna">
                        <i class="fa-solid fa-note-sticky text-info me-1"></i> Añadir Nota Interna
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($permisos['puede_responder']) && !$estaFinalizado && !$estaSuspendido): ?>
                    <button type="button" class="btn btn-white btn-sm border shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-formular-ofrecimiento">
                        <i class="fa-solid fa-handshake-angle text-warning me-1"></i> Formular Ofrecimiento
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($permisos['puede_responder']) && $estaSuspendido): ?>
                    <button type="button" class="btn btn-white btn-sm border shadow-sm text-success" data-bs-toggle="modal" data-bs-target="#modal-responder-ofrecimiento">
                        <i class="fa-solid fa-reply me-1"></i> Registrar Respuesta a Ofrecimiento
                    </button>
                    <button type="button" class="btn btn-white btn-sm border shadow-sm text-danger" id="btn-expirar-ofrecimiento">
                        <i class="fa-solid fa-hourglass-end me-1"></i> Expirar Ofrecimiento (5 d.h.)
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($permisos['puede_responder']) && !$estaFinalizado): ?>
                    <button type="button" class="btn btn-success btn-sm text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-respuesta-formal">
                        <i class="fa-solid fa-envelope-circle-check me-1"></i> Emitir Respuesta Formal
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($permisos['puede_anular']) && !$estaFinalizado): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modal-anular-expediente">
                        <i class="fa-solid fa-ban me-1"></i> Anulación Supervisada
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Columna Izquierda: Información Estructurada del Expediente y Snapshots T0 -->
    <div class="col-lg-7">
        <!-- Tarjeta de Datos del Expediente -->
        <div class="card shadow-sm border-0 b-r-16 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="f-s-15 f-w-700 mb-0"><i class="fa-solid fa-circle-info text-primary me-2"></i> Snapshot Legal T0 (Inmutable)</h5>
            </div>
            <div class="card-body p-4">
                <!-- 1. Consumidor -->
                <h6 class="f-s-13 fw-bold text-secondary text-uppercase border-bottom pb-1 mb-3">1. Consumidor Reclamante</h6>
                <div class="row g-2 mb-3 f-s-13">
                    <div class="col-sm-4 text-muted">Nombre Completo:</div>
                    <div class="col-sm-8 fw-bold text-dark"><?= htmlspecialchars((string) ($snapConsumidor['nombre_completo'] ?? '-')) ?></div>
                    <div class="col-sm-4 text-muted">Documento:</div>
                    <div class="col-sm-8"><?= htmlspecialchars((string) ($snapConsumidor['tipo_documento'] ?? 'DOC')) ?>: <?= htmlspecialchars((string) ($snapConsumidor['numero_documento'] ?? '-')) ?></div>
                    <div class="col-sm-4 text-muted">Correo Electrónico:</div>
                    <div class="col-sm-8 text-primary"><?= htmlspecialchars((string) ($snapConsumidor['email'] ?? '-')) ?></div>
                    <div class="col-sm-4 text-muted">Teléfono / Celular:</div>
                    <div class="col-sm-8"><?= htmlspecialchars((string) ($snapConsumidor['telefono'] ?? '-')) ?></div>
                    <div class="col-sm-4 text-muted">Domicilio Declarado:</div>
                    <div class="col-sm-8"><?= htmlspecialchars((string) ($snapConsumidor['direccion'] ?? '-')) ?></div>

                    <?php if ($reclamacion->esMenorEdad() && !empty($snapConsumidor['apoderado'])): ?>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <span class="badge bg-warning text-dark mb-1">Menor de Edad</span>
                        <div class="text-secondary small">
                            <strong>Apoderado:</strong> <?= htmlspecialchars((string) ($snapConsumidor['apoderado']['nombre_completo'] ?? '-')) ?> &bull; 
                            <?= htmlspecialchars((string) ($snapConsumidor['apoderado']['tipo_documento'] ?? 'DOC')) ?>: <?= htmlspecialchars((string) ($snapConsumidor['apoderado']['numero_documento'] ?? '-')) ?> &bull;
                            Tel: <?= htmlspecialchars((string) ($snapConsumidor['apoderado']['telefono'] ?? '-')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 2. Bien Contratado -->
                <h6 class="f-s-13 fw-bold text-secondary text-uppercase border-bottom pb-1 mb-3">2. Bien Contratado</h6>
                <div class="row g-2 mb-3 f-s-13">
                    <div class="col-sm-4 text-muted">Naturaleza:</div>
                    <div class="col-sm-8"><strong><?= htmlspecialchars($reclamacion->obtenerTipoBien()) ?></strong></div>
                    <div class="col-sm-4 text-muted">Monto Reclamado:</div>
                    <div class="col-sm-8"><strong><?= htmlspecialchars($reclamacion->obtenerMoneda()) ?> <?= number_format((float)$reclamacion->obtenerMontoReclamado(), 2) ?></strong></div>
                    <div class="col-sm-4 text-muted">Descripción:</div>
                    <div class="col-sm-8"><?= nl2br(htmlspecialchars($reclamacion->obtenerDescripcionBien())) ?></div>
                </div>

                <!-- 3. Hechos y Pedido -->
                <h6 class="f-s-13 fw-bold text-secondary text-uppercase border-bottom pb-1 mb-3">3. Detalle de Reclamación y Pedido</h6>
                <div class="mb-3">
                    <span class="text-muted f-s-12 d-block mb-1 fw-bold">Detalle de los Hechos:</span>
                    <div class="p-3 bg-light rounded border f-s-13">
                        <?= nl2br(htmlspecialchars($reclamacion->obtenerDetalleReclamacion())) ?>
                    </div>
                </div>
                <div class="mb-3">
                    <span class="text-muted f-s-12 d-block mb-1 fw-bold">Pedido Concreto del Consumidor:</span>
                    <div class="p-3 bg-light rounded border f-s-13">
                        <?= nl2br(htmlspecialchars($reclamacion->obtenerPedidoConsumidor())) ?>
                    </div>
                </div>

                <!-- 4. Proveedor -->
                <h6 class="f-s-13 fw-bold text-secondary text-uppercase border-bottom pb-1 mb-3">4. Proveedor</h6>
                <div class="row g-2 f-s-13">
                    <div class="col-sm-4 text-muted">Razón Social:</div>
                    <div class="col-sm-8"><?= htmlspecialchars((string) ($snapProveedor['razon_social'] ?? 'Camargo Hostelería')) ?> (RUC: <?= htmlspecialchars((string) ($snapProveedor['ruc'] ?? '-')) ?>)</div>
                    <div class="col-sm-4 text-muted">Sede Comercial:</div>
                    <div class="col-sm-8"><?= htmlspecialchars((string) ($snapProveedor['sede_nombre'] ?? 'Principal')) ?> &bull; <?= htmlspecialchars((string) ($snapProveedor['sede_direccion'] ?? '')) ?></div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Plazos Regulatorios -->
        <div class="card shadow-sm border-0 b-r-16 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="f-s-15 f-w-700 mb-0"><i class="fa-solid fa-stopwatch text-warning me-2"></i> Control de Plazos Legales (Ley 31435)</h5>
            </div>
            <div class="card-body p-4 f-s-13">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-secondary f-s-11 d-block">Fecha de Interposición:</span>
                            <strong><?= date('d/m/Y H:i', strtotime($reclamacion->obtenerFechaInterposicion())) ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-secondary f-s-11 d-block">Fecha Límite Legal (15 d.h.):</span>
                            <strong class="text-danger"><?= date('d/m/Y', strtotime($reclamacion->obtenerFechaLimiteLegal())) ?></strong>
                        </div>
                    </div>
                    <?php if ($reclamacion->obtenerFechaSuspension() !== null): ?>
                    <div class="col-sm-6">
                        <div class="p-2 border border-warning rounded bg-warning bg-opacity-10">
                            <span class="text-secondary f-s-11 d-block">Fecha Suspensión por Ofrecimiento:</span>
                            <strong class="text-warning"><?= date('d/m/Y', strtotime($reclamacion->obtenerFechaSuspension())) ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 border border-warning rounded bg-warning bg-opacity-10">
                            <span class="text-secondary f-s-11 d-block">Límite Respuesta Ofrecimiento (5 d.h.):</span>
                            <strong class="text-danger"><?= date('d/m/Y', strtotime($reclamacion->obtenerFechaLimiteOfrecimiento() ?? '')) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Bitácora Cronológica de Actuaciones (Append-Only) -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 b-r-16 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="f-s-15 f-w-700 mb-0"><i class="fa-solid fa-timeline text-primary me-2"></i> Actuaciones y Respuestas</h5>
                <span class="badge bg-light text-dark border"><?= count($actuaciones) ?> eventos</span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($actuaciones)): ?>
                    <p class="text-muted text-center py-4">No se registran actuaciones adicionales.</p>
                <?php else: ?>
                    <div class="timeline-container">
                        <?php foreach ($actuaciones as $act): ?>
                            <div class="card mb-3 border-0 bg-light-subtle rounded-3 p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-primary-subtle text-primary f-s-11">
                                        <?= htmlspecialchars($act->obtenerTipoActuacion()) ?>
                                    </span>
                                    <span class="text-muted f-s-11">
                                        <?= date('d/m/Y H:i', strtotime($act->obtenerCreadoEn() ?? '')) ?>
                                    </span>
                                </div>
                                <div class="f-s-13 text-dark mt-1">
                                    <?= nl2br(htmlspecialchars($act->obtenerDescripcion())) ?>
                                </div>
                                <?php if ($act->obtenerMedioNotificacion() !== null): ?>
                                <div class="text-secondary f-s-11 mt-2 pt-2 border-top">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Medio: <strong><?= htmlspecialchars($act->obtenerMedioNotificacion()) ?></strong>
                                    <?php if ($act->obtenerDestinatarioNotificacion() !== null): ?>
                                    &bull; <?= htmlspecialchars($act->obtenerDestinatarioNotificacion()) ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="text-muted f-s-10 mt-1">
                                    Registrado por: <strong><?= htmlspecialchars($act->obtenerNombreActor() ?? 'Sistema') ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Añadir Nota Interna -->
<div class="modal fade" id="modal-nota-interna" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 fw-bold"><i class="fa-solid fa-note-sticky text-info me-2"></i> Añadir Nota Interna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-nota-interna">
                <div class="modal-body p-4">
                    <label class="form-label required">Descripción / Observación</label>
                    <textarea class="form-control" name="descripcion" rows="4" placeholder="Ingrese nota interna del expediente..." required></textarea>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info btn-sm text-white" id="btn-guardar-nota">Guardar Nota</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Formular Ofrecimiento -->
<div class="modal fade" id="modal-formular-ofrecimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 fw-bold text-warning"><i class="fa-solid fa-handshake-angle me-2"></i> Formular Ofrecimiento de Solución</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-ofrecimiento">
                <div class="modal-body p-4">
                    <div class="alert alert-warning f-s-12 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Conforme al D.S. 101-2022-PCM, al formular esta propuesta el cómputo de 15 días hábiles se suspenderá por un plazo máximo de <strong>cinco (5) días hábiles</strong> a la espera de la respuesta del consumidor.
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Detalle de la Propuesta u Ofrecimiento de Solución</label>
                        <textarea class="form-control" name="propuesta" rows="4" placeholder="Detalle la solución concreta propuesta al consumidor..." required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Medio de Notificación</label>
                            <select class="form-select form-select-sm" name="medio_notificacion" required>
                                <option value="CORREO_ELECTRONICO" selected>Correo Electrónico</option>
                                <option value="CARTA_NOTARIAL">Carta Notarial</option>
                                <option value="FISICO_RECEPCION">Recepción Física</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Destinatario / Dirección / Correo</label>
                            <input type="text" class="form-control form-control-sm" name="destinatario" value="<?= htmlspecialchars((string) ($snapConsumidor['email'] ?? '')) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold" id="btn-guardar-ofrecimiento">Formular y Suspender Plazo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Responder Ofrecimiento -->
<div class="modal fade" id="modal-responder-ofrecimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 fw-bold"><i class="fa-solid fa-reply text-success me-2"></i> Respuesta a Ofrecimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-responder-ofrecimiento">
                <div class="modal-body p-4">
                    <label class="form-label required">Pronunciamiento del Consumidor</label>
                    <div class="d-flex gap-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="aceptado" id="resp-aceptado" value="1" checked>
                            <label class="form-check-label fw-bold text-success" for="resp-aceptado">
                                <i class="fa-solid fa-circle-check me-1"></i> ACEPTADO (Concluye por acuerdo)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="aceptado" id="resp-rechazado" value="0">
                            <label class="form-check-label fw-bold text-danger" for="resp-rechazado">
                                <i class="fa-solid fa-circle-xmark me-1"></i> RECHAZADO (Reanuda plazo legal)
                            </label>
                        </div>
                    </div>
                    <label class="form-label">Sustento o Declaración del Consumidor</label>
                    <textarea class="form-control" name="sustento" rows="3" placeholder="Observaciones o medio por el cual expresó aceptación/rechazo..."></textarea>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-resp-ofrecimiento">Guardar Respuesta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Emitir Respuesta Formal -->
<div class="modal fade" id="modal-respuesta-formal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 fw-bold text-success"><i class="fa-solid fa-envelope-circle-check me-2"></i> Emitir Respuesta Formal al Reclamo / Queja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-respuesta-formal">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label required">Contenido Fundamentado de la Respuesta Oficial</label>
                        <textarea class="form-control" name="contenido_respuesta" rows="6" placeholder="Redacte la respuesta legal motivada al consumidor..." required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Medio Probatorio de Notificación</label>
                            <select class="form-select form-select-sm" name="medio_notificacion" required>
                                <option value="CORREO_ELECTRONICO" selected>Correo Electrónico</option>
                                <option value="CARTA_NOTARIAL">Carta Notarial</option>
                                <option value="FISICO_RECEPCION">Físico en Recepción</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Destinatario / Correo Notificado</label>
                            <input type="text" class="form-control form-control-sm" name="destinatario" value="<?= htmlspecialchars((string) ($snapConsumidor['email'] ?? '')) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm text-white" id="btn-guardar-respuesta-formal">Concluir como ATENDIDO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 5: Anulación Supervisada -->
<div class="modal fade" id="modal-anular-expediente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 fw-bold text-danger"><i class="fa-solid fa-ban me-2"></i> Anulación Supervisada de Expediente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-anular">
                <div class="modal-body p-4">
                    <div class="alert alert-danger f-s-12 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> La anulación supervisada es irreversible y solo procede por duplicidad técnica o error material comprobado. Se asentará auditoría formal.
                    </div>
                    <label class="form-label required">Motivo Fundamentado de la Anulación (Mínimo 10 caracteres)</label>
                    <textarea class="form-control" name="motivo" rows="4" placeholder="Explique la causa técnica o legal de la anulación..." required></textarea>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-guardar-anular">Confirmar Anulación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.getElementById('csrf-token-global').value;
    var recId = document.getElementById('reclamacion-id').value;

    function manejarRespuesta(promise, btn, modalId) {
        btn.disabled = true;
        promise
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (!data.exito) {
                    alert('Error: ' + (data.mensaje || 'Operación fallida.'));
                    return;
                }
                if (modalId) {
                    var m = bootstrap.Modal.getInstance(document.getElementById(modalId));
                    if (m) m.hide();
                }
                alert(data.mensaje || 'Operación completada con éxito.');
                window.location.reload();
            })
            .catch(function(err) {
                btn.disabled = false;
                alert('Fallo de comunicación con el servidor.');
            });
    }

    // 1. Nota interna
    var fNota = document.getElementById('form-nota-interna');
    if (fNota) {
        fNota.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-nota');
            var payload = { descripcion: fNota.querySelector('[name="descripcion"]').value };
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/notas') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            });
            manejarRespuesta(p, btn, 'modal-nota-interna');
        });
    }

    // 2. Ofrecimiento
    var fOfrec = document.getElementById('form-ofrecimiento');
    if (fOfrec) {
        fOfrec.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-ofrecimiento');
            var payload = {
                propuesta: fOfrec.querySelector('[name="propuesta"]').value,
                medio_notificacion: fOfrec.querySelector('[name="medio_notificacion"]').value,
                destinatario: fOfrec.querySelector('[name="destinatario"]').value
            };
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/ofrecimiento') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            });
            manejarRespuesta(p, btn, 'modal-formular-ofrecimiento');
        });
    }

    // 3. Responder Ofrecimiento
    var fRespOfrec = document.getElementById('form-responder-ofrecimiento');
    if (fRespOfrec) {
        fRespOfrec.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-resp-ofrecimiento');
            var aceptado = fRespOfrec.querySelector('[name="aceptado"]:checked').value === '1';
            var sustento = fRespOfrec.querySelector('[name="sustento"]').value;
            var payload = { aceptado: aceptado, sustento: sustento };
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/ofrecimiento/respuesta') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            });
            manejarRespuesta(p, btn, 'modal-responder-ofrecimiento');
        });
    }

    // 3.1 Expirar Ofrecimiento
    var btnExp = document.getElementById('btn-expirar-ofrecimiento');
    if (btnExp) {
        btnExp.addEventListener('click', function() {
            if (!confirm('¿Confirma que expiró el plazo de 5 días hábiles sin pronunciamiento del consumidor?')) return;
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/ofrecimiento/expirar') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({})
            });
            manejarRespuesta(p, btnExp, null);
        });
    }

    // 4. Respuesta Formal
    var fRespFormal = document.getElementById('form-respuesta-formal');
    if (fRespFormal) {
        fRespFormal.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-respuesta-formal');
            var payload = {
                contenido_respuesta: fRespFormal.querySelector('[name="contenido_respuesta"]').value,
                medio_notificacion: fRespFormal.querySelector('[name="medio_notificacion"]').value,
                destinatario: fRespFormal.querySelector('[name="destinatario"]').value
            };
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/respuesta') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            });
            manejarRespuesta(p, btn, 'modal-respuesta-formal');
        });
    }

    // 5. Anulación
    var fAnular = document.getElementById('form-anular');
    if (fAnular) {
        fAnular.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-anular');
            var payload = { motivo: fAnular.querySelector('[name="motivo"]').value };
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/anular') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            });
            manejarRespuesta(p, btn, 'modal-anular-expediente');
        });
    }

    // 6. Regenerar PDF
    var btnRegen = document.getElementById('btn-regenerar-pdf');
    if (btnRegen) {
        btnRegen.addEventListener('click', function() {
            var p = fetch('<?= url_ruta('/reclamaciones/' . $reclamacion->obtenerId() . '/regenerar-pdf') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({})
            });
            manejarRespuesta(p, btnRegen, null);
        });
    }
});
</script>
