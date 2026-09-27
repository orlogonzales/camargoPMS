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
- **Matriz Formal de Roles y Permisos (ROL2-01 a ROL2-40 — ROLES-2 / ROLES-2A):** 40 verificaciones automáticas cubriendo serialización completa de modelos `Rol` y `Permiso`, creación válida con persistencia, rechazo de creación con clave reservada `SUPERADMINISTRADOR` o duplicada, validación sintáctica de claves técnicas (3-50 chars, alfanumérico + guión bajo), validación de nombres (2-100 chars), validación de estados (`ACTIVO`/`INACTIVO`), actualización de metadatos, rechazo de colisiones de claves, transiciones operativas de estado, protección inviolable de `SUPERADMINISTRADOR` (contra renombrado y desactivación), protección de roles de sistema (`es_sistema = 1`), preservación del invariante pesimista del último Superadministrador humano activo, preservación del ciclo de vida de roles personalizados mediante `ACTIVO` / `INACTIVO` (ROL2-16), persistencia incondicional en base de datos sin borrado físico (ROL2-17), preservación intacta de asignaciones históricas de permisos y usuarios tras desactivación (ROL2-18), erradicación total de métodos de eliminación física productiva en servicio y controlador (ROL2-19), reactivación operativa sin pérdida de relaciones (ROL2-20), agregaciones en `listarRolesConConteos()` (`total_usuarios`, `total_permisos`), filtros de búsqueda textual y de estado, consulta de usuarios asignados con detalle de `Persona` vinculada, catálogo jerárquico por módulos, sincronización matricial atómica transaccional de permisos, desasignación total con matriz vacía, rechazo de permisos inexistentes, protección de permisos críticos de gobernanza de `SUPERADMINISTRADOR`, rollback completo ante fallos transaccionales, auditoría transversal bajo D-061 con actor humano resuelto (`CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`), eventos `ASIGNAR` y `REVOCAR` con correlación unificada (`correlacion_id`), resolución de `CAMARGO_PMS` para operaciones sin ejecutor, evaluación de permisos en tiempo real sin relogin, supresión de permisos ante roles inactivos, acumulación distributiva multimódulo de permisos y cero fugas de contraseñas o hashes en auditoría (40 PASS / 0 FAIL).
- **Prueba Específica de Persistencia Histórica (ROL-HIST-01 — ROLES-2A):** 10 verificaciones secuenciales automáticas certificando el ciclo de vida completo de un rol: creación, asignación de permisos, vinculación y desvinculación de usuario fixture, desactivación operativa, comprobación estricta de existencia inmutable de la fila en tabla `roles` con su ID y código originales, verificación de bitácora de auditoría append-only sin eventos de borrado físico (`ELIMINAR` = 0), reactivación a `ACTIVO` y restauración funcional íntegra de capacidades y permisos asignados (1 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-ROL2-01 a E2E-ROL2-12 — ROLES-2 / ROLES-2A):** 12 validaciones end-to-end con cURL contra el servidor Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando login real con Superadministrador, redirección anónima de `/configuracion/roles` hacia `/login` (HTTP 302), bloqueo de usuario ordinario sin permiso `roles.ver` (HTTP 403 estricto), renderizado de interfaz Alina con modales operativos (HTTP 200), endpoint JSON de listado con agregaciones `/configuracion/roles/datos` (HTTP 200), catálogo de permisos por módulo `/configuracion/roles/permisos-catalogo` (HTTP 200), bloqueo de mutación sin token CSRF (HTTP 403), creación de rol vía POST y consulta de detalle GET (HTTP 201/200), actualización matricial atómica de permisos vía POST (HTTP 200), desactivación y reactivación operativa preservando ID histórico (HTTP 200), verificación de inexistencia absoluta de endpoints de DELETE físico (POST/DELETE a rutas de eliminación retornan HTTP 404 y rol persiste intacto en BD), y auditoría bajo D-061 para eventos de ciclo de vida (`DESACTIVAR` / `ACTIVAR`) con actor humano resuelto del Superadministrador y cleanup defensivo total (12 PASS / 0 FAIL).
- **Matriz Formal de Configuración del Sistema (CFG-01 a CFG-40 — CONFIGURACIÓN-1):** 40 verificaciones automáticas cubriendo validación y casting de todos los 7 tipos funcionales (`TEXTO`, `ENTERO`, `DECIMAL`, `BOOLEANO`, `FECHA`, `HORA`, `JSON`), hidratación fiel del modelo `ConfiguracionParametro`, tipado nativo en `obtenerValorTipado()` y `obtenerValorPredeterminadoTipado()`, exportación asociativa completa, operaciones de repositorio (búsqueda por clave, ID, existencia, listado general, agrupado, actualización y restauración), operaciones de servicio (`obtener()`, valor por defecto, caché de memoria request-scoped, actualización individual con validación y excepciones `ConfiguracionNoEncontradaExcepcion` y `ConfiguracionNoEditableExcepcion`, validación estricta de tipos, auditoría D-061 imputada a `USR_1`, actualización atómica en lote con reversión completa ante fallos y `correlacion_id` unificado, restauración a valores de fábrica y ofuscación automática de parámetros sensibles `***` en auditoría) (40 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-CFG-01 a E2E-CFG-12 — CONFIGURACIÓN-1):** 12 validaciones end-to-end con cURL contra el servidor Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando redirección anónima de `/configuracion/sistema` a `/login` (HTTP 302), bloqueo de usuario ordinario sin permiso `configuracion.ver` (HTTP 403 estricto), acceso Superadmin a interfaz Alina con navegación por pestañas y CSRF (HTTP 200), endpoint JSON `/configuracion/sistema/datos` con catálogo agrupado (HTTP 200), rechazo de mutaciones sin token CSRF (HTTP 403), rechazo de mutaciones con usuario sin permiso `configuracion.editar` (HTTP 403), actualización atómica en lote vía POST con Superadmin (HTTP 200), consistencia de persistencia en MySQL y API JSON, rechazo con HTTP 422 ante intento de mutación sobre parámetro protegido `sistema.version_instalada`, rechazo con HTTP 422 ante valor incompatible con el tipo funcional, restauración vía POST a valor de fábrica en BD (HTTP 200), y verificación de auditoría en base de datos con actor humano `USR_{adminId}` cumpliendo D-061 y cleanup defensivo total (12 PASS / 0 FAIL).
- **Matriz Formal de Propiedades e Inmuebles Físicos (PROP-01 a PROP-40 — PROPIEDADES-1):** 40 verificaciones automáticas cubriendo instanciación e hidratación de `Propiedad`, `estaActiva()`, formateo de `obtenerUbicacionCompleta()`, parseo y casting de `obtenerCoordenadas()`, serialización consistente en `aArreglo()`, `haciaArreglo()` y `aArray()`, recreación con `desdeArreglo()`, excepciones de dominio `PropiedadDuplicadaExcepcion` (409) y `PropiedadNoEncontradaExcepcion` (404), principio `PROPIEDAD ≠ UNIDAD` (cero atributos de unidad/habitación), principio `PROPIEDAD ≠ REGISTRO DESECHABLE` (cero métodos de eliminación), validación sintáctica de código (3-30 chars, alfanumérico), validación de longitud de nombre (mínimo 3), validación de existencia de país en BD, validación de rangos de coordenadas GPS (latitud `[-90, 90]`, longitud `[-180, 180]`), coordenadas opcionales nulas admitidas, sanitización y normalización en mayúsculas, persistencia e inserción en repositorio con nuevo ID, verificación de unicidad con `existeCodigo()`, recuperación por ID y código con JOIN a países, actualización de campos técnicos, alternancia de estado operativo a `INACTIVO`, filtrado por término de búsqueda con parámetros unívocos anti-HY093, filtrado por estado y país, conteo exacto de registros en repositorio, registro de evento `CREAR` en auditoría, resolución de actor humano ejecutor (`USR_1`) bajo D-061, resolución defensiva de `CAMARGO_PMS` sin ejecutor, registro de evento `EDITAR` con diff de atributos, omisión de auditoría redundante cuando no hay cambios, registro de evento `DESACTIVAR` con motivo opcional, rechazo de código duplicado en creación y actualización colisionante, actualización exitosa conservando el propio código, y ausencia absoluta de métodos de eliminación física productiva (40 PASS / 0 FAIL).
- **Prueba Específica de Persistencia Histórica (PROP-HIST-01 — PROPIEDADES-1):** 10 verificaciones secuenciales automáticas certificando el ciclo de vida completo de un inmueble: creación de propiedad física fixture, verificación de persistencia y estado inicial `ACTIVO`, actualización de datos técnicos, comprobación de trazabilidad `CREAR` y `EDITAR` en `auditoria`, desactivación operativa a `INACTIVO` con motivo justificado, comprobación estricta de existencia ininterrumpida de la fila en tabla `propiedades` (cero DELETE), comprobación de evento `DESACTIVAR` en auditoría, recuperación y listado con filtro de inactivas, reactivación a estado `ACTIVO`, y comprobación de evento `ACTIVAR` y garantía de cero DELETE en arquitectura (10/10 PASS).
- **Suite E2E HTTP Real contra Apache (E2E-PROP-01 a E2E-PROP-12 — PROPIEDADES-1):** 12 validaciones end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando:
  - `E2E-PROP-01`: Acceso anónimo a `/propiedades` redirige a `/login` (HTTP 302).
  - `E2E-PROP-02`: Usuario ordinario sin permiso `propiedades.ver` recibe HTTP 403 Forbidden.
  - `E2E-PROP-03`: Superadmin accede a `/propiedades` (HTTP 200) y la plantilla contiene el contrato de navegación Alina.
  - `E2E-PROP-04`: Endpoint `/propiedades/datos` no autenticado es rechazado con HTTP 302 hacia `/login`.
  - `E2E-PROP-05`: Endpoint `/propiedades/datos` retorna JSON válido con datos y metadatos de paginación.
  - `E2E-PROP-06`: POST `/propiedades` sin token CSRF devuelve HTTP 403 Forbidden.
  - `E2E-PROP-07`: POST `/propiedades` crea nueva propiedad física y retorna HTTP 201 JSON.
  - `E2E-PROP-08`: GET `/propiedades/{id}` retorna detalle completo en JSON (HTTP 200).
  - `E2E-PROP-09`: GET `/propiedades/{id}/perfil` renderiza la ficha técnica con principio `PROPIEDAD ≠ UNIDAD` (HTTP 200).
  - `E2E-PROP-10`: PUT `/propiedades/{id}` actualiza información y coordenadas GPS (HTTP 200 JSON).
  - `E2E-PROP-11`: PATCH `/propiedades/{id}/estado` conmuta ciclo de vida `ACTIVO` ↔ `INACTIVO` con HTTP 200 JSON.
  - `E2E-PROP-12`: DELETE `/propiedades/{id}` es una ruta no registrada que responde 404 Not Found (garantía de no DELETE físico).
- **Matriz Formal de Unidades Físicas y Alojables (UNI-01 a UNI-40 — UNIDADES-1):** 40 verificaciones automáticas cubriendo:
  - Instanciación e hidratación de `TipoUnidad` y `Unidad`.
  - Comprobación de `estaActiva()`, cálculo de ocupación total `capacidadTotal()` (`capacidad_estandar + capacidad_maxima`), validación de número de camas, baños, piso/nivel.
  - Serialización consistente en `aArreglo()`, `haciaArreglo()`, `aArray()` y deserialización fiel con `desdeArreglo()`.
  - Excepciones de dominio `UnidadDuplicadaExcepcion` (409) y `UnidadNoEncontradaExcepcion` (404).
  - Principio `PROPIEDAD ≠ UNIDAD` (propiedad física continente con ID obligatorio `propiedad_id NOT NULL`, cero unidades huérfanas).
  - Principio `UNIDAD ≠ REGISTRO DESECHABLE` (cero métodos de eliminación física, ciclo histórico `ACTIVO ↔ INACTIVO`).
  - Principio `UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD` (cero fechas de ocupación, check-in/out, precios, contratos de arrendamiento; P-004, P-005 y P-006 estrictamente abiertas).
  - Unicidad de código acotada por propiedad (`UNIQUE(propiedad_id, codigo)` bajo D-065). Códigos idénticos en distintas propiedades admitidos; duplicados en la misma propiedad rechazados con 409.
  - Regla de negocio de propiedad inactiva: rechazo de creación de nuevas unidades en propiedades inactivas, pero preservación intacta de unidades históricas preexistentes.
  - Validación de tipos de unidad existentes en BD (`tipos_unidad`).
  - Persistencia, filtros de búsqueda textual, filtros por propiedad, tipo y estado operativo.
  - Registro de eventos en bitácora de auditoría (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) con resolución de actor humano (`USR_1`) bajo D-061 y diff de atributos sin auditoría redundante (40 PASS / 0 FAIL).
- **Prueba Específica de Persistencia Histórica (UNI-HIST-01 — UNIDADES-1):** 10 verificaciones secuenciales automáticas certificando el ciclo de vida completo de una unidad: creación vinculada a propiedad activa, persistencia inicial en estado `ACTIVO`, actualización de especificaciones físicas, trazabilidad `CREAR` y `EDITAR` en `auditoria`, desactivación operativa a `INACTIVO` con motivo, comprobación de persistencia inmutable en tabla `unidades` (cero DELETE), comprobación de evento `DESACTIVAR`, reactivación a `ACTIVO`, comprobación de evento `ACTIVAR`, y garantía arquitectónica de cero DELETE en servicios y repositorios (10/10 PASS).
- **Suite E2E HTTP Real contra Apache (E2E-UNI-01 a E2E-UNI-12 — UNIDADES-1):** 12 validaciones end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando:
  - `E2E-UNI-01`: Acceso anónimo a `/unidades` redirige a `/login` (HTTP 302).
  - `E2E-UNI-02`: Usuario ordinario sin permiso `unidades.ver` recibe HTTP 403 Forbidden.
  - `E2E-UNI-03`: Superadmin accede a `/unidades` (HTTP 200) y la plantilla contiene el contrato de navegación Alina.
  - `E2E-UNI-04`: Endpoint `/unidades/datos` no autenticado es redirigido a `/login` (HTTP 302).
  - `E2E-UNI-05`: Endpoint `/unidades/datos` retorna JSON válido con listado, metadatos y conteos (HTTP 200).
  - `E2E-UNI-06`: POST `/unidades` sin token CSRF devuelve HTTP 403 Forbidden.
  - `E2E-UNI-07`: POST `/unidades` crea nueva unidad física alojable vinculada a propiedad y retorna HTTP 201 JSON.
  - `E2E-UNI-08`: GET `/unidades/{id}` retorna detalle completo en JSON (HTTP 200).
  - `E2E-UNI-09`: GET `/unidades/{id}/perfil` renderiza la ficha técnica con principio `PROPIEDAD ≠ UNIDAD` (HTTP 200).
  - `E2E-UNI-10`: PUT `/unidades/{id}` actualiza capacidades físicas y metadatos (HTTP 200 JSON).
  - `E2E-UNI-11`: PATCH `/unidades/{id}/estado` conmuta ciclo de vida `ACTIVO` ↔ `INACTIVO` con HTTP 200 JSON.
  - `E2E-UNI-12`: DELETE `/unidades/{id}` es una ruta no registrada que responde 404 Not Found (garantía de no DELETE físico).
- **Harness Técnico de Concurrencia de Disponibilidad e Inventario Diario (CONC-01 a CONC-05 — GATE OPERATIVO-1 / P-006):** 5 verificaciones automáticas de concurrencia real ejecutadas sobre base de datos efímera aislada (`camargo_pms_concurrencia_p006`) con dos conexiones PDO concurrentes independientes:
  - `CONC-01`: Concurrencia directa / Condición de carrera por la misma noche en una unidad. Dos transacciones concurrentes intentan ocupar la misma fecha; solo la primera confirma exitosamente (`COMMIT`), mientras que la segunda es bloqueada y rechazada por la restricción `UNIQUE (unidad_id, fecha)`. Cero sobreventa confirmada (1 PASS / 0 FAIL).
  - `CONC-02`: Atomicidad multinoche y rollback completo. Un intento de reserva multinoche colisiona con una noche previamente ocupada y ejecuta `ROLLBACK` total inmediato de toda la transacción; comprobación estricta de cero noches huérfanas o reservas parcialmente persistidas en base de datos (1 PASS / 0 FAIL).
  - `CONC-03`: Coexistencia de intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$. Comprobación de que la fecha de salida (checkout) de una reserva y la fecha de entrada (check-in) de una reserva subsecuente sobre la misma unidad en la misma fecha calendario coexisten a la perfección sin generar colisión espuria (1 PASS / 0 FAIL).
  - `CONC-04`: Liberación atómica y reocupación inmediata. La cancelación o expiración de una reserva elimina atómicamente sus noches en el inventario diario y permite la reocupación inmediata por otra reserva sin residuos lógicos (1 PASS / 0 FAIL).
  - `CONC-05`: Timeouts defensivos y verificación de cero locks residuales. La sesión impone `innodb_lock_wait_timeout = 2` y confirma la inexistencia de transacciones zombis o bloqueos residuales en `information_schema.innodb_trx` tras la ejecución concurrente (1 PASS / 0 FAIL).
- **Suite de Concurrencia Productiva sobre BD Real (CONC-PROD-01 a CONC-PROD-05 — DISPONIBILIDAD-1):** 5 verificaciones de concurrencia real ejecutadas sobre el motor MySQL Community Server 8.4.3 LTS con dos conexiones PDO concurrentes independientes:
  - `CONC-PROD-01`: Inserción atómica multinoche sparse en orden determinista `ORDER BY unidad_id ASC, fecha ASC` (3 noches registradas exactamente).
  - `CONC-PROD-02`: Colisión capturada y rollback íntegro ejecutado (cero noches huérfanas persistidas ante colisión de clave duplicada 1062).
  - `CONC-PROD-03`: Bloqueos contiguos $[\text{D1}, \text{D2})$ y $[\text{D2}, \text{D3})$ operan sin falso conflicto en fecha de checkout (noche de salida liberada).
  - `CONC-PROD-04`: Liberación atómica de bloqueo restaura disponibilidad inmediata en el rango completo.
  - `CONC-PROD-05`: Error traducido formalmente a HTTP 409 `ConflictoDisponibilidadExcepcion`.
- **Verificación Rigurosa de Contratos de Concurrencia 1205 y 1213 (G-1205 y G-1213 — DISPONIBILIDAD-1A):** 2 verificaciones automáticas directas sobre el motor MySQL 8.4.3:
  - `G-1205`: Error 1205 real (*Lock wait timeout exceeded*) inducido en MySQL 8.4.3 con `innodb_lock_wait_timeout = 1`. Captura de `["HY000", 1205]`, reversión transaccional completa (`inTransaction = false`), y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409).
  - `G-1213`: Error 1213 real (*Deadlock found when trying to get lock*) inducido mediante contención circular entre dos procesos concurrentes en InnoDB. Detección automática por el InnoDB Deadlock Detector de MySQL 8.4.3 (`["40001", 1213]`), captura en servicio, rollback atómico y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409).
- **Matriz Formal de Disponibilidad e Inventario Diario (DISP-01 a DISP-40 — DISPONIBILIDAD-1):** 40 verificaciones automáticas de dominio e integración cubriendo:
  - Fallback a zona horaria central del PMS (`America/Lima`) y prevalencia de zona horaria por propiedad física.
  - Mitigación segura ante identificadores IANA desconocidos o inválidos recurriendo de forma segura a `America/Lima`.
  - Validación de formato de fechas (`Y-m-d`), rechazo de fechas gregorianas irreales (ej. 31 de febrero).
  - Rechazo de estancias de 0 noches y fechas invertidas ($\text{salida} < \text{entrada}$).
  - Cálculo de noches en cruce de mes (30 ene al 02 feb = 3 noches).
  - Ausencia de filas en inventario sparse indica disponibilidad completa.
  - Bloqueo de 1 noche marca la unidad como no disponible; consultas fuera de fechas mantienen disponibilidad.
  - Solapamientos parciales (inicio, fin, interno y exacto) detectan ocupación.
  - Intervalo semiabierto $[\text{D1}, \text{D2})$ permite checkout y check-in contiguos sin colisión.
  - Filtro por propiedad física restringe el catálogo; unidades inactivas o de propiedades inactivas excluidas comercialmente y rechazan bloqueos.
  - Estados comerciales y motivos técnicos descriptivos (mantenimiento vs manual).
  - Filtro `solo_disponibles` excluye unidades ocupadas; validaciones de motivo no vacío y longitudes.
  - Persistencia de registro maestro `bloqueos_unidad` (estado `ACTIVO`, 4 noches) e inventario sparse (4 noches exactas).
  - Liberación actualiza a `LIBERADO`, registra timestamps y actor liberador, y elimina atómicamente filas en inventario (0 filas restantes).
  - Rechazo de re-liberación sobre bloqueos inactivos.
  - Matriz mensual / rack de ocupación con cálculo dinámico de días del mes (bisiesto 29, ordinario 28).
  - Trazabilidad transversal de auditoría D-061 (`CREAR` y `LIBERAR`) con resolución de actor humano (`USR_1`).
  - Preservación estricta de P-005: cero columnas de precio, tarifa, moneda o impuestos en tablas.
  - Opción de menú `disponibilidad_calendario` presente bajo `propiedades` con ruta `/disponibilidad` (40 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-DISP-01 a E2E-DISP-12 — DISPONIBILIDAD-1):** 12 validaciones end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando:
  - `E2E-DISP-01`: Acceso anónimo a `/disponibilidad` redirige a `/login` (HTTP 302).
  - `E2E-DISP-02`: Usuario ordinario sin permiso `disponibilidad.ver` recibe HTTP 403 Forbidden.
  - `E2E-DISP-03`: Superadmin accede a `/disponibilidad` (HTTP 200) y la plantilla contiene pestañas operativas y KPIs.
  - `E2E-DISP-04`: Endpoint `/disponibilidad/consultar` no autenticado redirige a `/login` (HTTP 302).
  - `E2E-DISP-05`: Endpoint `/disponibilidad/consultar` retorna 200 OK con estructura completa de inventario.
  - `E2E-DISP-06`: Consulta con fechas invertidas (salida < entrada) responde 422 Unprocessable Entity.
  - `E2E-DISP-07`: POST `/disponibilidad/bloquear` sin token CSRF devuelve HTTP 403 Forbidden.
  - `E2E-DISP-08`: POST `/disponibilidad/bloquear` con datos válidos crea bloqueo y responde 201 Created.
  - `E2E-DISP-09`: POST `/disponibilidad/bloquear` sobre fechas ocupadas responde 409 Conflict (D-067).
  - `E2E-DISP-10`: GET `/disponibilidad/bloqueos` retorna 200 OK con listado paginado y metadatos.
  - `E2E-DISP-11`: GET `/disponibilidad/matriz` retorna 200 OK con estructura de rack mensual y estadísticas.
  - `E2E-DISP-12`: POST `/disponibilidad/liberar` con CSRF libera bloqueo y restaura disponibilidad en inventario.
- **Matriz Formal de Contrato Monetario y Financiero (FIN-01 a FIN-20 — GATE FINANCIERO-1 / P-005):** 20 verificaciones automáticas de dominio, aritmética exacta y motor SQL:
  - `FIN-01`: PEN canónico según ISO 4217 (código oficial de 3 letras alfabéticas en mayúsculas).
  - `FIN-02`: Símbolo 'S/' desacoplado de la moneda (exclusivo para presentación visual en capa de vistas).
  - `FIN-03`: Prohibición estricta de `FLOAT`/`DOUBLE` y comprobación empírica de fallas de precisión binaria IEEE 754 (`0.1 + 0.2 != 0.3`).
  - `FIN-04`: Importe comercial estándar a 2 decimales (`DECIMAL(15,2)` verificado en MySQL 8.4).
  - `FIN-05`: Valor unitario / tasa con alta precisión decimal (`DECIMAL(15,4)` para tarifas base, alícuotas y consumos).
  - `FIN-06`: Redondeo `ROUND_HALF_UP` verificado en MySQL 8.4 sobre valores positivos.
  - `FIN-07`: Redondeo `ROUND_HALF_UP` en límites exactos `.005` con BCMath en PHP (`1.005->1.01`, `10.005->10.01`, `2.675->2.68`).
  - `FIN-08`: Demostración de precisión intermedia requerida sin redondeo prematuro acumulativo (evita descuadres de céntimos).
  - `FIN-09`: Backend como única autoridad centralizada de cálculo financiero.
  - `FIN-10`: Frontend estimativo y rechazo categórico de importes o totales ciegos enviados por el cliente.
  - `FIN-11`: Almacenamiento explícito de código de moneda ISO 4217 (`moneda_codigo`) por cada importe persistido.
  - `FIN-12`: Tarifa histórica inmutable almacenada en snapshot de la operación emitida.
  - `FIN-13`: Tasa de impuesto histórica inmutable congelada en snapshot de la operación emitida.
  - `FIN-14`: Modificación de tarifa maestra de catálogo no altera transacciones u operaciones pasadas.
  - `FIN-15`: Modificación de tasa impositiva legal (ej. variaciones de IGV) no altera facturación o reservas pasadas.
  - `FIN-16`: Compatibilidad con pagos parciales y saldo pendiente determinista calculable ($\text{Saldo} = \text{Total} - \sum \text{Pagos Válidos}$).
  - `FIN-17`: Principio de anulación/reverso contable sin `DELETE` físico (`ANULACIÓN ≠ DELETE`).
  - `FIN-18`: Aritmética exacta verificada en MySQL 8.4 con tipos `DECIMAL(15,2)` y escala de multiplicación $D1+D2$.
  - `FIN-19`: Aritmética exacta verificada en PHP 8.3 mediante la extensión `BCMath` y cadenas numéricas.
  - `FIN-20`: Cierre formal e inequívoco de la decisión P-005 antes de abordar la fase `RESERVAS-1` (20/20 PASS).
- **Suite Específica de Verificación RESERVAS-1A (RES-FISC, RES-HOLD, RES-D061 — RESERVAS-1A):** 12 verificaciones de dominio, aritmética BCMath, ausencia de defaults inventados y resolución de actor canónico:
  - `RES-FISC-01`: Ausencia de fuente tributaria formal aplica rigurosamente `impuesto_total = 0.00` y `total = subtotal`.
  - `RES-FISC-02`: No existe 18% hardcodeado ni asumido como regla universal en unidad ni cabecera.
  - `RES-FISC-03`: Snapshot monetario inmutable con moneda canónica `PEN` (`DECIMAL(15,2)`).
  - `RES-FISC-04`: Aritmética de redondeo comercial `ROUND_HALF_UP` en strings con BCMath puro (ej. `1.005` -> `1.01`, cero IEEE 754 float precision loss).
  - `RES-HOLD-01`: Ausencia de default 30 inventado en catálogo (`valor = NULL`, `valor_predeterminado = NULL` en `configuraciones`).
  - `RES-HOLD-02`: Creación de reserva `PENDIENTE` sin parámetro explícito es rechazada limpiamente con `ConfiguracionFaltanteExcepcion` (HTTP 422, cero fallback silencioso).
  - `RES-HOLD-03`: Parámetro explícito válido (ej. 45 min) calcula `expira_en` con exactitud matemática como instante técnico absoluto.
  - `RES-HOLD-04`: Expiración atómica e idempotente: estado `EXPIRADA`, 0 noches residuales en inventario y segunda ejecución reporta 0 expiraciones.
  - `RES-D061-01`: Creación de reserva resuelve canónicamente actor `USR_{id}` vinculado a `usuario_id` (`ACTOR ≠ USUARIO`).
  - `RES-D061-02`: Confirmación registra `confirmado_por_actor_id` resuelto como actor humano.
  - `RES-D061-03`: Cancelación registra `cancelado_por_actor_id` resuelto como actor humano.
  - `RES-D061-04`: Expiración automática del sistema registra actor estructural `CAMARGO_PMS` (actor de sistema D-061).
- **Reconciliación Canónica y Matemática Suite por Suite:**

| Módulo / Fase | Suite de Prueba | Casos Dominio / Integración | Casos HTTP E2E (Apache) | Estado |
|---|---|---|---|---|
| **IDENTIDAD-1** | `test_identidad_suite.php` | 27 | — | 27/27 PASS |
| **PERSONAL-1 / 1A** | `test_reconstruccion_personal.php`, `test_ddl_003.php` | 26 | — | 26/26 PASS |
| **AUTH-1 / 1A** | `test_parity_auth1.php`, `test_pw_spaces.php` + invariantes | 60 | 6 (`NAV-1`) | 66/66 PASS |
| **ROLES-1 / 2 / 2A** | `test_roles_matriz_40.php`, `test_rol_hist_01.php` | 48 (40 + 7 + 1) | 12 (`E2E-ROL2`) | 60/60 PASS |
| **MENÚ-1 / 1A** | `test_regresion_menu1a.php`, `test_delete_integrity.php`, Pristine | 57 (40 + 12 + 5) | 10 (`E2E-MENU`) | 67/67 PASS |
| **AUDITORÍA-1 / 1A** | `test_auditoria_completo.php`, `test_sanitizador_14_variantes.php` | 102 (40 + 62) | 8 (`E2E-AUD`) | 110/110 PASS |
| **USUARIOS-1 / 1A** | `test_usuarios_matriz_40.php`, `test_actor_suite.php` | 46 (40 + 6) | 10 (`E2E-USR`) | 56/56 PASS |
| **CONFIGURACIÓN-1** | `test_configuracion_matriz_40.php` | 40 | 12 (`E2E-CFG`) | 52/52 PASS |
| **PROPIEDADES-1** | `test_propiedades_matriz_40.php`, `test_prop_hist_01.php` | 41 (40 + 1) | 12 (`E2E-PROP`) | 53/53 PASS |
| **UNIDADES-1** | `test_unidades_matriz_40.php`, `test_uni_hist_01.php` | 41 (40 + 1) | 12 (`E2E-UNI`) | 53/53 PASS |
| **GATE OPERATIVO-1**| `harness_concurrencia_p006.php` (CONC-01..05) | 5 | — | 5/5 PASS |
| **DISPONIBILIDAD-1**| `test_disponibilidad_matriz_40.php` (DISP-01..40) | 40 | — | 40/40 PASS |
| **DISPONIBILIDAD-1**| `test_concurrencia_productiva.php` (CONC-PROD-01..05) | 5 | — | 5/5 PASS |
| **DISPONIBILIDAD-1A**| `test_1205_1213.php` (G-1205 y G-1213) | 2 | — | 2/2 PASS |
| **DISPONIBILIDAD-1**| `test_e2e_disponibilidad.php` (E2E-DISP-01..12) | — | 12 (`E2E-DISP`) | 12/12 PASS |
| **GATE FINANCIERO-1**| `test_finanzas_p005.php` (FIN-01..20) | 20 | — | 20/20 PASS |
| **RESERVAS-1** | `test_reservas_matriz_50.php` (RES-01..50) | 50 | — | 50/50 PASS |
| **RESERVAS-1** | `test_reservas_concurrencia.php` (RES-CONC-01..05, EXP-01) | 6 | — | 6/6 PASS |
| **RESERVAS-1** | `test_e2e_reservas.php` (E2E-RES-01..15) | — | 15 (`E2E-RES`) | 15/15 PASS |
| **RESERVAS-1A** | `test_reservas_1a_fisc_hold_d061.php` (FISC, HOLD, D061) | 12 | — | 12/12 PASS |
| **UI-2** | `test_ui2_matriz_25.php` (UI2-01..25) | 25 | — | 25/25 PASS |
| **UI-2A** | `test_ui2a_matriz_20.php` (UI2A-01..20) | 20 | — | 20/20 PASS |
| **ESTADÍAS-1** | `test_estadias_matriz_40.php` (EST-01..40) | 40 | — | 40/40 PASS |
| **ESTADÍAS-1** | `test_estadias_concurrencia.php` (EST-CONC-01..06) | 6 | — | 6/6 PASS |
| **ESTADÍAS-1** | `test_e2e_estadias.php` (E2E-EST-01..15) | — | 15 (`E2E-EST`) | 15/15 PASS |
| **SERVICIOS-1** | `test_servicios_matriz_40.php` (SERV-01..40) | 40 | — | 40/40 PASS |
| **SERVICIOS-1** | `test_servicios_concurrencia.php` (SERV-C01..C06) | 6 | — | 6/6 PASS |
| **SERVICIOS-1** | `test_e2e_servicios.php` (E2E-SERV-01..15) | — | 15 (`E2E-SERV`) | 15/15 PASS |
| **FINANCIERO-2** | `test_financiero_matriz_40.php` (FIN2-01..40) | 40 | — | 40/40 PASS |
| **FINANCIERO-2** | `test_financiero_concurrencia.php` (FIN2-C01..C06) | 6 | — | 6/6 PASS |
| **FINANCIERO-2** | `test_e2e_financiero.php` (E2E-FIN2-01..15) | — | 15 (`E2E-FIN2`) | 15/15 PASS |
| **UI-3** | `test_ui3_matriz_25.php` (UI3-01..25) | 25 | — | 25/25 PASS |
| **TOTALES CANÓNICOS**| **31 suites ejecutadas** | **836** | **154** | **990 casos PASS (100%)** |

  - **Matriz de Regresión de Ciclo Activo (Verificación Multi-Fase):**
    - UI-2 (25) + UI-2A (20) + UI-3 (25) = 70 casos
    - Disponibilidad Matriz (40) + E2E Disponibilidad (12) = 52 casos
    - Reservas Matriz (50) + Concurrencia Reservas (6) + E2E Reservas (15) + RES-1A (12) = 83 casos
    - Estadías Matriz (40) + Concurrencia Estadías (6) + E2E Estadías (15) = 61 casos
    - Servicios Matriz (40) + Concurrencia Servicios (6) + E2E Servicios (15) = 61 casos
    - Financiero-2 Matriz (40) + Concurrencia Financiero-2 (6) + E2E Financiero-2 (15) = 61 casos
    - **Total Consolidado de Regresión Activa: 388/388 PASS (100%)**.

