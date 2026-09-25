# Plantilla Alina

## Fuente oficial y referencias visuales

Los archivos bajo `admin-dashboard/` son referencias originales e inmutables: no deben editarse, trasladarse ni convertirse directamente en archivos productivos.

| Recurso Alina | Archivo Original | Uso en Camargo PMS |
|---|---|---|
| Layout general | `admin-dashboard/alina/template/blank.html` | Estructura principal autenticada |
| Login / Acceso | `admin-dashboard/alina/template/sign_in.html` | Referencia visual de autenticación |
| Dashboard / Home | `admin-dashboard/alina/template/index.html` | Referencia visual de dashboard |
| Error HTTP 400 | `admin-dashboard/alina/template/error_400.html` | Bad Request |
| Error HTTP 403 | `admin-dashboard/alina/template/error_403.html` | Forbidden / Acceso denegado |
| Error HTTP 404 | `admin-dashboard/alina/template/error_404.html` | Not Found / Recurso no encontrado |
| Error HTTP 500 | `admin-dashboard/alina/template/error_500.html` | Internal Server Error |
| Error HTTP 503 | `admin-dashboard/alina/template/error_503.html` | Service Unavailable |

Todo `admin-dashboard/` se conserva intacto. La aplicación utiliza copias selectivas de los recursos aprobados en `public/assets/`.

## Clasificación de Assets

- **GLOBAL:** Fuentes Lexend Deca, Tabler Icons, Bootstrap CSS/JS base, CSS propio `camargo.css`.
- **LAYOUT:** Simplebar, `style.css`, `responsive.css`, `camargo-layout.js`, avatares y logos.
- **AUTENTICACIÓN:** Estilos de formulario flotante y recursos visuales específicos de `sign_in.html` (previstos para fase de autenticación).
- **DASHBOARD:** Librerías gráficas (ej. Apexcharts) o widgets específicos de `index.html` (previstos para fase de dashboard con datos reales).
- **ERRORES:** Ilustraciones `images/error/error-*.png` y plantilla aislada `.error-container`.

## Jerarquía de Plantillas

1. `plantillas/principal.php`: Layout general autenticado (`.app-wrapper`, navegación, cabecera, contenido, pie).
2. `plantillas/error.php`: Layout aislado y centrado para respuestas de error HTTP (`.error-container`).
3. `plantillas/autenticacion.php` *(previsto)*: Layout para login y registro (`.sign-bg-wrapper`).

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
5. Arquitectura de errores implementada con plantilla aislada `plantillas/error.php` e ilustración oficial `images/error/error-*.png`.
6. Vista genérica `errores/error.php` lista para 400, 403, 404, 500 y 503 sin duplicación de código.
7. `admin-dashboard/` permanece 100% inmutable.
