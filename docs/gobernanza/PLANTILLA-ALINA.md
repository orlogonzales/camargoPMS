# Plantilla Alina

## Fuente oficial

- Base obligatoria: `admin-dashboard/alina/template/blank.html`.
- Assets originales: `admin-dashboard/alina/assets/`.
- Documentación: `admin-dashboard/documentation/`.
- Las 147 páginas HTML son catálogo de componentes, no páginas para copiar completas.

Todo `admin-dashboard/` se conserva intacto como referencia. La aplicación copiará selectivamente assets aprobados a `public/assets/`.

## DOM verificado

```text
.app-wrapper
├── .loader-wrapper
├── nav.app-navbar
│   ├── .semi-side-nav
│   │   └── .navbar-menu-list
│   └── .main-side-nav
│       └── .main-side-menu
├── .app-content
│   ├── header.header-main
│   ├── .app-breadcrumbs
│   └── main > .container-fluid
├── .go-top
├── footer.footer-container
├── #theme-customizer-box
├── #cartCanvas
└── #notificationCanvas
```

## Dependencias de `blank.html`

CSS: Lexend Deca remota, Tabler Icons, Bootstrap, Simplebar, `style.css` y `responsive.css`.

JavaScript: jQuery 3.6.3, Bootstrap Bundle, Simplebar 6.2.4, `theme_customizer.js` y `script.js`.

PristineJS no está incluido. SweetAlert existe en demos, pero no es dependencia global de `blank.html`.

## Navegación

`script.js` vincula cada `.semi-side-nav .nav-link[data-target]` con `.main-side-menu .main-menu#<clave>`. Al cargar una ruta, busca el `href` actual, activa su categoría y expande los `collapse` padres. En disposición horizontal muestra todos los grupos.

Este acoplamiento debe conservarse como contrato de datos, no como HTML cableado. Las claves futuras son identificadores internos saneados y únicos.

## Riesgos confirmados

- El loader usa jQuery directamente.
- Algunas consultas DOM de `script.js` no toleran que falten controles de sidebar, pantalla completa, tema, carrito o volver arriba.
- Retirar el carrito o customizer conservando el script original puede producir errores.
- Las rutas `../assets/...` fallarán con rutas profundas del Front Controller.
- El carrito, enlaces ecommerce, idiomas y notificaciones son contenido demo.
- Cargar todas las demos incorporaría plugins innecesarios.

## Estrategia aprobada

1. No editar originales.
2. Crear layout PHP por componentes a partir del DOM real.
3. Crear `camargo-layout.js` propio con comprobaciones seguras.
4. Copiar solo assets globales y recursos realmente usados.
5. Añadir plugins por módulo.
6. Comparar visualmente con `blank.html` antes de congelar la baseline.

## Criterios para UI-0/UI-1

- Sin errores de consola ni rutas 404.
- Equivalencia visual en escritorio y anchos responsive representativos.
- Sidebar, categorías, submenús, estado activo, breadcrumbs, tema y loader probados.
- HTML dinámico escapado y menús filtrados por backend.
- Originales Alina sin cambios.

## Materialización en UI-0

1. Plantilla base creada en `app/Vistas/plantillas/principal.php` encapsulando `.app-wrapper`.
2. Componentes extraídos en `app/Vistas/componentes/`: `head`, `cargador`, `navegacion`, `menu-principal`, `menu-secundario`, `cabecera`, `migas-pan`, `pie` y `scripts`.
3. Controlador JS `public/assets/js/camargo-layout.js` sustituye la dependencia de `script.js`, `theme_customizer.js` y jQuery, ejecutando operaciones defensivas sobre el DOM.
4. Assets mínimos aislados en `public/assets/` con resolución dinámica de URL absoluta para prevenir roturas en rutas profundas.
5. `admin-dashboard/` permanece 100% inmutable.
