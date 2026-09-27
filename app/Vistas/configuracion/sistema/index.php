<?php

declare(strict_types=1);

/**
 * Vista de Configuración General del Sistema — Camargo PMS (CONFIGURACIÓN-1).
 *
 * Principios vinculantes:
 * - CONFIGURACIÓN FUNCIONAL (BD) ≠ ENTORNO TÉCNICO (.env).
 * - CONTRATO DE TIPADO: Renderiza controles acordes a cada tipo de dato.
 * - PARÁMETROS PROTEGIDOS: No mutables por interfaz ni backend.
 *
 * @var array{puede_editar: bool} $capacidades
 * @var array<string, array<int, array<string, mixed>>> $agrupadas
 * @var string $csrf_token
 */
?>
<input type="hidden" id="csrf-token-global" value="<?= e($csrf_token) ?>">

<!-- Encabezado del Módulo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card equal-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3 d-flex-center">
                        <i class="fa-solid fa-sliders f-s-22"></i>
                    </span>
                    <div>
                        <h4 class="card-title mb-0 f-s-18 f-w-700">Configuración General del Sistema</h4>
                        <p class="text-secondary f-s-13 mb-0">
                            Parámetros funcionales y operacionales centralizados de Camargo PMS. Principio: <strong>Configuración (BD) ≠ Entorno (.env)</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <?php if (!empty($capacidades['puede_editar'])): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-configuracion">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Navegación por Pestañas de Grupos Funcionales -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-tabs-bottom px-4 pt-3 border-bottom" id="tabs-configuracion" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-medium" id="tab-general" data-bs-toggle="tab" data-bs-target="#panel-general" type="button" role="tab" aria-controls="panel-general" aria-selected="true">
                            <i class="fa-solid fa-building me-1"></i> General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-medium" id="tab-localizacion" data-bs-toggle="tab" data-bs-target="#panel-localizacion" type="button" role="tab" aria-controls="panel-localizacion" aria-selected="false">
                            <i class="fa-solid fa-globe me-1"></i> Localización
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-medium" id="tab-operacion" data-bs-toggle="tab" data-bs-target="#panel-operacion" type="button" role="tab" aria-controls="panel-operacion" aria-selected="false">
                            <i class="fa-solid fa-wrench me-1"></i> Operación
                        </button>
                    </li>
                </ul>

                <!-- Contenido de las Pestañas y Formulario Central -->
                <form id="form-configuracion-sistema" class="app-form p-4" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                    <div class="tab-content" id="contenido-tabs-configuracion">
                        <!-- Pestaña GENERAL -->
                        <div class="tab-pane fade show active" id="panel-general" role="tabpanel" aria-labelledby="tab-general">
                            <div class="row g-4">
                                <?php
                                $generalParams = $agrupadas['GENERAL'] ?? [];
                                foreach ($generalParams as $param):
                                    $esEditable = !empty($param['editable']);
                                    $clave = (string) $param['clave'];
                                    $tipo = (string) $param['tipo'];
                                    $valor = $param['valor'] ?? '';
                                    $valorPredet = $param['valor_predeterminado'] ?? '';
                                ?>
                                    <div class="col-md-6 col-12">
                                        <div class="card border p-3 h-100 bg-light-subtle">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <label for="cfg_<?= e(str_replace('.', '_', $clave)) ?>" class="form-label fw-bold mb-0">
                                                        <?= e($param['nombre']) ?>
                                                    </label>
                                                    <span class="badge bg-light-secondary font-monospace f-s-11 ms-1">
                                                        <?= e($clave) ?>
                                                    </span>
                                                </div>
                                                <?php if (!$esEditable): ?>
                                                    <span class="badge bg-light-danger f-s-11">
                                                        <i class="fa-solid fa-lock me-1"></i> Protegido
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($param['descripcion'])): ?>
                                                <p class="text-muted f-s-12 mb-3"><?= e($param['descripcion']) ?></p>
                                            <?php endif; ?>

                                            <div class="mb-2">
                                                <?php if ($tipo === 'FECHA'): ?>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>
                                                        <input type="text"
                                                               class="form-control form-control-sm campo-configuracion basic-date"
                                                               data-provider="datepicker"
                                                               id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                               name="configuraciones[<?= e($clave) ?>]"
                                                               value="<?= e((string)$valor) ?>"
                                                               placeholder="YYYY-MM-DD"
                                                               <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                    </div>
                                                <?php elseif ($tipo === 'TEXTO' && str_contains($clave, 'descripcion')): ?>
                                                    <textarea class="form-control form-control-sm campo-configuracion"
                                                              id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                              name="configuraciones[<?= e($clave) ?>]"
                                                              rows="3"
                                                              <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>><?= e((string)$valor) ?></textarea>
                                                <?php else: ?>
                                                    <input type="text"
                                                           class="form-control form-control-sm campo-configuracion"
                                                           id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                           name="configuraciones[<?= e($clave) ?>]"
                                                           value="<?= e((string)$valor) ?>"
                                                           <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                <?php endif; ?>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                                <small class="text-muted f-s-11">
                                                    Predeterminado: <code class="text-muted"><?= e((string)$valorPredet) ?></code>
                                                </small>
                                                <?php if ($esEditable && !empty($capacidades['puede_editar'])): ?>
                                                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 f-s-12 btn-restaurar-cfg" data-clave="<?= e($clave) ?>" title="Restaurar a valor de fábrica">
                                                        <i class="fa-solid fa-rotate-right me-1"></i> Restaurar
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Pestaña LOCALIZACION -->
                        <div class="tab-pane fade" id="panel-localizacion" role="tabpanel" aria-labelledby="tab-localizacion">
                            <div class="row g-4">
                                <?php
                                $locParams = $agrupadas['LOCALIZACION'] ?? [];
                                foreach ($locParams as $param):
                                    $esEditable = !empty($param['editable']);
                                    $clave = (string) $param['clave'];
                                    $tipo = (string) $param['tipo'];
                                    $valor = $param['valor'] ?? '';
                                    $valorPredet = $param['valor_predeterminado'] ?? '';
                                ?>
                                    <div class="col-md-6 col-12">
                                        <div class="card border p-3 h-100 bg-light-subtle">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <label for="cfg_<?= e(str_replace('.', '_', $clave)) ?>" class="form-label fw-bold mb-0">
                                                        <?= e($param['nombre']) ?>
                                                    </label>
                                                    <span class="badge bg-light-secondary font-monospace f-s-11 ms-1">
                                                        <?= e($clave) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <?php if (!empty($param['descripcion'])): ?>
                                                <p class="text-muted f-s-12 mb-3"><?= e($param['descripcion']) ?></p>
                                            <?php endif; ?>

                                            <div class="mb-2">
                                                <?php if ($tipo === 'FECHA'): ?>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>
                                                        <input type="text"
                                                               class="form-control form-control-sm campo-configuracion basic-date"
                                                               data-provider="datepicker"
                                                               id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                               name="configuraciones[<?= e($clave) ?>]"
                                                               value="<?= e((string)$valor) ?>"
                                                               placeholder="YYYY-MM-DD"
                                                               <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                    </div>
                                                <?php elseif ($clave === 'sistema.idioma'): ?>
                                                    <select class="form-select campo-configuracion basic-select2"
                                                            id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                            name="configuraciones[<?= e($clave) ?>]"
                                                            <?= !$esEditable || empty($capacidades['puede_editar']) ? 'disabled' : '' ?>>
                                                        <option value="es" <?= $valor === 'es' ? 'selected' : '' ?>>Español (es)</option>
                                                        <option value="en" <?= $valor === 'en' ? 'selected' : '' ?>>English (en)</option>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="text"
                                                           class="form-control form-control-sm campo-configuracion"
                                                           id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                           name="configuraciones[<?= e($clave) ?>]"
                                                           value="<?= e((string)$valor) ?>"
                                                           <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                <?php endif; ?>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                                <small class="text-muted f-s-11">
                                                    Predeterminado: <code class="text-muted"><?= e((string)$valorPredet) ?></code>
                                                </small>
                                                <?php if ($esEditable && !empty($capacidades['puede_editar'])): ?>
                                                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 f-s-12 btn-restaurar-cfg" data-clave="<?= e($clave) ?>" title="Restaurar a valor de fábrica">
                                                        <i class="fa-solid fa-rotate-right me-1"></i> Restaurar
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Pestaña OPERACION -->
                        <div class="tab-pane fade" id="panel-operacion" role="tabpanel" aria-labelledby="tab-operacion">
                            <div class="row g-4">
                                <?php
                                $opParams = $agrupadas['OPERACION'] ?? [];
                                foreach ($opParams as $param):
                                    $esEditable = !empty($param['editable']);
                                    $clave = (string) $param['clave'];
                                    $tipo = (string) $param['tipo'];
                                    $valor = $param['valor'] ?? '';
                                    $valorPredet = $param['valor_predeterminado'] ?? '';
                                ?>
                                    <div class="col-md-6 col-12">
                                        <div class="card border p-3 h-100 bg-light-subtle">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <label for="cfg_<?= e(str_replace('.', '_', $clave)) ?>" class="form-label fw-bold mb-0">
                                                        <?= e($param['nombre']) ?>
                                                    </label>
                                                    <span class="badge bg-light-secondary font-monospace f-s-11 ms-1">
                                                        <?= e($clave) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <?php if (!empty($param['descripcion'])): ?>
                                                <p class="text-muted f-s-12 mb-3"><?= e($param['descripcion']) ?></p>
                                            <?php endif; ?>

                                            <div class="mb-2">
                                                <?php if ($tipo === 'BOOLEANO'): ?>
                                                    <div class="form-check form-switch pt-1">
                                                        <input class="form-check-input f-s-18 campo-configuracion"
                                                               type="checkbox"
                                                               role="switch"
                                                               id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                               name="configuraciones[<?= e($clave) ?>]"
                                                               value="1"
                                                               <?= in_array(strtolower((string)$valor), ['1', 'true', 'si', 'on'], true) ? 'checked' : '' ?>
                                                               <?= !$esEditable || empty($capacidades['puede_editar']) ? 'disabled' : '' ?>>
                                                        <label class="form-check-label f-s-13 text-secondary" for="cfg_<?= e(str_replace('.', '_', $clave)) ?>">
                                                            Activar estado de parámetro
                                                        </label>
                                                    </div>
                                                <?php elseif ($tipo === 'ENTERO'): ?>
                                                    <input type="number"
                                                           step="1"
                                                           min="1"
                                                           max="1000"
                                                           class="form-control form-control-sm campo-configuracion"
                                                           id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                           name="configuraciones[<?= e($clave) ?>]"
                                                           value="<?= e((string)$valor) ?>"
                                                           <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                <?php elseif ($tipo === 'FECHA'): ?>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>
                                                        <input type="text"
                                                               class="form-control form-control-sm campo-configuracion basic-date"
                                                               data-provider="datepicker"
                                                               id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                               name="configuraciones[<?= e($clave) ?>]"
                                                               value="<?= e((string)$valor) ?>"
                                                               placeholder="YYYY-MM-DD"
                                                               <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                    </div>
                                                <?php else: ?>
                                                    <input type="text"
                                                           class="form-control form-control-sm campo-configuracion"
                                                           id="cfg_<?= e(str_replace('.', '_', $clave)) ?>"
                                                           name="configuraciones[<?= e($clave) ?>]"
                                                           value="<?= e((string)$valor) ?>"
                                                           <?= !$esEditable || empty($capacidades['puede_editar']) ? 'readonly disabled' : '' ?>>
                                                <?php endif; ?>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                                <small class="text-muted f-s-11">
                                                    Predeterminado: <code class="text-muted"><?= e((string)$valorPredet) ?></code>
                                                </small>
                                                <?php if ($esEditable && !empty($capacidades['puede_editar'])): ?>
                                                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 f-s-12 btn-restaurar-cfg" data-clave="<?= e($clave) ?>" title="Restaurar a valor de fábrica">
                                                        <i class="fa-solid fa-rotate-right me-1"></i> Restaurar
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="fa-solid fa-circle-info me-1"></i> Los cambios en la configuración se aplican de manera inmediata y auditable en todo el sistema.
                </small>
                <?php if (!empty($capacidades['puede_editar'])): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-configuracion-footer">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Dependencias y Módulo JavaScript -->
<script src="<?= url_asset('vendor/sweetalert/sweetalert.js') ?>"></script>
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>
<script src="<?= url_asset('js/gestion-configuracion.js') ?>"></script>
