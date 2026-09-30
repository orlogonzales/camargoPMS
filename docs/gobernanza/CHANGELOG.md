# Changelog

Los cambios se agrupan por micro-baseline. Este archivo no reemplaza el historial Git.

## Sin publicar

### Microfase AIRBNB-ICAL-1D2 — Despliegue y Operación Automática Portable del Scheduler iCalendar

- **Infraestructura de Automatización Desasistida Portable:**
  - Arquitectura canónica de ejecución episódica mediante programador del SO: Cron / Task Scheduler (cada 5 min) → Control de Instancia Única (`flock` / `IgnoreNew`) → PHP CLI (`bin/sincronizar-ical.php --solo-debidas --quiet`) → `SincronizacionIcalServicio` → `GET_LOCK` por conexión en MySQL → Motor iCalendar soberano.
  - Cero daemons PHP, cero workers en segundo plano residentes, cero dependencias de Supervisor, Redis, colas o web crons por HTTP.
- **Prevención de Solapamiento del Scheduler sin Alterar Concurrencia de Conexión:**
  - Implementación de control de instancia única para evitar acumulaciones si un ciclo se retrasa por latencia externa de red:
    - En Linux: script wrapper [`bin/cron-ical.sh`](file:///d:/laragon/www/app.camargo-pms/bin/cron-ical.sh) con descriptor y bloqueo `flock -n /tmp/camargo_ical_scheduler.lock`.
    - En Windows: script PowerShell [`bin/task-scheduler-ical.ps1`](file:///d:/laragon/www/app.camargo-pms/bin/task-scheduler-ical.ps1) con `System.Threading.Mutex` global y plantilla XML [`bin/task-scheduler-ical.xml`](file:///d:/laragon/www/app.camargo-pms/bin/task-scheduler-ical.xml) con política nativa `<MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>`.
  - El control global no sustituye ni interfiere con el `GET_LOCK("camargo_ical_sync_{id}")` por conexión; la UI Alina mantiene capacidad de sincronización manual concurrente independiente.
- **Portabilidad y Plantillas Parametrizadas:**
  - Entorno de producción catalogado formalmente como **NO VERIFICADO**, entregando plantillas parametrizadas con rutas absolutas entrecomilladas (tolerantes a espacios) y variables de entorno estándar.
  - Plantilla de crontab para Linux en [`bin/crontab-ical.template`](file:///d:/laragon/www/app.camargo-pms/bin/crontab-ical.template).
  - Verificación en entorno de desarrollo local (Windows/Laragon) sin dejar tareas programadas persistentes residuales.
- **Manual Operativo y de Gobernanza:**
  - Creación de [`docs/gobernanza/SCHEDULER_ICAL.md`](file:///d:/laragon/www/app.camargo-pms/docs/gobernanza/SCHEDULER_ICAL.md) con guía paso a paso de instalación, verificación de logs en `storage/logs/ical_scheduler.log`, diagnóstico de errores, desactivación y principios de seguridad.
  - Documentación de jerarquía de autoridades: Disparador (SO), Autoridad Temporal (`--solo-debidas`), Autoridad de Concurrencia (`GET_LOCK`) y Autoridad de Estado (MySQL / Dominio).
- **Seguridad Operativa y Cero DDL:**
  - Inmunidad en tablas de procesos del SO (`ps aux`, `tasklist`): cero contraseñas, URLs privadas o tokens de exportación pasados como argumentos CLI.
  - Cero DDL: 122 tablas relacionales preservadas, ranura de migración 036 estrictamente **LIBRE**, catálogo `admin-dashboard/` 100% intacto.

## Baseline oficial 6f4b12b (AIRBNB-ICAL-1D1)

### Microfase AIRBNB-ICAL-1D1 — Hardening del Motor, Contrato Único de Exclusión de Sincronización y CLI Seguro

- **Contrato Único de Exclusión Soberana en Sincronización:**
  - Encapsulación unificada del bloqueo de concurrencia directamente en `SincronizacionIcalServicio::sincronizarConexion()`, garantizando que toda invocación —interfaz web administrativa Alina, comando CLI manual o futuro programador/cron desasistido— quede inexorablemente sometida a la misma política atómica de exclusión mutua por conexión.
  - Bloqueo a nivel de conexión mediante `SELECT GET_LOCK('camargo_ical_sync_{id}', 0)` en MySQL con timeout 0 (fail-fast sin esperas activas) y liberación incondicional `SELECT RELEASE_LOCK(...)` garantizada en bloque `finally`.
  - Excepción de dominio tipada `ConexionIcalEnSincronizacionExcepcion` con código `CONEXION_BLOQUEADA` ante colisiones concurrentes activas.
- **Desacoplamiento Estricto del Controlador HTTP:**
  - `CanalIcalControlador::sincronizar()` delega 100% de la lógica de locking al servicio soberano; su única responsabilidad es orquestar la petición HTTP, verificar CSRF/sesión y traducir `ConexionIcalEnSincronizacionExcepcion` a respuesta `HTTP 409 Conflict` con error estructurado `codigo: 'CONEXION_BLOQUEADA'`.
- **Detección y Saneamiento Automático de Ejecuciones Huérfanas (Stale Runs):**
  - Implementación de `SincronizacionIcalLogRepositorio::limpiarLogsHuerfanos(?int $conexionId, int $segundosMaximos = 120)` para identificar y cerrar de forma determinista corridas interrumpidas por crash de proceso cliente, SIGKILL o caída de socket (donde `finalizado_en IS NULL` y `iniciado_en < NOW() - INTERVAL 120 SECOND`), marcando su estado como `ERROR` y documentando el fallo en `mensaje_resultado`.
  - La verificación previa `haySincronizacionEnCurso($conexionId, 120)` incorpora ventana de gracia de 120 segundos para evitar bloqueos perpetuos por procesos muertos.
- **Selección Eficiente de Conexiones Debidas para Sondeo:**
  - Implementación de `ConexionIcalRepositorio::listarDebidasParaSondeo()` que selecciona de forma óptima aquellas conexiones activas con importación habilitada cuya última sincronización sea `NULL` o supere el intervalo programado (`ultima_sincronizacion_en <= NOW() - INTERVAL frecuencia_minutos MINUTE`).
- **Comando CLI Robusto y Preparado para Scheduler (`bin/sincronizar-ical.php`):**
  - Incorporación de opciones `--solo-debidas`, `--quiet`/`--silencioso` y `--ayuda`.
  - Contrato formal de códigos de salida: `0` (éxito total o 0 debidas), `1` (error fatal de argumentos o configuración), `2` (fallo parcial tolerante en lote).
  - Aislamiento de fallos: omisión elegante `[OMITIDO]` ante conexiones bloqueadas concurrentemente, sin interrumpir el lote de sincronización ni generar reintentos de red inmediatos.
  - Test negativo de salida confirmando cero filtración de secretos, tokens o variables `.env` en consola.
- **Gobernanza, Calidad y Cero DDL:**
  - Cero DDL: Base de datos relacional preservada estrictamente en **122 tablas**. Ranura de migración `036` permanece 100% **LIBRE**.
  - Catálogo `admin-dashboard/` 100% inalterado y de solo lectura.
  - Nueva suite automatizada `tests/test_airbnb_ical_scheduler.php` con 46 comprobaciones exhaustivas (46/46 PASS).
  - Regresión transversal activa: 74 suites automatizadas, 1,993 comprobaciones, 0 fallos (100% PASS).

## Baseline oficial d8fe8c3 (AIRBNB-ICAL-1C / 1C-C1)

### Microfase AIRBNB-ICAL-1C / 1C-C1 — Capa Operativa Alina para Canales y Conexiones iCalendar

- **Interfaz Administrativa y Operativa Alina:**
  - Implementación del módulo administrativo en `/canales-ical` y vista `app/Vistas/canales_ical/index.php`.
  - 4 KPIs de monitorización en tiempo real: Conexiones Totales, Conexiones Activas, Conexiones Pausadas y Conflictos Pendientes.
  - Tabla dinámica e interactiva con badges de canal y estado, acciones contextuales seguras, y modales nativos para:
    - Crear y Editar Conexión (`#modal-conexion-ical`).
    - Bitácora de Sincronizaciones Técnicas (`#modal-historial-ical`).
    - Matriz de Conflictos con Reservas PMS (`#modal-conflictos-ical`).
  - Cumplimiento riguroso de convenciones Alina: cero clases `dotted`, `dashed` o `*-subtle`.
- **Operación Manual y Bloqueo Concurrente Robusto:**
  - Endpoint de sincronización manual bajo demanda `POST /canales-ical/{id}/sincronizar`.
  - Concurrencia blindada a nivel de conexión mediante bloqueo cooperativo en base de datos con `GET_LOCK('camargo_pms_ical_sync_{id}', 10)` y liberación incondicional `RELEASE_LOCK()` en bloque `finally`.
  - Detección de sincronizaciones en curso con código de error estructurado `CONEXION_EN_SINCRONIZACION` (HTTP 409 Conflict).
  - Mitigación frontend contra doble-clic, bloqueo de botones y feedback interactivo durante la sincronización.
- **Seguridad en Revelación de Secretos y Hardening de Caché (AIRBNB-ICAL-1C-C1):**
  - Protección estricta en listados: El endpoint `GET /canales-ical/datos` omite de forma absoluta `url_importacion_cifrada`, `token_exportacion_hash`, `token_exportacion_cifrado` y cualquier clave criptográfica.
  - Recuperación controlada on-demand: La URL del feed de exportación se genera exclusivamente bajo petición expresa mediante `POST /canales-ical/{id}/copiar-feed`.
  - Hardening de caché defensiva (RFC 7234): El endpoint `copiar-feed` responde con cabeceras explícitas `Cache-Control: no-store, no-cache, must-revalidate` y `Pragma: no-cache`, previniendo que navegadores o proxies almacenen el token descifrado en cachés de historial.
- **Rotación Criptográfica y Revocación Lógica:**
  - Endpoint `POST /canales-ical/{id}/rotar-token`: regenera un secreto CSPRNG de 32 bytes, recomputa el hash SHA-256 e invalida de forma inmediata el feed con el token anterior (HTTP 404).
  - Preservación histórica: Cero `DELETE` físico. La revocación de conexiones se opera mediante transición de estado a `REVOCADO`, conservando eventos, logs de sincronización y auditoría inalterados.
- **Autorización RBAC y Protección CSRF:**
  - Permisos atómicos granulares: `canales.ver`, `canales.gestionar` y `canales.sincronizar`.
  - Validación obligatoria de token CSRF en todos los endpoints mutacionales (`POST`).
- **JavaScript Modular Nativo:**
  - Implementación de `public/assets/js/gestion-canales-ical.js` en JavaScript ES6+ moderno.
  - Consumo asíncrono con `Fetch API` y sobre JSON canónico. Cero uso de jQuery (`$.ajax`, `$.post`, `$.get`).
  - Alertas y confirmaciones mediante SweetAlert2 integrado con Alina. Cero invocación a `alert()` o `confirm()` nativos.
- **Ajuste Incidental de Estabilidad en Persistencia de Mantenimiento (Fuera del Dominio iCal):**
  - En `app/Repositorios/IncidenciaRepositorio.php` y `app/Repositorios/OrdenTrabajoRepositorio.php`, se optimizó la generación de códigos únicos (`generarCodigo()`) mediante bucle de verificación de colisiones `do { ... } while (SELECT 1 ... WHERE codigo = :cod)`.
  - Contexto: Corrección incidental defensiva para prevenir colisiones por clave duplicada (`1062 Duplicate entry`) durante ejecuciones repetitivas continuas del test `test_mantenimiento_matriz_40.php`, originadas por inserciones con códigos aleatorios en la suite de `Housekeeping`. Este ajuste técnico de persistencia estabiliza la suite global de mantenimiento y no altera en absoluto el dominio de canales iCalendar.
- **Calidad, Pruebas y Cero DDL:**
  - Nueva suite automatizada `tests/test_airbnb_ical_ui.php` con 94 comprobaciones exhaustivas (94/94 PASS).
  - Regresión transversal: 73 suites automatizadas, 1,947 comprobaciones, 0 fallos (100% PASS).
  - Cero DDL: 122 tablas relacionales preservadas; migración `035_canales_ical.sql` última ejecutada; ranura `036` estrictamente LIBRE.
  - Catálogo `admin-dashboard/` 100% inalterado y de solo lectura.

## Baseline oficial 991fba4 (AIRBNB-ICAL-1B)

### Microfase AIRBNB-ICAL-1B — Infraestructura Soberana Multicanal iCalendar (D-103)

- **Separación Ontológica y Principios de Dominio:**
  - $\text{EVENTO ICAL EXTERNO} \neq \text{RESERVA PMS} \neq \text{ESTADÍA} \neq \text{CONTRATO} \neq \text{PERSONA/CLIENTE}$.
  - $\text{CANAL} \neq \text{CONEXIÓN} \neq \text{EVENTO} \neq \text{SINCRONIZACIÓN}$.
  - Cero creación de entidades comerciales o financieras a partir de eventos iCalendar.
- **Persistencia Relacional y Migración 035 (`035_canales_ical.sql`):**
  - Creación de 4 tablas relacionales: `canales_distribucion`, `conexiones_ical`, `eventos_ical_externos`, `sincronizaciones_ical_log`.
  - Cero DDL sobre `inventario_diario_unidades`: los bloqueos se persisten con `tipo_bloqueo = 'BLOQUEO_MANUAL'`, `origen_tipo = 'EVENTO_ICAL_EXTERNO'` y `origen_id = eventos_ical_externos.id`.
  - Paridad 100% en `SQL/camargo_pms.sql`. Total de tablas relacionales asciende a 122. Ranura 036 estrictamente LIBRE.
- **Parser Oficial RFC 5545 (`sabre/vobject: ^5.0`):**
  - Incorporación de `sabre/vobject: 5.0.0` (BSD-3-Clause, PHP 8.3 compatible, sin DAV) vía Composer.
  - Implementación de `app/Adaptadores/IcalAdaptador.php`: parseo seguro, manejo de line-folding, escaping, `DATE-TIME` con UTC, cálculo exacto de noches hoteleras en intervalo semiabierto $[inicio, fin)$ sin off-by-one, y expansión acotada de recurrencias (`RRULE`).
- **Criptografía Autenticada AES-256-GCM y Tokens Híbridos:**
  - Implementación de `app/Servicios/IcalCriptografiaServicio.php` con clave `ICAL_ENCRYPTION_KEY` vía `.env`.
  - Cifrado autenticado con IV aleatorio y tag de 16 bytes para URLs privadas de importación. Fail-closed ante clave faltante.
  - Modelo híbrido de tokens de exportación: hash SHA-256 para indexación $O(1)$ sin secretos planos en volcados + ciphertext AES-256-GCM para visualización en panel admin.
- **Defensa en Profundidad Anti-SSRF:**
  - Implementación de `app/Servicios/ClienteHttpIcalSeguro.php`: validación previa de host, resolución DNS manual, filtrado exhaustivo de rangos IPv4/IPv6 privados, loopback, CGNAT, link-local y cloud metadata (`169.254.169.254`).
  - Pinning de IP mediante `CURLOPT_RESOLVE` para neutralizar ataques de DNS Rebinding. Redirecciones manuales verificadas (máx 3), límite de 2 MB y timeout de 10s.
- **Motor Soberano de Sincronización e Idempotencia:**
  - Implementación de `app/Servicios/SincronizacionIcalServicio.php`: reconciliación determinista mediante la unión de bloqueos de múltiples OTAs (Airbnb + Booking), transición atómica de noches compartidas ante cancelaciones, detección y no sobreescritura de reservas locales (`EN_CONFLICTO`), y salvaguarda ante feeds vacíos (`FEED_VACIO_SOSPECHOSO`).
- **Exportación Segura con Filtro Anti-Echo y Privacidad:**
  - Implementación de `app/Servicios/ExportacionIcalServicio.php` y `app/Controladores/IcalExportarControlador.php` en ruta `GET /ical/exportar/{token}`.
  - Filtro Anti-Echo que suprime los eventos de la conexión destino $C$ y propaga los bloqueos de otras OTAs y reservas PMS.
  - Anonimización total: `SUMMARY: No disponible`, 0 `DESCRIPTION`, cero filtrado de datos personales o identificadores comerciales.
- **Invocación Desacoplada y CLI:**
  - Script administrativo de sincronización: `bin/sincronizar-ical.php`. Cero bloqueo en el hilo HTTP web.
- **Calidad, Pruebas y Regresión:**
  - Nueva suite automatizada `tests/test_airbnb_ical.php` con 155 comprobaciones exhaustivas (155/155 PASS).
  - Regresión transversal: 72 suites automatizadas, 1,847 comprobaciones, 0 fallos (100% PASS).
  - Catálogo `admin-dashboard/` 100% inalterado y de solo lectura.

## Baseline oficial 2686ae9 (APISPERU-1B / APISPERU-1C)

### Microfase APISPERU-1C — Extensión Controlada del Motor DNI/RUC a Módulos Internos (D-102)

- **Extensión Controlada y Reutilización de Infraestructura Soberana:**
  - Cero duplicación de infraestructura: Se reutiliza íntegramente el motor homologado en 1B (`ApisPeruAdaptador`, `ConsultaDocumentoServicio`, `DocumentoConsultaControlador`, `GET /api/documentos/consultar`, `CamargoForms.consultarDocumento()`). Cero segundos adaptadores, clientes cURL o endpoints paralelos.
  - **Módulo Personal / Colaboradores (`app/Vistas/personal/index.php`):**
    - Integración de botón de consulta asistida `#btn-consultar-dni-personal` en modal de alta.
    - Flujo Local-First dual: verificación de persona en base de datos local y fallback asistido mediante APIsPERU (Reniec) para DNI de 8 dígitos.
    - Autocompletado no destructivo de nombres, apellido paterno y apellido materno exclusivamente en campos vacíos.
    - CE y Pasaporte delimitados estrictamente a entrada manual con alerta informativa.
  - **Módulo Servicios / Proveedores (`app/Vistas/servicios/index.php` y `public/assets/js/gestion-servicios.js`):**
    - Integración de botón `#btn-consultar-doc-proveedor` en modal de proveedores.
    - Manejo polimórfico de documentos: RUC (11 dígitos, SUNAT) y DNI (8 dígitos, Reniec).
    - Para proveedores personas naturales con DNI, ajusta select a `PERSONA_NATURAL` y autocompleta nombres y dirección sin sobreescribir. Para RUC, autocompleta razón social, nombre comercial, dirección y teléfono si están vacíos.
    - Cero persistencia lateral automática: la consulta únicamente asiste el llenado del formulario en el cliente.
  - **Módulo Gastos (`app/Vistas/gastos/index.php`):**
    - Integración de botón `#btn-consultar-acreedor-doc` junto al campo de documento del acreedor.
    - Autocompletado no destructivo de `#input-acreedor-nombre` con razón social (RUC) o nombre completo (DNI).
    - Aislamiento canónico de dominio contable: GASTO ≠ COMPRA ≠ CUENTA_POR_PAGAR ≠ PAGO ≠ MOVIMIENTO. Cero persistencia lateral, cero creación automática de empresas o proveedores en base de datos.
  - **Módulo Reclamaciones Internas (`app/Vistas/reclamaciones/index.php`):**
    - Integración de botón `#btn-consultar-doc-reclamante` junto al número de documento en modal asistido.
    - Soporte asistido para DNI y RUC rellenando nombres, apellidos y domicilio sin sobreescritura.
    - Preservación íntegra de snapshots T0 y requisitos regulatorios del libro de reclamaciones.
  - **Exclusión y Protección Regulatoria:**
    - Libro de Reclamaciones público (`app/Vistas/reclamaciones/publico/formulario.php`) verificado 100% manual: 0 llamadas externas, 0 consumo de APIsPERU.
- **Calidad, Regresión y Gobernanza:**
  - Suite `tests/test_apisperu_integracion.php` extendida a 92 comprobaciones (92/92 PASS), incluyendo pruebas directas de Personal, Proveedores, Gastos, Reclamaciones internas y prueba negativa del libro público.
  - Regresión transversal: 71 suites de pruebas, 1,692 checks, 0 fallos.
  - Cero DDL: 118 tablas relacionales preservadas; última migración `034_agregar_foto_personas.sql`; ranura `035` estrictamente libre.
  - Catálogo Alina `admin-dashboard/` 100% inalterado.

### Microfase APISPERU-1B — Motor Seguro Local-First DNI/RUC e Integración Piloto (D-101)

- **Arquitectura de Integración Externa y Adaptador Técnico:**
  - Creación de `app/Adaptadores/ApisPeruAdaptador.php` para consumo seguro de APIsPERU vía cURL nativo con token en query string (`?token=...`).
  - Protección estricta contra fuga de credenciales: Ni el token, ni el query string, ni la URL completa se imprimen, loguean o exponen en excepciones o mensajes.
  - Timeout configurable con valor defensivo por defecto de 5 segundos (`APISPERU_DNIRUC_TIMEOUT=5`).
  - Soporte de cliente HTTP mock inyectable para pruebas 100% aisladas con 0 consumo de cuota real.
- **Servicio Soberano y Política Local-First:**
  - Implementación de `app/Servicios/ConsultaDocumentoServicio.php` gobernado por la política Local-First: si la persona natural o empresa existe en la base de datos local del PMS (`PersonaRepositorio`, `EmpresaRepositorio`), se retorna con origen `'LOCAL'` sin invocar la red externa.
  - Fallback a APIsPERU únicamente para DNI y RUC cuando no existen localmente (`origen: 'APISPERU'`).
  - Validación estructural estricta: DNI (8 dígitos) y RUC (11 dígitos).
  - Tipos no automatizados (CE, Pasaporte, Otros) quedan formalmente delimitados a registro manual con `origen: 'MANUAL'` y 0 peticiones de red.
  - Normalización no destructiva y cero invención de datos (DNI no inventa género, domicilio ni fecha de nacimiento).
- **Capa Controlador y Enrutamiento Interno:**
  - Creación de `app/Controladores/DocumentoConsultaControlador.php` exponiendo el endpoint interno `GET /api/documentos/consultar`.
  - Registro de la ruta en `public/index.php` protegida mediante `AutenticacionIntermediario`.
- **Frontend y UX Piloto Alina:**
  - Incorporación del método asíncrono universal `CamargoForms.consultarDocumento(tipo, numero, callbacks)` en `public/assets/js/camargo-forms.js`.
  - Integración piloto en Clientes (`app/Vistas/clientes/index.php`): cableado de `#btn-verificar-persona` y botón `#btn-consultar-dni-alta` para DNI. Si es local selecciona la persona; si es APIsPERU autocompleta nombres y apellidos en campos vacíos de alta sin sobreescribir.
  - Integración piloto en Empresas (`app/Vistas/empresas/index.php`): botón `#btn-consultar-ruc` con autocompletado no destructivo y badge informativo `#empresa-sunat-badge` (condición y estado SUNAT) sin alterar el estado operativo interno del emisor.
- **Configuración y Gobernanza:**
  - Variables de plantilla añadidas en `.env.example` (`APISPERU_DNIRUC_TOKEN=`, `APISPERU_DNIRUC_BASE_URL=`, `APISPERU_DNIRUC_TIMEOUT=5`).
  - Soporte en `Configuracion.php` para carga y lectura transparente de variables `APISPERU_*`.
  - Cero DDL: 118 tablas relacionales preservadas; migración `034_agregar_foto_personas.sql` última; ranura `035` libre.
  - Catálogo `admin-dashboard/` 100% intacto.
  - Nueva suite automatizada `tests/test_apisperu_integracion.php` con 73 comprobaciones (73/73 PASS).

### Microfase FIX-PERFIL-1 — Alineación del Contrato de Detalle de Usuario en Perfil (D-100)

- **Corrección de Contrato en Capa Controlador:**
  - En `app/Controladores/PerfilControlador.php`, se reemplazó la invocación al método inexistente `obtenerDetalleCompleto($usuarioId)` por el contrato canónico formal `buscarDetallePorId($usuarioId)` en `UsuarioRepositorio`.
  - En `app/Vistas/perfil/index.php`, se actualizó la referencia de sesiones activas al estándar del repositorio `sesiones_activas` con fallback defensivo `?? 0` (`(int) ($detalleUsuario['sesiones_activas'] ?? 0)`), erradicando claves no estándar (`total_sesiones_activas`) y evitando inferencias artificiales de datos.
- **Inmutabilidad de Persistencia y Base de Datos:**
  - `UsuarioRepositorio.php` permanece 100% inalterado (cero modificaciones).
  - Cero DDL: Base de datos congelada en 118 tablas relacionales; última migración `034_agregar_foto_personas.sql`; ranura `035` estrictamente libre.
- **Calidad y Cobertura:**
  - Nueva suite automatizada `tests/test_fix_perfil_detalle.php` con 21 comprobaciones exhaustivas (21/21 PASS).
  - Actualización de `tests/test_ui_alina_1b_perfil_customizer.php` para validar el contrato canónico `buscarDetallePorId` (46/46 PASS).
  - Regresión global consolidada: 70 suites automatizadas, 1,600 checks, 0 fallos.

### Microfase UI-ALINA-1F-C1 — Corrección de Alcance y Certificación Final

- **Delimitación Estricta de Alcance Arquitectónico:**
  - Reversión íntegra de la modificación en `app/Repositorios/UsuarioRepositorio.php` (el archivo vuelve a su estado idéntico a `b33128e...`).
  - Clasificación de la llamada `$this->usuarioRepo->obtenerDetalleCompleto($usuarioId)` en `PerfilControlador.php` como **HALLAZGO FUNCIONAL — FUERA DE ALCANCE UI-ALINA-1F (CASO A: Defecto preexistente desde UI-ALINA-1B)**, preservando la inmutabilidad de la capa de persistencia en microfases visuales.
- **Matriz de Validación de Pantallas Diferenciada:**
  - Diferenciación explícita entre smoke test HTTP estructural (36 pantallas en runtime HTTPS 200 OK con wrappers Alina) y verificación visual responsive (Desktop/Tablet/Mobile en 12 pantallas críticas de operación, más 6 pantallas de solo código/plantilla).

### Microfase UI-ALINA-1F — Barrido Visual Global, Responsive y Cierre de Homologación Alina (D-099)

- **Barrido Visual y Erradicación Total de Clases Residuales `-subtle`:**
  - Auditoría transversal sobre 42 pantallas operativas y 54 archivos de vista en `app/Vistas/` y scripts JavaScript en `public/assets/js/`.
  - Reemplazo del 100% de clases Bootstrap 5 `-subtle` remanentes (`bg-primary-subtle`, `bg-light-subtle`, `bg-secondary-subtle`, `bg-info-subtle`, `bg-warning-subtle`, `bg-danger-subtle`, `bg-success-subtle`, `border-danger-subtle`, `border-primary-subtle`, `text-light-subtle`) por superficies nativas Alina: `.bg-light-primary`, `.bg-light-secondary`, `.bg-light-info`, `.bg-light-warning`, `.bg-light-danger`, `.bg-light-success`, `.bg-light` y `.text-white-50`.
  - Cero residuos `-subtle` en vistas, código JavaScript propio y reglas CSS.
- **Consistencia de Diálogos Modales y Responsive:**
  - 100% de modales con centrado vertical obligatorio `.modal-dialog-centered` (0 sin centrar).
  - Cero bordes punteados o discontinuos (`0 dotted / 0 dashed`).
  - Iconografía 100% Font Awesome 6 Free (cero iconos ajenos `ti-`, `bi-`, `feather-`).
  - Verificación en vivo (runtime HTTPS sobre Apache) de 36 pantallas con respuesta HTTP 200 OK y estructura canónica Alina (`.app-wrapper`, `.app-navbar`, `.app-content`).
- **Preservación Estricta de Ganancias Acumuladas:**
  - Preservación íntegra de layouts modulares (1A), perfil y customizer Alina (1B/1B-C1), formularios y validación PristineJS (1C), componentes interactivos (1D) y 80 tablas convencionales con sus 16 exclusiones legítimas (1E).
- **Gobernanza y Control de Versiones:**
  - Nueva suite automatizada `tests/test_ui_alina_1f_barrido_final.php` con 24 comprobaciones exhaustivas (24/24 PASS).
  - Regresión consolidada en 69 suites de pruebas, 1,579 checks automatizados y 0 fallos.
  - Cero DDL: Base de datos congelada en exactamente 118 tablas relacionales; última migración `034_*`; ranura `035_*` estrictamente libre.
  - Directorio `admin-dashboard/` 100% intacto, inmutable y de solo lectura.

### Microfase UI-ALINA-1E — Homologación Transversal de Tablas Alina (D-098)

- **Estándar Canónico Bordered + Striped + Hoverable (`.table.table-bordered.table-striped.table-hover.align-middle.mb-0`):**
  - Homologación sistemática de 80 tablas en 30 archivos de vista a lo largo de todos los módulos del PMS.
  - Implementación de `.table-bordered` para delimitación perimetral continua y celdas estructuradas.
  - Implementación de `.table-striped` para alternancia cromática suave (`rgba(var(--light), 0.35)`).
  - Implementación de `.table-hover` para retroalimentación interactiva al pasar el cursor (`rgba(var(--primary), 0.04)`).
  - Alineación vertical obligatoria `align-middle` en celdas y cabeceras.
  - Contenedor `.table-responsive` verificado y garantizado en el 100% de las tablas homologadas.
- **Inventario Semántico y Clasificación:**
  - 59 tablas de datos principales y listados de vistas operativas y administrativas.
  - 4 tablas de configuración y catálogos maestros (`feriados`, `menu`, `roles`, `usuarios`).
  - 17 tablas auxiliares dentro de modales interactivos.
- **Exclusiones Legítimas Justificadas (16 tablas):**
  - 6 tablas de plantilla PDF/impresión A4 (`hoja_reclamacion.php`).
  - 2 grillas interactivas matriciales (`tape-chart-table` y `#tabla-rack`).
  - 8 fichas de metadatos clave-valor con `.table-borderless`.
- **Integridad y Calidad:**
  - Nueva suite automatizada `tests/test_ui_alina_1e_tablas.php`.
  - Cero DDL: Base de datos congelada en exactamente 118 tablas relacionales; última migración `034_*`; ranura `035_*` estrictamente libre.
  - Directorio `admin-dashboard/` 100% intacto y de solo lectura.

### Microfase UI-ALINA-1D — Homologación Transversal de Componentes y Contenedores de Interacción Alina (D-097)

- **Modales y Diálogos Centrados (`modal-dialog-centered`):**
  - Homologación transversal del centrado vertical y dimensionamiento canónico (`modal-sm`, `modal-lg`, `modal-xl`) en todos los modales del sistema (100% de cumplimiento en arrendamientos, gastos, housekeeping, bitácora, reclamaciones, feriados, suministros, clientes, empresas, personal y sesiones).
  - Normalización de cabeceras, divisores (`border-bottom`) y pies de modal con botones de acción sólidos (`.btn-primary`) y botones de cancelación suaves (`.btn-light-secondary`).
- **SweetAlert2 — Delimitación Estricta a Confirmaciones y Avisos:**
  - Adaptación de la paleta corporativa Alina en SweetAlert2 (`.swal2-confirm`, `.swal2-cancel`).
  - Prohibición vinculante de formularios CRUD anidados dentro de SweetAlert; toda captura estructurada reside en modales semánticos HTML o páginas dedicadas validadas con PristineJS.
- **Tooltips Runtime Centralizados:**
  - Inicialización defensiva de tooltips mediante `bootstrap.Tooltip.getOrCreateInstance` en `camargo-forms.js` (`CamargoForms.inicializarTooltips`) y `camargo-layout.js` (`CamargoPMS.inicializarTooltips`).
  - Escucha automática del evento `shown.bs.modal` para refrescar instancias en modales dinámicos.
- **Botones Sólidos y Exclusividad Font Awesome 6:**
  - Estandarización de botones sólidos Alina (`.btn-primary`, `.btn-secondary`, `.btn-light-secondary`, `.btn-light-danger`, `.btn-light-success`).
  - Iconografía 100% Font Awesome 6 Free (`fa-solid`, `fa-regular`, `fa-brands`); prohibición absoluta de iconos heterogéneos (Tabler `ti-`, Bootstrap `bi-`, Feather `feather-`).
- **Acordeones Nativos Alina (`.app-accordion`):**
  - Integración del componente canónico `.accordion.app-accordion`, `.accordion-item`, `.accordion-button.accordion-icon` con chevron rotatorio continuo (ej. información de trazabilidad y auditoría en `clientes/detalle.php`).
- **Erradicación de Clases Bootstrap Crudas (`-subtle`):**
  - Reemplazo integral y sistemático de sintaxis `bg-*-subtle` y `alert-*-subtle` por el estándar Alina: `.bg-light-*` y `.alert-light-*`.
  - Cero bordes punteados o discontinuos (`0 dotted / 0 dashed`) en todos los badges, chips y divisores.
- **Placeholders / Preload Skeleton:**
  - Adopción de esqueletos visuales `.placeholder-glow` con `.placeholder` para cargas asíncronas en lugar de textos planos (`suministros/index.php`, `CamargoForms.crearPlaceholder()`).
- **Progress Bar — Criterio Rector Vinculante:**
  - Documentado explícitamente como **NO APLICA / SIN CASO REAL ACTUAL**, evitando la invención de barras de progreso artificiales sin procesos multifase reales en segundo plano.
- **Verificación y Cobertura:**
  - Nueva suite automatizada `tests/test_ui_alina_1d_componentes.php` con 17 comprobaciones exhaustivas (17/17 PASS).
  - Regresión global del repositorio: 67/67 suites evaluadas, 67/67 PASSED (100%), 0 fallos.
  - Base de datos: exactamente 118 tablas relacionales; migración `034_*` última aplicada; ranura `035_*` estrictamente libre (0 DDL).
  - Catálogo Alina original (`admin-dashboard/`) 100% inalterado y prístino.

### Microfase UI-ALINA-1C — Homologación Transversal de Formularios y Controles Alina (D-096)

- **Estandarización de Formularios Canónicos Alina (`Vertical Form With Icon`):**
  - Implementación transversal del patrón `.app-form.app-icon-form` con `.icon-control.position-relative`, iconos Font Awesome 6 posicionados absolutamente (`top-50 start-0 translate-middle-y ms-3 text-secondary`), padding canónico `.form-control.ps-5` y soporte para áreas de texto (`textarea`) mediante `.icon-control.icon-textarea` (`top-0 start-0 mt-3 ms-3`).
  - Separador sutil vertical de 1px x 20px a 40px del borde izquierdo en `.icon-control::before`.
  - Cero bordes punteados o discontinuos (`border-style: dotted/dashed = 0` en todo el CSS del proyecto).
- **Selectores Select2 Alina y Reglas de Compatibilidad:**
  - Adopción estricta de `.form-select.basic-select2` con altura Alina de 42px (`calc(2.5rem + 2px)`), píldora de 20px de curvatura y flecha chevron Font Awesome (`\f078`).
  - Erradicación y prohibición de combinaciones conflictivas con `.form-select-sm` sobre `basic-select2`, garantizando el cumplimiento al 100% de la suite de fidelidad Alina (`test_ui3a_fidelidad_alina.php`).
- **Controles Basic Switch y File Upload:**
  - Estandarización de conmutadores `.form-check.form-switch.app-switch` y selectores de archivos `.form-control[type="file"]` con feedback visual, cursor interactivo y bordes continuos sólidos.
- **Validación Client-Side Modular con PristineJS y Estados de Carga:**
  - Integración local de PristineJS v1.1.0 (`public/assets/vendor/pristine/pristine.min.js`) y orquestador Vanilla JS `public/assets/js/camargo-forms.js`.
  - Soporte completo de localización en español (`es`) para mensajes de validación reactivos en modales y páginas completas.
  - Gestión transversal de estados de carga en botones mediante `CamargoForms.establecerCargando(boton, texto)` y `CamargoForms.restaurarCargando(boton)` (spinner Font Awesome `fa-solid fa-spinner fa-spin me-2`, atributo `data-texto-original`, deshabilitación para evitar envíos duplicados).
  - Reseteo automático de instancias PristineJS y limpieza de clases de validación en el ciclo de vida de modales Bootstrap 5 (`hidden.bs.modal`).
  - Principio rector: **PRISTINEJS ES MEJORA DE EXPERIENCIA (UX), NO CONTROL DE SEGURIDAD**.
- **Preparación e Inventario Estructural para Futura Integración APIsPERU:**
  - Identificación y categorización de matriz de 9 campos candidatos para consulta DNI/RUC en 6 módulos del sistema (`personal`, `clientes`, `empresas`, `gastos`, `reclamaciones`, `servicios`).
  - Cero llamadas de red externas, cero tokens consumidos y cero dependencias remotas introducidas en esta microfase.
- **Verificación y Cobertura:**
  - Nueva suite automatizada `tests/test_ui_alina_1c_formularios.php` con 24 comprobaciones exhaustivas (24/24 PASS).
  - Regresión global del repositorio: 66/66 suites evaluadas, 66/66 PASSED (100%), 1,511 checks verificados (+24 checks sobre 1B-C1), 0 fallos.
  - Base de datos: exactamente 118 tablas relacionales; migración `034_*` última aplicada; ranura `035_*` estrictamente libre (0 DDL).
  - Catálogo Alina original (`admin-dashboard/`) 100% inalterado y prístino.

### Microfase UI-ALINA-1B-C1 — Persistencia Segura de Fotografía de Persona (D-095)

- **Persistencia y Desacoplamiento de Fotografía (`foto_ruta`):**
  - Consumo formal de la migración `034_agregar_foto_personas.sql` agregando la columna `foto_ruta VARCHAR(255) NULL` a la tabla `personas`. Paridad 100% con `SQL/camargo_pms.sql`.
  - La base de datos mantiene exactamente 118 tablas relacionales y la ranura `035_*` permanece estrictamente libre.
  - Almacena una referencia relativa controlada (`avatars/<id-seguro>.<ext>`), desacoplada de la ruta física del servidor y de URLs absolutas.
  - Resolución limpia: referencia relativa $\to$ almacenamiento público $\to$ URL de presentación (`Ayudante::storage()`, `url_storage()`) con fallback canónico de Alina (`images/avatar/01.png`).
- **Servicio Soberano de Almacenamiento (`FotoPersonaServicio`):**
  - Implementación de `FotoPersonaServicio` con validación estricta de binarios mediante Fileinfo (`image/jpeg` $\to$ `.jpg`, `image/png` $\to$ `.png`). Rechazo absoluto de SVG, GIF, ejecutables y archivos $> 2$ MB.
  - Reemplazo atómico y seguro: validación $\to$ identificador seguro (`random_bytes(16)`) $\to$ guardado físico $\to$ actualización en BD $\to$ confirmación $\to$ eliminación segura de foto anterior. Si falla la BD, se destruye el archivo nuevo huérfano y se preserva intacta la foto previa.
  - Protección contra *path traversal* en borrado y neutralización de rutas relativas maliciosas.
- **Endpoints Autenticados y Controlador Delgado:**
  - Registro de `POST /perfil/foto` y `POST /perfil/foto/eliminar` en `public/index.php`, protegidos por `AutenticacionIntermediario`.
  - Validación obligatoria de CSRF y resolución estricta de la Persona vinculada a la sesión del usuario (se rechaza `persona_id` externo del cliente).
- **Interfaz Alina profile.html y UX Interactivo:**
  - Previsualización dinámica con `FileReader` de cliente, botones de Guardar y Cancelar, estado de carga (spinner), alertas de feedback y opción para restablecer avatar.
- **Verificación y Cobertura:**
  - Nueva suite automatizada `tests/test_ui_alina_1b_c1_foto_persona.php` con 39 comprobaciones rigurosas (100% PASS).
  - Regresión global ampliada: 65/65 suites evaluadas, 65/65 PASSED (100%), 1,487 checks verificados (+39 checks sobre 1B), 0 fallos.

### Microfase UI-ALINA-1B — Perfil de Usuario + Theme Customizer + Flotante Lateral (D-094)

- **Theme Customizer y Flotante Lateral Alina (`app/Vistas/componentes/personalizador.php`):**
  - Restauración del flotante lateral derecho (`.theme-customizer-container`) conforme a `index.html` de Alina con exactamente dos funciones autorizadas: *Configuración de plantilla* (abre el panel offcanvas con Font Awesome `fa-solid fa-gear`) y *Soporte técnico* (`href="#"` con `fa-solid fa-headset`). Erradicación total de *Buy Now*, *ThemeForest* y publicidad comercial.
  - Traducción al español del panel offcanvas (`#offcanvasPersonalizador`): Colores de tema, Disposición de diseño (LTR, RTL, Caja), Variante de barra lateral (Vertical, Horizontal, Oscura) y Escala de texto (Pequeño, Mediano, Grande).
  - Incorporación de botón *Restablecer* centrado (`#btn-restablecer-personalizador`, `btn-danger w-100`) que reinicia instantáneamente las preferencias a los valores oficiales de Camargo PMS (`theme-gradient-1`, `ltr`, `vertical`, `medium-text`) sin requerir recarga forzada.
  - Integración modular y defensiva en `public/assets/js/camargo-layout.js` (`inicializarPersonalizador()`) en Vanilla JS nativo (0 jQuery, 0 Tabler Icons).
  - Persistencia exclusiva en `localStorage` del navegador; cero alteraciones en base de datos, 118 tablas relacionales preservadas y ranura de migración `034_*` estrictamente libre.
  - Inyección de bloque anti-FOUC en `app/Vistas/componentes/head.php` para prevenir parpadeos durante el renderizado.
- **Perfil de Usuario Soberano (`/perfil`) y Separación `PERSONA ≠ USUARIO`:**
  - Incorporación del acceso *Mi Perfil* (`url_ruta('/perfil')`, `fa-solid fa-user`) en el dropdown de cabecera (`app/Vistas/componentes/cabecera.php`).
  - Creación del controlador `app/Controladores/PerfilControlador.php` despachando la ruta protegida por sesión `GET /perfil`.
  - Vista `app/Vistas/perfil/index.php` fiel a `admin-dashboard/alina/template/profile.html` (`.profile-container`, `.profile-pic`, `.avatar-preview`, `#imgPreview`, `.avatar-edit`, `#imageUpload`) con previsualización interactiva de fotografía en Vanilla JS (FileReader API).
  - Respeto riguroso de la ontología de identidad: datos de Persona humana (nombres, apellidos, documentos, contactos, dirección, nacimiento) separados de los datos de cuenta de Usuario (login, estado, roles RBAC, último acceso y cambio de clave). No se duplicó información ni se alteró el maestro de autenticación/sesiones.
- **Verificación y Cobertura:**
  - Nueva suite automatizada `tests/test_ui_alina_1b_perfil_customizer.php` con 46 checks exhaustivos (100% PASS).
  - Regresión global ampliada: 64/64 suites evaluadas, 64/64 PASSED (100%), 1,448 checks verificados (+46 checks), 0 fallos.
  - Catálogo Alina original (`admin-dashboard/`) 100% inalterado y prístino.
  - Se declara STOP PARCIAL para la persistencia en disco/BD de la fotografía a la espera de autorización DDL para la ranura 034.

### Microfase UI-ALINA-1A-C2 — Paridad SQL Consolidado y Activación de Tooltips Alina en Runtime (D-093-C2)

- **Paridad Semántica Completa del Dump Consolidado (`SQL/camargo_pms.sql`):**
  - Incorporación de semillas para permisos `personal.ver` y `personal.gestionar` en el catálogo maestro de permisos de `SQL/camargo_pms.sql`, así como su asignación al rol `SUPERADMINISTRADOR`.
  - Alineación canónica de nombres, descripciones y módulos de permisos de `documentos.*` para coincidir 100% con la migración histórica 021.
  - Certificación de paridad semántica absoluta (100%) entre la instalación limpia de migraciones (`001 -> 033`) y la importación consolidada `camargo_pms.sql` tanto en `opciones_menu` (39 = 39, 9 dominios = 9 dominios, 0 huérfanos) como en `permisos` (137 = 137).
- **Activación Defensiva de Tooltips Alina / Bootstrap 5 en Runtime:**
  - Implementación de `inicializarTooltips()` en `public/assets/js/camargo-layout.js` utilizando la API nativa de Bootstrap 5 (`bootstrap.Tooltip.getOrCreateInstance`), ejecutado dentro del ciclo `iniciar()` tras `DOMContentLoaded`.
  - Reutilización del patrón oficial de Alina (`admin-dashboard/alina/assets/js/tooltips_popovers.js`) de forma 100% defensiva: prevención de inicializaciones duplicadas, sin jQuery, degradación elegante y cero nombres de dominios hardcodeados en JavaScript.
  - Enriquecimiento de accesibilidad en `app/Vistas/componentes/menu-principal.php` mediante atributos `aria-label` y `role="tab"` sin alterar la estructura iconográfica oficial.
- **Verificación y Cobertura:**
  - Incorporación del Grupo 9 en `tests/test_menu_tres_niveles.php` con 7 comprobaciones dedicadas de tooltips, placement, inicialización runtime, API Bootstrap y ausencia de nombres hardcodeados (34/34 PASS).
  - Regresión global del repositorio: 63/63 suites evaluadas, 63/63 PASSED (100%), 1,402 checks verificados (+7 checks), 0 fallos.
  - Base de datos relacional: 118 tablas, última migración 033 registrada, ranura 034 estrictamente libre.

### Microfase UI-ALINA-1A-C1 — Corrección Canónica: Migración 033, Reubicación Operativa/Financiera y Tooltips Alina (D-093-C1)

- **Migración Canónica de Datos 033 (`SQL/migraciones/033_reorganizar_menu_alina.sql`):**
  - Versionamiento formal y reproducible de la reorganización de menú en 9 dominios canónicos y 39 opciones.
  - Cero modificaciones DDL (tabla `opciones_menu` inalterada; exactamente 118 tablas en el motor relacional).
  - Idempotencia total (`INSERT ... ON DUPLICATE KEY UPDATE` y `UPDATE`) con soporte asegurado para permisos y opciones de sesiones y personal.
  - Reproducibilidad bidireccional certificada: idéntico resultado tanto en ruta de actualización (`032 -> 033`) como en instalación limpia desde cero (`001 -> 033`).
  - Ranura de migración `034_*` estrictamente libre y disponible.
- **Reubicación Operativa y Financiera:**
  - `Estadías / Check-in` (`/estadias`, orden 1) y `Servicios y Consumos` (`/servicios`, orden 2) reubicados desde *Comercial y Reservas* al dominio **Operaciones**.
  - `Auditoría Nocturna` (`/operaciones/night-audit`, orden 4) reubicada desde *Operaciones* al dominio **Caja y Finanzas**.
  - Orden canónico en Operaciones: Estadías (1), Servicios (2), Housekeeping (3), Mantenimiento (4), Libro de Guardia (5).
  - Orden canónico en Caja y Finanzas: Caja y Cuentas (1), Recibos (2), Gastos (3), Auditoría Nocturna (4).
  - Orden canónico en Comercial y Reservas: Tape Chart (1), Reservas (2), Clientes (3), Arrendamientos (4).
- **Usabilidad y Accesibilidad Alina:**
  - Incorporación de tooltips nativos Bootstrap/Alina (`data-bs-toggle="tooltip"`, `data-bs-placement="right"`) en todos los enlaces de dominios principales de la barra lateral vertical N1 (`app/Vistas/componentes/menu-principal.php`).
- **Verificación y Cobertura:**
  - Suite de menú de 3 niveles actualizada: `tests/test_menu_tres_niveles.php` (27/27 PASS, 100%).
  - Regresión global del repositorio: 63 suites evaluadas, 63/63 PASSED (100%), 0 fallos.

### Microfase UI-ALINA-1A — Gobernanza + Menú Dinámico Real de 3 Niveles + Reorganización Funcional (D-093)

- **Gobernanza del Sistema de Diseño Alina:**
  - Consagración del principio superior: **ALINA ES EL SISTEMA DE DISEÑO DE CAMARGO PMS**. Bootstrap 5 actúa como infraestructura interna, no como catálogo visual alternativo.
  - Prohibición transversal vinculante del estilo `border-style: dotted/dashed` en cualquier componente propio.
  - Documentación del catálogo canónico de 23 componentes de referencia en `FRONTEND.md` y `PLANTILLA-ALINA.md`.
- **Evolución a Menú Dinámico de Hasta 3 Niveles:**
  - Soporte de 3 niveles reales en el motor de navegación: Nivel 1 (Dominio Principal), Nivel 2 (Módulo), Nivel 3 (Función/Submódulo).
  - Profundidad máxima acotada estrictamente a 3 niveles: rechazo de nivel 4+ con `NivelMenuInvalidoExcepcion` en backend y controles en interfaz.
  - Detección y rechazo de ciclos y autorreferencias ($A \to A$, $A \to B \to A$).
  - Prevención de desbordamiento de profundidad en movimientos de subárboles.
  - Renderizado nativo Alina en `menu-secundario.php` con clases `another-level` y colapso ordenado `data-bs-toggle="collapse"`.
  - Propagación de estado activo (active state) en cascada desde la función de nivel 3 hasta el dominio de nivel 1.
  - Ordenamiento independiente y transaccional entre hermanos bajo el mismo padre.
  - Acciones contextuales acotadas: Nivel 1 agrega hijo Nivel 2; Nivel 2 agrega hijo Nivel 3; Nivel 3 no permite agregar hijos.
- **Reorganización Funcional de la Navegación en 9 Dominios:**
  - Descongestión del catálogo histórico de *Reservas*, distribuyendo las 27+ opciones en 9 dominios operativos: `INICIO`, `CONFIGURACIÓN`, `PROPIEDADES`, `COMERCIAL Y RESERVAS`, `OPERACIONES`, `CAJA Y FINANZAS`, `ABASTECIMIENTO`, `DOCUMENTOS` y `ATENCIÓN AL CLIENTE`.
  - Separación formal de responsabilidades: $\text{COMPRA} \neq \text{PAGO} \neq \text{MOVIMIENTO DE CAJA}$.
  - Preservación 100% de rutas URL, controladores, intermediarios y contratos de dominio existentes sin rotura de bookmarks.
  - Modelo autorreferenciado de `opciones_menu` sin alteraciones DDL; 118 tablas mantenidas y ranura 033 estrictamente libre.

### Microfase RECLAMACIONES-1A — Throttling Progresivo No Impeditivo en Canal Público (D-092)

- **Defensa Progresiva No Impeditiva (`ANTIABUSO ≠ DENEGACIÓN DEL DERECHO A RECLAMAR`):**
  - Incorporación del servicio soberano `ProteccionFormularioPublicoServicio` (`app/Servicios/ProteccionFormularioPublicoServicio.php`).
  - Escala de micro-retardo gradual sin bloqueo permanente:
    - Intento 1 (Cortesía normal): 0 ms de retardo (nivel 0).
    - Intento 2: 250 ms.
    - Intento 3: 500 ms.
    - Intento 4: 1,000 ms (1.0 s).
    - Intento 5 o más: 2,000 ms (techo máximo acotado para evitar agotamiento de workers PHP/Apache).
  - Cero respuestas HTTP 429: ningún usuario legítimo es bloqueado ni privado del derecho legal a presentar una reclamación.
- **Aislamiento de Sesión vs IP Compartida (`IP ≠ IDENTIDAD`):**
  - El mecanismo se apoya estrictamente en el contexto de sesión PHP (`$_SESSION['_proteccion_formulario_reclamaciones']`) con ventana rodante móvil de 60 segundos.
  - Las redes compartidas (NAT, Wi-Fi del establecimiento, cibercafés, oficinas y redes móviles) no son castigadas colectivamente por la actividad de otros usuarios.
  - Decaimiento y recuperación natural: al transcurrir la ventana rodante, el nivel de penalización se reduce y vuelve a nivel 0.
- **Privacidad y Cero Telemetría en Expediente:**
  - Garantía estricta de que no se almacena IP, User-Agent ni fingerprint en `reclamaciones`, `reclamacion_actuaciones` ni en los snapshots legales T0 (`snapshot_consumidor_json`, `snapshot_proveedor_json`).
- **Prevención de Doble Clic:**
  - Deshabilitación reactiva del botón de envío (`Interponer Reclamación`) en el formulario cliente mediante JavaScript ante el primer envío válido.
- **Certificación y Cobertura Automatizada:**
  - Suite unitaria y de dominio dedicada `tests/test_reclamaciones_throttling.php` (15/15 PASS, con inyección determinista de reloj y retardador sin dependencias de esperas lentas).
  - Caso E2E HTTP real añadido en `tests/test_e2e_reclamaciones.php` (E2E-REC-07B, 24/24 PASS).
  - Regresión global del sistema: 62 suites evaluadas, 62/62 PASSED (100%), 1,368 checks verificados, 0 fallos.

### Microfase RECLAMACIONES-1 — Libro de Reclamaciones Peruano Integral (D-092)

- **Marco Regulatorio y Cómputo Vinculante:**
  - Implementación integral según la normativa peruana: Ley 29571 (Código de Protección y Defensa del Consumidor), D.S. 011-2011-PCM, Ley 31435 y Ley 32495 (plataformas digitales y comercio electrónico).
  - Plazo legal de respuesta: 15 días hábiles improrrogables (computados a partir del primer día hábil siguiente a la presentación, excluyendo sábados, domingos y feriados nacionales con `aplica_sector_privado = 1`).
  - Suspensión temporal (D.S. 101-2022-PCM): Hasta 5 días hábiles cuando la empresa formula un ofrecimiento formal de solución y espera aceptación/rechazo expreso del consumidor; reanudación automática de los días hábiles restantes ante rechazo o expiración del plazo.
- **Arquitectura de Superficie Dual y Resiliencia:**
  - **Portal Público Digital:** Formulario interactivo responsive en `/libro-reclamaciones` sin requerir autenticación previa, con protección anti-abuso mediante Honeypot y token CSRF con inicialización de sesión transparente. Generación inmediata de constancia con descarga de Hoja de Reclamación en PDF (`/libro-reclamaciones/confirmacion` y `/libro-reclamaciones/descargar-pdf`).
  - **Consola Interna Administrativa Alina:** Módulo operativo en `/reclamaciones` protegido por RBAC (`reclamaciones.ver`, `reclamaciones.crear`, `reclamaciones.responder`, `reclamaciones.anular`), soporte para registro asistido de reclamos presenciales/telefónicos, bitácora cronológica append-only de actuaciones (`reclamacion_actuaciones`), formulación y respuesta de ofrecimientos de solución y respuestas finales normativas.
  - **Gestión Soberana de Calendario Laboral y Feriados:** Módulo `/configuracion/feriados` con permiso `configuracion.feriados.gestionar` para la administración indefinida de calendarios laborales (alta y alternancia activo/inactivo).
- **Axioma SNAPSHOT T0 e Inmutabilidad de la Evidencia:**
  - Al asentar la reclamación en base de datos, se capturan snapshots JSON inmutables del consumidor (`snapshot_consumidor_json`, incluyendo datos de apoderado en caso de menores de edad) y del proveedor/establecimiento (`snapshot_proveedor_json`). Las modificaciones posteriores en personas, clientes o empresas no mutan la evidencia legal registrada.
- **Axioma CERO DELETE y Trazabilidad Append-Only:**
  - Prohibición categórica de borrado físico (`DELETE`). Toda acción (creación, notas internas, ofrecimientos, respuestas formales, anulaciones supervisadas) genera una actuación histórica en `reclamacion_actuaciones`.
  - La anulación supervisada exige motivo justificado ($\ge 10$ caracteres) y supervisor responsable, sin alterar el correlativo ni borrar el expediente.
- **Emisión de PDF Resiliente (Dompdf 3.x):**
  - Asiento transaccional previo en BD antes del renderizado de la Hoja de Reclamación. Si el motor PDF fallara, la reclamación queda intacta y segura, permitiendo su regeneración en cualquier momento (`ReclamacionDocumentoServicio`).
- **Evolución Controlada de Esquema:**
  - Consumo formal de la migración `SQL/migraciones/032_reclamaciones_libro.sql` creando 4 tablas relacionales: `calendario_feriados`, `reclamacion_secuencias`, `reclamaciones` y `reclamacion_actuaciones`.
  - El catálogo de base de datos evoluciona exactamente de 114 a 118 tablas. Ranura `033_*` estrictamente libre.
  - Consolidado `SQL/camargo_pms.sql` completamente sincronizado (Sección 30).
  - Clean install 001 → 032 verificado con paridad absoluta de 118 tablas.
- **Certificación Automatizada Exhaustiva (63/63 PASS en el dominio, 61/61 suites globales):**
  - Matriz de Dominio y Axiomas: `tests/test_reclamaciones_matriz_40.php` (40/40 PASS).
  - End-to-End HTTP / Interfaz Dual: `tests/test_e2e_reclamaciones.php` (23/23 PASS).
  - Regresión Global de la Suite Completa: 61 suites ejecutadas, 61/61 PASS (100%), 1,352 aserciones verificadas (0 fallos).
  - Confinamiento local estricto: Cero dependencias CDN externas en vistas, asegurando fidelidad visual nativa de Alina (`test_ui3a_fidelidad_alina.php` 70/70 PASS).

### Microfase DEVENGO-ALOJAMIENTO-1 — Devengo Diario de Alojamiento, Libro Diario y Auditoría Nocturna (D-090)

- **Axioma Ontológico Quíntuple:**
  - Consagración del principio vinculante: $\text{RESERVA} \neq \text{ESTADÍA} \neq \text{DEVENGO} \neq \text{CARGO} \neq \text{PAGO}$.
  - El devengo diario reconoce de forma soberana el hecho económico del servicio de alojamiento prestado noche a noche para cada fecha hotelera en el intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$.
  - La fecha de salida prevista no devenga noche de alojamiento.
- **Jerarquía Tarifaria y Absorción Determinista de Redondeo:**
  - Prioridad 1: Tarifa pactada explícita por noche en snapshot de reserva.
  - Prioridad 2: Tarifa base uniforme cuando la multiplicación exacta coincide con el total.
  - Prioridad 3 (Fallback Contractual Determinista): En caso de residuo fraccionario de céntimos ($100.00 / 3$), las noches regulares devengan la base truncada ($33.33$) y la primera noche (`noche_indice = 1`) absorbe el céntimo residual ($33.34$) con método `AJUSTE_RESIDUAL`, garantizando cuadre estricto al céntimo.
  - Aritmética 100% `BCMath` con escala 2 y cero tipos de punto flotante (`FLOAT`/`DOUBLE`) prohibidos en el esquema.
- **Auditoría Nocturna (Night Audit) y Cierre de Fecha Hotelera:**
  - Entidad `CierreHotelero` con unicidad estricta `(propiedad_id, fecha_hotelera)` y estados `EN_PROCESO`, `CERRADO`, `FALLIDO`.
  - Congelamiento inmutable de inventario vendible: unidades totales, OOO (fuera de orden por mantenimiento bloqueante), vendibles netas y habitaciones vendidas/cortesía.
  - Cálculo soberano no estimado de métricas hoteleras: $\text{ADR} = \text{Ingreso Neto} / \text{Vendidas}$ y $\text{RevPAR} = \text{Ingreso Neto} / \text{Vendibles Netas}$.
  - Protección ante re-ejecución sobre fechas ya cerradas y bloqueo pesimista `SELECT ... FOR UPDATE`.
- **Inmutabilidad y Reversiones Supervisadas (Append-Only):**
  - Prohibición categórica de borrado físico (`DELETE`). Reversión supervisada obligatoria con motivo formal ($\ge 5$ caracteres), cambiando a `REVERTIDO` e incrementando la secuencia en re-devengos posteriores (`secuencia = 2`, `secuencia = 3`, etc.).
  - Inmutabilidad histórica de devengos pasados frente a checkout posterior, cancelaciones o acortamiento de estadía.
- **Evolución Controlada de Esquema:**
  - Migración `SQL/migraciones/028_devengo_alojamiento.sql` creando `cierres_hoteleros` y `devengos_alojamiento`.
  - El catálogo de base de datos evoluciona exactamente de 106 a 108 tablas. Ranura `029_*` estrictamente libre.
  - Sincronización completa con `SQL/camargo_pms.sql` (0 diferencias).
- **Aislamiento RBAC y Navegación Alina:**
  - 5 permisos atómicos: `night_audit.ver`, `night_audit.ejecutar`, `devengo.ver`, `devengo.ejecutar`, `devengo.revertir`.
  - Opción de menú en Alina: `operaciones_night_audit` (`/operaciones/night-audit`).
  - Consola web operativa Alina con 4 tarjetas KPI, selector de sede, formulario de ejecución y tabla histórica de cierres.
  - Endpoints REST con validación CSRF y protección de sesión.
- **Integración con Reportes (MDR):**
  - Actualización de `ReporteServicio` y `ReporteRepositorio` para consumir métricas auditadas desde `cierres_hoteleros` y `devengos_alojamiento`.
  - Preservación del estado `DIFERIDO_A_DEVENGO_ALOJAMIENTO_1` para fechas históricas pre-cutover sin devengos.
- **Certificación Automatizada Exhaustiva (85/85 PASS):**
  - Matriz de dominio y axiomas: `tests/test_devengo_matriz_40.php` (40/40 PASS).
  - Aritmética de precisión, redondeo y métricas: `tests/test_devengo_calculo_20.php` (20/20 PASS).
  - Concurrencia, bloqueo pesimista y resiliencia: `tests/test_devengo_concurrencia.php` (15/15 PASS).
  - End-to-End HTTP/API: `tests/test_e2e_devengo.php` (10/10 PASS).
  - Regresión global de la suite: 52 suites ejecutadas, 52/52 PASS (100%), 1103 aserciones verificadas (0 fallos).

### Microfase BITÁCORA-1 — Libro de Guardia y Bitácora Operacional (D-089)

- **Axioma Ontológico Hexagonal:**
  - Consagración del principio vinculante: $\text{BITÁCORA} \neq \text{AUDITORÍA TÉCNICA (D-061)} \neq \text{TURNO CAJA} \neq \text{MANTENIMIENTO} \neq \text{HOUSEKEEPING}$.
  - El libro de guardia captura el relato operativo humano, consignas entre relevos, avisos generales y novedades circunstanciales de los turnos.
- **Inmutabilidad del Relato y Enmiendas Append-Only:**
  - El texto original de una novedad registrada en `bitacora_entradas` es inmutable desde su inserción.
  - Toda aclaración, ampliación o corrección de hechos se registra estrictamente de forma cronológica append-only en `bitacora_seguimientos` con tipos `COMENTARIO` y `ENMIENDA`.
- **Axioma ANULAR $\neq$ DELETE:**
  - Queda prohibido el borrado físico (`DELETE`) de registros en el libro de guardia.
  - La anulación supervisada exige motivo justificado ($\ge 5$ caracteres), registra el supervisor y fecha, cambia el estado a `ANULADA` y emite un seguimiento normativo tipo `ANULACION`, conservando intacta la trazabilidad histórica.
- **Ciclo de Vida de Consignas e Incidencias:**
  - Estados soportados: `REGISTRADA`, `PENDIENTE`, `EN_PROCESO`, `RESUELTA`, `ANULADA`.
  - Resolución formal con descargo obligatorio mediante `resolverEntrada()`.
  - Reapertura controlada de novedades resueltas con motivo explícito mediante `reabrirEntrada()`.
- **Evolución Controlada de Esquema:**
  - Consumo formal de la migración `027_bitacora_guardia.sql` creando dos tablas: `bitacora_entradas` y `bitacora_seguimientos`.
  - Total de tablas en el esquema oficial de Camargo PMS pasa exactamente de 104 a 106 tablas.
  - Ranura `028_*` libre para futuras microfases.
  - Consolidado `SQL/camargo_pms.sql` completamente sincronizado para instalaciones limpias (*clean install*).
- **Aislamiento RBAC y Navegación Alina:**
  - Permisos atómicos en módulo `operaciones`: `bitacora.ver`, `bitacora.crear`, `bitacora.seguir`, `bitacora.resolver`, `bitacora.anular`.
  - Opción de menú registrada en Alina: `operaciones_bitacora` (Libro de Guardia en `/operaciones/bitacora` bajo la sección `reservas`).
  - Intermediarios responden con HTTP 401 opaco ante peticiones no autenticadas y HTTP 403 ante carencia de permisos o fallo de token CSRF.
- **Consola Operativa Alina y Endpoints JSON:**
  - Vista `/operaciones/bitacora` con 4 KPIs (Novedades Hoy, Pendientes/En Proceso, Urgencias Activas, Consignas e Incidencias), filtros reactivos por sede, tipo, prioridad, turno, estado, fecha y texto.
  - Modales dedicados para creación, seguimiento append-only, cambio de estado, resolución, reapertura y anulación supervisada.
  - Endpoints REST protegidos: `GET /api/operaciones/bitacora`, `GET /api/operaciones/bitacora/{id}`, `GET /api/operaciones/bitacora/metricas`, `POST /api/operaciones/bitacora`, `POST /api/operaciones/bitacora/{id}/seguimiento`, `POST /api/operaciones/bitacora/{id}/estado`, `POST /api/operaciones/bitacora/{id}/resolver`, `POST /api/operaciones/bitacora/{id}/reabrir`, `POST /api/operaciones/bitacora/{id}/anular`.
- **Certificación Automatizada Exhaustiva (80/80 PASS):**
  - Matriz de dominio y axiomas: `tests/test_bitacora_matriz_40.php` (40/40 PASS).
  - Seguridad, RBAC, CSRF, inyección y Auditoría D-061: `tests/test_bitacora_seguridad.php` (20/20 PASS).
  - Concurrencia, aislamiento y append-only: `tests/test_bitacora_concurrencia.php` (10/10 PASS).
  - End-to-End HTTP/API: `tests/test_e2e_bitacora.php` (10/10 PASS).
  - Regresión integral de la suite completa: 48 suites ejecutadas, 48/48 PASS (100%), 1018 aserciones verificadas.
- **Herramienta Soberana de Recuperación de Acceso Administrativo (CLI):**
  - Incorporación del script formal `bin/restablecer-contrasena-usuario.php` exclusivo para entorno CLI (`PHP_SAPI === 'cli'`), con guarda estricta HTTP 403.
  - Implementación del método de dominio `UsuarioServicio::restablecerContrasenaAdministrativa()` con política obligatoria $\ge 12$ caracteres UTF-8, algoritmo estándar `PASSWORD_DEFAULT` (bcrypt), revocación inmediata de todas las sesiones previas activas (`REVOCACION_ADMINISTRATIVA`), y emisión de auditoría D-061 sin almacenar contraseñas ni hashes en claro.
  - Suite de verificación permanente `tests/test_recuperacion_contrasena_cli.php` (12/12 PASS).

### Microfase SESIONES-1 — Monitoreo, Presencia y Revocación Administrativa de Sesiones (D-088)

- **Axioma Ontológico Hexagonal:**
  - Consagración del cuarteto ontológico vinculante: $\text{SESIÓN PHP} \neq \text{REGISTRO DE SESIÓN} \neq \text{USUARIO ACTIVO} \neq \text{PRESENCIA RECIENTE}$.
  - La sesión PHP de runtime (cookie/PHPSESSID) es un mecanismo de transporte efímero y desacoplado del registro de persistencia en `sesiones_usuario`.
  - Un usuario activo en catálogo puede sostener 0 o $N$ sesiones concurrentes. Desactivar un usuario revoca sus sesiones de inmediato pero la revocación de una sesión no desactiva al usuario.
- **Relojes de Expiración Independientes:**
  - Expiración por inactividad ($30\text{ min}$ deslizante con throttling de persistencia a $60\text{ s}$).
  - Expiración absoluta ($12\text{ h}$ fija desde `iniciada_en` computada soberanamente).
  - Presencia reciente ($\le 15\text{ min}$ desde última actividad HTTP) como heurística de supervisión visual que no constituye un tercer timeout ni muta el estado de validez de la sesión.
- **Estados Soberanos y Motivos Normativos:**
  - Estados soberanos: `ACTIVA`, `EXPIRADA_INACTIVIDAD`, `EXPIRADA_ABSOLUTA`, `REVOCADA`.
  - Motivos normativos: `LOGOUT`, `EXPIRACION_INACTIVIDAD`, `EXPIRACION_ABSOLUTA`, `REVOCACION_ADMINISTRATIVA`, `CAMBIO_CONTRASENA`, `CAMBIO_ESTADO_USUARIO`, `DESACTIVACION_PERSONA`.
  - Irreversibilidad estricta: una sesión revocada o cerrada no puede reactivarse.
- **Aislamiento RBAC y Autorrevocación Defensiva:**
  - Permisos atómicos dedicados en módulo `seguridad`: `sesiones.ver` y `sesiones.revocar` (asignados al rol `SUPERADMINISTRADOR`). El permiso `usuarios.editar` no confiere facultades de revocación de sesiones.
  - Intermediarios `AutenticacionIntermediario` y `AutorizacionIntermediario` responden con `HTTP 401 Unauthorized` y JSON opaco (`codigo: SESION_NO_VALIDA`, `error: La sesión ya no es válida.`) ante peticiones asíncronas no autenticadas, previniendo fuga de detalles internos.
  - Autorrevocación administrativa: cuando un usuario revoca su propia sesión en curso, se revoca en base de datos, se registra auditoría inmutable D-061 (`CERRAR_SESION`), se destruye la sesión PHP local y se invalidan las cookies.
  - Idempotencia: la revocación de una sesión ya revocada retorna éxito seguro con `ya_revocada = true`.
- **Economía de Esquema Estricta:**
  - Cero tablas nuevas: se preservan exactamente las 104 tablas preexistentes del esquema de Camargo PMS.
  - Cero migraciones nuevas: la ranura `027_*` permanece libre.
- **Consola Alina y Endpoints JSON:**
  - Vista `/seguridad/sesiones` con 4 KPIs en tiempo real (Sesiones Activas, Actividad Reciente, Expiradas, Revocadas), filtros por estado, presencia, búsqueda y paginación reactiva.
  - Endpoints REST: `GET /api/seguridad/sesiones`, `GET /api/seguridad/sesiones/metricas`, `POST /api/seguridad/sesiones/{id}/revocar`, `POST /api/seguridad/sesiones/usuario/{id}/revocar-todas`, `POST /api/seguridad/sesiones/purgar-expiradas`.
- **Certificación Automatizada Exhaustiva (80/80 PASS):**
  - Matriz de dominio y axiomas: `tests/test_sesiones_matriz_40.php` (40/40 PASS).
  - Seguridad, RBAC, CSRF y Auditoría D-061: `tests/test_sesiones_seguridad.php` (20/20 PASS).
  - Concurrencia, multi-sesión e invariantes: `tests/test_sesiones_concurrencia.php` (10/10 PASS).
  - End-to-End HTTP/API: `tests/test_e2e_sesiones.php` (10/10 PASS).
  - Regresión integral de la suite completa: 42 suites ejecutadas, 42/42 PASS (100%).

- **Axioma Ontológico Hexagonal:**
  - Consagración del axioma $\text{ESTADO COMERCIAL} \neq \text{ESTADO DE OCUPACIÓN} \neq \text{ESTADO DE LIMPIEZA} \neq \text{DISPONIBILIDAD} \neq \text{MANTENIMIENTO}$.
  - Derivación dinámica en vivo: $\text{UNIDAD LISTA PARA CHECK-IN} = \text{resultado operacional derivado}$ (VR - Vacant Ready).
  - Cero columnas redundantes en BD: `VR`, `VD`, `VCL`, `OD`, `OC`, `OOO`, `OOS` son proyecciones computadas por el DTO `HousekeepingDerivacionOperativa` sin desincronización posible.
- **Acoplamiento Atómico de Check-out e Idempotencia:**
  - Finalización de estadía, marcación de unidad a `SUCIA` y generación de tarea de `SALIDA` en estado `PENDIENTE` consolidados en la misma transacción PDO de `EstadiaServicio::realizarCheckout()`.
  - Idempotencia estricta: múltiples invocaciones sobre la misma estadía devuelven la tarea existente sin duplicar órdenes de trabajo.
- **Check-in Hotelero Estricto (Cero Bypass):**
  - `validarAptaParaCheckin()` inyectado en `EstadiaServicio::realizarCheckin()`.
  - Bloqueo absoluto de check-in si la habitación no está `LIMPIA_INSPECCIONADA` (VR) lanzando `UnidadNoListaExcepcion` (HTTP 409) con detalle exhaustivo de causa (ocupada, sucia, en limpieza, vcl o mantenimiento).
- **Checklists Versionados con Snapshots Inmutables:**
  - Clonado inmutable de puntos de control hacia `housekeeping_tarea_checklist` en el momento de crear la tarea.
  - Evaluación tri-valente estricta (`CONFORME`, `NO_CONFORME`, `NO_APLICA`).
  - Puntos de control críticos: si algún ítem crítico está `NO_CONFORME`, el sistema impide la aprobación formal mediante `ValidacionHousekeepingExcepcion` (422) forzando el envío a reproceso (`RECHAZADA` / `RETOQUE_REQUERIDO`).
- **Integración Atómica con Kardex e Inventario (INVENTARIO-1):**
  - Débito automático de amenities (jabón, shampoo, kits dentales) desde `almacen_origen_id` mediante `SALIDA_CONSUMO` en `inventario_movimientos` y descuento seguro sin saldos negativos en `inventario_existencias`.
  - Trazabilidad con clave foránea `inventario_movimiento_id` en `housekeeping_tarea_consumos`.
- **Circuito Textil de Lavandería en Custodia Externa:**
  - Registro de lotes de lavandería con folios correlativos `LAV-YYYYMMDD-XXXX`.
  - Doble pata de Kardex (`Office -> Custodia Externa`) con custodia técnica de prendas de blancos y lencería.
  - Retorno con desglose formal de prendas limpias recibidas y bajas por merma irrecuperable.
  - Preservación explícita de discrepancias mediante columna virtual `cantidad_diferencia GENERATED ALWAYS AS (cantidad_enviada - (cantidad_recibida + cantidad_baja_merma)) VIRTUAL` y estado `CON_DISCREPANCIA`.
- **Persistencia Relacional (Migración 025_housekeeping.sql):**
  - Creación de 9 tablas relacionales (tablas 81 a 89): `housekeeping_unidades_limpieza`, `housekeeping_tareas`, `housekeeping_tarea_checklist`, `housekeeping_tarea_consumos`, `housekeeping_tarea_historial`, `housekeeping_lotes_lavanderia`, `housekeeping_lote_lineas`, `housekeeping_checklist_plantillas`, `housekeeping_checklist_plantilla_items`.
  - Folios correlativos `HK-YYYYMMDD-XXXX` y `LAV-YYYYMMDD-XXXX` administrados en `documento_secuencias` con `FOR UPDATE`.
  - Permisos RBAC (`housekeeping.ver`, `housekeeping.tareas.gestionar`, `housekeeping.limpieza.ejecutar`, `housekeeping.inspeccion.ejecutar`, `housekeeping.lavanderia.gestionar`, `housekeeping.reportes.ver`) y opción de menú Alina.
- **Interfaz Gráfica Alina (D-075 / D-076):**
  - Vista modular en `/housekeeping` con 4 KPIs operacionales (VR, VD, VCL, OOO), Rack Operacional en vivo, pestaña de Tareas con modales de acción rápida y pestaña de Lotes de Lavandería Textil.
  - Script Vanilla `public/assets/js/gestion-housekeeping.js` con SweetAlert2 y Fetch JSON.
- **Verificación Automatizada Exhaustiva (60/60 PASS):**
  - Matriz de dominio y reglas de negocio: `tests/test_housekeeping_matriz_40.php` (40/40 PASS).
  - Concurrencia, locking e integridad física: `tests/test_housekeeping_concurrencia.php` (6/6 PASS).
  - Flujo HTTP E2E real: `tests/test_e2e_housekeeping.php` (14/14 PASS).

## [439b16f] - 2026-09-27

### Microfase RECIBOS-1 — Emisión de Recibos de Cobranza, Snapshots Financieros $T_0$, Preservación Criptográfica Inmutable e Integración con FINANCIERO-2 y DOCUMENTOS-1 (D-082)

- **Axioma Ontológico Hexagonal:**
  - Consagración del axioma $\text{CARGO} \neq \text{PAGO} \neq \text{APLICACIÓN} \neq \text{RECIBO} \neq \text{PDF}$.
  - Definición vinculante: $\text{RECIBO} = \text{CONSTANCIA HISTÓRICA INMUTABLE DE UN HECHO DE COBRO}$. No es una fotografía mutable del estado vivo de la cuenta corriente.
- **Congelamiento Temporal en $T_0$:**
  - Cada recibo congela la realidad económica del instante exacto de su emisión: cargos amortizados, saldo restante del cargo en $T_0$ (`cargo_saldo_restante`), e importe total adeudado del folio en $T_0$ (`folio_saldo_pendiente_historico`).
  - Los abonos o reliquidaciones posteriores en $T_1$ devengan sus propios recibos y no mutan retroactivamente los recibos emitidos en $T_0$.
- **Ecuación Contable Inviolable y Soporte de Fondos No Aplicados:**
  - Ecuación universal satisfecha: $\text{monto\_recaudado} = \text{monto\_imputado} + \text{monto\_no\_aplicado\_pago}$.
  - Soporte de recibos para pagos con saldo a favor o anticipos sin imputación previa (`monto_imputado = 0.00`, `monto_no_aplicado_pago > 0.00`), generando documento formal con leyenda institucional de custodia en cuenta.
- **Garantías Segregadas y Depósitos en Custodia:**
  - Los recibos por depósitos de garantía segregan fielmente el concepto (`cargo_origen_tipo = DEPOSITO_GARANTIA`), preservando la naturaleza no operativa del fondo en custodia contractual.
- **Unicidad Relacional Estricta:**
  - Regla: 1 Pago Confirmado = Máximo 1 Recibo Activo.
  - Implementada físicamente en InnoDB mediante la columna virtual generada `recibo_activo_idx BIGINT UNSIGNED GENERATED ALWAYS AS (IF(estado = 'EMITIDO', pago_id, NULL)) VIRTUAL` y la restricción `UNIQUE KEY uq_rec_pago_activo (recibo_activo_idx)`.
- **Inviolabilidad Física y Criptográfica del PDF Soberano:**
  - Integración nativa con `DOCUMENTOS-1` y Dompdf 3.1.6 sin dependencias CDN ni esquemas de red remotos.
  - Generación de PDF A4 con membrete institucional, formato de moneda exacto `S/ #,##0.00`, hash criptográfico `hash_pdf_sha256` y correlativo atómico `REC-YYYYMM-XXXX`.
  - La anulación formal transiciona el registro a estado `ANULADO`, registrando actor y motivo; el archivo PDF físico en disco y su hash SHA-256 jamás se sobreescriben ni mutilan (inviolabilidad de la evidencia histórica).
- **Desacople Operativo con FINANCIERO-2:**
  - Anular un recibo NO revierte el pago en caja/folio.
  - Si un pago se reversa en FINANCIERO-2, el recibo probatorio emitido no se elimina de la base de datos (preservación histórica auditable).
- **Persistencia Relacional (Migración 024_recibos.sql):**
  - Creación de tablas `recibos` (tabla 79) y `recibo_lineas` (tabla 80), con índices de búsqueda rápida, constraints de integridad referencial (`ON DELETE RESTRICT`) y columna virtual de unicidad.
  - Folios atómicos concurrency-safe (`REC-YYYYMM-XXXX`) mediante `documento_secuencias` con `FOR UPDATE`.
  - Permisos RBAC (`recibos.ver`, `recibos.emitir`, `recibos.anular`, `recibos.descargar`) y opción de menú Alina bajo Menú Reservas/Finanzas.
- **Interfaz Alina Conforme a D-075 y D-076:**
  - Módulo en `/recibos` con 4 KPIs en vivo (Emitidos, Recaudado Total, Anulados, Pagos Pendientes de Recibo).
  - Modales reactivos `modal-emitir-recibo`, `modal-detalle-recibo`, `modal-anular-recibo`, maquetados con `app-form app-icon-form`, bordes píldora `b-r-20` y Select2 42px.
  - Controlador JavaScript `public/assets/js/gestion-recibos.js` con SweetAlert2 y Fetch asíncrono.
- **Verificación Automatizada Exhaustiva (60/60 PASS):**
  - Matriz de dominio y reglas de negocio: `tests/test_recibos_matriz_40.php` (40/40 PASS).
  - Concurrencia e integridad transaccional: `tests/test_recibos_concurrencia.php` (6/6 PASS).
  - Flujo HTTP E2E real contra Apache HTTPS: `tests/test_e2e_recibos.php` (14/14 PASS).

## [75e5a5e] - 2026-09-27

### Microfase SUMINISTROS-1 — Servicios Básicos, Medidores, Tarifas con Vigencia Histórica e Imputación a Folios de Arrendamiento (D-081)

- **Axioma Ontológico Hexagonal:**
  - Consagración del axioma $\text{SUMINISTRO} \neq \text{MEDIDOR} \neq \text{LECTURA} \neq \text{TARIFA} \neq \text{CONSUMO VALORIZADO} \neq \text{CARGO} \neq \text{PAGO}$.
  - Desacople estricto entre ubicación física (`unidades`) y responsabilidad económica (`arrendamientos` / `cuentas_folios`).
- **Modalidades de Suministro sin Hardcoding de Conceptos:**
  - Modalidad `MEDIDO`: para recursos con instrumento físico de conteo (electricidad por kWh, agua por $m^3$). Cálculo volumétrico por diferencia de lecturas ($\Delta = L_{\text{fin}} - L_{\text{ini}}$) con soporte formal para dial cíclico (`permite_rollover`).
  - Modalidad `FIJO_PERIODICO`: para servicios recurrentes de tarifa plana (Internet dedicado, cuotas de mantenimiento común) que no requieren medidor ni lecturas intermedias.
- **Precedencia Tarifaria Jerárquica y Bloqueo Pesimista Anti-Solapamiento:**
  - Resolución de tarifa efectiva según orden jerárquico estricto: $\text{UNIDAD} > \text{PROPIEDAD} > \text{GLOBAL}$.
  - Protección de concurrencia en InnoDB mediante bloqueo pesimista `SELECT ... FOR UPDATE` en `suministro_tarifas`, impidiendo la creación concurrente de tarifas que se solapen temporalmente en el mismo ámbito territorial.
- **Medidores Físicos y Reemplazo Atómico:**
  - Gestión integral del parque de medidores físicos asociados a unidades habitacionales.
  - Reemplazo atómico en una única transacción: el medidor saliente se marca como `RETIRADO` (consignando fecha de corte y lectura final obligatoria) y el nuevo medidor se activa inmediatamente con su lectura inicial base.
  - Columna virtual y clave única `medidor_activo_idx` en `suministro_medidores` que impide colisiones físicas de dos medidores activos simultáneos para el mismo suministro en una unidad.
- **Lecturas Inmutables Append-Only y Correcciones Auditadas:**
  - Cero mutaciones destructivas sobre lecturas históricas. Toda lectura registrada queda preservada con trazabilidad inmutable.
  - Las correcciones operativas generan un nuevo registro de lectura de tipo `CORRECCION` referenciando a la lectura previa y marcando la original como `CORREGIDA`.
  - Columna virtual y clave única `correccion_activa_idx` en `suministro_lecturas` que impide bifurcaciones concurrentes sobre la misma lectura.
- **Liquidación Multitramo y Devengo Financiero en Folio (`FINANCIERO-2`):**
  - Cómputo aritmético determinista con BCMath a 4 decimales en cantidades y 2 decimales en moneda.
  - Desglose transparente en `suministro_liquidacion_tramos` cuando ocurren cambios de tarifa o reemplazos de medidor dentro del período de facturación.
  - Devengo atómico del cargo en la cuenta folio del contrato de arrendamiento (`cargos_cuenta` con tipo `SUMINISTRO_CONSUMO` o `SUMINISTRO_CUOTA_FIJA`).
  - Columna virtual y clave única `liquidacion_activa_idx` que bloquea la doble liquidación activa de un mismo período.
- **Anulación y Reliquidación con Preservación Contable e Invariantes FINANCIERO-2 (SUM-FIN-01):**
  - Al anular una liquidación, su cargo asociado pasa formalmente a `ANULADO`.
  - Des-aplicación formal no destructiva: toda aplicación previa transiciona a estado `REVERTIDA` sellando fecha y actor, preservando su `monto_aplicado` histórico original positivo (conforme a `chk_aplp_monto_positivo`).
  - Reintegración de fondos: el saldo aplicado se descuenta del pago liberando saldo disponible a favor del titular en su folio, sin generar movimientos ficticios en caja (`movimientos_caja` permanece estrictamente intacto).
  - En reliquidaciones por corrección (`revision = N + 1`), los fondos liberados se re-aplican de manera automática mediante una nueva aplicación activa (`ACTIVA`) hacia el nuevo cargo devengado, quedando el saldo residual disponible en el folio (Escenario A) o el saldo pendiente exigible (Escenarios B y C).
- **Persistencia Relacional (Migración 023_suministros.sql):**
  - 6 tablas estructuradas (tablas 73 a 78 del esquema canónico): `suministros`, `suministro_tarifas`, `suministro_medidores`, `suministro_lecturas`, `suministro_liquidaciones`, `suministro_liquidacion_tramos`.
  - Folios atómicos concurrency-safe (`LIQ-SUM-YYYYMM-XXXX`) mediante `documento_secuencias` con `FOR UPDATE`.
  - Permisos RBAC (`suministros.*`) y opción de menú Alina bajo Menú Reservas/Operaciones.
- **Interfaz Alina Conforme a D-075 y D-076:**
  - Módulo en `/suministros` con 4 KPIs en vivo, navegación por pestañas (Catálogo de Suministros, Parque de Medidores, Registro de Lecturas, Liquidaciones a Folios), modales nativos `app-form app-icon-form`, bordes píldora `b-r-20` y Select2 42px.
  - Controlador JavaScript modular `public/assets/js/gestion-suministros.js` en Vanilla Fetch y SweetAlert2.
- **Verificación Automatizada Exhaustiva (71/71 PASS):**
  - Matriz de dominio y reglas de negocio: `tests/test_suministros_matriz_40.php` (40/40 PASS).
  - Concurrencia e integridad transaccional: `tests/test_suministros_concurrencia.php` (6/6 PASS).
  - Flujo HTTP E2E real contra Apache HTTPS: `tests/test_e2e_suministros.php` (14/14 PASS).
  - Gate Financiero de Auditoría Contable: `tests/test_suministros_gate_fin01.php` (11/11 PASS).

## [aff5c4a] - 2026-09-27

### Microfase COMPRAS-1 — Abastecimiento, Órdenes de Compra, Recepción Física, Conformidad de Servicios, Comprobantes de Proveedor y Cuentas por Pagar (D-080)

- **Axioma Ontológico Hexagonal y Desacople de Dominio:**
  - Consagración del axioma $\text{SOLICITUD} \neq \text{ORDEN DE COMPRA} \neq \text{RECEPCIÓN / CONFORMIDAD} \neq \text{COMPROBANTE PROVEEDOR} \neq \text{CUENTA POR PAGAR} \neq \text{PAGO}$.
  - Principio de separación patrimonial: $\text{ORDEN DE COMPRA} \neq \text{MOVIMIENTO DE INVENTARIO} \neq \text{GASTO} \neq \text{PAGO}$. La orden representa un compromiso comercial formal y no afecta existencias físicas ni devenga gasto financiero.
- **Líneas Fuertemente Tipadas (BIEN vs SERVICIO):**
  - Líneas de tipo `BIEN`: exigen estrictamente `articulo_id` de inventario (`inventario_articulos`). Solo se materializan físicamente en almacén mediante Recepción Física.
  - Líneas de tipo `SERVICIO`: exigen estrictamente `descripcion_servicio` no vacía y `articulo_id = NULL`. Quedan confinadas ontológicamente a compras y no se mezclan con el catálogo de servicios de cara al huésped. Cero Kardex; su conformidad se acredita mediante Acta Técnica de Conformidad.
- **Recepciones Físicas en Almacén e Integración con Kardex (`INVENTARIO-1`):**
  - Regla ontológica D-080 #7: $\text{Aceptado} \neq \text{Recibido Físicamente}$. Solo las unidades marcadas como aceptadas generan movimiento soberano `ENTRADA_COMPRA` en Kardex. Las unidades rechazadas exigen motivo formal obligatorio y no entran a existencias.
  - Protección de saldo pendiente de recepción: bloqueo pesimista ante intentos de recibir cantidades superiores al saldo pactado.
- **Actas de Conformidad de Servicios:**
  - Emisión de actas técnicas con folio atómico `CONF-YYYYMM-XXXX`, informe de trabajo obligatorio y cero afectación en el inventario físico. Bloqueo contra doble conformidad sobre la misma línea.
- **Comprobantes Tributarios del Proveedor y 3-Way Matching:**
  - Registro de facturas, boletas y recibos por honorarios con clave de unicidad relacional `UNIQUE(proveedor_id, tipo_comprobante, serie, numero)` que impide duplicidad tributaria.
  - Motor de 3-Way Matching automatizado que evalúa coincidencias de cantidades, precios y montos entre Orden, Recepciones/Conformidades y Comprobante, clasificando en `CONFORME`, `CON_DIFERENCIA` u `OBSERVADO`.
- **Cuentas por Pagar, Amortizaciones y Enlace Financiero (`FINANCIERO-2`):**
  - Devengo automático de Cuenta por Pagar (`CXP-YYYYMM-XXXX`) al registrar el comprobante tributario.
  - Invariante contable reconstructible: $\text{monto\_total} - \sum(\text{cxp\_pagos.monto}) \equiv \text{saldo\_pendiente} \ge 0.00$.
  - Amortizaciones con medios de pago `EFECTIVO_CAJA` (egreso en caja chica de FINANCIERO-2) o `TRANSFERENCIA_BANCARIA` con bloqueo pesimista contra sobregiros.
- **Emisión Oficial de Órdenes de Compra en PDF A4 (`DOCUMENTOS-1`):**
  - Plantilla oficial `ORDEN_COMPRA` con snapshot inmutable determinista, hash SHA-256 congelado y membrete institucional A4 emitido mediante Dompdf 3.1.6.
- **Persistencia Relacional (Migración 022_compras.sql):**
  - 12 tablas estructuradas (tablas 61 a 72 del esquema canónico): `compra_solicitudes`, `compra_solicitud_lineas`, `compra_ordenes`, `compra_orden_lineas`, `compra_recepciones`, `compra_recepcion_lineas`, `compra_conformidades`, `compra_comprobantes`, `compra_comprobante_aplicaciones`, `cuentas_por_pagar`, `cxp_pagos`, `compra_historial_estados`.
  - Folios atómicos concurrency-safe (`SOL`, `OC`, `REC`, `CONF`, `CXP`) gestionados mediante `documento_secuencias` con `SELECT ... FOR UPDATE`.
  - Permisos RBAC granulares (`compras.*`) y opción de menú bajo Operaciones.
- **Interfaz Alina Conforme a D-075 y D-076:**
  - Módulo en `/compras` con 4 KPIs dinámicos, navegación en 5 pestañas operativas (Órdenes, Solicitudes, Recepciones/Conformidades, Comprobantes/3-Way Matching, Cuentas por Pagar), modales nativos `app-form app-icon-form` con bordes `b-r-20` y Select2 42px.
  - Controlador JavaScript modular `public/assets/js/gestion-compras.js` en Vanilla Fetch y SweetAlert2.
- **Verificación Automatizada Exhaustiva (60/60 PASS):**
  - Matriz de dominio y reglas de negocio: `tests/test_compras_matriz_40.php` (40/40 PASS).
  - Concurrencia e integridad transaccional: `tests/test_compras_concurrencia.php` (6/6 PASS).
  - Flujo HTTP E2E real contra Apache HTTPS: `tests/test_e2e_compras.php` (14/14 PASS).

## [c35e7d6] - 2026-09-27

### Microfase DOCUMENTOS-1 — Motor documental, plantillas versionadas y generación PDF (D-079)

- **Motor Documental y Dompdf Confinado:**
  - Integración de Dompdf 3.1.6 como motor oficial de generación PDF (`dompdf/dompdf ~3.1.0`), sin dependencias de motores externos o procesos Node/headless.
  - Confinamiento de seguridad estricto: `isRemoteEnabled = false`, `isPhpEnabled = false`, `isJavascriptEnabled = false`, y `chroot` acotado exclusivamente a `storage/membretes` y rutas autorizadas.
  - Numeración nativa de páginas mediante canvas de Dompdf ("Página X de Y").
- **Seguridad y Validación Estricta de HTML/CSS:**
  - `ValidadorHtmlDocumental`: análisis con `DOMDocument` para rechazo categórico de `<script>`, `<iframe>`, `<object>`, `<embed>`, eventos JS (`onclick`, `onload`, etc.), esquemas de URL `javascript:`, `data:`, y URLs remotas `http://`, `https://`.
  - Confinamiento estricto de hojas de estilo CSS: soporte para `@page` (tamaño A4, márgenes milimétricos estándar: sup 35mm, inf 28mm, izq 20mm, der 20mm), rechazo de `@import`, selectores maliciosos o scripts embebidos.
- **Interpolación Tipada y Determinismo Documental:**
  - `RegistroVariablesDocumentales`: catálogo canónico de shortcodes para origen `ARRENDAMIENTO` (`contrato.*`, `arrendador.*`, `arrendatario.*`, `inmueble.*`, `garantia.*`, `sistema.*`, etc.) con tipos y obligatoriedad.
  - Sanitización obligatoria `htmlspecialchars()` en valores dinámicos interpolados.
  - `CompiladorDocumental`: ordenamiento lexicográfico de variables con `ksort()` en `snapshot_datos_json`, cálculo de `hash_snapshot_sha256 = hash('sha256', snapshot_html)`. Garantía de idempotencia matemática y determinismo.
- **Persistencia, Restricciones InnoDB e Inmutabilidad:**
  - Migración `021_documentos.sql` (tablas 56 a 60 en esquema consolidado):
    - `documento_secuencias`: generador atómico de folios `DOC-ARR-YYYYMM-XXXX` con reinicio mensual y bloqueo pesimista `SELECT ... FOR UPDATE`.
    - `documento_plantillas`: catálogo de plantillas con soporte A4, márgenes milimétricos y membrete de fondo.
    - `documento_plantilla_versiones`: versiones inmutables con activación atómica y restricción relacional física `uq_dpv_plantilla_activa` sobre columna virtual InnoDB (`version_activa_idx = IF(es_activa = 1, 1, NULL)`), bloqueando físicamente en BD dos versiones activas simultáneas (Error 1062).
    - `documentos_emitidos`: almacenamiento inmutable de contratos emitidos con folios oficiales, `snapshot_html`, `snapshot_datos_json`, `hash_snapshot_sha256` y `hash_pdf_sha256`.
    - `documento_incidencias`: bitácora de auditoría ante discrepancias físicas o archivos faltantes.
  - Preservación histórica garantizada: cambios posteriores en la base de datos (renta, titulares, etc.) o en la plantilla no mutan el snapshot congelado ni el binario PDF.
- **Emisión Oficial, Borradores y Verificación en Vivo:**
  - Modo borrador: renderiza PDF al vuelo con marca de agua "BORRADOR NO VÁLIDO" sin consumir folio ni persistir en base de datos.
  - Emisión oficial: genera folio consecutivo, persiste snapshot inmutable, almacena PDF físico en `storage/documentos/YYYY/MM/folio.pdf`, y calcula hash SHA-256 de los bytes exactos almacenados.
  - Descarga segura con verificación en vivo del 100% de los bytes contra `hash_pdf_sha256`. Detección inmediata de manipulación (1 byte alterado) con `DocumentoCorruptoExcepcion` (HTTP 500) y registro formal en `documento_incidencias`.
  - Cero regeneración silenciosa: la descarga ordinaria jamás sobrescribe o repara archivos corruptos en secreto.
  - Regeneración asistida controlada: endpoint administrativo explícito que reconstruye el binario exclusivamente a partir del `snapshot_html` congelado, resolviendo las incidencias previas.
  - Anulación formal: revoca la validez legal del documento registrando actor, motivo y marca de agua sin destruir el archivo físico ni el folio histórico.
- **Interfaz Alina Conforme a D-075 y D-076:**
  - Módulo `/documentos` con 4 KPIs (Emitidos, Activos, Plantillas, Incidencias), navegación por 3 pestañas operativas (Emitidos, Plantillas Versionadas, Auditoría de Incidencias), visualizador de PDF con iframe, y modales nativos Alina (`app-form app-icon-form`, Select2 42px).
  - Controlador JS modular `public/assets/js/gestion-documentos.js` (Vanilla Fetch, SweetAlert2, 0 jQuery en lógica).
  - Integración en menú lateral bajo Operaciones y permisos RBAC granulares (`documentos.ver`, `documentos.emitir`, `documentos.plantillas.gestionar`, `documentos.anular`, `documentos.regenerar`).
- **Verificación Automatizada Exhaustiva (60/60 PASS):**
  - Matriz de dominio y seguridad: `tests/test_documentos_matriz_40.php` (40/40 PASS).
  - Concurrencia, atomicidad e inmutabilidad: `tests/test_documentos_concurrencia.php` (6/6 PASS).
  - Flujo HTTP E2E real contra Apache: `tests/test_e2e_documentos.php` (14/14 PASS).

## [f8f1fcb] - 2026-09-27

### Microfase INVENTARIO-1 — Catálogo de artículos, almacenes/ubicaciones, existencias, movimientos, activos y dotaciones (D-078)

- **Ontología y Modelo de Dominio de Inventario:**
  - Consagración del principio vinculante $\text{ARTÍCULO} \neq \text{EXISTENCIA} \neq \text{MOVIMIENTO} \neq \text{ACTIVO INDIVIDUAL}$.
  - Entidades de dominio `InventarioUnidadMedida`, `InventarioUbicacion`, `InventarioArticulo`, `InventarioExistencia`, `InventarioMovimiento`, `InventarioActivo` e `InventarioDotacionEstandar`.
  - Taxonomía hotelera de artículos: `CONSUMIBLE_OPERATIVO`, `LENCERIA_BLANCOS`, `REPUESTO_MANTENIMIENTO` y `ACTIVO_SERIALIZABLE`.
- **Kardex Inmutable Append-Only vs Proyección Materializada:**
  - `inventario_existencias` opera como proyección operacional rápida materializada con columna `cantidad_actual DECIMAL(15,4)` y restricción relacional `CHECK (cantidad_actual >= 0)`.
  - Kardex inmutable `inventario_movimientos` soberano sin `UPDATE` ni `DELETE`. Tipos de movimiento: `SALDO_INICIAL`, `ENTRADA_COMPRA`, `SALIDA_CONSUMO`, `SALIDA_MANTENIMIENTO`, `TRASLADO_SALIDA`, `TRASLADO_ENTRADA`, `AJUSTE_POSITIVO`, `AJUSTE_NEGATIVO` y `REVERSO`.
  - Método nativo de auditoría `verificarReconciliacionExistencia()` que valida al milésimo la suma algebraica exacta del Kardex contra las existencias proyectadas.
- **Traslados Atómicos de Dos Patas:**
  - Correlativo único compartido (`TRS-YYYYMMDD-XXXX`) emitido para ambas patas (`TRASLADO_SALIDA` y `TRASLADO_ENTRADA`) con enlace referencial directo `movimiento_relacionado_id`.
  - Mitigación pesimista de interbloqueos (deadlocks) ordenando determinísticamente las existencias bloqueadas por ID físico (`ORDER BY id ASC FOR UPDATE`).
- **Integración No Incremental con Mantenimiento-1:**
  - Salidas de repuestos con `referencia_tipo = 'MANTENIMIENTO_ORDEN'` y `referencia_id = orden_trabajo_id`.
  - Recálculo soberano no incremental del costo de materiales de la orden de trabajo mediante sumatoria histórica (`sumarCostoMovimientosPorReferencia`), evitando drift aritmético o errores por reintentos.
- **Activos Serializables y Dotaciones Estándar:**
  - Activos serializados individuales desacoplados de existencias cuantitativas (cero doble contador en existencias). Ciclo de vida por estados (`DISPONIBLE`, `ASIGNADO`, `EN_MANTENIMIENTO`, `DE_BAJA`), con asignación física a habitaciones (`tipo = 'UNIDAD'`) y bajas formalmente justificadas (cero eliminación física).
  - Dotaciones estándar por tipo de unidad o unidad habitacional específica, con motor de auditoría (`obtenerDotacionRealVsEstandarPorUnidad`) que contrasta el estándar teórico contra la realidad física de inventario y activos asignados.
- **Persistencia Relacional y Migración 020:**
  - Script `SQL/migraciones/020_inventario.sql` y esquema maestro `SQL/camargo_pms.sql` sincronizados (tablas 49 a 55).
  - RBAC: 7 permisos granulares (`inventario.ver`, `inventario.articulos.gestionar`, `inventario.ubicaciones.gestionar`, `inventario.movimientos.registrar`, `inventario.traslados.ejecutar`, `inventario.activos.gestionar`, `inventario.dotaciones.gestionar`). Menú dinámico `/inventario` bajo categoría Operaciones.
- **Interfaz Alina Conforme a D-075 y D-076:**
  - Vista `/inventario` con 4 tarjetas KPI, navegación por 6 pestañas operativas (Existencias, Artículos, Kardex, Activos, Ubicaciones, Dotaciones), 7 modales nativos Alina (`app-form app-icon-form`, Select2 42px píldora `b-r-20`).
  - Controlador JS modular `public/assets/js/gestion-inventario.js` (Vanilla JS, 0 jQuery en negocio, PristineJS y SweetAlert2).
- **Verificación Automatizada Exhaustiva (60/60 PASS):**
  - Matriz de dominio: `tests/test_inventario_matriz_40.php` (40/40 PASS).
  - Suite de concurrencia e integridad: `tests/test_inventario_concurrencia.php` (6/6 PASS).
  - Suite HTTP E2E real contra Apache: `tests/test_e2e_inventario.php` (14/14 PASS).

## [34988e8] - 2026-09-27

### Microfase MANTENIMIENTO-1 — Incidencias, Órdenes de Trabajo y Bloqueo Operativo de Unidades (D-077)

- **Separación Ontológica y Modelo de Dominio:**
  - Consagración del principio vinculante $\text{INCIDENCIA} \neq \text{ORDEN DE TRABAJO} \neq \text{BLOQUEO DE DISPONIBILIDAD}$.
  - Entidades de dominio ricas `Incidencia`, `OrdenTrabajo`, `OrdenIncidencia` y `MantenimientoHistorialEstado`.
- **Invariante Operacional de Inventario:**
  - Una incidencia técnica por sí misma **jamás bloquea** disponibilidad ni retira unidades de la venta.
  - La orden de trabajo en estado `BORRADOR` **no altera** inventario diario.
  - El flag `requiere_bloqueo = 1` exige obligatoriamente `unidad_id` e intervalo válido $[\text{fecha\_bloqueo\_inicio}, \text{fecha\_bloqueo\_fin})$ con $\text{fecha\_fin} > \text{fecha\_inicio}$. Si `requiere_bloqueo = 0`, las columnas de fechas de bloqueo son estrictamente `NULL`.
- **Protección Relacional DDL y Motor InnoDB:**
  - Restricción `chk_mord_bloqueo_coherente` validada a nivel de tabla en MySQL/MariaDB.
  - Bloqueo pesimista con `SELECT ... FOR UPDATE` al programar órdenes bloqueantes, garantizando serialización y exclusión mutua.
  - Captura y traducción estricta de códigos MySQL 1062 (colisión de clave única), 1205 (lock wait timeout) y 1213 (deadlock) a `ConflictoDisponibilidadExcepcion` (HTTP 409).
- **Semántica Semiabierta Hotelera y Preservación Histórica:**
  - Intervalos bloqueados en $[\text{inicio}, \text{fin})$, dejando `fecha_fin` libre para check-in inmediato de huéspedes o arrendatarios.
  - Prórrogas transaccionales con verificación de noches futuras sin colisión.
  - Culminación y cancelación de órdenes liberan selectivamente noches futuras, preservando intacto el histórico de noches pasadas efectivamente consumidas.
- **Costeo Aritmético con BCMath:**
  - Columnas `DECIMAL(15,2)` en BD sin columnas generadas rígidas, procesadas aritméticamente con `BCMath` (`costo_total = bcadd(mano_obra, materiales, 2)`).
- **Persistencia Relacional y Migración 019:**
  - Script `SQL/migraciones/019_mantenimiento.sql` y esquema canónico `SQL/camargo_pms.sql` sincronizados (tablas 45 a 48).
  - 8 permisos RBAC (`mantenimiento.*`), menú dinámico `/mantenimiento` bajo categoría Operaciones.
- **Interfaz Alina Conforme a D-075 y D-076:**
  - Vista `/mantenimiento` con tarjetas KPI, tabla responsiva con badges Alina suaves (`bg-light-*`), modales nativos Alina (`app-form app-icon-form`, Select2 42px píldora 20px, Flatpickr y toggle switch).
  - Controlador JS reactivo `public/assets/js/gestion-mantenimiento.js` (Vanilla JS, 0 jQuery en negocio, PristineJS y SweetAlert2).
- **Verificación Automatizada Exhaustiva (60/60 PASS):**
  - Matriz de dominio: `tests/test_mantenimiento_matriz_40.php` (40/40 PASS).
  - Suite de concurrencia e integridad: `tests/test_mantenimiento_concurrencia.php` (6/6 PASS).
  - Suite HTTP E2E real contra Apache: `tests/test_e2e_mantenimiento.php` (14/14 PASS).

## [c9eb823] - 2026-09-27

### Microfase ARRENDAMIENTOS-1 — Gestión de Arrendamientos de Mediana y Larga Estancia (D-076)

- **Separación Ontológica y Modelo de Dominio:**
  - Consagración del principio rector vinculante $\text{RESERVA} \neq \text{ESTADÍA} \neq \text{ARRENDAMIENTO}$.
  - Entidades de dominio ricas `Arrendamiento`, `ArrendamientoPersona`, `ArrendamientoCuota`, `ArrendamientoGarantia` y `ArrendamientoHistorialEstado`.
- **Titularidad Unificada en BD:**
  - Modelado en `arrendamiento_personas` con columna virtual generada `es_titular_unico` y `UNIQUE KEY uq_arrp_titular_unico`. Blindaje estructural en motor InnoDB de a lo sumo un titular principal por contrato.
  - Soporte de cotitulares (corresponsables) y ocupantes autorizados.
- **Temporalidad Contractual y Semántica Semiabierta:**
  - Plazo determinado en V1 (`fecha_fin NOT NULL`, $\text{fecha\_fin} > \text{fecha\_inicio}$).
  - Semántica hotelera semiabierta $[\text{fecha\_inicio}, \text{fecha\_fin})$, donde `fecha_fin` queda libre para nuevas entradas.
  - Prórrogas transaccionales con bloqueo pesimista y renovaciones trazables vía `arrendamiento_anterior_id`.
- **Disponibilidad Sparse:**
  - Materialización directa de noches en `inventario_diario_unidades` bajo `tipo_bloqueo = 'ARRENDAMIENTO'`.
  - Rescisión anticipada con preservación intacta de noches pasadas y liberación atómica de noches futuras (`DELETE` selectivo).
- **Devengo Mensual Idempotente y Día de Vencimiento:**
  - Entidad `arrendamiento_cuotas` con `UNIQUE KEY (arrendamiento_id, periodo_anio, periodo_mes, tipo_cuota)`.
  - Nomenclatura vinculante `dia_vencimiento` (1..31) con ajuste automático al último día para meses cortos (ej. día 31 vence el 30 en abril, 28/29 en febrero).
- **Custodia Segregada de Fondos en Garantía:**
  - Entidad `arrendamiento_garantias` segregada de rentas ordinarias con saldo reconstructible:
    $$\text{monto\_recibido} = \text{monto\_retenido\_actual} + \text{monto\_compensado\_danos} + \text{monto\_compensado\_renta} + \text{monto\_devuelto}$$
  - Compensación formal por daños y rentas insolutas con liquidación contra cargos devengados en el folio.
- **Extensión Compatible de Cuentas Folios (FINANCIERO-2):**
  - Modificación de `cuentas_folios` con `reserva_id NULL`, `arrendamiento_id NULL UNIQUE` y restricción XOR `chk_ctaf_sujeto_exclusivo`.
  - Consulta `CuentaFolioRepositorio::listar()` adaptada con `LEFT JOIN` hacia reservas y arrendamientos.
- **Persistencia Relacional y Migración 018:**
  - Script `SQL/migraciones/018_arrendamientos.sql` y esquema canónico `SQL/camargo_pms.sql` sincronizados al 100%. Tablas 40 a 44, alter de inventario, permisos RBAC y opción de menú dinámico bajo `operaciones`.
- **Interfaz Alina Conforme a D-075:**
  - Vista `/arrendamientos` con diseño Alina (`app-form app-icon-form`, Select2 píldora 20px a 42px, Flatpickr, badges y PristineJS).
  - Controlador JS Vanilla reactivo `public/assets/js/gestion-arrendamientos.js`.
- **Verificación Automatizada Exhaustiva:**
  - Matriz de dominio: `tests/test_arrendamientos_matriz_40.php` (40/40 PASS).
  - Suite de concurrencia e integridad: `tests/test_arrendamientos_concurrencia.php` (6/6 PASS).
  - Suite HTTP E2E real contra Apache: `tests/test_e2e_arrendamientos.php` (14/14 PASS).
  - Regresión consolidada completa de fases anteriores al 100% PASS.

## [0514447] - 2026-09-27

### Microfase UI-3A — Fidelidad Visual Exacta de Formularios Nativos Alina (D-075)

- **Corrección Geométrica y Fidelidad Visual Exacta de Formularios Alina:**
  - Diagnóstico empírico mediante Headless Edge CDP de los componentes originales de Alina (`admin-dashboard/alina/template/default_forms.html` y `select.html`).
  - Eliminación absoluta de overrides artificiales destructivos en `camargo.css` que forzaban `border-radius: 0.375rem !important` y alturas achatadas.
- **Vertical Form With Icon de Alina:**
  - Adopción transversal de la clase canónica `<form class="app-form app-icon-form">` en todas las vistas y modales del PMS.
  - Implementación nativa del separador vertical `|` de 1px entre el icono y el texto mediante el pseudo-elemento `.icon-control::after` (`left: 40px`, `height: 20px`, `background: rgba(var(--dark), 0.6)`).
  - Geometría tipo píldora nativa con `border-radius: var(--app-border-radius)` (20px), padding de `0.8rem 0.75rem 0.8rem 3rem` (48px) e icono centrado con `pointer-events: none`.
- **Select 2 de Alina:**
  - Estandarización de la caja de selección a píldora redondeada `border-radius: var(--app-border-radius)` (20px), borde `1px solid rgba(var(--secondary), 0.4)` y altura nativa de 42px (`calc(2.5rem + var(--bs-border-width) * 2)`).
  - Flecha chevron de Font Awesome 6 `\f078` y botón de limpieza redondeado a 14px (`.select2-selection__clear`) con fondo tenue rojo `rgba(var(--danger), 0.2)`.
  - Erradicación de clases `form-control-sm` y `form-select-sm` en campos dentro de `.icon-control` o con `.basic-select2`.
- **Verificación Automatizada e Inmutabilidad:**
  - Suite automatizada `tests/test_ui3a_fidelidad_alina.php` con 70/70 aserciones PASS.
  - Regresión consolidada completa de fases anteriores: 363/363 PASS.
  - Gran total verificado: 433/433 PASS (100%).
  - Cero dependencias externas / CDN, cero alteraciones funcionales de backend y cero migraciones SQL.

### Microfase UI-3 — Estandarización de Formularios mediante Componentes Nativos Alina

- **Vertical Form With Icon de Alina (D-075):**
  - Implementación sistemática del patrón nativo de Alina en todas las vistas y modales operativos: contenedor `.icon-control.position-relative`, icono centrado verticalmente con clase `ms-3`, e input identado `.ps-5`.
  - Regla CSS defensiva en `camargo.css`: `.icon-control > i { pointer-events: none; }` para asegurar transferencia inmediata del foco al campo al hacer clic en el icono.
  - Estandarización integral en 11 vistas: `/login`, `/caja` (7 modales financieros), `/servicios` (3 pestañas operativas y modales), `/estadias`, `/reservas`, `/disponibilidad`, `/unidades`, `/propiedades`, `/usuarios`, `/configuracion/roles` y `/configuracion/menu`.
- **Select2 4.0.13 y jQuery 3.7.1 100% Locales (Cero CDN):**
  - Copia de librerías locales desde `admin-dashboard/alina/` hacia `public/assets/vendor/jquery/jquery.min.js`, `public/assets/vendor/select/select2.min.css` y `public/assets/vendor/select/select2.min.js`.
  - Cero dependencias externas / CDN en toda la infraestructura de carga (`head.php` y `scripts.php`).
  - Confinamiento estricto de jQuery a inicializar Select2: 0 llamadas `$.ajax()`, 0 manipulaciones DOM en lógica de negocio, 100% Vanilla JS + Fetch API.
- **Controlador Reactivo `camargo-select.js`:**
  - Creación de wrapper global modular `window.CamargoSelect` con métodos defensivos (`init`, `initElement`, `reinit`, `setValue`).
  - Configuración mandatoria de `dropdownParent: $el.closest('.modal')` para prevenir recortes de dropdown y trampas de foco en modales Bootstrap 5.
  - Sincronización automática con PristineJS mediante redespacho de eventos nativos `input` y `change` (`bubbles: true`).
  - Detección de mutaciones dinámicas en el DOM mediante `MutationObserver` y soporte reactivo en eventos `shown.bs.modal`.
- **Estilos Visuales de Select2 en `camargo.css`:**
  - Sustitución de Tabler Icons por flecha Font Awesome 6 (`\f078` fa-chevron-down).
  - Altura estandarizada a 2.35rem alineada con inputs nativos Alina.
  - Eliminación de bordes punteados (`dashed`/`dotted`) en selecciones simples y múltiples.
  - Integración de bordes de error con PristineJS (`.has-danger .select2-selection`).
- **Radios, Checkboxes y Switches Nativos:**
  - Homogeneización de controles mediante clases nativas Alina: `.form-check.d-flex.align-items-center.gap-1` y `.form-check-input.f-s-18.mb-1` en matrices de permisos, interruptores de configuración del sistema y filtros.
- **Erradicación Absoluta de Degradados:**
  - Eliminación del 100% de clases `btn-gradient-*` y `bg-gradient-*` en todo `app/Vistas/` (0 coincidencias en auditoría de código).
  - Reemplazo por estilos canónicos sólidos / outline de Bootstrap 5 / Alina e insignias suaves `bg-light-*` con texto `f-w-500` / `f-w-600`.
- **Verificación Automatizada y Preservación de Negocio:**
  - Suite de verificación automatizada `test_ui3_matriz_25.php` (25/25 PASS).
  - Regresión consolidada multi-fase completa: 363/363 PASS.
  - Gran total verificado: 388/388 PASS (100%).

### Microfase FINANCIERO-2 — Cuentas de Folios, Cargos, Pagos, Aplicaciones, Devoluciones y Caja Física

- **Tríada Financiera y Desacoplamiento de Cobros (D-074):**
  - Consagración del principio rector inviolable: `CARGO ≠ PAGO ≠ MOVIMIENTO DE CAJA` y `PAGO ≠ APLICACIÓN DE PAGO`.
  - Cero banderas booleanas (`pagado = 1`): el balance del folio, saldo pendiente del cargo y saldo no aplicado del pago se derivan mediante cálculo aritmético exacto con `BCMath` en `DECIMAL(15,2)`.
  - Exclusiones estrictas respetadas: CERO facturación electrónica / SUNAT, CERO IGV inventado (0.00 por defecto salvo alícuota explícita), CERO cuentas por pagar a proveedores externos, CERO eliminación física (`DELETE = 0`).
- **Folios Comerciales 1:1 por Reserva:**
  - Cada reserva comercial dispone de exactamente una cuenta/folio financiero principal (`cuentas_folios.reserva_id UNIQUE`).
  - Cargos imputados a la cuenta con puntero opcional `estadia_id NULL`, permitiendo estados de cuenta consolidados por reserva y desglosados por habitación.
- **Sincronización Transaccional Automática:**
  - Cargos de alojamiento devengados automáticamente al confirmar reservas comerciales.
  - Cargos de servicios sincronizados atómicamente con el ciclo operativo: `SOLICITADO`/`CONFIRMADO` $\rightarrow$ `PROVISIONAL`, `EJECUTADO` $\rightarrow$ `DEVENGADO`, y `CANCELADO` $\rightarrow$ `ANULADO`.
- **Caja Física, Turnos y Arqueo Determinista:**
  - Exigencia estricta de sesión de caja física abierta para cobros o devoluciones en efectivo (`EFECTIVO`).
  - Arqueo determinista de cierre computando la diferencia entre el dinero contado declarado y el esperado por el sistema:
    - $\Delta = 0.00$ $\rightarrow$ `CUADRADA`.
    - $\Delta > 0.00$ $\rightarrow$ `SOBRANTE` (justificación obligatoria de 10 a 500 caracteres).
    - $\Delta < 0.00$ $\rightarrow$ `FALTANTE` (justificación obligatoria de 10 a 500 caracteres).
  - Movimientos manuales de caja tipados: `INGRESO_AJUSTE` y `EGRESO_GASTO_MENOR` con motivo obligatorio y actor responsable.
- **Cuentas Bancarias vs Medios Electrónicos:**
  - Depósitos y transferencias exigen cuenta bancaria activa. Tarjetas y billeteras digitales operan con referencia externa y cuenta opcional nullable.
- **Reversiones y Devoluciones Formalizadas:**
  - Reversión compensatoria de aplicaciones de pago (`reversada = 1`) restaurando saldos pendientes sin alterar los registros originales.
  - Devoluciones formalizadas (`devoluciones_cuenta`) reduciendo saldo del pago con motivo justificado y egreso de caja física si es en efectivo.
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Módulo completo de Tesorería en `/caja` con KPIs en tiempo real, pestañas de Cuentas/Folios y Turno de Recepción, y 7 modales operativos.
  - Font Awesome 6.3.0 exclusivo, Flatpickr, Variants of badge de Alina (`bg-light-*`), 0 dotted, 0 dashed, Vanilla JS modular (`gestion-caja.js`), PristineJS y SweetAlert2.
- **Persistencia Relacional (Migración 017):**
  - 11 nuevas tablas: `cajas_fisicas`, `cuentas_bancarias`, `metodos_pago`, `sesiones_caja`, `movimientos_caja`, `movimientos_bancarios`, `cuentas_folios`, `cargos_cuenta`, `pagos_cuenta`, `aplicaciones_pago`, `devoluciones_cuenta`.
  - 8 nuevos permisos RBAC: `caja.ver`, `caja.aperturar`, `caja.cerrar`, `caja.movimientos`, `caja.cobrar`, `caja.aplicar`, `caja.devolver`, `caja.reversar`.
  - Opción de menú de nivel 2: `caja` con ruta `/caja` bajo categoría principal `finanzas`.

### Microfase SERVICIOS-1 — Catálogo de Servicios, Proveedores, Consumos y Traslados

- **Separación Ontológica Estricta (D-073):**
  - Principio rector inviolable: `PROVEEDOR ≠ SERVICIO ≠ SERVICIO CONTRATADO ≠ RESERVA ≠ ESTADÍA`.
  - Exclusión taxativa: cero pagos, cero caja, cero facturación electrónica SUNAT, cero cálculo financiero prematuro y cero eliminación física (`DELETE = 0`).
- **Maestro Independiente de Proveedores:**
  - Tabla `proveedores` administrable para prestadores externos, clasificados en `EMPRESA` (RUC / razón social) y `PERSONA_NATURAL` (vinculación opcional al maestro central de `personas` para no duplicar identidad).
  - Principio `PROVEEDOR ≠ OPERACIÓN INTERNA`: no se crea proveedor ficticio "Interno". Cuando un servicio es ejecutado con personal o recursos propios de Camargo Hostelería, `es_operacion_interna = 1` y `proveedor_id` permanece obligatoriamente en `NULL`.
- **Integridad Estructural en Base de Datos (CHECK Constraint):**
  - Regla formal implementada a nivel de motor MySQL 8.4 InnoDB (`chk_sc_coherencia_operacion_interna`):
    `((es_operacion_interna = 1 AND proveedor_id IS NULL) OR (es_operacion_interna = 0 AND proveedor_id IS NOT NULL))`.
- **Matriz de Homologación de Proveedores y Unicidad de Preferente:**
  - Tabla asociativa `servicio_proveedores` con costo pactado (`costo_pactado DECIMAL(15,2)`), plazo de pago en días, código de referencia del proveedor y condición de preferente (`es_preferente`).
  - Restricción de unicidad estricta en base de datos para proveedor preferente: columna virtual generada `uq_preferente` indexada por `uq_sp_servicio_preferente`, impidiendo más de un preferente activo por servicio tanto en concurrencia como transaccionalmente.
- **Catálogo Maestro de Servicios:**
  - Catálogo normalizado con `categorias_servicio` (8 categorías seeded) y `modalidades_cobro_servicio` (6 modalidades seeded, incluyendo explícitamente `POR_UNIDAD`).
  - Configuración con código canónico autogenerado (`SERV-CAT-XXX`), precio de venta referencial y flags operativas (`requiere_proveedor_externo`, `es_traslado`).
- **Contratación e Imputación de Consumos:**
  - Reserva comercial obligatoria (`reserva_id NOT NULL`): no se admiten consumos desanclados.
  - Imputación a estadía opcional pero coherente: si se proporciona `estadia_id`, el sistema verifica bajo transacciones con bloqueo pesimista (`FOR UPDATE`) que la estadía pertenezca a la misma reserva y no se encuentre `ANULADA` (emitiendo HTTP 422 si no coincide).
  - Snapshot económico y descriptivo inmutable (D-010 / D-069): se congelan `concepto_servicio`, `cantidad`, `precio_unitario`, `costo_unitario`, `subtotal`, `impuesto_total = 0.00` y `total = subtotal` (`moneda_codigo = 'PEN'`). Modificaciones posteriores del catálogo o costos de proveedor no alteran operaciones emitidas.
- **Ciclo Operativo y Prohibición Estricta en Servicios Ejecutados:**
  - Estados: `SOLICITADO`, `CONFIRMADO`, `EJECUTADO`, `CANCELADO`.
  - **Bloqueo Vinculante:** PROHIBIDO cancelar un servicio que ya ha sido `EJECUTADO` físicamente (rechazo riguroso con `EstadoServicioInvalidoExcepcion` / HTTP 422).
  - Transición a `EJECUTADO` registra instante UTC técnico (`ejecutado_en`) y actor ejecutor (`ejecutado_por_actor_id`).
  - Cancelación justificada requiere motivo explícito, registrando instante UTC técnico y actor cancelador.
- **Extensión Especializada 1:1 de Traslados (Transfers):**
  - Tabla `servicio_traslados` vinculada 1:1 a `servicios_contratados` con `UNIQUE(servicio_contratado_id)`.
  - Soporta traslados de `LLEGADA` y `SALIDA`, capturando origen y destino generalizados (aeropuerto, terminal, estación, propiedad, centro u otra dirección libre), número de vuelo/transporte, cantidad de pasajeros y equipaje, conductor asignado y vehículo.
  - Rollback transaccional atómico: cualquier fallo en los datos logísticos revierte integralmente la cabecera del servicio contratado.
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Módulo completo bajo `/servicios` con 4 pestañas operativas (Consumos Imputados, Catálogo de Conceptos, Directorio de Proveedores, Logística de Traslados).
  - Conformidad estricta con D-071: Font Awesome 6.3.0 exclusivo, Flatpickr con formato visual d/m/Y y envío canónico Y-m-d, Badges oficiales de Alina (`bg-light-success`, `bg-light-info`, `bg-light-primary`, `bg-light-warning`, `bg-light-danger`, `bg-light-secondary`), 0 dotted, 0 dashed, Vanilla JS nativo modular, PristineJS y SweetAlert2.
- **Persistencia Relacional (Migración 016):**
  - 7 nuevas tablas: `categorias_servicio`, `modalidades_cobro_servicio`, `proveedores`, `servicios`, `servicio_proveedores`, `servicios_contratados`, `servicio_traslados`.
  - 5 nuevos permisos RBAC: `servicios.ver`, `servicios.gestionar`, `servicios.contratar`, `servicios.ejecutar`, `servicios.cancelar`.
  - Opción de menú dinámica de nivel 2: `servicios_catalogo` bajo `reservas`.

### Microfase ESTADÍAS-1 — Check-in, Registro de Huéspedes y Ciclo Operativo de Estancias

- **Separación Ontológica Estricta (D-072):**
  - Principio rector inviolable: `RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO`.
  - La reserva formaliza el acuerdo comercial; la estadía formaliza la ocupación física real de la unidad habitacional; el arrendamiento se mantiene reservado a contratos de largo plazo.
  - Exclusión taxativa de esta fase: pagos, caja, facturación electrónica, consumos, servicios adicionales y arrendamientos.
- **Multiunidad Operativa (1 Reserva : N Estadías Físicas Independientes):**
  - Cada unidad de una reserva confirmada genera una estadía física independiente con su propio check-in, llaves, lista de ocupantes y check-out.
  - Restricción única en base de datos: `UNIQUE KEY uq_estadias_reserva_unidad (reserva_unidad_id)`.
  - El agregado comercial `reservas` permanece en estado `CONFIRMADA`: el estado operativo de la reserva es derivado (`SIN_CHECKIN`, `PARCIAL_EN_CURSO`, `COMPLETA_EN_CURSO`, `FINALIZADA`).
- **Validación de Walk-in Diferido:**
  - El check-in requiere indispensablemente una reserva comercial confirmada previa. Se rechaza check-in sobre reservas `PENDIENTE`, `CANCELADA` o `EXPIRADA` con `EstadoReservaInvalidoExcepcion` (HTTP 422).
- **Control Estricto de Capacidad y Huésped Responsable:**
  - Bloqueo estricto de capacidad física: $1 \le \text{huéspedes} \le \text{capacidad\_personas}$ (HTTP 422 si se supera, sin sobrecapacidad autorizada).
  - Designación obligatoria de exactamente 1 huésped responsable (`es_responsable = 1`) perteneciente a la lista de ocupantes.
  - Todos los ocupantes vinculados al maestro central de `personas` (`estadia_huespedes.persona_id`), con prevención de duplicados (`UNIQUE(estadia_id, persona_id)`).
- **Ciclo de Vida Operativo e Inmutabilidad Histórica:**
  - Estados: `EN_CURSO`, `FINALIZADA`, `ANULADA`.
  - Principio `CHECK-OUT ≠ DELETE` y `ANULACIÓN ≠ DELETE`: cero eliminación física (`DELETE = 0`) en tablas `estadias` y `estadia_huespedes`; claves foráneas con `ON DELETE RESTRICT` (cero borrado en cascada).
  - Anulación excepcional con justificación obligatoria (1-255 caracteres) y preservación histórica para auditoría.
  - Check-out anticipado o tardío con registro del instante real técnico UTC (`DATETIME` con `gmdate`) sin recalcular noches comerciales (D-066).
- **Identificación de Llaves y Accesos:**
  - Campo plano `identificador_llave VARCHAR(50) NULL`, registrando código de tarjeta magnética o número de llave física, sin acoplamiento a domótica en esta fase.
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Tablero de recepción en `/estadias` con KPIs en tiempo real, tabla interactiva, filtros combinados y modales de Check-in, Check-out, Anular y Detalle con gestión de huéspedes.
  - Vanilla JS nativo, validación PristineJS, notificaciones SweetAlert2 y badges oficiales Alina (`Variants of badge` con `bg-light-success`, `bg-light-secondary`, `bg-light-danger`).
- **Persistencia Relacional (Migración 015):**
  - Tablas `estadias` y `estadia_huespedes`, 5 permisos RBAC (`estadias.*`) asignados a `SUPERADMINISTRADOR` y opción de menú dinámico bajo 'reservas'.


### Microfase UI-2A — Estandarización obligatoria de Badges y Chips de Alina

- **Estandarización de Badges y Chips Oficiales de Alina (D-071):**
  - **Componentes Permitidos:** Adopción obligatoria de *Variants of badge* de Alina (`badge bg-light-*`, `badge text-bg-*`) para estados compactos y contadores, y *Variants of chip* de Alina (`chip bg-light-*`, `chip text-bg-*`) para categorías, clasificaciones, tipos de unidad física, atributos y tags.
  - **Prohibición Estricta de Dotted y Dashed:** Conteo absoluto de badges punteados o discontinuos = 0 (`dotted` = 0, `dashed` = 0) en toda la aplicación.
  - **Erradicación de Clases Bootstrap Crudas:** Eliminadas todas las ocurrencias directas de `bg-*-subtle` en vistas y módulos JavaScript propios, reemplazándolas por las variantes oficiales de Alina (`bg-light-*`).
  - **Iconografía Unificada Font Awesome 6:** Todos los iconos embebidos dentro de badges y chips provienen exclusivamente de Font Awesome 6 Free (`fa-solid fa-*`), con 0 Tabler Icons.
  - **Mapa Semántico Vinculante:**
    - `SUCCESS` (`bg-light-success`): `ACTIVO`, `CONFIRMADA`, `DISPONIBLE`.
    - `WARNING` (`bg-light-warning`): `PENDIENTE`, `MANTENIMIENTO`, `SUPERADMIN`.
    - `DANGER` (`bg-light-danger`): `CANCELADA`, `BLOQUEADO`, `OCUPADO`, `SUSPENDIDO`.
    - `INFO` (`bg-light-info`): informativo neutral, sistema, manual, tipo de unidad.
    - `SECONDARY` (`bg-light-secondary`): `INACTIVO`, `EXPIRADA`, `LIBERADO`, contadores y códigos auxiliares.
    - `LIGHT` / `DARK`: contraste o códigos destacados de propiedad/unidad.
  - **Infraestructura Centralizada:**
    - Backend: Clase `\CamargoPMS\Nucleo\Insignia` y funciones globales `insignia_badge()`, `insignia_chip()` e `insignia_estado()`.
    - Frontend: Módulo `window.CamargoInsignia` en `camargo-layout.js` con métodos `badge()`, `chip()`, `estado()` y `resolverClase()` en Vanilla JS puro (0 jQuery).
  - **Gobernanza e Invariantes:**
    - Registro de decisión D-071 sección 6 en `DECISIONES.md`, `FRONTEND.md`, `PLANTILLA-ALINA.md`, `CONVENCIONES.md` y `.skills/camargo-ui-alina/SKILL.md`.
    - Invariables intactos: `admin-dashboard/` intacto (100% inmutable), `.env` intacto, 14 migraciones de BD (0 añadidas).

### Microfase UI-2 — Estandarización transversal obligatoria de recursos de interfaz

- **Estandarización de Iconografía Oficial Font Awesome 6 (D-071):**
  - Adopción exclusiva de Font Awesome 6 Free (v6.3.0) en todo el código propio del sistema.
  - Erradicación total (0 ocurrencias) de Tabler Icons (`ti ti-*`, `ti-*`) en todas las vistas PHP (`app/Vistas/`), layouts, controladores y módulos JavaScript propios (`public/assets/js/`).
  - Actualización de catálogo de iconos de menú en base de datos (`opciones_menu.icono`) y esquema maestro `SQL/camargo_pms.sql` a formato `fa-solid fa-*`.
  - Despliegue de assets locales: `public/assets/vendor/fontawesome/css/all.css` y 8 archivos de fuentes web en `public/assets/fonts/fontawesome/` con política estricta de *Local Assets First* (0 CDNs).
- **Selectores de Fecha Alina Transversales (Flatpickr / D-071):**
  - Implementación del componente oficial Date Picker de Alina (`.camargo-datepicker`) para fechas individuales.
  - Implementación del componente oficial Range Picker de Alina (`.camargo-rangepicker`) para selección de intervalos y períodos en Disponibilidad y Reservas.
  - Controlador modular `public/assets/js/camargo-pickers.js` en Vanilla JS (0 jQuery) con sincronización atómica de inputs canónicos ocultos, despacho de eventos nativos `input` y `change` compatibles con PristineJS y soporte modal/mobile (`disableMobile: true`).
  - **Preservación Inviolable de D-066:** El Range Picker opera puramente como experiencia de usuario (UX); la arquitectura backend conserva `fecha_entrada` y `fecha_salida` independientes como `DATE` (`YYYY-MM-DD`) e intervalo semiabierto $[ \text{entrada}, \text{salida} )$.
- **Cero Dependencia de jQuery:**
  - Código propio 100% en Vanilla JS (ES6+) moderno y nativo.
- **Gobernanza y Documentación:**
  - Registro de decisión vinculante `D-071` en `docs/gobernanza/DECISIONES.md`.
  - Actualización integral de `docs/gobernanza/FRONTEND.md`, `PLANTILLA-ALINA.md`, `CONVENCIONES.md` y `.skills/camargo-ui-alina/SKILL.md`.
  - Preservación íntegra de `admin-dashboard/` (0 modificaciones) y `.env` (0 modificaciones).
  - 0 migraciones de base de datos añadidas (permanece en 001–014).

### Microfase RESERVAS-1A — Corrección Fiscal, Configuración de Hold y Semántica D-061

- **Corrección de Política Fiscal y Snapshot Tributario (D-069 / D-070):**
  - **Eliminación de Asunción Impositiva:** Suprimida cualquier referencia o regla que asuma que toda reserva aplica universalmente 18% IGV.
  - **Contrato Fiscal Provisorio:** Al no existir aún fuente impositiva formal ni categorización tributaria en el PMS, se establece provisoriamente `impuesto = 0.00` (ningún impuesto aplicado por el PMS, sin calificar la operación como exonerada o inafecta) y `total = subtotal`.
  - **Aritmética Exacta con BCMath:** Implementación del método canónico `redondearBc()` sobre strings con `ROUND_HALF_UP`, eliminando cualquier casting o conversión intermedia a tipos de coma flotante binaria (`(float)` o `round()` nativo).
  - **Snapshot Inmutable:** El snapshot de la reserva (`subtotal`, `impuesto`, `total`) y de las unidades asociadas conserva las columnas tributarias preparadas para cuando se integre un motor impositivo formal, manteniendo los valores congelados en el instante de emisión.
- **Corrección de Parámetro Operacional de Hold:**
  - **Eliminación de Default Arbitrario:** Eliminado el valor por defecto de 30 minutos del código, migraciones y seeds (`SQL/migraciones/014_reservas.sql`, `SQL/camargo_pms.sql` y tabla `configuraciones` en base de datos real quedan con `valor = NULL` y `valor_predeterminado = NULL`).
  - **Comportamiento sin Configuración:** Creación de la excepción de dominio `ConfiguracionFaltanteExcepcion` (HTTP 422). Si se intenta crear una reserva en estado `PENDIENTE` y el parámetro `reservas.duracion_hold_minutos` no ha sido definido explícitamente por el negocio, la operación es rechazada limpiamente sin recurrir a fallbacks inventados ni generar errores 500 genéricos.
  - **Reservas Confirmadas:** Las reservas creadas directamente en estado `CONFIRMADA` no requieren hold (`expira_en = NULL`), operando sin depender del parámetro de expiración.
- **Corrección de Gobernanza y Semántica D-061:**
  - **Desacoplamiento de Preservación Histórica:** Separado el principio de ciclo de vida de Reserva (`CANCELACIÓN / EXPIRACIÓN ≠ DELETE`) del contrato D-061.
  - **Contrato Canónico D-061 (`ACTOR ≠ USUARIO`):** D-061 se restringe y formaliza con precisión para la resolución obligatoria del actor ejecutor (`USR_X` para usuario humano autenticado o `CAMARGO_PMS` para procesos de sistema), garantizando de forma inviolable que jamás se asigne un `usuario_id` directamente como `actor_id` por coincidencia numérica.
- **Actualización de Documentación de Gobernanza:**
  - `DECISIONES.md`: Rectificada la decisión D-070 en sus cláusulas fiscal, de hold y de autoría D-061.
  - `BASE-DATOS.md`: Documentado el estado del parámetro de hold sin default inventado y el contrato fiscal provisional.
  - `ROADMAP.md`: Actualizada la descripción del módulo de reservas directas.

### Fase RESERVAS-1 — Núcleo Transaccional de Reservas Directas

- **Dominio Transaccional y Persistencia Relacional (D-070):**
  - **Entidades de Dominio:** Implementación de `Reserva` y `ReservaUnidad` con tipado estricto, métodos de estado (`retieneInventario()`, `haExpirado()`, `estaActiva()`), normalización y serialización desacoplada de la presentación.
  - **Esquema de Base de Datos:** Migración `014_reservas.sql` aplicada exitosamente, creando las tablas `reservas` (cabecera comercial con 4 Foreign Keys y 4 índices optimizados) y `reserva_unidades` (detalle multiunidad con 2 Foreign Keys y restricción única `(reserva_id, unidad_id)`). Esquema general consolidado en 28 tablas físicas y 35 Foreign Keys.
  - **Ampliación de Inventario Diario:** Extensión de `inventario_diario_unidades.tipo_bloqueo` incorporando el valor `RESERVA`.
  - **Parámetro de Configuración:** Semilla `reservas.duracion_hold_minutos` (30 minutos) registrada en `configuraciones` para el control de holds temporales.
  - **Permisos RBAC:** Semillas `reservas.ver`, `reservas.crear`, `reservas.confirmar`, `reservas.cancelar` y `reservas.expirar` asignadas al rol `SUPERADMINISTRADOR` (catálogo ampliado a 30 permisos).
  - **Navegación Alina:** Registrada opción de menú `reservas_directas` bajo el área de operaciones con ruta `/reservas`.
- **Soporte Multiunidad Nativo (1 Reserva : N Unidades):**
  - Capacidad de reservar una o múltiples unidades en una misma operación atómica.
  - Desglose individual de precio por noche, noches, subtotal e impuesto en `reserva_unidades`.
  - Asignación ordenada deterministamente: `ORDER BY unidad_id ASC, fecha ASC` en `inventario_diario_unidades`.
- **Snapshot Financiero Inmutable (D-069):**
  - Cálculo centralizado exclusivamente en el backend; rechazo estricto de totales enviados por clientes o APIs externas.
  - Aritmética de precisión arbitraria mediante `BCMath` en PHP 8.3 y persistencia en tipos `DECIMAL(15,2)` en MySQL 8.4.
  - Moneda canónica `PEN` (ISO 4217), subtotal, 18% IGV y total congelados en el instante de emisión; inmutabilidad histórica garantizada frente a cambios futuros de tarifas maestras o alícuotas tributarias.
  - Redondeo mercantil `ROUND_HALF_UP` en el límite contractual final preservando precisión intermedia sin redondeo prematuro acumulativo.
- **Ciclo de Estados y Expiración Automática de Holds:**
  - Ciclo de estados formalizado: `PENDIENTE`, `CONFIRMADA`, `CANCELADA`, `EXPIRADA`.
  - Retención de inventario en `PENDIENTE` y `CONFIRMADA`; liberación atómica e inmediata en `CANCELADA` y `EXPIRADA`.
  - Expiración de holds vencidos: método `expirarReservasPendientes()` en `ReservaServicio` con procesamiento en lote, reversión atómica de inventario diario y trazabilidad en auditoría.
  - Endpoint web seguro `POST /reservas/expirar` y comando de consola CLI `bin/expirar-reservas.php` para integración en cron o tareas programadas del sistema.
- **Concurrencia, Locking y Rollback Integral (D-067):**
  - Transacciones ACID sobre motor MySQL 8.4.3 LTS InnoDB.
  - Captura explícita de errores 1062 (clave duplicada), 1205 (lock wait timeout) y 1213 (deadlock) con `ROLLBACK` total y emisión de `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict).
  - Rollback multiunidad completo: si la unidad $N$ de una reserva multinoche colisiona, revierte íntegramente las unidades previas sin dejar noches huérfanas en el inventario.
- **Trazabilidad y Cero Eliminación Física (D-061):**
  - Principio `CANCELACIÓN / EXPIRACIÓN ≠ DELETE`: cero eliminación física en `reservas` y `reserva_unidades`.
  - Cancelación exige motivo obligatorio (1-255 caracteres).
  - Auditoría transversal registrando eventos de dominio con autoría humana resuelta (`USR_X`) o actor de sistema.
- **Interfaz Alina y Controladores:**
  - Controlador `ReservaControlador` protegiendo cada acción con RBAC (`reservas.ver`, `reservas.crear`, etc.) y validación CSRF.
  - Vista Alina responsive en `app/Vistas/reservas/index.php` con KPIs operativos, filtros en tiempo real, modales de reserva multiunidad reactivo, detalle con desglose de snapshot y modal de cancelación con validación.
  - JavaScript moderno `public/assets/js/gestion-reservas.js` en Vanilla JS (0 jQuery), Fetch API, PristineJS y SweetAlert2.
- **Verificación Automatizada Completa:**
  - Matriz de dominio e integración: 50/50 PASS (`test_reservas_matriz_50.php`).
  - Suite de concurrencia y locking: 6/6 PASS (`test_reservas_concurrencia.php`).
  - Suite HTTP E2E Real Apache HTTPS: 15/15 PASS (`test_e2e_reservas.php`).
  - Regresiones históricas: 205/205 PASS.
  - Total acumulado del sistema: 725 casos únicos / 731 ejecuciones brutas (100% PASS).
- **Invariantes del Incremento:**
  - `admin-dashboard/` y `.env` intactos.
  - Paridad 100% entre `SQL/camargo_pms.sql` y `SQL/migraciones/001_...` a `014_reservas.sql`.

### Microfase GATE FINANCIERO-1 — Definición del Contrato Monetario y Cierre de P-005

- **Formalización de la Decisión D-069 (Cierre Definitivo de P-005):**
  - **Moneda Canónica ISO 4217:** Adopción oficial del Sol peruano (`PEN`) como moneda canónica operativa de Camargo PMS.
  - **Desacoplamiento de Símbolo:** Principio `MONEDA ≠ SÍMBOLO`. El símbolo comercial `'S/'` queda desacoplado del dominio y reservado exclusivamente a la capa visual de interfaz (`Vistas/`), impidiendo su almacenamiento o concatenación en base de datos.
  - **Prohibición Terminante de Coma Flotante:** Demostradas empíricamente las fallas de precisión binaria IEEE 754 de `FLOAT`/`DOUBLE` (`0.1 + 0.2 = 0.30000000000000004`, pérdida de redondeo en `1.005`). Se prohíben en DDL, consultas SQL y modelos PHP.
  - **Escala y Precisión Numérica Diferenciada:**
    - `DECIMAL(15,2)` en MySQL para importes comerciales y saldos finales (céntimos).
    - `DECIMAL(15,4)` en MySQL para tarifas unitarias base, consumos de suministros y tasas de impuestos (ej. `0.1800` para 18% IGV).
  - **Aritmética Exacta en PHP:** Implementación mediante la extensión nativa `BCMath` y cadenas numéricas (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`) sobre PHP 8.3.30.
  - **Estándar de Redondeo ROUND_HALF_UP:** Adopción del redondeo mercantil estándar hacia arriba en el límite exacto `.005` para importes positivos.
  - **Precisión Intermedia sin Redondeo Prematuro:** Demostrado que redondear cada ítem individualmente a 2 decimales versus redondear sobre la suma agregada genera descuadres de céntimos. Se establece como regla preservar precisión ($\ge 4$ decimales) en cálculos intermedios y aplicar `ROUND_HALF_UP` en el límite contractual final.
  - **Autoridad Financiera Centralizada en Backend:** La capa de `Servicios` es la única autoridad de cálculo. El frontend es puramente estimativo y se rechazan categóricamente totales o precios enviados a ciegas por el cliente.
  - **Preparación Multimoneda:** Exigencia obligatoria de la columna `moneda_codigo VARCHAR(3)` en toda tabla o entidad con importes financieros.
  - **Inmutabilidad Histórica y Snapshots:** Las transacciones emitidas congelan snapshots de tarifas pactadas e impuestos vigentes. Cero recálculo ante variaciones posteriores de catálogo o de alícuotas fiscales.
  - **Determinismo del Saldo:** Soporte de pagos parciales donde $\text{Saldo Pendiente} = \text{Total Contratado} - \sum(\text{Pagos Válidos})$.
  - **Preservación Contable:** Principio `ANULACIÓN / REVERSO ≠ DELETE`. Cero borrado físico de pagos o movimientos financieros; reversos mediante estados de anulación o contra-asientos con trazabilidad transversal bajo D-061.
- **Verificación Técnica Automatizada (FIN-01 a FIN-20):**
  - Implementación y ejecución de la suite técnica `test_finanzas_p005.php` verificando los 20 requisitos monetarios y fiscales sobre MySQL 8.4.3 LTS y PHP 8.3.30 (20/20 PASS).
- **Reconciliación Matemática Canónica:**
  - 16 suites ejecutadas en verde (0 fallos).
  - 560 casos independientes de dominio e integración (540 previos + 20 FIN-01..20).
  - 94 casos HTTP E2E reales contra servidor Apache HTTPS.
  - 654 casos tabulados / 660 ejecuciones brutas acumuladas (100% PASS).
- **Invariantes del Incremento:**
  - Cero código de producción prematuro (no se implementaron servicios de reservas ni caja).
  - Cero migración 014 (migraciones permanecen en 001–013).
  - `admin-dashboard/` y `.env` intactos.

### Microfase DISPONIBILIDAD-1A — Verificación final de concurrencia, motor SQL y reconciliación

- **Confirmación Empírica del Motor y Versión de Base de Datos:**
  - Comprobado mediante `SELECT VERSION()` y `SELECT @@version_comment` que el motor en ejecución es **MySQL Community Server 8.4.3 LTS (GPL)**.
  - Rectificadas referencias documentales a fin de reflejar con exactitud la base instalada.
- **Verificación Rigurosa de Contratos de Concurrencia 1205 y 1213:**
  - **G-1205 (Lock Wait Timeout Exceeded):** Inducido en MySQL 8.4.3 configurando `innodb_lock_wait_timeout = 1` y contención por clave de inventario. Confirmada captura de error nativo `["HY000", 1205]`, rollback integral en servicio (`inTransaction = false`), y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409).
  - **G-1213 (Deadlock Detected):** Inducido ciclo real de interbloqueo circular entre dos procesos concurrentes en InnoDB. Confirmada detección inmediata por el Deadlock Detector arrojando `["40001", 1213]`. Captura en `DisponibilidadServicio`, reversión atómica y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409).
- **Ejecución y Reconciliación Integral Suite por Suite:**
  - Ejecución en verde de 15 suites de prueba cubriendo todo el histórico del PMS: Identidad (27/27), Usuarios (40/40), Actor (6/6), Roles (40/40), Rol-Hist (10/10), Menú (9/9), Auditoría (40/40), Sanitizador (62/62), Configuración (40/40), Propiedades (40/40), Prop-Hist (10/10), Unidades (40/40), Uni-Hist (10/10), Disponibilidad (40/40), Concurrencia Productiva (5/5), Contratos 1205/1213 (2/2) y E2E Apache HTTPS (94/94).
- **Reconciliación Matemática Canónica:**
  - 540 casos independientes de dominio e integración (493 previos + 40 DISP + 5 CONC-PROD + 2 G-1205/G-1213).
  - 94 casos HTTP E2E reales contra servidor Apache HTTPS.
  - 640 ejecuciones acumuladas con 0 fallos (100% PASS).

### Fase DISPONIBILIDAD-1 — Motor Central de Disponibilidad e Inventario Diario

- **Arquitectura e Integridad Transaccional (D-066, D-067 y D-068):**
  - **Inventario Diario Sparse:** Implementada la tabla `inventario_diario_unidades` en motor InnoDB con restricción inviolable `UNIQUE KEY uq_inventario_unidad_fecha (unidad_id, fecha)` como última línea de defensa ante sobreventas y carreras concurrentes.
  - **Inserción Determinista y Rollback Íntegro:** Operaciones de ocupación multinoche procesadas en orden `ORDER BY unidad_id ASC, fecha ASC`, con captura de errores de clave duplicada (1062), lock wait timeout (1205) y deadlock (1213), rollback completo y traducción a `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict).
  - **Registro Maestro de Bloqueos:** Tabla `bloqueos_unidad` para la gestión de indisponibilidades técnicas (`MANTENIMIENTO`) y administrativas (`BLOQUEO_MANUAL`), con trazabilidad de actores (`creado_por_actor_id`, `liberado_por_actor_id`), marcas temporales y ciclo de vida histórico (`ACTIVO` ↔ `LIBERADO`) sin eliminación física (`DELETE` = 0 en registro maestro).
  - **Liberación Atómica de Inventario:** El método `liberar()` actualiza el estado del bloqueo y elimina atómicamente todas sus filas asociadas en `inventario_diario_unidades` (`DELETE ... WHERE origen_tipo = 'BLOQUEO_MANUAL' AND origen_id = ?`), restableciendo la disponibilidad de forma inmediata.
  - **Semántica Temporal Hotelero:** Intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$ donde la noche de salida queda liberada para check-in simultáneo sin falso conflicto. Noches: $\text{salida} - \text{entrada} \ge 1$.
  - **Resolución de Zona Horaria IANA:** Campo `propiedades.zona_horaria` evaluado prioritariamente, con fallback al parámetro central `operacion.zona_horaria_predeterminada` (`America/Lima`) y validación segura contra identificadores no canónicos.
  - **Preservación Estricta de Gobernanza P-005:** Cero columnas o nociones de tarifas, precios, costos, monedas, redondeos o impuestos en las tablas o entidades de disponibilidad.
- **Base de Datos y Migración 013:**
  - Migración `SQL/migraciones/013_disponibilidad.sql` ejecutada con paridad exacta al 100% en `SQL/camargo_pms.sql` (26 tablas, 29 Foreign Keys).
  - Permisos RBAC sembrados y asignados a `SUPERADMINISTRADOR`: `disponibilidad.ver`, `disponibilidad.bloquear`, `disponibilidad.liberar`.
  - Opción de menú sembrada: `disponibilidad_calendario` ('Disponibilidad', ruta `/disponibilidad`, icono `ti ti-calendar-event`, orden 3) bajo la categoría `propiedades`.
  - Parámetro de sistema sembrado: `'operacion.zona_horaria_predeterminada'` = `'America/Lima'`.
- **Capa de Backend (MVC):**
  - Modelos de dominio: `BloqueoUnidad` e `InventarioDiario` con tipado estricto y métodos de serialización.
  - Modelo `Propiedad`: soporte de `zonaHoraria` preservando compatibilidad posicional del constructor.
  - Excepciones de dominio: `ConflictoDisponibilidadExcepcion` (409), `IntervaloInvalidoExcepcion` (422), `BloqueoNoEncontradoExcepcion` (404).
  - Repositorio `DisponibilidadRepositorio`: consultas de disponibilidad sparse, inserción determinista atómica, eliminación atómica por origen, CRUD de bloqueos y matriz de inventario.
  - Servicio `DisponibilidadServicio`: orquestador de lógica de negocio, resolución de huso horario, validación de intervalos, cálculo de noches, transacciones ACID con captura de 1062/1205/1213, auditoría transversal D-061 y matriz mensual.
  - Controlador `DisponibilidadControlador`: 7 endpoints HTTP asegurados con `AutorizacionIntermediario` (`index`, `consultar`, `bloquear`, `liberar`, `bloqueosJson`, `matrizJson`).
- **Interfaz Alina y Experiencia de Usuario:**
  - Vista Alina responsiva en `app/Vistas/disponibilidad/index.php` con navegación por pestañas (Consulta, Matriz/Rack mensual, Bloqueos activos), KPIs en tiempo real (Totales, Disponibles, Bloqueadas, Tasa) y modales operativos de bloqueo y liberación.
  - Módulo JavaScript Vanilla moderno en `public/assets/js/gestion-disponibilidad.js` (0 dependencias jQuery), consumo asíncrono con Fetch API, protección CSRF, PristineJS v1.1.0 para validación cliente y diálogos interactivos con SweetAlert2.
- **Batería de Pruebas Automatizadas:**
  - Suite de Concurrencia Productiva sobre BD Real: CONC-PROD-01..05 (5/5 PASS).
  - Matriz Formal de Disponibilidad e Inventario: DISP-01..40 (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS: E2E-DISP-01..12 (12/12 PASS).

### Gate Operativo-1 / Gate Operativo-1A — Definición del Tiempo Hotelero y Concurrencia de Disponibilidad (P-004 + P-006) [HOMOLOGADO — ef6a806]

- **Cierre Formal de Decisión P-004 (D-066 — Modelo Temporal Hotelero y Zonas Horarias IANA):**
  - Formalizada la separación ontológica tripartita: `INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL`.
  - Instantes técnicos (creación, auditoría, sesiones, tokens, webhooks) se almacenan normalizados en UTC (`TIMESTAMP`).
  - Fechas hoteleras (noches de estancia) se modelan como `DATE` local de la propiedad; no son timestamps ni se convierten a UTC.
  - Horarios de check-in y check-out se gobernarán mediante parámetros configurables del PMS (`operacion.hora_checkin_predeterminada` y `operacion.hora_checkout_predeterminada`), cuyos valores iniciales se definirán operativamente antes de producción; no alteran qué noches están ocupadas.
  - Prohibidos los offsets fijos (`UTC-5`). Se adoptan identificadores canónicos IANA con zona predeterminada del PMS en `America/Lima` (`operacion.zona_horaria_predeterminada`).
  - Preparada la tabla `propiedades` para incorporar la columna nullable `zona_horaria VARCHAR(50)` en `DISPONIBILIDAD-1`, heredando la zona del PMS si es nula.
  - Intervalo de estancia modelado matemáticamente como semiabierto: $[\text{fecha\_entrada}, \text{fecha\_salida})$. El día de salida queda libre para check-in simultáneo sin conflicto.
  - Contrato de noches: $\text{noches} = \text{fecha\_salida} - \text{fecha\_entrada}$ con $\text{noches} \ge 1$ en el motor ordinario.
- **Cierre Formal de Decisión P-006 (D-067 — Concurrencia de Disponibilidad e Inventario Diario):**
  - Aprobado el **Modelo Híbrido**: desacoplamiento entre contrato comercial (`reservas`/`bloqueos`) e inventario diario físico (`inventario_diario_unidades`).
  - Semántica *sparse*: inventario diario registra únicamente noches ocupadas/bloqueadas (cero pregeneración de años vacíos); disponibilidad formalizada como ausencia de registro para `(unidad_id, fecha)` en el rango semiabierto.
  - Barrera absoluta de concurrencia: restricción `UNIQUE (unidad_id, fecha)` en InnoDB que previene condiciones de carrera y sobreventa (cero dependencia de lógica en frontend o checks previos desfasados).
  - Atomicidad transaccional y rollback completo ante colisiones (cero reservas parcialmente confirmadas, cero noches huérfanas).
  - Orden determinista de bloqueo: `ORDER BY unidad_id ASC, fecha ASC`, reduciendo sustancialmente el riesgo de deadlocks y adquisiciones cruzadas.
  - Captura y traducción de errores de clave duplicada (1062), lock wait timeouts (1205) y deadlocks (1213) a `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict), nunca HTTP 500.
  - Liberación atómica de noches por cancelación o expiración de hold temporal (`DELETE FROM inventario_diario_unidades WHERE reserva_id = ?`).
  - Centralización multicanal: Camargo PMS como única fuente de verdad autoritativa para PMS, WordPress, App móvil, OTAs y Webhooks.
- **Micro-fase GATE-OPERATIVO-1A (Corrección de Gobernanza):**
  - Retiradas las horas fijas (15:00 y 11:00) estableciendo parámetros configurables sin valores arbitrarios preasignados.
  - Sustituida la afirmación absoluta sobre deadlocks por la formulación técnica precisa de mitigación mediante orden determinista y captura obligatoria de códigos MySQL 1062, 1205 y 1213 con rollback.
- **Preservación de Decisión P-005:**
  - P-005 (moneda, redondeo e impuestos) se mantiene formal y estrictamente **PENDIENTE** para antes de la fase de tarifas y caja.
- **Prueba Técnica Aislada de Concurrencia:**
  - Harness automatizado (`harness_concurrencia_p006.php`) con 5/5 verificaciones superadas en base de datos efímera aislada (`camargo_pms_concurrencia_p006`): concurrencia directa multiconexión PDO, atomicidad multinoche con rollback, intervalo semiabierto, liberación atómica y cero locks residuales en InnoDB (`innodb_lock_wait_timeout = 2`).
- **Esquema de Base de Datos y Código Productivo:**
  - Cero código productivo nuevo; cero migración 013 (migraciones permanecen en 001–012).

### Fase UNIDADES-1 — Maestro Central de Unidades Físicas y Alojables por Propiedad

- **Arquitectura y Principios de Delimitación de Dominio:**
  - **`PROPIEDAD ≠ UNIDAD`:** La propiedad es el contenedor físico raíz (`propiedades`); la unidad (`unidades`) modela la división física, departamento, habitación o espacio divisible e individualizable con destino de alojamiento. Toda unidad está subordinada obligatoriamente a una propiedad física existente y activa (`propiedad_id INT NOT NULL`, `fk_unidades_propiedad`). Cero unidades huérfanas en el sistema.
  - **`UNIDAD ≠ REGISTRO DESECHABLE`:** Cero eliminación física en toda la arquitectura (`DELETE FROM unidades` = 0). Ciclo de vida operacional gobernado exclusivamente por la alternancia de estados `ACTIVO` e `INACTIVO`. Ausencia absoluta de métodos `eliminar()` en repositorio y servicio, y de acciones de eliminación en controlador. Rutas HTTP DELETE no registradas responden `404 Not Found`.
  - **`UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD`:** Respeto estricto a las decisiones de gobernanza P-004 (zona horaria y corte hotelero), P-005 (moneda, redondeo e impuestos) y P-006 (concurrencia de disponibilidad). Cero atributos o lógica de fechas, check-in/out, calendarios, tarifas monetarias o contratos de arrendamiento en esta fase.
  - **Unicidad de Código Acotada a la Propiedad (D-065):** Restricción de unicidad compuesta `UNIQUE KEY uq_unidades_propiedad_codigo (propiedad_id, codigo)`. Se permite el uso de códigos idénticos (ej. "101") en propiedades distintas, garantizando unicidad estricta al interior de una misma propiedad.
  - **Regla de Negocio de Propiedad Inactiva:** Una propiedad inactiva preserva intactas sus unidades históricas para consulta y trazabilidad, pero bloquea incondicionalmente la creación de nuevas unidades con `DominioReglaExcepcion` (HTTP 422).
- **Base de Datos y Migración 012:**
  - Migración `SQL/migraciones/012_unidades.sql` aplicada con paridad canónica al 100% en `SQL/camargo_pms.sql` (24 tablas en total, 25 foreign keys).
  - Tabla `tipos_unidad`: catálogo clasificador de tipologías físicas (`DEPARTAMENTO`, `HABITACION`, `CASA`, `SUITE`, `BUNGALOW`).
  - Tabla `unidades`: campos físicos `propiedad_id`, `tipo_unidad_id`, `codigo`, `nombre`, `nivel` (piso/planta), `capacidad_estandar`, `capacidad_maxima`, `numero_camas`, `numero_banos`, `descripcion`, `estado`, `creado_en`, `actualizado_en`.
  - Permisos RBAC sembrados: `unidades.ver`, `unidades.crear`, `unidades.editar`, `unidades.cambiar_estado` asignados al rol `SUPERADMINISTRADOR`.
  - Menú de navegación sembrado: submenú `unidades_catalogo` ('Catálogo de Unidades', ruta `/unidades`, orden 2) bajo la categoría principal `propiedades`.
- **Capa de Dominio y Validaciones:**
  - Modelos de dominio `TipoUnidad` y `Unidad` con tipado estricto, métodos de utilidad `estaActiva()`, cálculo de ocupación total `capacidadTotal()` (`capacidad_estandar + capacidad_maxima`), y serialización consistente `haciaArreglo()`, `aArreglo()`, `aArray()`, y recreación `desdeArreglo()`.
  - Excepciones de dominio `UnidadDuplicadaExcepcion` (HTTP 409) y `UnidadNoEncontradaExcepcion` (HTTP 404).
  - Validaciones de dominio en `UnidadServicio`: validación de propiedad activa existente, tipo de unidad existente, unicidad de código por propiedad, longitudes de texto, capacidades no negativas con `capacidad_maxima >= capacidad_estandar`.
- **Servicio y Trazabilidad Transversal D-061:**
  - `UnidadServicio`: punto único de acceso para operaciones sobre unidades físicas.
  - Actualizaciones con cálculo diferencial exacto (`diff`) que previene la emisión de auditoría redundante cuando no hay cambios.
  - Trazabilidad integral de operaciones (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) en `auditoria` bajo D-061, resolviendo el actor humano (`USR_x`) mediante `resolverActorEjecutor()` y reservando `CAMARGO_PMS` (`id = 1`) para ejecuciones de sistema.
  - Enlace bidireccional en `AuditoriaServicio::listarPorEntidad()` para consultar el historial de auditoría de cualquier entidad del sistema.
- **Controlador, Rutas y Permisos RBAC:**
  - Rutas registradas en `public/index.php`: `GET /unidades`, `GET /unidades/datos`, `GET /unidades/{id}`, `GET /unidades/{id}/perfil`, `POST /unidades`, `PUT /unidades/{id}`, `PATCH /unidades/{id}/estado`, y variantes POST compatibles.
  - Integración en `PropiedadControlador` y vista de perfil `app/Vistas/propiedades/detalle.php`: tabla de unidades asociadas al inmueble y conteos consolidados.
- **Interfaz Alina y Ficha Técnica:**
  - Vista general `app/Vistas/unidades/index.php` con maquetación de tarjetas Alina, tabla dinámica, filtros de búsqueda textual, selector por propiedad, por tipo y por estado, y modal interactivo para creación y edición.
  - Vista de perfil `app/Vistas/unidades/detalle.php` con ficha técnica de la unidad, especificaciones físicas (camas, baños, capacidad estándar y máxima, nivel), tarjeta del inmueble contenedor, trazabilidad de auditoría e indicador visual del principio `PROPIEDAD ≠ UNIDAD`.
  - Script Vanilla JS modular `public/assets/js/gestion-unidades.js` con debounce de búsqueda, paginación dinámica, validación cliente mediante PristineJS v1.1.0 y confirmaciones con SweetAlert2.
- **Pruebas y Verificaciones:**
  - Matriz formal unitaria y de integración `UNI-01` a `UNI-40` (40/40 PASS).
  - Prueba específica de persistencia histórica `UNI-HIST-01` de 10 pasos (10/10 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS `E2E-UNI-01` a `E2E-UNI-12` (12/12 PASS).
  - Regresión integral sin fallos sobre todos los módulos previos del sistema (421/421 verificaciones directas PASS).

### Fase PROPIEDADES-1 — Maestro Central de Propiedades e Inmuebles Físicos

- **Arquitectura y Principio Ontológico de Delimitación Física (`PROPIEDAD ≠ UNIDAD`):**
  - Implementación de la tabla `propiedades` como raíz inmobiliaria física mediante migración `011_propiedades.sql` con paridad exacta al 100% en `SQL/camargo_pms.sql` (22 tablas, 23 FKs).
  - Delimitación ontológica estricta: una propiedad modela única y exclusivamente el contenedor físico, edificación o inmueble raíz (ej. "Edificio Ayuda Mutua"). Se prohíbe taxativamente modelar unidades, tipologías de alojamiento, habitaciones o camas en esta fase, reservando dicha modelación para `UNIDADES-1`.
  - Respeto riguroso de gobernanza: Decisiones P-004 (zona horaria y corte hotelero), P-005 (moneda, redondeo e impuestos) y P-006 (estrategia de concurrencia para disponibilidad) permanecen estrictamente pendientes, sin introducir atributos temporales, tarifarios o de inventario prematuros.
- **Preservación Histórica del Ciclo de Vida (`PROPIEDAD ≠ REGISTRO DESECHABLE`):**
  - Cero eliminación física en toda la arquitectura (`DELETE FROM propiedades` = 0).
  - Ciclo de vida operacional gobernado exclusivamente por la alternancia de estados `ACTIVO` e `INACTIVO`.
  - Ausencia absoluta de métodos `eliminar()` en repositorio y servicio, y de acciones de eliminación en controlador. Rutas HTTP DELETE no registradas responden `404 Not Found`.
- **Capa de Dominio y Validaciones Geográficas:**
  - Modelo de dominio `Propiedad` con tipado estricto, métodos de utilidad `estaActiva()`, `obtenerUbicacionCompleta()`, `obtenerCoordenadas(): array`, y serialización consistente `haciaArreglo()`, `aArreglo()`, `aArray()`, y recreación `desdeArreglo()`.
  - Excepciones de dominio `PropiedadDuplicadaExcepcion` (HTTP 409) y `PropiedadNoEncontradaExcepcion` (HTTP 404).
  - Validaciones de dominio en `PropiedadServicio`: código alfanumérico único (3-30 caracteres, mayúsculas normalizadas), nombre obligatorio (mínimo 3 caracteres), dirección física obligatoria y no vacía, vinculación a país válido del catálogo (`paises`), y validación defensiva de coordenadas geográficas en rangos válidos (latitud en `[-90.0, 90.0]`, longitud en `[-180.0, 180.0]`).
- **Servicio y Trazabilidad Transversal D-061:**
  - `PropiedadServicio`: punto de acceso único para gestión de propiedades.
  - Actualización funcional con cálculo diferencial exacto (`diff`) que previene la emisión de eventos de auditoría redundantes cuando no hay cambios.
  - Trazabilidad integral de operaciones (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) en `auditoria` bajo D-061, resolviendo el actor humano (`USR_x`) mediante `resolverActorEjecutor()` y reservando `CAMARGO_PMS` (`id = 1`) para ejecuciones de sistema.
- **Controlador, Rutas y Permisos RBAC:**
  - Nuevos permisos de sistema: `propiedades.ver`, `propiedades.crear`, `propiedades.editar`, `propiedades.cambiar_estado`.
  - Rutas registradas en `public/index.php`: `GET /propiedades`, `GET /propiedades/datos`, `GET /propiedades/{id}`, `GET /propiedades/{id}/perfil`, `POST /propiedades`, `PUT /propiedades/{id}`, `PATCH /propiedades/{id}/estado`, y variantes POST compatibles.
  - Nueva opción de menú autorizada: Nivel 1 `propiedades` ('Propiedades', icono `ti ti-building`, orden 2) y Nivel 2 `propiedades_catalogo` ('Catálogo de Inmuebles', ruta `/propiedades`, permiso `propiedades.ver`).
- **Interfaz Alina y Ficha Técnica:**
  - Vista general `app/Vistas/propiedades/index.php` con maquetación de tarjetas Alina, tabla dinámica, filtros de búsqueda, selector de estado y modal interactivo para creación y edición.
  - Vista de perfil `app/Vistas/propiedades/detalle.php` con ficha técnica del inmueble, tarjeta de georreferenciación con enlace a Google Maps, trazabilidad de auditoría y bloque reservado con advertencia arquitectónica del principio `PROPIEDAD ≠ UNIDAD` hacia `UNIDADES-1`.
  - Script Vanilla JS modular `public/assets/js/gestion-propiedades.js` con debounce de búsqueda, paginación dinámica, validación cliente mediante PristineJS v1.1.0 y confirmaciones con SweetAlert2.
- **Pruebas y Verificaciones:**
  - Matriz formal unitaria y de integración `PROP-01` a `PROP-40` (40/40 PASS).
  - Prueba específica de persistencia histórica `PROP-HIST-01` de 10 pasos (10/10 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS `E2E-PROP-01` a `E2E-PROP-12` (12/12 PASS).
  - Regresión integral sin fallos sobre todos los módulos previos del sistema.

### Fase CONFIGURACIÓN-1 — Núcleo Central de Configuración y Parámetros del Sistema

- **Arquitectura y Separación Canónica (`CONFIGURACIÓN FUNCIONAL (BD) ≠ ENTORNO TÉCNICO (.env)`):**
  - Implementación de la tabla `configuraciones` para el catálogo de parámetros funcionales del PMS mediante migración `010_configuracion_sistema.sql` con paridad exacta al 100% en `SQL/camargo_pms.sql` (21 tablas, 22 FKs).
  - Separación estricta: parámetros operativos residen en BD; secretos de infraestructura y credenciales de servicios permanecen inmutables en `.env` (cero modificaciones a `.env` desde UI o backend).
  - Parámetros sensibles (`es_sensible = 1`) son automáticamente ofuscados (`***`) en los registros de auditoría y salidas operativas.
  - Respeto riguroso de gobernanza: Decisiones P-004 (zona horaria y fecha hotelera) y P-005 (moneda, redondeo e impuestos) se mantienen formalmente pendientes, sin sembrar parámetros especulativos.
- **Contrato de Tipado Fuerte y Capa de Dominio:**
  - Enumeración `TipoConfiguracion` soportando `TEXTO`, `ENTERO`, `DECIMAL`, `BOOLEANO`, `FECHA`, `HORA` y `JSON`, con validación sintáctica, casting a tipos nativos PHP y serialización canónica.
  - Modelo de dominio `ConfiguracionParametro` con métodos `obtenerValorTipado()`, `obtenerValorPredeterminadoTipado()`, `esEditable()`, `esSensible()` y serialización `haciaArreglo()`.
  - Repositorio `ConfiguracionRepositorio` con consultas estructuradas por clave, ID, grupo y soporte de transacciones PDO.
  - Excepciones de dominio `ConfiguracionNoEncontradaExcepcion` (404) y `ConfiguracionNoEditableExcepcion` (422).
- **Servicio Canónico y Trazabilidad D-061:**
  - `ConfiguracionServicio`: punto de acceso único para la lectura y escritura de parámetros.
  - Caché de memoria en tiempo de petición (`request-scoped`) para optimizar lecturas consecutivas de alta frecuencia.
  - Actualización atómica en lote (`actualizarMultiples()`) bajo una única transacción con reversión completa ante fallos y `correlacion_id` unificado.
  - Parámetros protegidos (`editable = 0`, ej. `sistema.version_instalada`) protegidos incondicionalmente contra mutación.
  - Capacidad de reversión o restablecimiento a valores de fábrica (`restaurarPredeterminado()`).
  - Auditoría transversal imputando la autoría al actor humano correspondiente (`USR_x`) mediante `resolverActorEjecutor()`, cumpliendo D-061.
- **Controlador, Rutas y Permisos RBAC:**
  - Nuevos permisos de sistema: `configuracion.ver` y `configuracion.editar`.
  - Rutas registradas en `public/index.php`: `GET /configuracion/sistema`, `GET /configuracion/sistema/datos`, `POST /configuracion/sistema`, `PUT /configuracion/sistema`, `POST /configuracion/sistema/restaurar` y `POST /configuracion/sistema/{clave}/restaurar`.
  - Nueva opción de menú autorizada: `config_sistema` ('Configuración General', `/configuracion/sistema`, orden 1 bajo `configuracion`).
- **Interfaz Alina y Assets Propios:**
  - Vista `app/Vistas/configuracion/sistema/index.php` estructurada en pestañas temáticas (`General`, `Localización`, `Operación`), badges de claves técnicas y controles adaptados por tipo de dato (switches, inputs numéricos, selectores, badges protegidos).
  - Script Vanilla JS modular `public/assets/js/gestion-configuracion.js` con soporte de validación cliente (PristineJS v1.1.0) y diálogos de confirmación defensivos con SweetAlert2.
- **Pruebas y Verificaciones:**
  - Matriz formal unitaria y de integración `CFG-01` a `CFG-40` (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS `E2E-CFG-01` a `E2E-CFG-12` (12/12 PASS).
  - Cero regresiones en toda la batería histórica de pruebas (406 pruebas independientes unitarias/integración + 58 pruebas E2E reales 100% PASS).

### Micro-fase ROLES-2A — Preservación Histórica del Ciclo de Vida de Roles

- **Preservación Histórica del Ciclo de Vida (`ROL ≠ REGISTRO DESECHABLE`):**
  - Reemplazado el criterio provisional de eliminación física de roles introducido en ROLES-2 antes de la homologación definitiva.
  - Formalizado el principio de que los roles participan en autorizaciones, auditoría, asignaciones y relaciones históricas; su ciclo de vida operacional se administra exclusivamente mediante la alternancia `ACTIVO` / `INACTIVO`.
  - Retiradas del enrutador las rutas de eliminación física (`DELETE /configuracion/roles/{id}` y `POST /configuracion/roles/{id}/eliminar`).
  - Retirada la acción productiva `eliminar()` de `RolControlador` y la operación `eliminarRol()` de `RolServicio`.
  - Retirado el botón de eliminar, diálogo SweetAlert2 de borrado y llamada Fetch de la interfaz Alina (`public/assets/js/gestion-roles.js`).
  - La UI comunica el estado de forma clara y ofrece exclusivamente alternancia de estado: *Desactivar* para roles activos y *Activar* para roles inactivos.
  - Se mantiene en `RolRepositorio::eliminar()` su presencia de bajo nivel heredada (originada en commit `25018c4`, `fase-roles-1`), sin invocación en servicios, controladores ni interfaz.
- **Protección y Trazabilidad:**
  - Preservada la protección incondicional de `SUPERADMINISTRADOR` (no desactivable, código inmutable, permisos críticos protegidos).
  - Preservada la protección de roles de sistema (`es_sistema = 1`).
  - La bitácora de auditoría no genera eventos `ELIMINAR` sobre roles; registra de forma normalizada las acciones `DESACTIVAR` y `ACTIVAR` con autoría humana resuelta conforme a D-061.
- **Pruebas y Verificaciones:**
  - Matriz formal `ROL2-01` a `ROL2-40` adaptada al contrato de ciclo de vida histórico (40/40 PASS).
  - Implementada la prueba específica de persistencia histórica `ROL-HIST-01` (10/10 pasos PASS).
  - Suite HTTP E2E Real contra Apache HTTPS adaptada (`E2E-ROL2-01` a `E2E-ROL2-12`), verificando respuesta HTTP 404 ante intentos de DELETE físico (12/12 PASS).
  - Batería completa de regresiones aprobada al 100%.

### Fase ROLES-2 — Administración Visual de Roles y Permisos

- **Administración Visual de Roles y Permisos (`/configuracion/roles`):**
  - Implementada la interfaz administrativa completa con maquetación Alina y Bootstrap 5 bajo `/configuracion/roles` y opción de menú autorizada (`config_roles`, permiso `roles.ver`).
  - Catálogo de roles con búsqueda en tiempo real (nombre, clave, descripción), filtros por estado (`ACTIVO`/`INACTIVO`), conteo agregado de usuarios vinculados y permisos asociados.
  - Cuatro modales Bootstrap 5 orquestados de forma asíncrona:
    - *Crear Rol:* Formulario con validación en cliente mediante PristineJS v1.1.0 local, clave técnica normalizada en mayúsculas (alfanumérico y guión bajo, 3 a 50 caracteres), nombre (2 a 100 caracteres), descripción opcional y estado inicial. Prohibición de crear roles con la clave reservada `SUPERADMINISTRADOR`.
    - *Editar Rol:* Modificación segura de nombre, descripción y estado operativo. Clave técnica protegida en modo solo lectura para roles del sistema. Prohibición de desactivar el rol `SUPERADMINISTRADOR`.
    - *Matriz de Permisos:* Visualización jerárquica agrupada por módulo funcional (`usuarios`, `roles`, `permisos`, `menu`) con checkboxes interactivos, botones de selección masiva global y por módulo, contador reactivo de permisos seleccionados y protección explícita de `SUPERADMINISTRADOR` que bloquea la revocación de permisos críticos de administración.
    - *Usuarios Vinculados:* Consulta en solo lectura de las cuentas humanas que ostentan el rol, con detalle de la `Persona` vinculada, estado de la cuenta y fecha de asignación.
- **Autorización Dinámica en Tiempo Real:**
  - La actualización matricial de permisos de un rol impacta de inmediato en `puede()` y `obtenerPermisosEfectivos()` para los usuarios activos sin requerir re-login ni revocación forzada de sesiones.
- **Protección Inviolable de `SUPERADMINISTRADOR` y Roles de Sistema:**
  - El rol estructural `SUPERADMINISTRADOR` no puede ser renombrado, desactivado ni eliminado físicamente (`RolProtegidoExcepcion`).
  - La sincronización matricial impide revocar los permisos críticos de administración del rol `SUPERADMINISTRADOR` (`roles.ver`, `roles.editar`, `permisos.ver`, `usuarios.ver`, `usuarios.editar`).
  - Se preserva el invariante pesimista del último Superadministrador humano activo (`UltimoSuperadministradorExcepcion`).
  - Roles con `es_sistema = 1` no permiten modificación de clave técnica ni eliminación física.
- **Trazabilidad y Auditoría Transversal bajo D-061:**
  - Todas las operaciones de creación (`CREAR`), edición (`EDITAR`), cambio de estado (`ACTIVAR`/`DESACTIVAR`), eliminación (`ELIMINAR`) y sincronización matricial (`ASIGNAR`/`REVOCAR`) resuelven el `ActorAuditoria` del ejecutor mediante `resolverActorEjecutor(?int $usuarioId)`.
  - La sincronización matricial agrupa todos los eventos atómicos de asignación y revocación bajo un identificador de correlación unificado (`correlacion_id`).
  - Cero fugas de credenciales, tokens o hashes en auditoría.
- **Base de Datos y Esquema:**
  - Esquema completamente cubierto por las migraciones existentes `001..009`. Cero migraciones nuevas creadas (001-009 aplicadas, 0 pendientes).
  - Archivos bajo `SQL/` y catálogo `admin-dashboard/` intactos.
- **Pruebas y Verificaciones:**
  - Matriz formal `ROL2-01` a `ROL2-40` aprobada al 100% (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS (`test_e2e_roles2.php`) aprobada al 100% (12/12 PASS).
  - Cero regresiones en todo el árbol histórico de pruebas (ACTOR 6/6, USR 40/40, E2E-USR 10/10, AUD 40/40, Sanitizador 62/62, E2E-AUD 8/8, Menú 40/40).

### Micro-fase USUARIOS-1A — Corrección de Identidad del Actor de Auditoría

- **Resolución Rigurosa de Identidad de Actor (`ACTOR ≠ USUARIO`):**
  - Subsanada la colisión conceptual en `UsuarioServicio` donde se transfería directamente `$creadoPorUsuarioId` / `$ejecutadoPorUsuarioId` (entero de `usuarios.id`) al noveno argumento (`$actor`) de `AuditoriaServicio::registrar()`, el cual esperaba `ActorAuditoria|int|string|null` interpretando enteros como `actores.id`.
  - Esta divergencia generaba excepciones `ActorNoEncontradoExcepcion` (HTTP 500) cuando mutaciones eran ejecutadas por administradores distintos al usuario inicial (`usuario_id != actor_id`), o provocaba la falsa atribución de autoría al sistema `CAMARGO_PMS` (`actor_id = 1`) cuando ejecutaba el usuario 1 (`orlando`) en vez de su actor humano correspondiente `USR_1` (`actores.id = 2`).
  - Implementado el método auxiliar `resolverActorEjecutor(?int $usuarioId): ?ActorAuditoria` en `UsuarioServicio`, asegurando la resolución mediante `AuditoriaServicio::obtenerOAsegurarActorUsuario($usuarioId, $this->pdo)`.
  - Corregidas las 6 invocaciones a `registrar()` en `UsuarioServicio`: `crearUsuario()`, `cambiarEstado()`, `cambiarContrasenaPropia()`, `restablecerContrasena()`, `cerrarSesion()` y `cerrarTodasSesiones()`.
  - Actualizado `RolServicio::asignarRolAUsuario()` y `RolServicio::revocarRolDeUsuario()` para resolver el actor ejecutor explícito (`$asignadoPor` / `$revocadoPor`) participando en la conexión transaccional.
  - Actualizado `UsuarioControlador::revocarRol()` para extraer el usuario activo en sesión y transferirlo como `$revocadoPor` a `RolServicio::revocarRolDeUsuario()`.
- **Verificación, Gates y Pruebas Automatizadas:**
  - Creada y aprobada la matriz específica de resolución de identidad de actor `ACTOR-01` a `ACTOR-06` (6 PASS / 0 FAIL), comprobando desacople de IDs, autoría correcta de Orlando (`USR_1`), preservación de `CAMARGO_PMS` para procesos de sistema, auto-aseguramiento `USR_{id}` sin duplicados y atomicidad transaccional con rollback.
  - Aprobada al 100% la suite HTTP E2E Real contra servidor Apache (`test_e2e_usuarios.php`) con 10/10 PASS (E2E-USR-01 a E2E-USR-10), verificando que las mutaciones HTTP de creación, asignación de rol, cambio de estado y restablecimiento de contraseña operan con éxito (HTTP 201/200) y registran autoría íntegra con cero fugas.
  - Matriz de usuarios `USR-01` a `USR-40` aprobada al 100% (40/40 PASS).
  - Regresiones de todo el árbol histórico aprobadas al 100% (Auditoría 40/40, Sanitizador 62/62, Auditoría E2E 8/8, Menú 57/57, Identidad 27/27, Personal 26/26, Auth 60/60).
  - Esquema de base de datos intacto (migraciones 001-009, 0 migraciones nuevas, `SQL/` intacto).
  - Catálogo `admin-dashboard/` intacto.

### Fase USUARIOS-1 — Administración Integral de Cuentas Humanas

- **Administración Integral de Cuentas Humanas (`/usuarios`):**
  - Implementada la interfaz administrativa completa bajo `/usuarios` con diseño nativo Alina y Bootstrap 5, paginación server-side, búsqueda multicriterio (nombre de usuario, nombre y apellidos de la persona vinculada) y filtros dinámicos por estado y rol RBAC.
  - Cuatro modales Bootstrap 5 orquestados de forma asíncrona:
    - *Crear Usuario:* Búsqueda y vinculación de Personas humanas elegibles (no vinculadas a otros usuarios), definición de nombre de usuario con normalización en tiempo real, asignación de rol inicial RBAC y contraseña segura con validación en cliente.
    - *Detalle de Usuario:* Inspección completa de perfil (datos personales, rol, estado, fechas) y tabla de sesiones activas con revocación puntual o masiva.
    - *Cambiar Estado:* Transiciones operacionales (`ACTIVO`, `INACTIVO`, `BLOQUEADO`) con confirmación SweetAlert2 y protección defensiva del último superadministrador activo.
    - *Restablecer Contraseña:* Asignación de nuevas credenciales criptográficamente seguras con validación en cliente, confirmación y revocación forzada de sesiones concurrentes.
- **Principio Vinculante `PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL` y Cero Cuentas Sintéticas:**
  - El Usuario representa exclusivamente la credencial de acceso de un individuo humano vinculado unívocamente a una Persona natural (`usuarios.persona_id UNIQUE NOT NULL REFERENCES personas(id)`).
  - Prohibición categórica de crear usuarios sintéticos para integraciones, WordPress o procesos del sistema.
- **Prohibición Estricta de Eliminación Física (Zero Physical Delete):**
  - Las cuentas de usuario son inmutables en su existencia histórica (`DELETE FROM usuarios` = 0). Las bajas y suspensiones se gestionan únicamente mediante transiciones de estado a `INACTIVO` o `BLOQUEADO`.
- **Invariante Concurrente del Último Superadministrador Activo:**
  - Implementada protección pesimista (`FOR UPDATE`) en `UsuarioServicio::cambiarEstado()` para asegurar que el último superadministrador humano en estado activo con persona activa no pueda ser bloqueado o desactivado (`UltimoSuperadministradorExcepcion`), impidiendo condiciones de carrera concurrentes.
- **Políticas Criptográficas y de Sesiones Consolidadas:**
  - Preservación estricta de `PASSWORD_DEFAULT`, política de 12 a 1024 caracteres, preservación total de espacios y caracteres Unicode sin normalizaciones destructivas ni truncamiento. Cero usos de algoritmos obsoletos o no aprobados.
  - La alteración o restablecimiento de contraseña revoca de forma concurrente todas las demás sesiones activas en `sesiones_usuario` marcando `revocada_en = NOW()`.
- **Validación Frontend Modular con PristineJS v1.1.0 y SweetAlert2:**
  - Integrada la biblioteca local PristineJS v1.1.0 en `public/assets/js/gestion-usuarios.js` para validación interactiva inmediata en modales (longitud, caracteres permitidos, confirmación de contraseña coincidente).
  - Limpieza defensiva del ciclo de vida de modales en `hidden.bs.modal` con `validador.reset()`, garantizando ausencia de fugas de memoria o errores residuales al reabrir modales.
  - Comunicación asíncrona mediante Fetch API con token CSRF transmitido por cabecera `X-CSRF-TOKEN` y soporte de respuestas 403 JSON en `AutorizacionIntermediario`.
- **Auditoría Transversal Nativa con Cero Fuga de Secretos:**
  - Integración nativa de `AuditoriaServicio` en todos los flujos de `UsuarioServicio`: eventos `CREAR`, `CAMBIAR_ESTADO` y `CAMBIAR_CLAVE` con resolución de actor (`ACTOR ≠ USUARIO`), contexto HTTP y sanitización total de credenciales y contraseñas vía `SanitizadorAuditoria`.
- **Base de Datos y Migraciones:**
  - Esquema completamente cubierto por las migraciones oficiales `001` a `009`. Cero migraciones nuevas creadas (001-009 aplicadas, 0 pendientes).
- **Puertas de Calidad y Pruebas:**
  - Matriz Formal de Usuarios `USR-01` a `USR-40` aprobada al 100% (40 PASS / 0 FAIL).
  - Suite HTTP E2E Real contra servidor Apache bajo HTTPS `E2E-USR-01` a `E2E-USR-10` aprobada al 100% (10 PASS / 0 FAIL).
  - Regresiones históricas completas aprobadas al 100%: IDENTIDAD (27/27), PERSONAL (26/26), AUTH-1/1A (60/60), MENÚ-1/1A (40/40), SANITIZADOR AUDITORIA-1A (62/62), AUDITORIA-1 (40/40).
  - Total consolidado: 268 pruebas unitarias/integración estrictamente independientes (272 brutas) + 24 pruebas E2E HTTP reales (100% PASS).

### Micro-fase AUDITORÍA-1A — Sanitización Multibyte y Corrección de Trazabilidad Documental

- **Completitud y Normalización en `SanitizadorAuditoria`:**
  - Incorporadas explícitamente las claves técnicas y sensibles `'contraseña'`, `'session'` y `'client_secret'` a la lista canónica `CLAVES_SENSIBLES`.
  - Implementada normalización insensible a mayúsculas y minúsculas con soporte estricto de codificación multibyte (`mb_strtolower(..., 'UTF-8')`) en `esClaveSensible()` y en `sanitizarTexto()`, neutralizando variantes como `CONTRASEÑA`, `Contraseña`, `SESSION` y `Client_Secret`.
  - Verificada la eliminación recursiva total (14/14 variantes sensibles evaluadas: `password`, `contrasena`, `contraseña`, `password_hash`, `csrf`, `csrf_token`, `authorization`, `cookie`, `session`, `session_id`, `token`, `api_key`, `secret`, `client_secret` en mayúsculas, minúsculas, arreglos anidados y JSON embebido).
- **Corrección Documental de Integridad Referencial:**
  - Rectificada la errata descriptiva en la documentación de `fk_actores_usuario` hacia `usuarios(id)`: el DDL canónico real de la migración `009` y `camargo_pms.sql` es `ON DELETE SET NULL ON UPDATE CASCADE` (no `CASCADE`).
  - Formalizada la política vinculante de ciclo de vida de usuarios: las cuentas humanas no se eliminan físicamente en operación ordinaria (`DELETE FROM usuarios` está prohibido a nivel de servicio y repositorio; solo existen estados `ACTIVO`, `INACTIVO` y `BLOQUEADO`). La configuración de FKs (`SET NULL` en usuario y `RESTRICT` en actor) garantiza de forma absoluta la supervivencia inmutable de los registros de auditoría.
- **Reconciliación y Trazabilidad del Conteo de Pruebas:**
  - Registrado el conteo canónico exacto: 222 ejecuciones brutas en el árbol de suites (222 PASS / 0 FAIL), que representan 218 pruebas estrictamente independientes (debido a 4 verificaciones de comprobación rápida en `test_regresion_menu1a.php` que solapan cobertura ya existente en `probar_menu_completo.php` y contratos previos).

### Fase AUDITORÍA-1 — Núcleo Transversal de Auditoría y Trazabilidad

- **Principio Arquitectónico Vinculante `ACTOR ≠ USUARIO`:**
  - Desacople ontológico entre el sujeto de una acción y la cuenta humana de acceso: un actor puede ser `USUARIO` (humano), `SISTEMA` (procesos de background / cron), `INTEGRACION` (clientes técnicos de API como WordPress o app móvil) o `PROVEEDOR_PAGO` (webhooks seguros).
  - Tabla `actores` con tipo, código único, nombre descriptivo, referencia opcional a `usuarios(id)` (`ON DELETE SET NULL ON UPDATE CASCADE`) y metadatos en JSON.
  - Semilla estructural protegida `CAMARGO_PMS` (`id = 1`, `tipo = 'SISTEMA'`).
  - Sincronización automática 1:1 de actores humanos para usuarios registrados en el sistema (`USR_{id}`).
- **Inmutabilidad Estricta de la Bitácora (`auditoria`):**
  - Tabla `auditoria` diseñada para registro append-only de solo inserción. Clave foránea `fk_auditoria_actor` con `ON DELETE RESTRICT` (impide borrar actores con histórico) y `fk_auditoria_usuario` con `ON DELETE SET NULL` (garantiza que la baja de un usuario jamás degrade la autoría histórica).
  - Repositorio `AuditoriaRepositorio` implementado sin métodos `actualizar()` o `eliminar()`. Prohibición de mutaciones o borrado en caliente.
- **Sanitizador Recursivo de Secretos (`SanitizadorAuditoria`):**
  - Algoritmo recursivo que inspecciona campos `valores_anteriores`, `valores_nuevos` y `metadatos`, eliminando o redactando (`[REDACTADO]`) contraseñas, hashes criptográficos, tokens CSRF, cabeceras de autorización, tokens de sesión y claves de API en cualquier nivel de anidamiento.
  - Verificación formal de CERO fugas de credenciales en base de datos real y en respuestas HTTP.
- **Trazabilidad Contextual y Correlación:**
  - Captura estandarizada de contexto HTTP (`ip`, `user_agent`, `metodo_http`, `ruta`) y generación/propagación de identificador único de trazabilidad `correlacion_id` (UUIDv4 / CSPRNG 32 hex chars).
  - Modelo polimórfico de excepciones de dominio: `AuditoriaExcepcion`, `ActorNoEncontradoExcepcion` y `ActorDuplicadoExcepcion`.
- **Integración Transversal en Servicios de Dominio:**
  - `AutenticacionServicio`: Audita eventos de `LOGIN` y `LOGOUT` vinculados al actor correspondiente.
  - `RolServicio`: Audita atómicamente la asignación (`ASIGNAR`) y revocación (`REVOCAR`) de roles a usuarios bajo transacción.
  - `MenuServicio`: Audita operaciones de `CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`, `REORDENAR` y `ELIMINAR` de opciones de menú.
  - `UsuarioServicio`: Audita `CREAR`, `CAMBIAR_ESTADO` y `CAMBIAR_CLAVE` garantizando trazabilidad sin filtrar contraseñas o hashes.
- **Base de Datos y Migración 009:**
  - Creada la migración `SQL/migraciones/009_auditoria_actores.sql` (lote 9) creando las tablas `actores` y `auditoria`, con restricciones CHECK, claves foráneas e índices optimizados por actor, usuario, acción, módulo, entidad, correlación y fecha.
  - Sincronizado `SQL/camargo_pms.sql` alcanzando 20 tablas operativas y paridad estructural del 100%.
- **Puertas de Calidad y Pruebas:**
  - Matriz Formal AUD-01 a AUD-40 aprobada al 100% (40 PASS / 0 FAIL).
  - Suite HTTP E2E Real contra Apache bajo HTTPS aprobada al 100% (8 PASS / 0 FAIL).
  - Auditoría de Paridad SQL 100% entre migraciones 001..009 y `SQL/camargo_pms.sql`.
  - Inspección de base de datos productiva: 0 fugas de credenciales detectadas.
  - Regresiones históricas completas aprobadas (174/174 PASS).

### Micro-lote MENÚ-1A — Integración de PristineJS y Verificación de Contrato

- **Integración de PristineJS (Frontend UX):**
  - Incorporada la librería `pristine.min.js` (v1.1.0, distribución oficial de código abierto) en `public/assets/vendor/pristine/` bajo carga modular exclusiva para `/configuracion/menu`.
  - Configurada validación interactiva sobre el formulario y modales de `Gestión de Menú`: validación de clave técnica con regex, nombre visible, selección obligatoria de categoría padre en opciones secundarias y restricción estricta de rutas locales sin esquemas externos ni scripts.
  - Implementado ciclo de vida limpio para modales Bootstrap (`hidden.bs.modal`) con `validador.reset()`, garantizando ausencia de duplicación de mensajes de error o listeners al cerrar y reabrir.
  - Sincronización dinámica de visibilidad y reglas aplicables al alternar entre nivel principal y nivel secundario.
  - Bloqueo preventivo de envíos asíncronos (`Fetch`) cuando la validación en cliente falla.
- **Principio "VALIDACIÓN FRONTEND ≠ VALIDACIÓN DE SEGURIDAD":**
  - Preservada la autoridad canónica e independiente del backend en `MenuControlador` y `MenuServicio`: cualquier petición directa que evada el cliente continúa siendo rechazada con `HTTP 422 Unprocessable Entity`.
- **Verificación del Contrato de Contraseñas AUTH-1A:**
  - Inspección exhaustiva de código productivo: confirmado que `AutenticacionServicio` y `UsuarioServicio` utilizan estrictamente `PASSWORD_DEFAULT` conforme a AUTH-1A. (Se rectifica el error descriptivo de la auditoría anterior que mencionó erróneamente un uso general de ARGON2ID en el sistema).
- **Puertas de Calidad:**
  - Matriz de pruebas automatizada `PRISTINE-01` a `PRISTINE-12` aprobada al 100% (12 PASS / 0 FAIL).
  - Regresión acotada PASS (`MENU-28`, `MENU-39`, `MENU-40`, `AUTH-PASS-01`).

### Fase MENÚ-1 — Menú Dinámico, Navegación Autorizada y Gestión de Menú

- **Navegación Dinámica y Contrato Visual Alina:**
  - Sustituida la navegación estática de prueba por una estructura jerárquica de dos niveles persistida en la tabla `opciones_menu`, respetando estrictamente el contrato Alina (`navbar-menu-list` con `data-target="clave"` <-> `main-side-menu` con `id="clave"`).
  - Categorías principales (`padre_id IS NULL`, Nivel 1) representadas como iconos horizontales en la cabecera; opciones secundarias (`padre_id IS NOT NULL`, Nivel 2) renderizadas en el menú lateral desplegable.
  - Sincronización automática de elementos activos (`.active`) para nivel 1 y nivel 2 en base a la ruta actual (`$rutaActual`).
- **Principio "MENÚ ≠ AUTORIZACIÓN" y Filtrado Aditivo:**
  - Separación total entre visibilidad en cliente y autorización real en backend: el menú guía la experiencia visual del usuario, mientras que `AutorizacionIntermediario` protege de forma independiente cada endpoint HTTP.
  - Filtro aditivo RBAC: las opciones con permiso asociado (`permiso_id`) solo se muestran si el usuario cuenta con la capacidad activa (`AutorizacionServicio::puede()`) o posee el rol `SUPERADMINISTRADOR`.
  - Depuración automática de categorías principales vacías: una categoría solo se renderiza si tiene al menos una opción secundaria activa y visible.
- **Base de Datos y Migración 008:**
  - Creada la migración `SQL/migraciones/008_menu_dinamico.sql` (lote 8) definiendo la tabla `opciones_menu` con claves foráneas autorreferenciales (`padre_id` RESTRICT) y hacia permisos (`permiso_id` SET NULL).
  - Semillas base de permisos: `menu.ver` ("Ver menú de navegación") y `menu.gestionar` ("Gestionar opciones de menú").
  - Semillas base de opciones: Nivel 1 (`inicio`, `configuracion`) y Nivel 2 (`inicio_panel`, `config_menu`, `config_usuarios`).
  - Sincronizado `SQL/camargo_pms.sql` con las 17 tablas operativas y paridad estructural 100%.
- **Módulo de Administración de Menú (`/configuracion/menu`):**
  - Desarrollada vista reactiva `app/Vistas/configuracion/menu/index.php` con tabla jerárquica, modales de creación/edición, alternancia rápida de estado (activo/inactivo) y controles de ordenamiento.
  - Controlador `MenuControlador`: endpoints para CRUD completo, alternancia de estado y reordenamiento transaccional atómico por pares `[id, orden]`.
  - Enrutador ampliado: soporte de patrones parametrizados (`{id}`), verbos REST (`put`, `patch`, `delete`) y emulación por `_method` o cabecera `X-HTTP-Method-Override`.
  - Frontend interactivo: `public/assets/js/gestion-menu.js` desarrollado con Vanilla JS nativo y Fetch API (sin dependencias de jQuery), con SweetAlert2 para alertas y confirmaciones.
- **Seguridad y Protección Estructural:**
  - Protección de opciones del sistema (`es_sistema = 1`): `config_menu` no puede ser eliminada físicamente ni desactivada (`OpcionMenuProtegidaExcepcion`), impidiendo que el sistema quede sin acceso a su propia gestión.
  - Sanitización de rutas: las rutas deben ser relativas internas locales comenzando con `/`; rechazo categórico de esquemas maliciosos (`javascript:`, `data:`, `vbscript:`, URLs externas con `http:`, `https:`).
  - Protección CSRF estricta en todas las operaciones de mutación mediante token en payload o cabecera `X-CSRF-TOKEN`.
- **Pruebas y Verificación:**
  - Suite de pruebas de dominio `scratch/probar_menu_completo.php` con 40 gates formales aprobados (40 PASS / 0 FAIL, `MENU-01` a `MENU-40`) en base temporal aislada con timeouts de seguridad (`innodb_lock_wait_timeout = 2`, `lock_wait_timeout = 3`) y cleanup defensivo.
  - Auditoría de paridad SQL estricta `scratch/verificar_paridad_sql.php`: 100% idéntico entre migraciones (`001` a `008`) y `SQL/camargo_pms.sql`.
  - Suite E2E HTTP real contra Apache bajo HTTPS `scratch/e2e_http_menu.php`: 10/10 PASS (`E2E-MENU-01` a `E2E-MENU-10`).
  - Regresiones de suites previas: AUTH-1/AUTH-1A (60 PASS / 0 FAIL), HTTP Apache autenticado (6/6 PASS).

### Fase ROLES-1 — Roles, Permisos y Autorización RBAC de Backend

- **Arquitectura de Autorización RBAC:**
  - Implementada la infraestructura de autorización de backend bajo el principio de separación: `Usuario <---> usuarios_roles <---> Rol <---> roles_permisos <---> Permiso`.
  - Permisos modelados como capacidades atómicas granulares con convención `recurso.accion` en minúsculas (D-047).
  - Permisos puramente aditivos: la autorización efectiva para usuarios ordinarios resulta de la unión de todos los permisos de sus roles asignados activos.
- **Rol Estructural Protegido SUPERADMINISTRADOR:**
  - Creado el rol `SUPERADMINISTRADOR` (`es_sistema = 1`, `es_superadministrador = 1`) con autoridad total e incondicional centralizada en `AutorizacionServicio` (D-048).
  - Cualquier comprobación de permisos para un Superadministrador activo devuelve `true` de inmediato, sin requerir asignación exhaustiva en `roles_permisos` y garantizando inmunidad ante permisos futuros.
  - Protección absoluta del rol contra cambio de código, desactivación o eliminación física (`RolProtegidoExcepcion`).
- **Invariante del Último Superadministrador Activo:**
  - Bloqueo pesimista de filas (`FOR UPDATE`) en transacciones para garantizar la permanencia de al menos un Superadministrador activo en el sistema (D-049).
  - Se prohíbe revocar el rol, bloquear o desactivar la cuenta del usuario, o desactivar la persona asociada si es el último Superadministrador activo (`UltimoSuperadministradorExcepcion`).
  - Lógica libre de hardcoding de IDs estáticos o nombres de usuario específicos.
- **Base de Datos y Migración 007:**
  - Creada la migración `SQL/migraciones/007_roles_permisos_autorizacion.sql` (lote 7) definiendo las tablas `roles`, `permisos`, `roles_permisos` y `usuarios_roles`.
  - Semillas estructurales: rol `SUPERADMINISTRADOR` y catálogo de 10 permisos atómicos iniciales (`usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.bloquear`, `roles.ver`, `roles.crear`, `roles.editar`, `roles.asignar`, `roles.revocar`, `permisos.ver`).
  - Transición determinista: vinculación automática de `SUPERADMINISTRADOR` al usuario inicial existente solo si `COUNT(usuarios) = 1` y no cuenta con el rol.
  - Sincronizado `SQL/camargo_pms.sql` con las 16 tablas del sistema y semillas RBAC (paridad 100%).
- **Intermediario de Autorización y Manejo HTTP 403:**
  - Implementado `AutorizacionIntermediario`: redirige a `/login` a usuarios sin sesión y emite `HTTP 403 Forbidden` controlado con plantilla segura de Alina (`Vistas/errores/error.php`) ante permisos insuficientes (D-050).
  - Registrada ruta protegida de demostración `/usuarios` con permiso `usuarios.ver`.
- **Modelos, Repositorios y Servicios:**
  - Modelos puros: `Rol` y `Permiso` en `app/Modelos/`.
  - Repositorios con PDO: `RolRepositorio`, `PermisoRepositorio` y `AutorizacionRepositorio` en `app/Repositorios/`.
  - Servicios de dominio: `AutorizacionServicio` y `RolServicio` en `app/Servicios/`.
  - Actualizados `UsuarioServicio` y `PersonaServicio` para defender la invariante del último Superadministrador.
  - Actualizado `bin/crear-usuario-inicial.php` para asignar atómicamente el rol `SUPERADMINISTRADOR` al usuario inicial (D-051).
- **Pruebas y Verificación:**
  - Suite de pruebas de dominio `scratch/probar_roles_completo.php` con 55 pruebas aprobadas (55 PASS / 0 FAIL) en base de datos temporal aislada.
  - Regresiones completas aprobadas: IDENTIDAD-1 (27 PASS), PERSONAL-1 (26 PASS), AUTH-1/AUTH-1A (60 PASS). Total pruebas automatizadas: 168 PASS / 0 FAIL.
  - Validación HTTP real end-to-end contra servidor Apache (`https://app.camargo-pms.test`): 6/6 PASS (redirección 302 sin sesión, HTTP 200 para Superadmin en `/usuarios`, y HTTP 403 Forbidden con layout Alina para usuario sin permiso).

### Hotfix Post-AUTH-1 — Corrección de Renderizado Autenticado Post-Login y Seguridad 500

- **Síntoma:** Al autenticarse exitosamente en `/login` y ser redirigido a la ruta protegida `/`, la aplicación arrojaba una pantalla de error HTTP 500 controlada ("Error del Servidor — Camargo PMS").
- **Causa raíz:** En el componente visual `app/Vistas/componentes/cabecera.php`, la función auxiliar `usuario_autenticado()` devuelve una instancia del modelo de dominio puro `\CamargoPMS\Modelos\Usuario`. El componente intentaba acceder a sus propiedades mediante sintaxis de arreglo (`$usuarioActual['nombre_usuario']`), lo que en PHP 8.3 arrojaba un `TypeError` fatal (`Cannot use object of type CamargoPMS\Modelos\Usuario as array`). En solicitudes no autenticadas dicho bloque condicional no se ejecutaba, razón por la cual las pruebas previas sin renderizado completo de vistas bajo sesión activa no detectaron la falla.
- **Corrección:**
  - `app/Vistas/componentes/cabecera.php`: Reemplazado el acceso por arreglo con la llamada al método de dominio `$usuarioActual->obtenerNombreUsuario()`.
  - `public/index.php`: Establecido `ini_set('display_errors', '0')`, añadido registro seguro de excepciones mediante `error_log((string) $error)` en el bloque `catch (\Throwable $error)` global, y eliminado el volcado de diagnósticos técnicos hacia el navegador para garantizar que las páginas de error 500 nunca filtren rutas internas, trazas ni datos de depuración.
- **Pruebas de regresión agregadas:**
  - Incorporada de forma permanente la prueba `T-AUTH-45` (sub-pruebas A, B y C) en la suite automatizada: valida que un usuario con sesión persistente acceda a `GET /`, el intermediario permita el paso sin redirigir a `/login`, responda con código HTTP 200, y renderice el layout Alina completo mostrando el nombre de usuario, el menú de perfil, el formulario POST de logout y el token CSRF sin arrojar excepciones ni página 500.
  - Validación HTTP real end-to-end con 6 gates contra servidor Apache (`https://app.camargo-pms.test`):
    1. `GET /login` sin sesión → HTTP 200.
    2. `GET /` sin sesión → HTTP 302 Redirige a `/login`.
    3. `GET /` con sesión activa → HTTP 200 Dashboard renderizado con éxito.
    4. `POST /logout` con token CSRF válido → HTTP 302 a `/login` y sesión revocada en BD.
    5. `GET /` tras logout con cookie revocada → HTTP 302 Redirige a `/login`.
    6. `GET /ruta-inexistente` → HTTP 404 Not Found.
  - Suite de regresión ejecutada sobre base de datos aislada temporal (`camargo_pms_test`) con 60/60 pruebas aprobadas (PASS), preservando intacto el usuario real y datos del propietario en `camargo_pms`.

### Micro-lote AUTH-1A — Cierre de Contrato Criptográfico y Bootstrap Inicial

- Implementada la migración evolutiva `SQL/migraciones/006_ajustes_auth_credenciales.sql` (lote 6):
  - Modificación formal de la columna `contrasena_hash` en la tabla `usuarios` a `VARCHAR(255) NOT NULL` con comentario explícito de soporte para `PASSWORD_DEFAULT` y futuros algoritmos.
  - Sincronización de comentarios descriptivos en tablas `usuarios`, `sesiones_usuario` e `intentos_autenticacion`.
- Eliminación de la fijación rígida a `PASSWORD_BCRYPT` y `cost=12` en el dominio y gobernanza:
  - Adopción de `PASSWORD_DEFAULT` estándar en `UsuarioServicio` y `AutenticacionServicio`.
  - Rehash automático y dinámico (`password_needs_rehash()`) con `PASSWORD_DEFAULT` tras autenticaciones exitosas.
  - Mitigación dinámica contra timing attacks y enumeración mediante hash dummy precalculado dinámicamente con `PASSWORD_DEFAULT`.
- Formalización definitiva de la política de contraseñas (D-036):
  - Longitud mínima de 12 caracteres y máxima de 1024 caracteres evaluada estrictamente antes del hash.
  - Ausencia absoluta de transformaciones destructivas (`trim`, `lowercase`, truncamiento); soporte pleno de espacios internos/externos y caracteres Unicode.
- Endurecimiento del contrato de bootstrap inicial en `bin/crear-usuario-inicial.php` (D-046):
  - Eliminación categórica de la bandera `--forzar` y de cualquier mecanismo de bypass de línea de comandos.
  - Regla estricta: `usuarios = 0` permite bootstrap inicial; `usuarios >= 1` rechaza incondicionalmente la ejecución. La creación posterior de usuarios queda reservada exclusivamente a las funciones administrativas del sistema bajo autorización.
- Corrección de decisiones en gobernanza:
  - D-036 actualizada para formalizar `PASSWORD_DEFAULT`, `VARCHAR(255)`, 12/1024 chars y rehash sin asunciones rígidas.
  - D-046 actualizada eliminando toda referencia a `--forzar` y estableciendo el carácter de uso estrictamente único del bootstrap.
- Paridad estructural 100% verificada entre las migraciones 001 a 006 y el consolidado `SQL/camargo_pms.sql` mediante recreación aislada en base de datos temporal `camargo_pms_test_parity_auth1a`.
- Suite automatizada ampliada a 57 pruebas (57 PASS / 0 FAIL), reteniendo las 47 de AUTH-1 e incorporando las pruebas específicas A a L para validación de longitudes (11 rechazada, 12 aceptada, 500 aceptada, 1025 rechazada), preservación de espacios y no trim, compatibilidad `PASSWORD_DEFAULT`, soporte `VARCHAR(255)`, rehash dinámico y rechazo incondicional de `--forzar` en bootstrap con cuentas existentes.
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Fase AUTH-1 — Autenticación, Cuentas Humanas y Sesiones

- Implementada la migración evolutiva `SQL/migraciones/005_auth_usuarios_sesiones.sql` (lote 5):
  - Creación de la tabla `usuarios`: vinculación directa 1:1 con `personas` (`persona_id UNIQUE NOT NULL`), clave foránea `fk_usuarios_persona` con restricción de borrado `ON DELETE RESTRICT`. Prohibición absoluta de relación con `colaboradores` (`colaborador_id`).
  - Nombres de usuario canónicos y normalizados: `nombre_usuario` para presentación y `nombre_usuario_normalizado` con restricción `UNIQUE` para colisión insensible a mayúsculas (D-035).
  - Almacenamiento seguro de credenciales: `contrasena_hash CHAR(60) NOT NULL` con algoritmo `PASSWORD_BCRYPT` (factor de costo 12).
  - Creación de la tabla `sesiones_usuario`: gestión de sesiones con tokens opacos (32 bytes CSPRNG / 64 caracteres hex) persistiendo exclusivamente su hash SHA-256 (`token_hash UNIQUE`) (D-038).
  - Creación de la tabla `intentos_autenticacion`: auditoría de intentos de acceso para control de fuerza bruta y rate limiting por IP e identificador (D-045).
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` conteniendo las 13 tablas del sistema y finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`. Paridad 100% verificada entre migración y consolidado.
- Implementados los modelos de dominio puros: `Usuario` y `SesionUsuario` en `app/Modelos/`.
- Implementadas las excepciones de dominio especializadas en `app/Excepciones/`: `CredencialesInvalidasExcepcion`, `UsuarioDuplicadoExcepcion`, `UsuarioBloqueadoExcepcion`, `SesionInvalidaExcepcion`, `CsrfInvalidoExcepcion` y `DemasiadosIntentosExcepcion`.
- Implementados los repositorios con PDO parametrizado: `UsuarioRepositorio`, `SesionUsuarioRepositorio` e `IntentoAutenticacionRepositorio` en `app/Repositorios/`.
- Implementados los servicios de dominio en `app/Servicios/`:
  - `UsuarioServicio`: creación de usuario para persona activa, validación de contraseñas (10-128 chars), normalización de username, cambio de contraseña con revocación concurrente de sesiones previas (D-040).
  - `AutenticacionServicio`: autenticación segura con timing-attack dummy hash (D-037), migración automática de costo bcrypt con `password_needs_rehash()`, respuestas genéricas indistinguibles (`'Credenciales de acceso inválidas.'`), rate limiting de 5 intentos en 15 minutos sin alterar el estado del usuario.
  - `SesionServicio`: creación de sesiones en base de datos con expiración dual (30 min inactividad, 12 h absoluta), validación con verificación de vigencia de usuario/persona, revocación forzada y destrucción de cookies.
  - `CsrfServicio`: generación de tokens por sesión y verificación de tiempo constante mediante `hash_equals()` (D-041).
- Implementado el pipeline web de autenticación:
  - `AutenticacionIntermediario`: intermediario de protección de rutas privadas, redirección a `/login` con sanitización estricta del parámetro `return` contra Open Redirect (D-044).
  - `AutenticacionControlador`: controlador web siguiendo el patrón PRG (Post-Redirect-Get) para `GET /login`, `POST /login` y `POST /logout`.
  - Integración en `public/index.php`: ruta protegida `GET /` mediante `AutenticacionIntermediario`, soporte transparente del método HTTP `HEAD` en `Enrutador`, y métodos de consulta en `Respuesta`.
  - Ayudantes globales `usuario_autenticado()` y `csrf_campo()` en `app/Nucleo/Funciones.php`.
- Interfaz y vistas:
  - Creada la vista de autenticación `app/Vistas/auth/login.php` adaptada al diseño Alina (`sign-in.html` / `sign-in-2.html`) con campos flotantes, mensajes flash de error y token CSRF.
  - Añadido el asset visual oficial `public/assets/images/login/01.jpg`.
  - Actualizado el componente `cabecera.php` para renderizar el perfil del usuario autenticado y el formulario POST de cierre de sesión seguro con CSRF.
- Script de utilidad administrativa CLI:
  - Desarrollado `bin/crear-usuario-inicial.php` para inicialización idempotente del sistema, con restricción estricta a entornos de terminal (`PHP_SAPI === 'cli'`), creación atómica de persona si no existe, y sin exposición de contraseñas en consola ni logs (D-046).
- Suite integral de pruebas automatizadas:
  - Desarrollada y ejecutada la suite de 47 pruebas exhaustivas (47 PASS / 0 FAIL) cubriendo creación de usuarios, validaciones de dominio, ciclo de vida de sesiones, expiración por inactividad y absoluta, revocación concurrente, timing attacks, mitigación de session fixation, open redirect, cookies seguras, CSRF, rate limiting, restricción CLI y regresión de Identidad y Personal.
  - Verificada la limpieza completa de la base de datos (0 registros residuales en tablas operativas).
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Micro-lote PERSONAL-1A — Cierre de Invariantes Documentales y Temporales

- Implementada la migración evolutiva no destructiva `SQL/migraciones/004_ajustes_identidad_personal.sql` (lote 4):
  - Modelado estructural de jurisdicción en `tipos_documento`: incorporación de `pais_fijo_id` (FK a `paises`) y `pais_emisor_obligatorio`. DNI y Carné de Extranjería configurados con `pais_fijo_id = 1` (Perú) y `pais_emisor_obligatorio = 0`; Pasaporte configurado con `pais_fijo_id = NULL` y `pais_emisor_obligatorio = 1` (D-031).
  - Eliminación definitiva del centinela técnico `COALESCE(pais_emisor_id, 0)` y de la columna virtual `pais_emisor_efectivo` en `personas_documentos`.
  - `personas_documentos.pais_emisor_id` pasa a ser estrictamente `INT UNSIGNED NOT NULL`, respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado con `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)` (`uq_documentos_tipo_pais_numero`).
- Actualizados los modelos de dominio, repositorios y servicios de identidad:
  - `TipoDocumento` incorpora `paisFijoId`, `paisEmisorObligatorio` y métodos semánticos (`tienePaisFijo()`, `requierePaisEmisor()`).
  - `DocumentoPersonaRepositorio` y `PersonaServicio` consultan y validan directamente la jurisdicción real sin centinelas: resolución automática al país fijo cuando se omite, rechazo tajante de países no autorizados en documentos fijos y exigencia obligatoria de país emisor en pasaportes.
- Implementado el blindaje exhaustivo de la invariante temporal de no solapamiento de asignaciones de cargo dentro de un episodio laboral (D-032):
  - Métodos incorporados en `ColaboradorServicio`: `validarSolapamientoAsignacionCargo()` y `asignarCargoAEpisodio()`.
  - Rechazo de solapamiento cronológico entre asignaciones del mismo episodio, cubriendo tanto intervalos abiertos como cerrados (ej. Cargo A 01/01 a 30/06 y Cargo B 01/04 a 31/05 rechazado por `SolapamientoLaboralExcepcion`).
  - Verificación estricta de límites temporales respecto al episodio laboral padre (prohibición de iniciar antes de la contratación o iniciar/finalizar después del cese).
  - Protección de concurrencia mediante transacciones y bloqueos pesimistas (`SELECT ... FOR UPDATE`).
  - Integración de la validación en `cambiarCargo()`, preservando la contigüidad matemática estricta $D-1$ / $D$.
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` con el nuevo esquema limpio de 10 tablas, finalizando con `SET FOREIGN_KEY_CHECKS = 1;`.
- Validada la paridad 100% de columnas e índices entre `SQL/camargo_pms.sql` y la base de datos `camargo_pms` mediante recreación aislada en base de datos temporal `camargo_pms_prueba_p1a`.
- Ampliada la suite automatizada a 26 pruebas (26 PASS / 0 FAIL), reteniendo las 14 pruebas de PERSONAL-1 e incorporando 7 pruebas de invariante documental estructural (T-DOC-A a T-DOC-G) y 5 pruebas de invariante temporal de cargos (T-CARGO-A a T-CARGO-E).
- Confirmada la intangibilidad total del catálogo `admin-dashboard/` (0 cambios).

### Fase PERSONAL-1 — Colaboradores, Cargos, Episodios Laborales y Ajuste Evolutivo de Identidad

- Consolidado el Principio de Separación Integral: `PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL` (D-026).
- Implementado el ajuste evolutivo no destructivo de Identidad mediante la migración `SQL/migraciones/003_personal_colaboradores.sql`:
  - Eliminada la restricción que exigía al menos un apellido (`chk_personas_al_menos_un_apellido`), habilitando soporte para monónimos legales y personas extranjeras.
  - Evolucionada la unicidad documental incorporando la jurisdicción de emisión mediante la columna virtual generada `pais_emisor_efectivo = COALESCE(pais_emisor_id, 0)` y la restricción `UNIQUE (tipo_documento_id, pais_emisor_efectivo, numero_documento)`, resolviendo colisiones entre pasaportes de distintos países con igual número y previniendo duplicados dentro del mismo país emisor (D-030).
- Creada la entidad y tabla `cargos` como catálogo administrable de funciones laborales (`ADMINISTRADOR`, `RECEPCIONISTA`, `RESERVAS`, `LIMPIEZA`, `MANTENIMIENTO`), totalmente desacoplado de permisos de seguridad de software.
- Creada la entidad y tabla `colaboradores` con código interno estable secuencial (`COL-XXXX`), estado (`ACTIVO`, `INACTIVO`) y restricción `UNIQUE (persona_id)` para garantizar la cardinalidad 1:1 lógica estricta (D-027).
- Creada la entidad y tabla `episodios_laborales` para registrar la vinculación laboral continua, con columna virtual generada `uq_colaborador_abierto` y restricción `UNIQUE` para forzar a nivel de base de datos a lo sumo un episodio abierto activo por colaborador (D-028).
- Creada la entidad y tabla `episodios_laborales_cargos` para registrar el historial inmutable de funciones por episodio, con columna virtual generada `uq_episodio_cargo_abierto` y restricción `UNIQUE` para forzar a lo sumo un cargo vigente por episodio.
- Implementada la convención temporal de continuidad en transiciones de cargo: cierre de la asignación previa en $D-1$ y apertura de la nueva asignación en $D$, con prohibición de solapamiento de fechas (D-029).
- Implementados los modelos puros de dominio: `Cargo`, `Colaborador`, `EpisodioLaboral` y `AsignacionCargo` en `app/Modelos/`.
- Implementados los repositorios con PDO parametrizado: `CargoRepositorio`, `ColaboradorRepositorio`, `EpisodioLaboralRepositorio` y `AsignacionCargoRepositorio` en `app/Repositorios/`.
- Implementadas las excepciones de dominio: `ColaboradorDuplicadoExcepcion`, `EpisodioLaboralActivoExcepcion` y `SolapamientoLaboralExcepcion` en `app/Excepciones/`.
- Implementado el servicio de dominio `app/Servicios/ColaboradorServicio.php` con métodos atómicos transaccionales: `crearColaborador`, `reingresarColaborador`, `cambiarCargo`, `cesarColaborador`, `obtenerSituacionActual` y bloqueos pesimistas `SELECT ... FOR UPDATE`.
- Sincronizado el archivo consolidado oficial `SQL/camargo_pms.sql` conteniendo las 10 tablas del sistema y finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`.
- Verificada la reconstrucción estructural limpia desde cero en base de datos temporal aislada `camargo_pms_prueba_personal` con 100% de paridad con `camargo_pms`.
- Ejecutada la suite completa de 14 pruebas automatizadas (14 PASS / 0 FAIL), incluyendo monónimos, pasaportes internacionales con igual número, unicidad 1:1 Persona-Colaborador, transiciones de cargo, cese sin borrado físico, reingreso sin duplicar colaborador, rechazo de solapamiento temporal, atomicidad transaccional y regresión completa del núcleo de identidad.
- Verificada la intangibilidad total del catálogo `admin-dashboard/` (0 archivos modificados).

### Fase IDENTIDAD-1 — Núcleo de Identidad Humana (Personas Naturales)

- Diseñado e implementado el modelo relacional del maestro de Personas Naturales bajo el principio `PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL` y la separación de entidades normalizadas `Persona`, `DocumentoPersona` y `ContactoPersona`.
- Creados los catálogos relacionales `paises` (ISO-3166-1 alpha-2 y alpha-3 únicos) con semilla estructural de Perú ('PE', 'PER', 'Perú', 'Peruana'), y `tipos_documento` con semillas estructurales para DNI (8 dígitos exactos), Pasaporte y Carné de Extranjería (CE).
- Excluido formalmente el RUC como tipo de documento personal de personas naturales, reservándolo para personas jurídicas / fiscalidad (D-022).
- Creada la migración oficial `SQL/migraciones/002_identidad_personas.sql` y sincronizado el esquema consolidado `SQL/camargo_pms.sql` finalizando explícitamente con `SET FOREIGN_KEY_CHECKS = 1;`.
- Implementadas restricciones de integridad a nivel de base de datos: `UNIQUE (tipo_documento_id, numero_documento)` para unicidad documental estricta, y columnas virtuales generadas `uq_persona_principal` y `uq_contacto_tipo_principal` para garantizar que no existan múltiples documentos principales o múltiples contactos principales del mismo tipo activos para una persona.
- Implementado el indicador booleano `es_whatsapp` en contactos telefónicos para evitar la duplicación innecesaria de números físicos.
- Implementada la política de soft delete mediante el campo `estado` (`ACTIVO`, `INACTIVO`) en personas, documentos y contactos, preservando registros históricos y de auditoría sin borrados físicos destructivos.
- Implementadas las entidades de dominio puras en `app/Modelos/` (`Persona`, `DocumentoPersona`, `ContactoPersona`, `Pais`, `TipoDocumento`) totalmente desacopladas de PDO.
- Implementados los repositorios en `app/Repositorios/` (`PersonaRepositorio`, `DocumentoPersonaRepositorio`, `ContactoPersonaRepositorio`, `PaisRepositorio`, `TipoDocumentoRepositorio`) con consultas SQL estrictamente preparadas y parametrizadas.
- Implementadas las excepciones de dominio en `app/Excepciones/` (`ValidacionExcepcion`, `DocumentoDuplicadoExcepcion`, `EntidadNoEncontradaExcepcion`).
- Implementado el servicio de dominio `app/Servicios/PersonaServicio.php` con validación sintáctica, normalización, control de fechas futuras, gestión de documentos/contactos principales y límites transaccionales atómicos (rollback probado ante fallos).
- Verificado el banco completo de pruebas técnicas de dominio y persistencia (27 PASS / 0 FAIL), incluyendo idempotencia de migraciones, validación sintáctica DNI, detección de duplicados, transaccionalidad, extranjeros sin segundo apellido, soft delete y reconstrucción aislada desde cero en base de datos temporal `camargo_pms_prueba_identidad`.
- Verificada la intangibilidad total del catálogo `admin-dashboard/`.

### Fase INFRA-1 — Infraestructura de configuración, base de datos, PDO y migraciones

- Ejecutada auditoría estricta de solo lectura sobre la base de datos local `camargo_pms`, confirmando el motor MySQL Community Server 8.4.3 LTS (GPL), almacenamiento InnoDB, charset `utf8mb4`, collation `utf8mb4_0900_ai_ci`, `sql_mode` estricto y esquema inicial completamente vacío (Clasificación A).
- Resuelto formalmente P-001 registrando la decisión D-018 que oficializa MySQL 8.4.3 LTS como motor de base de datos del proyecto.
- Resuelto formalmente P-002 registrando la decisión D-019 que adopta Composer 2.x para dependencias mínimas (`vlucas/phpdotenv`) y autoloading PSR-4 (`CamargoPMS\` -> `app/`), preservando el enrutador nativo `app/Nucleo/Enrutador.php`.
- Formalizada la decisión D-020 estableciendo el principio de persistencia sincronizada: `SQL/camargo_pms.sql` como esquema consolidado oficial y versionado, y `SQL/migraciones/` para la evolución incremental determinista.
- Creada la migración inicial `SQL/migraciones/001_infraestructura.sql` para la tabla técnica de control `migraciones` (`id`, `migracion`, `lote`, `ejecutado_en`).
- Implementada la plantilla versionada de entorno `.env.example` y la configuración local privada `.env` (debidamente ignorada por Git).
- Implementada la capa centralizada de configuración `app/Nucleo/Configuracion.php` con soporte para variables de entorno seguras.
- Implementada la fábrica centralizada de conexiones `app/Nucleo/BaseDatos.php` con opciones estrictas de PDO, emulación de prepares desactivada y manejo seguro de excepciones sin filtrar credenciales.
- Implementado el runner CLI de migraciones `app/Nucleo/Migrador.php` y el comando de consola `migrar.php` protegido contra accesos web.
- Validadas exitosamente la conexión PDO, la ejecución de la migración inicial y la completa idempotencia en la segunda ejecución (0 migraciones pendientes).
- Validada la reconstrucción completa del esquema desde cero en una base de datos temporal aislada (`camargo_pms_prueba_infra`), eliminada inmediatamente tras la verificación.
- Confirmada la ausencia total de tablas funcionales de negocio y la inmutabilidad íntegra de `admin-dashboard/`.

### Fase UI-1 — Validación técnica/visual y consolidación documental

- Ejecutada la validación técnica y visual real bajo Apache/Laragon (PID 25016, puertos 80 y 443) tanto en VirtualHost (`https://app.camargo-pms.test/`) como en subcarpeta (`http://localhost/app.camargo-pms/`).
- Verificada la entrega con HTTP 200 de los 18 assets vinculados (CSS, JS, fuentes Tabler e imágenes), confirmando 0 errores 404 en assets.
- Verificado el renderizado del DOM en navegador Chrome/Edge headless, confirmando remoción automática del cargador (`.loader-wrapper`), aplicación de clase de tema en `<body>` y 0 excepciones de JavaScript.
- Validadas las respuestas HTTP reales ante rutas no encontradas (HTTP 404 real) e invocación defensiva de errores 500 con plantilla centrada `.error-container`.
- Consolidado en gobernanza el Principio de Separación de Identidad (`PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`).
- Formalizado el diseño documental del maestro central de Personas (documentos DNI/Pasaporte, nacionalidad inicial Perú por defecto y datos de contacto).
- Documentada la Gestión de Personal, catálogo administrable de Cargos laborales e Historial Laboral inmutable por episodios cronológicos.
- Formalizada la distinción entre Cargo laboral y Rol de seguridad, catálogo dinámico de Roles y permisos atómicos `recurso.accion`.
- Incorporada la regla de seguridad vinculante `OCULTAR EL MENÚ NO ES AUTORIZACIÓN` con intermediarios de backend y respuesta HTTP 403 real.
- Formalizado el diseño del módulo de Gestión de Menú Dinámico con soporte drag & drop, vinculación opción ↔ permiso y contrato Alina `data-target` ↔ `id`.
- Establecida la frontera conceptual entre Personal y Caja (Personal como contraparte de gastos/sueldos, sin convertir Personal en subsistema contable).
- Aprobada y registrada la decisión D-017 de integración externa con APIsPERU (DNI de 8 dígitos y RUC de 11 dígitos) desacoplada mediante `ServicioConsultaIdentidad` y adaptadores.
- Reafirmada la auditoría transversal y la diferenciación estricta de actores (`USER`, `SYSTEM`, `INTEGRATION`, `PAYMENT_PROVIDER`).
- Verificada la inmutabilidad total de `admin-dashboard/`.

### Fase UI-0 — Esqueleto MVC y plantilla Alina reutilizable

- Creado el Front Controller en `public/index.php` con manejo defensivo de errores y resolución dinámica de URL base.
- Implementado el motor de renderizado seguro `Vista` y la clase `Respuesta` con cabeceras de seguridad.
- Implementado el `Autocargador` PSR-4 propio para el espacio de nombres `CamargoPMS\` y `Enrutador` mínimo reversible.
- Formalizadas las referencias visuales oficiales de Alina: `blank.html` (layout general), `sign_in.html` (login), `index.html` (dashboard) y `error_*.html` (errores HTTP).
- Adaptada la plantilla principal `principal.php` preservando el árbol DOM de `blank.html`.
- Extraídos 9 componentes reutilizables: `head`, `cargador`, `navegacion`, `menu-principal`, `menu-secundario`, `cabecera`, `migas-pan`, `pie` y `scripts`.
- Desarrollado el script propio de layout `camargo-layout.js` en JavaScript moderno nativo (sin jQuery ni scripts demo) asegurando el contrato `data-target` ↔ `id`, responsive, tema claro/oscuro y scroll.
- Copiados selectivamente los assets esenciales de Bootstrap 5, Tabler Icons, Simplebar, CSS de Alina e ilustraciones de error; eliminadas rutas relativas frágiles mediante ayudantes de URL absoluta.
- Creada la vista neutra de comprobación en `app/Vistas/panel/inicio.php`.
- Implementada la arquitectura de errores con plantilla aislada `plantillas/error.php` (.error-container) y vista reutilizable `errores/error.php` para 400, 403, 404, 500 y 503.
- Creado `camargo.css` para estilos propios sin alterar los originales de Alina.
- Verificada la inmutabilidad íntegra de `admin-dashboard/`.

### Fase G-1 — Git y baseline documental

- Definida `main` como rama oficial y el repositorio GitHub de Camargo PMS como `origin`.
- Creado `.gitignore` para secretos, dependencias, runtime, IDE, artefactos, respaldos y bases locales.
- Aprobado versionar intactos Alina y su documentación como referencia reproducible.
- Mantenido el Figma local e intacto fuera de Git, sin incorporar Git LFS.
- Incorporado el control de SHA, working tree y ahead/behind a los gates de micro-baseline.
- Preparado el primer baseline documental sin código funcional, Composer ni base de datos.

### Fase G-0 — Gobernanza

- Creada la entrada obligatoria `AGENTS.md`.
- Definidas arquitectura por capas y nomenclatura propia en español.
- Documentados dominio, seguridad, base de datos, API, auditoría, pruebas, Git y roadmap.
- Registrado el contrato acoplado de menús Alina y la estrategia de assets mínimos.
- Documentada la preservación íntegra de los originales Alina.
- Preparados skills locales especializados.

No se ha iniciado Git, PHP, base de datos ni implementación funcional.
