# Seguridad

## Principios

- Denegar por defecto y otorgar el mínimo privilegio.
- Validar toda entrada en servidor aunque exista validación cliente.
- Separar autenticación, autorización y visibilidad de interfaz.
- Proteger secretos fuera del repositorio y rotarlos.
- Registrar eventos relevantes sin exponer datos sensibles.

## Sesiones y autenticación

- Cookies `Secure`, `HttpOnly` y `SameSite` apropiado en HTTPS.
- Regenerar el identificador al iniciar sesión o cambiar privilegios.
- Expiración por inactividad y duración máxima configurables.
- Contraseñas con las funciones modernas de hash de PHP; nunca cifrado reversible ni hash propio.
- Login, logout, fallos, bloqueos y recuperación quedan auditados.
- La recuperación no revela si una cuenta existe.

## Autorización

Cada ruta y caso de uso comprueba permiso en backend. El filtrado de menús es solo presentación. Las consultas deben respetar el alcance permitido del actor; conocer un ID no concede acceso.

Los permisos se modelarán como capacidades estables. Roles agrupan capacidades, pero el código crítico comprueba la capacidad requerida.

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
