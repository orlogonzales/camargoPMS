# Módulos

## Plataforma

- Identidad y acceso: personas, cuentas, sesiones, roles, capacidades y clientes técnicos.
- Configuración: empresa, documentos, parámetros, tarifas y catálogos.
- Auditoría: actores, eventos, correlación y consulta autorizada.
- Menús: áreas, opciones, jerarquía, orden, permisos y estado.

## Inmuebles

- Propiedades: datos generales y configuración.
- Niveles: organización física cuando aplique.
- Unidades: características, modalidades, precio base y estado.
- Inventario: activos, amenities, asignación y movimientos.
- Mantenimiento: incidencias, trabajos e impacto en disponibilidad.

## Personas y terceros

- Personas: identidad reutilizable y datos de contacto.
- Proveedores: categorías y servicios que presta.
- Huéspedes/arrendatarios: roles de una persona en operaciones.

## Operaciones

- Disponibilidad: consolida reservas, estadías, arrendamientos, bloqueos y mantenimiento.
- Reservas: origen, fechas, unidad, persona, conceptos, total y estados.
- Estadías: ocupación corta efectiva.
- Arrendamientos: relación prolongada y calendario contractual.
- Contratos: plantillas, versiones, generación y documentos.

## Servicios

- Consumos: agua, electricidad, internet y futuros servicios medidos.
- Tarifas: vigencia e histórico.
- Servicios adicionales: catálogo, proveedores y venta congelada.
- Traslados: especialización con datos de viaje.

## Finanzas

- Cobros y pagos.
- Transacciones y movimientos de caja.
- Ingresos, egresos, gastos y retiros.
- Impuestos y conciliaciones.
- Integración con proveedores de pago.

## Integraciones

- API: contrato común.
- WordPress: disponibilidad y operaciones, sin fuente paralela.
- Webhooks de pago: confirmación idempotente.
- Futuras app y OTA/Airbnb: adaptadores separados.

## Dependencias relevantes

Configuración, identidad, autorización y auditoría son transversales. Disponibilidad precede a reservas. Personas, unidades y disponibilidad preceden a estadías/arrendamientos. Las operaciones preceden a contratos y cobros. Caja consume eventos trazables de módulos anteriores, pero no debe acoplarlos a un proveedor externo.
