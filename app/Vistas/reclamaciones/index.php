<?php

declare(strict_types=1);

/**
 * Vista del Directorio Interno del Libro de Reclamaciones — Camargo PMS (RECLAMACIONES-1).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array<int, \CamargoPMS\Modelos\Propiedad> $propiedades
 * @var array<int, \CamargoPMS\Modelos\TipoDocumento> $tiposDoc
 * @var array<string, int> $kpis
 * @var array<string, bool> $permisos
 * @var string $csrf_token
 * @var string $titulo
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= htmlspecialchars($csrf_token) ?>">

<!-- Encabezado del Módulo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-book-open-reader f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Libro de Reclamaciones</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Supervisión y control de expedientes regulatorios conforme a la Ley N° 29571 y Ley N° 31435 (15 días hábiles).
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($permisos['puede_crear'])): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-nueva-reclamacion">
                        <i class="fa-solid fa-plus me-1"></i> Registro Asistido
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- KPIs Regulatorios -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-3">
                    <div class="col-md-2 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-primary-subtle text-primary p-2 rounded-circle me-2">
                                <i class="fa-solid fa-folder-open f-s-16"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-11 d-block">Total Registrados</span>
                                <strong class="f-s-16 text-dark" id="kpi-total"><?= (int) ($kpis['total'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-info-subtle text-info p-2 rounded-circle me-2">
                                <i class="fa-solid fa-clock f-s-16"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-11 d-block">En Trámite</span>
                                <strong class="f-s-16 text-info" id="kpi-en-proceso"><?= (int) ($kpis['en_proceso'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-warning-subtle text-warning p-2 rounded-circle me-2">
                                <i class="fa-solid fa-pause f-s-16"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-11 d-block">Suspendidos</span>
                                <strong class="f-s-16 text-warning" id="kpi-suspendidos"><?= (int) ($kpis['suspendidos'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-danger-subtle text-danger p-2 rounded-circle me-2">
                                <i class="fa-solid fa-triangle-exclamation f-s-16"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-11 d-block">Vencidos (+15 d.h.)</span>
                                <strong class="f-s-16 text-danger" id="kpi-vencidos"><?= (int) ($kpis['vencidos'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-warning-subtle text-dark p-2 rounded-circle me-2">
                                <i class="fa-solid fa-hourglass-half f-s-16 text-warning"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-11 d-block">Por Vencer (&le;3 d.)</span>
                                <strong class="f-s-16 text-dark" id="kpi-por-vencer"><?= (int) ($kpis['por_vencer'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-success-subtle text-success p-2 rounded-circle me-2">
                                <i class="fa-solid fa-circle-check f-s-16"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-11 d-block">Atendidos / Acuerdo</span>
                                <strong class="f-s-16 text-success" id="kpi-atendidos"><?= (int) ($kpis['atendidos'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros de Consulta -->
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda" placeholder="Buscar por código, nombre o doc...">
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-propiedad">
                            <option value="">Todas las sedes</option>
                            <?php foreach ($propiedades as $p): ?>
                                <option value="<?= htmlspecialchars((string) $p->obtenerId()) ?>"><?= htmlspecialchars($p->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado">
                            <option value="">Todos los estados</option>
                            <option value="REGISTRADO">Registrado</option>
                            <option value="EN_PROCESO">En Proceso</option>
                            <option value="SUSPENDIDO_OFRECIMIENTO">Suspendido (Ofrecimiento)</option>
                            <option value="ATENDIDO">Atendido</option>
                            <option value="CONCLUIDO_POR_ACUERDO">Concluido por Acuerdo</option>
                            <option value="ANULADO">Anulado</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-tipo">
                            <option value="">Reclamos y Quejas</option>
                            <option value="RECLAMO">Reclamos</option>
                            <option value="QUEJA">Quejas</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-6">
                        <select class="form-select form-select-sm" id="filtro-anio">
                            <option value=""><?= date('Y') ?></option>
                            <option value="<?= (int)date('Y') - 1 ?>"><?= (int)date('Y') - 1 ?></option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-limpiar-filtros">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Expedientes -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabla-reclamaciones">
                    <thead class="table-light">
                        <tr class="f-s-12 text-uppercase text-secondary">
                            <th class="ps-3">Hoja / Código</th>
                            <th>Fecha</th>
                            <th>Sede</th>
                            <th>Consumidor</th>
                            <th>Tipo / Bien</th>
                            <th>Estado</th>
                            <th>Semáforo Legal</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-reclamaciones" class="f-s-13">
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando expedientes...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="text-secondary f-s-12" id="info-paginacion">Mostrando registros</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="paginador-contenedor"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registro Asistido en Recepción -->
<?php if (!empty($permisos['puede_crear'])): ?>
<div class="modal fade" id="modal-nueva-reclamacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 f-w-700">
                    <i class="fa-solid fa-file-circle-plus text-primary me-2"></i> Registro Asistido en Recepción
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-asistido" class="app-form app-icon-form" method="POST" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Establecimiento / Sede</label>
                            <select class="form-select basic-select2" name="propiedad_id" required>
                                <option value="" selected disabled>-- Seleccione sede --</option>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= htmlspecialchars((string) $p->obtenerId()) ?>"><?= htmlspecialchars($p->obtenerNombre()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Tipo Solicitud</label>
                            <select class="form-select basic-select2" name="tipo" required>
                                <option value="RECLAMO" selected>RECLAMO</option>
                                <option value="QUEJA">QUEJA</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Tipo Bien</label>
                            <select class="form-select basic-select2" name="tipo_bien" required>
                                <option value="SERVICIO" selected>SERVICIO</option>
                                <option value="PRODUCTO">PRODUCTO</option>
                            </select>
                        </div>

                        <!-- Consumidor -->
                        <div class="col-12"><hr class="my-1"><h6 class="f-s-13 fw-bold text-secondary">Datos del Consumidor</h6></div>

                        <div class="col-md-3">
                            <label class="form-label required">Tipo Doc.</label>
                            <select class="form-select basic-select2" name="consumidor_tipo_documento" required>
                                <?php foreach ($tiposDoc as $td): ?>
                                    <option value="<?= htmlspecialchars($td->obtenerCodigo()) ?>"><?= htmlspecialchars($td->obtenerCodigo()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">N° Documento</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-id-card position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="consumidor_numero_documento" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label required">Teléfono / Celular</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-phone position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="tel" class="form-control ps-5" name="consumidor_telefono" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Nombres</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="consumidor_nombres" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Apellidos</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-user-tag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="consumidor_apellidos" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Correo Electrónico</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-envelope position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="email" class="form-control ps-5" name="consumidor_email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Domicilio</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-location-dot position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="consumidor_direccion" required>
                            </div>
                        </div>

                        <!-- Reclamación -->
                        <div class="col-12"><hr class="my-1"><h6 class="f-s-13 fw-bold text-secondary">Contenido de la Reclamación</h6></div>

                        <div class="col-md-3">
                            <label class="form-label">Monto</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-money-bill position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="number" step="0.01" class="form-control ps-5" name="monto_reclamado" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Moneda</label>
                            <select class="form-select basic-select2" name="moneda">
                                <option value="PEN">PEN (S/)</option>
                                <option value="USD">USD ($)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Descripción Bien / Servicio</label>
                            <div class="icon-control position-relative">
                                <i class="fa-solid fa-tag position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                <input type="text" class="form-control ps-5" name="descripcion_bien" placeholder="Ej. Habitación 302, Desayuno buffet" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label required">Detalle de los Hechos</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-comment-dots position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="detalle_reclamacion" rows="3" placeholder="Detalle manifestado por el consumidor..." required></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label required">Pedido Concreto</label>
                            <div class="icon-control position-relative icon-textarea">
                                <i class="fa-solid fa-bullhorn position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                <textarea class="form-control ps-5" name="pedido_consumidor" rows="2" placeholder="Qué solicita el consumidor..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-asistido">
                        <i class="fa-solid fa-save me-1"></i> Asentar en Libro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.getElementById('csrf-token-global').value;
    var paginaActual = 1;

    function cargarExpedientes(pagina) {
        paginaActual = pagina || 1;
        var tbody = document.getElementById('tbody-reclamaciones');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando expedientes...</td></tr>';

        var params = new URLSearchParams({
            pagina: paginaActual,
            busqueda: document.getElementById('filtro-busqueda').value,
            propiedad_id: document.getElementById('filtro-propiedad').value,
            estado: document.getElementById('filtro-estado').value,
            tipo: document.getElementById('filtro-tipo').value,
            anio: document.getElementById('filtro-anio').value
        });

        fetch('<?= url_ruta('/reclamaciones/datos') ?>?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.exito) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">' + (data.mensaje || 'Error al cargar') + '</td></tr>';
                return;
            }

            // Actualizar KPIs
            if (data.kpis) {
                document.getElementById('kpi-total').textContent = data.kpis.total || 0;
                document.getElementById('kpi-en-proceso').textContent = data.kpis.en_proceso || 0;
                document.getElementById('kpi-suspendidos').textContent = data.kpis.suspendidos || 0;
                document.getElementById('kpi-vencidos').textContent = data.kpis.vencidos || 0;
                document.getElementById('kpi-por-vencer').textContent = data.kpis.por_vencer || 0;
                document.getElementById('kpi-atendidos').textContent = data.kpis.atendidos || 0;
            }

            if (!data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron expedientes con los criterios seleccionados.</td></tr>';
                document.getElementById('info-paginacion').textContent = '0 registros encontrados';
                document.getElementById('paginador-contenedor').innerHTML = '';
                return;
            }

            var html = '';
            data.datos.forEach(function(rec) {
                var semaforo = rec.semaforo || {};
                var badgeColor = 'secondary';
                if (semaforo.color) {
                    badgeColor = semaforo.color;
                }

                var tipoBadge = rec.tipo === 'RECLAMO' 
                    ? '<span class="badge bg-primary-subtle text-primary">RECLAMO</span>' 
                    : '<span class="badge bg-warning-subtle text-dark">QUEJA</span>';

                var estadoBadge = '<span class="badge bg-secondary">' + rec.estado + '</span>';
                if (rec.estado === 'REGISTRADO' || rec.estado === 'EN_PROCESO') {
                    estadoBadge = '<span class="badge bg-info-subtle text-info">' + rec.estado + '</span>';
                } else if (rec.estado === 'SUSPENDIDO_OFRECIMIENTO') {
                    estadoBadge = '<span class="badge bg-warning-subtle text-dark">SUSPENDIDO</span>';
                } else if (rec.estado === 'ATENDIDO' || rec.estado === 'CONCLUIDO_POR_ACUERDO') {
                    estadoBadge = '<span class="badge bg-success-subtle text-success">' + rec.estado + '</span>';
                } else if (rec.estado === 'ANULADO') {
                    estadoBadge = '<span class="badge bg-dark">ANULADO</span>';
                }

                var fechaTxt = rec.fecha_interposicion ? rec.fecha_interposicion.substring(0, 10) : '';
                var consumidorNom = rec.snapshot_consumidor && rec.snapshot_consumidor.nombre_completo ? rec.snapshot_consumidor.nombre_completo : (rec.consumidor ? (rec.consumidor.nombres + ' ' + (rec.consumidor.apellido_paterno || '')) : '-');
                var sedeNom = rec.propiedad ? rec.propiedad.nombre : '-';

                html += '<tr>' +
                    '<td class="ps-3"><strong class="text-primary">' + rec.codigo_hoja + '</strong><br><span class="text-muted f-s-11">' + rec.codigo_interno + '</span></td>' +
                    '<td>' + fechaTxt + '</td>' +
                    '<td>' + sedeNom + '</td>' +
                    '<td><strong>' + consumidorNom + '</strong></td>' +
                    '<td>' + tipoBadge + '<br><span class="text-muted f-s-11">' + rec.tipo_bien + '</span></td>' +
                    '<td>' + estadoBadge + '</td>' +
                    '<td><span class="badge bg-' + badgeColor + '">' + (semaforo.etiqueta || '-') + '</span></td>' +
                    '<td class="text-end pe-3">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<a href="' + rec.url_detalle + '" class="btn btn-outline-primary" title="Ver Expediente"><i class="fa-solid fa-eye"></i></a>' +
                            '<a href="' + rec.url_pdf + '" class="btn btn-outline-secondary" target="_blank" title="Descargar PDF"><i class="fa-solid fa-file-pdf"></i></a>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
            });

            tbody.innerHTML = html;
            renderPaginacion(data.paginacion);
        })
        .catch(function(err) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Error de conexión al cargar expedientes.</td></tr>';
        });
    }

    function renderPaginacion(pag) {
        if (!pag) return;
        document.getElementById('info-paginacion').textContent = 'Página ' + pag.pagina_actual + ' de ' + pag.total_paginas + ' (' + pag.total_registros + ' registros)';

        var ul = document.getElementById('paginador-contenedor');
        ul.innerHTML = '';
        if (pag.total_paginas <= 1) return;

        for (var i = 1; i <= pag.total_paginas; i++) {
            var li = document.createElement('li');
            li.className = 'page-item ' + (i === pag.pagina_actual ? 'active' : '');
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = i;
            (function(p) {
                a.onclick = function(e) {
                    e.preventDefault();
                    cargarExpedientes(p);
                };
            })(i);
            li.appendChild(a);
            ul.appendChild(li);
        }
    }

    // Eventos filtros
    document.getElementById('filtro-busqueda').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') cargarExpedientes(1);
    });
    document.getElementById('filtro-propiedad').addEventListener('change', function() { cargarExpedientes(1); });
    document.getElementById('filtro-estado').addEventListener('change', function() { cargarExpedientes(1); });
    document.getElementById('filtro-tipo').addEventListener('change', function() { cargarExpedientes(1); });
    document.getElementById('filtro-anio').addEventListener('change', function() { cargarExpedientes(1); });
    document.getElementById('btn-recargar').addEventListener('click', function() { cargarExpedientes(paginaActual); });
    document.getElementById('btn-limpiar-filtros').addEventListener('click', function() {
        document.getElementById('filtro-busqueda').value = '';
        document.getElementById('filtro-propiedad').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-tipo').value = '';
        document.getElementById('filtro-anio').value = '';
        cargarExpedientes(1);
    });

    // Form asistido submit
    var formAsistido = document.getElementById('form-asistido');
    if (formAsistido) {
        formAsistido.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-asistido');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';

            var formData = new FormData(formAsistido);
            var payload = {};
            formData.forEach(function(v, k) { payload[k] = v; });

            fetch('<?= url_ruta('/reclamaciones/crear-asistido') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save me-1"></i> Asentar en Libro';
                if (!data.exito) {
                    alert('Error: ' + (data.mensaje || 'Error al guardar reclamación asistida.'));
                    return;
                }
                var modal = bootstrap.Modal.getInstance(document.getElementById('modal-nueva-reclamacion'));
                if (modal) modal.hide();
                formAsistido.reset();
                cargarExpedientes(1);
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save me-1"></i> Asentar en Libro';
                alert('Fallo de comunicación con el servidor.');
            });
        });
    }

    // Carga inicial
    cargarExpedientes(1);
});
</script>
