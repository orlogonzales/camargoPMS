# Roadmap

El roadmap fija orden y gates, no fechas. Cada fase puede dividirse en microfases revisables.

## G-0 — Gobernanza integral

Crear `AGENTS.md`, documentos y skills; consolidar decisiones y evidencia de Alina. Sin PHP, BD o Git.

Estado: completada.

## G-1 — Git y baseline documental

Revisar exclusiones, definir tratamiento de assets grandes, inicializar Git y crear baseline autorizada.

Estado: completada.

## UI-0 — Conversión controlada de Alina

Front Controller mínimo, assets seleccionados, layout y componentes PHP, vista neutra, menús de prueba y `camargo-layout.js`. Sin módulos funcionales ni BD.

Estado: completada.

## UI-1 — Validación técnica y visual + Consolidación documental

Probar bajo Apache real (`https://app.camargo-pms.test/` y subcarpeta), responsive, consola de navegador, navegación, contratos Alina, rutas y códigos HTTP reales (404/500). Consolidar en gobernanza el diseño de Identidad (`PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`), Personal, Cargos, Historial, Usuarios, Roles, Permisos, Menú Dinámico e integración con APIsPERU (DNI/RUC).

Estado: completada.

## INFRA-1 — Infraestructura de configuración, base de datos, PDO y migraciones

Resueltas decisiones P-001 (MySQL 8.4.3 LTS oficial) y P-002 (Composer PSR-4 con router nativo preservado), variables de entorno (.env y .env.example), conexión centralizada PDO (`BaseDatos.php`), esquema consolidado oficial `SQL/camargo_pms.sql` y runner CLI de migraciones versionadas (`migrar.php` con tabla técnica `migraciones`).

Estado: completada.

## IDENTIDAD-1 — Núcleo de Identidad y Personas Naturales

Construcción del maestro humano central de Personas Naturales, separación de entidades (`Persona`, `DocumentoPersona`, `ContactoPersona`), catálogos normalizados de `paises` (ISO-3166-1) y `tipos_documento` (DNI 8 dígitos, Pasaporte, CE; exclusión de RUC), reglas de unicidad documental y de contactos principales en base de datos vía columnas virtuales, soft delete, DDL + migración `002_identidad_personas.sql` sincronizada con `SQL/camargo_pms.sql`, repositorios, excepciones y servicio de dominio `PersonaServicio` con transaccionalidad atómica y pruebas completas.

Estado: completada.

## PERSONAL-1 — Colaboradores, Cargos, Episodios Laborales y Ajuste Evolutivo de Identidad

Ajuste evolutivo a Identidad (monónimos internacionales y unicidad documental parametrizada por `pais_emisor_efectivo` en migración `003`), entidad `Colaborador` con código interno estable (`COL-XXXX`) y cardinalidad 1:1 lógica con `personas`, catálogo administrable de `cargos` (semillas: ADMINISTRADOR, RECEPCIONISTA, RESERVAS, LIMPIEZA, MANTENIMIENTO), historial laboral inmutable por `episodios_laborales` y `episodios_laborales_cargos` con columnas virtuales generadas para unicidad de episodios y cargos abiertos, transiciones de cargo con continuidad temporal estricta ($D-1$ / $D$), servicio de dominio `ColaboradorServicio` con bloqueos pesimistas (`FOR UPDATE`), repositorios y suite de pruebas completas.

Estado: completada.

## AUTH-1 / AUTH-1A — Autenticación, Cuentas Humanas y Sesiones

Gestión de Usuarios humanos vinculados 1:1 a Personas (`usuarios.persona_id UNIQUE NOT NULL`), desacoplado de Colaboradores. Hashing seguro y extensible de contraseñas (`PASSWORD_DEFAULT`, `contrasena_hash VARCHAR(255)`, política de 12 a 1024 caracteres, rehash dinámico con `password_needs_rehash()`), mitigación dinámica de timing attacks / enumeración, sesiones persistidas en base de datos con tokens opacos (SHA-256), expiración dual (30m inactividad / 12h absoluta), revocación concurrente ante cambio de clave, rate limiting (5 intentos / 15m), protección CSRF estricta, mitigación session fixation y open redirect, cookies seguras, middleware de autenticación, vista de login Alina y script CLI de bootstrap estrictamente de uso único sin bypass.

Estado: completada (AUTH-1A aplicada y verificada).

## ROLES-1 — Roles, Permisos y Autorización RBAC de Backend

Infraestructura de control de acceso basado en roles (RBAC) puro en backend: modelos `Rol` y `Permiso`, entidades asociativas `usuarios_roles` y `roles_permisos`, convención atómica de permisos `recurso.accion` aditiva, rol estructural protegido `SUPERADMINISTRADOR` con autoridad total e incondicional reconocida centralmente en `AutorizacionServicio`, protección de la cuenta raíz e invariante concurrente del último Superadministrador activo mediante bloqueo pesimista (`FOR UPDATE`) en base de datos sin hardcoding de IDs o nombres de usuario, intermediario `AutorizacionIntermediario` con emisión de HTTP 403 Forbidden y layout seguro de Alina, asignación atómica en bootstrap CLI y migración determinista 007.

Estado: completada.

## MENÚ-1 — Menú Dinámico, Navegación Autorizada y Gestión de Menú

Navegación dinámica autorizada de 2 niveles persistida en tabla `opciones_menu`, preservando el contrato visual Alina (`navbar-menu-list` con `data-target="clave"` <-> `main-side-menu` con `id="clave"`). Filtro de visibilidad aditiva gobernado por RBAC (`menu.ver`, `menu.gestionar`), eliminación de categorías principales vacías, módulo interactivo bajo `/configuracion/menu` (CRUD, alternancia de estado y reordenamiento transaccional atómico sin jQuery), protección de opciones del sistema (`es_sistema = 1`), sanitización estricta de rutas internas y suites formales de prueba (MENU-01..40 y E2E-MENU-01..10).

Estado: completada (candidata a micro-baseline).

## Configuración

Empresa, logo, membrete, márgenes, parámetros, vigencias y plantillas versionadas.

## Dominio operativo

1. propiedades, niveles y unidades;
2. personas y proveedores;
3. disponibilidad y reservas;
4. estadías y arrendamientos;
5. contratos y documentos;
6. servicios, consumos y tarifas;
7. caja, cobros, gastos, impuestos y conciliación;
8. mantenimiento e inventario.

## Integraciones y reportes

API externa, WordPress, pagos/webhooks, mensajería, app, OTA y reportes. Cada integración inicia tras estabilizar el servicio de dominio que consumirá.

## Gate entre fases

Alcance aceptado, pruebas aplicables superadas, diff revisado, documentación actualizada, riesgos declarados y autorización cuando corresponda.
