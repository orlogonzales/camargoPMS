/**
 * Camargo PMS — Gestión de Menú Dinámico
 *
 * Módulo JavaScript para la administración interactiva de opciones de menú:
 * creación, edición, alternancia de estado y reordenamiento transaccional.
 *
 * Cumple con el stack oficial: Vanilla JS, Fetch API, SweetAlert2 y sin jQuery.
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // Elementos del DOM
    const contenedorMenu = document.getElementById('tabla-gestion-menu');
    const modalOpcionEl = document.getElementById('modal-opcion-menu');
    const formOpcion = document.getElementById('form-opcion-menu');
    const btnGuardarOpcion = document.getElementById('btn-guardar-opcion');
    const modalTitulo = document.getElementById('modal-opcion-titulo');

    // Campos del formulario
    const inputId = document.getElementById('opcion-id');
    const selectPadre = document.getElementById('opcion-padre-id');
    const inputClave = document.getElementById('opcion-clave');
    const inputNombre = document.getElementById('opcion-nombre');
    const inputIcono = document.getElementById('opcion-icono');
    const inputRuta = document.getElementById('opcion-ruta');
    const selectPermiso = document.getElementById('opcion-permiso-id');
    const inputOrden = document.getElementById('opcion-orden');
    const selectEstado = document.getElementById('opcion-estado');
    const contenedorRuta = document.getElementById('grupo-campo-ruta');

    let modalBootstrap = null;
    if (modalOpcionEl && typeof bootstrap !== 'undefined') {
        modalBootstrap = new bootstrap.Modal(modalOpcionEl);
    }

    /**
     * Obtiene el token CSRF activo de la metaetiqueta o campo hidden.
     *
     * @returns {string}
     */
    function obtenerCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        const input = document.querySelector('input[name="_csrf_token"], input[name="_token"]');
        return input ? input.value : '';
    }

    /**
     * Muestra una notificación al usuario empleando SweetAlert2 o fallback nativo.
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

    let validadorPristine = null;

    /**
     * Inicializa y configura la instancia de validación PristineJS sobre el formulario de opción de menú.
     *
     * @returns {Object|null} Instancia de Pristine o null si no está disponible.
     */
    function inicializarPristine() {
        if (!formOpcion || typeof Pristine === 'undefined') {
            return null;
        }

        if (validadorPristine) {
            return validadorPristine;
        }

        // Parche defensivo para soporte universal de patrones regex en PristineJS
        if (typeof Pristine.addValidator === 'function' && !Pristine.__patternParcheado) {
            Pristine.__patternParcheado = true;
            Pristine.addValidator('pattern', function(val, pattern) {
                if (!val) return true;
                let regex;
                const m = String(pattern).match(/^\/(.*?)\/([gimy]*)$/);
                if (m) {
                    regex = new RegExp(m[1], m[2]);
                } else {
                    regex = new RegExp(pattern);
                }
                return regex.test(val);
            }, 'El formato no coincide con el solicitado.', 2, false);
        }

        validadorPristine = new Pristine(formOpcion, {
            classTo: 'mb-3',
            errorClass: 'has-validation-error',
            successClass: 'is-valid-group',
            errorTextParent: 'mb-3',
            errorTextTag: 'div',
            errorTextClass: 'text-danger f-s-11 mt-1 pristine-error'
        }, true);

        // Validador explícito de nombre visible
        validadorPristine.addValidator(
            inputNombre,
            function(valor) {
                return valor !== undefined && valor !== null && valor.trim() !== '';
            },
            'El nombre visible es obligatorio.',
            99,
            true
        );

        // Validador de categoría padre: obligatorio si el modo es módulo o submódulo
        validadorPristine.addValidator(
            selectPadre,
            function(valor) {
                const esModoHijo = formOpcion.dataset.modo === 'modulo' || formOpcion.dataset.modo === 'submodulo' || formOpcion.dataset.modo === 'secundaria';
                if (esModoHijo && (!valor || valor.trim() === '')) {
                    return false;
                }
                return true;
            },
            'Debe seleccionar un dominio o módulo padre.',
            10,
            false
        );

        // Validador de clave técnica: formato alfanumérico seguro
        validadorPristine.addValidator(
            inputClave,
            function(valor) {
                if (!valor || valor.trim() === '') {
                    return false;
                }
                return /^[a-z0-9_\-]+$/.test(valor.trim());
            },
            'La clave técnica solo admite letras minúsculas, números, guiones y barras bajas.',
            8,
            false
        );

        // Validador de ruta: inocua, sin esquemas externos ni scripts (solo aplicable a módulos y submódulos)
        validadorPristine.addValidator(
            inputRuta,
            function(valor) {
                const esPrincipal = selectPadre.value === '' && formOpcion.dataset.modo === 'principal';
                if (esPrincipal) {
                    return true;
                }
                if (!valor || valor.trim() === '') {
                    return true;
                }
                const valLimpio = valor.trim().toLowerCase();
                if (
                    valLimpio.startsWith('javascript:') ||
                    valLimpio.startsWith('data:') ||
                    valLimpio.startsWith('vbscript:') ||
                    valLimpio.startsWith('http://') ||
                    valLimpio.startsWith('https://') ||
                    valLimpio.includes('<') ||
                    valLimpio.includes('>') ||
                    valLimpio.includes('"')
                ) {
                    return false;
                }
                return true;
            },
            'La ruta debe ser interna y no contener esquemas externos (http/https) ni scripts no seguros.',
            5,
            false
        );

        return validadorPristine;
    }

    /**
     * Ajusta la visibilidad y requerimiento del campo ruta según el nivel seleccionado.
     */
    function sincronizarVisibilidadRuta() {
        const tienePadre = selectPadre.value !== '';
        if (contenedorRuta) {
            contenedorRuta.style.display = tienePadre ? 'block' : 'none';
        }

        if (tienePadre) {
            const opcionSel = selectPadre.options[selectPadre.selectedIndex];
            const nivelPadre = opcionSel && opcionSel.dataset.nivelPadre ? parseInt(opcionSel.dataset.nivelPadre, 10) : 1;
            formOpcion.dataset.modo = (nivelPadre === 2) ? 'submodulo' : 'modulo';
        } else {
            formOpcion.dataset.modo = 'principal';
        }

        if (validadorPristine) {
            validadorPristine.reset();
        }
    }

    if (selectPadre) {
        selectPadre.addEventListener('change', sincronizarVisibilidadRuta);
    }

    if (modalOpcionEl) {
        modalOpcionEl.addEventListener('hidden.bs.modal', () => {
            if (validadorPristine) {
                validadorPristine.reset();
            }
            formOpcion.reset();
        });
    }

    /**
     * Abre el modal en modo creación.
     *
     * @param {number|string|null} [padreIdPredeterminado] ID del elemento padre si se anida.
     * @param {number|null} [nivelSugerido] Nivel que se creará (1, 2 o 3).
     */
    window.abrirModalCrearOpcion = function(padreIdPredeterminado = null, nivelSugerido = null) {
        formOpcion.reset();
        inputId.value = '';
        inputClave.readOnly = false;
        selectPadre.disabled = false;

        if (padreIdPredeterminado !== null && padreIdPredeterminado !== undefined) {
            selectPadre.value = String(padreIdPredeterminado);
            if (nivelSugerido === 3) {
                formOpcion.dataset.modo = 'submodulo';
                modalTitulo.textContent = 'Nuevo Submódulo (Nivel 3)';
            } else {
                formOpcion.dataset.modo = 'modulo';
                modalTitulo.textContent = 'Nuevo Módulo (Nivel 2)';
            }
        } else if (padreIdPredeterminado === undefined) {
            formOpcion.dataset.modo = 'modulo';
            selectPadre.value = '';
            modalTitulo.textContent = 'Nueva Opción de Menú';
        } else {
            formOpcion.dataset.modo = 'principal';
            selectPadre.value = '';
            modalTitulo.textContent = 'Nuevo Dominio Principal (Nivel 1)';
        }

        if (selectPadre) selectPadre.dispatchEvent(new Event('change'));
        if (selectPermiso) selectPermiso.dispatchEvent(new Event('change'));
        if (selectEstado) selectEstado.dispatchEvent(new Event('change'));

        const validador = inicializarPristine();
        if (validador) {
            validador.reset();
        }

        sincronizarVisibilidadRuta();

        if (modalBootstrap) {
            modalBootstrap.show();
        }
    };

    /**
     * Abre el modal en modo edición con los datos del elemento.
     *
     * @param {Object} datos
     */
    window.abrirModalEditarOpcion = function(datos) {
        formOpcion.reset();
        inputId.value = datos.id || '';
        selectPadre.value = datos.padre_id ? String(datos.padre_id) : '';
        inputClave.value = datos.clave || '';
        inputNombre.value = datos.nombre || '';
        inputIcono.value = datos.icono || '';
        inputRuta.value = datos.ruta || '';
        selectPermiso.value = datos.permiso_id ? String(datos.permiso_id) : '';
        inputOrden.value = datos.orden || 1;
        selectEstado.value = datos.estado || 'ACTIVO';

        if (selectPadre) selectPadre.dispatchEvent(new Event('change'));
        if (selectPermiso) selectPermiso.dispatchEvent(new Event('change'));
        if (selectEstado) selectEstado.dispatchEvent(new Event('change'));

        // Elementos de sistema protegen clave y nivel
        const esSistema = Boolean(datos.es_sistema);
        inputClave.readOnly = esSistema;
        selectPadre.disabled = esSistema;

        const validador = inicializarPristine();
        if (validador) {
            validador.reset();
        }

        sincronizarVisibilidadRuta();
        const nivel = datos.nivel || (datos.padre_id ? 2 : 1);
        modalTitulo.textContent = `Editar: ${datos.nombre} (Nivel ${nivel})`;

        if (modalBootstrap) {
            modalBootstrap.show();
        }
    };

    /**
     * Maneja el envío del formulario para crear o actualizar una opción.
     */
    if (formOpcion) {
        formOpcion.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Validación estricta con PristineJS previa al Fetch
            const validador = inicializarPristine();
            if (validador) {
                const esValido = validador.validate();
                if (!esValido) {
                    // Validación en cliente fallida: cancelar Fetch de inmediato
                    return;
                }
            }

            const id = inputId.value.trim();
            const esEdicion = id !== '';
            const csrf = obtenerCsrfToken();

            const payload = {
                _csrf_token: csrf,
                clave: inputClave.value.trim(),
                nombre: inputNombre.value.trim(),
                icono: inputIcono.value.trim(),
                ruta: selectPadre.value !== '' ? inputRuta.value.trim() : null,
                padre_id: selectPadre.value !== '' ? parseInt(selectPadre.value, 10) : null,
                permiso_id: selectPermiso.value !== '' ? parseInt(selectPermiso.value, 10) : null,
                orden: parseInt(inputOrden.value, 10) || 1,
                estado: selectEstado.value
            };

            const url = esEdicion
                ? `/configuracion/menu/${id}`
                : '/configuracion/menu';

            const metodo = esEdicion ? 'PUT' : 'POST';

            btnGuardarOpcion.disabled = true;
            btnGuardarOpcion.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            try {
                const respuesta = await fetch(url, {
                    method: metodo,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const resultado = await respuesta.json();

                if (!respuesta.ok || !resultado.exito) {
                    throw new Error(resultado.error || 'Ocurrió un error al procesar la solicitud.');
                }

                if (modalBootstrap) {
                    modalBootstrap.hide();
                }

                notificar('success', resultado.mensaje || 'Operación completada con éxito.');
                setTimeout(() => window.location.reload(), 800);
            } catch (error) {
                notificar('error', error.message, 'Error de Validación');
            } finally {
                btnGuardarOpcion.disabled = false;
                btnGuardarOpcion.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios';
            }
        });
    }

    /**
     * Alterna el estado ACTIVO / INACTIVO de una opción de menú.
     *
     * @param {number|string} id
     * @param {string} nombre
     * @param {string} estadoActual
     */
    window.alternarEstadoOpcion = async function(id, nombre, estadoActual) {
        const nuevoEstado = estadoActual === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        const accion = nuevoEstado === 'ACTIVO' ? 'activar' : 'desactivar';

        const ejecutarCambio = async () => {
            const csrf = obtenerCsrfToken();
            try {
                const respuesta = await fetch(`/configuracion/menu/${id}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        _csrf_token: csrf,
                        estado: nuevoEstado
                    })
                });

                const resultado = await respuesta.json();
                if (!respuesta.ok || !resultado.exito) {
                    throw new Error(resultado.error || 'Error al cambiar el estado.');
                }

                notificar('success', resultado.mensaje || `La opción ha sido ${nuevoEstado.toLowerCase()}a.`);
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                notificar('error', err.message, 'No se pudo cambiar el estado');
            }
        };

        if (typeof Swal !== 'undefined') {
            const confirmacion = await Swal.fire({
                title: `¿Desea ${accion} esta opción?`,
                text: `"${nombre}" pasará a estado ${nuevoEstado}.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Sí, ${accion}`,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: nuevoEstado === 'ACTIVO' ? '#198754' : '#d33'
            });

            if (confirmacion.isConfirmed) {
                await ejecutarCambio();
            }
        } else {
            if (confirm(`¿Desea ${accion} "${nombre}"?`)) {
                await ejecutarCambio();
            }
        }
    };

    /**
     * Mueve una opción hacia arriba o hacia abajo en su mismo nivel de orden.
     *
     * @param {number} id
     * @param {number|null} padreId
     * @param {'arriba'|'abajo'} direccion
     */
    window.moverOrdenOpcion = async function(id, padreId, direccion) {
        // Encontrar filas hermanas dentro del contenedor
        const selector = padreId === null
            ? '[data-item-padre="null"]'
            : `[data-item-padre="${padreId}"]`;

        const filas = Array.from(document.querySelectorAll(selector));
        const index = filas.findIndex(f => parseInt(f.dataset.itemId, 10) === id);

        if (index === -1) {
            return;
        }

        const nuevoIndex = direccion === 'arriba' ? index - 1 : index + 1;
        if (nuevoIndex < 0 || nuevoIndex >= filas.length) {
            return; // Ya está en el límite
        }

        // Intercambiar elementos en el array
        const temp = filas[index];
        filas[index] = filas[nuevoIndex];
        filas[nuevoIndex] = temp;

        // Construir payload homogéneo con orden consecutivo 1..N
        const elementos = filas.map((fila, i) => ({
            id: parseInt(fila.dataset.itemId, 10),
            orden: i + 1
        }));

        const csrf = obtenerCsrfToken();

        try {
            const respuesta = await fetch('/configuracion/menu/orden', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    _csrf_token: csrf,
                    padre_id: padreId,
                    elementos: elementos
                })
            });

            const resultado = await respuesta.json();
            if (!respuesta.ok || !resultado.exito) {
                throw new Error(resultado.error || 'Error al reordenar los elementos.');
            }

            notificar('success', 'El orden fue actualizado.');
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            notificar('error', err.message, 'Error de Reordenamiento');
        }
    };

    /**
     * Elimina una opción de menú que no sea de sistema.
     *
     * @param {number|string} id
     * @param {string} nombre
     */
    window.eliminarOpcionMenu = async function(id, nombre) {
        const ejecutarEliminacion = async () => {
            const csrf = obtenerCsrfToken();
            try {
                const respuesta = await fetch(`/configuracion/menu/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ _csrf_token: csrf })
                });

                const resultado = await respuesta.json();
                if (!respuesta.ok || !resultado.exito) {
                    throw new Error(resultado.error || 'No se pudo eliminar la opción.');
                }

                notificar('success', resultado.mensaje || 'Opción eliminada con éxito.');
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                notificar('error', err.message, 'Error al Eliminar');
            }
        };

        if (typeof Swal !== 'undefined') {
            const confirmacion = await Swal.fire({
                title: '¿Confirmar eliminación?',
                text: `Se eliminará la opción "${nombre}". Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (confirmacion.isConfirmed) {
                await ejecutarEliminacion();
            }
        } else {
            if (confirm(`¿Eliminar definitivamente "${nombre}"?`)) {
                await ejecutarEliminacion();
            }
        }
    };
});
