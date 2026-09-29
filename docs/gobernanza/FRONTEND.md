# Frontend

## Principios

> **ALINA ES EL SISTEMA DE DISEÑO DE CAMARGO PMS.** Bootstrap 5 es infraestructura interna de Alina, no el catálogo visual del proyecto. Si Alina dispone de un componente equivalente, debe utilizarse obligatoriamente el componente/estilo de Alina.

Los archivos bajo `admin-dashboard/alina/template/` son de solo lectura y constituyen la referencia visual canónica inmutable. Las vistas son pasivas y reciben datos preparados. JavaScript propio mejora interacción en Vanilla JS puro pero no decide permisos ni reglas definitivas.

Queda estrictamente prohibido el uso de `border-style: dotted` o `border-style: dashed` en todos los componentes propios de Camargo PMS.

## Layouts y Plantillas

1. `Vistas/plantillas/principal.php`: Layout general autenticado (`.app-wrapper`) estructurado en componentes modulares:

```text
head.php
cargador.php
navegacion.php
menu-principal.php
menu-secundario.php
cabecera.php
migas-pan.php
pie.php
scripts.php
```

2. `Vistas/plantillas/error.php`: Layout aislado y centrado para respuestas de error HTTP (`.error-container`), desacoplado de barras de navegación.
3. `Vistas/plantillas/autenticacion.php` *(previsto)*: Layout para acceso y login (`.sign-bg-wrapper`).

La vista de un módulo aporta solo su contenido y assets particulares. El layout autenticado conserva `.app-wrapper`, `.app-navbar`, `.app-content`, `<main>` y los puntos de integración requeridos.

## Contrato de navegación Alina (Hasta 3 Niveles)

Camargo PMS implementa un sistema de navegación dinámica autorizada de **hasta 3 niveles reales**:

```text
Nivel 1 — Dominio Principal (.semi-side-nav .navbar-menu-list a[data-target="clave"])
    ↓
Nivel 2 — Módulo (.main-side-nav .main-side-menu ul.main-menu#clave > li)
    ↓
Nivel 3 — Función / Submódulo (nativo Alina: li.another-level o ul.collapse)
```

1. **Nivel 1 (Dominio Principal):** Icono primario en la barra vertical izquierda `.semi-side-nav`. Selecciona el panel mediante `data-target="clave"`.
2. **Nivel 2 (Módulo):** Opción del panel lateral desplegable `.main-side-nav`. Si no tiene hijos, es un enlace directo (`li.no-sub > a`). Si tiene hijos de nivel 3, es un grupo colapsable nativo Alina (`data-bs-toggle="collapse"`).
3. **Nivel 3 (Función / Submódulo):** Submenú desplegable anidado. No se permite anidar más allá del nivel 3 (máximo estricto de tres niveles; nivel 4+ es rechazado tanto en backend como en interfaz).

Ambas zonas forman una sola navegación vinculada dinámicamente desde la base de datos (`opciones_menu` vía `MenuServicio`). La clave técnica es alfanumérica en minúsculas, estable, única y apta para selectores DOM.

La ruta activa (`$rutaActual`) propaga el active state a los tres niveles: marca la categoría principal activa (`.active`), el módulo padre (`.show` y `aria-expanded="true"`) y la función submódulo en curso (`.active`). El servidor filtra la visibilidad según permisos RBAC y elimina categorías principales sin hijos visibles.

Principio vinculante: **MENÚ ≠ AUTORIZACIÓN**. La visibilidad en el menú no sustituye la autorización del backend, cuyas rutas permanecen selladas por intermediarios soberanos.

En la interfaz de administración de menú (`/configuracion/menu`), se utiliza `public/assets/js/gestion-menu.js` (Vanilla JS puro, Fetch API y validación client-side modular con PristineJS v1.1.0) junto con SweetAlert2 para modales y confirmaciones, comunicando tokens CSRF mediante `<meta name="csrf-token">`. Permite gestionar los 3 niveles jerárquicos con botones contextuales acotados por profundidad y ordenamiento independiente entre hermanos sin líneas punteadas (cero `dotted/dashed`).

En la administración de usuarios (`/usuarios`), se implementa `app/Vistas/usuarios/index.php` con tabla responsive Alina, filtros dinámicos, paginación server-side y cuatro modales Bootstrap 5 orquestados por `public/assets/js/gestion-usuarios.js`:
- **Modal Crear Usuario:** Selección reactiva de personas disponibles no vinculadas, nombre de usuario con validación en tiempo real PristineJS v1.1.0, asignación de rol inicial RBAC y contraseña segura (`PASSWORD_DEFAULT` validada de 12 a 1024 caracteres).
- **Modal Detalle de Usuario:** Perfil de la persona vinculada, rol, estado, fechas de creación/actualización y listado tabular de sesiones activas con revocación puntual o masiva.
- **Modal Cambiar Estado:** Transiciones de estado operacionales (`ACTIVO`, `INACTIVO`, `BLOQUEADO`) con confirmación SweetAlert2 y protección defensiva del último superadministrador activo.
- **Modal Restablecer Contraseña:** Asignación de credenciales seguras de 12 a 1024 caracteres validadas por PristineJS v1.1.0, confirmación SweetAlert2 y revocación obligatoria de sesiones concurrentes.
Todos los modales resetean el formulario e instancias del validador en `hidden.bs.modal` con `validador.reset()`, operan con Fetch API sin recarga de página y transmiten tokens CSRF mediante cabecera `X-CSRF-TOKEN`.

## Assets

Globales mínimos (D-071):

- Bootstrap 5.
- Font Awesome 6 Free (v6.3.0) y fuentes web locales en `public/assets/vendor/fontawesome/` y `public/assets/fonts/fontawesome/` (librería de iconos oficial y obligatoria; 0 Tabler Icons en código propio).
- Flatpickr v4.6.13 local (CSS y JS en `public/assets/vendor/flatpickr/`) para Date Picker y Range Picker Alina transversales.
- Simplebar.
- CSS esencial de Alina (`style.css`, `responsive.css`).
- CSS y JavaScript propios del layout y componentes (`camargo.css`, `camargo-layout.js`, `camargo-pickers.js`).

Por módulo:

- FullCalendar, DataTables, SweetAlert2, PristineJS, gráficos, editores o uploads únicamente donde se usan.

El orden de CSS y scripts debe ser determinista y sin duplicados. Las rutas de assets parten de una URL base, no de `../` dependiente de la ruta actual. Cero uso de CDNs externas: política estricta de *Local Assets First*.

## JavaScript

`camargo-layout.js` reproduce las funciones del shell: selección de área, enlace activo, colapsables, sidebar responsive, Simplebar, loader, tema y volver arriba con comprobaciones defensivas de existencia.

`camargo-pickers.js` estandariza de forma transversal la inicialización de Date Pickers y Range Pickers de Alina mediante Flatpickr en Vanilla JS puro:
- **Range Picker UX:** Captura rangos de fechas (ej. `YYYY-MM-DD to YYYY-MM-DD`) y los sincroniza de forma atómica e invisible con los inputs canónicos individuales `fecha_entrada` y `fecha_salida`.
- **Preservación D-066:** La capa backend recibe fechas discretas independientes en formato ISO `YYYY-MM-DD` bajo el modelo de intervalo semiabierto $[ \text{entrada}, \text{salida} )$. El Range Picker es una mejora exclusiva de experiencia de usuario en cliente.
- **Validación reactiva:** Emite eventos nativos `input` y `change` para desencadenar validaciones PristineJS y recálculos reactivos en tiempo real.

Regla vinculante: **0 jQuery en código propio**. Todo desarrollo de frontend propio se escribe en JavaScript moderno nativo (Vanilla JS ES6+). No modificar vendor ni cargar `script.js` o `theme_customizer.js` original como núcleo definitivo.

## Formularios y accesibilidad

- Etiquetas asociadas, navegación por teclado y foco visible.
- Errores vinculados al campo y resumen comprensible.
- PristineJS complementa, no reemplaza, la validación servidor.
- Selectores de fecha integrados con PristineJS y alertas accesibles.
- Confirmaciones destructivas explican el objeto y consecuencia.
- Mantener semántica, ARIA y contraste al adaptar componentes Alina.

## Badges y Chips (UI-2A / D-071)

La representación visual de estados, categorías, atributos, contadores y tags en Camargo PMS se rige estrictamente por los componentes oficiales de Alina (`admin-dashboard/alina/template/badges.html`):

1. **Variants of badge de Alina (`badge bg-light-*`, `badge text-bg-*`):**
   - Destinados a estados compactos en tablas, listados, modales y contadores numéricos breves.
   - Aplica estilos nativos de Alina con transparencia y borde tonal suave (`rgba(var(--color), 0.1)`).
2. **Variants of chip de Alina (`chip bg-light-*`, `chip text-bg-*`):**
   - Destinados a categorías, clasificaciones, tipos de unidad física, roles de usuario, principios arquitectónicos y tags removibles o interactivos.
   - Aplica estilos nativos de Alina con padding específico y esquinas redondeadas controladas.
3. **Prohibiciones estrictas:**
   - Badges y Chips con bordes punteados o discontinuos estrictamente prohibidos (conteo de `dotted` = 0 y `dashed` = 0).
   - Erradicación de clases Bootstrap crudas (`bg-*-subtle`) en favor de las clases oficiales de Alina (`bg-light-*`).
   - Iconografía interna exclusivamente mediante Font Awesome 6 Free (v6.3.0).
4. **Mapa Semántico Oficial:**
   - `SUCCESS` (`bg-light-success`): `ACTIVO`, `CONFIRMADA`, `DISPONIBLE`.
   - `WARNING` (`bg-light-warning`): `PENDIENTE`, `MANTENIMIENTO`, `SUPERADMIN`.
   - `DANGER` (`bg-light-danger`): `CANCELADA`, `BLOQUEADO`, `OCUPADO`, `SUSPENDIDO`.
   - `INFO` (`bg-light-info`): informativo / sistema / manual / tipo de unidad.
   - `SECONDARY` (`bg-light-secondary`): `INACTIVO`, `EXPIRADA`, `LIBERADO`, contadores y códigos auxiliares.
   - `LIGHT` / `DARK`: contraste puntual o códigos de unidad/propiedad destacados.
5. **Resolución técnica centralizada:**
   - Backend (PHP): Clase `\CamargoPMS\Nucleo\Insignia` y funciones globales `insignia_badge()`, `insignia_chip()`, `insignia_estado()`.
   - Frontend (JS): Namespace global Vanilla JS `window.CamargoInsignia` en `camargo-layout.js` (`badge()`, `chip()`, `estado()`, `resolverClase()`).

## Seguridad de salida

Escapar en servidor según contexto. En cliente usar `textContent` para datos no confiables y evitar `innerHTML`. URLs, iconos y clases dinámicas se eligen de listas permitidas.

## Contrato de Componentes Canónicos Alina (UI-ALINA-1A)

Conforme a la regla superior (**Alina es el sistema de diseño de Camargo PMS**), todo componente visual debe extraerse de su equivalente oficial en `admin-dashboard/alina/template/`:

| Componente | Plantilla Alina de Referencia | Contrato Técnico en Camargo PMS |
|---|---|---|
| **Date Picker** | `date_picker.html` | Flatpickr nativo con tema Alina, input con icono FA6 y validación ISO `YYYY-MM-DD`. |
| **Range Picker** | `date_picker.html` | Flatpickr modo `range`, sincronizado atómicamente a dos inputs `fecha_entrada` y `fecha_salida` mediante `camargo-pickers.js`. |
| **Select2** | `select2.html` | Clase `.basic-select2`, altura nativa 42px (`calc(2.5rem + 2px)`), píldora redondeada (20px), flecha chevron FA6 `\f078`, integrado con PristineJS. |
| **Vertical Form With Icon** | `form_elements.html` | Estructura `.app-form.app-icon-form .icon-control` con separador vertical 1px x 20px a `left: 40px`, padding izquierdo de 48px y esquinas redondeadas Alina. |
| **Radio Styles** | `form_elements.html` | Estilos de radio Alina (`form-check-input` con colores semánticos). |
| **Checkbox Styles** | `form_elements.html` | Estilos de checkbox Alina (`form-check-input` con acento semántico). |
| **Basic File Upload** | `file_upload.html` | Control de subida de archivos Alina con selector estilizado y retroalimentación de archivo. |
| **Basic Input Groups** | `form_elements.html` | Grupos de entrada `.input-group` con `.input-group-text` redondeados y coherencia de altura. |
| **Basic Switch** | `form_elements.html` | Conmutadores `.form-check.form-switch` estilizados para estados booleanos rápidos. |
| **Booking Wizard** | `form_wizard.html` / `form_wizard_two.html` | Flujos secuenciales paso a paso con validación por etapas en cliente (PristineJS) y servidor. |
| **Book Appointment Form** | `form_elements.html` | Formularios de reserva y captura de citas/servicios con layout vertical denso y limpio. |
| **Modal Sizing / Default** | `modal.html` | Modales centrados `.modal-dialog-centered`, tamaños estándar (`modal-sm`, `modal-lg`, `modal-xl`), cabecera `bg-light` con `border-bottom`. |
| **SweetAlert Alina** | `sweetalert2.html` | Diálogos modales interactivos con SweetAlert2 configurado con la paleta de Alina (`public/assets/vendor/sweetalert/sweetalert.js`). |
| **Default Tooltips** | `tooltips.html` | Tooltips nativos Bootstrap 5 inicializados en Vanilla JS mediante `camargo-layout.js`. |
| **Solid Buttons** | `button.html` | Botones sólidos (`.btn.btn-primary`, `.btn-secondary`, etc.) con esquinas redondeadas Alina (`--app-border-radius`). |
| **Accordions with Icon** | `accordions.html` | Acordeones colapsables con chevron Font Awesome e icono identificador del bloque. |
| **Basic Alert** | `alert.html` | Alertas informativas `.alert.alert-*` con esquinas suaves, botón de cierre e iconografía semántica. |
| **Badges / Chips Alina** | `badges.html` | Variants de badge (`.badge.bg-light-*`) y chip (`.chip.bg-light-*`) según D-071. |
| **Backgrounds** | `background.html` | Utilidades de fondo suaves de Alina (`.bg-light-*`). |
| **Cards** | `cards.html` | Tarjetas `.card.equal-card.shadow-sm` con cabecera `bg-white py-3 border-bottom`. |
| **Dropdowns** | `dropdown.html` | Menús desplegables contextuales `.dropdown-menu` estilizados con bordes redondeados y sombras suaves. |
| **Editor** | `editor.html` | Editor de texto enriquecido para contratos, notas y plantillas documentales. |
| **Lists** | `list.html` | Listados `.list-group.list-group-flush` con divisores limpios. |
| **Placeholders** | `placeholder.html` | Efectos esqueléticos de carga `.placeholder-glow` para cargas asíncronas. |
| **Progress** | `progress.html` | Barras de progreso `.progress` con barras redondeadas semánticas. |
| **Basic Tabs** | `tab.html` | Pestañas de navegación interna `.nav.nav-tabs` y `.nav.nav-pills`. |
| **Profile** | `profile.html` | Pantalla de perfil de usuario (`GET /perfil`, `PERSONA ≠ USUARIO`) con ficha Alina, previsualización de fotografía y aislamiento entre persona civil y cuenta de acceso. |
| **Theme Customizer** | `blank.html` | Flotante lateral derecho (Configuración de plantilla + Soporte `#`, Reset centrado, sin Buy Now, persistencia soberana en navegador mediante `localStorage`). |
| **PristineJS UX Gate** | N/A | Validación reactiva cliente obligatoria (`PristineJS ≠ seguridad`) combinada con validación backend ineludible. |
| **Tablas Bordered + Striped + Hover** | `table.html` | Tablas de datos normalizadas: `table table-bordered table-striped table-hover align-middle mb-0`. |

Prohibición transversal vinculante: **`border-style: dotted/dashed` no está permitido** en ningún componente propio de Camargo PMS.

