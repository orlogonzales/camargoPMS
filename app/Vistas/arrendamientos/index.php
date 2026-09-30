<?php

declare(strict_types=1);

/**
 * Vista principal de Arrendamientos de Mediana y Larga Estancia — Camargo PMS (ARRENDAMIENTOS-1 / D-076).
 *
 * Principios vinculantes:
 * - RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO.
 * - Temporalidad a plazo cerrado (fecha_fin NOT NULL).
 * - Semántica hotelera semiabierta [fecha_inicio, fecha_fin).
 * - Formulario con geometría nativa Alina (app-form app-icon-form, border-radius 20px, select2 42px).
 * - D-071: Font Awesome 6.3.0 exclusivo, Variants of badge Alina (bg-light-*).
 *
 * @var array<int, array<string, mixed>> $propiedades
 * @var array<int, array<string, mixed>> $unidades
 * @var string $csrf_token
 * @var array<string, bool> $capacidades
 * @var \CamargoPMS\Modelos\Usuario|null $usuario_actual
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Arrendamientos -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-file-contract f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Contratos de Arrendamiento</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión patrimonial de mediana y larga estancia, disponibilidad protegida en inventario y obligaciones mensuales.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-abrir-crear-arrendamiento">
                            <i class="fa-solid fa-plus me-1"></i> Nuevo Contrato
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs de Arrendamientos -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Contratos Vigentes</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-success mt-1" id="kpi-vigentes">0</h3>
                                    <span class="f-s-11 text-muted">En ocupación activa</span>
                                </div>
                                <div class="bg-light-success text-success p-3 b-r-8">
                                    <i class="fa-solid fa-house-chimney-user f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Borradores</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-warning mt-1" id="kpi-borradores">0</h3>
                                    <span class="f-s-11 text-muted">Por activar o firmar</span>
                                </div>
                                <div class="bg-light-warning text-warning p-3 b-r-8">
                                    <i class="fa-solid fa-pen-ruler f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Finalizados / Rescindidos</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-secondary mt-1" id="kpi-cerrados">0</h3>
                                    <span class="f-s-11 text-muted">Histórico contractual</span>
                                </div>
                                <div class="bg-light-secondary text-secondary p-3 b-r-8">
                                    <i class="fa-solid fa-box-archive f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="card border-0 shadow-none bg-white p-3 mb-0 b-r-8">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-secondary f-s-12 text-uppercase f-w-600">Custodia en Garantías</span>
                                    <h3 class="mb-0 f-s-20 f-w-700 text-primary mt-1" id="kpi-garantias">S/ 0.00</h3>
                                    <span class="f-s-11 text-muted">Fondos segregados</span>
                                </div>
                                <div class="bg-light-primary text-primary p-3 b-r-8">
                                    <i class="fa-solid fa-vault f-s-20"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros de Búsqueda -->
            <div class="card-body p-3 border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4 col-12">
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm ps-4" id="filtro-busqueda-arrendamiento" placeholder="Buscar por código, unidad o titular...">
                            <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-2 text-muted f-s-12"></i>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <select class="form-select form-select-sm" id="filtro-propiedad-arrendamiento">
                            <option value="">Todas las Propiedades</option>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= e((string) $p->obtenerId()) ?>"><?= e($p->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <select class="form-select form-select-sm" id="filtro-estado-arrendamiento">
                            <option value="">Todos los Estados</option>
                            <option value="BORRADOR">BORRADOR</option>
                            <option value="VIGENTE">VIGENTE</option>
                            <option value="FINALIZADO">FINALIZADO</option>
                            <option value="RESCINDIDO">RESCINDIDO</option>
                            <option value="CANCELADO">CANCELADO</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-limpiar-filtros">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Arrendamientos -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-arrendamientos">
                        <thead class="bg-light text-secondary f-s-12 text-uppercase">
                            <tr>
                                <th class="ps-3">Código</th>
                                <th>Propiedad / Unidad</th>
                                <th>Titular Contractual</th>
                                <th>Vigencia</th>
                                <th class="text-end">Renta Mensual</th>
                                <th class="text-end">Garantía</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="f-s-13" id="tbody-arrendamientos">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Cargando catálogo de arrendamientos...
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
<!-- Modal: Formular Contrato de Arrendamiento (app-form app-icon-form)        -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-crear-arrendamiento" tabindex="-1" aria-labelledby="modalCrearLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-700" id="modalCrearLabel">
                    <i class="fa-solid fa-file-signature me-2"></i> Formular Contrato de Arrendamiento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-crear-arrendamiento" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Unidad Habitacional -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-unidad-id">Unidad Habitacional <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <select class="form-select icon-control" id="arr-unidad-id" name="unidad_id" required>
                                    <option value="">Seleccione una unidad...</option>
                                    <?php foreach ($unidades as $u): ?>
                                        <option value="<?= e((string) $u->obtenerId()) ?>">
                                            Unidad <?= e($u->obtenerCodigo()) ?> — <?= e($u->obtenerNombre()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-door-open form-icon"></i>
                            </div>
                        </div>

                        <!-- Titular Persona ID -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-titular-persona-id">ID Persona Titular <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="number" class="form-control icon-control" id="arr-titular-persona-id" name="titular_persona_id" placeholder="Ej. 1" min="1" required>
                                <i class="fa-solid fa-user form-icon"></i>
                            </div>
                            <small class="text-muted f-s-11">Identificador numérico de la persona en el PMS.</small>
                        </div>

                        <!-- Fechas de Inicio y Fin -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-fecha-inicio">Fecha de Inicio <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="date" class="form-control icon-control" id="arr-fecha-inicio" name="fecha_inicio" required>
                                <i class="fa-solid fa-calendar-day form-icon"></i>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-fecha-fin">Fecha de Fin (Pacto cerrado) <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="date" class="form-control icon-control" id="arr-fecha-fin" name="fecha_fin" required>
                                <i class="fa-solid fa-calendar-check form-icon"></i>
                            </div>
                            <small class="text-muted f-s-11">Intervalo semiabierto [Inicio, Fin). Fin queda libre.</small>
                        </div>

                        <!-- Renta Mensual y Día de Vencimiento -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-renta-mensual">Renta Mensual (S/) <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="number" step="0.01" min="0.01" class="form-control icon-control" id="arr-renta-mensual" name="renta_mensual" placeholder="0.00" required>
                                <i class="fa-solid fa-money-bill-wave form-icon"></i>
                            </div>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-dia-vencimiento">Día Contractual de Vencimiento <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="number" min="1" max="31" class="form-control icon-control" id="arr-dia-vencimiento" name="dia_vencimiento" value="1" required>
                                <i class="fa-solid fa-calendar-week form-icon"></i>
                            </div>
                            <small class="text-muted f-s-11">Día del mes (1..31). Meses cortos se ajustan a fin de mes.</small>
                        </div>

                        <!-- Depósito en Garantía -->
                        <div class="col-md-6 col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-deposito-garantia">Depósito de Garantía (S/)</label>
                            <div class="position-relative">
                                <input type="number" step="0.01" min="0.00" class="form-control icon-control" id="arr-deposito-garantia" name="deposito_garantia" value="0.00">
                                <i class="fa-solid fa-shield-halved form-icon"></i>
                            </div>
                            <small class="text-muted f-s-11">Fondo segregado en custodia contable.</small>
                        </div>

                        <!-- Prorrateo del Primer Mes -->
                        <div class="col-md-6 col-12 d-flex flex-column justify-content-center">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="arr-es-prorrateado" name="es_primer_mes_prorrateado" value="1">
                                <label class="form-check-label f-s-13 f-w-600" for="arr-es-prorrateado">
                                    Prorratear primer mes de ocupación
                                </label>
                            </div>
                            <small class="text-muted f-s-11">Calcula el importe según los días restantes del mes de inicio.</small>
                        </div>

                        <!-- Notas Adicionales -->
                        <div class="col-12">
                            <label class="form-label f-s-13 f-w-600 mb-1" for="arr-notas">Notas o Condiciones Especiales</label>
                            <textarea class="form-control" id="arr-notas" name="notas_adicionales" rows="2" placeholder="Observaciones contractuales o cláusulas complementarias..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-arrendamiento">
                        <i class="fa-solid fa-check me-1"></i> Formular Borrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Modal: Detalle Completo de Arrendamiento                                   -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-detalle-arrendamiento" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary text-white p-2 b-r-8"><i class="fa-solid fa-file-contract"></i></span>
                    <div>
                        <h5 class="modal-title f-s-16 f-w-700 mb-0" id="detalle-codigo">ARR-00000000-0000</h5>
                        <span class="f-s-12 text-muted" id="detalle-subtitulo">Contrato de Arrendamiento Patrimonial</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="detalle-badge-estado"></span>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <!-- Barra de Acciones de Dominio según estado -->
                <div class="p-3 bg-light border b-r-8 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2" id="barra-acciones-detalle">
                    <div class="d-flex align-items-center gap-2">
                        <span class="f-s-12 text-muted">Acciones operativas:</span>
                        <div id="botones-accion-estado" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="f-s-12 text-muted" id="detalle-folio-referencia">
                        Folio: <strong id="detalle-folio-codigo" class="text-primary">-</strong>
                    </div>
                </div>

                <!-- Resumen Financiero y Contrato -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="p-3 border b-r-8 bg-white h-100">
                            <span class="f-s-11 text-muted text-uppercase d-block">Unidad Habitacional</span>
                            <strong class="f-s-14 text-dark d-block mt-1" id="detalle-unidad">-</strong>
                            <span class="f-s-12 text-secondary" id="detalle-propiedad">-</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="p-3 border b-r-8 bg-white h-100">
                            <span class="f-s-11 text-muted text-uppercase d-block">Vigencia Contractual</span>
                            <strong class="f-s-14 text-dark d-block mt-1" id="detalle-rango-fechas">-</strong>
                            <span class="f-s-12 text-muted">Vence día <span id="detalle-dia-vencimiento">-</span> de cada mes</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="p-3 border b-r-8 bg-white h-100">
                            <span class="f-s-11 text-muted text-uppercase d-block">Renta Mensual</span>
                            <strong class="f-s-16 text-primary d-block mt-1" id="detalle-renta-mensual">S/ 0.00</strong>
                            <span class="f-s-11 text-muted">Primer mes: <span id="detalle-monto-primer-periodo">S/ 0.00</span></span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="p-3 border b-r-8 bg-white h-100">
                            <span class="f-s-11 text-muted text-uppercase d-block">Garantía en Custodia</span>
                            <strong class="f-s-16 text-success d-block mt-1" id="detalle-garantia-monto">S/ 0.00</strong>
                            <span class="f-s-11 text-muted">Estado: <span id="detalle-garantia-estado">-</span></span>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de Detalle -->
                <ul class="nav nav-tabs nav-bottom-line border-bottom mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active f-s-13 f-w-600" data-bs-toggle="tab" href="#tab-cuotas" role="tab">
                            <i class="fa-solid fa-receipt me-1"></i> Cuotas de Renta (<span id="conteo-cuotas">0</span>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link f-s-13 f-w-600" data-bs-toggle="tab" href="#tab-sujetos" role="tab">
                            <i class="fa-solid fa-users me-1"></i> Titular y Residentes (<span id="conteo-sujetos">0</span>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link f-s-13 f-w-600" data-bs-toggle="tab" href="#tab-garantia" role="tab">
                            <i class="fa-solid fa-vault me-1"></i> Fondo de Garantía
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link f-s-13 f-w-600" data-bs-toggle="tab" href="#tab-historial" role="tab">
                            <i class="fa-solid fa-timeline me-1"></i> Auditoría de Estados
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Cuotas -->
                    <div class="tab-pane fade show active" id="tab-cuotas" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="f-s-12 text-muted">Obligaciones mensuales recurrentes imputadas al folio:</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-emitir-cuota-manual">
                                <i class="fa-solid fa-plus me-1"></i> Generar Cuota Mensual
                            </button>
                        </div>
                        <div class="table-responsive border b-r-8">
                            <table class="table table-sm table-bordered table-striped table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary f-s-11 text-uppercase">
                                    <tr>
                                        <th class="ps-3">Período</th>
                                        <th>Tipo de Cuota</th>
                                        <th>Emisión</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Monto Renta</th>
                                        <th class="text-end">Amortizado</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-center pe-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="f-s-12" id="tbody-detalle-cuotas"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Sujetos -->
                    <div class="tab-pane fade" id="tab-sujetos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="f-s-12 text-muted">Partes contractuales y residentes autorizados:</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-agregar-persona">
                                <i class="fa-solid fa-user-plus me-1"></i> Incorporar Residente
                            </button>
                        </div>
                        <div class="table-responsive border b-r-8">
                            <table class="table table-sm table-bordered table-striped table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary f-s-11 text-uppercase">
                                    <tr>
                                        <th class="ps-3">Rol</th>
                                        <th>Nombre Completo</th>
                                        <th>Documento</th>
                                        <th>Contacto</th>
                                        <th>Observaciones</th>
                                        <th class="text-center pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="f-s-12" id="tbody-detalle-sujetos"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Garantía -->
                    <div class="tab-pane fade" id="tab-garantia" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <div class="card border p-3 b-r-8 h-100">
                                    <h6 class="f-s-13 f-w-700 text-dark mb-3"><i class="fa-solid fa-scale-balanced me-2"></i> Estado Reconstructible de Custodia</h6>
                                    <div class="d-flex justify-content-between py-1 border-bottom f-s-12">
                                        <span class="text-muted">Monto Pactado en Contrato:</span>
                                        <strong id="gar-pactado">S/ 0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom f-s-12">
                                        <span class="text-muted">Total Recibido en Custodia:</span>
                                        <strong class="text-success" id="gar-recibido">S/ 0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom f-s-12">
                                        <span class="text-muted">Monto Retenido Actual:</span>
                                        <strong class="text-primary f-s-14" id="gar-retenido">S/ 0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom f-s-12">
                                        <span class="text-muted">Compensado por Daños Físicos:</span>
                                        <strong class="text-danger" id="gar-danos">S/ 0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom f-s-12">
                                        <span class="text-muted">Compensado por Renta Insoluta:</span>
                                        <strong class="text-warning" id="gar-renta">S/ 0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 f-s-12">
                                        <span class="text-muted">Fondos Devueltos al Titular:</span>
                                        <strong class="text-secondary" id="gar-devuelto">S/ 0.00</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <div class="card border p-3 b-r-8 h-100">
                                    <h6 class="f-s-13 f-w-700 text-dark mb-3"><i class="fa-solid fa-hand-holding-dollar me-2"></i> Operaciones sobre la Garantía</h6>
                                    <div class="d-flex flex-column gap-2" id="acciones-garantia-bloque">
                                        <button type="button" class="btn btn-outline-success btn-sm text-start" id="btn-garantia-recibir">
                                            <i class="fa-solid fa-arrow-down-to-bracket me-2"></i> Registrar Recepción de Fondos
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm text-start" id="btn-garantia-compensar">
                                            <i class="fa-solid fa-file-invoice-dollar me-2"></i> Compensar por Daños o Impago
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm text-start" id="btn-garantia-devolver">
                                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Devolver Saldo Retenido
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Historial -->
                    <div class="tab-pane fade" id="tab-historial" role="tabpanel">
                        <div class="timeline-widget p-2" id="contenedor-historial-estados"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Modal: Prorrogar Contrato                                                 -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-prorrogar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-15 f-w-700"><i class="fa-solid fa-calendar-plus me-2"></i> Prórroga de Contrato</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-prorrogar" class="app-form app-icon-form">
                <input type="hidden" id="prorrogar-arrendamiento-id" name="id">
                <div class="modal-body p-4">
                    <p class="f-s-13 text-secondary mb-3">
                        La prórroga extenderá la vigencia del contrato y materializará automáticamente las nuevas noches en el inventario diario.
                    </p>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600 mb-1" for="prorrogar-nueva-fecha-fin">Nueva Fecha de Fin <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="date" class="form-control icon-control" id="prorrogar-nueva-fecha-fin" name="nueva_fecha_fin" required>
                            <i class="fa-solid fa-calendar-check form-icon"></i>
                        </div>
                        <small class="text-muted f-s-11">Debe ser posterior a la fecha fin actual.</small>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Confirmar Prórroga</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Modal: Rescindir Contrato                                                 -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-rescindir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title f-s-15 f-w-700"><i class="fa-solid fa-ban me-2"></i> Rescisión Anticipada de Contrato</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-rescindir" class="app-form app-icon-form">
                <input type="hidden" id="rescindir-arrendamiento-id" name="id">
                <div class="modal-body p-4">
                    <div class="alert alert-danger f-s-12 p-3 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <strong>Atención vinculante:</strong> Las noches futuras a partir de la fecha efectiva se liberarán del inventario. Las noches pasadas quedarán preservadas para trazabilidad y auditoría.
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600 mb-1" for="rescindir-fecha-efectiva">Fecha Efectiva de Rescisión <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="date" class="form-control icon-control" id="rescindir-fecha-efectiva" name="fecha_efectiva" required>
                            <i class="fa-solid fa-calendar-xmark form-icon"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600 mb-1" for="rescindir-motivo">Causa / Justificación de Rescisión <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rescindir-motivo" name="motivo" rows="3" placeholder="Explique el motivo de la terminación anticipada..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirmar Rescisión</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- Modal: Incorporar Residente (Sujeto)                                      -->
<!-- ========================================================================= -->
<div class="modal fade" id="modal-agregar-persona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-15 f-w-700"><i class="fa-solid fa-user-plus me-2"></i> Incorporar Residente al Contrato</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-agregar-persona" class="app-form app-icon-form">
                <input type="hidden" id="persona-arrendamiento-id" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600 mb-1" for="persona-id-input">ID Persona <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="number" class="form-control icon-control" id="persona-id-input" name="persona_id" placeholder="ID numérico de la persona" min="1" required>
                            <i class="fa-solid fa-id-card form-icon"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600 mb-1" for="persona-tipo-relacion">Rol en el Arrendamiento <span class="text-danger">*</span></label>
                        <select class="form-select" id="persona-tipo-relacion" name="tipo_relacion" required>
                            <option value="COTITULAR">COTITULAR (Corresponsable contractual)</option>
                            <option value="OCUPANTE" selected>OCUPANTE (Residente autorizado sin firma)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-13 f-w-600 mb-1" for="persona-observaciones">Observaciones</label>
                        <input type="text" class="form-control" id="persona-observaciones" name="observaciones" placeholder="Ej. Parentesco o condición especial">
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Vincular Persona</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dependencias JS del Módulo -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-arrendamientos.js') ?>"></script>
