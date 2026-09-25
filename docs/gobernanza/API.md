# API

## Propósito

La API comparte servicios y reglas con la interfaz web. Será el canal para WordPress, futuras aplicaciones, OTA y otros clientes; no es una segunda aplicación con lógica paralela.

## Versionado y transporte

- Prefijo inicial: `/api/v1/`.
- HTTPS obligatorio fuera del entorno local.
- JSON con UTF-8 y `Content-Type` correcto.
- Recursos plurales en español y métodos HTTP semánticos.
- Cambios incompatibles requieren nueva versión o estrategia de transición documentada.

## Respuestas

Las respuestas deben ser consistentes y distinguibles entre éxito, validación, regla de negocio, autenticación, autorización, ausencia, conflicto y fallo técnico. El formato exacto del sobre JSON se decidirá antes del primer endpoint y quedará probado como contrato.

Los errores de producción incluyen un código estable y, cuando corresponda, identificador de correlación; no incluyen traza, SQL o secretos.

## Autenticación y clientes

Usuarios y clientes técnicos son identidades distintas. WordPress, app móvil y conectores usan credenciales revocables, rotables, con alcance y expiración. Nunca se reutiliza usuario y contraseña de una persona para una integración.

## Idempotencia

Crear pagos, confirmar webhooks o registrar operaciones reintentables exige una clave o identificador idempotente. Repetir el mismo evento no puede duplicar reserva, cobro o movimiento de caja.

## Disponibilidad

Consultar disponibilidad no reserva inventario. La creación o confirmación vuelve a validar dentro de una transacción. El cliente debe manejar conflictos aunque una consulta previa haya sido positiva.

## Webhooks

1. verificar autenticidad y entorno;
2. registrar identificador del evento;
3. detectar duplicados;
4. procesar mediante servicio transaccional;
5. responder de forma compatible con reintentos;
6. auditar resultado sin almacenar secretos o datos innecesarios.

## Evolución

Documentar cada endpoint con entrada, salida, permisos, errores, idempotencia y efectos secundarios. Las pruebas de contrato deben ejecutarse antes de publicar cambios consumidos por terceros.
