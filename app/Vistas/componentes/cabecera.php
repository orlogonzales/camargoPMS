<?php

declare(strict_types=1);

/**
 * Componente Cabecera: Barra superior de la aplicación (header.header-main).
 *
 * @var string $titulo Título de la página preparado por el controlador.
 */
?>
<!-- Header Section starts -->
<header class="header-main">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-6 head-left">
                <div class="d-flex align-items-center gap-3">
                    <span class="cursor-pointer main-side-toggle" title="Alternar menú lateral">
                        <i class="ti ti-align-justified f-s-22 text-secondary"></i>
                    </span>
                    <h4 class="txt-ellipsis-2 mb-0 f-s-18">
                        Camargo PMS <span class="badge bg-light text-primary border ms-2">UI-0</span>
                    </h4>
                </div>
            </div>
            <div class="col-6 head-right">
                <ul class="d-flex gap-2 align-items-center justify-content-end mb-0 list-unstyled">
                    <!-- Pantalla completa -->
                    <li class="head-maximize-screen" title="Pantalla completa">
                        <span class="h-40 w-40 d-flex-center b-r-50 head-icon cursor-pointer">
                            <i class="ti ti-arrows-maximize"></i>
                        </span>
                    </li>

                    <!-- Alternador de tema claro / oscuro -->
                    <li class="header-dark" title="Cambiar tema claro/oscuro">
                        <div class="sun-logo h-40 w-40 d-flex-center b-r-50 head-icon cursor-pointer">
                            <i id="theme-icon" class="ti ti-moon-stars"></i>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
<!-- Header Section ends -->
