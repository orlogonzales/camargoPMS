/**
 * Camargo PMS — Módulo de Abastecimiento, Compras y Cuentas por Pagar (COMPRAS-1 / D-080)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API y SweetAlert2.
 * Principios vinculantes:
 * - SOLICITUD != ORDEN != RECEPCION/CONFORMIDAD != COMPROBANTE != CUENTA POR PAGAR != PAGO.
 * - ORDEN != MOVIMIENTO DE INVENTARIO != GASTO != PAGO.
 * - Líneas fuertemente tipadas: BIEN (Kardex) vs SERVICIO (Cero Kardex).
 * - Moneda funcional en PEN.
 * - 3-Way Matching: CONFORME, CON_DIFERENCIA, OBSERVADO.
 * - D-071: Badges suaves bg-light-*, Font Awesome 6.3.0 exclusivo, cero degradados.
 * - Geometría Alina nativa (border-radius 20px, app-form).
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Modales Bootstrap
    const modalCrearOrdenEl = document.getElementById('modal-crear-orden');
    const modalCrearSolEl = document.getElementById('modal-crear-solicitud');
    const modalRecEl = document.getElementById('modal-registrar-recepcion');
    const modalCompEl = document.getElementById('modal-registrar-comprobante');
    const modalPagoEl = document.getElementById('modal-registrar-pago');

    const modalCrearOrden = modalCrearOrdenEl ? new bootstrap.Modal(modalCrearOrdenEl) : null;
    const modalCrearSol = modalCrearSolEl ? new bootstrap.Modal(modalCrearSolEl) : null;
    const modalRec = modalRecEl ? new bootstrap.Modal(modalRecEl) : null;
    const modalComp = modalCompEl ? new bootstrap.Modal(modalCompEl) : null;
    const modalPago = modalPagoEl ? new bootstrap.Modal(modalPagoEl) : null;

    // Cache local de datos
    let catalogos = {
        proveedores: [],
        almacenes: [],
        articulos: [],
        sesiones_caja: [],
    };

    let ordenesCache = [];
    let solicitudesCache = [];
    let recepcionesCache = [];
    let comprobantesCache = [];
    let cxpCache = [];

    // =========================================================================
    // Utilidades y Helpers
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

    function formatearNumero(val, decimals = 2) {
        const num = parseFloat(val);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('es-PE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function formatearFecha(isoString) {
        if (!isoString) return '—';
        const d = new Date(isoString.replace(' ', 'T'));
        if (isNaN(d.getTime())) return isoString;
        return d.toLocaleDateString('es-PE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function badgeEstadoComercial(estado) {
        switch (estado) {
            case 'BORRADOR':
                return '<span class="badge bg-light-secondary text-secondary f-w-600"><i class="fa-solid fa-pen-ruler me-1"></i> Borrador</span>';
            case 'APROBADA':
                return '<span class="badge bg-light-primary text-primary f-w-600"><i class="fa-solid fa-check-double me-1"></i> Aprobada</span>';
            case 'CERRADA':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-lock me-1"></i> Cerrada</span>';
            case 'CANCELADA':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-ban me-1"></i> Cancelada</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoRecepcion(estado) {
        switch (estado) {
            case 'SIN_RECEPCION':
                return '<span class="badge bg-light-secondary text-secondary f-w-600">Sin recepción</span>';
            case 'RECEPCION_PARCIAL':
                return '<span class="badge bg-light-warning text-warning f-w-600">Parcial</span>';
            case 'RECEPCION_TOTAL':
                return '<span class="badge bg-light-success text-success f-w-600">Total</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoFacturacion(estado) {
        switch (estado) {
            case 'SIN_FACTURAR':
                return '<span class="badge bg-light-secondary text-secondary f-w-600">Sin facturar</span>';
            case 'FACTURADA_PARCIAL':
                return '<span class="badge bg-light-warning text-warning f-w-600">Fact. Parcial</span>';
            case 'FACTURADA_TOTAL':
                return '<span class="badge bg-light-info text-info f-w-600">Facturada</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoPago(estado) {
        switch (estado) {
            case 'PENDIENTE':
                return '<span class="badge bg-light-danger text-danger f-w-600">Pendiente</span>';
            case 'PAGADO_PARCIAL':
                return '<span class="badge bg-light-warning text-warning f-w-600">Pagado Parcial</span>';
            case 'PAGADO_TOTAL':
                return '<span class="badge bg-light-success text-success f-w-600">Pagado Total</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeMatching(estado) {
        switch (estado) {
            case 'CONFORME':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-circle-check me-1"></i> Conforme</span>';
            case 'CON_DIFERENCIA':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-triangle-exclamation me-1"></i> Con Diferencia</span>';
            case 'OBSERVADO':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-circle-xmark me-1"></i> Observado</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoCxp(estado) {
        switch (estado) {
            case 'PENDIENTE':
                return '<span class="badge bg-light-danger text-danger f-w-600">Pendiente</span>';
            case 'AMORTIZADA_PARCIAL':
                return '<span class="badge bg-light-warning text-warning f-w-600">Amortizada Parcial</span>';
            case 'LIQUIDADA':
                return '<span class="badge bg-light-success text-success f-w-600">Liquidada</span>';
            case 'ANULADA':
                return '<span class="badge bg-light-secondary text-secondary f-w-600">Anulada</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    // =========================================================================
    // 1. CARGA DE CATÁLOGOS
    // =========================================================================

    async function cargarCatalogos() {
        try {
            const resp = await fetch('/api/compras/catalogos', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (data.exito) {
                catalogos = data;
                poblarSelectoresCatalogos();
            }
        } catch (e) {
            console.error('Error al cargar catálogos de compras:', e);
        }
    }

    function poblarSelectoresCatalogos() {
        // Proveedores en modal orden
        const selectProv = document.getElementById('oc-proveedor-id');
        if (selectProv) {
            selectProv.innerHTML = '<option value="">Seleccione proveedor...</option>';
            catalogos.proveedores.forEach(p => {
                selectProv.innerHTML += `<option value="${p.id}">${escaparHtml(p.razon_social)} (${escaparHtml(p.numero_documento)})</option>`;
            });
        }

        // Almacenes en modal orden y recepción
        const selectAlmOc = document.getElementById('oc-almacen-id');
        const selectAlmRec = document.getElementById('rec-almacen-id');
        if (selectAlmOc) {
            selectAlmOc.innerHTML = '<option value="">Seleccione almacén...</option>';
            catalogos.almacenes.forEach(a => {
                selectAlmOc.innerHTML += `<option value="${a.id}">${escaparHtml(a.nombre)}</option>`;
            });
        }
        if (selectAlmRec) {
            selectAlmRec.innerHTML = '<option value="">Seleccione almacén destino...</option>';
            catalogos.almacenes.forEach(a => {
                selectAlmRec.innerHTML += `<option value="${a.id}">${escaparHtml(a.nombre)}</option>`;
            });
        }

        // Sesiones de caja en modal pago
        const selectCaja = document.getElementById('pago-sesion-caja-id');
        if (selectCaja) {
            selectCaja.innerHTML = '<option value="">Seleccione turno de caja...</option>';
            catalogos.sesiones_caja.forEach(s => {
                selectCaja.innerHTML += `<option value="${s.id}">${escaparHtml(s.caja_nombre)} (${escaparHtml(s.codigo)})</option>`;
            });
        }
    }

    // =========================================================================
    // 2. ÓRDENES DE COMPRA
    // =========================================================================

    async function cargarOrdenes() {
        const tbody = document.querySelector('#tabla-ordenes tbody');
        if (!tbody) return;

        try {
            const resp = await fetch('/api/compras/ordenes', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Error al consultar órdenes de compra.');
            const data = await resp.json();
            ordenesCache = data.datos || [];
            renderizarOrdenes();
            actualizarSelectoresOrdenes();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> ${escaparHtml(e.message)}</td></tr>`;
        }
    }

    function renderizarOrdenes() {
        const tbody = document.querySelector('#tabla-ordenes tbody');
        if (!tbody) return;

        const termino = (document.getElementById('filtro-oc-termino')?.value || '').toLowerCase().trim();
        const estComercial = document.getElementById('filtro-oc-comercial')?.value || '';
        const estRecepcion = document.getElementById('filtro-oc-recepcion')?.value || '';
        const estPago = document.getElementById('filtro-oc-pago')?.value || '';

        const filtradas = ordenesCache.filter(o => {
            const matchTerm = !termino || (o.codigo && o.codigo.toLowerCase().includes(termino));
            const matchCom = !estComercial || o.estado_comercial === estComercial;
            const matchRec = !estRecepcion || o.estado_recepcion === estRecepcion;
            const matchPago = !estPago || o.estado_pago === estPago;
            return matchTerm && matchCom && matchRec && matchPago;
        });

        if (filtradas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No se encontraron órdenes de compra registradas.</td></tr>';
            return;
        }

        tbody.innerHTML = filtradas.map(o => {
            const esBorrador = o.estado_comercial === 'BORRADOR';
            const esAprobada = o.estado_comercial === 'APROBADA';

            return `
                <tr>
                    <td><strong class="text-primary">${escaparHtml(o.codigo)}</strong></td>
                    <td>${escaparHtml(o.proveedor_razon_social || 'Proveedor #' + o.proveedor_id)}</td>
                    <td>${formatearFecha(o.creado_en)}</td>
                    <td class="text-end f-w-700">S/ ${formatearNumero(o.total)}</td>
                    <td class="text-center">${badgeEstadoComercial(o.estado_comercial)}</td>
                    <td class="text-center">${badgeEstadoRecepcion(o.estado_recepcion)}</td>
                    <td class="text-center">${badgeEstadoFacturacion(o.estado_facturacion)}</td>
                    <td class="text-center">${badgeEstadoPago(o.estado_pago)}</td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm dropdown-toggle py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                ${esBorrador ? `
                                <li><a class="dropdown-item btn-aprobar-oc" href="javascript:void(0)" data-id="${o.id}"><i class="fa-solid fa-check text-success me-2"></i> Aprobar OC</a></li>
                                ` : ''}
                                ${esAprobada ? `
                                <li><a class="dropdown-item" href="/api/compras/ordenes/${o.id}/pdf" target="_blank"><i class="fa-solid fa-file-pdf text-danger me-2"></i> Ver PDF Oficial</a></li>
                                <li><a class="dropdown-item btn-recibir-oc" href="javascript:void(0)" data-id="${o.id}"><i class="fa-solid fa-dolly text-primary me-2"></i> Registrar Recepción</a></li>
                                <li><a class="dropdown-item btn-comprobante-oc" href="javascript:void(0)" data-id="${o.id}"><i class="fa-solid fa-receipt text-success me-2"></i> Registrar Factura</a></li>
                                ` : ''}
                                ${(esBorrador || (esAprobada && o.estado_recepcion === 'SIN_RECEPCION' && o.estado_pago === 'PENDIENTE')) ? `
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger btn-cancelar-oc" href="javascript:void(0)" data-id="${o.id}"><i class="fa-solid fa-ban me-2"></i> Cancelar Orden</a></li>
                                ` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function actualizarSelectoresOrdenes() {
        const selectRec = document.getElementById('rec-orden-id');
        const selectComp = document.getElementById('comp-orden-id');

        const ordenesAprobadas = ordenesCache.filter(o => o.estado_comercial === 'APROBADA' || o.estado_comercial === 'CERRADA');

        if (selectRec) {
            selectRec.innerHTML = '<option value="">Seleccione OC aprobada...</option>' +
                ordenesAprobadas.filter(o => o.estado_recepcion !== 'RECEPCION_TOTAL').map(o => `
                    <option value="${o.id}">${escaparHtml(o.codigo)} — Total S/ ${formatearNumero(o.total)}</option>
                `).join('');
        }

        if (selectComp) {
            selectComp.innerHTML = '<option value="">Seleccione OC...</option>' +
                ordenesAprobadas.map(o => `
                    <option value="${o.id}">${escaparHtml(o.codigo)} — Total S/ ${formatearNumero(o.total)}</option>
                `).join('');
        }
    }

    // =========================================================================
    // 3. DINÁMICA DE LÍNEAS DE ORDEN (BIEN vs SERVICIO)
    // =========================================================================

    const btnAgregarBien = document.getElementById('btn-agregar-linea-bien');
    const btnAgregarServicio = document.getElementById('btn-agregar-linea-servicio');
    const contenedorLineasOrden = document.getElementById('contenedor-lineas-orden');

    if (btnAgregarBien) {
        btnAgregarBien.addEventListener('click', () => agregarFilaOrden('BIEN'));
    }

    if (btnAgregarServicio) {
        btnAgregarServicio.addEventListener('click', () => agregarFilaOrden('SERVICIO'));
    }

    function agregarFilaOrden(tipo) {
        if (!contenedorLineasOrden) return;

        const tr = document.createElement('tr');
        tr.dataset.tipo = tipo;

        let colIdentificador = '';
        if (tipo === 'BIEN') {
            const opcionesArt = catalogos.articulos.map(a =>
                `<option value="${a.id}" data-costo="${a.costo_referencial}">${escaparHtml(a.nombre)} (${escaparHtml(a.codigo_sku)})</option>`
            ).join('');
            colIdentificador = `
                <select class="form-select form-select-sm input-articulo-id" required>
                    <option value="">Seleccione artículo...</option>
                    ${opcionesArt}
                </select>
            `;
        } else {
            colIdentificador = `
                <input type="text" class="form-control form-control-sm input-desc-servicio" required placeholder="Descripción técnica del servicio contratado...">
            `;
        }

        tr.innerHTML = `
            <td>
                <span class="badge ${tipo === 'BIEN' ? 'bg-light-primary text-primary' : 'bg-light-info text-info'} f-w-600">
                    ${tipo}
                </span>
            </td>
            <td>${colIdentificador}</td>
            <td><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-end input-cantidad" value="1.0000" required></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end input-precio" value="0.00" required></td>
            <td class="text-end f-w-600 col-subtotal">S/ 0.00</td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 btn-eliminar-linea">
                    <i class="fa-solid fa-trash f-s-12"></i>
                </button>
            </td>
        `;

        contenedorLineasOrden.appendChild(tr);

        // Event listeners para cálculo de subtotales
        const inputCant = tr.querySelector('.input-cantidad');
        const inputPrecio = tr.querySelector('.input-precio');
        const selectArt = tr.querySelector('.input-articulo-id');

        if (selectArt) {
            selectArt.addEventListener('change', () => {
                const opt = selectArt.selectedOptions[0];
                if (opt && opt.dataset.costo) {
                    inputPrecio.value = parseFloat(opt.dataset.costo).toFixed(2);
                    calcularSubtotalesOrden();
                }
            });
        }

        inputCant.addEventListener('input', calcularSubtotalesOrden);
        inputPrecio.addEventListener('input', calcularSubtotalesOrden);

        tr.querySelector('.btn-eliminar-linea').addEventListener('click', () => {
            tr.remove();
            calcularSubtotalesOrden();
        });

        calcularSubtotalesOrden();
    }

    function calcularSubtotalesOrden() {
        if (!contenedorLineasOrden) return;
        let totalAcum = 0;

        contenedorLineasOrden.querySelectorAll('tr').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.input-cantidad')?.value || '0');
            const precio = parseFloat(tr.querySelector('.input-precio')?.value || '0');
            const subtotal = cant * precio;
            totalAcum += subtotal;

            const colSub = tr.querySelector('.col-subtotal');
            if (colSub) {
                colSub.textContent = `S/ ${formatearNumero(subtotal)}`;
            }
        });

        const resumenEl = document.getElementById('oc-resumen-total');
        if (resumenEl) {
            resumenEl.textContent = `S/ ${formatearNumero(totalAcum)}`;
        }
    }

    // =========================================================================
    // 4. SUBMIT FORMULAR ORDEN
    // =========================================================================

    const formCrearOrden = document.getElementById('form-crear-orden');
    if (formCrearOrden) {
        formCrearOrden.addEventListener('submit', async (e) => {
            e.preventDefault();

            const proveedorId = document.getElementById('oc-proveedor-id')?.value;
            if (!proveedorId) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar un proveedor.' });
                return;
            }

            const filas = contenedorLineasOrden?.querySelectorAll('tr') || [];
            if (filas.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe agregar al menos una línea a la orden.' });
                return;
            }

            const lineas = [];
            for (const tr of filas) {
                const tipo = tr.dataset.tipo;
                const cant = tr.querySelector('.input-cantidad')?.value;
                const precio = tr.querySelector('.input-precio')?.value;

                if (tipo === 'BIEN') {
                    const artId = tr.querySelector('.input-articulo-id')?.value;
                    if (!artId) {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione un artículo para todas las líneas de bien.' });
                        return;
                    }
                    lineas.push({
                        tipo_linea: 'BIEN',
                        articulo_id: parseInt(artId, 10),
                        cantidad_pactada: cant,
                        precio_unitario: precio,
                    });
                } else {
                    const desc = tr.querySelector('.input-desc-servicio')?.value.trim();
                    if (!desc) {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingrese la descripción para todas las líneas de servicio.' });
                        return;
                    }
                    lineas.push({
                        tipo_linea: 'SERVICIO',
                        descripcion_servicio: desc,
                        cantidad_pactada: cant,
                        precio_unitario: precio,
                    });
                }
            }

            const payload = {
                proveedor_id: parseInt(proveedorId, 10),
                almacen_entrega_id: document.getElementById('oc-almacen-id')?.value || null,
                condicion_pago: document.getElementById('oc-condicion-pago')?.value || 'CONTADO',
                fecha_entrega_esperada: document.getElementById('oc-fecha-entrega')?.value || null,
                notas_comerciales: document.getElementById('oc-notas')?.value || null,
                lineas: lineas,
                csrf_token: csrfToken,
            };

            const btnSubmit = document.getElementById('btn-guardar-orden');
            if (btnSubmit) btnSubmit.disabled = true;

            try {
                const resp = await fetch('/api/compras/ordenes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await resp.json();
                if (!resp.ok || !data.exito) {
                    throw new Error(data.error || 'Error al formular orden.');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Orden Formulada',
                    text: data.mensaje,
                    timer: 2000,
                    showConfirmButton: false,
                });

                modalCrearOrden?.hide();
                formCrearOrden.reset();
                if (contenedorLineasOrden) contenedorLineasOrden.innerHTML = '';
                await cargarOrdenes();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            } finally {
                if (btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    // =========================================================================
    // 5. ACCIONES SOBRE ÓRDENES (APROBAR / CANCELAR)
    // =========================================================================

    document.addEventListener('click', async (e) => {
        // APROBAR ORDEN
        const btnAprobar = e.target.closest('.btn-aprobar-oc');
        if (btnAprobar) {
            const ordenId = btnAprobar.dataset.id;
            const res = await Swal.fire({
                title: '¿Aprobar Orden de Compra?',
                text: 'Esta acción congelará las condiciones comerciales pactadas y permitirá registrar recepciones y facturas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, aprobar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
            });

            if (res.isConfirmed) {
                try {
                    const resp = await fetch(`/api/compras/ordenes/${ordenId}/aprobar`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ csrf_token: csrfToken }),
                    });
                    const data = await resp.json();
                    if (!resp.ok || !data.exito) throw new Error(data.error || 'Error al aprobar orden.');

                    Swal.fire({ icon: 'success', title: 'Aprobada', text: data.mensaje, timer: 1800 });
                    await cargarOrdenes();
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            }
            return;
        }

        // CANCELAR ORDEN
        const btnCancelar = e.target.closest('.btn-cancelar-oc');
        if (btnCancelar) {
            const ordenId = btnCancelar.dataset.id;
            const resPrompt = await Swal.fire({
                title: 'Cancelar Orden de Compra',
                text: 'Ingrese el motivo formal de cancelación:',
                input: 'text',
                inputPlaceholder: 'Ej: Proveedor no dispone de stock / Desestimado por gerencia',
                showCancelButton: true,
                confirmButtonText: 'Confirmar Cancelación',
                cancelButtonText: 'Volver',
                confirmButtonColor: '#dc3545',
                inputValidator: (v) => !v && 'Debe indicar un motivo de cancelación obligatorio',
            });

            if (resPrompt.isConfirmed && resPrompt.value) {
                try {
                    const resp = await fetch(`/api/compras/ordenes/${ordenId}/cancelar`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ motivo: resPrompt.value, csrf_token: csrfToken }),
                    });
                    const data = await resp.json();
                    if (!resp.ok || !data.exito) throw new Error(data.error || 'Error al cancelar orden.');

                    Swal.fire({ icon: 'success', title: 'Cancelada', text: data.mensaje, timer: 1800 });
                    await cargarOrdenes();
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            }
            return;
        }

        // ACCIÓN RÁPIDA: ABRIR MODAL RECEPCIÓN DESDE FILA
        const btnRecibir = e.target.closest('.btn-recibir-oc');
        if (btnRecibir) {
            const ordenId = btnRecibir.dataset.id;
            const selectRec = document.getElementById('rec-orden-id');
            if (selectRec) {
                selectRec.value = ordenId;
                selectRec.dispatchEvent(new Event('change'));
            }
            modalRec?.show();
            return;
        }

        // ACCIÓN RÁPIDA: ABRIR MODAL COMPROBANTE DESDE FILA
        const btnComp = e.target.closest('.btn-comprobante-oc');
        if (btnComp) {
            const ordenId = btnComp.dataset.id;
            const selectComp = document.getElementById('comp-orden-id');
            if (selectComp) {
                selectComp.value = ordenId;
                selectComp.dispatchEvent(new Event('change'));
            }
            modalComp?.show();
            return;
        }

        // ACCIÓN: ABRIR PAGO DE CXP
        const btnPagarCxp = e.target.closest('.btn-pagar-cxp');
        if (btnPagarCxp) {
            const cxpId = btnPagarCxp.dataset.id;
            const cxpCodigo = btnPagarCxp.dataset.codigo;
            const cxpSaldo = btnPagarCxp.dataset.saldo;

            document.getElementById('pago-cxp-id').value = cxpId;
            document.getElementById('pago-cxp-codigo').textContent = cxpCodigo;
            document.getElementById('pago-cxp-saldo').textContent = `S/ ${formatearNumero(cxpSaldo)}`;
            document.getElementById('pago-monto').value = parseFloat(cxpSaldo).toFixed(2);
            document.getElementById('pago-monto').max = cxpSaldo;

            modalPago?.show();
            return;
        }
    });

    // =========================================================================
    // 6. DINÁMICA DE RECEPCIONES FÍSICAS (KARDEX)
    // =========================================================================

    const selectRecOrden = document.getElementById('rec-orden-id');
    const contenedorLineasRec = document.getElementById('contenedor-lineas-recepcion');

    if (selectRecOrden) {
        selectRecOrden.addEventListener('change', async () => {
            const ordenId = selectRecOrden.value;
            if (!ordenId || !contenedorLineasRec) {
                if (contenedorLineasRec) contenedorLineasRec.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Seleccione una orden para cargar sus bienes pendientes...</td></tr>';
                return;
            }

            try {
                const resp = await fetch(`/api/compras/ordenes/${ordenId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!resp.ok) throw new Error('No se pudo consultar el detalle de la orden.');
                const data = await resp.json();
                const lineas = data.datos?.lineas || [];

                const bienes = lineas.filter(l => l.tipo_linea === 'BIEN');
                if (bienes.length === 0) {
                    contenedorLineasRec.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-warning">Esta orden no contiene líneas de bienes inventariables.</td></tr>';
                    return;
                }

                contenedorLineasRec.innerHTML = bienes.map((l, idx) => {
                    const pactada = parseFloat(l.cantidad_pactada || '0');
                    const aceptada = parseFloat(l.cantidad_aceptada || '0');
                    const pendiente = Math.max(0, pactada - aceptada);

                    return `
                        <tr data-linea-id="${l.id}">
                            <td>
                                <strong>${escaparHtml(l.articulo_nombre || 'Artículo #' + l.articulo_id)}</strong>
                                <br><small class="text-muted">SKU: ${escaparHtml(l.codigo_sku || '—')}</small>
                            </td>
                            <td class="text-end f-w-600">${formatearNumero(pendiente, 4)}</td>
                            <td>
                                <input type="number" step="0.0001" min="0" max="${pendiente}" class="form-control form-control-sm text-end input-rec-aceptada" value="${pendiente > 0 ? pendiente.toFixed(4) : '0.0000'}">
                            </td>
                            <td>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end input-rec-rechazada" value="0.0000">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm input-rec-motivo" placeholder="Obligatorio si hay rechazo">
                            </td>
                        </tr>
                    `;
                }).join('');
            } catch (err) {
                contenedorLineasRec.innerHTML = `<tr><td colspan="5" class="text-center py-3 text-danger">${escaparHtml(err.message)}</td></tr>`;
            }
        });
    }

    const formRec = document.getElementById('form-registrar-recepcion');
    if (formRec) {
        formRec.addEventListener('submit', async (e) => {
            e.preventDefault();

            const ordenId = selectRecOrden?.value;
            const almacenId = document.getElementById('rec-almacen-id')?.value;

            if (!ordenId || !almacenId) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar orden y almacén destino.' });
                return;
            }

            const filas = contenedorLineasRec?.querySelectorAll('tr[data-linea-id]') || [];
            const lineas = [];

            for (const tr of filas) {
                const lineaId = parseInt(tr.dataset.lineaId, 10);
                const aceptada = tr.querySelector('.input-rec-aceptada')?.value || '0';
                const rechazada = tr.querySelector('.input-rec-rechazada')?.value || '0';
                const motivo = tr.querySelector('.input-rec-motivo')?.value.trim();

                if (parseFloat(rechazada) > 0 && !motivo) {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe ingresar el motivo formal si registra unidades rechazadas.' });
                    return;
                }

                if (parseFloat(aceptada) > 0 || parseFloat(rechazada) > 0) {
                    lineas.push({
                        orden_linea_id: lineaId,
                        cantidad_aceptada: aceptada,
                        cantidad_rechazada: rechazada,
                        motivo_rechazo: motivo || null,
                    });
                }
            }

            if (lineas.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe ingresar al menos una cantidad a recibir.' });
                return;
            }

            const payload = {
                orden_compra_id: parseInt(ordenId, 10),
                almacen_id: parseInt(almacenId, 10),
                numero_guia_remision: formRec.querySelector('[name="numero_guia_remision"]')?.value || null,
                observaciones: formRec.querySelector('[name="observaciones"]')?.value || null,
                lineas: lineas,
                csrf_token: csrfToken,
            };

            try {
                const resp = await fetch('/api/compras/recepciones', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (!resp.ok || !data.exito) throw new Error(data.error || 'Error al registrar recepción física.');

                Swal.fire({ icon: 'success', title: 'Recepción Registrada', text: data.mensaje });
                modalRec?.hide();
                formRec.reset();
                await cargarOrdenes();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    // =========================================================================
    // 7. COMPROBANTES Y 3-WAY MATCHING
    // =========================================================================

    const selectCompOrden = document.getElementById('comp-orden-id');
    if (selectCompOrden) {
        selectCompOrden.addEventListener('change', () => {
            const ordenId = selectCompOrden.value;
            const orden = ordenesCache.find(o => o.id == ordenId);
            if (orden) {
                document.getElementById('comp-subtotal').value = parseFloat(orden.subtotal || '0').toFixed(2);
                document.getElementById('comp-impuesto').value = parseFloat(orden.impuesto_total || '0').toFixed(2);
                document.getElementById('comp-total').value = parseFloat(orden.total || '0').toFixed(2);
            }
        });
    }

    const formComp = document.getElementById('form-registrar-comprobante');
    if (formComp) {
        formComp.addEventListener('submit', async (e) => {
            e.preventDefault();

            const ordenId = selectCompOrden?.value;
            if (!ordenId) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe asociar la factura a una orden de compra.' });
                return;
            }

            const payload = {
                orden_compra_id: parseInt(ordenId, 10),
                tipo_comprobante: document.getElementById('comp-tipo')?.value,
                serie: formComp.querySelector('[name="serie"]')?.value,
                numero: formComp.querySelector('[name="numero"]')?.value,
                fecha_emision: formComp.querySelector('[name="fecha_emision"]')?.value,
                fecha_vencimiento: formComp.querySelector('[name="fecha_vencimiento"]')?.value,
                subtotal: document.getElementById('comp-subtotal')?.value,
                impuesto: document.getElementById('comp-impuesto')?.value,
                total: document.getElementById('comp-total')?.value,
                csrf_token: csrfToken,
            };

            try {
                const resp = await fetch('/api/compras/comprobantes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (!resp.ok || !data.exito) throw new Error(data.error || 'Error al registrar comprobante fiscal.');

                Swal.fire({
                    icon: 'success',
                    title: '3-Way Matching Completado',
                    text: data.mensaje,
                });

                modalComp?.hide();
                formComp.reset();
                await cargarOrdenes();
                await cargarCuentasPorPagar();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error Fiscal / Matching', text: err.message });
            }
        });
    }

    // =========================================================================
    // 8. CUENTAS POR PAGAR & PAGOS (FINANCIERO-2)
    // =========================================================================

    async function cargarCuentasPorPagar() {
        const tbody = document.querySelector('#tabla-cxp tbody');
        if (!tbody) return;

        try {
            const resp = await fetch('/api/compras/cuentas-por-pagar', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Error al consultar cuentas por pagar.');
            const data = await resp.json();
            cxpCache = data.datos || [];
            renderizarCuentasPorPagar();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${escaparHtml(e.message)}</td></tr>`;
        }
    }

    function renderizarCuentasPorPagar() {
        const tbody = document.querySelector('#tabla-cxp tbody');
        if (!tbody) return;

        const termino = (document.getElementById('filtro-cxp-termino')?.value || '').toLowerCase().trim();
        const estado = document.getElementById('filtro-cxp-estado')?.value || '';

        const filtradas = cxpCache.filter(c => {
            const matchTerm = !termino || (c.codigo && c.codigo.toLowerCase().includes(termino));
            const matchEst = !estado || c.estado === estado;
            return matchTerm && matchEst;
        });

        if (filtradas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No existen cuentas por pagar registradas.</td></tr>';
            return;
        }

        tbody.innerHTML = filtradas.map(c => {
            const saldoNum = parseFloat(c.saldo_pendiente || '0');
            const puedePagar = saldoNum > 0 && c.estado !== 'LIQUIDADA' && c.estado !== 'ANULADA';

            return `
                <tr>
                    <td><strong class="text-danger">${escaparHtml(c.codigo)}</strong></td>
                    <td>Proveedor #${c.proveedor_id}</td>
                    <td>${formatearFecha(c.fecha_vencimiento)}</td>
                    <td class="text-end f-w-600">S/ ${formatearNumero(c.monto_total)}</td>
                    <td class="text-end text-success">S/ ${formatearNumero(c.monto_amortizado)}</td>
                    <td class="text-end text-danger f-w-700">S/ ${formatearNumero(c.saldo_pendiente)}</td>
                    <td class="text-center">${badgeEstadoCxp(c.estado)}</td>
                    <td class="text-center">
                        ${puedePagar ? `
                        <button type="button" class="btn btn-outline-danger btn-sm py-1 btn-pagar-cxp" data-id="${c.id}" data-codigo="${escaparHtml(c.codigo)}" data-saldo="${c.saldo_pendiente}">
                            <i class="fa-solid fa-money-bill-transfer me-1"></i> Pagar
                        </button>
                        ` : '<span class="text-muted f-s-12">Completado</span>'}
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Manejo de visibilidad de caja chica según medio de pago
    const selectMedioPago = document.getElementById('pago-medio');
    const bloqueCaja = document.getElementById('bloque-pago-sesion-caja');
    const bloqueBanco = document.getElementById('bloque-pago-op-bancaria');

    if (selectMedioPago) {
        selectMedioPago.addEventListener('change', () => {
            if (selectMedioPago.value === 'EFECTIVO_CAJA') {
                if (bloqueCaja) bloqueCaja.style.display = 'block';
                if (bloqueBanco) bloqueBanco.style.display = 'none';
            } else {
                if (bloqueCaja) bloqueCaja.style.display = 'none';
                if (bloqueBanco) bloqueBanco.style.display = 'block';
            }
        });
    }

    const formPago = document.getElementById('form-registrar-pago');
    if (formPago) {
        formPago.addEventListener('submit', async (e) => {
            e.preventDefault();

            const cxpId = document.getElementById('pago-cxp-id')?.value;
            const monto = document.getElementById('pago-monto')?.value;

            if (!cxpId || !monto || parseFloat(monto) <= 0) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingrese un monto válido a pagar.' });
                return;
            }

            const payload = {
                cuenta_pagar_id: parseInt(cxpId, 10),
                medio_pago: selectMedioPago?.value || 'TRANSFERENCIA_BANCARIA',
                monto: monto,
                sesion_caja_id: document.getElementById('pago-sesion-caja-id')?.value || null,
                numero_operacion_bancaria: formPago.querySelector('[name="numero_operacion_bancaria"]')?.value || null,
                notas: formPago.querySelector('[name="notas"]')?.value || null,
                csrf_token: csrfToken,
            };

            try {
                const resp = await fetch(`/api/compras/cuentas-por-pagar/${cxpId}/pagos`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (!resp.ok || !data.exito) throw new Error(data.error || 'Error al aplicar pago.');

                Swal.fire({ icon: 'success', title: 'Pago Aplicado', text: data.mensaje });
                modalPago?.hide();
                formPago.reset();
                await cargarCuentasPorPagar();
                await cargarOrdenes();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        });
    }

    // =========================================================================
    // 9. INICIALIZACIÓN
    // =========================================================================

    // Botones para abrir modales
    document.getElementById('btn-abrir-modal-orden')?.addEventListener('click', () => {
        modalCrearOrden?.show();
    });

    document.getElementById('btn-abrir-modal-solicitud')?.addEventListener('click', () => {
        modalCrearSol?.show();
    });

    document.getElementById('btn-abrir-modal-recepcion')?.addEventListener('click', () => {
        modalRec?.show();
    });

    document.getElementById('btn-abrir-modal-comprobante')?.addEventListener('click', () => {
        modalComp?.show();
    });

    document.getElementById('btn-recargar-ordenes')?.addEventListener('click', cargarOrdenes);
    document.getElementById('btn-recargar-cxp')?.addEventListener('click', cargarCuentasPorPagar);

    // Carga inicial
    cargarCatalogos();
    cargarOrdenes();
    cargarCuentasPorPagar();
});
