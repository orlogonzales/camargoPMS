# Plantilla Alina

## Principio Superior

> **ALINA ES EL SISTEMA DE DISEÑO DE CAMARGO PMS.** Bootstrap 5 es infraestructura interna de Alina, no el catálogo visual del proyecto. Si Alina dispone de un componente equivalente, debe utilizarse obligatoriamente el componente/estilo de Alina.

Los archivos bajo `admin-dashboard/alina/template/` son referencias originales e inmutables: no deben editarse, trasladarse ni convertirse directamente en archivos productivos.

Queda terminantemente prohibido el uso de `border-style: dotted` o `border-style: dashed` en los componentes propios de Camargo PMS.

## Fuente oficial y referencias visuales

| Recurso Alina | Archivo Original | Uso en Camargo PMS |
|---|---|---|
| Layout general | `admin-dashboard/alina/template/blank.html` | Estructura principal autenticada (`.app-wrapper`, navegación) |
| Navegación 3 niveles | `admin-dashboard/alina/template/accordions.html` | Patrón nativo `.another-level` y `.collapse` para nivel 3 |
| Login / Acceso | `admin-dashboard/alina/template/sign_in.html` | Referencia visual de autenticación |
| Dashboard / Home | `admin-dashboard/alina/template/index.html` | Referencia visual de dashboard |
| Font Awesome | `admin-dashboard/alina/template/fontawesome.html` | Catálogo de referencia de iconos Font Awesome 6 |
| Date Picker / Range | `admin-dashboard/alina/template/date_picker.html` | Referencia de Date Picker y Range Picker Flatpickr |
| Select2 | `admin-dashboard/alina/template/select2.html` | Select2 alineado a 42px, borde redondeado y chevron FA6 |
| Formularios e Iconos | `admin-dashboard/alina/template/form_elements.html` | Formularios verticales con `.icon-control`, switches y radios |
| Subida de archivos | `admin-dashboard/alina/template/file_upload.html` | Controles de carga de documentos |
| Wizards | `admin-dashboard/alina/template/form_wizard.html` | Flujos de varios pasos guiados |
| Modales | `admin-dashboard/alina/template/modal.html` | Diálogos modales centrados con cabecera `bg-light` |
| SweetAlert2 | `admin-dashboard/alina/template/sweetalert2.html` | Diálogos de confirmación interactivos |
| Botones | `admin-dashboard/alina/template/button.html` | Botones sólidos Alina |
| Acordeones | `admin-dashboard/alina/template/accordions.html` | Bloques colapsables con chevron |
| Badges y Chips | `admin-dashboard/alina/template/badges.html` | Referencia oficial de Variants of badge y Variants of chip (D-071) |
| Tablas | `admin-dashboard/alina/template/table.html` | Tablas Bordered + Striped + Hoverable |
| Perfil de usuario | `admin-dashboard/alina/template/profile.html` | Ficha de usuario y avatar (`PERSONA ≠ USUARIO`) |
| Theme Customizer | `admin-dashboard/alina/template/blank.html` | Flotante lateral con Configuración y Soporte `#` |
| Error HTTP 400 | `admin-dashboard/alina/template/error_400.html` | Bad Request |
| Error HTTP 403 | `admin-dashboard/alina/template/error_403.html` | Forbidden / Acceso denegado |
| Error HTTP 404 | `admin-dashboard/alina/template/error_404.html` | Not Found / Recurso no encontrado |
| Error HTTP 500 | `admin-dashboard/alina/template/error_500.html` | Internal Server Error |
| Error HTTP 503 | `admin-dashboard/alina/template/error_503.html` | Service Unavailable |

Todo `admin-dashboard/` se conserva intacto. La aplicación utiliza copias selectivas de los recursos aprobados en `public/assets/`.

## Clasificación de Assets

- **GLOBAL (D-071):** Fuentes Lexend Deca, Font Awesome 6 Free v6.3.0 (`all.css` y 8 fuentes web locales en `public/assets/`), Flatpickr v4.6.13 (`flatpickr.min.css` y `flatpickr.js`), controlador `camargo-pickers.js`, Bootstrap CSS/JS base, CSS propio `camargo.css`. Queda prohibido Tabler Icons en código propio.
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

## Materialización en MENÚ-1

1. **Navegación Dinámica Autorizada:** El menú estático de prueba fue reemplazado por la estructura de 2 niveles persistida en la tabla `opciones_menu`. `Vistas/componentes/navegacion.php` resuelve el árbol para el usuario actual mediante `MenuServicio::obtenerMenuParaUsuario()`.
2. **Contrato Alina Preservado:** Las claves de nivel 1 alimentan `navbar-menu-list` con `data-target="clave"` y las opciones de nivel 2 se agrupan en `main-side-menu` con `id="clave"`, respetando al 100% el comportamiento visual de `blank.html`.
3. **Resaltado Activo Sincronizado:** `$rutaActual` activa simultáneamente el elemento principal horizontal y la opción secundaria correspondiente.
4. **Depuración Automática de Categorías Vacías:** Categorías principales sin opciones secundarias activas y autorizadas no se renderizan, evitando secciones huérfanas en la barra superior.
5. **Assets Selectivos por Módulo:** Se copió `sweetalert.js` desde `admin-dashboard/alina/assets/vendor/sweetalert/` hacia `public/assets/vendor/sweetalert/` sin tocar los originales de Alina.
6. **Módulo de Gestión:** Se implementó `public/assets/js/gestion-menu.js` con Vanilla JS y Fetch API nativo para la administración reactiva de opciones sin dependencias de jQuery.
