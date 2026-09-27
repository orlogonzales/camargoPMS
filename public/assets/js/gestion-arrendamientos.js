/**
 * Camargo PMS — Módulo de Gestión de Arrendamientos de Mediana y Larga Estancia (ARRENDAMIENTOS-1 / D-076)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO.
 * - Temporalidad a plazo determinado cerrado en V1.
 * - Disponibilidad sparse y materialización total protegida en InnoDB.
 * - Idempotencia recurrente en cuotas de renta y cargos al folio.
 * - Custodia segregada de garantía con saldo reconstructible.
 * - D-071 / D-075: Geometría Alina, badges bg-light-*, iconos Font Awesome 6.3.0.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Cache local
    let arrendamientosCache = [];
    let arrendamientoActualCache = null;

    // Elementos DOM
    const tbody = document.getElementById('tbody-arrendamientos');
    const filtroBusqueda = document.getElementById('filtro-busqueda-arrendamiento');
    const filtroPropiedad = document.getElementById('filtro-propiedad-arrendamiento');
    const filtroEstado = document.getElementById('filtro-estado-arrendamiento');
    const btnLimpiar = document.getElementById('btn-limpiar-filtros');
    const btnAbrirCrear = document.getElementById('btn-abrir-crear-arrendamiento');

    // Modales Bootstrap
    const modalCrear = new bootstrap.Modal(document.getElementById('modal-crear-arrendamiento'));
    const modalDetalle = new bootstrap.Modal(document.getElementById('modal-detalle-arrendamiento'));
    const modalProrrogar = new bootstrap.Modal(document.getElementById('modal-prorrogar'));
    const modalRescindir = new bootstrap.Modal(document.getElementById('modal-rescindir'));
    const modalAgregarPersona = new bootstrap.Modal(document.getElementById('modal-agregar-persona'));

    // Formularios
    const formCrear = document.getElementById('form-crear-arrendamiento');
    const formProrrogar = document.getElementById('form-prorrogar');
    const formRescindir = document.getElementById('form-rescindir');
    const formAgregarPersona = document.getElementById('form-agregar-persona');

    // =========================================================================
    // Utilidades de Formato y Badges Alina
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

    function badgeEstadoArrendamiento(estado) {
        switch (estado) {
            case 'VIGENTE':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i> VIGENTE</span>';
            case 'BORRADOR':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-pen-ruler me-1"></i> BORRADOR</span>';
            case 'FINALIZADO':
                return '<span class="badge bg-light-secondary text-secondary"><i class="fa-solid fa-check-double me-1"></i> FINALIZADO</span>';
            case 'RESCINDIDO':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-ban me-1"></i> RESCINDIDO</span>';
            case 'CANCELADO':
                return '<span class="badge bg-light-dark text-dark"><i class="fa-solid fa-xmark me-1"></i> CANCELADO</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeEstadoCuota(estado) {
        switch (estado) {
            case 'PAGADA_TOTAL':
                return '<span class="badge bg-light-success text-success">PAGADA</span>';
            case 'PAGADA_PARCIAL':
                return '<span class="badge bg-light-info text-info">PAGO PARCIAL</span>';
            case 'PENDIENTE':
                return '<span class="badge bg-light-warning text-warning">PENDIENTE</span>';
            case 'ANULADA':
                return '<span class="badge bg-light-danger text-danger">ANULADA</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    // =========================================================================
    // Carga de Datos y Renderizado
    // =========================================================================

    async function cargarArrendamientos() {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Cargando contratos...
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        if (filtroBusqueda.value.trim()) params.append('q', filtroBusqueda.value.trim());
        if (filtroPropiedad.value) params.append('propiedad_id', filtroPropiedad.value);
        if (filtroEstado.value) params.append('estado', filtroEstado.value);

        try {
            const resp = await fetch(`/api/arrendamientos?${params.toString()}`);
            const data = await resp.json();

            if (!data.ok) {
                throw new Error(data.mensaje || 'Error al obtener contratos.');
            }

            arrendamientosCache = data.datos || [];
            actualizarKpis(arrendamientosCache);
            renderizarTabla(arrendamientosCache);
        } catch (error) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> ${escaparHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    function actualizarKpis(lista) {
        let vigentes = 0;
        let borradores = 0;
        let cerrados = 0;
        let totalGarantiaRetenida = 0;

        lista.forEach(arr => {
            if (arr.estado === 'VIGENTE') vigentes++;
            else if (arr.estado === 'BORRADOR') borradores++;
            else if (arr.estado === 'FINALIZADO' || arr.estado === 'RESCINDIDO') cerrados++;

            if (arr.garantia_retenida) {
                totalGarantiaRetenida += parseFloat(arr.garantia_retenida) || 0;
            }
        });

        document.getElementById('kpi-vigentes').textContent = vigentes;
        document.getElementById('kpi-borradores').textContent = borradores;
        document.getElementById('kpi-cerrados').textContent = cerrados;
        document.getElementById('kpi-garantias').textContent = formatearDinero(totalGarantiaRetenida);
    }

    function renderizarTabla(lista) {
        if (!lista.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-inbox me-1"></i> No se encontraron contratos de arrendamiento registrados.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        lista.forEach(arr => {
            const titular = arr.titular_nombre_completo || 'Sin titular asignado';
            const doc = arr.titular_numero_documento ? `<span class="badge bg-light-secondary text-secondary f-s-10 ms-1">${escaparHtml(arr.titular_numero_documento)}</span>` : '';
            const unidad = `Unidad ${escaparHtml(arr.unidad_numero || '')}`;
            const propiedad = escaparHtml(arr.propiedad_nombre || '');

            html += `
                <tr>
                    <td class="ps-3">
                        <strong class="text-primary">${escaparHtml(arr.codigo)}</strong>
                    </td>
                    <td>
                        <div class="f-w-600 text-dark">${unidad}</div>
                        <span class="f-s-11 text-muted">${propiedad}</span>
                    </td>
                    <td>
                        <div class="f-w-600 text-dark">${escaparHtml(titular)} ${doc}</div>
                    </td>
                    <td>
                        <span class="f-s-12">${escaparHtml(arr.fecha_inicio)}</span>
                        <i class="fa-solid fa-arrow-right mx-1 text-muted f-s-10"></i>
                        <span class="f-s-12">${escaparHtml(arr.fecha_fin)}</span>
                        <div class="f-s-10 text-muted">Vence día ${arr.dia_vencimiento}</div>
                    </td>
                    <td class="text-end f-w-600 text-primary">
                        ${formatearDinero(arr.renta_mensual)}
                    </td>
                    <td class="text-end">
                        <div class="f-w-600 text-success">${formatearDinero(arr.garantia_retenida || arr.deposito_garantia)}</div>
                        <span class="f-s-10 text-muted">${escaparHtml(arr.garantia_estado || 'PENDIENTE')}</span>
                    </td>
                    <td class="text-center">
                        ${badgeEstadoArrendamiento(arr.estado)}
                    </td>
                    <td class="text-center pe-3">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-ver-detalle" data-id="${arr.id}" title="Ver Detalle y Operaciones">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            ${arr.estado === 'BORRADOR' ? `
                                <button type="button" class="btn btn-outline-success btn-activar-rapido" data-id="${arr.id}" title="Activar Contrato">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        adjuntarEventosTabla();
    }

    function adjuntarEventosTabla() {
        document.querySelectorAll('.btn-ver-detalle').forEach(btn => {
            btn.addEventListener('click', () => abrirDetalleArrendamiento(btn.dataset.id));
        });

        document.querySelectorAll('.btn-activar-rapido').forEach(btn => {
            btn.addEventListener('click', () => activarArrendamiento(btn.dataset.id));
        });
    }

    // =========================================================================
    // Detalle Completo de Arrendamiento
    // =========================================================================

    async function abrirDetalleArrendamiento(id) {
        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}`);
            const data = await resp.json();
            Swal.close();

            if (!data.ok) {
                throw new Error(data.mensaje || 'Error al consultar contrato.');
            }

            arrendamientoActualCache = data.datos;
            const a = data.datos.arrendamiento;
            const personas = data.datos.personas || [];
            const cuotas = data.datos.cuotas || [];
            const gar = data.datos.garantia || {};
            const hist = data.datos.historial || [];
            const folio = data.datos.folio || {};

            // Encabezado
            document.getElementById('detalle-codigo').textContent = a.codigo;
            document.getElementById('detalle-subtitulo').textContent = `Contrato de Arrendamiento Patrimonial — Unidad ${a.unidad_numero || ''} (${a.propiedad_nombre || ''})`;
            document.getElementById('detalle-badge-estado').innerHTML = badgeEstadoArrendamiento(a.estado);

            // Resumen
            document.getElementById('detalle-unidad').textContent = `Unidad ${a.unidad_numero || ''} (${a.unidad_nombre || ''})`;
            document.getElementById('detalle-propiedad').textContent = a.propiedad_nombre || '';
            document.getElementById('detalle-rango-fechas').textContent = `${a.fecha_inicio} al ${a.fecha_fin}`;
            document.getElementById('detalle-dia-vencimiento').textContent = a.dia_vencimiento;
            document.getElementById('detalle-renta-mensual').textContent = formatearDinero(a.renta_mensual);
            document.getElementById('detalle-monto-primer-periodo').textContent = formatearDinero(a.monto_primer_periodo);
            document.getElementById('detalle-garantia-monto').textContent = formatearDinero(gar.monto_retenido_actual || 0);
            document.getElementById('detalle-garantia-estado').textContent = gar.estado || 'PENDIENTE';

            if (folio && folio.codigo) {
                document.getElementById('detalle-folio-codigo').textContent = folio.codigo;
                document.getElementById('detalle-folio-referencia').classList.remove('d-none');
            } else {
                document.getElementById('detalle-folio-referencia').classList.add('d-none');
            }

            // Barra de Botones de Acción de Estado
            renderizarBotonesAccionEstado(a);

            // Tab Cuotas
            document.getElementById('conteo-cuotas').textContent = cuotas.length;
            renderizarCuotas(cuotas);

            // Tab Sujetos
            document.getElementById('conteo-sujetos').textContent = personas.length;
            renderizarSujetos(personas, a.estado);

            // Tab Garantía
            renderizarGarantia(gar, a.estado);

            // Tab Historial
            renderizarHistorial(hist);

            modalDetalle.show();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    function renderizarBotonesAccionEstado(arr) {
        const contenedor = document.getElementById('botones-accion-estado');
        let html = '';

        if (arr.estado === 'BORRADOR') {
            html += `
                <button type="button" class="btn btn-success btn-sm" id="btn-accion-activar" data-id="${arr.id}">
                    <i class="fa-solid fa-play me-1"></i> Activar y Materializar
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-accion-cancelar-borrador" data-id="${arr.id}">
                    <i class="fa-solid fa-xmark me-1"></i> Descartar Borrador
                </button>
            `;
        } else if (arr.estado === 'VIGENTE') {
            html += `
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-accion-prorrogar" data-id="${arr.id}">
                    <i class="fa-solid fa-calendar-plus me-1"></i> Prorrogar Plazo
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-accion-rescindir" data-id="${arr.id}">
                    <i class="fa-solid fa-ban me-1"></i> Rescindir Anticipadamente
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-accion-finalizar" data-id="${arr.id}">
                    <i class="fa-solid fa-check-double me-1"></i> Finalizar Contrato
                </button>
            `;
        } else {
            html += `<span class="badge bg-light-secondary text-secondary f-s-12">Contrato en estado inmutable</span>`;
        }

        contenedor.innerHTML = html;

        // Adjuntar eventos de acción
        const btnActivar = document.getElementById('btn-accion-activar');
        if (btnActivar) btnActivar.addEventListener('click', () => activarArrendamiento(arr.id));

        const btnCancelarBorrador = document.getElementById('btn-accion-cancelar-borrador');
        if (btnCancelarBorrador) btnCancelarBorrador.addEventListener('click', () => cancelarBorrador(arr.id));

        const btnProrrogar = document.getElementById('btn-accion-prorrogar');
        if (btnProrrogar) btnProrrogar.addEventListener('click', () => {
            document.getElementById('prorrogar-arrendamiento-id').value = arr.id;
            document.getElementById('prorrogar-nueva-fecha-fin').min = arr.fecha_fin;
            document.getElementById('prorrogar-nueva-fecha-fin').value = '';
            modalProrrogar.show();
        });

        const btnRescindir = document.getElementById('btn-accion-rescindir');
        if (btnRescindir) btnRescindir.addEventListener('click', () => {
            document.getElementById('rescindir-arrendamiento-id').value = arr.id;
            document.getElementById('rescindir-fecha-efectiva').min = arr.fecha_inicio;
            document.getElementById('rescindir-fecha-efectiva').max = arr.fecha_fin;
            document.getElementById('rescindir-fecha-efectiva').value = new Date().toISOString().split('T')[0];
            modalRescindir.show();
        });

        const btnFinalizar = document.getElementById('btn-accion-finalizar');
        if (btnFinalizar) btnFinalizar.addEventListener('click', () => finalizarArrendamiento(arr.id));
    }

    function renderizarCuotas(cuotas) {
        const tbodyCuotas = document.getElementById('tbody-detalle-cuotas');
        if (!cuotas.length) {
            tbodyCuotas.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-3 text-muted">
                        No hay cuotas emitidas para este contrato.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        cuotas.forEach(c => {
            html += `
                <tr>
                    <td class="ps-3"><strong class="text-primary">${escaparHtml(c.periodo_codigo)}</strong></td>
                    <td><span class="badge bg-light-secondary text-secondary f-s-10">${escaparHtml(c.tipo_cuota)}</span></td>
                    <td>${escaparHtml(c.fecha_emision)}</td>
                    <td>${escaparHtml(c.fecha_vencimiento)}</td>
                    <td class="text-end f-w-600">${formatearDinero(c.monto_renta)}</td>
                    <td class="text-end text-success">${formatearDinero(c.monto_aplicado_acumulado || 0)}</td>
                    <td class="text-end text-danger f-w-600">${formatearDinero(c.saldo_pendiente || c.monto_renta)}</td>
                    <td class="text-center pe-3">${badgeEstadoCuota(c.estado)}</td>
                </tr>
            `;
        });
        tbodyCuotas.innerHTML = html;
    }

    function renderizarSujetos(personas, estadoContrato) {
        const tbodySujetos = document.getElementById('tbody-detalle-sujetos');
        if (!personas.length) {
            tbodySujetos.innerHTML = `
                <tr><td colspan="6" class="text-center py-3 text-muted">No se registran sujetos vinculados.</td></tr>
            `;
            return;
        }

        let html = '';
        personas.forEach(p => {
            const esTitular = p.tipo_relacion === 'TITULAR';
            const badgeRol = esTitular 
                ? '<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-crown me-1"></i> TITULAR</span>'
                : (p.tipo_relacion === 'COTITULAR' 
                    ? '<span class="badge bg-light-info text-info">COTITULAR</span>'
                    : '<span class="badge bg-light-secondary text-secondary">OCUPANTE</span>');

            const puedeQuitar = !esTitular && (estadoContrato === 'BORRADOR' || estadoContrato === 'VIGENTE');

            html += `
                <tr>
                    <td class="ps-3">${badgeRol}</td>
                    <td><strong class="text-dark">${escaparHtml(p.nombre_completo || 'ID: ' + p.persona_id)}</strong></td>
                    <td>${escaparHtml(p.tipo_documento || '')} ${escaparHtml(p.numero_documento || '')}</td>
                    <td>${escaparHtml(p.telefono || p.correo_electronico || '-')}</td>
                    <td class="text-muted">${escaparHtml(p.observaciones || '-')}</td>
                    <td class="text-center pe-3">
                        ${puedeQuitar ? `
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-quitar-persona" data-persona-id="${p.persona_id}">
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        ` : '<span class="text-muted">-</span>'}
                    </td>
                </tr>
            `;
        });

        tbodySujetos.innerHTML = html;

        document.querySelectorAll('.btn-quitar-persona').forEach(btn => {
            btn.addEventListener('click', () => quitarPersona(btn.dataset.personaId));
        });
    }

    function renderizarGarantia(gar, estadoContrato) {
        document.getElementById('gar-pactado').textContent = formatearDinero(gar.monto_pactado || 0);
        document.getElementById('gar-recibido').textContent = formatearDinero(gar.monto_recibido || 0);
        document.getElementById('gar-retenido').textContent = formatearDinero(gar.monto_retenido_actual || 0);
        document.getElementById('gar-danos').textContent = formatearDinero(gar.monto_compensado_danos || 0);
        document.getElementById('gar-renta').textContent = formatearDinero(gar.monto_compensado_renta || 0);
        document.getElementById('gar-devuelto').textContent = formatearDinero(gar.monto_devuelto || 0);

        const bloqueAcciones = document.getElementById('acciones-garantia-bloque');
        if (estadoContrato === 'CANCELADO') {
            bloqueAcciones.classList.add('d-none');
        } else {
            bloqueAcciones.classList.remove('d-none');
        }
    }

    function renderizarHistorial(hist) {
        const contenedor = document.getElementById('contenedor-historial-estados');
        if (!hist.length) {
            contenedor.innerHTML = '<p class="text-muted text-center py-3">No hay eventos registrados en el historial.</p>';
            return;
        }

        let html = '<ul class="list-unstyled mb-0">';
        hist.forEach(h => {
            html += `
                <li class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-light-secondary text-secondary f-s-10">${escaparHtml(h.estado_anterior)}</span>
                            <i class="fa-solid fa-arrow-right mx-1 f-s-10 text-muted"></i>
                            <span class="badge bg-light-primary text-primary f-s-10">${escaparHtml(h.estado_nuevo)}</span>
                        </div>
                        <small class="text-muted">${escaparHtml(h.cambiado_en)}</small>
                    </div>
                    <div class="f-s-12 text-dark mt-1">${escaparHtml(h.motivo || 'Transición de estado')}</div>
                    <small class="text-muted f-s-11">Actor: ${escaparHtml(h.actor_nombre || 'Sistema')} (${escaparHtml(h.actor_tipo || 'USER')})</small>
                </li>
            `;
        });
        html += '</ul>';
        contenedor.innerHTML = html;
    }

    // =========================================================================
    // Operaciones de Dominio (Fetch POST)
    // =========================================================================

    async function activarArrendamiento(id) {
        const confirm = await Swal.fire({
            title: '¿Activar contrato de arrendamiento?',
            text: 'Se validará la disponibilidad en inventario, se materializarán las noches y se apertura el folio financiero.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, activar contrato',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}/activar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al activar contrato.');

            await Swal.fire('¡Contrato Activado!', data.mensaje, 'success');
            modalDetalle.hide();
            cargarArrendamientos();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    async function finalizarArrendamiento(id) {
        const confirm = await Swal.fire({
            title: '¿Finalizar contrato de arrendamiento?',
            text: 'El contrato pasará a estado FINALIZADO por cumplimiento del plazo pactado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}/finalizar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al finalizar contrato.');

            await Swal.fire('Finalizado', data.mensaje, 'success');
            modalDetalle.hide();
            cargarArrendamientos();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    async function cancelarBorrador(id) {
        const { value: motivo } = await Swal.fire({
            title: '¿Descartar borrador contractual?',
            input: 'text',
            inputLabel: 'Motivo de descarte',
            inputPlaceholder: 'Indique la razón de la anulación...',
            showCancelButton: true,
            confirmButtonText: 'Descartar',
            cancelButtonText: 'Volver',
            inputValidator: (value) => {
                if (!value) return '¡Debe ingresar un motivo!';
            }
        });

        if (!motivo) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}/cancelar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken, motivo: motivo })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al cancelar borrador.');

            await Swal.fire('Borrador Descartado', data.mensaje, 'success');
            modalDetalle.hide();
            cargarArrendamientos();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    async function quitarPersona(personaId) {
        if (!arrendamientoActualCache) return;
        const arrId = arrendamientoActualCache.arrendamiento.id;

        const confirm = await Swal.fire({
            title: '¿Desvincular a este residente?',
            text: 'Se eliminará la relación del sujeto con este contrato de arrendamiento.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desvincular',
            cancelButtonText: 'Cancelar'
        });

        if (!confirm.isConfirmed) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${arrId}/personas/${personaId}/eliminar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al desvincular residente.');

            Swal.fire('Desvinculado', data.mensaje, 'success');
            abrirDetalleArrendamiento(arrId);
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    // =========================================================================
    // Operaciones de Fondo de Garantía
    // =========================================================================

    document.getElementById('btn-garantia-recibir')?.addEventListener('click', async () => {
        if (!arrendamientoActualCache) return;
        const arrId = arrendamientoActualCache.arrendamiento.id;

        const { value: monto } = await Swal.fire({
            title: 'Registrar Recepción de Garantía',
            text: 'Ingrese el importe en efectivo o transferencia recibido en custodia:',
            input: 'number',
            inputAttributes: { step: '0.01', min: '0.01' },
            showCancelButton: true,
            confirmButtonText: 'Registrar Ingreso',
            cancelButtonText: 'Cancelar',
            inputValidator: (val) => {
                if (!val || parseFloat(val) <= 0) return 'Ingrese un monto mayor a 0.';
            }
        });

        if (!monto) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${arrId}/garantia/recibir`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf: csrfToken, monto: monto })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al recibir garantía.');

            await Swal.fire('Custodia Actualizada', data.mensaje, 'success');
            abrirDetalleArrendamiento(arrId);
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btn-garantia-compensar')?.addEventListener('click', async () => {
        if (!arrendamientoActualCache) return;
        const arrId = arrendamientoActualCache.arrendamiento.id;

        const { value: formValues } = await Swal.fire({
            title: 'Compensar Fondos de Garantía',
            html: `
                <div class="text-start mb-2">
                    <label class="form-label f-s-12 mb-1">Concepto de Compensación:</label>
                    <select id="swal-comp-tipo" class="form-select form-select-sm mb-2">
                        <option value="DANOS">Daños físicos a la unidad / inventario</option>
                        <option value="RENTA">Renta insoluta / saldo impago</option>
                    </select>
                    <label class="form-label f-s-12 mb-1">Monto a deducir de la garantía (S/):</label>
                    <input id="swal-comp-monto" type="number" step="0.01" class="form-control form-control-sm mb-2" placeholder="0.00">
                    <label class="form-label f-s-12 mb-1">Justificación detallada:</label>
                    <textarea id="swal-comp-motivo" class="form-control form-control-sm" rows="2" placeholder="Describa el daño o período adeudado..."></textarea>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Aplicar Compensación',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const tipo = document.getElementById('swal-comp-tipo').value;
                const monto = document.getElementById('swal-comp-monto').value;
                const motivo = document.getElementById('swal-comp-motivo').value;

                if (!monto || parseFloat(monto) <= 0) {
                    Swal.showValidationMessage('Ingrese un monto válido a compensar.');
                    return false;
                }
                if (!motivo.trim()) {
                    Swal.showValidationMessage('Debe fundamentar la causa de la compensación.');
                    return false;
                }
                return { tipo, monto, motivo };
            }
        });

        if (!formValues) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${arrId}/garantia/compensar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    tipo_compensacion: formValues.tipo,
                    monto: formValues.monto,
                    motivo: formValues.motivo
                })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al compensar garantía.');

            await Swal.fire('Compensación Registrada', data.mensaje, 'success');
            abrirDetalleArrendamiento(arrId);
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btn-garantia-devolver')?.addEventListener('click', async () => {
        if (!arrendamientoActualCache) return;
        const arrId = arrendamientoActualCache.arrendamiento.id;

        const { value: formValues } = await Swal.fire({
            title: 'Devolución de Garantía al Titular',
            html: `
                <div class="text-start mb-2">
                    <label class="form-label f-s-12 mb-1">Monto a devolver (S/):</label>
                    <input id="swal-dev-monto" type="number" step="0.01" class="form-control form-control-sm mb-2" placeholder="0.00">
                    <label class="form-label f-s-12 mb-1">Motivo / Acta de entrega:</label>
                    <input id="swal-dev-motivo" type="text" class="form-control form-control-sm" value="Devolución de saldo de garantía conforme al acta de entrega">
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Registrar Devolución',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const monto = document.getElementById('swal-dev-monto').value;
                const motivo = document.getElementById('swal-dev-motivo').value;

                if (!monto || parseFloat(monto) <= 0) {
                    Swal.showValidationMessage('Ingrese un monto válido a devolver.');
                    return false;
                }
                return { monto, motivo };
            }
        });

        if (!formValues) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${arrId}/garantia/devolver`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    monto: formValues.monto,
                    motivo: formValues.motivo
                })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al devolver garantía.');

            await Swal.fire('Devolución Liquidada', data.mensaje, 'success');
            abrirDetalleArrendamiento(arrId);
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // =========================================================================
    // Emisión de Cuota Manual
    // =========================================================================

    document.getElementById('btn-emitir-cuota-manual')?.addEventListener('click', async () => {
        if (!arrendamientoActualCache) return;
        const arrId = arrendamientoActualCache.arrendamiento.id;
        const ahora = new Date();

        const { value: formValues } = await Swal.fire({
            title: 'Generar Cuota Mensual de Renta',
            text: 'Emite el devengo del período e imputa el cargo correspondiente al folio.',
            html: `
                <div class="row g-2 text-start mt-2">
                    <div class="col-6">
                        <label class="form-label f-s-12 mb-1">Año:</label>
                        <input id="swal-cuota-anio" type="number" class="form-control form-control-sm" value="${ahora.getFullYear()}">
                    </div>
                    <div class="col-6">
                        <label class="form-label f-s-12 mb-1">Mes (1-12):</label>
                        <input id="swal-cuota-mes" type="number" min="1" max="12" class="form-control form-control-sm" value="${ahora.getMonth() + 1}">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Generar Cuota',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const anio = document.getElementById('swal-cuota-anio').value;
                const mes = document.getElementById('swal-cuota-mes').value;
                return { anio, mes };
            }
        });

        if (!formValues) return;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${arrId}/cuotas`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    anio: formValues.anio,
                    mes: formValues.mes
                })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al emitir cuota.');

            await Swal.fire('Cuota Procesada', data.mensaje, 'success');
            abrirDetalleArrendamiento(arrId);
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // =========================================================================
    // Envíos de Formularios (PristineJS / Fetch)
    // =========================================================================

    if (btnAbrirCrear) {
        btnAbrirCrear.addEventListener('click', () => {
            formCrear.reset();
            modalCrear.show();
        });
    }

    formCrear.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(formCrear);
        const payload = Object.fromEntries(formData.entries());
        payload._csrf = csrfToken;

        try {
            Swal.showLoading();
            const resp = await fetch('/api/arrendamientos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al crear contrato.');

            await Swal.fire('¡Borrador Creado!', data.mensaje, 'success');
            modalCrear.hide();
            cargarArrendamientos();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    formProrrogar.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = document.getElementById('prorrogar-arrendamiento-id').value;
        const nuevaFechaFin = document.getElementById('prorrogar-nueva-fecha-fin').value;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}/prorrogar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    nueva_fecha_fin: nuevaFechaFin
                })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al prorrogar contrato.');

            await Swal.fire('¡Contrato Prorrogado!', data.mensaje, 'success');
            modalProrrogar.hide();
            modalDetalle.hide();
            cargarArrendamientos();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    formRescindir.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = document.getElementById('rescindir-arrendamiento-id').value;
        const fechaEfectiva = document.getElementById('rescindir-fecha-efectiva').value;
        const motivo = document.getElementById('rescindir-motivo').value;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}/rescindir`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    fecha_efectiva: fechaEfectiva,
                    motivo: motivo
                })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al rescindir contrato.');

            await Swal.fire('¡Contrato Rescindido!', data.mensaje, 'success');
            modalRescindir.hide();
            modalDetalle.hide();
            cargarArrendamientos();
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    document.getElementById('btn-abrir-agregar-persona')?.addEventListener('click', () => {
        if (!arrendamientoActualCache) return;
        document.getElementById('persona-arrendamiento-id').value = arrendamientoActualCache.arrendamiento.id;
        formAgregarPersona.reset();
        modalAgregarPersona.show();
    });

    formAgregarPersona.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = document.getElementById('persona-arrendamiento-id').value;
        const personaId = document.getElementById('persona-id-input').value;
        const tipoRelacion = document.getElementById('persona-tipo-relacion').value;
        const observaciones = document.getElementById('persona-observaciones').value;

        try {
            Swal.showLoading();
            const resp = await fetch(`/api/arrendamientos/${id}/personas`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    persona_id: personaId,
                    tipo_relacion: tipoRelacion,
                    observaciones: observaciones
                })
            });

            const data = await resp.json();
            if (!data.ok) throw new Error(data.mensaje || 'Error al incorporar residente.');

            await Swal.fire('Residente Incorporado', data.mensaje, 'success');
            modalAgregarPersona.hide();
            abrirDetalleArrendamiento(id);
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });

    // =========================================================================
    // Filtros y Limpieza
    // =========================================================================

    let debounceTimer = null;
    filtroBusqueda.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(cargarArrendamientos, 350);
    });

    filtroPropiedad.addEventListener('change', cargarArrendamientos);
    filtroEstado.addEventListener('change', cargarArrendamientos);

    btnLimpiar.addEventListener('click', () => {
        filtroBusqueda.value = '';
        filtroPropiedad.value = '';
        filtroEstado.value = '';
        cargarArrendamientos();
    });

    // Inicio automático
    cargarArrendamientos();
});
