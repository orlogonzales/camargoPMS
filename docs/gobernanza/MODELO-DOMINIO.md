# Modelo de dominio inicial

Este documento fija conceptos e invariantes, no tablas definitivas. El diseño físico se realizará por módulo y mediante migraciones revisadas.

## Núcleo inmobiliario

```text
Propiedad → Nivel/Piso → Unidad
```

Una propiedad agrupa unidades. Un nivel permite representar edificios, pero el modelo deberá admitir propiedades donde no resulte necesario mostrarlo. Cada unidad conserva configuración, características, precio base, disponibilidad, inventario y servicios aplicables.

## Identidad, Personas y Personal

El modelo de Camargo PMS establece el **Principio de Separación de Identidad**:

```text
PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
```

Esta separación es vinculante y rige toda la arquitectura:

- Una **Persona** puede existir en el sistema sin ser colaborador ni tener usuario (ej. huéspedes, contactos, clientes).
- Un **Colaborador** siempre se vincula a una Persona humana preexistente, pero puede existir sin una cuenta de Usuario.
- Un **Usuario** representa una cuenta de acceso a la plataforma vinculada a una Persona humana; no todo colaborador requiere acceso al sistema.
- **Cargo** y **Rol** son conceptos radicalmente distintos:
  - **Cargo:** Define el puesto o función laboral que desempeña un colaborador en la empresa (ej. Recepcionista, Limpieza, Administrador). No confiere permisos de software automáticamente.
  - **Rol:** Agrupa permisos de seguridad para operar funciones dentro de Camargo PMS (ej. Ventas, Operaciones, Superadministrador). Nunca se infieren permisos a partir del cargo.

### Maestro de Personas Naturales (Separación Persona / Documento / Contacto)

Entidad central reutilizable que consolida los datos de identificación y contacto de individuos naturales, normalizada en tres estructuras para evitar redundancia y columnas planas:

1. **Persona Natural (`personas`):**
   - Atributos ontológicos: nombres (obligatorio), apellido paterno, apellido materno (nulabilidad defensiva para extranjeros o personas con un solo apellido), fecha de nacimiento (nullable, sin fechas futuras), país de nacionalidad (`paises`) y dirección residencial básica.
   - Estado de operación (`ACTIVO`, `INACTIVO`) para soft delete.
   - El nombre completo no se persiste; se computa dinámicamente en capa de aplicación.

2. **Documentos Personales (`personas_documentos`):**
   - Relación 1:N con `tipos_documento` (DNI, Pasaporte, Carné de Extranjería).
   - Unicidad estricta `(tipo_documento_id, numero_documento)` para evitar colisiones entre distintas personas.
   - Regla de dominio e integridad DB: exactamente un documento principal activo por persona.
   - **Regla vinculante:** El RUC **no** forma parte del catálogo de documentos personales de personas naturales; pertenece al modelado de identidad fiscal y personas jurídicas.

3. **Medios de Contacto (`personas_contactos`):**
   - Relación 1:N con medios normalizados (`TELEFONO`, `EMAIL`).
   - Teléfono móvil con indicador booleano `es_whatsapp` para evitar duplicar el mismo número físico.
   - Regla de dominio e integridad DB: un contacto principal activo por tipo y persona.
   - Normalización de emails en minúsculas y teléfonos limpios.


### Personal y Colaboradores

Representa la vinculación laboral de una Persona con Camargo Hostelería:

- Estado laboral (ej. Activo, Cesado, Licencia).
- Cargo asignado.
- Fecha de ingreso.
- Fecha de salida y motivo de cese cuando corresponda.
- Observaciones laborales y datos de auditoría.

### Historial Laboral por Episodios

El historial laboral es inmutable y no se sobreescribe cuando un colaborador se reincorpora o cambia de puesto. Registra episodios laborales cronológicos independientes:

```text
Persona X
├── Periodo 1: 01/02/2026 - 30/06/2026 | Cargo: Reservas | Cese: Fin de contrato temporal
├── Periodo 2: 15/10/2026 - 31/12/2027 | Cargo: Reservas | Cese: Renuncia voluntaria
└── Periodo 3: 01/01/2028 - Vigente    | Cargo: Jefe de Reservas (Reincorporación / Ascenso)
```

Cada periodo conserva fecha de ingreso, cese, motivo, observaciones, cargo ejercido y trazabilidad de auditoría.

### Catálogo de Cargos

Catálogo administrable y dinámico de puestos laborales en la organización (ej. Administrador, Reservas, Sistemas, Limpieza, Mantenimiento). No se modela como un `ENUM` estático para permitir agregar cargos futuros sin alterar la base de datos o el código fuente.

### Gestión de Usuarios

Cuentas humanas de acceso al PMS:

- Identidad humana asociada (`Persona`).
- Nombre de usuario único.
- Hash de contraseña mediante algoritmos criptográficos robustos de PHP (`password_hash`).
- Estado de la cuenta (activo, bloqueado, suspendido).
- Roles asignados.
- Control de sesiones activas y último acceso registrado.
- Auditoría de modificaciones y autenticación.

### Catálogo de Roles y Permisos

- **Roles:** Catálogo administrable que agrupa permisos funcionales (ej. Superadministrador, Administrador, Ventas, Operaciones, Solo Consulta). Un usuario puede soportar uno o múltiples roles.
- **Permisos:** Capacidades atómicas expresadas en formato `recurso.accion` (ej. `personal.ver`, `personal.crear`, `personal.editar`, `personal.desactivar`, `usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.bloquear`, `reservas.cancelar`, `caja.registrar_ingreso`, `caja.anular`, `configuracion.roles`, `configuracion.menu`).

### Relación entre Personal y Caja

Un colaborador puede ser contraparte, beneficiario o responsable de transacciones financieras:

- Fondos por rendir y dinero entregado para compras operativas.
- Rendiciones de gastos, devoluciones y reembolsos.
- Pago de honorarios, sueldos o remuneraciones.

**Regla de diseño:** Personal **no** es un subsistema financiero. El módulo de Finanzas/Caja mantiene sus propias entidades, saldos y comprobantes, limitándose a referenciar al colaborador/persona correspondiente en cada movimiento.
### Actores Auditados del Sistema

Un actor auditado puede ser:

- `USER`: Persona humana autenticada en el sistema mediante su cuenta de usuario.
- `SYSTEM`: Tareas en segundo plano, cron jobs o procesos internos del PMS.
- `INTEGRATION`: Clientes técnicos autorizados (WordPress, aplicación móvil u otros consumidores de API).
- `PAYMENT_PROVIDER`: Proveedores de pasarelas de pago a través de webhooks seguros.

Nunca se crean usuarios humanos ficticios para representar procesos de integración técnica.

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
