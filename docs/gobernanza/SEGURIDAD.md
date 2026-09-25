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
- Contraseñas con `PASSWORD_BCRYPT` (factor de costo 12) y migración automática transparente vía `password_needs_rehash()`. Longitud mínima de 10 caracteres (máximo 128). Prohibición estricta de texto claro en base de datos (`contrasena_hash CHAR(60)`).
- Mitigación contra ataques de temporización (timing attacks) y enumeración de usuarios: hash bcrypt dummy precalculado ante usuarios inexistentes y mensajes genéricos uniformes (`'Credenciales de acceso inválidas.'`).
- Mitigación contra redirección abierta (Open Redirect): sanitización estricta de rutas de retorno (`return`) forzando esquemas relativos locales.
- Rate limiting y defensa contra fuerza bruta: registro de intentos en `intentos_autenticacion`; bloqueo temporal tras 5 fallos en 15 minutos sin alterar el estado permanente del usuario (`usuarios.estado`).
- Bootstrap CLI defensivo: utilidades administrativas iniciales (`bin/crear-usuario-inicial.php`) restringidas a CLI (`PHP_SAPI === 'cli'`), idempotentes y sin exposición de credenciales en consola o logs.

## Autorización

### Regla Vinculante: OCULTAR EL MENÚ NO ES AUTORIZACIÓN

El filtrado visual u ocultamiento de opciones en la interfaz es un control exclusivo de ergonomía y presentación; **no constituye bajo ninguna circunstancia un control de seguridad**.

1. **Validación obligatoria en servidor:** Cada endpoint, ruta HTTP y caso de uso debe verificar la capacidad del actor en backend mediante intermediarios (*middleware*) de autorización.
2. **Respuesta ante violación de acceso:** Si un usuario sin privilegios intenta invocar una ruta directamente (ej. escribiendo la URL manual en el navegador o mediante petición Fetch/API), el sistema emitirá invariablemente una respuesta **HTTP 403 Forbidden real** utilizando la plantilla de error correspondiente.
3. **Principio MENÚ ≠ PERMISO:** Una opción de menú define su visibilidad en el cliente en función de un permiso requerido; la autorización real valida el permiso en el controlador o servicio del backend.
4. **Protección de la cuenta Superadministrador:** El sistema debe incorporar salvaguardas arquitectónicas para impedir que una modificación accidental de roles o permisos despoje a la plataforma de su cuenta administrativa raíz.

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
