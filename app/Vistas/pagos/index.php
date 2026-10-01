<?php

declare(strict_types=1);

/**
 * Monitor Principal de Pasarelas de Pago y Conciliación — Camargo PMS (PAGOS-1D).
 *
 * Principios vinculantes:
 * - ALINA DESIGN SYSTEM: Cards equal-card, tablas bordeadas/striped, badges bg-light-* sin bordes punteados ni clases ajenas.
 * - FILTROS INTEGRADOS: Flatpickr data-provider="rangepicker" obligatorio (CERO input[type="date"]).
 * - INVARIANTE C1/C2: Pagos tardíos (DISCREPANCIA_HOLD_EXPIRADO) señalizados con claridad meridiana sin mutar reserva.
 * - CERO JQUERY CRUD: Vanilla JS y Fetch nativo para filtros dinámicos y modal de reembolso.
 * - ZERO-TRUST: Ni llaves privadas, ni PAN/CVV en HTML/DOM.
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array{puede_ver: bool, puede_reembolsar: bool, puede_conciliar: bool} $permisos
 * @var string $csrf_token
 * @var array<string, mixed> $kpis
 * @var array<array<string, mixed>> $transacciones
 * @var array{pagina: int, por_pagina: int, total: int, total_paginas: int} $paginacion
 */

use CamargoPMS\Nucleo\Ayudante;

$formatearDinero = static function (mixed $monto): string {
    return 'S/ ' . number_format((float) ($monto ?? 0), 2, '.', ',');
};

$badgeEstadoPago = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'APROBADO' => '<span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i> Aprobado</span>',
        'PROCESANDO' => '<span class="badge bg-light-info text-info"><i class="fa-solid fa-spinner fa-spin me-1"></i> Procesando</span>',
        'PENDIENTE' => '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> Pendiente</span>',
        'FALLIDO' => '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-circle-xmark me-1"></i> Fallido</span>',
        'EXPIRADO' => '<span class="badge bg-light-secondary text-secondary"><i class="fa-solid fa-hourglass-end me-1"></i> Expirado</span>',
        default => '<span class="badge bg-light-secondary text-secondary">' . Ayudante::escapar($estado) . '</span>',
    };
};

$badgeEstadoConciliacion = static function (string $estado, ?string $subtipo = null): string {
    if ($subtipo === 'DISCREPANCIA_HOLD_EXPIRADO') {
        return '<span class="badge bg-light-danger text-danger border border-danger" title="Pago recibido con reserva expirada">'
            . '<i class="fa-solid fa-triangle-exclamation me-1"></i> PAGO RECIBIDO / RES. EXPIRADA</span>';
    }
    return match (strtoupper($estado)) {
        'CONCILIADO' => '<span class="badge bg-light-success text-success"><i class="fa-solid fa-check-double me-1"></i> Conciliado</span>',
        'PENDIENTE' => '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> Pendiente</span>',
        'DISCREPANCIA' => '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Discrepancia</span>',
        default => '<span class="badge bg-light-secondary text-secondary">' . Ayudante::escapar($estado) . '</span>',
    };
};

$badgeEstadoReembolso = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'NO_APLICA' => '<span class="badge bg-light-secondary text-muted">Sin Reembolso</span>',
        'PENDIENTE' => '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> Reemb. Pendiente</span>',
        'PROCESANDO' => '<span class="badge bg-light-info text-info"><i class="fa-solid fa-spinner fa-spin me-1"></i> Reemb. Procesando</span>',
        'REEMBOLSADO_TOTAL' => '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-arrow-rotate-left me-1"></i> Reembolsado Total</span>',
        'REEMBOLSADO_PARCIAL' => '<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-arrow-rotate-left me-1"></i> Reembolsado Parcial</span>',
        'FALLIDO' => '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Reemb. Fallido</span>',
        default => '<span class="badge bg-light-secondary text-secondary">' . Ayudante::escapar($estado) . '</span>',
    };
};
?>
<input type="hidden" id="csrf-token-global" value="<?= Ayudante::escapar($csrf_token) ?>">

<!-- Encabezado del Monitor de Pasarelas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-credit-card f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Monitor de Pasarelas de Pago</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Supervisión en tiempo real de transacciones digitales, conciliación con reservas y gestión de reembolsos.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-pagos">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- 4 KPIs Operativos en Tiempo Real -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-3">
                    <!-- KPI 1: Volumen Cobrado Aprobado -->
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border h-100">
                            <span class="bg-light-success text-success p-2 rounded-circle me-3">
                                <i class="fa-solid fa-circle-check f-s-20"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Cobros Aprobados</span>
                                <h5 class="mb-0 f-w-700 text-dark" id="kpi-monto-aprobado"><?= $formatearDinero($kpis['monto_aprobado'] ?? 0) ?></h5>
                                <span class="text-muted f-s-11"><span id="kpi-conteo-aprobado"><?= (int) ($kpis['conteo_aprobado'] ?? 0) ?></span> transacciones</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: En Conciliación / Discrepancias -->
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border h-100">
                            <span class="bg-light-warning text-warning p-2 rounded-circle me-3">
                                <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">En Conciliación / Alertas</span>
                                <h5 class="mb-0 f-w-700 text-warning" id="kpi-discrepancias"><?= (int) ($kpis['discrepancias_hold_expirado'] ?? 0) + (int) ($kpis['discrepancias_monto'] ?? 0) ?></h5>
                                <span class="text-muted f-s-11">
                                    <span id="kpi-hold-expirado"><?= (int) ($kpis['discrepancias_hold_expirado'] ?? 0) ?></span> pagos tardíos C1
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 3: Reembolsado Total/Parcial -->
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border h-100">
                            <span class="bg-light-danger text-danger p-2 rounded-circle me-3">
                                <i class="fa-solid fa-arrow-rotate-left f-s-20"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Monto Reembolsado</span>
                                <h5 class="mb-0 f-w-700 text-danger" id="kpi-monto-reembolsado"><?= $formatearDinero($kpis['monto_reembolsado'] ?? 0) ?></h5>
                                <span class="text-muted f-s-11">
                                    <span id="kpi-reembolsos-pendientes"><?= (int) ($kpis['conteo_reembolsos_pendientes'] ?? 0) ?></span> solicitudes pendientes
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 4: Total de Transacciones Registradas -->
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="d-flex align-items-center p-3 bg-white rounded border h-100">
                            <span class="bg-light-primary text-primary p-2 rounded-circle me-3">
                                <i class="fa-solid fa-receipt f-s-20"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Total Transacciones</span>
                                <h5 class="mb-0 f-w-700 text-primary" id="kpi-total-transacciones"><?= (int) ($kpis['total_transacciones'] ?? 0) ?></h5>
                                <span class="text-muted f-s-11">
                                    <span id="kpi-conteo-pendientes"><?= (int) ($kpis['conteo_pendientes_conciliacion'] ?? 0) ?></span> por conciliar
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra de Filtros Combinables Alina -->
            <div class="card-body p-3 bg-white border-bottom">
                <form id="form-filtros-pagos" class="row g-2 align-items-end">
                    <!-- Búsqueda libre -->
                    <div class="col-md-3 col-12">
                        <label for="filtro-buscar" class="form-label f-s-12 f-w-600 mb-1">Buscar Transacción</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-2 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-4" id="filtro-buscar" name="buscar" placeholder="Código, ID externo, cliente...">
                        </div>
                    </div>

                    <!-- Proveedor -->
                    <div class="col-md-2 col-6">
                        <label for="filtro-proveedor" class="form-label f-s-12 f-w-600 mb-1">Pasarela</label>
                        <select class="form-select form-select-sm" id="filtro-proveedor" name="proveedor">
                            <option value="">Todas las pasarelas</option>
                            <option value="CULQI">Culqi</option>
                            <option value="PAYPAL">PayPal</option>
                            <option value="IZIPAY">Izipay</option>
                        </select>
                    </div>

                    <!-- Estado del Pago -->
                    <div class="col-md-2 col-6">
                        <label for="filtro-estado-pago" class="form-label f-s-12 f-w-600 mb-1">Estado Pago</label>
                        <select class="form-select form-select-sm" id="filtro-estado-pago" name="estado_pago">
                            <option value="">Todos los estados</option>
                            <option value="APROBADO">Aprobado</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="PROCESANDO">Procesando</option>
                            <option value="FALLIDO">Fallido</option>
                            <option value="EXPIRADO">Expirado</option>
                        </select>
                    </div>

                    <!-- Estado Conciliación -->
                    <div class="col-md-2 col-6">
                        <label for="filtro-estado-conciliacion" class="form-label f-s-12 f-w-600 mb-1">Conciliación</label>
                        <select class="form-select form-select-sm" id="filtro-estado-conciliacion" name="estado_conciliacion">
                            <option value="">Todas</option>
                            <option value="CONCILIADO">Conciliado</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="DISCREPANCIA">Discrepancia</option>
                        </select>
                    </div>

                    <!-- Rango de Fechas (Flatpickr Range Picker Alina Obligatorio) -->
                    <div class="col-md-3 col-6">
                        <label class="form-label f-s-12 f-w-600 mb-1">Rango de Creación</label>
                        <div class="icon-control position-relative">
                            <i class="fa-solid fa-calendar-days position-absolute top-50 start-0 translate-middle-y ms-2 text-secondary"></i>
                            <input type="text" class="form-control form-control-sm ps-4" id="filtro-rango-fechas"
                                   placeholder="Seleccionar rango..."
                                   data-provider="rangepicker"
                                   data-target-inicio="#filtro-fecha-desde"
                                   data-target-fin="#filtro-fecha-hasta"
                                   readonly>
                        </div>
                        <input type="hidden" id="filtro-fecha-desde" name="fecha_desde" value="">
                        <input type="hidden" id="filtro-fecha-hasta" name="fecha_hasta" value="">
                    </div>

                    <!-- Botones de Acción de Filtro -->
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-limpiar-filtros">
                            <i class="fa-solid fa-eraser me-1"></i> Limpiar Filtros
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-aplicar-filtros">
                            <i class="fa-solid fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla de Transacciones Alina -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-transacciones-pagos">
                        <thead class="bg-light text-uppercase f-s-12 text-secondary">
                            <tr>
                                <th scope="col" class="py-3 px-3">Transacción</th>
                                <th scope="col" class="py-3 px-3">Fecha y Hora</th>
                                <th scope="col" class="py-3 px-3">Reserva / Pagador</th>
                                <th scope="col" class="py-3 px-3 text-end">Monto Cobrado</th>
                                <th scope="col" class="py-3 px-3 text-center">Estado Pago</th>
                                <th scope="col" class="py-3 px-3 text-center">Conciliación</th>
                                <th scope="col" class="py-3 px-3 text-center">Reembolso</th>
                                <th scope="col" class="py-3 px-3 text-center" style="min-width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-transacciones">
                            <?php if (empty($transacciones)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-secondary">
                                    <i class="fa-solid fa-inbox f-s-24 d-block mb-2 text-muted"></i>
                                    No se encontraron transacciones registradas con los filtros seleccionados.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($transacciones as $tx): ?>
                                    <?php
                                    $txId = (int) $tx['id'];
                                    $esAprobado = ($tx['estado_pago'] ?? '') === 'APROBADO';
                                    $saldoDisponible = (float) ($tx['saldo_reembolsable'] ?? 0);
                                    $permiteReembolso = $permisos['puede_reembolsar'] && $esAprobado && $saldoDisponible > 0.00;
                                    $esTardio = ($tx['subtipo_discrepancia'] ?? '') === 'DISCREPANCIA_HOLD_EXPIRADO';
                                    ?>
                                    <tr data-id="<?= $txId ?>" class="<?= $esTardio ? 'table-warning' : '' ?>">
                                        <td class="px-3">
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-light-primary text-primary me-2 f-s-11">
                                                    <?= Ayudante::escapar($tx['proveedor']) ?>
                                                </span>
                                                <div>
                                                    <a href="<?= Ayudante::ruta('/pagos/transacciones/' . $txId) ?>" class="f-w-600 text-dark text-decoration-none">
                                                        <?= Ayudante::escapar($tx['codigo_transaccion']) ?>
                                                    </a>
                                                    <?php if (!empty($tx['transaccion_id_externo'])): ?>
                                                    <div class="f-s-11 text-muted">ID Ext: <code><?= Ayudante::escapar($tx['transaccion_id_externo']) ?></code></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 f-s-12">
                                            <span class="text-dark d-block"><?= Ayudante::escapar(substr($tx['fecha_creacion'] ?? '', 0, 10)) ?></span>
                                            <span class="text-muted f-s-11"><?= Ayudante::escapar(substr($tx['fecha_creacion'] ?? '', 11, 8)) ?></span>
                                        </td>
                                        <td class="px-3 f-s-12">
                                            <?php if (!empty($tx['reserva_codigo'])): ?>
                                                <a href="<?= Ayudante::ruta('/reservas/' . (int) $tx['reserva_id']) ?>" class="badge bg-light-secondary text-dark text-decoration-none mb-1">
                                                    <i class="fa-solid fa-bookmark me-1 text-primary"></i> <?= Ayudante::escapar($tx['reserva_codigo']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light-secondary text-muted mb-1">Sin Reserva</span>
                                            <?php endif; ?>
                                            <div class="text-dark f-w-500"><?= Ayudante::escapar($tx['pagador_nombre'] ?: '—') ?></div>
                                            <div class="text-muted f-s-11"><?= Ayudante::escapar($tx['pagador_email'] ?: '') ?></div>
                                        </td>
                                        <td class="px-3 text-end f-s-13">
                                            <strong class="text-dark d-block"><?= $formatearDinero($tx['monto_cobrado']) ?></strong>
                                            <?php if ((float) ($tx['monto_reembolsado'] ?? 0) > 0): ?>
                                                <span class="text-danger f-s-11 d-block">-<?= $formatearDinero($tx['monto_reembolsado']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 text-center">
                                            <?= $badgeEstadoPago($tx['estado_pago']) ?>
                                        </td>
                                        <td class="px-3 text-center">
                                            <?= $badgeEstadoConciliacion($tx['estado_conciliacion'], $tx['subtipo_discrepancia'] ?? null) ?>
                                        </td>
                                        <td class="px-3 text-center">
                                            <?= $badgeEstadoReembolso($tx['estado_reembolso']) ?>
                                        </td>
                                        <td class="px-3 text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?= Ayudante::ruta('/pagos/transacciones/' . $txId) ?>" class="btn btn-outline-secondary" title="Ver Detalle Completo">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>
                                                <?php if ($permiteReembolso): ?>
                                                <button type="button" class="btn btn-outline-danger btn-abrir-reembolso"
                                                        data-id="<?= $txId ?>"
                                                        data-codigo="<?= Ayudante::escapar($tx['codigo_transaccion']) ?>"
                                                        data-cobrado="<?= (float) $tx['monto_cobrado'] ?>"
                                                        data-reembolsado="<?= (float) $tx['monto_reembolsado'] ?>"
                                                        data-disponible="<?= $saldoDisponible ?>"
                                                        title="Emitir Reembolso">
                                                    <i class="fa-solid fa-arrow-rotate-left"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación Dinámica Alina -->
            <div class="card-footer bg-white py-3 border-top d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-secondary f-s-13" id="info-paginacion">
                    Mostrando <strong id="pagina-actual"><?= (int) $paginacion['pagina'] ?></strong> de <strong id="total-paginas"><?= max(1, (int) $paginacion['total_paginas']) ?></strong> (Total: <span id="total-registros"><?= (int) $paginacion['total'] ?></span> transacciones)
                </span>
                <nav aria-label="Navegación de transacciones">
                    <ul class="pagination pagination-sm mb-0" id="contenedor-paginacion">
                        <!-- Generado dinámicamente por camargo-pagos.js -->
                    </ul>
                </nav>
            </div>
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
