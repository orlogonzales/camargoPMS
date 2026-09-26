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
5. **Protección estructural de SUPERADMINISTRADOR:** El rol `SUPERADMINISTRADOR` confiere autoridad total e incondicional centralizada en `AutorizacionServicio`. El rol está protegido contra modificación de su código, desactivación o eliminación física (`RolProtegidoExcepcion`).
6. **Invariante del Último Superadministrador Activo:** Se prohíbe categóricamente revocar el rol, bloquear la cuenta de usuario o desactivar la persona si es el último Superadministrador activo del sistema (`UltimoSuperadministradorExcepcion`). Dicho control opera con bloqueo pesimista de filas (`FOR UPDATE`) bajo transacción para evitar condiciones de carrera concurrentes.

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

## Menús Alina

El servidor filtra las áreas y opciones visibles. Las claves de `data-target` e `id` se generan desde identificadores internos permitidos, no desde etiquetas de base de datos. URL, icono y orden se validan antes de renderizar. Una opción oculta o deshabilitada no sustituye el control de autorización de su endpoint.

## Revisión obligatoria

Cambios de autenticación, permisos, pagos, webhooks, subida de archivos, contratos, caja o datos personales requieren pruebas negativas y revisión específica de amenazas antes del micro-baseline.
