/**
 * Camargo PMS — Gestión Integral de Cuentas de Usuario (USUARIOS-1)
 *
 * Módulo JavaScript para la administración interactiva de usuarios humanos:
 * listado reactivo, filtros, paginación, alta vinculada a Persona, cambio de estado,
 * restablecimiento administrativo de contraseña, roles RBAC y gestión de sesiones.
 *
 * Stack vinculante: Vanilla JS moderno, Fetch API, PristineJS v1.1.0, SweetAlert2 (sin jQuery).
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // Elementos principales de la vista
    const tbodyUsuarios = document.getElementById('tbody-usuarios');
    const infoPaginacion = document.getElementById('info-paginacion-usuarios');
    const contenedorPaginacion = document.getElementById('paginacion-usuarios');
    const inputBusqueda = document.getElementById('filtro-busqueda-usuario');
    const btnLimpiarBusqueda = document.getElementById('btn-limpiar-busqueda');
    const selectEstado = document.getElementById('filtro-estado-usuario');
    const selectRol = document.getElementById('filtro-rol-usuario');
    const btnRecargar = document.getElementById('btn-recargar-usuarios');
    const btnAbrirCrear = document.getElementById('btn-abrir-crear-usuario');

    // Modales y Formularios
    const modalCrearEl = document.getElementById('modal-crear-usuario');
    const formCrear = document.getElementById('form-crear-usuario');
    const selectPersonaCrear = document.getElementById('crear-persona-id');
    const btnGuardarCrear = document.getElementById('btn-guardar-crear-usuario');

    const modalResetEl = document.getElementById('modal-restablecer-clave');
    const formReset = document.getElementById('form-restablecer-clave');
    const inputResetUsuarioId = document.getElementById('reset-usuario-id');
    const txtResetUsuarioNombre = document.getElementById('reset-nombre-usuario-txt');
    const btnGuardarReset = document.getElementById('btn-guardar-reset-clave');

    const modalRolesEl = document.getElementById('modal-roles-usuario');
    const txtRolesUsuarioNombre = document.getElementById('roles-modal-usuario-txt');
    const inputRolesUsuarioId = document.getElementById('roles-modal-usuario-id');
    const divRolesAsignados = document.getElementById('roles-modal-lista-asignados');
    const formAsignarRol = document.getElementById('form-asignar-rol');
    const selectNuevoRol = document.getElementById('select-nuevo-rol');

    const modalSesionesEl = document.getElementById('modal-sesiones-usuario');
    const txtSesionesUsuarioNombre = document.getElementById('sesiones-modal-usuario-txt');
    const inputSesionesUsuarioId = document.getElementById('sesiones-modal-usuario-id');
    const tbodySesiones = document.getElementById('tbody-sesiones-usuario');
    const btnCerrarTodasSesiones = document.getElementById('btn-cerrar-todas-sesiones');

    // Instancias de Modales Bootstrap
    const modalCrearBs = modalCrearEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalCrearEl) : null;
    const modalResetBs = modalResetEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalResetEl) : null;
    const modalRolesBs = modalRolesEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalRolesEl) : null;
    const modalSesionesBs = modalSesionesEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalSesionesEl) : null;

    // Estado local de filtros y paginación
    const estadoFiltros = {
        busqueda: '',
        estado: '',
        rol_id: '',
        pagina: 1,
        limite: 15
    };

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
                title: titulo || (tipo === 'success' ? 'Éxito' : 'Atención'),
                text: mensaje,
                confirmButtonColor: '#3085d6'
            });
        } else {
            alert(`${titulo ? titulo + ': ' : ''}${mensaje}`);
        }
    }

    /**
     * Cuadro de diálogo de confirmación interactivo.
     *
     * @param {string} titulo
     * @param {string} texto
     * @param {string} [textoConfirmar='Sí, confirmar']
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
    let validadorReset = null;

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

        // Validador custom: confirmación de contraseña debe coincidir
        const inputPass = document.getElementById('crear-contrasena');
        const inputConf = document.getElementById('crear-confirmar-contrasena');
        if (inputPass && inputConf) {
            validadorCrear.addValidator(inputConf, (valor) => {
                return valor === inputPass.value;
            }, 'La confirmación no coincide con la contraseña ingresada.', 2, false);
        }

        return validadorCrear;
    }

    function inicializarPristineReset() {
        if (!formReset || typeof Pristine === 'undefined') {
            return null;
        }

        validadorReset = new Pristine(formReset, {
            classTo: 'mb-3',
            errorClass: 'has-danger',
            successClass: 'has-success',
            errorTextParent: 'mb-3',
            errorTextTag: 'div',
            errorTextClass: 'text-danger f-s-12 mt-1'
        });

        const inputNueva = document.getElementById('reset-nueva-contrasena');
        const inputConf = document.getElementById('reset-confirmar-contrasena');
        if (inputNueva && inputConf) {
            validadorReset.addValidator(inputConf, (valor) => {
                return valor === inputNueva.value;
            }, 'La confirmación no coincide con la nueva contraseña ingresada.', 2, false);
        }

        return validadorReset;
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

    if (modalResetEl) {
        modalResetEl.addEventListener('hidden.bs.modal', () => {
            if (validadorReset) {
                validadorReset.reset();
            }
            formReset.reset();
        });
    }

    // =========================================================================
    // CARGA DE LISTADO DE USUARIOS Y RENDERIZADO
    // =========================================================================

    /**
     * Consulta el endpoint JSON de usuarios con los filtros activos.
     */
    async function cargarUsuarios() {
        if (!tbodyUsuarios) return;

        tbodyUsuarios.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Cargando usuarios...
                </td>
            </tr>
        `;

        const queryParams = new URLSearchParams();
        if (estadoFiltros.busqueda) queryParams.set('busqueda', estadoFiltros.busqueda);
        if (estadoFiltros.estado) queryParams.set('estado', estadoFiltros.estado);
        if (estadoFiltros.rol_id) queryParams.set('rol_id', estadoFiltros.rol_id);
        queryParams.set('pagina', estadoFiltros.pagina);
        queryParams.set('limite', estadoFiltros.limite);

        try {
            const resp = await fetch(`/usuarios/datos?${queryParams.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error(`Error en el servidor (HTTP ${resp.status})`);
            }

            const json = await resp.json();
            if (!json.ok) {
                throw new Error(json.error || 'No se pudo cargar la información de usuarios.');
            }

            renderizarTablaUsuarios(json.datos.usuarios, json.datos.total, json.datos.pagina, json.datos.paginas);
        } catch (e) {
            tbodyUsuarios.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        <i class="ti ti-alert-triangle me-1"></i> ${e.message}
                    </td>
                </tr>
            `;
            if (infoPaginacion) infoPaginacion.textContent = 'Error al cargar datos.';
            if (contenedorPaginacion) contenedorPaginacion.innerHTML = '';
        }
    }

    /**
     * Renderiza las filas de la tabla de usuarios.
     *
     * @param {Array<Object>} usuarios
     * @param {number} total
     * @param {number} pagina
     * @param {number} paginas
     */
    function renderizarTablaUsuarios(usuarios, total, pagina, paginas) {
        if (!usuarios || usuarios.length === 0) {
            tbodyUsuarios.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No se encontraron usuarios que coincidan con los criterios de búsqueda.
                    </td>
                </tr>
            `;
            if (infoPaginacion) infoPaginacion.textContent = '0 usuarios encontrados.';
            if (contenedorPaginacion) contenedorPaginacion.innerHTML = '';
            return;
        }

        const htmlFilas = usuarios.map(u => {
            const persona = u.persona || {};
            const doc = persona.documento ? `<span class="badge bg-light text-secondary border f-s-11 ms-1">${escapeHtml(persona.documento)}</span>` : '';

            // Badges de roles
            const rolesHtml = (u.roles && u.roles.length > 0)
                ? u.roles.map(r => {
                    const badgeClass = r.es_superadministrador ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-primary-subtle text-primary border-primary-subtle';
                    return `<span class="badge ${badgeClass} border f-s-11 me-1 mb-1">${escapeHtml(r.nombre)}</span>`;
                }).join('')
                : '<span class="text-muted f-s-12">Sin roles</span>';

            // Badge de estado
            let badgeEstadoClass = 'bg-success-subtle text-success';
            if (u.estado === 'BLOQUEADO') badgeEstadoClass = 'bg-danger-subtle text-danger';
            if (u.estado === 'INACTIVO') badgeEstadoClass = 'bg-secondary-subtle text-secondary';
            const estadoHtml = `<span class="badge ${badgeEstadoClass} f-s-11 px-2 py-1">${escapeHtml(u.estado)}</span>`;

            // Sesiones activas
            const sesionesHtml = u.sesiones_activas > 0
                ? `<span class="badge bg-info-subtle text-info border border-info-subtle cursor-pointer btn-ver-sesiones" data-id="${u.id}" data-username="${escapeHtml(u.nombre_usuario)}" title="Ver sesiones activas">
                       <i class="ti ti-devices me-1"></i>${u.sesiones_activas} activa${u.sesiones_activas > 1 ? 's' : ''}
                   </span>`
                : `<span class="text-muted f-s-12">Sin sesión</span>`;

            // Último acceso
            const ultimoAccesoHtml = u.ultimo_acceso_en
                ? `<span class="f-s-12 text-secondary">${escapeHtml(u.ultimo_acceso_en)}</span>`
                : `<span class="text-muted f-s-12 italic">Nunca</span>`;

            return `
                <tr data-usuario-id="${u.id}">
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm bg-light text-primary b-r-50 me-2 d-flex-center f-w-700">
                                ${escapeHtml(u.nombre_usuario.charAt(0).toUpperCase())}
                            </span>
                            <div>
                                <span class="f-w-600 f-s-13 text-dark">${escapeHtml(u.nombre_usuario)}</span>
                                <div class="text-muted f-s-11">ID: ${u.id}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="f-s-13 f-w-600 text-dark">${escapeHtml(persona.nombre_completo || 'Sin nombre')} ${doc}</div>
                        <div class="text-muted f-s-11">Persona ID: ${persona.id || u.persona_id}</div>
                    </td>
                    <td>${rolesHtml}</td>
                    <td class="text-center">${estadoHtml}</td>
                    <td>${ultimoAccesoHtml}</td>
                    <td class="text-center">${sesionesHtml}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-roles-usuario"
                                    data-id="${u.id}" data-username="${escapeHtml(u.nombre_usuario)}" title="Gestionar roles">
                                <i class="ti ti-shield"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-reset-clave"
                                    data-id="${u.id}" data-username="${escapeHtml(u.nombre_usuario)}" title="Restablecer contraseña">
                                <i class="ti ti-key"></i>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sesiones-usuario"
                                    data-id="${u.id}" data-username="${escapeHtml(u.nombre_usuario)}" title="Ver/Cerrar sesiones">
                                <i class="ti ti-devices"></i>
                            </button>
                            <button type="button" class="btn btn-outline-dark btn-cambiar-estado"
                                    data-id="${u.id}" data-username="${escapeHtml(u.nombre_usuario)}" data-estado="${u.estado}" title="Cambiar estado">
                                <i class="ti ti-toggle-left"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        tbodyUsuarios.innerHTML = htmlFilas;

        // Info de paginación
        const desde = (pagina - 1) * estadoFiltros.limite + 1;
        const hasta = Math.min(pagina * estadoFiltros.limite, total);
        if (infoPaginacion) {
            infoPaginacion.textContent = `Mostrando ${desde} a ${hasta} de ${total} usuarios`;
        }

        // Renderizar botones de paginación
        renderizarPaginacion(pagina, paginas);
    }

    /**
     * Renderiza controles numéricos de paginación.
     */
    function renderizarPaginacion(paginaActual, totalPaginas) {
        if (!contenedorPaginacion) return;
        if (totalPaginas <= 1) {
            contenedorPaginacion.innerHTML = '';
            return;
        }

        let html = '';
        html += `<li class="page-item ${paginaActual <= 1 ? 'disabled' : ''}">
                    <a class="page-link btn-paginar" href="#" data-pagina="${paginaActual - 1}">Anterior</a>
                 </li>`;

        for (let p = 1; p <= totalPaginas; p++) {
            if (p === 1 || p === totalPaginas || (p >= paginaActual - 2 && p <= paginaActual + 2)) {
                html += `<li class="page-item ${p === paginaActual ? 'active' : ''}">
                            <a class="page-link btn-paginar" href="#" data-pagina="${p}">${p}</a>
                         </li>`;
            } else if (p === paginaActual - 3 || p === paginaActual + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        html += `<li class="page-item ${paginaActual >= totalPaginas ? 'disabled' : ''}">
                    <a class="page-link btn-paginar" href="#" data-pagina="${paginaActual + 1}">Siguiente</a>
                 </li>`;

        contenedorPaginacion.innerHTML = html;
    }

    // =========================================================================
    // CREACIÓN DE USUARIO (MODAL)
    // =========================================================================

    /**
     * Carga el catálogo de personas naturales disponibles en el selector del modal.
     */
    async function cargarPersonasDisponibles() {
        if (!selectPersonaCrear) return;

        selectPersonaCrear.innerHTML = '<option value="">Cargando personas...</option>';

        try {
            const resp = await fetch('/usuarios/personas-disponibles', {
                headers: { 'Accept': 'application/json' }
            });
            const json = await resp.json();

            if (!json.ok) {
                throw new Error(json.error || 'Error al obtener personas disponibles.');
            }

            if (!json.datos || json.datos.length === 0) {
                selectPersonaCrear.innerHTML = '<option value="">-- No hay personas naturales disponibles sin cuenta --</option>';
                return;
            }

            let opciones = '<option value="">-- Seleccionar Persona disponible --</option>';
            json.datos.forEach(p => {
                const doc = p.documento ? ` (${p.documento})` : '';
                opciones += `<option value="${p.id}">${escapeHtml(p.nombre_completo)}${doc} [ID: ${p.id}]</option>`;
            });
            selectPersonaCrear.innerHTML = opciones;
        } catch (e) {
            selectPersonaCrear.innerHTML = `<option value="">Error: ${escapeHtml(e.message)}</option>`;
        }
    }

    if (btnAbrirCrear) {
        btnAbrirCrear.addEventListener('click', () => {
            inicializarPristineCrear();
            cargarPersonasDisponibles();
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
                persona_id: formData.get('persona_id'),
                nombre_usuario: formData.get('nombre_usuario'),
                contrasena: formData.get('contrasena'),
                confirmar_contrasena: formData.get('confirmar_contrasena'),
                rol_id: formData.get('rol_id'),
                estado: formData.get('estado'),
                _csrf_token: obtenerCsrfToken()
            };

            btnGuardarCrear.disabled = true;
            btnGuardarCrear.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creando...';

            try {
                const resp = await fetch('/usuarios', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify(payload)
                });

                const json = await resp.json();

                if (!resp.ok || !json.ok) {
                    const msgError = json.error || (json.errores ? Object.values(json.errores).join(' ') : 'Error al crear usuario.');
                    throw new Error(msgError);
                }

                notificar('success', json.mensaje || 'Usuario creado exitosamente.');
                if (modalCrearBs) {
                    modalCrearBs.hide();
                }
                cargarUsuarios();
            } catch (err) {
                notificar('error', err.message);
            } finally {
                btnGuardarCrear.disabled = false;
                btnGuardarCrear.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Crear Usuario';
            }
        });
    }

    // =========================================================================
    // RESTABLECIMIENTO ADMINISTRATIVO DE CONTRASEÑA
    // =========================================================================

    function abrirModalResetClave(usuarioId, username) {
        if (!modalResetEl) return;

        inputResetUsuarioId.value = usuarioId;
        if (txtResetUsuarioNombre) txtResetUsuarioNombre.textContent = username;

        inicializarPristineReset();
        if (modalResetBs) {
            modalResetBs.show();
        }
    }

    if (formReset) {
        formReset.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!validadorReset) {
                inicializarPristineReset();
            }

            const esValido = validadorReset ? validadorReset.validate() : true;
            if (!esValido) {
                return;
            }

            const usuarioId = inputResetUsuarioId.value;
            const formData = new FormData(formReset);
            const payload = {
                nueva_contrasena: formData.get('nueva_contrasena'),
                confirmar_contrasena: formData.get('confirmar_contrasena'),
                _csrf_token: obtenerCsrfToken()
            };

            btnGuardarReset.disabled = true;
            btnGuardarReset.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            try {
                const resp = await fetch(`/usuarios/${usuarioId}/restablecer-clave`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify(payload)
                });

                const json = await resp.json();

                if (!resp.ok || !json.ok) {
                    const msgError = json.error || (json.errores ? Object.values(json.errores).join(' ') : 'Error al restablecer contraseña.');
                    throw new Error(msgError);
                }

                notificar('success', json.mensaje || 'Contraseña restablecida correctamente.');
                if (modalResetBs) {
                    modalResetBs.hide();
                }
                cargarUsuarios();
            } catch (err) {
                notificar('error', err.message);
            } finally {
                btnGuardarReset.disabled = false;
                btnGuardarReset.innerHTML = '<i class="ti ti-check me-1"></i> Restablecer Contraseña';
            }
        });
    }

    // =========================================================================
    // GESTIÓN DE ROLES DE USUARIO (MODAL)
    // =========================================================================

    async function abrirModalRoles(usuarioId, username) {
        if (!modalRolesEl) return;

        inputRolesUsuarioId.value = usuarioId;
        if (txtRolesUsuarioNombre) txtRolesUsuarioNombre.textContent = username;
        if (divRolesAsignados) divRolesAsignados.innerHTML = '<p class="text-muted f-s-12">Cargando roles...</p>';

        if (modalRolesBs) {
            modalRolesBs.show();
        }

        await recargarRolesUsuario(usuarioId);
    }

    async function recargarRolesUsuario(usuarioId) {
        try {
            const resp = await fetch(`/usuarios/${usuarioId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await resp.json();

            if (!json.ok) {
                throw new Error(json.error || 'No se pudieron consultar los roles del usuario.');
            }

            const detalle = json.datos;
            const rolesAsignados = detalle.roles || [];
            const rolesDisponibles = detalle.roles_disponibles || [];

            // Renderizar asignados
            if (rolesAsignados.length === 0) {
                divRolesAsignados.innerHTML = '<p class="text-muted f-s-12 mb-0">El usuario no tiene roles asignados actualmente.</p>';
            } else {
                let html = '<div class="d-flex flex-wrap gap-2">';
                rolesAsignados.forEach(r => {
                    const badgeClass = r.es_superadministrador ? 'bg-danger text-white' : 'bg-primary text-white';
                    html += `
                        <div class="badge ${badgeClass} p-2 d-flex align-items-center gap-2 f-s-12">
                            <span>${escapeHtml(r.nombre)}</span>
                            <button type="button" class="btn btn-sm btn-link text-white p-0 btn-revocar-rol"
                                    data-rol-id="${r.id}" data-rol-nombre="${escapeHtml(r.nombre)}" title="Revocar rol">
                                <i class="ti ti-x f-s-14"></i>
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                divRolesAsignados.innerHTML = html;
            }

            // Población de select de disponibles (excluyendo los ya asignados)
            if (selectNuevoRol) {
                const idsAsignados = rolesAsignados.map(r => r.id);
                const disponiblesParaAsignar = rolesDisponibles.filter(r => !idsAsignados.includes(r.id));

                if (disponiblesParaAsignar.length === 0) {
                    selectNuevoRol.innerHTML = '<option value="">-- Todos los roles ya están asignados --</option>';
                } else {
                    let opciones = '<option value="">-- Seleccionar Rol para Asignar --</option>';
                    disponiblesParaAsignar.forEach(r => {
                        opciones += `<option value="${r.id}">${escapeHtml(r.nombre)}</option>`;
                    });
                    selectNuevoRol.innerHTML = opciones;
                }
            }
        } catch (e) {
            if (divRolesAsignados) {
                divRolesAsignados.innerHTML = `<p class="text-danger f-s-12">${escapeHtml(e.message)}</p>`;
            }
        }
    }

    if (formAsignarRol) {
        formAsignarRol.addEventListener('submit', async (e) => {
            e.preventDefault();

            const usuarioId = inputRolesUsuarioId.value;
            const rolId = selectNuevoRol.value;

            if (!rolId) {
                notificar('warning', 'Por favor selecciona un rol a asignar.');
                return;
            }

            try {
                const resp = await fetch(`/usuarios/${usuarioId}/roles`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify({ rol_id: rolId, _csrf_token: obtenerCsrfToken() })
                });

                const json = await resp.json();

                if (!resp.ok || !json.ok) {
                    throw new Error(json.error || 'No se pudo asignar el rol.');
                }

                notificar('success', json.mensaje || 'Rol asignado exitosamente.');
                await recargarRolesUsuario(usuarioId);
                cargarUsuarios();
            } catch (err) {
                notificar('error', err.message);
            }
        });
    }

    // Delegación para revocar rol dentro del modal
    if (divRolesAsignados) {
        divRolesAsignados.addEventListener('click', async (e) => {
            const btnRevocar = e.target.closest('.btn-revocar-rol');
            if (!btnRevocar) return;

            const usuarioId = inputRolesUsuarioId.value;
            const rolId = btnRevocar.dataset.rolId;
            const rolNombre = btnRevocar.dataset.rolNombre;

            const confirmado = await confirmar(
                'Revocar Rol',
                `¿Estás seguro de que deseas revocar el rol "${rolNombre}" de este usuario?`,
                'Sí, revocar rol',
                '#dc3545'
            );

            if (!confirmado) return;

            try {
                const resp = await fetch(`/usuarios/${usuarioId}/roles/${rolId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify({ rol_id: rolId, _csrf_token: obtenerCsrfToken() })
                });

                const json = await resp.json();

                if (!resp.ok || !json.ok) {
                    throw new Error(json.error || 'Error al revocar rol.');
                }

                notificar('success', json.mensaje || 'Rol revocado exitosamente.');
                await recargarRolesUsuario(usuarioId);
                cargarUsuarios();
            } catch (err) {
                notificar('error', err.message);
            }
        });
    }

    // =========================================================================
    // GESTIÓN DE SESIONES ACTIVAS (MODAL)
    // =========================================================================

    async function abrirModalSesiones(usuarioId, username) {
        if (!modalSesionesEl) return;

        inputSesionesUsuarioId.value = usuarioId;
        if (txtSesionesUsuarioNombre) txtSesionesUsuarioNombre.textContent = username;
        if (tbodySesiones) {
            tbodySesiones.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Cargando sesiones...</td></tr>';
        }

        if (modalSesionesBs) {
            modalSesionesBs.show();
        }

        await recargarSesionesUsuario(usuarioId);
    }

    async function recargarSesionesUsuario(usuarioId) {
        if (!tbodySesiones) return;

        try {
            const resp = await fetch(`/usuarios/${usuarioId}/sesiones`, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await resp.json();

            if (!json.ok) {
                throw new Error(json.error || 'Error al consultar sesiones.');
            }

            const sesiones = json.datos || [];
            if (sesiones.length === 0) {
                tbodySesiones.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">El usuario no tiene sesiones activas vigentes.</td></tr>';
                return;
            }

            let html = '';
            sesiones.forEach(s => {
                html += `
                    <tr>
                        <td class="f-w-600">#${s.id}</td>
                        <td><code>${escapeHtml(s.ip || 'Desconocida')}</code></td>
                        <td class="f-s-12 text-secondary" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(s.user_agent || '')}">
                            ${escapeHtml(s.user_agent || 'N/A')}
                        </td>
                        <td class="f-s-11 text-secondary">${escapeHtml(s.iniciada_en)}</td>
                        <td class="f-s-11 text-secondary">${escapeHtml(s.ultima_actividad_en)}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-cerrar-sesion-indiv"
                                    data-sesion-id="${s.id}" title="Cerrar esta sesión">
                                <i class="ti ti-x"></i> Cerrar
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbodySesiones.innerHTML = html;
        } catch (e) {
            tbodySesiones.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    // Cerrar sesión individual
    if (tbodySesiones) {
        tbodySesiones.addEventListener('click', async (e) => {
            const btnCerrar = e.target.closest('.btn-cerrar-sesion-indiv');
            if (!btnCerrar) return;

            const usuarioId = inputSesionesUsuarioId.value;
            const sesionId = btnCerrar.dataset.sesionId;

            const confirmado = await confirmar(
                'Cerrar Sesión',
                `¿Deseas cerrar inmediatamente la sesión #${sesionId} de este usuario?`,
                'Sí, cerrar sesión',
                '#dc3545'
            );

            if (!confirmado) return;

            try {
                const resp = await fetch(`/usuarios/${usuarioId}/sesiones/${sesionId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify({ _csrf_token: obtenerCsrfToken() })
                });

                const json = await resp.json();

                if (!resp.ok || !json.ok) {
                    throw new Error(json.error || 'Error al cerrar sesión.');
                }

                notificar('success', json.mensaje || 'Sesión cerrada exitosamente.');
                await recargarSesionesUsuario(usuarioId);
                cargarUsuarios();
            } catch (err) {
                notificar('error', err.message);
            }
        });
    }

    // Cerrar todas las sesiones
    if (btnCerrarTodasSesiones) {
        btnCerrarTodasSesiones.addEventListener('click', async () => {
            const usuarioId = inputSesionesUsuarioId.value;

            const confirmado = await confirmar(
                'Cerrar Todas las Sesiones',
                '¿Confirmas la revocación inmediata de TODAS las sesiones activas de este usuario?',
                'Sí, revocar todas',
                '#dc3545'
            );

            if (!confirmado) return;

            try {
                const resp = await fetch(`/usuarios/${usuarioId}/sesiones/cerrar-todas`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': obtenerCsrfToken()
                    },
                    body: JSON.stringify({ _csrf_token: obtenerCsrfToken() })
                });

                const json = await resp.json();

                if (!resp.ok || !json.ok) {
                    throw new Error(json.error || 'Error al revocar sesiones.');
                }

                notificar('success', json.mensaje || 'Todas las sesiones fueron cerradas.');
                await recargarSesionesUsuario(usuarioId);
                cargarUsuarios();
            } catch (err) {
                notificar('error', err.message);
            }
        });
    }

    // =========================================================================
    // CAMBIO DE ESTADO ADMINISTRATIVO (ACTIVO, INACTIVO, BLOQUEADO)
    // =========================================================================

    async function cambiarEstadoUsuario(usuarioId, username, estadoActual) {
        let opciones = {
            'ACTIVO': 'ACTIVO (Acceso permitido)',
            'INACTIVO': 'INACTIVO (Desactivación lógica)',
            'BLOQUEADO': 'BLOQUEADO (Bloqueo de seguridad)'
        };

        if (typeof Swal !== 'undefined') {
            const { value: nuevoEstado } = await Swal.fire({
                title: `Cambiar Estado: ${username}`,
                text: 'Selecciona el nuevo estado administrativo:',
                input: 'select',
                inputOptions: opciones,
                inputValue: estadoActual,
                showCancelButton: true,
                confirmButtonText: 'Actualizar Estado',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Debes seleccionar un estado';
                    }
                    if (value === estadoActual) {
                        return 'Selecciona un estado distinto al actual';
                    }
                }
            });

            if (!nuevoEstado) return;

            ejecutarCambioEstado(usuarioId, nuevoEstado);
        } else {
            const nuevo = window.prompt(`Nuevo estado para ${username} (ACTIVO, INACTIVO, BLOQUEADO):`, estadoActual);
            if (nuevo && nuevo !== estadoActual) {
                ejecutarCambioEstado(usuarioId, nuevo.toUpperCase());
            }
        }
    }

    async function ejecutarCambioEstado(usuarioId, nuevoEstado) {
        try {
            const resp = await fetch(`/usuarios/${usuarioId}/estado`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': obtenerCsrfToken()
                },
                body: JSON.stringify({ estado: nuevoEstado, _csrf_token: obtenerCsrfToken() })
            });

            const json = await resp.json();

            if (!resp.ok || !json.ok) {
                throw new Error(json.error || 'Error al actualizar estado del usuario.');
            }

            notificar('success', json.mensaje || 'Estado actualizado exitosamente.');
            cargarUsuarios();
        } catch (err) {
            notificar('error', err.message);
        }
    }

    // =========================================================================
    // DELEGACIÓN DE EVENTOS EN TABLA DE USUARIOS
    // =========================================================================

    if (tbodyUsuarios) {
        tbodyUsuarios.addEventListener('click', (e) => {
            const btnReset = e.target.closest('.btn-reset-clave');
            if (btnReset) {
                abrirModalResetClave(btnReset.dataset.id, btnReset.dataset.username);
                return;
            }

            const btnRoles = e.target.closest('.btn-roles-usuario');
            if (btnRoles) {
                abrirModalRoles(btnRoles.dataset.id, btnRoles.dataset.username);
                return;
            }

            const btnSesiones = e.target.closest('.btn-sesiones-usuario') || e.target.closest('.btn-ver-sesiones');
            if (btnSesiones) {
                abrirModalSesiones(btnSesiones.dataset.id, btnSesiones.dataset.username);
                return;
            }

            const btnEstado = e.target.closest('.btn-cambiar-estado');
            if (btnEstado) {
                cambiarEstadoUsuario(btnEstado.dataset.id, btnEstado.dataset.username, btnEstado.dataset.estado);
                return;
            }
        });
    }

    // Delegación de paginación
    if (contenedorPaginacion) {
        contenedorPaginacion.addEventListener('click', (e) => {
            const link = e.target.closest('.btn-paginar');
            if (!link) return;
            e.preventDefault();

            const pag = parseInt(link.dataset.pagina, 10);
            if (!isNaN(pag) && pag > 0) {
                estadoFiltros.pagina = pag;
                cargarUsuarios();
            }
        });
    }

    // Eventos de Filtros con Debounce
    let timerBusqueda = null;
    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', () => {
            clearTimeout(timerBusqueda);
            timerBusqueda = setTimeout(() => {
                estadoFiltros.busqueda = inputBusqueda.value.trim();
                estadoFiltros.pagina = 1;
                cargarUsuarios();
            }, 300);
        });
    }

    if (btnLimpiarBusqueda) {
        btnLimpiarBusqueda.addEventListener('click', () => {
            if (inputBusqueda) inputBusqueda.value = '';
            estadoFiltros.busqueda = '';
            estadoFiltros.pagina = 1;
            cargarUsuarios();
        });
    }

    if (selectEstado) {
        selectEstado.addEventListener('change', () => {
            estadoFiltros.estado = selectEstado.value;
            estadoFiltros.pagina = 1;
            cargarUsuarios();
        });
    }

    if (selectRol) {
        selectRol.addEventListener('change', () => {
            estadoFiltros.rol_id = selectRol.value;
            estadoFiltros.pagina = 1;
            cargarUsuarios();
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => {
            cargarUsuarios();
        });
    }

    /**
     * Escapa caracteres especiales en strings HTML para mitigar XSS.
     *
     * @param {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Carga inicial
    cargarUsuarios();
});
