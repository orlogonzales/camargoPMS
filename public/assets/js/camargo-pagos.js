/**
 * Camargo PMS — Monitor de Pasarelas de Pago, Conciliación y Reembolsos (PAGOS-1D).
 *
 * Módulo JavaScript Soberano:
 * - Alina Design System + Bootstrap 5 Modal & Offcanvas nativos.
 * - SweetAlert2 exclusivo para diálogos de confirmación y alertas (0 alert/confirm).
 * - Fetch nativo + JSON para comunicación asíncrona (0 jQuery AJAX).
 * - Protección radical Zero-Trust: ni llaves privadas ni PAN/CVV en el DOM.
 * - Invariante C1/C2 inviolable: pagos tardíos gestionados sin reasignación ni mutación de inventario.
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // =========================================================================
    // 1. CONSTANTES, ELEMENTOS DEL DOM Y ESTADO
    // =========================================================================

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';
    const formFiltros = document.getElementById('form-filtros-pagos');
    const tbodyTransacciones = document.getElementById('tbody-transacciones');
    const btnRecargar = document.getElementById('btn-recargar-pagos');
    const btnLimpiarFiltros = document.getElementById('btn-limpiar-filtros');
    const contenedorPaginacion = document.getElementById('contenedor-paginacion');
    const infoPaginacion = document.getElementById('info-paginacion');

    // KPIs
    const kpiMontoAprobado = document.getElementById('kpi-monto-aprobado');
    const kpiConteoAprobado = document.getElementById('kpi-conteo-aprobado');
    const kpiDiscrepancias = document.getElementById('kpi-discrepancias');
    const kpiHoldExpirado = document.getElementById('kpi-hold-expirado');
    const kpiMontoReembolsado = document.getElementById('kpi-monto-reembolsado');
    const kpiReembolsosPendientes = document.getElementById('kpi-reembolsos-pendientes');
    const kpiTotalTransacciones = document.getElementById('kpi-total-transacciones');
    const kpiConteoPendientes = document.getElementById('kpi-conteo-pendientes');

    // Modal Reembolso
    const modalReembolsoEl = document.getElementById('modal-reembolso');
    const modalReembolso = modalReembolsoEl ? new bootstrap.Modal(modalReembolsoEl) : null;
    const formReembolso = document.getElementById('form-reembolso-pasarela');
    const btnSubmitReembolso = document.getElementById('btn-submit-reembolso');
    const inputReembolsoTxId = document.getElementById('reembolso-transaccion-id');
    const lblReembolsoSubtitulo = document.getElementById('reembolso-subtitulo');
    const lblReembolsoCobrado = document.getElementById('reembolso-info-cobrado');
    const lblReembolsoYaDevuelto = document.getElementById('reembolso-info-reembolsado');
    const lblReembolsoDisponible = document.getElementById('reembolso-info-disponible');
    const radioReembolsoTotal = document.getElementById('reembolso-tipo-total');
    const radioReembolsoParcial = document.getElementById('reembolso-tipo-parcial');
    const contenedorMontoParcial = document.getElementById('contenedor-monto-parcial');
    const inputMontoParcial = document.getElementById('reembolso-monto');
    const txtReembolsoMotivo = document.getElementById('reembolso-motivo');

    // Offcanvas Webhook
    const offcanvasWebhookEl = document.getElementById('offcanvas-webhook-payload');
    const offcanvasWebhook = offcanvasWebhookEl ? new bootstrap.Offcanvas(offcanvasWebhookEl) : null;
    const webhookLoading = document.getElementById('webhook-loading');
    const webhookContenido = document.getElementById('webhook-contenido');
    const webhookSubtitulo = document.getElementById('webhook-offcanvas-subtitulo');
    const webhookMetaProveedor = document.getElementById('webhook-meta-proveedor');
    const webhookMetaTipo = document.getElementById('webhook-meta-tipo');
    const webhookMetaEventoId = document.getElementById('webhook-meta-evento-id');
    const webhookMetaHttpCode = document.getElementById('webhook-meta-http-code');
    const webhookMetaRecibido = document.getElementById('webhook-meta-recibido');
    const webhookMetaIp = document.getElementById('webhook-meta-ip');
    const webhookContenedorError = document.getElementById('webhook-contenedor-error');
    const webhookErrorTexto = document.getElementById('webhook-error-texto');
    const webhookPayloadJson = document.getElementById('webhook-payload-json');
    const btnCopiarPayload = document.getElementById('btn-copiar-payload');

    // Modal Observación Seguimiento (en Detalle)
    const modalObservacionEl = document.getElementById('modal-observacion-seguimiento');
    const modalObservacion = modalObservacionEl ? new bootstrap.Modal(modalObservacionEl) : null;
    const formObservacion = document.getElementById('form-observacion-seguimiento');
    const btnAbrirObservacion = document.getElementById('btn-observacion-seguimiento');

    let paginaActual = 1;
    let maxSaldoDisponible = 0.00;

    // =========================================================================
    // 2. UTILIDADES Y FORMATEO
    // =========================================================================

    function formatearDinero(monto) {
        const num = parseFloat(monto || 0);
        return 'S/ ' + num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escaparHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function obtenerBadgeEstadoPago(estado) {
        const norm = (estado || '').toUpperCase();
        switch (norm) {
            case 'APROBADO':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-circle-check me-1"></i> Aprobado</span>';
            case 'PROCESANDO':
                return '<span class="badge bg-light-info text-info"><i class="fa-solid fa-spinner fa-spin me-1"></i> Procesando</span>';
            case 'PENDIENTE':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> Pendiente</span>';
            case 'FALLIDO':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-circle-xmark me-1"></i> Fallido</span>';
            case 'EXPIRADO':
                return '<span class="badge bg-light-secondary text-secondary"><i class="fa-solid fa-hourglass-end me-1"></i> Expirado</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function obtenerBadgeEstadoConciliacion(estado, subtipo) {
        if (subtipo === 'DISCREPANCIA_HOLD_EXPIRADO') {
            return '<span class="badge bg-light-danger text-danger border border-danger" title="Pago recibido con reserva expirada">' +
                   '<i class="fa-solid fa-triangle-exclamation me-1"></i> PAGO RECIBIDO / RES. EXPIRADA</span>';
        }
        const norm = (estado || '').toUpperCase();
        switch (norm) {
            case 'CONCILIADO':
                return '<span class="badge bg-light-success text-success"><i class="fa-solid fa-check-double me-1"></i> Conciliado</span>';
            case 'PENDIENTE':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> Pendiente</span>';
            case 'DISCREPANCIA':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Discrepancia</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    function obtenerBadgeEstadoReembolso(estado) {
        const norm = (estado || '').toUpperCase();
        switch (norm) {
            case 'NO_APLICA':
                return '<span class="badge bg-light-secondary text-muted">Sin Reembolso</span>';
            case 'PENDIENTE':
                return '<span class="badge bg-light-warning text-warning"><i class="fa-solid fa-clock me-1"></i> Reemb. Pendiente</span>';
            case 'PROCESANDO':
                return '<span class="badge bg-light-info text-info"><i class="fa-solid fa-spinner fa-spin me-1"></i> Reemb. Procesando</span>';
            case 'REEMBOLSADO_TOTAL':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-arrow-rotate-left me-1"></i> Reembolsado Total</span>';
            case 'REEMBOLSADO_PARCIAL':
                return '<span class="badge bg-light-primary text-primary"><i class="fa-solid fa-arrow-rotate-left me-1"></i> Reembolsado Parcial</span>';
            case 'FALLIDO':
                return '<span class="badge bg-light-danger text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Reemb. Fallido</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary">${escaparHtml(estado)}</span>`;
        }
    }

    // =========================================================================
    // 3. CARGA DINÁMICA DE TRANSACCIONES Y KPIS (GET /pagos/datos)
    // =========================================================================

    async function cargarDatos(pagina = 1) {
        if (!tbodyTransacciones) return;

        paginaActual = pagina;
        tbodyTransacciones.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                    <span class="text-secondary f-s-13">Actualizando transacciones...</span>
                </td>
            </tr>
        `;

        const params = new URLSearchParams();
        params.append('pagina', pagina.toString());
        params.append('por_pagina', '20');

        if (formFiltros) {
            const formData = new FormData(formFiltros);
            for (const [key, value] of formData.entries()) {
                if (value && typeof value === 'string' && value.trim() !== '') {
                    params.append(key, value.trim());
                }
            }
        }

        try {
            const res = await fetch(`/pagos/datos?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) {
                throw new Error(`Error ${res.status}: No fue posible cargar el listado.`);
            }

            const data = await res.json();
            const payload = data.datos || data;
            actualizarKpis(payload.kpis);
            renderizarTabla(payload.transacciones);
            renderizarPaginacion(payload.paginacion);
        } catch (err) {
            console.error('Error cargando transacciones de pagos:', err);
            tbodyTransacciones.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger f-s-13">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        ${escaparHtml(err.message || 'Error al comunicar con el servidor.')}
                    </td>
                </tr>
            `;
        }
    }

    function actualizarKpis(kpis) {
        if (!kpis) return;
        if (kpiMontoAprobado) kpiMontoAprobado.textContent = formatearDinero(kpis.monto_aprobado);
        if (kpiConteoAprobado) kpiConteoAprobado.textContent = kpis.conteo_aprobado ?? 0;
        if (kpiDiscrepancias) {
            const discTotal = (parseInt(kpis.discrepancias_hold_expirado || 0, 10)) + (parseInt(kpis.discrepancias_monto || 0, 10));
            kpiDiscrepancias.textContent = discTotal;
        }
        if (kpiHoldExpirado) kpiHoldExpirado.textContent = kpis.discrepancias_hold_expirado ?? 0;
        if (kpiMontoReembolsado) kpiMontoReembolsado.textContent = formatearDinero(kpis.monto_reembolsado);
        if (kpiReembolsosPendientes) kpiReembolsosPendientes.textContent = kpis.conteo_reembolsos_pendientes ?? 0;
        if (kpiTotalTransacciones) kpiTotalTransacciones.textContent = kpis.total_transacciones ?? 0;
        if (kpiConteoPendientes) kpiConteoPendientes.textContent = kpis.conteo_pendientes_conciliacion ?? 0;
    }

    function renderizarTabla(transacciones) {
        if (!tbodyTransacciones) return;

        if (!transacciones || transacciones.length === 0) {
            tbodyTransacciones.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-secondary">
                        <i class="fa-solid fa-inbox f-s-24 d-block mb-2 text-muted"></i>
                        No se encontraron transacciones registradas con los filtros seleccionados.
                    </td>
                </tr>
            `;
            return;
        }

        const fragmento = document.createDocumentFragment();

        transacciones.forEach(tx => {
            const tr = document.createElement('tr');
            tr.dataset.id = tx.id;
            const esTardio = tx.subtipo_discrepancia === 'DISCREPANCIA_HOLD_EXPIRADO';
            if (esTardio) tr.className = 'table-warning';

            const esAprobado = tx.estado_pago === 'APROBADO';
            const saldoDisp = parseFloat(tx.saldo_reembolsable || 0);
            const permiteReembolso = esAprobado && saldoDisp > 0.00;

            const montoReemb = parseFloat(tx.monto_reembolsado || 0);
            const htmlMontoReemb = montoReemb > 0
                ? `<span class="text-danger f-s-11 d-block">-${formatearDinero(montoReemb)}</span>`
                : '';

            const fechaStr = (tx.fecha_creacion || '').slice(0, 10);
            const horaStr = (tx.fecha_creacion || '').slice(11, 19);

            const htmlReserva = tx.reserva_codigo
                ? `<a href="/reservas/${encodeURIComponent(tx.reserva_id)}" class="badge bg-light-secondary text-dark text-decoration-none mb-1">
                       <i class="fa-solid fa-bookmark me-1 text-primary"></i> ${escaparHtml(tx.reserva_codigo)}
                   </a>`
                : '<span class="badge bg-light-secondary text-muted mb-1">Sin Reserva</span>';

            const htmlBotonReembolso = permiteReembolso
                ? `<button type="button" class="btn btn-outline-danger btn-abrir-reembolso"
                           data-id="${tx.id}"
                           data-codigo="${escaparHtml(tx.codigo_transaccion)}"
                           data-cobrado="${tx.monto_cobrado}"
                           data-reembolsado="${tx.monto_reembolsado}"
                           data-disponible="${saldoDisp}"
                           title="Emitir Reembolso">
                       <i class="fa-solid fa-arrow-rotate-left"></i>
                   </button>`
                : '';

            tr.innerHTML = `
                <td class="px-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-light-primary text-primary me-2 f-s-11">
                            ${escaparHtml(tx.proveedor)}
                        </span>
                        <div>
                            <a href="/pagos/transacciones/${encodeURIComponent(tx.id)}" class="f-w-600 text-dark text-decoration-none">
                                ${escaparHtml(tx.codigo_transaccion)}
                            </a>
                            ${tx.transaccion_id_externo ? `<div class="f-s-11 text-muted">ID Ext: <code>${escaparHtml(tx.transaccion_id_externo)}</code></div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-3 f-s-12">
                    <span class="text-dark d-block">${escaparHtml(fechaStr)}</span>
                    <span class="text-muted f-s-11">${escaparHtml(horaStr)}</span>
                </td>
                <td class="px-3 f-s-12">
                    ${htmlReserva}
                    <div class="text-dark f-w-500">${escaparHtml(tx.pagador_nombre || '—')}</div>
                    <div class="text-muted f-s-11">${escaparHtml(tx.pagador_email || '')}</div>
                </td>
                <td class="px-3 text-end f-s-13">
                    <strong class="text-dark d-block">${formatearDinero(tx.monto_cobrado)}</strong>
                    ${htmlMontoReemb}
                </td>
                <td class="px-3 text-center">
                    ${obtenerBadgeEstadoPago(tx.estado_pago)}
                </td>
                <td class="px-3 text-center">
                    ${obtenerBadgeEstadoConciliacion(tx.estado_conciliacion, tx.subtipo_discrepancia)}
                </td>
                <td class="px-3 text-center">
                    ${obtenerBadgeEstadoReembolso(tx.estado_reembolso)}
                </td>
                <td class="px-3 text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="/pagos/transacciones/${encodeURIComponent(tx.id)}" class="btn btn-outline-secondary" title="Ver Detalle Completo">
                            <i class="fa-regular fa-eye"></i>
                        </a>
                        ${htmlBotonReembolso}
                    </div>
                </td>
            `;
            fragmento.appendChild(tr);
        });

        tbodyTransacciones.innerHTML = '';
        tbodyTransacciones.appendChild(fragmento);
    }

    function renderizarPaginacion(pag) {
        if (!contenedorPaginacion || !pag) return;

        const actual = parseInt(pag.pagina || 1, 10);
        const totalPags = Math.max(1, parseInt(pag.total_paginas || 1, 10));
        const totalRegs = parseInt(pag.total || 0, 10);

        if (infoPaginacion) {
            infoPaginacion.innerHTML = `Mostrando <strong id="pagina-actual">${actual}</strong> de <strong id="total-paginas">${totalPags}</strong> (Total: <span id="total-registros">${totalRegs}</span> transacciones)`;
        }

        if (totalPags <= 1) {
            contenedorPaginacion.innerHTML = '';
            return;
        }

        let html = '';
        html += `<li class="page-item ${actual <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${actual - 1}" aria-label="Anterior">&laquo;</a>
                 </li>`;

        const inicio = Math.max(1, actual - 2);
        const fin = Math.min(totalPags, actual + 2);

        if (inicio > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (inicio > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = inicio; i <= fin; i++) {
            html += `<li class="page-item ${i === actual ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                     </li>`;
        }

        if (fin < totalPags) {
            if (fin < totalPags - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPags}">${totalPags}</a></li>`;
        }

        html += `<li class="page-item ${actual >= totalPags ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${actual + 1}" aria-label="Siguiente">&raquo;</a>
                 </li>`;

        contenedorPaginacion.innerHTML = html;
    }

    // =========================================================================
    // 4. EVENT LISTENERS — MONITOR & FILTROS
    // =========================================================================

    if (formFiltros) {
        formFiltros.addEventListener('submit', function (e) {
            e.preventDefault();
            cargarDatos(1);
        });
    }

    if (btnLimpiarFiltros) {
        btnLimpiarFiltros.addEventListener('click', function () {
            if (formFiltros) formFiltros.reset();
            const inputRango = document.getElementById('filtro-rango-fechas');
            if (inputRango && inputRango._flatpickr) {
                inputRango._flatpickr.clear();
            }
            const fDesde = document.getElementById('filtro-fecha-desde');
            const fHasta = document.getElementById('filtro-fecha-hasta');
            if (fDesde) fDesde.value = '';
            if (fHasta) fHasta.value = '';
            cargarDatos(1);
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', function () {
            cargarDatos(paginaActual);
        });
    }

    if (contenedorPaginacion) {
        contenedorPaginacion.addEventListener('click', function (e) {
            e.preventDefault();
            const target = e.target.closest('a[data-page]');
            if (!target) return;
            const nuevaPagina = parseInt(target.getAttribute('data-page'), 10);
            if (!isNaN(nuevaPagina) && nuevaPagina > 0 && nuevaPagina !== paginaActual) {
                cargarDatos(nuevaPagina);
            }
        });
    }

    // =========================================================================
    // 5. GESTIÓN DEL MODAL DE REEMBOLSO (POST /pagos/transacciones/{id}/reembolsar)
    // =========================================================================

    // Delegación para botones de abrir reembolso (tanto en tabla como en vista de detalle)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-abrir-reembolso');
        if (!btn || !modalReembolso) return;

        const id = btn.getAttribute('data-id');
        const codigo = btn.getAttribute('data-codigo') || `#${id}`;
        const cobrado = parseFloat(btn.getAttribute('data-cobrado') || 0);
        const devuelto = parseFloat(btn.getAttribute('data-reembolsado') || 0);
        const disponible = parseFloat(btn.getAttribute('data-disponible') || 0);

        maxSaldoDisponible = disponible;

        if (inputReembolsoTxId) inputReembolsoTxId.value = id;
        if (lblReembolsoSubtitulo) lblReembolsoSubtitulo.textContent = `Transacción ${codigo}`;
        if (lblReembolsoCobrado) lblReembolsoCobrado.textContent = formatearDinero(cobrado);
        if (lblReembolsoYaDevuelto) lblReembolsoYaDevuelto.textContent = formatearDinero(devuelto);
        if (lblReembolsoDisponible) lblReembolsoDisponible.textContent = formatearDinero(disponible);

        // Reset inputs
        if (radioReembolsoTotal) radioReembolsoTotal.checked = true;
        if (contenedorMontoParcial) contenedorMontoParcial.classList.add('d-none');
        if (inputMontoParcial) {
            inputMontoParcial.value = '';
            inputMontoParcial.max = disponible.toFixed(2);
        }
        if (txtReembolsoMotivo) txtReembolsoMotivo.value = '';

        modalReembolso.show();
    });

    if (radioReembolsoTotal && radioReembolsoParcial && contenedorMontoParcial) {
        radioReembolsoTotal.addEventListener('change', function () {
            if (this.checked) contenedorMontoParcial.classList.add('d-none');
        });
        radioReembolsoParcial.addEventListener('change', function () {
            if (this.checked) {
                contenedorMontoParcial.classList.remove('d-none');
                if (inputMontoParcial) inputMontoParcial.focus();
            }
        });
    }

    if (formReembolso) {
        formReembolso.addEventListener('submit', async function (e) {
            e.preventDefault();

            const txId = inputReembolsoTxId?.value;
            if (!txId) return;

            const esParcial = radioReembolsoParcial?.checked;
            const tipo = esParcial ? 'PARCIAL' : 'TOTAL';
            let monto = maxSaldoDisponible;

            if (esParcial) {
                monto = parseFloat(inputMontoParcial?.value || 0);
                if (isNaN(monto) || monto <= 0.00) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Monto inválido',
                        text: 'Debe ingresar un monto parcial mayor a S/ 0.00.',
                        confirmButtonColor: '#0d6efd'
                    });
                    return;
                }
                if (monto > maxSaldoDisponible + 0.0001) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Monto excede saldo',
                        text: `El monto solicitado (${formatearDinero(monto)}) no puede superar el saldo reembolsable (${formatearDinero(maxSaldoDisponible)}).`,
                        confirmButtonColor: '#0d6efd'
                    });
                    return;
                }
            }

            const motivo = txtReembolsoMotivo?.value?.trim() || '';
            if (motivo.length < 10) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Motivo requerido',
                    text: 'Debe detallar una justificación clara de al menos 10 caracteres para la auditoría contable.',
                    confirmButtonColor: '#0d6efd'
                });
                return;
            }

            // Confirmación con SweetAlert2
            const confirmacion = await Swal.fire({
                title: '¿Confirmar Solicitud de Reembolso?',
                html: `Se emitirá una instrucción de reembolso <strong>${tipo}</strong> por <strong>${formatearDinero(monto)}</strong> ante la pasarela de pagos.<br><br>Esta acción tiene efectos contables y financieros irreversibles.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, Procesar Reembolso',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            });

            if (!confirmacion.isConfirmed) return;

            if (btnSubmitReembolso) {
                btnSubmitReembolso.disabled = true;
                btnSubmitReembolso.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Procesando...';
            }

            try {
                const res = await fetch(`/pagos/transacciones/${encodeURIComponent(txId)}/reembolsar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        tipo_reembolso: tipo,
                        monto: monto,
                        motivo: motivo
                    })
                });

                const data = await res.json();
                const exitoso = res.ok && (data.ok === true || data.exito === true);

                if (!exitoso) {
                    throw new Error(data.error || data.mensaje || 'No fue posible completar el reembolso.');
                }

                modalReembolso.hide();

                await Swal.fire({
                    icon: 'success',
                    title: 'Reembolso Procesado con Éxito',
                    text: data.mensaje || 'La pasarela confirmó el reembolso correctamente.',
                    confirmButtonColor: '#198754'
                });

                // Si estamos en la vista de detalle, recargamos la página para actualizar timeline y métricas
                if (window.location.pathname.includes('/pagos/transacciones/')) {
                    window.location.reload();
                } else {
                    cargarDatos(paginaActual);
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al Reembolsar',
                    text: err.message || 'Ocurrió un error inesperado al contactar con la pasarela.',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                if (btnSubmitReembolso) {
                    btnSubmitReembolso.disabled = false;
                    btnSubmitReembolso.innerHTML = '<i class="fa-solid fa-arrow-rotate-left me-1"></i> Confirmar y Procesar Reembolso';
                }
            }
        });
    }

    // =========================================================================
    // 6. GESTIÓN DEL OFFCANVAS DE WEBHOOK (GET /pagos/webhooks/{id}/payload)
    // =========================================================================

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-ver-webhook');
        if (!btn || !offcanvasWebhook) return;

        const webhookId = btn.getAttribute('data-id');
        if (!webhookId) return;

        if (webhookSubtitulo) webhookSubtitulo.textContent = `Evento #${webhookId}`;
        if (webhookLoading) webhookLoading.classList.remove('d-none');
        if (webhookContenido) webhookContenido.classList.add('d-none');
        if (webhookContenedorError) webhookContenedorError.classList.add('d-none');

        offcanvasWebhook.show();

        try {
            const res = await fetch(`/pagos/webhooks/${encodeURIComponent(webhookId)}/payload`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) {
                throw new Error(`Error ${res.status}: No fue posible obtener los datos del webhook.`);
            }

            const data = await res.json();
            const w = data.datos || data.webhook || data;

            if (webhookMetaProveedor) webhookMetaProveedor.textContent = w.proveedor || 'DESCONOCIDO';
            if (webhookMetaTipo) webhookMetaTipo.textContent = w.tipo_evento || '—';
            if (webhookMetaEventoId) webhookMetaEventoId.textContent = w.proveedor_evento_id || w.evento_id_externo || '—';

            if (webhookMetaHttpCode) {
                const httpCode = parseInt(w.codigo_http_respuesta || 200, 10);
                webhookMetaHttpCode.textContent = `${httpCode} ${httpCode === 200 ? 'OK' : 'Error'}`;
                webhookMetaHttpCode.className = httpCode === 200
                    ? 'badge bg-light-success text-success f-s-12'
                    : 'badge bg-light-danger text-danger f-s-12';
            }

            if (webhookMetaRecibido) webhookMetaRecibido.textContent = w.creado_en || w.recibido_en || '—';
            if (webhookMetaIp) webhookMetaIp.textContent = w.ip_origen || 'No registrada';

            const errorDetalle = w.error_detalle || w.error_procesamiento;
            if (errorDetalle && webhookContenedorError && webhookErrorTexto) {
                webhookErrorTexto.textContent = errorDetalle;
                webhookContenedorError.classList.remove('d-none');
            }

            if (webhookPayloadJson) {
                webhookPayloadJson.textContent = JSON.stringify(w.payload_sanitizado || w.payload || {}, null, 2);
            }

            if (webhookLoading) webhookLoading.classList.add('d-none');
            if (webhookContenido) webhookContenido.classList.remove('d-none');
        } catch (err) {
            if (webhookLoading) webhookLoading.classList.add('d-none');
            if (webhookContenido) webhookContenido.classList.remove('d-none');
            if (webhookPayloadJson) webhookPayloadJson.textContent = `Error al cargar: ${err.message}`;
        }
    });

    if (btnCopiarPayload && webhookPayloadJson) {
        btnCopiarPayload.addEventListener('click', function () {
            const texto = webhookPayloadJson.textContent;
            if (!texto) return;

            navigator.clipboard.writeText(texto).then(() => {
                const textoOriginal = btnCopiarPayload.innerHTML;
                btnCopiarPayload.innerHTML = '<i class="fa-solid fa-check text-success me-1"></i> ¡Copiado!';
                setTimeout(() => {
                    btnCopiarPayload.innerHTML = textoOriginal;
                }, 2000);
            }).catch(err => {
                console.error('Error al copiar al portapapeles:', err);
            });
        });
    }

    // =========================================================================
    // 7. GESTIÓN DE OBSERVACIÓN ADMINISTRATIVA (EN DETALLE)
    // =========================================================================

    if (btnAbrirObservacion && modalObservacion) {
        btnAbrirObservacion.addEventListener('click', function () {
            modalObservacion.show();
        });
    }

    if (formObservacion) {
        formObservacion.addEventListener('submit', async function (e) {
            e.preventDefault();

            const txId = formObservacion.querySelector('input[name="transaccion_id"]')?.value;
            const obsTexto = document.getElementById('observacion-texto')?.value?.trim();

            if (!obsTexto || obsTexto.length < 10) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Texto requerido',
                    text: 'Debe ingresar una observación detallada de al menos 10 caracteres.',
                    confirmButtonColor: '#0d6efd'
                });
                return;
            }

            const btnSubmit = document.getElementById('btn-guardar-observacion');
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...';
            }

            try {
                const res = await fetch(`/pagos/transacciones/${encodeURIComponent(txId)}/conciliar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        accion: 'REGISTRAR_SEGUIMIENTO',
                        observacion: obsTexto
                    })
                });

                const data = await res.json();
                const exitoso = res.ok && (data.ok === true || data.exito === true);
                if (!exitoso) {
                    throw new Error(data.error || data.mensaje || 'No fue posible registrar la observación.');
                }

                if (modalObservacion) modalObservacion.hide();

                await Swal.fire({
                    icon: 'success',
                    title: 'Observación Registrada',
                    text: 'La bitácora de seguimiento administrativo ha sido actualizada.',
                    confirmButtonColor: '#198754'
                });

                window.location.reload();
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: err.message || 'No fue posible guardar la observación.',
                    confirmButtonColor: '#dc3545'
                });
            } finally {
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Observación';
                }
            }
        });
    }
});
