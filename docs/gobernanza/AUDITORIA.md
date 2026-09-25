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

- inicio/cierre de sesión, fallos y cambios de acceso;
- reservas, disponibilidad, estadías y arrendamientos;
- contratos, plantillas y versiones;
- precios, tarifas y configuración;
- cobros, pagos, caja, gastos, retiros e impuestos;
- inventario, incidencias y mantenimiento;
- usuarios, roles, permisos e integraciones;
- recepción y procesamiento de webhooks.

## Integridad y acceso

La auditoría es append-only para usuarios ordinarios. Una corrección genera otro evento. El acceso requiere permiso específico y queda auditado. No registrar contraseñas, tokens, CVV, números completos de tarjeta, documentos completos ni payloads sensibles sin necesidad demostrada.

## Relación con logs

Los logs técnicos sirven para diagnóstico; la auditoría sirve para trazabilidad de negocio y seguridad. Pueden compartir correlación, pero tienen políticas y consumidores diferentes.
