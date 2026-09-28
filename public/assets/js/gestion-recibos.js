/**
 * Módulo JavaScript para la Gestión de Recibos de Cobranza (RECIBOS-1 / D-082).
 *
 * Arquitectura:
 * - Vanilla JS moderno con Fetch API y SweetAlert2.
 * - Sin frameworks reactivos pesados.
 * - Consume endpoints REST del backend Camargo PMS.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Estado y variables globales
    const csrfToken = document.getElementById('csrf-token-global')?.value || '';
    let recibosData = [];
    let pagosElegibles = [];
    let reciboActualSeleccionado = null;

    // Elementos DOM
    const tbodyRecibos = document.getElementById('tbody-recibos');
    const filtroBusqueda = document.getElementById('filtro-busqueda');
    const filtroEstado = document.getElementById('filtro-estado');
    const filtroFolio = document.getElementById('filtro-folio');
    const btnRefrescar = document.getElementById('btn-refrescar-tabla');
    const btnAbrirEmitir = document.getElementById('btn-abrir-modal-emitir');

    // Modales Bootstrap
    const modalEmitirEl = document.getElementById('modal-emitir-recibo');
    const modalEmitir = modalEmitirEl ? new bootstrap.Modal(modalEmitirEl) : null;
    const modalDetalleEl = document.getElementById('modal-detalle-recibo');
    const modalDetalle = modalDetalleEl ? new bootstrap.Modal(modalDetalleEl) : null;
    const modalAnularEl = document.getElementById('modal-anular-recibo');
    const modalAnular = modalAnularEl ? new bootstrap.Modal(modalAnularEl) : null;

    // Formularios
    const formEmitir = document.getElementById('form-emitir-recibo');
    const selectPagoEmitir = document.getElementById('emitir-pago-id');
    const cardDetallePago = document.getElementById('card-detalle-pago-seleccionado');
    const formAnular = document.getElementById('form-anular-recibo');

    // Inicialización
    cargarCatalogos();
    cargarRecibos();

    // =========================================================================
    // 1. CARGA DE CATÁLOGOS Y FOLIOS
    // =========================================================================
    async function cargarCatalogos() {
        try {
            const resp = await fetch('/api/recibos/catalogos');
            const data = await resp.json();
            if (!data.exito) return;

            pagosElegibles = data.datos.pagos_elegibles || [];
            const folios = data.datos.cuentas_folios || [];

            // Llenar select de pagos elegibles
            if (selectPagoEmitir) {
                selectPagoEmitir.innerHTML = '<option value="">Seleccione un pago confirmado elegible...</option>';
                pagosElegibles.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.codigo} — S/ ${parseFloat(p.monto_total).toFixed(2)} (${p.folio_codigo} - ${p.titular_nombre})`;
                    selectPagoEmitir.appendChild(opt);
                });
            }

            // Llenar filtro de folios
            if (filtroFolio) {
                filtroFolio.innerHTML = '<option value="">Todas las Cuentas Folio</option>';
                folios.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.textContent = `${f.codigo} — ${f.titular_nombre}`;
                    filtroFolio.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Error al cargar catálogos de recibos:', err);
        }
    }

    // =========================================================================
    // 2. CARGA Y RENDERIZADO DE TABLA DE RECIBOS
    // =========================================================================
    async function cargarRecibos() {
        if (!tbodyRecibos) return;
        tbodyRecibos.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i> Cargando recibos...
                </td>
            </tr>`;

        const params = new URLSearchParams();
        if (filtroEstado && filtroEstado.value) params.append('estado', filtroEstado.value);
        if (filtroFolio && filtroFolio.value) params.append('cuenta_folio_id', filtroFolio.value);

        try {
            const resp = await fetch(`/api/recibos?${params.toString()}`);
            const data = await resp.json();

            if (!data.exito) {
                tbodyRecibos.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">${data.error || 'Error al cargar'}</td></tr>`;
                return;
            }

            recibosData = data.datos || [];
            renderizarTabla(recibosData);
        } catch (err) {
            console.error('Error al consultar recibos:', err);
            tbodyRecibos.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error de conexión al servidor</td></tr>';
        }
    }

    function renderizarTabla(items) {
        if (!tbodyRecibos) return;

        const filtroTexto = filtroBusqueda ? filtroBusqueda.value.toLowerCase().trim() : '';
        const filtrados = items.filter(r => {
            if (!filtroTexto) return true;
            return (
                (r.codigo || '').toLowerCase().includes(filtroTexto) ||
                (r.persona_nombre_snapshot || '').toLowerCase().includes(filtroTexto) ||
                (r.persona_documento_numero_snapshot || '').toLowerCase().includes(filtroTexto) ||
                (r.referencia_cobro || '').toLowerCase().includes(filtroTexto)
            );
        });

        if (filtrados.length === 0) {
            tbodyRecibos.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-folder-open f-s-32 mb-2 d-block"></i>
                        No se encontraron recibos de cobranza con los filtros aplicados.
                    </td>
                </tr>`;
            return;
        }

        tbodyRecibos.innerHTML = filtrados.map(r => {
            const esActivo = r.estado === 'EMITIDO';
            const badgeEstado = esActivo
                ? '<span class="badge bg-success">EMITIDO</span>'
                : '<span class="badge bg-danger">ANULADO</span>';

            const montoRec = parseFloat(r.monto_recaudado).toFixed(2);
            const montoImp = parseFloat(r.monto_imputado).toFixed(2);
            const montoNoApl = parseFloat(r.monto_no_aplicado_pago).toFixed(2);

            return `
                <tr>
                    <td class="text-center f-w-600">
                        <a href="javascript:void(0)" class="text-primary btn-ver-detalle" data-id="${r.id}">
                            <code>${r.codigo}</code>
                        </a>
                    </td>
                    <td>
                        <span class="f-s-12">${formatearFecha(r.fecha_emision)}</span>
                    </td>
                    <td>
                        <div class="f-w-600 f-s-13">${escapeHtml(r.persona_nombre_snapshot)}</div>
                        <div class="text-muted f-s-11">${escapeHtml(r.persona_documento_tipo_snapshot)} ${escapeHtml(r.persona_documento_numero_snapshot)}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">Folio #${r.cuenta_folio_id}</span>
                    </td>
                    <td class="text-end f-w-700 text-dark">
                        ${r.moneda_codigo} ${montoRec}
                    </td>
                    <td class="text-end text-primary">
                        ${r.moneda_codigo} ${montoImp}
                    </td>
                    <td class="text-end text-success f-w-600">
                        ${r.moneda_codigo} ${montoNoApl}
                    </td>
                    <td class="text-center">
                        ${badgeEstado}
                    </td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end f-s-12 shadow border-0">
                                <li>
                                    <a class="dropdown-item btn-ver-detalle" href="javascript:void(0)" data-id="${r.id}">
                                        <i class="fa-solid fa-eye me-2 text-primary"></i> Ver Detalle y T0
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item btn-descargar-pdf" href="javascript:void(0)" data-id="${r.id}">
                                        <i class="fa-solid fa-file-pdf me-2 text-danger"></i> Descargar PDF Oficial
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item btn-verificar-hash" href="javascript:void(0)" data-id="${r.id}">
                                        <i class="fa-solid fa-shield-halved me-2 text-info"></i> Validar Integridad SHA-256
                                    </a>
                                </li>
                                ${esActivo ? `
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger btn-abrir-anular" href="javascript:void(0)" data-id="${r.id}" data-codigo="${r.codigo}">
                                        <i class="fa-solid fa-ban me-2"></i> Anular Administrativamente
                                    </a>
                                </li>` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        vincularEventosTabla();
    }

    // =========================================================================
    // 3. EVENTOS DE TABLA
    // =========================================================================
    function vincularEventosTabla() {
        document.querySelectorAll('.btn-ver-detalle').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                abrirModalDetalle(id);
            });
        });

        document.querySelectorAll('.btn-descargar-pdf').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                descargarPdf(id);
            });
        });

        document.querySelectorAll('.btn-verificar-hash').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                verificarIntegridadHash(id);
            });
        });

        document.querySelectorAll('.btn-abrir-anular').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                const codigo = btn.dataset.codigo;
                abrirModalAnular(id, codigo);
            });
        });
    }

    // =========================================================================
    // 4. EMISIÓN DE RECIBO OFICIAL
    // =========================================================================
    if (btnAbrirEmitir) {
        btnAbrirEmitir.addEventListener('click', () => {
            if (formEmitir) formEmitir.reset();
            if (cardDetallePago) cardDetallePago.classList.add('d-none');
            cargarCatalogos();
            if (modalEmitir) modalEmitir.show();
        });
    }

    if (selectPagoEmitir) {
        selectPagoEmitir.addEventListener('change', () => {
            const pagoId = parseInt(selectPagoEmitir.value, 10);
            const pago = pagosElegibles.find(p => p.id === pagoId);

            if (!pago) {
                if (cardDetallePago) cardDetallePago.classList.add('d-none');
                return;
            }

            document.getElementById('prev-titular').textContent = pago.titular_nombre || '-';
            document.getElementById('prev-folio').textContent = pago.folio_codigo || '-';
            document.getElementById('prev-monto').textContent = `${pago.moneda_codigo} ${parseFloat(pago.monto_total).toFixed(2)}`;
            document.getElementById('prev-metodo').textContent = pago.metodo_pago_nombre || 'Efectivo';
            document.getElementById('prev-referencia').textContent = pago.referencia_operacion || 'Operación Directa';

            if (cardDetallePago) cardDetallePago.classList.remove('d-none');
        });
    }

    if (formEmitir) {
        formEmitir.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnSubmit = document.getElementById('btn-confirmar-emision');
            if (btnSubmit) btnSubmit.disabled = true;

            const datos = {
                pago_id: parseInt(document.getElementById('emitir-pago-id').value, 10),
                concepto_general: document.getElementById('emitir-concepto').value.trim() || null,
                notas: document.getElementById('emitir-notas').value.trim() || null,
            };

            try {
                const resp = await fetch('/api/recibos/emitir', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(datos),
                });
                const data = await resp.json();

                if (!data.exito) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Emisión',
                        text: data.error || 'No se pudo emitir el recibo.',
                    });
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Recibo Emitido Exitosamente',
                    text: data.mensaje || `Recibo [${data.datos.codigo}] emitido con preservación criptográfica.`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF',
                    cancelButtonText: 'Cerrar',
                }).then((result) => {
                    if (result.isConfirmed) {
                        descargarPdf(data.datos.id);
                    }
                });

                if (modalEmitir) modalEmitir.hide();
                cargarRecibos();
                cargarCatalogos();
            } catch (err) {
                console.error('Error al emitir recibo:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexión',
                    text: 'Ocurrió un error al comunicarse con el servidor.',
                });
            } finally {
                if (btnSubmit) btnSubmit.disabled = false;
            }
        });
    }

    // =========================================================================
    // 5. DETALLE DE RECIBO
    // =========================================================================
    async function abrirModalDetalle(id) {
        try {
            const resp = await fetch(`/api/recibos/${id}`);
            const data = await resp.json();

            if (!data.exito) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo cargar el recibo.' });
                return;
            }

            const r = data.datos;
            reciboActualSeleccionado = r;

            document.getElementById('det-folio-codigo').textContent = r.codigo;
            const badgeEstado = document.getElementById('det-estado-badge');
            if (badgeEstado) {
                badgeEstado.textContent = r.estado;
                badgeEstado.className = `badge ms-3 ${r.estado === 'EMITIDO' ? 'bg-success' : 'bg-danger'}`;
            }

            document.getElementById('det-titular-nombre').textContent = r.persona_nombre_snapshot;
            document.getElementById('det-titular-documento').textContent = `${r.persona_documento_tipo_snapshot} ${r.persona_documento_numero_snapshot}`;
            document.getElementById('det-folio-asociado').textContent = `Folio #${r.cuenta_folio_id}`;
            document.getElementById('det-fecha-emision').textContent = formatearFecha(r.fecha_emision);

            document.getElementById('det-pago-codigo').textContent = `Pago #${r.pago_id}`;
            document.getElementById('det-metodo-pago').textContent = r.metodo_pago_nombre || 'Efectivo';
            document.getElementById('det-referencia-cobro').textContent = r.referencia_cobro || 'Operación Directa';
            document.getElementById('det-monto-recaudado').textContent = `${r.moneda_codigo} ${parseFloat(r.monto_recaudado).toFixed(2)}`;

            document.getElementById('det-res-recaudado').textContent = `${r.moneda_codigo} ${parseFloat(r.monto_recaudado).toFixed(2)}`;
            document.getElementById('det-res-imputado').textContent = `${r.moneda_codigo} ${parseFloat(r.monto_imputado).toFixed(2)}`;
            document.getElementById('det-res-no-aplicado').textContent = `${r.moneda_codigo} ${parseFloat(r.monto_no_aplicado_pago).toFixed(2)}`;
            document.getElementById('det-res-saldo-pendiente').textContent = `${r.moneda_codigo} ${parseFloat(r.saldo_pendiente_folio_despues).toFixed(2)}`;
            document.getElementById('det-res-saldo-favor').textContent = `${r.moneda_codigo} ${parseFloat(r.saldo_favor_folio_despues).toFixed(2)}`;

            document.getElementById('det-doc-id').textContent = r.documento_emitido_id ? `#${r.documento_emitido_id}` : 'Sin enlace';
            document.getElementById('det-doc-hash').textContent = 'Consultar integridad...';
            document.getElementById('det-doc-integridad').innerHTML = '<span class="badge bg-secondary">Sin validar</span>';

            // Líneas de amortizaciones
            const tbodyLineas = document.getElementById('det-tbody-lineas');
            if (tbodyLineas) {
                if (!r.lineas || r.lineas.length === 0) {
                    tbodyLineas.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-3 text-muted f-s-12">
                                <i class="fa-solid fa-info-circle me-1"></i> Sin imputaciones directas en T0 (Monto recibido como saldo a favor en cuenta folio).
                            </td>
                        </tr>`;
                } else {
                    tbodyLineas.innerHTML = r.lineas.map(l => `
                        <tr>
                            <td class="text-center">${l.numero_linea}</td>
                            <td><code>${escapeHtml(l.cargo_codigo)}</code></td>
                            <td>${escapeHtml(l.cargo_concepto)}</td>
                            <td class="text-end">${parseFloat(l.cargo_monto_total).toFixed(2)}</td>
                            <td class="text-end f-w-700 text-primary">${parseFloat(l.monto_aplicado).toFixed(2)}</td>
                            <td class="text-end">${parseFloat(l.cargo_saldo_restante).toFixed(2)}</td>
                        </tr>`).join('');
                }
            }

            // Trazabilidad de anulación
            const alertaAnulacion = document.getElementById('det-alerta-anulacion');
            if (alertaAnulacion) {
                if (r.estado === 'ANULADO') {
                    document.getElementById('det-anulacion-motivo').textContent = r.motivo_anulacion || 'Sin motivo consignado.';
                    document.getElementById('det-anulacion-meta').textContent = `Anulado en: ${formatearFecha(r.anulado_en)} por Actor #${r.anulado_por_actor_id || '-'}`;
                    alertaAnulacion.classList.remove('d-none');
                } else {
                    alertaAnulacion.classList.add('d-none');
                }
            }

            if (modalDetalle) modalDetalle.show();
        } catch (err) {
            console.error('Error al abrir detalle de recibo:', err);
        }
    }

    // Botones dentro del modal detalle
    const btnDescargarModal = document.getElementById('btn-descargar-pdf-modal');
    if (btnDescargarModal) {
        btnDescargarModal.addEventListener('click', () => {
            if (reciboActualSeleccionado) {
                descargarPdf(reciboActualSeleccionado.id);
            }
        });
    }

    const btnVerificarHashModal = document.getElementById('btn-verificar-hash-modal');
    if (btnVerificarHashModal) {
        btnVerificarHashModal.addEventListener('click', () => {
            if (reciboActualSeleccionado) {
                verificarIntegridadHash(reciboActualSeleccionado.id, true);
            }
        });
    }

    // =========================================================================
    // 6. ANULACIÓN FORMAL AUDITADA
    // =========================================================================
    function abrirModalAnular(id, codigo) {
        document.getElementById('anular-recibo-id').value = id;
        document.getElementById('anular-folio-texto').textContent = codigo;
        document.getElementById('anular-motivo').value = '';
        if (modalAnular) modalAnular.show();
    }

    if (formAnular) {
        formAnular.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = parseInt(document.getElementById('anular-recibo-id').value, 10);
            const motivo = document.getElementById('anular-motivo').value.trim();

            if (!motivo) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'El motivo de anulación es obligatorio.' });
                return;
            }

            const btnConfirmar = document.getElementById('btn-confirmar-anulacion');
            if (btnConfirmar) btnConfirmar.disabled = true;

            try {
                const resp = await fetch(`/api/recibos/${id}/anular`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ motivo }),
                });
                const data = await resp.json();

                if (!data.exito) {
                    Swal.fire({ icon: 'error', title: 'Error de Anulación', text: data.error || 'No se pudo anular el recibo.' });
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Recibo Anulado Formalmente',
                    text: data.mensaje || 'El recibo ha sido anulado administrativamente preservando intacto el archivo PDF original.',
                });

                if (modalAnular) modalAnular.hide();
                cargarRecibos();
            } catch (err) {
                console.error('Error al anular recibo:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al comunicarse con el servidor.' });
            } finally {
                if (btnConfirmar) btnConfirmar.disabled = false;
            }
        });
    }

    // =========================================================================
    // 7. DESCARGA Y VERIFICACIÓN CRIPTOGRÁFICA
    // =========================================================================
    function descargarPdf(id) {
        window.open(`/api/recibos/${id}/pdf?descargar=1`, '_blank');
    }

    async function verificarIntegridadHash(id, actualizarModal = false) {
        try {
            const resp = await fetch(`/api/recibos/${id}/verificar-hash`);
            const data = await resp.json();

            if (!data.exito) {
                Swal.fire({ icon: 'error', title: 'Error de Integridad', text: data.error || 'No se pudo verificar el documento.' });
                return;
            }

            const v = data.datos;
            const esValido = v.valido === true;

            if (actualizarModal) {
                const lblHash = document.getElementById('det-doc-hash');
                const lblInteg = document.getElementById('det-doc-integridad');
                if (lblHash) lblHash.textContent = v.hash_esperado;
                if (lblInteg) {
                    lblInteg.innerHTML = esValido
                        ? '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> SHA-256 Coincidente</span>'
                        : '<span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Corrupto / Modificado</span>';
                }
            }

            Swal.fire({
                icon: esValido ? 'success' : 'error',
                title: esValido ? 'Integridad Criptográfica Verificada' : 'Violación de Integridad Detectada',
                html: `
                    <div class="text-start f-s-12">
                        <p class="mb-1"><strong>Estado:</strong> ${esValido ? '<span class="text-success font-bold">VÁLIDO (Intacto)</span>' : '<span class="text-danger font-bold">CORRUPTO / MODIFICADO</span>'}</p>
                        <p class="mb-1"><strong>Hash en BD:</strong></p>
                        <code class="word-break-all f-s-11 d-block p-1 bg-light">${v.hash_esperado}</code>
                        <p class="mb-1 mt-2"><strong>Hash en Disco:</strong></p>
                        <code class="word-break-all f-s-11 d-block p-1 bg-light">${v.hash_calculado}</code>
                    </div>`,
            });
        } catch (err) {
            console.error('Error al verificar hash de recibo:', err);
        }
    }

    // =========================================================================
    // 8. FILTROS Y EVENTOS GENERALES
    // =========================================================================
    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('input', () => renderizarTabla(recibosData));
    }
    if (filtroEstado) {
        filtroEstado.addEventListener('change', () => cargarRecibos());
    }
    if (filtroFolio) {
        filtroFolio.addEventListener('change', () => cargarRecibos());
    }
    if (btnRefrescar) {
        btnRefrescar.addEventListener('click', () => {
            cargarCatalogos();
            cargarRecibos();
        });
    }

    // =========================================================================
    // UTILIDADES
    // =========================================================================
    function formatearFecha(strFecha) {
        if (!strFecha) return '-';
        const d = new Date(strFecha.replace(' ', 'T'));
        if (isNaN(d.getTime())) return strFecha;
        const pad = n => String(n).padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
