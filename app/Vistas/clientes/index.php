<?php

declare(strict_types=1);

/**
 * Vista de Directorio Comercial de Clientes — Camargo PMS (CLIENTES-1).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var \CamargoPMS\Modelos\ClienteCategoria[] $categorias
 * @var array<string, string> $canales
 * @var array<int, array<string, mixed>> $tiposDocumento
 * @var array<string, mixed> $paisDefault
 * @var array<int, array<string, mixed>> $departamentos
 * @var array<int, array<string, mixed>> $paises
 * @var array{total: int, activos: int, bloqueados: int} $kpis
 * @var array{puede_crear: bool, puede_editar: bool, puede_bloquear: bool, puede_gestionar_categorias: bool} $permisos
 * @var string $csrf_token
 * @var string $titulo
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo de Clientes -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-users f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Directorio Comercial de Clientes</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Gestión comercial, categorización y trazabilidad 360°. Principio: <strong>PERSONA ≠ CLIENTE</strong> (CLIENTE → PERSONA).
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if ($permisos['puede_crear']): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-nuevo-cliente">
                        <i class="fa-solid fa-user-plus me-1"></i> Alta de Cliente
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-recargar-clientes">
                        <i class="fa-solid fa-rotate me-1"></i> Actualizar
                    </button>
                </div>
            </div>

            <!-- Métricas Principales Rápidas -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-3">
                    <div class="col-md-4 col-12">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-primary-subtle text-primary p-2 rounded-circle me-3">
                                <i class="fa-solid fa-id-card f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Total Clientes</span>
                                <h5 class="mb-0 f-w-700" id="kpi-total-clientes"><?= (int) $kpis['total'] ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-success-subtle text-success p-2 rounded-circle me-3">
                                <i class="fa-solid fa-user-check f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Clientes Activos</span>
                                <h5 class="mb-0 f-w-700 text-success" id="kpi-activos-clientes"><?= (int) $kpis['activos'] ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="d-flex align-items-center p-2 bg-white rounded border">
                            <span class="bg-danger-subtle text-danger p-2 rounded-circle me-3">
                                <i class="fa-solid fa-user-lock f-s-18"></i>
                            </span>
                            <div>
                                <span class="text-secondary f-s-12 d-block">Clientes Bloqueados</span>
                                <h5 class="mb-0 f-w-700 text-danger" id="kpi-bloqueados-clientes"><?= (int) $kpis['bloqueados'] ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros de Directorio -->
            <div class="card-body p-3 bg-light-subtle border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4 col-12">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="filtro-busqueda-cliente"
                                   placeholder="Buscar por código, nombres, DNI/RUC, teléfono..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-limpiar-busqueda" title="Limpiar búsqueda">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtro-categoria-cliente">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= (int) $cat->obtenerId() ?>"><?= e($cat->obtenerNombre()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <select class="form-select form-select-sm" id="filtro-estado-cliente">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                            <option value="BLOQUEADO">BLOQUEADO</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-12">
                        <select class="form-select form-select-sm" id="filtro-canal-cliente">
                            <option value="">Todos los canales</option>
                            <?php foreach ($canales as $codCanal => $nomCanal): ?>
                                <option value="<?= e($codCanal) ?>"><?= e($nomCanal) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla de Clientes -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabla-clientes">
                        <thead class="table-light f-s-13">
                            <tr>
                                <th class="ps-3" style="width: 110px;">Código</th>
                                <th>Cliente / Identidad (Persona)</th>
                                <th>Documento</th>
                                <th>Contacto</th>
                                <th>Categoría</th>
                                <th>Historial</th>
                                <th>Estado</th>
                                <th class="text-end pe-3" style="width: 140px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-clientes" class="f-s-13">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Cargando clientes...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center border-top">
                <span class="text-secondary f-s-12" id="info-paginacion-clientes">Mostrando 0 de 0 registros</span>
                <nav aria-label="Navegación de páginas">
                    <ul class="pagination pagination-sm mb-0" id="paginacion-clientes">
                        <!-- Generado dinámicamente -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- MODALES DEL MÓDULO CLIENTES                                                               -->
<!-- ========================================================================================= -->

<!-- 1. MODAL: ALTA DE CLIENTE -->
<div class="modal fade" id="modal-alta-cliente" tabindex="-1" aria-labelledby="modalAltaClienteTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalAltaClienteTitulo">
                    <i class="fa-solid fa-user-plus text-primary me-2"></i> Alta de Cliente Comercial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-alta-cliente" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <!-- Selector de Modo de Alta -->
                    <div class="btn-group w-100 mb-3" role="group" aria-label="Modo de alta">
                        <input type="radio" class="btn-check" name="modo_alta" id="modo-buscar-persona" value="EXISTENTE" checked>
                        <label class="btn btn-outline-primary" for="modo-buscar-persona">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Vincular Persona Existente
                        </label>

                        <input type="radio" class="btn-check" name="modo_alta" id="modo-nueva-persona" value="NUEVA">
                        <label class="btn btn-outline-primary" for="modo-nueva-persona">
                            <i class="fa-solid fa-user-plus me-1"></i> Crear Nueva Persona
                        </label>
                    </div>

                    <!-- SECCIÓN 1: VINCULAR PERSONA EXISTENTE -->
                    <div id="seccion-persona-existente" class="card bg-light-subtle border mb-4">
                        <div class="card-body p-3">
                            <h6 class="f-w-700 f-s-13 text-primary mb-2">
                                <i class="fa-solid fa-address-book me-1"></i> Verificación de Identidad Soberana (PERSONA)
                            </h6>
                            <p class="text-secondary f-s-12 mb-3">
                                Ingrese el número de documento o nombre para asociar una Persona registrada al perfil comercial.
                            </p>
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-buscar-doc">Número de Documento o Búsqueda</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="alta-buscar-doc" placeholder="Ej. 12345678 o Nombre" autocomplete="off">
                                        <button class="btn btn-primary" type="button" id="btn-verificar-persona">
                                            <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <span id="alta-persona-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                                    <span id="alta-persona-feedback" class="f-s-12"></span>
                                </div>
                            </div>

                            <!-- Ficha de Persona Seleccionada -->
                            <div id="box-persona-seleccionada" class="p-3 bg-white rounded border d-none">
                                <input type="hidden" id="alta-persona-id" name="persona_id" value="">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 f-w-700 text-dark" id="sel-persona-nombre">-</h6>
                                        <div class="text-secondary f-s-12">
                                            <span class="badge bg-secondary-subtle text-secondary me-1" id="sel-persona-doc">-</span>
                                            <span class="me-2" id="sel-persona-tel"><i class="fa-solid fa-phone me-1"></i>-</span>
                                            <span id="sel-persona-email"><i class="fa-solid fa-envelope me-1"></i>-</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-quitar-persona-sel" title="Quitar selección">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: CREAR NUEVA PERSONA ATÓMICAMENTE -->
                    <div id="seccion-persona-nueva" class="card bg-light-subtle border mb-4 d-none">
                        <div class="card-body p-3">
                            <h6 class="f-w-700 f-s-13 text-primary mb-3">
                                <i class="fa-solid fa-id-card me-1"></i> Identidad Biológica y Civil de la Persona
                            </h6>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-nombres">Nombres <span class="text-danger">*</span></label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" name="nombres" id="alta-nombres" placeholder="Nombres">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <label class="form-label f-s-12 f-w-600" for="alta-paterno">Apellido Paterno <span class="text-danger">*</span></label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" name="apellido_paterno" id="alta-paterno" placeholder="Apellido paterno">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <label class="form-label f-s-12 f-w-600" for="alta-materno">Apellido Materno</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-user position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" name="apellido_materno" id="alta-materno" placeholder="Apellido materno">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4 col-6">
                                    <label class="form-label f-s-12 f-w-600" for="alta-genero">Género</label>
                                    <select class="form-select form-select-sm" name="genero" id="alta-genero">
                                        <option value="NO_ESPECIFICADO">No especificado</option>
                                        <option value="MASCULINO">Masculino</option>
                                        <option value="FEMENINO">Femenino</option>
                                        <option value="OTRO">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4 col-6">
                                    <label class="form-label f-s-12 f-w-600" for="alta-nacimiento">Fecha de Nacimiento</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-cake-candles position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="date" class="form-control form-control-sm ps-5" name="fecha_nacimiento" id="alta-nacimiento">
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-nacionalidad">País de Nacionalidad</label>
                                    <select class="form-select basic-select2" name="pais_nacionalidad_id" id="alta-nacionalidad">
                                        <?php foreach ($paises as $p): ?>
                                            <option value="<?= (int) $p['id'] ?>" <?= ($p['codigo_iso2'] === 'PE' ? 'selected' : '') ?>>
                                                <?= e($p['nombre']) ?> (<?= e($p['codigo_iso2']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Documento Principal -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-4 col-6">
                                    <label class="form-label f-s-12 f-w-600" for="alta-tipo-doc">Tipo Documento</label>
                                    <select class="form-select basic-select2" name="tipo_documento_id" id="alta-tipo-doc">
                                        <?php foreach ($tiposDocumento as $td): ?>
                                            <option value="<?= (int) $td['id'] ?>"><?= e($td['codigo']) ?> — <?= e($td['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5 col-6">
                                    <label class="form-label f-s-12 f-w-600" for="alta-num-doc">Número Documento</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-id-card position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" name="numero_documento" id="alta-num-doc" placeholder="Número">
                                    </div>
                                </div>
                                <div class="col-md-3 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-pais-emisor">País Emisor</label>
                                    <select class="form-select basic-select2" name="pais_emisor_id" id="alta-pais-emisor">
                                        <?php foreach ($paises as $p): ?>
                                            <option value="<?= (int) $p['id'] ?>" <?= ($p['codigo_iso2'] === 'PE' ? 'selected' : '') ?>>
                                                <?= e($p['codigo_iso2']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Contactos -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-telefono">Teléfono / Celular</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-phone position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" name="telefono" id="alta-telefono" placeholder="+51 987654321">
                                    </div>
                                    <div class="form-check form-switch app-switch mt-1 d-flex align-items-center gap-2">
                                        <input class="form-check-input mt-0" type="checkbox" name="es_whatsapp" id="alta-es-whatsapp" value="1" checked>
                                        <label class="form-check-label f-s-11 text-secondary" for="alta-es-whatsapp">
                                            <i class="fa-brands fa-whatsapp text-success me-1"></i> WhatsApp
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-email">Correo Electrónico</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-envelope position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="email" class="form-control form-control-sm ps-5" name="email" id="alta-email" placeholder="cliente@correo.com">
                                    </div>
                                </div>
                            </div>

                            <!-- Residencia Geográfica -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-4 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-pais-residencia">País de Residencia</label>
                                    <select class="form-select form-select-sm" name="pais_residencia_id" id="alta-pais-residencia">
                                        <?php foreach ($paises as $p): ?>
                                            <option value="<?= (int) $p['id'] ?>" data-iso="<?= e($p['codigo_iso2']) ?>" <?= ($p['codigo_iso2'] === 'PE' ? 'selected' : '') ?>>
                                                <?= e($p['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Cascada INEI para Perú -->
                                <div class="col-md-8 col-12" id="box-geografia-peru">
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <label class="form-label f-s-12 f-w-600" for="alta-departamento">Departamento</label>
                                            <select class="form-select form-select-sm" id="alta-departamento">
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($departamentos as $dep): ?>
                                                    <option value="<?= (int) $dep['id'] ?>"><?= e($dep['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label f-s-12 f-w-600" for="alta-provincia">Provincia</label>
                                            <select class="form-select form-select-sm" id="alta-provincia" disabled>
                                                <option value="">Seleccione...</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label f-s-12 f-w-600" for="alta-distrito">Distrito</label>
                                            <select class="form-select form-select-sm" name="distrito_id" id="alta-distrito" disabled>
                                                <option value="">Seleccione...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Domicilio Extranjero -->
                                <div class="col-md-8 col-12 d-none" id="box-geografia-extranjera">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label f-s-12 f-w-600" for="alta-region-ext">Región / Estado</label>
                                            <div class="icon-control position-relative">
                                                <i class="fa-solid fa-earth-americas position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                                <input type="text" class="form-control form-control-sm ps-5" name="region_residencia_extranjera" id="alta-region-ext" placeholder="Ej. Antioquia">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label f-s-12 f-w-600" for="alta-ciudad-ext">Ciudad</label>
                                            <div class="icon-control position-relative">
                                                <i class="fa-solid fa-city position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                                <input type="text" class="form-control form-control-sm ps-5" name="ciudad_residencia_extranjera" id="alta-ciudad-ext" placeholder="Ej. Medellín">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-direccion">Dirección Domiciliaria</label>
                                    <div class="icon-control position-relative">
                                        <i class="fa-solid fa-location-dot position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                                        <input type="text" class="form-control form-control-sm ps-5" name="direccion" id="alta-direccion" placeholder="Av. Principal 123, Urb...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: PERFIL COMERCIAL DEL CLIENTE -->
                    <div class="card bg-light-subtle border">
                        <div class="card-body p-3">
                            <h6 class="f-w-700 f-s-13 text-primary mb-3">
                                <i class="fa-solid fa-briefcase me-1"></i> Parámetros de la Relación Comercial
                            </h6>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-categoria">Categoría Comercial <span class="text-danger">*</span></label>
                                    <select class="form-select basic-select2" name="categoria_id" id="alta-categoria" required>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= (int) $cat->obtenerId() ?>" <?= $cat->esPredeterminada() ? 'selected' : '' ?>>
                                                <?= e($cat->obtenerNombre()) ?> (<?= e($cat->obtenerCodigo()) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-canal">Canal de Captación</label>
                                    <select class="form-select basic-select2" name="canal_captacion" id="alta-canal">
                                        <option value="">Seleccione canal de origen...</option>
                                        <?php foreach ($canales as $codCanal => $nomCanal): ?>
                                            <option value="<?= e($codCanal) ?>"><?= e($nomCanal) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-preferencias">Preferencias del Cliente</label>
                                    <div class="icon-control position-relative icon-textarea">
                                        <i class="fa-solid fa-star position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                        <textarea class="form-control form-control-sm ps-5" name="preferencias" id="alta-preferencias" rows="2"
                                                  placeholder="Preferencias declaradas (piso alto, almohadas extras, silencioso...)"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-s-12 f-w-600" for="alta-observaciones">Observaciones Comerciales Internas</label>
                                    <div class="icon-control position-relative icon-textarea">
                                        <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                        <textarea class="form-control form-control-sm ps-5" name="observaciones" id="alta-observaciones" rows="2"
                                                  placeholder="Notas operativas y comerciales de recepción / atención..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-cliente">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Registrar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL BLOQUEAR CLIENTE -->
<div class="modal fade" id="modal-bloquear-cliente" tabindex="-1" aria-labelledby="modalBloquearTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title f-w-700" id="modalBloquearTitulo">
                    <i class="fa-solid fa-user-lock me-2"></i> Bloquear Cliente Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-bloquear-cliente" class="app-form app-icon-form" novalidate>
                <input type="hidden" id="bloquear-cliente-id" value="">
                <div class="modal-body p-4">
                    <div class="alert alert-danger f-s-13 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        El bloqueo comercial genera una advertencia visual prominente en la Ficha 360° y en la recepción. Requiere un motivo justificado obligatorio.
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600">Cliente a bloquear:</label>
                        <p class="f-w-700 mb-0" id="bloquear-cliente-nombre">-</p>
                    </div>
                    <div class="mb-2">
                        <label class="form-label f-s-12 f-w-600" for="bloquear-motivo">Motivo del Bloqueo <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-shield-halved position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="bloquear-motivo" rows="3" required
                                      placeholder="Detalle la justificación comercial o de seguridad para el bloqueo..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-confirmar-bloqueo">
                        <i class="fa-solid fa-lock me-1"></i> Confirmar Bloqueo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT DEL MÓDULO CLIENTES -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global').value;

    let paginaActual = 1;
    let limiteActual = 20;

    const tbody = document.getElementById('tbody-clientes');
    const infoPaginacion = document.getElementById('info-paginacion-clientes');
    const paginacionUl = document.getElementById('paginacion-clientes');

    const filtroBusqueda = document.getElementById('filtro-busqueda-cliente');
    const filtroCategoria = document.getElementById('filtro-categoria-cliente');
    const filtroEstado = document.getElementById('filtro-estado-cliente');
    const filtroCanal = document.getElementById('filtro-canal-cliente');
    const btnLimpiar = document.getElementById('btn-limpiar-busqueda');
    const btnRecargar = document.getElementById('btn-recargar-clientes');

    // Modales y formularios
    const modalAltaEl = document.getElementById('modal-alta-cliente');
    const modalAlta = new bootstrap.Modal(modalAltaEl);
    const formAlta = document.getElementById('form-alta-cliente');

    const modalBloquearEl = document.getElementById('modal-bloquear-cliente');
    const modalBloquear = new bootstrap.Modal(modalBloquearEl);
    const formBloquear = document.getElementById('form-bloquear-cliente');

    // ------------------------------------------------------------------------
    // Carga de Clientes con AJAX
    // ------------------------------------------------------------------------
    function cargarClientes(pagina = 1) {
        paginaActual = pagina;
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            Cargando clientes...
        </td></tr>`;

        const params = new URLSearchParams({
            pagina: paginaActual,
            limite: limiteActual,
            buscar: filtroBusqueda.value.trim(),
            categoria_id: filtroCategoria.value,
            estado: filtroEstado.value,
            canal_captacion: filtroCanal.value
        });

        fetch(`/clientes/datos?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (!res.exito) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${res.mensaje || 'Error al cargar clientes.'}</td></tr>`;
                return;
            }

            renderizarTabla(res.datos);
            renderizarPaginacion(res.paginacion);
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Error de comunicación con el servidor.</td></tr>`;
        });
    }

    function renderizarTabla(clientes) {
        if (!clientes || clientes.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron clientes registrados con los filtros aplicados.</td></tr>`;
            return;
        }

        let html = '';
        clientes.forEach(c => {
            // Badges nativos Alina
            let badgeCategoria = `<span class="badge bg-${c.categoria_color_badge || 'secondary'}-subtle text-${c.categoria_color_badge || 'secondary'}">${c.categoria_nombre || 'Estándar'}</span>`;

            let badgeEstado = '';
            if (c.estado === 'ACTIVO') {
                badgeEstado = `<span class="badge bg-success-subtle text-success">ACTIVO</span>`;
            } else if (c.estado === 'BLOQUEADO') {
                badgeEstado = `<span class="badge bg-danger text-white" title="${c.motivo_bloqueo || ''}"><i class="fa-solid fa-lock me-1"></i>BLOQUEADO</span>`;
            } else {
                badgeEstado = `<span class="badge bg-secondary-subtle text-secondary">INACTIVO</span>`;
            }

            let docInfo = c.numero_documento 
                ? `<span class="badge bg-light text-dark border">${c.tipo_documento_codigo || 'DOC'}: ${c.numero_documento}</span>` 
                : '<span class="text-muted f-s-12">Sin documento</span>';

            let telInfo = c.telefono_principal 
                ? `<div><i class="fa-solid fa-phone f-s-11 text-secondary me-1"></i>${c.telefono_principal} ${c.es_whatsapp == 1 ? '<i class="fa-brands fa-whatsapp text-success"></i>' : ''}</div>` 
                : '';
            let emailInfo = c.email_principal 
                ? `<div class="text-secondary f-s-11"><i class="fa-solid fa-envelope me-1"></i>${c.email_principal}</div>` 
                : '';

            let historialInfo = `
                <div class="f-s-11 text-secondary">
                    <span title="Reservas"><i class="fa-solid fa-calendar-check text-primary me-1"></i>${c.total_reservas || 0}</span> | 
                    <span title="Estadías"><i class="fa-solid fa-bed text-info me-1"></i>${c.total_estadias || 0}</span> | 
                    <span title="Arrendamientos"><i class="fa-solid fa-file-contract text-warning me-1"></i>${c.total_arrendamientos || 0}</span>
                </div>
            `;

            html += `
                <tr>
                    <td class="ps-3"><a href="/clientes/${c.id}" class="f-w-700 text-primary text-decoration-none">${c.codigo}</a></td>
                    <td>
                        <a href="/clientes/${c.id}" class="text-dark f-w-600 text-decoration-none d-block">${c.nombre_completo || 'Sin nombre'}</a>
                        <small class="text-muted f-s-11">Alta: ${c.creado_en ? c.creado_en.substring(0, 10) : '-'}</small>
                    </td>
                    <td>${docInfo}</td>
                    <td>${telInfo}${emailInfo || (!telInfo ? '<span class="text-muted f-s-12">Sin contacto</span>' : '')}</td>
                    <td>${badgeCategoria}</td>
                    <td>${historialInfo}</td>
                    <td>${badgeEstado}</td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <a href="/clientes/${c.id}" class="btn btn-outline-primary" title="Ver Ficha Integral 360°">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            ${c.estado === 'BLOQUEADO' ? `
                                <button type="button" class="btn btn-outline-success btn-desbloquear-cliente" data-id="${c.id}" data-codigo="${c.codigo}" data-nombre="${c.nombre_completo}" title="Desbloquear cliente">
                                    <i class="fa-solid fa-lock-open"></i>
                                </button>
                            ` : `
                                <button type="button" class="btn btn-outline-danger btn-bloquear-cliente" data-id="${c.id}" data-codigo="${c.codigo}" data-nombre="${c.nombre_completo}" title="Bloquear cliente">
                                    <i class="fa-solid fa-user-lock"></i>
                                </button>
                            `}
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderizarPaginacion(pag) {
        if (!pag || pag.total === 0) {
            infoPaginacion.textContent = 'Mostrando 0 de 0 registros';
            paginacionUl.innerHTML = '';
            return;
        }

        const desde = ((pag.pagina - 1) * pag.limite) + 1;
        const hasta = Math.min(pag.pagina * pag.limite, pag.total);
        infoPaginacion.textContent = `Mostrando ${desde} a ${hasta} de ${pag.total} clientes`;

        let html = '';
        if (pag.pagina > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pag.pagina - 1}">&laquo;</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">&laquo;</span></li>`;
        }

        const totalPag = pag.total_paginas;
        let startPage = Math.max(1, pag.pagina - 2);
        let endPage = Math.min(totalPag, pag.pagina + 2);

        for (let i = startPage; i <= endPage; i++) {
            if (i === pag.pagina) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
        }

        if (pag.pagina < totalPag) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pag.pagina + 1}">&raquo;</a></li>`;
        } else {
            html += `<li class="page-item disabled"><span class="page-link">&raquo;</span></li>`;
        }

        paginacionUl.innerHTML = html;
    }

    // Eventos paginación
    paginacionUl.addEventListener('click', function (e) {
        e.preventDefault();
        const a = e.target.closest('a[data-page]');
        if (a) {
            cargarClientes(parseInt(a.getAttribute('data-page'), 10));
        }
    });

    // Filtros con debounce
    let timerBusqueda = null;
    filtroBusqueda.addEventListener('input', function () {
        clearTimeout(timerBusqueda);
        timerBusqueda = setTimeout(() => cargarClientes(1), 350);
    });

    filtroCategoria.addEventListener('change', () => cargarClientes(1));
    filtroEstado.addEventListener('change', () => cargarClientes(1));
    filtroCanal.addEventListener('change', () => cargarClientes(1));

    btnLimpiar.addEventListener('click', function () {
        filtroBusqueda.value = '';
        cargarClientes(1);
    });

    btnRecargar.addEventListener('click', function () {
        cargarClientes(paginaActual);
    });

    // ------------------------------------------------------------------------
    // Modo de Alta: Existente vs Nueva Persona
    // ------------------------------------------------------------------------
    const radExistente = document.getElementById('modo-buscar-persona');
    const radNueva = document.getElementById('modo-nueva-persona');
    const secExistente = document.getElementById('seccion-persona-existente');
    const secNueva = document.getElementById('seccion-persona-nueva');

    radExistente.addEventListener('change', function () {
        if (this.checked) {
            secExistente.classList.remove('d-none');
            secNueva.classList.add('d-none');
        }
    });

    radNueva.addEventListener('change', function () {
        if (this.checked) {
            secNueva.classList.remove('d-none');
            secExistente.classList.add('d-none');
        }
    });

    // Búsqueda de Persona Existente
    const btnVerificar = document.getElementById('btn-verificar-persona');
    const inputBuscarDoc = document.getElementById('alta-buscar-doc');
    const spinnerBuscar = document.getElementById('alta-persona-spinner');
    const feedbackBuscar = document.getElementById('alta-persona-feedback');
    const boxPersonaSel = document.getElementById('box-persona-seleccionada');
    const hiddenPersonaId = document.getElementById('alta-persona-id');
    const selNombre = document.getElementById('sel-persona-nombre');
    const selDoc = document.getElementById('sel-persona-doc');
    const selTel = document.getElementById('sel-persona-tel');
    const selEmail = document.getElementById('sel-persona-email');
    const btnQuitarSel = document.getElementById('btn-quitar-persona-sel');

    btnVerificar.addEventListener('click', function () {
        const val = inputBuscarDoc.value.trim();
        if (!val) return;

        spinnerBuscar.classList.remove('d-none');
        feedbackBuscar.textContent = '';

        fetch(`/api/clientes/buscar-persona?documento=${encodeURIComponent(val)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            spinnerBuscar.classList.add('d-none');
            if (res.encontrado && res.persona) {
                if (res.es_cliente) {
                    feedbackBuscar.innerHTML = `<span class="text-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>Esta persona ya es cliente (${res.cliente_codigo}).</span>`;
                    return;
                }

                hiddenPersonaId.value = res.persona.id;
                selNombre.textContent = res.persona.nombre_completo || (res.persona.nombres + ' ' + res.persona.apellido_paterno);
                selDoc.textContent = `${res.persona.tipo_documento || 'DOC'}: ${res.persona.numero_documento || '-'}`;
                selTel.innerHTML = `<i class="fa-solid fa-phone me-1"></i>${res.persona.telefono || 'Sin teléfono'}`;
                selEmail.innerHTML = `<i class="fa-solid fa-envelope me-1"></i>${res.persona.email || 'Sin correo'}`;

                boxPersonaSel.classList.remove('d-none');
                feedbackBuscar.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Persona seleccionada correctamente.</span>`;
            } else {
                feedbackBuscar.innerHTML = `<span class="text-warning"><i class="fa-solid fa-info-circle me-1"></i>Persona no encontrada. Puede crearla en "Crear Nueva Persona".</span>`;
            }
        })
        .catch(() => {
            spinnerBuscar.classList.add('d-none');
            feedbackBuscar.innerHTML = `<span class="text-danger">Error al consultar persona.</span>`;
        });
    });

    btnQuitarSel.addEventListener('click', function () {
        hiddenPersonaId.value = '';
        boxPersonaSel.classList.add('d-none');
        feedbackBuscar.textContent = '';
        inputBuscarDoc.value = '';
    });

    // Cascada Geográfica INEI
    const selPaisResidencia = document.getElementById('alta-pais-residencia');
    const boxGeoPeru = document.getElementById('box-geografia-peru');
    const boxGeoExt = document.getElementById('box-geografia-extranjera');
    const selDep = document.getElementById('alta-departamento');
    const selProv = document.getElementById('alta-provincia');
    const selDist = document.getElementById('alta-distrito');

    selPaisResidencia.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const iso = opt.getAttribute('data-iso');
        if (iso === 'PE') {
            boxGeoPeru.classList.remove('d-none');
            boxGeoExt.classList.add('d-none');
        } else {
            boxGeoPeru.classList.add('d-none');
            boxGeoExt.classList.remove('d-none');
        }
    });

    selDep.addEventListener('change', function () {
        const depId = this.value;
        selProv.innerHTML = '<option value="">Seleccione...</option>';
        selDist.innerHTML = '<option value="">Seleccione...</option>';
        selDist.disabled = true;

        if (!depId) {
            selProv.disabled = true;
            return;
        }

        fetch(`/api/geografia/provincias?departamento_id=${depId}`)
        .then(r => r.json())
        .then(res => {
            if (res.exito && res.datos) {
                res.datos.forEach(p => {
                    selProv.innerHTML += `<option value="${p.id}">${p.nombre}</option>`;
                });
                selProv.disabled = false;
            }
        });
    });

    selProv.addEventListener('change', function () {
        const provId = this.value;
        selDist.innerHTML = '<option value="">Seleccione...</option>';

        if (!provId) {
            selDist.disabled = true;
            return;
        }

        fetch(`/api/geografia/distritos?provincia_id=${provId}`)
        .then(r => r.json())
        .then(res => {
            if (res.exito && res.datos) {
                res.datos.forEach(d => {
                    selDist.innerHTML += `<option value="${d.id}">${d.nombre} (${d.codigo_ubigeo})</option>`;
                });
                selDist.disabled = false;
            }
        });
    });

    // Abrir Modal de Alta
    const btnNuevoCliente = document.getElementById('btn-nuevo-cliente');
    if (btnNuevoCliente) {
        btnNuevoCliente.addEventListener('click', function () {
            formAlta.reset();
            hiddenPersonaId.value = '';
            boxPersonaSel.classList.add('d-none');
            feedbackBuscar.textContent = '';
            radExistente.checked = true;
            secExistente.classList.remove('d-none');
            secNueva.classList.add('d-none');
            boxGeoPeru.classList.remove('d-none');
            boxGeoExt.classList.add('d-none');
            selProv.disabled = true;
            selDist.disabled = true;
            modalAlta.show();
        });
    }

    // Submit Alta de Cliente
    formAlta.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(formAlta);
        const payload = {
            csrf_token: csrfToken,
            categoria_id: formData.get('categoria_id'),
            canal_captacion: formData.get('canal_captacion'),
            preferencias: formData.get('preferencias'),
            observaciones: formData.get('observaciones'),
        };

        const modo = document.querySelector('input[name="modo_alta"]:checked').value;
        if (modo === 'EXISTENTE') {
            const pId = formData.get('persona_id');
            if (!pId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Persona requerida',
                    text: 'Debe buscar y seleccionar una persona existente antes de guardar.'
                });
                return;
            }
            payload.persona_id = pId;
        } else {
            // Nueva Persona
            payload.nombres = formData.get('nombres');
            payload.apellido_paterno = formData.get('apellido_paterno');
            payload.apellido_materno = formData.get('apellido_materno');
            payload.genero = formData.get('genero');
            payload.fecha_nacimiento = formData.get('fecha_nacimiento');
            payload.pais_nacionalidad_id = formData.get('pais_nacionalidad_id');
            payload.tipo_documento_id = formData.get('tipo_documento_id');
            payload.numero_documento = formData.get('numero_documento');
            payload.pais_emisor_id = formData.get('pais_emisor_id');
            payload.telefono = formData.get('telefono');
            payload.es_whatsapp = formData.get('es_whatsapp') ? 1 : 0;
            payload.email = formData.get('email');
            payload.pais_residencia_id = formData.get('pais_residencia_id');
            payload.distrito_id = formData.get('distrito_id');
            payload.region_residencia_extranjera = formData.get('region_residencia_extranjera');
            payload.ciudad_residencia_extranjera = formData.get('ciudad_residencia_extranjera');
            payload.direccion = formData.get('direccion');
        }

        const btnGuardar = document.getElementById('btn-guardar-cliente');
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...`;

        fetch('/api/clientes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json().then(data => ({ status: r.status, body: data })))
        .then(({ status, body }) => {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = `<i class="fa-solid fa-floppy-disk me-1"></i> Registrar Cliente`;

            if (status === 201 && body.exito) {
                modalAlta.hide();
                Swal.fire({
                    icon: 'success',
                    title: '¡Cliente Registrado!',
                    text: body.mensaje,
                    timer: 1800,
                    showConfirmButton: false
                });
                cargarClientes(1);
            } else if (status === 409) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cliente Duplicado',
                    text: body.mensaje || 'Esta persona ya cuenta con un perfil comercial de cliente.'
                });
            } else {
                let msg = body.mensaje || 'No se pudo registrar el cliente.';
                if (body.errores) {
                    msg += '<br><small>' + Object.values(body.errores).join('<br>') + '</small>';
                }
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    html: msg
                });
            }
        })
        .catch(() => {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = `<i class="fa-solid fa-floppy-disk me-1"></i> Registrar Cliente`;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión con el servidor.'
            });
        });
    });

    // ------------------------------------------------------------------------
    // Bloquear / Desbloquear Clientes
    // ------------------------------------------------------------------------
    tbody.addEventListener('click', function (e) {
        const btnBloquear = e.target.closest('.btn-bloquear-cliente');
        if (btnBloquear) {
            const id = btnBloquear.getAttribute('data-id');
            const codigo = btnBloquear.getAttribute('data-codigo');
            const nombre = btnBloquear.getAttribute('data-nombre');

            document.getElementById('bloquear-cliente-id').value = id;
            document.getElementById('bloquear-cliente-nombre').textContent = `${codigo} — ${nombre}`;
            document.getElementById('bloquear-motivo').value = '';
            modalBloquear.show();
            return;
        }

        const btnDesbloquear = e.target.closest('.btn-desbloquear-cliente');
        if (btnDesbloquear) {
            const id = btnDesbloquear.getAttribute('data-id');
            const codigo = btnDesbloquear.getAttribute('data-codigo');

            Swal.fire({
                title: `¿Reactivar cliente ${codigo}?`,
                text: 'El estado del cliente pasará a ACTIVO y se removerán las restricciones visuales.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, reactivar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(`/api/clientes/${id}/desbloquear`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ csrf_token: csrfToken })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.exito) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Cliente Reactivado',
                                text: res.mensaje,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            cargarClientes(paginaActual);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                        }
                    });
                }
            });
        }
    });

    formBloquear.addEventListener('submit', function (e) {
        e.preventDefault();
        const id = document.getElementById('bloquear-cliente-id').value;
        const motivo = document.getElementById('bloquear-motivo').value.trim();

        if (!motivo) {
            Swal.fire({ icon: 'warning', title: 'Motivo obligatorio', text: 'Debe ingresar un motivo para bloquear al cliente.' });
            return;
        }

        fetch(`/api/clientes/${id}/bloquear`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ motivo: motivo, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(res => {
            if (res.exito) {
                modalBloquear.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Cliente Bloqueado',
                    text: res.mensaje,
                    timer: 1500,
                    showConfirmButton: false
                });
                cargarClientes(paginaActual);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
            }
        });
    });

    // Carga inicial
    cargarClientes(1);
});
</script>
