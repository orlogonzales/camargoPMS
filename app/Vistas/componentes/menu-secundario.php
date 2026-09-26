<?php

declare(strict_types=1);

/**
 * Componente Menú Secundario: Panel lateral desplegable (.main-side-nav).
 * Responde al contrato de navegación mediante ul.main-menu con id="clave".
 *
 * @var array<string, array<string, mixed>>|null $menu
 * @var array<string, array<string, mixed>>|null $menuEstatico
 * @var string $categoriaActiva
 */

$menuItems = $menu ?? $menuEstatico ?? [];
?>
<div class="main-side-nav">
    <div class="p-3 d-flex align-items-center justify-content-between border-bottom">
        <a class="logo d-inline-block text-decoration-none" href="<?= url_ruta('/') ?>">
            <img alt="Camargo PMS" src="<?= url_asset('images/logo/1.png') ?>" style="max-height: 38px;">
        </a>
        <span class="w-30 h-30 d-none bg-gradient-danger b-r-8 cursor-pointer side-toggle d-flex-center">
            <i class="ti ti-x f-s-18 text-white"></i>
        </span>
    </div>

    <div class="nav-wrapper app-scroll app-simple-bar">
        <div class="main-side-menu">
            <?php foreach ($menuItems as $clave => $item): ?>
                <?php $estaActivo = ($clave === $categoriaActiva); ?>
                <ul class="main-menu" id="<?= e($item['clave']) ?>" style="display: <?= $estaActivo ? 'block' : 'none' ?>;">
                    <li class="menu-title px-3 pt-3 pb-1 text-uppercase f-s-11 text-secondary f-w-600">
                        <?= e($item['etiqueta']) ?>
                    </li>

                    <?php foreach ($item['grupos'] as $grupo): ?>
                        <?php if ($grupo['tipo'] === 'simple'): ?>
                            <li class="no-sub">
                                <a href="<?= e($grupo['url']) ?>" class="<?= !empty($grupo['activo']) ? 'active' : '' ?>">
                                    <?php if (!empty($grupo['icono'])): ?>
                                        <i class="<?= e($grupo['icono']) ?> me-2"></i>
                                    <?php endif; ?>
                                    <?= e($grupo['titulo']) ?>
                                </a>
                            </li>
                        <?php elseif ($grupo['tipo'] === 'colapsable'): ?>
                            <li>
                                <a aria-expanded="false" data-bs-toggle="collapse" href="#<?= e($grupo['id']) ?>">
                                    <?php if (!empty($grupo['icono'])): ?>
                                        <i class="<?= e($grupo['icono']) ?> me-2"></i>
                                    <?php endif; ?>
                                    <?= e($grupo['titulo']) ?>
                                </a>
                                <ul class="collapse" id="<?= e($grupo['id']) ?>">
                                    <?php foreach ($grupo['items'] as $subItem): ?>
                                        <li>
                                            <a href="<?= e($subItem['url']) ?>">
                                                <?= e($subItem['titulo']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    </div>
</div>
