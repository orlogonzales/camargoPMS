<?php

declare(strict_types=1);

/**
 * Vista del Centro Operacional de Recepción — Tape Chart y Rack Hotelero (TAPE-CHART-1 / D-084).
 *
 * Principios vinculantes:
 * - TAPE CHART != FUENTE DE VERDAD (proyección en memoria sin tablas físicas).
 * - Componentes y directrices visuales oficiales de Alina (UI-2, UI-3A, D-071, D-075).
 * - Doble eje de trabajo: Tablero Calendario continuo + Rack Operacional de Hoy.
 * - KPIs del Rack de hoy clicables como filtros operacionales.
 *
 * @var array<string, bool> $capacidades
 * @var array<int, array{id: int, codigo: string, nombre: string, zona_horaria: ?string}> $propiedades
 * @var int $propiedadSeleccionadaId
 * @var array<int, array{id: int, codigo: string, nombre: string}> $tiposUnidad
 * @var string $fechaHoteleraHoy
 * @var string $fechaDefectoDesde
 * @var string $fechaDefectoHasta
 * @var string $zonaHoraria
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado y Barra de Filtros del Centro Operacional -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-calendar-week f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Centro Operacional de Recepción</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Tape Chart interactivo y Rack de Habitaciones en tiempo real. Zona horaria: <strong><?= e($zonaHoraria) ?></strong> (D-066 / D-084).
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm b-r-20" id="btn-refrescar-tape-chart" title="Actualizar datos en vivo">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Actualizar
                    </button>
                    <?php if (!empty($capacidades['puede_crear_reserva'])): ?>
                        <a href="<?= url_ruta('/reservas') ?>" class="btn btn-primary btn-sm b-r-20">
                            <i class="fa-solid fa-plus me-1"></i> Nueva Reserva
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barra de Controles Operativos (Propiedad, Horizontes y Flatpickr) -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-2 align-items-center">
                    <!-- Selector de Propiedad -->
                    <div class="col-lg-3 col-md-4 col-12">
                        <label class="form-label f-s-12 text-secondary mb-1">Inmueble / Propiedad:</label>
                        <select class="form-select b-r-20 basic-select2" id="filtro-propiedad">
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $p['id'] === $propiedadSeleccionadaId ? 'selected' : '' ?>>
                                    <?= e($p['nombre']) ?> (<?= e($p['codigo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Horizontes Rápidos -->
                    <div class="col-lg-3 col-md-4 col-12 text-center text-md-start">
                        <label class="form-label f-s-12 text-secondary mb-1">Horizonte de Días:</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <button type="button" class="btn btn-outline-primary btn-horizonte" data-dias="7">7 Días</button>
                            <button type="button" class="btn btn-primary btn-horizonte active" data-dias="14">14 Días</button>
                            <button type="button" class="btn btn-outline-primary btn-horizonte" data-dias="30">30 Días</button>
                        </div>
                    </div>

                    <!-- Navegación Temporal (Anterior, Hoy, Siguiente) -->
                    <div class="col-lg-3 col-md-4 col-12 text-center text-md-start">
                        <label class="form-label f-s-12 text-secondary mb-1">Navegación:</label>
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="btn-nav-anterior" title="Retroceder horizonte">
                                <i class="fa-solid fa-chevron-left"></i> Anterior
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-nav-hoy" title="Ir a la fecha de hoy">
                                Hoy
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-nav-siguiente" title="Avanzar horizonte">
                                Siguiente <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Selector de Rango Personalizado Alina Flatpickr -->
                    <div class="col-lg-3 col-12">
                        <label class="form-label f-s-12 text-secondary mb-1">Rango Personalizado:</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white b-r-left-20">
                                <i class="fa-regular fa-calendar text-secondary"></i>
                            </span>
                            <input type="text" class="form-control form-control-sm b-r-right-20 flatpickr-range" id="filtro-rango-personalizado" placeholder="Seleccionar rango..." readonly>
                        </div>
                    </div>
                </div>

                <!-- Filtros secundarios colapsables (Tipología y Piso) -->
                <div class="row g-2 mt-1 pt-2 border-top">
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm b-r-20" id="filtro-tipo-unidad">
                            <option value="">Todas las tipologías</option>
                            <?php foreach ($tiposUnidad as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <input type="text" class="form-control form-control-sm b-r-20" id="filtro-piso" placeholder="Filtrar por piso/ala...">
                    </div>
                    <div class="col-md-6 col-12 d-flex align-items-center justify-content-end gap-2 text-secondary f-s-12">
                        <span><i class="fa-solid fa-circle text-primary me-1"></i> Estadía</span>
                        <span><i class="fa-solid fa-circle text-success me-1"></i> Reserva</span>
                        <span><i class="fa-solid fa-circle text-info me-1"></i> Arriendo</span>
                        <span><i class="fa-solid fa-circle text-dark me-1"></i> Mantenimiento OOO</span>
                    </div>
                </div>
            </div>

            <!-- KPIs Operacionales del Rack de Hoy (Clicables como Filtros - Ajuste 12) -->
            <div class="card-body p-3 bg-white border-bottom">
                <div class="row g-2 text-center" id="contenedor-kpis-hoy">
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card activo" data-filtro="TODAS" title="Mostrar todas las unidades">
                            <span class="text-secondary f-s-11 text-uppercase d-block">Total Unidades</span>
                            <span class="f-s-18 f-w-700 text-dark" id="kpi-total-unidades">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-primary text-primary" data-filtro="STAYOVER" title="Huéspedes en casa hoy">
                            <span class="text-primary f-s-11 text-uppercase d-block f-w-600">En Casa (Stay-over)</span>
                            <span class="f-s-18 f-w-700" id="kpi-stayover">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-success text-success" data-filtro="ARRIVAL" title="Llegadas / Check-ins previstos hoy">
                            <span class="text-success f-s-11 text-uppercase d-block f-w-600">Llegadas Hoy</span>
                            <span class="f-s-18 f-w-700" id="kpi-arrivals">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-warning text-warning" data-filtro="DEPARTURE" title="Salidas / Check-outs de hoy">
                            <span class="text-warning f-s-11 text-uppercase d-block f-w-600">Salidas Hoy</span>
                            <span class="f-s-18 f-w-700" id="kpi-departures">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-success text-success border-success" data-filtro="VR" title="Vacantes listas para check-in (VR)">
                            <span class="text-success f-s-11 text-uppercase d-block f-w-600">Listas (VR)</span>
                            <span class="f-s-18 f-w-700" id="kpi-vr">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-danger text-danger border-danger" data-filtro="VD" title="Vacantes sucias por limpiar (VD)">
                            <span class="text-danger f-s-11 text-uppercase d-block f-w-600">Sucias (VD)</span>
                            <span class="f-s-18 f-w-700" id="kpi-vd">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-info text-info border-info" data-filtro="VCL" title="En limpieza o por inspeccionar (VCL)">
                            <span class="text-info f-s-11 text-uppercase d-block f-w-600">En Limpieza</span>
                            <span class="f-s-18 f-w-700" id="kpi-vcl">-</span>
                        </div>
                    </div>
                    <div class="col-lg col-md-3 col-6">
                        <div class="p-2 border b-r-8 kpi-filtro-card bg-light-dark text-dark border-dark" data-filtro="OOO" title="Fuera de servicio por mantenimiento">
                            <span class="text-dark f-s-11 text-uppercase d-block f-w-600">Mantenimiento</span>
                            <span class="f-s-18 f-w-700" id="kpi-ooo">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navegación por Pestañas Alina (Tape Chart vs Rack de Hoy) -->
<div class="row">
    <div class="col-12">
        <ul class="nav nav-tabs nav-tabs-primary mb-3" id="pestañas-tape-chart" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active f-w-600" id="tab-calendario" data-bs-toggle="tab" data-bs-target="#panel-calendario" type="button" role="tab">
                    <i class="fa-solid fa-table-cells me-1"></i> Tablero Tape Chart
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tab-rack-hoy" data-bs-toggle="tab" data-bs-target="#panel-rack-hoy" type="button" role="tab">
                    <i class="fa-solid fa-door-open me-1"></i> Rack de Habitaciones de Hoy
                </button>
            </li>
        </ul>

        <div class="tab-content" id="contenido-pestañas-tape-chart">
            <!-- PANEL 1: TABLERO TAPE CHART CONTINUO -->
            <div class="tab-pane fade show active" id="panel-calendario" role="tabpanel">
                <!-- Estado de Carga (Skeleton) -->
                <div id="tape-chart-skeleton" class="p-5 text-center bg-white b-r-12 border">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Cargando proyección operacional...</span>
                    </div>
                    <h5 class="f-s-16 f-w-600 text-dark">Consultando proyección del Tape Chart...</h5>
                    <p class="text-secondary f-s-13 mb-0">Extrayendo disponibilidad sparse, reservas, estadías, contratos y limpieza en bloque.</p>
                </div>

                <!-- Estado de Error Defensivo con Reintento (Ajuste 16) -->
                <div id="tape-chart-error" class="p-5 text-center bg-white b-r-12 border d-none">
                    <div class="text-danger mb-3">
                        <i class="fa-solid fa-circle-exclamation f-s-40"></i>
                    </div>
                    <h5 class="f-s-16 f-w-700 text-danger" id="tape-chart-error-mensaje">Error al cargar el Tape Chart</h5>
                    <p class="text-secondary f-s-13 mb-3">Ocurrió un problema de comunicación con el servidor. Un fallo de carga no representa disponibilidad vacante.</p>
                    <button type="button" class="btn btn-outline-danger btn-sm b-r-20" id="btn-reintentar-carga">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Reintentar Carga
                    </button>
                </div>

                <!-- Contenedor de la Cuadrícula del Tape Chart -->
                <div id="tape-chart-wrapper" class="tape-chart-container d-none table-responsive" data-simplebar>
                    <table class="tape-chart-table table table-hover" id="tabla-tape-chart">
                        <thead>
                            <tr id="tape-chart-header-row">
                                <!-- Generado dinámicamente por JS -->
                            </tr>
                        </thead>
                        <tbody id="tape-chart-body">
                            <!-- Filas generadas dinámicamente por JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PANEL 2: RACK OPERACIONAL DE HOY (VISTA DETALLADA) -->
            <div class="tab-pane fade" id="panel-rack-hoy" role="tabpanel">
                <div class="card equal-card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="row g-3" id="contenedor-rack-hoy-cards">
                            <!-- Generado dinámicamente por JS con tarjetas operacionales de unidades -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONTEXTUAL DE DETALLE Y ACCIONES SEGÚN RBAC (Ajuste 14) -->
<div class="modal fade" id="modal-detalle-celda" tabindex="-1" aria-labelledby="modalDetalleCeldaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center">
                    <span class="p-2 b-r-8 me-2" id="modal-celda-icono-box">
                        <i class="fa-solid fa-door-open f-s-18" id="modal-celda-icono"></i>
                    </span>
                    <div>
                        <h5 class="modal-title f-s-16 f-w-700 mb-0" id="modalDetalleCeldaTitulo">Detalle Operativo de Unidad</h5>
                        <span class="text-secondary f-s-12" id="modal-celda-subtitulo">Unidad 101 — 28 Sep 2026</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Alertas de Conflicto Concurrente (Ajuste 3) -->
                <div id="modal-celda-conflictos-wrapper" class="mb-3 d-none">
                    <div class="alert alert-danger py-2 px-3 b-r-8 mb-0 d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation me-2 f-s-18"></i>
                        <div>
                            <strong class="d-block f-s-13">Conflicto Operacional Detectado</strong>
                            <span class="f-s-12" id="modal-celda-conflicto-texto">Esta celda presenta una colisión entre mantenimiento y reserva.</span>
                        </div>
                    </div>
                </div>

                <!-- Resumen de Datos de la Celda -->
                <div class="bg-light p-3 b-r-12 mb-3">
                    <div class="row g-2 f-s-13">
                        <div class="col-6">
                            <span class="text-secondary d-block f-s-11">Estado Operacional:</span>
                            <span class="f-w-600" id="modal-celda-estado-texto">VACANTE (VR)</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block f-s-11">Limpieza de Hoy:</span>
                            <span class="badge" id="modal-celda-limpieza-badge">Limpia Lista (VR)</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block f-s-11">Titular / Huésped:</span>
                            <span class="f-w-600" id="modal-celda-titular">Ninguno</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block f-s-11">Documento:</span>
                            <span class="f-w-600" id="modal-celda-documento">-</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block f-s-11">Código Referencia:</span>
                            <span class="f-w-600 font-monospace text-primary" id="modal-celda-codigo">-</span>
                        </div>
                        <div class="col-6">
                            <span class="text-secondary d-block f-s-11">Duración:</span>
                            <span class="f-w-600" id="modal-celda-duracion">1 noche</span>
                        </div>
                    </div>
                </div>

                <!-- Botonera de Acciones Candidatas Autorizadas por RBAC -->
                <h6 class="f-s-13 f-w-700 text-secondary text-uppercase mb-2">Acciones Disponibles:</h6>
                <div class="d-grid gap-2" id="modal-celda-acciones">
                    <!-- Los botones de acción se inyectan dinámicamente según permisos -->
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm b-r-20" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Carga del Módulo JavaScript Moderno del Tape Chart -->
<script src="/assets/js/modulos/tape-chart.js"></script>
