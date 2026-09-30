/**
 * Camargo PMS — Infraestructura transversal de formularios y validación (UI-ALINA-1C / D-096)
 *
 * Estandariza la experiencia de usuario de formularios nativos Alina:
 * - Integración formal de PristineJS (v1.1.0 local) con localización en español.
 * - Validación frontend UX sin duplicar reglas de dominio soberanas del backend.
 * - Prevención de doble submit y estados de carga coherentes con Alina (spinner/loading).
 * - Sincronización automática de Select2, Flatpickr y switches con el ciclo de vida del formulario.
 * - Reinicialización defensiva ante apertura de modales Bootstrap 5.
 * - Estricto cumplimiento de gobernanza: 0 jQuery AJAX, Fetch + JSON exclusivo.
 */

(function () {
    'use strict';

    /**
     * Registro de instancias de Pristine por formulario.
     * @type {WeakMap<HTMLFormElement, Object>}
     */
    const instanciasPristine = new WeakMap();

    // =========================================================================
    // 1. CONFIGURACIÓN DE PRISTINE JS (LOCALIZACIÓN ES)
    // =========================================================================

    if (typeof window.Pristine !== 'undefined') {
        window.Pristine.addMessages('es', {
            required: 'Este campo es obligatorio.',
            email: 'Ingrese un correo electrónico válido.',
            number: 'Este campo debe ser numérico.',
            integer: 'Este campo debe ser un número entero.',
            url: 'Ingrese una URL válida.',
            tel: 'Ingrese un número telefónico válido.',
            maxlength: 'No debe superar los ${1} caracteres.',
            minlength: 'Debe contener al menos ${1} caracteres.',
            min: 'El valor mínimo es ${1}.',
            max: 'El valor máximo es ${1}.',
            pattern: 'El formato ingresado no es válido.',
            equals: 'Los valores no coinciden.',
            default: 'Por favor ingrese un valor válido.'
        });

        window.Pristine.setLocale('es');

        window.Pristine.setGlobalConfig({
            classTo: 'mb-3',
            errorClass: 'has-danger is-invalid',
            successClass: 'has-success',
            errorTextParent: 'mb-3',
            errorTextTag: 'div',
            errorTextClass: 'invalid-feedback d-block f-s-12 mt-1'
        });
    }

    // =========================================================================
    // 2. CONTROLADOR CENTRAL CAMARGO FORMS
    // =========================================================================

    const CamargoForms = {
        /**
         * Inicializa la validación y comportamiento Alina en un formulario.
         *
         * @param {HTMLFormElement} form
         * @param {Object} [opcionesCustom={}]
         * @returns {Object|null} Instancia de Pristine o null
         */
        inicializar(form, opcionesCustom = {}) {
            if (!form || !(form instanceof HTMLFormElement)) {
                return null;
            }

            if (instanciasPristine.has(form)) {
                return instanciasPristine.get(form);
            }

            // Desactivar validación nativa del navegador para permitir estilo Alina
            form.setAttribute('novalidate', 'true');

            let validador = null;
            if (typeof window.Pristine !== 'undefined') {
                const config = {
                    classTo: 'mb-3',
                    errorClass: 'has-danger is-invalid',
                    successClass: 'has-success',
                    errorTextParent: 'mb-3',
                    errorTextTag: 'div',
                    errorTextClass: 'invalid-feedback d-block f-s-12 mt-1',
                    ...opcionesCustom
                };

                validador = new window.Pristine(form, config);
                instanciasPristine.set(form, validador);
            }

            // Manejo de reset: limpiar errores y restaurar Select2 / Flatpickr
            form.addEventListener('reset', function () {
                if (validador) {
                    validador.reset();
                }
                // Limpiar clases de validación manuales
                form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                    el.classList.remove('is-invalid', 'is-valid');
                });
            });

            return validador;
        },

        /**
         * Obtiene la instancia de Pristine asociada a un formulario.
         * @param {HTMLFormElement} form
         * @returns {Object|null}
         */
        obtenerValidador(form) {
            return instanciasPristine.get(form) || null;
        },

        /**
         * Establece el estado de carga en el botón de envío del formulario.
         * Previene envíos múltiples y muestra spinner de Alina.
         *
         * @param {HTMLButtonElement|HTMLElement} boton
         * @param {string} [textoCarga='Guardando...']
         */
        establecerCargando(boton, textoCarga = 'Guardando...') {
            if (!boton) return;
            if (boton.dataset.cargando === 'true') return;

            boton.dataset.cargando = 'true';
            boton.dataset.textoOriginal = boton.innerHTML;
            boton.disabled = true;

            boton.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${textoCarga}`;
        },

        /**
         * Restaura el estado original del botón de envío.
         *
         * @param {HTMLButtonElement|HTMLElement} boton
         */
        restaurarCargando(boton) {
            if (!boton || boton.dataset.cargando !== 'true') return;

            boton.innerHTML = boton.dataset.textoOriginal || boton.innerHTML;
            boton.disabled = false;
            delete boton.dataset.cargando;
            delete boton.dataset.textoOriginal;
        },

        /**
         * Inicializa tooltips de Bootstrap / Alina de forma idempotente.
         *
         * @param {HTMLElement|Document} [contenedor=document]
         */
        inicializarTooltips(contenedor = document) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            const elementos = contenedor.querySelectorAll('[data-bs-toggle="tooltip"]');
            elementos.forEach(el => {
                try {
                    if (typeof bootstrap.Tooltip.getOrCreateInstance === 'function') {
                        bootstrap.Tooltip.getOrCreateInstance(el);
                    } else if (!bootstrap.Tooltip.getInstance(el)) {
                        new bootstrap.Tooltip(el);
                    }
                } catch (e) {
                    // Degradación silenciosa con title nativo
                }
            });
        },

        /**
         * Genera un bloque HTML de placeholder skeleton según el patrón Alina (UI-ALINA-1D).
         *
         * @param {number} [lineas=3]
         * @returns {string}
         */
        crearPlaceholder(lineas = 3) {
            let html = '<div class="placeholder-glow py-2">';
            for (let i = 0; i < lineas; i++) {
                const col = (i % 2 === 0) ? 'col-8' : 'col-5';
                html += `<span class="placeholder ${col} d-block mb-2"></span>`;
            }
            html += '</div>';
            return html;
        },

        /**
         * Escanea e inicializa formularios en el contenedor indicado.
         * @param {HTMLElement|Document} [contexto=document]
         */
        autoInicializar(contexto = document) {
            const formularios = contexto.querySelectorAll('form.app-form, form.pristine-form, form[data-pristine-validate]');
            formularios.forEach(form => {
                CamargoForms.inicializar(form);
            });
            CamargoForms.inicializarTooltips(contexto);
        }
    };

    // =========================================================================
    // 3. LISTENERS GLOBALES Y MODALES BOOTSTRAP 5
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function () {
        CamargoForms.autoInicializar(document);

        // Auto-sincronización ante apertura de modales Bootstrap 5
        document.addEventListener('shown.bs.modal', function (event) {
            const modal = event.target;
            if (modal) {
                // Re-escanear formularios dentro del modal
                CamargoForms.autoInicializar(modal);

                // Re-inicializar tooltips dentro del modal
                CamargoForms.inicializarTooltips(modal);

                // Re-inicializar selectores Select2 si CamargoSelect está disponible
                if (window.CamargoSelect && typeof window.CamargoSelect.autoInicializar === 'function') {
                    window.CamargoSelect.autoInicializar(modal);
                }

                // Re-inicializar pickers si CamargoPickers está disponible
                if (window.CamargoPickers && typeof window.CamargoPickers.autoInicializar === 'function') {
                    window.CamargoPickers.autoInicializar(modal);
                }
            }
        });
    });

    // Exposición global
    window.CamargoForms = CamargoForms;

})();
