<?php

declare(strict_types=1);

/**
 * Componente Scripts: Carga de librerías globales de JavaScript y script propio del layout.
 */
?>
<!-- Bootstrap 5 Bundle JS -->
<script src="<?= url_asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>

<!-- Simplebar Scrollbar JS -->
<script src="<?= url_asset('vendor/simplebar/simplebar.js') ?>"></script>

<!-- Controlador propio defensivo de interfaz Camargo PMS -->
<script src="<?= url_asset('js/camargo-layout.js') ?>"></script>
