# Changelog

Los cambios se agrupan por micro-baseline. Este archivo no reemplaza el historial Git.

## Sin publicar

### Fase UI-1 — Validación técnica/visual y consolidación documental

- Ejecutada la validación técnica y visual real bajo Apache/Laragon (PID 25016, puertos 80 y 443) tanto en VirtualHost (`https://app.camargo-pms.test/`) como en subcarpeta (`http://localhost/app.camargo-pms/`).
- Verificada la entrega con HTTP 200 de los 18 assets vinculados (CSS, JS, fuentes Tabler e imágenes), confirmando 0 errores 404 en assets.
- Verificado el renderizado del DOM en navegador Chrome/Edge headless, confirmando remoción automática del cargador (`.loader-wrapper`), aplicación de clase de tema en `<body>` y 0 excepciones de JavaScript.
- Validadas las respuestas HTTP reales ante rutas no encontradas (HTTP 404 real) e invocación defensiva de errores 500 con plantilla centrada `.error-container`.
- Consolidado en gobernanza el Principio de Separación de Identidad (`PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`).
- Formalizado el diseño documental del maestro central de Personas (documentos DNI/Pasaporte, nacionalidad inicial Perú por defecto y datos de contacto).
- Documentada la Gestión de Personal, catálogo administrable de Cargos laborales e Historial Laboral inmutable por episodios cronológicos.
- Formalizada la distinción entre Cargo laboral y Rol de seguridad, catálogo dinámico de Roles y permisos atómicos `recurso.accion`.
- Incorporada la regla de seguridad vinculante `OCULTAR EL MENÚ NO ES AUTORIZACIÓN` con intermediarios de backend y respuesta HTTP 403 real.
- Formalizado el diseño del módulo de Gestión de Menú Dinámico con soporte drag & drop, vinculación opción ↔ permiso y contrato Alina `data-target` ↔ `id`.
- Establecida la frontera conceptual entre Personal y Caja (Personal como contraparte de gastos/sueldos, sin convertir Personal en subsistema contable).
- Aprobada y registrada la decisión D-017 de integración externa con APIsPERU (DNI de 8 dígitos y RUC de 11 dígitos) desacoplada mediante `ServicioConsultaIdentidad` y adaptadores.
- Reafirmada la auditoría transversal y la diferenciación estricta de actores (`USER`, `SYSTEM`, `INTEGRATION`, `PAYMENT_PROVIDER`).
- Verificada la inmutabilidad total de `admin-dashboard/`.

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
