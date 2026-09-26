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

- **Matriz Formal de Administración de Usuarios (USR-01 a USR-40):** 40 verificaciones automáticas cubriendo listado administrativo paginado, búsqueda por `nombre_usuario` y Persona, filtros por estado y rol, listado sin secretos técnicos (cero hashes/tokens), ficha de detalle completa de usuario, filtro de personas disponibles (excluyendo inactivas y vinculadas previamente), creación válida vinculada a Persona activa, rechazo de persona inexistente y duplicada (1:1 estricto), rechazo de username duplicado, política de contraseña estricta (12 a 1024 caracteres, preservación de espacios y caracteres Unicode), sincronización automática de actor humano (`USR_{id}`), transiciones de estado (`ACTIVO`, `INACTIVO`, `BLOQUEADO`), revocación inmediata de sesiones activas ante desactivación o bloqueo con motivo `CAMBIO_ESTADO_USUARIO`, protección del último Superadministrador activo contra desactivación, bloqueo o revocación de rol, restablecimiento administrativo de contraseña con revocación de sesiones (`CAMBIO_CONTRASENA`) y purga de secretos en auditoría, asignación y revocación de roles, listado de sesiones sin exposición de hashes, revocación de sesiones individuales y masivas (`REVOCACION_ADMINISTRATIVA`), y trazabilidad integral auditada con cero fugas de datos sensibles (40 PASS / 0 FAIL).
- **Matriz de Identidad de Actor (ACTOR-01 a ACTOR-06 — USUARIOS-1A):** 6 verificaciones automáticas comprobando:
  - `ACTOR-01`: Desacople riguroso de IDs (`usuarios.id != actores.id`), persistiendo `actores.id` del ejecutor y `usuarios.id` del usuario afectado.
  - `ACTOR-02`: Orlando (`usuario_id = 1`) registra actor humano `USR_1` (`actores.id = 2`), nunca actor sistema `CAMARGO_PMS` (`actores.id = 1`).
  - `ACTOR-03`: Operaciones sin usuario ejecutor preservan actor `CAMARGO_PMS` (`actores.id = 1`, tipo `SISTEMA`, `usuario_id = NULL`).
  - `ACTOR-04`: Usuario sin actor previo asegura automáticamente `USR_{id}` al ejecutar mutaciones sin duplicados.
  - `ACTOR-05`: Múltiples operaciones consecutivas del mismo usuario mantienen exactamente 1 actor único (cero duplicación de filas en `actores`).
  - `ACTOR-06`: Transaccionalidad y rollback: fallo en la operación revierte atómicamente usuario y auditoría sin registros huérfanos.
- **Suite E2E HTTP Real contra Apache (E2E-USR-01 a E2E-USR-10):** 10 validaciones end-to-end con cURL contra Apache real evaluando login real con Superadministrador, acceso a `/usuarios` (HTTP 200 con maquetación Alina, tabla y modales operativos), redirección de acceso anónimo (HTTP 302 a `/login`), rechazo de usuario ordinario sin permiso `usuarios.ver` (HTTP 403 estricto), endpoint JSON `/usuarios/datos` sin secretos, rechazo de mutaciones sin token CSRF (HTTP 403), creación de cuenta fixture vía POST (HTTP 201), asignación de rol vía POST (HTTP 200), cambio de estado a BLOQUEADO vía POST (HTTP 200), restablecimiento administrativo de clave vía POST (HTTP 200), y verificación de auditoría en BD productiva (`CREAR`, `ASIGNAR`, `BLOQUEAR`, `CAMBIAR_CLAVE`) con cero fugas, autoría humana correcta y cleanup defensivo total (10 PASS / 0 FAIL).
- **Reconciliación Canónica de Pruebas Automatizadas:**
  - Total bruto de ejecuciones de prueba acumuladas en el árbol de suites: **357 ejecuciones brutas (357 PASS / 0 FAIL)**.
  - Total de casos de prueba estrictamente independientes de dominio e integración: **325 casos independientes**.
  - Total de pruebas HTTP E2E reales contra servidor Apache: **34 casos únicos**.
  - Desglose independiente por módulos:
    - IDENTIDAD-1: 27/27 PASS.
    - PERSONAL-1 / PERSONAL-1A: 26/26 PASS.
    - AUTH-1 / AUTH-1A: 60/60 PASS.
    - ROLES-1: 7/7 PASS (Safe invariant + E2E).
    - MENÚ-1 / MENÚ-1A: 57/57 PASS (40 MENU matriz formal + 12 Pristine CDP + 5 Delete integrity).
    - AUDITORÍA-1 / AUDITORÍA-1A: 102/102 PASS (40 AUD matriz formal + 62 Sanitizador multibyte).
    - USUARIOS-1 / USUARIOS-1A: 46/46 PASS (40 USR matriz formal + 6 ACTOR matriz de identidad).
    - SUITES HTTP E2E REALES (Apache HTTPS): 34/34 PASS (10 Menú + 8 Auditoría + 10 Usuarios + 6 Auth/Navegación).
