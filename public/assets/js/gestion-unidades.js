/**
 * Camargo PMS — Módulo de Gestión de Unidades Físicas (UNIDADES-1)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principio: PROPIEDAD ≠ UNIDAD.
 * Principio: UNIDAD ≠ REGISTRO DESECHABLE (no DELETE físico, ciclo ACTIVO ↔ INACTIVO).
 */
document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------------------
    // 1. Elementos del DOM y Estado del Módulo
    // -------------------------------------------------------------------------
    const csrfToken = document.getElementById('csrf-token-global')?.value || '';
    const tablaUnidades = document.getElementById('tabla-unidades');
    const tbodyUnidades = document.getElementById('tbody-unidades');
    const estadoVacio = document.getElementById('estado-vacio-unidades');
    const infoPaginacion = document.getElementById('info-paginacion-unidades');
    const paginacionUl = document.getElementById('paginacion-unidades');

    const filtroBusqueda = document.getElementById('filtro-busqueda-unidad');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda-unidad');
    const filtroPropiedad = document.getElementById('filtro-propiedad-unidad');
    const filtroTipo = document.getElementById('filtro-tipo-unidad');
    const filtroEstado = document.getElementById('filtro-estado-unidad');
    const btnRecargar = document.getElementById('btn-recargar-unidades');

    const modalUnidadEl = document.getElementById('modal-unidad');
    const modalUnidad = modalUnidadEl ? new bootstrap.Modal(modalUnidadEl) : null;
    const formUnidad = document.getElementById('form-unidad');
    const modalUnidadTitulo = document.getElementById('modal-unidad-titulo');
    const btnAbrirCrear = document.getElementById('btn-abrir-crear-unidad');
    const btnCrearVacio = document.getElementById('btn-crear-unidad-vacio');
    const btnGuardar = document.getElementById('btn-guardar-unidad');
    const spinnerGuardar = document.getElementById('spinner-guardar-unidad');

    const modalEstadoEl = document.getElementById('modal-estado-unidad');
    const modalEstado = modalEstadoEl ? new bootstrap.Modal(modalEstadoEl) : null;
    const formEstado = document.getElementById('form-estado-unidad');
    const estadoUnidadId = document.getElementById('estado-unidad-id');
    const estadoUnidadNuevo = document.getElementById('estado-unidad-nuevo');
    const mensajeConfirmacionEstado = document.getElementById('mensaje-confirmacion-estado-unidad');
    const motivoCambioEstado = document.getElementById('motivo-cambio-estado-unidad');

    // Botones en vista de perfil
    const btnEditarPerfil = document.getElementById('btn-editar-unidad-perfil');
    const btnCambiarEstadoPerfil = document.getElementById('btn-cambiar-estado-perfil');

    let estadoModulo = {
        pagina: 1,
        limite: 15,
        totalPaginas: 1,
        busqueda: '',
        propiedadId: filtroPropiedad?.value || '',
        tipoUnidadId: '',
        estado: '',
    };

    let debounceTimer = null;
    let pristine = null;

    // Inicializar PristineJS si existe el formulario
    if (formUnidad && typeof Pristine !== 'undefined') {
        pristine = new Pristine(formUnidad, {
            classTo: 'col-md-3,col-md-4,col-md-5,col-md-6,col-12',
            errorClass: 'has-danger',
            successClass: 'has-success',
            errorTextParent: 'col-md-3,col-md-4,col-md-5,col-md-6,col-12',
            errorTextTag: 'div',
            errorTextClass: 'text-danger f-s-12 mt-1',
        });
    }

    // -------------------------------------------------------------------------
    // 2. Funciones de Carga y Renderizado (Catálogo General)
    // -------------------------------------------------------------------------
    async function cargarUnidades(pagina = 1) {
        if (!tbodyUnidades) return;

        estadoModulo.pagina = pagina;
        tbodyUnidades.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Cargando catálogo de unidades...
                </td>
            </tr>
        `;

        const params = new URLSearchParams({
            pagina: estadoModulo.pagina.toString(),
            limite: estadoModulo.limite.toString(),
        });

        if (estadoModulo.busqueda.trim()) {
            params.append('busqueda', estadoModulo.busqueda.trim());
        }
        if (estadoModulo.propiedadId) {
            params.append('propiedad_id', estadoModulo.propiedadId);
        }
        if (estadoModulo.tipoUnidadId) {
            params.append('tipo_unidad_id', estadoModulo.tipoUnidadId);
        }
        if (estadoModulo.estado) {
            params.append('estado', estadoModulo.estado);
        }

        try {
            const respuesta = await fetch(`/unidades/datos?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!respuesta.ok) {
                throw new Error(`Error HTTP ${respuesta.status}`);
            }

            const resultado = await respuesta.json();
            if (!resultado.ok) {
                throw new Error(resultado.mensaje || 'Error al obtener unidades.');
            }

            renderizarTabla(resultado.datos);
            renderizarPaginacion(resultado.paginacion);
        } catch (error) {
            tbodyUnidades.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        <i class="ti ti-alert-triangle me-1"></i> No se pudo cargar el catálogo de unidades.
                    </td>
                </tr>
            `;
            console.error('Error al cargar unidades:', error);
        }
    }

    function renderizarTabla(unidades) {
        if (!tbodyUnidades) return;

        if (!unidades || unidades.length === 0) {
            tbodyUnidades.innerHTML = '';
            tablaUnidades?.classList.add('d-none');
            estadoVacio?.classList.remove('d-none');
            return;
        }

        tablaUnidades?.classList.remove('d-none');
        estadoVacio?.classList.add('d-none');

        tbodyUnidades.innerHTML = unidades.map(u => {
            const esActiva = u.estado === 'ACTIVO';
            const estadoBadge = esActiva
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle f-s-11">ACTIVO</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle f-s-11">INACTIVO</span>';

            const pisoTxt = u.piso_nivel ? `<span class="badge bg-light text-secondary border me-1">${escapeHtml(u.piso_nivel)}</span>` : '';
            const areaTxt = u.area_m2 ? `${u.area_m2} m²` : '';

            return `
                <tr data-id="${u.id}">
                    <td>
                        <span class="f-w-700 text-dark">${escapeHtml(u.codigo)}</span>
                    </td>
                    <td>
                        <a href="/unidades/${u.id}/perfil" class="text-decoration-none fw-semibold text-primary d-block">
                            ${escapeHtml(u.nombre)}
                        </a>
                        ${u.descripcion ? `<small class="text-muted text-truncate d-inline-block" style="max-width: 250px;">${escapeHtml(u.descripcion)}</small>` : ''}
                    </td>
                    <td>
                        <a href="/propiedades/${u.propiedad_id}/perfil" class="text-decoration-none text-secondary">
                            <i class="ti ti-building me-1 text-muted"></i>${escapeHtml(u.propiedad_nombre || 'Propiedad #' + u.propiedad_id)}
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-info-subtle text-info border border-info-subtle f-s-11">
                            ${escapeHtml(u.tipo_unidad_nombre || 'Unidad')}
                        </span>
                    </td>
                    <td class="f-s-12 text-secondary">
                        ${pisoTxt}
                        <span title="Capacidad de personas"><i class="ti ti-users me-1 text-muted"></i>${u.capacidad_personas}</span> · 
                        <span title="Dormitorios"><i class="ti ti-bed me-1 text-muted"></i>${u.dormitorios}</span> · 
                        <span title="Baños"><i class="ti ti-bath me-1 text-muted"></i>${u.banos}</span>
                        ${areaTxt ? ` · <span title="Área"><i class="ti ti-dimensions me-1 text-muted"></i>${areaTxt}</span>` : ''}
                    </td>
                    <td class="text-center">
                        ${estadoBadge}
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="/unidades/${u.id}/perfil" class="btn btn-outline-secondary" title="Ver ficha técnica">
                                <i class="ti ti-eye"></i>
                            </a>
                            <button type="button" class="btn btn-outline-primary btn-editar-unidad" data-id="${u.id}" title="Editar unidad">
                                <i class="ti ti-edit"></i>
                            </button>
                            ${esActiva
                                ? `<button type="button" class="btn btn-outline-danger btn-cambiar-estado" data-id="${u.id}" data-nuevo-estado="INACTIVO" title="Desactivar unidad">
                                        <i class="ti ti-ban"></i>
                                   </button>`
                                : `<button type="button" class="btn btn-outline-success btn-cambiar-estado" data-id="${u.id}" data-nuevo-estado="ACTIVO" title="Activar unidad">
                                        <i class="ti ti-check"></i>
                                   </button>`
                            }
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderizarPaginacion(pag) {
        if (!paginacionUl || !infoPaginacion) return;

        estadoModulo.totalPaginas = pag.paginas;
        const inicio = pag.total > 0 ? (pag.pagina - 1) * pag.limite + 1 : 0;
        const fin = Math.min(pag.pagina * pag.limite, pag.total);

        infoPaginacion.textContent = `Mostrando ${inicio} a ${fin} de ${pag.total} unidades`;

        if (pag.paginas <= 1) {
            paginacionUl.innerHTML = '';
            return;
        }

        let html = '';
        // Botón Anterior
        html += `
            <li class="page-item ${pag.pagina === 1 ? 'disabled' : ''}">
                <button class="page-link" data-pagina="${pag.pagina - 1}" aria-label="Anterior">&laquo;</button>
            </li>
        `;

        for (let p = 1; p <= pag.paginas; p++) {
            if (p === 1 || p === pag.paginas || (p >= pag.pagina - 1 && p <= pag.pagina + 1)) {
                html += `
                    <li class="page-item ${p === pag.pagina ? 'active' : ''}">
                        <button class="page-link" data-pagina="${p}">${p}</button>
                    </li>
                `;
            } else if (p === pag.pagina - 2 || p === pag.pagina + 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Botón Siguiente
        html += `
            <li class="page-item ${pag.pagina === pag.paginas ? 'disabled' : ''}">
                <button class="page-link" data-pagina="${pag.pagina + 1}" aria-label="Siguiente">&raquo;</button>
            </li>
        `;

        paginacionUl.innerHTML = html;
    }

    // -------------------------------------------------------------------------
    // 3. Eventos de Filtrado y Búsqueda
    // -------------------------------------------------------------------------
    filtroBusqueda?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            estadoModulo.busqueda = filtroBusqueda.value;
            cargarUnidades(1);
        }, 300);
    });

    btnLimpiarBusqueda?.addEventListener('click', () => {
        if (filtroBusqueda) {
            filtroBusqueda.value = '';
            estadoModulo.busqueda = '';
            cargarUnidades(1);
        }
    });

    filtroPropiedad?.addEventListener('change', () => {
        estadoModulo.propiedadId = filtroPropiedad.value;
        cargarUnidades(1);
    });

    filtroTipo?.addEventListener('change', () => {
        estadoModulo.tipoUnidadId = filtroTipo.value;
        cargarUnidades(1);
    });

    filtroEstado?.addEventListener('change', () => {
        estadoModulo.estado = filtroEstado.value;
        cargarUnidades(1);
    });

    btnRecargar?.addEventListener('click', () => {
        cargarUnidades(estadoModulo.pagina);
    });

    paginacionUl?.addEventListener('click', (e) => {
        const btn = e.target.closest('button.page-link');
        if (btn && btn.dataset.pagina) {
            const p = parseInt(btn.dataset.pagina, 10);
            if (!isNaN(p) && p >= 1 && p <= estadoModulo.totalPaginas && p !== estadoModulo.pagina) {
                cargarUnidades(p);
            }
        }
    });

    // -------------------------------------------------------------------------
    // 4. Modal: Crear / Editar Unidad
    // -------------------------------------------------------------------------
    function abrirModalCrear() {
        if (!formUnidad || !modalUnidad) return;

        formUnidad.reset();
        document.getElementById('unidad-id').value = '';
        if (modalUnidadTitulo) {
            modalUnidadTitulo.innerHTML = '<i class="ti ti-door me-2 text-primary"></i>Nueva Unidad';
        }

        // Si hay una propiedad preseleccionada en el filtro, asignarla
        if (estadoModulo.propiedadId) {
            const selectProp = document.getElementById('unidad-propiedad-id');
            if (selectProp) selectProp.value = estadoModulo.propiedadId;
        }

        pristine?.reset();
        modalUnidad.show();
    }

    btnAbrirCrear?.addEventListener('click', abrirModalCrear);
    btnCrearVacio?.addEventListener('click', abrirModalCrear);

    async function abrirModalEditar(id) {
        if (!formUnidad || !modalUnidad) return;

        formUnidad.reset();
        pristine?.reset();

        try {
            const respuesta = await fetch(`/unidades/${id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!respuesta.ok) {
                throw new Error(`Error HTTP ${respuesta.status}`);
            }

            const data = await respuesta.json();
            if (!data.ok || !data.unidad) {
                throw new Error(data.mensaje || 'No se pudo cargar la unidad.');
            }

            const u = data.unidad;
            document.getElementById('unidad-id').value = u.id;
            document.getElementById('unidad-propiedad-id').value = u.propiedad_id;
            document.getElementById('unidad-tipo-id').value = u.tipo_unidad_id;
            document.getElementById('unidad-codigo').value = u.codigo;
            document.getElementById('unidad-nombre').value = u.nombre;
            document.getElementById('unidad-piso-nivel').value = u.piso_nivel || '';
            document.getElementById('unidad-capacidad').value = u.capacidad_personas;
            document.getElementById('unidad-dormitorios').value = u.dormitorios;
            document.getElementById('unidad-banos').value = u.banos;
            document.getElementById('unidad-area-m2').value = u.area_m2 || '';
            document.getElementById('unidad-descripcion').value = u.descripcion || '';
            document.getElementById('unidad-observaciones').value = u.observaciones || '';

            if (modalUnidadTitulo) {
                modalUnidadTitulo.innerHTML = `<i class="ti ti-edit me-2 text-primary"></i>Editar Unidad: ${escapeHtml(u.codigo)}`;
            }

            modalUnidad.show();
        } catch (error) {
            console.error('Error al abrir modal de edición:', error);
            mostrarAlerta('error', 'Error', error.message || 'No se pudo cargar la unidad.');
        }
    }

    // Delegación de clic para editar en tabla
    tbodyUnidades?.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-editar-unidad');
        if (btn && btn.dataset.id) {
            abrirModalEditar(btn.dataset.id);
        }
    });

    // Botón de edición en vista de perfil
    btnEditarPerfil?.addEventListener('click', () => {
        if (btnEditarPerfil.dataset.id) {
            abrirModalEditar(btnEditarPerfil.dataset.id);
        }
    });

    // Guardar (Crear o Actualizar)
    formUnidad?.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (pristine && !pristine.validate()) {
            return;
        }

        const id = document.getElementById('unidad-id')?.value;
        const esEdicion = id && id.trim() !== '';
        const url = esEdicion ? `/unidades/${id}` : '/unidades';
        const method = esEdicion ? 'PUT' : 'POST';

        const datos = {
            propiedad_id: document.getElementById('unidad-propiedad-id')?.value,
            tipo_unidad_id: document.getElementById('unidad-tipo-id')?.value,
            codigo: document.getElementById('unidad-codigo')?.value,
            nombre: document.getElementById('unidad-nombre')?.value,
            piso_nivel: document.getElementById('unidad-piso-nivel')?.value,
            capacidad_personas: document.getElementById('unidad-capacidad')?.value,
            dormitorios: document.getElementById('unidad-dormitorios')?.value,
            banos: document.getElementById('unidad-banos')?.value,
            area_m2: document.getElementById('unidad-area-m2')?.value,
            descripcion: document.getElementById('unidad-descripcion')?.value,
            observaciones: document.getElementById('unidad-observaciones')?.value,
        };

        setLoading(true);

        try {
            const respuesta = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(datos),
            });

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.ok) {
                if (resultado.errores) {
                    const mensajes = Object.values(resultado.errores).flat().join('<br>');
                    throw new Error(mensajes);
                }
                throw new Error(resultado.mensaje || 'Error al procesar la solicitud.');
            }

            modalUnidad?.hide();
            mostrarAlerta('success', '¡Éxito!', resultado.mensaje || 'Operación realizada correctamente.');

            if (tbodyUnidades) {
                cargarUnidades(estadoModulo.pagina);
            } else {
                // En vista de perfil, recargar para reflejar cambios
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            console.error('Error al guardar unidad:', error);
            mostrarAlerta('error', 'Inconsistencia en Datos', error.message || 'Error al guardar la unidad.');
        } finally {
            setLoading(false);
        }
    });

    // -------------------------------------------------------------------------
    // 5. Modal: Alternar Estado de Unidad (ACTIVO ↔ INACTIVO)
    // -------------------------------------------------------------------------
    function abrirModalEstado(id, nuevoEstado) {
        if (!modalEstado || !estadoUnidadId || !estadoUnidadNuevo) return;

        estadoUnidadId.value = id;
        estadoUnidadNuevo.value = nuevoEstado;
        if (motivoCambioEstado) motivoCambioEstado.value = '';

        const accion = nuevoEstado === 'ACTIVO' ? 'activar' : 'desactivar';
        if (mensajeConfirmacionEstado) {
            mensajeConfirmacionEstado.textContent = `¿Confirma ${accion} esta unidad habitacional?`;
        }

        modalEstado.show();
    }

    // Delegación de clic para alternar estado en tabla
    tbodyUnidades?.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-cambiar-estado');
        if (btn && btn.dataset.id && btn.dataset.nuevoEstado) {
            abrirModalEstado(btn.dataset.id, btn.dataset.nuevoEstado);
        }
    });

    // Botón de alternar estado en vista de perfil
    btnCambiarEstadoPerfil?.addEventListener('click', () => {
        if (btnCambiarEstadoPerfil.dataset.id && btnCambiarEstadoPerfil.dataset.nuevoEstado) {
            abrirModalEstado(btnCambiarEstadoPerfil.dataset.id, btnCambiarEstadoPerfil.dataset.nuevoEstado);
        }
    });

    formEstado?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = estadoUnidadId.value;
        const nuevoEstado = estadoUnidadNuevo.value;
        const motivo = motivoCambioEstado?.value || '';

        try {
            const respuesta = await fetch(`/unidades/${id}/estado`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    estado: nuevoEstado,
                    motivo: motivo,
                }),
            });

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.ok) {
                throw new Error(resultado.mensaje || 'Error al cambiar estado.');
            }

            modalEstado?.hide();
            mostrarAlerta('success', 'Estado Actualizado', resultado.mensaje || 'Estado modificado correctamente.');

            if (tbodyUnidades) {
                cargarUnidades(estadoModulo.pagina);
            } else {
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            console.error('Error al cambiar estado:', error);
            mostrarAlerta('error', 'Error', error.message || 'No se pudo alternar el estado.');
        }
    });

    // -------------------------------------------------------------------------
    // 6. Utilidades
    // -------------------------------------------------------------------------
    function setLoading(isLoading) {
        if (!btnGuardar) return;
        btnGuardar.disabled = isLoading;
        if (spinnerGuardar) {
            spinnerGuardar.classList.toggle('d-none', !isLoading);
        }
    }

    function mostrarAlerta(tipo, titulo, mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: tipo,
                title: titulo,
                html: mensaje,
                confirmButtonColor: '#0d6efd',
            });
        } else {
            alert(`${titulo}: ${mensaje}`);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // -------------------------------------------------------------------------
    // 7. Arranque Inicial
    // -------------------------------------------------------------------------
    if (tbodyUnidades) {
        cargarUnidades(1);
    }
});
