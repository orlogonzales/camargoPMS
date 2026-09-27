/**
 * Camargo PMS — Módulo de Motor Documental, Plantillas y Generación PDF (DOCUMENTOS-1 / D-079)
 *
 * Implementado en Vanilla JavaScript (ES6+), Fetch API, PristineJS y SweetAlert2.
 * Principios vinculantes:
 * - PLANTILLA != VERSIÓN != SNAPSHOT != DOCUMENTO EMITIDO != PDF BINARIO.
 * - Snapshots inmutables.
 * - Hash SHA-256 verificado en cada descarga.
 * - D-071: Badges suaves bg-light-*, Font Awesome 6.3.0 exclusivo, cero degradados.
 * - Geometría Alina nativa (border-radius 20px, app-form).
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const csrfToken = document.getElementById('csrf-token-global')?.value || '';

    // Modales Bootstrap
    const modalEmitirEl = document.getElementById('modal-emitir-contrato');
    const modalVersionEl = document.getElementById('modal-nueva-version');
    const modalAnularEl = document.getElementById('modal-anular-documento');
    const modalVisorEl = document.getElementById('modal-visor-documento');

    const modalEmitir = modalEmitirEl ? new bootstrap.Modal(modalEmitirEl) : null;
    const modalVersion = modalVersionEl ? new bootstrap.Modal(modalVersionEl) : null;
    const modalAnular = modalAnularEl ? new bootstrap.Modal(modalAnularEl) : null;
    const modalVisor = modalVisorEl ? new bootstrap.Modal(modalVisorEl) : null;

    // Cache local de datos
    let documentosCache = [];
    let plantillasCache = [];
    let incidenciasCache = [];

    // =========================================================================
    // Utilidades y Helpers
    // =========================================================================

    function escaparHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatearFecha(isoString) {
        if (!isoString) return '—';
        const d = new Date(isoString.replace(' ', 'T'));
        if (isNaN(d.getTime())) return isoString;
        return d.toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function badgeEstadoDoc(estado) {
        switch (estado) {
            case 'VALIDO':
                return '<span class="badge bg-light-success text-success f-w-600"><i class="fa-solid fa-circle-check me-1"></i> Válido</span>';
            case 'ANULADO':
                return '<span class="badge bg-light-danger text-danger f-w-600"><i class="fa-solid fa-ban me-1"></i> Anulado</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(estado)}</span>`;
        }
    }

    function badgeOrigen(origen) {
        switch (origen) {
            case 'ARRENDAMIENTO':
                return '<span class="badge bg-light-primary text-primary f-w-600"><i class="fa-solid fa-house-chimney-user me-1"></i> Arrendamiento</span>';
            case 'RESERVA':
                return '<span class="badge bg-light-info text-info f-w-600"><i class="fa-solid fa-calendar-check me-1"></i> Reserva</span>';
            case 'PAGO':
                return '<span class="badge bg-light-warning text-warning f-w-600"><i class="fa-solid fa-receipt me-1"></i> Pago</span>';
            default:
                return `<span class="badge bg-light-secondary text-secondary f-w-600">${escaparHtml(origen)}</span>`;
        }
    }

    // =========================================================================
    // Carga de Datos y Renders
    // =========================================================================

    async function cargarDocumentos() {
        const tbody = document.querySelector('#tabla-documentos tbody');
        if (!tbody) return;

        try {
            const resp = await fetch('/api/documentos', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Error al consultar documentos.');
            const data = await resp.json();
            documentosCache = data.datos || [];
            renderizarDocumentos();
            actualizarKpiEmitidos(documentosCache.length);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${escaparHtml(err.message)}</td></tr>`;
        }
    }

    function renderizarDocumentos() {
        const tbody = document.querySelector('#tabla-documentos tbody');
        if (!tbody) return;

        const termino = document.getElementById('filtro-docs-termino')?.value.toLowerCase().trim() || '';
        const origen = document.getElementById('filtro-docs-origen')?.value || '';
        const estado = document.getElementById('filtro-docs-estado')?.value || '';

        const filtrados = documentosCache.filter(d => {
            const matchTermino = !termino || 
                (d.codigo_folio && d.codigo_folio.toLowerCase().includes(termino)) ||
                (d.plantilla_nombre && d.plantilla_nombre.toLowerCase().includes(termino)) ||
                (d.emisor_nombre && d.emisor_nombre.toLowerCase().includes(termino));
            const matchOrigen = !origen || d.origen_tipo === origen;
            const matchEstado = !estado || d.estado === estado;
            return matchTermino && matchOrigen && matchEstado;
        });

        if (filtrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron documentos emitidos con los criterios especificados.</td></tr>';
            return;
        }

        tbody.innerHTML = filtrados.map(d => {
            const hashCorto = d.hash_pdf_sha256 ? d.hash_pdf_sha256.substring(0, 12) + '...' : '—';
            return `
                <tr>
                    <td>
                        <span class="f-w-700 text-dark">${escaparHtml(d.codigo_folio)}</span>
                    </td>
                    <td>
                        <div class="f-w-600">${escaparHtml(d.plantilla_nombre || d.plantilla_codigo)}</div>
                        <span class="f-s-11 text-muted">Versión ${d.plantilla_numero_version || d.plantilla_version_id} (${d.numero_paginas || 1} pág.)</span>
                    </td>
                    <td>
                        ${badgeOrigen(d.origen_tipo)}
                        <span class="f-s-12 text-secondary ms-1">#${d.origen_id}</span>
                    </td>
                    <td>
                        <div class="f-s-12">${formatearFecha(d.emitido_en)}</div>
                        <span class="f-s-11 text-muted">Por: ${escaparHtml(d.emisor_nombre || 'Sistema')}</span>
                    </td>
                    <td>
                        <span class="font-monospace f-s-11 text-secondary" title="${escaparHtml(d.hash_pdf_sha256)}">${hashCorto}</span>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-primary btn-verificar-doc" data-id="${d.id}" title="Verificar integridad SHA-256">
                            <i class="fa-solid fa-shield-check"></i>
                        </button>
                    </td>
                    <td class="text-center">
                        ${badgeEstadoDoc(d.estado)}
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a href="/api/documentos/${d.id}/descargar" class="btn btn-outline-primary btn-sm" title="Descargar PDF" target="_blank">
                                <i class="fa-solid fa-download"></i>
                            </a>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-previsualizar-doc" data-id="${d.id}" title="Visualizar">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            ${d.estado === 'VALIDO' ? `
                                <button type="button" class="btn btn-outline-danger btn-sm btn-anular-doc" data-id="${d.id}" data-folio="${escaparHtml(d.codigo_folio)}" title="Anular Documento">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                            ` : ''}
                            <button type="button" class="btn btn-outline-warning btn-sm btn-regenerar-doc" data-id="${d.id}" title="Regenerar PDF desde Snapshot">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function cargarPlantillas() {
        const cont = document.getElementById('contenedor-plantillas-tarjetas');
        if (!cont) return;

        try {
            const resp = await fetch('/api/documentos/plantillas', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Error al cargar plantillas.');
            const data = await resp.json();
            plantillasCache = data.datos || [];
            renderizarPlantillas();
            actualizarSelectPlantillas();
        } catch (err) {
            cont.innerHTML = `<div class="col-12 text-center text-danger py-4">${escaparHtml(err.message)}</div>`;
        }
    }

    function renderizarPlantillas() {
        const cont = document.getElementById('contenedor-plantillas-tarjetas');
        if (!cont) return;

        if (plantillasCache.length === 0) {
            cont.innerHTML = '<div class="col-12 text-center text-muted py-4">No hay plantillas registradas.</div>';
            return;
        }

        cont.innerHTML = plantillasCache.map(p => {
            const vActiva = p.version_activa;
            return `
                <div class="col-md-6 col-12">
                    <div class="card border shadow-sm b-r-12 h-100 p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-light-primary text-primary f-s-11 mb-1">${escaparHtml(p.codigo)}</span>
                                <h5 class="f-s-16 f-w-700 mb-0">${escaparHtml(p.nombre)}</h5>
                            </div>
                            <span class="badge bg-light-success text-success">${escaparHtml(p.estado)}</span>
                        </div>
                        <p class="f-s-12 text-secondary mb-3">${escaparHtml(p.descripcion || 'Sin descripción.')}</p>
                        
                        <div class="bg-light p-2 b-r-8 mb-3 f-s-12">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Origen admitido:</span>
                                <span class="f-w-600">${escaparHtml(p.origen_tipo_permitido)}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Formato / Papel:</span>
                                <span class="f-w-600">${escaparHtml(p.tamano_papel)} (${escaparHtml(p.orientacion)})</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Membrete institucional:</span>
                                <span class="f-w-600">${p.requiere_membrete ? 'Sí (A4 Canónico)' : 'No'}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Versión activa actual:</span>
                                <span class="f-w-700 text-primary">${vActiva ? `V${vActiva.numero_version} — ${escaparHtml(vActiva.titulo_documento)}` : 'Ninguna activa'}</span>
                            </div>
                        </div>

                        <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="f-s-11 text-muted">Márgenes: ${p.margen_superior_mm}s / ${p.margen_inferior_mm}i / ${p.margen_izquierdo_mm}iz / ${p.margen_derecho_mm}de mm</span>
                            <button type="button" class="btn btn-outline-primary btn-sm btn-ver-versiones-plantilla" data-id="${p.id}" data-nombre="${escaparHtml(p.nombre)}">
                                <i class="fa-solid fa-code-branch me-1"></i> Historial Versiones
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function actualizarSelectPlantillas() {
        const selectVersion = document.getElementById('emitir-plantilla-version-id');
        if (selectVersion) {
            selectVersion.innerHTML = '<option value="">Usar versión activa oficial (Recomendado)</option>';
            plantillasCache.forEach(p => {
                if (p.version_activa) {
                    const opt = document.createElement('option');
                    opt.value = p.version_activa.id;
                    opt.textContent = `${p.nombre} (V${p.version_activa.numero_version} Oficial)`;
                    selectVersion.appendChild(opt);
                }
            });
        }

        const selectPlantillaVersion = document.getElementById('version-plantilla-id');
        if (selectPlantillaVersion) {
            selectPlantillaVersion.innerHTML = plantillasCache.map(p => 
                `<option value="${p.id}">${escaparHtml(p.nombre)} (${escaparHtml(p.codigo)})</option>`
            ).join('');
        }
    }

    async function cargarIncidencias() {
        const tbody = document.querySelector('#tabla-incidencias tbody');
        if (!tbody) return;

        try {
            const resp = await fetch('/api/documentos/incidencias', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error('Error al cargar bitácora de auditoría.');
            const data = await resp.json();
            incidenciasCache = data.datos || [];
            renderizarIncidencias();
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${escaparHtml(err.message)}</td></tr>`;
        }
    }

    function renderizarIncidencias() {
        const tbody = document.querySelector('#tabla-incidencias tbody');
        if (!tbody) return;

        if (incidenciasCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-success"><i class="fa-solid fa-circle-check me-1"></i> No se han registrado incidencias de integridad documental. Todos los documentos están íntegros.</td></tr>';
            return;
        }

        tbody.innerHTML = incidenciasCache.map(i => `
            <tr>
                <td class="f-w-700">#${i.id}</td>
                <td>Doc #${i.documento_emitido_id}</td>
                <td><span class="badge bg-light-danger text-danger f-w-600">${escaparHtml(i.tipo_incidencia)}</span></td>
                <td class="f-s-12 text-secondary">${escaparHtml(i.descripcion)}</td>
                <td class="f-s-11 text-muted">${formatearFecha(i.detectado_en)}</td>
                <td class="text-center">
                    ${i.resuelto ? '<span class="badge bg-light-success text-success">Resuelta</span>' : '<span class="badge bg-light-warning text-warning">Abierta</span>'}
                </td>
                <td class="f-s-11 text-muted">${escaparHtml(i.resolucion_notas || '—')}</td>
            </tr>
        `).join('');
    }

    function actualizarKpiEmitidos(total) {
        const el = document.getElementById('kpi-total-emitidos');
        if (el) el.textContent = total;
    }

    // =========================================================================
    // Operaciones del Dominio
    // =========================================================================

    // Emitir Contrato
    const btnAbrirEmitir = document.getElementById('btn-abrir-modal-emitir');
    btnAbrirEmitir?.addEventListener('click', () => {
        document.getElementById('form-emitir-contrato')?.reset();
        modalEmitir?.show();
    });

    const formEmitir = document.getElementById('form-emitir-contrato');
    formEmitir?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const arrendamientoId = document.getElementById('emitir-arrendamiento-id')?.value;
        const versionId = document.getElementById('emitir-plantilla-version-id')?.value;
        const esBorrador = document.getElementById('emitir-es-borrador')?.checked;

        if (!arrendamientoId) {
            Swal.fire('Atención', 'Debe especificar el ID de arrendamiento.', 'warning');
            return;
        }

        const btn = document.getElementById('btn-confirmar-emision');
        if (btn) btn.disabled = true;

        try {
            const body = {
                es_borrador: esBorrador,
            };
            if (versionId) {
                body.plantilla_version_id = parseInt(versionId, 10);
            }

            const resp = await fetch(`/api/arrendamientos/${arrendamientoId}/emitir-contrato`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(body),
            });

            if (esBorrador && resp.status === 200 && resp.headers.get('content-type')?.includes('application/pdf')) {
                // Descarga o visualización directa del borrador
                const blob = await resp.blob();
                const url = URL.createObjectURL(blob);
                modalEmitir?.hide();
                abrirVisorPdf(url, `Borrador Contrato Arrendamiento #${arrendamientoId}`, 'Documento preliminar no válido');
                return;
            }

            const resJson = await resp.json();
            if (!resp.ok) {
                throw new Error(resJson.error || 'Falla al emitir el contrato.');
            }

            modalEmitir?.hide();
            Swal.fire({
                icon: 'success',
                title: 'Contrato Emitido',
                html: `Folio oficial asignado: <strong>${escaparHtml(resJson.documento.codigo_folio)}</strong><br>Hash SHA-256 verificado.`,
                confirmButtonText: 'Ver Documento',
                showCancelButton: true,
                cancelButtonText: 'Aceptar',
            }).then((result) => {
                if (result.isConfirmed) {
                    abrirVisorPdf(`/api/documentos/${resJson.documento.id}/descargar?inline=1`, resJson.documento.codigo_folio, `Hash SHA-256: ${resJson.documento.hash_pdf_sha256}`);
                }
            });

            cargarDocumentos();
        } catch (err) {
            Swal.fire('Error de Emisión', err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    // Nueva Versión de Plantilla
    const btnAbrirVersion = document.getElementById('btn-abrir-modal-version');
    btnAbrirVersion?.addEventListener('click', () => {
        document.getElementById('form-nueva-version')?.reset();
        modalVersion?.show();
    });

    const formVersion = document.getElementById('form-nueva-version');
    formVersion?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const plantillaId = document.getElementById('version-plantilla-id')?.value;
        const titulo = document.getElementById('version-titulo-doc')?.value;
        const notas = document.getElementById('version-notas')?.value;
        const cuerpoHtml = document.getElementById('version-cuerpo-html')?.value;
        const estilosCss = document.getElementById('version-estilos-css')?.value;
        const activar = document.getElementById('version-activar-inmediata')?.checked;

        if (!plantillaId || !titulo || !cuerpoHtml) {
            Swal.fire('Atención', 'Complete los campos obligatorios.', 'warning');
            return;
        }

        try {
            const resp = await fetch(`/api/documentos/plantillas/${plantillaId}/versiones`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    titulo_documento: titulo,
                    notas_version: notas,
                    cuerpo_html: cuerpoHtml,
                    estilos_css: estilosCss,
                    activar_inmediatamente: activar,
                }),
            });

            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Error al crear la versión.');

            modalVersion?.hide();
            Swal.fire('Éxito', 'Nueva versión de plantilla publicada exitosamente.', 'success');
            cargarPlantillas();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    });

    // Delegación de eventos en tabla de documentos
    document.querySelector('#tabla-documentos tbody')?.addEventListener('click', async (e) => {
        const btnVerificar = e.target.closest('.btn-verificar-doc');
        if (btnVerificar) {
            const docId = btnVerificar.dataset.id;
            try {
                const resp = await fetch(`/api/documentos/${docId}/verificar`);
                const data = await resp.json();
                if (resp.ok && data.estado_integridad === 'VALIDO') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Integridad Confirmada',
                        html: `El archivo físico coincide al 100% con su hash criptográfico registrado:<br><code class="f-s-11">${escaparHtml(data.hash_sha256)}</code><br>Tamaño: ${data.tamano_bytes} bytes.`,
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Alerta de Integridad',
                        html: `Discrepancia detectada: <strong>${escaparHtml(data.error || 'Hash no coincide')}</strong>.<br>Se requiere regeneración asistida.`,
                    });
                    cargarIncidencias();
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
            return;
        }

        const btnPrevisualizar = e.target.closest('.btn-previsualizar-doc');
        if (btnPrevisualizar) {
            const docId = btnPrevisualizar.dataset.id;
            const doc = documentosCache.find(d => String(d.id) === String(docId));
            const titulo = doc ? doc.codigo_folio : `Documento #${docId}`;
            const subtitulo = doc ? `SHA-256: ${doc.hash_pdf_sha256}` : '';
            abrirVisorPdf(`/api/documentos/${docId}/descargar?inline=1`, titulo, subtitulo);
            return;
        }

        const btnAnular = e.target.closest('.btn-anular-doc');
        if (btnAnular) {
            const docId = btnAnular.dataset.id;
            const folio = btnAnular.dataset.folio;
            document.getElementById('anular-doc-id').value = docId;
            document.getElementById('anular-doc-folio').textContent = folio;
            document.getElementById('anular-doc-motivo').value = '';
            modalAnular?.show();
            return;
        }

        const btnRegenerar = e.target.closest('.btn-regenerar-doc');
        if (btnRegenerar) {
            const docId = btnRegenerar.dataset.id;
            Swal.fire({
                title: '¿Regenerar Documento?',
                text: 'Se reconstruirá el archivo PDF a partir del snapshot HTML congelado inmutable. Esto resolverá discrepancias de hash o faltantes físicos.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, regenerar',
                cancelButtonText: 'Cancelar',
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const resp = await fetch(`/api/documentos/${docId}/regenerar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': csrfToken,
                            },
                            body: JSON.stringify({ motivo: 'Regeneración manual desde interfaz Alina' }),
                        });
                        const data = await resp.json();
                        if (!resp.ok) throw new Error(data.error || 'Error al regenerar documento.');

                        Swal.fire('Regenerado', 'El PDF físico fue reconstruido exitosamente y su integridad quedó restablecida.', 'success');
                        cargarDocumentos();
                        cargarIncidencias();
                    } catch (err) {
                        Swal.fire('Error', err.message, 'error');
                    }
                }
            });
            return;
        }
    });

    // Formulario de anulación
    const formAnular = document.getElementById('form-anular-documento');
    formAnular?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const docId = document.getElementById('anular-doc-id')?.value;
        const motivo = document.getElementById('anular-doc-motivo')?.value;

        if (!docId || !motivo.trim()) {
            Swal.fire('Atención', 'El motivo de anulación es obligatorio.', 'warning');
            return;
        }

        try {
            const resp = await fetch(`/api/documentos/${docId}/anular`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ motivo: motivo.trim() }),
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Error al anular documento.');

            modalAnular?.hide();
            Swal.fire('Anulado', 'El documento ha sido marcado formalmente como anulado.', 'success');
            cargarDocumentos();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    });

    // Visor PDF
    function abrirVisorPdf(url, titulo, subtitulo) {
        const iframe = document.getElementById('visor-iframe-pdf');
        const titEl = document.getElementById('visor-titulo');
        const subEl = document.getElementById('visor-subtitulo');
        const descBtn = document.getElementById('visor-btn-descarga-directa');
        const metaEl = document.getElementById('visor-metadatos-sha256');

        if (iframe) iframe.src = url;
        if (titEl) titEl.textContent = titulo;
        if (subEl) subEl.textContent = subtitulo;
        if (descBtn) descBtn.href = url.replace('?inline=1', '');
        if (metaEl) metaEl.textContent = subtitulo;

        modalVisor?.show();
    }

    // Filtros
    document.getElementById('filtro-docs-termino')?.addEventListener('input', renderizarDocumentos);
    document.getElementById('filtro-docs-origen')?.addEventListener('change', renderizarDocumentos);
    document.getElementById('filtro-docs-estado')?.addEventListener('change', renderizarDocumentos);

    // Botones de recarga
    document.getElementById('btn-recargar-docs')?.addEventListener('click', cargarDocumentos);
    document.getElementById('btn-recargar-plantillas')?.addEventListener('click', cargarPlantillas);
    document.getElementById('btn-recargar-incidencias')?.addEventListener('click', cargarIncidencias);

    // Inicialización
    cargarDocumentos();
    cargarPlantillas();
    cargarIncidencias();
});
