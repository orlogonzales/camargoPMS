/**
 * @file camargo-layout.js
 * @description Controlador de interfaz propio y defensivo para Camargo PMS.
 * Implementa el contrato de navegación Alina, comportamiento responsive,
 * cargador neutro, tema claro/oscuro y scrollbar sin dependencias de scripts demo.
 * @author Camargo PMS
 */

'use strict';

(function () {
    /**
     * Inicializa el cargador de la aplicación desvaneciéndolo al completar la carga.
     * @returns {void}
     */
    function inicializarCargador() {
        const cargador = document.querySelector('.loader-wrapper');
        if (!cargador) return;

        const ocultar = () => {
            cargador.classList.add('desvanecer');
            setTimeout(() => {
                if (cargador.parentNode) {
                    cargador.parentNode.removeChild(cargador);
                }
            }, 450);
        };

        if (document.readyState === 'complete') {
            ocultar();
        } else {
            window.addEventListener('load', ocultar, { once: true });
        }
    }

    /**
     * Activa una categoría del menú primario y su sección correspondiente en el menú secundario.
     * @param {string} claveDestino - Identificador de la sección a activar.
     * @returns {void}
     */
    function activarCategoria(claveDestino) {
        if (!claveDestino) return;

        const enlacesPrimarios = document.querySelectorAll('.semi-side-nav .navbar-menu-list .nav-link');
        const todosLosMenus = document.querySelectorAll('.main-side-menu .main-menu');
        const wrapper = document.querySelector('.app-wrapper');
        const esHorizontal = wrapper?.classList.contains('sidebar-horizontal');

        // Actualiza clases del menú de iconos primario
        enlacesPrimarios.forEach(enlace => {
            if (enlace.getAttribute('data-target') === claveDestino) {
                enlace.classList.add('active');
            } else {
                enlace.classList.remove('active');
            }
        });

        // Muestra el menú secundario correspondiente
        todosLosMenus.forEach(menu => {
            if (esHorizontal) {
                menu.style.display = 'block';
            } else {
                menu.style.display = (menu.id === claveDestino) ? 'block' : 'none';
            }
        });
    }

    /**
     * Expande recursivamente todos los contenedores colapsables padres de un enlace activo.
     * @param {HTMLElement} elemento - Enlace o elemento dentro del menú.
     * @returns {void}
     */
    function expandirPadresColapsables(elemento) {
        if (!elemento) return;

        let colapsoPadre = elemento.closest('.main-side-menu ul.collapse');
        while (colapsoPadre) {
            colapsoPadre.classList.add('show');
            const disparador = colapsoPadre.previousElementSibling;
            if (disparador) {
                disparador.classList.remove('collapsed');
                disparador.setAttribute('aria-expanded', 'true');
            }
            colapsoPadre = colapsoPadre.parentElement ? colapsoPadre.parentElement.closest('.main-side-menu ul.collapse') : null;
        }
    }

    /**
     * Inicializa la navegación por contrato: enlaces de iconos con su submenú.
     * @returns {void}
     */
    function inicializarNavegacion() {
        const enlacesPrimarios = document.querySelectorAll('.semi-side-nav .navbar-menu-list .nav-link');
        const todosLosMenus = document.querySelectorAll('.main-side-menu .main-menu');

        if (enlacesPrimarios.length === 0 || todosLosMenus.length === 0) return;

        enlacesPrimarios.forEach(enlace => {
            enlace.addEventListener('click', function (evento) {
                evento.preventDefault();
                const destino = this.getAttribute('data-target');
                activarCategoria(destino);
            });
        });

        // Resuelve la opción activa por URL o categoría predeterminada
        const rutaActual = window.location.pathname;
        let enlaceCoincidente = null;

        // Búsqueda de coincidencia exacta o por pathname
        const enlacesSecundarios = document.querySelectorAll('.main-side-menu a[href]');
        enlacesSecundarios.forEach(enlace => {
            const href = enlace.getAttribute('href');
            if (href && href !== '#' && (href === rutaActual || rutaActual.endsWith(href))) {
                enlaceCoincidente = enlace;
            }
        });

        if (enlaceCoincidente) {
            const menuPadre = enlaceCoincidente.closest('.main-menu');
            if (menuPadre && menuPadre.id) {
                activarCategoria(menuPadre.id);
            }
            expandirPadresColapsables(enlaceCoincidente);
            enlaceCoincidente.classList.add('active');
        } else {
            // Activar la primera categoría con clase active o la primera por defecto
            const primerActivo = document.querySelector('.semi-side-nav .navbar-menu-list .nav-link.active')
                || enlacesPrimarios[0];
            if (primerActivo) {
                activarCategoria(primerActivo.getAttribute('data-target'));
            }
        }

        // Acordeón seguro para submenús utilizando Bootstrap Collapse si está disponible
        if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
            document.querySelectorAll('.main-menu > li > a[data-bs-toggle="collapse"]').forEach(disparador => {
                disparador.addEventListener('click', function () {
                    const selectorObjetivo = this.getAttribute('href') || this.getAttribute('data-bs-target');
                    if (!selectorObjetivo || selectorObjetivo === '#') return;

                    const menuActual = document.querySelector(selectorObjetivo);
                    const contenedorMenu = this.closest('.main-menu');
                    if (!contenedorMenu) return;

                    contenedorMenu.querySelectorAll(':scope > li > ul.collapse.show').forEach(abierto => {
                        if (abierto !== menuActual) {
                            const instancia = bootstrap.Collapse.getInstance(abierto);
                            if (instancia) {
                                instancia.hide();
                            } else {
                                abierto.classList.remove('show');
                            }
                        }
                    });
                });
            });
        }
    }

    /**
     * Inicializa los controles de apertura y cierre de la barra lateral (responsive).
     * @returns {void}
     */
    function inicializarSidebarResponsive() {
        const botonTogglePrincipal = document.querySelector('.main-side-toggle');
        const botonToggleSecundario = document.querySelector('.side-toggle');
        const barraLateral = document.querySelector('.app-navbar');
        const barraSemi = document.querySelector('.semi-side-nav');
        const enlacesNav = document.querySelectorAll('.semi-side-nav .navbar-menu-list .nav-link');

        if (!barraLateral) return;

        const alternarSidebar = () => {
            barraLateral.classList.toggle('side-nav-toggle');
            if (window.innerWidth <= 576 && barraSemi) {
                barraSemi.classList.toggle('semi-nav-toggle');
            }
        };

        if (botonTogglePrincipal) {
            botonTogglePrincipal.addEventListener('click', alternarSidebar);
        }

        if (botonToggleSecundario) {
            botonToggleSecundario.addEventListener('click', alternarSidebar);
        }

        const aplicarEstadoPorResolucion = () => {
            const ancho = window.innerWidth;
            if (ancho <= 1199) {
                barraLateral.classList.add('side-nav-toggle');
            } else {
                barraLateral.classList.remove('side-nav-toggle');
            }

            if (barraSemi) {
                if (ancho <= 576) {
                    barraSemi.classList.add('semi-nav-toggle');
                } else {
                    barraSemi.classList.remove('semi-nav-toggle');
                }
            }
        };

        window.addEventListener('resize', aplicarEstadoPorResolucion);
        aplicarEstadoPorResolucion();

        // En pantallas móviles, al pulsar un enlace de navegación, replegar el sidebar
        enlacesNav.forEach(enlace => {
            enlace.addEventListener('click', () => {
                if (window.innerWidth <= 1199) {
                    barraLateral.classList.remove('side-nav-toggle');
                }
            });
        });
    }

    /**
     * Inicializa el scroll personalizado SimpleBar si el elemento y la librería existen.
     * @returns {void}
     */
    function inicializarSimpleBar() {
        const elementoScroll = document.querySelector('.app-simple-bar');
        if (elementoScroll && typeof SimpleBar !== 'undefined') {
            try {
                new SimpleBar(elementoScroll, { autoHide: true });
            } catch (error) {
                // Degradación elegante: el contenedor mantendrá scroll nativo CSS
            }
        }
    }

    /**
     * Inicializa el botón para volver a la parte superior de la página.
     * @returns {void}
     */
    function inicializarVolverArriba() {
        const botonTop = document.querySelector('.go-top');
        if (!botonTop) return;

        window.addEventListener('scroll', () => {
            if (window.scrollY > 150) {
                botonTop.classList.add('active');
            } else {
                botonTop.classList.remove('active');
            }
        }, { passive: true });

        botonTop.addEventListener('click', (evento) => {
            evento.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /**
     * Inicializa el modo de tema claro / oscuro de forma defensiva.
     * @returns {void}
     */
    function inicializarTema() {
        const botonTema = document.querySelector('.header-dark');
        const iconoTema = document.getElementById('theme-icon');

        const aplicarTema = (tema) => {
            if (tema === 'dark') {
                document.body.classList.add('dark');
                document.body.classList.remove('light');
                if (iconoTema) iconoTema.className = 'ti ti-sun';
            } else {
                document.body.classList.add('light');
                document.body.classList.remove('dark');
                if (iconoTema) iconoTema.className = 'ti ti-moon-stars';
            }
        };

        // Leer preferencia guardada o predeterminada
        let temaActual = 'light';
        try {
            temaActual = localStorage.getItem('camargo-tema') || 'light';
        } catch (e) {
            // LocalStorage restringido o no disponible
        }
        aplicarTema(temaActual);

        if (botonTema) {
            botonTema.addEventListener('click', () => {
                const esOscuro = document.body.classList.contains('dark');
                const nuevoTema = esOscuro ? 'light' : 'dark';
                aplicarTema(nuevoTema);
                try {
                    localStorage.setItem('camargo-tema', nuevoTema);
                } catch (e) {
                    // Ignora error de escritura en localStorage
                }
            });
        }
    }

    /**
     * Inicializa el botón de pantalla completa si está presente.
     * @returns {void}
     */
    function inicializarPantallaCompleta() {
        const botonMaximizar = document.querySelector('.head-maximize-screen');
        if (!botonMaximizar) return;

        botonMaximizar.addEventListener('click', () => {
            try {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                } else if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            } catch (error) {
                // Manejo defensivo si fullscreen no está permitido
            }
        });
    }

    /**
     * Inicializador maestro tras la carga del DOM.
     * @returns {void}
     */
    function iniciar() {
        inicializarCargador();
        inicializarNavegacion();
        inicializarSidebarResponsive();
        inicializarSimpleBar();
        inicializarVolverArriba();
        inicializarTema();
        inicializarPantallaCompleta();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
