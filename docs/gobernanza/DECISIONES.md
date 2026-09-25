# Registro de decisiones

Las decisiones aprobadas se modifican mediante una nueva entrada o una actualización explícita que conserve el motivo. Los asuntos pendientes no autorizan una elección automática.

## Decisiones aprobadas

### D-001 — PMS como fuente central

Camargo PMS concentra disponibilidad y operaciones. WordPress, apps y futuras OTA consumen API y no mantienen reglas paralelas.

### D-002 — MVC propio por capas

PHP 8.3 con Enrutador, Intermediarios, Controladores, Servicios, Repositorios, PDO y MySQL/MariaDB. Sin framework PHP ni ORM hasta decisión posterior.

### D-003 — Código propio en español

Clases, métodos, variables, carpetas, vistas, rutas y esquema propios usan español. APIs externas y keywords mantienen su nombre.

### D-004 — Alina como interfaz oficial e inmutable

`blank.html` es la fuente visual. Originales, demos, assets, documentación, Figma, SCSS/Webpack y vendors se conservan intactos.

### D-005 — Navegación Alina como contrato único

`navbar-menu-list[data-target]` y `main-side-menu .main-menu[id]` forman una navegación acoplada por clave estable. El backend filtra visibilidad y protege rutas.

### D-006 — JavaScript propio del layout

No se adopta `script.js` ni `theme_customizer.js` como núcleo definitivo. Se creará `camargo-layout.js` con funciones necesarias y componentes opcionales seguros.

### D-007 — Assets mínimos

Bootstrap, Tabler Icons, Simplebar y CSS esencial de Alina son globales; plugins especializados se cargan por módulo.

### D-008 — Vistas pasivas

El layout recibe información preparada. Las vistas no consultan BD ni deciden permisos.

### D-009 — Identidad y actores separados

Una persona puede asumir roles de dominio; cuenta de acceso y cliente técnico son responsabilidades distintas. Auditoría distingue `USER`, `SYSTEM`, `INTEGRATION` y `PAYMENT_PROVIDER`.

### D-010 — Históricos preservados

Contratos, tarifas, servicios vendidos, impuestos y movimientos conservan versiones o snapshots necesarios. Un cambio de catálogo no altera operaciones pasadas.

### D-011 — Desarrollo por fases y micro-baselines

Cada incremento ejecuta precheck, análisis, implementación limitada, pruebas, diff, documentación y baseline autorizada. No se inicia una fase posterior automáticamente.

### D-012 — Gobernanza antes de implementación

G-0 precede a Git, infraestructura PHP y conversión de Alina.

### D-013 — Repositorio y baseline inicial

La rama oficial es `main` y el remoto oficial es `origin` en `https://github.com/orlogonzales/camargoPMS.git`. La distribución original de Alina y su documentación se versionan intactas. El Figma de 22,62 MiB permanece local, se ignora y no se elimina; no se adopta Git LFS en G-1. Cada micro-baseline exige verificar el SHA, working tree y relación entre `main` y `origin/main`.

### D-014 — Autocargador PSR-4 y Enrutador Reversible para UI-0

Se implementan `Autocargador` PSR-4 propio para el espacio de nombres `CamargoPMS\` y un `Enrutador` mínimo en `app/Nucleo/` para soportar la fase de interfaz sin incorporar dependencias externas tempranas. La decisión P-002 (adopción del motor definitivo de enrutamiento y dependencias vía Composer) se mantiene formalmente abierta para la fase de infraestructura.
### D-015 — Asignación de referencias visuales Alina y arquitectura de errores

Se formalizan las páginas de Alina como referencias inmutables oficiales: `blank.html` (layout general autenticado), `sign_in.html` (referencia visual de login sin implementar autenticación funcional en UI-0), `index.html` (referencia visual de dashboard sin datos simulados en UI-0) y las cinco páginas `error_*.html` (400, 403, 404, 500 y 503). Se implementa una plantilla aislada `plantillas/error.php` (`.error-container`) y la vista reutilizable `errores/error.php` que desacopla el diagnóstico técnico interno de la visualización segura del usuario.

### D-016 — Separación de Identidad, Personal, Usuarios, Roles y Permisos

Se formaliza el principio arquitectónico vinculante `PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL`. Un Colaborador es la vinculación laboral de una Persona y mantiene un historial de episodios laborales inmutable; un Cargo describe la función laboral y no confiere privilegios de software; un Usuario es una cuenta de acceso humano con contraseñas seguras y roles dinámicos; y los Permisos son capacidades atómicas `recurso.accion` evaluadas estrictamente en backend. La ocultación de menús es solo cosmética y nunca sustituye la autorización del servidor (devolviendo HTTP 403 real ante accesos no autorizados). Personal no es un subsistema financiero; Caja gestiona sus movimientos referenciando a personas. El menú dinámico preserva el contrato Alina `data-target` ↔ `id`.

### D-017 — Integración Externa con APIsPERU (DNI y RUC) mediante Abstracción

Se aprueba la integración con APIsPERU para la consulta y autocompletado ágil de datos de identidad en Perú mediante dos endpoints desacoplados: `GET /api/v1/dni/{dni}` (8 dígitos) y `GET /api/v1/ruc/{ruc}` (11 dígitos, reutilizable para proveedores y empresas). La integración debe orquestarse mediante una capa de abstracción en el dominio (`ServicioConsultaIdentidad` e interfaces de proveedor) que impida el acoplamiento directo y permita cambiar de proveedor en el futuro. Es vinculante no asumir campos no documentados en contrato y permitir la edición o complemento manual en la interfaz. El token técnico se administra exclusivamente en el entorno del servidor, fuera de Git y del cliente.

### D-018 — Motor de base de datos oficial: MySQL 8.4.3 LTS

Se resuelve formalmente **P-001** con base en la evidencia de la auditoría del entorno local. Se adopta oficialmente **MySQL Community Server 8.4.3 LTS (GPL)** con almacenamiento transaccional `InnoDB`, codificación `utf8mb4`, collation `utf8mb4_0900_ai_ci` y `sql_mode` estricto estándar.

### D-019 — Adopción pragmática de Composer y preservación del router nativo

Se resuelve formalmente **P-002**. Se adopta Composer para la gestión de dependencias mínimas justificadas (`vlucas/phpdotenv` para entorno) y el autoloading PSR-4 asignando el espacio de nombres raíz `CamargoPMS\` al directorio `app/`. Se preserva el enrutador nativo `app/Nucleo/Enrutador.php` tras validar su óptimo funcionamiento en UI-0 y UI-1, evitando el reemplazo innecesario de infraestructura funcional.

### D-020 — Esquema consolidado oficial y migraciones incrementales

Se formaliza el contrato de persistencia: `SQL/camargo_pms.sql` es la representación consolidada oficial y versionada del esquema vigente de Camargo PMS, libre de datos operativos y credenciales. `SQL/migraciones/` contiene la secuencia cronológica de cambios estructurales controlados mediante la tabla técnica `migraciones` y el runner CLI `php migrar.php`. Todo cambio estructural futuro debe reflejarse simultáneamente en una migración y en el SQL consolidado. El campo `lote` (`batch`) representa estrictamente metadatos de trazabilidad y agrupación cronológica, sin asumir reversibilidad automática.

### D-021 — Núcleo de Identidad Humana y separación Persona / Documento / Contacto

Se establece el modelo relacional del maestro humano central separando estrictamente la entidad `personas` de sus colecciones dependientes `personas_documentos` y `personas_contactos`. La tabla `personas` contiene exclusivamente atributos ontológicos del individuo (nombres obligatorios, apellidos paterno/materno con nulabilidad defensiva para extranjeros, fecha de nacimiento nullable sin fechas futuras, país de nacionalidad y dirección básica). Los documentos y medios de contacto se modelan como entidades normalizadas 1:N sin columnas repetibles planas (`telefono_1`, `email_1`, etc.).

### D-022 — Exclusión estricta de RUC como documento personal

Se prohíbe modelar el RUC como un tipo de documento personal equivalente a DNI o Pasaporte en el maestro de personas naturales. APIsPERU provee consultas independientes de DNI y RUC, pero conceptualmente el RUC corresponde a la identidad tributaria/fiscal de personas con negocio o entidades jurídicas (empresas/proveedores). Las personas jurídicas y sus números de RUC serán abordados en su módulo específico sin degradar el maestro de personas naturales.

### D-023 — Catálogos normalizados de Países y Tipos de Documento

Se formalizan los catálogos relacionales `paises` (códigos ISO-3166-1 alpha-2 y alpha-3 únicos, nombre, nacionalidad y estado activo) y `tipos_documento` (código único, nombre, descripción, restricciones de longitud y expresiones regulares de formato). Se definen como datos estructurales iniciales: Perú ('PE', 'PER') como país base disponible, y DNI (8 dígitos numéricos exactos), Pasaporte y Carné de Extranjería (CE) como tipos de documento personales.

### D-024 — Unicidad documental y garantía de principales activos vía columnas virtuales generadas

Se asegura la unicidad documental a nivel de base de datos mediante la restricción `UNIQUE (tipo_documento_id, numero_documento)`, impidiendo que dos personas compartan el mismo documento. Para garantizar la invariante de dominio de "máximo un documento principal activo por persona" y "máximo un contacto principal activo por tipo y persona", se adoptan en MySQL 8.4 columnas virtuales generadas (`uq_persona_principal` y `uq_contacto_tipo_principal`) con índices únicos condicionales sobre valores no nulos, complementados por la coordinación transaccional en `PersonaServicio`. La relación teléfono/WhatsApp se resuelve mediante un indicador booleano `es_whatsapp` en el mismo registro de contacto telefónico, evitando la duplicación ciega de números.

### D-025 — Gestión de estados, soft delete y preservación histórica

Se prohíbe el borrado físico (`DELETE`) en las operaciones ordinarias de personas, documentos y contactos. Se adopta la desactivación lógica mediante el campo `estado` (`'ACTIVO'`, `'INACTIVO'`) para preservar relaciones contractuales, de reservas y auditoría futura. Las claves foráneas hacia personas y catálogos aplican `ON DELETE RESTRICT ON UPDATE CASCADE`. La clave primaria adopta `BIGINT UNSIGNED AUTO_INCREMENT` para personas y colecciones de alto volumen e `INT UNSIGNED` para catálogos.

### D-026 — Principio de Separación Integral: PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL

Se formaliza la separación conceptual y técnica estricta de las entidades del dominio humano y organizacional:
1. `Persona`: Ser humano ontológico (identidad biométrica/civil).
2. `Colaborador`: Identidad laboral estable dentro de la organización.
3. `EpisodioLaboral`: Período cronológico continuo de vinculación laboral (ingreso hasta cese).
4. `Cargo`: Catálogo administrable de funciones/puestos de trabajo (no otorga permisos de software).
5. `Usuario`: Cuenta de acceso técnico autenticado a la plataforma.
6. `Rol`: Conjunto de permisos de seguridad de software para autorización granular.
Ninguna entidad se mezcla ni sustituye a otra.

### D-027 — Cardinalidad 1:1 lógica Persona-Colaborador con inmutabilidad de identidad interna

Una Persona natural puede tener como máximo un registro en la tabla `colaboradores` (`uq_colaboradores_persona`). La identidad del colaborador se identifica externamente mediante un código secuencial estable (`COL-XXXX`) que permanece inmutable ante ceses y reingresos. Se prohíbe terminantemente crear múltiples filas de colaboradores para la misma persona en recontrataciones o retornos futuros.

### D-028 — Historial laboral inmutable por Episodios y Asignaciones de Cargo con columnas virtuales generadas

El historial laboral no se sobreescribe ni se destruye. Cada período de contratación genera un registro en `episodios_laborales` y cada cambio o ascenso funcional dentro de un episodio genera un registro en `episodios_laborales_cargos`. Para garantizar a nivel de base de datos la invariante de "a lo sumo un episodio abierto activo por colaborador" y "a lo sumo una asignación de cargo vigente por episodio", se implementan columnas virtuales generadas (`uq_colaborador_abierto` y `uq_episodio_cargo_abierto`) con restricciones `UNIQUE` sobre valores no nulos.

### D-029 — Convención temporal de continuidad en transiciones de cargo y prohibición de solapamiento

En un cambio de cargo en fecha $D$, la asignación de cargo anterior se cierra con `fecha_fin = D - 1 día` y la nueva asignación se abre con `fecha_inicio = D` y `fecha_fin = NULL`, garantizando continuidad cronológica estricta sin solapamiento ni días vacíos. Se prohíbe el solapamiento temporal tanto a nivel de episodios laborales como de cargos dentro de un episodio. Toda operación de transición y cese se ejecuta con bloqueos pesimistas (`SELECT ... FOR UPDATE`) dentro de transacciones ACID.

### D-030 — Ajuste evolutivo de identidad: monónimos y unicidad documental internacional inicial

Se adoptó un ajuste evolutivo no destructivo en la migración `003` para dos aspectos de la identidad:
1. Nombres internacionales y monónimos: Se eliminó la restricción que exigía al menos un apellido (`chk_personas_al_menos_un_apellido`), manteniendo `nombres` como obligatorio y permitiendo apellidos nulos para individuos extranjeros o monónimos legales.
2. Unicidad documental internacional: La unicidad documental de `personas_documentos` incorporó inicialmente el país emisor mediante la columna virtual generada `pais_emisor_efectivo = COALESCE(pais_emisor_id, 0)` y la restricción `UNIQUE (tipo_documento_id, pais_emisor_efectivo, numero_documento)`. Esta solución fue superada estructuralmente en la migración `004` (ver D-031).

### D-031 — Jurisdicción documental estructural sin centinelas (Migración 004 / PERSONAL-1A)

Se elimina formalmente el centinela técnico `COALESCE(pais_emisor_id, 0)` y la columna virtual `pais_emisor_efectivo` mediante la migración `004`. La jurisdicción se modela estructuralmente en el catálogo `tipos_documento` mediante dos atributos canónicos:
1. `pais_fijo_id`: Clave foránea nullable hacia `paises(id)`. Si está definido, el tipo de documento posee jurisdicción fija no configurable (ej. DNI y CE fijados a Perú, ID 1). El servicio de dominio asigna automáticamente el país fijo si se omite y rechaza tajantemente cualquier emisión en otro país.
2. `pais_emisor_obligatorio`: Indicador booleano que señala si el tipo de documento requiere obligatoriamente indicar el país emisor (ej. PASAPORTE fijado en 1).

En consecuencia, `personas_documentos.pais_emisor_id` se define estrictamente como `INT UNSIGNED NOT NULL`, respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado bajo la restricción única natural `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)`. Queda prohibido el almacenamiento de jurisdicciones nulas o ficticias en la base de datos.

### D-032 — Invariante de solapamiento temporal de cargos en episodio (PERSONAL-1A)

Se formaliza la verificación estricta de no solapamiento temporal para todas las asignaciones de cargo dentro de un episodio laboral:
1. Ninguna asignación de cargo puede solaparse cronológicamente con otra del mismo episodio, aplicable tanto a intervalos abiertos como a intervalos cerrados (ej. Cargo A: 01/01 a 30/06 y Cargo B: 01/04 a 31/05 se rechaza tajantemente mediante `SolapamientoLaboralExcepcion`).
2. Las asignaciones de cargo están circunscritas a los límites del episodio laboral padre: no pueden iniciar antes de la apertura del episodio ni iniciar/finalizar después de su cese.
3. Se mantiene la convención $D-1$ / $D$ en transiciones de cargo con `cambiarCargo()`.
4. La verificación se centraliza en `ColaboradorServicio::validarSolapamientoAsignacionCargo()` y se protege concurrentemente mediante transacciones y bloqueos pesimistas (`SELECT ... FOR UPDATE`).

### D-033 — Vinculación directa 1:1 entre Usuario y Persona (AUTH-1)

Se establece formalmente que la cuenta de usuario humano (`usuarios`) se vincula de manera directa, unívoca y obligatoria con el núcleo humano (`personas`), mediante la clave foránea `usuarios.persona_id UNIQUE NOT NULL REFERENCES personas(id)`.
1. Una persona puede tener a lo sumo una cuenta de usuario en el sistema.
2. Queda tajantemente prohibido vincular usuarios a colaboradores (`colaborador_id`), cargos, clientes o huéspedes; la condición laboral es un rol de dominio que emana de `personas` a través de `colaboradores` y sus episodios, no una identidad de acceso.
3. Se garantiza la reutilización futura del modelo de autenticación para cualquier persona que requiera acceso al sistema sin inventar colaboradores ficticios.

### D-034 — Separación de actores en autenticación y auditoría (AUTH-1)

Se ratifica la frontera arquitectónica de actores técnicos:
1. `USER`: Cuenta humana autenticada (`usuarios`), vinculada 1:1 a una `persona`.
2. `SYSTEM`: Procesos batch, scripts de mantenimiento por CLI y cron jobs internos sin intervención interactiva humana.
3. `INTEGRATION`: Clientes técnicos y APIs externas (WordPress, aplicaciones móviles, pasarelas) autenticados mediante tokens de máquina o credenciales API rotables, nunca mediante cuentas humanas.
4. `PAYMENT_PROVIDER`: Proveedores de pago y webhooks externos.

### D-035 — Nombres de usuario canónicos y unicidad insensible a mayúsculas (AUTH-1)

Se define un doble almacenamiento canónico para el identificador de inicio de sesión:
1. `nombre_usuario VARCHAR(50) NOT NULL`: Almacena el nombre de usuario con el formato de presentación provisto.
2. `nombre_usuario_normalizado VARCHAR(50) NOT NULL UNIQUE`: Almacena el identificador transformado estrictamente a minúsculas y sin espacios mediante `UsuarioServicio::normalizarNombreUsuario()`.
3. Esto garantiza que nombres como `Admin.Bootstrap` y `admin.bootstrap` colisionen a nivel de base de datos impidiendo suplantaciones y ambigüedades.

### D-036 — Política de contraseñas robusta y hashing bcrypt con rehash (AUTH-1)

1. Longitud mínima de contraseña obligatoria de 10 caracteres, sin límites máximos arbitrariamente bajos (máximo 128 caracteres para mitigar denegación de servicio en hashing).
2. Hashing criptográfico unidireccional obligatorio con `PASSWORD_BCRYPT` y factor de costo fijado en 12 (`['cost' => 12]`).
3. Detección y migración transparente de costo (`password_needs_rehash()`) durante inicios de sesión exitosos si la política o configuración del sistema evoluciona.
4. Prohibición absoluta de almacenar o loguear contraseñas en texto claro. La columna en base de datos se denomina estrictamente `contrasena_hash CHAR(60) NOT NULL`.

### D-037 — Mitigación de timing attacks y enumeración de usuarios (AUTH-1)

1. En caso de que un usuario no exista en el sistema durante el intento de inicio de sesión, el servicio ejecuta una verificación matemática ficticia (`password_verify()`) contra un hash bcrypt dummy precalculado (`$2y$10$abcdefghijklmnopqrstuu...`), equiparando el tiempo de respuesta con el de un usuario existente.
2. El mensaje devuelto ante credenciales erróneas o cuentas inactivas es siempre indistinguible y uniforme: `'Credenciales de acceso inválidas.'`, impidiendo la enumeración de nombres de usuario.

### D-038 — Sesiones de usuario persistidas con tokens opacos en base de datos (AUTH-1)

1. Las sesiones de usuario web se gestionan en base de datos mediante la tabla `sesiones_usuario`.
2. El token de sesión emitido al cliente es una cadena opaca de 64 caracteres hexadecimales (32 bytes criptográficamente seguros generados con `random_bytes(32)`).
3. En la base de datos únicamente se almacena el hash criptográfico `token_hash CHAR(64) NOT NULL UNIQUE` computado mediante `hash('sha256', $token)`. Si la base de datos es comprometida, los tokens activos no pueden ser reconstruidos ni utilizados.

### D-039 — Política de expiración dual de sesiones: inactividad y duración máxima (AUTH-1)

Las sesiones de usuario están sujetas a dos ventanas de vencimiento no prorrogables:
1. Vencimiento por inactividad: Sesiones inactivas durante más de 30 minutos (`SESION_INACTIVIDAD_MINUTOS=30`, controlado por `ultimo_acceso_en`) son rechazadas y revocadas automáticamente.
2. Vencimiento por duración absoluta: Sesiones con una vida mayor a 12 horas desde su creación (`SESION_DURACION_MAXIMA_HORAS=12`, controlado por `creado_en`) expiran de forma inapelable y son revocadas.

### D-040 — Revocación concurrente de sesiones ante eventos de seguridad (AUTH-1)

1. Al realizar un cambio exitoso de contraseña, todas las demás sesiones activas del usuario son revocadas en base de datos (`revocada_en = NOW()`), preservando únicamente la sesión actual que ejecutó el cambio.
2. La desactivación o bloqueo de un usuario o de su persona asociada invalida de inmediato la validez de todas sus sesiones activas en el middleware de autenticación.

### D-041 — Protección CSRF estricta en operaciones mutables de autenticación (AUTH-1)

1. Todos los formularios con métodos HTTP sensibles (`POST`, `PUT`, `DELETE`) deben incluir un campo oculto `_csrf_token`.
2. Los tokens CSRF se almacenan en la sesión PHP del usuario y son validados mediante comparación de tiempo constante con `hash_equals()`.
3. Peticiones sin token o con token alterado se rechazan inmediatamente mediante `CsrfInvalidoExcepcion`.

### D-042 — Configuración de cookies de sesión defensivas (AUTH-1)

La cookie de sesión PHP se configura obligatoriamente con atributos de seguridad:
1. `HttpOnly = true`: Impide el acceso a la cookie desde scripts del lado del cliente (mitigación XSS).
2. `SameSite = 'Lax'` (o `'Strict'` según contexto): Mitiga ataques de falsificación de peticiones en sitios cruzados (CSRF).
3. `Secure = true` en entornos donde HTTPS está habilitado.
4. `use_strict_mode = 1` y `use_only_cookies = 1`.

### D-043 — Mitigación contra fijación de sesión (AUTH-1)

Al completarse una autenticación exitosa, el sistema ejecuta obligatoriamente `session_regenerate_id(true)`, destruyendo el identificador de sesión PHP anterior y asignando uno nuevo antes de registrar la identidad del usuario en la sesión.

### D-044 — Mitigación de redirección abierta (Open Redirect) (AUTH-1)

El parámetro de ruta de retorno (`return`) en el flujo de inicio de sesión se sanitiza de forma defensiva mediante `AutenticacionIntermediario::sanitizarRutaRetorno()`:
1. Se rechazan o neutralizan esquemas absolutos (ej. `http://`, `https://`, `javascript:`).
2. Se neutralizan URLs relativas al protocolo que comiencen con doble barra (`//`).
3. Se neutralizan rutas que contengan caracteres de control o saltos de línea (CR/LF).
4. Si la ruta no comienza con una única barra `/` o es insegura, se redirecciona de manera predeterminada a `/`.

### D-045 — Rate limiting y control de fuerza bruta mediante intentos de autenticación (AUTH-1)

1. Los intentos de inicio de sesión fallidos se registran en la tabla `intentos_autenticacion` almacenando la IP del cliente, el identificador normalizado, la fecha y el resultado.
2. Si se registran 5 o más intentos fallidos en una ventana móvil de 15 minutos para una misma IP o identificador, el servicio deniega temporalmente el intento con `DemasiadosIntentosExcepcion`.
3. El throttling temporal no modifica el estado permanente del usuario (`usuarios.estado` permanece `ACTIVO`), evitando ataques de denegación de servicio distribuidos dirigidos a bloquear cuentas legítimas.

### D-046 — Bootstrap CLI seguro e idempotente para usuario inicial (AUTH-1)

1. Se provee la utilidad de línea de comandos `bin/crear-usuario-inicial.php` restringida estrictamente a entornos CLI (`PHP_SAPI === 'cli'`).
2. Si el sistema ya cuenta con al menos un usuario registrado en base de datos, el script rechaza la ejecución a menos que se invoque con el flag explícito `--forzar`.
3. Si la persona asociada no existe, el script la crea de forma atómica en el núcleo de personas con su correspondiente documento de identidad.
4. Por motivos de seguridad y auditoría, el script jamás imprime la contraseña generada o asignada en la salida de la consola ni en archivos de log.

## Pendientes de decisión

| ID | Tema | Momento límite |
|---|---|---|
| P-003 | Framework de pruebas PHP/JS | Antes de pruebas automatizadas de dominio |
| P-004 | Estrategia de zona horaria y fecha hotelera | Antes de disponibilidad |
| P-005 | Moneda, redondeo e impuestos | Antes de tarifas/caja |
| P-006 | Estrategia de concurrencia para disponibilidad | Antes de reservas |
| P-007 | Librería PDF | Antes de contratos/recibos |
| P-008 | Proveedor inicial de pagos | Antes de integración de pagos |
| P-009 | Retención de datos y auditoría | Antes de producción |
