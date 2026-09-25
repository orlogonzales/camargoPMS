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

## Personal y Colaboradores

Gestión de colaboradores vinculados a Personas, catálogo administrable de Cargos laborales e Historial Laboral inmutable por episodios cronológicos.

Estado: siguiente fase recomendada.


## Autenticación y Cuentas

Gestión de Usuarios humanos, hashing seguro de contraseñas (`password_hash`), control de sesiones activas, expiración, revocación forzada y protección de cuenta Superadministrador.

Estado: pendiente.

## Roles, Permisos y Autorización

Catálogo administrable de Roles, permisos atómicos granulares (`recurso.accion`) independientes de los cargos, intermediarios de backend para autorización estricta (HTTP 403 real) bajo la regla vinculante `OCULTAR EL MENÚ NO ES AUTORIZACIÓN`.

Estado: pendiente.

## Menú Dinámico

Módulo de administración visual de menú en Configuración (jerarquía, rutas, iconos Tabler, drag & drop, activación y asociación opción ↔ permiso), preservando el contrato visual Alina (`data-target` ↔ `id`).

Estado: pendiente.

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
