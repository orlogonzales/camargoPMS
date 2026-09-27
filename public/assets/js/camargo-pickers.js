/**
 * Camargo PMS — Infraestructura transversal de selectores de fecha y hora (UI-2 / D-071)
 *
 * Estandariza el uso de Flatpickr (componente oficial de Alina) en Vanilla JS puro (0 jQuery).
 * Satisface:
 * - Date Picker de Alina: obligatorio para fecha individual.
 * - Range Picker de Alina: obligatorio para intervalos (fecha_entrada -> fecha_salida).
 * - Time Picker & Date-Time Picker de Alina para dominios temporales específicos.
 * - Preservación de D-066: fechas canónicas en formato ISO 'YYYY-MM-DD'.
 */

(function () {
    'use strict';

    if (typeof flatpickr === 'undefined') {
        console.warn('CamargoPickers: Flatpickr no está cargado en el entorno.');
        return;
    }

    const configuracionBaseAlina = {
        disableMobile: true,
        appendTo: document.body,
        position: 'auto',
        prevArrow: '<i class="fa-solid fa-chevron-left"></i>',
        nextArrow: '<i class="fa-solid fa-chevron-right"></i>',
        monthSelectorType: 'dropdown'
    };

    /**
     * Inicializa un Date Picker individual en un elemento.
     * @param {HTMLInputElement} elemento
     * @param {Object} opciones
     * @returns {Object} Instancia de Flatpickr
     */
    function inicializarDatePicker(elemento, opciones = {}) {
        if (!elemento || elemento._flatpickr) {
            return elemento?._flatpickr || null;
        }

        const config = {
            ...configuracionBaseAlina,
            dateFormat: 'Y-m-d',
            ...opciones
        };

        const targetSelector = elemento.getAttribute('data-target-fecha');
        if (targetSelector) {
            const targetInput = document.querySelector(targetSelector);
            if (targetInput && targetInput.value) {
                config.defaultDate = targetInput.value;
            }
            config.onChange = function (selectedDates, dateStr) {
                if (targetInput) {
                    targetInput.value = dateStr;
                    targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                    targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (typeof opciones.onChange === 'function') {
                    opciones.onChange(selectedDates, dateStr);
                }
            };
        }

        return flatpickr(elemento, config);
    }

    /**
     * Inicializa un Range Picker de Alina en un elemento.
     * Vincula automáticamente fecha de inicio y fecha de fin hacia campos canónicos.
     *
     * @param {HTMLInputElement} elemento
     * @param {Object} opciones
     * @returns {Object} Instancia de Flatpickr
     */
    function inicializarRangePicker(elemento, opciones = {}) {
        if (!elemento || elemento._flatpickr) {
            return elemento?._flatpickr || null;
        }

        const targetInicioSel = elemento.getAttribute('data-target-inicio');
        const targetFinSel = elemento.getAttribute('data-target-fin');
        const targetNochesSel = elemento.getAttribute('data-target-noches');

        const inputInicio = targetInicioSel ? document.querySelector(targetInicioSel) : null;
        const inputFin = targetFinSel ? document.querySelector(targetFinSel) : null;
        const targetNoches = targetNochesSel ? document.querySelector(targetNochesSel) : null;

        const config = {
            ...configuracionBaseAlina,
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: {
                rangeSeparator: '  →  '
            },
            ...opciones
        };

        // Si los inputs objetivos tienen fechas iniciales, cargarlas en el picker
        if (inputInicio && inputFin && inputInicio.value && inputFin.value) {
            config.defaultDate = [inputInicio.value, inputFin.value];
        }

        config.onChange = function (selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                const fInicio = instance.formatDate(selectedDates[0], 'Y-m-d');
                const fFin = instance.formatDate(selectedDates[1], 'Y-m-d');

                if (inputInicio) {
                    inputInicio.value = fInicio;
                    inputInicio.dispatchEvent(new Event('input', { bubbles: true }));
                    inputInicio.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (inputFin) {
                    inputFin.value = fFin;
                    inputFin.dispatchEvent(new Event('input', { bubbles: true }));
                    inputFin.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (targetNoches) {
                    const diffMs = selectedDates[1].getTime() - selectedDates[0].getTime();
                    const noches = Math.max(0, Math.round(diffMs / (1000 * 60 * 60 * 24)));
                    if (targetNoches.tagName === 'INPUT') {
                        targetNoches.value = noches.toString();
                    } else {
                        targetNoches.textContent = noches.toString();
                    }
                    targetNoches.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else if (selectedDates.length === 0) {
                if (inputInicio) {
                    inputInicio.value = '';
                    inputInicio.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (inputFin) {
                    inputFin.value = '';
                    inputFin.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (targetNoches) {
                    if (targetNoches.tagName === 'INPUT') {
                        targetNoches.value = '0';
                    } else {
                        targetNoches.textContent = '0';
                    }
                }
            }

            if (typeof opciones.onChange === 'function') {
                opciones.onChange(selectedDates, dateStr, instance);
            }
        };

        const fp = flatpickr(elemento, config);

        // Helper para establecer rango programáticamente
        fp.establecerRango = function (inicio, fin) {
            if (inicio && fin) {
                fp.setDate([inicio, fin], true);
            } else {
                fp.clear();
            }
        };

        return fp;
    }

    /**
     * Inicializa selectores de hora en un elemento.
     */
    function inicializarTimePicker(elemento, opciones = {}) {
        if (!elemento || elemento._flatpickr) {
            return elemento?._flatpickr || null;
        }

        return flatpickr(elemento, {
            ...configuracionBaseAlina,
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            ...opciones
        });
    }

    /**
     * Inicializa selectores de fecha y hora en un elemento.
     */
    function inicializarDateTimePicker(elemento, opciones = {}) {
        if (!elemento || elemento._flatpickr) {
            return elemento?._flatpickr || null;
        }

        return flatpickr(elemento, {
            ...configuracionBaseAlina,
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            ...opciones
        });
    }

    /**
     * Escanea el contenedor indicado y activa selectores por atributos de datos o clases.
     * @param {HTMLElement|Document} contexto
     */
    function autoInicializar(contexto = document) {
        // 1. Range Pickers
        const rangeElements = contexto.querySelectorAll('[data-provider="rangepicker"], .picker-range, .campo-rangepicker');
        rangeElements.forEach(el => inicializarRangePicker(el));

        // 2. Date Pickers individuales
        const dateElements = contexto.querySelectorAll('[data-provider="datepicker"], .basic-date, .campo-datepicker');
        dateElements.forEach(el => inicializarDatePicker(el));

        // 3. Time Pickers
        const timeElements = contexto.querySelectorAll('[data-provider="timepicker"], .time-picker, .campo-timepicker');
        timeElements.forEach(el => inicializarTimePicker(el));

        // 4. Date & Time Pickers
        const dateTimeElements = contexto.querySelectorAll('[data-provider="datetimepicker"], .date-time-picker, .campo-datetimepicker');
        dateTimeElements.forEach(el => inicializarDateTimePicker(el));
    }

    // Exponer API global limpia
    window.CamargoPickers = {
        inicializar: autoInicializar,
        datePicker: inicializarDatePicker,
        rangePicker: inicializarRangePicker,
        timePicker: inicializarTimePicker,
        dateTimePicker: inicializarDateTimePicker
    };

    // Auto-inicializar al cargar el DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => autoInicializar());
    } else {
        autoInicializar();
    }

    // Auto-inicializar cuando se abra cualquier modal de Bootstrap
    document.addEventListener('shown.bs.modal', function (event) {
        autoInicializar(event.target);
    });

})();
