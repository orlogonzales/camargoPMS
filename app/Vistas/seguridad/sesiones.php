<?php

declare(strict_types=1);

/**
 * Vista del Monitor de Sesiones y Concurrencia — Camargo PMS (SESIONES-1 / D-088)
 *
 * Principio Vinculante:
 * SESIÓN PHP ≠ REGISTRO DE SESIÓN ≠ USUARIO ACTIVO ≠ PRESENCIA RECIENTE
 *
 * @var array<string, mixed>|null $usuario_actual
 * @var array<string, int> $metricas_iniciales
 * @var string $csrf_token
 * @var int $minutos_inactividad
 * @var int $horas_duracion_maxima
 */
?>
<div id="app-sesiones">
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-light-primary text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-shield-halved f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Monitor de Sesiones y Concurrencia</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Supervisión de accesos, presencia HTTP heurística y revocación administrativa (D-088).
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refrescar-sesiones" title="Actualizar datos">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btn-purgar-expiradas" title="Marcar en BD sesiones expiradas">
                        <i class="fa-solid fa-broom me-1"></i> Purgar Expiradas
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4 Tarjetas de Métricas Directas -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary f-s-12 f-w-600 text-uppercase">Sesiones Activas</span>
                        <h3 class="mb-0 f-w-700 text-primary mt-1" id="metrica-activas">
                            <?= e((string) ($metricas_iniciales['sesiones_activas'] ?? 0)) ?>
                        </h3>
                        <span class="f-s-11 text-muted">Vigentes (&le; <?= e((string) $minutos_inactividad) ?>m inactividad)</span>
                    </div>
                    <div class="bg-light-primary text-primary p-3 b-r-10 d-flex-center">
                        <i class="fa-solid fa-desktop f-s-20"></i>
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
                        <span class="text-secondary f-s-12 f-w-600 text-uppercase">Actividad Reciente</span>
                        <h3 class="mb-0 f-w-700 text-success mt-1" id="metrica-recientes">
                            <?= e((string) ($metricas_iniciales['actividad_reciente'] ?? 0)) ?>
                        </h3>
                        <span class="f-s-11 text-muted">Presencia HTTP (&le; 15 min)</span>
                    </div>
                    <div class="bg-light-success text-success p-3 b-r-10 d-flex-center">
                        <i class="fa-solid fa-bolt f-s-20"></i>
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
                        <span class="text-secondary f-s-12 f-w-600 text-uppercase">Expiradas</span>
                        <h3 class="mb-0 f-w-700 text-warning mt-1" id="metrica-expiradas">
                            <?= e((string) ($metricas_iniciales['sesiones_expiradas'] ?? 0)) ?>
                        </h3>
                        <span class="f-s-11 text-muted">Por inactividad o duraci&oacute;n (&gt; <?= e((string) $horas_duracion_maxima) ?>h)</span>
                    </div>
                    <div class="bg-light-warning text-warning p-3 b-r-10 d-flex-center">
                        <i class="fa-solid fa-clock f-s-20"></i>
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
                        <span class="text-secondary f-s-12 f-w-600 text-uppercase">Revocadas</span>
                        <h3 class="mb-0 f-w-700 text-danger mt-1" id="metrica-revocadas">
                            <?= e((string) ($metricas_iniciales['sesiones_revocadas'] ?? 0)) ?>
                        </h3>
                        <span class="f-s-11 text-muted"><?= e((string) ($metricas_iniciales['revocadas_hoy'] ?? 0)) ?> cerradas hoy</span>
                    </div>
                    <div class="bg-light-danger text-danger p-3 b-r-10 d-flex-center">
                        <i class="fa-solid fa-ban f-s-20"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros y Tabla Principal -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3 bg-light border-bottom">
        <div class="row g-2 align-items-center">
            <div class="col-md-4 col-12">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="filtro-busqueda" placeholder="Buscar por usuario, persona o IP..." autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <select class="form-select form-select-sm" id="filtro-estado">
                    <option value="ACTIVAS">Estado: Solo Activas</option>
                    <option value="TODAS">Estado: Todas las sesiones</option>
                    <option value="EXPIRADAS">Estado: Solo Expiradas</option>
                    <option value="REVOCADAS">Estado: Solo Revocadas</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <select class="form-select form-select-sm" id="filtro-presencia">
                    <option value="">Presencia: Cualquier actividad</option>
                    <option value="PRESENCIA_RECIENTE">Presencia Reciente (&le; 15 min)</option>
                    <option value="SIN_ACTIVIDAD_RECIENTE">Sin actividad reciente (&gt; 15 min)</option>
                </select>
            </div>
            <div class="col-md-2 col-12 text-md-end text-start">
                <span class="f-s-12 text-muted" id="contador-resultados">Cargando...</span>
            </div>
        </div>
    </div>

    <!-- Tabla de Sesiones -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tabla-sesiones">
            <thead class="table-light">
                <tr>
                    <th style="width: 22%;">Usuario / Persona</th>
                    <th style="width: 14%;">Inicio</th>
                    <th style="width: 15%;">Última Actividad</th>
                    <th style="width: 13%;">Expiración</th>
                    <th style="width: 12%;">IP / Dispositivo</th>
                    <th style="width: 10%;" class="text-center">Estado</th>
                    <th style="width: 14%;" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-sesiones">
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando sesiones de usuario...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 border-top">
        <span class="f-s-12 text-muted" id="info-paginacion">Mostrando página 1</span>
        <ul class="pagination pagination-sm mb-0" id="paginador-sesiones">
            <!-- Dinámico -->
        </ul>
    </div>
</div>
</div><!-- /#app-sesiones -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global').value;
    const filtroBusqueda = document.getElementById('filtro-busqueda');
    const filtroEstado = document.getElementById('filtro-estado');
    const filtroPresencia = document.getElementById('filtro-presencia');
    const btnLimpiar = document.getElementById('btn-limpiar-busqueda');
    const btnRefrescar = document.getElementById('btn-refrescar-sesiones');
    const btnPurgar = document.getElementById('btn-purgar-expiradas');
    const tbody = document.getElementById('tbody-sesiones');
    const infoPaginacion = document.getElementById('info-paginacion');
    const paginador = document.getElementById('paginador-sesiones');
    const contadorResultados = document.getElementById('contador-resultados');

    let paginaActual = 1;
    let timerBusqueda = null;

    function cargarMetricas() {
        fetch('/api/seguridad/sesiones/metricas', {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok && res.datos) {
                document.getElementById('metrica-activas').textContent = res.datos.sesiones_activas;
                document.getElementById('metrica-recientes').textContent = res.datos.actividad_reciente;
                document.getElementById('metrica-expiradas').textContent = res.datos.sesiones_expiradas;
                document.getElementById('metrica-revocadas').textContent = res.datos.sesiones_revocadas;
            }
        })
        .catch(err => console.error('Error al cargar métricas:', err));
    }

    function cargarSesiones(pagina = 1) {
        paginaActual = pagina;
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Actualizando sesiones...</td></tr>`;

        const params = new URLSearchParams({
            pagina: paginaActual,
            limite: 15,
            estado: filtroEstado.value,
            presencia: filtroPresencia.value,
            busqueda: filtroBusqueda.value.trim()
        });

        fetch(`/api/seguridad/sesiones?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => {
            if (r.status === 401) {
                window.location.href = '/login';
                return;
            }
            return r.json();
        })
        .then(res => {
            if (!res || !res.ok) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error: ${res?.error || 'No fue posible cargar las sesiones'}</td></tr>`;
                return;
            }

            renderizarTabla(res.datos);
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Fallo de comunicación: ${err.message}</td></tr>`;
        });
    }

    function formatearFechaRelativa(fechaStr) {
        if (!fechaStr) return '-';
        const fecha = new Date(fechaStr.replace(' ', 'T'));
        const ahora = new Date();
        const diffSegundos = Math.floor((ahora - fecha) / 1000);

        if (diffSegundos < 60) return 'hace un momento';
        const diffMinutos = Math.floor(diffSegundos / 60);
        if (diffMinutos < 60) return `hace ${diffMinutos} min`;
        const diffHoras = Math.floor(diffMinutos / 60);
        if (diffHoras < 24) return `hace ${diffHoras} h`;
        return fechaStr;
    }

    function renderizarTabla(datos) {
        contadorResultados.textContent = `${datos.total} sesión(es) encontrada(s)`;
        infoPaginacion.textContent = `Página ${datos.pagina} de ${Math.max(1, datos.total_paginas)} (${datos.total} total)`;

        if (!datos.items || datos.items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron sesiones bajo los filtros seleccionados.</td></tr>`;
            paginador.innerHTML = '';
            return;
        }

        let html = '';
        datos.items.forEach(s => {
            let badgeEstado = '';
            if (s.estado_sesion === 'ACTIVA') {
                badgeEstado = '<span class="badge bg-success">ACTIVA</span>';
            } else if (s.estado_sesion === 'EXPIRADA_INACTIVIDAD' || s.estado_sesion === 'EXPIRADA_ABSOLUTA') {
                badgeEstado = `<span class="badge bg-warning text-dark" title="${s.estado_sesion}">EXPIRADA</span>`;
            } else {
                badgeEstado = `<span class="badge bg-danger" title="Motivo: ${s.motivo_cierre || 'REVOCADA'}">REVOCADA</span>`;
            }

            let badgePresencia = '';
            if (s.presencia_reciente === 'PRESENCIA_RECIENTE') {
                badgePresencia = '<span class="badge bg-light-success text-success ms-1" title="Actividad en los últimos 15 min"><i class="fa-solid fa-bolt f-s-10 me-1"></i>Reciente</span>';
            }

            let badgeActual = '';
            if (s.es_sesion_actual) {
                badgeActual = '<span class="badge bg-light-primary text-primary ms-1"><i class="fa-solid fa-user-check f-s-10 me-1"></i>Esta sesión</span>';
            }

            const relativeActividad = formatearFechaRelativa(s.ultima_actividad_en);

            let botonRevocar = '';
            if (s.esta_activa) {
                botonRevocar = `
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-revocar-sesion"
                            data-id="${s.id}" data-usuario="${s.usuario?.nombre_usuario || ''}" data-es-actual="${s.es_sesion_actual ? '1' : '0'}"
                            title="Revocar sesión administrativamente">
                        <i class="fa-solid fa-ban me-1"></i> Revocar
                    </button>
                `;
            }

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light text-primary rounded-circle p-2 me-2 text-center" style="width: 34px; height: 34px;">
                                <i class="fa-solid fa-user f-s-14"></i>
                            </div>
                            <div>
                                <div class="f-w-700 text-dark f-s-13">
                                    ${escapeHtml(s.usuario?.nombre_usuario || 'Desconocido')}
                                    ${badgeActual}
                                </div>
                                <div class="text-muted f-s-11">
                                    ${escapeHtml(s.usuario?.persona?.nombre_completo || '')} · <span class="badge bg-light text-secondary">${escapeHtml(s.usuario?.rol_nombre || '')}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="f-s-12 text-dark">${escapeHtml(s.iniciada_en)}</div>
                    </td>
                    <td>
                        <div class="f-s-12 text-dark">${escapeHtml(s.ultima_actividad_en)}</div>
                        <div class="f-s-11 text-muted">${relativeActividad} ${badgePresencia}</div>
                    </td>
                    <td>
                        <div class="f-s-11 text-muted" title="Límite por inactividad">Inact: ${escapeHtml(s.expira_en)}</div>
                        <div class="f-s-11 text-muted" title="Límite absoluto 12h">Abs: ${escapeHtml(s.expiracion_absoluta_en || '-')}</div>
                    </td>
                    <td>
                        <div class="f-s-12 f-w-600 text-dark"><i class="fa-solid fa-network-wired me-1 text-muted f-s-10"></i>${escapeHtml(s.ip || 'N/D')}</div>
                        <div class="f-s-10 text-muted text-truncate" style="max-width: 140px;" title="${escapeHtml(s.user_agent || '')}">${escapeHtml(s.user_agent ? s.user_agent.substring(0, 30) + '...' : '-')}</div>
                    </td>
                    <td class="text-center">
                        ${badgeEstado}
                    </td>
                    <td class="text-end">
                        ${botonRevocar}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        renderizarPaginador(datos);
        enlazarBotonesRevocar();
    }

    function renderizarPaginador(datos) {
        if (datos.total_paginas <= 1) {
            paginador.innerHTML = '';
            return;
        }

        let html = '';
        if (datos.pagina > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${datos.pagina - 1}">&laquo;</a></li>`;
        }

        for (let i = 1; i <= datos.total_paginas; i++) {
            if (i === 1 || i === datos.total_paginas || (i >= datos.pagina - 2 && i <= datos.pagina + 2)) {
                html += `<li class="page-item ${i === datos.pagina ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            } else if (i === datos.pagina - 3 || i === datos.pagina + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        if (datos.pagina < datos.total_paginas) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${datos.pagina + 1}">&raquo;</a></li>`;
        }

        paginador.innerHTML = html;
        paginador.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                cargarSesiones(parseInt(this.getAttribute('data-page')));
            });
        });
    }

    function enlazarBotonesRevocar() {
        tbody.querySelectorAll('.btn-revocar-sesion').forEach(btn => {
            btn.addEventListener('click', function () {
                const sesionId = this.getAttribute('data-id');
                const username = this.getAttribute('data-usuario');
                const esActual = this.getAttribute('data-es-actual') === '1';

                let advertencia = `¿Confirmas revocar la sesión ID #${sesionId} del usuario <strong>${escapeHtml(username)}</strong>?`;
                if (esActual) {
                    advertencia = `<strong>¡ATENCIÓN!</strong> Esta es tu <strong>sesión actual</strong>.<br>Si la revocas, tu sesión finalizará de inmediato y serás redirigido a la pantalla de login.`;
                }

                Swal.fire({
                    title: 'Revocar Sesión',
                    html: advertencia,
                    icon: esActual ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: esActual ? 'Sí, revocar mi sesión' : 'Sí, revocar sesión',
                    cancelButtonText: 'Cancelar'
                }).then(result => {
                    if (result.isConfirmed) {
                        ejecutarRevocacion(sesionId, esActual);
                    }
                });
            });
        });
    }

    function ejecutarRevocacion(sesionId, esActual) {
        fetch(`/api/seguridad/sesiones/${sesionId}/revocar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                _csrf_token: csrfToken,
                motivo: 'REVOCACION_ADMINISTRATIVA'
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                if (esActual || res.datos?.es_sesion_actual) {
                    Swal.fire({
                        title: 'Sesión Finalizada',
                        text: 'Su sesión ha sido revocada. Redirigiendo...',
                        icon: 'info',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '/login';
                    });
                } else {
                    Swal.fire('Revocada', res.mensaje || 'Sesión revocada exitosamente.', 'success');
                    cargarMetricas();
                    cargarSesiones(paginaActual);
                }
            } else {
                Swal.fire('Error', res.error || 'No se pudo revocar la sesión.', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Fallo', err.message, 'error');
        });
    }

    btnPurgar.addEventListener('click', function () {
        Swal.fire({
            title: 'Purgar Sesiones Expiradas',
            text: 'Esta acción marcará formalmente en base de datos como EXPIRADA toda sesión inactiva (> 30 min) o caducada (> 12 h).',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, purgar ahora',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch('/api/seguridad/sesiones/purgar-expiradas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ _csrf_token: csrfToken })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        Swal.fire('Saneamiento Completado', res.mensaje, 'success');
                        cargarMetricas();
                        cargarSesiones(paginaActual);
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                })
                .catch(err => Swal.fire('Fallo', err.message, 'error'));
            }
        });
    });

    filtroEstado.addEventListener('change', () => cargarSesiones(1));
    filtroPresencia.addEventListener('change', () => cargarSesiones(1));
    btnRefrescar.addEventListener('click', () => {
        cargarMetricas();
        cargarSesiones(paginaActual);
    });

    filtroBusqueda.addEventListener('input', () => {
        clearTimeout(timerBusqueda);
        timerBusqueda = setTimeout(() => cargarSesiones(1), 350);
    });

    btnLimpiar.addEventListener('click', () => {
        filtroBusqueda.value = '';
        cargarSesiones(1);
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Carga inicial
    cargarSesiones(1);
});
</script>
