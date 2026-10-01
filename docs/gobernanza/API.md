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

### 7. Automatización Desasistida y Wrappers de Operación (AIRBNB-ICAL-1D2)

La ejecución periódica en background se apoya en los programadores nativos del sistema operativo (Cron en Linux / Task Scheduler en Windows), despertando cada 5 minutos e invocando `bin/sincronizar-ical.php --solo-debidas --quiet`:
- **División de Autoridades:** Cron/Task Scheduler actúa exclusivamente como disparador; `--solo-debidas` como autoridad temporal evaluando periodicidades vencidas; y `GET_LOCK` en MySQL como autoridad de concurrencia atómica por conexión.
- **Prevención de Solapamiento Global:** Para evitar acumulación de procesos si un ciclo se retrasa por latencia de red en OTAs, se implementa control de instancia única: `flock -n` en Linux ([`bin/cron-ical.sh`](file:///d:/laragon/www/app.camargo-pms/bin/cron-ical.sh)) y política nativa `IgnoreNew` con Mutex en Windows ([`bin/task-scheduler-ical.ps1`](file:///d:/laragon/www/app.camargo-pms/bin/task-scheduler-ical.ps1)). Este control no interfiere con el `GET_LOCK` por conexión ni impide sincronizaciones manuales desde la UI Alina.
- **Sin Exposición de Secretos:** Los comandos y scripts no reciben contraseñas, tokens de exportación ni URLs privadas por argumentos de proceso (`ps aux` / `tasklist` limpios).
- **Manual Operativo Completo:** Véase [`docs/gobernanza/SCHEDULER_ICAL.md`](file:///d:/laragon/www/app.camargo-pms/docs/gobernanza/SCHEDULER_ICAL.md) para los procedimientos de instalación, plantillas de cron/XML, diagnóstico y desactivación.

### 8. Contratos de Disponibilidad, Cotización y Clientes API (WORDPRESS-1B)

- **Arquitectura de Clientes API:**
  - Cadena estricta de separación: `ACTOR INTEGRACION -> API_CLIENT -> CREDENCIAL TÉCNICA -> SCOPES`.
  - Cero cuentas humanas ficticias; asignación de identidad formal técnica en `actores` (`tipo = 'INTEGRACION'`, `usuario_id = NULL`).
  - Tokens Bearer criptográficos CSPRNG de alta entropía (`cpms_live_...`). El secreto en claro se entrega una sola vez; la persistencia almacena únicamente el hash SHA-256 para validación $O(1)$ y un prefijo de 16 caracteres para trazabilidad.
  - Catálogo de scopes canónicos: `disponibilidad.leer`, `cotizacion.crear`, `reservas.hold`, `reservas.confirmar`, `reservas.cancelar`, `reservas.leer`.
  - Operaciones atómicas de emisión, rotación segura con período de gracia opcional, y revocación inmediata.
- **Autoridad Soberana de Cotización:**
  - `CotizacionServicio`: orquesta noche a noche la tarifa jerárquica aplicable (`UNIDAD` > `TIPO_UNIDAD` > `PROPIEDAD`).
  - Cumplimiento de directiva monetaria D-069: precisión con `BCMath`, moneda soberana 'PEN', redondeo formal bancario a 2 decimales (`round_half_up`), 4 decimales intermedios, regla provisional de impuestos 0.00.
  - Emisión de token reproducible firmado con HMAC-SHA256 (`token_cotizacion`) con expiración temporal de 15 minutos.
  - Principio hotelero fundamental: la cotización **no inserta filas físicas en inventario**.
- **Creación de Reservas Soberanas desde Cotización:**
  - `ReservaServicio::crearReservaDesdeCotizacion`: recibe y valida el token firmado, comprueba disponibilidad soberana en tiempo real, registra o asocia al titular y crea la reserva en hold `PENDIENTE` con expiración programada (`expira_en`).
  - Bloquea atómicamente el inventario en `inventario_diario_unidades` (`BLOQUEO_MANUAL`, origen `RESERVA`), garantizando la soberanía de inventario del PMS frente a sobreventas.
- **Consolidación en WORDPRESS-1C:**
  - El perímetro HTTP, intermediarios, sobre unificado de respuesta, idempotencia persistida y endpoints técnicos de diagnóstico quedan completados y validados.

### 9. Perímetro HTTP /api/v1 (WORDPRESS-1C)

- **Pipeline de Intermediarios HTTP:**
  - `ApiCorrelacionIntermediario`: Gestiona la cabecera `X-Correlacion-ID`. Si el cliente provee una válida (alfanumérica/guiones, máx 64 caracteres) la preserva; de lo contrario genera una segura con CSPRNG.
  - `ApiCorsIntermediario`: Aplica la política CORS mediante allowlist configurable (`API_CORS_ORIGINS`). Resuelve de forma inmediata las peticiones preflight `OPTIONS` retornando `HTTP 204 No Content` con cabeceras completas de CORS sin requerir autenticación Bearer (cortocircuito anticipado del pipeline).
  - `ApiAutenticacionIntermediario`: Autentica clientes mediante cabecera `Authorization: Bearer cpms_live_...`. Extrae el token, calcula su hash SHA-256 y verifica contra `api_credenciales` en tiempo $O(1)$. Comprueba estado activo de credencial y cliente, vigencia (`expira_en`), identidad técnica de auditoría (`actores`) y lista blanca de IPs (`ips_permitidas`). Retorna HTTP 401 (`NO_AUTORIZADO`, `TOKEN_INVALIDO`, `CREDENCIAL_INVALIDA`) o HTTP 403 (`IP_NO_AUTORIZADA`).
  - `ApiRateLimitIntermediario`: Rate limiting desacoplado con ventana deslizante de 60 segundos gestionado por `ApiRateLimitServicio`. Utiliza archivos locales en `storage/cache/rate_limits/` con bloqueo exclusivo atómico `flock(LOCK_EX)`. Emite cabeceras estándar `X-RateLimit-Limit`, `X-RateLimit-Remaining` y `X-RateLimit-Reset`. Ante exceso de cuota responde `HTTP 429 Too Many Requests` con código semántico `RATE_LIMIT_EXCEDIDO` y cabecera obligatoria `Retry-After`.
  - `ApiScopeIntermediario`: Valida que la credencial técnica posea los permisos específicos requeridos para la ruta solicitada. Responde `HTTP 403 Forbidden` (`ACCESO_DENEGADO`) sin exponer detalles internos de la entidad.
  - `ApiIdempotenciaIntermediario`: Aplica exclusivamente a métodos mutables (`POST`, `PUT`, `PATCH`). Exige la cabecera `Idempotency-Key` (8-128 caracteres seguros). Si la clave está en proceso concurrente, responde `HTTP 409 Conflict` (`OPERACION_EN_CURSO`). Si la clave ya fue completada exitosamente, realiza replay determinista instantáneo retornando el código y cuerpo previo con cabecera `X-Cache-Lookup: IDEMPOTENT-REPLAY`. Si la misma clave se envía con un payload diferente (distinto hash SHA-256), responde `HTTP 422 Unprocessable Content` (`IDEMPOTENCIA_DESAJUSTE_PAYLOAD`). Captura la respuesta del controlador vía hook post-dispatch `despues()` y persiste el resultado en la tabla `api_idempotencia` (migración 037).
- **Constructor Unificado de Respuestas (`RespuestaApi`):**
  - Formato JSON homogéneo para todas las respuestas:
    ```json
    {
        "ok": true,
        "datos": { ... },
        "error": null,
        "codigo": "OPERACION_EXITOSA",
        "meta": {
            "correlacion_id": "...",
            "marca_tiempo": "2026-09-30T23:00:00Z"
        }
    }
    ```
  - Errores de ruta inexistente bajo `/api/*` son capturados por el enrutador retornando automáticamente `HTTP 404 Not Found` en formato JSON con código `RECURSO_NO_ENCONTRADO`.
- **Endpoints de Diagnóstico e Inspección Técnica:**
  - `GET /api/v1/ping`: Endpoint público con correlación, rate limit y CORS. Retorna `{"servicio": "Camargo PMS API", "version": "1.0", "estado": "OPERATIVO"}` (HTTP 200).
  - `OPTIONS /api/v1/ping`: Preflight CORS retornando HTTP 204.
  - `GET /api/v1/perfil`: Inspección de la identidad técnica autenticada (HTTP 200). Retorna datos del cliente, metadatos de la credencial activa, actor técnico de auditoría y lista de scopes asignados. **Cero exposición de secretos en claro, contraseñas o hashes**.
  - `OPTIONS /api/v1/perfil`: Preflight CORS retornando HTTP 204 sin requerir token Bearer.

### 10. Endpoints de Negocio de Disponibilidad, Cotización y Reservas Directas (WORDPRESS-1D)

Exposición soberana de endpoints comerciales para integración headless con WordPress y canales de venta directa:

- **1. Disponibilidad de Inventario:**
  - **Ruta:** `GET /api/v1/disponibilidad`
  - **Scope requerido:** `disponibilidad.leer`
  - **Parámetros de Consulta (Query Params):**
    - `fecha_desde` (obligatorio, formato `YYYY-MM-DD`): Inicio del rango hotelero.
    - `fecha_hasta` (obligatorio, formato `YYYY-MM-DD`): Fin del rango (estricto `fecha_hasta > fecha_desde`).
    - `propiedad_id` (opcional, entero positivo): Filtra unidades de una propiedad específica.
    - `tipo_unidad_id` (opcional, entero positivo): Filtra unidades de un tipo específico.
    - `huespedes` (opcional, entero positivo): Capacidad mínima de personas requerida.
  - **Comportamiento:** Consulta en tiempo real las unidades operativas y su ocupación en `inventario_diario_unidades`. Retorna catálogo de unidades disponibles sin crear bloqueos.
  - **Respuesta Exitosa (HTTP 200):**
    ```json
    {
      "ok": true,
      "datos": {
        "fecha_desde": "2026-10-10",
        "fecha_hasta": "2026-10-13",
        "noches": 3,
        "total_disponibles": 2,
        "unidades": [
          {
            "id": 1,
            "propiedad_id": 1,
            "tipo_unidad_id": 1,
            "nombre": "Habitación 101",
            "capacidad_estandar": 2,
            "capacidad_maxima": 3
          }
        ]
      },
      "codigo": "DISPONIBILIDAD_CONSULTADA"
    }
    ```

- **2. Cotización Soberana de Estancia:**
  - **Ruta:** `POST /api/v1/cotizaciones`
  - **Scope requerido:** `cotizacion.crear`
  - **Cuerpo JSON:**
    ```json
    {
      "unidad_id": 1,
      "fecha_llegada": "2026-10-10",
      "fecha_salida": "2026-10-13",
      "numero_huespedes": 2
    }
    ```
  - **Comportamiento:** Valida disponibilidad soberana en las fechas. Orquesta el cálculo tarifario noche a noche bajo directiva D-069 y `BCMath`. **No bloquea inventario ni escribe en base de datos**. Emite un `token_cotizacion` firmado con HMAC-SHA256 con vigencia de 30 minutos.
  - **Respuesta Exitosa (HTTP 200):**
    ```json
    {
      "ok": true,
      "datos": {
        "cotizacion_id": "COT-101-20261010-ABCD1234",
        "unidad_id": 1,
        "fecha_llegada": "2026-10-10",
        "fecha_salida": "2026-10-13",
        "noches": 3,
        "moneda": "PEN",
        "total": "450.00",
        "desglose_noches": [
          { "fecha": "2026-10-10", "tarifa_noche": "150.00" }
        ],
        "vigente_hasta": "2026-09-30 23:45:00",
        "token_cotizacion": "eyJhbGciOi..."
      },
      "codigo": "COTIZACION_CALCULADA"
    }
    ```

- **3. Creación de Reserva / Hold Comercial:**
  - **Ruta:** `POST /api/v1/reservas`
  - **Scope requerido:** `reservas.hold`
  - **Cabeceras obligatorias:** `Authorization: Bearer <token>`, `Idempotency-Key: <key-uuid-o-hash>` (8 a 128 caracteres).
  - **Cuerpo JSON:**
    ```json
    {
      "token_cotizacion": "eyJhbGciOi...",
      "titular": {
        "nombres": "Carlos",
        "apellidos": "Alvarez",
        "email": "carlos.alvarez@ejemplo.com",
        "telefono": "+51 987654321",
        "tipo_documento": "DNI",
        "numero_documento": "44556677"
      },
      "duracion_hold_minutos": 15,
      "observaciones": "Llegada estimada a las 18:00"
    }
    ```
  - **Comportamiento:**
    - Verifica criptográficamente el `token_cotizacion` (reproducibilidad e integridad).
    - Resuelve o crea la entidad `personas` y su registro 360° en `clientes`.
    - Bloquea atómicamente el inventario en `inventario_diario_unidades` (`BLOQUEO_MANUAL`, origen `RESERVA`) con orden determinista de fechas (D-067).
    - Asigna el actor técnico autenticado en `creado_por_actor_id` (D-061: `ACTOR != USUARIO`).
    - Crea la reserva en estado `PENDIENTE` con `expira_en = NOW() + INTERVAL 15 MINUTE`.
    - Replay determinista si se reenvía la misma `Idempotency-Key` (HTTP 201 con `X-Cache-Lookup: IDEMPOTENT-REPLAY`).
    - Previene sobreventa: si otra transacción tomó el inventario, retorna `HTTP 409 Conflict` (`CONFLICTO_DISPONIBILIDAD`).
  - **Respuesta Exitosa (HTTP 201 Created):**
    ```json
    {
      "ok": true,
      "datos": {
        "codigo_reserva": "RSV-202610-0001",
        "estado": "PENDIENTE",
        "unidad_id": 1,
        "fecha_llegada": "2026-10-10",
        "fecha_salida": "2026-10-13",
        "noches": 3,
        "moneda": "PEN",
        "total": "450.00",
        "expira_en": "2026-09-30 23:30:00",
        "titular": {
          "nombre_completo": "Carlos A.",
          "email": "c***z@ejemplo.com"
        }
      },
      "codigo": "RESERVA_HOLD_CREADA"
    }
    ```

- **4. Consulta Pública y Segura de Reserva:**
  - **Ruta:** `GET /api/v1/reservas/{codigo}`
  - **Scope requerido:** `reservas.leer`
  - **Comportamiento:** Localiza la reserva por su código alfanumérico público. Aplica estricta ofuscación de PII (Directiva D-106.3): oculta IDs relacionales de base de datos (`id`, `persona_titular_id`, `actor_id`) y notas internas, enmascarando nombre (`Carlos A.`), email (`c***z@ejemplo.com`) y teléfono (`***-**-4321`). Retorna HTTP 404 si el código no existe.
  - **Respuesta Exitosa (HTTP 200):**
    ```json
    {
      "ok": true,
      "datos": {
        "codigo_reserva": "RSV-202610-0001",
        "estado": "PENDIENTE",
        "unidad_id": 1,
        "fecha_llegada": "2026-10-10",
        "fecha_salida": "2026-10-13",
        "noches": 3,
        "moneda": "PEN",
        "total": "450.00",
        "expira_en": "2026-09-30 23:30:00",
        "titular": {
          "nombre_completo": "Carlos A.",
          "email": "c***z@ejemplo.com",
          "telefono": "***-**-4321"
        }
      },
      "codigo": "RESERVA_CONSULTADA"
    }
    ```

- **5. Solicitudes Preflight CORS:**
  - Las 4 rutas soportan el método `OPTIONS` respondiendo `HTTP 204 No Content` con cabeceras completas de CORS sin requerir autenticación Bearer ni `Idempotency-Key`.

## Evolución

Documentar cada endpoint con entrada, salida, permisos, errores, idempotencia y efectos secundarios. Las pruebas de contrato deben ejecutarse antes de publicar cambios consumidos por terceros.


