/**
 * Camargo PMS — Gestión de Propiedades e Inmuebles Físicos (PROPIEDADES-1)
 *
 * Módulo JavaScript para administración reactiva de propiedades, filtros,
 * georreferenciación GPS, modales de alta/edición y ciclo de vida histórico (ACTIVO/INACTIVO).
 *
 * Principios vinculantes:
 * - PROPIEDAD ≠ UNIDAD (inmueble físico contenedor, no unidad arrendable)
 * - PROPIEDAD ≠ REGISTRO DESECHABLE (no DELETE físico, ciclo ACTIVO / INACTIVO)
 * - ACTOR ≠ USUARIO (D-061)
 * - Stack: Vanilla JS moderno, Fetch API, PristineJS v1.1.0, SweetAlert2.
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // Elementos principales de la vista de catálogo
    const tbodyPropiedades = document.getElementById('tbody-propiedades');
    const inputBusqueda = document.getElementById('filtro-busqueda-propiedad');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda-propiedad');
    const selectPais = document.getElementById('filtro-pais-propiedad');
    const selectEstado = document.getElementById('filtro-estado-propiedad');
    const btnRecargar = document.getElementById('btn-recargar-propiedades');
    const btnAbrirCrear = document.getElementById('btn-abrir-crear-propiedad');
    const infoPaginacion = document.getElementById('info-paginacion-propiedades');
    const paginacionUl = document.getElementById('paginacion-propiedades');

    // Modal y Formulario de Crear / Editar
    const modalPropiedadEl = document.getElementById('modal-propiedad');
    const formPropiedad = document.getElementById('form-propiedad');
    const modalAccionTxt = document.getElementById('modal-propiedad-accion');
    const inputPropiedadId = document.getElementById('propiedad-id');
    const inputCodigo = document.getElementById('propiedad-codigo');
    const inputNombre = document.getElementById('propiedad-nombre');
    const selectPaisForm = document.getElementById('propiedad-pais-id');
    const inputDepartamento = document.getElementById('propiedad-departamento');
    const inputProvincia = document.getElementById('propiedad-provincia');
    const inputDistrito = document.getElementById('propiedad-distrito');
    const inputDireccion = document.getElementById('propiedad-direccion');
    const inputReferencia = document.getElementById('propiedad-referencia');
    const inputLatitud = document.getElementById('propiedad-latitud');
    const inputLongitud = document.getElementById('propiedad-longitud');
    const txtDescripcion = document.getElementById('propiedad-descripcion');
    const txtObservaciones = document.getElementById('propiedad-observaciones');
    const btnGuardarPropiedad = document.getElementById('btn-guardar-propiedad');

    // Elementos de la vista de detalle/perfil (si existen)
    const btnPerfilEditar = document.getElementById('btn-perfil-editar-propiedad');
    const btnPerfilCambiarEstado = document.getElementById('btn-perfil-cambiar-estado');

    // Instancia de Modal Bootstrap
    const modalPropiedadBs = modalPropiedadEl && typeof bootstrap !== 'undefined'
        ? new bootstrap.Modal(modalPropiedadEl)
        : null;

    // Instancia de validador PristineJS
    let validadorPristine = null;
    if (formPropiedad && typeof Pristine !== 'undefined') {
        validadorPristine = new Pristine(formPropiedad, {
            classTo: 'col-md-3,col-md-4,col-md-5,col-md-6,col-md-7,col-md-8,col-12',
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorTextParent: 'col-md-3,col-md-4,col-md-5,col-md-6,col-md-7,col-md-8,col-12',
            errorTextTag: 'div',
            errorTextClass: 'invalid-feedback f-s-11'
        });
    }

    // Estado local para paginación y búsqueda
    let paginaActual = 1;
    let limitePorPagina = 10;
    let temporizadorDebounce = null;

    /**
     * Obtiene el token CSRF activo del documento.
     *
     * @returns {string}
     */
    function obtenerCsrfToken() {
        const inputGlobal = document.getElementById('csrf-token-global');
        if (inputGlobal && inputGlobal.value) {
            return inputGlobal.value;
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        return '';
    }

    /**
     * Sanitiza cadenas para inyección HTML segura.
     *
     * @param {string|null} str
     * @returns {string}
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Carga y renderiza el listado paginado de propiedades desde el servidor.
     *
     * @param {number} [pagina=1]
     */
    async function cargarPropiedades(pagina = 1) {
        if (!tbodyPropiedades) {
            return;
        }

        paginaActual = pagina;
        tbodyPropiedades.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Cargando propiedades...
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        params.set('pagina', String(paginaActual));
        params.set('limite', String(limitePorPagina));

        if (inputBusqueda && inputBusqueda.value.trim() !== '') {
            params.set('busqueda', inputBusqueda.value.trim());
        }
        if (selectEstado && selectEstado.value !== '') {
            params.set('estado', selectEstado.value);
        }
        if (selectPais && selectPais.value !== '') {
            params.set('pais_id', selectPais.value);
        }

        try {
            const respuesta = await fetch(`/propiedades/datos?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!respuesta.ok) {
                throw new Error(`Error HTTP: ${respuesta.status}`);
            }

            const json = await respuesta.json();
            if (!json.ok && !json.exito) {
                throw new Error(json.error || 'No se pudieron recuperar las propiedades.');
            }

            renderizarTabla(json.datos || []);
            renderizarPaginacion(json.paginacion);
        } catch (error) {
            console.error('Error al cargar propiedades:', error);
            tbodyPropiedades.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation f-s-18 me-1"></i>
                        No se pudo cargar el catálogo de propiedades. (${escapeHtml(error.message)})
                    </td>
                </tr>
            `;
            if (infoPaginacion) {
                infoPaginacion.textContent = 'Error de carga';
            }
            if (paginacionUl) {
                paginacionUl.innerHTML = '';
            }
        }
    }

    /**
     * Renderiza el cuerpo de la tabla con las propiedades recibidas.
     *
     * @param {Array<Object>} propiedades
     */
    function renderizarTabla(propiedades) {
        if (!tbodyPropiedades) return;

        if (propiedades.length === 0) {
            tbodyPropiedades.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open f-s-24 d-block mb-1"></i>
                        No se encontraron propiedades registradas con los criterios seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        let filasHtml = '';
        propiedades.forEach((p) => {
            const esActivo = p.estado === 'ACTIVO';
            const badgeEstado = esActivo
                ? '<span class="badge bg-success-subtle text-success">ACTIVO</span>'
                : '<span class="badge bg-danger-subtle text-danger">INACTIVO</span>';

            // Ubicación concatenada
            const partesUbicacion = [];
            if (p.distrito) partesUbicacion.push(p.distrito);
            if (p.provincia) partesUbicacion.push(p.provincia);
            if (p.departamento) partesUbicacion.push(p.departamento);
            if (p.pais_nombre) partesUbicacion.push(p.pais_nombre);
            const ubicacionTxt = partesUbicacion.length > 0 ? partesUbicacion.join(', ') : 'No especificada';
            const direccionTxt = p.direccion ? `<br><small class="text-muted"><i class="fa-solid fa-location-dot f-s-11"></i> ${escapeHtml(p.direccion)}</small>` : '';

            // Coordenadas GPS
            let coordsHtml = '<span class="text-muted f-s-12">No registradas</span>';
            if (p.latitud !== null && p.longitud !== null) {
                const latFormatted = parseFloat(p.latitud).toFixed(5);
                const lngFormatted = parseFloat(p.longitud).toFixed(5);
                coordsHtml = `
                    <a href="https://www.google.com/maps?q=${p.latitud},${p.longitud}" target="_blank" rel="noopener noreferrer"
                       class="badge bg-primary-subtle text-primary text-decoration-none" title="Ver en Google Maps">
                        <i class="fa-solid fa-location-dot me-1"></i>${latFormatted}, ${lngFormatted}
                    </a>
                `;
            }

            filasHtml += `
                <tr data-id="${p.id}">
                    <td>
                        <strong class="f-s-13 text-dark">${escapeHtml(p.codigo)}</strong>
                    </td>
                    <td>
                        <div class="f-s-14 f-w-600 text-dark">${escapeHtml(p.nombre)}</div>
                        ${p.descripcion ? `<small class="text-muted text-truncate d-block" style="max-width: 280px;">${escapeHtml(p.descripcion)}</small>` : ''}
                    </td>
                    <td>
                        <div class="f-s-13 text-secondary">${escapeHtml(ubicacionTxt)}</div>
                        ${direccionTxt}
                    </td>
                    <td>
                        ${coordsHtml}
                    </td>
                    <td class="text-center">
                        ${badgeEstado}
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="/propiedades/${p.id}/perfil" class="btn btn-outline-info" title="Ver Ficha Técnica">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-outline-primary btn-accion-editar"
                                    data-id="${p.id}" title="Editar Propiedad">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" class="btn btn-outline-${esActivo ? 'warning' : 'success'} btn-accion-estado"
                                    data-id="${p.id}" data-nombre="${escapeHtml(p.nombre)}" data-estado="${p.estado}"
                                    title="${esActivo ? 'Desactivar Propiedad' : 'Activar Propiedad'}">
                                <i class="fa-solid fa-power-off"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbodyPropiedades.innerHTML = filasHtml;

        // Asignar listeners para botones de acción dentro de la tabla
        tbodyPropiedades.querySelectorAll('.btn-accion-editar').forEach((btn) => {
            btn.addEventListener('click', () => abrirModalEditar(btn.getAttribute('data-id')));
        });

        tbodyPropiedades.querySelectorAll('.btn-accion-estado').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const nombre = btn.getAttribute('data-nombre');
                const estado = btn.getAttribute('data-estado');
                confirmarCambioEstado(id, nombre, estado);
            });
        });
    }

    /**
     * Renderiza los controles de paginación.
     *
     * @param {Object} pag
     */
    function renderizarPaginacion(pag) {
        if (!infoPaginacion || !paginacionUl || !pag) return;

        const total = pag.total_registros || 0;
        const totalPaginas = pag.total_paginas || 1;
        const actual = pag.pagina_actual || 1;
        const limite = pag.limite || limitePorPagina;

        const desde = total === 0 ? 0 : (actual - 1) * limite + 1;
        const hasta = Math.min(actual * limite, total);

        infoPaginacion.textContent = `Mostrando ${desde} a ${hasta} de ${total} propiedades`;

        if (totalPaginas <= 1) {
            paginacionUl.innerHTML = '';
            return;
        }

        let paginacionHtml = '';

        // Botón Anterior
        paginacionHtml += `
            <li class="page-item ${actual <= 1 ? 'disabled' : ''}">
                <button class="page-link btn-paginacion" data-pagina="${actual - 1}" aria-label="Anterior">
                    &laquo;
                </button>
            </li>
        `;

        // Páginas numeradas
        for (let i = 1; i <= totalPaginas; i++) {
            if (i === 1 || i === totalPaginas || (i >= actual - 2 && i <= actual + 2)) {
                paginacionHtml += `
                    <li class="page-item ${i === actual ? 'active' : ''}">
                        <button class="page-link btn-paginacion" data-pagina="${i}">${i}</button>
                    </li>
                `;
            } else if (i === actual - 3 || i === actual + 3) {
                paginacionHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        // Botón Siguiente
        paginacionHtml += `
            <li class="page-item ${actual >= totalPaginas ? 'disabled' : ''}">
                <button class="page-link btn-paginacion" data-pagina="${actual + 1}" aria-label="Siguiente">
                    &raquo;
                </button>
            </li>
        `;

        paginacionUl.innerHTML = paginacionHtml;

        paginacionUl.querySelectorAll('.btn-paginacion').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const p = parseInt(btn.getAttribute('data-pagina'), 10);
                if (p >= 1 && p <= totalPaginas && p !== actual) {
                    cargarPropiedades(p);
                }
            });
        });
    }

    /**
     * Abre el modal para dar de alta una nueva propiedad física.
     */
    function abrirModalCrear() {
        if (!modalPropiedadBs || !formPropiedad) return;

        formPropiedad.reset();
        if (inputPropiedadId) inputPropiedadId.value = '';
        if (modalAccionTxt) modalAccionTxt.textContent = 'Nueva';
        if (inputCodigo) inputCodigo.removeAttribute('readonly');

        if (validadorPristine) {
            validadorPristine.reset();
        }

        modalPropiedadBs.show();
    }

    /**
     * Abre el modal de edición para una propiedad existente cargando sus datos vía AJAX.
     *
     * @param {string|number} id
     */
    async function abrirModalEditar(id) {
        if (!modalPropiedadBs || !formPropiedad) return;

        try {
            const respuesta = await fetch(`/propiedades/${id}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!respuesta.ok) {
                throw new Error(`Error HTTP: ${respuesta.status}`);
            }

            const json = await respuesta.json();
            if (!json.ok && !json.exito) {
                throw new Error(json.error || 'No se pudo cargar el detalle de la propiedad.');
            }

            const prop = json.datos;

            formPropiedad.reset();
            if (validadorPristine) validadorPristine.reset();

            if (inputPropiedadId) inputPropiedadId.value = prop.id;
            if (modalAccionTxt) modalAccionTxt.textContent = 'Editar';
            if (inputCodigo) inputCodigo.value = prop.codigo || '';
            if (inputNombre) inputNombre.value = prop.nombre || '';
            if (selectPaisForm) selectPaisForm.value = prop.pais_id || '';
            if (inputDepartamento) inputDepartamento.value = prop.departamento || '';
            if (inputProvincia) inputProvincia.value = prop.provincia || '';
            if (inputDistrito) inputDistrito.value = prop.distrito || '';
            if (inputDireccion) inputDireccion.value = prop.direccion || '';
            if (inputReferencia) inputReferencia.value = prop.referencia || '';
            if (inputLatitud) inputLatitud.value = prop.latitud !== null ? prop.latitud : '';
            if (inputLongitud) inputLongitud.value = prop.longitud !== null ? prop.longitud : '';
            if (txtDescripcion) txtDescripcion.value = prop.descripcion || '';
            if (txtObservaciones) txtObservaciones.value = prop.observaciones || '';

            modalPropiedadBs.show();
        } catch (error) {
            console.error('Error al abrir modal de edición:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de carga',
                    text: error.message || 'No se pudo recuperar la información de la propiedad.'
                });
            } else {
                alert(error.message);
            }
        }
    }

    /**
     * Envía los datos del formulario de propiedad para crear o actualizar.
     *
     * @param {Event} e
     */
    async function guardarPropiedad(e) {
        e.preventDefault();

        if (validadorPristine && !validadorPristine.validate()) {
            return;
        }

        const id = inputPropiedadId ? inputPropiedadId.value.trim() : '';
        const esEdicion = id !== '';
        const url = esEdicion ? `/propiedades/${id}` : '/propiedades';
        const metodo = esEdicion ? 'PUT' : 'POST';

        const payload = {
            _csrf_token: obtenerCsrfToken(),
            codigo: inputCodigo ? inputCodigo.value.trim().toUpperCase() : '',
            nombre: inputNombre ? inputNombre.value.trim() : '',
            pais_id: selectPaisForm ? parseInt(selectPaisForm.value, 10) : null,
            departamento: inputDepartamento && inputDepartamento.value.trim() !== '' ? inputDepartamento.value.trim() : null,
            provincia: inputProvincia && inputProvincia.value.trim() !== '' ? inputProvincia.value.trim() : null,
            distrito: inputDistrito && inputDistrito.value.trim() !== '' ? inputDistrito.value.trim() : null,
            direccion: inputDireccion && inputDireccion.value.trim() !== '' ? inputDireccion.value.trim() : null,
            referencia: inputReferencia && inputReferencia.value.trim() !== '' ? inputReferencia.value.trim() : null,
            latitud: inputLatitud && inputLatitud.value.trim() !== '' ? parseFloat(inputLatitud.value) : null,
            longitud: inputLongitud && inputLongitud.value.trim() !== '' ? parseFloat(inputLongitud.value) : null,
            descripcion: txtDescripcion && txtDescripcion.value.trim() !== '' ? txtDescripcion.value.trim() : null,
            observaciones: txtObservaciones && txtObservaciones.value.trim() !== '' ? txtObservaciones.value.trim() : null
        };

        if (btnGuardarPropiedad) {
            btnGuardarPropiedad.disabled = true;
            btnGuardarPropiedad.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';
        }

        try {
            const respuesta = await fetch(url, {
                method: metodo,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': obtenerCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const json = await respuesta.json();

            if (!respuesta.ok || (!json.ok && !json.exito)) {
                if (respuesta.status === 409) {
                    throw new Error(json.error || 'Ya existe una propiedad con ese código.');
                }
                if (respuesta.status === 422) {
                    const detalles = json.errores ? Object.values(json.errores).flat().join('<br>') : json.error;
                    throw new Error(detalles || 'Errores de validación en los datos enviados.');
                }
                throw new Error(json.error || 'Error al procesar la propiedad.');
            }

            if (modalPropiedadBs) {
                modalPropiedadBs.hide();
            }

            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: 'Operación Exitosa',
                    text: json.mensaje || (esEdicion ? 'Propiedad actualizada exitosamente.' : 'Propiedad creada exitosamente.'),
                    timer: 2000,
                    showConfirmButton: false
                });
            }

            // Si estamos en la página de detalle, recargamos la página para ver cambios
            if (!tbodyPropiedades) {
                window.location.reload();
            } else {
                cargarPropiedades(paginaActual);
            }
        } catch (error) {
            console.error('Error al guardar propiedad:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    html: error.message || 'No se pudo guardar la propiedad.'
                });
            } else {
                alert(error.message);
            }
        } finally {
            if (btnGuardarPropiedad) {
                btnGuardarPropiedad.disabled = false;
                btnGuardarPropiedad.innerHTML = '<i class="fa-solid fa-check me-1"></i> ' + (esEdicion ? 'Guardar Cambios' : 'Guardar Propiedad');
            }
        }
    }

    /**
     * Muestra alerta de confirmación para cambiar el estado operativo (ACTIVO / INACTIVO).
     *
     * @param {string|number} id
     * @param {string} nombre
     * @param {string} estadoActual
     */
    async function confirmarCambioEstado(id, nombre, estadoActual) {
        const esActivo = estadoActual === 'ACTIVO';
        const nuevoEstado = esActivo ? 'INACTIVO' : 'ACTIVO';
        const accionTxt = esActivo ? 'desactivar' : 'activar';
        const confirmBtnClass = esActivo ? 'btn btn-warning' : 'btn btn-success';

        if (typeof Swal !== 'undefined') {
            const resultado = await Swal.fire({
                title: `¿Desea ${accionTxt} la propiedad?`,
                html: `La propiedad <strong>${escapeHtml(nombre)}</strong> pasará al estado <strong>${nuevoEstado}</strong>.<br><small class="text-muted">Principio: No se eliminan registros físicos para preservar la trazabilidad histórica.</small>`,
                icon: esActivo ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonText: `Sí, ${accionTxt}`,
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: confirmBtnClass,
                    cancelButton: 'btn btn-secondary me-2'
                },
                buttonsStyling: false
            });

            if (!resultado.isConfirmed) {
                return;
            }
        } else {
            if (!confirm(`¿Desea ${accionTxt} la propiedad "${nombre}"?`)) {
                return;
            }
        }

        try {
            const respuesta = await fetch(`/propiedades/${id}/estado`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': obtenerCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    _csrf_token: obtenerCsrfToken(),
                    estado: nuevoEstado
                })
            });

            const json = await respuesta.json();

            if (!respuesta.ok || (!json.ok && !json.exito)) {
                throw new Error(json.error || `No se pudo ${accionTxt} la propiedad.`);
            }

            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: 'Estado Actualizado',
                    text: json.mensaje || `La propiedad ahora está ${nuevoEstado}.`,
                    timer: 1800,
                    showConfirmButton: false
                });
            }

            if (!tbodyPropiedades) {
                window.location.reload();
            } else {
                cargarPropiedades(paginaActual);
            }
        } catch (error) {
            console.error('Error al cambiar estado:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'No se pudo actualizar el estado de la propiedad.'
                });
            } else {
                alert(error.message);
            }
        }
    }

    // =========================================================================
    // INICIALIZACIÓN Y EVENT LISTENERS
    // =========================================================================

    // Eventos de la vista de catálogo
    if (tbodyPropiedades) {
        if (btnAbrirCrear) {
            btnAbrirCrear.addEventListener('click', abrirModalCrear);
        }

        if (inputBusqueda) {
            inputBusqueda.addEventListener('input', () => {
                clearTimeout(temporizadorDebounce);
                temporizadorDebounce = setTimeout(() => {
                    cargarPropiedades(1);
                }, 300);
            });
        }

        if (btnLimpiarBusqueda && inputBusqueda) {
            btnLimpiarBusqueda.addEventListener('click', () => {
                inputBusqueda.value = '';
                cargarPropiedades(1);
            });
        }

        if (selectEstado) {
            selectEstado.addEventListener('change', () => cargarPropiedades(1));
        }

        if (selectPais) {
            selectPais.addEventListener('change', () => cargarPropiedades(1));
        }

        if (btnRecargar) {
            btnRecargar.addEventListener('click', () => cargarPropiedades(paginaActual));
        }

        // Carga inicial
        cargarPropiedades(1);
    }

    // Eventos del formulario modal
    if (formPropiedad) {
        formPropiedad.addEventListener('submit', guardarPropiedad);
    }

    // Eventos de la vista de detalle/perfil
    if (btnPerfilEditar) {
        btnPerfilEditar.addEventListener('click', () => {
            const id = btnPerfilEditar.getAttribute('data-id');
            abrirModalEditar(id);
        });
    }

    if (btnPerfilCambiarEstado) {
        btnPerfilCambiarEstado.addEventListener('click', () => {
            const id = btnPerfilCambiarEstado.getAttribute('data-id');
            const nombre = btnPerfilCambiarEstado.getAttribute('data-nombre');
            const estado = btnPerfilCambiarEstado.getAttribute('data-estado');
            confirmarCambioEstado(id, nombre, estado);
        });
    }
});
