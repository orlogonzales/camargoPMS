<?php

declare(strict_types=1);

/**
 * Vista de Libro de Guardia y Bitácora Operacional — Camargo PMS (BITÁCORA-1 / D-089)
 *
 * Principios Vinculantes:
 * - BITÁCORA ≠ AUDITORÍA TÉCNICA (D-061) ≠ TURNO CAJA ≠ MANTENIMIENTO ≠ HOUSEKEEPING.
 * - Inmutabilidad del relato original de guardia.
 * - Trazabilidad append-only en comentarios y enmiendas.
 * - ANULAR ≠ DELETE: anulación supervisada y justificada sin borrado físico.
 *
 * @var array<string, mixed>|null $usuario_actual
 * @var string $csrf_token
 * @var array<array<string, mixed>> $propiedades
 * @var array<array<string, mixed>> $unidades
 * @var array<string, int> $metricas_iniciales
 * @var array<string> $tipos
 * @var array<string> $prioridades
 * @var array<string> $turnos
 * @var array<string> $estados
 */
?>
<div id="app-bitacora">
    <input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

    <!-- Encabezado del Módulo -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                    <div class="d-flex align-items-center">
                        <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                            <i class="fa-solid fa-book-bookmark f-s-22"></i>
                        </span>
                        <div>
                            <h4 class="card-title mb-0 f-s-18 f-w-700">Libro de Guardia y Bitácora Operacional</h4>
                            <p class="text-secondary f-s-13 mb-0">
                                Registro inmutable de relevos, novedades de turno, consignas e incidencias (D-089).
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refrescar-feed" title="Actualizar bitácora">
                            <i class="fa-solid fa-rotate me-1"></i> Actualizar
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-modal-crear">
                            <i class="fa-solid fa-plus me-1"></i> Nueva Entrada de Guardia
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Métricas Operacionales -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-secondary f-s-12 f-w-600 text-uppercase">Novedades Hoy</span>
                            <h3 class="mb-0 f-w-700 text-primary mt-1" id="metrica-total-hoy">
                                <?= e((string) ($metricas_iniciales['total_hoy'] ?? 0)) ?>
                            </h3>
                            <span class="f-s-11 text-muted">Total general: <?= e((string) ($metricas_iniciales['total_general'] ?? 0)) ?> registros</span>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 b-r-10 d-flex-center">
                            <i class="fa-solid fa-clipboard-list f-s-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-secondary f-s-12 f-w-600 text-uppercase">Pendientes / En Proceso</span>
                            <h3 class="mb-0 f-w-700 text-warning mt-1" id="metrica-pendientes">
                                <?= e((string) (($metricas_iniciales['pendientes'] ?? 0) + ($metricas_iniciales['en_proceso'] ?? 0))) ?>
                            </h3>
                            <span class="f-s-11 text-muted">
                                <?= e((string) ($metricas_iniciales['pendientes'] ?? 0)) ?> pendientes, <?= e((string) ($metricas_iniciales['en_proceso'] ?? 0)) ?> en proceso
                            </span>
                        </div>
                        <div class="bg-warning-subtle text-warning p-3 b-r-10 d-flex-center">
                            <i class="fa-solid fa-hourglass-half f-s-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-secondary f-s-12 f-w-600 text-uppercase">Urgencias Activas</span>
                            <h3 class="mb-0 f-w-700 text-danger mt-1" id="metrica-urgentes">
                                <?= e((string) ($metricas_iniciales['urgentes_activas'] ?? 0)) ?>
                            </h3>
                            <span class="f-s-11 text-muted">Requieren atención prioritaria de turno</span>
                        </div>
                        <div class="bg-danger-subtle text-danger p-3 b-r-10 d-flex-center">
                            <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-secondary f-s-12 f-w-600 text-uppercase">Consignas e Incidencias</span>
                            <h3 class="mb-0 f-w-700 text-info mt-1" id="metrica-consignas">
                                <?= e((string) (($metricas_iniciales['consignas_activas'] ?? 0) + ($metricas_iniciales['incidencias_activas'] ?? 0))) ?>
                            </h3>
                            <span class="f-s-11 text-muted">
                                <?= e((string) ($metricas_iniciales['consignas_activas'] ?? 0)) ?> consignas, <?= e((string) ($metricas_iniciales['incidencias_activas'] ?? 0)) ?> incidencias
                            </span>
                        </div>
                        <div class="bg-info-subtle text-info p-3 b-r-10 d-flex-center">
                            <i class="fa-solid fa-clipboard-check f-s-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <form id="form-filtros-bitacora" class="row g-2 align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label f-s-12 text-secondary mb-1">Propiedad / Sede</label>
                            <select class="form-select form-select-sm" id="filtro-propiedad" name="propiedad_id">
                                <option value="">Todas las propiedades</option>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= e((string) $p['id']) ?>"><?= e((string) $p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label f-s-12 text-secondary mb-1">Tipo de Entrada</label>
                            <select class="form-select form-select-sm" id="filtro-tipo" name="tipo">
                                <option value="">Todos los tipos</option>
                                <?php foreach ($tipos as $t): ?>
                                    <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label f-s-12 text-secondary mb-1">Prioridad</label>
                            <select class="form-select form-select-sm" id="filtro-prioridad" name="prioridad">
                                <option value="">Todas</option>
                                <?php foreach ($prioridades as $pr): ?>
                                    <option value="<?= e($pr) ?>"><?= e($pr) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label f-s-12 text-secondary mb-1">Turno</label>
                            <select class="form-select form-select-sm" id="filtro-turno" name="turno">
                                <option value="">Todos los turnos</option>
                                <?php foreach ($turnos as $tu): ?>
                                    <option value="<?= e($tu) ?>"><?= e($tu) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label f-s-12 text-secondary mb-1">Estado</label>
                            <select class="form-select form-select-sm" id="filtro-estado" name="estado">
                                <option value="">Todos los estados</option>
                                <?php foreach ($estados as $est): ?>
                                    <option value="<?= e($est) ?>"><?= e($est) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label f-s-12 text-secondary mb-1">Fecha Operativa</label>
                            <input type="date" class="form-control form-control-sm" id="filtro-fecha" name="fecha_operativa">
                        </div>

                        <div class="col-md-5 col-sm-8">
                            <label class="form-label f-s-12 text-secondary mb-1">Búsqueda rápida</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" class="form-control" id="filtro-busqueda" name="busqueda" placeholder="Buscar por título, consigna o relato...">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-4 d-flex gap-2">
                            <button type="submit" class="btn btn-secondary btn-sm w-100">
                                <i class="fa-solid fa-filter me-1"></i> Filtrar
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-limpiar-filtros" title="Restablecer filtros">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Feed / Timeline de Entradas -->
    <div class="row">
        <div class="col-12">
            <div id="contenedor-feed-bitacora">
                <div class="text-center py-5" id="feed-cargando">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando libro de guardia...</span>
                    </div>
                    <p class="text-muted mt-2 f-s-13">Cargando novedades y consignas...</p>
                </div>

                <div id="feed-vacio" class="card shadow-sm border-0 text-center py-5 d-none">
                    <div class="card-body">
                        <i class="fa-solid fa-book-open text-muted f-s-40 mb-3"></i>
                        <h5 class="text-secondary f-w-600">No se encontraron entradas</h5>
                        <p class="text-muted f-s-13 mb-3">No hay novedades registradas con los filtros seleccionados.</p>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-crear-vacio">
                            <i class="fa-solid fa-plus me-1"></i> Registrar la primera novedad
                        </button>
                    </div>
                </div>

                <div id="feed-lista" class="d-flex flex-column gap-3"></div>

                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-4 d-none" id="seccion-paginacion">
                    <span class="f-s-13 text-secondary" id="info-paginacion">Mostrando 0 de 0</span>
                    <ul class="pagination pagination-sm mb-0" id="lista-paginas"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: REGISTRAR ENTRADA EN BITÁCORA -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modal-crear-entrada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-s-16 f-w-700">
                        <i class="fa-solid fa-book-bookmark text-primary me-2"></i>Registrar Entrada en Libro de Guardia
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-crear-entrada" class="app-form app-icon-form" novalidate>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 px-3 f-s-12 mb-3">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <strong>Regla vinculante:</strong> El relato original es inmutable. Toda corrección posterior quedará registrada como enmienda append-only en el historial.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label f-s-13 f-w-600 required">Propiedad / Sede</label>
                                <select class="form-select basic-select2" name="propiedad_id" required>
                                    <option value="">Seleccione una sede...</option>
                                    <?php foreach ($propiedades as $p): ?>
                                        <option value="<?= e((string) $p['id']) ?>"><?= e((string) $p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label f-s-13 f-w-600 required">Tipo</label>
                                <select class="form-select basic-select2" name="tipo" id="input-crear-tipo" required>
                                    <?php foreach ($tipos as $t): ?>
                                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label f-s-13 f-w-600 required">Prioridad</label>
                                <select class="form-select basic-select2" name="prioridad" required>
                                    <option value="BAJA">BAJA</option>
                                    <option value="MEDIA" selected>MEDIA</option>
                                    <option value="ALTA">ALTA</option>
                                    <option value="URGENTE">URGENTE</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label f-s-13 f-w-600 required">Turno</label>
                                <select class="form-select basic-select2" name="turno" required>
                                    <option value="GENERAL" selected>GENERAL</option>
                                    <option value="MANANA">MAÑANA</option>
                                    <option value="TARDE">TARDE</option>
                                    <option value="NOCHE">NOCHE</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label f-s-13 f-w-600 required">Fecha Operativa</label>
                                <div class="icon-control position-relative">
                                    <i class="fa-solid fa-calendar-day position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                    <input type="date" class="form-control ps-5" name="fecha_operativa" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label f-s-13 f-w-600">Habitación / Unidad (Opcional)</label>
                                <select class="form-select basic-select2" name="unidad_id">
                                    <option value="">Ninguna / Áreas comunes</option>
                                    <?php foreach ($unidades as $u): ?>
                                        <option value="<?= e((string) $u['id']) ?>"><?= e((string) $u['codigo']) ?> - <?= e((string) $u['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label f-s-13 f-w-600 required">Título / Asunto Breve</label>
                                <div class="icon-control position-relative">
                                    <i class="fa-solid fa-heading position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                    <input type="text" class="form-control ps-5" name="titulo" maxlength="200" placeholder="Ej. Solicitud de toallas adicionales en Hab 204 o Fuga de agua detectada" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label f-s-13 f-w-600 required">Relato Operativo / Contenido Detallado</label>
                                <div class="icon-control position-relative icon-textarea">
                                    <i class="fa-solid fa-pen-to-square position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                    <textarea class="form-control ps-5" name="contenido" rows="4" placeholder="Describa con precisión los hechos ocurridos, consignas dadas o novedades observadas durante la guardia..." required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-entrada">
                            <i class="fa-solid fa-check me-1"></i> Guardar en Bitácora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: AGREGAR SEGUIMIENTO / ENMIENDA (APPEND-ONLY) -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modal-agregar-seguimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-s-16 f-w-700">
                        <i class="fa-solid fa-comment-dots text-primary me-2"></i>Añadir Seguimiento / Enmienda
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-agregar-seguimiento" class="app-form app-icon-form" novalidate>
                    <input type="hidden" name="entrada_id" id="seg-entrada-id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600 required">Tipo de Registro</label>
                            <select class="form-select basic-select2" name="tipo_evento" id="seg-tipo-evento" required>
                                <option value="COMENTARIO" selected>Comentario de seguimiento</option>
                                <option value="ENMIENDA">Enmienda aclaratoria (corrección sobre el hecho)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600 required">Contenido de la Nota</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="contenido" id="seg-contenido" rows="4" placeholder="Escriba la actualización operativa o aclaración append-only..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-paper-plane me-1"></i> Registrar Seguimiento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: CAMBIAR ESTADO OPERATIVO -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modal-cambiar-estado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-s-16 f-w-700">
                        <i class="fa-solid fa-arrows-rotate text-warning me-2"></i>Cambiar Estado Operativo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-cambiar-estado" class="app-form app-icon-form" novalidate>
                    <input type="hidden" name="entrada_id" id="est-entrada-id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600 required">Nuevo Estado</label>
                            <select class="form-select basic-select2" name="nuevo_estado" id="est-nuevo-estado" required>
                                <option value="PENDIENTE">PENDIENTE</option>
                                <option value="EN_PROCESO">EN PROCESO</option>
                                <option value="REGISTRADA">REGISTRADA</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600">Nota u observación del cambio (Opcional)</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-arrows-rotate position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="nota" id="est-nota" rows="2" placeholder="Motivo o detalle del cambio de estado..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fa-solid fa-check me-1"></i> Actualizar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: RESOLVER CONSIGNAS / INCIDENCIAS -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modal-resolver-entrada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-s-16 f-w-700">
                        <i class="fa-solid fa-check-double text-success me-2"></i>Marcar Novedad como RESUELTA
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-resolver-entrada" class="app-form app-icon-form" novalidate>
                    <input type="hidden" name="entrada_id" id="res-entrada-id">
                    <div class="modal-body">
                        <p class="f-s-13 text-secondary mb-3">
                            Al resolver esta consigna o incidencia quedará fijada la trazabilidad de cumplimiento en el libro de guardia.
                        </p>
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600 required">Detalle de Solución / Descargo</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-check-double position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="nota_resolucion" id="res-nota" rows="3" placeholder="Detalle qué acciones se tomaron y cómo quedó subsanada la situación..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-check-circle me-1"></i> Confirmar Resolución
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: REABRIR ENTRADA -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modal-reabrir-entrada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-s-16 f-w-700">
                        <i class="fa-solid fa-arrow-rotate-left text-info me-2"></i>Reabrir Novedad Resuelta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-reabrir-entrada" class="app-form app-icon-form" novalidate>
                    <input type="hidden" name="entrada_id" id="reab-entrada-id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600 required">Motivo de Reapertura</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-arrow-rotate-left position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="motivo_reapertura" id="reab-motivo" rows="3" placeholder="Explique por qué se reabre la novedad y qué acciones faltan..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-white btn-sm">
                            <i class="fa-solid fa-arrow-rotate-left me-1"></i> Reabrir Novedad
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: ANULACIÓN SUPERVISADA (ANULAR != DELETE) -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="modal-anular-entrada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-s-16 f-w-700 text-danger">
                        <i class="fa-solid fa-ban me-2"></i>Anulación Supervisada de Entrada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="form-anular-entrada" class="app-form app-icon-form" novalidate>
                    <input type="hidden" name="entrada_id" id="anul-entrada-id">
                    <div class="modal-body">
                        <div class="alert alert-danger py-2 px-3 f-s-12 mb-3">
                            <i class="fa-solid fa-shield-virus me-1"></i>
                            <strong>Principio D-089 (ANULAR != DELETE):</strong> Los registros del libro de guardia no se borran de la base de datos. La anulación conservará el histórico con su firma y motivo.
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-s-13 f-w-600 required">Motivo Justificado de Anulación</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-triangle-exclamation position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="motivo_anulacion" id="anul-motivo" rows="3" placeholder="Fundamente la anulación (mínimo 5 caracteres, ej. Registro duplicado accidentalmente por error de digitación)..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-ban me-1"></i> Confirmar Anulación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- JavaScript Propio del Módulo de Bitácora -->
<script>
(function() {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';
    let paginaActual = 1;
    const porPagina = 20;

    // Helpers
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function badgePrioridad(p) {
        switch (p) {
            case 'URGENTE': return '<span class="badge bg-danger"><i class="fa-solid fa-fire me-1"></i>URGENTE</span>';
            case 'ALTA': return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-bolt me-1"></i>ALTA</span>';
            case 'MEDIA': return '<span class="badge bg-primary">MEDIA</span>';
            default: return '<span class="badge bg-secondary">BAJA</span>';
        }
    }

    function badgeTipo(t) {
        switch (t) {
            case 'CONSIGNA': return '<span class="badge bg-primary-subtle text-primary border border-primary"><i class="fa-solid fa-clipboard-check me-1"></i>CONSIGNA</span>';
            case 'INCIDENCIA': return '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>INCIDENCIA</span>';
            case 'RELEVO': return '<span class="badge bg-info-subtle text-info border border-info"><i class="fa-solid fa-people-arrows me-1"></i>RELEVO</span>';
            case 'AVISO_GENERAL': return '<span class="badge bg-warning-subtle text-warning border border-warning"><i class="fa-solid fa-bullhorn me-1"></i>AVISO GENERAL</span>';
            default: return '<span class="badge bg-secondary-subtle text-secondary border border-secondary"><i class="fa-solid fa-note-sticky me-1"></i>NOVEDAD</span>';
        }
    }

    function badgeEstado(e) {
        switch (e) {
            case 'RESUELTA': return '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>RESUELTA</span>';
            case 'EN_PROCESO': return '<span class="badge bg-info text-white"><i class="fa-solid fa-spinner fa-spin me-1"></i>EN PROCESO</span>';
            case 'PENDIENTE': return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>PENDIENTE</span>';
            case 'ANULADA': return '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-ban me-1"></i>ANULADA</span>';
            default: return '<span class="badge bg-secondary">REGISTRADA</span>';
        }
    }

    function badgeEvento(ev) {
        switch (ev) {
            case 'ENMIENDA': return '<span class="badge bg-warning-subtle text-dark border border-warning f-s-10">ENMIENDA</span>';
            case 'CAMBIO_ESTADO': return '<span class="badge bg-info-subtle text-info border border-info f-s-10">CAMBIO ESTADO</span>';
            case 'RESOLUCION': return '<span class="badge bg-success-subtle text-success border border-success f-s-10">RESOLUCIÓN</span>';
            case 'REAPERTURA': return '<span class="badge bg-primary-subtle text-primary border border-primary f-s-10">REAPERTURA</span>';
            case 'ANULACION': return '<span class="badge bg-danger-subtle text-danger border border-danger f-s-10">ANULACIÓN</span>';
            default: return '<span class="badge bg-light text-secondary border f-s-10">COMENTARIO</span>';
        }
    }

    // Cargar Feed
    async function cargarFeed(pagina = 1) {
        paginaActual = pagina;
        const feedCargando = document.getElementById('feed-cargando');
        const feedVacio = document.getElementById('feed-vacio');
        const feedLista = document.getElementById('feed-lista');
        const seccionPaginacion = document.getElementById('seccion-paginacion');

        feedCargando.classList.remove('d-none');
        feedVacio.classList.add('d-none');
        feedLista.innerHTML = '';

        const form = document.getElementById('form-filtros-bitacora');
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [k, v] of formData.entries()) {
            if (v.trim() !== '') params.append(k, v.trim());
        }
        params.append('pagina', pagina);
        params.append('limite', porPagina);

        try {
            const resp = await fetch(`/api/operaciones/bitacora?${params.toString()}`);
            const data = await resp.json();

            feedCargando.classList.add('d-none');

            if (!data.ok || !data.datos || data.datos.entradas.length === 0) {
                feedVacio.classList.remove('d-none');
                seccionPaginacion.classList.add('d-none');
                actualizarMetricas(data.metricas || {});
                return;
            }

            renderizarEntradas(data.datos.entradas);
            renderizarPaginacion(data.datos);
            actualizarMetricas(data.metricas || {});
        } catch (err) {
            feedCargando.classList.add('d-none');
            feedLista.innerHTML = `
                <div class="alert alert-danger p-3 f-s-13">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    Error al cargar entradas de bitácora: ${escapeHtml(err.message)}
                </div>
            `;
        }
    }

    function actualizarMetricas(m) {
        if (!m) return;
        document.getElementById('metrica-total-hoy').textContent = m.total_hoy || 0;
        document.getElementById('metrica-pendientes').textContent = ((m.pendientes || 0) + (m.en_proceso || 0));
        document.getElementById('metrica-urgentes').textContent = m.urgentes_activas || 0;
        document.getElementById('metrica-consignas').textContent = ((m.consignas_activas || 0) + (m.incidencias_activas || 0));
    }

    function renderizarEntradas(entradas) {
        const feedLista = document.getElementById('feed-lista');
        feedLista.innerHTML = '';

        entradas.forEach(e => {
            const card = document.createElement('div');
            card.className = `card shadow-sm border-0 ${e.estado === 'ANULADA' ? 'opacity-75 bg-light' : ''}`;
            card.id = `bitacora-card-${e.id}`;

            const borderClass = e.prioridad === 'URGENTE' ? 'border-start border-4 border-danger' :
                               (e.prioridad === 'ALTA' ? 'border-start border-4 border-warning' : '');

            let htmlResolucion = '';
            if (e.estado === 'RESUELTA' && e.resuelta_en) {
                htmlResolucion = `
                    <div class="alert alert-success py-2 px-3 f-s-12 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong><i class="fa-solid fa-check-circle me-1"></i>Resuelta por:</strong>
                            <span class="text-muted">${escapeHtml(e.resuelta_por_nombre || 'Usuario')} &bull; ${escapeHtml(e.resuelta_en)}</span>
                        </div>
                        <div class="fst-italic text-dark">${escapeHtml(e.nota_resolucion || 'Sin nota de descargo')}</div>
                    </div>
                `;
            }

            let htmlAnulacion = '';
            if (e.estado === 'ANULADA' && e.anulada_en) {
                htmlAnulacion = `
                    <div class="alert alert-danger py-2 px-3 f-s-12 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong><i class="fa-solid fa-ban me-1"></i>Anulación Supervisada:</strong>
                            <span class="text-muted">${escapeHtml(e.anulada_por_nombre || 'Supervisor')} &bull; ${escapeHtml(e.anulada_en)}</span>
                        </div>
                        <div class="text-danger">${escapeHtml(e.motivo_anulacion || 'Sin motivo indicado')}</div>
                    </div>
                `;
            }

            let seguimientosHtml = '';
            if (e.seguimientos && e.seguimientos.length > 0) {
                seguimientosHtml = `
                    <div class="border-top pt-2 mt-2">
                        <div class="f-s-12 f-w-600 text-secondary mb-2">
                            <i class="fa-solid fa-timeline me-1"></i>Trazabilidad Cronológica Append-Only (${e.seguimientos.length}):
                        </div>
                        <div class="d-flex flex-column gap-2">
                            ${e.seguimientos.map(s => `
                                <div class="bg-white border rounded p-2 f-s-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            ${badgeEvento(s.tipo_evento)}
                                            <span class="f-w-600 ms-1">${escapeHtml(s.usuario_nombre || 'Colaborador')}</span>
                                        </div>
                                        <span class="text-muted f-s-11">${escapeHtml(s.creado_en || '')}</span>
                                    </div>
                                    <div class="text-secondary">${escapeHtml(s.contenido)}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            // Botones de acción
            let botonesAccion = '';
            if (e.estado !== 'ANULADA') {
                botonesAccion += `
                    <button type="button" class="btn btn-outline-primary btn-sm btn-accion-seguimiento" data-id="${e.id}" title="Añadir seguimiento o enmienda">
                        <i class="fa-solid fa-comment-dots me-1"></i> Comentar / Enmendar
                    </button>
                `;

                if (e.estado !== 'RESUELTA') {
                    botonesAccion += `
                        <button type="button" class="btn btn-outline-warning btn-sm btn-accion-estado" data-id="${e.id}" data-estado="${e.estado}" title="Cambiar estado operativo">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Estado
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm btn-accion-resolver" data-id="${e.id}" title="Resolver consigna o incidencia">
                            <i class="fa-solid fa-check-circle me-1"></i> Resolver
                        </button>
                    `;
                } else {
                    botonesAccion += `
                        <button type="button" class="btn btn-outline-info btn-sm btn-accion-reabrir" data-id="${e.id}" title="Reabrir novedad resuelta">
                            <i class="fa-solid fa-arrow-rotate-left me-1"></i> Reabrir
                        </button>
                    `;
                }

                botonesAccion += `
                    <button type="button" class="btn btn-outline-danger btn-sm btn-accion-anular" data-id="${e.id}" title="Anular con supervisión (ANULAR != DELETE)">
                        <i class="fa-solid fa-ban me-1"></i> Anular
                    </button>
                `;
            }

            card.innerHTML = `
                <div class="card-body p-3 ${borderClass}">
                    <div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            ${badgeTipo(e.tipo)}
                            ${badgePrioridad(e.prioridad)}
                            ${badgeEstado(e.estado)}
                            <span class="badge bg-light text-secondary border">Turno: ${escapeHtml(e.turno)}</span>
                            ${e.unidad_numero ? `<span class="badge bg-primary-subtle text-primary"><i class="fa-solid fa-door-open me-1"></i>Hab/Unidad ${escapeHtml(e.unidad_numero)}</span>` : ''}
                        </div>
                        <div class="text-end f-s-12 text-muted">
                            <span><i class="fa-solid fa-calendar me-1"></i>${escapeHtml(e.fecha_operativa)}</span>
                            <span class="ms-2"><i class="fa-solid fa-clock me-1"></i>${escapeHtml(e.creado_en || '')}</span>
                        </div>
                    </div>

                    <h5 class="card-title f-s-15 f-w-700 mb-1 text-dark">${escapeHtml(e.titulo)}</h5>

                    <div class="text-muted f-s-11 mb-2">
                        <span><i class="fa-solid fa-building me-1"></i>${escapeHtml(e.propiedad_nombre || 'Sede')}</span>
                        <span class="mx-1">&bull;</span>
                        <span><i class="fa-solid fa-user-pen me-1"></i>Autor: ${escapeHtml(e.usuario_creador_nombre || 'Colaborador')}</span>
                    </div>

                    <div class="bg-light p-3 rounded mb-3 border-start border-3 border-primary-subtle">
                        <div class="f-s-11 f-w-700 text-uppercase text-secondary mb-1">
                            <i class="fa-solid fa-lock me-1"></i>Relato Original Inmutable:
                        </div>
                        <div class="f-s-13 text-dark text-break" style="white-space: pre-wrap;">${escapeHtml(e.contenido)}</div>
                    </div>

                    ${htmlResolucion}
                    ${htmlAnulacion}
                    ${seguimientosHtml}

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 pt-2 border-top">
                        ${botonesAccion}
                    </div>
                </div>
            `;

            feedLista.appendChild(card);
        });

        adjuntarEventosAcciones();
    }

    function renderizarPaginacion(datos) {
        const seccionPaginacion = document.getElementById('seccion-paginacion');
        const infoPaginacion = document.getElementById('info-paginacion');
        const listaPaginas = document.getElementById('lista-paginas');

        if (datos.total_paginas <= 1) {
            seccionPaginacion.classList.add('d-none');
            return;
        }

        seccionPaginacion.classList.remove('d-none');
        const desde = (datos.pagina - 1) * datos.por_pagina + 1;
        const hasta = Math.min(datos.pagina * datos.por_pagina, datos.total);
        infoPaginacion.textContent = `Mostrando ${desde} - ${hasta} de ${datos.total} entradas`;

        listaPaginas.innerHTML = '';

        for (let p = 1; p <= datos.total_paginas; p++) {
            const li = document.createElement('li');
            li.className = `page-item ${p === datos.pagina ? 'active' : ''}`;
            li.innerHTML = `<button class="page-link" type="button">${p}</button>`;
            li.querySelector('button').addEventListener('click', () => cargarFeed(p));
            listaPaginas.appendChild(li);
        }
    }

    function adjuntarEventosAcciones() {
        // Seguimiento / Enmienda
        document.querySelectorAll('.btn-accion-seguimiento').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                document.getElementById('seg-entrada-id').value = id;
                document.getElementById('seg-contenido').value = '';
                const modal = new bootstrap.Modal(document.getElementById('modal-agregar-seguimiento'));
                modal.show();
            });
        });

        // Cambiar Estado
        document.querySelectorAll('.btn-accion-estado').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const estado = btn.getAttribute('data-estado');
                document.getElementById('est-entrada-id').value = id;
                document.getElementById('est-nuevo-estado').value = estado === 'PENDIENTE' ? 'EN_PROCESO' : 'PENDIENTE';
                document.getElementById('est-nota').value = '';
                const modal = new bootstrap.Modal(document.getElementById('modal-cambiar-estado'));
                modal.show();
            });
        });

        // Resolver
        document.querySelectorAll('.btn-accion-resolver').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                document.getElementById('res-entrada-id').value = id;
                document.getElementById('res-nota').value = '';
                const modal = new bootstrap.Modal(document.getElementById('modal-resolver-entrada'));
                modal.show();
            });
        });

        // Reabrir
        document.querySelectorAll('.btn-accion-reabrir').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                document.getElementById('reab-entrada-id').value = id;
                document.getElementById('reab-motivo').value = '';
                const modal = new bootstrap.Modal(document.getElementById('modal-reabrir-entrada'));
                modal.show();
            });
        });

        // Anular
        document.querySelectorAll('.btn-accion-anular').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                document.getElementById('anul-entrada-id').value = id;
                document.getElementById('anul-motivo').value = '';
                const modal = new bootstrap.Modal(document.getElementById('modal-anular-entrada'));
                modal.show();
            });
        });
    }

    // Inicializar listeners de formularios
    document.addEventListener('DOMContentLoaded', () => {
        // Refrescar feed
        document.getElementById('btn-refrescar-feed')?.addEventListener('click', () => cargarFeed(1));

        // Formulario de filtros
        document.getElementById('form-filtros-bitacora')?.addEventListener('submit', (e) => {
            e.preventDefault();
            cargarFeed(1);
        });

        // Limpiar filtros
        document.getElementById('btn-limpiar-filtros')?.addEventListener('click', () => {
            document.getElementById('form-filtros-bitacora').reset();
            cargarFeed(1);
        });

        // Botón abrir modal crear
        document.getElementById('btn-abrir-modal-crear')?.addEventListener('click', () => {
            document.getElementById('form-crear-entrada').reset();
            const modal = new bootstrap.Modal(document.getElementById('modal-crear-entrada'));
            modal.show();
        });
        document.getElementById('btn-crear-vacio')?.addEventListener('click', () => {
            document.getElementById('form-crear-entrada').reset();
            const modal = new bootstrap.Modal(document.getElementById('modal-crear-entrada'));
            modal.show();
        });

        // Enviar Nueva Entrada
        document.getElementById('form-crear-entrada')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                const resp = await fetch('/api/operaciones/bitacora', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (!res.ok) {
                    alert(res.error || 'Ocurrió un error al registrar en bitácora.');
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('modal-crear-entrada'))?.hide();
                form.reset();
                cargarFeed(1);
            } catch (err) {
                alert('Falla de comunicación con el servidor: ' + err.message);
            }
        });

        // Enviar Seguimiento
        document.getElementById('form-agregar-seguimiento')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const entradaId = document.getElementById('seg-entrada-id').value;
            const payload = {
                tipo_evento: document.getElementById('seg-tipo-evento').value,
                contenido: document.getElementById('seg-contenido').value
            };

            try {
                const resp = await fetch(`/api/operaciones/bitacora/${entradaId}/seguimiento`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (!res.ok) {
                    alert(res.error || 'Error al agregar seguimiento.');
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('modal-agregar-seguimiento'))?.hide();
                cargarFeed(paginaActual);
            } catch (err) {
                alert('Falla de red: ' + err.message);
            }
        });

        // Enviar Cambio de Estado
        document.getElementById('form-cambiar-estado')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const entradaId = document.getElementById('est-entrada-id').value;
            const payload = {
                nuevo_estado: document.getElementById('est-nuevo-estado').value,
                nota: document.getElementById('est-nota').value
            };

            try {
                const resp = await fetch(`/api/operaciones/bitacora/${entradaId}/estado`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (!res.ok) {
                    alert(res.error || 'Error al cambiar estado.');
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('modal-cambiar-estado'))?.hide();
                cargarFeed(paginaActual);
            } catch (err) {
                alert('Falla de red: ' + err.message);
            }
        });

        // Enviar Resolución
        document.getElementById('form-resolver-entrada')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const entradaId = document.getElementById('res-entrada-id').value;
            const payload = {
                nota_resolucion: document.getElementById('res-nota').value
            };

            try {
                const resp = await fetch(`/api/operaciones/bitacora/${entradaId}/resolver`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (!res.ok) {
                    alert(res.error || 'Error al resolver novedad.');
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('modal-resolver-entrada'))?.hide();
                cargarFeed(paginaActual);
            } catch (err) {
                alert('Falla de red: ' + err.message);
            }
        });

        // Enviar Reapertura
        document.getElementById('form-reabrir-entrada')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const entradaId = document.getElementById('reab-entrada-id').value;
            const payload = {
                motivo_reapertura: document.getElementById('reab-motivo').value
            };

            try {
                const resp = await fetch(`/api/operaciones/bitacora/${entradaId}/reabrir`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (!res.ok) {
                    alert(res.error || 'Error al reabrir novedad.');
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('modal-reabrir-entrada'))?.hide();
                cargarFeed(paginaActual);
            } catch (err) {
                alert('Falla de red: ' + err.message);
            }
        });

        // Enviar Anulación Supervisada
        document.getElementById('form-anular-entrada')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const entradaId = document.getElementById('anul-entrada-id').value;
            const payload = {
                motivo_anulacion: document.getElementById('anul-motivo').value
            };

            try {
                const resp = await fetch(`/api/operaciones/bitacora/${entradaId}/anular`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (!res.ok) {
                    alert(res.error || 'Error al anular entrada.');
                    return;
                }

                bootstrap.Modal.getInstance(document.getElementById('modal-anular-entrada'))?.hide();
                cargarFeed(paginaActual);
            } catch (err) {
                alert('Falla de red: ' + err.message);
            }
        });

        // Carga inicial de feed
        cargarFeed(1);
    });
})();
</script>
