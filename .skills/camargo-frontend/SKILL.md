---
name: camargo-frontend
description: Implementa o revisa vistas y JavaScript propios de Camargo PMS con Bootstrap/Alina, Fetch, JSON, validación, accesibilidad y assets por módulo.
---

# Camargo Frontend

Leer `../../AGENTS.md`, `../../docs/gobernanza/FRONTEND.md`, `CONVENCIONES.md`, `SEGURIDAD.md` y, cuando se use Alina, `PLANTILLA-ALINA.md`.

## Vistas

- Entregar al layout datos preparados; no consultar BD, servicios o permisos desde la vista.
- Reutilizar plantilla y componentes; una vista de módulo no repite `html`, cabecera, navegación, pie o scripts globales.
- Escapar salida según contexto.
- Mantener semántica, etiquetas, foco, teclado y mensajes de error accesibles.

## JavaScript

- Código propio modular, en español y documentado con JSDoc cuando sea relevante.
- Usar Fetch/JSON; manejar carga, éxito, validación, conflicto, error y cancelación cuando aplique.
- La validación cliente mejora UX, nunca reemplaza servidor.
- Evitar `innerHTML` con contenido no confiable.
- No modificar vendors ni originales Alina.

## Assets

Mantener un núcleo mínimo y declarar CSS/JS específicos por vista sin duplicados. No agregar un plugin global por conveniencia de una sola pantalla.

## Verificación

Comprobar anchos representativos, teclado, consola, red, rutas de assets, estados vacíos/carga/error y degradación cuando un componente opcional no existe.
