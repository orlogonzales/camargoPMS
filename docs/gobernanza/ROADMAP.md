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
  - Concurrencia productiva en MySQL 8.4.3: CONC-PROD-01..05 (5/5 PASS).
  - Verificación rigurosa de contratos 1205 y 1213: G-1205 y G-1213 (2/2 PASS).
  - Matriz formal exhaustiva de dominio: DISP-01..40 (40/40 PASS).
  - Suite HTTP E2E Real contra servidor Apache HTTPS: E2E-DISP-01..12 (12/12 PASS).

Estado: homologada (micro-baseline oficial a7ad7b2).

## GATE FINANCIERO-1 — Definición del Contrato Monetario, Precisión Decimal e Impuestos (P-005)

Formalización vinculante del contrato monetario, financiero y fiscal del PMS, cerrando de forma definitiva la decisión pendiente P-005 mediante la decisión D-069 antes de abordar la fase transaccional RESERVAS-1:
- **P-005 Resuelta (D-069 — Contrato Monetario y Financiero Base):**
  - Moneda canónica operativa: Sol peruano (`PEN`), código alfabético ISO 4217 de 3 caracteres en mayúsculas.
  - Desacoplamiento de presentación: símbolo comercial `'S/'` restringido exclusivamente a la capa visual (`Vistas/`); prohibición de persistencia o concatenación en lógica de dominio.
  - Prohibición estricta de coma flotante (`FLOAT`, `DOUBLE`, `REAL`); adopción de `DECIMAL` en base de datos y `BCMath` / strings en PHP 8.3.
  - Escala diferenciada: `DECIMAL(15,2)` para importes comerciales finales y saldos; `DECIMAL(15,4)` para tarifas base unitarias, prorrateos, consumos y tasas de impuestos (ej. `0.1800` para 18% IGV).
  - Regla de redondeo comercial: `ROUND_HALF_UP` a 2 decimales en el límite transaccional final, preservando precisión intermedia ($\ge 4$ decimales) sin redondeo prematuro acumulativo.
  - Autoridad de cálculo financiera centralizada en los servicios de backend; frontend puramente estimativo y rechazo categórico de totales ciegos enviados por clientes o APIs externas.
  - Preparación multimoneda: columna obligatoria `moneda_codigo VARCHAR(3)` en cada importe financiero persistido.
  - Inmutabilidad histórica: transacciones emitidas congelan snapshots de tarifas pactadas e impuestos vigentes; cero recálculo ante mutaciones posteriores de catálogos maestros o leyes fiscales.
  - Determinismo de saldos: compatibilidad con pagos parciales donde $\text{Saldo} = \text{Total} - \sum \text{Pagos Válidos}$.
  - Preservación contable: principio `ANULACIÓN / REVERSO ≠ DELETE`; cero eliminación física de pagos o asientos contables.
- **Verificación Técnica Automatizada (FIN-01 a FIN-20):**
  - Matriz técnica de 20 casos (`test_finanzas_p005.php`) ejecutada con 100% de éxito (20/20 PASS) sobre MySQL 8.4.3 LTS y PHP 8.3.30.
- **Invariantes de Repositorio:**
  - Cero código de producción prematuro (no se implementaron servicios de reservas ni caja en esta fase de gate).
  - Cero migración 014 (migraciones permanecen estrictamente en 001–013).
  - `admin-dashboard/` y `.env` intactos.

Estado: homologada (micro-baseline oficial c2d41cc).

## RESERVAS-1 / RESERVAS-1A — Núcleo Transaccional de Reservas Directas

Implementación del dominio transaccional de reservas directas con soporte nativo de multiunidad, snapshot financiero inmutable y ciclo de expiración de holds:
- **Modelo de Dominio y Persistencia (D-070):**
  - Entidades `Reserva` y `ReservaUnidad` con tipado estricto y desacoplamiento de presentación.
  - Tablas `reservas` y `reserva_unidades` persistidas mediante la migración `014_reservas.sql` (28 tablas, 35 Foreign Keys en el esquema consolidado).
  - Delimitación ontológica estricta: `RESERVA ≠ DISPONIBILIDAD ≠ INVENTARIO ≠ ESTANCIA ≠ PAGO`.
- **Soporte Multiunidad (1 Reserva : N Unidades):**
  - Permite agrupar múltiples unidades en un mismo contrato de reserva, con desglose individual de precio por noche, noches, subtotal e impuesto.
  - Asignación atómica de inventario en `inventario_diario_unidades` con orden determinista `ORDER BY unidad_id ASC, fecha ASC`.
- **Snapshot Financiero Inmutable (D-069 / D-070):**
  - Cálculo centralizado en el backend con aritmética exacta `BCMath` y redondeo `ROUND_HALF_UP` a 2 decimales sin recurrir a tipos flotantes binarios.
  - Almacenamiento de `moneda_codigo = 'PEN'`, `subtotal`, `impuesto = 0.00` (provisionalmente, sin clasificaciones tributarias asumidas por el PMS) y `total = subtotal` en `DECIMAL(15,2)`.
  - Inmutabilidad histórica garantizada: cambios de catálogo posteriores no alteran los snapshots pactados.
- **Ciclo de Estados y Expiración Automática de Holds:**
  - Estados: `PENDIENTE`, `CONFIRMADA`, `CANCELADA`, `EXPIRADA`.
  - Retención de inventario en estados activos (`PENDIENTE` y `CONFIRMADA`); liberación atómica e inmediata en estados terminales (`CANCELADA` y `EXPIRADA`).
  - Lógica de hold temporal: parámetro operacional `reservas.duracion_hold_minutos` sin valor por defecto arbitrario; su ausencia impide la creación de reservas `PENDIENTE` arrojando `ConfiguracionFaltanteExcepcion` (HTTP 422). Al estar configurado, la expiración de holds opera de forma atómica e idempotente vía web (`POST /reservas/expirar`) o comando CLI (`bin/expirar-reservas.php`).
- **Concurrencia, Locking y Manejo de Conflictos (D-067):**
  - Transacciones ACID en MySQL 8.4 InnoDB con captura de errores 1062, 1205 y 1213 convertidos a `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict).
  - Rollback integral multiunidad: ante colisión en cualquier unidad, revierte atómicamente todas las unidades previas sin dejar noches huérfanas.
- **Interfaz Alina y Experiencia de Usuario:**
  - Módulo completo en `/reservas` con KPIs en tiempo real, catálogo paginado, filtros multicriterio, modal de reserva multiunidad reactivo, modal de detalle con snapshot y modal de cancelación con motivo obligatorio.
  - Vanilla JS moderno (0 jQuery), validación PristineJS, notificaciones SweetAlert2 y token CSRF en todas las mutaciones.
- **Suites de Pruebas:**
  - Matriz de dominio e integración: RES-01..50 (50/50 PASS).
  - Concurrencia y locking: RES-CONC-01..05 + RES-EXP-01 (6/6 PASS).
  - Pruebas HTTP E2E reales contra Apache HTTPS: E2E-RES-01..15 (15/15 PASS).
  - Regresiones históricas: 205/205 PASS.
  - Total consolidado del sistema: 725 casos únicos / 731 ejecuciones brutas (100% PASS).

Estado: homologada (micro-baseline oficial 301fa00).

## UI-2 / UI-2A — Estandarización de Recursos de Interfaz (Font Awesome 6, Pickers y Badges/Chips Alina)

Estandarización transversal de recursos de interfaz y controles UI bajo D-071:
- Font Awesome 6.3.0 exclusivo y local (erradicación del 100% de Tabler icons en vistas, JavaScript y base de datos).
- Adopción obligatoria de Date Picker y Range Picker de Alina basados en Flatpickr v4.6.13 local con preservación de campos canónicos D-066.
- Estandarización estricta de Badges y Chips: adopción de *Variants of badge* y *Variants of chip* de Alina con prohibición absoluta de bordes `dotted`, `dashed` y clases crudas `bg-*-subtle`.
- Infraestructura centralizada: `CamargoPMS\Nucleo\Insignia` en backend y `window.CamargoInsignia` en frontend.
- Suites de pruebas: UI2-01..25 (25/25 PASS) y UI2A-01..20 (20/20 PASS).

Estado: homologada (micro-baseline oficial a914594).

## ESTADÍAS-1 — Check-in, Registro de Huéspedes y Ciclo Operativo de Estancias

Implementación del dominio de ocupación física real, check-in, llaves, lista centralizada de huéspedes y check-out bajo D-072:
- **Separación Ontológica Estricta:** `RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO`.
- **Multiunidad Operativa (1 Reserva : N Estadías Físicas Independientes):**
  - Cada unidad de una reserva confirmada genera una estadía independiente (`UNIQUE(reserva_unidad_id)`).
  - Ciclo de check-in y salida autónomo por unidad.
  - El agregado comercial `reservas` permanece inmutable; el estado de ocupación de la reserva es derivado.
- **Walk-in Diferido:** Validación de que todo check-in requiera una reserva comercial en estado `CONFIRMADA`.
- **Capacidad Física Estricta y Huésped Responsable:**
  - Validación de capacidad física $1 \le \text{huéspedes} \le \text{capacidad\_personas}$ (HTTP 422 si se excede).
  - Designación obligatoria de exactamente 1 huésped responsable por estadía.
  - Todos los ocupantes vinculados al maestro central de `personas` con prevención de duplicados (`UNIQUE(estadia_id, persona_id)`).
- **Ciclo de Vida Operativo e Inmutabilidad Histórica:**
  - Estados: `EN_CURSO`, `FINALIZADA`, `ANULADA`.
  - Principio `CHECK-OUT ≠ DELETE` y `ANULACIÓN ≠ DELETE`: cero eliminación física (`DELETE = 0`) en tablas `estadias` y `estadia_huespedes`; claves foráneas con `ON DELETE RESTRICT`.
  - Anulación excepcional con justificación obligatoria (1-255 caracteres).
  - Check-out anticipado o tardío con registro del instante real UTC sin recalcular noches comerciales (D-066).
- **Interfaz Alina y Experiencia de Usuario (D-071):**
  - Tablero de recepción en `/estadias` con KPIs en tiempo real, tabla interactiva, filtros combinados y modales de Check-in, Check-out, Anular y Detalle con gestión de huéspedes.
  - Vanilla JS nativo, validación PristineJS, notificaciones SweetAlert2 y badges oficiales Alina.
- **Persistencia Relacional (Migración 015):**
  - Tablas `estadias` y `estadia_huespedes`, 5 permisos RBAC (`estadias.*`) asignados a `SUPERADMINISTRADOR` y opción de menú dinámico bajo 'reservas'.

Estado: homologada (micro-baseline oficial 7911ae1).

## SERVICIOS-1 — Catálogo de Servicios, Proveedores, Consumos y Traslados

Implementación del dominio de catálogo maestro de servicios, directorio de proveedores externos homologados, imputación de consumos a reservas/estadías y extensión logística 1:1 de traslados bajo D-073:
- **Separación Ontológica Estricta:** `PROVEEDOR ≠ SERVICIO ≠ SERVICIO CONTRATADO ≠ RESERVA ≠ ESTADÍA`. Cero pagos, caja o facturación electrónica en esta fase.
- **Directorio Maestro de Proveedores Externos:**
  - Prestadores `EMPRESA` y `PERSONA_NATURAL` vinculados al maestro central de personas sin duplicar identidad.
  - Cero proveedor "Interno" ficticio: servicios de Camargo Hostelería se marcan con `es_operacion_interna = 1` y `proveedor_id = NULL`.
- **Integridad Estructural en Base de Datos (CHECK Constraint):**
  - Motor InnoDB MySQL 8.4 valida formalmente `chk_sc_coherencia_operacion_interna` garantizando coherencia matemática absoluta entre origen interno y proveedor externo.
- **Matriz de Homologación de Proveedores y Unicidad de Preferente:**
  - Costo pactado, plazos de pago y referencia.
  - Columna virtual generada e índice UNIQUE `uq_sp_servicio_preferente` para garantizar exactamente un proveedor preferente activo por servicio.
- **Catálogo Maestro de Servicios:**
  - Clasificación en 8 categorías y 6 modalidades de cobro (incluyendo `POR_UNIDAD`). Códigos canónicos `SERV-CAT-XXX`.
- **Contratación e Imputación de Consumos:**
  - Reserva comercial obligatoria (`reserva_id NOT NULL`).
  - Imputación coherente a estadía física validando transaccionalmente con bloqueo `FOR UPDATE` que pertenezca a la misma reserva y no esté `ANULADA`.
  - Snapshot económico y descriptivo inmutable (D-010 / D-069): congelamiento de importes en `DECIMAL(15,2)` con `moneda_codigo = 'PEN'`, `impuesto_total = 0.00` y `total = subtotal`.
- **Ciclo Operativo y Prohibición Estricta en Servicios Ejecutados:**
  - Estados: `SOLICITADO`, `CONFIRMADO`, `EJECUTADO`, `CANCELADO`.
  - Bloqueo vinculante: Prohibido cancelar un servicio `EJECUTADO` (HTTP 422).
  - Trazabilidad UTC y autoría D-061 en ejecución y cancelación.
- **Extensión Especializada 1:1 de Traslados (Transfers):**
  - Tabla `servicio_traslados` con origen/destino generalizado, número de vuelo, pasajeros, equipaje, vehículo y chofer asignado, con rollback transaccional atómico ante fallas logísticas.
- **Interfaz Alina Conforme a D-071:**
  - Módulo completo en `/servicios` con 4 pestañas interactivas, Flatpickr, Font Awesome 6.3.0 exclusivo, Variants of badge de Alina (`bg-light-*`), 0 dotted, 0 dashed, Vanilla JS nativo, PristineJS y SweetAlert2.
- **Persistencia Relacional (Migración 016):**
  - 7 tablas nuevas, 5 permisos RBAC (`servicios.ver`, `servicios.gestionar`, `servicios.contratar`, `servicios.ejecutar`, `servicios.cancelar`) y opción de menú dinámico bajo `reservas`.

Estado: homologada (micro-baseline oficial 39ad372).

## FINANCIERO-2 — Cuentas de Folios, Cargos, Pagos, Aplicaciones, Devoluciones y Caja Física

Implementación de la Tríada Financiera, tesorería operativa, folios comerciales 1:1 por reserva, arqueo determinista y ciclo de vida de cargos bajo D-074:
- **Tríada Financiera y Desacoplamiento de Cobros:**
  - Consagración formal de `CARGO ≠ PAGO ≠ MOVIMIENTO DE CAJA` y `PAGO ≠ APLICACIÓN DE PAGO`.
  - Cero banderas booleanas (`pagado = 1`); saldo de cargos y pagos derivado con `BCMath` en `DECIMAL(15,2)`.
  - Cero facturación electrónica SUNAT, cero IGV inventado, cero cuentas por pagar y cero eliminación física (`DELETE = 0`).
- **Folios Comerciales 1:1 por Reserva:**
  - Un folio financiero principal por reserva comercial (`cuentas_folios.reserva_id UNIQUE`).
  - Imputación a estadía opcional (`estadia_id NULL`) para desgloses por habitación.
- **Sincronización Automática de Cargos:**
  - Devengado automático de alojamiento al confirmar reservas.
  - Sincronización atómica de servicios contratados (`PROVISIONAL` $\rightarrow$ `DEVENGADO` $\rightarrow$ `ANULADO`).
- **Caja Física, Turnos y Arqueo Determinista:**
  - Obligatoriedad de caja abierta para cobros o devoluciones en efectivo (`EFECTIVO`).
  - Arqueo determinista de cierre: `CUADRADA` ($\Delta = 0.00$), `SOBRANTE` ($\Delta > 0.00$) o `FALTANTE` ($\Delta < 0.00$), con justificación obligatoria (10-500 caracteres).
  - Movimientos manuales de caja tipados: `INGRESO_AJUSTE` y `EGRESO_GASTO_MENOR`.
- **Cuentas Bancarias vs Medios Electrónicos:**
  - Depósito/transferencia exige cuenta bancaria. Tarjetas/billeteras operan con referencia externa y cuenta de destino opcional hasta conciliación.
- **Reversiones y Devoluciones:**
  - Reversión compensatoria de aplicaciones (`reversada = 1`) y devoluciones formales (`devoluciones_cuenta`) reduciendo saldo y afectando caja si es en efectivo.
- **Interfaz Alina Conforme a D-071:**
  - Módulo en `/caja` con KPIs, 2 pestañas (Folios y Turno de Recepción), 7 modales operativos, Vanilla JS (`gestion-caja.js`), PristineJS y SweetAlert2.
- **Persistencia Relacional (Migración 017):**
  - 11 nuevas tablas, 8 permisos RBAC (`caja.*`) y opción de menú dinámico `/caja` bajo `finanzas`.

Estado: completada (candidata a micro-baseline).

## UI-3 — Estandarización de Formularios mediante Componentes Nativos Alina

Estandarización transversal de todos los formularios, modales y filtros de Camargo PMS alineándolos con los patrones oficiales de Alina (D-075):
- **Vertical Form With Icon de Alina:** Adopción sistemática del contenedor `.icon-control.position-relative` con icono decorativo (`ms-3`) e input identado (`.ps-5`) en 11 vistas y más de 20 modales operativos.
- **Select2 4.0.13 y jQuery 3.7.1 100% Locales:** Assets servidos exclusivamente desde `public/assets/vendor/` local, eliminando cualquier dependencia de CDN externa o remota. Confinamiento estricto de jQuery a inicializar Select2 (`0 $.ajax()`, `0 CRUD` en scripts de negocio, 100% Vanilla JS + Fetch API).
- **Controlador Reactivo `camargo-select.js`:** Integración modular defensiva con soporte para `dropdownParent: $el.closest('.modal')` (evitando recortes y bloqueos de foco en modales Bootstrap 5), despacho automático de eventos nativos `input` y `change` para PristineJS, sincronización DOM vía `MutationObserver` y soporte para eventos de apertura modal.
- **Estilos Alina en `camargo.css`:** Sustitución de Tabler Icons por flecha Font Awesome 6 (`\f078` fa-chevron-down), altura estandarizada de 2.35rem, eliminación de bordes punteados (dotted/dashed) en multi-selects y estilización de bordes de error para PristineJS (`.has-danger`).
- **Radios, Checkboxes y Switches Nativos:** Estandarización dimensional mediante clases oficiales de Alina `.form-check.d-flex.align-items-center.gap-1` y `.form-check-input.f-s-18.mb-1`.
- **Erradicación Absoluta de Degradados:** Cero clases `btn-gradient-*` y `bg-gradient-*` en todo el árbol de vistas, sustituidas por estilos planos canónicos de Bootstrap 5 / Alina (`btn-primary`, `btn-outline-*`) e insignias suaves `bg-light-*` con texto semántico (`f-w-500` / `f-w-600`).
- **Preservación Integral:** Cero cambios en la base de datos o lógica de negocio (388/388 PASS verificados: 363 consolidado + 25 UI-3).

Estado: homologada (micro-baseline oficial 4e2afde).

## UI-3A — Fidelidad Visual Exacta de Formularios Nativos Alina

Microfase correctiva de fidelidad estética y geométrica absoluta basada en la inspección empírica de los componentes originales de Alina (`default_forms.html` y `select.html`):
- **Diagnóstico y Eliminación de Reglas Destructivas:** Detección y erradicación de overrides artificiales en `camargo.css` que forzaban `border-radius: 0.375rem !important` y alturas rectangulares en Select2 e inputs.
- **Vertical Form With Icon de Alina:** Adopción transversal de `<form class="app-form app-icon-form">`, inputs píldora (`border-radius: var(--app-border-radius)` = 20px), padding izquierdo de 48px (`3rem`) e incorporación del separador vertical `|` de 1px a 40px con altura de 20px mediante el pseudo-elemento `.icon-control::after`.
- **Select 2 de Alina:** Caja de selección redondeada tipo píldora de 20px (`var(--app-border-radius)`), borde de 1px, altura nativa de 42px (`calc(2.5rem + var(--bs-border-width) * 2)`), flecha chevron Font Awesome 6 `\f078` y botón de limpieza redondeado a 14px con fondo tenue rojo (`rgba(var(--danger), 0.2)`).
- **Limpieza Transversal de Clases:** Erradicación de `form-control-sm` y `form-select-sm` en campos que emplean `.icon-control` o `.basic-select2`.
- **Verificación Automatizada e Inmutabilidad:** Suite automatizada `test_ui3a_fidelidad_alina.php` (70/70 PASS) y regresión consolidada completa (363/363 PASS), alcanzando un total consolidado de 458/458 PASS (100%). Cero dependencias externas / CDN, cero alteraciones de backend y cero migraciones SQL.

Estado: homologada (micro-baseline oficial 0514447).

## ARRENDAMIENTOS-1 — Gestión de Arrendamientos de Mediana y Larga Estancia

Implementación del dominio de contratos de arrendamiento prolongado, coexistencia con el motor sparse de disponibilidad, devengo mensual recurrente, fondos de garantía en custodia y extensión compatible de cuentas folios bajo D-076:
- **Separación Ontológica Estricta:** $\text{RESERVA} \neq \text{ESTADÍA} \neq \text{ARRENDAMIENTO}$.
- **Titularidad Unificada en BD:** Sujetos modelados en `arrendamiento_personas` con columna virtual `es_titular_unico` y `UNIQUE KEY` para garantizar exactamente un titular principal en BD sin duplicar relaciones.
- **Vigencia Determinada y Semántica Hotelera:** Plazo determinado cerrado (`fecha_fin NOT NULL`, $\text{fecha\_fin} > \text{fecha\_inicio}$) con intervalo semiabierto $[\text{fecha\_inicio}, \text{fecha\_fin})$ donde `fecha_fin` queda libre/disponible. Prórrogas atómicas y renovaciones enlazadas.
- **Disponibilidad Sparse:** Materialización de noches en `inventario_diario_unidades` bajo `tipo_bloqueo = 'ARRENDAMIENTO'`, blindado por `UNIQUE(unidad_id, fecha)` en InnoDB. Rescisión anticipada con preservación de noches pasadas y liberación atómica de noches futuras.
- **Devengo Mensual Idempotente:** Entidad `arrendamiento_cuotas` con `UNIQUE(arrendamiento_id, periodo_anio, periodo_mes, tipo_cuota)`. Nomenclatura `dia_vencimiento` con regla para meses cortos (29/30/31).
- **Fondos en Garantía / Custodia:** Entidad `arrendamiento_garantias` segregada de la renta ordinaria con saldo reconstructible y trazabilidad completa de compensaciones.
- **Extensión Compatible de Folios:** Adaptación no destructiva de `cuentas_folios` mediante `reserva_id NULL`, `arrendamiento_id NULL UNIQUE` y `CHECK XOR`, con auditoría de consumidores y verificación por regresión completa de FINANCIERO-2.
- **Persistencia Relacional (Migración 018):** Tablas `arrendamientos`, `arrendamiento_personas`, `arrendamiento_cuotas`, `arrendamiento_garantias`, `arrendamiento_historial_estados`, permisos RBAC (`arrendamientos.*`) y opción de menú dinámico bajo `operaciones`.
- **Interfaz Alina Conforme a D-075:** Módulo en `/arrendamientos` con formulario nativo Alina, Select2 píldora 20px a 42px, Flatpickr, badges y PristineJS.

Estado: homologada (micro-baseline oficial c9eb823).

## MANTENIMIENTO-1 — Incidencias, Órdenes de Trabajo y Bloqueo Operativo de Unidades

Implementación del dominio integral de incidencias físicas, órdenes de trabajo preventivas y correctivas, tercer origen de indisponibilidad física en el inventario diario sparse y preservación histórica bajo D-077:
- **Separación Ontológica Estricta:** $\text{INCIDENCIA} \neq \text{ORDEN DE TRABAJO} \neq \text{BLOQUEO OPERATIVO}$. Las incidencias son tickets de observación que por sí mismos jamás bloquean unidades.
- **Coherencia de Bloqueo en BD:** Regla `CHECK` estricta garantizando que si `requiere_bloqueo = 1`, `unidad_id` y fechas de bloqueo sean obligatorias con $\text{fecha\_bloqueo\_fin} > \text{fecha\_bloqueo\_inicio}$; y si `requiere_bloqueo = 0`, ambas fechas sean estrictamente `NULL`.
- **Protección Anticipada de Inventario:** Materialización transaccional inmediata en `inventario_diario_unidades` en estado `PROGRAMADA` para el intervalo semiabierto $[\text{fecha\_bloqueo\_inicio}, \text{fecha\_bloqueo\_fin})$.
- **Mapeo de Origen en Disponibilidad:** `tipo_bloqueo = 'MANTENIMIENTO'`, `origen_tipo = 'MANTENIMIENTO_ORDEN'`, `origen_id = mantenimiento_ordenes.id`.
- **Responsables Desacoplados:** Soporte para colaboradores internos (`colaborador_asignado_id`) y contratistas externos de `SERVICIOS-1` (`proveedor_id`), gobernado por reglas de negocio en `MantenimientoServicio`.
- **Desacoplamiento Económico con Arrendamientos:** Cero columna `arrendamiento_id` en `mantenimiento_ordenes`; imputación financiera reservada a fases posteriores.
- **Modelo de Costos:** Costo estimado, mano de obra, materiales y total gestionados en `DECIMAL(15,2)` mediante `BCMath` en backend.
- **Relación N:M Incidencia <-> Orden:** Asociación de múltiples tickets en una orden correctiva mediante `mantenimiento_orden_incidencias`, admitiendo preventivos sin incidencias previas.
- **Preservación Histórica:** Al cerrar o cancelar órdenes con bloqueo transcurrido, las noches pasadas consumidas se preservan y solo se liberan las noches futuras.
- **Persistencia Relacional (Migración 019):** Tablas `mantenimiento_incidencias`, `mantenimiento_ordenes`, `mantenimiento_orden_incidencias`, `mantenimiento_historial_estados`, permisos RBAC (`mantenimiento.*`) y opción de menú dinámico bajo `reservas` (Operaciones).
- **Interfaz Alina Conforme a D-075:** Módulo en `/mantenimiento` con formulario nativo Alina, Select2 píldora 20px a 42px, Flatpickr, badges suaves y PristineJS.

Estado: homologada (micro-baseline oficial 34988e8).

## INVENTARIO-1 — Catálogo de Artículos, Almacenes/Ubicaciones, Existencias, Movimientos, Activos y Dotaciones

Implementación del dominio de inventario físico, existencias por ubicación, Kardex append-only, activos serializables y dotaciones de unidades bajo D-078:
- **Separación Ontológica Estricta:** $\text{ARTÍCULO} \neq \text{EXISTENCIA\ (STOCK)} \neq \text{MOVIMIENTO\ (KARDEX)} \neq \text{ACTIVO\ INDIVIDUAL}$.
- **Kardex Append-Only y Proyección Materializada:** La verdad histórica inmutable reside en `inventario_movimientos`. `inventario_existencias.cantidad_actual` actúa como proyección operacional materializada con locking pesimista (`FOR UPDATE`). Cero mutación vía CRUD. Reconciliación matemática garantizada.
- **Traslado Atómico de Dos Patas:** Cada traslado genera de forma atómica dentro de la misma transacción un `TRASLADO_SALIDA` en origen y un `TRASLADO_ENTRADA` en destino, vinculados con `correlativo_operacion`.
- **Normalización de Unidades de Medida:** Maestro `inventario_unidades_medida` (`codigo`, `nombre`, `simbolo`, `admite_decimales`). Separación de unidad base de stock vs presentación de compra.
- **Precisión Numérica y Financiera:** Cantidades en `DECIMAL(15,4)`, costo unitario histórico en `DECIMAL(15,4)` y costo total en `DECIMAL(15,2)` computado con `BCMath` y `ROUND_HALF_UP`. Moneda funcional `PEN` desacoplada.
- **Ubicaciones Polimórficas:** `inventario_ubicaciones` con tipos `ALMACEN`, `UNIDAD` (vinculada a `unidades.id`) y `CUSTODIA_EXTERNA` (vinculada a `proveedores.id` de `SERVICIOS-1`). Rotación de lencería limpia sin almacenes ficticios.
- **Activos Serializables Desacoplados de Existencias:** Bienes de categoría `ACTIVO_SERIALIZABLE` no poseen stock en existencias; cada ejemplar vive en `inventario_activos` con placa, serie y ubicación. Cero doble contador.
- **Ciclo de Vida de Activos:** Estados `DISPONIBLE`, `ASIGNADO`, `EN_MANTENIMIENTO`, `DE_BAJA` con justificación obligatoria y cero `DELETE` físico.
- **Dotaciones Estándar vs Realidad:** `inventario_dotaciones_estandar` modela la expectativa reglamentaria; la dotación real se consulta dinámicamente mediante las asignaciones de activos y existencias en la ubicación `UNIDAD`.
- **Integración con MANTENIMIENTO-1 No Incremental:** Salidas técnicas `SALIDA_MANTENIMIENTO` ligadas a `mantenimiento_orden_id`. Recálculo de costo de materiales como suma de movimientos válidos de la orden.
- **Manejo de Concurrencia y Restricciones:** Bloqueo pesimista ordenado por ID en traslados para evitar deadlocks. Restricción DDL `CHECK (cantidad_actual >= 0)`. Stock insuficiente genera `StockInsuficienteExcepcion` (HTTP 422); deadlocks/locks 1205/1213 se traducen a `ConflictoInventarioExcepcion` (HTTP 409).
- **Persistencia Relacional (Migración 020):** Tablas `inventario_unidades_medida`, `inventario_ubicaciones`, `inventario_articulos`, `inventario_existencias`, `inventario_movimientos`, `inventario_activos`, `inventario_dotaciones_estandar`, permisos RBAC (`inventario.*`) y menú bajo Operaciones.
- **Interfaz Alina Conforme a D-075:** Módulo en `/inventario` con formulario píldora 20px, Select2 42px, Flatpickr, badges y PristineJS.

Estado: homologada (micro-baseline oficial f8f1fcb).

## DOCUMENTOS-1 — Motor Documental, Plantillas Versionadas y Generación PDF

Implementación del motor central de generación documental, plantillas versionadas y contratos de arrendamiento bajo D-079:
- **Homologación de Dompdf 3.x:** Motor PDF oficial en PHP 8.3 puro (congelado por `composer.lock`), cero binarios externos.
- **Configuración de Seguridad Estricta:** `isRemoteEnabled = false`, `chroot` confinado estrictamente a rutas locales autorizadas, PHP y JavaScript deshabilitados.
- **Sanitización de HTML Documental:** Rechazo absoluto de `<script>`, `<iframe>`, `<object>`, `<embed>`, URLs externas o esquemas arbitrarios y event handlers.
- **Ontología Documental Vinculante:** $\text{PLANTILLA} \neq \text{VERSIÓN} \neq \text{SNAPSHOT} \neq \text{DOCUMENTO EMITIDO} \neq \text{PDF BINARIO}$. Modificaciones futuras no alteran documentos históricos.
- **Snapshots Inmutables:** `snapshot_datos_json` (datos deterministas) y `snapshot_html` (HTML resuelto congelado).
- **Integridad Criptográfica:** `hash_pdf_sha256` calculado sobre los bytes exactos del PDF físico almacenado.
- **Regeneración Auditada:** Cero regeneración silenciosa ante pérdida o corrupción de archivos; detección y registro en `documento_incidencias`.
- **Shortcodes Tipados:** Registro central tipado (`RegistroVariablesDocumentales`) con resolución y escape HTML. Shortcodes desconocidos o faltantes lanzan HTTP 422.
- **Activación Única y Folios Seguros:** Activación protegida a nivel InnoDB mediante columna virtual generada con índice único. Folios `DOC-ARR-YYYYMM-XXXX` generados con secuencias transaccionales atómicas.
- **Vertical Inicial de Contrato de Arrendamiento:** Generación completa del contrato legal formal A4 a partir de `arrendamiento_contratos`, sus partes, cánones, garantías y anexo de dotación física.
- **Persistencia Relacional (Migración 021):** Tablas `documento_secuencias`, `documento_plantillas`, `documento_plantilla_versiones`, `documentos_emitidos`, `documento_incidencias` y permisos RBAC (`documentos.*`).

Estado: homologada (micro-baseline oficial c35e7d6).

## COMPRAS-1 — Abastecimiento, Órdenes de Compra, Recepción Física, Conformidad de Servicios, Comprobantes de Proveedor y Cuentas por Pagar

Implementación del dominio de compras, abastecimiento y cuentas por pagar bajo la decisión vinculante D-080:
- **Axioma Ontológico Hexagonal:** $\text{SOLICITUD} \neq \text{ORDEN DE COMPRA} \neq \text{RECEPCIÓN/CONFORMIDAD} \neq \text{COMPROBANTE PROVEEDOR} \neq \text{CUENTA POR PAGAR} \neq \text{PAGO}$.
- **Desacople entre Orden e Inventario:** Una Orden de Compra representa un compromiso comercial y no altera existencias de inventario ni mueve el Kardex.
- **Líneas Tipadas Fuertes (BIEN vs. SERVICIO):** Líneas de `BIEN` exigen `inventario_articulos(id)` y recepción física; líneas de `SERVICIO` exigen descripción técnica y Acta de Conformidad, sin tocar almacén ni catálogo de servicios al huésped.
- **Aceptado ≠ Recibido Físicamente:** Solo la cantidad aceptada conforme ingresa al Kardex (`ENTRADA_COMPRA`). El rechazo físico conserva evidencia y motivo formal sin entrar a existencias.
- **Moneda Funcional:** Exclusivamente `PEN` en esta fase, derivada de la configuración monetaria existente sin literales quemados.
- **Inmutabilidad de la Orden Aprobada:** Proveedor, líneas, cantidades, precios y condiciones quedan congelados. Cero `UPDATE` destructivo.
- **Estados Ortogonales:** Dimensiones separadas para estado comercial, recepción física, facturación y pago.
- **Recepciones Parciales y Matching M:N:** Comprobantes tributarios pueden liquidar múltiples recepciones parciales mediante 3-Way Matching (`CONFORME`, `CON_DIFERENCIA`, `OBSERVADO`).
- **Desacople Comprobante, Pasivo y Pago:** La factura es la evidencia fiscal; la Cuenta por Pagar es el pasivo devengado; el Pago es el egreso financiero real de caja (`FINANCIERO-2`) o banco.
- **Integración Documental:** La orden aprobada emite su PDF A4 oficial mediante `DOCUMENTOS-1` (Dompdf 3.1.6).
- **Persistencia Relacional (Migración 022):** Tablas `compra_solicitudes`, `compra_solicitud_lineas`, `compra_ordenes`, `compra_orden_lineas`, `compra_recepciones`, `compra_recepcion_lineas`, `compra_conformidades`, `compra_comprobantes`, `compra_comprobante_aplicaciones`, `cuentas_por_pagar`, `cxp_pagos`, `compra_historial_estados` y permisos RBAC (`compras.*`).

Estado: homologada (micro-baseline oficial aff5c4a).

## SUMINISTROS-1 — Servicios Básicos, Medidores, Tarifas con Vigencia Histórica e Imputación a Folios de Arrendamiento

Implementación del dominio de suministros y consumos periódicos bajo la decisión vinculante D-081:
- **Axioma Ontológico Hexagonal:** $\text{SUMINISTRO} \neq \text{MEDIDOR} \neq \text{LECTURA} \neq \text{TARIFA} \neq \text{CONSUMO VALORIZADO} \neq \text{CARGO} \neq \text{PAGO}$.
- **Desacople Ubicación vs. Sujeto Económico:** La unidad aloja físicamente el medidor; el contrato de arrendamiento y su cuenta folio asumen la obligación de pago.
- **Modalidades de Suministro:** Soporte dual para modalidad `MEDIDO` (cálculo por delta de lecturas volumétricas con dial cíclico / rollover) y `FIJO_PERIODICO` (cuotas fijas sin medidor como Internet o áreas comunes).
- **Precedencia Tarifaria Jerárquica:** $\text{UNIDAD} > \text{PROPIEDAD} > \text{GLOBAL}$, protegida en InnoDB mediante bloqueo pesimista `SELECT ... FOR UPDATE` anti-solapamiento.
- **Medidores Físicos y Reemplazo Atómico:** Parque de medidores con reemplazo atómico (retiro con lectura final obligatoria + alta con lectura inicial) e índice virtual `medidor_activo_idx`.
- **Lecturas Inmutables Append-Only:** Cero mutación destructiva sobre lecturas previas. Correcciones auditadas mediante nuevo registro referenciado e índice virtual `correccion_activa_idx`.
- **Liquidación Multitramo y Devengo en Folio (`FINANCIERO-2`):** Cómputo aritmético determinista con BCMath a 4 decimales en cantidad y 2 en moneda. Desglose en `suministro_liquidacion_tramos` ante cambios tarifarios o de medidor. Devengo formal atómico del cargo en cuenta folio (`SUMINISTRO_CONSUMO` o `SUMINISTRO_CUOTA_FIJA`).
- **Anulación y Reliquidación con Preservación Contable:** Anulación del cargo con des-aplicación formal de pagos (`monto_aplicado = 0.00`, aplicación `REVERTIDA`) liberando saldo a favor en cuenta folio sin egresos ficticios de caja.
- **Frontera Estricta:** Culmina en el devengo del cargo. Cero emisión de recibos PDF ni cobranza en esta fase (reservado a `RECIBOS-1`).
- **Persistencia Relacional (Migración 023):** Tablas `suministros`, `suministro_tarifas`, `suministro_medidores`, `suministro_lecturas`, `suministro_liquidaciones`, `suministro_liquidacion_tramos` y permisos RBAC (`suministros.*`).

Estado: homologada (micro-baseline oficial 75e5a5e, 71/71 pruebas específicas PASS, 889/889 regresión activa PASS).

## RECIBOS-1 — Emisión de Recibos de Cobranza, Snapshots Financieros $T_0$, Preservación Criptográfica Inmutable e Integración con FINANCIERO-2 y DOCUMENTOS-1

Implementación del dominio de recibos de cobranza, constancias históricas probatorias inmutables e integración documental bajo la decisión vinculante D-082:
- **Axioma Ontológico Hexagonal:** $\text{CARGO} \neq \text{PAGO} \neq \text{APLICACIÓN} \neq \text{RECIBO} \neq \text{PDF}$.
- **Definición Vinculante de Recibo:** $\text{RECIBO} = \text{CONSTANCIA HISTÓRICA INMUTABLE DE UN HECHO DE COBRO}$. No es un extracto mutable de cuenta corriente.
- **Congelamiento Temporal en $T_0$:** El recibo congela la realidad económica del instante exacto de emisión: cargos amortizados, saldo restante de cada cargo (`cargo_saldo_restante`) y deuda total del folio (`folio_saldo_pendiente_historico`). Pagos o liquidaciones en $T_1$ devengan sus propios recibos y no mutan los de $T_0$.
- **Ecuación Contable Universal:** $\text{monto\_recaudado} = \text{monto\_imputado} + \text{monto\_no\_aplicado\_pago}$. Soporte para abonos exactos, parciales, compuestos multipropósito y anticipos sin imputación previa (`monto_no_aplicado_pago > 0`).
- **Garantías Segregadas:** Tipificación formal de depósitos en garantía (`cargo_origen_tipo = DEPOSITO_GARANTIA`), preservando la custodia no operativa de fondos.
- **Unicidad Relacional:** 1 Pago Confirmado = Máximo 1 Recibo Activo, blindado físicamente en InnoDB por la columna virtual `recibo_activo_idx` y la clave única `uq_rec_pago_activo`.
- **Inviolabilidad Criptográfica y Física:** Integración con Dompdf 3.1.6 vía `DOCUMENTOS-1`. Cálculo de `hash_pdf_sha256` sobre bytes en disco y folio `REC-YYYYMM-XXXX`. Al anularse, el recibo pasa a `ANULADO`; el archivo físico en disco y su hash jamás se sobreescriben ni corrompen.
- **Desacople con FINANCIERO-2:** Anular un recibo NO revierte el pago en caja/folio. Reversar un pago en FINANCIERO-2 conserva el recibo emitido en BD como prueba histórica inmutable.
- **Persistencia Relacional (Migración 024):** Tablas `recibos` (tabla 79) y `recibo_lineas` (tabla 80), con índices rápidos, constraints `ON DELETE RESTRICT` y permisos RBAC (`recibos.*`).
- **Interfaz Alina Conforme a D-075 y D-076:** Módulo en `/recibos` con 4 KPIs en vivo, modales `app-form app-icon-form`, bordes `b-r-20`, Select2 42px y controlador JS modular `gestion-recibos.js`.

Estado: homologada (micro-baseline oficial 439b16f, 60/60 pruebas específicas PASS, 949/949 regresión activa PASS).

## HOUSEKEEPING-1 — Housekeeping, Pisos, Inspección de Habitaciones, Reproceso y Control Textil de Lencería

Implementación del subsistema de pisos, gobernanza operativa de limpieza, checklists inmutables, derivación dinámica de disponibilidad hotelera y circuito textil bajo la decisión vinculante D-083:
- **Axioma Ontológico Hexagonal:** $\text{ESTADO COMERCIAL} \neq \text{ESTADO DE OCUPACIÓN} \neq \text{ESTADO DE LIMPIEZA} \neq \text{DISPONIBILIDAD} \neq \text{MANTENIMIENTO}$.
- **Derivación Dinámica en Vivo:** $\text{UNIDAD LISTA PARA CHECK-IN} = \text{resultado operacional derivado}$ (VR - Vacant Ready). Prohibición estricta de guardar VR, VD, OD, OOO como columnas redundantes en BD; cálculo transparente en tiempo real mediante el DTO `HousekeepingDerivacionOperativa`.
- **Acoplamiento Atómico en Check-out:** En `EstadiaServicio::realizarCheckout()`, la actualización de la estadía a `FINALIZADA`, el pase de la unidad a `SUCIA` y la creación de la tarea operativa de `SALIDA` ocurren dentro de la misma transacción PDO de forma indivisible.
- **Idempotencia Transaccional:** Múltiples ejecuciones del check-out sobre una misma estadía devuelven la tarea de salida preexistente sin duplicar órdenes de trabajo.
- **Validación Estricta de Check-in (Cero Bypass):** En `EstadiaServicio::realizarCheckin()`, hook vinculante `validarAptaParaCheckin()`. Bloqueo absoluto de check-in si la unidad no se encuentra en estado `LIMPIA_INSPECCIONADA` (VR), arrojando `UnidadNoListaExcepcion` (HTTP 409).
- **Checklists Inmutables y Puntos Críticos:** Snapshot inmutable de la plantilla asignada a la tarea con evaluación tri-valente (`CONFORME`, `NO_CONFORME`, `NO_APLICA`). Puntos de control marcados como críticos impiden forzar la aprobación si están no conformes (`ValidacionHousekeepingExcepcion` 422), obligando al envío a reproceso (`RECHAZADA` / `RETOQUE_REQUERIDO`).
- **Integración con Kardex e Inventario (INVENTARIO-1):** Salida de consumibles de amenities mediante movimiento de Kardex (`SALIDA_CONSUMO`) y descuento seguro de inventario sin permitir saldos negativos en existencias.
- **Circuito Textil de Lavandería con Custodia Externa:** Despacho de lencería mediante traslado de dos patas (`ALMACEN -> CUSTODIA_EXTERNA`), retorno con registro de prendas conformes y mermas por deterioro, y cálculo de discrepancias en columna virtual `cantidad_diferencia`.
- **Persistencia Relacional (Migración 025):** Tablas 81 a 89 (`housekeeping_unidades_limpieza`, `housekeeping_tareas`, `housekeeping_tarea_checklist`, `housekeeping_tarea_consumos`, `housekeeping_tarea_historial`, `housekeeping_lotes_lavanderia`, `housekeeping_lote_lineas`, `housekeeping_checklist_plantillas`, `housekeeping_checklist_plantilla_items`) y permisos RBAC (`housekeeping.*`).
- **Interfaz Alina Conforme a D-075 y D-076:** Módulo `/housekeeping` con 4 KPIs (VR, VD, VCL, OOO), Rack Operacional interactivo, pestaña de Tareas con modales dinámicos y pestaña de Lotes de Lavandería Textil.

Estado: candidata pre-commit (60/60 pruebas específicas PASS, 1,009/1,009 regresión activa PASS).

## AIRBNB-ICAL-1 — Canales de Distribución y Sincronización iCalendar RFC 5545

Implementación de la infraestructura soberana de sincronización bilateral multicanal con OTAs bajo D-103:
- **AIRBNB-ICAL-1A:** Análisis arquitectónico, contratos RFC 5545, inventario de canales y diseño desacoplado (Cerrada y Homologada).
- **AIRBNB-ICAL-1B:** Infraestructura soberana multicanal iCalendar:
  - Adopción oficial del parser RFC 5545 `sabre/vobject: ^5.0` (BSD-3-Clause, PHP 8.3 puro, sin dependencias pesadas).
  - Cero DDL sobre `inventario_diario_unidades`: los bloqueos se persisten con `tipo_bloqueo = 'BLOQUEO_MANUAL'`, `origen_tipo = 'EVENTO_ICAL_EXTERNO'` y `origen_id = eventos_ical_externos.id`.
  - Persistencia relacional (Migración 035): 4 tablas (`canales_distribucion`, `conexiones_ical`, `eventos_ical_externos`, `sincronizaciones_ical_log`). Total base de datos asciende a 122 tablas; ranura 036 estrictamente libre.
  - Criptografía autenticada AES-256-GCM para URLs privadas (`ICAL_ENCRYPTION_KEY` vía `.env`) y modelo híbrido de tokens de exportación (hash SHA-256 para indexación $O(1)$ + ciphertext AES-256-GCM para panel).
  - Defensa en profundidad Anti-SSRF: bloqueo exhaustivo IPv4/IPv6/CGNAT/metadata, DNS pinning mediante `CURLOPT_RESOLVE`, redirecciones manuales verificadas (máx 3), límite de 2 MB y timeout de 10s.
  - Motor de sincronización con unión determinista multi-OTA, detección de conflictos locales y salvaguarda defensiva ante feeds vacíos (`FEED_VACIO_SOSPECHOSO`).
  - Exportación segura Anti-Echo y anonimización de privacidad en `GET /ical/exportar/{token}`.
  - Invocación desacoplada y CLI runner `bin/sincronizar-ical.php`.
  - Suite de pruebas: `test_airbnb_ical.php` (155/155 PASS).
  - Estado: homologada y publicada (micro-baseline oficial `991fba40e3831b5f5eb7e6812a16bb9100a64aef`, 72/72 suites globales PASS, 1,847 checks, 0 fallos).
- **AIRBNB-ICAL-1C / 1C-C1:** Capa Operativa Alina para Canales y Conexiones iCalendar:
  - Módulo administrativo interactivo bajo `/canales-ical` con diseño 100% Alina, 4 KPIs en vivo (Total, Activas, Pausadas, Conflictos), modales reactivos (Crear/Editar Conexión, Historial de Sincronizaciones, Matriz de Conflictos) y tabla responsiva.
  - Sincronización manual bajo demanda con bloqueo distribuido concurrente (`GET_LOCK`), mitigación de doble click y prevención de colisiones.
  - Protección estricta de secretos: URLs privadas de importación jamás viajan al cliente; recuperación de URL de exportación bajo demanda vía POST autenticado con directivas de cabecera `Cache-Control: no-store, no-cache, must-revalidate` y `Pragma: no-cache`.
  - Rotación criptográfica de tokens con invalidación inmediata del feed previo (HTTP 404).
  - RBAC granular (`canales.ver`, `canales.gestionar`, `canales.sincronizar`) y protección CSRF estricta en todos los endpoints mutacionales.
  - Cero DDL (122 tablas relacionales preservadas, ranura 036 estrictamente libre, `admin-dashboard/` 100% inalterado).
  - Suite de pruebas: `tests/test_airbnb_ical_ui.php` (94/94 PASS).
  - Estado: Implementada y Validada (73/73 suites globales PASS, 1,947 checks, 0 fallos).
- **AIRBNB-ICAL-1D (Siguiente):** Automatización periódica desasistida (daemon/scheduler CLI, gestión de colas, retries con backoff y telemetría de fallos continuos).

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
