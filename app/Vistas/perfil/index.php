<?php

declare(strict_types=1);

/**
 * Vista de Mi Perfil de Usuario — Camargo PMS (UI-ALINA-1B).
 *
 * Basada en la arquitectura visual de Alina profile.html.
 * Aplica con estricto rigor el principio vinculante:
 * PERSONA ≠ USUARIO
 *
 * Muestra separadamente la información soberana de la Persona humana
 * y las credenciales/metadatos de acceso de la cuenta de Usuario.
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array<string, mixed>|null $detalleUsuario
 * @var \CamargoPMS\Modelos\Persona|null $persona
 */
?>
<div class="row">
    <!-- Columna Izquierda: Ficha y Fotografía de Perfil (profile.html) -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card equal-card">
            <div class="card-body">
                <div class="profile-container">
                    <!-- Fotografía con Previsualizador Dinámico Alina -->
                    <div class="image-details position-relative text-center">
                        <div class="profile-pic mx-auto position-relative" style="width: 120px; height: 120px;">
                            <div class="avatar-preview">
                                <div id="imgPreview" style="background-image: url('<?= e($urlFoto ?? url_asset('images/avatar/01.png')) ?>');"></div>
                            </div>
                            <div class="avatar-edit">
                                <input type="file" id="imageUpload" accept=".png, .jpg, .jpeg" aria-label="Seleccionar fotografía de perfil">
                                <label for="imageUpload" title="Seleccionar fotografía">
                                    <i class="fa-solid fa-camera f-s-14 text-dark"></i>
                                </label>
                            </div>
                        </div>

                        <!-- Botones de Acción de Fotografía (UI-ALINA-1B-C1) -->
                        <div id="contenedorAccionesFoto" class="mt-3 d-none">
                            <button type="button" id="btnGuardarFoto" class="btn btn-primary btn-sm me-1">
                                <i class="fa-solid fa-cloud-arrow-up me-1"></i> <span id="textoBtnGuardar">Guardar</span>
                            </button>
                            <button type="button" id="btnCancelarFoto" class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-xmark me-1"></i> Cancelar
                            </button>
                        </div>

                        <?php if (!empty($fotoRuta)): ?>
                            <div id="contenedorEliminarFoto" class="mt-2">
                                <button type="button" id="btnEliminarFoto" class="btn btn-outline-danger btn-sm f-s-12">
                                    <i class="fa-solid fa-trash me-1"></i> Restablecer avatar
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Alertas de Estado y Feedback -->
                        <div id="alertaFoto" class="mt-2 d-none alert alert-dismissible fade show f-s-12 text-center" role="alert">
                            <span id="mensajeAlertaFoto"></span>
                        </div>
                    </div>

                    <!-- Datos del Sujeto -->
                    <div class="person-details mt-4 text-center">
                        <h5 class="f-w-600 mb-1 text-dark">
                            <?= e($persona !== null ? $persona->obtenerNombreCompleto() : $usuario->obtenerNombreUsuario()) ?>
                        </h5>
                        <p class="text-secondary f-s-13 mb-3">
                            <i class="fa-solid fa-user-tag me-1"></i>Persona Natural #<?= (int) ($persona !== null ? $persona->obtenerId() : 0) ?>
                        </p>

                        <!-- Roles RBAC Asignados -->
                        <div class="d-flex flex-wrap justify-content-center gap-1 mb-3">
                            <?php if (!empty($detalleUsuario['roles'])): ?>
                                <?php foreach ($detalleUsuario['roles'] as $rol): ?>
                                    <?= insignia_badge((string) $rol['nombre'], 'primary', 'fa-solid fa-shield-halved', 'f-s-11') ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary f-s-11">Sin rol asignado</span>
                            <?php endif; ?>
                        </div>

                        <!-- Estado de la Cuenta -->
                        <div class="mb-3">
                            <span class="f-s-12 text-secondary d-block mb-1">Estado de la cuenta:</span>
                            <?= insignia_estado((string) $usuario->obtenerEstado(), false, 'f-s-12 px-3 py-1') ?>
                        </div>

                        <!-- Aviso de Separación Ontológica de Identidad -->
                        <div class="alert bg-light border p-2 text-start mt-4 mb-0 f-s-12 text-secondary rounded">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fa-solid fa-circle-info text-primary"></i>
                                <span class="f-w-600 text-dark">Arquitectura de Identidad:</span>
                            </div>
                            <span><strong>PERSONA ≠ USUARIO:</strong> Los datos civiles pertenecen al maestro de Personas; el usuario administra exclusivamente el acceso seguro al PMS.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Pestañas de Detalle Separado (Persona vs Usuario) -->
    <div class="col-xl-8 col-lg-7">
        <div class="card">
            <div class="card-body">
                <!-- Pestañas de Navegación Alina -->
                <ul class="nav nav-tabs app-tabs-primary bg-light-primary d-inline-flex b-r-20 p-2 mb-4 w-100" role="tablist">
                    <li class="nav-item flex-fill text-center" role="presentation">
                        <button class="nav-link active b-r-15 w-100" id="tab-persona" data-bs-toggle="tab" data-bs-target="#panel-persona" type="button" role="tab" aria-controls="panel-persona" aria-selected="true">
                            <i class="fa-solid fa-id-card pe-2"></i>Persona Natural
                        </button>
                    </li>
                    <li class="nav-item flex-fill text-center" role="presentation">
                        <button class="nav-link b-r-15 w-100" id="tab-usuario" data-bs-toggle="tab" data-bs-target="#panel-usuario" type="button" role="tab" aria-controls="panel-usuario" aria-selected="false">
                            <i class="fa-solid fa-user-lock pe-2"></i>Cuenta de Acceso
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="perfilTabContent">
                    <!-- Panel 1: Datos Soberanos de la Persona -->
                    <div class="tab-pane fade show active" id="panel-persona" role="tabpanel" aria-labelledby="tab-persona">
                        <h6 class="f-w-600 text-dark mb-3">
                            <i class="fa-solid fa-user text-primary me-2"></i>Información Personal y Civil
                        </h6>

                        <?php if ($persona !== null): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-4">
                                <tbody>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13" style="width: 30%;">Nombres:</th>
                                        <td class="f-s-14 f-w-500 text-dark"><?= e($persona->obtenerNombres()) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Apellido Paterno:</th>
                                        <td class="f-s-14 text-dark"><?= e($persona->obtenerApellidoPaterno() ?? '—') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Apellido Materno:</th>
                                        <td class="f-s-14 text-dark"><?= e($persona->obtenerApellidoMaterno() ?? '—') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Género:</th>
                                        <td class="f-s-14 text-dark"><?= e($persona->obtenerGenero() ?? 'No especificado') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Fecha de Nacimiento:</th>
                                        <td class="f-s-14 text-dark"><?= e($persona->obtenerFechaNacimiento() ?? '—') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Dirección de Residencia:</th>
                                        <td class="f-s-14 text-dark"><?= e($persona->obtenerDireccion() ?? 'No registrada') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Documentos de Identidad de la Persona -->
                        <h6 class="f-w-600 text-dark mb-2 mt-4">
                            <i class="fa-solid fa-address-card text-primary me-2"></i>Documentos de Identificación
                        </h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12">Tipo de Documento</th>
                                        <th class="f-s-12">Número</th>
                                        <th class="f-s-12 text-center">Condición</th>
                                        <th class="f-s-12 text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $docs = $persona->obtenerDocumentos(); ?>
                                    <?php if (!empty($docs)): ?>
                                        <?php foreach ($docs as $doc): ?>
                                        <tr>
                                            <td class="f-s-13 f-w-500 text-dark">
                                                <?= e($doc->obtenerTipoDocumento()?->obtenerNombre() ?? 'Documento') ?>
                                            </td>
                                            <td class="f-s-13 font-monospace text-dark">
                                                <?= e($doc->obtenerNumeroDocumento()) ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($doc->esPrincipal()): ?>
                                                    <?= insignia_chip('Principal', 'primary', 'fa-solid fa-star', 'f-s-11') ?>
                                                <?php else: ?>
                                                    <span class="text-muted f-s-12">Secundario</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= insignia_estado($doc->obtenerEstado(), false, 'f-s-11') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3 f-s-13">
                                                No hay documentos registrados para esta persona.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Contactos Registrados de la Persona -->
                        <h6 class="f-w-600 text-dark mb-2 mt-4">
                            <i class="fa-solid fa-phone-volume text-primary me-2"></i>Canales de Contacto
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12">Tipo de Contacto</th>
                                        <th class="f-s-12">Valor / Contacto</th>
                                        <th class="f-s-12 text-center">Condición</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $contactos = $persona->obtenerContactos(); ?>
                                    <?php if (!empty($contactos)): ?>
                                        <?php foreach ($contactos as $c): ?>
                                        <tr>
                                            <td class="f-s-13 text-secondary text-uppercase">
                                                <?= e(str_replace('_', ' ', $c->obtenerTipoContacto())) ?>
                                            </td>
                                            <td class="f-s-13 f-w-500 text-dark">
                                                <?= e($c->obtenerValor()) ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($c->esPrincipal()): ?>
                                                    <?= insignia_chip('Principal', 'primary', 'fa-solid fa-star', 'f-s-11') ?>
                                                <?php else: ?>
                                                    <span class="text-muted f-s-12">Alternativo</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3 f-s-13">
                                                No hay contactos registrados para esta persona.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No se localizó el registro de Persona Natural asociado a esta cuenta.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Datos de la Cuenta de Usuario y Sesiones -->
                    <div class="tab-pane fade" id="panel-usuario" role="tabpanel" aria-labelledby="tab-usuario">
                        <h6 class="f-w-600 text-dark mb-3">
                            <i class="fa-solid fa-user-lock text-primary me-2"></i>Credenciales y Parámetros de Acceso
                        </h6>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <tbody>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13" style="width: 35%;">Nombre de Usuario (Login):</th>
                                        <td class="f-s-14 f-w-600 text-dark"><?= e($usuario->obtenerNombreUsuario()) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Identificador Único (ID Usuario):</th>
                                        <td class="f-s-14 font-monospace text-dark">#<?= (int) $usuario->obtenerId() ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Estado Operativo:</th>
                                        <td><?= insignia_estado((string) $usuario->obtenerEstado(), false, 'f-s-12') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Fecha de Creación de Cuenta:</th>
                                        <td class="f-s-14 text-dark"><?= e((string) $usuario->obtenerCreadoEn()) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Último Acceso Registrado:</th>
                                        <td class="f-s-14 text-dark"><?= e((string) ($usuario->obtenerUltimoAccesoEn() ?? 'Primer inicio de sesión')) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light text-secondary f-s-13">Último Cambio de Contraseña:</th>
                                        <td class="f-s-14 text-dark"><?= e((string) ($usuario->obtenerContrasenaCambiadaEn() ?? 'Sin registros')) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Gestión de Sesiones Activas -->
                        <h6 class="f-w-600 text-dark mb-2 mt-4">
                            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Sesiones y Seguridad Concurrente
                        </h6>
                        <div class="card bg-light border p-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <span class="d-block f-w-600 text-dark f-s-14">Sesiones concurrentes del usuario</span>
                                    <span class="text-secondary f-s-13">
                                        Actualmente tienes <strong><?= (int) ($detalleUsuario['total_sesiones_activas'] ?? 1) ?></strong> sesión(es) activa(s) registrada(s).
                                    </span>
                                </div>
                                <div>
                                    <a href="<?= url_ruta('/seguridad/sesiones') ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-shield-halved"></i>
                                        <span>Consola de Sesiones</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script interactivo en Vanilla JS para persistencia y UX de fotografía Alina (profile.html / UI-ALINA-1B-C1) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputFoto = document.getElementById('imageUpload');
    const previewDiv = document.getElementById('imgPreview');
    const contenedorAcciones = document.getElementById('contenedorAccionesFoto');
    const btnGuardar = document.getElementById('btnGuardarFoto');
    const btnCancelar = document.getElementById('btnCancelarFoto');
    const btnEliminar = document.getElementById('btnEliminarFoto');
    const contenedorEliminar = document.getElementById('contenedorEliminarFoto');
    const alertaFoto = document.getElementById('alertaFoto');
    const mensajeAlerta = document.getElementById('mensajeAlertaFoto');
    const textoBtnGuardar = document.getElementById('textoBtnGuardar');

    let urlFotoPersistida = <?= json_encode($urlFoto ?? url_asset('images/avatar/01.png')) ?>;
    const tokenCsrf = <?= json_encode($csrf_token ?? '') ?>;
    const rutaSubirFoto = <?= json_encode(url_ruta('/perfil/foto')) ?>;
    const rutaEliminarFoto = <?= json_encode(url_ruta('/perfil/foto/eliminar')) ?>;

    function mostrarAlerta(mensaje, esExito) {
        if (!alertaFoto || !mensajeAlerta) return;
        alertaFoto.className = 'mt-2 alert alert-dismissible fade show f-s-12 text-center alert-' + (esExito ? 'success' : 'danger');
        mensajeAlerta.textContent = mensaje;
        alertaFoto.classList.remove('d-none');
    }

    function ocultarAlerta() {
        if (alertaFoto) {
            alertaFoto.classList.add('d-none');
        }
    }

    // 1. Selección y previsualización local (FileReader)
    if (inputFoto && previewDiv) {
        inputFoto.addEventListener('change', function () {
            ocultarAlerta();

            if (this.files && this.files[0]) {
                const archivo = this.files[0];

                // Validación cliente de tipo MIME
                if (!archivo.type.match(/^image\/(png|jpe?g)$/i)) {
                    mostrarAlerta('Formato no permitido. Se aceptan exclusivamente archivos JPG y PNG.', false);
                    this.value = '';
                    return;
                }

                // Validación cliente de tamaño (máx 2MB)
                if (archivo.size > 2 * 1024 * 1024) {
                    mostrarAlerta('El archivo supera el tamaño máximo permitido de 2 MB.', false);
                    this.value = '';
                    return;
                }

                const lector = new FileReader();
                lector.onload = function (evento) {
                    previewDiv.style.backgroundImage = 'url(' + evento.target.result + ')';
                    if (contenedorAcciones) {
                        contenedorAcciones.classList.remove('d-none');
                    }
                };
                lector.readAsDataURL(archivo);
            }
        });
    }

    // 2. Cancelar selección y restaurar imagen persistida
    if (btnCancelar) {
        btnCancelar.addEventListener('click', function () {
            ocultarAlerta();
            if (inputFoto) inputFoto.value = '';
            if (previewDiv) previewDiv.style.backgroundImage = 'url(' + urlFotoPersistida + ')';
            if (contenedorAcciones) contenedorAcciones.classList.add('d-none');
        });
    }

    // 3. Guardar fotografía en backend (POST asíncrono con CSRF)
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function () {
            if (!inputFoto || !inputFoto.files || !inputFoto.files[0]) {
                mostrarAlerta('Seleccione un archivo de imagen antes de guardar.', false);
                return;
            }

            ocultarAlerta();
            btnGuardar.disabled = true;
            if (btnCancelar) btnCancelar.disabled = true;
            if (textoBtnGuardar) textoBtnGuardar.textContent = 'Guardando...';

            const formData = new FormData();
            formData.append('foto', inputFoto.files[0]);
            formData.append('_csrf_token', tokenCsrf);

            fetch(rutaSubirFoto, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': tokenCsrf
                }
            })
            .then(function (respuesta) {
                return respuesta.json().then(function (datos) {
                    return { estado: respuesta.status, datos: datos };
                });
            })
            .then(function (resultado) {
                btnGuardar.disabled = false;
                if (btnCancelar) btnCancelar.disabled = false;
                if (textoBtnGuardar) textoBtnGuardar.textContent = 'Guardar';

                if (resultado.estado >= 200 && resultado.estado < 300 && resultado.datos.ok) {
                    urlFotoPersistida = resultado.datos.url_foto;
                    if (previewDiv) previewDiv.style.backgroundImage = 'url(' + urlFotoPersistida + ')';
                    if (inputFoto) inputFoto.value = '';
                    if (contenedorAcciones) contenedorAcciones.classList.add('d-none');
                    if (contenedorEliminar) contenedorEliminar.classList.remove('d-none');
                    mostrarAlerta(resultado.datos.mensaje || 'Fotografía actualizada correctamente.', true);
                } else {
                    mostrarAlerta(resultado.datos.error || 'Ocurrió un error al guardar la fotografía.', false);
                }
            })
            .catch(function (error) {
                btnGuardar.disabled = false;
                if (btnCancelar) btnCancelar.disabled = false;
                if (textoBtnGuardar) textoBtnGuardar.textContent = 'Guardar';
                mostrarAlerta('Error de comunicación con el servidor al guardar la fotografía.', false);
            });
        });
    }

    // 4. Restablecer avatar a fallback
    if (btnEliminar) {
        btnEliminar.addEventListener('click', function () {
            if (!confirm('¿Desea restablecer su fotografía de perfil al avatar predeterminado?')) {
                return;
            }

            ocultarAlerta();
            btnEliminar.disabled = true;

            const formData = new FormData();
            formData.append('_csrf_token', tokenCsrf);

            fetch(rutaEliminarFoto, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': tokenCsrf
                }
            })
            .then(function (respuesta) {
                return respuesta.json();
            })
            .then(function (datos) {
                btnEliminar.disabled = false;
                if (datos.ok) {
                    urlFotoPersistida = datos.url_foto;
                    if (previewDiv) previewDiv.style.backgroundImage = 'url(' + urlFotoPersistida + ')';
                    if (contenedorEliminar) contenedorEliminar.classList.add('d-none');
                    mostrarAlerta(datos.mensaje || 'Avatar restablecido correctamente.', true);
                } else {
                    mostrarAlerta(datos.error || 'No se pudo restablecer la fotografía.', false);
                }
            })
            .catch(function () {
                btnEliminar.disabled = false;
                mostrarAlerta('Error de conexión al intentar restablecer el avatar.', false);
            });
        });
    }
});
</script>
