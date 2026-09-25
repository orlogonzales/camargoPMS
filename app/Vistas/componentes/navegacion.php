<?php

declare(strict_types=1);

use CamargoPMS\Nucleo\Vista;

/**
 * Componente Navegación: Contenedor principal de la barra de navegación Alina (nav.app-navbar).
 * Agrupa el menú de iconos primario y el menú secundario colapsable.
 *
 * @var array<string, array<string, mixed>> $menuEstatico
 * @var string $categoriaActiva
 */
?>
<!-- Menu Navigation starts -->
<nav class="app-navbar">
    <?= Vista::componente('menu-principal', [
        'menuEstatico' => $menuEstatico ?? [],
        'categoriaActiva' => $categoriaActiva ?? 'inicio'
    ]) ?>

    <?= Vista::componente('menu-secundario', [
        'menuEstatico' => $menuEstatico ?? [],
        'categoriaActiva' => $categoriaActiva ?? 'inicio'
    ]) ?>
</nav>
<!-- Menu Navigation ends -->
