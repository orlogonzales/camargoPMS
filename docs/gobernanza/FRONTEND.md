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

Globales mínimos:

- Bootstrap.
- Tabler Icons y fuentes.
- Simplebar.
- CSS esencial de Alina.
- CSS y JavaScript propios del layout Camargo PMS.

Por módulo:

- FullCalendar, DataTables, SweetAlert, PristineJS, gráficos, editores o uploads únicamente donde se usan.

El orden de CSS y scripts debe ser determinista y sin duplicados. Las rutas de assets parten de una URL base, no de `../` dependiente de la ruta actual.

## JavaScript

`camargo-layout.js` reproducirá solo lo necesario: selección de área, enlace activo, colapsables, sidebar responsive, Simplebar, loader, tema y volver arriba según decisiones de producto. Debe tolerar componentes opcionales mediante comprobaciones de existencia.

No modificar vendor ni cargar `script.js` o `theme_customizer.js` original como núcleo definitivo. Evitar jQuery en código nuevo; si se mantiene temporalmente, documentar el comportamiento que impide retirarlo.

## Formularios y accesibilidad

- Etiquetas asociadas, navegación por teclado y foco visible.
- Errores vinculados al campo y resumen comprensible.
- PristineJS complementa, no reemplaza, la validación servidor.
- Confirmaciones destructivas explican el objeto y consecuencia.
- Mantener semántica, ARIA y contraste al adaptar componentes Alina.

## Seguridad de salida

Escapar en servidor según contexto. En cliente usar `textContent` para datos no confiables y evitar `innerHTML`. URLs, iconos y clases dinámicas se eligen de listas permitidas.
