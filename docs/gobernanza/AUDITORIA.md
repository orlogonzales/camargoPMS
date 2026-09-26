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

## Arquitectura del Núcleo Transversal (AUDITORÍA-1)

### Principio ACTOR ≠ USUARIO
- Todo evento es atribuido a un `ActorAuditoria` (`actores`).
- Los actores pueden ser de cuatro tipos (`TipoActor`): `USUARIO` (humano con credenciales), `SISTEMA` (procesos automáticos, seed `CAMARGO_PMS`), `INTEGRACION` (clientes técnicos API) o `PROVEEDOR_PAGO` (webhooks).
- Si el actor es humano, se vincula mediante `usuario_id`; la auditoría conserva `actor_id` (`ON DELETE RESTRICT`) y `usuario_id` (`ON DELETE SET NULL`), preservando la inmutabilidad histórica aún si la cuenta de usuario se elimina.

### Sanitización Recursiva de Secretos
- Implementada por `SanitizadorAuditoria`: inspecciona y purga recursivamente estructuras JSON y arreglos para `valores_anteriores`, `valores_nuevos` y `metadatos`.
- Campos redactados automáticamente: `contrasena`, `contrasena_hash`, `password`, `clave`, `token`, `_csrf_token`, `authorization`, `api_key`, `secret`, `tarjeta`, `cvv`, etc.

### Contexto y Correlación
- Captura de forma no invasiva: `ip`, `user_agent`, `metodo_http`, `ruta` y `correlacion_id`.
- Permite vincular múltiples eventos de auditoría a una única transacción HTTP o ciclo de vida de petición.

### Persistencia y Repositorios
- `AuditoriaRepositorio` es estrictamente append-only: carece de métodos de edición o borrado.
- Operaciones críticas ejecutan la auditoría dentro de la transacción de dominio (`COMMIT` conjunto o `ROLLBACK` total).
- Operaciones no críticas aplican persistencia defensiva tolerante a fallos secundarios.

### Integraciones de Dominio Activas
- `AutenticacionServicio`: `LOGIN`, `LOGOUT`.
- `RolServicio`: `ASIGNAR`, `REVOCAR`.
- `MenuServicio`: `CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`, `REORDENAR`, `ELIMINAR`.
- `UsuarioServicio`: `CREAR`, `CAMBIAR_ESTADO`, `CAMBIAR_CLAVE`.

## Arquitectura del Núcleo Transversal (AUDITORÍA-1)

### Principio ACTOR ≠ USUARIO
- Todo evento es atribuido a un `ActorAuditoria` (`actores`).
- Los actores pueden ser de cuatro tipos (`TipoActor`): `USUARIO` (humano con credenciales), `SISTEMA` (procesos automáticos, seed `CAMARGO_PMS`), `INTEGRACION` (clientes técnicos API) o `PROVEEDOR_PAGO` (webhooks).
- Si el actor es humano, se vincula mediante `usuario_id`; la auditoría conserva `actor_id` (`ON DELETE RESTRICT`) y `usuario_id` (`ON DELETE SET NULL`), preservando la inmutabilidad histórica aún si la cuenta de usuario se elimina.

### Sanitización Recursiva de Secretos
- Implementada por `SanitizadorAuditoria`: inspecciona y purga recursivamente estructuras JSON y arreglos para `valores_anteriores`, `valores_nuevos` y `metadatos`.
- Campos redactados automáticamente: `contrasena`, `contrasena_hash`, `password`, `clave`, `token`, `_csrf_token`, `authorization`, `api_key`, `secret`, `tarjeta`, `cvv`, etc.

### Contexto y Correlación
- Captura de forma no invasiva: `ip`, `user_agent`, `metodo_http`, `ruta` y `correlacion_id`.
- Permite vincular múltiples eventos de auditoría a una única transacción HTTP o ciclo de vida de petición.

### Persistencia y Repositorios
- `AuditoriaRepositorio` es estrictamente append-only: carece de métodos de edición o borrado.
- Operaciones críticas ejecutan la auditoría dentro de la transacción de dominio (`COMMIT` conjunto o `ROLLBACK` total).
- Operaciones no críticas aplican persistencia defensiva tolerante a fallos secundarios.

### Integraciones de Dominio Activas
- `AutenticacionServicio`: `LOGIN`, `LOGOUT`.
- `RolServicio`: `ASIGNAR`, `REVOCAR`.
- `MenuServicio`: `CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`, `REORDENAR`, `ELIMINAR`.
- `UsuarioServicio`: `CREAR`, `CAMBIAR_ESTADO`, `CAMBIAR_CLAVE`.

## Relación con logs

Los logs técnicos sirven para diagnóstico; la auditoría sirve para trazabilidad de negocio y seguridad. Pueden compartir correlación, pero tienen políticas y consumidores diferentes.
