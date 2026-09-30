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
| Perfil de usuario | `admin-dashboard/alina/template/profile.html` | Ficha de usuario y avatar (`GET /perfil`, `PERSONA ≠ USUARIO`) |
| Theme Customizer | `admin-dashboard/alina/template/blank.html` | Flotante lateral con Configuración y Soporte `#` (persistencia local en navegador con `localStorage`) |
| Error HTTP 400 | `admin-dashboard/alina/template/error_400.html` | Bad Request |
| Error HTTP 403 | `admin-dashboard/alina/template/error_403.html` | Forbidden / Acceso denegado |
| Error HTTP 404 | `admin-dashboard/alina/template/error_404.html` | Not Found / Recurso no encontrado |
| Error HTTP 500 | `admin-dashboard/alina/template/error_500.html` | Internal Server Error |
| Error HTTP 503 | `admin-dashboard/alina/template/error_503.html` | Service Unavailable |

Todo `admin-dashboard/` se conserva intacto. La aplicación utiliza copias selectivas de los recursos aprobados en `public/assets/`.

## Clasificación de Assets

- **GLOBAL (D-071, D-096):** Fuentes Lexend Deca, Font Awesome 6 Free v6.3.0 (`all.css` y 8 fuentes web locales en `public/assets/`), Flatpickr v4.6.13 (`flatpickr.min.css` y `flatpickr.js`), controlador `camargo-pickers.js`, PristineJS v1.1.0 (`pristine.min.js`), controlador `camargo-forms.js`, Bootstrap CSS/JS base, CSS propio `camargo.css`. Queda prohibido Tabler Icons en código propio.
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

## Materialización en UI-ALINA-1C

1. **Homologación de Formularios Canónicos:** Adopción transversal de la estructura oficial Vertical Form With Icon (`.app-form.app-icon-form`) con `.icon-control.position-relative`, iconos Font Awesome 6 posicionados absolutamente (`top-50 start-0 translate-middle-y ms-3`), padding izquierdo `.form-control.ps-5` y soporte para textareas con `.icon-control.icon-textarea`.
2. **Selectores Select2 Alina Canónicos:** Unificación de todos los controles `select.basic-select2` con altura Alina oficial de 42px, chevron Font Awesome y esquinas redondeadas suaves, prohibiendo taxativamente combinaciones con `.form-select-sm`.
3. **Controles Basic Switch y File Upload:** Implementación de `.form-check.form-switch.app-switch` y `.form-control[type="file"]` con bordes estrictamente sólidos (0 líneas punteadas o discontinuas en todo el CSS).
4. **Validación Reactiva Client-Side con PristineJS (D-096):** Integración nativa de `pristine.min.js` y `camargo-forms.js` en Vanilla JS (ES6+), con localización al español, feedback visual inmediato, gestión de estados de carga en botones con spinners Font Awesome (`CamargoForms.establecerCargando`) y reseteo en el ciclo de vida de modales Bootstrap (`hidden.bs.modal`).
5. **Preparación Estructural para APIsPERU:** Identificación de matriz de 9 campos DNI/RUC en 6 módulos del PMS sin invocar prematuramente servicios externos.
6. **Catálogo Alina Intacto:** Cero modificaciones en `admin-dashboard/`. 100% de assets consumidos desde copias locales controladas.

## Materialización en UI-ALINA-1D

1. **Modales y Diálogos:** Homologación transversal del centrado vertical obligatorio (`modal-dialog-centered`) en todos los modales del sistema (100% de cumplimiento en modales de arrendamientos, gastos, housekeeping, bitácora, reclamaciones, feriados, suministros, clientes, empresas, personal y sesiones). Sizing estándar (`modal-sm`, `modal-lg`, `modal-xl`).
2. **SweetAlert2 Alina:** Estandarización de alertas interactivas y confirmaciones con la paleta Alina (`.swal2-confirm`, `.swal2-cancel`). Prohibición expresa de su uso como contenedor de formularios CRUD.
3. **Tooltips Runtime:** Delegación e inicialización defensiva mediante `bootstrap.Tooltip.getOrCreateInstance` en `camargo-forms.js` y `camargo-layout.js`, con auto-refresco en el evento `shown.bs.modal`.
4. **Botones y Enlaces:** Botones sólidos Alina (.btn-primary, .btn-secondary, .btn-light-secondary, .btn-light-danger, .btn-light-success) con iconografía 100% Font Awesome 6 Free (erradicación de librerías heterogéneas).
5. **Accordions y Elementos Colapsables:** Adopción del componente canónico `.accordion.app-accordion` con `.accordion-button.accordion-icon` y chevron rotatorio suave (ej. ficha 360° en `clientes/detalle.php`).
6. **Erradicación de `-subtle`:** Reemplazo integral de clases Bootstrap crudas (`bg-*-subtle`, `alert-*-subtle`) por la paleta nativa Alina (`bg-light-*`, `alert-light-*`).
7. **Badges, Chips y Bordes:** Cero bordes punteados o discontinuos (`0 dotted / 0 dashed`).
8. **Placeholders / Preload Skeleton:** Implementación de esqueletos de carga visual `.placeholder-glow` con `.placeholder` en lugar de textos planos en cargas asíncronas (`suministros/index.php`, `CamargoForms.crearPlaceholder()`).
9. **Progress (Criterio Rector):** Documentado explícitamente como **NO APLICA / SIN CASO REAL ACTUAL**, evitando la invención de barras de progreso artificiales sin procesos multifase reales en segundo plano.
10. **Aislamiento e Inmutabilidad:** `admin-dashboard/` 100% intacta; base de datos preservada en exactamente 118 tablas y slot de migración 035 estrictamente libre (0 DDL).

## Materialización en UI-ALINA-1E

1. **Estándar Canónico Bordered + Striped + Hoverable (D-098):**
   - Unificación de 80 tablas del PMS bajo las clases `.table.table-bordered.table-striped.table-hover.align-middle.mb-0` (y variante `.table-sm` en modales o vistas densas).
   - Encapsulación en `.table-responsive` en el 100% de las tablas homologadas.
   - Alineación vertical middle y tipografía semibold en `thead`.
2. **Clasificación y Alcance Transversal:**
   - **Tablas de datos principales / catálogos:** 59 tablas en arrendamientos, caja, clientes, compras, disponibilidad, documentos, empresas, estadías, gastos, housekeeping, inventario, mantenimiento, operaciones, personal, propiedades, recibos, reclamaciones, reportes, reservas, seguridad, servicios, suministros y unidades.
   - **Tablas de configuración:** 4 tablas en feriados, menú, roles y usuarios.
   - **Tablas en modales:** 17 tablas de selección, asignación y desgloses secundarios.
3. **Exclusiones Legítimas Preservadas:**
   - 6 tablas de plantilla PDF/impresión A4 (`hoja_reclamacion.php`).
   - 2 grillas interactivas matriciales (`tape-chart-table` y `#tabla-rack`).
   - 8 fichas de metadatos clave-valor con `.table-borderless`.
4. **Integridad del Repositorio:**
   - Cero alteraciones en `admin-dashboard/` (permanece 100% inmutable).
   - Cero DDL: Base de datos congelada en exactamente 118 tablas y slot de migración 035 libre.

## Materialización en UI-ALINA-1F

1. **Barrido Visual y Erradicación Total de Clases Residuales (D-099):**
   - Auditoría transversal sobre las 42 pantallas mapeadas en `app/Vistas/` y scripts en `public/assets/js/`.
   - Eliminación del 100% de clases Bootstrap 5 `-subtle` remanentes (`bg-primary-subtle`, `bg-light-subtle`, `bg-secondary-subtle`, `bg-info-subtle`, `bg-warning-subtle`, `bg-danger-subtle`, `bg-success-subtle`, `border-danger-subtle`, `border-primary-subtle`, `text-light-subtle`) en favor de las superficies Alina `.bg-light-*`, `.bg-light` y contraste `.text-white-50`.
   - Cero clases residuales `-subtle` restantes en vistas, JavaScript y CSS propio.
2. **Consistencia Transversal de Modales y Responsive:**
   - 100% de modales con centrado vertical `.modal-dialog-centered`.
   - Cero bordes punteados o discontinuos (`0 dotted / 0 dashed`).
   - Iconografía 100% Font Awesome 6 Free (cero iconos ajenos `ti-`, `bi-`, `feather-`).
   - Verificación de 36 pantallas en vivo bajo Apache HTTPS con código 200 OK y estructura canónica Alina.
   - Sincronización de `UsuarioRepositorio::obtenerDetalleCompleto()` para la carga pasiva de `/perfil` respetando `PERSONA ≠ USUARIO`.
3. **Preservación Estricta de Fases Previas (1A a 1E):**
   - Preservación íntegra de layouts (1A), perfil y customizer (1B/1B-C1), formularios (1C), componentes interactivos (1D) y las 80 tablas convencionales y 16 exclusiones legítimas (1E).
4. **Gobernanza y Estado Prístino:**
   - Base de datos congelada en exactamente 118 tablas relacionales; última migración aplicada `034_agregar_foto_personas.sql`; ranura `035` estrictamente LIBRE (0 DDL).
   - Catálogo de referencia `admin-dashboard/` 100% intacto, inmutable y de solo lectura.
   - Regresión consolidada en 69 suites de pruebas, 1,579 checks automatizados y 0 fallos.
