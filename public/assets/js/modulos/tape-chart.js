/**
 * Centro Operacional de Recepción — Tape Chart y Rack Hotelero (TAPE-CHART-1 / D-084).
 *
 * Principios vinculantes:
 * - TAPE CHART != FUENTE DE VERDAD (proyección en memoria sin tablas físicas).
 * - O(1) en número de consultas en backend.
 * - Doble eje: Tablero Calendario + Rack de Hoy (consistencia matemática compartida).
 * - Cero jQuery en lógica de negocio (Fetch API, Vanilla JS nativo).
 * - Refresco automático tras mutaciones exitosas en modales.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Referencias a elementos del DOM
    const filtroPropiedad = document.getElementById('filtro-propiedad');
    const filtroTipoUnidad = document.getElementById('filtro-tipo-unidad');
    const filtroPiso = document.getElementById('filtro-piso');
    const filtroRangoInput = document.getElementById('filtro-rango-personalizado');
    const btnRefrescar = document.getElementById('btn-refrescar-tape-chart');
    const btnNavAnterior = document.getElementById('btn-nav-anterior');
    const btnNavHoy = document.getElementById('btn-nav-hoy');
    const btnNavSiguiente = document.getElementById('btn-nav-siguiente');
    const botonesHorizonte = document.querySelectorAll('.btn-horizonte');
    const cardsKpiFiltro = document.querySelectorAll('.kpi-filtro-card');

    const skeletonEl = document.getElementById('tape-chart-skeleton');
    const errorEl = document.getElementById('tape-chart-error');
    const errorMensajeEl = document.getElementById('tape-chart-error-mensaje');
    const btnReintentar = document.getElementById('btn-reintentar-carga');
    const wrapperEl = document.getElementById('tape-chart-wrapper');
    const headerRowEl = document.getElementById('tape-chart-header-row');
    const bodyEl = document.getElementById('tape-chart-body');
    const rackHoyCardsEl = document.getElementById('contenedor-rack-hoy-cards');

    // Modal contextual
    const modalEl = document.getElementById('modal-detalle-celda');
    const modalBootstrap = modalEl ? new bootstrap.Modal(modalEl) : null;
    const modalTitulo = document.getElementById('modalDetalleCeldaTitulo');
    const modalSubtitulo = document.getElementById('modal-celda-subtitulo');
    const modalConflictosWrapper = document.getElementById('modal-celda-conflictos-wrapper');
    const modalConflictoTexto = document.getElementById('modal-celda-conflicto-texto');
    const modalEstadoTexto = document.getElementById('modal-celda-estado-texto');
    const modalLimpiezaBadge = document.getElementById('modal-celda-limpieza-badge');
    const modalTitular = document.getElementById('modal-celda-titular');
    const modalDocumento = document.getElementById('modal-celda-documento');
    const modalCodigo = document.getElementById('modal-celda-codigo');
    const modalDuracion = document.getElementById('modal-celda-duracion');
    const modalAcciones = document.getElementById('modal-celda-acciones');

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Estado reactivo local del módulo
    const estado = {
        propiedadId: filtroPropiedad ? parseInt(filtroPropiedad.value, 10) : 0,
        fechaDesde: '',
        fechaHasta: '',
        fechaHoteleraHoy: '',
        horizonteDias: 14,
        tipoUnidadId: null,
        pisoNivel: null,
        filtroKpiActivo: 'TODAS',
        proyeccion: null,
        cargando: false
    };

    /**
     * Inicializa el Flatpickr de Alina para el rango personalizado.
     */
    let flatpickrInstancia = null;
    if (filtroRangoInput && window.flatpickr) {
        flatpickrInstancia = window.flatpickr(filtroRangoInput, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: {
                firstDayOfWeek: 1,
                weekdays: { shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'], longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'] },
                months: { shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'], longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] }
            },
            onClose: (fechasSeleccionadas, fechaStr) => {
                if (fechasSeleccionadas.length === 2) {
                    const desde = formatearFechaYmd(fechasSeleccionadas[0]);
                    const hasta = formatearFechaYmd(fechasSeleccionadas[1]);
                    if (desde && hasta && hasta > desde) {
                        estado.fechaDesde = desde;
                        estado.fechaHasta = hasta;
                        // Desactivar botones de horizonte preconfigurado
                        botonesHorizonte.forEach(b => b.classList.remove('active', 'btn-primary'));
                        botonesHorizonte.forEach(b => b.classList.add('btn-outline-primary'));
                        cargarTapeChart();
                    }
                }
            }
        });
    }

    /**
     * Carga la proyección operacional completa desde el backend.
     */
    async function cargarTapeChart() {
        if (estado.cargando) return;
        estado.cargando = true;

        // Visualización de estado de carga
        if (skeletonEl) skeletonEl.classList.remove('d-none');
        if (errorEl) errorEl.classList.add('d-none');
        if (wrapperEl) wrapperEl.classList.add('d-none');

        const params = new URLSearchParams({
            propiedad_id: estado.propiedadId
        });

        if (estado.fechaDesde) params.append('fecha_desde', estado.fechaDesde);
        if (estado.fechaHasta) params.append('fecha_hasta', estado.fechaHasta);
        if (estado.tipoUnidadId) params.append('tipo_unidad_id', estado.tipoUnidadId);
        if (estado.pisoNivel) params.append('piso_nivel', estado.pisoNivel);

        try {
            const resp = await fetch(`/tape-chart/datos?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!resp.ok) {
                const errorData = await resp.json().catch(() => ({}));
                throw new Error(errorData.mensaje || `Error HTTP ${resp.status} al consultar el Tape Chart.`);
            }

            const data = await resp.json();
            if (!data.ok || !data.datos) {
                throw new Error(data.mensaje || 'Respuesta inválida del servidor.');
            }

            estado.proyeccion = data.datos;
            estado.fechaHoteleraHoy = data.datos.propiedad.fecha_hotelera_hoy;
            estado.fechaDesde = data.datos.horizonte.fecha_desde;
            estado.fechaHasta = data.datos.horizonte.fecha_hasta;

            // Sincronizar input Flatpickr si está inicializado
            if (flatpickrInstancia) {
                flatpickrInstancia.setDate([estado.fechaDesde, estado.fechaHasta], false);
            }

            // Renderizar componentes
            renderizarKpisHoy(data.datos.kpis_hoy);
            renderizarCuadriculaTapeChart(data.datos);
            renderizarRackHoyCards(data.datos);

            // Mostrar el tablero
            if (skeletonEl) skeletonEl.classList.add('d-none');
            if (wrapperEl) wrapperEl.classList.remove('d-none');
        } catch (err) {
            console.error('Error al cargar Tape Chart:', err);
            if (skeletonEl) skeletonEl.classList.add('d-none');
            if (wrapperEl) wrapperEl.classList.add('d-none');
            if (errorEl) {
                errorEl.classList.remove('d-none');
                if (errorMensajeEl) errorMensajeEl.textContent = err.message;
            }
        } finally {
            estado.cargando = false;
        }
    }

    /**
     * Renderiza los KPIs del Rack de Hoy con consistencia matemática compartida.
     */
    function renderizarKpisHoy(kpis) {
        if (!kpis) return;

        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val !== undefined ? val : '-';
        };

        setTxt('kpi-total-unidades', kpis.total_unidades);
        setTxt('kpi-stayover', kpis.ocupadas_stayover);
        setTxt('kpi-arrivals', kpis.llegadas_hoy);
        setTxt('kpi-departures', kpis.salidas_hoy);
        setTxt('kpi-vr', kpis.vacantes_listas_vr);
        setTxt('kpi-vd', kpis.vacantes_sucias_vd);
        setTxt('kpi-vcl', kpis.vacantes_limpieza_vcl);
        setTxt('kpi-ooo', kpis.fuera_servicio_ooo);
    }

    /**
     * Renderiza la cuadrícula continua del Tape Chart.
     */
    function renderizarCuadriculaTapeChart(datos) {
        if (!headerRowEl || !bodyEl) return;

        headerRowEl.innerHTML = '';
        bodyEl.innerHTML = '';

        const columnas = datos.columnas_fechas || [];
        const unidades = datos.filas_unidades || [];

        // 1. Cabecera: Esquina Sticky de Unidades
        const thEsquina = document.createElement('th');
        thEsquina.className = 'tape-sticky-corner text-uppercase f-s-12 p-2';
        thEsquina.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <span>Unidad</span>
                <span class="badge bg-light-secondary text-secondary f-s-10">${unidades.length} u.</span>
            </div>
        `;
        headerRowEl.appendChild(thEsquina);

        // Columnas de días
        columnas.forEach(col => {
            const th = document.createElement('th');
            th.className = `tape-sticky-header p-2 f-s-12 ${col.es_hoy ? 'tape-header-hoy' : ''}`;
            th.innerHTML = `
                <span class="d-block f-s-11 text-uppercase ${col.es_hoy ? 'text-primary f-w-700' : 'text-secondary'}">${col.dia_nombre_corto}</span>
                <span class="f-s-14 f-w-700 d-block">${col.dia_numero}</span>
                <span class="f-s-10 text-secondary text-uppercase d-block">${col.mes_nombre_corto}</span>
            `;
            headerRowEl.appendChild(th);
        });

        // 2. Filas de Unidades
        const unidadesFiltradas = filtrarUnidadesSegunKpi(unidades, estado.filtroKpiActivo, estado.fechaHoteleraHoy);

        if (unidadesFiltradas.length === 0) {
            const trVacio = document.createElement('tr');
            trVacio.innerHTML = `
                <td colspan="${columnas.length + 1}" class="text-center py-5 text-secondary">
                    <i class="fa-solid fa-filter-circle-xmark f-s-28 mb-2 d-block opacity-50"></i>
                    No hay unidades que coincidan con el filtro seleccionado (<strong>${estado.filtroKpiActivo}</strong>).
                </td>
            `;
            bodyEl.appendChild(trVacio);
            return;
        }

        unidadesFiltradas.forEach(u => {
            const tr = document.createElement('tr');

            // Celda Sticky de Unidad (Eje Y)
            const tdUnidad = document.createElement('td');
            tdUnidad.className = 'tape-sticky-unit p-2';
            tdUnidad.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="f-s-14 text-dark">${escaparHtml(u.codigo)}</strong>
                    <span class="badge ${u.limpieza_hoy.clase} f-s-10">${u.limpieza_hoy.codigo}</span>
                </div>
                <div class="text-truncate text-secondary f-s-11 mb-1" title="${escaparHtml(u.nombre)}">
                    ${escaparHtml(u.nombre)}
                </div>
                <div class="d-flex justify-content-between text-secondary f-s-10">
                    <span>${u.piso_nivel ? 'Piso ' + escaparHtml(u.piso_nivel) : 'P.B.'}</span>
                    <span><i class="fa-solid fa-user-group me-1"></i>${u.capacidad_personas}</span>
                </div>
            `;
            tr.appendChild(tdUnidad);

            // Celdas por fecha
            columnas.forEach(col => {
                const fechaStr = col.fecha;
                const celda = u.celdas[fechaStr] || null;

                const td = document.createElement('td');
                td.className = `tape-celda ${col.es_hoy ? 'tape-col-hoy' : ''} ${celda ? celda.clase_color : ''}`;

                if (celda) {
                    td.innerHTML = generarHtmlBloqueCelda(celda, u, col.es_hoy);
                    td.addEventListener('click', () => abrirModalDetalle(celda, u));
                } else {
                    td.innerHTML = `<div class="tape-bloque-vacio">-</div>`;
                }

                tr.appendChild(td);
            });

            bodyEl.appendChild(tr);
        });
    }

    /**
     * Genera el HTML interno de una celda del Tape Chart con indicadores visuales Alina.
     */
    function generarHtmlBloqueCelda(celda, unidad, esHoy) {
        if (celda.estado_principal === 'VACANTE') {
            if (esHoy) {
                return `
                    <div class="tape-bloque" title="Unidad ${unidad.codigo} — Vacante (${celda.subestado})">
                        <span class="tape-bloque-titulo text-uppercase f-s-11 f-w-700">${celda.subestado}</span>
                        <span class="tape-bloque-subtitulo">Libre</span>
                    </div>
                `;
            }
            return `<div class="tape-bloque-vacio"><i class="fa-solid fa-plus opacity-25"></i></div>`;
        }

        const icono = resolverIconoEstado(celda.estado_principal);
        const tieneConflicto = celda.conflictos && celda.conflictos.length > 0;

        return `
            <div class="tape-bloque" title="${escaparHtml(celda.titular_nombre || celda.codigo_referencia || celda.estado_principal)}">
                ${tieneConflicto ? `<span class="tape-badge-conflicto" title="Conflicto detectado"><i class="fa-solid fa-triangle-exclamation"></i></span>` : ''}
                <div class="tape-bloque-titulo">
                    <i class="${icono} me-1 f-s-10"></i>${escaparHtml(celda.titular_nombre || celda.codigo_referencia || celda.estado_principal)}
                </div>
                <div class="tape-bloque-subtitulo d-flex justify-content-between">
                    <span>${celda.codigo_referencia ? escaparHtml(celda.codigo_referencia) : celda.subestado}</span>
                    <span>${celda.noche_indice}/${celda.duracion_noches}n</span>
                </div>
            </div>
        `;
    }

    /**
     * Renderiza las tarjetas del Rack Operacional de Hoy (Pestaña 2).
     */
    function renderizarRackHoyCards(datos) {
        if (!rackHoyCardsEl) return;
        rackHoyCardsEl.innerHTML = '';

        const unidades = datos.filas_unidades || [];
        const fechaHoy = datos.propiedad.fecha_hotelera_hoy;
        const unidadesFiltradas = filtrarUnidadesSegunKpi(unidades, estado.filtroKpiActivo, fechaHoy);

        if (unidadesFiltradas.length === 0) {
            rackHoyCardsEl.innerHTML = `
                <div class="col-12 text-center py-5 text-secondary">
                    <i class="fa-solid fa-door-closed f-s-32 mb-2 d-block opacity-50"></i>
                    No hay unidades que coincidan con el filtro actual en el Rack de Hoy.
                </div>
            `;
            return;
        }

        unidadesFiltradas.forEach(u => {
            const celdaHoy = u.celdas[fechaHoy];
            const colCard = document.createElement('div');
            colCard.className = 'col-xl-3 col-lg-4 col-md-6 col-12';

            const icono = celdaHoy ? resolverIconoEstado(celdaHoy.estado_principal) : 'fa-solid fa-door-open';
            const estadoTexto = celdaHoy ? (celdaHoy.titular_nombre || celdaHoy.codigo_referencia || celdaHoy.estado_principal) : 'Disponible';

            colCard.innerHTML = `
                <div class="card border b-r-12 h-100 shadow-sm p-3 kpi-filtro-card" style="cursor: pointer;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span class="p-2 b-r-8 me-2 bg-light-primary text-primary">
                                <i class="${icono} f-s-16"></i>
                            </span>
                            <div>
                                <h6 class="mb-0 f-s-15 f-w-700 text-dark">Unidad ${escaparHtml(u.codigo)}</h6>
                                <span class="text-secondary f-s-11">${escaparHtml(u.tipo_unidad_nombre)} • Piso ${escaparHtml(u.piso_nivel || 'PB')}</span>
                            </div>
                        </div>
                        <span class="badge ${u.limpieza_hoy.clase} f-s-11">${u.limpieza_hoy.codigo}</span>
                    </div>
                    <div class="bg-light-subtle p-2 b-r-8 mb-2">
                        <span class="text-secondary f-s-11 d-block">Condición Hoy:</span>
                        <strong class="f-s-13 text-dark text-truncate d-block">${escaparHtml(estadoTexto)}</strong>
                        ${celdaHoy && celdaHoy.codigo_referencia ? `<span class="f-s-11 text-primary font-monospace">${escaparHtml(celdaHoy.codigo_referencia)}</span>` : ''}
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top f-s-11 text-secondary">
                        <span><i class="fa-solid fa-user-group me-1"></i>Cap. ${u.capacidad_personas} pers.</span>
                        <span class="text-primary f-w-600">Ver Detalles <i class="fa-solid fa-chevron-right ms-1 f-s-9"></i></span>
                    </div>
                </div>
            `;

            colCard.querySelector('.card').addEventListener('click', () => {
                if (celdaHoy) {
                    abrirModalDetalle(celdaHoy, u);
                }
            });

            rackHoyCardsEl.appendChild(colCard);
        });
    }

    /**
     * Abre el modal de detalle contextual respetando RBAC y revalidación transaccional (Ajuste 14).
     */
    function abrirModalDetalle(celda, unidad) {
        if (!modalBootstrap) return;

        modalTitulo.textContent = `Unidad ${unidad.codigo} — ${unidad.nombre}`;
        modalSubtitulo.textContent = `Fecha: ${celda.fecha} • Tipo: ${unidad.tipo_unidad_nombre}`;

        // Alerta de conflictos concurrentes (Ajuste 3)
        if (celda.conflictos && celda.conflictos.length > 0) {
            modalConflictosWrapper.classList.remove('d-none');
            modalConflictoTexto.textContent = celda.conflictos.join(', ');
        } else {
            modalConflictosWrapper.classList.add('d-none');
        }

        modalEstadoTexto.textContent = `${celda.estado_principal} (${celda.subestado})`;
        modalLimpiezaBadge.className = `badge ${unidad.limpieza_hoy.clase}`;
        modalLimpiezaBadge.textContent = unidad.limpieza_hoy.texto;

        modalTitular.textContent = celda.titular_nombre || 'Ninguno';
        modalDocumento.textContent = celda.titular_documento || '-';
        modalCodigo.textContent = celda.codigo_referencia || '-';
        modalDuracion.textContent = `${celda.noche_indice} de ${celda.duracion_noches} noche(s)`;

        // Inyección dinámica de acciones candidatas según RBAC
        modalAcciones.innerHTML = '';
        const acciones = celda.acciones_candidatas || [];

        if (acciones.length === 0) {
            modalAcciones.innerHTML = `<span class="text-secondary f-s-12 text-center d-block py-2">Sin acciones permitidas para su rol.</span>`;
        } else {
            acciones.forEach(acc => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm b-r-20 text-start d-flex justify-content-between align-items-center';

                if (acc === 'CHECKIN_RAPIDO') {
                    btn.classList.add('btn-success');
                    btn.innerHTML = `<span><i class="fa-solid fa-key me-2"></i>Realizar Check-in Rápido</span> <i class="fa-solid fa-arrow-right"></i>`;
                    btn.addEventListener('click', () => ejecutarAccionContextual('CHECKIN_RAPIDO', celda, unidad));
                } else if (acc === 'CHECKOUT_RAPIDO') {
                    btn.classList.add('btn-warning');
                    btn.innerHTML = `<span><i class="fa-solid fa-door-closed me-2"></i>Realizar Check-out</span> <i class="fa-solid fa-arrow-right"></i>`;
                    btn.addEventListener('click', () => ejecutarAccionContextual('CHECKOUT_RAPIDO', celda, unidad));
                } else if (acc === 'VER_RESERVA') {
                    btn.classList.add('btn-outline-primary');
                    btn.innerHTML = `<span><i class="fa-solid fa-calendar-check me-2"></i>Ver Detalle de Reserva</span> <i class="fa-solid fa-arrow-up-right-from-square"></i>`;
                    btn.addEventListener('click', () => {
                        window.location.href = `/reservas`;
                    });
                } else if (acc === 'VER_ESTADIA') {
                    btn.classList.add('btn-outline-primary');
                    btn.innerHTML = `<span><i class="fa-solid fa-user-check me-2"></i>Ver Detalle de Estadía</span> <i class="fa-solid fa-arrow-up-right-from-square"></i>`;
                    btn.addEventListener('click', () => {
                        window.location.href = `/estadias`;
                    });
                } else if (acc === 'VER_MANTENIMIENTO') {
                    btn.classList.add('btn-outline-dark');
                    btn.innerHTML = `<span><i class="fa-solid fa-screwdriver-wrench me-2"></i>Ver Orden de Mantenimiento</span> <i class="fa-solid fa-arrow-up-right-from-square"></i>`;
                    btn.addEventListener('click', () => {
                        window.location.href = `/mantenimiento`;
                    });
                } else if (acc === 'BLOQUEAR') {
                    btn.classList.add('btn-outline-danger');
                    btn.innerHTML = `<span><i class="fa-solid fa-lock me-2"></i>Bloquear Disponibilidad</span> <i class="fa-solid fa-arrow-right"></i>`;
                    btn.addEventListener('click', () => {
                        window.location.href = `/disponibilidad`;
                    });
                } else if (acc === 'NUEVA_RESERVA') {
                    btn.classList.add('btn-outline-success');
                    btn.innerHTML = `<span><i class="fa-solid fa-plus me-2"></i>Crear Reserva para esta fecha</span> <i class="fa-solid fa-arrow-right"></i>`;
                    btn.addEventListener('click', () => {
                        window.location.href = `/reservas`;
                    });
                }

                modalAcciones.appendChild(btn);
            });
        }

        modalBootstrap.show();
    }

    /**
     * Ejecuta una acción contextual de recepción con revalidación transaccional y refresco automático (Ajustes 14 y 15).
     */
    async function ejecutarAccionContextual(accion, celda, unidad) {
        if (modalBootstrap) modalBootstrap.hide();

        if (accion === 'CHECKOUT_RAPIDO') {
            const confirm = await Swal.fire({
                title: '¿Confirmar Check-out?',
                text: `Se finalizará la estadía ${celda.codigo_referencia} de la Unidad ${unidad.codigo}. La unidad pasará a SUCIA y se formulará la tarea de limpieza.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, finalizar estadía',
                cancelButtonText: 'Cancelar'
            });

            if (!confirm.isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('estadia_id', celda.referencia_id);
                formData.append('_csrf_token', csrfToken);

                const resp = await fetch(`/estadias/${celda.referencia_id}/checkout`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const resData = await resp.json().catch(() => ({}));
                if (!resp.ok || !resData.ok) {
                    throw new Error(resData.mensaje || 'Error al procesar el check-out en el backend.');
                }

                await Swal.fire('Check-out Realizado', resData.mensaje || 'Estadía finalizada con éxito.', 'success');
                // Refresco automático de la proyección (Ajuste 15)
                cargarTapeChart();
            } catch (err) {
                Swal.fire('Conflicto Operacional', err.message, 'error');
            }
        } else if (accion === 'CHECKIN_RAPIDO') {
            window.location.href = `/estadias`;
        }
    }

    /**
     * Filtra unidades según el botón de KPI activo en el Rack de Hoy.
     */
    function filtrarUnidadesSegunKpi(unidades, filtroKpi, fechaHoy) {
        if (!filtroKpi || filtroKpi === 'TODAS') return unidades;

        return unidades.filter(u => {
            const celdaHoy = u.celdas[fechaHoy];
            if (!celdaHoy) return false;

            if (filtroKpi === 'STAYOVER') {
                return celdaHoy.es_stayover;
            }
            if (filtroKpi === 'ARRIVAL') {
                return celdaHoy.es_arrival;
            }
            if (filtroKpi === 'DEPARTURE') {
                return celdaHoy.es_departure;
            }
            if (filtroKpi === 'VR') {
                return u.limpieza_hoy.codigo === 'VR' && celdaHoy.estado_principal === 'VACANTE';
            }
            if (filtroKpi === 'VD') {
                return u.limpieza_hoy.codigo === 'VD' && celdaHoy.estado_principal === 'VACANTE';
            }
            if (filtroKpi === 'VCL') {
                return u.limpieza_hoy.codigo === 'VCL' && celdaHoy.estado_principal === 'VACANTE';
            }
            if (filtroKpi === 'OOO') {
                return celdaHoy.estado_principal === 'MANTENIMIENTO_OOO';
            }

            return true;
        });
    }

    /**
     * Resuelve el icono Font Awesome 6 correspondiente a cada estado.
     */
    function resolverIconoEstado(estado) {
        switch (estado) {
            case 'ESTADIA': return 'fa-solid fa-user-check';
            case 'RESERVA': return 'fa-solid fa-calendar-check';
            case 'HOLD_PENDIENTE': return 'fa-solid fa-hourglass-half';
            case 'ARRENDAMIENTO': return 'fa-solid fa-file-contract';
            case 'MANTENIMIENTO_OOO': return 'fa-solid fa-screwdriver-wrench';
            case 'BLOQUEO_MANUAL': return 'fa-solid fa-ban';
            default: return 'fa-solid fa-door-open';
        }
    }

    /**
     * Utilidad para formatear fechas a YYYY-MM-DD.
     */
    function formatearFechaYmd(dateObj) {
        const anio = dateObj.getFullYear();
        const mes = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dia = String(dateObj.getDate()).padStart(2, '0');
        return `${anio}-${mes}-${dia}`;
    }

    /**
     * Utilidad para escapar texto HTML contra XSS.
     */
    function escaparHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================================
    // LISTENERS DE EVENTOS Y VINCULACIONES
    // =========================================================================

    // Cambio de propiedad
    if (filtroPropiedad) {
        filtroPropiedad.addEventListener('change', () => {
            estado.propiedadId = parseInt(filtroPropiedad.value, 10);
            cargarTapeChart();
        });
    }

    // Cambio de tipología y piso
    if (filtroTipoUnidad) {
        filtroTipoUnidad.addEventListener('change', () => {
            estado.tipoUnidadId = filtroTipoUnidad.value ? parseInt(filtroTipoUnidad.value, 10) : null;
            cargarTapeChart();
        });
    }
    if (filtroPiso) {
        let timerPiso = null;
        filtroPiso.addEventListener('input', () => {
            clearTimeout(timerPiso);
            timerPiso = setTimeout(() => {
                estado.pisoNivel = filtroPiso.value.trim() || null;
                cargarTapeChart();
            }, 300);
        });
    }

    // Botones de Horizonte (7, 14, 30 Días)
    botonesHorizonte.forEach(btn => {
        btn.addEventListener('click', () => {
            botonesHorizonte.forEach(b => b.classList.remove('active', 'btn-primary'));
            botonesHorizonte.forEach(b => b.classList.add('btn-outline-primary'));
            btn.classList.add('active', 'btn-primary');
            btn.classList.remove('btn-outline-primary');

            estado.horizonteDias = parseInt(btn.dataset.dias, 10);

            if (estado.fechaDesde) {
                const dtDesde = new Date(estado.fechaDesde + 'T00:00:00');
                const dtHasta = new Date(dtDesde);
                dtHasta.setDate(dtHasta.getDate() + estado.horizonteDias);
                estado.fechaHasta = formatearFechaYmd(dtHasta);
            }
            cargarTapeChart();
        });
    });

    // Navegación temporal
    if (btnNavAnterior) {
        btnNavAnterior.addEventListener('click', () => {
            if (!estado.fechaDesde) return;
            const dtDesde = new Date(estado.fechaDesde + 'T00:00:00');
            dtDesde.setDate(dtDesde.getDate() - estado.horizonteDias);
            const dtHasta = new Date(dtDesde);
            dtHasta.setDate(dtHasta.getDate() + estado.horizonteDias);

            estado.fechaDesde = formatearFechaYmd(dtDesde);
            estado.fechaHasta = formatearFechaYmd(dtHasta);
            cargarTapeChart();
        });
    }
    if (btnNavHoy) {
        btnNavHoy.addEventListener('click', () => {
            if (!estado.fechaHoteleraHoy) return;
            const dtDesde = new Date(estado.fechaHoteleraHoy + 'T00:00:00');
            const dtHasta = new Date(dtDesde);
            dtHasta.setDate(dtHasta.getDate() + estado.horizonteDias);

            estado.fechaDesde = formatearFechaYmd(dtDesde);
            estado.fechaHasta = formatearFechaYmd(dtHasta);
            cargarTapeChart();
        });
    }
    if (btnNavSiguiente) {
        btnNavSiguiente.addEventListener('click', () => {
            if (!estado.fechaDesde) return;
            const dtDesde = new Date(estado.fechaDesde + 'T00:00:00');
            dtDesde.setDate(dtDesde.getDate() + estado.horizonteDias);
            const dtHasta = new Date(dtDesde);
            dtHasta.setDate(dtHasta.getDate() + estado.horizonteDias);

            estado.fechaDesde = formatearFechaYmd(dtDesde);
            estado.fechaHasta = formatearFechaYmd(dtHasta);
            cargarTapeChart();
        });
    }

    // Botón refrescar
    if (btnRefrescar) btnRefrescar.addEventListener('click', cargarTapeChart);
    if (btnReintentar) btnReintentar.addEventListener('click', cargarTapeChart);

    // Clic en tarjetas KPI para filtrar unidades (Ajuste 12)
    cardsKpiFiltro.forEach(card => {
        card.addEventListener('click', () => {
            cardsKpiFiltro.forEach(c => c.classList.remove('activo'));
            card.classList.add('activo');
            estado.filtroKpiActivo = card.dataset.filtro || 'TODAS';

            if (estado.proyeccion) {
                renderizarCuadriculaTapeChart(estado.proyeccion);
                renderizarRackHoyCards(estado.proyeccion);
            }
        });
    });

    // Carga inicial
    cargarTapeChart();
});
