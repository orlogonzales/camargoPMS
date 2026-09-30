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

<!-- Selector de fechas oficial: Flatpickr (Alina) -->
<script src="<?= url_asset('vendor/flatpickr/flatpickr.js') ?>"></script>

<!-- Controlador propio defensivo de selectores de fecha Camargo PMS -->
<script src="<?= url_asset('js/camargo-pickers.js') ?>"></script>

<!-- Dependencia técnica exclusiva para Select2 de Alina (0 AJAX, 0 CRUD) -->
<script src="<?= url_asset('vendor/jquery/jquery.min.js') ?>"></script>

<!-- Librería oficial de selectores Select2 (Alina) -->
<script src="<?= url_asset('vendor/select/select2.min.js') ?>"></script>

<!-- Controlador propio defensivo de selectores enriquecidos Camargo PMS -->
<script src="<?= url_asset('js/camargo-select.js') ?>"></script>

<!-- Validador de formularios oficial: PristineJS (Alina) -->
<script src="<?= url_asset('vendor/pristine/pristine.min.js') ?>"></script>

<!-- Controlador propio defensivo de formularios y validación Camargo PMS -->
<script src="<?= url_asset('js/camargo-forms.js') ?>"></script>

<!-- Controlador propio defensivo de interfaz Camargo PMS -->
<script src="<?= url_asset('js/camargo-layout.js') ?>"></script>

