# Changelog

Los cambios se agrupan por micro-baseline. Este archivo no reemplaza el historial Git.

## Sin publicar

### Hotfix Post-AUTH-1 — Corrección de Renderizado Autenticado Post-Login y Seguridad 500

- **Síntoma:** Al autenticarse exitosamente en `/login` y ser redirigido a la ruta protegida `/`, la aplicación arrojaba una pantalla de error HTTP 500 controlada ("Error del Servidor — Camargo PMS").
- **Causa raíz:** En el componente visual `app/Vistas/componentes/cabecera.php`, la función auxiliar `usuario_autenticado()` devuelve una instancia del modelo de dominio puro `\CamargoPMS\Modelos\Usuario`. El componente intentaba acceder a sus propiedades mediante sintaxis de arreglo (`$usuarioActual['nombre_usuario']`), lo que en PHP 8.3 arrojaba un `TypeError` fatal (`Cannot use object of type CamargoPMS\Modelos\Usuario as array`). En solicitudes no autenticadas dicho bloque condicional no se ejecutaba, razón por la cual las pruebas previas sin renderizado completo de vistas bajo sesión activa no detectaron la falla.
- **Corrección:**
  - `app/Vistas/componentes/cabecera.php`: Reemplazado el acceso por arreglo con la llamada al método de dominio `$usuarioActual->obtenerNombreUsuario()`.
  - `public/index.php`: Establecido `ini_set('display_errors', '0')`, añadido registro seguro de excepciones mediante `error_log((string) $error)` en el bloque `catch (\Throwable $error)` global, y eliminado el volcado de diagnósticos técnicos hacia el navegador para garantizar que las páginas de error 500 nunca filtren rutas internas, trazas ni datos de depuración.
- **Pruebas de regresión agregadas:**
  - Incorporada de forma permanente la prueba `T-AUTH-45` (sub-pruebas A, B y C) en la suite automatizada: valida que un usuario con sesión persistente acceda a `GET /`, el intermediario permita el paso sin redirigir a `/login`, responda con código HTTP 200, y renderice el layout Alina completo mostrando el nombre de usuario, el menú de perfil, el formulario POST de logout y el token CSRF sin arrojar excepciones ni página 500.
  - Validación HTTP real end-to-end con 6 gates contra servidor Apache (`https://app.camargo-pms.test`):
    1. `GET /login` sin sesión → HTTP 200.
    2. `GET /` sin sesión → HTTP 302 Redirige a `/login`.
    3. `GET /` con sesión activa → HTTP 200 Dashboard renderizado con éxito.
    4. `POST /logout` con token CSRF válido → HTTP 302 a `/login` y sesión revocada en BD.
    5. `GET /` tras logout con cookie revocada → HTTP 302 Redirige a `/login`.
    6. `GET /ruta-inexistente` → HTTP 404 Not Found.
  - Suite de regresión ejecutada sobre base de datos aislada temporal (`camargo_pms_test`) con 60/60 pruebas aprobadas (PASS), preservando intacto el usuario real y datos del propietario en `camargo_pms`.

### Micro-lote AUTH-1A — Cierre de Contrato Criptográfico y Bootstrap Inicial

- Implementada la migración evolutiva `SQL/migraciones/006_ajustes_auth_credenciales.sql` (lote 6):
  - Modificación formal de la columna `contrasena_hash` en la tabla `usuarios` a `VARCHAR(255) NOT NULL` con comentario explícito de soporte para `PASSWORD_DEFAULT` y futuros algoritmos.
  - Sincronización de comentarios descriptivos en tablas `usuarios`, `sesiones_usuario` e `intentos_autenticacion`.
- Eliminación de la fijación rígida a `PASSWORD_BCRYPT` y `cost=12` en el dominio y gobernanza:
  - Adopción de `PASSWORD_DEFAULT` estándar en `UsuarioServicio` y `AutenticacionServicio`.
  - Rehash automático y dinámico (`password_needs_rehash()`) con `PASSWORD_DEFAULT` tras autenticaciones exitosas.
  - Mitigación dinámica contra timing attacks y enumeración mediante hash dummy precalculado dinámicamente con `PASSWORD_DEFAULT`.
- Formalización definitiva de la política de contraseñas (D-036):
  - Longitud mínima de 12 caracteres y máxima de 1024 caracteres evaluada estrictamente antes del hash.
  - Ausencia absoluta de transformaciones destructivas (`trim`, `lowercase`, truncamiento); soporte pleno de espacios internos/externos y caracteres Unicode.
- Endurecimiento del contrato de bootstrap inicial en `bin/crear-usuario-inicial.php` (D-046):
  - Eliminación categórica de la bandera `--forzar` y de cualquier mecanismo de bypass de línea de comandos.
  - Regla estricta: `usuarios = 0` permite bootstrap inicial; `usuarios >= 1` rechaza incondicionalmente la ejecución. La creación posterior de usuarios queda reservada exclusivamente a las funciones administrativas del sistema bajo autorización.
- Corrección de decisiones en gobernanza:
  - D-036 actualizada para formalizar `PASSWORD_DEFAULT`, `VARCHAR(255)`, 12/1024 chars y rehash sin asunciones rígidas.
  - D-046 actualizada eliminando toda referencia a `--forzar` y estableciendo el carácter de uso estrictamente único del bootstrap.
- Paridad estructural 100% verificada entre las migraciones 001 a 006 y el consolidado `SQL/camargo_pms.sql` mediante recreación aislada en base de datos temporal `camargo_pms_test_parity_auth1a`.
- Suite automatizada ampliada a 57 pruebas (57 PASS / 0 FAIL), reteniendo las 47 de AUTH-1 e incorporando las pruebas específicas A a L para validación de longitudes (11 rechazada, 12 aceptada, 500 aceptada, 1025 rechazada), preservación de espacios y no trim, compatibilidad `PASSWORD_DEFAULT`, soporte `VARCHAR(255)`, rehash dinámico y rechazo incondicional de `--forzar` en bootstrap con cuentas existentes.
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Fase AUTH-1 — Autenticación, Cuentas Humanas y Sesiones

- Implementada la migración evolutiva `SQL/migraciones/005_auth_usuarios_sesiones.sql` (lote 5):
  - Creación de la tabla `usuarios`: vinculación directa 1:1 con `personas` (`persona_id UNIQUE NOT NULL`), clave foránea `fk_usuarios_persona` con restricción de borrado `ON DELETE RESTRICT`. Prohibición absoluta de relación con `colaboradores` (`colaborador_id`).
  - Nombres de usuario canónicos y normalizados: `nombre_usuario` para presentación y `nombre_usuario_normalizado` con restricción `UNIQUE` para colisión insensible a mayúsculas (D-035).
  - Almacenamiento seguro de credenciales: `contrasena_hash CHAR(60) NOT NULL` con algoritmo `PASSWORD_BCRYPT` (factor de costo 12).
  - Creación de la tabla `sesiones_usuario`: gestión de sesiones con tokens opacos (32 bytes CSPRNG / 64 caracteres hex) persistiendo exclusivamente su hash SHA-256 (`token_hash UNIQUE`) (D-038).
  - Creación de la tabla `intentos_autenticacion`: auditoría de intentos de acceso para control de fuerza bruta y rate limiting por IP e identificador (D-045).
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` conteniendo las 13 tablas del sistema y finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`. Paridad 100% verificada entre migración y consolidado.
- Implementados los modelos de dominio puros: `Usuario` y `SesionUsuario` en `app/Modelos/`.
- Implementadas las excepciones de dominio especializadas en `app/Excepciones/`: `CredencialesInvalidasExcepcion`, `UsuarioDuplicadoExcepcion`, `UsuarioBloqueadoExcepcion`, `SesionInvalidaExcepcion`, `CsrfInvalidoExcepcion` y `DemasiadosIntentosExcepcion`.
- Implementados los repositorios con PDO parametrizado: `UsuarioRepositorio`, `SesionUsuarioRepositorio` e `IntentoAutenticacionRepositorio` en `app/Repositorios/`.
- Implementados los servicios de dominio en `app/Servicios/`:
  - `UsuarioServicio`: creación de usuario para persona activa, validación de contraseñas (10-128 chars), normalización de username, cambio de contraseña con revocación concurrente de sesiones previas (D-040).
  - `AutenticacionServicio`: autenticación segura con timing-attack dummy hash (D-037), migración automática de costo bcrypt con `password_needs_rehash()`, respuestas genéricas indistinguibles (`'Credenciales de acceso inválidas.'`), rate limiting de 5 intentos en 15 minutos sin alterar el estado del usuario.
  - `SesionServicio`: creación de sesiones en base de datos con expiración dual (30 min inactividad, 12 h absoluta), validación con verificación de vigencia de usuario/persona, revocación forzada y destrucción de cookies.
  - `CsrfServicio`: generación de tokens por sesión y verificación de tiempo constante mediante `hash_equals()` (D-041).
- Implementado el pipeline web de autenticación:
  - `AutenticacionIntermediario`: intermediario de protección de rutas privadas, redirección a `/login` con sanitización estricta del parámetro `return` contra Open Redirect (D-044).
  - `AutenticacionControlador`: controlador web siguiendo el patrón PRG (Post-Redirect-Get) para `GET /login`, `POST /login` y `POST /logout`.
  - Integración en `public/index.php`: ruta protegida `GET /` mediante `AutenticacionIntermediario`, soporte transparente del método HTTP `HEAD` en `Enrutador`, y métodos de consulta en `Respuesta`.
  - Ayudantes globales `usuario_autenticado()` y `csrf_campo()` en `app/Nucleo/Funciones.php`.
- Interfaz y vistas:
  - Creada la vista de autenticación `app/Vistas/auth/login.php` adaptada al diseño Alina (`sign-in.html` / `sign-in-2.html`) con campos flotantes, mensajes flash de error y token CSRF.
  - Añadido el asset visual oficial `public/assets/images/login/01.jpg`.
  - Actualizado el componente `cabecera.php` para renderizar el perfil del usuario autenticado y el formulario POST de cierre de sesión seguro con CSRF.
- Script de utilidad administrativa CLI:
  - Desarrollado `bin/crear-usuario-inicial.php` para inicialización idempotente del sistema, con restricción estricta a entornos de terminal (`PHP_SAPI === 'cli'`), creación atómica de persona si no existe, y sin exposición de contraseñas en consola ni logs (D-046).
- Suite integral de pruebas automatizadas:
  - Desarrollada y ejecutada la suite de 47 pruebas exhaustivas (47 PASS / 0 FAIL) cubriendo creación de usuarios, validaciones de dominio, ciclo de vida de sesiones, expiración por inactividad y absoluta, revocación concurrente, timing attacks, mitigación de session fixation, open redirect, cookies seguras, CSRF, rate limiting, restricción CLI y regresión de Identidad y Personal.
  - Verificada la limpieza completa de la base de datos (0 registros residuales en tablas operativas).
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Micro-lote PERSONAL-1A — Cierre de Invariantes Documentales y Temporales

- Implementada la migración evolutiva no destructiva `SQL/migraciones/004_ajustes_identidad_personal.sql` (lote 4):
  - Modelado estructural de jurisdicción en `tipos_documento`: incorporación de `pais_fijo_id` (FK a `paises`) y `pais_emisor_obligatorio`. DNI y Carné de Extranjería configurados con `pais_fijo_id = 1` (Perú) y `pais_emisor_obligatorio = 0`; Pasaporte configurado con `pais_fijo_id = NULL` y `pais_emisor_obligatorio = 1` (D-031).
  - Eliminación definitiva del centinela técnico `COALESCE(pais_emisor_id, 0)` y de la columna virtual `pais_emisor_efectivo` en `personas_documentos`.
  - `personas_documentos.pais_emisor_id` pasa a ser estrictamente `INT UNSIGNED NOT NULL`, respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado con `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)` (`uq_documentos_tipo_pais_numero`).
- Actualizados los modelos de dominio, repositorios y servicios de identidad:
  - `TipoDocumento` incorpora `paisFijoId`, `paisEmisorObligatorio` y métodos semánticos (`tienePaisFijo()`, `requierePaisEmisor()`).
  - `DocumentoPersonaRepositorio` y `PersonaServicio` consultan y validan directamente la jurisdicción real sin centinelas: resolución automática al país fijo cuando se omite, rechazo tajante de países no autorizados en documentos fijos y exigencia obligatoria de país emisor en pasaportes.
- Implementado el blindaje exhaustivo de la invariante temporal de no solapamiento de asignaciones de cargo dentro de un episodio laboral (D-032):
  - Métodos incorporados en `ColaboradorServicio`: `validarSolapamientoAsignacionCargo()` y `asignarCargoAEpisodio()`.
  - Rechazo de solapamiento cronológico entre asignaciones del mismo episodio, cubriendo tanto intervalos abiertos como cerrados (ej. Cargo A 01/01 a 30/06 y Cargo B 01/04 a 31/05 rechazado por `SolapamientoLaboralExcepcion`).
  - Verificación estricta de límites temporales respecto al episodio laboral padre (prohibición de iniciar antes de la contratación o iniciar/finalizar después del cese).
  - Protección de concurrencia mediante transacciones y bloqueos pesimistas (`SELECT ... FOR UPDATE`).
  - Integración de la validación en `cambiarCargo()`, preservando la contigüidad matemática estricta $D-1$ / $D$.
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` con el nuevo esquema limpio de 10 tablas, finalizando con `SET FOREIGN_KEY_CHECKS = 1;`.
- Validada la paridad 100% de columnas e índices entre `SQL/camargo_pms.sql` y la base de datos `camargo_pms` mediante recreación aislada en base de datos temporal `camargo_pms_prueba_p1a`.
- Ampliada la suite automatizada a 26 pruebas (26 PASS / 0 FAIL), reteniendo las 14 pruebas de PERSONAL-1 e incorporando 7 pruebas de invariante documental estructural (T-DOC-A a T-DOC-G) y 5 pruebas de invariante temporal de cargos (T-CARGO-A a T-CARGO-E).
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 cambios).

### Fase PERSONAL-1 — Colaboradores, Cargos, Episodios Laborales y Ajuste Evolutivo de Identidad

- Consolidado el Principio de Separación Integral: `PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL` (D-026).
- Implementado el ajuste evolutivo no destructivo de Identidad mediante la migración `SQL/migraciones/003_personal_colaboradores.sql`:
  - Eliminada la restricción que exigía al menos un apellido (`chk_personas_al_menos_un_apellido`), habilitando soporte para monónimos legales y personas extranjeras.
  - Evolucionada la unicidad documental incorporando la jurisdicción de emisión mediante la columna virtual generada `pais_emisor_efectivo = COALESCE(pais_emisor_id, 0)` y la restricción `UNIQUE (tipo_documento_id, pais_emisor_efectivo, numero_documento)`, resolviendo colisiones entre pasaportes de distintos países con igual número y previniendo duplicados dentro del mismo país emisor (D-030).
- Creada la entidad y tabla `cargos` como catálogo administrable de funciones laborales (`ADMINISTRADOR`, `RECEPCIONISTA`, `RESERVAS`, `LIMPIEZA`, `MANTENIMIENTO`), totalmente desacoplado de permisos de seguridad de software.
- Creada la entidad y tabla `colaboradores` con código interno estable secuencial (`COL-XXXX`), estado (`ACTIVO`, `INACTIVO`) y restricción `UNIQUE (persona_id)` para garantizar la cardinalidad 1:1 lógica estricta (D-027).
- Creada la entidad y tabla `episodios_laborales` para registrar la vinculación laboral continua, con columna virtual generada `uq_colaborador_abierto` y restricción `UNIQUE` para forzar a nivel de base de datos a lo sumo un episodio abierto activo por colaborador (D-028).
- Creada la entidad y tabla `episodios_laborales_cargos` para registrar el historial inmutable de funciones por episodio, con columna virtual generada `uq_episodio_cargo_abierto` y restricción `UNIQUE` para forzar a lo sumo un cargo vigente por episodio.
- Implementada la convención temporal de continuidad en transiciones de cargo: cierre de la asignación previa en $D-1$ y apertura de la nueva asignación en $D$, con prohibición de solapamiento de fechas (D-029).
- Implementados los modelos puros de dominio: `Cargo`, `Colaborador`, `EpisodioLaboral` y `AsignacionCargo` en `app/Modelos/`.
- Implementados los repositorios con PDO parametrizado: `CargoRepositorio`, `ColaboradorRepositorio`, `EpisodioLaboralRepositorio` y `AsignacionCargoRepositorio` en `app/Repositorios/`.
- Implementadas las excepciones de dominio: `ColaboradorDuplicadoExcepcion`, `EpisodioLaboralActivoExcepcion` y `SolapamientoLaboralExcepcion` en `app/Excepciones/`.
- Implementado el servicio de dominio `app/Servicios/ColaboradorServicio.php` con métodos atómicos transaccionales: `crearColaborador`, `reingresarColaborador`, `cambiarCargo`, `cesarColaborador`, `obtenerSituacionActual` y bloqueos pesimistas `SELECT ... FOR UPDATE`.
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` conteniendo las 10 tablas del sistema y finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`.
- Verificada la reconstrucción estructural limpia desde cero en base de datos temporal aislada `camargo_pms_prueba_personal` con 100% de paridad con `camargo_pms`.
- Ejecutada la suite completa de 14 pruebas automatizadas (14 PASS / 0 FAIL), incluyendo monónimos, pasaportes internacionales con igual número, unicidad 1:1 Persona-Colaborador, transiciones de cargo, cese sin borrado físico, reingreso sin duplicar colaborador, rechazo de solapamiento temporal, atomicidad transaccional y regresión completa del núcleo de identidad.
- Verificada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Fase IDENTIDAD-1 — Núcleo de Identidad Humana (Personas Naturales)

- Diseñado e implementado el modelo relacional del maestro de Personas Naturales bajo el principio `PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL` y la separación de entidades normalizadas `Persona`, `DocumentoPersona` y `ContactoPersona`.
- Creados los catálogos relacionales `paises` (ISO-3166-1 alpha-2 y alpha-3 únicos) con semilla estructural de Perú ('PE', 'PER', 'Perú', 'Peruana'), y `tipos_documento` con semillas estructurales para DNI (8 dígitos exactos), Pasaporte y Carné de Extranjería (CE).
- Excluido formalmente el RUC como tipo de documento personal de personas naturales, reservándolo para personas jurídicas / fiscalidad (D-022).
- Creada la migración oficial `SQL/migraciones/002_identidad_personas.sql` y sincronizado el esquema consolidado `SQL/camargo_pms.sql` finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`.
- Implementadas restricciones de integridad a nivel de base de datos: `UNIQUE (tipo_documento_id, numero_documento)` para unicidad documental estricta, y columnas virtuales generadas `uq_persona_principal` y `uq_contacto_tipo_principal` para garantizar que no existan múltiples documentos principales o múltiples contactos principales del mismo tipo activos para una persona.
- Implementado el indicador booleano `es_whatsapp` en contactos telefónicos para evitar la duplicación innecesaria de números físicos.
- Implementada la política de soft delete mediante el campo `estado` (`ACTIVO`, `INACTIVO`) en personas, documentos y contactos, preservando registros históricos y de auditoría sin borrados físicos destructivos.
- Implementadas las entidades de dominio puras en `app/Modelos/` (`Persona`, `DocumentoPersona`, `ContactoPersona`, `Pais`, `TipoDocumento`) totalmente desacopladas de PDO.
- Implementados los repositorios en `app/Repositorios/` (`PersonaRepositorio`, `DocumentoPersonaRepositorio`, `ContactoPersonaRepositorio`, `PaisRepositorio`, `TipoDocumentoRepositorio`) con consultas SQL estrictamente preparadas y parametrizadas.
- Implementadas las excepciones de dominio en `app/Excepciones/` (`ValidacionExcepcion`, `DocumentoDuplicadoExcepcion`, `EntidadNoEncontradaExcepcion`).
- Implementado el servicio de dominio `app/Servicios/PersonaServicio.php` con validación sintáctica, normalización, control de fechas futuras, gestión de documentos/contactos principales y límites transaccionales atómicos (rollback probado ante fallos).
- Verificado el banco completo de pruebas técnicas de dominio y persistencia (27 PASS / 0 FAIL), incluyendo idempotencia de migraciones, validación sintáctica DNI, detección de duplicados, transaccionalidad, extranjeros sin segundo apellido, soft delete y reconstrucción aislada desde cero en base de datos temporal `camargo_pms_prueba_identidad`.
- Verificada la intangibilidad total del catálogo `admin-dashboard/`.

### Fase INFRA-1 — Infraestructura de configuración, base de datos, PDO y migraciones

- Ejecutada auditoría estricta de solo lectura sobre la base de datos local `camargo_pms`, confirmando el motor MySQL Community Server 8.4.3 LTS (GPL), almacenamiento InnoDB, charset `utf8mb4`, collation `utf8mb4_0900_ai_ci`, `sql_mode` estricto y esquema inicial completamente vacío (Clasificación A).
- Resuelto formalmente P-001 registrando la decisión D-018 que oficializa MySQL 8.4.3 LTS como motor de base de datos del proyecto.
- Resuelto formalmente P-002 registrando la decisión D-019 que adopta Composer 2.x para dependencias mínimas (`vlucas/phpdotenv`) y autoloading PSR-4 (`CamargoPMS\` -> `app/`), preservando el enrutador nativo `app/Nucleo/Enrutador.php`.
- Formalizada la decisión D-020 estableciendo el principio de persistencia sincronizada: `SQL/camargo_pms.sql` como esquema consolidado oficial y versionado, y `SQL/migraciones/` para la evolución incremental determinista.
- Creada la migración inicial `SQL/migraciones/001_infraestructura.sql` para la tabla técnica de control `migraciones` (`id`, `migracion`, `lote`, `ejecutado_en`).
- Implementada la plantilla versionada de entorno `.env.example` y la configuración local privada `.env` (debidamente ignorada por Git).
- Implementada la capa centralizada de configuración `app/Nucleo/Configuracion.php` con soporte para variables de entorno seguras.
- Implementada la fábrica centralizada de conexiones `app/Nucleo/BaseDatos.php` con opciones estrictas de PDO, emulación de prepares desactivada y manejo seguro de excepciones sin filtrar credenciales.
- Implementado el runner CLI de migraciones `app/Nucleo/Migrador.php` y el comando de consola `migrar.php` protegido contra accesos web.
- Validadas exitosamente la conexión PDO, la ejecución de la migración inicial y la completa idempotencia en la segunda ejecución (0 migraciones pendientes).
- Validada la reconstrucción completa del esquema desde cero en una base de datos temporal aislada (`camargo_pms_prueba_infra`), eliminada inmediatamente tras la verificación.
- Confirmada la ausencia total de tablas funcionales de negocio y la inmutabilidad íntegra de `admin-dashboard/`.

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
