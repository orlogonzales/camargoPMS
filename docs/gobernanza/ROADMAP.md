# Roadmap

El roadmap fija orden y gates, no fechas. Cada fase puede dividirse en microfases revisables.

## G-0 — Gobernanza integral

Crear `AGENTS.md`, documentos y skills; consolidar decisiones y evidencia de Alina. Sin PHP, BD o Git.

Estado: completada.

## G-1 — Git y baseline documental

Revisar exclusiones, definir tratamiento de assets grandes, inicializar Git y crear baseline autorizada.

Estado: completada.

## UI-0 — Conversión controlada de Alina

Front Controller mínimo, assets seleccionados, layout y componentes PHP, vista neutra, menús de prueba y `camargo-layout.js`. Sin módulos funcionales ni BD.

Estado: completada.

## UI-1 — Validación técnica y visual + Consolidación documental

Probar bajo Apache real (`https://app.camargo-pms.test/` y subcarpeta), responsive, consola de navegador, navegación, contratos Alina, rutas y códigos HTTP reales (404/500). Consolidar en gobernanza el diseño de Identidad (`PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`), Personal, Cargos, Historial, Usuarios, Roles, Permisos, Menú Dinámico e integración con APIsPERU (DNI/RUC).

Estado: completada.

## INFRA-1 — Infraestructura de configuración, base de datos, PDO y migraciones

Resueltas decisiones P-001 (MySQL 8.4.3 LTS oficial) y P-002 (Composer PSR-4 con router nativo preservado), variables de entorno (.env y .env.example), conexión centralizada PDO (`BaseDatos.php`), esquema consolidado oficial `SQL/camargo_pms.sql` y runner CLI de migraciones versionadas (`migrar.php` con tabla técnica `migraciones`).

Estado: completada.

## IDENTIDAD-1 — Núcleo de Identidad y Personas Naturales

Construcción del maestro humano central de Personas Naturales, separación de entidades (`Persona`, `DocumentoPersona`, `ContactoPersona`), catálogos normalizados de `paises` (ISO-3166-1) y `tipos_documento` (DNI 8 dígitos, Pasaporte, CE; exclusión de RUC), reglas de unicidad documental y de contactos principales en base de datos vía columnas virtuales, soft delete, DDL + migración `002_identidad_personas.sql` sincronizada con `SQL/camargo_pms.sql`, repositorios, excepciones y servicio de dominio `PersonaServicio` con transaccionalidad atómica y pruebas completas.

Estado: completada.

## PERSONAL-1 — Colaboradores, Cargos, Episodios Laborales y Ajuste Evolutivo de Identidad

Ajuste evolutivo a Identidad (monónimos internacionales y unicidad documental parametrizada por `pais_emisor_efectivo` en migración `003`), entidad `Colaborador` con código interno estable (`COL-XXXX`) y cardinalidad 1:1 lógica con `personas`, catálogo administrable de `cargos` (semillas: ADMINISTRADOR, RECEPCIONISTA, RESERVAS, LIMPIEZA, MANTENIMIENTO), historial laboral inmutable por `episodios_laborales` y `episodios_laborales_cargos` con columnas virtuales generadas para unicidad de episodios y cargos abiertos, transiciones de cargo con continuidad temporal estricta ($D-1$ / $D$), servicio de dominio `ColaboradorServicio` con bloqueos pesimistas (`FOR UPDATE`), repositorios y suite de pruebas completas.

Estado: completada.

## AUTH-1 / AUTH-1A — Autenticación, Cuentas Humanas y Sesiones

Gestión de Usuarios humanos vinculados 1:1 a Personas (`usuarios.persona_id UNIQUE NOT NULL`), desacoplado de Colaboradores. Hashing seguro y extensible de contraseñas (`PASSWORD_DEFAULT`, `contrasena_hash VARCHAR(255)`, política de 12 a 1024 caracteres, rehash dinámico con `password_needs_rehash()`), mitigación dinámica de timing attacks / enumeración, sesiones persistidas en base de datos con tokens opacos (SHA-256), expiración dual (30m inactividad / 12h absoluta), revocación concurrente ante cambio de clave, rate limiting (5 intentos / 15m), protección CSRF estricta, mitigación session fixation y open redirect, cookies seguras, middleware de autenticación, vista de login Alina y script CLI de bootstrap estrictamente de uso único sin bypass.

Estado: completada (AUTH-1A aplicada y verificada).

## ROLES-1 — Roles, Permisos y Autorización RBAC de Backend

Infraestructura de control de acceso basado en roles (RBAC) puro en backend: modelos `Rol` y `Permiso`, entidades asociativas `usuarios_roles` y `roles_permisos`, convención atómica de permisos `recurso.accion` aditiva, rol estructural protegido `SUPERADMINISTRADOR` con autoridad total e incondicional reconocida centralmente en `AutorizacionServicio`, protección de la cuenta raíz e invariante concurrente del último Superadministrador activo mediante bloqueo pesimista (`FOR UPDATE`) en base de datos sin hardcoding de IDs o nombres de usuario, intermediario `AutorizacionIntermediario` con emisión de HTTP 403 Forbidden y layout seguro de Alina, asignación atómica en bootstrap CLI y migración determinista 007.

Estado: completada.

## MENÚ-1 / MENÚ-1A — Menú Dinámico, Navegación Autorizada y Validación PristineJS

Navegación dinámica autorizada de 2 niveles persistida en tabla `opciones_menu`, preservando el contrato visual Alina (`navbar-menu-list` con `data-target="clave"` <-> `main-side-menu` con `id="clave"`). Filtro de visibilidad aditiva gobernado por RBAC (`menu.ver`, `menu.gestionar`), eliminación de categorías principales vacías, módulo interactivo bajo `/configuracion/menu` (CRUD, alternancia de estado y reordenamiento transaccional atómico sin jQuery), protección de opciones del sistema (`es_sistema = 1`), sanitización estricta de rutas internas, integración local de PristineJS (v1.1.0) y suites formales de prueba (MENU-01..40, PRISTINE-01..12 y E2E-MENU-01..10).

Estado: completada (baseline oficial `cdd3427`).

## AUDITORÍA-1 / AUDITORÍA-1A — Núcleo Transversal de Auditoría y Trazabilidad

Establecimiento del núcleo inmutable de auditoría y trazabilidad del sistema bajo el principio vinculante `ACTOR ≠ USUARIO`. Modelo polimórfico de actores (`actores`) soportando `USUARIO`, `SISTEMA`, `INTEGRACION` y `PROVEEDOR_PAGO`, con semilla protegida `CAMARGO_PMS`. Bitácora persistida (`auditoria`) append-only con integridad referencial (`ON DELETE RESTRICT`), sanitización recursiva de secretos (`SanitizadorAuditoria`), correlación contextual HTTP (`correlacion_id`), persistencia atómica en operaciones críticas y defensiva en eventos auxiliares, e integración transversal en Autenticación (Login/Logout), Roles (Asignar/Revocar), Menú (CRUD/Reordenar) y Usuarios (Crear/Estado/Clave). DDL migración `009_auditoria_actores.sql` y paridad 100% en `SQL/camargo_pms.sql`.

Estado: completada (baseline oficial `da672ba`).

## USUARIOS-1 — Administración Integral de Cuentas Humanas

Administración completa de cuentas humanas de acceso al sistema desde la interfaz Alina bajo `/usuarios`: listado paginado, búsqueda multicriterio, filtros dinámicos por rol y estado, vinculación exclusiva 1:1 a Personas humanas del maestro central (`PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL`), asignación de rol inicial RBAC, transiciones de estado operacionales (`ACTIVO`, `INACTIVO`, `BLOQUEADO`), prohibición estricta de eliminación física (`DELETE` = 0), preservación del invariante del último Superadministrador activo mediante bloqueo pesimista (`FOR UPDATE`), restablecimiento seguro de contraseñas (`PASSWORD_DEFAULT`, min 12, max 1024, Unicode/espacios), inspección de perfil con listado y revocación puntual o masiva de sesiones concurrentes (`sesiones_usuario`), protección CSRF estricta, validación frontend modular con PristineJS v1.1.0 y SweetAlert2, y auditoría transversal nativa con desinfección total de credenciales. Suites formales USR-01..40 y E2E-USR-01..10 (100% PASS).

Estado: completada (candidata a micro-baseline post USUARIOS-1).

## ROLES-2 / ROLES-2A — Administración Visual de Roles y Permisos RBAC

Administración interactiva de roles y permisos del sistema desde la interfaz Alina bajo `/configuracion/roles`: listado con filtros y búsqueda, creación y edición de roles personalizados, visualización de usuarios asociados en modo lectura, asignación masiva de permisos atómicos agrupados por módulos funcionales mediante matriz interactiva, preservación del principio `ROL ≠ REGISTRO DESECHABLE` (ciclo de vida exclusivo `ACTIVO` / `INACTIVO`, cero DELETE físico), protección incondicional del rol estructural `SUPERADMINISTRADOR` (código, desactivación y permisos críticos protegidos), actualización de autorizaciones en tiempo real sin relogin, auditoría contextual transversal (D-061 y D-062), validación cliente con PristineJS v1.1.0 y confirmaciones defensivas con SweetAlert2. Suites formales ROL2-01..40 (40/40 PASS), ROL-HIST-01 (1/1 PASS) y E2E-ROL2-01..12 (12/12 PASS).

## CONFIGURACIÓN-1 — Núcleo Central de Configuración y Parámetros del Sistema

Catálogo central de parámetros funcionales del sistema bajo la tabla `configuraciones` y la interfaz Alina en `/configuracion/sistema`:
- Separación canónica `CONFIGURACIÓN FUNCIONAL (BD) ≠ ENTORNO TÉCNICO (.env)` (cero secretos de infraestructura en BD, cero mutaciones a `.env` desde UI).
- Tipado fuertemente gobernado por `TipoConfiguracion` (`TEXTO`, `ENTERO`, `DECIMAL`, `BOOLEANO`, `FECHA`, `HORA`, `JSON`) con validación y casting nativo en backend.
- Acceso canónico encapsulado: `ConfiguracionServicio -> ConfiguracionRepositorio -> PDO -> MySQL` con caché de lectura en memoria de ciclo de vida de petición (`request-scoped`).
- Parámetros protegidos inmutables (`editable = 0`, ej. `sistema.version_instalada`) rechazados ante mutación con `ConfiguracionNoEditableExcepcion`.
- Capacidad de restauración a valores predeterminados de fábrica (`restaurarPredeterminado()`).
- Actualización atómica en lote (`actualizarMultiples()`) bajo una única transacción con reversión completa ante fallos.
- Auditoría contextual transversal cumpliendo D-061 (`ACTOR ≠ USUARIO`), imputación de autoría a `USR_x` y unificación de `correlacion_id` en operaciones de lote.
- Respeto riguroso de decisiones pendientes: exclusión deliberada de semillas de zona horaria / corte hotelero (P-004) y moneda / redondeo / impuestos (P-005).
- Vista administrativa Alina con navegación en pestañas (General, Localización, Operación), validación modular PristineJS v1.1.0 y confirmaciones con SweetAlert2.
- Suites de pruebas: CFG-01..40 (40/40 PASS) y E2E-CFG-01..12 (12/12 PASS).

Estado: completada.

## PROPIEDADES-1 — Maestro Central de Propiedades e Inmuebles Físicos

Maestro central de predios e inmuebles contenedores raíz bajo la tabla `propiedades` y la interfaz Alina en `/propiedades` y `/propiedades/{id}/perfil`:
- Principio ontológico vinculante `PROPIEDAD ≠ UNIDAD`: una propiedad representa única y estrictamente el contenedor físico o edificio (ej. "Edificio Ayuda Mutua"). Se prohíbe taxativamente modelar unidades, habitaciones, tipologías o inventario comercializable en esta fase (reservado a `UNIDADES-1`).
- Preservación histórica del ciclo de vida (`PROPIEDAD ≠ REGISTRO DESECHABLE`): cero eliminación física (`DELETE` = 0) en toda la arquitectura; ciclo de vida gobernado exclusivamente por la alternancia operativa `ACTIVO` / `INACTIVO`.
- Catálogo geográfico y georreferenciación defensiva: dirección física obligatoria no vacía (`direccion VARCHAR(255) NOT NULL`), vinculación íntegra al catálogo de países (`paises.id`), coordenadas GPS opcionales con rangos validados (`latitud` `[-90, 90]`, `longitud` `[-180, 180]`), y ficha técnica con enlace interactivo a Google Maps.
- Capa de servicio y repositorio con cálculo diferencial exacto (`diff`) que evita auditoría redundante, y trazabilidad integral bajo D-061 (`ACTOR ≠ USUARIO`) con autoría humana resuelta (`USR_x`).
- Respeto estricto de gobernanza: Decisiones P-004 (zona horaria y corte hotelero), P-005 (moneda, redondeo e impuestos) y P-006 (concurrencia de disponibilidad) se mantienen formalmente abiertas y pendientes.
- Interfaz Alina con maquetación de tarjetas, tabla dinámica, filtros multicriterio, modal con validación PristineJS v1.1.0 y diálogos con SweetAlert2.
- Suites de pruebas: Matriz formal PROP-01..40 (40/40 PASS), prueba histórica PROP-HIST-01 (10/10 PASS) y suite HTTP E2E Real Apache HTTPS E2E-PROP-01..12 (12/12 PASS).

Estado: completada.

## UNIDADES-1 — Maestro Central de Unidades Físicas y Alojables

Maestro central de divisiones físicas y unidades habitacionales/arrendables bajo las tablas `tipos_unidad` y `unidades`, y la interfaz Alina en `/unidades` y `/unidades/{id}/perfil`:
- Principio ontológico vinculante `PROPIEDAD ≠ UNIDAD`: La propiedad es el inmueble raíz y la unidad es la división física habitable/arrendable (Dpto, Habitación, Bungalow, Suite). Toda unidad pertenece obligatoriamente a una propiedad física (`propiedad_id NOT NULL`). No se admiten unidades huérfanas.
- Regla de propiedad inactiva: Se prohíbe la creación de nuevas unidades en propiedades inactivas. Una propiedad inactiva preserva íntegramente sus unidades históricas.
- Preservación histórica del ciclo de vida (`UNIDAD ≠ REGISTRO DESECHABLE`): Cero eliminación física (`DELETE FROM unidades` inexistente); ciclo de vida administrado por alternancia operacional `ACTIVO` ↔ `INACTIVO`. Solicitudes HTTP DELETE devuelven estrictamente `404 Not Found`.
- Unicidad scoped por propiedad (`UNIQUE(propiedad_id, codigo)`): El código técnico es único dentro de cada inmueble, admitiendo códigos idénticos en distintas propiedades (D-065).
- Catálogo de tipologías arquitectónicas (`tipos_unidad`): 5 semillas iniciales (`DEPARTAMENTO`, `HABITACION`, `CASA`, `SUITE`, `BUNGALOW`).
- Especificaciones físicas y ocupacionales: Capacidad de personas (1 a 100), dormitorios (>= 0, permitiendo 0 para monoambiente/estudio), baños (con decimales para medios baños, ej. 1.5), área en m² (opcional positiva) y piso/nivel (alfanumérico opcional).
- Trazabilidad y auditoría D-061 (`ACTOR ≠ USUARIO`): Eventos `CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR` registrados con actor humano (`USR_x`) y cálculo diferencial que suprime auditoría redundante en actualizaciones sin cambios reales.
- Delimitación estricta de dominio: `UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD`. P-004 (corte hotelero), P-005 (moneda/impuestos) y P-006 (concurrencia de disponibilidad) permanecen estrictamente abiertas y pendientes.
- Integración bidireccional con propiedades: Pestaña/tabla de unidades en `/propiedades/{id}/perfil` y tarjeta de inmueble raíz en `/unidades/{id}/perfil`.
- Suites de pruebas: Matriz formal UNI-01..40 (40/40 PASS), ciclo histórico UNI-HIST-01 (10/10 PASS) y suite HTTP E2E Real Apache HTTPS E2E-UNI-01..12 (12/12 PASS).

Estado: completada.

## GATE OPERATIVO-1 / GATE-OPERATIVO-1A — Definición del Tiempo Hotelero y Concurrencia de Disponibilidad (P-004 + P-006)

Microfase obligatoria de análisis, diseño técnico, prueba aislada y decisión formal antes del motor de disponibilidad:
- **P-004 Resuelta (D-066 — Modelo Temporal Hotelero):**
  - Separación ontológica tripartita: `INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL`.
  - UTC obligatorio para instantes técnicos (`TIMESTAMP`); `DATE` local para fechas/noches hoteleras.
  - Identificadores canónicos IANA obligatorios (cero offsets fijos).
  - Zona horaria predeterminada: `America/Lima` (`operacion.zona_horaria_predeterminada`), con capacidad de sobreescritura por propiedad física (`propiedades.zona_horaria` en DISPONIBILIDAD-1).
  - Intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$. Noches: $\text{noches} = \text{fecha\_salida} - \text{fecha\_entrada}$ con $\text{noches} \ge 1$.
  - Horarios de check-in / check-out: acuerdos operativos gobernados por parámetros configurables del PMS (`operacion.hora_checkin_predeterminada` y `operacion.hora_checkout_predeterminada`), cuyos valores iniciales se definirán operativamente antes de producción; no alteran el consumo de noches.
- **P-006 Resuelta (D-067 — Concurrencia e Inventario Diario):**
  - Modelo Híbrido: Entidad comercial (`reservas`/`bloqueos`) + Inventario Diario Físico (`inventario_diario_unidades`) bajo transacción ACID.
  - Inventario diario sparse: solo filas para noches ocupadas; disponibilidad = ausencia de fila.
  - Barrera absoluta de BD: restricción `UNIQUE(unidad_id, fecha)` en InnoDB que previene condiciones de carrera concurrentes y sobreventa.
  - Atomicidad y Rollback completo ante colisiones (cero reservas parcialmente confirmadas).
  - Orden determinista de locks: `ORDER BY unidad_id ASC, fecha ASC` (reducción sustancial del riesgo de deadlocks y patrones de adquisición cruzada).
  - Captura y traducción de conflictos (1062, 1205, 1213) a `ConflictoDisponibilidadExcepcion` (HTTP 409).
  - Liberación atómica por cancelación o expiración de hold temporal.
  - Centralización multicanal: Camargo PMS como única fuente de verdad autoritativa para PMS, WordPress, App móvil, OTAs y Webhooks.
- **P-005 Permanece Estrictamente Pendiente:** Moneda, redondeo e impuestos diferidos a la fase de tarifas y caja.
- **Harness Técnico Aislado:** 5/5 verificaciones de concurrencia directa, multinoches, intervalo semiabierto, liberación y timeouts defensivos (CONC-01..05 PASS).

Estado: homologada (micro-baseline oficial ef6a806).

## DISPONIBILIDAD-1 — Motor Central de Disponibilidad e Inventario Diario

Implementación completa del motor central de inventario diario y bloqueos operativos sobre la base de las decisiones D-066, D-067 y D-068:
- **Motor de Inventario Diario Sparse:**
  - Tabla `inventario_diario_unidades` en motor InnoDB con restricción inviolable `UNIQUE KEY uq_inventario_unidad_fecha (unidad_id, fecha)`.
  - Inserciones multinoche ordenadas deterministamente por `ORDER BY unidad_id ASC, fecha ASC`.
  - Captura y traducción de errores de clave duplicada (1062), lock wait timeout (1205) y deadlocks (1213) con `ROLLBACK` total y emisión de `ConflictoDisponibilidadExcepcion` (HTTP 409).
- **Gestión de Bloqueos Operativos:**
  - Maestro `bloqueos_unidad` para indisponibilidades por mantenimiento o manuales.
  - Ciclo de vida con preservación histórica (`ACTIVO` ↔ `LIBERADO`, cero `DELETE` en tabla maestra).
  - Liberación atómica: elimina noches en `inventario_diario_unidades` y actualiza el estado y actor liberador en `bloqueos_unidad`.
- **Modelo Temporal Hotelero Aplicado:**
  - Intervalo semiabierto $[\text{fecha\_inicio}, \text{fecha\_fin})$ donde la noche de checkout queda libre para check-in simultáneo.
  - Cálculo de noches $\ge 1$ con validación estricta de fechas gregorianas.
  - Resolución de huso horario IANA de la propiedad (`propiedades.zona_horaria`) con fallback a `operacion.zona_horaria_predeterminada` (`America/Lima`).
- **Preservación Estricta de Gobernanza:**
  - P-005 (moneda, redondeo e impuestos) se mantiene formalmente **PENDIENTE**; cero columnas o conceptos tarifarios en las tablas.
- **Interfaz Alina Operativa:**
  - Módulo completo en `/disponibilidad` con KPIs, consulta por fechas/propiedad/tipo, matriz/rack mensual interactivo y modales de bloqueo y liberación.
  - JavaScript moderno nativo (Vanilla JS, 0 jQuery, CSRF token, SweetAlert2, PristineJS v1.1.0).
- **Suites de Pruebas:**
  - Concurrencia productiva en MariaDB: CONC-PROD-01..05 (5/5 PASS).
  - Matriz formal exhaustiva de dominio: DISP-01..40 (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS: E2E-DISP-01..12 (12/12 PASS).

Estado: completada (candidata a micro-baseline).

## Dominio operativo

1. propiedades, niveles y unidades;
2. personas y proveedores;
3. disponibilidad y reservas;
4. estadías y arrendamientos;
5. contratos y documentos;
6. servicios, consumos y tarifas;
7. caja, cobros, gastos, impuestos y conciliación;
8. mantenimiento e inventario.

## Integraciones y reportes

API externa, WordPress, pagos/webhooks, mensajería, app, OTA y reportes. Cada integración inicia tras estabilizar el servicio de dominio que consumirá.

## Gate entre fases

Alcance aceptado, pruebas aplicables superadas, diff revisado, documentación actualizada, riesgos declarados y autorización cuando corresponda.
