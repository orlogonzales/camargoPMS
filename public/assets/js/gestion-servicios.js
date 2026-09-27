/**
 * Camargo PMS — Módulo de Gestión de Servicios, Proveedores, Consumos y Traslados (SERVICIOS-1)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO ≠ SERVICIO ≠ PROVEEDOR.
 * - Snapshots inmutables de catálogo al contratar (D-010 / D-069).
 * - Cero DELETE físico. Cancelación con motivo justificado; prohibida en EJECUTADO.
 * - D-071: Font Awesome 6.3.0 exclusivo, Flatpickr Date Picker, Variants of badge & chip Alina.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Cache local de datos maestros
    let auxiliares = {
        categorias: [],
        modalidades: [],
        proveedores: [],
        servicios: [],
        propiedades: [],
        reservas: []
    };

    let contratadosCache = [];
    let catalogoCache = [];
    let proveedoresCache = [];

    // =========================================================================
    // Inicialización de Flatpickr
    // =========================================================================
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#filtro-contratados-fecha-desde', { dateFormat: 'Y-m-d', allowInput: true });
        flatpickr('#filtro-contratados-fecha-hasta', { dateFormat: 'Y-m-d', allowInput: true });
        flatpickr('#contratar-fecha-servicio', { dateFormat: 'Y-m-d', defaultDate: 'today', allowInput: true });
        flatpickr('#traslado-fecha-hora', { enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true, allowInput: true });
    }

    // =========================================================================
    // Carga de Datos Maestros y Auxiliares
    // =========================================================================
    async function cargarAuxiliares() {
        try {
            const resp = await fetch('/servicios/auxiliares');
            const data = await resp.json();
            if (data.ok) {
                auxiliares = data;
                poblarSelectoresAuxiliares();
            }
        } catch (err) {
            console.error('Error al cargar auxiliares:', err);
        }
    }

    function poblarSelectoresAuxiliares() {
        // Selector de Proveedores en filtro de contratados
        const selFiltroProv = document.getElementById('filtro-contratados-proveedor');
        if (selFiltroProv) {
            const valActual = selFiltroProv.value;
            selFiltroProv.innerHTML = '<option value="">Operación / Proveedor</option><option value="interno">Operación Interna (Camargo)</option>';
            auxiliares.proveedores.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.razon_social} (${p.tipo})`;
                selFiltroProv.appendChild(opt);
            });
            selFiltroProv.value = valActual;
        }

        // Selector de Reservas en modal contratar
        const selReserva = document.getElementById('contratar-reserva-id');
        if (selReserva) {
            selReserva.innerHTML = '<option value="">Seleccione reserva comercial...</option>';
            auxiliares.reservas.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = `${r.codigo} — ${r.titular} (${r.fecha_entrada} al ${r.fecha_salida})`;
                selReserva.appendChild(opt);
            });
        }

        // Selector de Servicios en modal contratar
        const selServicio = document.getElementById('contratar-servicio-id');
        if (selServicio) {
            selServicio.innerHTML = '<option value="">Seleccione servicio del catálogo...</option>';
            auxiliares.servicios.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `[${s.codigo}] ${s.nombre} — PEN ${parseFloat(s.precio_venta_referencial).toFixed(2)}`;
                opt.dataset.precio = s.precio_venta_referencial;
                opt.dataset.costo = s.costo_referencial;
                opt.dataset.categoriaCodigo = s.categoria_codigo;
                opt.dataset.requiereTraslado = s.requiere_traslado_detalle ? '1' : '0';
                opt.dataset.esInterna = s.es_operacion_interna_habitual ? '1' : '0';
                selServicio.appendChild(opt);
            });
        }

        // Selector de Proveedores en modal contratar
        const selProvContratar = document.getElementById('contratar-proveedor-id');
        if (selProvContratar) {
            selProvContratar.innerHTML = '<option value="">Seleccione proveedor homologado...</option>';
            auxiliares.proveedores.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.razon_social} (${p.codigo})`;
                selProvContratar.appendChild(opt);
            });
        }

        // Selector de Proveedores en modal homologar
        const selProvHomologar = document.getElementById('homologar-proveedor-id');
        if (selProvHomologar) {
            selProvHomologar.innerHTML = '<option value="">Seleccione proveedor...</option>';
            auxiliares.proveedores.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.razon_social} [${p.codigo}] (${p.tipo})`;
                selProvHomologar.appendChild(opt);
            });
        }
    }

    // =========================================================================
    // TAB 1: Consumos y Contrataciones
    // =========================================================================
    async function cargarContratados() {
        const tbody = document.getElementById('tbody-servicios-contratados');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando consumos imputados...</td></tr>`;

        const q = document.getElementById('filtro-contratados-q')?.value || '';
        const estado = document.getElementById('filtro-contratados-estado')?.value || '';
        const proveedorId = document.getElementById('filtro-contratados-proveedor')?.value || '';
        const desde = document.getElementById('filtro-contratados-fecha-desde')?.value || '';
        const hasta = document.getElementById('filtro-contratados-fecha-hasta')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (estado) params.append('estado', estado);
        if (proveedorId) params.append('proveedor_id', proveedorId);
        if (desde) params.append('fecha_desde', desde);
        if (hasta) params.append('fecha_hasta', hasta);

        try {
            const resp = await fetch(`/servicios/contratados?${params.toString()}`);
            const data = await resp.json();

            if (data.ok) {
                contratadosCache = data.datos;
                renderizarContratados(data.datos);
                actualizarKpis();
                renderizarTraslados(data.datos);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${data.mensaje}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Error de conexión al cargar consumos.</td></tr>`;
        }
    }

    function renderizarContratados(items) {
        const tbody = document.getElementById('tbody-servicios-contratados');
        if (!tbody) return;

        if (items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-info me-2"></i> No se encontraron consumos ni servicios contratados.</td></tr>`;
            return;
        }

        let html = '';
        items.forEach(item => {
            const badgeEstado = obtenerBadgeEstado(item.estado);
            const operadorBadge = item.es_operacion_interna
                ? `<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-house-chimney me-1"></i> Interno (Camargo)</span>`
                : `<span class="badge bg-light-dark text-dark"><i class="fa-solid fa-truck me-1"></i> ${escaparHtml(item.proveedor_razon_social || 'Proveedor Externo')}</span>`;

            const estadiaInfo = item.estadia_codigo
                ? `<div class="f-s-11 text-muted"><i class="fa-solid fa-bed me-1"></i> ${escaparHtml(item.unidad_nombre || item.estadia_codigo)}</div>`
                : `<div class="f-s-11 text-muted">Folio general reserva</div>`;

            let botonesAccion = `
                <button type="button" class="btn btn-outline-info btn-xs btn-detalle-contratado" data-id="${item.id}" title="Ver ficha completa">
                    <i class="fa-solid fa-eye"></i>
                </button>
            `;

            if (item.estado === 'SOLICITADO') {
                botonesAccion += `
                    <button type="button" class="btn btn-outline-primary btn-xs btn-confirmar-contratado ms-1" data-id="${item.id}" title="Confirmar servicio">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-xs btn-cancelar-contratado ms-1" data-id="${item.id}" data-codigo="${item.codigo}" title="Cancelar servicio">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                `;
            } else if (item.estado === 'CONFIRMADO') {
                botonesAccion += `
                    <button type="button" class="btn btn-outline-success btn-xs btn-ejecutar-contratado ms-1" data-id="${item.id}" title="Marcar como ejecutado / entregado">
                        <i class="fa-solid fa-circle-check"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-xs btn-cancelar-contratado ms-1" data-id="${item.id}" data-codigo="${item.codigo}" title="Cancelar servicio">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                `;
            }

            html += `
                <tr>
                    <td class="ps-3"><strong class="text-dark">${escaparHtml(item.codigo)}</strong></td>
                    <td>
                        <div class="f-w-600">${escaparHtml(item.reserva_codigo || '')}</div>
                        <div class="f-s-11 text-secondary">${escaparHtml(item.titular_nombre_completo || '')}</div>
                        ${estadiaInfo}
                    </td>
                    <td>
                        <div class="f-w-600">${escaparHtml(item.descripcion_servicio_snapshot)}</div>
                        <span class="badge bg-light-secondary text-secondary f-s-10">${escaparHtml(item.categoria_codigo_snapshot)}</span>
                        ${item.traslado ? '<span class="badge bg-light-info text-info f-s-10 ms-1"><i class="fa-solid fa-van-shuttle"></i> Traslado</span>' : ''}
                    </td>
                    <td>
                        <div>${parseFloat(item.cantidad).toFixed(2)} ${escaparHtml(item.modalidad_cobro_codigo_snapshot)}</div>
                        <div class="f-s-11 text-muted">@ PEN ${parseFloat(item.precio_unitario).toFixed(2)}</div>
                    </td>
                    <td class="text-end f-w-700 text-primary">
                        PEN ${parseFloat(item.total).toFixed(2)}
                    </td>
                    <td>${operadorBadge}</td>
                    <td>
                        <div>${escaparHtml(item.fecha_servicio)}</div>
                        ${item.hora_servicio ? `<div class="f-s-11 text-muted">${escaparHtml(item.hora_servicio)}</div>` : ''}
                    </td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end pe-3">${botonesAccion}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        adjuntarEventosContratados();
    }

    function adjuntarEventosContratados() {
        // Botón Detalle
        document.querySelectorAll('.btn-detalle-contratado').forEach(btn => {
            btn.addEventListener('click', () => abrirDetalleContratado(btn.dataset.id));
        });

        // Botón Confirmar
        document.querySelectorAll('.btn-confirmar-contratado').forEach(btn => {
            btn.addEventListener('click', () => accionarTransicion(btn.dataset.id, 'confirmar'));
        });

        // Botón Ejecutar
        document.querySelectorAll('.btn-ejecutar-contratado').forEach(btn => {
            btn.addEventListener('click', () => accionarTransicion(btn.dataset.id, 'ejecutar'));
        });

        // Botón Cancelar
        document.querySelectorAll('.btn-cancelar-contratado').forEach(btn => {
            btn.addEventListener('click', () => abrirModalCancelar(btn.dataset.id, btn.dataset.codigo));
        });
    }

    // =========================================================================
    // TAB 2: Catálogo de Servicios
    // =========================================================================
    async function cargarCatalogo() {
        const tbody = document.getElementById('tbody-catalogo-servicios');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando catálogo de servicios...</td></tr>`;

        const q = document.getElementById('filtro-catalogo-q')?.value || '';
        const categoriaId = document.getElementById('filtro-catalogo-categoria')?.value || '';
        const estado = document.getElementById('filtro-catalogo-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (categoriaId) params.append('categoria_id', categoriaId);
        if (estado) params.append('estado', estado);

        try {
            const resp = await fetch(`/servicios/catalogo?${params.toString()}`);
            const data = await resp.json();

            if (data.ok) {
                catalogoCache = data.datos;
                renderizarCatalogo(data.datos);
            } else {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${data.mensaje}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Error de conexión al cargar catálogo.</td></tr>`;
        }
    }

    function renderizarCatalogo(servicios) {
        const tbody = document.getElementById('tbody-catalogo-servicios');
        if (!tbody) return;

        if (servicios.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-info me-2"></i> No se encontraron servicios en el catálogo.</td></tr>`;
            return;
        }

        let html = '';
        servicios.forEach(s => {
            const badgeEstado = s.estado === 'ACTIVO'
                ? `<span class="badge bg-light-success text-success">ACTIVO</span>`
                : `<span class="badge bg-light-secondary text-secondary">INACTIVO</span>`;

            const operacionLabel = s.es_operacion_interna_habitual
                ? `<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-house-chimney me-1"></i> Interno</span>`
                : `<span class="badge bg-light-dark text-dark"><i class="fa-solid fa-truck me-1"></i> Externo</span>`;

            html += `
                <tr>
                    <td class="ps-3"><strong class="text-dark">${escaparHtml(s.codigo)}</strong></td>
                    <td>
                        <i class="${escaparHtml(s.categoria_icono || 'fa-solid fa-bell-concierge')} text-primary me-1"></i>
                        <span>${escaparHtml(s.categoria_nombre || '')}</span>
                    </td>
                    <td>
                        <div class="f-w-600">${escaparHtml(s.nombre)}</div>
                        ${s.requiere_traslado_detalle ? '<span class="badge bg-light-info text-info f-s-10"><i class="fa-solid fa-van-shuttle"></i> Exige Traslado</span>' : ''}
                        ${s.propiedad_nombre ? `<div class="f-s-11 text-muted"><i class="fa-solid fa-building me-1"></i> ${escaparHtml(s.propiedad_nombre)}</div>` : ''}
                    </td>
                    <td>${escaparHtml(s.modalidad_cobro_nombre || s.modalidad_cobro_codigo || '')}</td>
                    <td class="text-end f-w-600">PEN ${parseFloat(s.precio_venta_referencial).toFixed(2)}</td>
                    <td class="text-end text-muted">PEN ${parseFloat(s.costo_referencial).toFixed(2)}</td>
                    <td class="text-center">${operacionLabel}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-outline-primary btn-xs btn-editar-servicio" data-id="${s.id}" title="Editar servicio">
                            <i class="fa-solid fa-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-xs btn-asociar-proveedor ms-1" data-id="${s.id}" data-nombre="${escaparHtml(s.nombre)}" title="Homologar proveedor">
                            <i class="fa-solid fa-link"></i>
                        </button>
                        <button type="button" class="btn btn-outline-${s.estado === 'ACTIVO' ? 'warning' : 'success'} btn-xs btn-toggle-servicio ms-1" data-id="${s.id}" data-estado="${s.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'}" title="${s.estado === 'ACTIVO' ? 'Desactivar' : 'Activar'}">
                            <i class="fa-solid fa-${s.estado === 'ACTIVO' ? 'power-off' : 'check'}"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        adjuntarEventosCatalogo();
    }

    function adjuntarEventosCatalogo() {
        document.querySelectorAll('.btn-editar-servicio').forEach(btn => {
            btn.addEventListener('click', () => abrirEditarServicio(btn.dataset.id));
        });

        document.querySelectorAll('.btn-asociar-proveedor').forEach(btn => {
            btn.addEventListener('click', () => abrirModalHomologar(btn.dataset.id, btn.dataset.nombre));
        });

        document.querySelectorAll('.btn-toggle-servicio').forEach(btn => {
            btn.addEventListener('click', () => cambiarEstadoServicio(btn.dataset.id, btn.dataset.estado));
        });
    }

    // =========================================================================
    // TAB 3: Proveedores Homologados
    // =========================================================================
    async function cargarProveedores() {
        const tbody = document.getElementById('tbody-proveedores');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando proveedores...</td></tr>`;

        const q = document.getElementById('filtro-proveedor-q')?.value || '';
        const tipo = document.getElementById('filtro-proveedor-tipo')?.value || '';
        const estado = document.getElementById('filtro-proveedor-estado')?.value || '';

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (tipo) params.append('tipo', tipo);
        if (estado) params.append('estado', estado);

        try {
            const resp = await fetch(`/servicios/proveedores?${params.toString()}`);
            const data = await resp.json();

            if (data.ok) {
                proveedoresCache = data.datos;
                renderizarProveedores(data.datos);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${data.mensaje}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión al cargar proveedores.</td></tr>`;
        }
    }

    function renderizarProveedores(proveedores) {
        const tbody = document.getElementById('tbody-proveedores');
        if (!tbody) return;

        if (proveedores.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-info me-2"></i> No se encontraron proveedores registrados.</td></tr>`;
            return;
        }

        let html = '';
        proveedores.forEach(p => {
            const badgeTipo = p.tipo === 'EMPRESA'
                ? `<span class="badge bg-light-dark text-dark"><i class="fa-solid fa-building me-1"></i> EMPRESA</span>`
                : `<span class="badge bg-light-secondary text-secondary"><i class="fa-solid fa-user-tie me-1"></i> PERSONA NATURAL</span>`;

            const badgeEstado = p.estado === 'ACTIVO'
                ? `<span class="badge bg-light-success text-success">ACTIVO</span>`
                : `<span class="badge bg-light-secondary text-secondary">INACTIVO</span>`;

            html += `
                <tr>
                    <td class="ps-3"><strong class="text-dark">${escaparHtml(p.codigo)}</strong></td>
                    <td>
                        <div class="f-w-600">${escaparHtml(p.razon_social)}</div>
                        ${p.nombre_comercial ? `<div class="f-s-11 text-secondary"><i class="fa-solid fa-tag me-1"></i> ${escaparHtml(p.nombre_comercial)}</div>` : ''}
                    </td>
                    <td>${badgeTipo}</td>
                    <td>${escaparHtml(p.numero_documento || '-')}</td>
                    <td>
                        <div>${p.email ? `<i class="fa-solid fa-envelope me-1 text-muted"></i> ${escaparHtml(p.email)}` : '-'}</div>
                        ${p.telefono ? `<div class="f-s-11 text-muted"><i class="fa-solid fa-phone me-1"></i> ${escaparHtml(p.telefono)}</div>` : ''}
                    </td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-outline-primary btn-xs btn-editar-proveedor" data-id="${p.id}" title="Editar proveedor">
                            <i class="fa-solid fa-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-${p.estado === 'ACTIVO' ? 'warning' : 'success'} btn-xs btn-toggle-proveedor ms-1" data-id="${p.id}" data-estado="${p.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'}" title="${p.estado === 'ACTIVO' ? 'Desactivar' : 'Activar'}">
                            <i class="fa-solid fa-${p.estado === 'ACTIVO' ? 'power-off' : 'check'}"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        adjuntarEventosProveedores();
    }

    function adjuntarEventosProveedores() {
        document.querySelectorAll('.btn-editar-proveedor').forEach(btn => {
            btn.addEventListener('click', () => abrirEditarProveedor(btn.dataset.id));
        });

        document.querySelectorAll('.btn-toggle-proveedor').forEach(btn => {
            btn.addEventListener('click', () => cambiarEstadoProveedor(btn.dataset.id, btn.dataset.estado));
        });
    }

    // =========================================================================
    // TAB 4: Traslados y Transfers
    // =========================================================================
    function renderizarTraslados(contratados) {
        const tbody = document.getElementById('tbody-traslados');
        const badgeConteo = document.getElementById('conteo-traslados-badge');
        if (!tbody) return;

        const traslados = contratados.filter(c => c.traslado !== null && c.traslado !== undefined);
        if (badgeConteo) {
            badgeConteo.textContent = `${traslados.length} traslados registrados`;
        }

        if (traslados.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-van-shuttle me-2"></i> No hay servicios de traslado programados.</td></tr>`;
            return;
        }

        let html = '';
        traslados.forEach(c => {
            const tr = c.traslado;
            const badgeTipo = tr.tipo_traslado === 'LLEGADA'
                ? `<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-plane-arrival me-1"></i> LLEGADA</span>`
                : `<span class="badge bg-light-info text-info"><i class="fa-solid fa-plane-departure me-1"></i> SALIDA</span>`;

            html += `
                <tr>
                    <td class="ps-3"><strong class="text-dark">${escaparHtml(c.codigo)}</strong></td>
                    <td>${badgeTipo}</td>
                    <td>
                        <div class="f-w-600">${escaparHtml(tr.origen)} ➔ ${escaparHtml(tr.destino)}</div>
                        <div class="f-s-11 text-muted">Reserva: ${escaparHtml(c.reserva_codigo || '')} (${escaparHtml(c.titular_nombre_completo || '')})</div>
                    </td>
                    <td><strong class="text-primary">${escaparHtml(tr.fecha_hora_recogida)}</strong></td>
                    <td>
                        <div>${escaparHtml(tr.aerolinea_empresa || '-')}</div>
                        ${tr.numero_vuelo_viaje ? `<div class="f-s-11 text-secondary f-w-600">${escaparHtml(tr.numero_vuelo_viaje)}</div>` : ''}
                    </td>
                    <td>
                        <div><i class="fa-solid fa-users me-1 text-muted"></i> ${tr.cantidad_pasajeros} pax</div>
                        <div class="f-s-11 text-muted"><i class="fa-solid fa-suitcase me-1"></i> ${tr.cantidad_maletas} valijas</div>
                    </td>
                    <td>${escaparHtml(tr.datos_conductor_vehiculo || 'Por asignar')}</td>
                    <td class="text-center">${obtenerBadgeEstado(c.estado)}</td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-outline-info btn-xs btn-detalle-contratado" data-id="${c.id}" title="Ver ficha">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        document.querySelectorAll('#tbody-traslados .btn-detalle-contratado').forEach(btn => {
            btn.addEventListener('click', () => abrirDetalleContratado(btn.dataset.id));
        });
    }

    // =========================================================================
    // Lógica de Modales y Formularios
    // =========================================================================

    // 1. Modal Contratar
    const modalContratarEl = document.getElementById('modal-contratar-servicio');
    const modalContratar = modalContratarEl ? new bootstrap.Modal(modalContratarEl) : null;
    const formContratar = document.getElementById('form-contratar-servicio');

    document.getElementById('btn-abrir-contratar')?.addEventListener('click', () => {
        formContratar.reset();
        document.getElementById('op-interna').checked = true;
        document.getElementById('bloque-proveedor-externo').classList.add('d-none');
        document.getElementById('bloque-detalle-traslado').classList.add('d-none');
        document.getElementById('contratar-total-calculado').value = 'PEN 0.00';
        modalContratar?.show();
    });

    // Cambio en Reserva: cargar estadias asociadas si las hay
    document.getElementById('contratar-reserva-id')?.addEventListener('change', async (e) => {
        const reservaId = e.target.value;
        const selEstadia = document.getElementById('contratar-estadia-id');
        selEstadia.innerHTML = '<option value="">Sin imputar a habitación específica</option>';

        if (!reservaId) return;

        try {
            const resp = await fetch(`/estadias?reserva_id=${reservaId}`);
            const data = await resp.json();
            if (data.ok && Array.isArray(data.datos)) {
                data.datos.forEach(est => {
                    const opt = document.createElement('option');
                    opt.value = est.id;
                    opt.textContent = `${est.codigo} — ${est.unidad_nombre || 'Unidad'} (${est.estado})`;
                    selEstadia.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Error al cargar estadias de reserva:', err);
        }
    });

    // Cambio en Servicio: autollenar precio y detectar si requiere traslado
    document.getElementById('contratar-servicio-id')?.addEventListener('change', (e) => {
        const sel = e.target;
        const opt = sel.options[sel.selectedIndex];

        if (opt && opt.dataset) {
            const precio = parseFloat(opt.dataset.precio || 0).toFixed(2);
            const costo = parseFloat(opt.dataset.costo || 0).toFixed(2);
            document.getElementById('contratar-precio-unitario').value = precio;
            document.getElementById('contratar-costo-unitario').value = costo;

            recalcularTotalContratar();

            const requiereTraslado = opt.dataset.requiereTraslado === '1' || opt.dataset.categoriaCodigo === 'TRASLADOS';
            const bloqueTraslado = document.getElementById('bloque-detalle-traslado');
            if (requiereTraslado) {
                bloqueTraslado.classList.remove('d-none');
            } else {
                bloqueTraslado.classList.add('d-none');
            }

            // Si es internamente habitual
            if (opt.dataset.esInterna === '1') {
                document.getElementById('op-interna').checked = true;
                document.getElementById('bloque-proveedor-externo').classList.add('d-none');
            }
        }
    });

    // Cambio en tipo de operacion (Interna vs Externa)
    document.querySelectorAll('input[name="tipo_operacion"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const esExterna = document.getElementById('op-externa').checked;
            const bloqueProv = document.getElementById('bloque-proveedor-externo');
            if (esExterna) {
                bloqueProv.classList.remove('d-none');
            } else {
                bloqueProv.classList.add('d-none');
                document.getElementById('contratar-proveedor-id').value = '';
            }
        });
    });

    // Recalcular total
    document.getElementById('contratar-cantidad')?.addEventListener('input', recalcularTotalContratar);
    document.getElementById('contratar-precio-unitario')?.addEventListener('input', recalcularTotalContratar);

    function recalcularTotalContratar() {
        const cant = parseFloat(document.getElementById('contratar-cantidad')?.value || 1);
        const precio = parseFloat(document.getElementById('contratar-precio-unitario')?.value || 0);
        const total = isNaN(cant) || isNaN(precio) ? 0 : cant * precio;
        document.getElementById('contratar-total-calculado').value = `PEN ${total.toFixed(2)}`;
    }

    // Guardar Contratación con PristineJS y SweetAlert2
    if (formContratar) {
        const pristineContratar = new Pristine(formContratar);
        formContratar.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!pristineContratar.validate()) return;

            const formData = new FormData(formContratar);
            const payload = Object.fromEntries(formData.entries());
            payload['_csrf_token'] = csrfToken;

            // Extraer traslado anidado si aplica
            const requiereTraslado = !document.getElementById('bloque-detalle-traslado').classList.contains('d-none');
            if (requiereTraslado) {
                payload['traslado'] = {
                    tipo_traslado: document.getElementById('traslado-tipo').value,
                    origen: document.getElementById('traslado-origen').value,
                    destino: document.getElementById('traslado-destino').value,
                    fecha_hora_recogida: document.getElementById('traslado-fecha-hora').value,
                    aerolinea_empresa: document.getElementById('traslado-aerolinea').value,
                    numero_vuelo_viaje: document.getElementById('traslado-vuelo').value,
                    cantidad_pasajeros: document.getElementById('traslado-pasajeros').value,
                    cantidad_maletas: document.getElementById('traslado-maletas').value,
                    datos_conductor_vehiculo: document.getElementById('traslado-conductor').value,
                    instrucciones_recogida: document.getElementById('traslado-instrucciones').value,
                };
            }

            const esInterna = document.getElementById('op-interna').checked;
            payload['es_operacion_interna'] = esInterna ? 1 : 0;
            if (esInterna) {
                payload['proveedor_id'] = null;
            }

            try {
                const btnSubmit = document.getElementById('btn-guardar-contratacion');
                btnSubmit.disabled = true;

                const resp = await fetch('/servicios/contratados', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                btnSubmit.disabled = false;

                if (res.ok) {
                    modalContratar?.hide();
                    Swal.fire({
                        icon: 'success',
                        title: '¡Servicio Contratado!',
                        text: res.mensaje,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    cargarContratados();
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No se pudo contratar',
                        text: res.mensaje
                    });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error inesperado al contratar el servicio.' });
            }
        });
    }

    // 2. Transiciones de Estado (Confirmar / Ejecutar)
    async function accionarTransicion(id, accion) {
        const titulo = accion === 'confirmar' ? '¿Confirmar Servicio?' : '¿Marcar como Ejecutado?';
        const texto = accion === 'confirmar'
            ? 'El servicio pasará a estado CONFIRMADO para su prestación operativa.'
            : 'Se registrará que el servicio fue entregado físicamente al huésped. Esta acción es definitiva.';

        const confirm = await Swal.fire({
            icon: 'question',
            title: titulo,
            text: texto,
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch(`/servicios/contratados/${id}/${accion}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf_token: csrfToken })
            });
            const res = await resp.json();

            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Completado', text: res.mensaje, timer: 1500, showConfirmButton: false });
                cargarContratados();
            } else {
                Swal.fire({ icon: 'warning', title: 'Atención', text: res.mensaje });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al procesar la transición de estado.' });
        }
    }

    // 3. Modal Cancelar
    const modalCancelarEl = document.getElementById('modal-cancelar-servicio');
    const modalCancelar = modalCancelarEl ? new bootstrap.Modal(modalCancelarEl) : null;
    const formCancelar = document.getElementById('form-cancelar-servicio');

    function abrirModalCancelar(id, codigo) {
        document.getElementById('cancelar-contratado-id').value = id;
        document.getElementById('cancelar-codigo-servicio').textContent = codigo;
        document.getElementById('cancelar-motivo').value = '';
        modalCancelar?.show();
    }

    if (formCancelar) {
        formCancelar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('cancelar-contratado-id').value;
            const motivo = document.getElementById('cancelar-motivo').value.trim();

            if (!motivo) {
                Swal.fire({ icon: 'warning', title: 'Motivo Requerido', text: 'Debe ingresar un motivo justificado para la cancelación.' });
                return;
            }

            try {
                const resp = await fetch(`/servicios/contratados/${id}/cancelar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ _csrf_token: csrfToken, motivo: motivo })
                });
                const res = await resp.json();

                if (res.ok) {
                    modalCancelar?.hide();
                    Swal.fire({ icon: 'success', title: 'Cancelado', text: res.mensaje, timer: 1500, showConfirmButton: false });
                    cargarContratados();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error al cancelar', text: res.mensaje });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error inesperado al cancelar servicio.' });
            }
        });
    }

    // 4. Modal Detalle Ficha Completa
    const modalDetalleEl = document.getElementById('modal-detalle-servicio');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;

    async function abrirDetalleContratado(id) {
        try {
            const resp = await fetch(`/servicios/contratados/${id}`);
            const data = await resp.json();
            if (!data.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
                return;
            }

            const sc = data.datos;
            document.getElementById('detalle-sc-nombre').textContent = sc.descripcion_servicio_snapshot;
            document.getElementById('detalle-sc-codigo').textContent = sc.codigo;
            document.getElementById('detalle-sc-categoria').textContent = sc.categoria_codigo_snapshot;
            document.getElementById('detalle-sc-reserva').textContent = `Reserva: ${sc.reserva_codigo || 'N/A'}`;
            document.getElementById('detalle-sc-titular').textContent = `Titular: ${sc.titular_nombre_completo || 'N/A'}`;
            document.getElementById('detalle-sc-estadia').textContent = sc.estadia_codigo
                ? `Estadía: ${sc.estadia_codigo} (${sc.unidad_nombre || 'Unidad'})`
                : 'Folio comercial general (sin habitación específica)';

            document.getElementById('detalle-sc-cantidad').textContent = parseFloat(sc.cantidad).toFixed(2);
            document.getElementById('detalle-sc-precio').textContent = `PEN ${parseFloat(sc.precio_unitario).toFixed(2)}`;
            document.getElementById('detalle-sc-modalidad').textContent = sc.modalidad_cobro_codigo_snapshot;
            document.getElementById('detalle-sc-total').textContent = `PEN ${parseFloat(sc.total).toFixed(2)}`;

            document.getElementById('detalle-sc-operador').textContent = sc.es_operacion_interna
                ? 'Operación Interna (Personal Camargo Hostelería)'
                : `${sc.proveedor_razon_social || 'Proveedor Externo'} (${sc.proveedor_codigo || ''})`;

            document.getElementById('detalle-sc-estado-badge').innerHTML = obtenerBadgeEstado(sc.estado);
            document.getElementById('detalle-sc-observaciones').textContent = sc.observaciones || 'Ninguna';

            // Traslado
            const bloqueTr = document.getElementById('detalle-sc-bloque-traslado');
            if (sc.traslado) {
                bloqueTr.classList.remove('d-none');
                document.getElementById('detalle-tr-ruta').textContent = `${sc.traslado.origen} ➔ ${sc.traslado.destino} (${sc.traslado.tipo_traslado})`;
                document.getElementById('detalle-tr-horario').textContent = sc.traslado.fecha_hora_recogida;
                document.getElementById('detalle-tr-vuelo').textContent = `${sc.traslado.aerolinea_empresa || ''} ${sc.traslado.numero_vuelo_viaje || ''}`;
                document.getElementById('detalle-tr-pax').textContent = `${sc.traslado.cantidad_pasajeros} pax / ${sc.traslado.cantidad_maletas} valijas`;
                document.getElementById('detalle-tr-conductor').textContent = sc.traslado.datos_conductor_vehiculo || 'Por asignar';
                document.getElementById('detalle-tr-instrucciones').textContent = sc.traslado.instrucciones_recogida || 'Ninguna';
            } else {
                bloqueTr.classList.add('d-none');
            }

            // Cancelación
            const bloqueCanc = document.getElementById('detalle-sc-bloque-cancelacion');
            if (sc.estado === 'CANCELADO') {
                bloqueCanc.classList.remove('d-none');
                document.getElementById('detalle-sc-cancelado-en').textContent = sc.cancelada_en || '-';
                document.getElementById('detalle-sc-cancelado-motivo').textContent = sc.motivo_cancelacion || '-';
            } else {
                bloqueCanc.classList.add('d-none');
            }

            modalDetalle?.show();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al consultar detalle del servicio.' });
        }
    }

    // 5. Modal Crear / Editar Servicio de Catálogo
    const modalServicioEl = document.getElementById('modal-crear-servicio');
    const modalServicio = modalServicioEl ? new bootstrap.Modal(modalServicioEl) : null;
    const formServicio = document.getElementById('form-crear-servicio');

    document.getElementById('btn-abrir-crear-servicio')?.addEventListener('click', () => {
        formServicio.reset();
        document.getElementById('servicio-id').value = '';
        document.getElementById('modalServicioTitulo').innerHTML = '<i class="fa-solid fa-layer-group text-primary me-2"></i> Registrar Servicio en Catálogo';
        modalServicio?.show();
    });

    async function abrirEditarServicio(id) {
        try {
            const resp = await fetch(`/servicios/catalogo/${id}`);
            const data = await resp.json();
            if (!data.ok) return;

            const s = data.datos;
            document.getElementById('servicio-id').value = s.id;
            document.getElementById('servicio-codigo').value = s.codigo;
            document.getElementById('servicio-nombre').value = s.nombre;
            document.getElementById('servicio-categoria-id').value = s.categoria_id;
            document.getElementById('servicio-modalidad-id').value = s.modalidad_cobro_id;
            document.getElementById('servicio-propiedad-id').value = s.propiedad_id || '';
            document.getElementById('servicio-precio').value = s.precio_venta_referencial;
            document.getElementById('servicio-costo').value = s.costo_referencial;
            document.getElementById('servicio-es-interna').checked = !!s.es_operacion_interna_habitual;
            document.getElementById('servicio-requiere-traslado').checked = !!s.requiere_traslado_detalle;
            document.getElementById('servicio-descripcion').value = s.descripcion || '';

            document.getElementById('modalServicioTitulo').innerHTML = '<i class="fa-solid fa-pencil text-primary me-2"></i> Editar Servicio en Catálogo';
            modalServicio?.show();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al consultar servicio.' });
        }
    }

    if (formServicio) {
        const pristineServicio = new Pristine(formServicio);
        formServicio.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!pristineServicio.validate()) return;

            const formData = new FormData(formServicio);
            const payload = Object.fromEntries(formData.entries());
            payload['_csrf_token'] = csrfToken;
            payload['es_operacion_interna_habitual'] = document.getElementById('servicio-es-interna').checked ? 1 : 0;
            payload['requiere_traslado_detalle'] = document.getElementById('servicio-requiere-traslado').checked ? 1 : 0;

            const id = document.getElementById('servicio-id').value;
            const url = id ? `/servicios/catalogo/${id}` : '/servicios/catalogo';

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (res.ok) {
                    modalServicio?.hide();
                    Swal.fire({ icon: 'success', title: 'Catálogo Actualizado', text: res.mensaje, timer: 1500, showConfirmButton: false });
                    cargarCatalogo();
                    cargarAuxiliares();
                } else {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: res.mensaje });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar servicio en catálogo.' });
            }
        });
    }

    async function cambiarEstadoServicio(id, nuevoEstado) {
        try {
            const resp = await fetch(`/servicios/catalogo/${id}/estado`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf_token: csrfToken, estado: nuevoEstado })
            });
            const res = await resp.json();
            if (res.ok) {
                cargarCatalogo();
                cargarAuxiliares();
            } else {
                Swal.fire({ icon: 'warning', title: 'Atención', text: res.mensaje });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al actualizar estado del servicio.' });
        }
    }

    // 6. Modal Homologar Proveedor en Servicio
    const modalHomologarEl = document.getElementById('modal-homologar-proveedor');
    const modalHomologar = modalHomologarEl ? new bootstrap.Modal(modalHomologarEl) : null;
    const formHomologar = document.getElementById('form-homologar-proveedor');

    function abrirModalHomologar(servicioId, servicioNombre) {
        document.getElementById('homologar-servicio-id').value = servicioId;
        document.getElementById('homologar-servicio-nombre').value = servicioNombre;
        formHomologar.reset();
        document.getElementById('homologar-servicio-id').value = servicioId;
        document.getElementById('homologar-servicio-nombre').value = servicioNombre;
        modalHomologar?.show();
    }

    if (formHomologar) {
        formHomologar.addEventListener('submit', async (e) => {
            e.preventDefault();
            const servicioId = document.getElementById('homologar-servicio-id').value;
            const formData = new FormData(formHomologar);
            const payload = Object.fromEntries(formData.entries());
            payload['_csrf_token'] = csrfToken;
            payload['es_preferente'] = document.getElementById('homologar-es-preferente').checked ? 1 : 0;

            try {
                const resp = await fetch(`/servicios/catalogo/${servicioId}/proveedores`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();
                if (res.ok) {
                    modalHomologar?.hide();
                    Swal.fire({ icon: 'success', title: 'Proveedor Homologado', text: res.mensaje, timer: 1500, showConfirmButton: false });
                    cargarCatalogo();
                } else {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: res.mensaje });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al asociar proveedor.' });
            }
        });
    }

    // 7. Modal Crear / Editar Proveedor
    const modalProveedorEl = document.getElementById('modal-crear-proveedor');
    const modalProveedor = modalProveedorEl ? new bootstrap.Modal(modalProveedorEl) : null;
    const formProveedor = document.getElementById('form-crear-proveedor');

    document.getElementById('btn-abrir-crear-proveedor')?.addEventListener('click', () => {
        formProveedor.reset();
        document.getElementById('proveedor-id').value = '';
        document.getElementById('modalProveedorTitulo').innerHTML = '<i class="fa-solid fa-truck-field text-primary me-2"></i> Registrar Proveedor Externo';
        modalProveedor?.show();
    });

    async function abrirEditarProveedor(id) {
        try {
            const resp = await fetch(`/servicios/proveedores/${id}`);
            const data = await resp.json();
            if (!data.ok) return;

            const p = data.datos;
            document.getElementById('proveedor-id').value = p.id;
            document.getElementById('proveedor-codigo').value = p.codigo;
            document.getElementById('proveedor-tipo').value = p.tipo;
            document.getElementById('proveedor-documento').value = p.numero_documento || '';
            document.getElementById('proveedor-razon-social').value = p.razon_social;
            document.getElementById('proveedor-nombre-comercial').value = p.nombre_comercial || '';
            document.getElementById('proveedor-email').value = p.email || '';
            document.getElementById('proveedor-telefono').value = p.telefono || '';
            document.getElementById('proveedor-direccion').value = p.direccion || '';
            document.getElementById('proveedor-observaciones').value = p.observaciones || '';

            document.getElementById('modalProveedorTitulo').innerHTML = '<i class="fa-solid fa-pencil text-primary me-2"></i> Editar Proveedor Externo';
            modalProveedor?.show();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al consultar proveedor.' });
        }
    }

    if (formProveedor) {
        const pristineProveedor = new Pristine(formProveedor);
        formProveedor.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!pristineProveedor.validate()) return;

            const formData = new FormData(formProveedor);
            const payload = Object.fromEntries(formData.entries());
            payload['_csrf_token'] = csrfToken;

            const id = document.getElementById('proveedor-id').value;
            const url = id ? `/servicios/proveedores/${id}` : '/servicios/proveedores';

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const res = await resp.json();

                if (res.ok) {
                    modalProveedor?.hide();
                    Swal.fire({ icon: 'success', title: 'Proveedor Guardado', text: res.mensaje, timer: 1500, showConfirmButton: false });
                    cargarProveedores();
                    cargarAuxiliares();
                } else {
                    Swal.fire({ icon: 'warning', title: 'Atención', text: res.mensaje });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error al registrar proveedor.' });
            }
        });
    }

    async function cambiarEstadoProveedor(id, nuevoEstado) {
        try {
            const resp = await fetch(`/servicios/proveedores/${id}/estado`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf_token: csrfToken, estado: nuevoEstado })
            });
            const res = await resp.json();
            if (res.ok) {
                cargarProveedores();
                cargarAuxiliares();
            } else {
                Swal.fire({ icon: 'warning', title: 'Atención', text: res.mensaje });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al actualizar estado del proveedor.' });
        }
    }

    // =========================================================================
    // Filtros con eventos debounce
    // =========================================================================
    document.getElementById('filtro-contratados-q')?.addEventListener('input', debounce(cargarContratados, 300));
    document.getElementById('filtro-contratados-estado')?.addEventListener('change', cargarContratados);
    document.getElementById('filtro-contratados-proveedor')?.addEventListener('change', cargarContratados);
    document.getElementById('filtro-contratados-fecha-desde')?.addEventListener('change', cargarContratados);
    document.getElementById('filtro-contratados-fecha-hasta')?.addEventListener('change', cargarContratados);
    document.getElementById('btn-limpiar-filtros-contratados')?.addEventListener('click', () => {
        document.getElementById('filtro-contratados-q').value = '';
        document.getElementById('filtro-contratados-estado').value = '';
        document.getElementById('filtro-contratados-proveedor').value = '';
        document.getElementById('filtro-contratados-fecha-desde').value = '';
        document.getElementById('filtro-contratados-fecha-hasta').value = '';
        cargarContratados();
    });

    document.getElementById('filtro-catalogo-q')?.addEventListener('input', debounce(cargarCatalogo, 300));
    document.getElementById('filtro-catalogo-categoria')?.addEventListener('change', cargarCatalogo);
    document.getElementById('filtro-catalogo-estado')?.addEventListener('change', cargarCatalogo);
    document.getElementById('btn-limpiar-filtros-catalogo')?.addEventListener('click', () => {
        document.getElementById('filtro-catalogo-q').value = '';
        document.getElementById('filtro-catalogo-categoria').value = '';
        document.getElementById('filtro-catalogo-estado').value = '';
        cargarCatalogo();
    });

    document.getElementById('filtro-proveedor-q')?.addEventListener('input', debounce(cargarProveedores, 300));
    document.getElementById('filtro-proveedor-tipo')?.addEventListener('change', cargarProveedores);
    document.getElementById('filtro-proveedor-estado')?.addEventListener('change', cargarProveedores);
    document.getElementById('btn-limpiar-filtros-proveedores')?.addEventListener('click', () => {
        document.getElementById('filtro-proveedor-q').value = '';
        document.getElementById('filtro-proveedor-tipo').value = '';
        document.getElementById('filtro-proveedor-estado').value = '';
        cargarProveedores();
    });

    // Pestañas dinámicas
    document.getElementById('tab-catalogo-btn')?.addEventListener('shown.bs.tab', () => {
        if (catalogoCache.length === 0) cargarCatalogo();
    });
    document.getElementById('tab-proveedores-btn')?.addEventListener('shown.bs.tab', () => {
        if (proveedoresCache.length === 0) cargarProveedores();
    });

    // =========================================================================
    // Helpers y Utilidades
    // =========================================================================
    function actualizarKpis() {
        const kpiSol = document.getElementById('kpi-solicitados');
        const kpiConf = document.getElementById('kpi-confirmados');
        const kpiEjec = document.getElementById('kpi-ejecutados');
        const kpiCat = document.getElementById('kpi-catalogo-activo');

        if (kpiSol) kpiSol.textContent = contratadosCache.filter(c => c.estado === 'SOLICITADO').length;
        if (kpiConf) kpiConf.textContent = contratadosCache.filter(c => c.estado === 'CONFIRMADO').length;
        if (kpiEjec) kpiEjec.textContent = contratadosCache.filter(c => c.estado === 'EJECUTADO').length;
        if (kpiCat) kpiCat.textContent = auxiliares.servicios.length;
    }

    function obtenerBadgeEstado(estado) {
        switch (estado) {
            case 'SOLICITADO':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> SOLICITADO</span>';
            case 'CONFIRMADO':
                return '<span class="badge bg-light-info text-info"><i class="fa-solid fa-circle-check me-1"></i> CONFIRMADO</span>';
            case 'EJECUTADO':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-handshake me-1"></i> EJECUTADO</span>';
            case 'CANCELADO':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-ban me-1"></i> CANCELADO</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function escaparHtml(texto) {
        if (texto === null || texto === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(texto);
        return div.innerHTML;
    }

    function debounce(func, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Carga inicial
    cargarAuxiliares().then(() => {
        cargarContratados();
    });
});
