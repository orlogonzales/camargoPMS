/**
 * Camargo PMS — Módulo de Gestión de Inventario Físico, Existencias, Kardex y Activos (INVENTARIO-1 / D-078)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - ARTÍCULO != EXISTENCIA != MOVIMIENTO != ACTIVO INDIVIDUAL.
 * - Formulario con geometría nativa Alina (border-radius 20px, select2 42px).
 * - D-071: Badges suaves bg-light-*, Font Awesome 6.3.0 exclusivo, cero degradados.
 * - Kardex append-only inmutable con reversos trazables.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Modales Bootstrap
    const modalArticuloEl = document.getElementById('modal-articulo');
    const modalMovimientoEl = document.getElementById('modal-movimiento');
    const modalActivoEl = document.getElementById('modal-activo');
    const modalTransfActivoEl = document.getElementById('modal-transferir-activo');
    const modalBajaActivoEl = document.getElementById('modal-baja-activo');
    const modalUbicacionEl = document.getElementById('modal-ubicacion');
    const modalDotacionEl = document.getElementById('modal-dotacion');

    const modalArticulo = modalArticuloEl ? new bootstrap.Modal(modalArticuloEl) : null;
    const modalMovimiento = modalMovimientoEl ? new bootstrap.Modal(modalMovimientoEl) : null;
    const modalActivo = modalActivoEl ? new bootstrap.Modal(modalActivoEl) : null;
    const modalTransfActivo = modalTransfActivoEl ? new bootstrap.Modal(modalTransfActivoEl) : null;
    const modalBajaActivo = modalBajaActivoEl ? new bootstrap.Modal(modalBajaActivoEl) : null;
    const modalUbicacion = modalUbicacionEl ? new bootstrap.Modal(modalUbicacionEl) : null;
    const modalDotacion = modalDotacionEl ? new bootstrap.Modal(modalDotacionEl) : null;

    // Cache local de datos
    let articulosCache = [];
    let ubicacionesCache = [];

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

    function badgeCategoria(cat) {
        switch (cat) {
            case 'CONSUMIBLE_OPERATIVO':
                return '<span class="badge bg-light-info text-info f-w-600"><i class="fa-solid fa-soap me-1"></i> Consumible</span>';
            case 'LENCERIA_BLANCOS':
                return '<span class="badge bg-light-primary text-primary f-w-600"><i class="fa-solid fa-bed me-1"></i> Lencería</span>';
            case 'REPUESTO_MANTENIMIENTO':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-wrench me-1"></i> Repuesto</span>';
            case 'ACTIVO_SERIALIZABLE':
                return '<span class="badge bg-light-purple text-purple f-w-600" style="background:#f3e8ff;color:#7e22ce;"><i class="fa-solid fa-tv me-1"></i> Activo Fijo</span>';
            case 'HERRAMIENTA':
                return '<span class="badge bg-light-secondary text-secondary f-w-600"><i class="fa-solid fa-screwdriver me-1"></i> Herramienta</span>';
            default:
                return '<span class="badge bg-light-secondary text-secondary f-w-600">Otro</span>';
        }
    }

    function badgeTipoMovimiento(tipo) {
        switch (tipo) {
            case 'SALDO_INICIAL':
                return '<span class="badge bg-light-secondary text-secondary f-w-600">Saldo Inicial</span>';
            case 'ENTRADA_COMPRA':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-arrow-down me-1"></i> Entrada Compra</span>';
            case 'SALIDA_CONSUMO':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-arrow-up me-1"></i> Salida Consumo</span>';
            case 'SALIDA_MANTENIMIENTO':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-screwdriver-wrench me-1"></i> Salida Mantenimiento</span>';
            case 'TRASLADO_SALIDA':
                return '<span class="badge bg-light-info text-info f-w-600"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Traslado Salida</span>';
            case 'TRASLADO_ENTRADA':
                return '<span class="badge bg-light-info text-info f-w-600"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Traslado Entrada</span>';
            case 'AJUSTE_POSITIVO':
                return '<span class="badge bg-light-success text-success f-w-600">+ Ajuste Físico</span>';
            case 'AJUSTE_NEGATIVO':
                return '<span class="badge bg-light-danger text-danger f-w-600">- Ajuste / Merma</span>';
            case 'REVERSO':
                return '<span class="badge bg-light-danger text-danger f-w-600" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-rotate-left me-1"></i> Reverso</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(tipo)}</span>`;
        }
    }

    function badgeEstadoActivo(estado) {
        switch (estado) {
            case 'DISPONIBLE':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-check me-1"></i> Disponible</span>';
            case 'ASIGNADO':
                return '<span class="badge bg-light-primary text-primary f-w-600"><i class="fa-solid fa-door-open me-1"></i> Asignado a Habitación</span>';
            case 'EN_MANTENIMIENTO':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-wrench me-1"></i> En Mantenimiento</span>';
            case 'DE_BAJA':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-ban me-1"></i> Dado de Baja</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    // =========================================================================
    // 1. Carga de Existencias y KPIs
    // =========================================================================

    async function cargarExistencias() {
        const termino = document.getElementById('filtro-existencias-termino')?.value || '';
        const ubicacionId = document.getElementById('filtro-existencias-ubicacion')?.value || '';
        const categoria = document.getElementById('filtro-existencias-categoria')?.value || '';

        const params = new URLSearchParams();
        if (termino) params.append('termino', termino);
        if (ubicacionId) params.append('ubicacion_id', ubicacionId);
        if (categoria) params.append('categoria', categoria);

        try {
            const resp = await fetch(`/api/inventario/existencias?${params.toString()}`);
            const data = await resp.json();
            const tbody = document.querySelector('#tabla-existencias tbody');

            if (!data.exito || !data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se registraron existencias con los filtros aplicados.</td></tr>';
                document.getElementById('kpi-total-existencias').textContent = '0';
                return;
            }

            document.getElementById('kpi-total-existencias').textContent = data.datos.length;

            let html = '';
            data.datos.forEach(ex => {
                const disponible = parseFloat(ex.cantidad_disponible);
                const claseStock = disponible <= 0 ? 'text-danger f-w-700' : 'text-success f-w-700';

                html += `
                    <tr>
                        <td>
                            <div class="f-w-700 f-s-13">${escaparHtml(ex.articulo_nombre)}</div>
                            <small class="text-muted"><i class="fa-solid fa-barcode me-1"></i> ${escaparHtml(ex.articulo_sku)}</small>
                        </td>
                        <td>${badgeCategoria(ex.articulo_categoria)}</td>
                        <td>
                            <div class="f-w-600 f-s-13">${escaparHtml(ex.ubicacion_nombre)}</div>
                            <small class="text-muted">${escaparHtml(ex.propiedad_nombre || '')} (${escaparHtml(ex.ubicacion_tipo)})</small>
                        </td>
                        <td class="text-end f-w-600">${parseFloat(ex.cantidad_actual).toFixed(2)} ${escaparHtml(ex.unidad_medida_simbolo)}</td>
                        <td class="text-end text-muted">${parseFloat(ex.cantidad_reservada).toFixed(2)} ${escaparHtml(ex.unidad_medida_simbolo)}</td>
                        <td class="text-end ${claseStock}">${disponible.toFixed(2)} ${escaparHtml(ex.unidad_medida_simbolo)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-primary btn-sm btn-transferir-rapido" data-art-id="${ex.articulo_id}" data-ubi-id="${ex.ubicacion_id}">
                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        } catch (e) {
            console.error('Error al cargar existencias:', e);
        }
    }

    // =========================================================================
    // 2. Carga del Catálogo de Artículos
    // =========================================================================

    async function cargarArticulos() {
        const termino = document.getElementById('filtro-articulos-termino')?.value || '';
        const categoria = document.getElementById('filtro-articulos-categoria')?.value || '';

        const params = new URLSearchParams();
        if (termino) params.append('termino', termino);
        if (categoria) params.append('categoria', categoria);

        try {
            const resp = await fetch(`/api/inventario/articulos?${params.toString()}`);
            const data = await resp.json();
            const tbody = document.querySelector('#tabla-articulos tbody');

            if (!data.exito || !data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No hay artículos registrados.</td></tr>';
                document.getElementById('kpi-total-articulos').textContent = '0';
                return;
            }

            articulosCache = data.datos;
            document.getElementById('kpi-total-articulos').textContent = data.datos.length;

            // Actualizar selects de artículos en modales
            actualizarSelectsArticulos(data.datos);

            let html = '';
            data.datos.forEach(art => {
                const stockTot = art.es_serializable ? '— (Serializado)' : `${parseFloat(art.stock_total || 0).toFixed(2)} ${escaparHtml(art.unidad_medida_simbolo)}`;
                const estadoBadge = art.estado === 'ACTIVO'
                    ? '<span class="badge bg-light-success text-success">Activo</span>'
                    : '<span class="badge bg-light-danger text-danger">Inactivo</span>';

                html += `
                    <tr>
                        <td class="f-w-700 f-s-13">${escaparHtml(art.codigo_sku)}</td>
                        <td>
                            <div class="f-w-600">${escaparHtml(art.nombre)}</div>
                            ${art.descripcion ? `<small class="text-muted">${escaparHtml(art.descripcion)}</small>` : ''}
                        </td>
                        <td>${badgeCategoria(art.categoria)}</td>
                        <td>${escaparHtml(art.unidad_medida_nombre)} (${escaparHtml(art.unidad_medida_simbolo)})</td>
                        <td class="text-end">S/ ${parseFloat(art.costo_referencial).toFixed(2)}</td>
                        <td class="text-end f-w-700">${stockTot}</td>
                        <td class="text-center">${estadoBadge}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-articulo" data-id="${art.id}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        } catch (e) {
            console.error('Error al cargar artículos:', e);
        }
    }

    function actualizarSelectsArticulos(articulos) {
        const selectMov = document.getElementById('mov-articulo-id');
        const selectAct = document.getElementById('activo-articulo-id');
        const selectDot = document.getElementById('dot-articulo-id');

        if (selectMov) {
            selectMov.innerHTML = '<option value="">Seleccione un artículo...</option>' +
                articulos.filter(a => !a.es_serializable).map(a => `<option value="${a.id}">${escaparHtml(a.nombre)} (${escaparHtml(a.codigo_sku)})</option>`).join('');
        }

        if (selectAct) {
            selectAct.innerHTML = '<option value="">Seleccione artículo serializable...</option>' +
                articulos.filter(a => a.es_serializable).map(a => `<option value="${a.id}">${escaparHtml(a.nombre)} (${escaparHtml(a.codigo_sku)})</option>`).join('');
        }

        if (selectDot) {
            selectDot.innerHTML = '<option value="">Seleccione un artículo...</option>' +
                articulos.map(a => `<option value="${a.id}">${escaparHtml(a.nombre)} (${escaparHtml(a.codigo_sku)})</option>`).join('');
        }
    }

    // =========================================================================
    // 3. Carga del Kardex / Movimientos
    // =========================================================================

    async function cargarKardex() {
        const tipo = document.getElementById('filtro-kardex-tipo')?.value || '';
        const params = new URLSearchParams();
        if (tipo) params.append('tipo_movimiento', tipo);

        try {
            const resp = await fetch(`/api/inventario/movimientos?${params.toString()}`);
            const data = await resp.json();
            const tbody = document.querySelector('#tabla-kardex tbody');

            if (!data.exito || !data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No se registran movimientos en el Kardex.</td></tr>';
                return;
            }

            let html = '';
            data.datos.forEach(m => {
                const esReverso = m.tipo_movimiento === 'REVERSO';
                const botonReverso = !esReverso
                    ? `<button type="button" class="btn btn-outline-danger btn-sm btn-reversar-mov" data-id="${m.id}" data-codigo="${escaparHtml(m.codigo)}" title="Reversar Movimiento">
                         <i class="fa-solid fa-rotate-left"></i>
                       </button>`
                    : '<span class="text-muted f-s-12">—</span>';

                html += `
                    <tr>
                        <td>
                            <div class="f-w-700 f-s-12">${escaparHtml(m.codigo)}</div>
                            <small class="text-muted">${escaparHtml(m.creado_en)}</small>
                        </td>
                        <td>${badgeTipoMovimiento(m.tipo_movimiento)}</td>
                        <td>
                            <div class="f-w-600 f-s-13">${escaparHtml(m.articulo_nombre)}</div>
                            <small class="text-muted">${escaparHtml(m.articulo_sku)}</small>
                        </td>
                        <td>${escaparHtml(m.ubicacion_nombre)}</td>
                        <td class="text-end f-w-700">${parseFloat(m.cantidad).toFixed(2)} ${escaparHtml(m.unidad_medida_simbolo)}</td>
                        <td class="text-end">S/ ${parseFloat(m.costo_total_historico).toFixed(2)}</td>
                        <td>
                            <div class="f-s-13">${escaparHtml(m.motivo)}</div>
                            ${m.correlativo_operacion ? `<small class="text-info"><i class="fa-solid fa-link me-1"></i> ${escaparHtml(m.correlativo_operacion)}</small>` : ''}
                        </td>
                        <td class="text-center">${botonReverso}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        } catch (e) {
            console.error('Error al cargar Kardex:', e);
        }
    }

    // =========================================================================
    // 4. Carga de Activos Serializados
    // =========================================================================

    async function cargarActivos() {
        const estado = document.getElementById('filtro-activos-estado')?.value || '';
        const params = new URLSearchParams();
        if (estado) params.append('estado', estado);

        try {
            const resp = await fetch(`/api/inventario/activos?${params.toString()}`);
            const data = await resp.json();
            const tbody = document.querySelector('#tabla-activos tbody');

            if (!data.exito || !data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay activos serializados registrados.</td></tr>';
                document.getElementById('kpi-total-activos').textContent = '0';
                return;
            }

            document.getElementById('kpi-total-activos').textContent = data.datos.length;

            let html = '';
            data.datos.forEach(act => {
                const deBaja = act.estado === 'DE_BAJA';
                const acciones = !deBaja
                    ? `
                        <button type="button" class="btn btn-outline-primary btn-sm btn-cambiar-ubicacion-activo" data-id="${act.id}" data-placa="${escaparHtml(act.codigo_placa)}" title="Cambiar Ubicación">
                            <i class="fa-solid fa-arrows-split-up-and-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-dar-baja-activo" data-id="${act.id}" data-placa="${escaparHtml(act.codigo_placa)}" title="Dar de Baja">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                      `
                    : '<span class="text-danger f-s-12 f-w-600">Baja formal</span>';

                html += `
                    <tr>
                        <td>
                            <div class="f-w-700 f-s-13"><i class="fa-solid fa-tag text-primary me-1"></i> ${escaparHtml(act.codigo_placa)}</div>
                            ${act.numero_serie_fabricante ? `<small class="text-muted">SN: ${escaparHtml(act.numero_serie_fabricante)}</small>` : ''}
                        </td>
                        <td>
                            <div class="f-w-600">${escaparHtml(act.articulo_nombre)}</div>
                            <small class="text-muted">${escaparHtml(act.articulo_sku)}</small>
                        </td>
                        <td>${escaparHtml(act.marca || '—')} / ${escaparHtml(act.modelo || '—')}</td>
                        <td>
                            <div class="f-w-600 f-s-13">${escaparHtml(act.ubicacion_nombre)}</div>
                            <small class="text-muted">${escaparHtml(act.propiedad_nombre || '')}</small>
                        </td>
                        <td>${badgeEstadoActivo(act.estado)}</td>
                        <td class="text-end">S/ ${parseFloat(act.costo_adquisicion).toFixed(2)}</td>
                        <td class="text-center">${acciones}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        } catch (e) {
            console.error('Error al cargar activos:', e);
        }
    }

    // =========================================================================
    // 5. Carga de Ubicaciones y Almacenes
    // =========================================================================

    async function cargarUbicaciones() {
        try {
            const resp = await fetch('/api/inventario/ubicaciones');
            const data = await resp.json();
            const tbody = document.querySelector('#tabla-ubicaciones tbody');

            if (!data.exito || !data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay ubicaciones registradas.</td></tr>';
                document.getElementById('kpi-total-ubicaciones').textContent = '0';
                return;
            }

            ubicacionesCache = data.datos;
            document.getElementById('kpi-total-ubicaciones').textContent = data.datos.length;

            // Actualizar selects de ubicaciones
            actualizarSelectsUbicaciones(data.datos);

            let html = '';
            data.datos.forEach(ubi => {
                const ref = ubi.tipo === 'UNIDAD'
                    ? `<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-door-open me-1"></i> Habitación ${escaparHtml(ubi.unidad_numero || '')}</span>`
                    : (ubi.tipo === 'CUSTODIA_EXTERNA'
                        ? `<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-truck me-1"></i> ${escaparHtml(ubi.proveedor_razon_social || '')}</span>`
                        : '<span class="text-muted">—</span>');

                html += `
                    <tr>
                        <td class="f-w-700 f-s-13">${escaparHtml(ubi.codigo)}</td>
                        <td>
                            <div class="f-w-600">${escaparHtml(ubi.nombre)}</div>
                            ${ubi.responsable_nombre ? `<small class="text-muted">Resp: ${escaparHtml(ubi.responsable_nombre)}</small>` : ''}
                        </td>
                        <td><span class="badge bg-light-info text-info">${escaparHtml(ubi.tipo)}</span></td>
                        <td>${escaparHtml(ubi.propiedad_nombre || '')}</td>
                        <td>${ref}</td>
                        <td class="text-center">
                            <span class="badge ${ubi.estado === 'ACTIVO' ? 'bg-light-success text-success' : 'bg-light-danger text-danger'}">
                                ${escaparHtml(ubi.estado)}
                            </span>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-ubicacion" data-id="${ubi.id}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        } catch (e) {
            console.error('Error al cargar ubicaciones:', e);
        }
    }

    function actualizarSelectsUbicaciones(ubicaciones) {
        const selectFiltro = document.getElementById('filtro-existencias-ubicacion');
        const selectMovUbi = document.getElementById('mov-ubicacion-id');
        const selectMovDest = document.getElementById('mov-ubicacion-destino-id');
        const selectActUbi = document.getElementById('activo-ubicacion-id');
        const selectTransf = document.getElementById('transf-nueva-ubicacion-id');

        const options = ubicaciones.filter(u => u.estado === 'ACTIVO').map(u =>
            `<option value="${u.id}">${escaparHtml(u.nombre)} (${escaparHtml(u.codigo)} - ${escaparHtml(u.tipo)})</option>`
        ).join('');

        if (selectFiltro) {
            selectFiltro.innerHTML = '<option value="">Todas las ubicaciones</option>' + options;
        }
        if (selectMovUbi) {
            selectMovUbi.innerHTML = '<option value="">Seleccione ubicación...</option>' + options;
        }
        if (selectMovDest) {
            selectMovDest.innerHTML = '<option value="">Seleccione destino...</option>' + options;
        }
        if (selectActUbi) {
            selectActUbi.innerHTML = '<option value="">Seleccione ubicación inicial...</option>' + options;
        }
        if (selectTransf) {
            selectTransf.innerHTML = '<option value="">Seleccione nueva ubicación...</option>' + options;
        }
    }

    // =========================================================================
    // 6. Carga y Auditoría de Dotaciones
    // =========================================================================

    async function cargarDotacionesEstandar() {
        try {
            const resp = await fetch('/api/inventario/dotaciones');
            const data = await resp.json();
            const tbody = document.querySelector('#tabla-dotaciones-estandar tbody');

            if (!data.exito || !data.datos || data.datos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No hay dotaciones configuradas.</td></tr>';
                return;
            }

            let html = '';
            data.datos.forEach(d => {
                const destino = d.tipo_unidad_id
                    ? `<span class="badge bg-light-info text-info">Tipo: ${escaparHtml(d.tipo_unidad_nombre || '')}</span>`
                    : `<span class="badge bg-light-primary text-primary">Hab. ${escaparHtml(d.unidad_numero || '')}</span>`;

                html += `
                    <tr>
                        <td>${destino}</td>
                        <td>
                            <div class="f-w-600 f-s-12">${escaparHtml(d.articulo_nombre)}</div>
                            <small class="text-muted">${escaparHtml(d.articulo_sku)}</small>
                        </td>
                        <td class="text-end f-w-700">${parseFloat(d.cantidad_estandar).toFixed(2)} ${escaparHtml(d.unidad_medida_simbolo)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-xs btn-eliminar-dotacion" data-id="${d.id}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        } catch (e) {
            console.error('Error al cargar dotaciones estándar:', e);
        }
    }

    // Botón de auditar unidad
    document.getElementById('btn-auditar-unidad')?.addEventListener('click', async () => {
        const unidadId = document.getElementById('auditoria-unidad-select')?.value;
        if (!unidadId) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione una unidad habitacional para auditar.' });
            return;
        }

        const contenedor = document.getElementById('resultado-auditoria-dotacion');
        contenedor.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Auditando dotación...</p></div>';

        try {
            const resp = await fetch(`/api/inventario/dotaciones/auditoria/${unidadId}`);
            const data = await resp.json();

            if (!data.exito || !data.datos || data.datos.length === 0) {
                contenedor.innerHTML = '<div class="alert alert-info py-3 text-center">Esta unidad no cuenta con dotaciones estándar asignadas.</div>';
                return;
            }

            let html = `
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-11">Artículo</th>
                                <th class="f-s-11">Tipo</th>
                                <th class="f-s-11 text-end">Estándar</th>
                                <th class="f-s-11 text-end">Real Físico</th>
                                <th class="f-s-11 text-end">Diferencia</th>
                                <th class="f-s-11 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.datos.forEach(row => {
                const cumple = row.cumple;
                const badgeCumple = cumple
                    ? '<span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i> Conforme</span>'
                    : '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Faltante</span>';

                const dif = parseFloat(row.diferencia);
                const difClase = dif < 0 ? 'text-danger f-w-700' : 'text-success f-w-700';

                html += `
                    <tr>
                        <td>
                            <div class="f-w-600 f-s-12">${escaparHtml(row.nombre)}</div>
                            <small class="text-muted">${escaparHtml(row.codigo_sku)}</small>
                        </td>
                        <td>${badgeCategoria(row.categoria)}</td>
                        <td class="text-end">${parseFloat(row.cantidad_estandar).toFixed(2)} ${escaparHtml(row.simbolo)}</td>
                        <td class="text-end f-w-600">${parseFloat(row.cantidad_real).toFixed(2)} ${escaparHtml(row.simbolo)}</td>
                        <td class="text-end ${difClase}">${dif > 0 ? '+' : ''}${dif.toFixed(2)} ${escaparHtml(row.simbolo)}</td>
                        <td class="text-center">${badgeCumple}</td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            contenedor.innerHTML = html;
        } catch (e) {
            contenedor.innerHTML = '<div class="alert alert-danger py-2">Error al consultar auditoría.</div>';
        }
    });

    // =========================================================================
    // 7. Acciones de Formularios y Modales
    // =========================================================================

    // Abrir modal artículo
    document.getElementById('btn-abrir-modal-articulo')?.addEventListener('click', () => {
        document.getElementById('form-articulo').reset();
        document.getElementById('articulo-id').value = '';
        document.getElementById('modal-articulo-titulo').innerHTML = '<i class="fa-solid fa-box text-primary me-2"></i> Registrar Artículo de Inventario';
        modalArticulo.show();
    });

    document.getElementById('btn-crear-articulo-tab')?.addEventListener('click', () => {
        document.getElementById('form-articulo').reset();
        document.getElementById('articulo-id').value = '';
        document.getElementById('modal-articulo-titulo').innerHTML = '<i class="fa-solid fa-box text-primary me-2"></i> Registrar Artículo de Inventario';
        modalArticulo.show();
    });

    // Guardar artículo
    document.getElementById('form-articulo')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('articulo-id').value;
        const payload = {
            codigo_sku: document.getElementById('articulo-sku').value,
            nombre: document.getElementById('articulo-nombre').value,
            categoria: document.getElementById('articulo-categoria').value,
            unidad_medida_id: document.getElementById('articulo-unidad-medida').value,
            costo_referencial: document.getElementById('articulo-costo').value,
            stock_minimo_alerta: document.getElementById('articulo-stock-min').value,
            descripcion: document.getElementById('articulo-descripcion').value,
        };

        const url = id ? `/api/inventario/articulos/${id}` : '/api/inventario/articulos';

        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (data.exito) {
                modalArticulo.hide();
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarArticulos();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al procesar la solicitud.' });
        }
    });

    // Abrir modal movimiento
    document.getElementById('btn-abrir-modal-movimiento')?.addEventListener('click', () => {
        document.getElementById('form-movimiento').reset();
        ajustarCamposSegunTipoMovimiento('ENTRADA');
        modalMovimiento.show();
    });

    // Cambio dinámico de tipo de movimiento
    document.getElementById('mov-tipo')?.addEventListener('change', (e) => {
        ajustarCamposSegunTipoMovimiento(e.target.value);
    });

    function ajustarCamposSegunTipoMovimiento(tipo) {
        const bloqueDestino = document.getElementById('bloque-ubicacion-destino');
        const bloqueCosto = document.getElementById('bloque-costo-unitario');
        const bloqueSubtipoAjuste = document.getElementById('bloque-tipo-ajuste');
        const labelUbi = document.getElementById('label-ubicacion-principal');

        if (tipo === 'TRASLADO') {
            bloqueDestino.classList.remove('d-none');
            labelUbi.textContent = 'Ubicación Origen *';
            bloqueCosto.classList.add('d-none');
            bloqueSubtipoAjuste.classList.add('d-none');
        } else if (tipo === 'AJUSTE') {
            bloqueDestino.classList.add('d-none');
            labelUbi.textContent = 'Ubicación / Almacén *';
            bloqueCosto.classList.add('d-none');
            bloqueSubtipoAjuste.classList.remove('d-none');
        } else if (tipo === 'CONSUMO') {
            bloqueDestino.classList.add('d-none');
            labelUbi.textContent = 'Ubicación / Almacén *';
            bloqueCosto.classList.add('d-none');
            bloqueSubtipoAjuste.classList.add('d-none');
        } else {
            // ENTRADA o SALDO_INICIAL
            bloqueDestino.classList.add('d-none');
            labelUbi.textContent = 'Ubicación / Almacén *';
            bloqueCosto.classList.remove('d-none');
            bloqueSubtipoAjuste.classList.add('d-none');
        }
    }

    // Procesar movimiento
    document.getElementById('form-movimiento')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const tipo = document.getElementById('mov-tipo').value;
        const artId = document.getElementById('mov-articulo-id').value;
        const ubiId = document.getElementById('mov-ubicacion-id').value;
        const cantidad = document.getElementById('mov-cantidad').value;
        const motivo = document.getElementById('mov-motivo').value;

        let endpoint = '/api/inventario/movimientos/entrada';
        let payload = { articulo_id: artId, ubicacion_id: ubiId, cantidad, motivo };

        if (tipo === 'CONSUMO') {
            endpoint = '/api/inventario/movimientos/consumo';
        } else if (tipo === 'SALDO_INICIAL') {
            endpoint = '/api/inventario/movimientos/saldo-inicial';
            payload.costo_unitario = document.getElementById('mov-costo-unitario').value;
        } else if (tipo === 'TRASLADO') {
            endpoint = '/api/inventario/movimientos/traslado';
            payload.ubicacion_origen_id = ubiId;
            payload.ubicacion_destino_id = document.getElementById('mov-ubicacion-destino-id').value;
        } else if (tipo === 'AJUSTE') {
            endpoint = '/api/inventario/movimientos/ajuste';
            payload.tipo_ajuste = document.getElementById('mov-subtipo-ajuste').value;
        } else {
            payload.costo_unitario = document.getElementById('mov-costo-unitario').value;
        }

        try {
            const resp = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (data.exito) {
                modalMovimiento.hide();
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarExistencias();
                cargarKardex();
                cargarArticulos();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al procesar el movimiento.' });
        }
    });

    // Reversar movimiento
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-reversar-mov');
        if (!btn) return;

        const movId = btn.dataset.id;
        const codigo = btn.dataset.codigo;

        const { value: motivo } = await Swal.fire({
            title: 'Reversar Movimiento',
            html: `Se neutralizará el movimiento <b>${escaparHtml(codigo)}</b> mediante un contra-asiento en el Kardex.`,
            input: 'text',
            inputLabel: 'Motivo de auditoría obligatorio:',
            inputPlaceholder: 'Explique el motivo del reverso...',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Reverso',
            confirmButtonColor: '#d33',
            inputValidator: (val) => {
                if (!val || !val.trim()) return 'Debe ingresar un motivo.';
            }
        });

        if (!motivo) return;

        try {
            const resp = await fetch(`/api/inventario/movimientos/${movId}/reverso`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ motivo }),
            });
            const data = await resp.json();

            if (data.exito) {
                Swal.fire({ icon: 'success', title: 'Reversado', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarExistencias();
                cargarKardex();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al ejecutar el reverso.' });
        }
    });

    // Abrir modal registrar activo
    document.getElementById('btn-abrir-modal-activo')?.addEventListener('click', () => {
        document.getElementById('form-activo').reset();
        modalActivo.show();
    });
    document.getElementById('btn-crear-activo-tab')?.addEventListener('click', () => {
        document.getElementById('form-activo').reset();
        modalActivo.show();
    });

    // Guardar activo
    document.getElementById('form-activo')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            articulo_id: document.getElementById('activo-articulo-id').value,
            codigo_placa: document.getElementById('activo-placa').value,
            numero_serie_fabricante: document.getElementById('activo-serie').value,
            marca: document.getElementById('activo-marca').value,
            modelo: document.getElementById('activo-modelo').value,
            propiedad_id: document.getElementById('activo-propiedad-id').value,
            ubicacion_id: document.getElementById('activo-ubicacion-id').value,
            costo_adquisicion: document.getElementById('activo-costo').value,
            fecha_adquisicion: document.getElementById('activo-fecha-adq').value,
        };

        try {
            const resp = await fetch('/api/inventario/activos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (data.exito) {
                modalActivo.hide();
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarActivos();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al registrar activo.' });
        }
    });

    // Cambiar ubicación de activo
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-cambiar-ubicacion-activo');
        if (!btn) return;

        document.getElementById('transf-activo-id').value = btn.dataset.id;
        document.getElementById('transf-activo-placa').value = btn.dataset.placa;
        modalTransfActivo.show();
    });

    document.getElementById('form-transferir-activo')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('transf-activo-id').value;
        const ubicacionId = document.getElementById('transf-nueva-ubicacion-id').value;

        try {
            const resp = await fetch(`/api/inventario/activos/${id}/transferir`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ ubicacion_id: ubicacionId }),
            });
            const data = await resp.json();

            if (data.exito) {
                modalTransfActivo.hide();
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarActivos();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al transferir activo.' });
        }
    });

    // Dar de baja activo
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-dar-baja-activo');
        if (!btn) return;

        document.getElementById('baja-activo-id').value = btn.dataset.id;
        document.getElementById('baja-motivo').value = '';
        modalBajaActivo.show();
    });

    document.getElementById('form-baja-activo')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('baja-activo-id').value;
        const motivoBaja = document.getElementById('baja-motivo').value;

        try {
            const resp = await fetch(`/api/inventario/activos/${id}/baja`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ motivo_baja: motivoBaja }),
            });
            const data = await resp.json();

            if (data.exito) {
                modalBajaActivo.hide();
                Swal.fire({ icon: 'success', title: 'Dado de Baja', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarActivos();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al procesar la baja.' });
        }
    });

    // Modal Ubicación
    document.getElementById('btn-crear-ubicacion-modal')?.addEventListener('click', () => {
        document.getElementById('form-ubicacion').reset();
        document.getElementById('bloque-ubi-unidad').classList.add('d-none');
        document.getElementById('bloque-ubi-proveedor').classList.add('d-none');
        modalUbicacion.show();
    });

    document.getElementById('ubi-tipo')?.addEventListener('change', (e) => {
        const tipo = e.target.value;
        const bloqueUnidad = document.getElementById('bloque-ubi-unidad');
        const bloqueProveedor = document.getElementById('bloque-ubi-proveedor');

        if (tipo === 'UNIDAD') {
            bloqueUnidad.classList.remove('d-none');
            bloqueProveedor.classList.add('d-none');
        } else if (tipo === 'CUSTODIA_EXTERNA') {
            bloqueUnidad.classList.add('d-none');
            bloqueProveedor.classList.remove('d-none');
        } else {
            bloqueUnidad.classList.add('d-none');
            bloqueProveedor.classList.add('d-none');
        }
    });

    document.getElementById('form-ubicacion')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            propiedad_id: document.getElementById('ubi-propiedad-id').value,
            codigo: document.getElementById('ubi-codigo').value,
            nombre: document.getElementById('ubi-nombre').value,
            tipo: document.getElementById('ubi-tipo').value,
            unidad_id: document.getElementById('ubi-unidad-id')?.value || null,
            proveedor_id: document.getElementById('ubi-proveedor-id')?.value || null,
        };

        try {
            const resp = await fetch('/api/inventario/ubicaciones', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (data.exito) {
                modalUbicacion.hide();
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarUbicaciones();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al guardar ubicación.' });
        }
    });

    // Modal Dotación
    document.getElementById('btn-nueva-dotacion-modal')?.addEventListener('click', () => {
        document.getElementById('form-dotacion').reset();
        modalDotacion.show();
    });

    document.querySelectorAll('input[name="dotacion_alcance"]')?.forEach(radio => {
        radio.addEventListener('change', (e) => {
            const tipo = e.target.value;
            const bloqueTipo = document.getElementById('bloque-dot-tipo-unidad');
            const bloqueUni = document.getElementById('bloque-dot-unidad');

            if (tipo === 'tipo_unidad') {
                bloqueTipo.classList.remove('d-none');
                bloqueUni.classList.add('d-none');
            } else {
                bloqueTipo.classList.add('d-none');
                bloqueUni.classList.remove('d-none');
            }
        });
    });

    document.getElementById('form-dotacion')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const alcance = document.querySelector('input[name="dotacion_alcance"]:checked').value;
        const payload = {
            tipo_unidad_id: alcance === 'tipo_unidad' ? document.getElementById('dot-tipo-unidad-id').value : null,
            unidad_id: alcance === 'unidad_especifica' ? document.getElementById('dot-unidad-id').value : null,
            articulo_id: document.getElementById('dot-articulo-id').value,
            cantidad_estandar: document.getElementById('dot-cantidad').value,
        };

        try {
            const resp = await fetch('/api/inventario/dotaciones', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();

            if (data.exito) {
                modalDotacion.hide();
                Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
                cargarDotacionesEstandar();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Falla al guardar dotación estándar.' });
        }
    });

    // Eliminar dotación estándar
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-eliminar-dotacion');
        if (!btn) return;

        const id = btn.dataset.id;
        const confirm = await Swal.fire({
            title: '¿Eliminar dotación estándar?',
            text: 'Esta acción removerá el requerimiento reglamentario para este artículo.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            confirmButtonColor: '#d33',
        });

        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch(`/api/inventario/dotaciones/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken,
                },
            });
            const data = await resp.json();

            if (data.exito) {
                cargarDotacionesEstandar();
            }
        } catch (e) {
            console.error('Error al eliminar dotación:', e);
        }
    });

    // Eventos de filtros y recarga
    document.getElementById('btn-recargar-existencias')?.addEventListener('click', cargarExistencias);
    document.getElementById('filtro-existencias-termino')?.addEventListener('input', cargarExistencias);
    document.getElementById('filtro-existencias-ubicacion')?.addEventListener('change', cargarExistencias);
    document.getElementById('filtro-existencias-categoria')?.addEventListener('change', cargarExistencias);

    document.getElementById('filtro-articulos-termino')?.addEventListener('input', cargarArticulos);
    document.getElementById('filtro-articulos-categoria')?.addEventListener('change', cargarArticulos);

    document.getElementById('btn-recargar-kardex')?.addEventListener('click', cargarKardex);
    document.getElementById('filtro-kardex-tipo')?.addEventListener('change', cargarKardex);

    document.getElementById('filtro-activos-estado')?.addEventListener('change', cargarActivos);

    // Inicialización global
    cargarUbicaciones();
    cargarArticulos();
    cargarExistencias();
    cargarKardex();
    cargarActivos();
    cargarDotacionesEstandar();
});
