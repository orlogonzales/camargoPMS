<?php

declare(strict_types=1);

/**
 * Componente Migas de Pan: Navegación jerárquica (.app-breadcrumbs).
 *
 * @var array<int, array{etiqueta: string, url?: string, activo?: bool}> $migasPan
 */
?>
<div>
    <ul class="app-breadcrumbs">
        <?php foreach ($migasPan ?? [] as $miga): ?>
            <?php $esActiva = !empty($miga['activo']); ?>
            <li class="<?= $esActiva ? 'active' : '' ?>">
                <?php if ($esActiva || empty($miga['url'])): ?>
                    <span class="f-s-14 f-w-500 text-secondary"><?= e($miga['etiqueta']) ?></span>
                <?php else: ?>
                    <a class="f-s-14 f-w-500" href="<?= e($miga['url']) ?>">
                        <span><?= e($miga['etiqueta']) ?></span>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
