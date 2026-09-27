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

- **PROPIEDADES:**
  - Catálogo de Inmuebles (`/propiedades`)
  - Catálogo de Unidades (`/unidades`)
  - Disponibilidad e Inventario (`/disponibilidad`)
- **PERSONAS:**
  - Directorio de Personas
  - Gestión de Personal (Colaboradores, Cargos, Historial)
- **ADMINISTRACIÓN Y CONFIGURACIÓN:**
  - Usuarios del Sistema
  - Roles y Permisos
  - Gestión de Menú
  - Sesiones Activas
  - Configuración General del Sistema (`/configuracion/sistema`)

## Inmuebles e Infraestructura Física

- **Propiedades Físicas (PROPIEDADES-1):**
  - Maestro central de edificaciones, predios e inmuebles contenedores (`propiedades`).
  - Administración de código único, nombre, dirección física obligatoria, ubicación geográfica (departamento, provincia, distrito, país del catálogo `paises`), coordenadas GPS opcionales con rangos validados (`latitud` en `[-90, 90]`, `longitud` en `[-180, 180]`), datos de contacto y ciclo de vida histórico (`ACTIVO` ↔ `INACTIVO`).
  - Ficha técnica y perfil del inmueble (`/propiedades/{id}/perfil`) con georreferenciación y enlace interactivo a Google Maps.
- **Unidades Físicas y Tipologías (UNIDADES-1 — Completada):**
  - Catálogo de tipologías arquitectónicas (`tipos_unidad`) y divisiones físicas habitacionales (`unidades`) vinculadas a su inmueble contenedor raíz.
  - Interfaz Alina operativa en `/unidades` y ficha técnica en `/unidades/{id}/perfil`.
  - Respeto de los principios `PROPIEDAD ≠ UNIDAD` (subordinación a propiedad_id), `UNIDAD ≠ REGISTRO DESECHABLE` (ciclo ACTIVO/INACTIVO, cero DELETE) y `UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD` (fechas y precios diferidos).
  - Unicidad scoped por propiedad `UNIQUE(propiedad_id, codigo)` bajo D-065.

## Personas y Terceros

- Personas: Identidad reutilizable e histórica.
- Proveedores: Catálogo de terceros (naturales o jurídicos mediante RUC) y servicios que prestan.
- Huéspedes / Arrendatarios: Roles funcionales asumidos por una Persona en el ciclo operativo.

## Operaciones

- **Disponibilidad e Inventario Diario (DISPONIBILIDAD-1 — Completada):**
  - Motor central de inventario diario *sparse* con restricción inviolable `UNIQUE(unidad_id, fecha)` en motor InnoDB (D-067).
  - Registro maestro de bloqueos operativos (`bloqueos_unidad`) y persistencia atómica por noche (`inventario_diario_unidades`).
  - Modelo temporal hotelero con intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$, cálculo de noches ($\text{salida} - \text{entrada} \ge 1$) y resolución de huso horario IANA por propiedad con fallback al PMS (`operacion.zona_horaria_predeterminada`).
  - Interfaz Alina operativa en `/disponibilidad` con KPIs en tiempo real, consulta por rango de fechas, matriz/rack mensual interactivo y gestión de bloqueos con validación en servidor, CSRF y SweetAlert2.
  - Cero tarifas, precios o conceptos monetarios (P-005 estrictamente preservada).
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
