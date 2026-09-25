# Auditoría

## Objetivo

Reconstruir quién o qué realizó una operación, cuándo, desde qué contexto y qué cambió, sin convertir el log de auditoría en almacenamiento indiscriminado de datos sensibles.

## Actor

| Tipo | Ejemplo |
|---|---|
| `USER` | Orlando, Cintia |
| `SYSTEM` | expiración automática de reservas |
| `INTEGRATION` | Web WordPress, app móvil |
| `PAYMENT_PROVIDER` | webhook del proveedor de pago |

Cada evento conserva tipo e identificador estable. Las integraciones no se representan como usuarios humanos falsos.

## Evento mínimo

```text
fecha y hora
actor y tipo
módulo
acción
entidad e identificador
resultado
identificador de correlación
contexto técnico permitido
cambio anterior/nuevo cuando sea pertinente
```

La IP y user-agent pueden registrarse cuando exista base y utilidad; deben someterse a retención y acceso controlados.

## Eventos prioritarios

- **Identidad y Personas:** Alta, edición de datos personales, actualización de documentos y cambio de estado.
- **Personal y Colaboradores:** Vinculación laboral, cese, reingreso, asignación o cambio de cargo y registro de observaciones laborales.
- **Acceso y Autenticación:** Inicio de sesión (login exitoso y fallido), cierre de sesión (logout), expiración, bloqueo preventivo y revocación forzada administrativa de sesiones activas.
- **Usuarios y Seguridad:** Creación de cuenta, suspensión, reactivación, cambio de contraseña, asignación o desasignación de roles y modificación de permisos granulares.
- **Gestión de Menú:** Creación, edición, activación/desactivación, reordenamiento jerárquico y asociación de permisos a opciones.
- **Operaciones:** Reservas, disponibilidad, estadías, arrendamientos, bloqueos y contratos.
- **Finanzas y Caja:** Cobros, pagos, egresos, anticipos, rendiciones a personal, gastos, retiros e impuestos.
- **Mantenimiento e Inventario:** Movimientos de activos, incidencias y estados de servicio.
- **Integraciones:** Consumo de APIs externas (APIsPERU DNI/RUC), clientes técnicos y recepción/procesamiento de webhooks.

## Integridad y acceso

La auditoría es append-only para usuarios ordinarios. Una corrección genera otro evento. El acceso requiere permiso específico y queda auditado. No registrar contraseñas, tokens, CVV, números completos de tarjeta, documentos completos ni payloads sensibles sin necesidad demostrada.

## Relación con logs

Los logs técnicos sirven para diagnóstico; la auditoría sirve para trazabilidad de negocio y seguridad. Pueden compartir correlación, pero tienen políticas y consumidores diferentes.
