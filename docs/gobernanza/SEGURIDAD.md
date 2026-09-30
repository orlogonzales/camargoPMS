# Seguridad

## Principios

- Denegar por defecto y otorgar el mínimo privilegio.
- Validar toda entrada en servidor aunque exista validación cliente.
- Separar autenticación, autorización y visibilidad de interfaz.
- Proteger secretos fuera del repositorio y rotarlos.
- Registrar eventos relevantes sin exponer datos sensibles.

## Sesiones y autenticación (AUTH-1)

- Cookies de sesión configuradas con `HttpOnly = true`, `SameSite = Lax` (o `Strict`), y `Secure = true` en HTTPS.
- Mitigación contra fijación de sesión: `session_regenerate_id(true)` obligatorio tras autenticación exitosa.
- Expiración dual estricta: inactividad máxima de 30 minutos y duración absoluta de 12 horas desde la creación de la sesión.
- Tokens opacos de sesión: cadenas de 64 caracteres hex (32 bytes CSPRNG); la base de datos almacena exclusivamente el hash SHA-256 (`token_hash`), impidiendo el uso de sesiones si la base de datos es vulnerada.
- Revocación forzada y concurrente de sesiones activas ante cambio de contraseña o desactivación de la cuenta/persona.
- Contraseñas con `PASSWORD_DEFAULT` y migración automática transparente vía `password_needs_rehash($hash, PASSWORD_DEFAULT)`. Longitud mínima de 12 caracteres y máxima de 1024 caracteres evaluada antes del hash. Sin reglas artificiales de composición ni transformaciones destructivas (`trim`, truncamiento), permitiendo espacios y caracteres Unicode. Almacenamiento seguro extensible en `contrasena_hash VARCHAR(255) NOT NULL`. Prohibición estricta de contraseñas en texto claro.
- Mitigación contra ataques de temporización (timing attacks) y enumeración de usuarios: verificación con hash dummy precalculado dinámicamente con `PASSWORD_DEFAULT` ante usuarios inexistentes y mensajes genéricos uniformes (`'Credenciales de acceso inválidas.'`).
- Mitigación contra redirección abierta (Open Redirect): sanitización estricta de rutas de retorno (`return`) forzando esquemas relativos locales.
- Rate limiting y defensa contra fuerza bruta: registro de intentos en `intentos_autenticacion`; bloqueo temporal tras 5 fallos en 15 minutos sin alterar el estado permanente del usuario (`usuarios.estado`).
- Bootstrap CLI de uso único estricto: utilidad administrativa inicial (`bin/crear-usuario-inicial.php`) restringida a CLI (`PHP_SAPI === 'cli'`), que rechaza categóricamente su ejecución si ya existe al menos un usuario registrado en el sistema (`usuarios >= 1`) sin banderas de bypass ni excepciones (`--forzar` eliminado). Sin exposición de credenciales en consola o logs.

## Autorización (ROLES-1)

### Regla Vinculante: OCULTAR EL MENÚ NO ES AUTORIZACIÓN

El filtrado visual u ocultamiento de opciones en la interfaz es un control exclusivo de ergonomía y presentación; **no constituye bajo ninguna circunstancia un control de seguridad**.

1. **Validación obligatoria en servidor:** Cada endpoint, ruta HTTP y caso de uso verifica la capacidad del actor en backend mediante `AutorizacionIntermediario` y `AutorizacionServicio::puede()` o `exigirPermiso()`.
2. **Respuesta ante violación de acceso:** Si un usuario autenticado sin privilegios intenta acceder a una ruta protegida (directamente por URL o mediante fetch), el sistema emite invariablemente una respuesta **HTTP 403 Forbidden** controlada utilizando la plantilla de error aislada de Alina (`Vistas/errores/error.php`), sin filtrar datos de negocio, consultas SQL ni trazas. Usuarios sin sesión son redirigidos a `/login` con parámetro de retorno sanitizado.
3. **Principio MENÚ ≠ PERMISO:** Una opción de menú define su visibilidad en el cliente en función de un permiso requerido; la autorización real valida el permiso atómico en el intermediario del enrutador o servicio de dominio.
4. **Permisos atómicos aditivos:** Los permisos se modelan exclusivamente como capacidades atómicas con formato `recurso.accion` (ej. `usuarios.ver`, `roles.crear`). Para usuarios ordinarios, los permisos efectivos corresponden a la unión de todos los permisos de sus roles activos. No existen sobreescrituras directas ni denegaciones negativas.
5. **Protección estructural de SUPERADMINISTRADOR:** El rol `SUPERADMINISTRADOR` confiere autoridad total e incondicional centralizada en `AutorizacionServicio`. El rol está protegido contra modificación de su código, mutación o desactivación (`RolProtegidoExcepcion`). En la administración matricial de permisos (ROLES-2), se prohíbe además la revocación de cualquiera de sus permisos críticos de gobernanza (`roles.ver`, `roles.editar`, `permisos.ver`, `usuarios.ver`, `usuarios.editar`).
6. **Invariante del Último Superadministrador Activo:** Se prohíbe categóricamente revocar el rol, bloquear la cuenta de usuario o desactivar la persona si es el último Superadministrador activo del sistema (`UltimoSuperadministradorExcepcion`). Dicho control opera con bloqueo pesimista de filas (`FOR UPDATE`) bajo transacción para evitar condiciones de carrera concurrentes.
7. **Autorización en Tiempo Real:** Las mutaciones en la asignación matricial de permisos de un rol impactan inmediatamente en tiempo real en los métodos de autorización del backend sin requerir re-login de los usuarios.
8. **Preservación Histórica de Roles (`ROL ≠ REGISTRO DESECHABLE` — ROLES-2A):** Los roles de autorización nunca se eliminan físicamente en la operación ordinaria. Su ciclo de vida se gestiona exclusivamente mediante `ACTIVO` / `INACTIVO`, garantizando la inmutabilidad de la trazabilidad en auditoría y la conservación de registros históricos de asignación (`usuarios_roles`, `roles_permisos`). Se eliminaron todas las capacidades de DELETE físico en UI, HTTP, Controlador y Servicio.

Los permisos se modelan como capacidades atómicas (`recurso.accion`). Los roles agrupan capacidades, pero la lógica de negocio y las capas intermediarias comprueban siempre el permiso granular exigido.

## Aplicación web

- Token CSRF en toda mutación basada en sesión.
- Escape contextual de HTML, atributos, URL y JavaScript.
- Consultas preparadas PDO sin concatenar entrada.
- Validación de método HTTP, tipo de contenido, tamaño y esquema.
- Cabeceras de seguridad y CSP se definirán antes del despliegue.
- Mensajes de producción no incluyen trazas, SQL ni rutas internas.

## Archivos

Validar extensión, MIME real, tamaño y contenido; generar nombre interno; guardar fuera de `public/`; servir mediante autorización. Imágenes y documentos potencialmente activos requieren procesamiento seguro. Nunca ejecutar contenido subido.

## API e integraciones

- Clientes técnicos con credenciales revocables, rotables y de alcance limitado.
- TLS obligatorio fuera del entorno local.
- Límites de uso, correlación, registro seguro y expiración de credenciales.
- Webhooks: firma/autenticidad, marca temporal cuando exista, idempotencia y conservación del evento mínimo necesario.
- Sandbox y producción usan secretos y endpoints separados.

## Datos y logs

Clasificar datos personales, financieros, contractuales y credenciales. Minimizar recopilación y acceso. Los logs no contienen contraseñas, tokens completos, números de tarjeta, documentos completos ni cuerpos sensibles. Las exportaciones y respaldos reciben protección equivalente a producción.

## Menús Alina y Navegación Dinámica (MENÚ-1)

El servidor filtra las áreas y opciones visibles según permisos RBAC y el estado de sus hijos. Las claves de `data-target` e `id` son identificadores técnicos estables en minúsculas. URL, icono y orden se validan rigurosamente antes de persistir y renderizar:
1. **Sanitización de rutas:** Las rutas configuradas deben ser rutas relativas locales comenzando con `/`. Se rechazan esquemas maliciosos (`javascript:`, `data:`, `vbscript:`) y URLs absolutas externas (`http:`, `https:`, `//`) mediante `RutaInvalidaExcepcion`.
2. **Protección estructural (`es_sistema = 1`):** Opciones críticas para el control del sistema (como `config_menu`) están blindadas a nivel de servicio y no pueden ser eliminadas ni desactivadas (`OpcionMenuProtegidaExcepcion`), garantizando la preservación del acceso administrativo.
3. **Protección CSRF estricta:** Todas las operaciones de mutación de menú (`POST /configuracion/menu`, `PUT /configuracion/menu/{id}`, `PATCH .../estado`, `PUT .../orden`, `DELETE .../{id}`) exigen validación de token CSRF mediante payload o cabecera `X-CSRF-TOKEN`.
4. **Reordenamiento atómico transaccional:** La actualización del orden se ejecuta bajo transacciones con validación previa de jerarquía, rechazando mezclas de padres o de niveles para prevenir estados inconsistentes en la navegación.
5. **Regla vinculante:** Una opción oculta o deshabilitada jamás sustituye el control de autorización de su endpoint en backend.

## Auditoría y Trazabilidad Transversal (AUDITORÍA-1)

1. **Inmutabilidad Absoluta del Registro:** La tabla `auditoria` no permite actualizaciones ni eliminaciones. El repositorio `AuditoriaRepositorio` es de solo anexado (`insertar()`, `buscarPorId()`, `listar()`, `contar()`). La clave foránea con `actores` aplica `ON DELETE RESTRICT`.
2. **Purga Recursiva de Secretos:** `SanitizadorAuditoria` desinfecta todo payload antes de persistirlo. Queda terminantemente prohibido almacenar contraseñas planas, hashes criptográficos, tokens CSRF, cabeceras de autorización o tokens opacos en campos de auditoría.
3. **Principio ACTOR ≠ USUARIO:** Desacopla la identidad del ejecutor técnico del usuario humano, impidiendo la falsificación de credenciales humanas para tareas del sistema o webhooks.
4. **Trazabilidad y Correlación Segura:** Registro de `correlacion_id`, método HTTP, ruta, IP y User-Agent para detección temprana de anomalías y auditoría forense sin comprometer la privacidad.

## Administración Integral de Usuarios (USUARIOS-1)

1. **Principio PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL:** Se prohíbe crear usuarios no vinculados a una Persona humana real. Cero cuentas genéricas o sintéticas para procesos automáticos.
2. **Prohibición Estricta de Eliminación Física (Zero Physical Delete):** No existe `DELETE` de usuarios en tiempo de ejecución. La desactivación o suspensión se opera exclusivamente mediante transiciones de estado (`ACTIVO`, `INACTIVO`, `BLOQUEADO`).
3. **Invariante Concurrente del Último Superadministrador Activo:** Imposibilidad matemática y transaccional de dejar el sistema sin al menos un superadministrador activo con persona activa, verificado bajo transacción con bloqueo pesimista (`FOR UPDATE`).
4. **Restablecimiento Seguro de Contraseñas:** Algoritmo estándar `PASSWORD_DEFAULT`, longitud de 12 a 1024 caracteres evaluada antes del hash, soporte íntegro de espacios y Unicode. Revocación concurrente obligatoria de todas las demás sesiones activas en `sesiones_usuario`.
5. **Protección CSRF y Verificación de Permisos:** Todas las rutas administrativas bajo `/usuarios` exigen autorización RBAC atómica (`usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.bloquear`, `usuarios.clave`, `usuarios.sesiones`) y token CSRF válido en toda mutación por `POST`, `PATCH`, `DELETE`.
6. **Desinfección de Secretos en Auditoría:** Cada mutación de usuario (`CREAR`, `CAMBIAR_ESTADO`, `CAMBIAR_CLAVE`) se audita transversalmente con `AuditoriaServicio`, garantizando cero fugas de contraseñas o hashes gracias a `SanitizadorAuditoria`.

## Seguridad en Integraciones iCalendar RFC 5545 (AIRBNB-ICAL-1B)

1. **Criptografía Autenticada AES-256-GCM Fail-Closed:**
   - Las URLs privadas de importación (que frecuentemente contienen tokens secretos de canales externos) se cifran mediante `AES-256-GCM` con vector de inicialización (IV) de 12 bytes aleatorio por registro y etiqueta de autenticación (tag) de 16 bytes.
   - La clave se administra mediante `ICAL_ENCRYPTION_KEY` vía `.env` (32 bytes binarios codificados en base64 o hex).
   - Principio Fail-Closed: si la clave no está configurada o es inválida, el servicio arroja `ConfiguracionExcepcion` e impide cualquier persistencia en texto plano o descifrado inseguro.

2. **Modelo Híbrido de Tokens de Exportación:**
   - La tabla `conexiones_ical` almacena `token_exportacion_hash` (SHA-256) para búsquedas $O(1)$ sin exponer el token plano en volcados de base de datos.
   - Almacena adicionalmente `token_exportacion_cifrado` (AES-256-GCM) para permitir al personal administrativo autorizado visualizar o copiar la URL completa en el panel.
   - Rotación y revocación atómica: rotar un token invalida de forma inmediata el hash anterior (HTTP 404). Conexiones en estado `REVOCADO` o con `exportacion_habilitada = 0` deniegan el feed de inmediato.

3. **Defensa en Profundidad Anti-SSRF (Server-Side Request Forgery):**
   - El descargador `ClienteHttpIcalSeguro` valida exhaustivamente las URLs antes de cursar tráfico HTTP.
   - Resolución DNS manual previa y validación contra listas completas de rangos no enrutables:
     - IPv4 privadas (RFC 1918): `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`.
     - Loopback: `127.0.0.0/8`, `::1`.
     - Carrier-Grade NAT (CGNAT): `100.64.0.0/10`.
     - Link-Local y Cloud Metadata: `169.254.0.0/16` (en especial `169.254.169.254` para AWS/GCP/Azure/DigitalOcean metadata).
     - IPv6 especiales: Unique Local (`fc00::/7`), Link-Local (`fe80::/10`), Multicast (`ff00::/8`).
     - Mapeos IPv4-mapped IPv6 (`::ffff:127.0.0.1`, `::ffff:169.254.169.254`).
   - Pinning de IP mediante `CURLOPT_RESOLVE`: la IP validada en DNS se fija directamente en cURL para evitar ataques de tiempo de comprobación a tiempo de uso (TOCTOU) y DNS Rebinding.
   - Redirecciones HTTP manuales verificadas: máximo 3 saltos; cada destino es validado nuevamente en esquema, host y resolución IP antes de seguir.
   - Protección contra Denegación de Servicio (DoS): límite estricto de descarga de 2 MB (`CURLOPT_BUFFERSIZE` / buffer cap) y timeout de conexión de 10 segundos.

4. **Filtro Anti-Echo y Privacidad de Datos:**
   - El servicio de exportación suprime los eventos externos provenientes del mismo canal receptor, impidiendo la re-importación infinita de bloqueos propios.
   - Los calendarios exportados jamás filtran nombres de huéspedes, teléfonos, documentos de identidad, tarifas, códigos de reserva o UIDs de canales externos. Todo evento exportado se emite bajo el resumen neutral `SUMMARY: No disponible` y sin propiedad `DESCRIPTION`.

5. **Capa Operativa, Protección de Secretos en Tránsito y Concurrencia (AIRBNB-ICAL-1C / 1C-C1):**
   - **Exclusión de Secretos en Listados:** El endpoint `GET /canales-ical/datos` omite tajantemente los campos sensibles `url_importacion_cifrada`, `token_exportacion_hash`, `token_exportacion_cifrado` y cualquier derivado plano o claves de cifrado.
   - **Defensa en Profundidad de Secretos Revelados (Cache-Control: no-store):** La recuperación del feed de exportación (`POST /canales-ical/{id}/copiar-feed`) requiere autenticación de sesión, permiso `canales.ver` y validación de CSRF. Para evitar la filtración del token en memorias intermedias, cachés de navegador o proxies corporativos (RFC 7234), la respuesta HTTP incluye obligatoriamente las cabeceras `Cache-Control: no-store, no-cache, must-revalidate` y `Pragma: no-cache`.
   - **Rotación Criptográfica con Invalidación Atómica:** Al rotar el token de exportación (`POST /canales-ical/{id}/rotar-token`), se regenera un secreto CSPRNG de 32 bytes y su hash SHA-256 se actualiza de forma atómica en InnoDB. Cualquier petición subsecuente que intente consumir el feed con el token previo es rechazada inmediatamente con HTTP 404 Not Found.
   - **Control Concurrente Distribuido:** Las operaciones manuales de sincronización emplean control de concurrencia y validación de estado activo para prevenir peticiones simultáneas accidentales.

6. **Contrato Soberano de Exclusión Mutua, Recuperación ante Fallos y Prevención de Deadlocks (AIRBNB-ICAL-1D1):**
   - **Centralización en Capa de Dominio:** El bloqueo de concurrencia se traslada del controlador al servicio de dominio `SincronizacionIcalServicio::sincronizarConexion()`, garantizando que toda vía de acceso (UI Alina, CLI manual, scripts de pruebas o tareas programadas de background) pase obligatoriamente por el mismo mecanismo atómico.
   - **Locking Atómico por Conexión (`GET_LOCK` Fail-Fast):** Se adquiere `GET_LOCK('camargo_ical_sync_{id}', 0)` con tiempo de espera cero (`0`). Esto previene el encolamiento indeseado de workers y deadlocks entre procesos concurrentes; si la conexión ya está en proceso, el servicio lanza de inmediato `ConexionIcalEnSincronizacionExcepcion`.
   - **Liberación Incondicional y Recuperación ante Caídas:** La liberación `RELEASE_LOCK` se ejecuta siempre dentro de un bloque `finally`. Adicionalmente, el motor MySQL libera automáticamente cualquier `GET_LOCK` si el proceso PHP cliente o la conexión TCP se terminan abruptamente (crash, OOM o SIGKILL), garantizando que no existan bloqueos de infraestructura huérfanos.
   - **Saneamiento Defensivo de Telemetría (Stale Runs):** Para prevenir que fallos abruptos dejen registros en estado inconsistente en la base de datos, `SincronizacionIcalLogRepositorio::limpiarLogsHuerfanos()` identifica ejecuciones con `finalizado_en IS NULL` e `iniciado_en < NOW() - INTERVAL 120 SECOND` y las normaliza a `ERROR`. Asimismo, `haySincronizacionEnCurso()` ignora registros con antigüedad superior a 120 segundos como salvaguarda fail-safe.
   - **Política de Reintentos:** Cero reintentos de red inmediatos ante fallos o bloqueos; la infraestructura delega la reanudación al siguiente ciclo programado por el scheduler, protegiendo las cuotas y estabilidad de las plataformas externas (Airbnb/Booking).

7. **Seguridad Operativa del Scheduler Desasistido (AIRBNB-ICAL-1D2):**
   - **Superficie de Ataque Reducida (CLI Exclusivo):** La automatización periódica no expone endpoints HTTP públicos ni privados para disparar sincronizaciones (cero "web crons" vulnerables a denegación de servicio o bypass de autenticación). El disparador se confina estrictamente a la consola local del sistema operativo (`PHP_SAPI === 'cli'`).
   - **Inmunidad en Tabla de Procesos:** Ningún secreto sensible (claves simétricas AES, hashes de token, tokens planos ni URLs privadas de importación) se transmite como argumento de línea de comandos. Los listados de procesos del sistema operativo (`ps aux`, `tasklist`, `Get-Process`) permanecen 100% limpios de información confidencial.
   - **Control de Concurrencia de Dos Niveles:** Prevención de acumulación de procesos del scheduler del SO mediante `flock` / `IgnoreNew` a nivel global del disparador, desacoplado y sin sustituir el `GET_LOCK` soberano a nivel de conexión en MySQL.
   - **Principio de Menor Privilegio:** Los wrappers y plantillas establecen que la ejecución debe asignarse al usuario sin privilegios del servidor web (`www-data`, `camargo`), nunca como `root` o `SYSTEM`.

## Revisión obligatoria

Cambios de autenticación, permisos, pagos, webhooks, subida de archivos, contratos, caja o datos personales requieren pruebas negativas y revisión específica de amenazas antes del micro-baseline.
