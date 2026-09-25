---
name: camargo-ui-alina
description: Analiza, adapta o valida la interfaz Alina de Camargo PMS preservando originales, el contrato de menús, el layout reutilizable y la carga mínima de assets.
---

# Camargo UI Alina

Leer `../../AGENTS.md`, `../../docs/gobernanza/PLANTILLA-ALINA.md`, `FRONTEND.md` y `SEGURIDAD.md`. Usar `admin-dashboard/alina/template/blank.html` como base y las demos solo como catálogo.

## Invariantes

- No modificar `admin-dashboard/`.
- Conservar la estructura esencial de `.app-wrapper`, navegación, `.app-content` y `<main>`.
- Tratar `navbar-menu-list[data-target]` y `main-side-menu .main-menu[id]` como un único contrato.
- Generar claves DOM desde identificadores permitidos, únicas y estables.
- Proteger rutas en backend además de filtrar menús.
- No adoptar `script.js` o `theme_customizer.js` original como núcleo.
- No copiar ni cargar plugins no usados.

## Adaptación

1. Comparar el componente requerido con `blank.html` y la demo específica.
2. Clasificar markup, CSS, JS, imágenes y dependencias en globales o del módulo.
3. Encapsular estructura repetida en plantilla/componentes; la vista aporta solo contenido.
4. Implementar conducta propia tolerante a componentes opcionales.
5. Verificar rutas de assets independientes de la URL actual.

## Validación

Comparar visualmente con la fuente en escritorio y móvil. Probar área principal, menú contextual, colapsables, estado activo, sidebar, scroll, loader, tema, breadcrumbs, consola y red. Confirmar al final que los originales siguen sin cambios.
