<?php

declare(strict_types=1);

/**
 * Vista de Ficha Integral 360° del Cliente — Camargo PMS (CLIENTES-1).
 *
 * @var \CamargoPMS\Modelos\Usuario $usuario
 * @var array<string, mixed> $ficha
 * @var \CamargoPMS\Modelos\ClienteCategoria[] $categorias
 * @var array<string, string> $canales
 * @var array{puede_editar: bool, puede_bloquear: bool} $permisos
 * @var string $csrf_token
 * @var string $titulo
 */

$cliente = $ficha['cliente'];
$persona = $ficha['persona'];
$categoria = $ficha['categoria'] ?? ['nombre' => 'Estándar', 'color_badge' => 'secondary'];
$kpis = $ficha['kpis'];
$reservas = $ficha['reservas'] ?? [];
$estadias = $ficha['estadias'] ?? [];
$arrendamientos = $ficha['arrendamientos'] ?? [];
$servicios = $ficha['servicios'] ?? [];
$estadoCuenta = $ficha['estado_cuenta'] ?? [];
$documentos = $ficha['documentos'] ?? [];

$nombreCompleto = trim($persona['nombres'] . ' ' . $persona['apellido_paterno'] . ' ' . ($persona['apellido_materno'] ?? ''));
$esBloqueado = ($cliente['estado'] === 'BLOQUEADO');
$colorCat = $categoria['color_badge'] ?? 'secondary';
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">
<input type="hidden" id="cliente-id-actual" value="<?= (int) $cliente['id'] ?>">

<!-- Encabezado de Navegación -->
<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <a href="/clientes" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver al Directorio
        </a>
        <div class="d-flex gap-2">
            <?php if ($permisos['puede_editar']): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-editar-perfil-top">
                <i class="fa-solid fa-pen-to-square me-1"></i> Editar Perfil Comercial
            </button>
            <?php endif; ?>

            <?php if ($permisos['puede_bloquear']): ?>
                <?php if ($esBloqueado): ?>
                <button type="button" class="btn btn-success btn-sm" id="btn-desbloquear-top">
                    <i class="fa-solid fa-lock-open me-1"></i> Reactivar Cliente
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-bloquear-top">
                    <i class="fa-solid fa-user-lock me-1"></i> Bloquear Cliente
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Banner de Advertencia si está BLOQUEADO -->
<?php if ($esBloqueado): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-danger shadow-sm border-danger border-2 d-flex align-items-center justify-content-between p-3 b-r-12">
            <div class="d-flex align-items-center">
                <span class="bg-danger text-white p-2 rounded-circle me-3">
                    <i class="fa-solid fa-triangle-exclamation f-s-20"></i>
                </span>
                <div>
                    <h5 class="alert-heading mb-1 f-w-700">CLIENTE BLOQUEADO COMERCIALMENTE</h5>
                    <p class="mb-0 f-s-13">
                        <strong>Motivo de bloqueo:</strong> <?= e($cliente['motivo_bloqueo'] ?? 'No especificado') ?>
                    </p>
                </div>
            </div>
            <?php if ($permisos['puede_bloquear']): ?>
            <button type="button" class="btn btn-danger btn-sm ms-3 text-nowrap" id="btn-desbloquear-banner">
                <i class="fa-solid fa-lock-open me-1"></i> Desbloquear Ahora
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tarjeta Principal de Identidad Comercial 360° -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar-lg bg-light-primary text-primary rounded-circle d-flex-center p-3 me-3" style="width: 64px; height: 64px;">
                            <i class="fa-solid fa-user f-s-28"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <h3 class="mb-0 f-w-700 text-dark"><?= e($nombreCompleto) ?></h3>
                                <span class="badge bg-primary text-white f-s-12 px-2 py-1"><?= e($cliente['codigo']) ?></span>
                                <span class="badge bg-light-<?= e($colorCat) ?> text-<?= e($colorCat) ?> f-s-12 px-2 py-1">
                                    <i class="fa-solid fa-award me-1"></i><?= e($categoria['nombre'] ?? 'Estándar') ?>
                                </span>
                                <?php if ($cliente['estado'] === 'ACTIVO'): ?>
                                    <span class="badge bg-light-success text-success f-s-12 px-2 py-1">ACTIVO</span>
                                <?php elseif ($cliente['estado'] === 'BLOQUEADO'): ?>
                                    <span class="badge bg-danger text-white f-s-12 px-2 py-1"><i class="fa-solid fa-lock me-1"></i>BLOQUEADO</span>
                                <?php else: ?>
                                    <span class="badge bg-light-secondary text-secondary f-s-12 px-2 py-1">INACTIVO</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary f-s-13 d-flex flex-wrap gap-3">
                                <?php if (!empty($persona['documento_principal'])): ?>
                                    <span>
                                        <i class="fa-solid fa-id-card text-muted me-1"></i>
                                        <strong><?= e($persona['documento_principal']['tipo_codigo'] ?? 'DOC') ?>:</strong> <?= e($persona['documento_principal']['numero_documento']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($persona['telefono_principal'])): ?>
                                    <span>
                                        <i class="fa-solid fa-phone text-muted me-1"></i>
                                        <?= e($persona['telefono_principal']['valor']) ?>
                                        <?php if (!empty($persona['telefono_principal']['es_whatsapp'])): ?>
                                            <i class="fa-brands fa-whatsapp text-success ms-1" title="Cuenta con WhatsApp"></i>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($persona['email_principal'])): ?>
                                    <span>
                                        <i class="fa-solid fa-envelope text-muted me-1"></i>
                                        <?= e($persona['email_principal']['valor']) ?>
                                    </span>
                                <?php endif; ?>
                                <span>
                                    <i class="fa-solid fa-bullhorn text-muted me-1"></i>
                                    <strong>Canal:</strong> <?= e($ficha['preferencias_notas']['canal_captacion_nombre'] ?? 'Directo') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KPIs Rápidos de Alto Nivel -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body p-3 text-center">
                <span class="text-secondary f-s-12 d-block mb-1">Reservas Titular</span>
                <h4 class="f-w-700 text-primary mb-0"><?= (int) $kpis['total_reservas'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body p-3 text-center">
                <span class="text-secondary f-s-12 d-block mb-1">Estadías Físicas</span>
                <h4 class="f-w-700 text-info mb-0"><?= (int) $kpis['total_estadias'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body p-3 text-center">
                <span class="text-secondary f-s-12 d-block mb-1">Arrendamientos</span>
                <h4 class="f-w-700 text-warning mb-0"><?= (int) $kpis['total_arrendamientos'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body p-3 text-center">
                <span class="text-secondary f-s-12 d-block mb-1">Servicios Contratados</span>
                <h4 class="f-w-700 text-dark mb-0"><?= (int) $kpis['total_servicios'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm b-r-12 h-100 <?= bccomp((string) $kpis['saldo_pendiente_consolidado'], '0.00', 2) > 0 ? 'bg-light-danger' : '' ?>">
            <div class="card-body p-3 text-center">
                <span class="text-secondary f-s-12 d-block mb-1">Saldo Exigible</span>
                <h4 class="f-w-700 <?= bccomp((string) $kpis['saldo_pendiente_consolidado'], '0.00', 2) > 0 ? 'text-danger' : 'text-success' ?> mb-0">
                    S/ <?= number_format((float) $kpis['saldo_pendiente_consolidado'], 2) ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 shadow-sm b-r-12 h-100">
            <div class="card-body p-3 text-center">
                <span class="text-secondary f-s-12 d-block mb-1">Antigüedad</span>
                <h5 class="f-w-700 text-dark mb-0"><?= e($kpis['antiguedad']) ?></h5>
                <small class="text-muted f-s-10">Desde <?= e($kpis['fecha_primer_registro']) ?></small>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- PESTAÑAS 360° DEL CLIENTE                                                                  -->
<!-- ========================================================================================= -->
<div class="row">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0 b-r-20">
            <div class="card-header bg-white p-0 border-bottom">
                <ul class="nav nav-tabs card-header-tabs m-0 px-3 pt-2" id="tabsCliente360" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active f-s-13 f-w-600" id="tab-resumen-btn" data-bs-toggle="tab" data-bs-target="#tab-resumen" type="button" role="tab">
                            <i class="fa-solid fa-circle-info me-1"></i> Resumen
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-reservas-btn" data-bs-toggle="tab" data-bs-target="#tab-reservas" type="button" role="tab">
                            <i class="fa-solid fa-calendar-check me-1"></i> Reservas <span class="badge bg-light-primary text-primary ms-1"><?= count($reservas) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-estadias-btn" data-bs-toggle="tab" data-bs-target="#tab-estadias" type="button" role="tab">
                            <i class="fa-solid fa-bed me-1"></i> Estadías <span class="badge bg-light-info text-info ms-1"><?= count($estadias) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-arrendamientos-btn" data-bs-toggle="tab" data-bs-target="#tab-arrendamientos" type="button" role="tab">
                            <i class="fa-solid fa-file-contract me-1"></i> Arrendamientos <span class="badge bg-light-warning text-warning ms-1"><?= count($arrendamientos) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-cuenta-btn" data-bs-toggle="tab" data-bs-target="#tab-cuenta" type="button" role="tab">
                            <i class="fa-solid fa-receipt me-1"></i> Estado de Cuenta
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-servicios-btn" data-bs-toggle="tab" data-bs-target="#tab-servicios" type="button" role="tab">
                            <i class="fa-solid fa-bell-concierge me-1"></i> Servicios <span class="badge bg-light-dark text-dark ms-1"><?= count($servicios) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-documentos-btn" data-bs-toggle="tab" data-bs-target="#tab-documentos" type="button" role="tab">
                            <i class="fa-solid fa-file-pdf me-1"></i> Documentos <span class="badge bg-light-secondary text-secondary ms-1"><?= count($documentos) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link f-s-13 f-w-600" id="tab-notas-btn" data-bs-toggle="tab" data-bs-target="#tab-notas" type="button" role="tab">
                            <i class="fa-solid fa-note-sticky me-1"></i> Preferencias / Notas
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="tabContentCliente360">

                    <!-- 1. TAB: RESUMEN -->
                    <div class="tab-pane fade show active" id="tab-resumen" role="tabpanel">
                        <div class="row g-4">
                            <!-- Datos de Identidad Soberana (Persona) -->
                            <div class="col-md-6 col-12">
                                <div class="card border bg-light h-100">
                                    <div class="card-header bg-white py-2 border-bottom">
                                        <h6 class="mb-0 f-w-700 text-primary f-s-13">
                                            <i class="fa-solid fa-id-card me-1"></i> Identidad Soberana (Persona Natural)
                                        </h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <table class="table table-sm table-borderless mb-0 f-s-13">
                                            <tr>
                                                <th class="text-secondary w-35 ps-0">Nombre Completo:</th>
                                                <td class="f-w-600"><?= e($nombreCompleto) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Nombres:</th>
                                                <td><?= e($persona['nombres']) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Apellido Paterno:</th>
                                                <td><?= e($persona['apellido_paterno']) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Apellido Materno:</th>
                                                <td><?= e($persona['apellido_materno'] ?? '—') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Género:</th>
                                                <td><?= e($persona['genero'] ?? 'NO_ESPECIFICADO') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Fecha de Nacimiento:</th>
                                                <td><?= e($persona['fecha_nacimiento'] ?? '—') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Nacionalidad:</th>
                                                <td><?= e($persona['pais_nacionalidad_nombre'] ?? 'Perú') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">País de Residencia:</th>
                                                <td><?= e($persona['pais_residencia_nombre'] ?? 'Perú') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Ubicación / Domicilio:</th>
                                                <td>
                                                    <?php if (!empty($persona['distrito_nombre'])): ?>
                                                        <?= e($persona['departamento_nombre'] ?? '') ?> / 
                                                        <?= e($persona['provincia_nombre'] ?? '') ?> / 
                                                        <strong><?= e($persona['distrito_nombre']) ?></strong>
                                                        <?php if (!empty($persona['ubigeo'])): ?>
                                                            <span class="badge bg-light text-dark border">UBIGEO: <?= e($persona['ubigeo']) ?></span>
                                                        <?php endif; ?>
                                                    <?php elseif (!empty($persona['ciudad_residencia_extranjera'])): ?>
                                                        <?= e($persona['ciudad_residencia_extranjera']) ?>, <?= e($persona['region_residencia_extranjera'] ?? '') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No especificado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Dirección:</th>
                                                <td><?= e($persona['direccion'] ?? '—') ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Perfil Comercial -->
                            <div class="col-md-6 col-12">
                                <div class="card border bg-light h-100">
                                    <div class="card-header bg-white py-2 border-bottom">
                                        <h6 class="mb-0 f-w-700 text-primary f-s-13">
                                            <i class="fa-solid fa-briefcase me-1"></i> Perfil Comercial y Parámetros
                                        </h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <table class="table table-sm table-borderless mb-0 f-s-13">
                                            <tr>
                                                <th class="text-secondary w-35 ps-0">Código Cliente:</th>
                                                <td><span class="badge bg-primary text-white"><?= e($cliente['codigo']) ?></span></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Categoría Comercial:</th>
                                                <td>
                                                    <span class="badge bg-light-<?= e($colorCat) ?> text-<?= e($colorCat) ?>">
                                                        <?= e($categoria['nombre'] ?? 'Estándar') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Estado Comercial:</th>
                                                <td>
                                                    <?php if ($cliente['estado'] === 'ACTIVO'): ?>
                                                        <span class="badge bg-light-success text-success">ACTIVO</span>
                                                    <?php elseif ($cliente['estado'] === 'BLOQUEADO'): ?>
                                                        <span class="badge bg-danger text-white"><i class="fa-solid fa-lock me-1"></i>BLOQUEADO</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light-secondary text-secondary">INACTIVO</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Canal de Captación:</th>
                                                <td><?= e($ficha['preferencias_notas']['canal_captacion_nombre'] ?? 'Directo') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Fecha Primer Registro:</th>
                                                <td><?= e($kpis['fecha_primer_registro']) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Última Operación:</th>
                                                <td><?= e($kpis['fecha_ultima_operacion'] ?? 'Sin operaciones registradas') ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Preferencias:</th>
                                                <td><?= nl2br(e($cliente['preferencias'] ?? 'Ninguna registrada.')) ?></td>
                                            </tr>
                                            <tr>
                                                <th class="text-secondary ps-0">Observaciones:</th>
                                                <td><?= nl2br(e($cliente['observaciones'] ?? 'Ninguna registrada.')) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion Alina: Información de Trazabilidad y Auditoría -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="accordion app-accordion" id="accordionInfoCliente">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingAuditoria">
                                            <button class="accordion-button accordion-icon collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAuditoria" aria-expanded="false" aria-controls="collapseAuditoria">
                                                <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Información de Auditoría y Trazabilidad
                                            </button>
                                        </h2>
                                        <div id="collapseAuditoria" class="accordion-collapse collapse" aria-labelledby="headingAuditoria" data-bs-parent="#accordionInfoCliente">
                                            <div class="accordion-body f-s-13">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <span class="text-secondary d-block">ID Registro Cliente:</span>
                                                        <strong><?= e((string)$cliente['id']) ?></strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <span class="text-secondary d-block">ID Registro Persona:</span>
                                                        <strong><?= e((string)$persona['id']) ?></strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <span class="text-secondary d-block">Creado en el sistema:</span>
                                                        <strong><?= e((string)($cliente['creado_en'] ?? '—')) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. TAB: RESERVAS -->
                    <div class="tab-pane fade" id="tab-reservas" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 f-s-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Entrada</th>
                                        <th>Salida</th>
                                        <th>Noches</th>
                                        <th>Canal</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Creado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reservas)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No existen reservas registradas para este cliente.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($reservas as $res): ?>
                                    <tr>
                                        <td class="f-w-700 text-primary"><?= e($res['codigo']) ?></td>
                                        <td><?= e($res['fecha_entrada']) ?></td>
                                        <td><?= e($res['fecha_salida']) ?></td>
                                        <td><?= (int) $res['noches'] ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($res['canal']) ?></span></td>
                                        <td class="f-w-600">S/ <?= number_format((float) $res['total'], 2) ?></td>
                                        <td>
                                            <?php if ($res['estado'] === 'CONFIRMADA'): ?>
                                                <span class="badge bg-light-success text-success">CONFIRMADA</span>
                                            <?php elseif ($res['estado'] === 'PENDIENTE'): ?>
                                                <span class="badge bg-light-warning text-warning">PENDIENTE</span>
                                            <?php elseif ($res['estado'] === 'CANCELADA'): ?>
                                                <span class="badge bg-light-danger text-danger">CANCELADA</span>
                                            <?php else: ?>
                                                <span class="badge bg-light-secondary text-secondary"><?= e($res['estado']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= substr((string) $res['creado_en'], 0, 10) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. TAB: ESTADÍAS -->
                    <div class="tab-pane fade" id="tab-estadias" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 f-s-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código Estadía</th>
                                        <th>Reserva</th>
                                        <th>Unidad / Hab.</th>
                                        <th>Rol en Estadía</th>
                                        <th>Entrada</th>
                                        <th>Salida Prevista</th>
                                        <th>Check-in Real</th>
                                        <th>Check-out Real</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($estadias)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No existen estadías físicas registradas para esta persona.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($estadias as $est): ?>
                                    <tr>
                                        <td class="f-w-700 text-info"><?= e($est['codigo']) ?></td>
                                        <td><?= e($est['reserva_codigo'] ?? '-') ?></td>
                                        <td><strong><?= e($est['unidad_codigo'] ?? '') ?></strong> <?= e($est['unidad_nombre'] ?? '') ?></td>
                                        <td>
                                            <?php if ($est['rol_estadia'] === 'RESPONSABLE'): ?>
                                                <span class="badge bg-primary text-white"><i class="fa-solid fa-crown me-1"></i>RESPONSABLE</span>
                                            <?php else: ?>
                                                <span class="badge bg-light-secondary text-secondary"><i class="fa-solid fa-user-group me-1"></i>ACOMPAÑANTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($est['fecha_entrada']) ?></td>
                                        <td><?= e($est['fecha_salida_prevista']) ?></td>
                                        <td><?= !empty($est['checkin_en']) ? substr((string) $est['checkin_en'], 0, 16) : '-' ?></td>
                                        <td><?= !empty($est['checkout_en']) ? substr((string) $est['checkout_en'], 0, 16) : '-' ?></td>
                                        <td>
                                            <?php if ($est['estado'] === 'EN_CURSO'): ?>
                                                <span class="badge bg-light-success text-success">EN CURSO</span>
                                            <?php elseif ($est['estado'] === 'FINALIZADA'): ?>
                                                <span class="badge bg-light-secondary text-secondary">FINALIZADA</span>
                                            <?php else: ?>
                                                <span class="badge bg-light-danger text-danger">ANULADA</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 4. TAB: ARRENDAMIENTOS -->
                    <div class="tab-pane fade" id="tab-arrendamientos" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 f-s-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código Contrato</th>
                                        <th>Unidad</th>
                                        <th>Rol Contractual</th>
                                        <th>Deudor Financiero</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Renta Mensual</th>
                                        <th>Garantía</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($arrendamientos)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No existen contratos de arrendamiento asociados a esta persona.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($arrendamientos as $arr): ?>
                                    <tr>
                                        <td class="f-w-700 text-warning"><?= e($arr['codigo']) ?></td>
                                        <td><strong><?= e($arr['unidad_codigo'] ?? '') ?></strong> <?= e($arr['unidad_nombre'] ?? '') ?></td>
                                        <td>
                                            <?php if ($arr['tipo_relacion'] === 'TITULAR'): ?>
                                                <span class="badge bg-primary text-white">TITULAR</span>
                                            <?php elseif ($arr['tipo_relacion'] === 'COTITULAR'): ?>
                                                <span class="badge bg-light-info text-info">COTITULAR</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border">OCUPANTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($arr['es_deudor_financiero'])): ?>
                                                <span class="text-success f-w-600"><i class="fa-solid fa-circle-check me-1"></i>Sí (Obligado)</span>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fa-solid fa-circle-xmark me-1"></i>No (Solo ocupante)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($arr['fecha_inicio']) ?></td>
                                        <td><?= e($arr['fecha_fin']) ?></td>
                                        <td class="f-w-600">S/ <?= number_format((float) $arr['renta_mensual'], 2) ?></td>
                                        <td>S/ <?= number_format((float) $arr['deposito_garantia'], 2) ?></td>
                                        <td>
                                            <?php if ($arr['estado'] === 'VIGENTE'): ?>
                                                <span class="badge bg-light-success text-success">VIGENTE</span>
                                            <?php elseif ($arr['estado'] === 'FINALIZADO'): ?>
                                                <span class="badge bg-light-secondary text-secondary">FINALIZADO</span>
                                            <?php elseif ($arr['estado'] === 'RESCINDIDO'): ?>
                                                <span class="badge bg-light-danger text-danger">RESCINDIDO</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark"><?= e($arr['estado']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 5. TAB: ESTADO DE CUENTA (SOBERANO) -->
                    <div class="tab-pane fade" id="tab-cuenta" role="tabpanel">
                        <!-- Balance Consolidado -->
                        <div class="card bg-light border mb-4">
                            <div class="card-body p-3">
                                <div class="row g-3 text-center">
                                    <div class="col-md-3 col-6">
                                        <span class="text-secondary f-s-12 d-block">Total Cargos Devengados</span>
                                        <h5 class="mb-0 f-w-700 text-dark">S/ <?= number_format((float) ($estadoCuenta['total_cargos_devengados'] ?? 0), 2) ?></h5>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <span class="text-secondary f-s-12 d-block">Total Pagos Confirmados</span>
                                        <h5 class="mb-0 f-w-700 text-success">S/ <?= number_format((float) ($estadoCuenta['total_pagos_confirmados'] ?? 0), 2) ?></h5>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <span class="text-secondary f-s-12 d-block">Total Pagos Aplicados</span>
                                        <h5 class="mb-0 f-w-700 text-info">S/ <?= number_format((float) ($estadoCuenta['total_pagos_aplicados'] ?? 0), 2) ?></h5>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <span class="text-secondary f-s-12 d-block">Saldo Neto Exigible</span>
                                        <h5 class="mb-0 f-w-700 <?= bccomp((string) ($estadoCuenta['saldo_consolidado'] ?? 0), '0.00', 2) > 0 ? 'text-danger' : 'text-success' ?>">
                                            S/ <?= number_format((float) ($estadoCuenta['saldo_consolidado'] ?? 0), 2) ?>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cuentas / Folios Detallados -->
                        <h6 class="f-w-700 text-primary f-s-14 mb-3">Folios Financieros Asociados</h6>
                        <?php if (empty($estadoCuenta['folios'])): ?>
                            <p class="text-muted text-center py-3">No existen folios financieros vinculados a este cliente.</p>
                        <?php else: ?>
                            <?php foreach ($estadoCuenta['folios'] as $fInfo): ?>
                            <?php $fol = $fInfo['folio'] ?? $fInfo; ?>
                            <div class="card border mb-3">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2"><?= e($fol['codigo'] ?? 'FOLIO') ?></span>
                                        <span class="text-muted f-s-12">Reserva: <?= e($fol['reserva_codigo'] ?? '-') ?></span>
                                    </div>
                                    <div>
                                        <span class="badge bg-light text-dark border me-2">Estado: <?= e($fol['estado'] ?? '-') ?></span>
                                        <span class="f-w-700 f-s-13 <?= bccomp((string) ($fInfo['saldo_neto_exigible'] ?? 0), '0.00', 2) > 0 ? 'text-danger' : 'text-success' ?>">
                                            Saldo Folio: S/ <?= number_format((float) ($fInfo['saldo_neto_exigible'] ?? 0), 2) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <!-- Cargos del Folio -->
                                    <?php if (!empty($fInfo['cargos'])): ?>
                                    <div class="p-2 bg-light f-s-12 f-w-600 border-bottom">Cargos a la Cuenta</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0 f-s-12">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Concepto</th>
                                                    <th>Cantidad</th>
                                                    <th>P. Unitario</th>
                                                    <th>Total</th>
                                                    <th>Aplicado</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($fInfo['cargos'] as $crg): ?>
                                                <tr>
                                                    <td><?= e($crg['codigo'] ?? '') ?></td>
                                                    <td><?= e($crg['concepto'] ?? '') ?></td>
                                                    <td><?= number_format((float) ($crg['cantidad'] ?? 1), 2) ?></td>
                                                    <td>S/ <?= number_format((float) ($crg['precio_unitario'] ?? 0), 2) ?></td>
                                                    <td class="f-w-600">S/ <?= number_format((float) ($crg['total'] ?? 0), 2) ?></td>
                                                    <td class="text-info">S/ <?= number_format((float) ($crg['monto_aplicado_acumulado'] ?? 0), 2) ?></td>
                                                    <td>
                                                        <?php if (($crg['estado'] ?? '') === 'DEVENGADO'): ?>
                                                            <span class="badge bg-light-success text-success">DEVENGADO</span>
                                                        <?php elseif (($crg['estado'] ?? '') === 'PROVISIONAL'): ?>
                                                            <span class="badge bg-light-warning text-warning">PROVISIONAL</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light-danger text-danger">ANULADO</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Pagos del Folio -->
                                    <?php if (!empty($fInfo['pagos'])): ?>
                                    <div class="p-2 bg-light f-s-12 f-w-600 border-bottom border-top">Pagos Confirmados</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0 f-s-12">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Medio de Pago</th>
                                                    <th>Monto Total</th>
                                                    <th>Monto Aplicado</th>
                                                    <th>Remanente Disponible</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($fInfo['pagos'] as $pag): ?>
                                                <tr>
                                                    <td><?= e($pag['codigo'] ?? '') ?></td>
                                                    <td><?= e($pag['metodo_pago_nombre'] ?? 'Pago') ?></td>
                                                    <td class="f-w-600 text-success">S/ <?= number_format((float) ($pag['monto_total'] ?? 0), 2) ?></td>
                                                    <td class="text-info">S/ <?= number_format((float) ($pag['monto_aplicado'] ?? 0), 2) ?></td>
                                                    <td class="text-dark">S/ <?= number_format((float) ($pag['saldo_disponible'] ?? 0), 2) ?></td>
                                                    <td><span class="badge bg-light-success text-success"><?= e($pag['estado'] ?? '') ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Recibos de Pago Emitidos -->
                        <h6 class="f-w-700 text-primary f-s-14 mt-4 mb-3">Recibos de Cobro Emitidos</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 f-s-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código Recibo</th>
                                        <th>Emisión</th>
                                        <th>Medio de Pago</th>
                                        <th>Concepto</th>
                                        <th>Monto Recaudado</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($estadoCuenta['recibos'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3 text-muted">No existen recibos de cobro formal emitidos para esta persona.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($estadoCuenta['recibos'] as $rec): ?>
                                    <tr>
                                        <td class="f-w-700 text-primary"><?= e($rec['codigo']) ?></td>
                                        <td><?= substr((string) $rec['fecha_emision'], 0, 16) ?></td>
                                        <td><?= e($rec['metodo_pago_nombre']) ?></td>
                                        <td><?= e($rec['concepto_general']) ?></td>
                                        <td class="f-w-600 text-success">S/ <?= number_format((float) $rec['monto_recaudado'], 2) ?></td>
                                        <td>
                                            <?php if ($rec['estado'] === 'EMITIDO'): ?>
                                                <span class="badge bg-light-success text-success">EMITIDO</span>
                                            <?php else: ?>
                                                <span class="badge bg-light-danger text-danger">ANULADO</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 6. TAB: SERVICIOS CONTRATADOS -->
                    <div class="tab-pane fade" id="tab-servicios" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 f-s-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código Servicio</th>
                                        <th>Reserva</th>
                                        <th>Concepto</th>
                                        <th>Fecha</th>
                                        <th>Cantidad</th>
                                        <th>P. Unitario</th>
                                        <th>Total Venta</th>
                                        <th>Estado Operativo</th>
                                        <th>Estado Financiero</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($servicios)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No existen consumos de servicios adicionales registrados.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($servicios as $srv): ?>
                                    <tr>
                                        <td class="f-w-700 text-dark"><?= e($srv['codigo']) ?></td>
                                        <td><?= e($srv['reserva_codigo'] ?? '-') ?></td>
                                        <td><strong><?= e($srv['concepto']) ?></strong></td>
                                        <td><?= e($srv['fecha_servicio']) ?></td>
                                        <td><?= number_format((float) $srv['cantidad'], 2) ?></td>
                                        <td>S/ <?= number_format((float) $srv['precio_unitario'], 2) ?></td>
                                        <td class="f-w-600">S/ <?= number_format((float) $srv['total_venta'], 2) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($srv['estado_operativo']) ?></span></td>
                                        <td>
                                            <?php if ($srv['estado_financiero'] === 'FACTURADO' || $srv['estado_financiero'] === 'PAGADO'): ?>
                                                <span class="badge bg-light-success text-success"><?= e($srv['estado_financiero']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light-warning text-warning"><?= e($srv['estado_financiero']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 7. TAB: DOCUMENTOS EMITIDOS -->
                    <div class="tab-pane fade" id="tab-documentos" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 f-s-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>Folio Documental</th>
                                        <th>Plantilla</th>
                                        <th>Origen</th>
                                        <th>Emisión</th>
                                        <th>Estado</th>
                                        <th class="text-end">Archivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($documentos)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No existen documentos generados o contratos emitidos para esta persona.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($documentos as $doc): ?>
                                    <tr>
                                        <td class="f-w-700 text-primary"><?= e($doc['codigo_folio']) ?></td>
                                        <td><?= e($doc['plantilla_nombre']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($doc['origen_tipo']) ?> #<?= (int) $doc['origen_id'] ?></span></td>
                                        <td><?= substr((string) $doc['emitido_en'], 0, 16) ?></td>
                                        <td>
                                            <?php if ($doc['estado'] === 'VALIDO'): ?>
                                                <span class="badge bg-light-success text-success">VÁLIDO</span>
                                            <?php else: ?>
                                                <span class="badge bg-light-danger text-danger">ANULADO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/documentos/descargar/<?= (int) $doc['id'] ?>" class="btn btn-outline-danger btn-sm" target="_blank" title="Ver documento PDF">
                                                <i class="fa-solid fa-file-pdf me-1"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 8. TAB: PREFERENCIAS Y NOTAS -->
                    <div class="tab-pane fade" id="tab-notas" role="tabpanel">
                        <form id="form-actualizar-preferencias" class="app-form app-icon-form">
                            <div class="row g-3">
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-w-600 f-s-13" for="perfil-canal">Canal de Captación Comercial</label>
                                    <select class="form-select basic-select2" name="canal_captacion" id="perfil-canal">
                                        <option value="">Seleccione canal...</option>
                                        <?php foreach ($canales as $codCanal => $nomCanal): ?>
                                            <option value="<?= e($codCanal) ?>" <?= ($cliente['canal_captacion'] === $codCanal ? 'selected' : '') ?>>
                                                <?= e($nomCanal) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label f-w-600 f-s-13" for="perfil-categoria">Categoría de Cliente</label>
                                    <select class="form-select basic-select2" name="categoria_id" id="perfil-categoria">
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= (int) $cat->obtenerId() ?>" <?= ((int) $cliente['categoria_id'] === (int) $cat->obtenerId() ? 'selected' : '') ?>>
                                                <?= e($cat->obtenerNombre()) ?> (<?= e($cat->obtenerCodigo()) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label f-w-600 f-s-13" for="perfil-preferencias">Preferencias Declaradas del Cliente</label>
                                    <div class="icon-control position-relative icon-textarea">
                                        <i class="fa-solid fa-star position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                        <textarea class="form-control ps-5" name="preferencias" id="perfil-preferencias" rows="3"
                                                  placeholder="Preferencias específicas de estancia, ubicación de habitación, amenities, etc."><?= e($cliente['preferencias'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label f-w-600 f-s-13" for="perfil-observaciones">Observaciones Comerciales y Operativas</label>
                                    <div class="icon-control position-relative icon-textarea">
                                        <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                                        <textarea class="form-control ps-5" name="observaciones" id="perfil-observaciones" rows="3"
                                                  placeholder="Notas internas de recepción, historial de incidentes o atenciones especiales..."><?= e($cliente['observaciones'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <?php if ($permisos['puede_editar']): ?>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary btn-sm" id="btn-guardar-notas">
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios Comerciales
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR PERFIL COMERCIAL -->
<div class="modal fade" id="modal-editar-perfil" tabindex="-1" aria-labelledby="modalEditarPerfilTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title f-w-700" id="modalEditarPerfilTitulo">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Editar Perfil Comercial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-modal-editar-perfil" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600" for="modal-edit-categoria">Categoría Comercial <span class="text-danger">*</span></label>
                        <select class="form-select basic-select2" name="categoria_id" id="modal-edit-categoria" required>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= (int) $cat->obtenerId() ?>" <?= ((int) $cliente['categoria_id'] === (int) $cat->obtenerId() ? 'selected' : '') ?>>
                                    <?= e($cat->obtenerNombre()) ?> (<?= e($cat->obtenerCodigo()) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600" for="modal-edit-canal">Canal de Captación</label>
                        <select class="form-select basic-select2" name="canal_captacion" id="modal-edit-canal">
                            <option value="">Seleccione canal...</option>
                            <?php foreach ($canales as $codCanal => $nomCanal): ?>
                                <option value="<?= e($codCanal) ?>" <?= ($cliente['canal_captacion'] === $codCanal ? 'selected' : '') ?>>
                                    <?= e($nomCanal) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600" for="modal-edit-preferencias">Preferencias</label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-star position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control form-control-sm ps-5" name="preferencias" id="modal-edit-preferencias" rows="2"><?= e($cliente['preferencias'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600" for="modal-edit-observaciones">Observaciones</label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-comment position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control form-control-sm ps-5" name="observaciones" id="modal-edit-observaciones" rows="2"><?= e($cliente['observaciones'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-modal-guardar-perfil">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: BLOQUEAR CLIENTE -->
<div class="modal fade" id="modal-bloquear-cliente-ficha" tabindex="-1" aria-labelledby="modalBloquearFichaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title f-w-700" id="modalBloquearFichaTitulo">
                    <i class="fa-solid fa-user-lock me-2"></i> Bloquear Cliente Comercial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="form-bloquear-cliente-ficha" class="app-form app-icon-form" novalidate>
                <div class="modal-body p-4">
                    <div class="alert alert-danger f-s-13 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        El bloqueo comercial genera una advertencia visual prominente en la Ficha 360° y en la recepción. Requiere un motivo justificado obligatorio.
                    </div>
                    <div class="mb-3">
                        <label class="form-label f-s-12 f-w-600">Cliente a bloquear:</label>
                        <p class="f-w-700 mb-0"><?= e($cliente['codigo']) ?> — <?= e($nombreCompleto) ?></p>
                    </div>
                    <div class="mb-2">
                        <label class="form-label f-s-12 f-w-600" for="ficha-bloquear-motivo">Motivo del Bloqueo <span class="text-danger">*</span></label>
                        <div class="icon-control position-relative icon-textarea">
                            <i class="fa-solid fa-shield-halved position-absolute top-0 start-0 mt-3 ms-3 text-secondary"></i>
                            <textarea class="form-control ps-5" id="ficha-bloquear-motivo" rows="3" required
                                      placeholder="Detalle la justificación comercial o de seguridad para el bloqueo..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-ficha-confirmar-bloqueo">
                        <i class="fa-solid fa-lock me-1"></i> Confirmar Bloqueo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPTS DE LA FICHA 360° -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-global').value;
    const clienteId = document.getElementById('cliente-id-actual').value;

    const modalEditarEl = document.getElementById('modal-editar-perfil');
    const modalEditar = modalEditarEl ? new bootstrap.Modal(modalEditarEl) : null;
    const btnEditarTop = document.getElementById('btn-editar-perfil-top');

    if (btnEditarTop && modalEditar) {
        btnEditarTop.addEventListener('click', function () {
            modalEditar.show();
        });
    }

    // Modal Bloquear
    const modalBloquearEl = document.getElementById('modal-bloquear-cliente-ficha');
    const modalBloquear = modalBloquearEl ? new bootstrap.Modal(modalBloquearEl) : null;
    const btnBloquearTop = document.getElementById('btn-bloquear-top');

    if (btnBloquearTop && modalBloquear) {
        btnBloquearTop.addEventListener('click', function () {
            modalBloquear.show();
        });
    }

    // Submit Modal Editar Perfil
    const formModalEditar = document.getElementById('form-modal-editar-perfil');
    if (formModalEditar) {
        formModalEditar.addEventListener('submit', function (e) {
            e.preventDefault();
            const payload = {
                csrf_token: csrfToken,
                categoria_id: document.getElementById('modal-edit-categoria').value,
                canal_captacion: document.getElementById('modal-edit-canal').value,
                preferencias: document.getElementById('modal-edit-preferencias').value,
                observaciones: document.getElementById('modal-edit-observaciones').value,
            };

            fetch(`/api/clientes/${clienteId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.exito) {
                    modalEditar.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Perfil Actualizado',
                        text: res.mensaje,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                }
            });
        });
    }

    // Submit Tab Preferencias y Notas
    const formNotas = document.getElementById('form-actualizar-preferencias');
    if (formNotas) {
        formNotas.addEventListener('submit', function (e) {
            e.preventDefault();
            const payload = {
                csrf_token: csrfToken,
                categoria_id: document.getElementById('perfil-categoria').value,
                canal_captacion: document.getElementById('perfil-canal').value,
                preferencias: document.getElementById('perfil-preferencias').value,
                observaciones: document.getElementById('perfil-observaciones').value,
            };

            const btn = document.getElementById('btn-guardar-notas');
            btn.disabled = true;

            fetch(`/api/clientes/${clienteId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                if (res.exito) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cambios Guardados',
                        text: res.mensaje,
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                }
            })
            .catch(() => {
                btn.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación.' });
            });
        });
    }

    // Submit Bloquear
    const formBloquear = document.getElementById('form-bloquear-cliente-ficha');
    if (formBloquear) {
        formBloquear.addEventListener('submit', function (e) {
            e.preventDefault();
            const motivo = document.getElementById('ficha-bloquear-motivo').value.trim();
            if (!motivo) {
                Swal.fire({ icon: 'warning', title: 'Motivo obligatorio', text: 'Debe ingresar el motivo de bloqueo.' });
                return;
            }

            fetch(`/api/clientes/${clienteId}/bloquear`, {
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
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                }
            });
        });
    }

    // Desbloquear desde Banner o Top
    function reactivarCliente() {
        Swal.fire({
            title: '¿Reactivar cliente comercial?',
            text: 'El estado pasará a ACTIVO y se removerá la restricción visual.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`/api/clientes/${clienteId}/desbloquear`, {
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
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.mensaje });
                    }
                });
            }
        });
    }

    const btnDesbloquearTop = document.getElementById('btn-desbloquear-top');
    if (btnDesbloquearTop) btnDesbloquearTop.addEventListener('click', reactivarCliente);

    const btnDesbloquearBanner = document.getElementById('btn-desbloquear-banner');
    if (btnDesbloquearBanner) btnDesbloquearBanner.addEventListener('click', reactivarCliente);
});
</script>
