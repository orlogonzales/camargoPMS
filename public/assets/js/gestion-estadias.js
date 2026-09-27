/**
 * Camargo PMS — Módulo de Gestión Operativa de Estadías, Check-in y Huéspedes (ESTADÍAS-1)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - 1 Reserva : N Estadías físicas independientes (una por reserva_unidad).
 * - UNIQUE(reserva_unidad_id): una reserva_unidad genera como máximo una estadía histórica.
 * - Bloqueo estricto de capacidad: 1 <= count(huéspedes) <= unidad.capacidad_personas.
 * - Exactamente 1 huésped responsable por estadía.
 * - Inmutabilidad histórica: Cero DELETE sobre estadías y huéspedes.
 * - D-071: Font Awesome 6.3.0 exclusivo, Flatpickr Date Picker, Variants of badge & chip Alina.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Elementos de la tabla y filtros
    const tablaEstadias = document.getElementById('tabla-estadias');
    const tbodyEstadias = document.getElementById('tbody-estadias');
    const filtroBusqueda = document.getElementById('filtro-busqueda-estadia');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda');
    const filtroEstado = document.getElementById('filtro-estado-estadia');
    const filtroPropiedad = document.getElementById('filtro-propiedad-estadia');
    const filtroFechaDesde = document.getElementById('filtro-fecha-desde');
    const filtroFechaHasta = document.getElementById('filtro-fecha-hasta');
    const btnRecargar = document.getElementById('btn-recargar-estadias');
    const paginacionResumen = document.getElementById('paginacion-resumen-estadias');
    const paginacionControl = document.getElementById('paginacion-control-estadias');

    // Botones de acción general
    const btnAbrirCheckin = document.getElementById('btn-abrir-checkin');

    // KPIs
    const kpiActivas = document.getElementById('kpi-estadias-activas');
    const kpiFinalizadas = document.getElementById('kpi-estadias-finalizadas');
    const kpiAnuladas = document.getElementById('kpi-estadias-anuladas');

    // Modal Check-in
    const modalCheckinEl = document.getElementById('modal-checkin');
    const modalCheckin = modalCheckinEl ? new bootstrap.Modal(modalCheckinEl) : null;
    const formCheckin = document.getElementById('form-checkin');
    const selectReservaCheckin = document.getElementById('checkin-reserva-id');
    const selectUnidadCheckin = document.getElementById('checkin-reserva-unidad-id');
    const inputFechaEntradaCheckin = document.getElementById('checkin-fecha-entrada');
    const inputFechaSalidaCheckin = document.getElementById('checkin-fecha-salida');
    const inputLlaveCheckin = document.getElementById('checkin-identificador-llave');
    const inputObsCheckin = document.getElementById('checkin-observaciones');
    const badgeCapacidadUnidad = document.getElementById('badge-capacidad-unidad');
    const ayudaCapacidadUnidad = document.getElementById('ayuda-capacidad-unidad');
    const tbodyHuespedesCheckin = document.getElementById('tbody-huespedes-checkin');
    const btnAgregarHuesped = document.getElementById('btn-agregar-huesped');
    const resumenConteoHuespedes = document.getElementById('resumen-conteo-huespedes');
    const alertaErrorHuespedes = document.getElementById('alerta-error-huespedes');
    const btnGuardarCheckin = document.getElementById('btn-guardar-checkin');
    const spinnerCheckin = document.getElementById('spinner-guardar-checkin');

    // Modal Check-out
    const modalCheckoutEl = document.getElementById('modal-checkout');
    const modalCheckout = modalCheckoutEl ? new bootstrap.Modal(modalCheckoutEl) : null;
    const formCheckout = document.getElementById('form-checkout');
    const inputCheckoutId = document.getElementById('checkout-estadia-id');
    const checkoutTextoCodigo = document.getElementById('checkout-texto-codigo');
    const checkoutTextoUnidad = document.getElementById('checkout-texto-unidad');
    const checkoutTextoResponsable = document.getElementById('checkout-texto-responsable');
    const checkoutTextoLlave = document.getElementById('checkout-texto-llave');
    const inputObsCheckout = document.getElementById('checkout-observaciones');
    const btnConfirmarCheckout = document.getElementById('btn-confirmar-checkout');
    const spinnerCheckout = document.getElementById('spinner-checkout');

    // Modal Anular
    const modalAnularEl = document.getElementById('modal-anular-estadia');
    const modalAnular = modalAnularEl ? new bootstrap.Modal(modalAnularEl) : null;
    const formAnular = document.getElementById('form-anular-estadia');
    const inputAnularId = document.getElementById('anular-estadia-id');
    const anularTextoCodigo = document.getElementById('anular-texto-codigo');
    const inputAnularMotivo = document.getElementById('anular-motivo');
    const btnConfirmarAnulacion = document.getElementById('btn-confirmar-anulacion');
    const spinnerAnular = document.getElementById('spinner-anular');

    // Modal Detalle
    const modalDetalleEl = document.getElementById('modal-detalle-estadia');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;
    const detalleCodigo = document.getElementById('detalle-codigo');
    const detalleEstadoBadge = document.getElementById('detalle-estado-badge');
    const detalleUnidad = document.getElementById('detalle-unidad');
    const detallePropiedad = document.getElementById('detalle-propiedad');
    const detalleReserva = document.getElementById('detalle-reserva');
    const detalleTitular = document.getElementById('detalle-titular');
    const detalleLlave = document.getElementById('detalle-llave');
    const detalleFechaEntrada = document.getElementById('detalle-fecha-entrada');
    const detalleFechaSalida = document.getElementById('detalle-fecha-salida');
    const detalleCheckinEn = document.getElementById('detalle-checkin-en');
    const detalleCheckinActor = document.getElementById('detalle-checkin-actor');
    const filaDetalleCheckout = document.getElementById('fila-detalle-checkout');
    const detalleCheckoutEn = document.getElementById('detalle-checkout-en');
    const detalleCheckoutActor = document.getElementById('detalle-checkout-actor');
    const filaDetalleAnulada = document.getElementById('fila-detalle-anulada');
    const detalleAnuladaEn = document.getElementById('detalle-anulada-en');
    const detalleMotivoAnulacion = document.getElementById('detalle-motivo-anulacion');
    const detalleConteoHuespedes = document.getElementById('detalle-conteo-huespedes');
    const tbodyDetalleHuespedes = document.getElementById('tbody-detalle-huespedes');
    const detalleObsCheckin = document.getElementById('detalle-obs-checkin');
    const detalleObsCheckout = document.getElementById('detalle-obs-checkout');

    // Estado local
    let reservasConfirmadasCatalogo = [];
    let personasCatalogo = [];
    let unidadesElegiblesActuales = [];
    let unidadSeleccionadaActual = null;
    let capacidadMaximaActual = 0;
    let paginaActual = 1;
    let debounceTimer = null;

    // Inicializar PristineJS para formulario de Check-in
    let pristineCheckin = null;
    if (formCheckin) {
        pristineCheckin = new Pristine(formCheckin, {
            classTo: 'col-md-7, col-md-5, col-md-4, col-12',
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorTextParent: 'col-md-7, col-md-5, col-md-4, col-12',
            errorTextTag: 'div',
            errorTextClass: 'invalid-feedback f-s-11',
        });
    }

    /**
     * Utilidad para escapar texto HTML defensivamente.
     */
    function escaparHtml(cadena) {
        if (!cadena) return '';
        const mapa = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return String(cadena).replace(/[&<>"']/g, m => mapa[m]);
    }

    /**
     * Formatea fechas y marcas temporales para presentación amigable.
     */
    function formatearFecha(cadena) {
        if (!cadena) return '-';
        const partes = cadena.split(' ');
        const fecha = partes[0];
        const hora = partes[1] ? ` ${partes[1].substring(0, 5)}` : '';
        return `${fecha}${hora}`;
    }

    /**
     * Notificación estándar con SweetAlert2.
     */
    function mostrarAlerta(icono, titulo, texto) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icono,
                title: titulo,
                text: texto,
                confirmButtonColor: '#2563eb',
            });
        } else {
            alert(`${titulo}: ${texto}`);
        }
    }

    /**
     * Carga el catálogo auxiliar (reservas confirmadas y personas) para los selectores.
     */
    async function cargarAuxiliares() {
        try {
            const respuesta = await fetch(url_ruta('/estadias/auxiliares'), {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();
            if (datos.ok) {
                reservasConfirmadasCatalogo = datos.reservas_confirmadas || [];
                personasCatalogo = datos.personas || [];
                poblarSelectReservas();
            }
        } catch (error) {
            console.error('Error al cargar datos auxiliares de estadía:', error);
        }
    }

    /**
     * Llena el select de reservas confirmadas en el modal de check-in.
     */
    function poblarSelectReservas() {
        if (!selectReservaCheckin) return;
        selectReservaCheckin.innerHTML = '<option value="">Seleccione una reserva confirmada...</option>';

        reservasConfirmadasCatalogo.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = `${r.codigo} — ${r.titular_nombre} (${r.fecha_entrada} a ${r.fecha_salida}) [${r.total_unidades} unid.]`;
            selectReservaCheckin.appendChild(opt);
        });
    }

    /**
     * Carga las unidades elegibles de una reserva seleccionada.
     */
    async function cargarUnidadesElegibles(reservaId) {
        if (!reservaId || !selectUnidadCheckin) return;

        selectUnidadCheckin.disabled = true;
        selectUnidadCheckin.innerHTML = '<option value="">Cargando unidades disponibles...</option>';

        try {
            const respuesta = await fetch(url_ruta(`/estadias/elegibles/${reservaId}`), {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();

            if (datos.ok) {
                unidadesElegiblesActuales = datos.unidades || [];
                selectUnidadCheckin.innerHTML = '<option value="">Seleccione la unidad para check-in...</option>';

                if (unidadesElegiblesActuales.length === 0) {
                    selectUnidadCheckin.innerHTML = '<option value="">Todas las unidades ya tienen check-in</option>';
                    selectUnidadCheckin.disabled = true;
                    return;
                }

                unidadesElegiblesActuales.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.reserva_unidad_id;
                    opt.textContent = `${u.unidad_numero} - ${u.unidad_nombre} (${u.propiedad_nombre}) — Capacidad: ${u.capacidad_personas} pers.`;
                    opt.dataset.capacidad = u.capacidad_personas;
                    opt.dataset.unidadId = u.unidad_id;
                    selectUnidadCheckin.appendChild(opt);
                });

                selectUnidadCheckin.disabled = false;
            } else {
                selectUnidadCheckin.innerHTML = `<option value="">Error: ${datos.mensaje}</option>`;
            }
        } catch (error) {
            console.error('Error al cargar unidades elegibles:', error);
            selectUnidadCheckin.innerHTML = '<option value="">Error al cargar unidades</option>';
        }
    }

    /**
     * Añade una fila de huésped al formulario de check-in.
     */
    function agregarFilaHuesped(personaIdPreseleccionada = null, esResponsable = false) {
        if (!tbodyHuespedesCheckin) return;

        const conteoActual = tbodyHuespedesCheckin.querySelectorAll('tr').length;
        if (capacidadMaximaActual > 0 && conteoActual >= capacidadMaximaActual) {
            mostrarAlerta(
                'warning',
                'Capacidad Máxima Alcanzada',
                `La unidad seleccionada tiene una capacidad física máxima de ${capacidadMaximaActual} personas. No se pueden añadir más ocupantes.`
            );
            return;
        }

        const tr = document.createElement('tr');
        const filaIdx = Date.now() + Math.floor(Math.random() * 100);

        let optionsHtml = '<option value="">Seleccione o busque una persona...</option>';
        personasCatalogo.forEach(p => {
            const selected = (personaIdPreseleccionada && parseInt(personaIdPreseleccionada) === parseInt(p.id)) ? 'selected' : '';
            const docInfo = p.documento ? ` (${p.documento})` : '';
            optionsHtml += `<option value="${p.id}" ${selected}>${escaparHtml(p.nombre_completo)}${escaparHtml(docInfo)}</option>`;
        });

        const checkedAttr = (esResponsable || conteoActual === 0) ? 'checked' : '';

        tr.innerHTML = `
            <td class="ps-2">
                <select class="form-select form-select-sm select-persona-huesped" required>
                    ${optionsHtml}
                </select>
            </td>
            <td class="text-center">
                <div class="form-check d-flex justify-content-center mb-0">
                    <input class="form-check-input radio-responsable-huesped" type="radio" 
                           name="huesped_responsable_radio" id="resp_${filaIdx}" value="1" ${checkedAttr}>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-xs btn-eliminar-huesped" title="Quitar ocupante">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        tbodyHuespedesCheckin.appendChild(tr);
        actualizarConteoHuespedes();
    }

    /**
     * Actualiza el resumen de huéspedes y valida reglas preliminares.
     */
    function actualizarConteoHuespedes() {
        if (!tbodyHuespedesCheckin || !resumenConteoHuespedes) return;

        const filas = tbodyHuespedesCheckin.querySelectorAll('tr');
        const total = filas.length;
        let responsables = 0;

        filas.forEach(f => {
            const radio = f.querySelector('.radio-responsable-huesped');
            if (radio && radio.checked) responsables++;
        });

        resumenConteoHuespedes.innerHTML = `<strong>${total}</strong> de <strong>${capacidadMaximaActual || '-'}</strong> ocupante(s) | Responsables: <strong>${responsables}</strong>`;

        if (alertaErrorHuespedes) {
            if (total === 0) {
                alertaErrorHuespedes.textContent = 'Debe registrar al menos 1 huésped.';
                alertaErrorHuespedes.classList.remove('d-none');
            } else if (capacidadMaximaActual > 0 && total > capacidadMaximaActual) {
                alertaErrorHuespedes.textContent = `Excede la capacidad de la unidad (${capacidadMaximaActual} personas).`;
                alertaErrorHuespedes.classList.remove('d-none');
            } else if (responsables === 0) {
                alertaErrorHuespedes.textContent = 'Debe designar exactamente un huésped responsable.';
                alertaErrorHuespedes.classList.remove('d-none');
            } else {
                alertaErrorHuespedes.textContent = '';
                alertaErrorHuespedes.classList.add('d-none');
            }
        }
    }

    /**
     * Carga y renderiza el listado principal de estadías con paginación y filtros.
     */
    async function cargarEstadias(pagina = 1) {
        if (!tbodyEstadias) return;

        paginaActual = pagina;
        tbodyEstadias.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando estadías...
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        params.append('pagina', paginaActual.toString());
        params.append('limite', '20');

        if (filtroBusqueda?.value.trim()) params.append('termino', filtroBusqueda.value.trim());
        if (filtroEstado?.value) params.append('estado', filtroEstado.value);
        if (filtroPropiedad?.value) params.append('propiedad_id', filtroPropiedad.value);
        if (filtroFechaDesde?.value) params.append('fecha_entrada', filtroFechaDesde.value);
        if (filtroFechaHasta?.value) params.append('fecha_salida_prevista', filtroFechaHasta.value);

        try {
            const respuesta = await fetch(url_ruta(`/estadias/datos?${params.toString()}`), {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();

            if (!datos.ok) {
                tbodyEstadias.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-4 text-danger">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Error al cargar datos: ${escaparHtml(datos.mensaje)}
                        </td>
                    </tr>
                `;
                return;
            }

            renderizarTablaEstadias(datos.datos);
            renderizarPaginacion(datos.paginacion);
            actualizarKpisDesdeDatos(datos.datos, datos.paginacion.total);
        } catch (error) {
            console.error('Error al consultar estadías:', error);
            tbodyEstadias.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Error de conexión con el servidor.
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Renderiza las filas en la tabla de estadías.
     */
    function renderizarTablaEstadias(estadias) {
        if (!estadias || estadias.length === 0) {
            tbodyEstadias.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-inbox f-s-24 d-block mb-1 text-secondary"></i>
                        No se encontraron estadías registradas con los filtros seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        estadias.forEach(e => {
            const badgeEstado = window.CamargoInsignia
                ? window.CamargoInsignia.estado(e.estado)
                : `<span class="badge bg-light-secondary">${escaparHtml(e.estado)}</span>`;

            const llaveBadge = e.identificador_llave
                ? `<span class="badge bg-light-secondary text-dark"><i class="fa-solid fa-key me-1"></i>${escaparHtml(e.identificador_llave)}</span>`
                : '<span class="text-muted f-s-11">Sin llave</span>';

            const ocupantesBadge = `<span class="badge bg-light-primary"><i class="fa-solid fa-user me-1"></i>${e.cantidad_huespedes || 1}</span>`;

            // Botones de acción según estado
            let accionesHtml = `
                <button type="button" class="btn btn-outline-info btn-xs btn-ver-detalle" data-id="${e.id}" title="Ver detalles y ocupantes">
                    <i class="fa-solid fa-eye"></i>
                </button>
            `;

            if (e.esta_en_curso) {
                accionesHtml += `
                    <button type="button" class="btn btn-outline-secondary btn-xs btn-abrir-checkout ms-1" data-id="${e.id}" title="Registrar Check-out">
                        <i class="fa-solid fa-flag-checkered"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-xs btn-abrir-anular ms-1" data-id="${e.id}" title="Anular estadía">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                `;
            }

            html += `
                <tr>
                    <td class="ps-3">
                        <a href="javascript:void(0)" class="f-w-700 text-decoration-none btn-ver-detalle" data-id="${e.id}">
                            ${escaparHtml(e.codigo)}
                        </a>
                    </td>
                    <td>
                        <strong class="text-primary">${escaparHtml(e.unidad_numero || '')}</strong> 
                        <span class="text-muted f-s-12">— ${escaparHtml(e.unidad_nombre || '')}</span>
                        <div class="f-s-11 text-secondary">${escaparHtml(e.propiedad_nombre || '')}</div>
                    </td>
                    <td>
                        <span class="badge bg-light-info text-dark font-monospace">${escaparHtml(e.reserva_codigo || '')}</span>
                    </td>
                    <td>
                        <div class="f-w-600 f-s-13">${escaparHtml(e.responsable_nombre || e.titular_nombre_completo || 'No asignado')}</div>
                        <span class="badge bg-light-success f-s-10"><i class="fa-solid fa-star me-1"></i>Responsable</span>
                    </td>
                    <td>${ocupantesBadge}</td>
                    <td>
                        <span class="f-s-12">${formatearFecha(e.fecha_entrada)}</span>
                        <div class="f-s-10 text-muted">${formatearFecha(e.checkin_en)} (UTC)</div>
                    </td>
                    <td>
                        <span class="f-s-12">${formatearFecha(e.fecha_salida_prevista)}</span>
                        ${e.checkout_en ? `<div class="f-s-10 text-success">${formatearFecha(e.checkout_en)} (UTC)</div>` : ''}
                    </td>
                    <td>${llaveBadge}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-center pe-3 text-nowrap">${accionesHtml}</td>
                </tr>
            `;
        });

        tbodyEstadias.innerHTML = html;
    }

    /**
     * Renderiza los controles de paginación.
     */
    function renderizarPaginacion(pag) {
        if (!paginacionResumen || !paginacionControl) return;

        paginacionResumen.textContent = `Mostrando página ${pag.pagina_actual} de ${pag.total_paginas || 1} (${pag.total} estadías en total)`;

        if (pag.total_paginas <= 1) {
            paginacionControl.innerHTML = '';
            return;
        }

        let html = '';
        const prevDisabled = pag.pagina_actual <= 1 ? 'disabled' : '';
        html += `<li class="page-item ${prevDisabled}"><a class="page-link" href="javascript:void(0)" data-pag="${pag.pagina_actual - 1}"><i class="fa-solid fa-chevron-left"></i></a></li>`;

        for (let i = 1; i <= pag.total_paginas; i++) {
            if (i === 1 || i === pag.total_paginas || (i >= pag.pagina_actual - 1 && i <= pag.pagina_actual + 1)) {
                const active = i === pag.pagina_actual ? 'active' : '';
                html += `<li class="page-item ${active}"><a class="page-link" href="javascript:void(0)" data-pag="${i}">${i}</a></li>`;
            } else if (i === pag.pagina_actual - 2 || i === pag.pagina_actual + 2) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        const nextDisabled = pag.pagina_actual >= pag.total_paginas ? 'disabled' : '';
        html += `<li class="page-item ${nextDisabled}"><a class="page-link" href="javascript:void(0)" data-pag="${pag.pagina_actual + 1}"><i class="fa-solid fa-chevron-right"></i></a></li>`;

        paginacionControl.innerHTML = html;
    }

    /**
     * Actualiza los KPIs informativos superiores.
     */
    function actualizarKpisDesdeDatos(items, total) {
        if (!items) return;
        let activas = 0;
        let finalizadas = 0;
        let anuladas = 0;

        items.forEach(e => {
            if (e.estado === 'EN_CURSO') activas++;
            else if (e.estado === 'FINALIZADA') finalizadas++;
            else if (e.estado === 'ANULADA') anuladas++;
        });

        if (kpiActivas) kpiActivas.textContent = activas.toString();
        if (kpiFinalizadas) kpiFinalizadas.textContent = finalizadas.toString();
        if (kpiAnuladas) kpiAnuladas.textContent = anuladas.toString();
    }

    /**
     * Abre y prepara el modal de Check-in físico.
     */
    async function abrirModalCheckin() {
        if (!formCheckin || !modalCheckin) return;

        formCheckin.reset();
        if (pristineCheckin) pristineCheckin.reset();

        selectUnidadCheckin.innerHTML = '<option value="">Primero seleccione una reserva...</option>';
        selectUnidadCheckin.disabled = true;
        tbodyHuespedesCheckin.innerHTML = '';
        capacidadMaximaActual = 0;
        badgeCapacidadUnidad.textContent = 'Capacidad: - personas';
        ayudaCapacidadUnidad.textContent = 'Capacidad máxima determinada por la unidad física.';

        await cargarAuxiliares();
        modalCheckin.show();
    }

    /**
     * Evento al seleccionar una reserva en el modal de Check-in.
     */
    if (selectReservaCheckin) {
        selectReservaCheckin.addEventListener('change', async function () {
            const resId = parseInt(this.value);
            if (!resId) {
                selectUnidadCheckin.innerHTML = '<option value="">Primero seleccione una reserva...</option>';
                selectUnidadCheckin.disabled = true;
                return;
            }

            const resData = reservasConfirmadasCatalogo.find(r => parseInt(r.id) === resId);
            if (resData) {
                if (inputFechaEntradaCheckin) inputFechaEntradaCheckin.value = resData.fecha_entrada;
                if (inputFechaSalidaCheckin) inputFechaSalidaCheckin.value = resData.fecha_salida;
            }

            await cargarUnidadesElegibles(resId);
        });
    }

    /**
     * Evento al seleccionar una unidad en el modal de Check-in.
     */
    if (selectUnidadCheckin) {
        selectUnidadCheckin.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) {
                capacidadMaximaActual = 0;
                badgeCapacidadUnidad.textContent = 'Capacidad: - personas';
                return;
            }

            capacidadMaximaActual = parseInt(selectedOpt.dataset.capacidad || '0');
            badgeCapacidadUnidad.textContent = `Capacidad: ${capacidadMaximaActual} personas`;
            ayudaCapacidadUnidad.textContent = `Capacidad máxima: ${capacidadMaximaActual} personas permitidas físicamente.`;

            // Si no hay filas de huéspedes, inicializar con 1 ocupante
            if (tbodyHuespedesCheckin.querySelectorAll('tr').length === 0) {
                agregarFilaHuesped(null, true);
            } else {
                actualizarConteoHuespedes();
            }
        });
    }

    /**
     * Añadir ocupante con el botón "+ Añadir Ocupante".
     */
    if (btnAgregarHuesped) {
        btnAgregarHuesped.addEventListener('click', () => {
            agregarFilaHuesped(null, false);
        });
    }

    /**
     * Delegación de eventos en tabla de huéspedes de check-in (eliminar ocupante).
     */
    if (tbodyHuespedesCheckin) {
        tbodyHuespedesCheckin.addEventListener('click', e => {
            const btnEliminar = e.target.closest('.btn-eliminar-huesped');
            if (btnEliminar) {
                const tr = btnEliminar.closest('tr');
                if (tr) {
                    const eraResponsable = tr.querySelector('.radio-responsable-huesped')?.checked;
                    tr.remove();

                    // Si se eliminó al responsable y aún quedan ocupantes, marcar al primero como responsable
                    if (eraResponsable) {
                        const primeraFilaRadio = tbodyHuespedesCheckin.querySelector('.radio-responsable-huesped');
                        if (primeraFilaRadio) primeraFilaRadio.checked = true;
                    }
                    actualizarConteoHuespedes();
                }
            }
        });

        tbodyHuespedesCheckin.addEventListener('change', e => {
            if (e.target.classList.contains('radio-responsable-huesped') || e.target.classList.contains('select-persona-huesped')) {
                actualizarConteoHuespedes();
            }
        });
    }

    /**
     * Envío del formulario de Check-in (POST /estadias/checkin).
     */
    if (formCheckin) {
        formCheckin.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (pristineCheckin && !pristineCheckin.validate()) {
                return;
            }

            const reservaId = parseInt(selectReservaCheckin.value);
            const reservaUnidadId = parseInt(selectUnidadCheckin.value);
            const optUnidad = selectUnidadCheckin.options[selectUnidadCheckin.selectedIndex];
            const unidadId = optUnidad ? parseInt(optUnidad.dataset.unidadId) : 0;

            if (!reservaId || !reservaUnidadId) {
                mostrarAlerta('warning', 'Campos Incompletos', 'Debe seleccionar una reserva confirmada y la unidad física correspondiente.');
                return;
            }

            // Recolectar lista de huéspedes
            const filasHuespedes = tbodyHuespedesCheckin.querySelectorAll('tr');
            if (filasHuespedes.length === 0) {
                mostrarAlerta('warning', 'Huéspedes Requeridos', 'Debe registrar al menos un huésped para completar el check-in.');
                return;
            }

            if (capacidadMaximaActual > 0 && filasHuespedes.length > capacidadMaximaActual) {
                mostrarAlerta('error', 'Capacidad Excedida', `La cantidad de huéspedes (${filasHuespedes.length}) excede la capacidad máxima de la unidad (${capacidadMaximaActual} personas).`);
                return;
            }

            const huespedes = [];
            const personasIds = new Set();
            let tieneResponsable = false;

            for (const f of filasHuespedes) {
                const selectPersona = f.querySelector('.select-persona-huesped');
                const radioResp = f.querySelector('.radio-responsable-huesped');
                const pId = parseInt(selectPersona?.value || '0');
                const esResp = radioResp?.checked || false;

                if (!pId) {
                    mostrarAlerta('warning', 'Huésped Incompleto', 'Debe seleccionar una persona válida para cada fila de ocupante.');
                    return;
                }

                if (personasIds.has(pId)) {
                    mostrarAlerta('error', 'Huésped Duplicado', 'No se puede registrar a la misma persona más de una vez en la misma estadía.');
                    return;
                }

                personasIds.add(pId);
                if (esResp) tieneResponsable = true;

                huespedes.push({
                    persona_id: pId,
                    es_responsable: esResp,
                });
            }

            if (!tieneResponsable) {
                mostrarAlerta('warning', 'Responsable Requerido', 'Debe designar exactamente un huésped como responsable de la estadía.');
                return;
            }

            const payload = {
                reserva_id: reservaId,
                reserva_unidad_id: reservaUnidadId,
                unidad_id: unidadId,
                fecha_entrada: inputFechaEntradaCheckin.value,
                fecha_salida_prevista: inputFechaSalidaCheckin.value,
                identificador_llave: inputLlaveCheckin.value.trim() || null,
                observaciones_checkin: inputObsCheckin.value.trim() || null,
                huespedes: huespedes,
                _csrf_token: csrfToken,
            };

            btnGuardarCheckin.disabled = true;
            spinnerCheckin.classList.remove('d-none');

            try {
                const respuesta = await fetch(url_ruta('/estadias/checkin'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const resultado = await respuesta.json();

                if (respuesta.ok && resultado.ok) {
                    modalCheckin.hide();
                    mostrarAlerta('success', '¡Check-in Exitoso!', resultado.mensaje);
                    cargarEstadias(1);
                } else {
                    mostrarAlerta('error', 'No se pudo realizar el check-in', resultado.mensaje || 'Error en validaciones de negocio.');
                }
            } catch (error) {
                console.error('Error en check-in:', error);
                mostrarAlerta('error', 'Error del Sistema', 'Ocurrió un error inesperado al procesar la solicitud.');
            } finally {
                btnGuardarCheckin.disabled = false;
                spinnerCheckin.classList.add('d-none');
            }
        });
    }

    /**
     * Abre y prepara el modal de Check-out.
     */
    async function abrirModalCheckout(estadiaId) {
        if (!modalCheckout || !formCheckout) return;
        formCheckout.reset();

        try {
            const respuesta = await fetch(url_ruta(`/estadias/${estadiaId}`), {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();

            if (datos.ok) {
                const e = datos.datos;
                inputCheckoutId.value = e.id;
                checkoutTextoCodigo.textContent = e.codigo;
                checkoutTextoUnidad.textContent = `${e.unidad_numero} - ${e.unidad_nombre} (${e.propiedad_nombre})`;
                checkoutTextoResponsable.textContent = e.responsable_nombre || e.titular_nombre_completo || '-';
                checkoutTextoLlave.textContent = e.identificador_llave || 'Sin llave';
                modalCheckout.show();
            } else {
                mostrarAlerta('error', 'Error', datos.mensaje);
            }
        } catch (error) {
            console.error('Error al cargar estadía para checkout:', error);
            mostrarAlerta('error', 'Error', 'No se pudo cargar la información de la estadía.');
        }
    }

    /**
     * Envío del formulario de Check-out (POST /estadias/{id}/checkout).
     */
    if (formCheckout) {
        formCheckout.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = inputCheckoutId.value;
            if (!id) return;

            btnConfirmarCheckout.disabled = true;
            spinnerCheckout.classList.remove('d-none');

            try {
                const respuesta = await fetch(url_ruta(`/estadias/${id}/checkout`), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        observaciones_checkout: inputObsCheckout.value.trim() || null,
                        _csrf_token: csrfToken,
                    }),
                });

                const resultado = await respuesta.json();

                if (respuesta.ok && resultado.ok) {
                    modalCheckout.hide();
                    mostrarAlerta('success', 'Check-out Completado', resultado.mensaje);
                    cargarEstadias(paginaActual);
                } else {
                    mostrarAlerta('error', 'Error en Check-out', resultado.mensaje);
                }
            } catch (error) {
                console.error('Error al procesar check-out:', error);
                mostrarAlerta('error', 'Error del Sistema', 'No se pudo registrar la salida.');
            } finally {
                btnConfirmarCheckout.disabled = false;
                spinnerCheckout.classList.add('d-none');
            }
        });
    }

    /**
     * Abre y prepara el modal de Anulación.
     */
    async function abrirModalAnular(estadiaId) {
        if (!modalAnular || !formAnular) return;
        formAnular.reset();

        try {
            const respuesta = await fetch(url_ruta(`/estadias/${estadiaId}`), {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();

            if (datos.ok) {
                inputAnularId.value = datos.datos.id;
                anularTextoCodigo.textContent = datos.datos.codigo;
                modalAnular.show();
            } else {
                mostrarAlerta('error', 'Error', datos.mensaje);
            }
        } catch (error) {
            console.error('Error al abrir anulación:', error);
            mostrarAlerta('error', 'Error', 'No se pudo cargar la información de la estadía.');
        }
    }

    /**
     * Envío del formulario de Anulación (POST /estadias/{id}/anular).
     */
    if (formAnular) {
        formAnular.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = inputAnularId.value;
            const motivo = inputAnularMotivo.value.trim();

            if (!id || !motivo) {
                mostrarAlerta('warning', 'Motivo Requerido', 'El motivo de anulación es obligatorio.');
                return;
            }

            btnConfirmarAnulacion.disabled = true;
            spinnerAnular.classList.remove('d-none');

            try {
                const respuesta = await fetch(url_ruta(`/estadias/${id}/anular`), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        motivo_anulacion: motivo,
                        _csrf_token: csrfToken,
                    }),
                });

                const resultado = await respuesta.json();

                if (respuesta.ok && resultado.ok) {
                    modalAnular.hide();
                    mostrarAlerta('success', 'Estadía Anulada', resultado.mensaje);
                    cargarEstadias(paginaActual);
                } else {
                    mostrarAlerta('error', 'No se pudo anular', resultado.mensaje);
                }
            } catch (error) {
                console.error('Error al anular estadía:', error);
                mostrarAlerta('error', 'Error del Sistema', 'No se pudo anular la estadía.');
            } finally {
                btnConfirmarAnulacion.disabled = false;
                spinnerAnular.classList.add('d-none');
            }
        });
    }

    /**
     * Muestra la ficha detallada de una estadía en el modal de detalle.
     */
    async function verDetalleEstadia(estadiaId) {
        if (!modalDetalle) return;

        try {
            const respuesta = await fetch(url_ruta(`/estadias/${estadiaId}`), {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();

            if (!datos.ok) {
                mostrarAlerta('error', 'Error', datos.mensaje);
                return;
            }

            const e = datos.datos;
            detalleCodigo.textContent = e.codigo;
            detalleEstadoBadge.innerHTML = window.CamargoInsignia
                ? window.CamargoInsignia.estado(e.estado)
                : `<span class="badge bg-light-secondary">${escaparHtml(e.estado)}</span>`;

            detalleUnidad.textContent = `${e.unidad_numero} - ${e.unidad_nombre}`;
            detallePropiedad.textContent = e.propiedad_nombre || '-';
            detalleReserva.textContent = e.reserva_codigo || '-';
            detalleTitular.textContent = e.titular_nombre_completo || '-';
            detalleLlave.textContent = e.identificador_llave || 'Sin asignar';

            detalleFechaEntrada.textContent = formatearFecha(e.fecha_entrada);
            detalleFechaSalida.textContent = formatearFecha(e.fecha_salida_prevista);
            detalleCheckinEn.textContent = formatearFecha(e.checkin_en);
            detalleCheckinActor.textContent = e.checkin_por_actor_nombre ? `por ${e.checkin_por_actor_nombre}` : '';

            if (e.checkout_en) {
                filaDetalleCheckout.classList.remove('d-none');
                detalleCheckoutEn.textContent = formatearFecha(e.checkout_en);
                detalleCheckoutActor.textContent = e.checkout_por_actor_nombre ? `por ${e.checkout_por_actor_nombre}` : '';
            } else {
                filaDetalleCheckout.classList.add('d-none');
            }

            if (e.anulada_en) {
                filaDetalleAnulada.classList.remove('d-none');
                detalleAnuladaEn.textContent = formatearFecha(e.anulada_en);
                detalleMotivoAnulacion.textContent = e.motivo_anulacion || 'Sin motivo especificado';
            } else {
                filaDetalleAnulada.classList.add('d-none');
            }

            detalleObsCheckin.textContent = e.observaciones_checkin || 'Ninguna';
            detalleObsCheckout.textContent = e.observaciones_checkout || 'Ninguna';

            // Huéspedes
            detalleConteoHuespedes.textContent = `${e.huespedes?.length || 0} personas`;
            let huespedesHtml = '';

            if (e.huespedes && e.huespedes.length > 0) {
                e.huespedes.forEach(h => {
                    const rolBadge = h.es_responsable
                        ? '<span class="badge bg-light-success"><i class="fa-solid fa-star me-1"></i>Responsable</span>'
                        : '<span class="badge bg-light-secondary">Acompañante</span>';

                    const docTexto = h.tipo_documento && h.numero_documento
                        ? `${escaparHtml(h.tipo_documento)}: ${escaparHtml(h.numero_documento)}`
                        : (h.numero_documento ? escaparHtml(h.numero_documento) : '-');

                    const contactoTexto = h.telefono || h.email || '-';

                    huespedesHtml += `
                        <tr>
                            <td class="ps-2 f-w-600">${escaparHtml(h.nombre_completo || '-')}</td>
                            <td>${docTexto}</td>
                            <td><span class="f-s-12 text-secondary">${escaparHtml(contactoTexto)}</span></td>
                            <td class="text-center">${rolBadge}</td>
                        </tr>
                    `;
                });
            } else {
                huespedesHtml = '<tr><td colspan="4" class="text-center py-2 text-muted">No hay ocupantes detallados.</td></tr>';
            }

            tbodyDetalleHuespedes.innerHTML = huespedesHtml;
            modalDetalle.show();
        } catch (error) {
            console.error('Error al ver detalle de estadía:', error);
            mostrarAlerta('error', 'Error', 'No se pudo cargar el detalle de la estadía.');
        }
    }

    // Delegación de eventos en tabla de estadías (Ver, Checkout, Anular)
    if (tbodyEstadias) {
        tbodyEstadias.addEventListener('click', e => {
            const btnVer = e.target.closest('.btn-ver-detalle');
            if (btnVer) {
                const id = btnVer.dataset.id;
                if (id) verDetalleEstadia(id);
                return;
            }

            const btnCheckout = e.target.closest('.btn-abrir-checkout');
            if (btnCheckout) {
                const id = btnCheckout.dataset.id;
                if (id) abrirModalCheckout(id);
                return;
            }

            const btnAnular = e.target.closest('.btn-abrir-anular');
            if (btnAnular) {
                const id = btnAnular.dataset.id;
                if (id) abrirModalAnular(id);
                return;
            }
        });
    }

    // Delegación en paginación
    if (paginacionControl) {
        paginacionControl.addEventListener('click', e => {
            const a = e.target.closest('a.page-link');
            if (a && a.dataset.pag) {
                const pag = parseInt(a.dataset.pag);
                if (pag && pag !== paginaActual) {
                    cargarEstadias(pag);
                }
            }
        });
    }

    // Filtros de búsqueda con debounce
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                cargarEstadias(1);
            }, 350);
        });
    }

    if (btnLimpiarBusqueda) {
        btnLimpiarBusqueda.addEventListener('click', () => {
            if (filtroBusqueda) {
                filtroBusqueda.value = '';
                cargarEstadias(1);
            }
        });
    }

    if (filtroEstado) {
        filtroEstado.addEventListener('change', () => cargarEstadias(1));
    }

    if (filtroPropiedad) {
        filtroPropiedad.addEventListener('change', () => cargarEstadias(1));
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => cargarEstadias(paginaActual));
    }

    if (btnAbrirCheckin) {
        btnAbrirCheckin.addEventListener('click', abrirModalCheckin);
    }

    const btnLimpiarFechas = document.getElementById('btn-limpiar-filtro-fechas');
    if (btnLimpiarFechas) {
        btnLimpiarFechas.addEventListener('click', () => {
            const inputRango = document.getElementById('filtro-rango-fechas');
            if (inputRango && inputRango._flatpickr) {
                inputRango._flatpickr.clear();
            }
            if (filtroFechaDesde) filtroFechaDesde.value = '';
            if (filtroFechaHasta) filtroFechaHasta.value = '';
            cargarEstadias(1);
        });
    }

    // Inicialización del catálogo al cargar la página
    cargarEstadias(1);
});
