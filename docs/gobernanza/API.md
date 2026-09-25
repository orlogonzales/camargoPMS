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

## Integración Externa con APIsPERU (DNI y RUC)

Para la verificación y autocompletado ágil de datos de identidad en Perú, Camargo PMS aprueba la integración con el servicio externo **APIsPERU** ([documentación oficial](https://dniruc.apisperu.com/doc)):

### Endpoints de Consulta

- `GET /api/v1/dni/{dni}`: Consulta de personas naturales mediante documento DNI (8 dígitos).
- `GET /api/v1/ruc/{ruc}`: Consulta de entidades jurídicas, empresas y personas con negocio mediante RUC (11 dígitos).

### Capa de Abstracción Desacoplada

El código de dominio, personas y proveedores **nunca** se acopla directamente a la API de APIsPERU. Se establece un patrón de adaptadores:

```text
ServicioConsultaIdentidad (Dominio)
    ├── ProveedorConsultaDni (Interfaz)
    │       └── AdaptadorApisPeruDni
    └── ProveedorConsultaRuc (Interfaz)
            └── AdaptadorApisPeruRuc
```

Esta arquitectura permite reemplazar el proveedor tecnológico en el futuro (ej. migrar a RENIEC directo, SUNAT directo u otro proveedor) sin modificar la lógica de Personas, Proveedores u Operaciones.

### Reglas Vinculantes de Consumo

1. **Fidelidad al contrato de API:** No asumir que la API retorna campos no documentados en su contrato oficial. Se mapean exclusivamente los atributos reales devueltos por el proveedor.
2. **Complemento manual:** La interfaz debe permitir al usuario completar o editar libremente datos no suministrados por la consulta externa (ej. dirección, email, teléfonos o fecha de nacimiento si la API no los incluye).
3. **Reutilización de RUC:** La consulta RUC es reutilizable en todo el sistema para entidades fiscales y comerciales (proveedores de servicios, clientes corporativos, empresas asociadas y contratistas).
4. **Seguridad y tokens:** El token técnico de APIsPERU se gestiona exclusivamente como secreto de entorno rotativo, fuera de Git y del frontend del navegador. Las consultas se orquestan mediante backend para proteger las credenciales técnicas.

## Evolución

Documentar cada endpoint con entrada, salida, permisos, errores, idempotencia y efectos secundarios. Las pruebas de contrato deben ejecutarse antes de publicar cambios consumidos por terceros.
