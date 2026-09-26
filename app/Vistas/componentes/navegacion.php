<?php

declare(strict_types=1);

use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\MenuServicio;

/**
 * Componente Navegación: Contenedor principal de la barra de navegación Alina (nav.app-navbar).
 * Agrupa el menú de iconos primario y el menú secundario colapsable.
 *
 * Principio Vinculante: MENÚ ≠ AUTORIZACIÓN.
 * Resuelve dinámicamente las opciones autorizadas basándose en el usuario autenticado y su RBAC.
 *
 * @var array<string, array<string, mixed>>|null $menu
 * @var array<string, array<string, mixed>>|null $menuDinamico
 * @var array<string, array<string, mixed>>|null $menuEstatico
 * @var string|null $categoriaActiva
 */

$usuario = usuario_autenticado();
$rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (isset($menuDinamico) && is_array($menuDinamico)) {
    $menuItems = $menuDinamico;
} elseif (isset($menu) && is_array($menu)) {
    $menuItems = $menu;
} elseif ($usuario !== null) {
    $menuServicio = new MenuServicio();
    $menuItems = $menuServicio->obtenerMenuParaUsuario($usuario, $rutaActual);
} else {
    $menuItems = $menuEstatico ?? [];
}

// Determinar categoría activa a partir de los datos calculados
$catActiva = $categoriaActiva ?? null;
if ($catActiva === null || !isset($menuItems[$catActiva])) {
    foreach ($menuItems as $clave => $item) {
        if (!empty($item['activo'])) {
            $catActiva = $clave;
            break;
        }
    }
}
if ($catActiva === null && !empty($menuItems)) {
    $catActiva = array_key_first($menuItems);
}
?>
<!-- Menu Navigation starts -->
<nav class="app-navbar">
    <?= Vista::componente('menu-principal', [
        'menu' => $menuItems,
        'categoriaActiva' => $catActiva ?? 'inicio'
    ]) ?>

    <?= Vista::componente('menu-secundario', [
        'menu' => $menuItems,
        'categoriaActiva' => $catActiva ?? 'inicio'
    ]) ?>
</nav>
<!-- Menu Navigation ends -->
