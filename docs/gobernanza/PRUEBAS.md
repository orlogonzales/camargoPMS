# Pruebas y puertas de calidad

## Capas

- Unitarias: reglas de servicios, cÃ¡lculos, transiciones y utilidades sin infraestructura real.
- IntegraciÃ³n: repositorios, PDO, migraciones, transacciones y adaptadores.
- Funcionales HTTP: rutas, intermediarios, permisos, CSRF, HTML y contratos JSON.
- Frontend: mÃ³dulos JavaScript, interacciÃ³n y errores de consola.
- End-to-end: flujos crÃ­ticos completos cuando la infraestructura exista.
- Visuales: comparaciÃ³n de Alina, responsive y documentos PDF cuando corresponda.

## Casos crÃ­ticos

Disponibilidad concurrente, idempotencia de pagos/webhooks, movimientos de caja, permisos, histÃ³ricos de tarifa, contratos versionados y migraciones requieren pruebas positivas, negativas y de repeticiÃ³n.

## Gate por incremento

Antes de cerrar:

1. anÃ¡lisis estÃ¡tico/lint y formato disponibles;
2. pruebas nuevas del comportamiento modificado;
3. suite relacionada sin fallos;
4. rutas y assets sin 404;
5. consola del navegador sin errores para cambios UI;
6. revisiÃ³n de seguridad cuando aplique;
7. diff sin cambios ajenos;
8. documentaciÃ³n y changelog actualizados.

Una prueba omitida debe informarse con motivo, riesgo y forma de ejecutarla; no se presenta como superada.

## Datos de prueba

Usar datos sintÃ©ticos y reproducibles. No copiar datos productivos, credenciales o documentos reales. Las pruebas de BD deben aislarse y poder reiniciarse sin afectar otros entornos.

## UI-0/UI-1

La primera baseline de interfaz debe comprobar escritorio y mÃ³vil, loader, navegaciÃ³n acoplada, submenÃºs, activo por ruta, sidebar, Simplebar, tema, breadcrumbs, assets globales/especÃ­ficos, accesibilidad bÃ¡sica y ausencia de errores de consola.

## Herramientas

El framework concreto de pruebas PHP/JS y las herramientas de navegador siguen pendientes. Se decidirÃ¡n en infraestructura con compatibilidad PHP 8.3, ejecuciÃ³n local simple y automatizaciÃ³n futura como criterios.

## Protocolo de Seguridad y Gates Formales (MENÃš-1)

- **Regla contra Bloqueos y Metadata Locks:** Toda prueba que manipule esquemas, transacciones o concurrencia debe fijar obligatoriamente al inicio de sesiÃ³n:
  - `SET SESSION innodb_lock_wait_timeout = 2;`
  - `SET SESSION lock_wait_timeout = 3;`
  - Limpieza defensiva en bloque `finally`: toda transacciÃ³n activa debe cerrarse con `rollBack()` y las conexiones PDO deben liberarse (`unset`) antes de ejecutar `DROP DATABASE` para evitar bloqueos por metadata locks.
- **Matriz Formal de MenÃº (MENU-01 a MENU-40):** 40 verificaciones automÃ¡ticas cubriendo jerarquÃ­a de 2 niveles, rechazo de rutas maliciosas, filtro aditivo RBAC, depuraciÃ³n de categorÃ­as principales vacÃ­as, operaciones CRUD, reordenamiento atÃ³mico transaccional, contrato Alina y regresiÃ³n de autenticaciÃ³n/autorizaciÃ³n.
- **Suite E2E HTTP Real contra Apache (E2E-MENU-01 a E2E-MENU-10):** Pruebas end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`), evaluando redirecciones, renderizado de dos niveles, validaciÃ³n de permisos `menu.ver`/`menu.gestionar`, mutaciones con token CSRF y revocaciÃ³n de sesiÃ³n.
- **AuditorÃ­a de Paridad SQL 100%:** VerificaciÃ³n automatizada de paridad total entre la ejecuciÃ³n secuencial de migraciones (`001` a `008`) y la carga del esquema consolidado canÃ³nico `SQL/camargo_pms.sql` sobre bases de datos efÃ­meras aisladas.
- **ValidaciÃ³n Frontend con PristineJS (MENÃš-1A):** VerificaciÃ³n automatizada PRISTINE-01 a PRISTINE-12 mediante protocolo CDP en navegador headless y API backend, comprobando disponibilidad local de la librerÃ­a (v1.1.0), inicializaciÃ³n modular en modales, bloqueo de submit ante campos invÃ¡lidos, saneamiento dinÃ¡mico al alternar niveles, tolerancia a reaberturas y rechazo de bypass en backend (HTTP 422).
- **Contrato de ContraseÃ±as AUTH-1A:** Confirmado formalmente el estÃ¡ndar canÃ³nico `PASSWORD_DEFAULT` en `password_hash()` y `password_verify()` de los servicios productivos de autenticaciÃ³n y usuarios. (La menciÃ³n previa en la auditorÃ­a respecto al uso de ARGON2ID en todo el sistema fue un error descriptivo de reporte; el cÃ³digo productivo preserva `PASSWORD_DEFAULT`).

- **Matriz Formal de AuditorÃ­a y Trazabilidad (AUD-01 a AUD-40):** 40 verificaciones automÃ¡ticas cubriendo creaciÃ³n de actores por tipo (`USUARIO`, `SISTEMA`, `INTEGRACION`, `PROVEEDOR_PAGO`), rechazo de duplicados, acciones de auditorÃ­a (`CREAR`, `EDITAR`, `ELIMINAR`), purga recursiva de secretos (`contrasena`, `contrasena_hash`, `CSRF`, tokens, authorization, api_key), captura de contexto HTTP y correlaciÃ³n, atomicidad transaccional y rollback, e integraciones activas en Login, Logout, Roles, MenÃº y Usuarios (40 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-01 a E2E-08):** 8 validaciones end-to-end con cURL contra Apache real evaluando generaciÃ³n de auditorÃ­a en Login/Logout real, mutaciones de roles, creaciÃ³n de opciones de menÃº vÃ­a JSON, ausencia absoluta de fugas de datos de auditorÃ­a en respuestas HTTP, y preservaciÃ³n inalterada de navegaciÃ³n y autorizaciÃ³n RBAC (8 PASS / 0 FAIL).
- **VerificaciÃ³n de Cero Fugas de Secretos en Base de Datos Real:** Script de inspecciÃ³n profunda sobre la tabla `auditoria` en `camargo_pms` confirmando cero coincidencias de contraseÃ±as, hashes, tokens o secretos en registros generados en tiempo real.
- **AuditorÃ­a de Paridad SQL 100% MigraciÃ³n 009:** Paridad verificada entre la ejecuciÃ³n acumulada de migraciones `001..009` y el esquema canÃ³nico consolidado `SQL/camargo_pms.sql` (20 tablas, 22 claves forÃ¡neas, semillas estructurales idÃ©nticas).
- **Matriz de SanitizaciÃ³n Multibyte AUDITORÃA-1A (14/14 PASS):** VerificaciÃ³n exhaustiva de eliminaciÃ³n y redacciÃ³n recursiva de todas las 14 variantes sensibles (`password`, `contrasena`, `contraseÃ±a`, `password_hash`, `csrf`, `csrf_token`, `authorization`, `cookie`, `session`, `session_id`, `token`, `api_key`, `secret`, `client_secret`), combinaciones de mayÃºsculas/minÃºsculas (`CONTRASEÃ‘A`, `SESSION`), anidamiento profundo y strings JSON embebidos, con preservaciÃ³n estricta de claves legÃ­timas de negocio.

- **Matriz Formal de AdministraciÃ³n de Usuarios (USR-01 a USR-40):** 40 verificaciones automÃ¡ticas cubriendo listado administrativo paginado, bÃºsqueda por `nombre_usuario` y Persona, filtros por estado y rol, listado sin secretos tÃ©cnicos (cero hashes/tokens), ficha de detalle completa de usuario, filtro de personas disponibles (excluyendo inactivas y vinculadas previamente), creaciÃ³n vÃ¡lida vinculada a Persona activa, rechazo de persona inexistente y duplicada (1:1 estricto), rechazo de username duplicado, polÃ­tica de contraseÃ±a estricta (12 a 1024 caracteres, preservaciÃ³n de espacios y caracteres Unicode), sincronizaciÃ³n automÃ¡tica de actor humano (`USR_{id}`), transiciones de estado (`ACTIVO`, `INACTIVO`, `BLOQUEADO`), revocaciÃ³n inmediata de sesiones activas ante desactivaciÃ³n o bloqueo con motivo `CAMBIO_ESTADO_USUARIO`, protecciÃ³n del Ãºltimo Superadministrador activo contra desactivaciÃ³n, bloqueo o revocaciÃ³n de rol, restablecimiento administrativo de contraseÃ±a con revocaciÃ³n de sesiones (`CAMBIO_CONTRASENA`) y purga de secretos en auditorÃ­a, asignaciÃ³n y revocaciÃ³n de roles, listado de sesiones sin exposiciÃ³n de hashes, revocaciÃ³n de sesiones individuales y masivas (`REVOCACION_ADMINISTRATIVA`), y trazabilidad integral auditada con cero fugas de datos sensibles (40 PASS / 0 FAIL).
- **Matriz de Identidad de Actor (ACTOR-01 a ACTOR-06 â€” USUARIOS-1A):** 6 verificaciones automÃ¡ticas comprobando:
  - `ACTOR-01`: Desacople riguroso de IDs (`usuarios.id != actores.id`), persistiendo `actores.id` del ejecutor y `usuarios.id` del usuario afectado.
  - `ACTOR-02`: Orlando (`usuario_id = 1`) registra actor humano `USR_1` (`actores.id = 2`), nunca actor sistema `CAMARGO_PMS` (`actores.id = 1`).
  - `ACTOR-03`: Operaciones sin usuario ejecutor preservan actor `CAMARGO_PMS` (`actores.id = 1`, tipo `SISTEMA`, `usuario_id = NULL`).
  - `ACTOR-04`: Usuario sin actor previo asegura automÃ¡ticamente `USR_{id}` al ejecutar mutaciones sin duplicados.
  - `ACTOR-05`: MÃºltiples operaciones consecutivas del mismo usuario mantienen exactamente 1 actor Ãºnico (cero duplicaciÃ³n de filas en `actores`).
  - `ACTOR-06`: Transaccionalidad y rollback: fallo en la operaciÃ³n revierte atÃ³micamente usuario y auditorÃ­a sin registros huÃ©rfanos.
- **Suite E2E HTTP Real contra Apache (E2E-USR-01 a E2E-USR-10):** 10 validaciones end-to-end con cURL contra Apache real evaluando login real con Superadministrador, acceso a `/usuarios` (HTTP 200 con maquetaciÃ³n Alina, tabla y modales operativos), redirecciÃ³n de acceso anÃ³nimo (HTTP 302 a `/login`), rechazo de usuario ordinario sin permiso `usuarios.ver` (HTTP 403 estricto), endpoint JSON `/usuarios/datos` sin secretos, rechazo de mutaciones sin token CSRF (HTTP 403), creaciÃ³n de cuenta fixture vÃ­a POST (HTTP 201), asignaciÃ³n de rol vÃ­a POST (HTTP 200), cambio de estado a BLOQUEADO vÃ­a POST (HTTP 200), restablecimiento administrativo de clave vÃ­a POST (HTTP 200), y verificaciÃ³n de auditorÃ­a en BD productiva (`CREAR`, `ASIGNAR`, `BLOQUEAR`, `CAMBIAR_CLAVE`) con cero fugas, autorÃ­a humana correcta y cleanup defensivo total (10 PASS / 0 FAIL).
- **Matriz Formal de Roles y Permisos (ROL2-01 a ROL2-40 â€” ROLES-2 / ROLES-2A):** 40 verificaciones automÃ¡ticas cubriendo serializaciÃ³n completa de modelos `Rol` y `Permiso`, creaciÃ³n vÃ¡lida con persistencia, rechazo de creaciÃ³n con clave reservada `SUPERADMINISTRADOR` o duplicada, validaciÃ³n sintÃ¡ctica de claves tÃ©cnicas (3-50 chars, alfanumÃ©rico + guiÃ³n bajo), validaciÃ³n de nombres (2-100 chars), validaciÃ³n de estados (`ACTIVO`/`INACTIVO`), actualizaciÃ³n de metadatos, rechazo de colisiones de claves, transiciones operativas de estado, protecciÃ³n inviolable de `SUPERADMINISTRADOR` (contra renombrado y desactivaciÃ³n), protecciÃ³n de roles de sistema (`es_sistema = 1`), preservaciÃ³n del invariante pesimista del Ãºltimo Superadministrador humano activo, preservaciÃ³n del ciclo de vida de roles personalizados mediante `ACTIVO` / `INACTIVO` (ROL2-16), persistencia incondicional en base de datos sin borrado fÃ­sico (ROL2-17), preservaciÃ³n intacta de asignaciones histÃ³ricas de permisos y usuarios tras desactivaciÃ³n (ROL2-18), erradicaciÃ³n total de mÃ©todos de eliminaciÃ³n fÃ­sica productiva en servicio y controlador (ROL2-19), reactivaciÃ³n operativa sin pÃ©rdida de relaciones (ROL2-20), agregaciones en `listarRolesConConteos()` (`total_usuarios`, `total_permisos`), filtros de bÃºsqueda textual y de estado, consulta de usuarios asignados con detalle de `Persona` vinculada, catÃ¡logo jerÃ¡rquico por mÃ³dulos, sincronizaciÃ³n matricial atÃ³mica transaccional de permisos, desasignaciÃ³n total con matriz vacÃ­a, rechazo de permisos inexistentes, protecciÃ³n de permisos crÃ­ticos de gobernanza de `SUPERADMINISTRADOR`, rollback completo ante fallos transaccionales, auditorÃ­a transversal bajo D-061 con actor humano resuelto (`CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`), eventos `ASIGNAR` y `REVOCAR` con correlaciÃ³n unificada (`correlacion_id`), resoluciÃ³n de `CAMARGO_PMS` para operaciones sin ejecutor, evaluaciÃ³n de permisos en tiempo real sin relogin, supresiÃ³n de permisos ante roles inactivos, acumulaciÃ³n distributiva multimÃ³dulo de permisos y cero fugas de contraseÃ±as o hashes en auditorÃ­a (40 PASS / 0 FAIL).
- **Prueba EspecÃ­fica de Persistencia HistÃ³rica (ROL-HIST-01 â€” ROLES-2A):** 10 verificaciones secuenciales automÃ¡ticas certificando el ciclo de vida completo de un rol: creaciÃ³n, asignaciÃ³n de permisos, vinculaciÃ³n y desvinculaciÃ³n de usuario fixture, desactivaciÃ³n operativa, comprobaciÃ³n estricta de existencia inmutable de la fila en tabla `roles` con su ID y cÃ³digo originales, verificaciÃ³n de bitÃ¡cora de auditorÃ­a append-only sin eventos de borrado fÃ­sico (`ELIMINAR` = 0), reactivaciÃ³n a `ACTIVO` y restauraciÃ³n funcional Ã­ntegra de capacidades y permisos asignados (1 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-ROL2-01 a E2E-ROL2-12 â€” ROLES-2 / ROLES-2A):** 12 validaciones end-to-end con cURL contra el servidor Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando login real con Superadministrador, redirecciÃ³n anÃ³nima de `/configuracion/roles` hacia `/login` (HTTP 302), bloqueo de usuario ordinario sin permiso `roles.ver` (HTTP 403 estricto), renderizado de interfaz Alina con modales operativos (HTTP 200), endpoint JSON de listado con agregaciones `/configuracion/roles/datos` (HTTP 200), catÃ¡logo de permisos por mÃ³dulo `/configuracion/roles/permisos-catalogo` (HTTP 200), bloqueo de mutaciÃ³n sin token CSRF (HTTP 403), creaciÃ³n de rol vÃ­a POST y consulta de detalle GET (HTTP 201/200), actualizaciÃ³n matricial atÃ³mica de permisos vÃ­a POST (HTTP 200), desactivaciÃ³n y reactivaciÃ³n operativa preservando ID histÃ³rico (HTTP 200), verificaciÃ³n de inexistencia absoluta de endpoints de DELETE fÃ­sico (POST/DELETE a rutas de eliminaciÃ³n retornan HTTP 404 y rol persiste intacto en BD), y auditorÃ­a bajo D-061 para eventos de ciclo de vida (`DESACTIVAR` / `ACTIVAR`) con actor humano resuelto del Superadministrador y cleanup defensivo total (12 PASS / 0 FAIL).
- **Matriz Formal de ConfiguraciÃ³n del Sistema (CFG-01 a CFG-40 â€” CONFIGURACIÃ“N-1):** 40 verificaciones automÃ¡ticas cubriendo validaciÃ³n y casting de todos los 7 tipos funcionales (`TEXTO`, `ENTERO`, `DECIMAL`, `BOOLEANO`, `FECHA`, `HORA`, `JSON`), hidrataciÃ³n fiel del modelo `ConfiguracionParametro`, tipado nativo en `obtenerValorTipado()` y `obtenerValorPredeterminadoTipado()`, exportaciÃ³n asociativa completa, operaciones de repositorio (bÃºsqueda por clave, ID, existencia, listado general, agrupado, actualizaciÃ³n y restauraciÃ³n), operaciones de servicio (`obtener()`, valor por defecto, cachÃ© de memoria request-scoped, actualizaciÃ³n individual con validaciÃ³n y excepciones `ConfiguracionNoEncontradaExcepcion` y `ConfiguracionNoEditableExcepcion`, validaciÃ³n estricta de tipos, auditorÃ­a D-061 imputada a `USR_1`, actualizaciÃ³n atÃ³mica en lote con reversiÃ³n completa ante fallos y `correlacion_id` unificado, restauraciÃ³n a valores de fÃ¡brica y ofuscaciÃ³n automÃ¡tica de parÃ¡metros sensibles `***` en auditorÃ­a) (40 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-CFG-01 a E2E-CFG-12 â€” CONFIGURACIÃ“N-1):** 12 validaciones end-to-end con cURL contra el servidor Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando redirecciÃ³n anÃ³nima de `/configuracion/sistema` a `/login` (HTTP 302), bloqueo de usuario ordinario sin permiso `configuracion.ver` (HTTP 403 estricto), acceso Superadmin a interfaz Alina con navegaciÃ³n por pestaÃ±as y CSRF (HTTP 200), endpoint JSON `/configuracion/sistema/datos` con catÃ¡logo agrupado (HTTP 200), rechazo de mutaciones sin token CSRF (HTTP 403), rechazo de mutaciones con usuario sin permiso `configuracion.editar` (HTTP 403), actualizaciÃ³n atÃ³mica en lote vÃ­a POST con Superadmin (HTTP 200), consistencia de persistencia en MySQL y API JSON, rechazo con HTTP 422 ante intento de mutaciÃ³n sobre parÃ¡metro protegido `sistema.version_instalada`, rechazo con HTTP 422 ante valor incompatible con el tipo funcional, restauraciÃ³n vÃ­a POST a valor de fÃ¡brica en BD (HTTP 200), y verificaciÃ³n de auditorÃ­a en base de datos con actor humano `USR_{adminId}` cumpliendo D-061 y cleanup defensivo total (12 PASS / 0 FAIL).
- **Matriz Formal de Propiedades e Inmuebles FÃ­sicos (PROP-01 a PROP-40 â€” PROPIEDADES-1):** 40 verificaciones automÃ¡ticas cubriendo instanciaciÃ³n e hidrataciÃ³n de `Propiedad`, `estaActiva()`, formateo de `obtenerUbicacionCompleta()`, parseo y casting de `obtenerCoordenadas()`, serializaciÃ³n consistente en `aArreglo()`, `haciaArreglo()` y `aArray()`, recreaciÃ³n con `desdeArreglo()`, excepciones de dominio `PropiedadDuplicadaExcepcion` (409) y `PropiedadNoEncontradaExcepcion` (404), principio `PROPIEDAD â‰  UNIDAD` (cero atributos de unidad/habitaciÃ³n), principio `PROPIEDAD â‰  REGISTRO DESECHABLE` (cero mÃ©todos de eliminaciÃ³n), validaciÃ³n sintÃ¡ctica de cÃ³digo (3-30 chars, alfanumÃ©rico), validaciÃ³n de longitud de nombre (mÃ­nimo 3), validaciÃ³n de existencia de paÃ­s en BD, validaciÃ³n de rangos de coordenadas GPS (latitud `[-90, 90]`, longitud `[-180, 180]`), coordenadas opcionales nulas admitidas, sanitizaciÃ³n y normalizaciÃ³n en mayÃºsculas, persistencia e inserciÃ³n en repositorio con nuevo ID, verificaciÃ³n de unicidad con `existeCodigo()`, recuperaciÃ³n por ID y cÃ³digo con JOIN a paÃ­ses, actualizaciÃ³n de campos tÃ©cnicos, alternancia de estado operativo a `INACTIVO`, filtrado por tÃ©rmino de bÃºsqueda con parÃ¡metros unÃ­vocos anti-HY093, filtrado por estado y paÃ­s, conteo exacto de registros en repositorio, registro de evento `CREAR` en auditorÃ­a, resoluciÃ³n de actor humano ejecutor (`USR_1`) bajo D-061, resoluciÃ³n defensiva de `CAMARGO_PMS` sin ejecutor, registro de evento `EDITAR` con diff de atributos, omisiÃ³n de auditorÃ­a redundante cuando no hay cambios, registro de evento `DESACTIVAR` con motivo opcional, rechazo de cÃ³digo duplicado en creaciÃ³n y actualizaciÃ³n colisionante, actualizaciÃ³n exitosa conservando el propio cÃ³digo, y ausencia absoluta de mÃ©todos de eliminaciÃ³n fÃ­sica productiva (40 PASS / 0 FAIL).
- **Prueba EspecÃ­fica de Persistencia HistÃ³rica (PROP-HIST-01 â€” PROPIEDADES-1):** 10 verificaciones secuenciales automÃ¡ticas certificando el ciclo de vida completo de un inmueble: creaciÃ³n de propiedad fÃ­sica fixture, verificaciÃ³n de persistencia y estado inicial `ACTIVO`, actualizaciÃ³n de datos tÃ©cnicos, comprobaciÃ³n de trazabilidad `CREAR` y `EDITAR` en `auditoria`, desactivaciÃ³n operativa a `INACTIVO` con motivo justificado, comprobaciÃ³n estricta de existencia ininterrumpida de la fila en tabla `propiedades` (cero DELETE), comprobaciÃ³n de evento `DESACTIVAR` en auditorÃ­a, recuperaciÃ³n y listado con filtro de inactivas, reactivaciÃ³n a estado `ACTIVO`, y comprobaciÃ³n de evento `ACTIVAR` y garantÃ­a de cero DELETE en arquitectura (10/10 PASS).
- **Suite E2E HTTP Real contra Apache (E2E-PROP-01 a E2E-PROP-12 â€” PROPIEDADES-1):** 12 validaciones end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando:
  - `E2E-PROP-01`: Acceso anÃ³nimo a `/propiedades` redirige a `/login` (HTTP 302).
  - `E2E-PROP-02`: Usuario ordinario sin permiso `propiedades.ver` recibe HTTP 403 Forbidden.
  - `E2E-PROP-03`: Superadmin accede a `/propiedades` (HTTP 200) y la plantilla contiene el contrato de navegaciÃ³n Alina.
  - `E2E-PROP-04`: Endpoint `/propiedades/datos` no autenticado es rechazado con HTTP 302 hacia `/login`.
  - `E2E-PROP-05`: Endpoint `/propiedades/datos` retorna JSON vÃ¡lido con datos y metadatos de paginaciÃ³n.
  - `E2E-PROP-06`: POST `/propiedades` sin token CSRF devuelve HTTP 403 Forbidden.
  - `E2E-PROP-07`: POST `/propiedades` crea nueva propiedad fÃ­sica y retorna HTTP 201 JSON.
  - `E2E-PROP-08`: GET `/propiedades/{id}` retorna detalle completo en JSON (HTTP 200).
  - `E2E-PROP-09`: GET `/propiedades/{id}/perfil` renderiza la ficha tÃ©cnica con principio `PROPIEDAD â‰  UNIDAD` (HTTP 200).
  - `E2E-PROP-10`: PUT `/propiedades/{id}` actualiza informaciÃ³n y coordenadas GPS (HTTP 200 JSON).
  - `E2E-PROP-11`: PATCH `/propiedades/{id}/estado` conmuta ciclo de vida `ACTIVO` â†” `INACTIVO` con HTTP 200 JSON.
  - `E2E-PROP-12`: DELETE `/propiedades/{id}` es una ruta no registrada que responde 404 Not Found (garantÃ­a de no DELETE fÃ­sico).
- **Matriz Formal de Unidades FÃ­sicas y Alojables (UNI-01 a UNI-40 â€” UNIDADES-1):** 40 verificaciones automÃ¡ticas cubriendo:
  - InstanciaciÃ³n e hidrataciÃ³n de `TipoUnidad` y `Unidad`.
  - ComprobaciÃ³n de `estaActiva()`, cÃ¡lculo de ocupaciÃ³n total `capacidadTotal()` (`capacidad_estandar + capacidad_maxima`), validaciÃ³n de nÃºmero de camas, baÃ±os, piso/nivel.
  - SerializaciÃ³n consistente en `aArreglo()`, `haciaArreglo()`, `aArray()` y deserializaciÃ³n fiel con `desdeArreglo()`.
  - Excepciones de dominio `UnidadDuplicadaExcepcion` (409) y `UnidadNoEncontradaExcepcion` (404).
  - Principio `PROPIEDAD â‰  UNIDAD` (propiedad fÃ­sica continente con ID obligatorio `propiedad_id NOT NULL`, cero unidades huÃ©rfanas).
  - Principio `UNIDAD â‰  REGISTRO DESECHABLE` (cero mÃ©todos de eliminaciÃ³n fÃ­sica, ciclo histÃ³rico `ACTIVO â†” INACTIVO`).
  - Principio `UNIDAD â‰  RESERVA / TARIFA / DISPONIBILIDAD` (cero fechas de ocupaciÃ³n, check-in/out, precios, contratos de arrendamiento; P-004, P-005 y P-006 estrictamente abiertas).
  - Unicidad de cÃ³digo acotada por propiedad (`UNIQUE(propiedad_id, codigo)` bajo D-065). CÃ³digos idÃ©nticos en distintas propiedades admitidos; duplicados en la misma propiedad rechazados con 409.
  - Regla de negocio de propiedad inactiva: rechazo de creaciÃ³n de nuevas unidades en propiedades inactivas, pero preservaciÃ³n intacta de unidades histÃ³ricas preexistentes.
  - ValidaciÃ³n de tipos de unidad existentes en BD (`tipos_unidad`).
  - Persistencia, filtros de bÃºsqueda textual, filtros por propiedad, tipo y estado operativo.
  - Registro de eventos en bitÃ¡cora de auditorÃ­a (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) con resoluciÃ³n de actor humano (`USR_1`) bajo D-061 y diff de atributos sin auditorÃ­a redundante (40 PASS / 0 FAIL).
- **Prueba EspecÃ­fica de Persistencia HistÃ³rica (UNI-HIST-01 â€” UNIDADES-1):** 10 verificaciones secuenciales automÃ¡ticas certificando el ciclo de vida completo de una unidad: creaciÃ³n vinculada a propiedad activa, persistencia inicial en estado `ACTIVO`, actualizaciÃ³n de especificaciones fÃ­sicas, trazabilidad `CREAR` y `EDITAR` en `auditoria`, desactivaciÃ³n operativa a `INACTIVO` con motivo, comprobaciÃ³n de persistencia inmutable en tabla `unidades` (cero DELETE), comprobaciÃ³n de evento `DESACTIVAR`, reactivaciÃ³n a `ACTIVO`, comprobaciÃ³n de evento `ACTIVAR`, y garantÃ­a arquitectÃ³nica de cero DELETE en servicios y repositorios (10/10 PASS).
- **Suite E2E HTTP Real contra Apache (E2E-UNI-01 a E2E-UNI-12 â€” UNIDADES-1):** 12 validaciones end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando:
  - `E2E-UNI-01`: Acceso anÃ³nimo a `/unidades` redirige a `/login` (HTTP 302).
  - `E2E-UNI-02`: Usuario ordinario sin permiso `unidades.ver` recibe HTTP 403 Forbidden.
  - `E2E-UNI-03`: Superadmin accede a `/unidades` (HTTP 200) y la plantilla contiene el contrato de navegaciÃ³n Alina.
  - `E2E-UNI-04`: Endpoint `/unidades/datos` no autenticado es redirigido a `/login` (HTTP 302).
  - `E2E-UNI-05`: Endpoint `/unidades/datos` retorna JSON vÃ¡lido con listado, metadatos y conteos (HTTP 200).
  - `E2E-UNI-06`: POST `/unidades` sin token CSRF devuelve HTTP 403 Forbidden.
  - `E2E-UNI-07`: POST `/unidades` crea nueva unidad fÃ­sica alojable vinculada a propiedad y retorna HTTP 201 JSON.
  - `E2E-UNI-08`: GET `/unidades/{id}` retorna detalle completo en JSON (HTTP 200).
  - `E2E-UNI-09`: GET `/unidades/{id}/perfil` renderiza la ficha tÃ©cnica con principio `PROPIEDAD â‰  UNIDAD` (HTTP 200).
  - `E2E-UNI-10`: PUT `/unidades/{id}` actualiza capacidades fÃ­sicas y metadatos (HTTP 200 JSON).
  - `E2E-UNI-11`: PATCH `/unidades/{id}/estado` conmuta ciclo de vida `ACTIVO` â†” `INACTIVO` con HTTP 200 JSON.
  - `E2E-UNI-12`: DELETE `/unidades/{id}` es una ruta no registrada que responde 404 Not Found (garantÃ­a de no DELETE fÃ­sico).
- **Harness TÃ©cnico de Concurrencia de Disponibilidad e Inventario Diario (CONC-01 a CONC-05 â€” GATE OPERATIVO-1 / P-006):** 5 verificaciones automÃ¡ticas de concurrencia real ejecutadas sobre base de datos efÃ­mera aislada (`camargo_pms_concurrencia_p006`) con dos conexiones PDO concurrentes independientes:
  - `CONC-01`: Concurrencia directa / CondiciÃ³n de carrera por la misma noche en una unidad. Dos transacciones concurrentes intentan ocupar la misma fecha; solo la primera confirma exitosamente (`COMMIT`), mientras que la segunda es bloqueada y rechazada por la restricciÃ³n `UNIQUE (unidad_id, fecha)`. Cero sobreventa confirmada (1 PASS / 0 FAIL).
  - `CONC-02`: Atomicidad multinoche y rollback completo. Un intento de reserva multinoche colisiona con una noche previamente ocupada y ejecuta `ROLLBACK` total inmediato de toda la transacciÃ³n; comprobaciÃ³n estricta de cero noches huÃ©rfanas o reservas parcialmente persistidas en base de datos (1 PASS / 0 FAIL).
  - `CONC-03`: Coexistencia de intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$. ComprobaciÃ³n de que la fecha de salida (checkout) de una reserva y la fecha de entrada (check-in) de una reserva subsecuente sobre la misma unidad en la misma fecha calendario coexisten a la perfecciÃ³n sin generar colisiÃ³n espuria (1 PASS / 0 FAIL).
  - `CONC-04`: LiberaciÃ³n atÃ³mica y reocupaciÃ³n inmediata. La cancelaciÃ³n o expiraciÃ³n de una reserva elimina atÃ³micamente sus noches en el inventario diario y permite la reocupaciÃ³n inmediata por otra reserva sin residuos lÃ³gicos (1 PASS / 0 FAIL).
  - `CONC-05`: Timeouts defensivos y verificaciÃ³n de cero locks residuales. La sesiÃ³n impone `innodb_lock_wait_timeout = 2` y confirma la inexistencia de transacciones zombis o bloqueos residuales en `information_schema.innodb_trx` tras la ejecuciÃ³n concurrente (1 PASS / 0 FAIL).
- **Suite de Concurrencia Productiva sobre BD Real (CONC-PROD-01 a CONC-PROD-05 â€” DISPONIBILIDAD-1):** 5 verificaciones de concurrencia real ejecutadas sobre el motor MySQL Community Server 8.4.3 LTS con dos conexiones PDO concurrentes independientes:
  - `CONC-PROD-01`: InserciÃ³n atÃ³mica multinoche sparse en orden determinista `ORDER BY unidad_id ASC, fecha ASC` (3 noches registradas exactamente).
  - `CONC-PROD-02`: ColisiÃ³n capturada y rollback Ã­ntegro ejecutado (cero noches huÃ©rfanas persistidas ante colisiÃ³n de clave duplicada 1062).
  - `CONC-PROD-03`: Bloqueos contiguos $[\text{D1}, \text{D2})$ y $[\text{D2}, \text{D3})$ operan sin falso conflicto en fecha de checkout (noche de salida liberada).
  - `CONC-PROD-04`: LiberaciÃ³n atÃ³mica de bloqueo restaura disponibilidad inmediata en el rango completo.
  - `CONC-PROD-05`: Error traducido formalmente a HTTP 409 `ConflictoDisponibilidadExcepcion`.
- **VerificaciÃ³n Rigurosa de Contratos de Concurrencia 1205 y 1213 (G-1205 y G-1213 â€” DISPONIBILIDAD-1A):** 2 verificaciones automÃ¡ticas directas sobre el motor MySQL 8.4.3:
  - `G-1205`: Error 1205 real (*Lock wait timeout exceeded*) inducido en MySQL 8.4.3 con `innodb_lock_wait_timeout = 1`. Captura de `["HY000", 1205]`, reversiÃ³n transaccional completa (`inTransaction = false`), y traducciÃ³n a `ConflictoDisponibilidadExcepcion` (HTTP 409).
  - `G-1213`: Error 1213 real (*Deadlock found when trying to get lock*) inducido mediante contenciÃ³n circular entre dos procesos concurrentes en InnoDB. DetecciÃ³n automÃ¡tica por el InnoDB Deadlock Detector de MySQL 8.4.3 (`["40001", 1213]`), captura en servicio, rollback atÃ³mico y traducciÃ³n a `ConflictoDisponibilidadExcepcion` (HTTP 409).
- **Matriz Formal de Disponibilidad e Inventario Diario (DISP-01 a DISP-40 â€” DISPONIBILIDAD-1):** 40 verificaciones automÃ¡ticas de dominio e integraciÃ³n cubriendo:
  - Fallback a zona horaria central del PMS (`America/Lima`) y prevalencia de zona horaria por propiedad fÃ­sica.
  - MitigaciÃ³n segura ante identificadores IANA desconocidos o invÃ¡lidos recurriendo de forma segura a `America/Lima`.
  - ValidaciÃ³n de formato de fechas (`Y-m-d`), rechazo de fechas gregorianas irreales (ej. 31 de febrero).
  - Rechazo de estancias de 0 noches y fechas invertidas ($\text{salida} < \text{entrada}$).
  - CÃ¡lculo de noches en cruce de mes (30 ene al 02 feb = 3 noches).
  - Ausencia de filas en inventario sparse indica disponibilidad completa.
  - Bloqueo de 1 noche marca la unidad como no disponible; consultas fuera de fechas mantienen disponibilidad.
  - Solapamientos parciales (inicio, fin, interno y exacto) detectan ocupaciÃ³n.
  - Intervalo semiabierto $[\text{D1}, \text{D2})$ permite checkout y check-in contiguos sin colisiÃ³n.
  - Filtro por propiedad fÃ­sica restringe el catÃ¡logo; unidades inactivas o de propiedades inactivas excluidas comercialmente y rechazan bloqueos.
  - Estados comerciales y motivos tÃ©cnicos descriptivos (mantenimiento vs manual).
  - Filtro `solo_disponibles` excluye unidades ocupadas; validaciones de motivo no vacÃ­o y longitudes.
  - Persistencia de registro maestro `bloqueos_unidad` (estado `ACTIVO`, 4 noches) e inventario sparse (4 noches exactas).
  - LiberaciÃ³n actualiza a `LIBERADO`, registra timestamps y actor liberador, y elimina atÃ³micamente filas en inventario (0 filas restantes).
  - Rechazo de re-liberaciÃ³n sobre bloqueos inactivos.
  - Matriz mensual / rack de ocupaciÃ³n con cÃ¡lculo dinÃ¡mico de dÃ­as del mes (bisiesto 29, ordinario 28).
  - Trazabilidad transversal de auditorÃ­a D-061 (`CREAR` y `LIBERAR`) con resoluciÃ³n de actor humano (`USR_1`).
  - PreservaciÃ³n estricta de P-005: cero columnas de precio, tarifa, moneda o impuestos en tablas.
  - OpciÃ³n de menÃº `disponibilidad_calendario` presente bajo `propiedades` con ruta `/disponibilidad` (40 PASS / 0 FAIL).
- **Suite E2E HTTP Real contra Apache (E2E-DISP-01 a E2E-DISP-12 â€” DISPONIBILIDAD-1):** 12 validaciones end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`) evaluando:
  - `E2E-DISP-01`: Acceso anÃ³nimo a `/disponibilidad` redirige a `/login` (HTTP 302).
  - `E2E-DISP-02`: Usuario ordinario sin permiso `disponibilidad.ver` recibe HTTP 403 Forbidden.
  - `E2E-DISP-03`: Superadmin accede a `/disponibilidad` (HTTP 200) y la plantilla contiene pestaÃ±as operativas y KPIs.
  - `E2E-DISP-04`: Endpoint `/disponibilidad/consultar` no autenticado redirige a `/login` (HTTP 302).
  - `E2E-DISP-05`: Endpoint `/disponibilidad/consultar` retorna 200 OK con estructura completa de inventario.
  - `E2E-DISP-06`: Consulta con fechas invertidas (salida < entrada) responde 422 Unprocessable Entity.
  - `E2E-DISP-07`: POST `/disponibilidad/bloquear` sin token CSRF devuelve HTTP 403 Forbidden.
  - `E2E-DISP-08`: POST `/disponibilidad/bloquear` con datos vÃ¡lidos crea bloqueo y responde 201 Created.
  - `E2E-DISP-09`: POST `/disponibilidad/bloquear` sobre fechas ocupadas responde 409 Conflict (D-067).
  - `E2E-DISP-10`: GET `/disponibilidad/bloqueos` retorna 200 OK con listado paginado y metadatos.
  - `E2E-DISP-11`: GET `/disponibilidad/matriz` retorna 200 OK con estructura de rack mensual y estadÃ­sticas.
  - `E2E-DISP-12`: POST `/disponibilidad/liberar` con CSRF libera bloqueo y restaura disponibilidad en inventario.
- **Matriz Formal de Contrato Monetario y Financiero (FIN-01 a FIN-20 â€” GATE FINANCIERO-1 / P-005):** 20 verificaciones automÃ¡ticas de dominio, aritmÃ©tica exacta y motor SQL:
  - `FIN-01`: PEN canÃ³nico segÃºn ISO 4217 (cÃ³digo oficial de 3 letras alfabÃ©ticas en mayÃºsculas).
  - `FIN-02`: SÃ­mbolo 'S/' desacoplado de la moneda (exclusivo para presentaciÃ³n visual en capa de vistas).
  - `FIN-03`: ProhibiciÃ³n estricta de `FLOAT`/`DOUBLE` y comprobaciÃ³n empÃ­rica de fallas de precisiÃ³n binaria IEEE 754 (`0.1 + 0.2 != 0.3`).
  - `FIN-04`: Importe comercial estÃ¡ndar a 2 decimales (`DECIMAL(15,2)` verificado en MySQL 8.4).
  - `FIN-05`: Valor unitario / tasa con alta precisiÃ³n decimal (`DECIMAL(15,4)` para tarifas base, alÃ­cuotas y consumos).
  - `FIN-06`: Redondeo `ROUND_HALF_UP` verificado en MySQL 8.4 sobre valores positivos.
  - `FIN-07`: Redondeo `ROUND_HALF_UP` en lÃ­mites exactos `.005` con BCMath en PHP (`1.005->1.01`, `10.005->10.01`, `2.675->2.68`).
  - `FIN-08`: DemostraciÃ³n de precisiÃ³n intermedia requerida sin redondeo prematuro acumulativo (evita descuadres de cÃ©ntimos).
  - `FIN-09`: Backend como Ãºnica autoridad centralizada de cÃ¡lculo financiero.
  - `FIN-10`: Frontend estimativo y rechazo categÃ³rico de importes o totales ciegos enviados por el cliente.
  - `FIN-11`: Almacenamiento explÃ­cito de cÃ³digo de moneda ISO 4217 (`moneda_codigo`) por cada importe persistido.
  - `FIN-12`: Tarifa histÃ³rica inmutable almacenada en snapshot de la operaciÃ³n emitida.
  - `FIN-13`: Tasa de impuesto histÃ³rica inmutable congelada en snapshot de la operaciÃ³n emitida.
  - `FIN-14`: ModificaciÃ³n de tarifa maestra de catÃ¡logo no altera transacciones u operaciones pasadas.
  - `FIN-15`: ModificaciÃ³n de tasa impositiva legal (ej. variaciones de IGV) no altera facturaciÃ³n o reservas pasadas.
  - `FIN-16`: Compatibilidad con pagos parciales y saldo pendiente determinista calculable ($\text{Saldo} = \text{Total} - \sum \text{Pagos VÃ¡lidos}$).
  - `FIN-17`: Principio de anulaciÃ³n/reverso contable sin `DELETE` fÃ­sico (`ANULACIÃ“N â‰  DELETE`).
  - `FIN-18`: AritmÃ©tica exacta verificada en MySQL 8.4 con tipos `DECIMAL(15,2)` y escala de multiplicaciÃ³n $D1+D2$.
  - `FIN-19`: AritmÃ©tica exacta verificada en PHP 8.3 mediante la extensiÃ³n `BCMath` y cadenas numÃ©ricas.
  - `FIN-20`: Cierre formal e inequÃ­voco de la decisiÃ³n P-005 antes de abordar la fase `RESERVAS-1` (20/20 PASS).
- **Suite EspecÃ­fica de VerificaciÃ³n RESERVAS-1A (RES-FISC, RES-HOLD, RES-D061 â€” RESERVAS-1A):** 12 verificaciones de dominio, aritmÃ©tica BCMath, ausencia de defaults inventados y resoluciÃ³n de actor canÃ³nico:
  - `RES-FISC-01`: Ausencia de fuente tributaria formal aplica rigurosamente `impuesto_total = 0.00` y `total = subtotal`.
  - `RES-FISC-02`: No existe 18% hardcodeado ni asumido como regla universal en unidad ni cabecera.
  - `RES-FISC-03`: Snapshot monetario inmutable con moneda canÃ³nica `PEN` (`DECIMAL(15,2)`).
  - `RES-FISC-04`: AritmÃ©tica de redondeo comercial `ROUND_HALF_UP` en strings con BCMath puro (ej. `1.005` -> `1.01`, cero IEEE 754 float precision loss).
  - `RES-HOLD-01`: Ausencia de default 30 inventado en catÃ¡logo (`valor = NULL`, `valor_predeterminado = NULL` en `configuraciones`).
  - `RES-HOLD-02`: CreaciÃ³n de reserva `PENDIENTE` sin parÃ¡metro explÃ­cito es rechazada limpiamente con `ConfiguracionFaltanteExcepcion` (HTTP 422, cero fallback silencioso).
  - `RES-HOLD-03`: ParÃ¡metro explÃ­cito vÃ¡lido (ej. 45 min) calcula `expira_en` con exactitud matemÃ¡tica como instante tÃ©cnico absoluto.
  - `RES-HOLD-04`: ExpiraciÃ³n atÃ³mica e idempotente: estado `EXPIRADA`, 0 noches residuales en inventario y segunda ejecuciÃ³n reporta 0 expiraciones.
  - `RES-D061-01`: CreaciÃ³n de reserva resuelve canÃ³nicamente actor `USR_{id}` vinculado a `usuario_id` (`ACTOR â‰  USUARIO`).
  - `RES-D061-02`: ConfirmaciÃ³n registra `confirmado_por_actor_id` resuelto como actor humano.
  - `RES-D061-03`: CancelaciÃ³n registra `cancelado_por_actor_id` resuelto como actor humano.
  - `RES-D061-04`: ExpiraciÃ³n automÃ¡tica del sistema registra actor estructural `CAMARGO_PMS` (actor de sistema D-061).
- **Matriz Formal de Motor Documental, Plantillas y PDF (MAT-01 a MAT-40 â€” DOCUMENTOS-1 / D-079):** 40 verificaciones automÃ¡ticas de dominio, compilaciÃ³n y seguridad:
  - *Bloque 1: Seguridad, Dompdf y Validador HTML (MAT-01 a MAT-09):* ConfiguraciÃ³n ultra-defensiva de Dompdf (`isRemoteEnabled=false`, `isPhpEnabled=false`, `isJavascriptEnabled=false`), confinamiento `chroot` estricto en almacenamiento local, rechazo categÃ³rico de `<script>`, `<iframe>`, `<object>`, `<embed>`, eventos JS (`onclick`, `onload`), esquemas `javascript:`/`data:`, URLs remotas `http://`/`https://`, y admisiÃ³n de etiquetas estructurales y membretes institucionales locales seguros.
  - *Bloque 2: Shortcodes, Compilador y Determinismo (MAT-10 a MAT-18):* CatÃ¡logo tipado para origen `ARRENDAMIENTO`, validaciÃ³n de shortcodes autorizados, rechazo con `VariableDocumentalDesconocidaExcepcion` (422) ante variables inventadas, rechazo con `VariableDocumentalFaltanteExcepcion` (422) ante datos requeridos vacÃ­os, sanitizaciÃ³n `htmlspecialchars()` de datos interpolados, ordenamiento determinista con `ksort()` en `snapshot_datos_json`, cÃ¡lculo criptogrÃ¡fico de `hash_snapshot_sha256 = hash('sha256', snapshot_html)`, e invariante de determinismo reproducible.
  - *Bloque 3: GeneraciÃ³n PDF, Canonicalidad y Folios (MAT-19 a MAT-27):* GeneraciÃ³n de binario PDF vÃ¡lido (`%PDF-1.`), cÃ¡lculo de `hash_pdf_sha256` sobre bytes fÃ­sicos almacenables, numeraciÃ³n nativa de pÃ¡ginas mediante canvas ("PÃ¡gina X de Y"), marca de agua diagonal con clase CSS `@page` en modo borrador, folio correlativo atÃ³mico `DOC-ARR-YYYYMM-XXXX` bajo `SELECT ... FOR UPDATE`, incremento consecutivo sin colisiones, persistencia fidedigna en `documentos_emitidos`, protecciÃ³n relacional InnoDB mediante clave Ãºnica `uq_dpv_plantilla_activa` sobre columna virtual (Error 1062 ante dos versiones activas simultÃ¡neas), y conmutaciÃ³n atÃ³mica de versiones.
  - *Bloque 4: EmisiÃ³n, Borradores, Descarga y VerificaciÃ³n (MAT-28 a MAT-33):* EmisiÃ³n oficial con generaciÃ³n simultÃ¡nea de fila en BD y archivo fÃ­sico, modo borrador sin consumo de folios ni persistencia en BD, descarga segura con verificaciÃ³n al 100% del hash SHA-256 de los bytes fÃ­sicos en disco, detecciÃ³n de archivos faltantes (`ARCHIVO_FALTANTE`) o alterados (`HASH_NO_COINCIDE`) con `DocumentoCorruptoExcepcion` (HTTP 500), y garantÃ­a estricta de cero regeneraciÃ³n silenciosa en descargas ordinarias.
  - *Bloque 5: Incidencias, RegeneraciÃ³n Asistida y AnulaciÃ³n (MAT-34 a MAT-40):* Registro formal de anomalÃ­as fÃ­sicas en tabla `documento_incidencias` con trazabilidad de actor ejecutor, regeneraciÃ³n asistida exclusiva desde `snapshot_html` inmutable congelado con resoluciÃ³n automÃ¡tica de incidencias previas, anulaciÃ³n documental sin borrado fÃ­sico registrando motivo, actor y fecha, preservaciÃ³n histÃ³rica garantizada ante mutaciones vivas de la base de datos, maquetaciÃ³n HTML de tablas de dotaciÃ³n fÃ­sica, y serializaciÃ³n de excepciones de dominio con cÃ³digos HTTP canÃ³nicos (404, 422, 500).
- **Suite de Concurrencia e Integridad Documental (DOC-C01 a DOC-C06 â€” DOCUMENTOS-1):** 6 verificaciones de resistencia concurrente y defensiva:
  - `DOC-C01`: GeneraciÃ³n atÃ³mica concurrente de folios consecutivos bajo `SELECT ... FOR UPDATE` sin saltos ni duplicados.
  - `DOC-C02`: RestricciÃ³n fÃ­sica relacional InnoDB `uq_dpv_plantilla_activa` sobre columna virtual bloquea versiones activas simultÃ¡neas (Error 1062).
  - `DOC-C03`: DetecciÃ³n transaccional de discrepancia de hash SHA-256 detiene la entrega del documento corrupto.
  - `DOC-C04`: Confinamiento `chroot` estricto en Dompdf sin URLs remotas ni acceso al sistema de archivos del SO.
  - `DOC-C05`: Rechazo estricto de 5 vectores de ataque XSS y HTML malicioso antes de compilar o persistir versiones de plantillas.
  - `DOC-C06`: Inmutabilidad histÃ³rica comprobada: PDF reconstruido utiliza exclusivamente el snapshot HTML congelado y no se contamina por modificaciones en vivo de la BD.
- **Suite E2E HTTP Real contra Apache (E2E-DOC-01 a E2E-DOC-14 â€” DOCUMENTOS-1):** 14 pruebas end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`):
  - `E2E-DOC-01`: RedirecciÃ³n anÃ³nima de `/documentos` hacia `/login` (HTTP 302).
  - `E2E-DOC-02`: AutenticaciÃ³n real de usuario administrador con sesiÃ³n y cookie HTTP.
  - `E2E-DOC-03`: Bloqueo RBAC a usuario autenticado sin permiso `documentos.ver` (HTTP 403 Forbidden).
  - `E2E-DOC-04`: Carga autorizada de `/documentos` con vista Alina, KPIs y pestaÃ±as operativas (HTTP 200).
  - `E2E-DOC-05`: CatÃ¡logo JSON de plantillas con plantilla canÃ³nica activa (HTTP 200).
  - `E2E-DOC-06`: GeneraciÃ³n de borrador de contrato con cabecera `application/pdf` y marca de agua sin consumir folio (HTTP 200).
  - `E2E-DOC-07`: EmisiÃ³n oficial de contrato con asignaciÃ³n atÃ³mica de folio correlativo y hash SHA-256 (HTTP 201).
  - `E2E-DOC-08`: Listado JSON de documentos emitidos reflejando el contrato emitido (HTTP 200).
  - `E2E-DOC-09`: Descarga segura del binario PDF con hash SHA-256 verificado en vivo (HTTP 200).
  - `E2E-DOC-10`: VerificaciÃ³n fÃ­sica del binario contra registro criptogrÃ¡fico en base de datos (HTTP 200).
  - `E2E-DOC-11`: Rechazo con HTTP 422 ante intento de guardar versiÃ³n de plantilla con inyecciones `<script>` o eventos JS.
  - `E2E-DOC-12`: PublicaciÃ³n de versiÃ³n inmutable V2 (HTTP 201) y conmutaciÃ³n atÃ³mica en InnoDB (HTTP 200).
  - `E2E-DOC-13`: AnulaciÃ³n formal del contrato revocando validez legal y preservando histÃ³rico (HTTP 200).
  - `E2E-DOC-14`: DetecciÃ³n en vivo de corrupciÃ³n fÃ­sica de archivo (HTTP 500) y regeneraciÃ³n asistida controlada desde snapshot (HTTP 200).
- **Matriz Formal de Recibos de Cobranza (REC-01 a REC-40 â€” RECIBOS-1 / D-082):** 40 verificaciones automÃ¡ticas cubriendo:
  - *Bloque 1: Pago Exacto, Parcial y Balance Algebraico (T01..T05):* EmisiÃ³n sobre pago exacto, amortizaciÃ³n al 100%, abono parcial con congelamiento de `cargo_saldo_restante`, y verificaciÃ³n de la ecuaciÃ³n contable inviolable $\text{monto\_recaudado} = \text{monto\_imputado} + \text{monto\_no\_aplicado\_pago}$.
  - *Bloque 2: Pago Compuesto MultipropÃ³sito (T06..T10):* GeneraciÃ³n correlativa de lÃ­neas en `recibo_lineas` para mÃºltiples cargos devengados (renta, servicios, suministros), preservaciÃ³n fiel de conceptos y amortizaciÃ³n total con saldo 0.00 en $T_0$.
  - *Bloque 3: Monto No Aplicado y Pagos sin ImputaciÃ³n (T11..T15):* Desglose exacto de excedentes no aplicados (`monto_no_aplicado_pago > 0`), pagos sin imputaciÃ³n previa (0 lÃ­neas en BD y documento formal con leyenda institucional de fondos en custodia).
  - *Bloque 4: DepÃ³sito de GarantÃ­a Segregada y Unicidad (T16..T20):* TipificaciÃ³n fiel de garantÃ­as (`DEPOSITO_GARANTIA`), preservaciÃ³n de no-negatividad en saldos informativos de folio en $T_0$ y bloqueo de segundo recibo activo sobre el mismo pago (`ConflictoReciboExcepcion`).
  - *Bloque 5: Inmutabilidad Temporal en $T_0$ (T21..T25):* Constancia histÃ³rica inmutable: abonos posteriores o reliquidaciones de suministros en $T_1$ no alteran el recibo ni los saldos congelados en $T_0$.
  - *Bloque 6: Snapshot de Titular y PrevenciÃ³n de CorrupciÃ³n (T26..T30):* Snapshot congelado de nombre y DNI del titular sin afectaciÃ³n por mutaciones posteriores en `personas`, bÃºsqueda pesimista por folio y delimitaciÃ³n no tributaria (`REC-YYYYMM-XXXX`).
  - *Bloque 7: AnulaciÃ³n Formal Auditada (T31..T35):* TransiciÃ³n a estado `ANULADO` con motivo, fecha y actor, preservaciÃ³n fÃ­sica absoluta del archivo PDF original en disco (cero sobreescritura), desacople con `FINANCIERO-2` (anular recibo no revierte pago) y rechazo de re-anulaciÃ³n.
  - *Bloque 8: ReversiÃ³n en FINANCIERO-2 y EstadÃ­sticas (T36..T40):* ReversiÃ³n de pago no destruye recibo histÃ³rico, bloqueo de emisiÃ³n sobre pagos reversados o inexistentes, descarga binaria con mime canÃ³nico y KPIs estadÃ­sticos consistentes.
- **Suite de Concurrencia e Integridad Transaccional (REC-C01 a REC-C06 â€” RECIBOS-1):** 6 verificaciones de resistencia concurrente y defensiva:
  - `REC-C01`: GeneraciÃ³n secuencial atÃ³mica y pesimista de folios (`REC-YYYYMM-XXXX`) bajo `FOR UPDATE` sin saltos ni colisiones.
  - `REC-C02`: RestricciÃ³n fÃ­sica relacional InnoDB `uq_rec_pago_activo` sobre columna virtual generada: bloqueo estricto contra doble emisiÃ³n activa simultÃ¡nea.
  - `REC-C03`: Atomicidad integral y rollback transaccional: ante fallo en generaciÃ³n documental, no quedan recibos ni lÃ­neas huÃ©rfanas en BD.
  - `REC-C04`: Ciclo de vida y reemisiÃ³n legÃ­tima tras anulaciÃ³n formal liberando `recibo_activo_idx`.
  - `REC-C05`: Integridad referencial (`ON DELETE RESTRICT`): recibo formal bloquea eliminaciÃ³n destructiva de pagos y folios vinculados.
  - `REC-C06`: Integridad criptogrÃ¡fica SHA-256 en disco y detecciÃ³n automÃ¡tica de discrepancias fÃ­sicas.
- **Suite E2E HTTP Real contra Apache (E2E-REC-01 a E2E-REC-14 â€” RECIBOS-1):** 14 pruebas end-to-end con cURL contra el servidor web real Apache en HTTPS (`https://app.camargo-pms.test/`):
  - `E2E-REC-01`: RedirecciÃ³n anÃ³nima de `/recibos` hacia `/login` (HTTP 302).
  - `E2E-REC-02`: Bloqueo RBAC a usuario autenticado sin permiso `recibos.ver` (HTTP 403 Forbidden).
  - `E2E-REC-03`: Carga autorizada de `/recibos` con vista Alina, 4 KPIs y modales reactivos (HTTP 200).
  - `E2E-REC-04`: Endpoint GET `/api/recibos/catalogos` retorna pagos confirmados candidatos (HTTP 200).
  - `E2E-REC-05`: CatÃ¡logo incluye cuentas folios activas estructuradas (HTTP 200).
  - `E2E-REC-06`: Rechazo CSRF estricto en emisiÃ³n mutacional POST (HTTP 403 Forbidden).
  - `E2E-REC-07`: EmisiÃ³n formal exitosa POST `/api/recibos/emitir` generando folio atÃ³mico y PDF (HTTP 201).
  - `E2E-REC-08`: Bloqueo de segundo recibo activo sobre el mismo pago retornando HTTP 409 Conflicto.
  - `E2E-REC-09`: Endpoint GET `/api/recibos` lista recibos emitidos con paginaciÃ³n y bÃºsqueda (HTTP 200).
  - `E2E-REC-10`: Endpoint GET `/api/recibos/{id}` entrega detalle exhaustivo con lÃ­neas congeladas en $T_0$ (HTTP 200).
  - `E2E-REC-11`: Descarga binaria GET `/api/recibos/{id}/pdf` con cabecera `application/pdf` y `X-Document-SHA256` (HTTP 200).
  - `E2E-REC-12`: Endpoint GET `/api/recibos/{id}/verificar-hash` valida correspondencia criptogrÃ¡fica en disco (HTTP 200).
  - `E2E-REC-13`: AnulaciÃ³n formal POST `/api/recibos/{id}/anular` cambia estado a `ANULADO` sin afectar el pago (HTTP 200).
  - `E2E-REC-14`: Intento de re-anular recibo ya anulado retorna HTTP 409 Conflicto preservando el histÃ³rico.
- **ReconciliaciÃ³n CanÃ³nica y MatemÃ¡tica Suite por Suite:**

| MÃ³dulo / Fase | Suite de Prueba | Casos Dominio / IntegraciÃ³n | Casos HTTP E2E (Apache) | Estado |
|---|---|---|---|---|
| **IDENTIDAD-1** | `test_identidad_suite.php` | 27 | â€” | 27/27 PASS |
| **PERSONAL-1 / 1A** | `test_reconstruccion_personal.php`, `test_ddl_003.php` | 26 | â€” | 26/26 PASS |
| **AUTH-1 / 1A** | `test_parity_auth1.php`, `test_pw_spaces.php` + invariantes | 60 | 6 (`NAV-1`) | 66/66 PASS |
| **ROLES-1 / 2 / 2A** | `test_roles_matriz_40.php`, `test_rol_hist_01.php` | 48 (40 + 7 + 1) | 12 (`E2E-ROL2`) | 60/60 PASS |
| **MENÃš-1 / 1A** | `test_regresion_menu1a.php`, `test_delete_integrity.php`, Pristine | 57 (40 + 12 + 5) | 10 (`E2E-MENU`) | 67/67 PASS |
| **AUDITORÃA-1 / 1A** | `test_auditoria_completo.php`, `test_sanitizador_14_variantes.php` | 102 (40 + 62) | 8 (`E2E-AUD`) | 110/110 PASS |
| **USUARIOS-1 / 1A** | `test_usuarios_matriz_40.php`, `test_actor_suite.php` | 46 (40 + 6) | 10 (`E2E-USR`) | 56/56 PASS |
| **CONFIGURACIÃ“N-1** | `test_configuracion_matriz_40.php` | 40 | 12 (`E2E-CFG`) | 52/52 PASS |
| **PROPIEDADES-1** | `test_propiedades_matriz_40.php`, `test_prop_hist_01.php` | 41 (40 + 1) | 12 (`E2E-PROP`) | 53/53 PASS |
| **UNIDADES-1** | `test_unidades_matriz_40.php`, `test_uni_hist_01.php` | 41 (40 + 1) | 12 (`E2E-UNI`) | 53/53 PASS |
| **GATE OPERATIVO-1**| `harness_concurrencia_p006.php` (CONC-01..05) | 5 | â€” | 5/5 PASS |
| **DISPONIBILIDAD-1**| `test_disponibilidad_matriz_40.php` (DISP-01..40) | 40 | â€” | 40/40 PASS |
| **DISPONIBILIDAD-1**| `test_concurrencia_productiva.php` (CONC-PROD-01..05) | 5 | â€” | 5/5 PASS |
| **DISPONIBILIDAD-1A**| `test_1205_1213.php` (G-1205 y G-1213) | 2 | â€” | 2/2 PASS |
| **DISPONIBILIDAD-1**| `test_e2e_disponibilidad.php` (E2E-DISP-01..12) | â€” | 12 (`E2E-DISP`) | 12/12 PASS |
| **GATE FINANCIERO-1**| `test_finanzas_p005.php` (FIN-01..20) | 20 | â€” | 20/20 PASS |
| **RESERVAS-1** | `test_reservas_matriz_50.php` (RES-01..50) | 50 | â€” | 50/50 PASS |
| **RESERVAS-1** | `test_reservas_concurrencia.php` (RES-CONC-01..05, EXP-01) | 6 | â€” | 6/6 PASS |
| **RESERVAS-1** | `test_e2e_reservas.php` (E2E-RES-01..15) | â€” | 15 (`E2E-RES`) | 15/15 PASS |
| **RESERVAS-1A** | `test_reservas_1a_fisc_hold_d061.php` (FISC, HOLD, D061) | 12 | â€” | 12/12 PASS |
| **UI-2** | `test_ui2_matriz_25.php` (UI2-01..25) | 25 | â€” | 25/25 PASS |
| **UI-2A** | `test_ui2a_matriz_20.php` (UI2A-01..20) | 20 | â€” | 20/20 PASS |
| **ESTADÃAS-1** | `test_estadias_matriz_40.php` (EST-01..40) | 40 | â€” | 40/40 PASS |
| **ESTADÃAS-1** | `test_estadias_concurrencia.php` (EST-CONC-01..06) | 6 | â€” | 6/6 PASS |
| **ESTADÃAS-1** | `test_e2e_estadias.php` (E2E-EST-01..15) | â€” | 15 (`E2E-EST`) | 15/15 PASS |
| **SERVICIOS-1** | `test_servicios_matriz_40.php` (SERV-01..40) | 40 | â€” | 40/40 PASS |
| **SERVICIOS-1** | `test_servicios_concurrencia.php` (SERV-C01..C06) | 6 | â€” | 6/6 PASS |
| **SERVICIOS-1** | `test_e2e_servicios.php` (E2E-SERV-01..15) | â€” | 15 (`E2E-SERV`) | 15/15 PASS |
| **FINANCIERO-2** | `test_financiero_matriz_40.php` (FIN2-01..40) | 40 | â€” | 40/40 PASS |
| **FINANCIERO-2** | `test_financiero_concurrencia.php` (FIN2-C01..C06) | 6 | â€” | 6/6 PASS |
| **FINANCIERO-2** | `test_e2e_financiero.php` (E2E-FIN2-01..15) | â€” | 15 (`E2E-FIN2`) | 15/15 PASS |
| **UI-3** | `test_ui3_matriz_25.php` (UI3-01..25) | 25 | â€” | 25/25 PASS |
| **UI-3A** | `test_ui3a_fidelidad_alina.php` (Fidelidad Alina D-075) | 70 | â€” | 70/70 PASS |
| **ARRENDAMIENTOS-1** | `test_arrendamientos_matriz_40.php` (ARR-01..40) | 40 | â€” | 40/40 PASS |
| **ARRENDAMIENTOS-1** | `test_arrendamientos_concurrencia.php` (ARR-C01..C06) | 6 | â€” | 6/6 PASS |
| **ARRENDAMIENTOS-1** | `test_e2e_arrendamientos.php` (E2E-ARR-01..14) | â€” | 14 (`E2E-ARR`) | 14/14 PASS |
| **MANTENIMIENTO-1** | `test_mantenimiento_matriz_40.php` (MNT-01..40) | 40 | â€” | 40/40 PASS |
| **MANTENIMIENTO-1** | `test_mantenimiento_concurrencia.php` (MNT-C01..C06) | 6 | â€” | 6/6 PASS |
| **MANTENIMIENTO-1** | `test_e2e_mantenimiento.php` (E2E-MNT-01..14) | â€” | 14 (`E2E-MNT`) | 14/14 PASS |
| **INVENTARIO-1**    | `test_inventario_matriz_40.php` (INV-01..40) | 40 | â€” | 40/40 PASS |
| **INVENTARIO-1**    | `test_inventario_concurrencia.php` (INV-C01..C06) | 6 | â€” | 6/6 PASS |
| **INVENTARIO-1**    | `test_e2e_inventario.php` (E2E-INV-01..14) | â€” | 14 (`E2E-INV`) | 14/14 PASS |
| **DOCUMENTOS-1**    | `test_documentos_matriz_40.php` (MAT-01..40) | 40 | â€” | 40/40 PASS |
| **DOCUMENTOS-1**    | `test_documentos_concurrencia.php` (DOC-C01..C06) | 6 | â€” | 6/6 PASS |
| **DOCUMENTOS-1**    | `test_e2e_documentos.php` (E2E-DOC-01..14) | â€” | 14 (`E2E-DOC`) | 14/14 PASS |
| **COMPRAS-1**       | `test_compras_matriz_40.php` (MAT-01..40) | 40 | â€” | 40/40 PASS |
| **COMPRAS-1**       | `test_compras_concurrencia.php` (COMP-C01..C06) | 6 | â€” | 6/6 PASS |
| **COMPRAS-1**       | `test_e2e_compras.php` (E2E-COMP-01..14) | â€” | 14 (`E2E-COMP`) | 14/14 PASS |
| **SUMINISTROS-1**    | `test_suministros_matriz_40.php` (T01..40) | 40 | â€” | 40/40 PASS |
| **SUMINISTROS-1**    | `test_suministros_concurrencia.php` (SUM-C01..C06) | 6 | â€” | 6/6 PASS |
| **SUMINISTROS-1**    | `test_e2e_suministros.php` (E2E-SUM-01..14) | â€” | 14 (`E2E-SUM`) | 14/14 PASS |
| **SUMINISTROS-1**    | `test_suministros_gate_fin01.php` (SUM-FIN-01) | 11 | â€” | 11/11 PASS |
| **RECIBOS-1**       | `test_recibos_matriz_40.php` (T01..40) | 40 | â€” | 40/40 PASS |
| **RECIBOS-1**       | `test_recibos_concurrencia.php` (REC-C01..C06) | 6 | â€” | 6/6 PASS |
| **RECIBOS-1**       | `test_e2e_recibos.php` (E2E-REC-01..14) | â€” | 14 (`E2E-REC`) | 14/14 PASS |
| **HOUSEKEEPING-1**  | `test_housekeeping_matriz_40.php` (T01..40) | 40 | â€” | 40/40 PASS |
| **HOUSEKEEPING-1**  | `test_housekeeping_concurrencia.php` (HK-C01..C06) | 6 | â€” | 6/6 PASS |
| **HOUSEKEEPING-1**  | `test_e2e_housekeeping.php` (E2E-HK-01..14) | â€” | 14 (`E2E-HK`) | 14/14 PASS |
| **AIRBNB-ICAL-1B**  | `test_airbnb_ical.php` (Secciones 1..12) | 155 | â€” | 155/155 PASS |
| **AIRBNB-ICAL-1C**  | `test_airbnb_ical_ui.php` (Secciones 1..12) | 94 | â€” | 94/94 PASS |
| **AIRBNB-ICAL-1D**   | `test_airbnb_ical_scheduler.php` (Secciones 1..15) | 71 | â€” | 71/71 PASS |
| **WORDPRESS-1B**    | `test_wordpress_1b.php` (Secciones 1..5) | 75 | â€” | 75/75 PASS |
| **WORDPRESS-1C**    | `test_wordpress_1c.php` (Secciones 1..10) | 108 | â€” | 108/108 PASS |
| **WORDPRESS-1D**    | `test_wordpress_1d.php` (Secciones 1..7) | 95 | â€” | 95/95 PASS |
| **PAGOS-1B**        | `test_pagos_1b.php` (Secciones 1..7) | 90 | â€” | 90/90 PASS |
| **PAGOS-1C**        | `test_pagos_1c.php` (Secciones 1..8) | 75 | â€” | 75/75 PASS |
| **PAGOS-1D**        | `test_pagos_1d.php` (Secciones 1..11) | 90 | â€” | 90/90 PASS |
| **REPORTES-1A**    | `test_reportes_analitica_1a.php` (Secciones 1..12) | 53 | â€” | 53/53 PASS |
| **REPORTES-1B**    | `test_reportes_analitica_1b.php` (Secciones 1..8) | 71 | â€” | 71/71 PASS |
| **NIGHT-AUDIT-2B**  | `test_night_audit_scheduler.php` (Secciones 1..9) | 46 | â€” | 46/46 PASS |
| **TOTALES CANÓNICOS**| **83 suites ejecutadas** | **—** | **—** | **2,714 checks PASS (100%)** |

  - **Consolidado de RegresiÃ³n Transversal Activa (NIGHT-AUDIT-2B):**
    - Suite `test_night_audit_scheduler.php`: 46 comprobaciones automÃ¡ticas cubriendo:
      - ResoluciÃ³n jerÃ¡rquica de timezone IANA de propiedad (`America/Lima`) y fallback determinista al sistema central.
      - ProhibiciÃ³n categÃ³rica de cierre para la fecha local de hoy en curso o fechas futuras (solo cerrable hasta ayer).
      - CÃ¡lculo de secuencia cronolÃ³gica ascendente estricta de fechas debidas pendientes (catch-up multi-dÃ­a).
      - Concurrencia distribuida vÃ­a advisory locks pesimistas MySQL (`GET_LOCK` y `RELEASE_LOCK` en `finally`), aislando colisiones entre scheduler cron y peticiones web concurrentes.
      - AtribuciÃ³n sistemÃ¡tica al actor estructural del sistema `CAMARGO_PMS` (ID 1) en ejecuciones desatendidas.
      - DetecciÃ³n no invasiva de no-shows potenciales durante el cierre, con CERO mutaciÃ³n de estado en reservas, CERO cancelaciÃ³n automÃ¡tica, y registro de advertencias en observaciones y auditorÃ­a D-061.
      - Ejecutor CLI oficial `bin/ejecutar-night-audit.php`: validaciÃ³n de argumentos, flags (`--solo-debidas`, `--propiedad`, `--todas`, `--fecha`, `--quiet`, `--ayuda`) y cÃ³digos de salida (0 = Ã©xito, 1 = error operacional, 2 = argumentos invÃ¡lidos).
      - HomologaciÃ³n Alina en vista `app/Vistas/operaciones/night_audit.php`: erradicaciÃ³n absoluta de `input[type="date"]`, adopciÃ³n de Flatpickr Datepicker (`data-provider="datepicker"`, `basic-date`) y SweetAlert2 (`Swal.fire`), suprimiendo llamadas nativas a `alert()` y `confirm()`.
      - PreservaciÃ³n estricta de base de datos: 130 tablas relacionales exactas, migraciÃ³n 038 como Ãºltima aplicada, ranura 039 estrictamente libre (CERO DDL).
    - Fix de determinismo de fixture: ajuste de período simulado a `202611` en `tests/test_suministros_concurrencia.php` (D-112.8) para desacoplar el caso SUM-C01 del mes calendario activo.
    - Suites previas sin regresión: 82 suites históricas ejecutadas y validadas al 100%.
    - **Total Consolidado de Regresión Activa: 83/83 suites PASS — 2,714 checks canónicos PASS — 0 fallos (100%)**.
