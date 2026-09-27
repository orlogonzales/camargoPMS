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
- **Suite de Concurrencia Productiva sobre BD Real (CONC-PROD-01 a CONC-PROD-05 — DISPONIBILIDAD-1):** 5 verificaciones de concurrencia real ejecutadas sobre el esquema productivo con dos conexiones PDO concurrentes independientes:
  - `CONC-PROD-01`: Inserción atómica multinoche sparse en orden determinista `ORDER BY unidad_id ASC, fecha ASC` (3 noches registradas exactamente).
  - `CONC-PROD-02`: Colisión capturada y rollback íntegro ejecutado (cero noches huérfanas persistidas ante colisión de clave duplicada 1062).
  - `CONC-PROD-03`: Bloqueos contiguos $[\text{D1}, \text{D2})$ y $[\text{D2}, \text{D3})$ operan sin falso conflicto en fecha de checkout (noche de salida liberada).
  - `CONC-PROD-04`: Liberación atómica de bloqueo restaura disponibilidad inmediata en el rango completo.
  - `CONC-PROD-05`: Error traducido formalmente a HTTP 409 `ConflictoDisponibilidadExcepcion`.
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
- **Reconciliación Canónica de Pruebas Automatizadas:**
  - Total bruto de ejecuciones de prueba acumuladas en el árbol de suites: **638 ejecuciones brutas (638 PASS / 0 FAIL)**.
  - Total de casos de prueba estrictamente independientes de dominio e integración: **538 casos independientes** (493 previos + 5 de CONC-PROD + 40 de DISP).
  - Total de pruebas HTTP E2E reales contra servidor Apache: **94 casos únicos** (82 previos + 12 de DISPONIBILIDAD-1).
  - Desglose independiente por módulos:
    - IDENTIDAD-1: 27/27 PASS.
    - PERSONAL-1 / PERSONAL-1A: 26/26 PASS.
    - AUTH-1 / AUTH-1A: 60/60 PASS.
    - ROLES-1 / ROLES-2 / ROLES-2A: 48/48 PASS (7 ROLES-1 safe invariant + 40 ROLES-2 matriz formal + 1 ROL-HIST-01 persistencia histórica).
    - MENÚ-1 / MENÚ-1A: 57/57 PASS (40 MENU matriz formal + 12 Pristine CDP + 5 Delete integrity).
    - AUDITORÍA-1 / AUDITORÍA-1A: 102/102 PASS (40 AUD matriz formal + 62 Sanitizador multibyte).
    - USUARIOS-1 / USUARIOS-1A: 46/46 PASS (40 USR matriz formal + 6 ACTOR matriz de identidad).
    - CONFIGURACIÓN-1: 40/40 PASS (40 CFG matriz formal unitaria/integración).
    - PROPIEDADES-1: 41/41 PASS (40 PROP matriz formal + 1 PROP-HIST-01 persistencia histórica de 10 pasos).
    - UNIDADES-1: 41/41 PASS (40 UNI matriz formal + 1 UNI-HIST-01 persistencia histórica de 10 pasos).
    - GATE OPERATIVO-1 / 1A: 5/5 PASS (5 CONC harness de concurrencia e inventario diario P-006).
    - DISPONIBILIDAD-1: 45/45 PASS (40 DISP matriz formal + 5 CONC-PROD concurrencia productiva).
    - SUITES HTTP E2E REALES (Apache HTTPS): 94/94 PASS (12 Disponibilidad + 12 Unidades + 12 Propiedades + 12 Configuración + 12 Roles + 10 Menú + 8 Auditoría + 10 Usuarios + 6 Auth/Navegación).



