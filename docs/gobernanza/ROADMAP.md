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

Estado: completada (candidata a micro-baseline).

## UI-3A — Fidelidad Visual Exacta de Formularios Nativos Alina

Microfase correctiva de fidelidad estética y geométrica absoluta basada en la inspección empírica de los componentes originales de Alina (`default_forms.html` y `select.html`):
- **Diagnóstico y Eliminación de Reglas Destructivas:** Detección y erradicación de overrides artificiales en `camargo.css` que forzaban `border-radius: 0.375rem !important` y alturas rectangulares en Select2 e inputs.
- **Vertical Form With Icon de Alina:** Adopción transversal de `<form class="app-form app-icon-form">`, inputs píldora (`border-radius: var(--app-border-radius)` = 20px), padding izquierdo de 48px (`3rem`) e incorporación del separador vertical `|` de 1px a 40px con altura de 20px mediante el pseudo-elemento `.icon-control::after`.
- **Select 2 de Alina:** Caja de selección redondeada tipo píldora de 20px (`var(--app-border-radius)`), borde de 1px, altura nativa de 42px (`calc(2.5rem + var(--bs-border-width) * 2)`), flecha chevron Font Awesome 6 `\f078` y botón de limpieza redondeado a 14px con fondo tenue rojo (`rgba(var(--danger), 0.2)`).
- **Limpieza Transversal de Clases:** Erradicación de `form-control-sm` y `form-select-sm` en campos que emplean `.icon-control` o `.basic-select2`.
- **Verificación Automatizada e Inmutabilidad:** Suite automatizada `test_ui3a_fidelidad_alina.php` (70/70 PASS) y regresión consolidada completa (363/363 PASS), alcanzando un total consolidado de 458/458 PASS (100%). Cero dependencias externas / CDN, cero alteraciones de backend y cero migraciones SQL.

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
