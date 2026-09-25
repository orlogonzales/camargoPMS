# Módulos

## Plataforma, Identidad y Configuración

- **Identidad:**
  - **Personas:** Maestro central de individuos (huéspedes, contactos, colaboradores), documentos de identidad (DNI, Pasaporte) y datos de contacto normalizados.
  - **Personal / Colaboradores:** Vinculación laboral, estados de vinculación, asignación de cargos e historial laboral inmutable estructurado por episodios.
  - **Cargos:** Catálogo administrable de puestos o funciones laborales (no rígido).
- **Acceso y Seguridad:**
  - **Usuarios:** Cuentas de acceso humano ligadas a personas, contraseñas criptográficamente seguras (`password_hash`), suspensión y activación.
  - **Roles y Permisos:** Catálogo dinámico de roles, capacidades atómicas (`recurso.accion`) independientes de los cargos laborales y autorización estricta en servidor.
  - **Superadministrador:** Definición y protección de la cuenta raíz para evitar pérdida accidental de administración.
  - **Sesiones Activas:** Monitoreo de actividad, duración máxima, expiración por inactividad y revocación forzada administrativa.
- **Gestión de Menú Dinámico:**
  - Administración visual de opciones de navegación: jerarquía, creación, edición, activación, iconos Tabler, rutas internas, anidamiento y reordenamiento interactivo (drag & drop).
  - Asociación directa de cada opción con el permiso requerido para visibilidad, preservando el contrato biunívoco de Alina (`data-target` ↔ `id`).
- **Configuración:** Empresa, documentos, parámetros operacionales, membretes, márgenes y tarifas.
- **Auditoría:** Registro transversal append-only de actores (`USER`, `SYSTEM`, `INTEGRATION`, `PAYMENT_PROVIDER`), eventos, correlación y consulta autorizada.

## Organización Conceptual de Menús Futuros

- **PERSONAS:**
  - Directorio de Personas
  - Gestión de Personal (Colaboradores, Cargos, Historial)
- **ADMINISTRACIÓN Y CONFIGURACIÓN:**
  - Usuarios del Sistema
  - Roles y Permisos
  - Gestión de Menú
  - Sesiones Activas
  - Parámetros Generales de Empresa

## Personas y Terceros

- Personas: Identidad reutilizable e histórica.
- Proveedores: Catálogo de terceros (naturales o jurídicos mediante RUC) y servicios que prestan.
- Huéspedes / Arrendatarios: Roles funcionales asumidos por una Persona en el ciclo operativo.

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
