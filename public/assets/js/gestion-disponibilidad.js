/**
 * Camargo PMS — Módulo de Gestión de Disponibilidad e Inventario Diario (DISPONIBILIDAD-1)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - D-066: INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL. Intervalo semiabierto [entrada, salida).
 * - D-067: Modelo Híbrido Sparse con UNIQUE(unidad_id, fecha) en InnoDB.
 * - P-005 PENDIENTE: Cero monedas, tarifas o impuestos en esta fase.
 */
document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------------------
    // 1. Elementos del DOM y Estado del Módulo
    // -------------------------------------------------------------------------
    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // KPIs
    const kpiTotal = document.getElementById('kpi-total-unidades');
    const kpiDisponibles = document.getElementById('kpi-unidades-disponibles');
    const kpiBloqueadas = document.getElementById('kpi-unidades-bloqueadas');
    const kpiTasa = document.getElementById('kpi-tasa-disponibilidad');

    // Tab 1: Consulta
    const formConsulta = document.getElementById('form-consulta-disponibilidad');
    const inputEntrada = document.getElementById('consulta-fecha-entrada');
    const inputSalida = document.getElementById('consulta-fecha-salida');
    const selectPropiedad = document.getElementById('consulta-propiedad-id');
    const selectTipo = document.getElementById('consulta-tipo-unidad-id');
    const checkSoloDisponibles = document.getElementById('check-solo-disponibles');
    const tbodyDisponibilidad = document.getElementById('tbody-disponibilidad');
    const infoRangoTexto = document.getElementById('info-rango-texto');
    const infoNochesTexto = document.getElementById('info-noches-texto');
    const badgeTotalConsultadas = document.getElementById('badge-total-consultadas');

    // Tab 2: Matriz (Rack)
    const selectMatrizPropiedad = document.getElementById('matriz-propiedad-id');
    const selectMatrizMes = document.getElementById('matriz-mes-selector');
    const btnRecargarMatriz = document.getElementById('btn-recargar-matriz');
    const theadRack = document.getElementById('thead-rack');
    const tbodyRack = document.getElementById('tbody-rack');

    // Tab 3: Bloqueos
    const filtroBloqueosPropiedad = document.getElementById('filtro-bloqueos-propiedad');
    const filtroBloqueosEstado = document.getElementById('filtro-bloqueos-estado');
    const filtroBloqueosTipo = document.getElementById('filtro-bloqueos-tipo');
    const btnRecargarBloqueos = document.getElementById('btn-recargar-bloqueos');
    const tbodyBloqueos = document.getElementById('tbody-bloqueos-maestro');
    const infoPaginacionBloqueos = document.getElementById('bloqueos-info-paginacion');
    const btnPaginacionBloqueos = document.getElementById('bloqueos-btn-paginacion');

    // Modal Crear Bloqueo
    const modalCrearBloqueoEl = document.getElementById('modal-crear-bloqueo');
    const modalCrearBloqueo = modalCrearBloqueoEl ? new bootstrap.Modal(modalCrearBloqueoEl) : null;
    const formCrearBloqueo = document.getElementById('form-crear-bloqueo');
    const bloqueoPropiedadSelect = document.getElementById('bloqueo-propiedad-id');
    const bloqueoUnidadSelect = document.getElementById('bloqueo-unidad-id');
    const bloqueoFechaInicio = document.getElementById('bloqueo-fecha-inicio');
    const bloqueoFechaFin = document.getElementById('bloqueo-fecha-fin');
    const bloqueoTipo = document.getElementById('bloqueo-tipo');
    const bloqueoMotivo = document.getElementById('bloqueo-motivo');
    const btnAbrirNuevoBloqueo = document.getElementById('btn-abrir-nuevo-bloqueo');
    const btnGuardarBloqueo = document.getElementById('btn-guardar-bloqueo');
    const spinnerGuardarBloqueo = document.getElementById('spinner-guardar-bloqueo');

    // Modal Liberar Bloqueo
    const modalLiberarEl = document.getElementById('modal-liberar-bloqueo');
    const modalLiberar = modalLiberarEl ? new bootstrap.Modal(modalLiberarEl) : null;
    const formLiberar = document.getElementById('form-liberar-bloqueo');
    const inputLiberarBloqueoId = document.getElementById('liberar-bloqueo-id');
    const mensajeConfirmacionLiberar = document.getElementById('mensaje-confirmacion-liberar');
    const btnConfirmarLiberacion = document.getElementById('btn-confirmar-liberacion');
    const spinnerLiberar = document.getElementById('spinner-liberar-bloqueo');

    let estadoBloqueos = {
        pagina: 1,
        limite: 15,
        total: 0,
        paginasTotales: 1,
    };

    let pristineBloqueo = null;
    if (formCrearBloqueo && typeof Pristine !== 'undefined') {
        pristineBloqueo = new Pristine(formCrearBloqueo, {
            classTo: 'mb-3,col-6',
            errorClass: 'has-danger',
            successClass: 'has-success',
            errorTextParent: 'mb-3,col-6',
            errorTextTag: 'div',
            errorTextClass: 'text-danger f-s-12 mt-1',
        });
    }

    // -------------------------------------------------------------------------
    // 2. Funciones de Notificación (SweetAlert2 o fallback nativo)
    // -------------------------------------------------------------------------
    function notificar(icono, titulo, mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icono,
                title: titulo,
                text: mensaje,
                confirmButtonColor: '#0d6efd',
            });
        } else {
            alert(`${titulo}: ${mensaje}`);
        }
    }

    function notificarToast(icono, mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icono,
                title: mensaje,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
        }
    }

    // -------------------------------------------------------------------------
    // 3. Tab 1: Consulta de Disponibilidad
    // -------------------------------------------------------------------------
    async function ejecutarConsultaDisponibilidad() {
        if (!tbodyDisponibilidad) return;

        const entrada = inputEntrada?.value || '';
        const salida = inputSalida?.value || '';
        const propiedadId = selectPropiedad?.value || '';
        const tipoId = selectTipo?.value || '';
        const soloDisponibles = checkSoloDisponibles?.checked ? '1' : '0';

        if (!entrada || !salida) {
            notificar('warning', 'Campos requeridos', 'Debe seleccionar fechas válidas de entrada y salida.');
            return;
        }

        tbodyDisponibilidad.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Consultando inventario diario...
                </td>
            </tr>
        `;

        try {
            const params = new URLSearchParams({
                fecha_entrada: entrada,
                fecha_salida: salida,
                propiedad_id: propiedadId,
                tipo_unidad_id: tipoId,
                solo_disponibles: soloDisponibles,
            });

            const resp = await fetch(`/disponibilidad/consultar?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                tbodyDisponibilidad.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="fa-solid fa-triangle-exclamation f-s-18 me-1"></i>
                            ${data.mensaje || 'Error al consultar disponibilidad.'}
                        </td>
                    </tr>
                `;
                return;
            }

            const resultado = data.datos;

            // Actualizar KPIs
            if (kpiTotal) kpiTotal.textContent = resultado.unidades_totales;
            if (kpiDisponibles) kpiDisponibles.textContent = resultado.unidades_disponibles;
            if (kpiBloqueadas) kpiBloqueadas.textContent = resultado.unidades_bloqueadas;
            if (kpiTasa) kpiTasa.textContent = `${resultado.tasa_disponibilidad}%`;

            // Actualizar banner
            if (infoRangoTexto) infoRangoTexto.textContent = `${resultado.intervalo.fecha_entrada} al ${resultado.intervalo.fecha_salida}`;
            if (infoNochesTexto) {
                const n = resultado.intervalo.noches;
                infoNochesTexto.textContent = `${n} ${n === 1 ? 'noche' : 'noches'}`;
            }
            if (badgeTotalConsultadas) badgeTotalConsultadas.textContent = `${resultado.unidades.length} unidades`;

            // Renderizar tabla
            if (resultado.unidades.length === 0) {
                tbodyDisponibilidad.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark f-s-32 d-block mb-2 text-secondary"></i>
                            No se encontraron unidades que coincidan con los criterios seleccionados.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            for (const u of resultado.unidades) {
                const disponibleBadge = u.disponible
                    ? `<span class="badge bg-light-success f-s-12">
                           <i class="fa-solid fa-check me-1"></i> Disponible
                       </span>`
                    : `<span class="badge bg-light-danger f-s-12" title="${u.motivo_no_disponible || 'Ocupada'}">
                           <i class="fa-solid fa-lock me-1"></i> ${u.motivo_no_disponible || 'Bloqueada'}
                       </span>`;

                const btnBloquear = u.disponible
                    ? `<button type="button" class="btn btn-outline-danger btn-sm btn-bloquear-rapido"
                               data-unidad-id="${u.id}"
                               data-unidad-codigo="${u.codigo}"
                               data-unidad-nombre="${u.nombre}"
                               data-propiedad-id="${u.propiedad_id}"
                               title="Bloquear unidad para estas fechas">
                           <i class="fa-solid fa-lock me-1"></i> Bloquear
                       </button>`
                    : `<button type="button" class="btn btn-outline-secondary btn-sm" disabled title="No disponible">
                           <i class="fa-solid fa-lock-open"></i>
                       </button>`;

                html += `
                    <tr>
                        <td class="f-w-700 text-dark">
                            <a href="/unidades/${u.id}/perfil" class="text-decoration-none text-primary">
                                ${u.codigo}
                            </a>
                        </td>
                        <td>
                            <div class="f-w-600 text-dark">${u.nombre}</div>
                        </td>
                        <td class="text-secondary f-s-13">
                            <i class="fa-solid fa-building me-1"></i> ${u.propiedad_nombre}
                        </td>
                        <td>
                            <span class="chip bg-light-primary">${u.tipo_unidad_nombre || '-'}</span>
                        </td>
                        <td class="text-secondary f-s-13">
                            <i class="fa-solid fa-users me-1"></i> ${u.capacidad_personas} pers.
                        </td>
                        <td>${disponibleBadge}</td>
                        <td class="text-end pe-3">${btnBloquear}</td>
                    </tr>
                `;
            }

            tbodyDisponibilidad.innerHTML = html;

            // Delegar clics de bloqueo rápido
            tbodyDisponibilidad.querySelectorAll('.btn-bloquear-rapido').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const uId = btn.getAttribute('data-unidad-id');
                    const pId = btn.getAttribute('data-propiedad-id');
                    abrirModalBloqueoConDatos(pId, uId, entrada, salida);
                });
            });

        } catch (err) {
            tbodyDisponibilidad.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        Error de comunicación con el servidor.
                    </td>
                </tr>
            `;
        }
    }

    // -------------------------------------------------------------------------
    // 4. Tab 2: Matriz Mensual (Rack de Disponibilidad)
    // -------------------------------------------------------------------------
    async function cargarMatrizRack() {
        if (!tbodyRack || !theadRack) return;

        const propiedadId = selectMatrizPropiedad?.value || '';
        const mes = selectMatrizMes?.value || '';

        if (!propiedadId) {
            tbodyRack.innerHTML = `<tr><td class="py-4 text-muted">Seleccione una propiedad.</td></tr>`;
            return;
        }

        tbodyRack.innerHTML = `
            <tr>
                <td class="py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando rack mensual...
                </td>
            </tr>
        `;

        try {
            const resp = await fetch(`/disponibilidad/matriz?propiedad_id=${propiedadId}&mes=${mes}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                tbodyRack.innerHTML = `<tr><td class="py-4 text-danger">${data.mensaje || 'Error al cargar rack.'}</td></tr>`;
                return;
            }

            const matriz = data.datos;
            const cols = matriz.columnas_dias || [];
            const unidades = matriz.unidades || [];

            // Construir thead
            let theadHtml = `
                <tr class="f-s-11 text-uppercase text-secondary">
                    <th style="min-width: 130px; position: sticky; left: 0; background: #f8f9fa; z-index: 2;" class="text-start ps-3">
                        Unidad
                    </th>
            `;

            for (const c of cols) {
                const bgWeekend = c.es_fin_de_semana ? 'bg-light-secondary text-secondary' : '';
                theadHtml += `
                    <th style="min-width: 34px; max-width: 36px;" class="${bgWeekend}" title="${c.fecha}">
                        <div class="f-s-10 text-muted">${c.nombre_dia}</div>
                        <div class="f-s-12 f-w-700">${c.dia}</div>
                    </th>
                `;
            }
            theadHtml += '</tr>';
            theadRack.innerHTML = theadHtml;

            // Construir tbody
            if (unidades.length === 0) {
                tbodyRack.innerHTML = `<tr><td colspan="${cols.length + 1}" class="py-5 text-muted">No hay unidades registradas para esta propiedad.</td></tr>`;
                return;
            }

            let tbodyHtml = '';
            for (const u of unidades) {
                tbodyHtml += `
                    <tr>
                        <td style="position: sticky; left: 0; background: #fff; z-index: 1;" class="text-start ps-3 text-nowrap">
                            <span class="f-w-700 text-dark">${u.codigo}</span>
                            <small class="text-muted d-block f-s-10">${u.tipo_unidad || ''}</small>
                        </td>
                `;

                for (const c of cols) {
                    const celda = u.dias[c.fecha];
                    const esOcupado = celda && celda.estado === 'OCUPADO';
                    let claseColor = 'bg-light-success text-success';
                    let icono = 'fa-solid fa-check';
                    let tooltip = `${c.fecha}: Disponible`;

                    if (esOcupado) {
                        if (celda.tipo === 'MANTENIMIENTO') {
                            claseColor = 'bg-warning text-white';
                            icono = 'fa-solid fa-wrench';
                            tooltip = `${c.fecha}: Mantenimiento`;
                        } else {
                            claseColor = 'bg-danger text-white';
                            icono = 'fa-solid fa-lock';
                            tooltip = `${c.fecha}: Bloqueado / Ocupado`;
                        }
                    }

                    tbodyHtml += `
                        <td class="p-1" title="${tooltip}">
                            <div class="d-flex-center py-1 b-r-4 ${claseColor}" style="font-size: 11px;">
                                <i class="${icono}"></i>
                            </div>
                        </td>
                    `;
                }
                tbodyHtml += '</tr>';
            }

            tbodyRack.innerHTML = tbodyHtml;

        } catch (err) {
            tbodyRack.innerHTML = `<tr><td class="py-4 text-danger">Error de comunicación al cargar el rack.</td></tr>`;
        }
    }

    // -------------------------------------------------------------------------
    // 5. Tab 3: Maestro de Bloqueos
    // -------------------------------------------------------------------------
    async function cargarBloqueos(pagina = 1) {
        if (!tbodyBloqueos) return;

        estadoBloqueos.pagina = pagina;
        tbodyBloqueos.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando bloqueos...
                </td>
            </tr>
        `;

        try {
            const params = new URLSearchParams({
                pagina: estadoBloqueos.pagina.toString(),
                limite: estadoBloqueos.limite.toString(),
                propiedad_id: filtroBloqueosPropiedad?.value || '',
                estado: filtroBloqueosEstado?.value || '',
                tipo: filtroBloqueosTipo?.value || '',
            });

            const resp = await fetch(`/disponibilidad/bloqueos?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                tbodyBloqueos.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${data.mensaje || 'Error al listar bloqueos.'}</td></tr>`;
                return;
            }

            const bloqueos = data.datos || [];
            const pag = data.paginacion || {};

            estadoBloqueos.total = pag.total || 0;
            estadoBloqueos.paginasTotales = pag.paginas || 1;

            if (infoPaginacionBloqueos) {
                infoPaginacionBloqueos.textContent = `Mostrando ${bloqueos.length} de ${estadoBloqueos.total} registros`;
            }

            if (bloqueos.length === 0) {
                tbodyBloqueos.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-lock-open f-s-32 d-block mb-2 text-secondary"></i>
                            No se encontraron bloqueos con los filtros actuales.
                        </td>
                    </tr>
                `;
                renderizarPaginacionBloqueos();
                return;
            }

            let html = '';
            for (const b of bloqueos) {
                const estadoBadge = b.activo
                    ? `<span class="badge bg-light-danger f-s-11">ACTIVO</span>`
                    : `<span class="badge bg-light-secondary f-s-11">LIBERADO</span>`;

                const tipoBadge = b.tipo === 'MANTENIMIENTO'
                    ? `<span class="chip bg-light-warning f-s-11"><i class="fa-solid fa-wrench me-1"></i>Mantenimiento</span>`
                    : `<span class="chip bg-light-info f-s-11"><i class="fa-solid fa-lock me-1"></i>Manual</span>`;

                const btnLiberar = b.activo
                    ? `<button type="button" class="btn btn-outline-danger btn-sm btn-abrir-liberar"
                               data-bloqueo-id="${b.id}"
                               data-unidad-codigo="${b.unidad_codigo || ''}"
                               data-rango="${b.fecha_inicio} al ${b.fecha_fin}"
                               title="Liberar bloqueo de fechas">
                           <i class="fa-solid fa-lock-open me-1"></i> Liberar
                       </button>`
                    : `<span class="text-muted f-s-12"><i class="fa-solid fa-check me-1"></i> Liberado</span>`;

                html += `
                    <tr>
                        <td class="text-secondary f-s-12">#${b.id}</td>
                        <td class="f-w-700 text-dark">
                            <a href="/unidades/${b.unidad_id}/perfil" class="text-decoration-none">
                                ${b.unidad_codigo || 'ID ' + b.unidad_id}
                            </a>
                        </td>
                        <td class="text-secondary f-s-13">${b.propiedad_nombre || '-'}</td>
                        <td>
                            <div class="f-w-600 text-dark">${b.fecha_inicio} ─── ${b.fecha_fin}</div>
                            <small class="text-muted">${b.noches} ${b.noches === 1 ? 'noche' : 'noches'} | Motivo: ${b.motivo}</small>
                        </td>
                        <td>${tipoBadge}</td>
                        <td>${estadoBadge}</td>
                        <td class="f-s-12 text-secondary">${b.creador_nombre || '-'}</td>
                        <td class="text-end pe-3">${btnLiberar}</td>
                    </tr>
                `;
            }

            tbodyBloqueos.innerHTML = html;

            // Delegar clics de liberación
            tbodyBloqueos.querySelectorAll('.btn-abrir-liberar').forEach(btn => {
                btn.addEventListener('click', () => {
                    const bId = btn.getAttribute('data-bloqueo-id');
                    const uCodigo = btn.getAttribute('data-unidad-codigo');
                    const rango = btn.getAttribute('data-rango');
                    abrirModalLiberar(bId, uCodigo, rango);
                });
            });

            renderizarPaginacionBloqueos();

        } catch (err) {
            tbodyBloqueos.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error de comunicación.</td></tr>`;
        }
    }

    function renderizarPaginacionBloqueos() {
        if (!btnPaginacionBloqueos) return;

        if (estadoBloqueos.paginasTotales <= 1) {
            btnPaginacionBloqueos.innerHTML = '';
            return;
        }

        let html = '';
        const act = estadoBloqueos.pagina;
        const tot = estadoBloqueos.paginasTotales;

        html += `<button type="button" class="btn btn-outline-secondary btn-sm ${act <= 1 ? 'disabled' : ''}" data-pag="${act - 1}">&laquo;</button>`;

        for (let p = 1; p <= tot; p++) {
            if (p === 1 || p === tot || (p >= act - 1 && p <= act + 1)) {
                html += `<button type="button" class="btn btn-sm ${p === act ? 'btn-primary' : 'btn-outline-secondary'}" data-pag="${p}">${p}</button>`;
            } else if (p === act - 2 || p === act + 2) {
                html += `<button type="button" class="btn btn-outline-secondary btn-sm disabled">...</button>`;
            }
        }

        html += `<button type="button" class="btn btn-outline-secondary btn-sm ${act >= tot ? 'disabled' : ''}" data-pag="${act + 1}">&raquo;</button>`;

        btnPaginacionBloqueos.innerHTML = html;

        btnPaginacionBloqueos.querySelectorAll('button[data-pag]').forEach(b => {
            b.addEventListener('click', () => {
                const targetPag = parseInt(b.getAttribute('data-pag'), 10);
                if (!isNaN(targetPag) && targetPag >= 1 && targetPag <= tot) {
                    cargarBloqueos(targetPag);
                }
            });
        });
    }

    // -------------------------------------------------------------------------
    // 6. Carga Dinámica de Unidades en Modal de Bloqueo
    // -------------------------------------------------------------------------
    async function cargarUnidadesDePropiedad(propiedadId, unidadSeleccionadaId = null) {
        if (!bloqueoUnidadSelect) return;

        if (!propiedadId) {
            bloqueoUnidadSelect.innerHTML = `<option value="">Primero seleccione una propiedad...</option>`;
            bloqueoUnidadSelect.disabled = true;
            return;
        }

        bloqueoUnidadSelect.disabled = true;
        bloqueoUnidadSelect.innerHTML = `<option value="">Cargando unidades activas...</option>`;

        try {
            const resp = await fetch(`/unidades/datos?propiedad_id=${propiedadId}&estado=ACTIVO&limite=500`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            const data = await resp.json();
            const unidades = data.datos || [];

            if (unidades.length === 0) {
                bloqueoUnidadSelect.innerHTML = `<option value="">No hay unidades activas en esta propiedad</option>`;
                bloqueoUnidadSelect.disabled = true;
                return;
            }

            let opts = `<option value="">Seleccione una unidad...</option>`;
            for (const u of unidades) {
                const sel = unidadSeleccionadaId && parseInt(unidadSeleccionadaId, 10) === parseInt(u.id, 10) ? 'selected' : '';
                opts += `<option value="${u.id}" ${sel}>${u.codigo} — ${u.nombre}</option>`;
            }

            bloqueoUnidadSelect.innerHTML = opts;
            bloqueoUnidadSelect.disabled = false;

        } catch (e) {
            bloqueoUnidadSelect.innerHTML = `<option value="">Error al cargar unidades</option>`;
            bloqueoUnidadSelect.disabled = true;
        }
    }

    function abrirModalBloqueoConDatos(propiedadId, unidadId, entrada, salida) {
        if (!modalCrearBloqueo) return;

        formCrearBloqueo?.reset();
        if (pristineBloqueo) pristineBloqueo.reset();

        if (bloqueoPropiedadSelect && propiedadId) {
            bloqueoPropiedadSelect.value = propiedadId;
            cargarUnidadesDePropiedad(propiedadId, unidadId);
        }

        if (bloqueoFechaInicio && entrada) bloqueoFechaInicio.value = entrada;
        if (bloqueoFechaFin && salida) bloqueoFechaFin.value = salida;

        // Sincronizar Range Picker Alina (Flatpickr) del modal si existe
        const rangeBloqueoInput = document.getElementById('bloqueo-rango-fechas');
        if (rangeBloqueoInput && rangeBloqueoInput._flatpickr) {
            if (entrada && salida) {
                rangeBloqueoInput._flatpickr.setDate([entrada, salida], false);
            } else {
                rangeBloqueoInput._flatpickr.clear();
            }
        }

        modalCrearBloqueo.show();
    }

    // -------------------------------------------------------------------------
    // 7. Envíos de Formulario (Bloquear y Liberar)
    // -------------------------------------------------------------------------
    if (formCrearBloqueo) {
        formCrearBloqueo.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (pristineBloqueo && !pristineBloqueo.validate()) {
                return;
            }

            const unidadId = bloqueoUnidadSelect?.value || '';
            const fechaInicio = bloqueoFechaInicio?.value || '';
            const fechaFin = bloqueoFechaFin?.value || '';
            const tipo = bloqueoTipo?.value || 'BLOQUEO_MANUAL';
            const motivo = bloqueoMotivo?.value || '';

            if (!unidadId || !fechaInicio || !fechaFin || !motivo.trim()) {
                notificar('warning', 'Campos incompletos', 'Complete todos los campos requeridos.');
                return;
            }

            if (btnGuardarBloqueo) btnGuardarBloqueo.disabled = true;
            if (spinnerGuardarBloqueo) spinnerGuardarBloqueo.classList.remove('d-none');

            try {
                const resp = await fetch('/disponibilidad/bloquear', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        unidad_id: parseInt(unidadId, 10),
                        fecha_inicio: fechaInicio,
                        fecha_fin: fechaFin,
                        tipo: tipo,
                        motivo: motivo.trim(),
                    }),
                });

                const data = await resp.json();

                if (resp.status === 409) {
                    notificar('error', 'Conflicto de Disponibilidad', data.mensaje || 'La unidad ya se encuentra ocupada o bloqueada en esas fechas.');
                    return;
                }

                if (!resp.ok || !data.ok) {
                    notificar('error', 'Error al bloquear', data.mensaje || 'No se pudo aplicar el bloqueo.');
                    return;
                }

                modalCrearBloqueo?.hide();
                notificarToast('success', data.mensaje || 'Bloqueo aplicado exitosamente.');

                // Refrescar vistas activas
                ejecutarConsultaDisponibilidad();
                cargarMatrizRack();
                cargarBloqueos(1);

            } catch (err) {
                notificar('error', 'Error de Conexión', 'No se pudo contactar al servidor.');
            } finally {
                if (btnGuardarBloqueo) btnGuardarBloqueo.disabled = false;
                if (spinnerGuardarBloqueo) spinnerGuardarBloqueo.classList.add('d-none');
            }
        });
    }

    function abrirModalLiberar(bloqueoId, unidadCodigo, rango) {
        if (!modalLiberar) return;

        if (inputLiberarBloqueoId) inputLiberarBloqueoId.value = bloqueoId;
        if (mensajeConfirmacionLiberar) {
            mensajeConfirmacionLiberar.innerHTML = `
                ¿Confirma la liberación del bloqueo <strong>#${bloqueoId}</strong> para la unidad <strong>${unidadCodigo}</strong> (${rango})?
                Las fechas ocupadas volverán a quedar inmediatamente disponibles en el inventario diario.
            `;
        }

        modalLiberar.show();
    }

    if (formLiberar) {
        formLiberar.addEventListener('submit', async (e) => {
            e.preventDefault();

            const bId = inputLiberarBloqueoId?.value || '';
            if (!bId) return;

            if (btnConfirmarLiberacion) btnConfirmarLiberacion.disabled = true;
            if (spinnerLiberar) spinnerLiberar.classList.remove('d-none');

            try {
                const resp = await fetch('/disponibilidad/liberar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        bloqueo_id: parseInt(bId, 10),
                    }),
                });

                const data = await resp.json();

                if (!resp.ok || !data.ok) {
                    notificar('error', 'Error al liberar', data.mensaje || 'No se pudo liberar el bloqueo.');
                    return;
                }

                modalLiberar?.hide();
                notificarToast('success', data.mensaje || 'Bloqueo liberado con éxito.');

                // Refrescar
                ejecutarConsultaDisponibilidad();
                cargarMatrizRack();
                cargarBloqueos(estadoBloqueos.pagina);

            } catch (err) {
                notificar('error', 'Error de Conexión', 'No se pudo contactar al servidor.');
            } finally {
                if (btnConfirmarLiberacion) btnConfirmarLiberacion.disabled = false;
                if (spinnerLiberar) spinnerLiberar.classList.add('d-none');
            }
        });
    }

    // -------------------------------------------------------------------------
    // 8. Event Listeners y Carga Inicial
    // -------------------------------------------------------------------------
    formConsulta?.addEventListener('submit', (e) => {
        e.preventDefault();
        ejecutarConsultaDisponibilidad();
    });

    checkSoloDisponibles?.addEventListener('change', () => {
        ejecutarConsultaDisponibilidad();
    });

    btnRecargarMatriz?.addEventListener('click', () => {
        cargarMatrizRack();
    });

    selectMatrizPropiedad?.addEventListener('change', () => {
        cargarMatrizRack();
    });

    selectMatrizMes?.addEventListener('change', () => {
        cargarMatrizRack();
    });

    filtroBloqueosPropiedad?.addEventListener('change', () => {
        cargarBloqueos(1);
    });

    filtroBloqueosEstado?.addEventListener('change', () => {
        cargarBloqueos(1);
    });

    filtroBloqueosTipo?.addEventListener('change', () => {
        cargarBloqueos(1);
    });

    btnRecargarBloqueos?.addEventListener('click', () => {
        cargarBloqueos(1);
    });

    btnAbrirNuevoBloqueo?.addEventListener('click', () => {
        const ent = inputEntrada?.value || '';
        const sal = inputSalida?.value || '';
        const prop = selectPropiedad?.value || '';
        abrirModalBloqueoConDatos(prop, null, ent, sal);
    });

    bloqueoPropiedadSelect?.addEventListener('change', () => {
        const pId = bloqueoPropiedadSelect.value;
        cargarUnidadesDePropiedad(pId);
    });

    // Pestaña eventos Bootstrap para carga bajo demanda
    const tabMatrizBtn = document.getElementById('tab-matriz-btn');
    tabMatrizBtn?.addEventListener('shown.bs.tab', () => {
        cargarMatrizRack();
    });

    const tabBloqueosBtn = document.getElementById('tab-bloqueos-btn');
    tabBloqueosBtn?.addEventListener('shown.bs.tab', () => {
        cargarBloqueos(1);
    });

    // Ejecución inicial: consultar disponibilidad por defecto
    ejecutarConsultaDisponibilidad();
});
