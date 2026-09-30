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

## Integración y Exportación iCalendar RFC 5545 (AIRBNB-ICAL-1B)

Para la distribución multicanal hacia OTAs (Airbnb, Booking, VRBO), Camargo PMS implementa servicios de sincronización bilateral basados en el estándar RFC 5545.

### Endpoint Público de Exportación

- **Ruta:** `GET /ical/exportar/{token}`
- **Controlador:** `\CamargoPMS\Controladores\IcalExportarControlador::exportar(string $token)`
- **Cabeceras de Respuesta:**
  - `Content-Type: text/calendar; charset=utf-8`
  - `Content-Disposition: attachment; filename="camargo_unidad_{unidad_id}.ics"`
- **Seguridad:**
  - El token en la URL es contrastado mediante su hash SHA-256 (`token_exportacion_hash`) en la tabla `conexiones_ical`.
  - Si el token no existe, está revocado, o la conexión tiene `exportacion_habilitada = 0` o `estado = 'REVOCADO'`, el endpoint responde inmediatamente **HTTP 404 Not Found**.
- **Filtro Anti-Echo:**
  - Al generar el calendario exportado para una conexión $C$, el servicio `ExportacionIcalServicio` excluye los bloqueos originados por la propia conexión $C$, evitando bucles de re-importación. Propaga los bloqueos de otros canales conectados y las reservas/bloqueos del PMS.
- **Protección de Privacidad:**
  - No se exportan datos personales de huéspedes, notas de reserva, ni UIDs de otros canales externos. Los eventos se anonimizan con `SUMMARY: No disponible` y se omite la propiedad `DESCRIPTION`.

### Interfaces de Sincronización e Invocación Desacoplada

- **Servicio Soberano:** `\CamargoPMS\Servicios\SincronizacionIcalServicio::sincronizarConexion(int $conexionId): array`
- **CLI Runner:** `bin/sincronizar-ical.php`
  - Uso: `php bin/sincronizar-ical.php [--conexion=ID]`
  - Descarga mediante cliente HTTP seguro con defensa en profundidad Anti-SSRF (`ClienteHttpIcalSeguro`), parseo con `sabre/vobject` vía `IcalAdaptador`, e inserción/actualización idempotente en `eventos_ical_externos` e `inventario_diario_unidades`.

## Capa Operativa y Administración de Canales iCalendar (AIRBNB-ICAL-1C / 1C-C1)

Endpoints administrativos internos para la gestión visual, monitorización técnica y sincronización manual de conexiones iCalendar desde la interfaz Alina.

### 1. Vista Administrativa y Datos Reactivos

- **`GET /canales-ical`**
  - **Permiso:** `canales.ver`
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::index()`
  - **Descripción:** Renderiza la vista Alina con layout principal, tabla responsiva y modales para la operación de canales.

- **`GET /canales-ical/datos`**
  - **Permiso:** `canales.ver`
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::datos()`
  - **Respuesta JSON:**
    - `ok: true`
    - `conexiones: [...]`: Lista de conexiones con metadatos no sensibles (`id`, `nombre`, `canal_nombre`, `unidad_numero`, `estado`, `sincronizacion_activa`, `ultima_sincronizacion_en`, `ultimo_estado_sync`, `token_prefijo`, etc.). Omite estrictamente secretos y URLs privadas.
    - `kpis: { total_conexiones, activas, pausadas, conflictos_pendientes }`

### 2. Gestión de Conexiones y Edición Segura

- **`POST /canales-ical`**
  - **Permisos:** `canales.gestionar` + CSRF obligatorio
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::guardar()`
  - **Payload:** `canal_id`, `unidad_id`, `nombre`, `url_importacion`, `sincronizacion_activa`, `exportacion_habilitada`
  - **Respuesta:** HTTP 201 Created con el ID asignado. Cifra la URL de importación con AES-256-GCM y genera el token híbrido de exportación.

- **`POST /canales-ical/{id}`**
  - **Permisos:** `canales.gestionar` + CSRF obligatorio
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::guardar(int $id)`
  - **Regla 15 (Edición Segura):** Si `url_importacion` se envía vacía, el backend conserva intacta la URL cifrada previamente almacenada, evitando la sobreescritura accidental por rellenado en blanco.

### 3. Recuperación Protegida del Feed de Exportación y Hardening de Caché

- **`POST /canales-ical/{id}/copiar-feed`**
  - **Permisos:** `canales.ver` + CSRF obligatorio
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::copiarFeed(int $id)`
  - **Cabeceras Obligatorias:**
    - `Cache-Control: no-store, no-cache, must-revalidate`
    - `Pragma: no-cache`
  - **Respuesta JSON:** `ok: true`, `url_feed: "https://.../ical/exportar/{token_plano}"`
  - **Seguridad:** Revelación estrictamente bajo demanda en memoria de ejecución. Ni el token plano ni la URL de feed se almacenan en cookies, cabeceras o respuestas de listados generales. Las cabeceras `no-store` previenen el almacenamiento de credenciales en historial de navegador o cachés intermedias.

### 4. Rotación Criptográfica y Sincronización Manual Concurrente

- **`POST /canales-ical/{id}/rotar-token`**
  - **Permisos:** `canales.gestionar` + CSRF obligatorio
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::rotarToken(int $id)`
  - **Efecto:** Genera un nuevo secreto CSPRNG de 32 bytes, recomputa el hash SHA-256 e invalida de forma inmediata el feed con el token anterior (HTTP 404).

- **`POST /canales-ical/{id}/sincronizar`**
  - **Permisos:** `canales.sincronizar` + CSRF obligatorio
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::sincronizar(int $id)`
  - **Concurrencia (Contrato Único de Exclusión Soberana):** La exclusión mutua se gestiona directamente en el dominio por `SincronizacionIcalServicio::sincronizarConexion()` mediante `GET_LOCK('camargo_ical_sync_{id}', 0)` en MySQL con timeout 0 (fail-fast sin esperas activas) y liberación incondicional `RELEASE_LOCK` en bloque `finally`.
  - **Manejo de Conflictos:** Si otra sincronización (UI manual, CLI o scheduler) retiene el lock o existe una corrida en curso no expirada en `sincronizaciones_ical_log`, el servicio arroja `ConexionIcalEnSincronizacionExcepcion`, la cual el controlador traduce a `HTTP 409 Conflict` con sobre JSON: `{"ok": false, "error": "...", "codigo": "CONEXION_BLOQUEADA"}`.

### 5. Historial Técnico y Conflictos

- **`GET /canales-ical/{id}/historial`**
  - **Permiso:** `canales.ver`
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::historial(int $id)`
  - **Respuesta JSON:** Últimos 20 registros de sincronización técnica para la conexión solicitada.

- **`GET /canales-ical/{id}/conflictos`** y **`GET /canales-ical/conflictos-activos`**
  - **Permiso:** `canales.ver`
  - **Controlador:** `\CamargoPMS\Controladores\CanalIcalControlador::conflictos(int $id)` / `conflictosActivos()`
  - **Respuesta JSON:** Lista de eventos externos que solapan con reservas locales PMS y requieren atención operativa.

### 6. Contrato de Ejecución CLI (`bin/sincronizar-ical.php`)

Comando técnico de línea de órdenes para sincronización manual, de pruebas o ejecución periódica desasistida:
- **`php bin/sincronizar-ical.php --conexion=<ID>`**: Sincroniza una conexión puntual. Retorna exit code `0` ante éxito o si fue omitida por lock concurrente, y `1` ante fallo técnico.
- **`php bin/sincronizar-ical.php --solo-debidas`**: Sincroniza conexiones activas cuya frecuencia de sondeo (`frecuencia_minutos`) esté vencida según `listarDebidasParaSondeo()`.
- **`php bin/sincronizar-ical.php --todas`**: Fuerza la sincronización de todas las conexiones activas con importación habilitada.
- **`php bin/sincronizar-ical.php --quiet` / `--silencioso`**: Suprime banners informativos y cabeceras decorativas (adecuado para cron / tareas programadas).
- **Códigos de Salida:**
  - `0`: Ejecución exitosa (todas las conexiones procesadas correctamente o ninguna debida).
  - `1`: Error fatal de bootstrap, base de datos, argumentos o configuración.
  - `2`: Ejecución por lote completada con fallos técnicos en una o más conexiones (aislamiento de fallos).

## Evolución

Documentar cada endpoint con entrada, salida, permisos, errores, idempotencia y efectos secundarios. Las pruebas de contrato deben ejecutarse antes de publicar cambios consumidos por terceros.
