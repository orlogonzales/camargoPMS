<?php

declare(strict_types=1);

/**
 * Vista Reutilizable de Error HTTP — Camargo PMS.
 * Basada fielmente en la estructura visual de las páginas error_*.html de Alina.
 *
 * @var int $codigo Código de estado HTTP (400, 403, 404, 500, 503).
 * @var string $titulo Título descriptivo del error.
 * @var string $mensaje Mensaje seguro para el usuario.
 * @var string $accion Texto del botón de retorno (por defecto "Volver al Inicio").
 * @var string $urlRetorno URL hacia donde dirige el botón de acción.
 * @var string|null $imagen URL de la imagen ilustrativa de Alina.
 */

$codigoValido = in_array($codigo ?? 404, [400, 403, 404, 500, 503], true) ? (int)$codigo : 404;
$rutaImagen = $imagen ?? url_asset("images/error/error-{$codigoValido}.png");
$textoAccion = $accion ?? 'Volver al Inicio';
$destinoRetorno = $urlRetorno ?? url_ruta('/');
?>
<div>
    <div>
        <img alt="Error <?= $codigoValido ?>"
             class="img-fluid"
             src="<?= e($rutaImagen) ?>"
             style="max-height: 320px;">
    </div>
    <div class="mb-3">
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <h3 class="f-w-700 text-dark mt-4 mb-2"><?= e($titulo ?? "Error {$codigoValido}") ?></h3>
                <p class="text-center text-secondary f-w-500 mt-3 f-s-16">
                    <?= e($mensaje ?? 'Ha ocurrido una situación inesperada al procesar la petición.') ?>
                </p>
            </div>
        </div>
    </div>
    <a class="btn btn-lg app-btn bg-gradient-primary text-white mt-2 px-4 py-2"
       href="<?= e($destinoRetorno) ?>"
       role="button">
        <i class="ti ti-arrow-bar-to-left f-s-20 align-text-top me-2"></i> <?= e($textoAccion) ?>
    </a>
</div>
