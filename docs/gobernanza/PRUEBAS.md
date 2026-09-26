# Pruebas y puertas de calidad

## Capas

- Unitarias: reglas de servicios, cálculos, transiciones y utilidades sin infraestructura real.
- Integración: repositorios, PDO, migraciones, transacciones y adaptadores.
- Funcionales HTTP: rutas, intermediarios, permisos, CSRF, HTML y contratos JSON.
- Frontend: módulos JavaScript, interacción y errores de consola.
- End-to-end: flujos críticos completos cuando la infraestructura exista.
- Visuales: comparación de Alina, responsive y documentos PDF cuando corresponda.

## Casos críticos

Disponibilidad concurrente, idempotencia de pagos/webhooks, movimientos de caja, permisos, históricos de tarifa, contratos versionados y migraciones requieren pruebas positivas, negativas y de repetición.

## Gate por incremento

Antes de cerrar:

1. análisis estático/lint y formato disponibles;
2. pruebas nuevas del comportamiento modificado;
3. suite relacionada sin fallos;
4. rutas y assets sin 404;
5. consola del navegador sin errores para cambios UI;
6. revisión de seguridad cuando aplique;
7. diff sin cambios ajenos;
8. documentación y changelog actualizados.

Una prueba omitida debe informarse con motivo, riesgo y forma de ejecutarla; no se presenta como superada.

## Datos de prueba

Usar datos sintéticos y reproducibles. No copiar datos productivos, credenciales o documentos reales. Las pruebas de BD deben aislarse y poder reiniciarse sin afectar otros entornos.

## UI-0/UI-1

La primera baseline de interfaz debe comprobar escritorio y móvil, loader, navegación acoplada, submenús, activo por ruta, sidebar, Simplebar, tema, breadcrumbs, assets globales/específicos, accesibilidad básica y ausencia de errores de consola.

## Herramientas

El framework concreto de pruebas PHP/JS y las herramientas de navegador siguen pendientes. Se decidirán en infraestructura con compatibilidad PHP 8.3, ejecución local simple y automatización futura como criterios.

## Protocolo de Seguridad y Gates Formales (MENÚ-1)

- **Regla contra Bloqueos y Metadata Locks:** Toda prueba que manipule esquemas, transacciones o concurrencia debe fijar obligatoriamente al inicio de sesión:
  - `SET SESSION innodb_lock_wait_timeout = 2;`
  - `SET SESSION lock_wait_timeout = 3;`
  - Limpieza defensiva en bloque `finally`: toda transacción activa debe cerrarse con `rollBack()` y las conexiones PDO deben liberarse (`unset`) antes de ejecutar `DROP DATABASE` para evitar bloqueos por metadata locks.
- **Matriz Formal de Menú (MENU-01 a MENU-40):** 40 verificaciones automáticas cubriendo jerarquía de 2 niveles, rechazo de rutas maliciosas, filtro aditivo RBAC, depuración de categorías principales vacías, operaciones CRUD, reordenamiento atómico transaccional, contrato Alina y regresión de autenticación/autorización.
- **Suite E2E HTTP Real contra Apache (E2E-MENU-01 a E2E-MENU-10):** Pruebas end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`), evaluando redirecciones, renderizado de dos niveles, validación de permisos `menu.ver`/`menu.gestionar`, mutaciones con token CSRF y revocación de sesión.
- **Auditoría de Paridad SQL 100%:** Verificación automatizada de paridad total entre la ejecución secuencial de migraciones (`001` a `008`) y la carga del esquema consolidado canónico `SQL/camargo_pms.sql` sobre bases de datos efímeras aisladas.
- **Validación Frontend con PristineJS (MENÚ-1A):** Verificación automatizada PRISTINE-01 a PRISTINE-12 mediante protocolo CDP en navegador headless y API backend, comprobando disponibilidad local de la librería (v1.1.0), inicialización modular en modales, bloqueo de submit ante campos inválidos, saneamiento dinámico al alternar niveles, tolerancia a reaberturas y rechazo de bypass en backend (HTTP 422).
- **Contrato de Contraseñas AUTH-1A:** Confirmado formalmente el estándar canónico `PASSWORD_DEFAULT` en `password_hash()` y `password_verify()` de los servicios productivos de autenticación y usuarios. (La mención previa en la auditoría respecto al uso de ARGON2ID en todo el sistema fue un error descriptivo de reporte; el código productivo preserva `PASSWORD_DEFAULT`).

- **Matriz Formal de Auditoría y Trazabilidad (AUD-01 a AUD-40):** 40 verificaciones automáticas cubriendo creación de actores por tipo (`USUARIO`, `SISTEMA`, `INTEGRACION`, `PROVEEDOR_PAGO`), rechazo de duplicados, acciones de auditoría (`CREAR`, `EDITAR`, `ELIMINAR`), purga recursiva de secretos (`contrasena`, `contrasena_hash`, `CSRF`, tokens, authorization, api_key), captura de contexto HTTP y correlación, atomicidad transaccional y rollback, e integraciones activas en Login, Logout, Roles, Menú y Usuarios (40 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-01 a E2E-08):** 8 validaciones end-to-end con cURL contra Apache real evaluando generación de auditoría en Login/Logout real, mutaciones de roles, creación de opciones de menú vía JSON, ausencia absoluta de fugas de datos de auditoría en respuestas HTTP, y preservación inalterada de navegación y autorización RBAC (8 PASS / 0 FAIL).
- **Verificación de Cero Fugas de Secretos en Base de Datos Real:** Script de inspección profunda sobre la tabla `auditoria` en `camargo_pms` confirmando cero coincidencias de contraseñas, hashes, tokens o secretos en registros generados en tiempo real.
- **Auditoría de Paridad SQL 100% Migración 009:** Paridad verificada entre la ejecución acumulada de migraciones `001..009` y el esquema canónico consolidado `SQL/camargo_pms.sql` (20 tablas, 22 claves foráneas, semillas estructurales idénticas).
- **Matriz de Sanitización Multibyte AUDITORÍA-1A (14/14 PASS):** Verificación exhaustiva de eliminación y redacción recursiva de todas las 14 variantes sensibles (`password`, `contrasena`, `contraseña`, `password_hash`, `csrf`, `csrf_token`, `authorization`, `cookie`, `session`, `session_id`, `token`, `api_key`, `secret`, `client_secret`), combinaciones de mayúsculas/minúsculas (`CONTRASEÑA`, `SESSION`), anidamiento profundo y strings JSON embebidos, con preservación estricta de claves legítimas de negocio.
- **Reconciliación Canónica de Pruebas Automatizadas:**
  - Total bruto de ejecuciones de prueba acumuladas: **222 ejecuciones (222 PASS / 0 FAIL)**.
  - Total de pruebas estrictamente independientes: **218 pruebas independientes** (las 4 restantes corresponden a comprobaciones de humo en `test_regresion_menu1a.php` que solapan verificaciones ya cubiertas en `probar_menu_completo.php` y pruebas de contrato).
  - Desglose independiente:
    - IDENTIDAD-1: 27/27 PASS.
    - PERSONAL-1: 26/26 PASS.
    - AUTH-1 / AUTH-1A: 60/60 PASS.
    - ROLES-1: 7/7 PASS (Safe invariant + E2E).
    - MENÚ-1 / MENÚ-1A: 50/50 PASS (40 unitarias/integración + 10 E2E HTTP real).
    - AUDITORÍA-1 / AUDITORÍA-1A: 48/48 PASS (40 AUD matriz formal + 8 E2E HTTP real).
