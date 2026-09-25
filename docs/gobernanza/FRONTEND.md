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
    data-target="operaciones"
              ↓
main-side-menu
    main-menu id="operaciones"
```

Ambas zonas forman una sola navegación. La clave es estable, única, apta para DOM y generada desde un identificador interno controlado. Etiquetas, iconos y orden son datos distintos. El menú secundario admite enlaces y grupos colapsables con IDs igualmente controlados.

La ruta activa debe seleccionar área, opción y padres colapsables. El servidor filtra por permisos y protege cada endpoint de forma independiente.

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
