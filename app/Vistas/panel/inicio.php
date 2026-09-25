<?php

declare(strict_types=1);

/**
 * Vista Inicial Neutra de Comprobación — Camargo PMS (Fase UI-0).
 *
 * @var array{nombre: string, version: string, entorno: string, phpVersion: string} $sistema
 */
?>
<div class="row">
    <!-- Tarjeta 1: Arquitectura MVC -->
    <div class="col-md-6 col-xxl-3 mb-4">
        <div class="card equal-card tarjeta-comprobacion h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="bg-primary-subtle text-primary p-2 b-r-8 me-3">
                        <i class="ti ti-layers-linked f-s-22"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-16">Arquitectura MVC</h5>
                        <span class="badge bg-success-subtle text-success badge-estado-pms">Verificado</span>
                    </div>
                </div>
                <p class="card-text text-secondary f-s-14">
                    Capas desacopladas (Front Controller, Enrutador, Controlador, Vista) con nomenclatura en español estricta.
                </p>
            </div>
        </div>
    </div>

    <!-- Tarjeta 2: Plantilla Alina Reutilizable -->
    <div class="col-md-6 col-xxl-3 mb-4">
        <div class="card equal-card tarjeta-comprobacion h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="bg-info-subtle text-info p-2 b-r-8 me-3">
                        <i class="ti ti-layout-dashboard f-s-22"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-16">Plantilla Alina</h5>
                        <span class="badge bg-success-subtle text-success badge-estado-pms">Verificado</span>
                    </div>
                </div>
                <p class="card-text text-secondary f-s-14">
                    Estructura DOM fiel a <code>blank.html</code>, componentes modulares y assets normalizados sin rutas relativas frágiles.
                </p>
            </div>
        </div>
    </div>

    <!-- Tarjeta 3: JavaScript Defensivo -->
    <div class="col-md-6 col-xxl-3 mb-4">
        <div class="card equal-card tarjeta-comprobacion h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="bg-warning-subtle text-warning p-2 b-r-8 me-3">
                        <i class="ti ti-brand-javascript f-s-22"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-16">JavaScript Propio</h5>
                        <span class="badge bg-success-subtle text-success badge-estado-pms">Verificado</span>
                    </div>
                </div>
                <p class="card-text text-secondary f-s-14">
                    <code>camargo-layout.js</code> moderno y tolerante a componentes opcionales. Sin dependencia de scripts demo ni errores de consola.
                </p>
            </div>
        </div>
    </div>

    <!-- Tarjeta 4: Gobernanza Activa -->
    <div class="col-md-6 col-xxl-3 mb-4">
        <div class="card equal-card tarjeta-comprobacion h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="bg-secondary-subtle text-secondary p-2 b-r-8 me-3">
                        <i class="ti ti-shield-check f-s-22"></i>
                    </span>
                    <div>
                        <h5 class="card-title mb-0 f-s-16">Gobernanza Activa</h5>
                        <span class="badge bg-success-subtle text-success badge-estado-pms">Verificado</span>
                    </div>
                </div>
                <p class="card-text text-secondary f-s-14">
                    Fase UI-0 aislada: sin conexión a BD, sin lógica funcional anticipada y con los originales de Alina preservados intactos.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Panel de verificación de entorno del sistema -->
<div class="row">
    <div class="col-12">
        <div class="card tarjeta-comprobacion">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between py-3">
                <h6 class="mb-0 text-dark f-w-600">
                    <i class="ti ti-server-cog text-primary me-2 f-s-18"></i> Estado de la Infraestructura de UI-0
                </h6>
                <span class="badge bg-primary text-white">Camargo PMS &bull; Fase UI-0</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 d-block">Entorno de Ejecución</span>
                            <strong class="text-dark f-s-15"><?= e($sistema['entorno'] ?? 'Local') ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 d-block">Versión de PHP</span>
                            <strong class="text-dark f-s-15">PHP <?= e($sistema['phpVersion'] ?? PHP_VERSION) ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 d-block">Contrato de Navegación</span>
                            <strong class="text-success f-s-15">6 Secciones Sincronizadas</strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <span class="text-secondary f-s-12 d-block">Acceso a Base de Datos</span>
                            <strong class="text-secondary f-s-15">Desconectado (Por Diseño)</strong>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border mt-4 mb-0" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-info-circle f-s-22 text-primary me-3"></i>
                        <div>
                            <strong>Comprobación de Layout exitosa:</strong>
                            Esta vista neutra confirma que el Front Controller, el Enrutador mínimo, la Plantilla Alina y el renderizado por capas se encuentran operativos. No existen métricas simuladas ni acceso a base de datos.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
