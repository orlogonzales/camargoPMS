/**
 * Camargo PMS â€” MÃ³dulo de Suministros y Servicios PeriÃ³dicos (SUMINISTROS-1 / D-081)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API y SweetAlert2.
 * Principios vinculantes:
 * - SUMINISTRO != MEDIDOR != LECTURA != TARIFA != CONSUMO VALORIZADO != CARGO != PAGO.
 * - UbicaciÃ³n fÃ­sica (unidad) != Responsabilidad econÃ³mica (arrendatario titular).
 * - Precedencia tarifaria UNIDAD > PROPIEDAD > GLOBAL.
 * - Lecturas inmutables append-only y devengo atÃ³mico en cuentas folios de arrendamiento.
 * - D-071: Badges suaves bg-light-*, Font Awesome 6.3.0 exclusivo, cero degradados.
 * - GeometrÃ­a Alina nativa (border-radius 20px, app-form).
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Modales Bootstrap
    const modalLiquidarEl = document.getElementById('modal-liquidar');
    const modalMedidorEl = document.getElementById('modal-medidor');
    const modalLecturaEl = document.getElementById('modal-lectura');
    const modalCorregirLecEl = document.getElementById('modal-corregir-lectura');
    const modalDetalleLiqEl = document.getElementById('modal-detalle-liquidacion');
    const modalTarifaEl = document.getElementById('modal-tarifa');

    const modalLiquidar = modalLiquidarEl ? new bootstrap.Modal(modalLiquidarEl) : null;
    const modalMedidor = modalMedidorEl ? new bootstrap.Modal(modalMedidorEl) : null;
    const modalLectura = modalLecturaEl ? new bootstrap.Modal(modalLecturaEl) : null;
    const modalCorregirLec = modalCorregirLecEl ? new bootstrap.Modal(modalCorregirLecEl) : null;
    const modalDetalleLiq = modalDetalleLiqEl ? new bootstrap.Modal(modalDetalleLiqEl) : null;
    const modalTarifa = modalTarifaEl ? new bootstrap.Modal(modalTarifaEl) : null;

    // Cache local de datos
    let catalogos = {
        suministros: [],
        propiedades: [],
        unidades: [],
        arrendamientos: [],
    };

    let liquidacionesCache = [];
    let medidoresCache = [];
    let lecturasCache = [];
    let suministroSeleccionadoId = null;

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
        if (!isoString) return 'â€”';
        const d = new Date(isoString.replace(' ', 'T'));
        if (isNaN(d.getTime())) return isoString;
        return d.toLocaleDateString('es-PE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function badgeEstadoLiquidacion(estado) {
        switch (estado) {
            case 'DEVENGADO':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-check me-1"></i> Devengado</span>';
            case 'ANULADO':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-ban me-1"></i> Anulado</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoMedidor(estado) {
        switch (estado) {
            case 'ACTIVO':
                return '<span class="badge bg-light-primary text-primary f-w-600"><i class="fa-solid fa-circle-check me-1"></i> Activo</span>';
            case 'RETIRADO':
                return '<span class="badge bg-light-secondary text-secondary f-w-600"><i class="fa-solid fa-box-archive me-1"></i> Retirado</span>';
            case 'EN_REPARACION':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-screwdriver-wrench me-1"></i> En ReparaciÃ³n</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeTipoEvento(tipo) {
        switch (tipo) {
            case 'INSTALACION':
                return '<span class="badge bg-light-info text-info f-w-600">InstalaciÃ³n</span>';
            case 'PERIODICA':
                return '<span class="badge bg-light-primary text-primary f-w-600">PeriÃ³dica</span>';
            case 'CORTE_TARIFARIO':
                return '<span class="badge bg-light-warning text-warning f-w-600">Corte Tarifario</span>';
            case 'CORTE_CAMBIO_MEDIDOR':
            case 'CAMBIO_MEDIDOR':
                return '<span class="badge bg-light-secondary text-secondary f-w-600">Corte Medidor</span>';
            case 'CORTE_CONTRATO':
                return '<span class="badge bg-light-dark text-dark f-w-600">Corte Contrato</span>';
            case 'CORRECCION':
                return '<span class="badge bg-light-danger text-danger f-w-600">CorrecciÃ³n Auditada</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(tipo)}</span>`;
        }
    }

    async function peticionApi(url, opciones = {}) {
        const defaultHeaders = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        };

        if (opciones.body && typeof opciones.body === 'string') {
            defaultHeaders['Content-Type'] = 'application/json';
        }

        opciones.headers = Object.assign(defaultHeaders, opciones.headers || {});

        const res = await fetch(url, opciones);
        const data = await res.json().catch(() => ({ exito: false, error: 'Respuesta no parseable del servidor' }));

        if (!res.ok) {
            throw new Error(data.error || `Error ${res.status}: ${res.statusText}`);
        }

        return data;
    }

    // =========================================================================
    // 1. CARGA DE CATÃLOGOS AUXILIARES
    // =========================================================================

    async function cargarCatalogos() {
        try {
            const res = await peticionApi('/api/suministros/catalogos');
            if (res.exito && res.datos) {
                catalogos = res.datos;
                poblarSelects();
                renderizarListaSuministros();
            }
        } catch (err) {
            console.error('Error al cargar catÃ¡logos:', err);
        }
    }

    function poblarSelects() {
        // Selects de Suministros
        const selectLiqSum = document.getElementById('liq-suministro-id');
        const selectMedSum = document.getElementById('med-suministro-id');
        const selectTarSum = document.getElementById('tar-suministro-id');

        const optSuministros = catalogos.suministros.map(s =>
            `<option value="${s.id}">${escaparHtml(s.nombre)} (${escaparHtml(s.modalidad)})</option>`
        ).join('');

        if (selectLiqSum) selectLiqSum.innerHTML = '<option value="">Seleccione suministro...</option>' + optSuministros;
        if (selectMedSum) selectMedSum.innerHTML = '<option value="">Seleccione...</option>' + catalogos.suministros.filter(s => s.modalidad === 'MEDIDO').map(s => `<option value="${s.id}">${escaparHtml(s.nombre)}</option>`).join('');
        if (selectTarSum) selectTarSum.innerHTML = '<option value="">Seleccione suministro...</option>' + optSuministros;

        // Selects de Arrendamientos
        const selectLiqArr = document.getElementById('liq-arrendamiento-id');
        const selectLecArr = document.getElementById('lec-arrendamiento-id');

        const optArrendamientos = catalogos.arrendamientos.map(a =>
            `<option value="${a.id}">Contrato ${escaparHtml(a.codigo)} â€” Unidad ${escaparHtml(a.unidad_numero)} (${escaparHtml(a.propiedad_nombre)})</option>`
        ).join('');

        if (selectLiqArr) selectLiqArr.innerHTML = '<option value="">Seleccione contrato...</option>' + optArrendamientos;
        if (selectLecArr) selectLecArr.innerHTML = '<option value="">Ninguno / Opcional</option>' + optArrendamientos;

        // Selects de Unidades y Propiedades
        const selectMedUnid = document.getElementById('med-unidad-id');
        const selectTarProp = document.getElementById('tar-propiedad-id');
        const selectTarUnid = document.getElementById('tar-unidad-id');

        if (selectMedUnid) {
            selectMedUnid.innerHTML = '<option value="">Seleccione...</option>' + catalogos.unidades.map(u =>
                `<option value="${u.id}">Unidad ${escaparHtml(u.numero)} (${escaparHtml(u.propiedad_nombre)})</option>`
            ).join('');
        }

        if (selectTarProp) {
            selectTarProp.innerHTML = '<option value="">Seleccione propiedad...</option>' + catalogos.propiedades.map(p =>
                `<option value="${p.id}">${escaparHtml(p.nombre)}</option>`
            ).join('');
        }

        if (selectTarUnid) {
            selectTarUnid.innerHTML = '<option value="">Seleccione unidad...</option>' + catalogos.unidades.map(u =>
                `<option value="${u.id}">Unidad ${escaparHtml(u.numero)} (${escaparHtml(u.propiedad_nombre)})</option>`
            ).join('');
        }
    }

    // =========================================================================
    // 2. LIQUIDACIONES DE SUMINISTROS
    // =========================================================================

    async function cargarLiquidaciones() {
        const tbody = document.getElementById('tbody-liquidaciones');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando liquidaciones...</td></tr>';

        try {
            const res = await peticionApi('/api/suministros/liquidaciones');
            liquidacionesCache = res.datos || [];
            renderizarTablaLiquidaciones(liquidacionesCache);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error: ${escaparHtml(err.message)}</td></tr>`;
        }
    }

    function renderizarTablaLiquidaciones(lista) {
        const tbody = document.getElementById('tbody-liquidaciones');
        if (!tbody) return;

        if (lista.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No se registran liquidaciones devengadas.</td></tr>';
            return;
        }

        tbody.innerHTML = lista.map(l => {
            const esActiva = l.estado === 'DEVENGADO';
            return `
                <tr>
                    <td class="f-w-700 text-primary">${escaparHtml(l.folio)} ${l.revision > 1 ? `<span class="badge bg-light-warning text-warning ms-1">Rev.${l.revision}</span>` : ''}</td>
                    <td><span class="badge bg-light-secondary text-dark">${escaparHtml(l.arrendamiento_codigo || l.arrendamiento_id)}</span></td>
                    <td>
                        <div class="f-w-600">Unidad ${escaparHtml(l.unidad_numero || l.unidad_id)}</div>
                        <div class="f-s-11 text-muted">${escaparHtml(l.propiedad_nombre || '')}</div>
                    </td>
                    <td>
                        <span class="f-w-600">${escaparHtml(l.suministro_nombre || l.suministro_id)}</span>
                        <div class="f-s-11 text-muted">${escaparHtml(l.modalidad)}</div>
                    </td>
                    <td>
                        <div class="f-s-12">${formatearFecha(l.periodo_desde)} al ${formatearFecha(l.periodo_hasta)}</div>
                    </td>
                    <td class="text-end f-w-600">${formatearNumero(l.cantidad_total, 4)}</td>
                    <td class="text-end f-w-700 text-primary">S/ ${formatearNumero(l.total, 2)}</td>
                    <td class="text-center">${badgeEstadoLiquidacion(l.estado)}</td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-ver-detalle-liq" data-id="${l.id}" title="Ver detalle de tramos">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            ${esActiva ? `
                            <button type="button" class="btn btn-outline-warning btn-reliquidar" data-id="${l.id}" title="Reliquidar por correcciÃ³n">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-anular-liq" data-id="${l.id}" title="Anular liquidaciÃ³n">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // =========================================================================
    // 3. MEDIDORES FÃSICOS
    // =========================================================================

    async function cargarMedidores() {
        const tbody = document.getElementById('tbody-medidores');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando medidores...</td></tr>';

        try {
            const res = await peticionApi('/api/suministros/medidores');
            medidoresCache = res.datos || [];
            renderizarTablaMedidores(medidoresCache);
            actualizarSelectMedidoresLecturas();
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error: ${escaparHtml(err.message)}</td></tr>`;
        }
    }

    function renderizarTablaMedidores(lista) {
        const tbody = document.getElementById('tbody-medidores');
        if (!tbody) return;

        if (lista.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No se registran medidores instalados.</td></tr>';
            return;
        }

        tbody.innerHTML = lista.map(m => `
            <tr>
                <td class="f-w-700 text-dark">
                    <i class="fa-solid fa-gauge text-muted me-1"></i> ${escaparHtml(m.numero_serie)}
                    ${m.codigo_interno ? `<div class="f-s-11 text-muted">CÃ³d: ${escaparHtml(m.codigo_interno)}</div>` : ''}
                </td>
                <td><span class="badge bg-light-primary text-primary f-w-600">${escaparHtml(m.suministro_nombre || m.suministro_id)}</span></td>
                <td class="f-w-600">Unidad ${escaparHtml(m.unidad_numero || m.unidad_id)}</td>
                <td class="text-secondary">${escaparHtml(m.propiedad_nombre || '')}</td>
                <td>${formatearFecha(m.fecha_instalacion)}</td>
                <td class="text-end f-w-600">${formatearNumero(m.lectura_inicial, 4)}</td>
                <td class="text-center">
                    ${m.permite_rollover ? '<span class="badge bg-light-success text-success"><i class="fa-solid fa-check"></i> SÃ­</span>' : '<span class="badge bg-light-secondary text-secondary">No</span>'}
                </td>
                <td class="text-center">${badgeEstadoMedidor(m.estado)}</td>
                <td class="text-center">
                    ${m.estado === 'ACTIVO' ? `
                    <button type="button" class="btn btn-sm btn-outline-warning btn-reemplazar-medidor" data-id="${m.id}" data-serie="${escaparHtml(m.numero_serie)}" title="Reemplazar medidor">
                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Reemplazar
                    </button>
                    ` : '<span class="text-muted f-s-12">â€”</span>'}
                </td>
            </tr>
        `).join('');
    }

    function actualizarSelectMedidoresLecturas() {
        const selectFiltro = document.getElementById('filtro-lecturas-medidor');
        const selectModal = document.getElementById('lec-medidor-id');

        const opciones = medidoresCache.map(m =>
            `<option value="${m.id}">Serie: ${escaparHtml(m.numero_serie)} â€” Unidad ${escaparHtml(m.unidad_numero || m.unidad_id)} (${m.estado})</option>`
        ).join('');

        if (selectFiltro) {
            selectFiltro.innerHTML = '<option value="">Seleccione un medidor...</option>' + opciones;
        }

        if (selectModal) {
            selectModal.innerHTML = '<option value="">Seleccione medidor...</option>' +
                medidoresCache.filter(m => m.estado === 'ACTIVO').map(m =>
                    `<option value="${m.id}">Serie: ${escaparHtml(m.numero_serie)} â€” Unidad ${escaparHtml(m.unidad_numero || m.unidad_id)}</option>`
                ).join('');
        }
    }

    // =========================================================================
    // 4. LECTURAS FÃSICAS
    // =========================================================================

    async function cargarLecturasMedidor(medidorId) {
        const tbody = document.getElementById('tbody-lecturas');
        if (!tbody) return;

        if (!medidorId) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Seleccione un medidor para consultar su bitÃ¡cora inmutable de lecturas.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando lecturas...</td></tr>';

        try {
            const res = await peticionApi(`/api/suministros/medidores/${medidorId}/lecturas?todas=1`);
            lecturasCache = res.datos || [];
            renderizarTablaLecturas(lecturasCache);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error: ${escaparHtml(err.message)}</td></tr>`;
        }
    }

    function renderizarTablaLecturas(lista) {
        const tbody = document.getElementById('tbody-lecturas');
        if (!tbody) return;

        if (lista.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se registran lecturas para el medidor seleccionado.</td></tr>';
            return;
        }

        tbody.innerHTML = lista.map(l => {
            const esVigente = l.estado === 'VIGENTE';
            return `
                <tr>
                    <td class="f-w-700 text-muted">#${l.id}</td>
                    <td>${formatearFecha(l.fecha_lectura)}</td>
                    <td>${badgeTipoEvento(l.tipo_evento)}</td>
                    <td class="text-end f-w-700 ${esVigente ? 'text-primary' : 'text-decoration-line-through text-muted'}">
                        ${formatearNumero(l.valor_lectura, 4)}
                    </td>
                    <td class="f-s-12">
                        ${escaparHtml(l.motivo || 'â€”')}
                        ${l.lectura_referencia_id ? `<span class="badge bg-light-warning text-dark ms-1">Ref: #${l.lectura_referencia_id}</span>` : ''}
                    </td>
                    <td class="text-center">
                        ${esVigente
                            ? '<span class="badge bg-light-success text-success">Vigente</span>'
                            : '<span class="badge bg-light-secondary text-secondary">Corregida</span>'}
                    </td>
                    <td class="text-center">
                        ${esVigente ? `
                        <button type="button" class="btn btn-sm btn-outline-warning btn-corregir-lec" data-id="${l.id}" data-valor="${l.valor_lectura}" title="Corregir lectura">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        ` : '<span class="text-muted f-s-12">â€”</span>'}
                    </td>
                </tr>
            `;
        }).join('');
    }

    // =========================================================================
    // 5. CATÃLOGO Y TARIFAS HISTÃ“RICAS
    // =========================================================================

    function renderizarListaSuministros() {
        const listaEl = document.getElementById('lista-suministros');
        if (!listaEl) return;

        if (catalogos.suministros.length === 0) {
            listaEl.innerHTML = '<li class="list-group-item text-center py-3 text-muted">No hay suministros dados de alta.</li>';
            return;
        }

        listaEl.innerHTML = catalogos.suministros.map(s => `
            <a href="#" class="list-group-item list-group-item-action item-suministro-catalogo py-3 ${suministroSeleccionadoId === s.id ? 'active' : ''}" data-id="${s.id}">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <h6 class="mb-1 f-w-700">${escaparHtml(s.nombre)}</h6>
                    <span class="badge ${s.modalidad === 'MEDIDO' ? 'bg-light-primary text-primary' : 'bg-light-info text-info'}">${escaparHtml(s.modalidad)}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center text-muted f-s-12 mt-1">
                    <span>CÃ³d: <code>${escaparHtml(s.codigo)}</code></span>
                    <span>Unidad: <strong>${escaparHtml(s.unidad_medida)}</strong></span>
                </div>
            </a>
        `).join('');

        // Seleccionar el primero por defecto si no hay selecciÃ³n
        if (!suministroSeleccionadoId && catalogos.suministros.length > 0) {
            seleccionarSuministro(catalogos.suministros[0].id);
        }
    }

    async function seleccionarSuministro(suministroId) {
        suministroSeleccionadoId = suministroId;
        renderizarListaSuministros();

        const tbody = document.getElementById('tbody-tarifas');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando tarifas...</td></tr>';

        try {
            const res = await peticionApi(`/api/suministros/${suministroId}/tarifas`);
            const tarifas = res.datos || [];

            if (tarifas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No se registran tarifas para este suministro.</td></tr>';
                return;
            }

            tbody.innerHTML = tarifas.map(t => {
                let badgeAmbito = '';
                let detalleAmbito = 'General (Global)';

                if (t.ambito === 'UNIDAD') {
                    badgeAmbito = '<span class="badge bg-light-danger text-danger">UNIDAD</span>';
                    detalleAmbito = `Unidad #${t.unidad_id}`;
                } else if (t.ambito === 'PROPIEDAD') {
                    badgeAmbito = '<span class="badge bg-light-warning text-warning">PROPIEDAD</span>';
                    detalleAmbito = `Propiedad #${t.propiedad_id}`;
                } else {
                    badgeAmbito = '<span class="badge bg-light-primary text-primary">GLOBAL</span>';
                }

                return `
                    <tr>
                        <td>${badgeAmbito}</td>
                        <td class="f-w-600">${escaparHtml(detalleAmbito)}</td>
                        <td class="text-end f-w-700 text-success">S/ ${formatearNumero(t.precio_unitario, 4)}</td>
                        <td>${formatearFecha(t.fecha_inicio)}</td>
                        <td>${t.fecha_fin ? formatearFecha(t.fecha_fin) : '<span class="badge bg-light-info text-info">Indefinida</span>'}</td>
                        <td class="text-center">
                            ${t.estado === 'ACTIVO' ? '<span class="badge bg-light-success text-success">Activa</span>' : '<span class="badge bg-light-secondary text-secondary">Inactiva</span>'}
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error: ${escaparHtml(err.message)}</td></tr>`;
        }
    }

    // =========================================================================
    // EVENTOS Y MODALES
    // =========================================================================

    // Botones de Apertura de Modales
    document.getElementById('btn-abrir-modal-liquidar')?.addEventListener('click', () => {
        document.getElementById('form-liquidar')?.reset();
        modalLiquidar?.show();
    });

    document.getElementById('btn-abrir-modal-medidor')?.addEventListener('click', () => {
        document.getElementById('form-medidor')?.reset();
        modalMedidor?.show();
    });

    document.getElementById('btn-abrir-modal-lectura')?.addEventListener('click', () => {
        document.getElementById('form-lectura')?.reset();
        modalLectura?.show();
    });

    document.getElementById('btn-abrir-modal-tarifa')?.addEventListener('click', () => {
        document.getElementById('form-tarifa')?.reset();
        if (suministroSeleccionadoId) {
            const sel = document.getElementById('tar-suministro-id');
            if (sel) sel.value = suministroSeleccionadoId;
        }
        document.getElementById('tar-ambito')?.dispatchEvent(new Event('change'));
        modalTarifa?.show();
    });

    // Filtro dinÃ¡mico en Modal Tarifa segÃºn Ãmbito
    document.getElementById('tar-ambito')?.addEventListener('change', (e) => {
        const val = e.target.value;
        const colProp = document.getElementById('tar-col-propiedad');
        const colUnid = document.getElementById('tar-col-unidad');

        if (val === 'GLOBAL') {
            if (colProp) colProp.style.display = 'none';
            if (colUnid) colUnid.style.display = 'none';
        } else if (val === 'PROPIEDAD') {
            if (colProp) colProp.style.display = 'block';
            if (colUnid) colUnid.style.display = 'none';
        } else if (val === 'UNIDAD') {
            if (colProp) colProp.style.display = 'none';
            if (colUnid) colUnid.style.display = 'block';
        }
    });

    // Refrescar
    document.getElementById('btn-refrescar-liquidaciones')?.addEventListener('click', cargarLiquidaciones);
    document.getElementById('btn-refrescar-medidores')?.addEventListener('click', cargarMedidores);
    document.getElementById('btn-refrescar-lecturas')?.addEventListener('click', () => {
        const id = document.getElementById('filtro-lecturas-medidor')?.value;
        cargarLecturasMedidor(id);
    });

    document.getElementById('filtro-lecturas-medidor')?.addEventListener('change', (e) => {
        cargarLecturasMedidor(e.target.value);
    });

    // DelegaciÃ³n: SelecciÃ³n de suministro en catÃ¡logo
    document.getElementById('lista-suministros')?.addEventListener('click', (e) => {
        const a = e.target.closest('.item-suministro-catalogo');
        if (a) {
            e.preventDefault();
            const id = parseInt(a.dataset.id, 10);
            seleccionarSuministro(id);
        }
    });

    // DelegaciÃ³n: Ver Detalle LiquidaciÃ³n
    document.getElementById('tbody-liquidaciones')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-ver-detalle-liq');
        if (!btn) return;

        const id = btn.dataset.id;
        try {
            const res = await peticionApi(`/api/suministros/liquidaciones/${id}`);
            const liq = res.datos;

            document.getElementById('det-liq-folio').textContent = liq.folio;
            document.getElementById('det-liq-modalidad').textContent = liq.modalidad;
            document.getElementById('det-liq-periodo').textContent = `${formatearFecha(liq.periodo_desde)} al ${formatearFecha(liq.periodo_hasta)}`;
            document.getElementById('det-liq-total').textContent = `S/ ${formatearNumero(liq.total, 2)}`;

            const tbodyTramos = document.getElementById('det-liq-tramos-tbody');
            if (tbodyTramos) {
                tbodyTramos.innerHTML = (liq.tramos || []).map(t => `
                    <tr>
                        <td><strong>Tramo #${t.numero_tramo}</strong></td>
                        <td>${formatearFecha(t.fecha_desde)} al ${formatearFecha(t.fecha_hasta)}</td>
                        <td class="text-end">${t.lectura_anterior_valor ? formatearNumero(t.lectura_anterior_valor, 4) : 'â€”'}</td>
                        <td class="text-end">${t.lectura_actual_valor ? formatearNumero(t.lectura_actual_valor, 4) : 'â€”'}</td>
                        <td class="text-end f-w-600">${formatearNumero(t.cantidad, 4)}</td>
                        <td class="text-end">S/ ${formatearNumero(t.tarifa_valor, 4)}</td>
                        <td class="text-end f-w-700 text-primary">S/ ${formatearNumero(t.total, 2)}</td>
                    </tr>
                `).join('');
            }

            modalDetalleLiq?.show();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    });

    // DelegaciÃ³n: Anular LiquidaciÃ³n
    document.getElementById('tbody-liquidaciones')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-anular-liq');
        if (!btn) return;

        const id = btn.dataset.id;
        const { value: motivo } = await Swal.fire({
            title: 'Â¿Anular LiquidaciÃ³n y Cargo?',
            text: 'Se desaplicarÃ¡n los pagos vinculados (si existieran) y se anularÃ¡ formalmente el cargo financiero devengado en la cuenta folio (D-081).',
            input: 'textarea',
            inputPlaceholder: 'Ingrese el motivo obligatorio de anulaciÃ³n...',
            inputAttributes: { required: 'true' },
            showCancelButton: true,
            confirmButtonText: 'SÃ­, anular cargo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });

        if (motivo) {
            try {
                const res = await peticionApi(`/api/suministros/liquidaciones/${id}/anular`, {
                    method: 'POST',
                    body: JSON.stringify({ motivo }),
                });
                Swal.fire('Ã‰xito', res.mensaje || 'LiquidaciÃ³n anulada.', 'success');
                cargarLiquidaciones();
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });

    // DelegaciÃ³n: Reliquidar por CorrecciÃ³n
    document.getElementById('tbody-liquidaciones')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-reliquidar');
        if (!btn) return;

        const id = btn.dataset.id;
        const { value: motivo } = await Swal.fire({
            title: 'Reliquidar PerÃ­odo',
            text: 'Esta acciÃ³n anula la liquidaciÃ³n actual y emite una nueva revisiÃ³n con el consumo corregido, reaplicando pagos previos automÃ¡ticamente (D-081).',
            input: 'text',
            inputPlaceholder: 'Motivo de la reliquidaciÃ³n (ej. correcciÃ³n de lectura previa)...',
            inputAttributes: { required: 'true' },
            showCancelButton: true,
            confirmButtonText: 'Reliquidar ahora',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
        });

        if (motivo) {
            try {
                const res = await peticionApi(`/api/suministros/liquidaciones/${id}/reliquidar`, {
                    method: 'POST',
                    body: JSON.stringify({ motivo }),
                });
                Swal.fire('Ã‰xito', res.mensaje || 'ReliquidaciÃ³n devengada.', 'success');
                cargarLiquidaciones();
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });

    // DelegaciÃ³n: Abrir Modal Corregir Lectura
    document.getElementById('tbody-lecturas')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-corregir-lec');
        if (!btn) return;

        const id = btn.dataset.id;
        const valor = btn.dataset.valor;

        document.getElementById('corr-lectura-id').value = id;
        document.getElementById('corr-valor-original').value = valor;
        document.getElementById('corr-nuevo-valor').value = valor;
        document.getElementById('corr-motivo').value = '';

        modalCorregirLec?.show();
    });

    // DelegaciÃ³n: Reemplazar Medidor
    document.getElementById('tbody-medidores')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-reemplazar-medidor');
        if (!btn) return;

        const id = btn.dataset.id;
        const serie = btn.dataset.serie;

        const { value: formValues } = await Swal.fire({
            title: `Reemplazar Medidor ${serie}`,
            html: `
                <div class="text-start">
                    <label class="form-label f-s-12 f-w-600 mb-1">Lectura Final del Medidor Saliente *</label>
                    <input type="number" step="0.0001" id="swal-lec-final" class="form-control mb-3" placeholder="0.0000">
                    <label class="form-label f-s-12 f-w-600 mb-1">NÃºmero de Serie del Nuevo Medidor *</label>
                    <input type="text" id="swal-serie-nueva" class="form-control mb-3" placeholder="Ej. MED-ELEC-402">
                    <label class="form-label f-s-12 f-w-600 mb-1">Lectura Inicial del Nuevo Medidor *</label>
                    <input type="number" step="0.0001" id="swal-lec-inicial-nueva" class="form-control mb-3" value="0.0000">
                    <label class="form-label f-s-12 f-w-600 mb-1">Fecha de Cambio *</label>
                    <input type="date" id="swal-fecha-corte" class="form-control" value="${new Date().toISOString().split('T')[0]}">
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Ejecutar Reemplazo AtÃ³mico',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const lecFinal = document.getElementById('swal-lec-final')?.value;
                const serieNueva = document.getElementById('swal-serie-nueva')?.value;
                const lecIniNueva = document.getElementById('swal-lec-inicial-nueva')?.value;
                const fechaCorte = document.getElementById('swal-fecha-corte')?.value;

                if (!lecFinal || !serieNueva || !lecIniNueva || !fechaCorte) {
                    Swal.showValidationMessage('Todos los campos son obligatorios');
                    return false;
                }

                return {
                    fecha_corte: fechaCorte,
                    lectura_final: lecFinal,
                    nuevo_medidor: {
                        numero_serie: serieNueva,
                        lectura_inicial: lecIniNueva,
                    }
                };
            }
        });

        if (formValues) {
            try {
                const res = await peticionApi(`/api/suministros/medidores/${id}/reemplazar`, {
                    method: 'POST',
                    body: JSON.stringify(formValues),
                });
                Swal.fire('Ã‰xito', res.mensaje || 'Reemplazo completado.', 'success');
                cargarMedidores();
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        }
    });

    // Formulario: Liquidar Suministro
    document.getElementById('form-liquidar')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const btn = document.getElementById('btn-submit-liquidar');
        if (btn) btn.disabled = true;

        try {
            const res = await peticionApi('/api/suministros/liquidaciones', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            modalLiquidar?.hide();
            Swal.fire('LiquidaciÃ³n Emitida', res.mensaje || 'Cargo devengado exitosamente en cuenta folio.', 'success');
            cargarLiquidaciones();
        } catch (err) {
            Swal.fire('Error al Liquidar', err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Formulario: Instalar Medidor
    document.getElementById('form-medidor')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        payload.permite_rollover = form.querySelector('[name="permite_rollover"]')?.checked ? 1 : 0;

        const btn = document.getElementById('btn-submit-medidor');
        if (btn) btn.disabled = true;

        try {
            const res = await peticionApi('/api/suministros/medidores', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            modalMedidor?.hide();
            Swal.fire('Medidor Instalado', res.mensaje || 'Medidor registrado con Ã©xito.', 'success');
            cargarMedidores();
        } catch (err) {
            Swal.fire('Error al Instalar', err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Formulario: Registrar Lectura
    document.getElementById('form-lectura')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const btn = document.getElementById('btn-submit-lectura');
        if (btn) btn.disabled = true;

        try {
            const res = await peticionApi('/api/suministros/lecturas', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            modalLectura?.hide();
            Swal.fire('Lectura Registrada', res.mensaje || 'Lectura asentada con Ã©xito.', 'success');

            const medidorFiltro = document.getElementById('filtro-lecturas-medidor');
            if (medidorFiltro) {
                medidorFiltro.value = payload.medidor_id;
                cargarLecturasMedidor(payload.medidor_id);
            }
        } catch (err) {
            Swal.fire('Error al Registrar Lectura', err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Formulario: Corregir Lectura
    document.getElementById('form-corregir-lectura')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const id = document.getElementById('corr-lectura-id')?.value;
        const payload = {
            nuevo_valor: document.getElementById('corr-nuevo-valor')?.value,
            motivo: document.getElementById('corr-motivo')?.value,
        };

        const btn = document.getElementById('btn-submit-corregir-lectura');
        if (btn) btn.disabled = true;

        try {
            const res = await peticionApi(`/api/suministros/lecturas/${id}/corregir`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            modalCorregirLec?.hide();
            Swal.fire('Lectura Corregida', res.mensaje || 'CorrecciÃ³n auditada asentada con Ã©xito.', 'success');

            const medidorId = document.getElementById('filtro-lecturas-medidor')?.value;
            if (medidorId) cargarLecturasMedidor(medidorId);
        } catch (err) {
            Swal.fire('Error al Corregir', err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Formulario: Nueva Tarifa
    document.getElementById('form-tarifa')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const btn = document.getElementById('btn-submit-tarifa');
        if (btn) btn.disabled = true;

        try {
            const res = await peticionApi('/api/suministros/tarifas', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            modalTarifa?.hide();
            Swal.fire('Tarifa Registrada', res.mensaje || 'Tarifa agregada al catÃ¡logo histÃ³rico.', 'success');
            if (suministroSeleccionadoId) {
                seleccionarSuministro(suministroSeleccionadoId);
            }
        } catch (err) {
            Swal.fire('Error al Crear Tarifa', err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // =========================================================================
    // INICIALIZACIÃ“N
    // =========================================================================
    cargarCatalogos();
    cargarLiquidaciones();
    cargarMedidores();
});
