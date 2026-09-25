# Modelo de dominio inicial

Este documento fija conceptos e invariantes, no tablas definitivas. El diseño físico se realizará por módulo y mediante migraciones revisadas.

## Núcleo inmobiliario

```text
Propiedad → Nivel/Piso → Unidad
```

Una propiedad agrupa unidades. Un nivel permite representar edificios, pero el modelo deberá admitir propiedades donde no resulte necesario mostrarlo. Cada unidad conserva configuración, características, precio base, disponibilidad, inventario y servicios aplicables.

## Personas y actores

`Persona` representa una identidad reutilizable que puede actuar como huésped, arrendatario, contacto o proveedor. Una cuenta de acceso es una responsabilidad distinta y no debe duplicar los datos personales sin necesidad.

Un actor auditado puede ser:

- `USER`: persona autenticada.
- `SYSTEM`: proceso interno.
- `INTEGRATION`: WordPress, aplicación u otro cliente técnico.
- `PAYMENT_PROVIDER`: webhook o proceso del proveedor de pago.

No se crean usuarios humanos ficticios para integraciones.

## Ocupación y disponibilidad

- Reserva: intención o bloqueo temporal.
- Estancia: ocupación efectiva de corta duración.
- Arrendamiento: relación contractual normalmente mensual o prolongada.
- Bloqueo: indisponibilidad administrativa.
- Mantenimiento: indisponibilidad técnica cuando corresponda.

La disponibilidad resulta de todas esas fuentes y futuras reservas OTA. Ninguna operación puede confirmar un solapamiento incompatible. La confirmación debe usar transacción y estrategia de concurrencia definida en el diseño de datos.

Las reservas registran persona, unidad, fechas, duración, tarifa, conceptos, total, canal, origen, estado de reserva y estado de pago. Las pendientes expiran mediante una regla configurable y auditable.

## Contratos y documentos

Las plantillas contractuales son editables y versionadas. Un contrato emitido conserva la versión y los datos con que fue generado. El membrete PNG A4 y sus márgenes son configuración versionable cuando su cambio pueda afectar reproducción histórica.

## Servicios, consumos y tarifas

Agua, electricidad, internet y servicios futuros tienen tarifas con vigencia. Un consumo facturado conserva la tarifa aplicada.

Electricidad considera lectura anterior, lectura actual, consumo, tarifa e importe. Correcciones deben conservar trazabilidad, no reescribir silenciosamente el historial.

Los servicios adicionales tienen catálogo y relación muchos-a-muchos con proveedores. Al contratar uno se congela descripción, cantidad, precio de venta, costo, proveedor y estado. Traslados agregan llegada/salida, origen, destino, fecha, hora, pasajeros y observaciones mediante una entidad especializada, no columnas universales de reserva.

## Finanzas

Cobros, ingresos, egresos, gastos, retiros, pagos, impuestos y conciliaciones deben mantener origen y actor. Un pago confirmado puede originar transacción, movimiento de caja y cambio de reserva; el caso de uso debe ser atómico o explícitamente recuperable.

No existen movimientos financieros huérfanos. Los importes históricos no cambian por modificaciones posteriores de precios o catálogos.

## Inventario y mantenimiento

Activos y amenities pueden asignarse a unidades. Se registran estado, ubicación, movimientos, incidencias y mantenimiento. Una incidencia puede afectar disponibilidad mediante una regla explícita.

## Configuración

Datos de empresa, logo, membrete, márgenes PDF, plantillas, tarifas, servicios, precios y parámetros viven en configuración administrada, no dispersos en constantes. Los valores con efecto histórico usan versión o vigencia.

## Integraciones

Camargo PMS es la fuente central. WordPress y futuras aplicaciones consultan y ordenan operaciones mediante API. Los adaptadores de pago implementan una interfaz común y separan sandbox de producción. Los webhooks son autenticados e idempotentes.

## Pendientes de modelado

- Cardinalidad exacta entre propiedad, nivel y unidad.
- Identificadores fiscales y reglas específicas por país.
- Catálogos definitivos de estados y transiciones.
- Política exacta de solapamiento, zonas horarias y noches.
- Contabilidad, impuestos y conciliación requeridos legalmente.
- Retención y anonimización de datos personales.
