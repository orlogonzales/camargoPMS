/**
 * Camargo PMS — Módulo de Gestión de Caja, Cuentas de Folios y Tesorería (FINANCIERO-2)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - Tríada Financiera: CARGO ≠ PAGO ≠ MOVIMIENTO DE CAJA.
 * - Desacoplamiento de cobro y deuda: PAGO ≠ APLICACIÓN.
 * - Arqueo de caja determinista (Cuadrada, Sobrante, Faltante). Justificación obligatoria en descuadres.
 * - D-071: Font Awesome 6.3.0 exclusivo, Flatpickr Date Picker, Variants of badge Alina (bg-light-*).
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Cache local
    let foliosCache = [];
    let folioActualCache = null;
    let sesionActivaCache = null;
    let auxiliaresCache = { cajas: [], metodos: [], bancos: [] };
    let foliosRelacionadosCache = [];

    // =========================================================================
    // Utilidades
    // =========================================================================
    function formatearDinero(monto) {
        const num = parseFloat(monto) || 0;
        return 'S/ ' + num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escaparHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function badgeEstadoFinanciero(estado) {
        switch (estado) {
            case 'SALDADA':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i> SALDADA</span>';
            case 'PENDIENTE_PAGO':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> PENDIENTE DE PAGO</span>';
            case 'SALDO_A_FAVOR':
                return '<span class="badge bg-light-info text-info"><i class="fa-solid fa-coins me-1"></i> SALDO A FAVOR</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoCargo(estado) {
        switch (estado) {
            case 'DEVENGADO':
                return '<span class="badge bg-light-success text-success">DEVENGADO</span>';
            case 'PROVISIONAL':
                return '<span class="badge bg-light-warning text-warning">PROVISIONAL</span>';
            case 'ANULADO':
                return '<span class="badge bg-light-danger text-danger">ANULADO</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoPago(estado) {
        switch (estado) {
            case 'CONFIRMADO':
                return '<span class="badge bg-light-success text-success">CONFIRMADO</span>';
            case 'REVERSADO':
                return '<span class="badge bg-light-danger text-danger">REVERSADO</span>';
            case 'PENDIENTE':
                return '<span class="badge bg-light-warning text-warning">PENDIENTE</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    // =========================================================================
    // Carga de Auxiliares y Sesión Activa
    // =========================================================================
    async function cargarAuxiliares() {
        try {
            const resp = await fetch('/caja/auxiliares');
            const data = await resp.json();
            if (data.ok) {
                auxiliaresCache = data;
            }
        } catch (err) {
            console.error('Error al cargar auxiliares de caja:', err);
        }
    }

    async function cargarSesionActiva() {
        try {
            const resp = await fetch('/caja/sesion-activa');
            const data = await resp.json();
            if (data.ok) {
                sesionActivaCache = data.datos;
                actualizarUiSesionCaja();
            }
        } catch (err) {
            console.error('Error al consultar sesión activa:', err);
        }
    }

    function actualizarUiSesionCaja() {
        const contenedorAcciones = document.getElementById('contenedor-acciones-caja');
        const tabSesionContenido = document.getElementById('contenedor-detalle-sesion');
        const inputSesionCajaId = document.getElementById('cobro-sesion-caja-id');

        if (!sesionActivaCache) {
            // Sin turno abierto
            if (contenedorAcciones) {
                contenedorAcciones.innerHTML = `
                    <span class="badge bg-light-secondary text-secondary p-2 f-s-12">
                        <i class="fa-solid fa-circle me-1 f-s-9"></i> Sin Turno Abierto
                    </span>
                    <button type="button" class="btn btn-success btn-sm" id="btn-abrir-apertura-caja">
                        <i class="fa-solid fa-key me-1"></i> Abrir Turno de Caja
                    </button>
                `;
                document.getElementById('btn-abrir-apertura-caja')?.addEventListener('click', () => {
                    const modal = new bootstrap.Modal(document.getElementById('modal-apertura-caja'));
                    modal.show();
                });
            }

            if (inputSesionCajaId) inputSesionCajaId.value = '';

            document.getElementById('kpi-efectivo-caja').textContent = 'S/ 0.00';
            document.getElementById('kpi-efectivo-caja-sub').textContent = 'Caja física cerrada';

            if (tabSesionContenido) {
                tabSesionContenido.innerHTML = `
                    <div class="text-center py-5">
                        <div class="bg-light-secondary text-secondary p-3 d-inline-block b-r-50 mb-3">
                            <i class="fa-solid fa-lock f-s-32"></i>
                        </div>
                        <h5 class="f-w-700">No hay un turno de caja abierto actualmente</h5>
                        <p class="text-muted f-s-13">Para registrar cobros o egresos en efectivo, abra un nuevo turno de trabajo con fondo de cambio.</p>
                        <button type="button" class="btn btn-success btn-sm mt-2" onclick="document.getElementById('btn-abrir-apertura-caja')?.click();">
                            <i class="fa-solid fa-key me-1"></i> Abrir Turno de Caja
                        </button>
                    </div>
                `;
            }
            return;
        }

        // Turno Abierto
        const s = sesionActivaCache;
        if (inputSesionCajaId) inputSesionCajaId.value = s.id;

        const ingresos = parseFloat(s.total_ingresos_efectivo) || 0;
        const egresos = parseFloat(s.total_egresos_efectivo) || 0;
        const apertura = parseFloat(s.monto_apertura) || 0;
        const esperado = apertura + ingresos - egresos;

        document.getElementById('kpi-efectivo-caja').textContent = formatearDinero(esperado);
        document.getElementById('kpi-efectivo-caja-sub').textContent = `Fondo S/ ${apertura.toFixed(2)} + Ing S/ ${ingresos.toFixed(2)} - Egr S/ ${egresos.toFixed(2)}`;

        if (contenedorAcciones) {
            contenedorAcciones.innerHTML = `
                <span class="badge bg-light-success text-success p-2 f-s-12">
                    <i class="fa-solid fa-circle me-1 f-s-9"></i> Turno #${s.id} Abierto
                </span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-movimiento">
                    <i class="fa-solid fa-money-bill-transfer me-1"></i> Movimiento Efectivo
                </button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-abrir-cierre-caja" data-sesion-id="${s.id}">
                    <i class="fa-solid fa-calculator me-1"></i> Arqueo y Cierre
                </button>
            `;

            document.getElementById('btn-abrir-movimiento')?.addEventListener('click', () => {
                const form = document.getElementById('form-movimiento-caja');
                form?.reset();
                const modal = new bootstrap.Modal(document.getElementById('modal-movimiento-caja'));
                modal.show();
            });

            document.getElementById('btn-abrir-cierre-caja')?.addEventListener('click', () => {
                abrirModalCierreCaja(s);
            });
        }

        cargarDetalleSesion(s.id);
    }

    async function cargarDetalleSesion(sesionId) {
        const tabSesionContenido = document.getElementById('contenedor-detalle-sesion');
        if (!tabSesionContenido) return;

        try {
            const resp = await fetch(`/caja/sesion/${sesionId}`);
            const data = await resp.json();
            if (!data.ok) {
                tabSesionContenido.innerHTML = `<div class="alert alert-danger">${escaparHtml(data.mensaje)}</div>`;
                return;
            }

            const s = data.datos.sesion;
            const movimientos = data.datos.movimientos || [];

            const apertura = parseFloat(s.monto_apertura) || 0;
            const ingresos = parseFloat(s.total_ingresos_efectivo) || 0;
            const egresos = parseFloat(s.total_egresos_efectivo) || 0;
            const esperado = apertura + ingresos - egresos;

            let htmlMovs = '';
            if (movimientos.length === 0) {
                htmlMovs = '<tr><td colspan="5" class="text-center py-3 text-muted">Sin movimientos registrados en este turno.</td></tr>';
            } else {
                movimientos.forEach(m => {
                    const esIngreso = m.tipo_movimiento.startsWith('INGRESO');
                    const colorClase = esIngreso ? 'text-success' : 'text-danger';
                    const signo = esIngreso ? '+' : '-';
                    htmlMovs += `
                        <tr>
                            <td>${escaparHtml(m.creado_en)}</td>
                            <td><span class="badge ${esIngreso ? 'bg-light-success text-success' : 'bg-light-danger text-danger'}">${escaparHtml(m.tipo_movimiento)}</span></td>
                            <td>${escaparHtml(m.concepto)}</td>
                            <td class="text-muted f-s-11">${m.pago_id ? 'Pago #' + m.pago_id : (m.devolucion_id ? 'Devolución #' + m.devolucion_id : 'MANUAL')}</td>
                            <td class="text-end f-w-700 ${colorClase}">${signo} ${formatearDinero(m.monto)}</td>
                        </tr>
                    `;
                });
            }

            tabSesionContenido.innerHTML = `
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light b-r-8 border">
                            <span class="text-muted f-s-11 text-uppercase f-w-600">Turno de Caja</span>
                            <h5 class="mb-0 mt-1 f-w-700 text-dark">Sesión #${s.id}</h5>
                            <span class="f-s-11 text-muted">Abierta: ${escaparHtml(s.abierta_en)}</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light b-r-8 border">
                            <span class="text-muted f-s-11 text-uppercase f-w-600">Fondo Inicial de Apertura</span>
                            <h5 class="mb-0 mt-1 f-w-700 text-primary">${formatearDinero(apertura)}</h5>
                            <span class="f-s-11 text-muted">Cambio en gaveta</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light b-r-8 border">
                            <span class="text-muted f-s-11 text-uppercase f-w-600">Cobros Efectivo (+)</span>
                            <h5 class="mb-0 mt-1 f-w-700 text-success">${formatearDinero(ingresos)}</h5>
                            <span class="f-s-11 text-muted">Ingresos del turno</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light b-r-8 border">
                            <span class="text-muted f-s-11 text-uppercase f-w-600">Egresos Efectivo (-)</span>
                            <h5 class="mb-0 mt-1 f-w-700 text-danger">${formatearDinero(egresos)}</h5>
                            <span class="f-s-11 text-muted">Devoluciones / Gastos</span>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-none border b-r-8">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="mb-0 f-s-13 f-w-700"><i class="fa-solid fa-list-ul text-secondary me-2"></i> Libro Diario de Movimientos de Caja</h6>
                        <span class="badge bg-light-primary text-primary">${movimientos.length} registro(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 f-s-12">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha / Hora</th>
                                    <th>Tipo</th>
                                    <th>Concepto</th>
                                    <th>Referencia</th>
                                    <th class="text-end">Monto PEN</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${htmlMovs}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } catch (err) {
            console.error('Error al cargar detalle de sesión:', err);
        }
    }

    // =========================================================================
    // Carga de Folios y Tabla de Cuentas
    // =========================================================================
    async function cargarFolios() {
        const tbody = document.getElementById('tbody-folios');
        const q = document.getElementById('filtro-q')?.value || '';
        const estado = document.getElementById('filtro-estado')?.value || '';

        try {
            const queryParams = new URLSearchParams();
            if (q) queryParams.append('q', q);
            if (estado) queryParams.append('estado', estado);

            const resp = await fetch(`/caja/folios?${queryParams.toString()}`);
            const data = await resp.json();

            if (!data.ok) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${escaparHtml(data.mensaje)}</td></tr>`;
                return;
            }

            foliosCache = data.datos || [];
            renderizarTablaFolios();
            actualizarKpisFolios();
        } catch (err) {
            console.error('Error al cargar folios:', err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Error de conexión al cargar folios.</td></tr>';
        }
    }

    function renderizarTablaFolios() {
        const tbody = document.getElementById('tbody-folios');
        if (!tbody) return;

        if (foliosCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron cuentas folios registradas con los filtros seleccionados.</td></tr>';
            return;
        }

        let html = '';
        foliosCache.forEach(f => {
            const devengado = parseFloat(f.total_cargos_devengados) || 0;
            const pagado = parseFloat(f.total_pagos_confirmados) || 0;
            const saldo = parseFloat(f.saldo_pendiente) || 0;
            const saldoDisp = parseFloat(f.saldo_disponible) || 0;

            let estadoFinanciero = 'PENDIENTE_PAGO';
            if (saldo === 0 && saldoDisp === 0) {
                estadoFinanciero = 'SALDADA';
            } else if (saldoDisp > 0 && saldo === 0) {
                estadoFinanciero = 'SALDO_A_FAVOR';
            }

            const esPrincipal = parseInt(f.es_principal) === 1 || f.es_principal === true;
            const badgePrincipal = esPrincipal
                ? '<span class="badge bg-light-primary text-primary f-s-10 me-1">PRINCIPAL</span>'
                : `<span class="badge bg-light-secondary text-secondary f-s-10 me-1">${escaparHtml(f.etiqueta || 'SECUNDARIO')}</span>`;

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center mb-1">
                            ${badgePrincipal}
                            <span class="f-w-700 text-dark f-s-13">${escaparHtml(f.codigo)}</span>
                        </div>
                        <div class="f-s-11 text-muted">Folio ID #${f.id}</div>
                    </td>
                    <td>
                        <div class="f-w-600 text-dark">${escaparHtml(f.titular_nombre_completo)}</div>
                        <div class="f-s-11 text-muted">Doc: ${escaparHtml(f.titular_documento || 'S/D')} | Res: <strong class="text-primary">${escaparHtml(f.reserva_codigo)}</strong></div>
                    </td>
                    <td>
                        <div class="f-s-12">${escaparHtml(f.fecha_llegada)} al ${escaparHtml(f.fecha_salida)}</div>
                    </td>
                    <td class="text-end f-w-600 text-dark">${formatearDinero(devengado)}</td>
                    <td class="text-end f-w-600 text-success">${formatearDinero(pagado)}</td>
                    <td class="text-end f-w-700 ${saldo > 0 ? 'text-danger' : 'text-success'}">
                        ${formatearDinero(saldo)}
                    </td>
                    <td class="text-center">${badgeEstadoFinanciero(estadoFinanciero)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary btn-sm btn-ver-folio" data-id="${f.id}">
                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Estado de Cuenta
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // Listeners para ver estado de cuenta
        document.querySelectorAll('.btn-ver-folio').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const folioId = e.currentTarget.dataset.id;
                abrirModalFolioDetalle(folioId);
            });
        });
    }

    function actualizarKpisFolios() {
        let cuentasPendientes = 0;
        let totalCobrado = 0;
        let totalDevuelto = 0;

        foliosCache.forEach(f => {
            const saldo = parseFloat(f.saldo_pendiente) || 0;
            if (saldo > 0) cuentasPendientes++;
            totalCobrado += parseFloat(f.total_pagos_confirmados) || 0;
            totalDevuelto += parseFloat(f.total_devoluciones_confirmadas) || 0;
        });

        document.getElementById('kpi-cuentas-pendientes').textContent = cuentasPendientes;
        document.getElementById('kpi-total-cobrado').textContent = formatearDinero(totalCobrado);
        document.getElementById('kpi-total-devuelto').textContent = formatearDinero(totalDevuelto);
    }

    // =========================================================================
    // Modal de Detalle de Folio / Estado de Cuenta
    // =========================================================================
    async function abrirModalFolioDetalle(folioId) {
        try {
            const resp = await fetch(`/caja/folios/${folioId}`);
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                return;
            }

            folioActualCache = data.datos;
            const f = data.datos;

            document.getElementById('folio-codigo-header').textContent = f.folio.codigo;
            document.getElementById('folio-sub-header').textContent = `Cuenta Folio ID #${f.folio.id} — Moneda: ${f.folio.moneda_codigo} — Estado: ${f.folio.estado}`;

            document.getElementById('bal-cargos-devengados').textContent = formatearDinero(f.total_cargos_devengados);
            document.getElementById('bal-cargos-provisionales').textContent = formatearDinero(f.total_cargos_provisionales);
            document.getElementById('bal-total-pagado').textContent = formatearDinero(f.total_pagos_confirmados);
            document.getElementById('bal-pagos-aplicados').textContent = formatearDinero(f.total_pagos_aplicados);
            document.getElementById('bal-saldo-disponible').textContent = formatearDinero(f.total_pagos_disponibles);
            document.getElementById('bal-saldo-exigible').textContent = formatearDinero(f.saldo_neto_exigible);

            // Consultar folios relacionados (hermanos) (FINANCIERO-3C)
            try {
                const respRel = await fetch(`/folios/${folioId}/relacionados`);
                const dataRel = await respRel.json();
                if (dataRel.ok && Array.isArray(dataRel.datos)) {
                    foliosRelacionadosCache = dataRel.datos;
                } else {
                    foliosRelacionadosCache = [f.folio];
                }
            } catch (errRel) {
                foliosRelacionadosCache = [f.folio];
            }

            // Renderizar Barra de Tabs Multi-Folio (FINANCIERO-3C)
            const ulTabs = document.getElementById('nav-folios-reserva');
            if (ulTabs) {
                let htmlTabs = '';
                foliosRelacionadosCache.forEach(rf => {
                    const esActual = parseInt(rf.id) === parseInt(f.folio.id);
                    const esPrincipal = parseInt(rf.es_principal) === 1 || rf.es_principal === true;
                    let badgeHtml = '';
                    if (esPrincipal) {
                        badgeHtml = '<i class="fa-solid fa-star text-warning me-1"></i><span class="badge bg-primary me-1">PRINCIPAL</span>';
                    } else {
                        badgeHtml = `<span class="badge bg-secondary me-1">${escaparHtml(rf.etiqueta || 'SECUNDARIO')}</span>`;
                    }
                    htmlTabs += `
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link btn-sm ${esActual ? 'active f-w-700' : ''} btn-switch-folio d-flex align-items-center" data-folio-id="${rf.id}">
                                ${badgeHtml} <span>${escaparHtml(rf.codigo)}</span>
                            </button>
                        </li>
                    `;
                });
                ulTabs.innerHTML = htmlTabs;

                // Listeners de cambio de folio en las pestañas
                ulTabs.querySelectorAll('.btn-switch-folio').forEach(btn => {
                    btn.onclick = (e) => {
                        const targetFolioId = e.currentTarget.dataset.folioId;
                        if (targetFolioId && parseInt(targetFolioId) !== parseInt(f.folio.id)) {
                            abrirModalFolioDetalle(targetFolioId);
                        }
                    };
                });
            }

            // Configurar botón "+ Nuevo Folio"
            const btnCrearSec = document.getElementById('btn-abrir-crear-folio-secundario');
            if (btnCrearSec) {
                btnCrearSec.onclick = () => {
                    abrirModalCrearFolioSecundario(f.folio.id);
                };
            }

            // Renderizar Cargos (FINANCIERO-3C con acciones Transferir y Dividir)
            const tbodyCargos = document.getElementById('tbody-folio-cargos');
            if (f.cargos.length === 0) {
                tbodyCargos.innerHTML = '<tr><td colspan="9" class="text-center py-3 text-muted">Sin cargos devengados en cuenta.</td></tr>';
            } else {
                let htmlC = '';
                f.cargos.forEach(c => {
                    const pendiente = parseFloat(c.monto_total) - parseFloat(c.monto_aplicado_acumulado);
                    const tieneSaldoPendiente = pendiente > 0.00;
                    const haySaldoAFavor = parseFloat(f.total_pagos_disponibles) > 0.00;
                    const tieneCobrosAplicados = parseFloat(c.monto_aplicado_acumulado) > 0.00;

                    let btnAplicar = '';
                    if (tieneSaldoPendiente && c.estado === 'DEVENGADO' && haySaldoAFavor) {
                        btnAplicar = `
                            <button type="button" class="btn btn-outline-info btn-xs py-0 px-2 btn-aplicar-a-cargo" 
                                    data-cargo-id="${c.id}" data-cargo-codigo="${escaparHtml(c.codigo)}" 
                                    data-saldo-pendiente="${pendiente.toFixed(2)}" title="Imputar saldo a favor">
                                <i class="fa-solid fa-link me-1"></i> Imputar Saldo
                            </button>
                        `;
                    }

                    // Botones de Transferencia y Split (FINANCIERO-3C)
                    let btnTransferir = `
                        <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2 btn-transferir-cargo"
                                data-cargo-id="${c.id}" data-cargo-codigo="${escaparHtml(c.codigo)}"
                                data-cargo-concepto="${escaparHtml(c.descripcion)}" data-cargo-total="${c.monto_total}"
                                data-aplicado="${c.monto_aplicado_acumulado}" title="Transferir cargo completo a otro folio">
                            <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Transferir
                        </button>
                    `;

                    let btnSplit = `
                        <button type="button" class="btn btn-outline-warning btn-xs py-0 px-2 btn-split-cargo"
                                data-cargo-id="${c.id}" data-cargo-codigo="${escaparHtml(c.codigo)}"
                                data-cargo-concepto="${escaparHtml(c.descripcion)}" data-cargo-total="${c.monto_total}"
                                data-aplicado="${c.monto_aplicado_acumulado}" title="Dividir parcialmente cargo">
                            <i class="fa-solid fa-scissors me-1"></i> Dividir
                        </button>
                    `;

                    htmlC += `
                        <tr>
                            <td>
                                <strong class="text-dark">${escaparHtml(c.codigo)}</strong>
                                ${tieneCobrosAplicados ? '<span class="badge bg-light-info text-info ms-1" title="Cargo con cobros aplicados"><i class="fa-solid fa-lock"></i></span>' : ''}
                            </td>
                            <td>${escaparHtml(c.descripcion)}</td>
                            <td class="text-center">${escaparHtml(c.cantidad)}</td>
                            <td class="text-end">${formatearDinero(c.precio_unitario)}</td>
                            <td class="text-end f-w-600">${formatearDinero(c.monto_total)}</td>
                            <td class="text-end text-success">${formatearDinero(c.monto_aplicado_acumulado)}</td>
                            <td class="text-end f-w-700 ${tieneSaldoPendiente ? 'text-danger' : 'text-success'}">${formatearDinero(pendiente)}</td>
                            <td class="text-center">${badgeEstadoCargo(c.estado)}</td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    ${btnAplicar}
                                    ${btnTransferir}
                                    ${btnSplit}
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbodyCargos.innerHTML = htmlC;
            }

            // Renderizar Pagos
            const tbodyPagos = document.getElementById('tbody-folio-pagos');
            if (f.pagos.length === 0) {
                tbodyPagos.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted">Sin pagos registrados.</td></tr>';
            } else {
                let htmlP = '';
                f.pagos.forEach(p => {
                    const disp = parseFloat(p.monto_total) - parseFloat(p.monto_aplicado);
                    const metodoNombre = auxiliaresCache.metodos.find(m => m.id == p.metodo_pago_id)?.nombre || `Método #${p.metodo_pago_id}`;
                    
                    let accionesPago = '';
                    if (p.estado === 'CONFIRMADO') {
                        accionesPago = `
                            <button type="button" class="btn btn-outline-danger btn-xs py-0 px-2 btn-reversar-pago" data-id="${p.id}" data-codigo="${escaparHtml(p.codigo)}">
                                <i class="fa-solid fa-ban me-1"></i> Reversar
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-xs py-0 px-2 btn-abrir-devolucion" 
                                    data-id="${p.id}" data-codigo="${escaparHtml(p.codigo)}" data-monto="${p.monto_total}">
                                <i class="fa-solid fa-arrow-rotate-left me-1"></i> Devolver
                            </button>
                        `;
                    }

                    htmlP += `
                        <tr>
                            <td><strong class="text-dark">${escaparHtml(p.codigo)}</strong></td>
                            <td>${escaparHtml(metodoNombre)}</td>
                            <td class="text-muted f-s-11">${escaparHtml(p.referencia_operacion || 'Sin referencia')}</td>
                            <td class="text-end f-w-700 text-success">${formatearDinero(p.monto_total)}</td>
                            <td class="text-end text-info">${formatearDinero(p.monto_aplicado)}</td>
                            <td class="text-end f-w-700 ${disp > 0 ? 'text-primary' : 'text-muted'}">${formatearDinero(disp)}</td>
                            <td class="text-center">${badgeEstadoPago(p.estado)}</td>
                            <td class="text-center">${accionesPago}</td>
                        </tr>
                    `;
                });
                tbodyPagos.innerHTML = htmlP;
            }

            // Renderizar Devoluciones
            const tbodyDev = document.getElementById('tbody-folio-devoluciones');
            if (f.devoluciones.length === 0) {
                tbodyDev.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Sin devoluciones registradas.</td></tr>';
            } else {
                let htmlD = '';
                f.devoluciones.forEach(d => {
                    const metodoNombre = auxiliaresCache.metodos.find(m => m.id == d.metodo_pago_id)?.nombre || `Método #${d.metodo_pago_id}`;
                    htmlD += `
                        <tr>
                            <td><strong class="text-danger">${escaparHtml(d.codigo)}</strong></td>
                            <td>Pago #${d.pago_origen_id}</td>
                            <td>${escaparHtml(metodoNombre)}</td>
                            <td>${escaparHtml(d.motivo)}</td>
                            <td class="text-end f-w-700 text-danger">-${formatearDinero(d.monto)}</td>
                            <td class="text-center"><span class="badge bg-light-danger text-danger">${escaparHtml(d.estado)}</span></td>
                        </tr>
                    `;
                });
                tbodyDev.innerHTML = htmlD;
            }

            // Renderizar Historial de Transferencias y Splits (FINANCIERO-3C)
            cargarHistorialTransferencias(f.folio.id);

            // Configurar botón "Registrar Cobro" del modal
            const btnCobroFolio = document.getElementById('btn-abrir-cobro-folio');
            if (btnCobroFolio) {
                btnCobroFolio.onclick = () => {
                    abrirModalRegistrarCobro(f);
                };
            }

            // Configurar botones de aplicar pago
            document.querySelectorAll('.btn-aplicar-a-cargo').forEach(b => {
                b.onclick = (e) => {
                    const cargoId = e.currentTarget.dataset.cargoId;
                    const cargoCod = e.currentTarget.dataset.cargoCodigo;
                    const pendiente = e.currentTarget.dataset.saldoPendiente;
                    abrirModalAplicarPago(cargoId, cargoCod, pendiente, f);
                };
            });

            // Configurar botones de transferir cargo (FINANCIERO-3C)
            document.querySelectorAll('.btn-transferir-cargo').forEach(b => {
                b.onclick = (e) => {
                    const aplicado = parseFloat(e.currentTarget.dataset.aplicado) || 0;
                    if (aplicado > 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Operación no permitida',
                            text: 'Este cargo tiene cobros aplicados y no puede transferirse ni dividirse. Debe revertirse previamente la aplicación correspondiente.',
                        });
                        return;
                    }
                    abrirModalTransferirCargo(e.currentTarget.dataset, f);
                };
            });

            // Configurar botones de dividir / split cargo (FINANCIERO-3C)
            document.querySelectorAll('.btn-split-cargo').forEach(b => {
                b.onclick = (e) => {
                    const aplicado = parseFloat(e.currentTarget.dataset.aplicado) || 0;
                    if (aplicado > 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Operación no permitida',
                            text: 'Este cargo tiene cobros aplicados y no puede transferirse ni dividirse. Debe revertirse previamente la aplicación correspondiente.',
                        });
                        return;
                    }
                    abrirModalSplitCargo(e.currentTarget.dataset, f);
                };
            });

            // Configurar botones de reversar pago
            document.querySelectorAll('.btn-reversar-pago').forEach(b => {
                b.onclick = (e) => {
                    const pagoId = e.currentTarget.dataset.id;
                    const pagoCod = e.currentTarget.dataset.codigo;
                    solicitarReversoPago(pagoId, pagoCod, f.folio.id);
                };
            });

            // Configurar botones de devolución
            document.querySelectorAll('.btn-abrir-devolucion').forEach(b => {
                b.onclick = (e) => {
                    const pagoId = e.currentTarget.dataset.id;
                    const pagoCod = e.currentTarget.dataset.codigo;
                    const monto = e.currentTarget.dataset.monto;
                    abrirModalDevolucion(pagoId, pagoCod, monto, f.folio.id);
                };
            });

            const modalEl = document.getElementById('modal-folio-detalle');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();

        } catch (err) {
            console.error('Error al abrir detalle de folio:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el estado de cuenta.' });
        }
    }

    /**
     * Carga y renderiza el historial de transferencias y splits de un folio (FINANCIERO-3C).
     */
    async function cargarHistorialTransferencias(folioId) {
        const tbodyTransf = document.getElementById('tbody-folio-transferencias');
        const badgeCount = document.getElementById('badge-total-transferencias');
        if (!tbodyTransf) return;

        try {
            const resp = await fetch(`/folios/${folioId}/transferencias`);
            const data = await resp.json();
            if (!data.ok || !Array.isArray(data.datos) || data.datos.length === 0) {
                tbodyTransf.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Sin transferencias registradas en este folio.</td></tr>';
                if (badgeCount) badgeCount.textContent = '0 movimientos';
                return;
            }

            const transferencias = data.datos;
            if (badgeCount) badgeCount.textContent = `${transferencias.length} movimientos`;

            let htmlT = '';
            transferencias.forEach(t => {
                const badgeTipo = t.tipo_operacion === 'TOTAL'
                    ? '<span class="badge bg-light-primary text-primary">TRANSFERENCIA TOTAL</span>'
                    : '<span class="badge bg-light-warning text-warning">SPLIT PARCIAL</span>';
                const origTexto = t.folio_origen_codigo || `FOL-#${t.cuenta_folio_origen_id}`;
                const destTexto = t.folio_destino_codigo || `FOL-#${t.cuenta_folio_destino_id}`;
                const cargoTexto = t.cargo_origen_id ? `Cargo #${t.cargo_origen_id}` : '-';

                htmlT += `
                    <tr>
                        <td>${escaparHtml(t.transferido_en || '-')}</td>
                        <td class="text-center">${badgeTipo}</td>
                        <td><strong class="text-dark">${escaparHtml(cargoTexto)}</strong></td>
                        <td><span class="badge bg-light-secondary text-secondary">${escaparHtml(origTexto)}</span></td>
                        <td><span class="badge bg-light-info text-info">${escaparHtml(destTexto)}</span></td>
                        <td class="text-end f-w-700 text-primary">${formatearDinero(t.monto_transferido)}</td>
                        <td class="text-muted f-s-11">${escaparHtml(t.motivo || '-')}</td>
                    </tr>
                `;
            });
            tbodyTransf.innerHTML = htmlT;
        } catch (err) {
            console.error('Error al cargar historial de transferencias:', err);
            tbodyTransf.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Sin transferencias registradas en este folio.</td></tr>';
        }
    }

    // =========================================================================
    // Modal Registrar Cobro / Pago
    // =========================================================================
    function abrirModalRegistrarCobro(f) {
        const form = document.getElementById('form-registrar-cobro');
        form?.reset();

        document.getElementById('cobro-folio-id').value = f.folio.id;

        // Poblar selector de auto-aplicar cargos pendientes
        const selAutoaplica = document.getElementById('cobro-cargo-autoaplica');
        selAutoaplica.innerHTML = '<option value="">No aplicar ahora (dejar como saldo a favor disponible)</option>';

        f.cargos.forEach(c => {
            const pend = parseFloat(c.monto_total) - parseFloat(c.monto_aplicado_acumulado);
            if (pend > 0 && c.estado === 'DEVENGADO') {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `[${c.codigo}] ${c.descripcion} — Pendiente: S/ ${pend.toFixed(2)}`;
                opt.dataset.pendiente = pend.toFixed(2);
                selAutoaplica.appendChild(opt);
            }
        });

        // Configurar monto sugerido según saldo pendiente si existe
        const saldoExigible = parseFloat(f.saldo_neto_exigible) || 0;
        if (saldoExigible > 0) {
            document.getElementById('cobro-monto').value = saldoExigible.toFixed(2);
        }

        const modal = new bootstrap.Modal(document.getElementById('modal-registrar-cobro'));
        modal.show();
    }

    // Cambio de método de pago en registro de cobro
    document.getElementById('cobro-metodo-id')?.addEventListener('change', (e) => {
        const sel = e.target;
        const opt = sel.options[sel.selectedIndex];
        const destino = opt?.dataset.tipoDestino;

        const grupoBanco = document.getElementById('grupo-cuenta-bancaria');
        const grupoCaja = document.getElementById('grupo-sesion-caja');

        if (destino === 'CUENTA_BANCARIA') {
            grupoBanco?.classList.remove('d-none');
            document.getElementById('cobro-cuenta-bancaria-id').setAttribute('required', 'required');
            grupoCaja?.classList.add('d-none');
        } else if (destino === 'CAJA_FISICA') {
            grupoCaja?.classList.remove('d-none');
            grupoBanco?.classList.add('d-none');
            document.getElementById('cobro-cuenta-bancaria-id')?.removeAttribute('required');
        } else {
            grupoBanco?.classList.add('d-none');
            grupoCaja?.classList.add('d-none');
            document.getElementById('cobro-cuenta-bancaria-id')?.removeAttribute('required');
        }
    });

    // Envío del cobro
    document.getElementById('form-registrar-cobro')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;

        const folioId = document.getElementById('cobro-folio-id').value;
        const metodoId = document.getElementById('cobro-metodo-id').value;
        const monto = document.getElementById('cobro-monto').value;
        const bancoId = document.getElementById('cobro-cuenta-bancaria-id')?.value || null;
        const sesionCajaId = document.getElementById('cobro-sesion-caja-id')?.value || null;
        const referencia = document.getElementById('cobro-referencia').value;
        const cargoAutoaplica = document.getElementById('cobro-cargo-autoaplica').value;

        if (!metodoId || !monto || parseFloat(monto) <= 0) {
            Swal.fire({ icon: 'warning', title: 'Campos Incompletos', text: 'Seleccione un método e ingrese un monto válido mayor a cero.' });
            return;
        }

        const selMetodo = document.getElementById('cobro-metodo-id');
        const destino = selMetodo.options[selMetodo.selectedIndex]?.dataset.tipoDestino;

        if (destino === 'CAJA_FISICA' && !sesionCajaId) {
            Swal.fire({
                icon: 'error',
                title: 'Caja Cerrada',
                text: 'No es posible cobrar en efectivo sin un turno de caja abierto. Por favor, abra turno de caja primero.'
            });
            return;
        }

        if (destino === 'CUENTA_BANCARIA' && !bancoId) {
            Swal.fire({ icon: 'warning', title: 'Cuenta Bancaria Requerida', text: 'Debe seleccionar la cuenta bancaria de abono.' });
            return;
        }

        const payload = {
            cuenta_folio_id: parseInt(folioId),
            metodo_pago_id: parseInt(metodoId),
            monto_total: monto,
            cuenta_bancaria_id: bancoId ? parseInt(bancoId) : null,
            sesion_caja_id: sesionCajaId ? parseInt(sesionCajaId) : null,
            referencia_operacion: referencia || null,
            cargo_id_autoaplica: cargoAutoaplica ? parseInt(cargoAutoaplica) : null,
            csrf_token: csrfToken
        };

        const btnSubmit = document.getElementById('btn-confirmar-cobro');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Registrando...';

        try {
            const resp = await fetch('/caja/cobros', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error al Registrar Cobro', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-registrar-cobro'))?.hide();

            Swal.fire({
                icon: 'success',
                title: 'Cobro Registrado',
                text: data.mensaje,
                timer: 2000,
                showConfirmButton: false
            });

            // Recargar datos
            await cargarFolios();
            await cargarSesionActiva();
            abrirModalFolioDetalle(folioId);

        } catch (err) {
            console.error('Error al registrar cobro:', err);
            Swal.fire({ icon: 'error', title: 'Error Inesperado', text: 'Falla de conexión al procesar el cobro.' });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-check me-1"></i> Registrar Cobro';
        }
    });

    // =========================================================================
    // Modal Aplicar Pago a Cargo
    // =========================================================================
    function abrirModalAplicarPago(cargoId, cargoCod, pendiente, f) {
        document.getElementById('aplicar-cargo-id').value = cargoId;
        document.getElementById('aplicar-cargo-info').value = `[${cargoCod}] — Saldo Pendiente: S/ ${pendiente}`;

        const selPago = document.getElementById('aplicar-pago-id');
        selPago.innerHTML = '<option value="">Seleccione el pago con saldo disponible...</option>';

        f.pagos.forEach(p => {
            const disp = parseFloat(p.monto_total) - parseFloat(p.monto_aplicado);
            if (disp > 0 && p.estado === 'CONFIRMADO') {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `[${p.codigo}] — Saldo Disponible: S/ ${disp.toFixed(2)}`;
                opt.dataset.disponible = disp.toFixed(2);
                selPago.appendChild(opt);
            }
        });

        // Monto sugerido: el menor entre el saldo pendiente y el primer pago disponible
        document.getElementById('aplicar-monto').value = pendiente;

        const modal = new bootstrap.Modal(document.getElementById('modal-aplicar-pago'));
        modal.show();
    }

    document.getElementById('form-aplicar-pago')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const cargoId = document.getElementById('aplicar-cargo-id').value;
        const pagoId = document.getElementById('aplicar-pago-id').value;
        const monto = document.getElementById('aplicar-monto').value;

        if (!pagoId || !monto || parseFloat(monto) <= 0) {
            Swal.fire({ icon: 'warning', title: 'Campos Requeridos', text: 'Seleccione un pago e ingrese un monto a imputar.' });
            return;
        }

        const payload = {
            cargo_id: parseInt(cargoId),
            pago_id: parseInt(pagoId),
            monto_aplicar: monto,
            csrf_token: csrfToken
        };

        const btn = document.getElementById('btn-confirmar-aplicacion');
        btn.disabled = true;

        try {
            const resp = await fetch('/caja/aplicaciones', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error al Imputar', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-aplicar-pago'))?.hide();

            Swal.fire({ icon: 'success', title: 'Imputado', text: data.mensaje, timer: 1500, showConfirmButton: false });

            await cargarFolios();
            if (folioActualCache) {
                abrirModalFolioDetalle(folioActualCache.folio.id);
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al imputar pago.' });
        } finally {
            btn.disabled = false;
        }
    });

    // =========================================================================
    // Reverso Compensatorio de Pago
    // =========================================================================
    async function solicitarReversoPago(pagoId, pagoCod, folioId) {
        const { value: motivo } = await Swal.fire({
            title: `Reversar Pago [${pagoCod}]`,
            text: 'Esta acción anulará el pago y des-aplicará compensatoriamente todos los cargos a los que hubiese sido imputado. El motivo es obligatorio:',
            input: 'textarea',
            inputPlaceholder: 'Ingrese motivo justificado del reverso...',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, Reversar Pago',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return 'Debe ingresar un motivo para el reverso compensatorio.';
                }
            }
        });

        if (!motivo) return;

        try {
            const resp = await fetch(`/caja/pagos/${pagoId}/reversar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ motivo: motivo.trim(), csrf_token: csrfToken })
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                return;
            }

            Swal.fire({ icon: 'success', title: 'Pago Reversado', text: data.mensaje, timer: 2000, showConfirmButton: false });

            await cargarFolios();
            await cargarSesionActiva();
            abrirModalFolioDetalle(folioId);

        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al reversar el pago.' });
        }
    }

    // =========================================================================
    // Modal Devolución / Reembolso
    // =========================================================================
    function abrirModalDevolucion(pagoId, pagoCod, montoMaximo, folioId) {
        document.getElementById('dev-folio-id').value = folioId;
        document.getElementById('dev-pago-id').value = pagoId;
        document.getElementById('dev-pago-info').value = `[${pagoCod}] — Monto Original: S/ ${parseFloat(montoMaximo).toFixed(2)}`;
        document.getElementById('dev-monto').value = parseFloat(montoMaximo).toFixed(2);
        document.getElementById('dev-motivo').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modal-registrar-devolucion'));
        modal.show();
    }

    document.getElementById('form-registrar-devolucion')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const folioId = document.getElementById('dev-folio-id').value;
        const pagoId = document.getElementById('dev-pago-id').value;
        const metodoId = document.getElementById('dev-metodo-id').value;
        const monto = document.getElementById('dev-monto').value;
        const motivo = document.getElementById('dev-motivo').value;

        if (!metodoId || !monto || parseFloat(monto) <= 0 || !motivo.trim()) {
            Swal.fire({ icon: 'warning', title: 'Campos Requeridos', text: 'Todos los campos son obligatorios.' });
            return;
        }

        const selMetodo = document.getElementById('dev-metodo-id');
        const destino = selMetodo.options[selMetodo.selectedIndex]?.dataset.tipoDestino;
        const sesionCajaId = sesionActivaCache ? sesionActivaCache.id : null;

        if (destino === 'CAJA_FISICA' && !sesionCajaId) {
            Swal.fire({ icon: 'error', title: 'Caja Cerrada', text: 'No puede efectuar egresos en efectivo sin un turno de caja abierto.' });
            return;
        }

        const payload = {
            cuenta_folio_id: parseInt(folioId),
            pago_id: parseInt(pagoId),
            metodo_pago_id: parseInt(metodoId),
            monto_devolucion: monto,
            motivo: motivo.trim(),
            sesion_caja_id: sesionCajaId ? parseInt(sesionCajaId) : null,
            csrf_token: csrfToken
        };

        const btn = document.getElementById('btn-confirmar-devolucion');
        btn.disabled = true;

        try {
            const resp = await fetch('/caja/devoluciones', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error en Devolución', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-registrar-devolucion'))?.hide();

            Swal.fire({ icon: 'success', title: 'Devolución Registrada', text: data.mensaje, timer: 2000, showConfirmButton: false });

            await cargarFolios();
            await cargarSesionActiva();
            abrirModalFolioDetalle(folioId);

        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al procesar la devolución.' });
        } finally {
            btn.disabled = false;
        }
    });

    // =========================================================================
    // Apertura de Turno de Caja
    // =========================================================================
    document.getElementById('form-apertura-caja')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const cajaId = document.getElementById('apertura-caja-id').value;
        const monto = document.getElementById('apertura-monto').value;
        const obs = document.getElementById('apertura-observaciones').value;

        if (!cajaId || parseFloat(monto) < 0) {
            Swal.fire({ icon: 'warning', title: 'Datos Inválidos', text: 'El monto de apertura no puede ser negativo.' });
            return;
        }

        const payload = {
            caja_fisica_id: parseInt(cajaId),
            monto_apertura: monto,
            observaciones: obs ? obs.trim() : null,
            csrf_token: csrfToken
        };

        const btn = document.getElementById('btn-confirmar-apertura');
        btn.disabled = true;

        try {
            const resp = await fetch('/caja/sesion/abrir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error al Aperturar Caja', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-apertura-caja'))?.hide();

            Swal.fire({ icon: 'success', title: 'Caja Abierta', text: data.mensaje, timer: 2000, showConfirmButton: false });

            await cargarSesionActiva();

        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al aperturar turno de caja.' });
        } finally {
            btn.disabled = false;
        }
    });

    // =========================================================================
    // Arqueo y Cierre de Caja
    // =========================================================================
    function abrirModalCierreCaja(s) {
        document.getElementById('cierre-sesion-id').value = s.id;

        const apertura = parseFloat(s.monto_apertura) || 0;
        const ingresos = parseFloat(s.total_ingresos_efectivo) || 0;
        const egresos = parseFloat(s.total_egresos_efectivo) || 0;
        const esperado = apertura + ingresos - egresos;

        document.getElementById('resumen-apertura').textContent = formatearDinero(apertura);
        document.getElementById('resumen-ingresos').textContent = `+${formatearDinero(ingresos)}`;
        document.getElementById('resumen-egresos').textContent = `-${formatearDinero(egresos)}`;
        document.getElementById('resumen-esperado').textContent = formatearDinero(esperado);

        const inputDeclarado = document.getElementById('cierre-monto-declarado');
        inputDeclarado.value = esperado.toFixed(2);

        calcularDiferenciaArqueo(esperado, esperado);

        inputDeclarado.oninput = () => {
            const decl = parseFloat(inputDeclarado.value) || 0;
            calcularDiferenciaArqueo(decl, esperado);
        };

        const modal = new bootstrap.Modal(document.getElementById('modal-cierre-caja'));
        modal.show();
    }

    function calcularDiferenciaArqueo(declarado, esperado) {
        const diff = declarado - esperado;
        const diffAbs = Math.abs(diff).toFixed(2);
        const diffFormatted = (diff >= 0 ? '+' : '-') + formatearDinero(diffAbs);

        const box = document.getElementById('box-resultado-arqueo');
        const txtResultado = document.getElementById('texto-resultado-arqueo');
        const valDiff = document.getElementById('valor-diferencia-arqueo');
        const labelObs = document.getElementById('label-cierre-obs');
        const inputObs = document.getElementById('cierre-observaciones');

        valDiff.textContent = diffFormatted;

        if (Math.abs(diff) < 0.005) {
            txtResultado.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> CAJA CUADRADA</span>';
            valDiff.className = 'mb-0 f-s-16 f-w-700 mt-1 text-success';
            box.style.background = '#e8f5e9';
            labelObs.innerHTML = 'Observaciones (Opcional)';
            inputObs.removeAttribute('required');
        } else if (diff > 0) {
            txtResultado.innerHTML = '<span class="text-info"><i class="fa-solid fa-arrow-trend-up me-1"></i> SOBRANTE EN CAJA</span>';
            valDiff.className = 'mb-0 f-s-16 f-w-700 mt-1 text-info';
            box.style.background = '#e1f5fe';
            labelObs.innerHTML = 'Justificación Obligatoria <span class="text-danger">*</span>';
            inputObs.setAttribute('required', 'required');
        } else {
            txtResultado.innerHTML = '<span class="text-danger"><i class="fa-solid fa-arrow-trend-down me-1"></i> FALTANTE EN CAJA</span>';
            valDiff.className = 'mb-0 f-s-16 f-w-700 mt-1 text-danger';
            box.style.background = '#ffebee';
            labelObs.innerHTML = 'Justificación Obligatoria <span class="text-danger">*</span>';
            inputObs.setAttribute('required', 'required');
        }
    }

    document.getElementById('form-cierre-caja')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const sesionId = document.getElementById('cierre-sesion-id').value;
        const declarado = document.getElementById('cierre-monto-declarado').value;
        const obs = document.getElementById('cierre-observaciones').value;

        if (!sesionId || parseFloat(declarado) < 0) {
            Swal.fire({ icon: 'warning', title: 'Datos Inválidos', text: 'Ingrese el monto contado en gaveta.' });
            return;
        }

        const payload = {
            sesion_id: parseInt(sesionId),
            monto_contado_declarado: declarado,
            observaciones_cierre: obs ? obs.trim() : null,
            csrf_token: csrfToken
        };

        const btn = document.getElementById('btn-confirmar-cierre');
        btn.disabled = true;

        try {
            const resp = await fetch('/caja/sesion/cerrar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error al Cerrar Caja', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-cierre-caja'))?.hide();

            Swal.fire({ icon: 'success', title: 'Turno Cerrado', text: data.mensaje });

            await cargarSesionActiva();

        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al cerrar turno de caja.' });
        } finally {
            btn.disabled = false;
        }
    });

    // =========================================================================
    // Movimiento Manual de Efectivo
    // =========================================================================
    document.getElementById('form-movimiento-caja')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!sesionActivaCache) {
            Swal.fire({ icon: 'error', title: 'Caja Cerrada', text: 'No hay turno abierto para registrar movimientos.' });
            return;
        }

        const tipo = document.getElementById('mov-tipo').value;
        const monto = document.getElementById('mov-monto').value;
        const concepto = document.getElementById('mov-concepto').value;

        if (!tipo || !monto || parseFloat(monto) <= 0 || !concepto.trim()) {
            Swal.fire({ icon: 'warning', title: 'Campos Requeridos', text: 'Ingrese tipo, monto positivo y concepto justificado.' });
            return;
        }

        const payload = {
            sesion_id: sesionActivaCache.id,
            tipo: tipo,
            monto: monto,
            concepto: concepto.trim(),
            csrf_token: csrfToken
        };

        const btn = document.getElementById('btn-guardar-movimiento');
        btn.disabled = true;

        try {
            const resp = await fetch('/caja/movimientos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-movimiento-caja'))?.hide();

            Swal.fire({ icon: 'success', title: 'Registrado', text: data.mensaje, timer: 1500, showConfirmButton: false });

            await cargarSesionActiva();

        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al registrar movimiento de caja.' });
        } finally {
            btn.disabled = false;
        }
    });

    // =========================================================================
    // Aperturar Folio Secundario (FINANCIERO-3C)
    // =========================================================================
    function abrirModalCrearFolioSecundario(folioBaseId) {
        const form = document.getElementById('form-crear-folio-secundario');
        form?.reset();
        document.getElementById('sec-folio-base-id').value = folioBaseId;

        // Listeners para las sugerencias de etiqueta
        document.querySelectorAll('.btn-sugerencia-etiqueta').forEach(btn => {
            btn.onclick = () => {
                const inputEtiqueta = document.getElementById('sec-etiqueta');
                if (inputEtiqueta) inputEtiqueta.value = btn.dataset.etiqueta;
            };
        });

        const modal = new bootstrap.Modal(document.getElementById('modal-crear-folio-secundario'));
        modal.show();
    }

    document.getElementById('form-crear-folio-secundario')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const folioBaseId = document.getElementById('sec-folio-base-id').value;
        const etiqueta = document.getElementById('sec-etiqueta').value.trim();

        if (!etiqueta) {
            Swal.fire({ icon: 'warning', title: 'Campo Requerido', text: 'Ingrese la etiqueta para el nuevo folio secundario.' });
            return;
        }

        const btnSubmit = document.getElementById('btn-confirmar-crear-folio-secundario');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Aperturando...';

        try {
            const resp = await fetch(`/folios/${folioBaseId}/secundarios`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ etiqueta: etiqueta, _csrf_token: csrfToken })
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-crear-folio-secundario'))?.hide();

            Swal.fire({
                icon: 'success',
                title: 'Folio Aperturado',
                text: data.mensaje,
                timer: 2000,
                showConfirmButton: false
            });

            // Asincrónicamente actualizar listado y abrir el nuevo folio secundario
            await cargarFolios();
            abrirModalFolioDetalle(data.datos.folio.id);

        } catch (err) {
            console.error('Error al aperturar folio secundario:', err);
            Swal.fire({ icon: 'error', title: 'Error Inesperado', text: 'Falla al procesar la apertura de folio.' });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-check me-1"></i> Aperturar Folio';
        }
    });

    // =========================================================================
    // Transferencia Total de Cargo (FINANCIERO-3C)
    // =========================================================================
    function abrirModalTransferirCargo(dataset, f) {
        const form = document.getElementById('form-transferir-cargo');
        form?.reset();

        document.getElementById('transf-folio-origen-id').value = f.folio.id;
        document.getElementById('transf-cargo-id').value = dataset.cargoId;
        document.getElementById('transf-cargo-codigo').textContent = dataset.cargoCodigo;
        document.getElementById('transf-cargo-concepto').textContent = dataset.cargoConcepto;
        document.getElementById('transf-cargo-total').textContent = formatearDinero(dataset.cargoTotal);

        const selDestino = document.getElementById('transf-folio-destino-id');
        selDestino.innerHTML = '<option value="">Seleccione el folio de destino...</option>';

        const destinosValidos = foliosRelacionadosCache.filter(rf => parseInt(rf.id) !== parseInt(f.folio.id));
        if (destinosValidos.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Sin Folios de Destino',
                text: 'No existen folios secundarios disponibles para recibir la transferencia. Aperture primero un nuevo folio desde el botón "+ Nuevo Folio".'
            });
            return;
        }

        destinosValidos.forEach(rf => {
            const opt = document.createElement('option');
            opt.value = rf.id;
            const esPrin = parseInt(rf.es_principal) === 1 || rf.es_principal === true;
            opt.textContent = `${rf.codigo} — ${esPrin ? 'PRINCIPAL' : (rf.etiqueta || 'SECUNDARIO')} (ID #${rf.id})`;
            selDestino.appendChild(opt);
        });

        const modal = new bootstrap.Modal(document.getElementById('modal-transferir-cargo'));
        modal.show();
    }

    document.getElementById('form-transferir-cargo')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const folioOrigenId = document.getElementById('transf-folio-origen-id').value;
        const cargoId = document.getElementById('transf-cargo-id').value;
        const folioDestinoId = document.getElementById('transf-folio-destino-id').value;
        const motivo = document.getElementById('transf-motivo').value.trim();

        if (!folioDestinoId || !motivo) {
            Swal.fire({ icon: 'warning', title: 'Campos Requeridos', text: 'Seleccione el folio destino e ingrese el motivo justificado.' });
            return;
        }

        const btnSubmit = document.getElementById('btn-confirmar-transferencia');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Transfiriendo...';

        try {
            const resp = await fetch(`/folios/${folioOrigenId}/transferir-cargo`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    folio_destino_id: parseInt(folioDestinoId),
                    cargo_id: parseInt(cargoId),
                    motivo: motivo,
                    _csrf_token: csrfToken
                })
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: resp.status === 422 ? 'warning' : 'error', title: 'Transferencia Rechazada', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-transferir-cargo'))?.hide();

            Swal.fire({
                icon: 'success',
                title: 'Cargo Transferido',
                text: data.mensaje,
                timer: 2000,
                showConfirmButton: false
            });

            // Asincrónicamente refrescar datos de folios y estado de cuenta actual
            await cargarFolios();
            abrirModalFolioDetalle(folioOrigenId);

        } catch (err) {
            console.error('Error al transferir cargo:', err);
            Swal.fire({ icon: 'error', title: 'Error Inesperado', text: 'Falla al procesar la transferencia del cargo.' });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Transferir Cargo';
        }
    });

    // =========================================================================
    // División Parcial de Cargo (Split) (FINANCIERO-3C)
    // =========================================================================
    function abrirModalSplitCargo(dataset, f) {
        const form = document.getElementById('form-split-cargo');
        form?.reset();

        document.getElementById('split-folio-origen-id').value = f.folio.id;
        document.getElementById('split-cargo-id').value = dataset.cargoId;
        document.getElementById('split-cargo-codigo').textContent = dataset.cargoCodigo;
        document.getElementById('split-cargo-concepto').textContent = dataset.cargoConcepto;
        document.getElementById('split-cargo-total').textContent = formatearDinero(dataset.cargoTotal);

        const selDestino = document.getElementById('split-folio-destino-id');
        selDestino.innerHTML = '<option value="">Seleccione el folio de destino...</option>';

        const destinosValidos = foliosRelacionadosCache.filter(rf => parseInt(rf.id) !== parseInt(f.folio.id));
        if (destinosValidos.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Sin Folios de Destino',
                text: 'No existen folios secundarios disponibles para recibir la división. Aperture primero un nuevo folio desde el botón "+ Nuevo Folio".'
            });
            return;
        }

        destinosValidos.forEach(rf => {
            const opt = document.createElement('option');
            opt.value = rf.id;
            const esPrin = parseInt(rf.es_principal) === 1 || rf.es_principal === true;
            opt.textContent = `${rf.codigo} — ${esPrin ? 'PRINCIPAL' : (rf.etiqueta || 'SECUNDARIO')} (ID #${rf.id})`;
            selDestino.appendChild(opt);
        });

        // Configurar max en split-monto
        const inputMonto = document.getElementById('split-monto');
        inputMonto.value = '';
        inputMonto.setAttribute('max', (parseFloat(dataset.cargoTotal) - 0.01).toFixed(2));

        const modal = new bootstrap.Modal(document.getElementById('modal-split-cargo'));
        modal.show();
    }

    document.getElementById('form-split-cargo')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const folioOrigenId = document.getElementById('split-folio-origen-id').value;
        const cargoId = document.getElementById('split-cargo-id').value;
        const folioDestinoId = document.getElementById('split-folio-destino-id').value;
        const montoSplit = document.getElementById('split-monto').value.trim();
        const motivo = document.getElementById('split-motivo').value.trim();

        if (!folioDestinoId || !montoSplit || parseFloat(montoSplit) <= 0 || !motivo) {
            Swal.fire({ icon: 'warning', title: 'Campos Requeridos', text: 'Seleccione folio destino, ingrese monto válido a dividir y el motivo justificado.' });
            return;
        }

        const btnSubmit = document.getElementById('btn-confirmar-split');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Dividiendo...';

        try {
            const resp = await fetch(`/folios/${folioOrigenId}/split-cargo`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    folio_destino_id: parseInt(folioDestinoId),
                    cargo_id: parseInt(cargoId),
                    monto_split: montoSplit,
                    motivo: motivo,
                    _csrf_token: csrfToken
                })
            });
            const data = await resp.json();

            if (!data.ok) {
                Swal.fire({ icon: resp.status === 422 ? 'warning' : 'error', title: 'División Rechazada', text: data.mensaje });
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('modal-split-cargo'))?.hide();

            Swal.fire({
                icon: 'success',
                title: 'Cargo Dividido Exitosamente',
                text: data.mensaje,
                timer: 2000,
                showConfirmButton: false
            });

            // Asincrónicamente refrescar datos de folios y estado de cuenta actual
            await cargarFolios();
            abrirModalFolioDetalle(folioOrigenId);

        } catch (err) {
            console.error('Error al dividir cargo:', err);
            Swal.fire({ icon: 'error', title: 'Error Inesperado', text: 'Falla al procesar la división del cargo.' });
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-scissors me-1"></i> Confirmar División (Split)';
        }
    });

    // =========================================================================
    // Filtros y Recargas
    // =========================================================================
    document.getElementById('btn-recargar-folios')?.addEventListener('click', cargarFolios);
    document.getElementById('filtro-estado')?.addEventListener('change', cargarFolios);

    let debounceTimer;
    document.getElementById('filtro-q')?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarFolios, 350);
    });

    // Inicialización al cargar la página
    cargarAuxiliares();
    cargarSesionActiva();
    cargarFolios();
});
