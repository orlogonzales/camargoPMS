/**
 * Módulo JavaScript para la Gestión de Housekeeping, Pisos y Control de Lencería (HOUSEKEEPING-1 / D-083).
 *
 * Arquitectura:
 * - Vanilla JS moderno con Fetch API y SweetAlert2.
 * - Sin duplicación de estado comercial en cliente; consumo de DTOs derivados en vivo.
 */
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.getElementById('csrf-token-global')?.value || '';
    let rackData = [];
    let tareasData = [];
    let lotesData = [];

    // Modales Bootstrap
    const modalCrearTareaEl = document.getElementById('modal-crear-tarea');
    const modalCrearTarea = modalCrearTareaEl ? new bootstrap.Modal(modalCrearTareaEl) : null;
    const modalInspeccionarEl = document.getElementById('modal-inspeccionar-tarea');
    const modalInspeccionar = modalInspeccionarEl ? new bootstrap.Modal(modalInspeccionarEl) : null;
    const modalFinalizarEl = document.getElementById('modal-finalizar-limpieza');
    const modalFinalizar = modalFinalizarEl ? new bootstrap.Modal(modalFinalizarEl) : null;
    const modalDesperfectoEl = document.getElementById('modal-reportar-desperfecto');
    const modalDesperfecto = modalDesperfectoEl ? new bootstrap.Modal(modalDesperfectoEl) : null;

    // Inicialización
    cargarRack();
    cargarTareas();
    cargarLotes();

    // Event listeners de botones y filtros
    document.getElementById('btn-recargar-rack')?.addEventListener('click', cargarRack);
    document.getElementById('filtro-rack-propiedad')?.addEventListener('change', cargarRack);
    document.getElementById('filtro-rack-piso')?.addEventListener('change', cargarRack);

    document.getElementById('btn-recargar-tareas')?.addEventListener('click', cargarTareas);
    document.getElementById('btn-recargar-lavanderia')?.addEventListener('click', cargarLotes);

    document.getElementById('btn-nueva-tarea')?.addEventListener('click', abrirModalCrearTarea);
    document.getElementById('form-crear-tarea')?.addEventListener('submit', guardarNuevaTarea);

    document.getElementById('btn-confirmar-finalizacion')?.addEventListener('click', confirmarFinalizarLimpieza);
    document.getElementById('btn-aprobar-inspeccion')?.addEventListener('click', () => procesarInspeccion(true));
    document.getElementById('btn-rechazar-inspeccion')?.addEventListener('click', () => procesarInspeccion(false));
    document.getElementById('btn-enviar-desperfecto')?.addEventListener('click', enviarDesperfecto);

    // =========================================================================
    // 1. RACK OPERACIONAL DE PISOS (DERIVACIÓN DINÁMICA D-083)
    // =========================================================================
    async function cargarRack() {
        const contenedor = document.getElementById('contenedor-rack-pisos');
        if (!contenedor) return;

        const propId = document.getElementById('filtro-rack-propiedad')?.value || '';
        const piso = document.getElementById('filtro-rack-piso')?.value || '';

        try {
            const url = new URL('/api/housekeeping/rack', window.location.origin);
            if (propId) url.searchParams.append('propiedad_id', propId);
            if (piso) url.searchParams.append('piso', piso);

            const res = await fetch(url.toString());
            const data = await res.json();

            if (!data.exito) {
                mostrarNotificacion('error', data.error || 'Error al cargar el rack');
                return;
            }

            rackData = data.rack || [];
            actualizarKpis(data.kpis || {});
            renderizarRack(rackData);
            actualizarSelectUnidades(rackData);
        } catch (err) {
            console.error('Error al cargar rack:', err);
            contenedor.innerHTML = '<div class="col-12 text-center text-danger py-4">Error de conexión al cargar el rack.</div>';
        }
    }

    function actualizarKpis(kpis) {
        document.getElementById('kpi-vr').textContent = kpis.vr || 0;
        document.getElementById('kpi-vd').textContent = (kpis.vd || 0) + (kpis.en_proceso || 0);
        document.getElementById('kpi-vcl').textContent = kpis.vcl || 0;
        document.getElementById('kpi-ooo').textContent = kpis.ooo || 0;
    }

    function renderizarRack(unidades) {
        const contenedor = document.getElementById('contenedor-rack-pisos');
        if (!contenedor) return;

        if (unidades.length === 0) {
            contenedor.innerHTML = '<div class="col-12 text-center text-muted py-5"><p>No se encontraron habitaciones para el filtro seleccionado.</p></div>';
            return;
        }

        let html = '';
        unidades.forEach(u => {
            const cond = u.condicion_derivada; // 'VR', 'VD', 'VCL', 'OD', 'OC', 'OOO', 'OOS'
            let badgeClase = 'bg-secondary';
            let badgeTexto = cond;
            let borderClase = 'border-secondary';

            switch (cond) {
                case 'VR':
                    badgeClase = 'bg-success';
                    badgeTexto = 'VR — LISTA (CHECK-IN)';
                    borderClase = 'border-success';
                    break;
                case 'VD':
                    badgeClase = 'bg-danger';
                    badgeTexto = u.estado_limpieza === 'EN_LIMPIEZA' ? 'EN LIMPIEZA' : 'VD — SUCIA';
                    borderClase = 'border-danger';
                    break;
                case 'VCL':
                    badgeClase = 'bg-warning text-dark';
                    badgeTexto = 'VCL — POR INSPECCIONAR';
                    borderClase = 'border-warning';
                    break;
                case 'OD':
                    badgeClase = 'bg-dark';
                    badgeTexto = 'OD — OCUPADA SUCIA';
                    borderClase = 'border-dark';
                    break;
                case 'OC':
                    badgeClase = 'bg-primary';
                    badgeTexto = 'OC — OCUPADA LIMPIA';
                    borderClase = 'border-primary';
                    break;
                case 'OOO':
                    badgeClase = 'bg-dark text-white';
                    badgeTexto = 'OOO — MANTENIMIENTO';
                    borderClase = 'border-danger';
                    break;
                case 'OOS':
                    badgeClase = 'bg-secondary';
                    badgeTexto = 'OOS — FUERA SERVICIO';
                    borderClase = 'border-secondary';
                    break;
            }

            html += `
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                <div class="card h-100 shadow-sm border ${borderClase} b-r-12">
                    <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="f-s-18 f-w-700 text-dark">#${u.unidad_numero}</span>
                            <span class="badge ${badgeClase} f-s-10">${badgeTexto}</span>
                        </div>
                        <span class="f-s-11 text-muted">Piso ${u.piso || 1}</span>
                    </div>
                    <div class="card-body p-3">
                        <p class="f-s-12 text-muted mb-1">${u.tipo_unidad_nombre || 'Habitación'} | ${u.propiedad_nombre}</p>
                        <div class="d-flex justify-content-between f-s-12 mb-2">
                            <span>Higiene:</span>
                            <strong class="text-uppercase">${u.estado_limpieza}</strong>
                        </div>
                        <div class="d-flex justify-content-between f-s-12 mb-2">
                            <span>Ocupación:</span>
                            <strong class="${u.estado_ocupacion === 'OCUPADA' ? 'text-primary' : 'text-success'}">${u.estado_ocupacion}</strong>
                        </div>
                        ${u.tarea_activa_codigo ? `
                        <div class="p-2 bg-light b-r-8 f-s-11 mb-2">
                            <span class="text-muted d-block">Tarea: <strong>${u.tarea_activa_codigo}</strong> (${u.tarea_activa_estado})</span>
                            ${u.camarera_nombre ? `<span class="text-muted d-block">Camarera: ${u.camarera_nombre}</span>` : ''}
                        </div>` : ''}
                        ${u.tiene_bloqueo_mantenimiento ? `
                        <div class="p-2 bg-light-danger text-danger b-r-8 f-s-11 mb-2">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Bloqueo por Mantenimiento
                        </div>` : ''}
                    </div>
                    <div class="card-footer bg-white py-2 px-3 border-top d-flex gap-1 justify-content-end">
                        ${u.tarea_activa_id ? `
                        <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2 f-s-11" onclick="abrirDetalleTarea(${u.tarea_activa_id})">
                            <i class="fa-solid fa-eye me-1"></i> Ver Tarea
                        </button>` : `
                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 f-s-11" onclick="crearTareaDirecta(${u.unidad_id})">
                            <i class="fa-solid fa-plus me-1"></i> Limpieza
                        </button>`}
                        <button type="button" class="btn btn-outline-warning btn-sm py-1 px-2 f-s-11" onclick="abrirModalDesperfecto(${u.tarea_activa_id || 0}, ${u.unidad_id})">
                            <i class="fa-solid fa-wrench"></i>
                        </button>
                    </div>
                </div>
            </div>`;
        });

        contenedor.innerHTML = html;
    }

    function actualizarSelectUnidades(unidades) {
        const select = document.getElementById('tarea-unidad-id');
        if (!select) return;
        select.innerHTML = '<option value="">Seleccione una habitación...</option>';
        unidades.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.unidad_id;
            opt.textContent = `Hab. ${u.unidad_numero} (${u.tipo_unidad_nombre} - ${u.propiedad_nombre})`;
            select.appendChild(opt);
        });
    }

    // =========================================================================
    // 2. TABLERO DE TAREAS Y CHECKLISTS (HOUSEKEEPING-1 / D-083)
    // =========================================================================
    async function cargarTareas() {
        const tbody = document.getElementById('tbody-tareas');
        if (!tbody) return;

        const fecha = document.getElementById('filtro-tarea-fecha')?.value || '';
        const estado = document.getElementById('filtro-tarea-estado')?.value || '';
        const tipo = document.getElementById('filtro-tarea-tipo')?.value || '';

        try {
            const url = new URL('/api/housekeeping/tareas', window.location.origin);
            if (fecha) url.searchParams.append('fecha_programada', fecha);
            if (estado) url.searchParams.append('estado', estado);
            if (tipo) url.searchParams.append('tipo_tarea', tipo);

            const res = await fetch(url.toString());
            const data = await res.json();

            if (!data.exito) return;
            tareasData = data.tareas || [];
            renderizarTablaTareas(tareasData);
        } catch (err) {
            console.error('Error al cargar tareas:', err);
        }
    }

    function renderizarTablaTareas(tareas) {
        const tbody = document.getElementById('tbody-tareas');
        if (!tbody) return;

        if (tareas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron tareas de housekeeping.</td></tr>';
            return;
        }

        let html = '';
        tareas.forEach(t => {
            let badgeEstado = 'bg-secondary';
            switch (t.estado) {
                case 'COMPLETADA': badgeEstado = 'bg-success'; break;
                case 'POR_INSPECCIONAR': badgeEstado = 'bg-warning text-dark'; break;
                case 'EN_PROCESO': badgeEstado = 'bg-info text-white'; break;
                case 'ASIGNADA': badgeEstado = 'bg-primary'; break;
                case 'RECHAZADA': badgeEstado = 'bg-danger'; break;
                case 'CANCELADA': badgeEstado = 'bg-dark'; break;
            }

            let badgePrio = 'bg-secondary';
            if (t.prioridad === 'URGENTE') badgePrio = 'bg-danger';
            else if (t.prioridad === 'ALTA') badgePrio = 'bg-warning text-dark';
            else if (t.prioridad === 'MEDIA') badgePrio = 'bg-info text-white';

            html += `
            <tr>
                <td><strong>${t.codigo}</strong></td>
                <td>Hab. ${t.unidad_numero} <span class="f-s-11 text-muted d-block">${t.propiedad_nombre}</span></td>
                <td><span class="badge bg-light text-dark border">${t.tipo_tarea}</span></td>
                <td><span class="badge ${badgePrio}">${t.prioridad}</span></td>
                <td><span class="badge ${badgeEstado}">${t.estado}</span></td>
                <td>${t.camarera_nombre || '<span class="text-muted">Sin asignar</span>'}</td>
                <td>${t.fecha_programada}</td>
                <td class="text-end">
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Acciones
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="abrirDetalleTarea(${t.id})"><i class="fa-solid fa-eye me-2"></i>Ver Checklist</a></li>
                            ${t.estado === 'PENDIENTE' || t.estado === 'ASIGNADA' || t.estado === 'RECHAZADA' ? `
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="iniciarLimpieza(${t.id})"><i class="fa-solid fa-play text-info me-2"></i>Iniciar Limpieza</a></li>` : ''}
                            ${t.estado === 'EN_PROCESO' ? `
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="abrirModalFinalizar(${t.id})"><i class="fa-solid fa-paper-plane text-warning me-2"></i>Enviar a Inspección</a></li>` : ''}
                            ${t.estado === 'POR_INSPECCIONAR' || t.estado === 'EN_PROCESO' ? `
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="abrirModalInspeccion(${t.id})"><i class="fa-solid fa-clipboard-check text-success me-2"></i>Inspeccionar</a></li>` : ''}
                        </ul>
                    </div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // Modal Crear Tarea Manual
    function abrirModalCrearTarea() {
        document.getElementById('form-crear-tarea')?.reset();
        modalCrearTarea?.show();
    }

    window.crearTareaDirecta = function(unidadId) {
        abrirModalCrearTarea();
        const sel = document.getElementById('tarea-unidad-id');
        if (sel) sel.value = unidadId;
    };

    async function guardarNuevaTarea(e) {
        e.preventDefault();
        const unidadId = document.getElementById('tarea-unidad-id')?.value;
        const tipoTarea = document.getElementById('tarea-tipo')?.value;
        const prioridad = document.getElementById('tarea-prioridad')?.value;
        const fecha = document.getElementById('tarea-fecha')?.value;
        const notas = document.getElementById('tarea-notas')?.value;

        if (!unidadId) {
            mostrarNotificacion('warning', 'Debe seleccionar una habitación.');
            return;
        }

        try {
            const res = await fetch('/api/housekeeping/tareas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    unidad_id: parseInt(unidadId),
                    tipo_tarea: tipoTarea,
                    prioridad: prioridad,
                    fecha_programada: fecha,
                    notas_operario: notas,
                }),
            });

            const data = await res.json();
            if (data.exito) {
                mostrarNotificacion('success', data.mensaje);
                modalCrearTarea?.hide();
                cargarRack();
                cargarTareas();
            } else {
                mostrarNotificacion('error', data.error || 'Error al crear tarea');
            }
        } catch (err) {
            console.error('Error al guardar tarea:', err);
            mostrarNotificacion('error', 'Error en la petición.');
        }
    }

    // Iniciar limpieza
    window.iniciarLimpieza = async function(tareaId) {
        try {
            const res = await fetch(`/api/housekeeping/tareas/${tareaId}/iniciar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            const data = await res.json();
            if (data.exito) {
                mostrarNotificacion('success', data.mensaje);
                cargarRack();
                cargarTareas();
            } else {
                mostrarNotificacion('error', data.error || 'Error al iniciar limpieza');
            }
        } catch (err) {
            console.error('Error:', err);
        }
    };

    // Modal Finalizar Limpieza
    window.abrirModalFinalizar = function(tareaId) {
        document.getElementById('fin-tarea-id').value = tareaId;
        modalFinalizar?.show();
    };

    async function confirmarFinalizarLimpieza() {
        const tareaId = document.getElementById('fin-tarea-id')?.value;
        const condicion = document.getElementById('fin-condicion')?.value;
        const notas = document.getElementById('fin-notas')?.value;

        try {
            const res = await fetch(`/api/housekeeping/tareas/${tareaId}/finalizar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    condicion_operacional: condicion,
                    notas_operario: notas,
                }),
            });
            const data = await res.json();
            if (data.exito) {
                mostrarNotificacion('success', data.mensaje);
                modalFinalizar?.hide();
                cargarRack();
                cargarTareas();
            } else {
                mostrarNotificacion('error', data.error || 'Error al finalizar');
            }
        } catch (err) {
            console.error('Error:', err);
        }
    }

    // Modal Inspección y Checklist
    window.abrirModalInspeccion = async function(tareaId) {
        try {
            const res = await fetch(`/api/housekeeping/tareas/${tareaId}`);
            const data = await res.json();
            if (!data.exito) return;

            const t = data.tarea;
            document.getElementById('insp-tarea-id').value = t.id;
            document.getElementById('insp-subtitulo').textContent = `Habitación ${t.unidad_numero} — Tarea ${t.codigo} (${t.tipo_tarea})`;

            const tbody = document.getElementById('tbody-checklist-items');
            tbody.innerHTML = '';

            (t.checklist || []).forEach(chk => {
                const tr = document.createElement('tr');
                tr.dataset.checkId = chk.id;
                tr.dataset.esCritico = chk.es_critico_snapshot ? '1' : '0';

                tr.innerHTML = `
                    <td><span class="badge bg-light text-dark">${chk.categoria_snapshot}</span></td>
                    <td>
                        <strong>${chk.descripcion_snapshot}</strong>
                    </td>
                    <td class="text-center">
                        ${chk.es_critico_snapshot ? '<span class="badge bg-danger">SÍ</span>' : '<span class="badge bg-light text-muted">NO</span>'}
                    </td>
                    <td>
                        <select class="form-select form-select-sm select-resultado-chk">
                            <option value="CONFORME" ${chk.resultado === 'CONFORME' ? 'selected' : ''}>CONFORME</option>
                            <option value="NO_CONFORME" ${chk.resultado === 'NO_CONFORME' ? 'selected' : ''}>NO CONFORME</option>
                            <option value="NO_APLICA" ${chk.resultado === 'NO_APLICA' ? 'selected' : ''}>NO APLICA</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm input-obs-chk" value="${chk.observacion || ''}" placeholder="Obs...">
                    </td>
                `;
                tbody.appendChild(tr);
            });

            modalInspeccionar?.show();
        } catch (err) {
            console.error('Error al cargar detalle tarea:', err);
        }
    };

    window.abrirDetalleTarea = function(tareaId) {
        abrirModalInspeccion(tareaId);
    };

    async function procesarInspeccion(aprobada) {
        const tareaId = document.getElementById('insp-tarea-id')?.value;
        const notas = document.getElementById('insp-notas')?.value;
        const filas = document.querySelectorAll('#tbody-checklist-items tr');
        const checklist = [];

        filas.forEach(f => {
            checklist.push({
                id: parseInt(f.dataset.checkId),
                resultado: f.querySelector('.select-resultado-chk')?.value,
                observacion: f.querySelector('.input-obs-chk')?.value,
            });
        });

        try {
            const res = await fetch(`/api/housekeeping/tareas/${tareaId}/inspeccionar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    aprobada: aprobada,
                    checklist: checklist,
                    notas_supervisor: notas,
                }),
            });
            const data = await res.json();
            if (data.exito) {
                mostrarNotificacion(aprobada ? 'success' : 'warning', data.mensaje);
                modalInspeccionar?.hide();
                cargarRack();
                cargarTareas();
            } else {
                mostrarNotificacion('error', data.error || 'Error en la inspección');
            }
        } catch (err) {
            console.error('Error:', err);
        }
    }

    // Modal Reportar Desperfecto Físico
    window.abrirModalDesperfecto = function(tareaId, unidadId) {
        document.getElementById('desp-tarea-id').value = tareaId;
        document.getElementById('desp-titulo').value = '';
        document.getElementById('desp-descripcion').value = '';
        modalDesperfecto?.show();
    };

    async function enviarDesperfecto() {
        const tareaId = document.getElementById('desp-tarea-id')?.value;
        const titulo = document.getElementById('desp-titulo')?.value;
        const prioridad = document.getElementById('desp-prioridad')?.value;
        const descripcion = document.getElementById('desp-descripcion')?.value;

        if (!descripcion || !titulo) {
            mostrarNotificacion('warning', 'Debe completar el título y la descripción del desperfecto.');
            return;
        }

        try {
            const res = await fetch(`/api/housekeeping/tareas/${tareaId}/desperfecto`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    titulo: titulo,
                    prioridad: prioridad,
                    descripcion: descripcion,
                }),
            });
            const data = await res.json();
            if (data.exito) {
                mostrarNotificacion('success', data.mensaje);
                modalDesperfecto?.hide();
            } else {
                mostrarNotificacion('error', data.error || 'Error al reportar');
            }
        } catch (err) {
            console.error('Error:', err);
        }
    }

    // =========================================================================
    // 3. CONTROL DE LENCERÍA Y LAVANDERÍA (HOUSEKEEPING-1 / D-083)
    // =========================================================================
    async function cargarLotes() {
        const tbody = document.getElementById('tbody-lotes');
        if (!tbody) return;

        try {
            const res = await fetch('/api/housekeeping/lavanderia/lotes');
            const data = await res.json();
            if (!data.exito) return;

            lotesData = data.lotes || [];
            renderizarTablaLotes(lotesData);
        } catch (err) {
            console.error('Error al cargar lotes:', err);
        }
    }

    function renderizarTablaLotes(lotes) {
        const tbody = document.getElementById('tbody-lotes');
        if (!tbody) return;

        if (lotes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay lotes de lavandería registrados.</td></tr>';
            return;
        }

        let html = '';
        lotes.forEach(l => {
            let badge = 'bg-secondary';
            if (l.estado === 'RETORNADO_TOTAL') badge = 'bg-success';
            else if (l.estado === 'DESPACHADO') badge = 'bg-primary';
            else if (l.estado === 'CON_DISCREPANCIA') badge = 'bg-danger';

            html += `
            <tr>
                <td><strong>${l.codigo}</strong></td>
                <td>${l.propiedad_nombre}</td>
                <td>${l.almacen_origen_nombre}</td>
                <td>${l.lavanderia_nombre}</td>
                <td>${l.fecha_despacho}</td>
                <td><span class="badge ${badge}">${l.estado}</span></td>
                <td class="text-end">
                    <span class="text-muted f-s-12">Detalles</span>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // Notificaciones con SweetAlert2 o fallback alert
    function mostrarNotificacion(tipo, mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: tipo === 'error' ? 'error' : (tipo === 'warning' ? 'warning' : 'success'),
                text: mensaje,
                timer: 3000,
                showConfirmButton: false,
            });
        } else {
            alert(mensaje);
        }
    }
});
