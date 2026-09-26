/**
 * Camargo PMS — Núcleo Central de Configuración del Sistema (CONFIGURACIÓN-1)
 *
 * Módulo JavaScript para la gestión reactiva de parámetros funcionales,
 * validación del lado del cliente con PristineJS y confirmaciones seguras con SweetAlert2.
 *
 * Principios vinculantes:
 * - CONFIGURACIÓN FUNCIONAL (BD) ≠ ENTORNO TÉCNICO (.env)
 * - SEPARACIÓN ESTRICTA: Parámetros protegidos no son mutables por el usuario
 * - TRAZABILIDAD D-061: Toda mutación viaja autenticada con token CSRF
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const form = document.getElementById('form-configuracion-sistema');
    const btnGuardarHeader = document.getElementById('btn-guardar-configuracion');
    const btnGuardarFooter = document.getElementById('btn-guardar-configuracion-footer');
    const btnsRestaurar = document.querySelectorAll('.btn-restaurar-cfg');

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
        const inputForm = form ? form.querySelector('input[name="csrf_token"]') : null;
        if (inputForm && inputForm.value) {
            return inputForm.value;
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        return '';
    }

    /**
     * Inicializa la validación en el cliente con PristineJS si está disponible.
     */
    let validador = null;
    if (form && typeof Pristine !== 'undefined') {
        validador = new Pristine(form, {
            classTo: 'mb-2',
            errorClass: 'is-invalid',
            successClass: 'is-valid',
            errorTextParent: 'mb-2',
            errorTextTag: 'div',
            errorTextClass: 'invalid-feedback f-s-12'
        });
    }

    /**
     * Recopila todos los campos editables del formulario en un objeto clave-valor.
     *
     * @returns {Record<string, string>}
     */
    function recopilarConfiguraciones() {
        const resultado = {};
        if (!form) return resultado;

        const campos = form.querySelectorAll('.campo-configuracion');
        campos.forEach(campo => {
            if (campo.disabled) return;

            // Extraer clave técnica del nombre: configuraciones[clave.tecnica]
            const match = campo.name.match(/^configuraciones\[([^\]]+)\]$/);
            if (!match) return;

            const clave = match[1];

            if (campo.type === 'checkbox') {
                resultado[clave] = campo.checked ? '1' : '0';
            } else {
                resultado[clave] = campo.value;
            }
        });

        return resultado;
    }

    /**
     * Envía la actualización de configuraciones al servidor.
     */
    async function guardarConfiguracion() {
        if (validador && !validador.validate()) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación incompleta',
                    text: 'Por favor revise los campos con formato incorrecto antes de guardar.',
                    confirmButtonText: 'Entendido'
                });
            }
            return;
        }

        const configuraciones = recopilarConfiguraciones();
        if (Object.keys(configuraciones).length === 0) {
            return;
        }

        const confirmar = async () => {
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: '¿Guardar cambios?',
                    text: 'Los nuevos valores de configuración se aplicarán inmediatamente.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#aaa',
                    confirmButtonText: 'Sí, guardar',
                    cancelButtonText: 'Cancelar'
                });
                return res.isConfirmed;
            }
            return window.confirm('¿Desea guardar los cambios en la configuración del sistema?');
        };

        const prosegir = await confirmar();
        if (!prosegir) return;

        // Deshabilitar botones durante el envío
        if (btnGuardarHeader) btnGuardarHeader.disabled = true;
        if (btnGuardarFooter) btnGuardarFooter.disabled = true;

        try {
            const csrfToken = obtenerCsrfToken();
            const respuesta = await fetch('/configuracion/sistema', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf_token: csrfToken,
                    configuraciones: configuraciones
                })
            });

            const datos = await respuesta.json();

            if (respuesta.ok && datos.ok) {
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Configuración actualizada',
                        text: datos.mensaje || 'Los parámetros fueron guardados exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert(datos.mensaje || 'Configuración guardada exitosamente.');
                }
            } else {
                let mensajeError = datos.error || 'Ocurrió un error al guardar la configuración.';
                if (datos.errores && typeof datos.errores === 'object') {
                    const lista = Object.values(datos.errores).join('\n');
                    mensajeError += '\n' + lista;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al guardar',
                        text: mensajeError
                    });
                } else {
                    alert(mensajeError);
                }
            }
        } catch (error) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Falla de conexión',
                    text: 'No se pudo comunicar con el servidor para guardar la configuración.'
                });
            } else {
                alert('No se pudo comunicar con el servidor.');
            }
        } finally {
            if (btnGuardarHeader) btnGuardarHeader.disabled = false;
            if (btnGuardarFooter) btnGuardarFooter.disabled = false;
        }
    }

    /**
     * Restaura un parámetro a su valor predeterminado de fábrica.
     *
     * @param {string} clave
     */
    async function restaurarParametro(clave) {
        if (!clave) return;

        const confirmar = async () => {
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: '¿Restaurar parámetro?',
                    text: `El parámetro "${clave}" volverá a su valor de fábrica predeterminado.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#aaa',
                    confirmButtonText: 'Sí, restaurar',
                    cancelButtonText: 'Cancelar'
                });
                return res.isConfirmed;
            }
            return window.confirm(`¿Desea restaurar el parámetro "${clave}" al valor predeterminado?`);
        };

        const prosegir = await confirmar();
        if (!prosegir) return;

        try {
            const csrfToken = obtenerCsrfToken();
            const respuesta = await fetch('/configuracion/sistema/restaurar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    _csrf_token: csrfToken,
                    clave: clave
                })
            });

            const datos = await respuesta.json();

            if (respuesta.ok && datos.ok) {
                // Actualizar el control visual en el DOM
                const idInput = 'cfg_' + clave.replace(/\./g, '_');
                const input = document.getElementById(idInput);
                if (input && datos.datos) {
                    const nuevoValor = datos.datos.valor;
                    if (input.type === 'checkbox') {
                        input.checked = (nuevoValor === '1' || nuevoValor === 'true' || nuevoValor === true);
                    } else {
                        input.value = nuevoValor !== null ? nuevoValor : '';
                    }
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Parámetro restaurado',
                        text: datos.mensaje || `El parámetro "${clave}" fue restaurado con éxito.`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert(datos.mensaje || 'Parámetro restaurado.');
                }
            } else {
                const mensajeError = datos.error || 'No se pudo restaurar el parámetro.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al restaurar',
                        text: mensajeError
                    });
                } else {
                    alert(mensajeError);
                }
            }
        } catch (error) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Falla de conexión',
                    text: 'No fue posible comunicar con el servidor para la restauración.'
                });
            } else {
                alert('Falla de conexión con el servidor.');
            }
        }
    }

    // Eventos
    if (btnGuardarHeader) {
        btnGuardarHeader.addEventListener('click', (e) => {
            e.preventDefault();
            guardarConfiguracion();
        });
    }

    if (btnGuardarFooter) {
        btnGuardarFooter.addEventListener('click', (e) => {
            e.preventDefault();
            guardarConfiguracion();
        });
    }

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            guardarConfiguracion();
        });
    }

    btnsRestaurar.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const clave = btn.getAttribute('data-clave');
            restaurarParametro(clave);
        });
    });
});
