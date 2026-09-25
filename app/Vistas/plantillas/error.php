<?php

declare(strict_types=1);

/**
 * Plantilla de Errores HTTP de Camargo PMS.
 * Basada en la estructura visual aislada de error_*.html de Alina (.error-container).
 *
 * @var string $contenido Contenido de la vista de error.
 * @var string $titulo Título de la página de error.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Error del sistema — Camargo PMS">
    <title><?= e($titulo ?? 'Error — Camargo PMS') ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= url_asset('images/logo/favicon.png') ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= url_asset('images/logo/favicon.png') ?>">

    <!-- Tipografía oficial Google Fonts: Lexend Deca -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">

    <!-- Iconos oficiales: Tabler Icons -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('vendor/tabler-icons/tabler-icons.css') ?>">

    <!-- Framework visual: Bootstrap 5 -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('vendor/bootstrap/bootstrap.min.css') ?>">

    <!-- Estilos base de Alina -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('css/style.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= url_asset('css/responsive.css') ?>">

    <!-- Ajustes propios Camargo PMS -->
    <link rel="stylesheet" type="text/css" href="<?= url_asset('css/camargo.css') ?>">
</head>
<body>

<div class="error-container p-0 text-center d-flex align-items-center justify-content-center min-vh-100">
    <div class="container py-4">
        <?= $contenido ?>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="<?= url_asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>

</body>
</html>
