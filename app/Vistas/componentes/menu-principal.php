<?php

declare(strict_types=1);

/**
 * Componente Menú Principal: Barra de iconos primaria (.semi-side-nav).
 * Implementa el disparador del contrato de navegación con [data-target].
 *
 * @var array<string, array<string, mixed>> $menuEstatico
 * @var string $categoriaActiva
 */
?>
<div class="semi-side-nav">
    <div class="py-4">
        <a href="<?= url_ruta('/') ?>" class="text-decoration-none">
            <span class="bg-white h-40 w-40 d-flex-center b-r-12 mx-auto shadow-sm">
                <span class="f-w-700 text-primary">CP</span>
            </span>
        </a>
    </div>

    <ul class="navbar-menu-list" role="tablist">
        <?php foreach ($menuEstatico as $clave => $item): ?>
            <?php $estaActivo = ($clave === $categoriaActiva); ?>
            <li class="nav-item">
                <a href="#"
                   class="nav-link <?= $estaActivo ? 'active' : '' ?>"
                   data-target="<?= e($item['clave']) ?>"
                   title="<?= e($item['etiqueta']) ?>">
                    <i class="<?= e($item['icono']) ?>"></i>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="mt-auto pb-3 text-center">
        <span class="bg-primary-800 h-45 w-45 d-flex-center b-r-30 position-relative mx-auto" title="Camargo PMS">
            <img alt="avatar" class="img-fluid b-r-30" src="<?= url_asset('images/avatar/01.png') ?>">
            <span class="position-absolute top-0 end-0 p-1 bg-gradient-success border border-light rounded-circle"></span>
        </span>
    </div>
</div>
