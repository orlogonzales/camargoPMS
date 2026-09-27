/**
 * Camargo PMS — Administración de Roles y Matriz de Permisos (ROLES-2)
 *
 * Módulo JavaScript para la gestión reactiva de roles, asignación matricial
 * de permisos por módulo funcional y consulta de usuarios vinculados.
 *
 * Principios vinculantes:
 * - ROL DE AUTORIZACIÓN ≠ CARGO LABORAL
 * - ACTOR ≠ USUARIO (D-061)
 * - MENÚ ≠ AUTORIZACIÓN
 * - Protección inviolable de SUPERADMINISTRADOR
 * - Actualización en tiempo real sin requerir re-login
 * - Stack: Vanilla JS moderno, Fetch API, PristineJS v1.1.0, SweetAlert2.
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // Elementos principales de la vista
    const tbodyRoles = document.getElementById('tbody-roles');
    const inputBusqueda = document.getElementById('filtro-busqueda-rol');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda-rol');
    const selectEstado = document.getElementById('filtro-estado-rol');
    const btnRecargar = document.getElementById('btn-recargar-roles');
    const btnAbrirCrear = document.getElementById('btn-abrir-crear-rol');
    const conteoInfo = document.getElementById('conteo-roles-info');

    // Modales y formularios
    const modalCrearEl = document.getElementById('modal-crear-rol');
    const formCrear = document.getElementById('form-crear-rol');
    const btnGuardarCrear = document.getElementById('btn-guardar-crear-rol');

    const modalEditarEl = document.getElementById('modal-editar-rol');
    const formEditar = document.getElementById('form-editar-rol');
    const inputEditarId = document.getElementById('editar-rol-id');
    const inputEditarCodigo = document.getElementById('editar-rol-codigo');
    const inputEditarNombre = document.getElementById('editar-rol-nombre');
    const inputEditarDescripcion = document.getElementById('editar-rol-descripcion');
    const selectEditarEstado = document.getElementById('editar-rol-estado');
    const txtEditarEstadoAyuda = document.getElementById('editar-rol-estado-ayuda');
    const btnGuardarEditar = document.getElementById('btn-guardar-editar-rol');

    const modalPermisosEl = document.getElementById('modal-gestionar-permisos');
    const inputPermisosRolId = document.getElementById('permisos-modal-rol-id');
    const txtSubtituloPermisos = document.getElementById('subtitulo-permisos-rol');
    const alertaSuperadmin = document.getElementById('alerta-superadmin-permisos');
    const contenedorPermisos = document.getElementById('contenedor-modulos-permisos');
    const contadorPermisos = document.getElementById('contador-permisos-seleccionados');
    const btnMarcarTodos = document.getElementById('btn-marcar-todos-permisos');
    const btnDesmarcarTodos = document.getElementById('btn-desmarcar-todos-permisos');
    const btnGuardarPermisos = document.getElementById('btn-guardar-permisos-rol');

    const modalUsuariosEl = document.getElementById('modal-usuarios-rol');
    const txtSubtituloUsuarios = document.getElementById('subtitulo-usuarios-rol');
    const tbodyUsuariosRol = document.getElementById('tbody-usuarios-rol');

    // Instancias de Modales Bootstrap
    const modalCrearBs = modalCrearEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalCrearEl) : null;
    const modalEditarBs = modalEditarEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEditarEl) : null;
    const modalPermisosBs = modalPermisosEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalPermisosEl) : null;
    const modalUsuariosBs = modalUsuariosEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalUsuariosEl) : null;

    // Estado local en memoria
    let catalogoPermisosGlobal = null;
    let rolActualEnPermisos = null;
    const permisosCriticosSuperadmin = ['roles.ver', 'roles.editar', 'permisos.ver', 'usuarios.ver', 'usuarios.editar'];

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
     * Notificación amigable mediante SweetAlert2 o fallback nativo.
     *
     * @param {'success'|'error'|'warning'|'info'} tipo
     * @param {string} mensaje
     * @param {string} [titulo]
     */
    function notificar(tipo, mensaje, titulo = '') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: tipo,
                title: titulo || (tipo === 'success' ? 'Operación exitosa' : 'Atención'),
                text: mensaje,
                confirmButtonColor: '#3085d6'
            });
        } else {
            alert(`${titulo ? titulo + ': ' : ''}${mensaje}`);
        }
    }

    /**
     * Diálogo de confirmación interactivo.
     *
     * @param {string} titulo
     * @param {string} texto
     * @param {string} [textoConfirmar='Sí, continuar']
     * @param {string} [colorConfirmar='#3085d6']
     * @returns {Promise<boolean>}
     */
    async function confirmar(titulo, texto, textoConfirmar = 'Sí, continuar', colorConfirmar = '#3085d6') {
        if (typeof Swal !== 'undefined') {
            const res = await Swal.fire({
                title: titulo,
                text: texto,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: colorConfirmar,
                cancelButtonColor: '#6c757d',
                confirmButtonText: textoConfirmar,
                cancelButtonText: 'Cancelar'
            });
            return res.isConfirmed;
        }
        return window.confirm(`${titulo}\n\n${texto}`);
    }

    // =========================================================================
    // INICIALIZACIÓN DE PRISTINE JS (v1.1.0 LOCAL)
    // =========================================================================

    let validadorCrear = null;
    let validadorEditar = null;

    function inicializarPristineCrear() {
        if (!formCrear || typeof Pristine === 'undefined') {
            return null;
        }

        validadorCrear = new Pristine(formCrear, {
            classTo: 'mb-3',
            errorClass: 'has-danger',
            successClass: 'has-success',
            errorTextParent: 'mb-3',
            errorTextTag: 'div',
            errorTextClass: 'text-danger f-s-12 mt-1'
        });

        // Validador de clave reservada SUPERADMINISTRADOR
        const inputCodigo = document.getElementById('crear-rol-codigo');
        if (inputCodigo) {
            validadorCrear.addValidator(inputCodigo, (valor) => {
                if (!valor) return true;
                return valor.trim().toUpperCase() !== 'SUPERADMINISTRADOR';
            }, 'No se permite crear roles adicionales con la clave reservada SUPERADMINISTRADOR.', 3, false);
        }

        return validadorCrear;
    }

    function inicializarPristineEditar() {
        if (!formEditar || typeof Pristine === 'undefined') {
            return null;
        }

        validadorEditar = new Pristine(formEditar, {
            classTo: 'mb-3',
            errorClass: 'has-danger',
            successClass: 'has-success',
            errorTextParent: 'mb-3',
            errorTextTag: 'div',
            errorTextClass: 'text-danger f-s-12 mt-1'
        });

        return validadorEditar;
    }

    // Limpieza de validadores al cerrar modales
    if (modalCrearEl) {
        modalCrearEl.addEventListener('hidden.bs.modal', () => {
            if (validadorCrear) {
                validadorCrear.reset();
            }
            formCrear.reset();
        });
    }

    if (modalEditarEl) {
        modalEditarEl.addEventListener('hidden.bs.modal', () => {
            if (validadorEditar) {
                validadorEditar.reset();
            }
            formEditar.reset();
            txtEditarEstadoAyuda.textContent = '';
            selectEditarEstado.disabled = false;
        });
    }

    // =========================================================================
    // CARGA Y RENDERIZADO DEL LISTADO DE ROLES
    // =========================================================================

    /**
     * Carga y renderiza el catálogo de roles con conteos agregados.
     */
    async function cargarRoles() {
        if (!tbodyRoles) return;

        tbodyRoles.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Cargando roles...
                </td>
            </tr>
        `;

        const busqueda = inputBusqueda ? inputBusqueda.value.trim() : '';
        const estado = selectEstado ? selectEstado.value.trim() : '';

        const params = new URLSearchParams();
        if (busqueda !== '') params.append('busqueda', busqueda);
        if (estado !== '') params.append('estado', estado);

        try {
            const resp = await fetch(`/configuracion/roles/datos?${params.toString()}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error(`Error HTTP ${resp.status}`);
            }

            const json = await resp.json();
            if (!json.ok && !json.exito) {
                throw new Error(json.error || 'Error al obtener roles.');
            }

            const roles = json.datos || [];
            renderizarTablaRoles(roles);
        } catch (error) {
            tbodyRoles.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation f-s-20 me-1"></i>
                        No se pudo cargar el listado de roles: ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Renderiza las filas de la tabla de roles.
     *
     * @param {Array<Object>} roles
     */
    function renderizarTablaRoles(roles) {
        if (!tbodyRoles) return;

        if (conteoInfo) {
            conteoInfo.textContent = `Mostrando ${roles.length} rol(es) registrado(s)`;
        }

        if (roles.length === 0) {
            tbodyRoles.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-circle-info f-s-18 me-1"></i>
                        No se encontraron roles con los criterios de búsqueda seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        roles.forEach((r) => {
            const esSuper = Boolean(r.es_superadministrador);
            const esSistema = Boolean(r.es_sistema);
            const estaActivo = r.estado === 'ACTIVO';

            // Chip de Tipo (Alina Variants of chip)
            let badgeTipo = '';
            if (esSuper) {
                badgeTipo = '<span class="chip bg-light-warning f-s-11"><i class="fa-solid fa-crown me-1"></i>Superadmin</span>';
            } else if (esSistema) {
                badgeTipo = '<span class="chip bg-light-info f-s-11"><i class="fa-solid fa-microchip me-1"></i>Sistema</span>';
            } else {
                badgeTipo = '<span class="chip bg-light-secondary f-s-11"><i class="fa-solid fa-user me-1"></i>Personalizado</span>';
            }

            // Badge de Estado (Alina Variants of badge)
            const badgeEstado = (window.CamargoInsignia && window.CamargoInsignia.estado)
                ? window.CamargoInsignia.estado(r.estado, false, 'f-s-11')
                : (estaActivo
                    ? '<span class="badge bg-light-success f-s-11"><i class="fa-solid fa-check me-1"></i>ACTIVO</span>'
                    : '<span class="badge bg-light-secondary f-s-11"><i class="fa-solid fa-ban me-1"></i>INACTIVO</span>');

            // Botones de acción
            let botonesAccion = `
                <div class="d-flex justify-content-end gap-1">
                    <button type="button" class="btn btn-outline-primary btn-sm btn-permisos-rol" data-id="${r.id}" title="Gestionar permisos">
                        <i class="fa-solid fa-key"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm btn-usuarios-rol" data-id="${r.id}" title="Ver usuarios asignados">
                        <i class="fa-solid fa-users"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-editar-rol" data-id="${r.id}" title="Editar rol">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
            `;

            // Alternar estado (Protegido para Superadmin)
            if (!esSuper) {
                if (estaActivo) {
                    botonesAccion += `
                        <button type="button" class="btn btn-outline-warning btn-sm btn-estado-rol" data-id="${r.id}" data-estado="INACTIVO" title="Desactivar rol">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                    `;
                } else {
                    botonesAccion += `
                        <button type="button" class="btn btn-outline-success btn-sm btn-estado-rol" data-id="${r.id}" data-estado="ACTIVO" title="Activar rol">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    `;
                }
            }

            botonesAccion += '</div>';

            html += `
                <tr data-rol-id="${r.id}">
                    <td>
                        <div class="f-w-600 text-dark f-s-14">${escapeHtml(r.nombre)}</div>
                        <div class="d-flex align-items-center gap-1 mt-1">
                            <span class="badge bg-light-primary font-monospace f-s-11">${escapeHtml(r.codigo)}</span>
                            ${r.descripcion ? `<span class="text-muted f-s-12 text-truncate d-inline-block" style="max-width: 250px;" title="${escapeHtml(r.descripcion)}">${escapeHtml(r.descripcion)}</span>` : ''}
                        </div>
                    </td>
                    <td>${badgeTipo}</td>
                    <td class="text-center">${badgeEstado}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-light btn-sm btn-permisos-rol border" data-id="${r.id}">
                            <i class="fa-solid fa-key me-1 text-primary"></i>
                            <strong>${r.total_permisos}</strong> asignado(s)
                        </button>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-light btn-sm btn-usuarios-rol border" data-id="${r.id}">
                            <i class="fa-solid fa-users me-1 text-info"></i>
                            <strong>${r.total_usuarios}</strong> vinculado(s)
                        </button>
                    </td>
                    <td class="text-end">${botonesAccion}</td>
                </tr>
            `;
        });

        tbodyRoles.innerHTML = html;
        vincularEventosTabla();
    }

    /**
     * Vincula los controladores de eventos a los botones interactivos de la tabla.
     */
    function vincularEventosTabla() {
        // Botones de permisos
        document.querySelectorAll('.btn-permisos-rol').forEach((btn) => {
            btn.addEventListener('click', () => {
                const rolId = btn.getAttribute('data-id');
                abrirModalPermisos(rolId);
            });
        });

        // Botones de usuarios asignados
        document.querySelectorAll('.btn-usuarios-rol').forEach((btn) => {
            btn.addEventListener('click', () => {
                const rolId = btn.getAttribute('data-id');
                abrirModalUsuarios(rolId);
            });
        });

        // Botones de edición
        document.querySelectorAll('.btn-editar-rol').forEach((btn) => {
            btn.addEventListener('click', () => {
                const rolId = btn.getAttribute('data-id');
                abrirModalEditar(rolId);
            });
        });

        // Botones de cambio de estado
        document.querySelectorAll('.btn-estado-rol').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const rolId = btn.getAttribute('data-id');
                const nuevoEstado = btn.getAttribute('data-estado');
                await ejecutarCambioEstado(rolId, nuevoEstado);
            });
        });
    }

    // =========================================================================
    // CREAR NUEVO ROL
    // =========================================================================

    if (btnAbrirCrear) {
        btnAbrirCrear.addEventListener('click', () => {
            inicializarPristineCrear();
            if (modalCrearBs) {
                modalCrearBs.show();
            }
        });
    }

    if (formCrear) {
        formCrear.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!validadorCrear) {
                inicializarPristineCrear();
            }

            const esValido = validadorCrear ? validadorCrear.validate() : true;
            if (!esValido) {
                return;
            }

            const formData = new FormData(formCrear);
            const payload = {
                codigo: (formData.get('codigo') || '').toString().trim().toUpperCase(),
                nombre: (formData.get('nombre') || '').toString().trim(),
                descripcion: (formData.get('descripcion') || '').toString().trim(),
                estado: (formData.get('estado') || 'ACTIVO').toString().trim().toUpperCase(),
                _csrf_token: obtenerCsrfToken()
            };

            if (btnGuardarCrear) {
                btnGuardarCrear.disabled = true;
                btnGuardarCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';
            }

            try {
                const resp = await fetch('/configuracion/roles', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify(payload)
                });

                const json = await resp.json();

                if (!resp.ok) {
                    if (resp.status === 422 && json.errores) {
                        const mensajes = Object.values(json.errores).join('\n');
                        throw new Error(mensajes || json.error);
                    }
                    throw new Error(json.error || `Error HTTP ${resp.status}`);
                }

                notificar('success', json.mensaje || 'Rol creado exitosamente.');
                if (modalCrearBs) {
                    modalCrearBs.hide();
                }
                await cargarRoles();
            } catch (error) {
                notificar('error', error.message, 'No se pudo crear el rol');
            } finally {
                if (btnGuardarCrear) {
                    btnGuardarCrear.disabled = false;
                    btnGuardarCrear.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Rol';
                }
            }
        });
    }

    // =========================================================================
    // EDITAR ROL
    // =========================================================================

    /**
     * Abre el modal de edición de rol cargando su ficha previa.
     *
     * @param {string|number} rolId
     */
    async function abrirModalEditar(rolId) {
        inicializarPristineEditar();

        try {
            const resp = await fetch(`/configuracion/roles/${rolId}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error(`Error HTTP ${resp.status}`);
            }

            const json = await resp.json();
            if (!json.ok && !json.exito) {
                throw new Error(json.error || 'Error al consultar rol.');
            }

            const rol = json.datos.rol;
            inputEditarId.value = rol.id;
            inputEditarCodigo.value = rol.codigo;
            inputEditarNombre.value = rol.nombre;
            inputEditarDescripcion.value = rol.descripcion || '';
            selectEditarEstado.value = rol.estado;

            // Protección de Superadministrador y Roles de Sistema
            if (rol.es_superadministrador) {
                selectEditarEstado.disabled = true;
                txtEditarEstadoAyuda.textContent = 'El rol SUPERADMINISTRADOR no puede desactivarse por ser estructural.';
            } else {
                selectEditarEstado.disabled = false;
                txtEditarEstadoAyuda.textContent = '';
            }

            if (modalEditarBs) {
                modalEditarBs.show();
            }
        } catch (error) {
            notificar('error', error.message, 'Error al abrir edición');
        }
    }

    if (formEditar) {
        formEditar.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!validadorEditar) {
                inicializarPristineEditar();
            }

            const esValido = validadorEditar ? validadorEditar.validate() : true;
            if (!esValido) {
                return;
            }

            const rolId = inputEditarId.value;
            const payload = {
                nombre: inputEditarNombre.value.trim(),
                descripcion: inputEditarDescripcion.value.trim(),
                estado: selectEditarEstado.value.trim().toUpperCase(),
                _csrf_token: obtenerCsrfToken()
            };

            if (btnGuardarEditar) {
                btnGuardarEditar.disabled = true;
                btnGuardarEditar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Actualizando...';
            }

            try {
                const resp = await fetch(`/configuracion/roles/${rolId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify(payload)
                });

                const json = await resp.json();

                if (!resp.ok) {
                    if (resp.status === 422 && json.errores) {
                        const mensajes = Object.values(json.errores).join('\n');
                        throw new Error(mensajes || json.error);
                    }
                    throw new Error(json.error || `Error HTTP ${resp.status}`);
                }

                notificar('success', json.mensaje || 'Rol actualizado exitosamente.');
                if (modalEditarBs) {
                    modalEditarBs.hide();
                }
                await cargarRoles();
            } catch (error) {
                notificar('error', error.message, 'No se pudo actualizar el rol');
            } finally {
                if (btnGuardarEditar) {
                    btnGuardarEditar.disabled = false;
                    btnGuardarEditar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Rol';
                }
            }
        });
    }

    // =========================================================================
    // CAMBIO DE ESTADO ADMINISTRATIVO (ACTIVO / INACTIVO)
    // =========================================================================

    /**
     * Ejecuta el cambio de estado con confirmación SweetAlert2.
     *
     * @param {string|number} rolId
     * @param {string} nuevoEstado
     */
    async function ejecutarCambioEstado(rolId, nuevoEstado) {
        const accionTexto = nuevoEstado === 'ACTIVO' ? 'activar' : 'desactivar';
        const color = nuevoEstado === 'ACTIVO' ? '#28a745' : '#dc3545';

        const confirmado = await confirmar(
            `¿Desea ${accionTexto} este rol?`,
            `El rol cambiará a estado ${nuevoEstado}. Los usuarios asignados a un rol inactivo no podrán ejercer sus permisos asociados.`,
            `Sí, ${accionTexto}`,
            color
        );

        if (!confirmado) return;

        try {
            const resp = await fetch(`/configuracion/roles/${rolId}/estado`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': obtenerCsrfToken()
                },
                body: JSON.stringify({
                    nuevo_estado: nuevoEstado,
                    _csrf_token: obtenerCsrfToken()
                })
            });

            const json = await resp.json();
            if (!resp.ok) {
                throw new Error(json.error || `Error HTTP ${resp.status}`);
            }

            notificar('success', json.mensaje || `Estado actualizado a ${nuevoEstado}.`);
            await cargarRoles();
        } catch (error) {
            notificar('error', error.message, 'Error al cambiar estado');
        }
    }

    // =========================================================================
    // GESTIÓN MATRICIAL DE PERMISOS POR MÓDULO
    // =========================================================================

    /**
     * Abre el modal de matriz de permisos por módulo y marca los asignados.
     *
     * @param {string|number} rolId
     */
    async function abrirModalPermisos(rolId) {
        if (!contenedorPermisos) return;

        contenedorPermisos.innerHTML = `
            <div class="text-center py-4 text-muted">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Cargando matriz de permisos...
            </div>
        `;

        inputPermisosRolId.value = rolId;

        try {
            // Asegurar catálogo global de permisos
            if (!catalogoPermisosGlobal) {
                const respCat = await fetch('/configuracion/roles/permisos-catalogo', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!respCat.ok) throw new Error('Error al cargar catálogo de permisos.');
                const jsonCat = await respCat.json();
                catalogoPermisosGlobal = jsonCat.datos;
            }

            // Consultar datos del rol y permisos asignados
            const respRol = await fetch(`/configuracion/roles/${rolId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!respRol.ok) throw new Error('Error al cargar detalle del rol.');
            const jsonRol = await respRol.json();

            rolActualEnPermisos = jsonRol.datos.rol;
            const permisosAsignadosIds = new Set((jsonRol.datos.permisos_ids || []).map(Number));

            // Actualizar encabezado del modal
            if (txtSubtituloPermisos) {
                txtSubtituloPermisos.innerHTML = `Rol: <strong>${escapeHtml(rolActualEnPermisos.nombre)}</strong> <span class="badge bg-light-primary font-monospace ms-1">${escapeHtml(rolActualEnPermisos.codigo)}</span>`;
            }

            // Banner Superadministrador
            if (rolActualEnPermisos.es_superadministrador) {
                if (alertaSuperadmin) alertaSuperadmin.classList.remove('d-none');
            } else {
                if (alertaSuperadmin) alertaSuperadmin.classList.add('d-none');
            }

            renderizarMatrizPermisos(catalogoPermisosGlobal, permisosAsignadosIds, Boolean(rolActualEnPermisos.es_superadministrador));

            if (modalPermisosBs) {
                modalPermisosBs.show();
            }
        } catch (error) {
            notificar('error', error.message, 'No se pudo abrir la matriz de permisos');
        }
    }

    /**
     * Renderiza visualmente los grupos de permisos por módulo con checkboxes.
     *
     * @param {Object} agrupados
     * @param {Set<number>} permisosAsignadosIds
     * @param {boolean} esSuperadmin
     */
    function renderizarMatrizPermisos(agrupados, permisosAsignadosIds, esSuperadmin) {
        if (!contenedorPermisos) return;

        let html = '';
        const modulos = Object.keys(agrupados);

        if (modulos.length === 0) {
            contenedorPermisos.innerHTML = '<div class="text-center py-3 text-muted">No hay permisos registrados en el catálogo.</div>';
            return;
        }

        modulos.forEach((moduloNombre) => {
            const permisosModulo = agrupados[moduloNombre] || [];
            const moduloIdSeguro = moduloNombre.replace(/[^a-zA-Z0-9_-]/g, '_');

            html += `
                <div class="card mb-3 border shadow-none modulo-permisos-card" data-modulo="${moduloNombre}">
                    <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <input class="form-check-input me-2 check-modulo-master" type="checkbox" id="master-${moduloIdSeguro}" data-modulo="${moduloNombre}">
                            <label class="form-check-label f-w-700 f-s-13 text-uppercase text-dark mb-0" for="master-${moduloIdSeguro}">
                                Módulo: ${escapeHtml(moduloNombre)}
                            </label>
                        </div>
                        <span class="badge bg-light-secondary f-s-11 conteo-modulo-badge" id="badge-${moduloIdSeguro}">
                            0 / ${permisosModulo.length}
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
            `;

            permisosModulo.forEach((p) => {
                const estaMarcado = permisosAsignadosIds.has(Number(p.id));
                const esCritico = esSuperadmin && permisosCriticosSuperadmin.includes(p.codigo);
                const disabledAttr = esCritico ? 'disabled checked' : '';

                html += `
                    <div class="col-md-6 col-12">
                        <div class="border rounded p-2 h-100 bg-white d-flex align-items-start ${esCritico ? 'border-warning bg-warning-subtle' : ''}">
                            <div class="form-check mb-0">
                                <input class="form-check-input check-permiso" type="checkbox"
                                       value="${p.id}" id="permiso-${p.id}"
                                       data-modulo="${moduloNombre}"
                                       data-codigo="${escapeHtml(p.codigo)}"
                                       ${estaMarcado || esCritico ? 'checked' : ''}
                                       ${disabledAttr}>
                                <label class="form-check-label f-s-13 d-block" for="permiso-${p.id}">
                                    <span class="f-w-600 text-dark">${escapeHtml(p.nombre)}</span>
                                    <span class="badge bg-light-primary font-monospace f-s-10 ms-1">${escapeHtml(p.codigo)}</span>
                                    ${esCritico ? '<span class="chip bg-light-warning f-s-10 ms-1"><i class="fa-solid fa-crown me-1"></i>Crítico Superadmin</span>' : ''}
                                    ${p.descripcion ? `<small class="text-secondary d-block mt-1 f-s-11">${escapeHtml(p.descripcion)}</small>` : ''}
                                </label>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                        </div>
                    </div>
                </div>
            `;
        });

        contenedorPermisos.innerHTML = html;
        actualizarContadoresPermisos();
        vincularEventosPermisos();
    }

    /**
     * Vincula listeners de cambios sobre los checkboxes de permisos.
     */
    function vincularEventosPermisos() {
        // Toggle por módulo completo
        document.querySelectorAll('.check-modulo-master').forEach((masterCheck) => {
            masterCheck.addEventListener('change', () => {
                const modulo = masterCheck.getAttribute('data-modulo');
                const checkboxes = document.querySelectorAll(`.check-permiso[data-modulo="${modulo}"]:not(:disabled)`);
                checkboxes.forEach((cb) => {
                    cb.checked = masterCheck.checked;
                });
                actualizarContadoresPermisos();
            });
        });

        // Cambio individual
        document.querySelectorAll('.check-permiso').forEach((cb) => {
            cb.addEventListener('change', () => {
                actualizarContadoresPermisos();
            });
        });
    }

    /**
     * Actualiza los contadores globales y por módulo de permisos marcados.
     */
    function actualizarContadoresPermisos() {
        let totalSeleccionados = 0;

        if (catalogoPermisosGlobal) {
            Object.keys(catalogoPermisosGlobal).forEach((moduloNombre) => {
                const moduloIdSeguro = moduloNombre.replace(/[^a-zA-Z0-9_-]/g, '_');
                const checkboxesModulo = document.querySelectorAll(`.check-permiso[data-modulo="${moduloNombre}"]`);
                const marcadosModulo = document.querySelectorAll(`.check-permiso[data-modulo="${moduloNombre}"]:checked`);
                const masterCheck = document.getElementById(`master-${moduloIdSeguro}`);
                const badgeModulo = document.getElementById(`badge-${moduloIdSeguro}`);

                if (badgeModulo) {
                    badgeModulo.textContent = `${marcadosModulo.length} / ${checkboxesModulo.length}`;
                }

                if (masterCheck) {
                    masterCheck.checked = (checkboxesModulo.length > 0 && marcadosModulo.length === checkboxesModulo.length);
                    masterCheck.indeterminate = (marcadosModulo.length > 0 && marcadosModulo.length < checkboxesModulo.length);
                }

                totalSeleccionados += marcadosModulo.length;
            });
        }

        if (contadorPermisos) {
            contadorPermisos.textContent = totalSeleccionados.toString();
        }
    }

    // Botones rápidos: Marcar Todos / Desmarcar Todos
    if (btnMarcarTodos) {
        btnMarcarTodos.addEventListener('click', () => {
            document.querySelectorAll('.check-permiso:not(:disabled)').forEach((cb) => {
                cb.checked = true;
            });
            actualizarContadoresPermisos();
        });
    }

    if (btnDesmarcarTodos) {
        btnDesmarcarTodos.addEventListener('click', () => {
            document.querySelectorAll('.check-permiso:not(:disabled)').forEach((cb) => {
                cb.checked = false;
            });
            actualizarContadoresPermisos();
        });
    }

    // Guardar matriz de permisos
    if (btnGuardarPermisos) {
        btnGuardarPermisos.addEventListener('click', async () => {
            const rolId = inputPermisosRolId.value;
            if (!rolId) return;

            // Recolectar IDs seleccionados (incluyendo los disabled de superadmin si aplica)
            const seleccionados = [];
            document.querySelectorAll('.check-permiso:checked').forEach((cb) => {
                seleccionados.push(Number(cb.value));
            });

            btnGuardarPermisos.disabled = true;
            btnGuardarPermisos.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando matriz...';

            try {
                const resp = await fetch(`/configuracion/roles/${rolId}/permisos`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify({
                        permisos: seleccionados,
                        _csrf_token: obtenerCsrfToken()
                    })
                });

                const json = await resp.json();
                if (!resp.ok) {
                    throw new Error(json.error || `Error HTTP ${resp.status}`);
                }

                notificar('success', json.mensaje || 'Matriz de permisos sincronizada exitosamente.');
                if (modalPermisosBs) {
                    modalPermisosBs.hide();
                }
                await cargarRoles();
            } catch (error) {
                notificar('error', error.message, 'No se pudo guardar la matriz de permisos');
            } finally {
                btnGuardarPermisos.disabled = false;
                btnGuardarPermisos.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios';
            }
        });
    }

    // =========================================================================
    // CONSULTA DE USUARIOS VINCULADOS A UN ROL (SOLO LECTURA)
    // =========================================================================

    /**
     * Abre el modal de usuarios vinculados a un rol.
     *
     * @param {string|number} rolId
     */
    async function abrirModalUsuarios(rolId) {
        if (!tbodyUsuariosRol) return;

        tbodyUsuariosRol.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Cargando usuarios...
                </td>
            </tr>
        `;

        try {
            const resp = await fetch(`/configuracion/roles/${rolId}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) throw new Error(`Error HTTP ${resp.status}`);

            const json = await resp.json();
            const rol = json.datos.rol;
            const usuarios = json.datos.usuarios || [];

            if (txtSubtituloUsuarios) {
                txtSubtituloUsuarios.innerHTML = `Rol: <strong>${escapeHtml(rol.nombre)}</strong> — Total: <strong>${usuarios.length}</strong> usuario(s) asignado(s)`;
            }

            if (usuarios.length === 0) {
                tbodyUsuariosRol.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-info me-1"></i> Este rol no cuenta con usuarios asignados actualmente.
                        </td>
                    </tr>
                `;
            } else {
                let html = '';
                usuarios.forEach((u) => {
                    const badgeEstadoUsuario = (window.CamargoInsignia && window.CamargoInsignia.estado)
                        ? window.CamargoInsignia.estado(u.estado_usuario, false, 'f-s-11')
                        : (u.estado_usuario === 'ACTIVO'
                            ? '<span class="badge bg-light-success f-s-11"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>'
                            : '<span class="badge bg-light-secondary f-s-11"><i class="fa-solid fa-circle-xmark me-1"></i>Inactivo</span>');

                    html += `
                        <tr>
                            <td>
                                <strong class="text-dark">${escapeHtml(u.nombre_usuario)}</strong>
                                <span class="badge bg-light-secondary font-monospace ms-1">ID: ${u.usuario_id}</span>
                            </td>
                            <td>
                                <div>${escapeHtml(u.nombre_completo_persona)}</div>
                                <small class="text-muted">ID Persona: ${u.persona_id} (${escapeHtml(u.estado_persona)})</small>
                            </td>
                            <td class="text-center">${badgeEstadoUsuario}</td>
                            <td class="text-secondary f-s-12">${escapeHtml(u.asignado_en)}</td>
                        </tr>
                    `;
                });
                tbodyUsuariosRol.innerHTML = html;
            }

            if (modalUsuariosBs) {
                modalUsuariosBs.show();
            }
        } catch (error) {
            notificar('error', error.message, 'No se pudo cargar la lista de usuarios');
        }
    }

    // =========================================================================
    // EVENTOS DE BÚSQUEDA Y FILTRADO
    // =========================================================================

    if (inputBusqueda) {
        let debounceTimer = null;
        inputBusqueda.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                cargarRoles();
            }, 300);
        });
    }

    if (btnLimpiarBusqueda) {
        btnLimpiarBusqueda.addEventListener('click', () => {
            if (inputBusqueda) {
                inputBusqueda.value = '';
                cargarRoles();
            }
        });
    }

    if (selectEstado) {
        selectEstado.addEventListener('change', () => {
            cargarRoles();
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => {
            cargarRoles();
        });
    }

    // Carga inicial
    cargarRoles();
});
