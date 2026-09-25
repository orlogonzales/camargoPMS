# Changelog

Los cambios se agrupan por micro-baseline. Este archivo no reemplaza el historial Git.

## Sin publicar

### Fase UI-0 — Esqueleto MVC y plantilla Alina reutilizable

- Creado el Front Controller en `public/index.php` con manejo defensivo de errores y resolución dinámica de URL base.
- Implementado el motor de renderizado seguro `Vista` y la clase `Respuesta` con cabeceras de seguridad.
- Implementado el `Autocargador` PSR-4 propio para el espacio de nombres `CamargoPMS\` y `Enrutador` mínimo reversible.
- Formalizadas las referencias visuales oficiales de Alina: `blank.html` (layout general), `sign_in.html` (login), `index.html` (dashboard) y `error_*.html` (errores HTTP).
- Adaptada la plantilla principal `principal.php` preservando el árbol DOM de `blank.html`.
- Extraídos 9 componentes reutilizables: `head`, `cargador`, `navegacion`, `menu-principal`, `menu-secundario`, `cabecera`, `migas-pan`, `pie` y `scripts`.
- Desarrollado el script propio de layout `camargo-layout.js` en JavaScript moderno nativo (sin jQuery ni scripts demo) asegurando el contrato `data-target` ↔ `id`, responsive, tema claro/oscuro y scroll.
- Copiados selectivamente los assets esenciales de Bootstrap 5, Tabler Icons, Simplebar, CSS de Alina e ilustraciones de error; eliminadas rutas relativas frágiles mediante ayudantes de URL absoluta.
- Creada la vista neutra de comprobación en `app/Vistas/panel/inicio.php`.
- Implementada la arquitectura de errores con plantilla aislada `plantillas/error.php` (.error-container) y vista reutilizable `errores/error.php` para 400, 403, 404, 500 y 503.
- Creado `camargo.css` para estilos propios sin alterar los originales de Alina.
- Verificada la inmutabilidad íntegra de `admin-dashboard/`.

### Fase G-1 — Git y baseline documental

- Definida `main` como rama oficial y el repositorio GitHub de Camargo PMS como `origin`.
- Creado `.gitignore` para secretos, dependencias, runtime, IDE, artefactos, respaldos y bases locales.
- Aprobado versionar intactos Alina y su documentación como referencia reproducible.
- Mantenido el Figma local e intacto fuera de Git, sin incorporar Git LFS.
- Incorporado el control de SHA, working tree y ahead/behind a los gates de micro-baseline.
- Preparado el primer baseline documental sin código funcional, Composer ni base de datos.

### Fase G-0 — Gobernanza

- Creada la entrada obligatoria `AGENTS.md`.
- Definidas arquitectura por capas y nomenclatura propia en español.
- Documentados dominio, seguridad, base de datos, API, auditoría, pruebas, Git y roadmap.
- Registrado el contrato acoplado de menús Alina y la estrategia de assets mínimos.
- Documentada la preservación íntegra de los originales Alina.
- Preparados skills locales especializados.

No se ha iniciado Git, PHP, base de datos ni implementación funcional.
