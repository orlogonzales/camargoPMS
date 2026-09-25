<?php

declare(strict_types=1);

use CamargoPMS\Nucleo\Vista;

/**
 * Plantilla Principal de Camargo PMS.
 * Basada en la arquitectura DOM de Alina blank.html.
 *
 * @var string $contenido Contenido renderizado de la vista secundaria.
 * @var string $titulo Título de la página.
 * @var array<int, array{etiqueta: string, url?: string, activo?: bool}> $migasPan
 * @var array<string, array<string, mixed>> $menuEstatico
 * @var string $categoriaActiva
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?= Vista::componente('head', ['titulo' => $titulo ?? 'Camargo PMS']) ?>
</head>
<body>
<div class="app-wrapper">

    <!-- Cargador neutro -->
    <?= Vista::componente('cargador') ?>

    <!-- Barra de navegación con contrato Alina -->
    <?= Vista::componente('navegacion', [
        'menuEstatico' => $menuEstatico ?? [],
        'categoriaActiva' => $categoriaActiva ?? 'inicio'
    ]) ?>

    <!-- Contenido principal de la aplicación -->
    <div class="app-content">
        <?= Vista::componente('cabecera', ['titulo' => $titulo ?? 'Camargo PMS']) ?>
        <?= Vista::componente('migas-pan', ['migasPan' => $migasPan ?? []]) ?>

        <main>
            <div class="container-fluid">
                <?= $contenido ?>
            </div>
        </main>
    </div>

    <!-- Botón volver arriba -->
    <div class="go-top">
        <span class="progress-value">
            <i class="ti ti-chevron-up"></i>
        </span>
    </div>

    <!-- Pie de página -->
    <?= Vista::componente('pie') ?>
</div>

<!-- Scripts globales y controlador de layout -->
<?= Vista::componente('scripts') ?>
</body>
</html>
