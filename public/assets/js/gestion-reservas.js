/**
 * Camargo PMS — Módulo de Gestión Comercial de Reservas Directas (RESERVAS-1)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO.
 * - Intervalo semiabierto [entrada, salida) (D-066).
 * - Multiunidad: 1 Reserva : N Unidades asignadas (D-067 / D-069).
 * - Manejo de conflicto HTTP 409 y estados PENDIENTE, CONFIRMADA, CANCELADA, EXPIRADA.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Elementos del catálogo y filtros
    const tablaReservas = document.getElementById('tabla-reservas');
    const tbodyReservas = document.getElementById('tbody-reservas');
    const filtroBusqueda = document.getElementById('filtro-busqueda-reserva');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda');
    const filtroEstado = document.getElementById('filtro-estado-reserva');
    const filtroPropiedad = document.getElementById('filtro-propiedad-reserva');
    const filtroFechaDesde = document.getElementById('filtro-fecha-desde');
    const filtroFechaHasta = document.getElementById('filtro-fecha-hasta');
    const btnRecargar = document.getElementById('btn-recargar-reservas');
    const paginacionResumen = document.getElementById('paginacion-resumen-reservas');
    const paginacionControl = document.getElementById('paginacion-control-reservas');

    // Botones de acción general
    const btnAbrirCrear = document.getElementById('btn-abrir-crear-reserva');
    const btnExpirarHolds = document.getElementById('btn-expirar-holds');

    // Modal Crear Reserva
    const modalCrearEl = document.getElementById('modal-crear-reserva');
    const modalCrear = modalCrearEl ? new bootstrap.Modal(modalCrearEl) : null;
    const formCrear = document.getElementById('form-crear-reserva');
    const selectTitular = document.getElementById('crear-titular-id');
    const inputFechaEntrada = document.getElementById('crear-fecha-entrada');
    const inputFechaSalida = document.getElementById('crear-fecha-salida');
    const badgeNochesCalculadas = document.getElementById('crear-noches-calculadas');
    const contenedorUnidades = document.getElementById('contenedor-unidades-reserva');
    const btnAgregarUnidad = document.getElementById('btn-agregar-fila-unidad');
    const selectEstadoCrear = document.getElementById('crear-estado');
    const selectCanalCrear = document.getElementById('crear-canal');
    const inputObservacionesCrear = document.getElementById('crear-observaciones');
    const btnGuardarReserva = document.getElementById('btn-guardar-reserva');
    const spinnerGuardar = document.getElementById('spinner-guardar-reserva');

    // Resumen financiero modal crear
    const resumenSubtotal = document.getElementById('resumen-subtotal');
    const resumenImpuestos = document.getElementById('resumen-impuestos');
    const resumenTotal = document.getElementById('resumen-total');

    // Modal Detalle Reserva
    const modalDetalleEl = document.getElementById('modal-detalle-reserva');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;
    const detalleCodigo = document.getElementById('detalle-codigo');
    const detalleEstadoBadge = document.getElementById('detalle-estado-badge');
    const detalleTitularNombre = document.getElementById('detalle-titular-nombre');
    const detalleTitularDocumento = document.getElementById('detalle-titular-documento');
    const detalleTitularContacto = document.getElementById('detalle-titular-contacto');
    const detalleFechaEntrada = document.getElementById('detalle-fecha-entrada');
    const detalleFechaSalida = document.getElementById('detalle-fecha-salida');
    const detalleNoches = document.getElementById('detalle-noches');
    const detalleCanal = document.getElementById('detalle-canal');
    const detalleAvisoHold = document.getElementById('detalle-aviso-hold');
    const detalleTbodyUnidades = document.getElementById('detalle-tbody-unidades');
    const detalleTotalSnapshot = document.getElementById('detalle-total-snapshot');
    const detalleObservaciones = document.getElementById('detalle-observaciones');
    const detalleTrazabilidad = document.getElementById('detalle-trazabilidad');
    const btnDetalleCancelar = document.getElementById('btn-detalle-cancelar');
    const btnDetalleConfirmar = document.getElementById('btn-detalle-confirmar');

    // Modal Cancelar Reserva
    const modalCancelarEl = document.getElementById('modal-cancelar-reserva');
    const modalCancelar = modalCancelarEl ? new bootstrap.Modal(modalCancelarEl) : null;
    const formCancelar = document.getElementById('form-cancelar-reserva');
    const inputCancelarId = document.getElementById('cancelar-reserva-id');
    const cancelarCodigoTexto = document.getElementById('cancelar-reserva-codigo');
    const inputCancelarMotivo = document.getElementById('cancelar-motivo');
    const btnConfirmarCancelacion = document.getElementById('btn-confirmar-cancelacion');
    const spinnerCancelar = document.getElementById('spinner-cancelar-reserva');

    // Estado local
    let catalogoUnidades = [];
    let catalogoPersonas = [];
    let paginaActual = 1;
    let reservaActualDetalle = null;

    // Inicializar PristineJS
    let pristineCrear = null;
    if (formCrear) {
        pristineCrear = new Pristine(formCrear, {
            classTo: 'col-md-5, col-md-6, col-md-12',
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorTextParent: 'col-md-5, col-md-6, col-md-12',
            errorTextTag: 'div',
            errorTextClass: 'invalid-feedback f-s-11',
        });
    }

    // -------------------------------------------------------------------------
    // 2. Carga Inicial de Datos y Auxiliares
    // -------------------------------------------------------------------------
    async function inicializar() {
        await cargarAuxiliares();
        await cargarReservas(1);
    }

    async function cargarAuxiliares() {
        try {
            const resp = await fetch('/reservas/auxiliares');
            const json = await resp.json();
            if (json.ok) {
                catalogoUnidades = json.unidades || [];
                catalogoPersonas = json.personas || [];
                poblarSelectPersonas();
            }
        } catch (e) {
            console.error('Error cargando catálogos auxiliares:', e);
        }
    }

    function poblarSelectPersonas() {
        if (!selectTitular) return;
        selectTitular.innerHTML = '<option value="">Seleccione o busque una persona registrada...</option>';
        catalogoPersonas.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            const docInfo = p.documento ? ` (${p.documento})` : '';
            opt.textContent = `${p.nombre_completo}${docInfo}`;
            selectTitular.appendChild(opt);
        });
    }

    // -------------------------------------------------------------------------
    // 3. Catálogo Principal de Reservas
    // -------------------------------------------------------------------------
    async function cargarReservas(pagina = 1) {
        paginaActual = pagina;
        tbodyReservas.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando reservas...
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        params.append('pagina', pagina);
        params.append('limite', '15');

        if (filtroBusqueda?.value.trim()) params.append('busqueda', filtroBusqueda.value.trim());
        if (filtroEstado?.value) params.append('estado', filtroEstado.value);
        if (filtroPropiedad?.value) params.append('propiedad_id', filtroPropiedad.value);
        if (filtroFechaDesde?.value) params.append('fecha_desde', filtroFechaDesde.value);
        if (filtroFechaHasta?.value) params.append('fecha_hasta', filtroFechaHasta.value);

        try {
            const resp = await fetch(`/reservas/datos?${params.toString()}`);
            const json = await resp.json();

            if (!json.ok) {
                tbodyReservas.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-danger">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> ${json.mensaje || 'Error al cargar reservas'}
                        </td>
                    </tr>
                `;
                return;
            }

            renderizarTablaReservas(json.datos, json.paginacion);
        } catch (e) {
            tbodyReservas.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-wifi me-1"></i> Error de conexión con el servidor.
                    </td>
                </tr>
            `;
        }
    }

    function renderizarTablaReservas(reservas, paginacion) {
        if (!reservas || reservas.length === 0) {
            tbodyReservas.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-calendar-xmark f-s-20 d-block mb-1"></i> No se encontraron reservas registradas con los filtros aplicados.
                    </td>
                </tr>
            `;
            actualizarPaginacion(paginacion);
            return;
        }

        let html = '';
        reservas.forEach(r => {
            const estadoBadge = obtenerBadgeEstado(r.estado);
            const totalFmt = parseFloat(r.total).toFixed(2);

            let unidadesHtml = '';
            if (r.unidades && r.unidades.length > 0) {
                unidadesHtml = r.unidades.map(u => `
                    <span class="chip bg-light-primary me-1 mb-1" title="${u.propiedad_nombre || ''}">
                        <i class="fa-solid fa-door-open f-s-11 me-1"></i>${u.unidad_codigo}
                    </span>
                `).join('');
            } else {
                unidadesHtml = `<span class="text-muted f-s-11">0 unidades</span>`;
            }

            html += `
                <tr>
                    <td class="ps-3 f-w-700">
                        <a href="javascript:void(0)" class="btn-ver-detalle text-primary text-decoration-none" data-id="${r.id}">
                            ${r.codigo}
                        </a>
                    </td>
                    <td>
                        <div class="f-s-13 f-w-600">${r.titular_nombre_completo || 'Titular ID ' + r.persona_titular_id}</div>
                        ${r.titular_documento_numero ? `<div class="f-s-11 text-muted">${r.titular_documento_tipo || 'DOC'}: ${r.titular_documento_numero}</div>` : ''}
                    </td>
                    <td class="f-s-13">
                        <i class="fa-solid fa-calendar-days me-1 text-secondary"></i>${r.fecha_entrada} &rarr; ${r.fecha_salida}
                    </td>
                    <td class="f-s-13 text-center">
                        <span class="badge bg-light-secondary">${r.noches} n</span>
                    </td>
                    <td>${unidadesHtml}</td>
                    <td class="text-end f-w-700 f-s-13 text-dark">
                        S/ ${totalFmt}
                    </td>
                    <td class="text-center">${estadoBadge}</td>
                    <td class="text-center pe-3">
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm p-1 dropdown-toggle no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical f-s-16"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 f-s-13">
                                <li>
                                    <a class="dropdown-item btn-ver-detalle" href="javascript:void(0)" data-id="${r.id}">
                                        <i class="fa-solid fa-eye me-2 text-primary"></i> Ver Detalle
                                    </a>
                                </li>
                                ${r.estado === 'PENDIENTE' ? `
                                <li>
                                    <a class="dropdown-item btn-confirmar-directo text-success" href="javascript:void(0)" data-id="${r.id}" data-codigo="${r.codigo}">
                                        <i class="fa-solid fa-check me-2"></i> Confirmar Reserva
                                    </a>
                                </li>
                                ` : ''}
                                ${(r.estado === 'PENDIENTE' || r.estado === 'CONFIRMADA') ? `
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item btn-abrir-cancelar text-danger" href="javascript:void(0)" data-id="${r.id}" data-codigo="${r.codigo}">
                                        <i class="fa-solid fa-xmark me-2"></i> Cancelar Reserva
                                    </a>
                                </li>
                                ` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbodyReservas.innerHTML = html;
        actualizarPaginacion(paginacion);
        vincularEventosTabla();
    }

    function obtenerBadgeEstado(estado) {
        if (window.CamargoInsignia && typeof window.CamargoInsignia.estado === 'function') {
            return window.CamargoInsignia.estado(estado, false, 'f-s-11');
        }
        switch (estado) {
            case 'PENDIENTE':
                return '<span class="badge bg-light-warning f-s-11"><i class="fa-solid fa-clock me-1"></i>PENDIENTE</span>';
            case 'CONFIRMADA':
                return '<span class="badge bg-light-success f-s-11"><i class="fa-solid fa-check me-1"></i>CONFIRMADA</span>';
            case 'CANCELADA':
                return '<span class="badge bg-light-danger f-s-11"><i class="fa-solid fa-xmark me-1"></i>CANCELADA</span>';
            case 'EXPIRADA':
                return '<span class="badge bg-light-secondary f-s-11"><i class="fa-solid fa-hourglass-half me-1"></i>EXPIRADA</span>';
            default:
                return `<span class="badge bg-light text-dark f-s-11">${estado}</span>`;
        }
    }

    function actualizarPaginacion(pag) {
        if (!pag) return;
        const total = pag.total || 0;
        const pagActual = pag.pagina_actual || 1;
        const totalPags = pag.total_paginas || 1;
        const limite = pag.limite || 15;

        const desde = total === 0 ? 0 : (pagActual - 1) * limite + 1;
        const hasta = Math.min(pagActual * limite, total);

        if (paginacionResumen) {
            paginacionResumen.textContent = `Mostrando ${desde} a ${hasta} de ${total} reservas`;
        }

        if (!paginacionControl) return;
        let htmlPags = '';

        // Botón anterior
        htmlPags += `
            <li class="page-item ${pagActual <= 1 ? 'disabled' : ''}">
                <a class="page-link btn-pag" href="javascript:void(0)" data-pagina="${pagActual - 1}">&laquo;</a>
            </li>
        `;

        for (let i = 1; i <= totalPags; i++) {
            if (i === 1 || i === totalPags || (i >= pagActual - 2 && i <= pagActual + 2)) {
                htmlPags += `
                    <li class="page-item ${i === pagActual ? 'active' : ''}">
                        <a class="page-link btn-pag" href="javascript:void(0)" data-pagina="${i}">${i}</a>
                    </li>
                `;
            } else if (i === pagActual - 3 || i === pagActual + 3) {
                htmlPags += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Botón siguiente
        htmlPags += `
            <li class="page-item ${pagActual >= totalPags ? 'disabled' : ''}">
                <a class="page-link btn-pag" href="javascript:void(0)" data-pagina="${pagActual + 1}">&raquo;</a>
            </li>
        `;

        paginacionControl.innerHTML = htmlPags;

        paginacionControl.querySelectorAll('.btn-pag').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const p = parseInt(btn.dataset.pagina, 10);
                if (p && p !== pagActual && p >= 1 && p <= totalPags) {
                    cargarReservas(p);
                }
            });
        });
    }

    function vincularEventosTabla() {
        document.querySelectorAll('.btn-ver-detalle').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                if (id) verDetalleReserva(id);
            });
        });

        document.querySelectorAll('.btn-confirmar-directo').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const codigo = btn.dataset.codigo;
                if (id) confirmarReserva(id, codigo);
            });
        });

        document.querySelectorAll('.btn-abrir-cancelar').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const codigo = btn.dataset.codigo;
                if (id) abrirModalCancelar(id, codigo);
            });
        });
    }

    // -------------------------------------------------------------------------
    // 4. Modal Crear Reserva y Gestión Multiunidad
    // -------------------------------------------------------------------------
    if (btnAbrirCrear) {
        btnAbrirCrear.addEventListener('click', () => {
            resetearFormularioCrear();
            modalCrear?.show();
        });
    }

    function resetearFormularioCrear() {
        formCrear?.reset();
        if (pristineCrear) pristineCrear.reset();

        // Fechas por defecto: hoy a mañana
        const hoy = new Date();
        const manana = new Date();
        manana.setDate(hoy.getDate() + 1);

        const fmtDate = (d) => d.toISOString().split('T')[0];
        if (inputFechaEntrada) inputFechaEntrada.value = fmtDate(hoy);
        if (inputFechaSalida) inputFechaSalida.value = fmtDate(manana);

        // Sincronizar Range Picker Alina (Flatpickr) del modal crear
        const rangeCrear = document.getElementById('crear-rango-fechas');
        if (rangeCrear && rangeCrear._flatpickr) {
            rangeCrear._flatpickr.setDate([fmtDate(hoy), fmtDate(manana)], false);
        }

        recalcularNoches();

        // Iniciar con 1 fila de unidad vacía
        if (contenedorUnidades) {
            contenedorUnidades.innerHTML = '';
            agregarFilaUnidad();
        }

        recalcularTotales();
    }

    function recalcularNoches() {
        if (!inputFechaEntrada || !inputFechaSalida || !badgeNochesCalculadas) return 0;
        const valE = inputFechaEntrada.value;
        const valS = inputFechaSalida.value;

        if (!valE || !valS) {
            badgeNochesCalculadas.textContent = '0';
            return 0;
        }

        const dE = new Date(valE + 'T00:00:00');
        const dS = new Date(valS + 'T00:00:00');

        const diffTime = dS - dE;
        const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays > 0) {
            badgeNochesCalculadas.textContent = String(diffDays);
            badgeNochesCalculadas.className = 'badge bg-light-primary f-s-14';
            return diffDays;
        } else {
            badgeNochesCalculadas.textContent = 'Inválido';
            badgeNochesCalculadas.className = 'badge bg-light-danger f-s-12';
            return 0;
        }
    }

    inputFechaEntrada?.addEventListener('change', () => {
        recalcularNoches();
        actualizarFilasUnidades();
    });

    inputFechaSalida?.addEventListener('change', () => {
        recalcularNoches();
        actualizarFilasUnidades();
    });

    if (btnAgregarUnidad) {
        btnAgregarUnidad.addEventListener('click', () => {
            agregarFilaUnidad();
        });
    }

    function agregarFilaUnidad() {
        if (!contenedorUnidades) return;

        const noches = recalcularNoches();
        const fila = document.createElement('div');
        fila.className = 'fila-unidad row g-2 align-items-center mb-2 p-2 bg-white border b-r-6';

        // Opciones de unidades
        let optionsHtml = '<option value="">-- Seleccionar unidad --</option>';
        catalogoUnidades.forEach(u => {
            optionsHtml += `<option value="${u.id}" data-codigo="${u.codigo}" data-propiedad="${u.propiedad_nombre}">[${u.propiedad_nombre}] ${u.codigo} - ${u.nombre}</option>`;
        });

        fila.innerHTML = `
            <div class="col-md-6 col-12">
                <select class="form-select form-select-sm select-unidad-id" required>
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-md-3 col-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">S/</span>
                    <input type="number" class="form-control input-precio-noche" placeholder="Precio/n" min="0" step="0.01" value="100.00" required>
                </div>
            </div>
            <div class="col-md-2 col-4 text-end">
                <span class="f-s-12 text-secondary d-block f-s-11">Subtotal:</span>
                <strong class="f-s-13 texto-subtotal-fila text-primary">S/ ${(100.00 * (noches > 0 ? noches : 1)).toFixed(2)}</strong>
            </div>
            <div class="col-md-1 col-2 text-end">
                <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-quitar-fila" title="Quitar unidad">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
        `;

        contenedorUnidades.appendChild(fila);

        const selectU = fila.querySelector('.select-unidad-id');
        const inputP = fila.querySelector('.input-precio-noche');
        const btnQ = fila.querySelector('.btn-quitar-fila');

        selectU.addEventListener('change', recalcularTotales);
        inputP.addEventListener('input', () => {
            recalcularFila(fila);
            recalcularTotales();
        });

        btnQ.addEventListener('click', () => {
            if (contenedorUnidades.querySelectorAll('.fila-unidad').length > 1) {
                fila.remove();
                recalcularTotales();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Unidad requerida',
                    text: 'La reserva debe contener al menos una unidad física asignada.',
                });
            }
        });

        recalcularTotales();
    }

    function recalcularFila(fila) {
        const noches = recalcularNoches();
        const inputP = fila.querySelector('.input-precio-noche');
        const textoSub = fila.querySelector('.texto-subtotal-fila');

        const precio = parseFloat(inputP.value) || 0;
        const n = noches > 0 ? noches : 0;
        const subtotal = precio * n;

        if (textoSub) {
            textoSub.textContent = `S/ ${subtotal.toFixed(2)}`;
        }
    }

    function actualizarFilasUnidades() {
        if (!contenedorUnidades) return;
        contenedorUnidades.querySelectorAll('.fila-unidad').forEach(f => recalcularFila(f));
        recalcularTotales();
    }

    function recalcularTotales() {
        const noches = recalcularNoches();
        let subtotalTotal = 0;

        if (contenedorUnidades) {
            contenedorUnidades.querySelectorAll('.fila-unidad').forEach(f => {
                const inputP = f.querySelector('.input-precio-noche');
                const precio = parseFloat(inputP?.value) || 0;
                subtotalTotal += precio * (noches > 0 ? noches : 0);
            });
        }

        const totalTotal = subtotalTotal; // Impuestos 0.00 en esta fase
        if (resumenSubtotal) resumenSubtotal.textContent = `S/ ${subtotalTotal.toFixed(2)}`;
        if (resumenImpuestos) resumenImpuestos.textContent = 'S/ 0.00';
        if (resumenTotal) resumenTotal.textContent = `S/ ${totalTotal.toFixed(2)}`;
    }

    // -------------------------------------------------------------------------
    // 5. Envío de Formulario Crear Reserva
    // -------------------------------------------------------------------------
    if (formCrear) {
        formCrear.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (pristineCrear && !pristineCrear.validate()) {
                return;
            }

            const noches = recalcularNoches();
            if (noches <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Intervalo inválido',
                    text: 'La fecha de salida debe ser posterior a la fecha de entrada (mínimo 1 noche).',
                });
                return;
            }

            // Recopilar unidades
            const filasUnidades = contenedorUnidades.querySelectorAll('.fila-unidad');
            if (filasUnidades.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sin unidades',
                    text: 'Debe asignar al menos una unidad a la reserva.',
                });
                return;
            }

            const unidadesPayload = [];
            const unidadesIdsVistos = new Set();
            let errorUnidadVacia = false;

            filasUnidades.forEach(f => {
                const uId = parseInt(f.querySelector('.select-unidad-id')?.value, 10);
                const precio = parseFloat(f.querySelector('.input-precio-noche')?.value);

                if (!uId || isNaN(uId)) {
                    errorUnidadVacia = true;
                    return;
                }

                if (unidadesIdsVistos.has(uId)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Unidad duplicada',
                        text: 'No puede seleccionar la misma unidad varias veces en la misma reserva.',
                    });
                    errorUnidadVacia = true;
                    return;
                }

                unidadesIdsVistos.add(uId);
                unidadesPayload.push({
                    unidad_id: uId,
                    precio_unitario_noche: isNaN(precio) ? 0.00 : precio,
                });
            });

            if (errorUnidadVacia || unidadesPayload.length === 0) {
                return;
            }

            const payload = {
                persona_titular_id: parseInt(selectTitular.value, 10),
                fecha_entrada: inputFechaEntrada.value,
                fecha_salida: inputFechaSalida.value,
                estado: selectEstadoCrear.value,
                canal: selectCanalCrear.value,
                observaciones: inputObservacionesCrear.value.trim(),
                unidades: unidadesPayload,
                _csrf_token: csrfToken,
            };

            // Enviar petición
            btnGuardarReserva.disabled = true;
            spinnerGuardar.classList.remove('d-none');

            try {
                const resp = await fetch('/reservas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                const json = await resp.json();

                if (resp.status === 201 && json.ok) {
                    modalCrear?.hide();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Reserva Creada!',
                        html: `Código: <strong>${json.datos.codigo}</strong><br>Estado: <strong>${json.datos.estado}</strong><br>Total: <strong>S/ ${parseFloat(json.datos.total).toFixed(2)}</strong>`,
                        confirmButtonText: 'Aceptar',
                    });
                    cargarReservas(1);
                } else if (resp.status === 409) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Conflicto de Disponibilidad',
                        text: json.mensaje || 'Una o más unidades seleccionadas ya no están disponibles para las fechas requeridas.',
                        confirmButtonText: 'Entendido',
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Inconsistencia en los datos',
                        text: json.mensaje || 'Revise los campos del formulario.',
                    });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Fallo de Red',
                    text: 'No se pudo comunicar con el servidor para registrar la reserva.',
                });
            } finally {
                btnGuardarReserva.disabled = false;
                spinnerGuardar.classList.add('d-none');
            }
        });
    }

    // -------------------------------------------------------------------------
    // 6. Ver Detalle de Reserva
    // -------------------------------------------------------------------------
    async function verDetalleReserva(id) {
        try {
            const resp = await fetch(`/reservas/${id}`);
            const json = await resp.json();

            if (!json.ok || !json.datos) {
                Swal.fire({
                    icon: 'error',
                    title: 'Reserva no encontrada',
                    text: json.mensaje || 'No se pudo cargar la información de la reserva.',
                });
                return;
            }

            reservaActualDetalle = json.datos;
            poblarModalDetalle(json.datos);
            modalDetalle?.show();
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo consultar el detalle de la reserva.',
            });
        }
    }

    function poblarModalDetalle(r) {
        if (!r) return;

        detalleCodigo.textContent = r.codigo;
        detalleEstadoBadge.innerHTML = obtenerBadgeEstado(r.estado);

        // Titular
        detalleTitularNombre.textContent = r.titular_nombre_completo || `Titular ID ${r.persona_titular_id}`;
        detalleTitularDocumento.textContent = r.titular_documento_numero ? `${r.titular_documento_tipo || 'DOC'}: ${r.titular_documento_numero}` : 'No registrado';
        detalleTitularContacto.textContent = r.titular_telefono || r.titular_email || 'Sin medios de contacto';

        // Período
        detalleFechaEntrada.textContent = r.fecha_entrada;
        detalleFechaSalida.textContent = r.fecha_salida;
        detalleNoches.textContent = `${r.noches} noche(s)`;
        detalleCanal.textContent = `${r.canal} (${r.origen})`;

        // Aviso hold si es PENDIENTE
        if (r.estado === 'PENDIENTE') {
            detalleAvisoHold.classList.remove('d-none');
            if (r.ha_expirado) {
                detalleAvisoHold.innerHTML = `<span class="text-danger f-w-600"><i class="fa-solid fa-triangle-exclamation me-1"></i> Hold vencido en: ${r.expira_en}</span>`;
            } else {
                detalleAvisoHold.innerHTML = `<span class="text-warning f-w-600"><i class="fa-solid fa-clock me-1"></i> Hold activo hasta: ${r.expira_en}</span>`;
            }
        } else {
            detalleAvisoHold.classList.add('d-none');
        }

        // Unidades
        let tbodyHtml = '';
        if (r.unidades && r.unidades.length > 0) {
            r.unidades.forEach(u => {
                tbodyHtml += `
                    <tr>
                        <td class="ps-2 f-w-600 text-primary">${u.unidad_codigo}</td>
                        <td>${u.propiedad_nombre || 'Inmueble'} / ${u.tipo_unidad_nombre || 'Unidad'}</td>
                        <td class="text-end">S/ ${parseFloat(u.precio_unitario_noche).toFixed(2)}</td>
                        <td class="text-center">${u.noches}</td>
                        <td class="text-end pe-2 f-w-600">S/ ${parseFloat(u.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });
        } else {
            tbodyHtml = `<tr><td colspan="5" class="text-center text-muted py-2">Sin unidades registradas</td></tr>`;
        }
        detalleTbodyUnidades.innerHTML = tbodyHtml;
        detalleTotalSnapshot.textContent = `S/ ${parseFloat(r.total).toFixed(2)}`;

        // Observaciones y Trazabilidad
        detalleObservaciones.textContent = r.observaciones || 'Ninguna';

        let trazaHtml = `<div>Creado: <strong>${r.creado_en}</strong> ${r.creador_nombre ? `por <strong>${r.creador_nombre}</strong>` : ''}</div>`;
        if (r.confirmada_en) {
            trazaHtml += `<div>Confirmado: <strong>${r.confirmada_en}</strong> ${r.confirmador_nombre ? `por <strong>${r.confirmador_nombre}</strong>` : ''}</div>`;
        }
        if (r.cancelada_en) {
            trazaHtml += `<div class="text-danger">Cancelado: <strong>${r.cancelada_en}</strong> ${r.cancelador_nombre ? `por <strong>${r.cancelador_nombre}</strong>` : ''} | Motivo: <em>${r.motivo_cancelacion || 'No especificado'}</em></div>`;
        }
        detalleTrazabilidad.innerHTML = trazaHtml;

        // Botones de acción modal
        if (r.estado === 'PENDIENTE') {
            btnDetalleConfirmar?.classList.remove('d-none');
            btnDetalleCancelar?.classList.remove('d-none');
        } else if (r.estado === 'CONFIRMADA') {
            btnDetalleConfirmar?.classList.add('d-none');
            btnDetalleCancelar?.classList.remove('d-none');
        } else {
            btnDetalleConfirmar?.classList.add('d-none');
            btnDetalleCancelar?.classList.add('d-none');
        }
    }

    // Acciones desde modal detalle
    btnDetalleConfirmar?.addEventListener('click', () => {
        if (!reservaActualDetalle) return;
        confirmarReserva(reservaActualDetalle.id, reservaActualDetalle.codigo);
    });

    btnDetalleCancelar?.addEventListener('click', () => {
        if (!reservaActualDetalle) return;
        modalDetalle?.hide();
        abrirModalCancelar(reservaActualDetalle.id, reservaActualDetalle.codigo);
    });

    // -------------------------------------------------------------------------
    // 7. Confirmar Reserva
    // -------------------------------------------------------------------------
    async function confirmarReserva(id, codigo) {
        const result = await Swal.fire({
            title: `¿Confirmar reserva ${codigo}?`,
            text: 'La reserva pasará a estado CONFIRMADA de forma permanente.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745',
        });

        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`/reservas/${id}/confirmar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ _csrf_token: csrfToken }),
            });

            const json = await resp.json();

            if (resp.ok && json.ok) {
                modalDetalle?.hide();
                Swal.fire({
                    icon: 'success',
                    title: '¡Reserva Confirmada!',
                    text: json.mensaje || 'La reserva fue confirmada exitosamente.',
                });
                cargarReservas(paginaActual);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo confirmar',
                    text: json.mensaje || 'Ocurrió un error al confirmar la reserva.',
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error de red',
                text: 'Fallo de comunicación con el servidor.',
            });
        }
    }

    // -------------------------------------------------------------------------
    // 8. Cancelar Reserva
    // -------------------------------------------------------------------------
    function abrirModalCancelar(id, codigo) {
        if (!inputCancelarId || !cancelarCodigoTexto || !inputCancelarMotivo) return;
        inputCancelarId.value = id;
        cancelarCodigoTexto.textContent = codigo;
        inputCancelarMotivo.value = '';
        modalCancelar?.show();
    }

    if (formCancelar) {
        formCancelar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = inputCancelarId.value;
            const motivo = inputCancelarMotivo.value.trim();

            if (!motivo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Motivo requerido',
                    text: 'Debe ingresar el motivo de cancelación.',
                });
                return;
            }

            btnConfirmarCancelacion.disabled = true;
            spinnerCancelar.classList.remove('d-none');

            try {
                const resp = await fetch(`/reservas/${id}/cancelar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        motivo: motivo,
                        _csrf_token: csrfToken,
                    }),
                });

                const json = await resp.json();

                if (resp.ok && json.ok) {
                    modalCancelar?.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Reserva Cancelada',
                        text: json.mensaje || 'La reserva fue cancelada y el inventario liberado.',
                    });
                    cargarReservas(paginaActual);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo cancelar',
                        text: json.mensaje || 'Error al cancelar la reserva.',
                    });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de red',
                    text: 'Fallo de conexión al procesar la cancelación.',
                });
            } finally {
                btnConfirmarCancelacion.disabled = false;
                spinnerCancelar.classList.add('d-none');
            }
        });
    }

    // -------------------------------------------------------------------------
    // 9. Expirar Holds Vencidos
    // -------------------------------------------------------------------------
    if (btnExpirarHolds) {
        btnExpirarHolds.addEventListener('click', async () => {
            const confirm = await Swal.fire({
                title: '¿Expirar holds vencidos?',
                text: 'Se buscarán todas las reservas PENDIENTES cuyo tiempo de hold haya vencido y se liberará su inventario.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ejecutar expiración',
                cancelButtonText: 'Cancelar',
            });

            if (!confirm.isConfirmed) return;

            try {
                const resp = await fetch('/reservas/expirar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ _csrf_token: csrfToken }),
                });

                const json = await resp.json();

                if (resp.ok && json.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Proceso completado',
                        text: json.mensaje || 'Se ejecutó la expiración de reservas.',
                    });
                    cargarReservas(paginaActual);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: json.mensaje || 'Ocurrió un error al expirar reservas.',
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de red',
                    text: 'Fallo de comunicación al ejecutar la expiración.',
                });
            }
        });
    }

    // -------------------------------------------------------------------------
    // 10. Eventos de Filtro y Recarga
    // -------------------------------------------------------------------------
    btnRecargar?.addEventListener('click', () => cargarReservas(1));
    filtroEstado?.addEventListener('change', () => cargarReservas(1));
    filtroPropiedad?.addEventListener('change', () => cargarReservas(1));
    filtroFechaDesde?.addEventListener('change', () => cargarReservas(1));
    filtroFechaHasta?.addEventListener('change', () => cargarReservas(1));

    let debounceTimer = null;
    filtroBusqueda?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => cargarReservas(1), 350);
    });

    btnLimpiarBusqueda?.addEventListener('click', () => {
        if (filtroBusqueda) filtroBusqueda.value = '';
        cargarReservas(1);
    });

    const btnLimpiarFiltroFechas = document.getElementById('btn-limpiar-filtro-fechas');
    btnLimpiarFiltroFechas?.addEventListener('click', () => {
        const rangeFiltro = document.getElementById('filtro-rango-fechas');
        if (rangeFiltro && rangeFiltro._flatpickr) {
            rangeFiltro._flatpickr.clear();
        }
        if (filtroFechaDesde) filtroFechaDesde.value = '';
        if (filtroFechaHasta) filtroFechaHasta.value = '';
        cargarReservas(1);
    });

    // Iniciar módulo
    inicializar();
});
