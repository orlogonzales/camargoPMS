/**
 * Camargo PMS — Controlador Frontend de Analítica Gerencial y Rendimiento por Canal
 * Microfase: REPORTES-1B / Gobernanza: D-110 / D-087
 *
 * PRINCIPIO VINCULANTE:
 * ReporteServicio (Backend) es la ÚNICA autoridad analítica.
 * Este script NO recalcula Ocupación, ADR, RevPAR, ingresos ni métricas de canal.
 * Se limita exclusivamente a orquestar la llamada asíncrona, renderizar las series
 * generadas por el backend en ApexCharts y poblar las tablas del DOM Alina.
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Instancias de ApexCharts
    let chartTemporal = null;
    let chartCanales = null;

    // Elementos del DOM
    const formFiltros = document.getElementById('form-filtros-analitica');
    const inputFechaDesde = document.getElementById('filtro-analitica-desde');
    const inputFechaHasta = document.getElementById('filtro-analitica-hasta');
    const selectPropiedad = document.getElementById('filtro-analitica-propiedad');
    const btnActualizar = document.getElementById('btn-actualizar-analitica');
    const btnLimpiar = document.getElementById('btn-limpiar-filtros-analitica');
    const btnExportarCsv = document.getElementById('btn-exportar-csv');

    // Inicializar con datos precargados si están disponibles en ventana
    if (window.__DATOS_ANALITICA__) {
        renderizarTodo(window.__DATOS_ANALITICA__);
    } else {
        cargarAnalitica();
    }

    // Eventos de formulario y botones
    if (formFiltros) {
        formFiltros.addEventListener('submit', function (e) {
            e.preventDefault();
            cargarAnalitica();
        });
    }

    if (btnActualizar) {
        btnActualizar.addEventListener('click', function () {
            cargarAnalitica();
        });
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            const hoy = new Date();
            const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

            const formatYmd = d => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const fDesde = formatYmd(primerDia);
            const fHasta = formatYmd(hoy);

            inputFechaDesde.value = fDesde;
            inputFechaHasta.value = fHasta;

            const inputRango = document.getElementById('filtro-rango-analitica');
            if (inputRango) {
                inputRango.value = `${fDesde} a ${fHasta}`;
                if (inputRango._flatpickr) {
                    inputRango._flatpickr.setDate([fDesde, fHasta], true);
                }
            }

            selectPropiedad.value = '';
            cargarAnalitica();
        });
    }

    /**
     * Consulta asíncrona al endpoint oficial backend (GET /reportes/analitica/datos).
     */
    function cargarAnalitica() {
        const fechaDesde = inputFechaDesde ? inputFechaDesde.value : '';
        const fechaHasta = inputFechaHasta ? inputFechaHasta.value : '';
        const propiedadId = selectPropiedad ? selectPropiedad.value : '';

        // Actualizar URL del botón de exportación CSV con los filtros actuales
        if (btnExportarCsv) {
            let exportUrl = `/reportes/analitica/exportar/csv?fecha_desde=${encodeURIComponent(fechaDesde)}&fecha_hasta=${encodeURIComponent(fechaHasta)}`;
            if (propiedadId) {
                exportUrl += `&propiedad_id=${encodeURIComponent(propiedadId)}`;
            }
            btnExportarCsv.setAttribute('href', exportUrl);
        }

        const params = new URLSearchParams();
        if (fechaDesde) params.append('fecha_desde', fechaDesde);
        if (fechaHasta) params.append('fecha_hasta', fechaHasta);
        if (propiedadId) params.append('propiedad_id', propiedadId);

        mostrarIndicadorCarga(true);

        fetch(`/reportes/analitica/datos?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error en respuesta analítica: HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            mostrarIndicadorCarga(false);
            if (data.ok && data.datos) {
                renderizarTodo(data.datos);
            } else {
                mostrarError(data.mensaje || 'Error al recuperar los datos del reporte analítico.');
            }
        })
        .catch(error => {
            mostrarIndicadorCarga(false);
            console.error('Error al cargar analítica:', error);
            mostrarError(`Fallo de comunicación analítica: ${error.message}`);
        });
    }

    /**
     * Orquesta la representación gráfica y tabular consumiendo los DTOs del backend.
     * CERO recálculo matemático de fórmulas.
     *
     * @param {Object} datos Estructura serializada de ReporteAnaliticaDTO
     */
    function renderizarTodo(datos) {
        if (!datos) return;

        poblarKpis(datos.kpis, datos.desglose_ingresos_devengados, datos.desglose_ingresos_percibidos);
        renderizarGraficoSerieTemporal(datos.serie_temporal);
        renderizarGraficoCanales(datos.rendimiento_canales);
        poblarTablaCanales(datos.rendimiento_canales);
        poblarTablasDesgloseDual(datos.desglose_ingresos_devengados, datos.desglose_ingresos_percibidos);
        poblarTablaSerieTemporal(datos.serie_temporal);
    }

    /**
     * Actualiza los 5 KPIs consolidados superiores del panel.
     */
    function poblarKpis(k, dev, perc) {
        if (!k) return;

        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        setTxt('kpi-ocupacion', (parseFloat(k.ocupacion_media_porcentaje) || 0).toFixed(2) + '%');
        setTxt('kpi-noches-ocupadas', k.total_noches_ocupadas || 0);
        setTxt('kpi-noches-disponibles', k.total_noches_disponibles || 0);

        setTxt('kpi-adr', 'S/ ' + (k.adr_promedio || '0.00'));
        setTxt('kpi-revpar', 'S/ ' + (k.revpar_promedio || '0.00'));
        setTxt('kpi-trevpar', 'S/ ' + (k.trevpar_promedio || '0.00'));

        if (dev) {
            setTxt('kpi-total-devengado', 'S/ ' + (dev.total_devengado || '0.00'));
        }

        if (perc) {
            setTxt('kpi-brecha', 'S/ ' + (perc.brecha_recaudacion || '0.00'));
            setTxt('kpi-total-percibido', 'S/ ' + (perc.total_percibido || '0.00'));
        }
    }

    /**
     * Renderiza o actualiza el gráfico multieje de serie temporal en ApexCharts.
     * Eje Y1 (Izquierdo): ADR y RevPAR en PEN (moneda).
     * Eje Y2 (Derecho): Ocupación en Porcentaje (%).
     */
    function renderizarGraficoSerieTemporal(serie) {
        const contenedor = document.getElementById('chart-serie-temporal');
        if (!contenedor || typeof ApexCharts === 'undefined') return;

        const categorias = [];
        const dataOcupacion = [];
        const dataAdr = [];
        const dataRevpar = [];

        if (Array.isArray(serie)) {
            serie.forEach(p => {
                categorias.push(p.fecha);
                dataOcupacion.push(parseFloat(p.ocupacion_porcentaje) || 0);
                dataAdr.push(parseFloat(p.adr) || 0);
                dataRevpar.push(parseFloat(p.revpar) || 0);
            });
        }

        const opciones = {
            chart: {
                type: 'line',
                height: 330,
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'inherit'
            },
            stroke: {
                width: [2, 3, 3],
                curve: 'smooth',
                dashArray: [0, 0, 0]
            },
            colors: ['#0d6efd', '#198754', '#0dcaf0'],
            series: [
                {
                    name: 'Ocupación (%)',
                    type: 'area',
                    data: dataOcupacion
                },
                {
                    name: 'ADR (S/)',
                    type: 'line',
                    data: dataAdr
                },
                {
                    name: 'RevPAR (S/)',
                    type: 'line',
                    data: dataRevpar
                }
            ],
            fill: {
                type: ['gradient', 'solid', 'solid'],
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.1,
                    opacityFrom: 0.25,
                    opacityTo: 0.05
                }
            },
            xaxis: {
                categories: categorias,
                labels: {
                    rotate: -45,
                    style: { fontSize: '11px' }
                }
            },
            yaxis: [
                {
                    title: { text: 'Ocupación (%)' },
                    min: 0,
                    max: 100,
                    labels: {
                        formatter: val => (val || 0).toFixed(0) + '%'
                    }
                },
                {
                    opposite: true,
                    title: { text: 'Tarifa & Rendimiento (PEN)' },
                    labels: {
                        formatter: val => 'S/ ' + (val || 0).toFixed(0)
                    }
                },
                {
                    opposite: true,
                    show: false
                }
            ],
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val, { seriesIndex }) {
                        if (seriesIndex === 0) {
                            return (val || 0).toFixed(2) + '%';
                        }
                        return 'S/ ' + (val || 0).toFixed(2);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right'
            },
            grid: {
                borderColor: '#e9ecef',
                strokeDashArray: 4
            }
        };

        if (chartTemporal) {
            chartTemporal.updateOptions({
                xaxis: { categories: categorias }
            });
            chartTemporal.updateSeries(opciones.series);
        } else {
            contenedor.innerHTML = '';
            chartTemporal = new ApexCharts(contenedor, opciones);
            chartTemporal.render();
        }
    }

    /**
     * Renderiza o actualiza el gráfico de dona de distribución por canal en ApexCharts.
     * Solo considera canales comerciales demostrables para la cuota económica.
     */
    function renderizarGraficoCanales(canales) {
        const contenedor = document.getElementById('chart-distribucion-canales');
        if (!contenedor || typeof ApexCharts === 'undefined') return;

        const etiquetas = [];
        const series = [];

        if (Array.isArray(canales)) {
            canales.forEach(c => {
                if (c.es_produccion_demostrable) {
                    const ingreso = parseFloat(c.ingresos_totales) || 0;
                    if (ingreso > 0) {
                        etiquetas.push(c.nombre_canal);
                        series.push(ingreso);
                    }
                }
            });
        }

        // Si no hay ingresos comerciales en el periodo
        if (series.length === 0) {
            contenedor.innerHTML = '<div class="d-flex-center h-100 text-muted f-s-13 py-5"><i class="fa-solid fa-chart-pie me-2"></i> Sin producción comercial en el periodo</div>';
            chartCanales = null;
            return;
        }

        const opciones = {
            chart: {
                type: 'donut',
                height: 330,
                fontFamily: 'inherit'
            },
            labels: etiquetas,
            series: series,
            colors: ['#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#6c757d'],
            dataLabels: {
                enabled: true,
                formatter: val => (val || 0).toFixed(1) + '%'
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            },
            tooltip: {
                y: {
                    formatter: val => 'S/ ' + (val || 0).toFixed(2)
                }
            }
        };

        if (chartCanales) {
            chartCanales.updateOptions(opciones);
        } else {
            contenedor.innerHTML = '';
            chartCanales = new ApexCharts(contenedor, opciones);
            chartCanales.render();
        }
    }

    /**
     * Rellena la tabla de canales distinguiendo producción demostrable vs feeds iCal.
     */
    function poblarTablaCanales(canales) {
        const tbody = document.getElementById('tbody-canales');
        const badgeTotal = document.getElementById('badge-total-canales');
        if (!tbody) return;

        if (badgeTotal) {
            badgeTotal.textContent = `${(canales || []).length} canales detectados`;
        }

        if (!Array.isArray(canales) || canales.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-muted"><i class="fa-solid fa-inbox f-s-24 d-block mb-2 text-secondary"></i>No se registran movimientos ni eventos de distribución en el periodo consultado.</td></tr>';
            return;
        }

        let html = '';
        canales.forEach(c => {
            const esDemostrable = Boolean(c.es_produccion_demostrable);

            const badgeClasificacion = esDemostrable
                ? '<span class="badge bg-light-success text-success f-s-11"><i class="fa-solid fa-circle-check me-1"></i> Producción Demostrable</span>'
                : '<span class="badge bg-light-warning text-warning f-s-11"><i class="fa-solid fa-calendar-xmark me-1"></i> Bloqueo iCal Externo</span>';

            const nochesVendidas = esDemostrable ? c.noches_vendidas : '—';
            const cuotaNoches = esDemostrable ? (parseFloat(c.cuota_noches_porcentaje) || 0).toFixed(2) + '%' : '—';
            const ingresos = esDemostrable ? 'S/ ' + c.ingresos_totales : 'S/ 0.00';
            const cuotaIngresos = esDemostrable ? (parseFloat(c.cuota_ingresos_porcentaje) || 0).toFixed(2) + '%' : '—';
            const adrTexto = esDemostrable ? 'S/ ' + c.adr_medio : '—';
            const alos = esDemostrable ? (parseFloat(c.alos_noches) || 0).toFixed(1) + ' n' : '—';
            const leadTime = esDemostrable ? (parseFloat(c.lead_time_dias) || 0).toFixed(1) + ' d' : '—';
            const tasaCanc = esDemostrable ? (parseFloat(c.tasa_cancelacion_porcentaje) || 0).toFixed(1) + '%' : '—';
            const nochesIcal = (c.noches_bloqueadas_ical && c.noches_bloqueadas_ical > 0)
                ? `<span class="badge bg-light-secondary text-dark f-s-11">${c.noches_bloqueadas_ical} noches</span>`
                : '—';

            const cancTxt = c.reservas_canceladas > 0 ? `<span class="text-danger f-s-11 d-block">(${c.reservas_canceladas} canc.)</span>` : '';

            html += `<tr>
                <td class="px-3">
                    <span class="f-w-700 d-block">${escapeHtml(c.nombre_canal)}</span>
                    <span class="text-muted f-s-11">${escapeHtml(c.codigo_canal)}</span>
                </td>
                <td class="px-3">${badgeClasificacion}</td>
                <td class="text-center"><strong>${c.reservas_totales}</strong>${cancTxt}</td>
                <td class="text-center f-w-600">${nochesVendidas}</td>
                <td class="text-center">${cuotaNoches}</td>
                <td class="text-end f-w-700 ${esDemostrable ? 'text-success' : 'text-muted'}">${ingresos}</td>
                <td class="text-center">${cuotaIngresos}</td>
                <td class="text-end f-w-600">${adrTexto}</td>
                <td class="text-center">${alos}</td>
                <td class="text-center">${leadTime}</td>
                <td class="text-center">${tasaCanc}</td>
                <td class="text-center">${nochesIcal}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    /**
     * Rellena las tablas de desglose contable dual (Devengado vs Percibido).
     */
    function poblarTablasDesgloseDual(dev, perc) {
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = 'S/ ' + (val || '0.00');
        };

        if (dev) {
            setVal('dev-alojamiento-neto', dev.alojamiento_neto);
            setVal('dev-alojamiento-impuestos', dev.alojamiento_impuestos);
            setVal('dev-servicios-extras', dev.servicios_extras);
            setVal('dev-arrendamientos', dev.arrendamientos);
            setVal('dev-suministros', dev.suministros_consumos);
            setVal('dev-penalidades', dev.penalidades);
            setVal('dev-total-devengado', dev.total_devengado);
        }

        if (perc) {
            setVal('perc-efectivo', perc.efectivo_caja);
            setVal('perc-transferencias', perc.transferencias_banco);
            setVal('perc-pos', perc.tarjetas_pos);
            setVal('perc-pasarelas', perc.pasarelas_neto);
            setVal('perc-total-percibido', perc.total_percibido);
            setVal('perc-brecha', perc.brecha_recaudacion);
        }
    }

    /**
     * Rellena la tabla detallada de serie temporal día por día.
     */
    function poblarTablaSerieTemporal(serie) {
        const tbody = document.getElementById('tbody-serie-temporal');
        const badgeDias = document.getElementById('badge-total-dias');
        if (!tbody) return;

        if (badgeDias) {
            badgeDias.textContent = `${(serie || []).length} días en periodo`;
        }

        if (!Array.isArray(serie) || serie.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-muted">No hay datos diarios disponibles para el rango consultado.</td></tr>';
            return;
        }

        let html = '';
        serie.forEach(p => {
            const badgeAudit = p.es_auditado
                ? '<span class="badge bg-light-success text-success f-s-10"><i class="fa-solid fa-stamp me-1"></i> Night Audit</span>'
                : '<span class="badge bg-light-secondary text-muted f-s-10"><i class="fa-solid fa-clock me-1"></i> En Curso</span>';

            html += `<tr>
                <td class="px-3 f-w-600">${escapeHtml(p.fecha)}</td>
                <td class="text-center">${p.unidades_totales}</td>
                <td class="text-center text-muted">${p.unidades_ooo}</td>
                <td class="text-center f-w-600 text-primary">${p.unidades_vendibles}</td>
                <td class="text-center">${p.unidades_ocupadas}</td>
                <td class="text-center f-w-600 text-success">${p.habitaciones_vendidas}</td>
                <td class="text-center text-muted">${p.habitaciones_cortesia}</td>
                <td class="text-center f-w-700">${(parseFloat(p.ocupacion_porcentaje) || 0).toFixed(2)}%</td>
                <td class="text-end f-w-600">S/ ${p.adr}</td>
                <td class="text-end f-w-600">S/ ${p.revpar}</td>
                <td class="text-end f-w-700 text-success">S/ ${p.ingreso_alojamiento_neto}</td>
                <td class="text-center">${badgeAudit}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    /**
     * Muestra u oculta indicadores visuales de carga durante el fetch.
     */
    function mostrarIndicadorCarga(cargando) {
        if (btnActualizar) {
            btnActualizar.disabled = cargando;
            const icon = btnActualizar.querySelector('i');
            if (icon) {
                if (cargando) icon.classList.add('fa-spin');
                else icon.classList.remove('fa-spin');
            }
        }
    }

    /**
     * Muestra un mensaje de error utilizando la interfaz disponible.
     */
    function mostrarError(mensaje) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error Analítico',
                text: mensaje,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            alert(mensaje);
        }
    }

    /**
     * Sanitiza cadenas HTML para prevenir XSS.
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
});
