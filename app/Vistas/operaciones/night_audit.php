<?php
declare(strict_types=1);

/**
 * Vista de Auditoría Nocturna (Night Audit) y Cierres Hoteleros (D-090).
 * Basada en la plantilla oficial Alina (blank.html).
 *
 * Variables disponibles:
 * @var string $titulo
 * @var array<string, mixed>|null $usuario_actual
 * @var string $csrf_token
 * @var array<int, array<string, mixed>> $propiedades
 * @var int $propiedad_id
 * @var array<int, array<string, mixed>> $cierres
 * @var array<string, mixed>|null $ultimo_cierre
 * @var string $fecha_sugerida
 */
?>

<div id="app-night-audit">

            <!-- Banner de Gobernanza D-090 -->
            <div class="alert alert-light-primary border-0 alert-dismissible fade show py-2 px-3 mb-3 b-r-8 f-s-13">
                <div class="d-flex align-items-center">
                    <div class="fs-4 text-primary me-2"><i class="fa-solid fa-moon"></i></div>
                    <div>
                        <strong>Gobernanza D-090:</strong> La Auditoría Nocturna ejecuta el reconocimiento económico formal de alojamiento fecha a fecha, congelando el inventario vendible y calculando de forma soberana el ADR y RevPAR.
                    </div>
                </div>
            </div>

            <!-- Tarjetas de Métricas del Último Cierre -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="card border shadow-none b-r-8 mb-0">
                        <div class="card-body p-3 text-center">
                            <span class="text-muted f-s-11 text-uppercase">ADR Soberano (Tarifa Promedio)</span>
                            <h3 class="mb-0 f-s-22 f-w-700 text-primary mt-1" id="kpi-adr">
                                <?= $ultimo_cierre ? 'S/ ' . number_format((float) $ultimo_cierre['adr'], 2) : 'S/ 0.00' ?>
                            </h3>
                            <span class="badge bg-light-primary text-primary f-s-10 mt-1">D-090</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="card border shadow-none b-r-8 mb-0">
                        <div class="card-body p-3 text-center">
                            <span class="text-muted f-s-11 text-uppercase">RevPAR (Ingreso x Disp.)</span>
                            <h3 class="mb-0 f-s-22 f-w-700 text-success mt-1" id="kpi-revpar">
                                <?= $ultimo_cierre ? 'S/ ' . number_format((float) $ultimo_cierre['revpar'], 2) : 'S/ 0.00' ?>
                            </h3>
                            <span class="badge bg-light-success text-success f-s-10 mt-1">Vendibles Netas</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="card border shadow-none b-r-8 mb-0">
                        <div class="card-body p-3 text-center">
                            <span class="text-muted f-s-11 text-uppercase">Ocupación Auditada</span>
                            <h3 class="mb-0 f-s-22 f-w-700 text-dark mt-1" id="kpi-ocupacion">
                                <?= $ultimo_cierre ? number_format((float) $ultimo_cierre['ocupacion_porcentaje'], 2) . '%' : '0.00%' ?>
                            </h3>
                            <span class="badge bg-light-dark text-dark f-s-10 mt-1">
                                <?= $ultimo_cierre ? $ultimo_cierre['habitaciones_vendidas'] . ' / ' . $ultimo_cierre['unidades_vendibles'] : '0 / 0' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="card border shadow-none b-r-8 mb-0">
                        <div class="card-body p-3 text-center">
                            <span class="text-muted f-s-11 text-uppercase">Ingreso Neto Alojamiento</span>
                            <h3 class="mb-0 f-s-22 f-w-700 text-info mt-1" id="kpi-ingreso">
                                <?= $ultimo_cierre ? 'S/ ' . number_format((float) $ultimo_cierre['ingreso_alojamiento_neto'], 2) : 'S/ 0.00' ?>
                            </h3>
                            <span class="badge bg-light-info text-info f-s-10 mt-1">
                                <?= $ultimo_cierre ? htmlspecialchars($ultimo_cierre['fecha_hotelera']) : 'Sin Cierres' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Operacional de Ejecución -->
            <div class="card border shadow-none b-r-8 mb-4">
                <div class="card-header bg-white py-2 f-w-600 f-s-14 border-bottom d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-bolt me-1 text-warning"></i> Ejecución de Cierre de Día Hotelero</span>
                    <span class="badge bg-secondary f-s-11">Por Propiedad</span>
                </div>
                <div class="card-body p-3">
                    <form id="form-night-audit" class="row g-3 align-items-end">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label f-s-12 f-w-600 text-muted">Sede / Propiedad</label>
                            <select class="form-select f-s-13" id="select-propiedad" name="propiedad_id" required>
                                <?php foreach ($propiedades as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $propiedad_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['codigo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label f-s-12 f-w-600 text-muted">Fecha Hotelera a Cerrar</label>
                            <input type="date" class="form-control f-s-13" id="input-fecha-hotelera" name="fecha_hotelera" value="<?= htmlspecialchars($fecha_sugerida) ?>" required>
                        </div>

                        <div class="col-md-4 col-sm-8">
                            <label class="form-label f-s-12 f-w-600 text-muted">Observaciones de Guardia</label>
                            <input type="text" class="form-control f-s-13" id="input-observaciones" name="observaciones" placeholder="Novedades o incidencias relevantes del turno nocturno...">
                        </div>

                        <div class="col-md-2 col-sm-4">
                            <button type="submit" class="btn btn-primary w-100 f-s-13 f-w-600" id="btn-ejecutar-cierre">
                                <i class="fa-solid fa-moon me-1"></i> Cerrar Día
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Historial de Cierres -->
            <div class="card border shadow-none b-r-8 mb-4">
                <div class="card-header bg-white py-2 f-w-600 f-s-14 border-bottom d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-list-check me-1 text-primary"></i> Historial de Cierres de Fecha Hotelera</span>
                    <button class="btn btn-sm btn-outline-secondary f-s-12" id="btn-refrescar-historial">
                        <i class="fa-solid fa-rotate me-1"></i> Refrescar
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0 f-s-13 text-center" id="tabla-cierres">
                            <thead class="table-light text-uppercase f-s-11">
                                <tr>
                                    <th>Fecha Hotelera</th>
                                    <th>Estado</th>
                                    <th>Estadías</th>
                                    <th>Noches Dev.</th>
                                    <th>Vendibles</th>
                                    <th>Vendidas</th>
                                    <th>Ocupación</th>
                                    <th>Ingreso Neto</th>
                                    <th>ADR</th>
                                    <th>RevPAR</th>
                                    <th>Iniciado En</th>
                                    <th>Cerrado En</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-cierres">
                                <?php if (empty($cierres)): ?>
                                    <tr>
                                        <td colspan="12" class="text-muted py-4">No se han registrado cierres de auditoría nocturna para esta propiedad.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cierres as $c): ?>
                                        <tr>
                                            <td class="f-w-600 text-dark"><?= htmlspecialchars($c['fecha_hotelera']) ?></td>
                                            <td>
                                                <?php if ($c['estado'] === 'CERRADO'): ?>
                                                    <span class="badge bg-light-success text-success">CERRADO</span>
                                                <?php elseif ($c['estado'] === 'EN_PROCESO'): ?>
                                                    <span class="badge bg-light-warning text-warning">EN PROCESO</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light-danger text-danger">FALLIDO</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= (int) $c['total_estadias_procesadas'] ?></td>
                                            <td><?= (int) $c['total_noches_devengadas'] ?></td>
                                            <td><?= (int) $c['unidades_vendibles'] ?></td>
                                            <td><?= (int) $c['habitaciones_vendidas'] ?></td>
                                            <td class="f-w-600"><?= number_format((float) $c['ocupacion_porcentaje'], 2) ?>%</td>
                                            <td class="text-end text-success f-w-600">S/ <?= number_format((float) $c['ingreso_alojamiento_neto'], 2) ?></td>
                                            <td class="text-end text-primary f-w-600">S/ <?= number_format((float) $c['adr'], 2) ?></td>
                                            <td class="text-end text-dark f-w-600">S/ <?= number_format((float) $c['revpar'], 2) ?></td>
                                            <td class="f-s-11 text-muted"><?= htmlspecialchars($c['iniciado_en']) ?></td>
                                            <td class="f-s-11 text-muted"><?= $c['cerrado_en'] ? htmlspecialchars($c['cerrado_en']) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formCierre = document.getElementById('form-night-audit');
    const btnEjecutar = document.getElementById('btn-ejecutar-cierre');
    const selectPropiedad = document.getElementById('select-propiedad');
    const btnRefrescar = document.getElementById('btn-refrescar-historial');

    if (formCierre) {
        formCierre.addEventListener('submit', function(e) {
            e.preventDefault();

            const propiedadId = selectPropiedad.value;
            const fechaHotelera = document.getElementById('input-fecha-hotelera').value;
            const observaciones = document.getElementById('input-observaciones').value;
            const token = document.querySelector('input[name="_token"]').value;

            if (!fechaHotelera) {
                alert('Debe indicar la fecha hotelera a cerrar.');
                return;
            }

            const confirmacion = confirm(`¿Está seguro de ejecutar la Auditoría Nocturna para la fecha ${fechaHotelera}?\nEsta acción devengará el alojamiento de las estadías activas y congelará las métricas ADR/RevPAR.`);
            if (!confirmacion) {
                return;
            }

            btnEjecutar.disabled = true;
            btnEjecutar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Procesando...';

            fetch('/api/operaciones/night-audit/ejecutar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    _token: token,
                    propiedad_id: propiedadId,
                    fecha_hotelera: fechaHotelera,
                    observaciones: observaciones
                })
            })
            .then(res => res.json())
            .then(data => {
                btnEjecutar.disabled = false;
                btnEjecutar.innerHTML = '<i class="fa-solid fa-moon me-1"></i> Cerrar Día';

                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    alert(data.mensaje || 'Cierre completado con éxito.');
                    window.location.reload();
                }
            })
            .catch(err => {
                btnEjecutar.disabled = false;
                btnEjecutar.innerHTML = '<i class="fa-solid fa-moon me-1"></i> Cerrar Día';
                alert('Ocurrió un error inesperado al procesar la auditoría.');
                console.error(err);
            });
        });
    }

    if (btnRefrescar) {
        btnRefrescar.addEventListener('click', function() {
            window.location.reload();
        });
    }

    if (selectPropiedad) {
        selectPropiedad.addEventListener('change', function() {
            window.location.href = '/operaciones/night-audit?propiedad_id=' + this.value;
        });
    }
});
</script>
