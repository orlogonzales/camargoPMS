# Frontend

## Principios

Alina y Bootstrap 5 constituyen el lenguaje visual. Se reutilizan sus clases y componentes sin rediseño innecesario. Las vistas son pasivas y reciben datos preparados. JavaScript propio mejora interacción pero no decide permisos ni reglas definitivas.

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

## Contrato de navegación Alina

```text
navbar-menu-list
    data-target="clave"
              ↓
main-side-menu
    main-menu id="clave"
```

Ambas zonas forman una sola navegación vinculada dinámicamente desde la base de datos (`opciones_menu` vía `MenuServicio`). La clave técnica es alfanumérica en minúsculas, estable, única y apta para selectores DOM. El menú secundario admite enlaces directos y grupos con estructura idéntica.

La ruta activa (`$rutaActual`) marca visualmente la categoría principal activa (`.active`) y la opción secundaria en curso. El servidor filtra la visibilidad según permisos RBAC y elimina categorías principales vacías. La seguridad reside independientemente en el backend.

En la interfaz de administración de menú (`/configuracion/menu`), se utiliza `public/assets/js/gestion-menu.js` (Vanilla JS puro, Fetch API y validación client-side modular con PristineJS v1.1.0) junto con SweetAlert2 para modales y confirmaciones, comunicando tokens CSRF mediante `<meta name="csrf-token">`.

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
