/**
 * Camargo PMS — Gestión Operativa de Canales y Conexiones iCalendar (AIRBNB-ICAL-1C).
 *
 * Módulo JavaScript soberano:
 * - Alina Design System + Bootstrap 5 Modals nativos.
 * - PristineJS para validación de formularios en cliente.
 * - SweetAlert2 exclusivo para confirmaciones y alertas (cero alert/confirm).
 * - Fetch nativo + JSON para comunicación asíncrona (cero jQuery AJAX).
 * - Protección radical de secretos: cero token o URL cifrada en DOM/dataset.
 * - Prevención estricta de doble submit y bloqueo de botones durante sincronización.
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // =========================================================================
    // 1. CONSTANTES Y ELEMENTOS DEL DOM
    // =========================================================================

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';
    const tbodyConexiones = document.getElementById('tbody-conexiones-ical');
    const mensajeVacio = document.getElementById('mensaje-vacio-conexiones');

    // KPIs
    const kpiTotal = document.getElementById('kpi-total-conexiones');
    const kpiActivas = document.getElementById('kpi-activas-conexiones');
    const kpiErrores = document.getElementById('kpi-errores-conexiones');
    const kpiConflictos = document.getElementById('kpi-conflictos-pendientes');

    // Filtros
    const filtroPropiedad = document.getElementById('filtro-propiedad');
    const filtroCanal = document.getElementById('filtro-canal');
    const filtroEstado = document.getElementById('filtro-estado');
    const filtroBusqueda = document.getElementById('filtro-busqueda');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda');
    const btnRecargar = document.getElementById('btn-recargar');

    // Modales Bootstrap
    const modalCrearEl = document.getElementById('modal-crear-conexion');
    const modalEditarEl = document.getElementById('modal-editar-conexion');
    const modalHistorialEl = document.getElementById('modal-historial');
    const modalConflictosEl = document.getElementById('modal-conflictos');
    const modalCopiarFeedEl = document.getElementById('modal-copiar-feed');

    const modalCrear = modalCrearEl ? new bootstrap.Modal(modalCrearEl) : null;
    const modalEditar = modalEditarEl ? new bootstrap.Modal(modalEditarEl) : null;
    const modalHistorial = modalHistorialEl ? new bootstrap.Modal(modalHistorialEl) : null;
    const modalConflictos = modalConflictosEl ? new bootstrap.Modal(modalConflictosEl) : null;
    const modalCopiarFeed = modalCopiarFeedEl ? new bootstrap.Modal(modalCopiarFeedEl) : null;

    // Formularios
    const formCrear = document.getElementById('form-crear-conexion');
    const formEditar = document.getElementById('form-editar-conexion');
    const btnSubmitCrear = document.getElementById('btn-submit-crear');
    const btnSubmitEditar = document.getElementById('btn-submit-editar');

    // Estado local en memoria (CERO secretos)
    let conexionesCargadas = [];
    let validadorCrear = null;
    let validadorEditar = null;

    // =========================================================================
    // 2. INICIALIZACIÓN DE SELECT2 Y PRISTINE
    // =========================================================================

    function inicializarSelect2() {
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            $('.select2-filtro').select2({
                width: '100%',
                placeholder: 'Seleccione...'
            });

            if (modalCrearEl) {
                $('#modal-crear-conexion .select2-modal').select2({
                    dropdownParent: $('#modal-crear-conexion'),
                    width: '100%'
                });
            }
        }
    }

    function inicializarValidadores() {
        if (typeof window.CamargoForms !== 'undefined') {
            if (formCrear) {
                validadorCrear = window.CamargoForms.inicializar(formCrear);
            }
            if (formEditar) {
                validadorEditar = window.CamargoForms.inicializar(formEditar);
            }
        }
    }

    inicializarSelect2();
    inicializarValidadores();

    // =========================================================================
    // 3. CARGA DE DATOS ASÍNCRONA
    // =========================================================================

    async function cargarConexiones() {
        mostrarSkeleton();

        const params = new URLSearchParams();
        if (filtroPropiedad && filtroPropiedad.value) {
            params.append('propiedad_id', filtroPropiedad.value);
        }
        if (filtroCanal && filtroCanal.value) {
            params.append('canal_id', filtroCanal.value);
        }
        if (filtroEstado && filtroEstado.value) {
            params.append('estado', filtroEstado.value);
        }

        try {
            const resp = await fetch(`/canales-ical/datos?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }

            const data = await resp.json();
            if (!data.ok) {
                throw new Error(data.error || 'Error al cargar conexiones');
            }

            conexionesCargadas = data.conexiones || [];
            actualizarKpis(data.kpis || {});
            renderizarTabla(aplicarFiltroTexto(conexionesCargadas));

        } catch (error) {
            console.error('Error al cargar conexiones:', error);
            if (tbodyConexiones) {
                tbodyConexiones.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="fa-solid fa-circle-exclamation me-1"></i> No se pudieron cargar las conexiones iCalendar.
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reintentar-carga">
                                    <i class="fa-solid fa-rotate me-1"></i> Reintentar
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                document.getElementById('btn-reintentar-carga')?.addEventListener('click', cargarConexiones);
            }
        }
    }

    function mostrarSkeleton() {
        if (!tbodyConexiones) return;
        tbodyConexiones.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="placeholder-glow py-3">
                        <span class="placeholder col-4 d-block mb-2"></span>
                        <span class="placeholder col-7 d-block mb-2"></span>
                        <span class="placeholder col-3 d-block"></span>
                    </div>
                </td>
            </tr>
        `;
        if (mensajeVacio) mensajeVacio.classList.add('d-none');
    }

    function actualizarKpis(kpis) {
        if (kpiTotal) kpiTotal.textContent = kpis.total_conexiones ?? 0;
        if (kpiActivas) kpiActivas.textContent = kpis.activas ?? 0;
        if (kpiErrores) kpiErrores.textContent = kpis.con_error ?? 0;
        if (kpiConflictos) kpiConflictos.textContent = kpis.conflictos_pendientes ?? 0;
    }

    function aplicarFiltroTexto(conexiones) {
        const texto = (filtroBusqueda?.value || '').toLowerCase().trim();
        if (!texto) return conexiones;

        return conexiones.filter(c => {
            const nom = (c.nombre || '').toLowerCase();
            const uNom = (c.unidad_nombre || '').toLowerCase();
            const uCod = (c.unidad_codigo || '').toLowerCase();
            const cNom = (c.canal_nombre || '').toLowerCase();
            const pNom = (c.propiedad_nombre || '').toLowerCase();
            return nom.includes(texto) || uNom.includes(texto) || uCod.includes(texto) || cNom.includes(texto) || pNom.includes(texto);
        });
    }

    // =========================================================================
    // 4. RENDERIZADO DE TABLA (ALINA DESIGN SYSTEM)
    // =========================================================================

    function renderizarTabla(conexiones) {
        if (!tbodyConexiones) return;

        if (conexiones.length === 0) {
            tbodyConexiones.innerHTML = '';
            if (mensajeVacio) mensajeVacio.classList.remove('d-none');
            return;
        }

        if (mensajeVacio) mensajeVacio.classList.add('d-none');

        let html = '';
        conexiones.forEach(c => {
            // Badges Alina: bg-light-* text-* (sin *-subtle)
            const badgeCanal = c.canal_color_badge || 'bg-light-primary text-primary';
            const badgeEstado = obtenerBadgeEstado(c.estado);
            const badgeResultado = obtenerBadgeResultado(c.ultimo_resultado);

            const importBadge = c.importacion_habilitada
                ? '<span class="badge bg-light-success text-success me-1" title="Importación activa"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Import</span>'
                : '<span class="badge bg-light-secondary text-secondary me-1" title="Importación deshabilitada"><i class="fa-solid fa-ban me-1"></i>Import</span>';

            const exportBadge = c.exportacion_habilitada
                ? '<span class="badge bg-light-info text-info" title="Exportación activa"><i class="fa-solid fa-cloud-arrow-down me-1"></i>Export</span>'
                : '<span class="badge bg-light-secondary text-secondary" title="Exportación deshabilitada"><i class="fa-solid fa-ban me-1"></i>Export</span>';

            const fechaSync = c.ultima_sincronizacion_en
                ? `<span class="f-s-12 text-dark d-block">${escapeHtml(c.ultima_sincronizacion_en)}</span>`
                : '<span class="f-s-12 text-muted italic">Nunca</span>';

            const errorDetalle = c.ultimo_error
                ? `<div class="text-danger f-s-11 mt-1 text-truncate" style="max-width: 180px;" title="${escapeHtml(c.ultimo_error)}"><i class="fa-solid fa-triangle-exclamation me-1"></i>${escapeHtml(c.ultimo_error)}</div>`
                : '';

            html += `
                <tr data-id="${c.id}">
                    <td>
                        <div class="f-w-600 text-dark">${escapeHtml(c.unidad_nombre || 'Unidad')}</div>
                        <div class="f-s-11 text-secondary">
                            <span class="badge bg-light-secondary text-secondary">${escapeHtml(c.unidad_codigo || '')}</span>
                            ${escapeHtml(c.propiedad_nombre || '')}
                        </div>
                    </td>
                    <td>
                        <span class="badge ${badgeCanal}">
                            ${escapeHtml(c.canal_nombre || c.canal_codigo || 'Canal')}
                        </span>
                    </td>
                    <td>
                        <div class="f-w-600 f-s-13 text-dark">${escapeHtml(c.nombre)}</div>
                        <div class="f-s-11 text-muted">
                            <i class="fa-solid fa-clock me-1"></i> Cada ${c.frecuencia_minutos} min
                            ${c.token_prefijo ? `• <span class="text-secondary" title="Prefijo del token">#${escapeHtml(c.token_prefijo)}</span>` : ''}
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            ${importBadge}
                            ${exportBadge}
                        </div>
                    </td>
                    <td>
                        ${badgeEstado}
                    </td>
                    <td>
                        ${fechaSync}
                        <div class="mt-1">${badgeResultado}</div>
                        ${errorDetalle}
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-accion-sync"
                                    data-id="${c.id}"
                                    title="Sincronizar ahora"
                                    ${!c.importacion_habilitada || c.estado !== 'ACTIVO' ? 'disabled' : ''}>
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="visually-hidden">Acciones</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 f-s-12">
                                <li>
                                    <button class="dropdown-item btn-accion-editar" data-id="${c.id}">
                                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Conexión
                                    </button>
                                </li>
                                ${c.exportacion_habilitada && c.estado !== 'REVOCADO' ? `
                                <li>
                                    <button class="dropdown-item btn-accion-copiar-feed" data-id="${c.id}">
                                        <i class="fa-regular fa-copy text-info me-2"></i> Copiar Feed URL
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item btn-accion-rotar-token" data-id="${c.id}">
                                        <i class="fa-solid fa-key text-warning me-2"></i> Rotar Token Feed
                                    </button>
                                </li>
                                ` : ''}
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item btn-accion-historial" data-id="${c.id}">
                                        <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> Ver Historial
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item btn-accion-conflictos" data-id="${c.id}">
                                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Ver Conflictos
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                ${c.estado === 'ACTIVO' ? `
                                <li>
                                    <button class="dropdown-item text-warning btn-accion-estado" data-id="${c.id}" data-estado="PAUSADO">
                                        <i class="fa-solid fa-pause me-2"></i> Pausar Conexión
                                    </button>
                                </li>
                                ` : c.estado === 'PAUSADO' ? `
                                <li>
                                    <button class="dropdown-item text-success btn-accion-estado" data-id="${c.id}" data-estado="ACTIVO">
                                        <i class="fa-solid fa-play me-2"></i> Reanudar Conexión
                                    </button>
                                </li>
                                ` : ''}
                                ${c.estado !== 'REVOCADO' ? `
                                <li>
                                    <button class="dropdown-item text-danger btn-accion-estado" data-id="${c.id}" data-estado="REVOCADO">
                                        <i class="fa-solid fa-ban me-2"></i> Revocar Conexión
                                    </button>
                                </li>
                                ` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbodyConexiones.innerHTML = html;
        if (typeof window.CamargoForms !== 'undefined') {
            window.CamargoForms.inicializarTooltips(tbodyConexiones);
        }
    }

    function obtenerBadgeEstado(estado) {
        switch (estado) {
            case 'ACTIVO':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>';
            case 'PAUSADO':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-circle-pause me-1"></i>Pausado</span>';
            case 'REVOCADO':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-ban me-1"></i>Revocado</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escapeHtml(estado)}</span>`;
        }
    }

    function obtenerBadgeResultado(resultado) {
        switch (resultado) {
            case 'EXITO':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-check me-1"></i>Éxito</span>';
            case 'CON_ADVERTENCIA':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Advertencia</span>';
            case 'ERROR':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-xmark me-1"></i>Error</span>';
            case 'NO_EJECUTADO':
            default:
                return '<span class="badge bg-light-secondary text-secondary">Pendiente</span>';
        }
    }

    // =========================================================================
    // 5. EVENT DELEGATION PARA ACCIONES DE FILA (ZERO DUPLICATE LISTENERS)
    // =========================================================================

    if (tbodyConexiones) {
        tbodyConexiones.addEventListener('click', async function (e) {
            const btnSync = e.target.closest('.btn-accion-sync');
            const btnEditar = e.target.closest('.btn-accion-editar');
            const btnCopiarFeed = e.target.closest('.btn-accion-copiar-feed');
            const btnRotarToken = e.target.closest('.btn-accion-rotar-token');
            const btnHistorial = e.target.closest('.btn-accion-historial');
            const btnConflictos = e.target.closest('.btn-accion-conflictos');
            const btnEstado = e.target.closest('.btn-accion-estado');

            if (btnSync) {
                e.preventDefault();
                const id = btnSync.dataset.id;
                await ejecutarSincronizacion(id, btnSync);
            } else if (btnEditar) {
                e.preventDefault();
                const id = btnEditar.dataset.id;
                abrirModalEditar(id);
            } else if (btnCopiarFeed) {
                e.preventDefault();
                const id = btnCopiarFeed.dataset.id;
                await copiarFeedExportacion(id);
            } else if (btnRotarToken) {
                e.preventDefault();
                const id = btnRotarToken.dataset.id;
                await confirmarRotacionToken(id);
            } else if (btnHistorial) {
                e.preventDefault();
                const id = btnHistorial.dataset.id;
                await abrirModalHistorial(id);
            } else if (btnConflictos) {
                e.preventDefault();
                const id = btnConflictos.dataset.id;
                await abrirModalConflictos(id);
            } else if (btnEstado) {
                e.preventDefault();
                const id = btnEstado.dataset.id;
                const nuevoEstado = btnEstado.dataset.estado;
                await confirmarCambioEstado(id, nuevoEstado);
            }
        });
    }

    // =========================================================================
    // 6. ACCIÓN: SINCRONIZAR AHORA (ANTI-DOUBLE-CLICK Y BLOQUEO CONCURRENTE)
    // =========================================================================

    async function ejecutarSincronizacion(id, boton) {
        if (!boton || boton.dataset.sincronizando === 'true') {
            return;
        }

        // Bloqueo frontend defensivo inmediato
        boton.dataset.sincronizando = 'true';
        boton.disabled = true;
        const contenidoOriginal = boton.innerHTML;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        try {
            const resp = await fetch(`/canales-ical/${id}/sincronizar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await resp.json();

            if (resp.status === 409) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sincronización en Curso',
                    text: data.error || 'Ya existe una sincronización en ejecución para esta conexión.',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al ejecutar sincronización');
            }

            const resumen = data.datos || {};
            const mensajeDetalle = `
                <div class="text-start f-s-13">
                    <p class="mb-2"><strong>${escapeHtml(data.mensaje || 'Sincronización completada')}</strong></p>
                    <ul class="list-unstyled mb-0">
                        <li><i class="fa-solid fa-arrow-down text-primary me-2"></i>Eventos recibidos: <strong>${resumen.eventos_recibidos ?? 0}</strong></li>
                        <li><i class="fa-solid fa-plus text-success me-2"></i>Nuevos bloqueos: <strong>${resumen.eventos_creados ?? 0}</strong></li>
                        <li><i class="fa-solid fa-pen text-info me-2"></i>Actualizados: <strong>${resumen.eventos_actualizados ?? 0}</strong></li>
                        <li><i class="fa-solid fa-xmark text-secondary me-2"></i>Cancelados: <strong>${resumen.eventos_cancelados ?? 0}</strong></li>
                        <li><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Conflictos: <strong>${resumen.conflictos ?? 0}</strong></li>
                    </ul>
                </div>
            `;

            Swal.fire({
                icon: (resumen.conflictos > 0 || resumen.resultado === 'CON_ADVERTENCIA') ? 'warning' : 'success',
                title: 'Sincronización Finalizada',
                html: mensajeDetalle,
                confirmButtonText: 'Aceptar'
            });

            // Recargar tabla e indicadores sin recargar página
            await cargarConexiones();

        } catch (error) {
            console.error('Error en sincronización manual:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de Sincronización',
                text: error.message || 'No fue posible completar la sincronización con el canal externo.'
            });
        } finally {
            boton.innerHTML = contenidoOriginal;
            boton.disabled = false;
            delete boton.dataset.sincronizando;
        }
    }

    // =========================================================================
    // 7. ACCIÓN: COPIAR FEED DE EXPORTACIÓN (CERO SECRETOS EN DOM)
    // =========================================================================

    async function copiarFeedExportacion(id) {
        try {
            const resp = await fetch(`/canales-ical/${id}/copiar-feed`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'No fue posible obtener la URL de exportación.');
            }

            const urlFeed = data.url_feed;
            const inputFeed = document.getElementById('input-feed-url');
            if (inputFeed) {
                inputFeed.value = urlFeed;
            }

            if (modalCopiarFeed) {
                modalCopiarFeed.show();
            }

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Exportación',
                text: error.message
            });
        }
    }

    // Botón copiar dentro del modal de feed
    document.getElementById('btn-copiar-clipboard')?.addEventListener('click', async function () {
        const inputFeed = document.getElementById('input-feed-url');
        if (!inputFeed || !inputFeed.value) return;

        try {
            await navigator.clipboard.writeText(inputFeed.value);
            Swal.fire({
                icon: 'success',
                title: 'URL Copiada',
                text: 'La URL privada de exportación ha sido copiada al portapapeles.',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (e) {
            inputFeed.select();
            document.execCommand('copy');
            Swal.fire({
                icon: 'success',
                title: 'URL Copiada',
                text: 'URL copiada al portapapeles.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });

    // =========================================================================
    // 8. ACCIÓN: ROTAR TOKEN DE FEED (CONFIRMACIÓN SWEETALERT)
    // =========================================================================

    async function confirmarRotacionToken(id) {
        const confirmacion = await Swal.fire({
            title: '¿Rotar URL de Exportación?',
            text: 'Al rotar el token, la URL anterior dejará de funcionar de forma inmediata. Deberá actualizar la configuración en los canales receptores (Airbnb, Booking, etc.).',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, rotar token',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f39c12'
        });

        if (!confirmacion.isConfirmed) {
            return;
        }

        try {
            const resp = await fetch(`/canales-ical/${id}/rotar-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al rotar token');
            }

            Swal.fire({
                icon: 'success',
                title: 'Token Rotado',
                text: data.mensaje || 'Token de exportación rotado exitosamente.',
                timer: 2500,
                showConfirmButton: true
            });

            await cargarConexiones();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error al Rotar',
                text: error.message
            });
        }
    }

    // =========================================================================
    // 9. ACCIÓN: CAMBIAR ESTADO / REVOCAR (CONFIRMACIÓN SWEETALERT)
    // =========================================================================

    async function confirmarCambioEstado(id, nuevoEstado) {
        const textos = {
            'ACTIVO': { title: '¿Reanudar conexión?', text: 'Se habilitará nuevamente la conexión para operaciones.' },
            'PAUSADO': { title: '¿Pausar conexión?', text: 'La sincronización quedará en pausa hasta que sea reanudada.' },
            'REVOCADO': { title: '¿Revocar conexión?', text: 'La conexión quedará revocada y no se procesarán más eventos ni exportaciones. Los registros históricos se preservarán intactos.' }
        };

        const config = textos[nuevoEstado] || { title: '¿Cambiar estado?', text: 'Confirmar cambio.' };

        const confirmacion = await Swal.fire({
            title: config.title,
            text: config.text,
            icon: nuevoEstado === 'REVOCADO' ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmacion.isConfirmed) {
            return;
        }

        try {
            const resp = await fetch(`/canales-ical/${id}/estado`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ estado: nuevoEstado, _csrf: csrfToken })
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al actualizar estado');
            }

            Swal.fire({
                icon: 'success',
                title: 'Estado Actualizado',
                text: data.mensaje,
                timer: 1500,
                showConfirmButton: false
            });

            await cargarConexiones();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        }
    }

    // =========================================================================
    // 10. MODAL CREAR CONEXIÓN (PRISTINE + FETCH)
    // =========================================================================

    document.getElementById('btn-nueva-conexion')?.addEventListener('click', () => {
        formCrear?.reset();
        if (typeof jQuery !== 'undefined') {
            $('#modal-crear-conexion .select2-modal').val('').trigger('change');
        }
        modalCrear?.show();
    });

    document.getElementById('btn-nueva-conexion-vacia')?.addEventListener('click', () => {
        formCrear?.reset();
        if (typeof jQuery !== 'undefined') {
            $('#modal-crear-conexion .select2-modal').val('').trigger('change');
        }
        modalCrear?.show();
    });

    formCrear?.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (validadorCrear && !validadorCrear.validate()) {
            return;
        }

        const formData = new FormData(formCrear);
        const payload = {
            unidad_id: formData.get('unidad_id'),
            canal_id: formData.get('canal_id'),
            nombre: formData.get('nombre'),
            importacion_habilitada: formData.get('importacion_habilitada') === '1' ? 1 : 0,
            url_importacion: formData.get('url_importacion'),
            exportacion_habilitada: formData.get('exportacion_habilitada') === '1' ? 1 : 0,
            frecuencia_minutos: formData.get('frecuencia_minutos'),
            estado: formData.get('estado'),
            _csrf: csrfToken
        };

        if (window.CamargoForms) {
            window.CamargoForms.establecerCargando(btnSubmitCrear, 'Guardando...');
        }

        try {
            const resp = await fetch('/canales-ical', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al guardar conexión');
            }

            modalCrear?.hide();
            Swal.fire({
                icon: 'success',
                title: 'Conexión Creada',
                text: data.mensaje || 'Conexión guardada exitosamente.',
                timer: 2000,
                showConfirmButton: false
            });

            await cargarConexiones();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Validación',
                text: error.message
            });
        } finally {
            if (window.CamargoForms) {
                window.CamargoForms.restaurarCargando(btnSubmitCrear);
            }
        }
    });

    // =========================================================================
    // 11. MODAL EDITAR CONEXIÓN (PRISTINE + FETCH + REGLA 15)
    // =========================================================================

    function abrirModalEditar(id) {
        const conexion = conexionesCargadas.find(c => String(c.id) === String(id));
        if (!conexion) return;

        formEditar?.reset();
        document.getElementById('editar-id').value = conexion.id;
        document.getElementById('editar-nombre').value = conexion.nombre || '';
        document.getElementById('editar-importacion-habilitada').checked = Boolean(conexion.importacion_habilitada);
        document.getElementById('editar-exportacion-habilitada').checked = Boolean(conexion.exportacion_habilitada);
        document.getElementById('editar-frecuencia').value = conexion.frecuencia_minutos || '60';
        document.getElementById('editar-estado').value = conexion.estado || 'ACTIVO';
        document.getElementById('editar-nueva-url').value = '';

        const contextoFijo = document.getElementById('editar-contexto-fijo');
        if (contextoFijo) {
            contextoFijo.innerHTML = `
                <strong>${escapeHtml(conexion.unidad_nombre || 'Unidad')}</strong> (${escapeHtml(conexion.unidad_codigo || '')}) •
                Canal: <span class="badge ${conexion.canal_color_badge || 'bg-light-primary text-primary'}">${escapeHtml(conexion.canal_nombre || '')}</span>
            `;
        }

        const badgeUrl = document.getElementById('editar-badge-url-actual');
        if (badgeUrl) {
            if (conexion.tiene_url_importacion) {
                badgeUrl.className = 'badge bg-light-success text-success';
                badgeUrl.textContent = 'Configurada (Cifrada AES-256)';
            } else {
                badgeUrl.className = 'badge bg-light-secondary text-secondary';
                badgeUrl.textContent = 'No configurada';
            }
        }

        modalEditar?.show();
    }

    formEditar?.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (validadorEditar && !validadorEditar.validate()) {
            return;
        }

        const id = document.getElementById('editar-id').value;
        const formData = new FormData(formEditar);
        const payload = {
            nombre: formData.get('nombre'),
            importacion_habilitada: formData.get('importacion_habilitada') === '1' ? 1 : 0,
            nueva_url_importacion: formData.get('nueva_url_importacion'),
            exportacion_habilitada: formData.get('exportacion_habilitada') === '1' ? 1 : 0,
            frecuencia_minutos: formData.get('frecuencia_minutos'),
            estado: formData.get('estado'),
            _csrf: csrfToken
        };

        if (window.CamargoForms) {
            window.CamargoForms.establecerCargando(btnSubmitEditar, 'Actualizando...');
        }

        try {
            const resp = await fetch(`/canales-ical/${id}/editar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al actualizar conexión');
            }

            modalEditar?.hide();
            Swal.fire({
                icon: 'success',
                title: 'Conexión Actualizada',
                text: data.mensaje || 'Cambios guardados exitosamente.',
                timer: 1500,
                showConfirmButton: false
            });

            await cargarConexiones();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error al Guardar',
                text: error.message
            });
        } finally {
            if (window.CamargoForms) {
                window.CamargoForms.restaurarCargando(btnSubmitEditar);
            }
        }
    });

    // =========================================================================
    // 12. MODAL HISTORIAL DE SINCRONIZACIONES (ALINA TABLE)
    // =========================================================================

    async function abrirModalHistorial(id) {
        const tbodyHistorial = document.getElementById('tbody-historial');
        const subtitulo = document.getElementById('historial-subtitulo');
        if (!tbodyHistorial) return;

        tbodyHistorial.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando registros técnicos...
                </td>
            </tr>
        `;

        modalHistorial?.show();

        try {
            const resp = await fetch(`/canales-ical/${id}/historial`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al cargar historial');
            }

            if (subtitulo) {
                subtitulo.textContent = `Conexión: ${data.conexion_nombre || '#' + id}`;
            }

            const logs = data.historial || [];
            if (logs.length === 0) {
                tbodyHistorial.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> No existen sincronizaciones registradas para esta conexión.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            logs.forEach(log => {
                const badgeRes = obtenerBadgeResultado(log.resultado);
                const httpBadge = log.http_codigo === 200
                    ? '<span class="badge bg-light-success text-success">200</span>'
                    : log.http_codigo
                        ? `<span class="badge bg-light-danger text-danger">${log.http_codigo}</span>`
                        : '<span class="text-muted">—</span>';

                html += `
                    <tr>
                        <td class="f-w-600">${escapeHtml(log.iniciado_en)}</td>
                        <td><span class="badge bg-light-secondary text-secondary">${escapeHtml(log.origen_ejecucion)}</span></td>
                        <td>${log.duracion_ms ? `${log.duracion_ms} ms` : '—'}</td>
                        <td>${httpBadge}</td>
                        <td class="text-center">${log.eventos_recibidos}</td>
                        <td class="text-center text-success">${log.eventos_creados}</td>
                        <td class="text-center text-info">${log.eventos_actualizados}</td>
                        <td class="text-center text-secondary">${log.eventos_cancelados}</td>
                        <td class="text-center ${log.conflictos_detectados > 0 ? 'text-danger f-w-700' : 'text-muted'}">${log.conflictos_detectados}</td>
                        <td>${badgeRes}</td>
                    </tr>
                `;
            });

            tbodyHistorial.innerHTML = html;

        } catch (error) {
            tbodyHistorial.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-danger">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    // =========================================================================
    // 13. MODAL CONFLICTOS DE INVENTARIO (SOBERANÍA LOCAL vs OTA)
    // =========================================================================

    async function abrirModalConflictos(id) {
        const tbodyConflictos = document.getElementById('tbody-conflictos');
        const subtitulo = document.getElementById('conflictos-subtitulo');
        if (!tbodyConflictos) return;

        tbodyConflictos.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Buscando colisiones de inventario...
                </td>
            </tr>
        `;

        modalConflictos?.show();

        try {
            const resp = await fetch(`/canales-ical/${id}/conflictos`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al cargar conflictos');
            }

            if (subtitulo) {
                subtitulo.textContent = `Conexión: ${data.conexion_nombre || '#' + id}`;
            }

            renderizarConflictos(data.conflictos || []);

        } catch (error) {
            tbodyConflictos.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-danger">${escapeHtml(error.message)}</td>
                </tr>
            `;
        }
    }

    // Modal de conflictos globales
    document.getElementById('btn-ver-conflictos-globales')?.addEventListener('click', async function () {
        const tbodyConflictos = document.getElementById('tbody-conflictos');
        const subtitulo = document.getElementById('conflictos-subtitulo');
        if (!tbodyConflictos) return;

        tbodyConflictos.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Consultando colisiones en todas las conexiones...
                </td>
            </tr>
        `;

        if (subtitulo) {
            subtitulo.textContent = 'Mostrando todas las colisiones activas entre reservas locales y feeds externos';
        }

        modalConflictos?.show();

        try {
            const resp = await fetch('/canales-ical/conflictos-activos', {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Error al cargar conflictos globales');
            }

            renderizarConflictos(data.conflictos || []);

        } catch (error) {
            tbodyConflictos.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-danger">${escapeHtml(error.message)}</td>
                </tr>
            `;
        }
    });

    function renderizarConflictos(conflictos) {
        const tbody = document.getElementById('tbody-conflictos');
        if (!tbody) return;

        if (conflictos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-success">
                        <i class="fa-solid fa-circle-check f-s-20 d-block mb-1"></i>
                        No existen conflictos pendientes. El inventario local y las OTAs se encuentran en armonía determinista.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        conflictos.forEach(c => {
            html += `
                <tr>
                    <td>
                        <div class="f-w-600">${escapeHtml(c.unidad_nombre || 'Unidad')} (${escapeHtml(c.unidad_codigo || '')})</div>
                        <span class="badge ${c.canal_color_badge || 'bg-light-primary text-primary'}">${escapeHtml(c.canal_nombre || c.canal_codigo || 'Canal')}</span>
                    </td>
                    <td>
                        <div class="f-w-600 f-s-12">${escapeHtml(c.fecha_inicio)} al ${escapeHtml(c.fecha_fin)}</div>
                        <span class="badge bg-light-secondary text-secondary">${c.noches} noches</span>
                    </td>
                    <td>
                        <div class="f-s-12 text-dark">${escapeHtml(c.resumen || 'Bloqueo externo')}</div>
                        <div class="f-s-11 text-muted text-truncate" style="max-width: 150px;">UID: ${escapeHtml(c.uid_externo || '')}</div>
                    </td>
                    <td>
                        <div class="alert alert-danger py-1 px-2 mb-0 f-s-11">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <strong>RESERVA LOCAL vs BLOQUEO EXTERNO:</strong><br>
                            ${escapeHtml(c.detalle_conflicto || 'Colisión con reserva física existente')}
                        </div>
                    </td>
                    <td class="f-s-11 text-secondary">${escapeHtml(c.creado_en || '—')}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // =========================================================================
    // 14. EVENTOS DE FILTROS Y BÚSQUEDA
    // =========================================================================

    if (typeof jQuery !== 'undefined') {
        $('#filtro-propiedad, #filtro-canal').on('change', cargarConexiones);
    } else {
        filtroPropiedad?.addEventListener('change', cargarConexiones);
        filtroCanal?.addEventListener('change', cargarConexiones);
    }

    filtroEstado?.addEventListener('change', cargarConexiones);

    filtroBusqueda?.addEventListener('input', function () {
        renderizarTabla(aplicarFiltroTexto(conexionesCargadas));
    });

    btnLimpiarBusqueda?.addEventListener('click', function () {
        if (filtroBusqueda) {
            filtroBusqueda.value = '';
            renderizarTabla(conexionesCargadas);
        }
    });

    btnRecargar?.addEventListener('click', cargarConexiones);

    // =========================================================================
    // 15. UTILIDADES DEFENSIVAS
    // =========================================================================

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Carga inicial
    cargarConexiones();
});
