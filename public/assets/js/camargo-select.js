/**
 * Camargo PMS — Infraestructura transversal de selectores enriquecidos Select2 (UI-3 / D-075)
 *
 * Estandariza el uso de Select2 (componente oficial de Alina).
 * Satisface:
 * - Soporte de clases nativas Alina: .basic-select2, .select-clear, .select-basic-multiple-four.
 * - Manejo dinámico de modales Bootstrap 5 (dropdownParent para evitar recortes y focus trap).
 * - Sincronización bidireccional con eventos nativos (input, change) para validación con PristineJS.
 * - Sincronización automática de opciones dinámicas mediante MutationObserver.
 * - Excepción de jQuery estrictamente confinada al plugin Select2 (0 AJAX, 0 CRUD, 0 reglas de negocio).
 */

(function () {
    'use strict';

    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
        console.warn('CamargoSelect: jQuery o Select2 no están disponibles en el entorno global.');
        return;
    }

    const $ = window.jQuery;

    /**
     * Inicializa Select2 en un elemento individual <select>.
     *
     * @param {HTMLSelectElement} elemento
     * @param {Object} opciones
     * @returns {Object|null}
     */
    function inicializarSelect(elemento, opciones = {}) {
        if (!elemento || !elemento.tagName || elemento.tagName.toLowerCase() !== 'select') {
            return null;
        }

        const $el = $(elemento);

        // Si ya está inicializado por Select2, evitar inicialización duplicada
        if ($el.hasClass('select2-hidden-accessible')) {
            return $el;
        }

        // Si el elemento está dentro de un modal de Bootstrap 5, asignar dropdownParent
        const $modal = $el.closest('.modal');
        const dropdownParent = $modal.length > 0 ? $modal : undefined;

        // Extraer placeholder
        const placeholder = elemento.getAttribute('data-placeholder') ||
                            elemento.getAttribute('placeholder') ||
                            (elemento.firstElementChild && elemento.firstElementChild.value === '' ? elemento.firstElementChild.textContent.trim() : undefined);

        // Determinar allowClear
        const allowClear = elemento.classList.contains('select-clear') ||
                           elemento.getAttribute('data-allow-clear') === 'true' ||
                           (Boolean(placeholder) && !elemento.required);

        const config = {
            width: '100%',
            dropdownParent: dropdownParent,
            placeholder: placeholder || undefined,
            allowClear: Boolean(allowClear),
            ...opciones
        };

        // Inicialización de Select2
        $el.select2(config);

        // Disparar eventos nativos para que PristineJS y validadores Vanilla JS reaccionen
        $el.on('select2:select select2:unselect select2:clear', function () {
            this.dispatchEvent(new Event('input', { bubbles: true }));
            this.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Sincronizar hacia Select2 si un script externo cambia el valor nativamente
        elemento.addEventListener('change', function (e) {
            if (!e.isTrigger) {
                $el.trigger('change.select2');
            }
        });

        // Sincronizar dinámicamente si el DOM agrega/remueve opciones vía JS
        if (window.MutationObserver) {
            const observer = new MutationObserver(function () {
                $el.trigger('change.select2');
            });
            observer.observe(elemento, { childList: true });
        }

        // Reaccionar al reseteo del formulario contenedor
        if (elemento.form) {
            elemento.form.addEventListener('reset', function () {
                setTimeout(function () {
                    $el.trigger('change.select2');
                }, 20);
            });
        }

        return $el;
    }

    /**
     * Inicializa todos los selects con clases oficiales Alina dentro de un contenedor.
     *
     * @param {HTMLElement|Document} contenedor
     */
    function inicializarTodos(contenedor = document) {
        if (!contenedor) return;

        const selector = 'select.basic-select2, select.select-clear, select.select-basic-multiple-four, select[data-select2="true"]';
        const elementos = contenedor.querySelectorAll(selector);

        elementos.forEach(function (el) {
            inicializarSelect(el);
        });
    }

    /**
     * Destruye y reinicializa Select2 en el contenedor dado.
     *
     * @param {HTMLElement|Document} contenedor
     */
    function reinicializar(contenedor = document) {
        if (!contenedor) return;

        const selector = 'select.basic-select2, select.select-clear, select.select-basic-multiple-four, select[data-select2="true"]';
        const elementos = contenedor.querySelectorAll(selector);

        elementos.forEach(function (el) {
            const $el = $(el);
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }
            inicializarSelect(el);
        });
    }

    /**
     * Asigna un valor a un select y actualiza Select2 y eventos nativos de forma unificada.
     *
     * @param {HTMLSelectElement|string} elementoOSelector
     * @param {string|string[]} valor
     */
    function asignarValor(elementoOSelector, valor) {
        const el = typeof elementoOSelector === 'string'
            ? document.querySelector(elementoOSelector)
            : elementoOSelector;

        if (!el) return;

        $(el).val(valor).trigger('change.select2');
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Auto-inicialización en carga de DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            inicializarTodos(document);
        });
    } else {
        inicializarTodos(document);
    }

    // Compatibilidad reactiva con Modales Bootstrap 5
    document.addEventListener('shown.bs.modal', function (e) {
        const modal = e.target;
        inicializarTodos(modal);
        // Garantizar que el ancho sea 100% al desplegarse
        $(modal).find('.select2-container').css('width', '100%');
    });

    // Exponer API global unificada
    window.CamargoSelect = {
        init: inicializarTodos,
        initElement: inicializarSelect,
        reinit: reinicializar,
        setValue: asignarValor
    };

})();
