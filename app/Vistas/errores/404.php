<?php

declare(strict_types=1);

/**
 * Vista de Error 404 — Ruta No Encontrada.
 *
 * @var string|null $rutaSolicitada
 */
?>
<div class="row justify-content-center py-5">
    <div class="col-md-8 col-lg-6 text-center">
        <div class="card tarjeta-comprobacion p-4">
            <div class="card-body">
                <div class="mb-4">
                    <span class="bg-danger-subtle text-danger p-3 b-r-50 d-inline-flex">
                        <i class="ti ti-alert-triangle f-s-40"></i>
                    </span>
                </div>
                <h2 class="text-dark f-w-700 mb-2">404</h2>
                <h4 class="text-secondary mb-3 f-s-18">Página No Encontrada</h4>
                <p class="text-muted f-s-14 mb-4">
                    La ruta solicitada <code><?= e($rutaSolicitada ?? '') ?></code> no existe o no ha sido registrada en el sistema.
                </p>
                <div>
                    <a href="<?= url_ruta('/') ?>" class="btn btn-primary px-4 py-2">
                        <i class="ti ti-arrow-left me-2"></i> Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
