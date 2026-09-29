<?php

declare(strict_types=1);

/**
 * Componente Personalizador de Plantilla (Theme Customizer) y Flotante Lateral.
 * Basado fielmente en el contrato visual de Alina (index.html / blank.html).
 *
 * Funcionalidad:
 * - Flotante lateral derecho con exactamente dos funciones: Configuración de plantilla y Soporte (#).
 * - Offcanvas interactivo completamente en español para preferencias visuales locales.
 * - Cero enlaces comerciales, cero botones de compra, cero referencias a tiendas externas.
 * - Botón Restablecer centrado que devuelve las preferencias a los valores oficiales de Camargo PMS.
 * - Persistencia soberana en el navegador (localStorage), sin alterar la base de datos.
 */
?>
<!-- Flotante lateral derecho oficial de Alina -->
<div class="theme-customizer-container" id="theme-customizer">
    <div class="customizer-box" title="Configuración de plantilla">
        <span class="w-35 h-35 d-flex-center b-r-12 cursor-pointer customizer-settings-btn"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasPersonalizador"
              role="button"
              aria-label="Abrir personalizador de plantilla">
            <i class="fa-solid fa-gear text-white f-s-18"></i>
        </span>
    </div>
    <div class="customizer-box mt-2" title="Soporte técnico">
        <a href="#" class="w-35 h-35 d-flex-center b-r-12 cursor-pointer text-decoration-none"
           aria-label="Soporte técnico">
            <i class="fa-solid fa-headset text-white f-s-18"></i>
        </a>
    </div>
</div>

<!-- Panel lateral Offcanvas del Personalizador -->
<div class="offcanvas offcanvas-end canvas-settings" tabindex="-1" id="offcanvasPersonalizador" aria-labelledby="offcanvasPersonalizadorTitulo">
    <div class="offcanvas-header bg-dark">
        <h5 class="offcanvas-title text-white f-s-16 f-w-600 mb-0" id="offcanvasPersonalizadorTitulo">
            <i class="fa-solid fa-sliders me-2"></i>Personalizador de Plantilla
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body">
        <!-- 1. Colores de Tema -->
        <div>
            <span class="title-badge-text f-w-600 f-s-13 text-secondary text-uppercase">Colores de Tema:</span>
            <ul class="theme-color-list d-flex align-items-center gap-3 m-3 ps-0 list-unstyled">
                <li class="theme-gradient-1 cursor-pointer active" data-theme="theme-gradient-1" title="Gradiente Azul Océano (Predeterminado)"></li>
                <li class="theme-gradient-2 cursor-pointer" data-theme="theme-gradient-2" title="Gradiente Verde Bosque"></li>
                <li class="theme-gradient-3 cursor-pointer" data-theme="theme-gradient-3" title="Gradiente Púrpura Magenta"></li>
                <li class="theme-gradient-4 cursor-pointer" data-theme="theme-gradient-4" title="Gradiente Ámbar Cálido"></li>
                <li class="theme-gradient-5 cursor-pointer" data-theme="theme-gradient-5" title="Gradiente Carmesí Coral"></li>
                <li class="theme-gradient-6 cursor-pointer" data-theme="theme-gradient-6" title="Gradiente Esmeralda Petróleo"></li>
            </ul>
        </div>

        <!-- 2. Disposición de Diseño -->
        <div class="mt-4">
            <span class="title-badge-text f-w-600 f-s-13 text-secondary text-uppercase">Disposición de Diseño:</span>
            <ul class="d-flex mt-3 gap-3 theme-layout-list ps-0 list-unstyled">
                <li class="ltr-layout cursor-pointer active" data-layout="ltr">
                    <ul class="layout ps-0 list-unstyled">
                        <li class="layout-badge"><span class="badge bg-secondary cursor-pointer">LTR</span></li>
                        <li class="sidebar"></li>
                        <li class="content">
                            <ul>
                                <li class="header"></li>
                                <li class="body"></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="rtl-layout cursor-pointer" data-layout="rtl">
                    <ul class="layout ps-0 list-unstyled">
                        <li class="layout-badge"><span class="badge bg-secondary cursor-pointer">RTL</span></li>
                        <li class="sidebar"></li>
                        <li class="content">
                            <ul>
                                <li class="header"></li>
                                <li class="body"></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="box-layout cursor-pointer" data-layout="box">
                    <ul class="layout ps-0 list-unstyled">
                        <li class="layout-badge"><span class="badge bg-secondary cursor-pointer">Caja</span></li>
                        <li class="sidebar"></li>
                        <li class="content">
                            <ul>
                                <li class="header"></li>
                                <li class="body"></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- 3. Variante de Barra Lateral -->
        <div class="mt-4">
            <span class="title-badge-text f-w-600 f-s-13 text-secondary text-uppercase">Variante de Barra Lateral:</span>
            <ul class="d-flex mt-3 gap-3 theme-sidebar-variant-list ps-0 list-unstyled">
                <li class="vertical-sidebar cursor-pointer active" data-sidebar="vertical">
                    <ul class="layout ps-0 list-unstyled">
                        <li class="layout-badge"><span class="badge bg-secondary cursor-pointer">Vertical</span></li>
                        <li class="sidebar"></li>
                        <li class="content">
                            <ul>
                                <li class="header"></li>
                                <li class="body"></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="horizontal-sidebar cursor-pointer" data-sidebar="horizontal">
                    <ul class="layout ps-0 list-unstyled">
                        <li class="layout-badge"><span class="badge bg-secondary cursor-pointer">Horizontal</span></li>
                        <li class="sidebar"></li>
                        <li class="content">
                            <ul>
                                <li class="body"></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="dark-sidebar cursor-pointer" data-sidebar="dark">
                    <ul class="layout ps-0 list-unstyled">
                        <li class="layout-badge"><span class="badge bg-secondary cursor-pointer">Oscura</span></li>
                        <li class="sidebar"></li>
                        <li class="content">
                            <ul>
                                <li class="header"></li>
                                <li class="body"></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- 4. Escala Tipográfica -->
        <div class="mt-4">
            <span class="title-badge-text f-w-600 f-s-13 text-secondary text-uppercase">Escala de Texto:</span>
            <ul class="d-flex mt-3 gap-3 theme-sizing-list ps-0 list-unstyled">
                <li class="w-100 cursor-pointer b-r-24 text-center py-2 border f-s-13" data-size="small-text">
                    Pequeño
                </li>
                <li class="w-100 cursor-pointer b-r-24 text-center py-2 border f-s-14 active" data-size="medium-text">
                    Mediano
                </li>
                <li class="w-100 cursor-pointer b-r-24 text-center py-2 border f-s-15" data-size="large-text">
                    Grande
                </li>
            </ul>
        </div>
    </div>

    <!-- Pie del Offcanvas: Restablecer centrado oficial -->
    <div class="offcanvas-footer p-3 border-top bg-light">
        <button type="button" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2" id="btn-restablecer-personalizador">
            <i class="fa-solid fa-rotate-left"></i>
            <span>Restablecer Ajustes</span>
        </button>
    </div>
</div>
