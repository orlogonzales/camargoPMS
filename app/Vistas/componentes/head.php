<?php

declare(strict_types=1);

/**
 * Componente Head: Meta etiquetas, fuentes tipográficas y hojas de estilo globales.
 *
 * @var string $titulo Título de la página preparado por el controlador.
 */
?>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Camargo PMS — Plataforma centralizada de gestión hotelera e inmobiliaria">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($titulo ?? 'Camargo PMS') ?></title>

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

<!-- Scroll personalizado: Simplebar -->
<link rel="stylesheet" type="text/css" href="<?= url_asset('vendor/simplebar/simplebar.css') ?>">

<!-- Estilos base de la plantilla Alina -->
<link rel="stylesheet" type="text/css" href="<?= url_asset('css/style.css') ?>">
<link rel="stylesheet" type="text/css" href="<?= url_asset('css/responsive.css') ?>">

<!-- Ajustes y estilos propios de Camargo PMS -->
<link rel="stylesheet" type="text/css" href="<?= url_asset('css/camargo.css') ?>">
