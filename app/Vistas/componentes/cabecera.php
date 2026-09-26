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

                    <?php
                    $usuarioActual = usuario_autenticado();
                    if ($usuarioActual !== null):
                    ?>
                    <!-- Perfil y Cierre de Sesión -->
                    <li class="head-profile dropdown">
                        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle p-1 rounded" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="h-35 w-35 d-flex-center b-r-50 bg-primary text-white">
                                <i class="ti ti-user f-s-18"></i>
                            </span>
                            <span class="d-none d-md-inline-block text-dark f-s-14 f-w-500">
                                <?= e($usuarioActual->obtenerNombreUsuario()) ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow p-2">
                            <li class="px-3 py-2 border-bottom mb-2">
                                <div class="f-w-600 f-s-14 text-dark"><?= e($usuarioActual->obtenerNombreUsuario()) ?></div>
                                <div class="f-s-12 text-secondary">Usuario autenticado</div>
                            </li>
                            <li>
                                <form action="<?= url_ruta('/logout') ?>" method="POST" class="m-0 p-0">
                                    <?= csrf_campo() ?>
                                    <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger rounded py-2">
                                        <i class="ti ti-logout f-s-18"></i>
                                        <span>Cerrar sesión</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</header>
<!-- Header Section ends -->
