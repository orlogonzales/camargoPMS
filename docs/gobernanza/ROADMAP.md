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

## Infraestructura

Resolver decisiones P-001 (MySQL vs MariaDB), P-002 (Composer, autoload definitivo y routing), P-003 (pruebas), variables de entorno (.env), conexión PDO centralizada, contenedor de dependencias/fábrica y framework de migraciones versionadas.

Estado: siguiente fase recomendada.

## Identidad y Personas

Maestro central de Personas, normalización de documentos (DNI, Pasaporte), datos de contacto, nacionalidad por defecto Perú e integración externa desacoplada mediante `ServicioConsultaIdentidad` con adaptador APIsPERU (DNI y RUC).

Estado: pendiente.

## Personal y Colaboradores

Gestión de colaboradores vinculados a Personas, catálogo administrable de Cargos laborales e Historial Laboral inmutable por episodios cronológicos.

Estado: pendiente.

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
