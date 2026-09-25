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
   - Atributos ontológicos: nombres (obligatorio), apellido paterno, apellido materno (nulabilidad defensiva para extranjeros o personas con un solo apellido/monónimos legales), fecha de nacimiento (nullable, sin fechas futuras), país de nacionalidad (`paises`) y dirección residencial básica.
   - Estado de operación (`ACTIVO`, `INACTIVO`) para soft delete.
   - El nombre completo no se persiste; se computa dinámicamente en capa de aplicación.

2. **Documentos Personales (`personas_documentos`):**
   - Relación 1:N con `tipos_documento` (DNI, Pasaporte, Carné de Extranjería).
   - **Jurisdicción Documental Estructural (PERSONAL-1A):** Modelada mediante atributos ontológicos en `tipos_documento`:
     - `pais_fijo_id`: Documentos de jurisdicción exclusiva fija (DNI y CE fijados a Perú). Si no se provee país emisor, el servicio resuelve automáticamente a dicho país fijo; cualquier intento de asociar otro país es rechazado.
     - `pais_emisor_obligatorio`: Documentos internacionales donde el país emisor es mandatorio (ej. PASAPORTE).
   - `pais_emisor_id` es estrictamente `NOT NULL` con clave foránea referencial íntegra (`fk_documentos_pais_emisor`) hacia `paises(id)`. Se prohíbe el uso de centinelas técnicos artificiales (`COALESCE(pais_emisor_id, 0)`).
   - Unicidad documental natural: `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)`. Permite que personas distintas porten el mismo número si fueron emitidos por países diferentes (ej. pasaportes de Chile y Argentina), impidiendo duplicados en una misma jurisdicción.
   - Regla de dominio e integridad DB: exactamente un documento principal activo por persona (`uq_persona_principal`).
   - **Regla vinculante:** El RUC **no** forma parte del catálogo de documentos personales de personas naturales; pertenece al modelado de identidad fiscal y personas jurídicas.

3. **Medios de Contacto (`personas_contactos`):**
   - Relación 1:N con medios normalizados (`TELEFONO`, `EMAIL`).
   - Teléfono móvil con indicador booleano `es_whatsapp` para evitar duplicar el mismo número físico.
   - Regla de dominio e integridad DB: un contacto principal activo por tipo y persona (`uq_contacto_tipo_principal`).
   - Normalización de emails en minúsculas y teléfonos limpios.

### Personal y Colaboradores

Representa la identidad laboral estable de una Persona con Camargo Hostelería:

- Cardinalidad estricta 1:1 lógica con `personas` (`uq_colaboradores_persona`). Una persona física nunca tiene más de un registro de colaborador.
- Código interno único secuencial e inmutable (`COL-XXXX`).
- Estado (`ACTIVO`, `INACTIVO`).
- Desacoplado de usuarios de software y roles.

### Historial Laboral por Episodios y Asignaciones de Cargo

El historial laboral es inmutable y no se sobreescribe cuando un colaborador se reincorpora o cambia de función. Se organiza jerárquicamente en dos niveles relacionales:

1. **Episodios Laborales (`episodios_laborales`):**
   - Representa un período continuo de vinculación laboral desde el ingreso hasta el cese.
   - Atributos: `fecha_inicio`, `fecha_fin` (NULL si está activo), `motivo_cese` (RENUNCIA, DESPIDO, MUTUO_ACUERDO, FIN_CONTRATO, JUBILACION, OTRO), `observaciones`, `estado` (`ACTIVO`, `INACTIVO`).
   - Integridad DB: Columna virtual generada `uq_colaborador_abierto` para forzar a lo sumo un episodio abierto activo por colaborador.
   - Reingreso: Un colaborador cesado reingresa mediante la creación de un nuevo episodio laboral con nueva fecha de inicio, sin crear un nuevo registro en `colaboradores` y reactivando su estado general.
   - Regla temporal: Prohibición estricta de solapamiento de fechas con episodios anteriores.

2. **Asignaciones de Cargo (`episodios_laborales_cargos`):**
   - Mantiene la trazabilidad histórica de los puestos o funciones ocupados dentro de un episodio laboral específico.
   - Atributos: `episodio_laboral_id`, `cargo_id`, `fecha_inicio`, `fecha_fin` (NULL si es el cargo vigente), `observaciones`.
   - Integridad DB: Columna virtual generada `uq_episodio_cargo_abierto` para forzar a lo sumo un cargo vigente activo por episodio.
   - **Invariante Temporal de No Solapamiento (PERSONAL-1A):** Ninguna asignación de cargo puede solaparse cronológicamente con otra dentro del mismo episodio, aplicable tanto a intervalos abiertos como cerrados (ej. Cargo A: 01/01 a 30/06 y Cargo B: 01/04 a 31/05 es rechazado tajantemente).
   - **Límites con el Episodio:** Toda asignación debe iniciar en o después del inicio del episodio y finalizar en o antes del cese del episodio; un episodio cerrado no admite cargos abiertos o indefinidos.
   - Transición de cargo (ascenso o cambio funcional): La asignación anterior se cierra en $D-1$ y la nueva se abre en $D$, garantizando continuidad temporal estricta validada por `ColaboradorServicio::validarSolapamientoAsignacionCargo()`.

```text
Persona X (ID 1)
└── Colaborador (ID 1, COL-0001, ACTIVO)
    ├── Episodio Laboral 1 (01/02/2026 - 30/06/2026 | INACTIVO | Cese: FIN_CONTRATO)
    │   └── Asignación Cargo 1 (01/02/2026 - 30/06/2026 | Cargo: RECEPCIONISTA)
    └── Episodio Laboral 2 (01/08/2026 - Abierto | ACTIVO) [Reingreso]
        ├── Asignación Cargo 2 (01/08/2026 - 30/09/2026 | Cargo: RECEPCIONISTA)
        └── Asignación Cargo 3 (01/10/2026 - Abierto | Cargo: ADMINISTRADOR) [Ascenso]
```

### Catálogo de Cargos

Catálogo dinámico y administrable de funciones laborales (`cargos`). Semillas estructurales iniciales:
- `ADMINISTRADOR`: Gestión general operativa del establecimiento.
- `RECEPCIONISTA`: Atención al huésped, check-in, check-out y soporte en mostrador.
- `RESERVAS`: Gestión comercial de reservas y asignaciones.
- `LIMPIEZA`: Aseo, desinfección y preparación de unidades.
- `MANTENIMIENTO`: Reparaciones técnicas e infraestructura.

Los cargos laborales no confieren permisos en el software; describen exclusivamente funciones dentro de la organización.

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
