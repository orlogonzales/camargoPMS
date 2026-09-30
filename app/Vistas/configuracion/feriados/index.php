<?php

declare(strict_types=1);

/**
 * Vista de Gestión de Calendario de Feriados — Camargo PMS (RECLAMACIONES-1).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var int $anioActual
 * @var array<int, \CamargoPMS\Modelos\Feriado> $feriados
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
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-calendar-days f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Calendario Oficial de Feriados y Días No Laborables</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Base de cómputo para el plazo legal de 15 días hábiles improrrogables del Libro de Reclamaciones (Ley N° 31435).
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-feriado" id="btn-nuevo-feriado">
                        <i class="fa-solid fa-calendar-plus me-1"></i> Añadir Fecha
                    </button>
                    <a href="<?= url_ruta('/reclamaciones') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-book-open-reader me-1"></i> Ir al Libro
                    </a>
                </div>
            </div>

            <!-- Panel Informativo Regulatorio -->
            <div class="card-body p-3 bg-light border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-9 f-s-12 text-secondary">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i>
                        <strong>Criterio Legal de Exclusión:</strong> Solo se descuentan del cómputo los días que sean <strong>Lunes a Viernes</strong>, que tengan <strong>Aplica a Sector Privado = Sí</strong> y se encuentren en estado <strong>Activo</strong>.
                    </div>
                    <div class="col-md-3 text-md-end">
                        <div class="d-inline-flex align-items-center gap-2">
                            <label class="form-label f-s-12 mb-0 fw-bold">Año:</label>
                            <select class="form-select form-select-sm" id="selector-anio" style="width: 110px;">
                                <option value="2026" <?= $anioActual === 2026 ? 'selected' : '' ?>>2026</option>
                                <option value="2027" <?= $anioActual === 2027 ? 'selected' : '' ?>>2027</option>
                                <option value="2028">2028</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Feriados -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0" id="tabla-feriados">
                    <thead class="table-light">
                        <tr class="f-s-12 text-uppercase text-secondary">
                            <th class="ps-3">Fecha</th>
                            <th>Día</th>
                            <th>Descripción Oficial</th>
                            <th>Tipo Regulatorio</th>
                            <th class="text-center">Aplica Sector Privado</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-feriados" class="f-s-13">
                        <?php if (empty($feriados)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No se registran feriados para el año seleccionado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feriados as $f): 
                                $dt = new DateTimeImmutable($f->obtenerFecha());
                                $diaNombre = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][(int)$dt->format('w')];
                                $esExcluyente = $f->esExcluyenteParaSectorPrivado();
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold <?= $esExcluyente ? 'text-danger' : 'text-dark' ?>">
                                    <?= date('d/m/Y', strtotime($f->obtenerFecha())) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= $diaNombre ?></span></td>
                                <td><?= htmlspecialchars($f->obtenerDescripcion()) ?></td>
                                <td>
                                    <?php if ($f->obtenerTipo() === \CamargoPMS\Modelos\Feriado::TIPO_FERIADO_LEGAL): ?>
                                        <span class="badge bg-light-primary text-primary">FERIADO LEGAL</span>
                                    <?php else: ?>
                                        <span class="badge bg-light-warning text-warning">NO LABORABLE</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($f->aplicaSectorPrivado()): ?>
                                        <span class="badge bg-light-success text-success"><i class="fa-solid fa-check me-1"></i> Sí (Pausa)</span>
                                    <?php else: ?>
                                        <span class="badge bg-light-secondary text-secondary">No (Solo público)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($f->esActivo()): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-alternar-feriado" data-id="<?= $f->obtenerId() ?>" title="Alternar activo/inactivo">
                                        <i class="fa-solid fa-power-off"></i>
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

<!-- Modal Añadir / Editar Feriado -->
<div class="modal fade" id="modal-feriado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content b-r-16">
            <div class="modal-header border-bottom">
                <h5 class="modal-title f-s-16 fw-bold"><i class="fa-solid fa-calendar-plus text-primary me-2"></i> Fecha en Calendario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-feriado">
                <input type="hidden" name="id" id="feriado_id" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label required">Fecha (YYYY-MM-DD)</label>
                        <input type="date" class="form-control" name="fecha" id="feriado_fecha" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Descripción / Conmemoración</label>
                        <input type="text" class="form-control" name="descripcion" id="feriado_descripcion" placeholder="Ej. Día del Trabajo, San Pedro y San Pablo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Tipo de Declaración</label>
                        <select class="form-select" name="tipo" id="feriado_tipo" required>
                             <option value="FERIADO_LEGAL" selected>Feriado Legal Nacional (D.L. 713 / Ley)</option>
                            <option value="NO_LABORABLE_COMPENSABLE">Día No Laborable Compensable (D.S.)</option>
                        </select>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="aplica_sector_privado" id="feriado_aplica_privado" value="1" checked>
                        <label class="form-check-label fw-bold" for="feriado_aplica_privado">
                            Aplica al Sector Privado (descontar del cómputo legal)
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="feriado_activo" value="1" checked>
                        <label class="form-check-label" for="feriado_activo">
                            Estado Activo
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-feriado">Guardar Feriado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.getElementById('csrf-token-global').value;

    document.getElementById('selector-anio').addEventListener('change', function() {
        var anio = this.value;
        fetch('<?= url_ruta('/configuracion/feriados/datos') ?>?anio=' + anio, {
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.exito) return;
            var tbody = document.getElementById('tbody-feriados');
            if (!data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se registran feriados para el año seleccionado.</td></tr>';
                return;
            }
            var html = '';
            data.datos.forEach(function(f) {
                var tipoBadge = f.tipo === 'FERIADO_LEGAL'
                    ? '<span class="badge bg-light-primary text-primary">FERIADO LEGAL</span>'
                    : '<span class="badge bg-light-warning text-warning">NO LABORABLE</span>';
                var privBadge = f.aplica_sector_privado
                    ? '<span class="badge bg-light-success text-success"><i class="fa-solid fa-check me-1"></i> Sí (Pausa)</span>'
                    : '<span class="badge bg-light-secondary text-secondary">No (Solo público)</span>';
                var actBadge = f.activo
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';

                html += '<tr>' +
                    '<td class="ps-3 fw-bold ' + (f.es_excluyente_privado ? 'text-danger' : 'text-dark') + '">' + f.fecha + '</td>' +
                    '<td><span class="badge bg-light text-dark border">-</span></td>' +
                    '<td>' + f.descripcion + '</td>' +
                    '<td>' + tipoBadge + '</td>' +
                    '<td class="text-center">' + privBadge + '</td>' +
                    '<td class="text-center">' + actBadge + '</td>' +
                    '<td class="text-end pe-3">' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm btn-alternar-feriado" data-id="' + f.id + '" title="Alternar activo/inactivo">' +
                            '<i class="fa-solid fa-power-off"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            });
            tbody.innerHTML = html;
            enlazarBotonesAlternar();
        });
    });

    function enlazarBotonesAlternar() {
        document.querySelectorAll('.btn-alternar-feriado').forEach(function(btn) {
            btn.onclick = function() {
                var id = this.getAttribute('data-id');
                fetch('<?= url_ruta('/configuracion/feriados/') ?>' + id + '/alternar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({})
                })
                .then(function(res) { return res.json(); })
                .then(function(d) {
                    if (d.exito) window.location.reload();
                    else alert('Error: ' + d.mensaje);
                });
            };
        });
    }

    enlazarBotonesAlternar();

    // Guardar nuevo feriado
    var formFeriado = document.getElementById('form-feriado');
    if (formFeriado) {
        formFeriado.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-guardar-feriado');
            btn.disabled = true;

            var payload = {
                id: document.getElementById('feriado_id').value,
                fecha: document.getElementById('feriado_fecha').value,
                descripcion: document.getElementById('feriado_descripcion').value,
                tipo: document.getElementById('feriado_tipo').value,
                aplica_sector_privado: document.getElementById('feriado_aplica_privado').checked ? 1 : 0,
                activo: document.getElementById('feriado_activo').checked ? 1 : 0
            };

            fetch('<?= url_ruta('/configuracion/feriados') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (!data.exito) {
                    alert('Error: ' + (data.mensaje || 'Error al guardar'));
                    return;
                }
                var m = bootstrap.Modal.getInstance(document.getElementById('modal-feriado'));
                if (m) m.hide();
                window.location.reload();
            })
            .catch(function(err) {
                btn.disabled = false;
                alert('Error de conexión.');
            });
        });
    }
});
</script>
