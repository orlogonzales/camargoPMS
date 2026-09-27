/**
 * Camargo PMS — Módulo de Gestión de Mantenimiento e Incidencias Técnicas (MANTENIMIENTO-1 / D-077)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - INCIDENCIA != ORDEN DE TRABAJO != BLOQUEO OPERATIVO.
 * - Formulario con geometría nativa Alina (app-form app-icon-form, border-radius 20px, select2 42px).
 * - D-071: Badges suaves bg-light-*, iconos Font Awesome 6.3.0, cero degradados.
 * - Concurrencia e inventario diario sparse blindado.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Modales Bootstrap
    const modalReportarIncidenciaEl = document.getElementById('modal-reportar-incidencia');
    const modalCrearOrdenEl = document.getElementById('modal-crear-orden');
    const modalCostosEl = document.getElementById('modal-costos-orden');
    const modalProrrogarEl = document.getElementById('modal-prorrogar-orden');
    const modalDetalleEl = document.getElementById('modal-detalle-orden');

    const modalReportarIncidencia = modalReportarIncidenciaEl ? new bootstrap.Modal(modalReportarIncidenciaEl) : null;
    const modalCrearOrden = modalCrearOrdenEl ? new bootstrap.Modal(modalCrearOrdenEl) : null;
    const modalCostos = modalCostosEl ? new bootstrap.Modal(modalCostosEl) : null;
    const modalProrrogar = modalProrrogarEl ? new bootstrap.Modal(modalProrrogarEl) : null;
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;

    // Elementos DOM de Tablas y Filtros
    const tbodyOrdenes = document.getElementById('tbody-ordenes');
    const tbodyIncidencias = document.getElementById('tbody-incidencias');

    const filtroOtBusqueda = document.getElementById('filtro-ot-busqueda');
    const filtroOtEstado = document.getElementById('filtro-ot-estado');
    const filtroOtTipo = document.getElementById('filtro-ot-tipo');
    const filtroOtPropiedad = document.getElementById('filtro-ot-propiedad');
    const btnRecargarOrdenes = document.getElementById('btn-recargar-ordenes');

    const filtroIncBusqueda = document.getElementById('filtro-inc-busqueda');
    const filtroIncEstado = document.getElementById('filtro-inc-estado');
    const filtroIncSeveridad = document.getElementById('filtro-inc-severidad');
    const filtroIncCategoria = document.getElementById('filtro-inc-categoria');
    const btnRecargarIncidencias = document.getElementById('btn-recargar-incidencias');

    // Botones de apertura
    const btnAbrirReportar = document.getElementById('btn-abrir-reportar-incidencia');
    const btnAbrirCrearOrden = document.getElementById('btn-abrir-crear-orden');

    // Formularios
    const formReportarIncidencia = document.getElementById('form-reportar-incidencia');
    const formCrearOrden = document.getElementById('form-crear-orden');
    const formCostos = document.getElementById('form-costos-orden');
    const formProrrogar = document.getElementById('form-prorrogar-orden');

    // KPIs
    const kpiIncidencias = document.getElementById('kpi-incidencias-abiertas');
    const kpiOrdenesProceso = document.getElementById('kpi-ordenes-proceso');
    const kpiBloqueadas = document.getElementById('kpi-unidades-bloqueadas');
    const kpiPreventivos = document.getElementById('kpi-preventivos-mes');

    // =========================================================================
    // Utilidades y Badges Alina
    // =========================================================================

    function escaparHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function badgeSeveridad(sev) {
        switch (sev) {
            case 'CRITICA':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-triangle-exclamation me-1"></i> CRÍTICA</span>';
            case 'ALTA':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-bell me-1"></i> ALTA</span>';
            case 'MEDIA':
                return '<span class="badge bg-light-primary text-primary f-w-600">MEDIA</span>';
            case 'BAJA':
                return '<span class="badge bg-light-info text-info f-w-600">BAJA</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(sev)}</span>`;
        }
    }

    function badgePrioridad(prio) {
        switch (prio) {
            case 'URGENTE':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-fire me-1"></i> URGENTE</span>';
            case 'ALTA':
                return '<span class="badge bg-light-warning text-warning f-w-600">ALTA</span>';
            case 'MEDIA':
                return '<span class="badge bg-light-primary text-primary f-w-600">MEDIA</span>';
            case 'BAJA':
                return '<span class="badge bg-light-info text-info f-w-600">BAJA</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(prio)}</span>`;
        }
    }

    function badgeEstadoIncidencia(estado) {
        switch (estado) {
            case 'REPORTADA':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-clock me-1"></i> REPORTADA</span>';
            case 'EN_EVALUACION':
                return '<span class="badge bg-light-info text-info f-w-600"><i class="fa-solid fa-magnifying-glass me-1"></i> EN EVALUACIÓN</span>';
            case 'CONVERTIDA_A_ORDEN':
                return '<span class="badge bg-light-primary text-primary f-w-600"><i class="fa-solid fa-screwdriver-wrench me-1"></i> EN ORDEN DE TRABAJO</span>';
            case 'RESUELTA_DIRECTA':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-check me-1"></i> RESUELTA DIRECTA</span>';
            case 'DESESTIMADA':
                return '<span class="badge bg-light-secondary text-secondary f-w-600"><i class="fa-solid fa-ban me-1"></i> DESESTIMADA</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoOrden(estado) {
        switch (estado) {
            case 'BORRADOR':
                return '<span class="badge bg-light-secondary text-secondary f-w-600"><i class="fa-solid fa-pen-ruler me-1"></i> BORRADOR</span>';
            case 'PROGRAMADA':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-calendar-check me-1"></i> PROGRAMADA</span>';
            case 'EN_PROCESO':
                return '<span class="badge bg-light-info text-info f-w-600"><i class="fa-solid fa-person-digging me-1"></i> EN PROCESO</span>';
            case 'COMPLETADA':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-circle-check me-1"></i> COMPLETADA</span>';
            case 'CANCELADA':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-xmark me-1"></i> CANCELADA</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function formatearDinero(monto) {
        const num = parseFloat(monto) || 0;
        return 'S/ ' + num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // =========================================================================
    // Carga de KPIs y Estadísticas
    // =========================================================================

    async function cargarEstadisticas() {
        try {
            const resp = await fetch('/api/mantenimiento/estadisticas');
            const data = await resp.json();
            if (data.ok && data.datos) {
                if (kpiIncidencias) kpiIncidencias.textContent = data.datos.incidencias_abiertas ?? 0;
                if (kpiOrdenesProceso) kpiOrdenesProceso.textContent = data.datos.ordenes_en_proceso ?? 0;
                if (kpiBloqueadas) kpiBloqueadas.textContent = data.datos.unidades_bloqueadas ?? 0;
                if (kpiPreventivos) kpiPreventivos.textContent = data.datos.preventivos_mes ?? 0;
            }
        } catch (e) {
            console.error('Error al cargar KPIs de mantenimiento:', e);
        }
    }

    // =========================================================================
    // Carga y Renderizado de Órdenes de Trabajo
    // =========================================================================

    async function cargarOrdenes() {
        if (!tbodyOrdenes) return;
        tbodyOrdenes.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando órdenes de trabajo...
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        if (filtroOtBusqueda?.value) params.append('q', filtroOtBusqueda.value);
        if (filtroOtEstado?.value) params.append('estado', filtroOtEstado.value);
        if (filtroOtTipo?.value) params.append('tipo', filtroOtTipo.value);
        if (filtroOtPropiedad?.value) params.append('propiedad_id', filtroOtPropiedad.value);

        try {
            const resp = await fetch('/api/mantenimiento/ordenes?' + params.toString());
            const data = await resp.json();

            if (!data.ok || !data.datos || data.datos.length === 0) {
                tbodyOrdenes.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open me-2"></i> No se encontraron órdenes de trabajo registradas.
                        </td>
                    </tr>
                `;
                return;
            }

            tbodyOrdenes.innerHTML = data.datos.map(ot => {
                const tipoBadge = ot.tipo === 'CORRECTIVO'
                    ? '<span class="badge bg-light-danger text-danger">CORRECTIVO</span>'
                    : '<span class="badge bg-light-success text-success">PREVENTIVO</span>';

                const bloqueoBadge = ot.requiere_bloqueo
                    ? `<span class="badge bg-light-danger text-danger" title="Bloquea del ${ot.fecha_bloqueo_inicio} al ${ot.fecha_bloqueo_fin}">
                         <i class="fa-solid fa-shield-halved me-1"></i> ${ot.fecha_bloqueo_inicio} a ${ot.fecha_bloqueo_fin}
                       </span>`
                    : '<span class="badge bg-light-secondary text-secondary">Sin Bloqueo</span>';

                const unidadStr = ot.unidad_numero
                    ? `<strong>${escaparHtml(ot.unidad_numero)}</strong> <small class="text-muted">(${escaparHtml(ot.unidad_nombre)})</small>`
                    : '<span class="text-muted f-s-12">Áreas Comunes</span>';

                const asignacionStr = ot.tipo_asignacion === 'EXTERNO'
                    ? `<span class="badge bg-light-info text-info"><i class="fa-solid fa-truck-field me-1"></i> ${escaparHtml(ot.proveedor_razon_social || 'Externo')}</span>`
                    : `<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-user-gear me-1"></i> ${escaparHtml(ot.colaborador_nombre_completo || 'Interno')}</span>`;

                let acciones = `
                    <button type="button" class="btn btn-outline-info btn-sm btn-ver-detalle" data-id="${ot.id}" title="Ver detalle e historial">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                `;

                if (ot.estado === 'BORRADOR') {
                    acciones += `
                        <button type="button" class="btn btn-primary btn-sm btn-programar-ot" data-id="${ot.id}" title="Programar y confirmar bloqueo">
                            <i class="fa-solid fa-calendar-check"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-cancelar-ot" data-id="${ot.id}" title="Cancelar borrador">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    `;
                } else if (ot.estado === 'PROGRAMADA') {
                    acciones += `
                        <button type="button" class="btn btn-info btn-sm btn-iniciar-ot text-white" data-id="${ot.id}" title="Iniciar trabajos">
                            <i class="fa-solid fa-play"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm btn-costos-ot" data-id="${ot.id}" data-materiales="${ot.costo_materiales}" data-mano-obra="${ot.costo_mano_obra}" title="Asentar costos">
                            <i class="fa-solid fa-calculator"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-cancelar-ot" data-id="${ot.id}" title="Cancelar orden">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    `;
                } else if (ot.estado === 'EN_PROCESO') {
                    acciones += `
                        <button type="button" class="btn btn-outline-success btn-sm btn-costos-ot" data-id="${ot.id}" data-materiales="${ot.costo_materiales}" data-mano-obra="${ot.costo_mano_obra}" title="Asentar costos">
                            <i class="fa-solid fa-calculator"></i>
                        </button>
                        <button type="button" class="btn btn-success btn-sm btn-completar-ot" data-id="${ot.id}" title="Culminar orden y liberar inventario">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    `;
                    if (ot.requiere_bloqueo) {
                        acciones += `
                            <button type="button" class="btn btn-outline-warning btn-sm btn-prorrogar-ot" data-id="${ot.id}" data-fin="${ot.fecha_bloqueo_fin}" title="Prorrogar inhabilitación">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </button>
                        `;
                    }
                    acciones += `
                        <button type="button" class="btn btn-outline-danger btn-sm btn-cancelar-ot" data-id="${ot.id}" title="Cancelar orden">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    `;
                }

                return `
                    <tr>
                        <td><strong class="text-primary">${escaparHtml(ot.codigo)}</strong></td>
                        <td>${tipoBadge} ${badgePrioridad(ot.prioridad)}</td>
                        <td>
                            <div>${escaparHtml(ot.propiedad_nombre)}</div>
                            ${unidadStr}
                        </td>
                        <td>
                            <div class="f-w-600">${escaparHtml(ot.titulo)}</div>
                            <small class="text-muted d-block text-truncate" style="max-width: 220px;">${escaparHtml(ot.descripcion)}</small>
                        </td>
                        <td>${asignacionStr}</td>
                        <td>
                            <div class="f-s-12">${ot.fecha_programada_inicio}</div>
                            <div class="f-s-11 text-muted">al ${ot.fecha_programada_fin}</div>
                        </td>
                        <td>${bloqueoBadge}</td>
                        <td>
                            <div class="f-w-700">${formatearDinero(ot.costo_total)}</div>
                            <small class="text-muted f-s-11">Est: ${formatearDinero(ot.costo_estimado)}</small>
                        </td>
                        <td>${badgeEstadoOrden(ot.estado)}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">${acciones}</div>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (e) {
            tbodyOrdenes.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Error al cargar órdenes de trabajo: ${escaparHtml(e.message)}
                    </td>
                </tr>
            `;
        }
    }

    // =========================================================================
    // Carga y Renderizado de Incidencias
    // =========================================================================

    async function cargarIncidencias() {
        if (!tbodyIncidencias) return;
        tbodyIncidencias.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando incidencias técnicas...
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        if (filtroIncBusqueda?.value) params.append('q', filtroIncBusqueda.value);
        if (filtroIncEstado?.value) params.append('estado', filtroIncEstado.value);
        if (filtroIncSeveridad?.value) params.append('severidad', filtroIncSeveridad.value);
        if (filtroIncCategoria?.value) params.append('categoria', filtroIncCategoria.value);

        try {
            const resp = await fetch('/api/mantenimiento/incidencias?' + params.toString());
            const data = await resp.json();

            if (!data.ok || !data.datos || data.datos.length === 0) {
                tbodyIncidencias.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open me-2"></i> No se encontraron incidencias técnicas registradas.
                        </td>
                    </tr>
                `;
                return;
            }

            tbodyIncidencias.innerHTML = data.datos.map(inc => {
                const unidadStr = inc.unidad_numero
                    ? `<strong>${escaparHtml(inc.unidad_numero)}</strong> <small class="text-muted">(${escaparHtml(inc.unidad_nombre)})</small>`
                    : '<span class="text-muted f-s-12">Áreas Comunes</span>';

                let acciones = '';
                if (inc.estado === 'REPORTADA') {
                    acciones = `
                        <button type="button" class="btn btn-outline-info btn-sm btn-evaluar-inc" data-id="${inc.id}" title="Pasar a evaluación">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm btn-resolver-directa-inc" data-id="${inc.id}" title="Resolver directamente">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-desestimar-inc" data-id="${inc.id}" title="Desestimar ticket">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    `;
                } else if (inc.estado === 'EN_EVALUACION') {
                    acciones = `
                        <button type="button" class="btn btn-outline-success btn-sm btn-resolver-directa-inc" data-id="${inc.id}" title="Resolver directamente">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-desestimar-inc" data-id="${inc.id}" title="Desestimar ticket">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    `;
                }

                return `
                    <tr>
                        <td><strong class="text-primary">${escaparHtml(inc.codigo)}</strong></td>
                        <td>${badgeSeveridad(inc.severidad)}</td>
                        <td><span class="badge bg-light-secondary text-secondary">${escaparHtml(inc.categoria)}</span></td>
                        <td>
                            <div>${escaparHtml(inc.propiedad_nombre)}</div>
                            ${unidadStr}
                        </td>
                        <td>
                            <div class="f-w-600">${escaparHtml(inc.titulo)}</div>
                            <small class="text-muted d-block text-truncate" style="max-width: 250px;">${escaparHtml(inc.descripcion)}</small>
                            ${inc.ubicacion_detallada ? `<small class="text-info"><i class="fa-solid fa-location-dot me-1"></i> ${escaparHtml(inc.ubicacion_detallada)}</small>` : ''}
                        </td>
                        <td>
                            <div class="f-s-12">${escaparHtml(inc.reportado_por_nombre || 'Desconocido')}</div>
                            <small class="text-muted">${escaparHtml(inc.reportado_por_documento || '')}</small>
                        </td>
                        <td><span class="f-s-12 text-muted">${inc.creado_en ? inc.creado_en.substring(0, 10) : ''}</span></td>
                        <td>${badgeEstadoIncidencia(inc.estado)}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">${acciones}</div>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (e) {
            tbodyIncidencias.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Error al cargar incidencias: ${escaparHtml(e.message)}
                    </td>
                </tr>
            `;
        }
    }

    // =========================================================================
    // Eventos y Acciones de Formularios
    // =========================================================================

    // Apertura modal reportar incidencia
    btnAbrirReportar?.addEventListener('click', () => {
        formReportarIncidencia?.reset();
        modalReportarIncidencia?.show();
    });

    // Guardar Reporte de Incidencia
    formReportarIncidencia?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(formReportarIncidencia);
        const payload = Object.fromEntries(fd.entries());
        payload._csrf = csrfToken;

        try {
            const btn = document.getElementById('btn-guardar-incidencia');
            if (btn) btn.disabled = true;

            const resp = await fetch('/api/mantenimiento/incidencias', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al reportar incidencia.');
            }

            modalReportarIncidencia?.hide();
            Swal.fire({
                icon: 'success',
                title: 'Incidencia Reportada',
                text: `Se ha generado el ticket ${data.datos.codigo}.`,
                timer: 2000,
                showConfirmButton: false,
            });

            cargarIncidencias();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        } finally {
            const btn = document.getElementById('btn-guardar-incidencia');
            if (btn) btn.disabled = false;
        }
    });

    // Apertura modal crear orden
    btnAbrirCrearOrden?.addEventListener('click', () => {
        formCrearOrden?.reset();
        const contenedorBloqueo = document.getElementById('contenedor-fechas-bloqueo');
        if (contenedorBloqueo) contenedorBloqueo.classList.add('d-none');
        modalCrearOrden?.show();
    });

    // Toggle switch de bloqueo
    const switchBloqueo = document.getElementById('ot-requiere-bloqueo');
    const contenedorBloqueo = document.getElementById('contenedor-fechas-bloqueo');
    switchBloqueo?.addEventListener('change', () => {
        if (switchBloqueo.checked) {
            contenedorBloqueo?.classList.remove('d-none');
            const inicio = document.getElementById('ot-fecha-prog-inicio')?.value;
            const fin = document.getElementById('ot-fecha-prog-fin')?.value;
            if (inicio) document.getElementById('ot-fecha-bloq-inicio').value = inicio;
            if (fin) document.getElementById('ot-fecha-bloq-fin').value = fin;
        } else {
            contenedorBloqueo?.classList.add('d-none');
        }
    });

    // Guardar Orden de Trabajo en Borrador
    formCrearOrden?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(formCrearOrden);
        const payload = Object.fromEntries(fd.entries());
        payload.requiere_bloqueo = switchBloqueo?.checked ? 1 : 0;
        payload._csrf = csrfToken;

        try {
            const btn = document.getElementById('btn-guardar-orden');
            if (btn) btn.disabled = true;

            const resp = await fetch('/api/mantenimiento/ordenes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al formular orden de trabajo.');
            }

            modalCrearOrden?.hide();
            Swal.fire({
                icon: 'success',
                title: 'Borrador Guardado',
                text: `Orden ${data.datos.codigo} formulada en estado BORRADOR.`,
                timer: 2000,
                showConfirmButton: false,
            });

            cargarOrdenes();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        } finally {
            const btn = document.getElementById('btn-guardar-orden');
            if (btn) btn.disabled = false;
        }
    });

    // Delegación de eventos en tabla de órdenes
    tbodyOrdenes?.addEventListener('click', async (e) => {
        const btnVer = e.target.closest('.btn-ver-detalle');
        const btnProgramar = e.target.closest('.btn-programar-ot');
        const btnIniciar = e.target.closest('.btn-iniciar-ot');
        const btnCostos = e.target.closest('.btn-costos-ot');
        const btnCompletar = e.target.closest('.btn-completar-ot');
        const btnCancelar = e.target.closest('.btn-cancelar-ot');
        const btnProrrogar = e.target.closest('.btn-prorrogar-ot');

        if (btnVer) {
            const id = btnVer.dataset.id;
            abrirDetalleOrden(id);
        } else if (btnProgramar) {
            const id = btnProgramar.dataset.id;
            confirmarProgramacionOrden(id);
        } else if (btnIniciar) {
            const id = btnIniciar.dataset.id;
            iniciarOrden(id);
        } else if (btnCostos) {
            const id = btnCostos.dataset.id;
            const mat = btnCostos.dataset.materiales;
            const mo = btnCostos.dataset.manoObra;
            abrirModalCostos(id, mat, mo);
        } else if (btnCompletar) {
            const id = btnCompletar.dataset.id;
            completarOrden(id);
        } else if (btnCancelar) {
            const id = btnCancelar.dataset.id;
            cancelarOrden(id);
        } else if (btnProrrogar) {
            const id = btnProrrogar.dataset.id;
            const fin = btnProrrogar.dataset.fin;
            abrirModalProrroga(id, fin);
        }
    });

    // Delegación de eventos en tabla de incidencias
    tbodyIncidencias?.addEventListener('click', async (e) => {
        const btnEvaluar = e.target.closest('.btn-evaluar-inc');
        const btnResolver = e.target.closest('.btn-resolver-directa-inc');
        const btnDesestimar = e.target.closest('.btn-desestimar-inc');

        if (btnEvaluar) {
            const id = btnEvaluar.dataset.id;
            evaluarIncidencia(id);
        } else if (btnResolver) {
            const id = btnResolver.dataset.id;
            resolverDirecta(id);
        } else if (btnDesestimar) {
            const id = btnDesestimar.dataset.id;
            desestimarIncidencia(id);
        }
    });

    // =========================================================================
    // Acciones Unitarias
    // =========================================================================

    async function confirmarProgramacionOrden(id) {
        const result = await Swal.fire({
            title: '¿Confirmar Programación?',
            text: 'Si la orden requiere bloqueo, las noches en el intervalo semiabierto quedarán inhabilitadas inmediatamente en el inventario diario de disponibilidad.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-calendar-check me-1"></i> Sí, Programar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
        });

        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`/api/mantenimiento/ordenes/${id}/programar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al programar orden.');
            }

            Swal.fire({
                icon: 'success',
                title: 'Orden Programada',
                text: 'La orden quedó programada y la disponibilidad de la unidad fue asegurada.',
                timer: 2000,
                showConfirmButton: false,
            });

            cargarOrdenes();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Conflicto o Error', text: err.message });
        }
    }

    async function iniciarOrden(id) {
        const result = await Swal.fire({
            title: '¿Iniciar Trabajos?',
            text: 'Se asentará la fecha y hora actual como inicio físico de labores (EN PROCESO).',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-play me-1"></i> Iniciar',
            cancelButtonText: 'Cancelar',
        });

        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`/api/mantenimiento/ordenes/${id}/iniciar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al iniciar trabajos.');
            }

            Swal.fire({ icon: 'success', title: 'En Proceso', text: 'Trabajos iniciados.', timer: 1500, showConfirmButton: false });
            cargarOrdenes();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }

    function abrirModalCostos(id, mat, mo) {
        document.getElementById('costos-orden-id').value = id;
        document.getElementById('costos-materiales').value = parseFloat(mat) || 0;
        document.getElementById('costos-mano-obra').value = parseFloat(mo) || 0;
        recalcularTotalCostos();
        modalCostos?.show();
    }

    function recalcularTotalCostos() {
        const mat = parseFloat(document.getElementById('costos-materiales')?.value) || 0;
        const mo = parseFloat(document.getElementById('costos-mano-obra')?.value) || 0;
        const total = mat + mo;
        const el = document.getElementById('costos-total-calculado');
        if (el) el.textContent = formatearDinero(total);
    }

    document.getElementById('costos-materiales')?.addEventListener('input', recalcularTotalCostos);
    document.getElementById('costos-mano-obra')?.addEventListener('input', recalcularTotalCostos);

    formCostos?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('costos-orden-id')?.value;
        const materiales = document.getElementById('costos-materiales')?.value || '0.00';
        const manoObra = document.getElementById('costos-mano-obra')?.value || '0.00';

        try {
            const resp = await fetch(`/api/mantenimiento/ordenes/${id}/costos`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ costo_materiales: materiales, costo_mano_obra: manoObra, _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al asentar costos.');
            }

            modalCostos?.hide();
            Swal.fire({ icon: 'success', title: 'Costos Asentados', text: 'Costos actualizados correctamente.', timer: 1500, showConfirmButton: false });
            cargarOrdenes();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    });

    async function completarOrden(id) {
        const { value: notas } = await Swal.fire({
            title: 'Culminar Orden de Trabajo',
            input: 'textarea',
            inputLabel: 'Notas de Cierre e Inspección Técnica',
            inputPlaceholder: 'Describa el trabajo realizado, pruebas hidráulicas/eléctricas y aprobación...',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Debe ingresar notas de cierre de al menos 5 caracteres.';
                }
            },
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check me-1"></i> Completar y Liberar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
        });

        if (!notas) return;

        try {
            const resp = await fetch(`/api/mantenimiento/ordenes/${id}/completar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ notas_cierre: notas, _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al culminar la orden.');
            }

            Swal.fire({
                icon: 'success',
                title: 'Orden Completada',
                text: 'La orden fue marcada como completada y las noches futuras fueron liberadas al mercado.',
                timer: 2000,
                showConfirmButton: false,
            });

            cargarOrdenes();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }

    async function cancelarOrden(id) {
        const { value: motivo } = await Swal.fire({
            title: 'Cancelar Orden de Trabajo',
            input: 'textarea',
            inputLabel: 'Motivo de Cancelación Obligatorio',
            inputPlaceholder: 'Indique por qué se aborta la orden...',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Debe ingresar un motivo de cancelación de al menos 5 caracteres.';
                }
            },
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-ban me-1"></i> Cancelar Orden',
            cancelButtonText: 'Volver',
            confirmButtonColor: '#dc3545',
        });

        if (!motivo) return;

        try {
            const resp = await fetch(`/api/mantenimiento/ordenes/${id}/cancelar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ motivo_cancelacion: motivo, _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al cancelar la orden.');
            }

            Swal.fire({
                icon: 'success',
                title: 'Orden Cancelada',
                text: 'La orden fue cancelada y las noches aplicables fueron liberadas.',
                timer: 2000,
                showConfirmButton: false,
            });

            cargarOrdenes();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }

    function abrirModalProrroga(id, fechaActual) {
        document.getElementById('prorroga-orden-id').value = id;
        document.getElementById('prorroga-fecha-actual').value = fechaActual || '';
        document.getElementById('prorroga-nueva-fecha').value = '';
        modalProrrogar?.show();
    }

    formProrrogar?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('prorroga-orden-id')?.value;
        const nuevaFin = document.getElementById('prorroga-nueva-fecha')?.value;

        try {
            const resp = await fetch(`/api/mantenimiento/ordenes/${id}/prorrogar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ nueva_fecha_fin: nuevaFin, _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al extender prórroga.');
            }

            modalProrrogar?.hide();
            Swal.fire({
                icon: 'success',
                title: 'Prórroga Confirmada',
                text: 'El bloqueo físico fue extendido sin conflictos.',
                timer: 2000,
                showConfirmButton: false,
            });

            cargarOrdenes();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Conflicto de Disponibilidad', text: err.message });
        }
    });

    async function evaluarIncidencia(id) {
        try {
            const resp = await fetch(`/api/mantenimiento/incidencias/${id}/evaluar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al pasar a evaluación.');
            }

            Swal.fire({ icon: 'success', title: 'En Evaluación', text: 'Ticket en revisión.', timer: 1500, showConfirmButton: false });
            cargarIncidencias();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }

    async function resolverDirecta(id) {
        const { value: motivo } = await Swal.fire({
            title: 'Resolver Incidencia Directa',
            input: 'textarea',
            inputLabel: 'Detalle de Solución In Situ',
            inputPlaceholder: 'Explique cómo se resolvió el desperfecto...',
            inputValidator: (val) => {
                if (!val || val.trim().length < 5) return 'Ingrese al menos 5 caracteres.';
            },
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-check me-1"></i> Resolver',
            cancelButtonText: 'Cancelar',
        });

        if (!motivo) return;

        try {
            const resp = await fetch(`/api/mantenimiento/incidencias/${id}/resolver-directa`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ motivo_cierre: motivo, _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al resolver.');
            }

            Swal.fire({ icon: 'success', title: 'Resuelta', text: 'Ticket cerrado directamente.', timer: 1500, showConfirmButton: false });
            cargarIncidencias();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }

    async function desestimarIncidencia(id) {
        const { value: motivo } = await Swal.fire({
            title: 'Desestimar Reporte',
            input: 'textarea',
            inputLabel: 'Justificación para Desestimar',
            inputPlaceholder: 'Indique motivo (duplicado, falsa alarma, etc.)...',
            inputValidator: (val) => {
                if (!val || val.trim().length < 5) return 'Ingrese al menos 5 caracteres.';
            },
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-ban me-1"></i> Desestimar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#6c757d',
        });

        if (!motivo) return;

        try {
            const resp = await fetch(`/api/mantenimiento/incidencias/${id}/desestimar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ motivo_cierre: motivo, _csrf: csrfToken }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                throw new Error(data.mensaje || 'Error al desestimar.');
            }

            Swal.fire({ icon: 'success', title: 'Desestimada', text: 'Ticket desestimado.', timer: 1500, showConfirmButton: false });
            cargarIncidencias();
            cargarEstadisticas();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }

    async function abrirDetalleOrden(id) {
        const cuerpo = document.getElementById('detalle-ot-cuerpo');
        if (!cuerpo) return;
        cuerpo.innerHTML = '<div class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando inspección de orden...</div>';
        modalDetalle?.show();

        try {
            const [respOt, respHist] = await Promise.all([
                fetch(`/api/mantenimiento/ordenes/${id}`),
                fetch(`/api/mantenimiento/ORDEN_TRABAJO/${id}/historial`),
            ]);

            const dataOt = await respOt.json();
            const dataHist = await respHist.json();

            if (!dataOt.ok || !dataOt.datos) {
                throw new Error('No se pudo consultar el detalle de la orden.');
            }

            const ot = dataOt.datos;
            const historial = dataHist.ok ? dataHist.datos : [];

            let incidenciasHtml = '<p class="text-muted f-s-12 mb-0">No tiene tickets de incidencias asociados.</p>';
            if (ot.incidencias_asociadas && ot.incidencias_asociadas.length > 0) {
                incidenciasHtml = `
                    <ul class="list-group list-group-flush border b-r-8 mb-0">
                        ${ot.incidencias_asociadas.map(inc => `
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <strong>${escaparHtml(inc.codigo)}</strong> — ${escaparHtml(inc.titulo)}
                                </div>
                                <div>
                                    ${badgeSeveridad(inc.severidad)}
                                    ${badgeEstadoIncidencia(inc.estado)}
                                </div>
                            </li>
                        `).join('')}
                    </ul>
                `;
            }

            let historialHtml = '<p class="text-muted f-s-12 mb-0">Sin transiciones registradas.</p>';
            if (historial.length > 0) {
                historialHtml = `
                    <div class="timeline-simple">
                        ${historial.map(h => `
                            <div class="border-start border-2 border-primary ps-3 pb-2 mb-2">
                                <div class="f-s-11 text-muted">${h.cambiado_en} — Por: <strong>${escaparHtml(h.actor_nombre || 'Sistema')}</strong></div>
                                <div class="f-w-600 f-s-13">${badgeEstadoOrden(h.estado_anterior)} &rarr; ${badgeEstadoOrden(h.estado_nuevo)}</div>
                                ${h.motivo ? `<div class="f-s-12 text-secondary mt-1">${escaparHtml(h.motivo)}</div>` : ''}
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            cuerpo.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light b-r-8">
                            <span class="text-secondary f-s-11 text-uppercase f-w-600">Identificación</span>
                            <h4 class="text-primary f-s-18 f-w-700 mb-1">${escaparHtml(ot.codigo)}</h4>
                            <div>${badgeEstadoOrden(ot.estado)} ${ot.tipo === 'CORRECTIVO' ? '<span class="badge bg-light-danger text-danger">CORRECTIVO</span>' : '<span class="badge bg-light-success text-success">PREVENTIVO</span>'} ${badgePrioridad(ot.prioridad)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light b-r-8">
                            <span class="text-secondary f-s-11 text-uppercase f-w-600">Ubicación y Asignación</span>
                            <div class="f-w-600 f-s-14 mt-1">${escaparHtml(ot.propiedad_nombre)}</div>
                            <div class="f-s-13">${ot.unidad_numero ? `Unidad ${escaparHtml(ot.unidad_numero)} (${escaparHtml(ot.unidad_nombre)})` : 'Áreas Comunes'}</div>
                            <div class="f-s-12 text-muted mt-1">Responsable: ${escaparHtml(ot.colaborador_nombre_completo || ot.proveedor_razon_social || 'Sin asignar')}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border p-3 b-r-8">
                            <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-1">Alcance Técnico</h6>
                            <h5 class="f-s-15 f-w-700 mb-1">${escaparHtml(ot.titulo)}</h5>
                            <p class="f-s-13 text-secondary mb-0">${escaparHtml(ot.descripcion)}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border p-3 b-r-8 h-100">
                            <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-2">Costeo Financiero (BCMath)</h6>
                            <div class="d-flex justify-content-between mb-1 f-s-13">
                                <span>Costo Estimado:</span>
                                <strong>${formatearDinero(ot.costo_estimado)}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1 f-s-13">
                                <span>Materiales:</span>
                                <strong>${formatearDinero(ot.costo_materiales)}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1 f-s-13">
                                <span>Mano de Obra:</span>
                                <strong>${formatearDinero(ot.costo_mano_obra)}</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between f-s-15 f-w-700 text-primary">
                                <span>Costo Total:</span>
                                <span>${formatearDinero(ot.costo_total)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border p-3 b-r-8 h-100">
                            <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-2">Inhabilitación y Tiempos</h6>
                            <div class="f-s-13 mb-1"><strong>Programación:</strong> ${ot.fecha_programada_inicio} al ${ot.fecha_programada_fin}</div>
                            <div class="f-s-13 mb-1"><strong>Bloqueo Diario:</strong> ${ot.requiere_bloqueo ? `<span class="text-danger f-w-600">${ot.fecha_bloqueo_inicio} al ${ot.fecha_bloqueo_fin}</span>` : '<span class="text-muted">No inhabilita</span>'}</div>
                            <div class="f-s-13 mb-1"><strong>Ejecución Inicio:</strong> ${ot.fecha_ejecucion_inicio || '<span class="text-muted">No iniciado</span>'}</div>
                            <div class="f-s-13"><strong>Ejecución Fin:</strong> ${ot.fecha_ejecucion_fin || '<span class="text-muted">No finalizado</span>'}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-2">Tickets de Incidencias Vinculados</h6>
                        ${incidenciasHtml}
                    </div>
                    <div class="col-12">
                        <h6 class="f-s-13 f-w-700 text-uppercase text-secondary mb-2">Bitácora de Estados Inmutable (D-061)</h6>
                        ${historialHtml}
                    </div>
                </div>
            `;
        } catch (err) {
            cuerpo.innerHTML = `<div class="alert alert-danger mb-0">${escaparHtml(err.message)}</div>`;
        }
    }

    // Filtros con debounce
    filtroOtBusqueda?.addEventListener('input', () => { cargarOrdenes(); });
    filtroOtEstado?.addEventListener('change', () => { cargarOrdenes(); });
    filtroOtTipo?.addEventListener('change', () => { cargarOrdenes(); });
    filtroOtPropiedad?.addEventListener('change', () => { cargarOrdenes(); });
    btnRecargarOrdenes?.addEventListener('click', () => { cargarOrdenes(); cargarEstadisticas(); });

    filtroIncBusqueda?.addEventListener('input', () => { cargarIncidencias(); });
    filtroIncEstado?.addEventListener('change', () => { cargarIncidencias(); });
    filtroIncSeveridad?.addEventListener('change', () => { cargarIncidencias(); });
    filtroIncCategoria?.addEventListener('change', () => { cargarIncidencias(); });
    btnRecargarIncidencias?.addEventListener('click', () => { cargarIncidencias(); cargarEstadisticas(); });

    // Inicialización al cargar la vista
    cargarEstadisticas();
    cargarOrdenes();
    cargarIncidencias();
});
