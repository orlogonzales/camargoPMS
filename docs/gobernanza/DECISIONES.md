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

### D-036 — Política de contraseñas robusta, hashing PASSWORD_DEFAULT y rehash (AUTH-1 / AUTH-1A)

1. Política de contraseñas: longitud mínima de 12 caracteres y máxima de 1024 caracteres evaluada estrictamente antes del hash. Sin reglas artificiales de composición obligatoria y sin transformaciones destructivas (`trim`, `lowercase`, truncamiento); espacios internos, externos y caracteres Unicode son parte integral de la clave.
2. Hashing criptográfico unidireccional estándar mediante `password_hash($contrasena, PASSWORD_DEFAULT)` y verificación nativa con `password_verify()`. No se fija contractualmente un algoritmo rígido (como bcrypt cost=12) en el dominio, permitiendo que PHP evolucione su algoritmo por defecto de manera nativa.
3. Mecanismo de migración y evolución transparente vía `password_needs_rehash($hash, PASSWORD_DEFAULT)` durante inicios de sesión exitosos, actualizando hashes antiguos de forma no disruptiva.
4. Almacenamiento seguro extensible: la columna en base de datos se modela como `contrasena_hash VARCHAR(255) NOT NULL`, garantizando espacio suficiente para soportar `PASSWORD_DEFAULT` y futuros algoritmos criptográficos sin necesidad de migraciones de DDL adicionales. Prohibición absoluta de almacenar o registrar contraseñas en texto claro.

### D-037 — Mitigación de timing attacks y enumeración de usuarios (AUTH-1 / AUTH-1A)

1. En caso de que un usuario no exista en el sistema durante el intento de inicio de sesión, el servicio ejecuta una verificación matemática ficticia (`password_verify()`) contra un hash dummy precalculado dinámicamente con `PASSWORD_DEFAULT`, equiparando el tiempo de respuesta con el de un usuario existente sin introducir discrepancias algorítmicas.
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

### D-046 — Bootstrap CLI estrictamente de uso único para cuenta inicial (AUTH-1 / AUTH-1A)

1. Se provee la utilidad de línea de comandos `bin/crear-usuario-inicial.php` restringida estrictamente a entornos CLI (`PHP_SAPI === 'cli'`).
2. Contrato de uso único inmutable: si el sistema ya cuenta con al menos un usuario registrado en base de datos (`usuarios >= 1`), la ejecución es rechazada categóricamente sin excepciones ni banderas de bypass (se prohíbe `--forzar` o mecanismos similares). La administración posterior de cuentas se delega a las funciones administrativas normales del software bajo autorización adecuada.
3. Si la persona asociada no existe y la base de datos no tiene usuarios, el script la crea de forma atómica en el núcleo de personas con su correspondiente documento de identidad.
4. Por motivos de seguridad y auditoría, el script jamás imprime la contraseña generada o asignada en la salida de la consola ni en archivos de log.

### D-047 — Modelo de autorización RBAC puro con permisos atómicos aditivos (ROLES-1)

1. La autorización de backend se modela mediante control de acceso basado en roles (RBAC) estructurado en dos relaciones muchos a muchos:
   `Usuario <---> usuarios_roles <---> Rol <---> roles_permisos <---> Permiso`.
2. Los permisos son estrictamente atómicos y siguen la convención obligatoria de nomenclatura `recurso.accion` en minúsculas (ej. `usuarios.ver`, `usuarios.crear`, `roles.editar`).
3. Para roles ordinarios, los permisos son puramente aditivos: los permisos efectivos corresponden a la unión de todos los permisos asociados a los roles activos asignados al usuario.
4. Se prohíben sobreescrituras directas de permisos a nivel de usuario (`usuarios_permisos`), jerarquías de herencia de roles e inferencias de permisos a partir de cargos laborales.

### D-048 — Rol estructural protegido SUPERADMINISTRADOR con autoridad total (ROLES-1)

1. El rol con clave técnica `SUPERADMINISTRADOR` es un rol estructural protegido del sistema (`es_sistema = 1`, `es_superadministrador = 1`).
2. La autoridad del Superadministrador se reconoce de forma centralizada en `AutorizacionServicio`: si un usuario cuenta con dicho rol activo, su cuenta está activa y su persona está activa, el método `puede()` retorna `true` de manera incondicional e inmediata para cualquier recurso o acción presente o futura, sin necesidad de listar exhaustivamente permisos en `roles_permisos`.
3. El rol `SUPERADMINISTRADOR` está protegido: se prohíbe renombrar su clave técnica, desactivarlo (`estado = 'INACTIVO'`) o eliminarlo físicamente de la base de datos (`RolProtegidoExcepcion`).

### D-049 — Invariante del último Superadministrador activo con bloqueo pesimista (ROLES-1)

1. El sistema garantiza en todo momento la existencia de al menos un Superadministrador humano activo y facultado.
2. Se prohíbe categóricamente revocar el rol `SUPERADMINISTRADOR`, bloquear o inactivar al usuario, o desactivar la persona natural asociada, si se trata del único Superadministrador activo restante en el sistema (`UltimoSuperadministradorExcepcion`).
3. La verificación de la cantidad de Superadministradores activos se realiza dentro de transacciones de base de datos utilizando bloqueo pesimista de filas (`FOR UPDATE`) para prevenir condiciones de carrera concurrentes (race conditions).
4. No se utilizan identificadores estáticos hardcodeados (como `id = 1` o nombre de usuario `'orlando'`); la invariante protege a cualquier usuario que sea el último titular activo del rol.

### D-050 — Intermediario de autorización y respuesta HTTP 403 Forbidden segura (ROLES-1)

1. La protección de rutas que exigen autorización se implementa mediante el intermediario `AutorizacionIntermediario` parametrizado con el permiso atómico requerido (`$permisoRequerido`).
2. Si la petición no cuenta con una sesión autenticada activa, el intermediario redirige a `/login` preservando el parámetro de retorno.
3. Si el usuario está autenticado pero carece del permiso exigido, el intermediario interrumpe el procesamiento y retorna inmediatamente una respuesta con código de estado `HTTP 403 Forbidden`, renderizando la plantilla de error aislada de Alina (`Vistas/errores/error.php` con código 403) sin filtrar datos de negocio, consultas SQL, contraseñas ni trazas técnicas.

### D-051 — Asignación atómica de SUPERADMINISTRADOR en bootstrap CLI y migración determinista 007 (ROLES-1)

1. La migración `007_roles_permisos_autorizacion.sql` crea las tablas `roles`, `permisos`, `roles_permisos` y `usuarios_roles`, siembra el rol `SUPERADMINISTRADOR` y el catálogo de 10 permisos atómicos iniciales, e incluye una consulta de transición determinista que asocia automáticamente el rol al usuario existente solo si existe exactamente una cuenta registrada en el sistema.
2. El script CLI `bin/crear-usuario-inicial.php` asigna de forma atómica y transaccional el rol `SUPERADMINISTRADOR` a la primera cuenta humana creada.

### D-052 — Menú dinámico de dos niveles y principio MENÚ ≠ AUTORIZACIÓN (MENÚ-1)

1. Estructura jerárquica estricta de 2 niveles:
   - Nivel 1: Categorías principales (`padre_id IS NULL`), representadas por los iconos superiores en la cabecera horizontal de Alina (`navbar-menu-list` con atributo `data-target="clave"`).
   - Nivel 2: Opciones secundarias (`padre_id IS NOT NULL`), agrupadas en paneles verticales del submenú lateral (`main-side-menu` con `id="clave"`).
   - Se prohíbe anidamiento de más de dos niveles (`NivelMenuInvalidoExcepcion`), la autorreferencia y el uso de opciones secundarias como padres de otras secundarias.
2. Principio fundamental "MENÚ ≠ AUTORIZACIÓN":
   - El menú es una guía visual de navegación y experiencia de usuario; jamás actúa como frontera o mecanismo de seguridad.
   - La visibilidad de un elemento de menú está condicionada a los permisos RBAC (`AutorizacionServicio::puede()` o rol `SUPERADMINISTRADOR`), pero la seguridad de los recursos y endpoints está garantizada independientemente en el backend mediante intermediarios (`AutorizacionIntermediario`) y comprobaciones directas en controladores y servicios.
   - Regla de visibilidad de categorías principales: una categoría solo se renderiza si está activa, autorizada (si tiene permiso asignado) y cuenta con al menos una opción secundaria visible para el usuario actual. Las categorías vacías se omiten automáticamente para evitar contenedores muertos.
3. Administración transaccional y protección estructural:
   - Se provee una interfaz administrativa bajo `/configuracion/menu` gobernada por los permisos `menu.ver` y `menu.gestionar`.
   - Elementos estructurales (`es_sistema = 1`) no pueden ser eliminados ni desactivados (`OpcionMenuProtegidaExcepcion`) para garantizar la persistencia del acceso administrativo al sistema.
   - El reordenamiento de opciones dentro de una misma categoría o entre categorías principales se ejecuta de forma atómica y transaccional mediante arrays de pares `[id, orden]`, rechazando mezclas de padres o niveles con rollback total ante fallos.
   - Las rutas configuradas deben ser rutas internas relativas; se prohíben URLs externas o esquemas maliciosos (`javascript:`, `data:`, `vbscript:`).

### D-053 — Modelo relacional y principio ACTOR ≠ USUARIO en Auditoría (AUDITORÍA-1)

1. Se establece el principio arquitectónico vinculante `ACTOR ≠ USUARIO`:
   - Un **Actor** representa el sujeto o entidad que ejecuta una operación en el sistema. Soporta cuatro tipos ontológicos estrictos (`TipoActor`): `USUARIO` (humano autenticado), `SISTEMA` (tareas en segundo plano, cron jobs o rutinas internas del PMS), `INTEGRACION` (clientes técnicos de API, ej. WordPress o app móvil) y `PROVEEDOR_PAGO` (webhooks seguros de pasarelas de pago).
   - Un **Usuario** representa exclusivamente una cuenta de acceso humano con contraseñas seguras y sesiones. Se prohíbe crear usuarios humanos ficticios para representar procesos de sistema o clientes técnicos de API.
2. La tabla `actores` desacopla la autoría del concepto exclusivo de usuario:
   - Contiene `tipo`, `codigo` único, `nombre`, `usuario_id` nullable y `metadatos` en JSON.
   - Todo usuario humano registrado posee un actor asociado de tipo `USUARIO` mediante `usuario_id` único (`fk_actores_usuario` con `ON DELETE SET NULL ON UPDATE CASCADE`), generado deterministamente (`USR_{id}`).
   - La tabla `auditoria` referencia obligatoriamente `actor_id` (`ON DELETE RESTRICT`) y opcionalmente `usuario_id` (`ON DELETE SET NULL`), garantizando que la eliminación o desactivación de un usuario jamás destruya ni degrade la autoría histórica de los registros de auditoría.
3. Se siembra el actor estructural protegido `CAMARGO_PMS` (`id = 1`, `tipo = 'SISTEMA'`, `codigo = 'CAMARGO_PMS'`).
4. Política vinculante sobre el ciclo de vida de usuarios e integridad referencial:
   - En las operaciones ordinarias de negocio, las cuentas de usuario jamás se eliminan físicamente de la base de datos (`DELETE FROM usuarios` está prohibido a nivel de servicio y repositorio; no se exponen métodos destructivos en `UsuarioServicio` ni `UsuarioRepositorio`).
   - El ciclo de vida de las cuentas humanas se gestiona exclusivamente mediante transiciones de estado explícitas: `ACTIVO`, `INACTIVO` y `BLOQUEADO`.
   - Ante cualquier depuración física técnica excepcional a bajo nivel, la integridad referencial y de auditoría está 100% blindada por diseño DDL: `actores.usuario_id` pasa a `NULL` (el actor humano sobrevive intacto) y `auditoria.usuario_id` pasa a `NULL` (el registro inmutable de auditoría sobrevive intacto vinculado al actor), mientras que `auditoria.actor_id` (`ON DELETE RESTRICT`) prohíbe de forma absoluta cualquier eliminación de actores con histórico.

### D-054 — Inmutabilidad estricta del registro de auditoría y persistencia defensiva (AUDITORÍA-1)

1. El registro de auditoría es estrictamente inmutable y de solo anexado (append-only):
   - `AuditoriaRepositorio` expone exclusivamente métodos de inserción (`insertar()`) y lectura (`buscarPorId()`, `listar()`, `contar()`), careciendo deliberadamente de métodos `actualizar()` o `eliminar()`.
   - Se prohíben modificaciones o eliminaciones físicas de filas en `auditoria` en tiempo de ejecución.
2. Persistencia en operaciones críticas vs. no críticas:
   - En mutaciones críticas de dominio (ej. asignación/revocación de roles, reordenamiento o mutación de menú, creación de usuarios), el registro de auditoría se ejecuta dentro de la misma transacción de base de datos (`COMMIT` conjunto o `ROLLBACK` total ante fallos).
   - En eventos de infraestructura o no críticos (ej. login, logout), el servicio aplica persistencia defensiva de forma que un fallo secundario de auditoría no bloquee el flujo principal del usuario, pero se registre con severidad.

### D-055 — Sanitización recursiva de secretos y sanitizador transversal (AUDITORÍA-1)

1. Se prohíbe terminantemente la presencia de credenciales, tokens, secretos o datos financieros en el registro de auditoría.
2. Se implementa el servicio transversal `SanitizadorAuditoria`:
   - Aplica purga recursiva sobre cualquier estructura de datos (`valores_anteriores`, `valores_nuevos`, `metadatos`).
   - Elimina o redacta (`[REDACTADO]`) campos sensibles: `password`, `contrasena`, `contraseña`, `password_hash`, `contrasena_hash`, `clave`, `csrf`, `csrf_token`, `authorization`, `cookie`, `session`, `session_id`, `token`, `api_key`, `secret`, `client_secret`, `tarjeta`, `cvv`, `pin`, etc., con normalización multibyte insensible a mayúsculas/minúsculas (`mb_strtolower(..., 'UTF-8')`) e independientemente del nivel de anidamiento en estructuras JSON o arreglos.
3. La base de datos de auditoría no debe contener contraseñas planas ni hashes de contraseñas de usuarios.

### D-056 — Trazabilidad contextual y correlación de peticiones (AUDITORÍA-1)

1. Cada registro de auditoría captura metadatos contextuales estandarizados:
   - `metodo_http`, `ruta`, `ip` del cliente y `user_agent`.
   - `correlacion_id`: identificador único de trazabilidad de la petición (UUIDv4 o token CSPRNG de 32 hex chars), preservado a lo largo del ciclo de vida de la solicitud.
2. Integración nativa en los servicios de dominio existentes:
   - `AutenticacionServicio`: audita eventos de `LOGIN` y `LOGOUT`.
   - `RolServicio`: audita asignación (`ASIGNAR`) y revocación (`REVOCAR`) atómica de roles.
   - `MenuServicio`: audita `CREAR`, `EDITAR`, `ACTIVAR`, `DESACTIVAR`, `REORDENAR` y `ELIMINAR` de opciones de menú.
   - `UsuarioServicio`: audita `CREAR`, `CAMBIAR_ESTADO` y `CAMBIAR_CLAVE` de cuentas de usuario.

### D-057 — Administración de cuentas humanas y segregación conceptual (USUARIOS-1)

1. Principio rector de identidad y acceso:
   - Se mantiene la estricta separación ontológica: `PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL` y `ACTOR ≠ USUARIO`.
   - Las cuentas de usuario en `usuarios` representan exclusivamente identidades humanas interactivas vinculadas a una persona natural real existente y activa en la tabla `personas`.
   - Se prohíbe tajantemente la creación de cuentas de usuario para procesos desatendidos, WordPress, APIs externas, webhooks, aplicaciones móviles o proveedores de pago; dichos entes se modelan exclusivamente como actores técnicos independientes en la tabla `actores`.
2. Cardinalidad y vinculación:
   - Se impone unicidad 1:1 estricta entre `Persona` y `Usuario`: una persona natural puede poseer a lo sumo una cuenta de usuario en el software.
   - El formulario de alta requiere la selección de una persona existente no vinculada previamente; no se permite la creación anónima de cuentas.

### D-058 — Ciclo de vida de usuarios, no-eliminación física e invariante de Superadministrador (USUARIOS-1)

1. Transición de estados de cuenta:
   - Los únicos estados válidos son `ACTIVO`, `INACTIVO` y `BLOQUEADO`.
   - Se prohíbe categóricamente el `DELETE` físico de usuarios en la capa de negocio; la revocación de acceso se materializa exclusivamente mediante desactivación o bloqueo.
   - Toda transición a un estado no activo (`INACTIVO` o `BLOQUEADO`) revoca de forma atómica e inmediata todas las sesiones abiertas del usuario en la tabla `sesiones_usuario` fijando `revocada_en = NOW()` y `motivo_cierre = 'CAMBIO_ESTADO_USUARIO'`.
2. Invariante estructural del último Superadministrador activo:
   - El sistema garantiza que siempre debe existir al menos un Superadministrador activo (`≥ 1 SUPERADMINISTRADOR activo`).
   - Se prohíbe expresamente que el último Superadministrador activo restante pase a estado `INACTIVO`, pase a estado `BLOQUEADO` o sea despojado de su rol de Superadministrador.
   - Dicha verificación se ejecuta a nivel de servicio mediante consultas transaccionales con bloqueo pesimista (`FOR UPDATE`), previniendo condiciones de carrera concurrentes y lanzando `UltimoSuperadministradorExcepcion` ante cualquier intento.

### D-059 — Restablecimiento administrativo de contraseñas y gestión de sesiones (USUARIOS-1)

1. Restablecimiento administrativo de credenciales:
   - La funcionalidad administrativa permite asignar una nueva contraseña sin requerir la contraseña actual del usuario auditado.
   - Se valida rigurosamente la política canónica de contraseñas: longitud mínima de 12 caracteres, máxima de 1024 caracteres, preservación de espacios y caracteres Unicode sin trim ni transformaciones destructivas.
   - Se genera el hash criptográfico mediante `PASSWORD_DEFAULT`.
   - La operación revoca todas las sesiones activas del usuario con motivo `CAMBIO_CONTRASENA`.
   - El registro de auditoría (`CAMBIAR_CLAVE`) es sometido a purga recursiva, garantizando que ni la contraseña en claro ni el hash criptográfico queden registrados en `auditoria`.
2. Gestión administrativa de sesiones:
   - Se provee la consulta y revocación controlada de sesiones activas (individual o masiva) a través de `SesionServicio` y `UsuarioServicio`.
   - Las respuestas de la API hacia la interfaz jamás exponen tokens en texto plano, identificadores de cookies ni hashes SHA-256 de las sesiones.
   - La revocación administrativa se registra con motivo `REVOCACION_ADMINISTRATIVA` y audita el evento `CERRAR_SESION`.

### D-060 — Interfaz administrativa Alina, validación client-side con PristineJS y backend canónico (USUARIOS-1)

1. Experiencia de usuario e interfaz:
   - La gestión de usuarios se integra fluidamente en el diseño Alina bajo la ruta `/usuarios`, conservando la estructura de maquetación, paleta de colores y componentes visuales estándar.
   - La comunicación asíncrona se ejecuta mediante la API nativa Fetch y contratos JSON uniformes (`ok`, `mensaje`, `datos`, `errores`), utilizando SweetAlert2 para diálogos y notificaciones de éxito/error. Cero dependencias de jQuery.
2. Validación desacoplada:
   - Los formularios interactivos en modales (`modal-crear-usuario`, `modal-restablecer-clave`) implementan validación en el cliente mediante PristineJS local v1.1.0, bloqueando envíos HTTP inválidos y limpiando errores en eventos `hidden.bs.modal`.
   - El backend opera como autoridad canónica estricta: todos los datos son revalidados en los controladores y servicios, retornando códigos HTTP semánticos (400, 403, 404, 409, 422, 500) y validando obligatoriamente tokens CSRF en todas las operaciones de mutación.

### D-061 — Resolución canónica de identidad del actor en auditoría y prevención de colisión de identificadores (USUARIOS-1A)

1. Principio vinculante de desacople de identidad (`ACTOR ≠ USUARIO`):
   - Los identificadores de cuenta humana (`usuarios.id`) y los identificadores de actor de trazabilidad (`actores.id`) habitan dominios ontológicos distintos y no comparten equivalencia numérica.
   - Se prohíbe terminantemente suministrar identificadores primarios de la tabla `usuarios` como enteros planos en el parámetro `$actor` de `AuditoriaServicio::registrar()`, dado que dicho servicio interpreta los enteros escalares exclusivamente como claves primarias de la tabla `actores` (`actores.id`).
2. Mecanismo canónico de resolución en capas de servicio:
   - Todo servicio de dominio que gestione mutaciones administrativas con usuario ejecutor conocido (`$ejecutadoPorUsuarioId`, `$creadoPorUsuarioId`, `$asignadoPor`, `$revocadoPor`) debe resolver explícitamente la instancia correspondiente de `ActorAuditoria` invocando `$this->auditoriaServicio->obtenerOAsegurarActorUsuario($usuarioId, $this->pdo)` a través de un resolvedor centralizado (`resolverActorEjecutor()`).
   - Dicha invocación garantiza que:
     - Si ya existe un actor vinculado (`actores.usuario_id = $usuarioId`), se recupera de inmediato su instancia.
     - Si el usuario aún no posee un actor en la base de datos, se crea y persiste automáticamente un nuevo actor con tipo `USUARIO`, código canónico `USR_{id}` y nombre del usuario, preservando la relación 1:1 sin duplicados.
     - Se previene la generación de excepciones críticas de tiempo de ejecución (`ActorNoEncontradoExcepcion` -> HTTP 500) ante IDs de usuario que no coincidan con claves primarias de `actores`.
     - Se erradica la colisión accidental de autoría en el usuario inicial (`usuario_id = 1`), asegurando que sus acciones queden correctamente imputadas a su actor humano `USR_1` (`actores.id = 2`) y no al actor estructural del sistema `CAMARGO_PMS` (`actores.id = 1`).
   - Cuando la operación no cuente con un usuario ejecutor explícito (procesos en segundo plano, tareas programadas, CLI o ejecuciones sin contexto), el actor se resuelve defensivamente hacia el contexto actual de sesión o hacia el actor de sistema `CAMARGO_PMS` (`tipo = 'SISTEMA'`).

### D-062 — Administración visual de Roles, sincronización matricial de permisos y protección estructural (ROLES-2)

1. Principio de autorización en tiempo real (`ROL DE AUTORIZACIÓN ≠ CARGO LABORAL`):
   - La administración de roles y permisos bajo `/configuracion/roles` opera como fuente canónica de autorización RBAC del sistema.
   - Toda alteración en la asignación matricial de permisos de un rol entra en vigencia de forma inmediata en las consultas de `AutorizacionServicio::puede()` y `obtenerPermisosEfectivos()` para los usuarios activos sin requerir re-login ni revocación de sesiones.
2. Protección estructural de `SUPERADMINISTRADOR` y preservación histórica de roles (ROLES-2A):
   - El rol estructural `SUPERADMINISTRADOR` es inmutable en su clave técnica, no puede ser desactivado (`estado = INACTIVO`) ni mutado (`RolProtegidoExcepcion`).
   - La sincronización matricial de permisos prohíbe taxativamente revocar los permisos críticos de administración del sistema (`roles.ver`, `roles.editar`, `permisos.ver`, `usuarios.ver`, `usuarios.editar`), lanzando `RolProtegidoExcepcion`.
   - Se mantiene el invariante pesimista del último Superadministrador humano activo (`>= 1` superadmin humano activo con persona activa), prohibiendo su revocación o desactivación.
   - Roles con `es_sistema = 1` impiden modificación de su clave técnica.
   - **Principio `ROL ≠ REGISTRO DESECHABLE` (ROLES-2A)**: Los roles no se eliminan físicamente durante la operación ordinaria. Su ciclo de vida se administra exclusivamente mediante la alternancia operativa `ACTIVO` / `INACTIVO`. Se retiraron todas las capacidades de DELETE físico de la UI, HTTP, Controlador y Servicio para asegurar la conservación de trazabilidad histórica (`usuarios_roles`, `roles_permisos`, `auditoria`).
3. Sincronización atómica y trazabilidad unificada bajo D-061:
   - Las mutaciones matriciales de permisos se procesan de forma atómica dentro de una transacción de base de datos (`beginTransaction` / `commit` / `rollBack`).
   - Cada permiso agregado genera un evento de auditoría `ASIGNAR` y cada permiso retirado genera `REVOCAR`, agrupados bajo un identificador de correlación unificado (`correlacion_id`) y con autoría humana resuelta conforme a D-061 (`resolverActorEjecutor()`).
   - La alternancia de estado operacional registra auditoría con acciones normalizadas `DESACTIVAR` y `ACTIVAR`, conservando autoría y correlación.

### D-063 — Núcleo Central de Configuración, tipado funcional y separación estricta de entorno (CONFIGURACIÓN-1)

1. Principio de separación `CONFIGURACIÓN FUNCIONAL (BD) ≠ ENTORNO TÉCNICO (.env)`:
   - Los parámetros funcionales de operación administrativa del PMS residen en la base de datos (`configuraciones`), auditados y gobernados por RBAC.
   - Las variables de entorno de bajo nivel, secretos de infraestructura y credenciales de servicios permanecen exclusivamente en `.env`, gestionadas por administradores de sistemas y nunca mutables desde la UI del PMS.
   - Parámetros sensibles en BD (`es_sensible = 1`) son ofuscados (`***`) en los registros de auditoría y salidas operativas.
2. Contrato de tipado canónico y acceso encapsulado:
   - Todo parámetro de configuración pertenece a un tipo de dato funcional fuertemente tipado (`TEXTO`, `ENTERO`, `DECIMAL`, `BOOLEANO`, `FECHA`, `HORA`, `JSON`) administrado por la enumeración `TipoConfiguracion`.
   - Se prohíbe el acceso directo mediante consultas SQL dispersas: todo consumo se canaliza estrictamente a través de `ConfiguracionServicio -> ConfiguracionRepositorio -> PDO -> MySQL`.
   - `ConfiguracionServicio::obtener()` retorna el valor casteado a su tipo nativo de PHP y aprovecha una caché de ciclo de vida de petición en memoria (`request-scoped`).
3. Inmutabilidad de parámetros del sistema y capacidad de restauración:
   - Parámetros marcados con `editable = 0` (como `sistema.version_instalada`) están estrictamente protegidos contra mutación tanto a nivel de backend (`ConfiguracionNoEditableExcepcion`) como en la interfaz de usuario.
   - Cada parámetro conserva su `valor_predeterminado` de fábrica, permitiendo restauración atómica (`restaurarPredeterminado()`).
   - La actualización múltiple o en lote (`actualizarMultiples()`) se ejecuta en una transacción atómica con validación previa estricta y reversión completa (`rollBack`) ante cualquier error.
   - Mutaciones auditadas bajo D-061 con imputación a actores humanos (`USR_x`) y `correlacion_id` unificado para lotes.
4. Preservación estricta de decisiones pendientes (P-004 y P-005):
   - Se mantiene la exclusión deliberada de semillas funcionales para zona horaria / corte hotelero (P-004) y moneda / redondeo / impuestos (P-005) hasta su resolución formal en las fases correspondientes.

### D-064 — Maestro Central de Propiedades, principio PROPIEDAD ≠ UNIDAD y preservación de ciclo de vida histórico (PROPIEDADES-1)

1. Principio ontológico de delimitación física (`PROPIEDAD ≠ UNIDAD`):
   - Una propiedad representa exclusiva y estrictamente el contenedor físico, edificación o inmueble raíz (ej. "Edificio Ayuda Mutua", "Casona Principal", "Villas del Valle").
   - Bajo ninguna circunstancia una propiedad modela unidades arrendables, habitaciones, departamentos, camas o inventario comercializable. La modelación de unidades y tipologías de alojamiento queda estrictamente reservada para la fase subsiguiente `UNIDADES-1`.
   - Se prohíbe introducir atributos o relaciones prematuras de disponibilidad, tarifas, estancias, reservas, bloqueos o amenidades específicas de habitación dentro de la entidad `Propiedad`.
2. Principio de preservación histórica (`PROPIEDAD ≠ REGISTRO DESECHABLE`):
   - Las propiedades físicas constituyen la raíz de la jerarquía inmobiliaria del PMS. No se admite eliminación física (`DELETE FROM propiedades`) bajo ningún escenario de la operativa del sistema.
   - El ciclo de vida de una propiedad se gestiona exclusivamente a través de los estados operativos `ACTIVO` e `INACTIVO` mediante `cambiarEstado()`.
   - Se prohíbe la creación de métodos o endpoints destructivos de eliminación (`eliminar()`, `DELETE /propiedades/{id}`). Las solicitudes HTTP DELETE responden estrictamente `404 Not Found`.
   - Propiedades inactivas persisten íntegras en base de datos conservando su trazabilidad histórica, auditoría previa y referencias estructurales.
3. Catálogo geográfico y georreferenciación defensiva:
   - Toda propiedad física requiere obligatoriamente una dirección física no vacía (`direccion VARCHAR(255) NOT NULL`) y vinculación a un país válido registrado en el catálogo (`paises.id`).
   - Las coordenadas geográficas (`latitud` y `longitud`) son opcionales con validación estricta de rango numérico: latitud en `[-90.0, 90.0]` y longitud en `[-180.0, 180.0]` almacenadas en tipo `DECIMAL(10, 8)` y `DECIMAL(11, 8)` respectivamente.
4. Trazabilidad integral y auditoría bajo D-061:
   - Toda mutación (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) es auditada de forma transversal en la tabla `auditoria`.
   - Conforme a D-061, se resuelve explícitamente el actor ejecutor humano (`USR_x`) a partir del usuario en sesión, reservando el actor estructural `CAMARGO_PMS` (`id = 1`) únicamente para ejecuciones desatendidas o de sistema.
   - En actualizaciones (`EDITAR`), se calcula el diferencial exacto de atributos mutados y se suprime la emisión de eventos de auditoría redundantes cuando no existen cambios funcionales.
5. Preservación estricta de decisiones pendientes (P-004, P-005 y P-006):
   - Se mantiene la exclusión deliberada de zonas horarias de propiedad, fechas de corte hotelero (P-004), monedas/tarifas asociadas (P-005) y reglas de concurrencia de disponibilidad (P-006) hasta sus respectivas fases autorizadas.

### D-065 — Maestro Central de Unidades Físicas, tipología arquitectónica y unicidad de código por propiedad (UNIDADES-1)

1. Principio ontológico de delimitación física (`PROPIEDAD ≠ UNIDAD`):
   - La propiedad (`propiedades`) actúa exclusivamente como el inmueble, edificación o contenedor físico raíz.
   - La unidad (`unidades`) modela la división física habitable, arrendable o alojable (ej. "Dpto 101", "Habitación 204", "Bungalow B", "Suite Presidencial") subordinada a su propiedad física (`propiedad_id NOT NULL`).
   - Una unidad física no puede existir de forma huérfana en el sistema; toda unidad pertenece estrictamente a una propiedad física existente.
   - Regla de negocio vinculante: Se prohíbe la creación de nuevas unidades en propiedades que se encuentren en estado `INACTIVO`. Una propiedad inactiva preserva íntegramente sus unidades históricas para fines de trazabilidad y consulta, pero no admite el registro de inventario físico adicional.

2. Principio de preservación histórica (`UNIDAD ≠ REGISTRO DESECHABLE`):
   - Las unidades físicas constituyen el inventario habitable estructural del PMS. No se admite eliminación física (`DELETE FROM unidades`) bajo ningún escenario de la operativa del sistema.
   - El ciclo de vida de una unidad se gestiona exclusivamente a través de los estados operativos `ACTIVO` e `INACTIVO` mediante el método `cambiarEstado()`.
   - Se prohíbe la creación de métodos o endpoints destructivos de eliminación (`eliminar()`, `DELETE /unidades/{id}`). Las solicitudes HTTP DELETE responden estrictamente `404 Not Found`.
   - Las unidades inactivas persisten íntegras en base de datos conservando sus especificaciones físicas, auditoría previa y vínculos estructurales con su propiedad.

3. Unicidad de código por propiedad (`UNIQUE(propiedad_id, codigo)`):
   - La unicidad del código técnico de una unidad (`codigo`) tiene alcance local por propiedad (`uq_unidades_propiedad_codigo`), no global a nivel de todo el PMS.
   - Es admisible y válido que propiedades físicas independientes contengan unidades con el mismo identificador o código (ej. "Dpto 101" en la propiedad A y "Dpto 101" en la propiedad B).
   - Se rechaza estrictamente cualquier intento de registrar o renombrar una unidad con un código que ya exista dentro de la misma propiedad (`UnidadDuplicadaExcepcion`, HTTP 409).

4. Catálogo de Tipologías Arquitectónicas (`tipos_unidad`):
   - Las unidades se tipifican a través de un catálogo maestro estructurado (`tipos_unidad`) con semillas arquitectónicas iniciales: `DEPARTAMENTO`, `HABITACION`, `CASA`, `SUITE` y `BUNGALOW`.
   - Cada tipo de unidad cuenta con código técnico inmutable, nombre descriptivo y estado (`ACTIVO` / `INACTIVO`).
   - Toda unidad valida obligatoriamente que su `tipo_unidad_id` corresponda a un tipo activo y existente.

5. Especificaciones físicas y validaciones numéricas:
   - Capacidad física de personas (`capacidad_personas`): entero positivo mayor o igual a 1 (máximo 100).
   - Dormitorios (`dormitorios`): entero no negativo mayor o igual a 0 (admitiendo 0 para tipologías monoambiente o tipo estudio).
   - Baños (`banos`): decimal positivo o no negativo con hasta 1 decimal (ej. 1.0, 1.5, 2.0 baños).
   - Área construida (`area_m2`): decimal opcional con hasta 2 decimales mayor a 0 si es especificada.
   - Piso / Nivel (`piso_nivel`): cadena alfanumérica opcional de hasta 30 caracteres (admitiendo "PB", "Sótano", "Azotea", "Piso 3").

6. Trazabilidad integral y auditoría bajo D-061:
   - Toda mutación de unidades (`CREAR`, `EDITAR`, `DESACTIVAR`, `ACTIVAR`) es auditada de forma transversal en la tabla `auditoria`.
   - Conforme a D-061, se resuelve explícitamente el actor ejecutor humano (`USR_x`) a partir del usuario en sesión, reservando el actor estructural `CAMARGO_PMS` (`id = 1`) únicamente para ejecuciones de sistema o CLI.
   - En actualizaciones (`EDITAR`), se calcula el diferencial exacto de atributos mutados ignorando timestamps y metadatos relacionales, suprimiendo la emisión de eventos de auditoría redundantes cuando no existen cambios funcionales reales.

7. Preservación estricta de decisiones pendientes (P-004, P-005 y P-006):
   - Principio: `UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD`.
   - Se mantiene la exclusión deliberada de calendarios, bloqueos, tarifas nocturnas, precios, monedas (P-005), cortes hoteleros (P-004) y concurrencia de disponibilidad (P-006) hasta sus respectivas fases autorizadas.

### D-066 — Modelo Temporal Hotelero, Zonas Horarias IANA y Definición del Día Hotelero (cierra P-004)

1. Separación ontológica tripartita:
   - `INSTANTE ≠ FECHA HOTELERA ≠ HORARIO OPERACIONAL`.
   - **Instante técnico (`TIMESTAMP` / `DATETIME` UTC):** Representa un punto exacto e inequívoco en la línea de tiempo universal (ej. creación de registros, tokens, sesiones, eventos de auditoría, webhooks). Se almacena normalizado en UTC.
   - **Fecha hotelera (`DATE` local):** Representa la noche de ocupación o venta en la localidad geográfica donde se sitúa el inmueble físico (ej. `2026-11-10`). No es un instante ni debe convertirse indiscriminadamente a UTC.
    - **Horario operacional (`TIME` / parámetros configurables):** Las horas de check-in y check-out son acuerdos operativos administrativos que regulan el flujo de huéspedes, limpieza y entrega de llaves. Serán gobernadas por parámetros configurables del PMS (`operacion.hora_checkin_predeterminada` y `operacion.hora_checkout_predeterminada`), cuyos valores iniciales deberán definirse explícitamente como decisión operativa antes de utilizarlos en producción; no se fijan valores arbitrarios en esta fase. Dichos horarios no deciden qué noches están ocupadas en el motor de inventario.

2. Identificadores de zona horaria IANA:
   - Se prohíbe el uso de offsets fijos (`UTC-5`, `GMT-5`, `-05:00`) como identidad persistente de zona horaria, ya que carecen de semántica sobre cambios estacionales o reglas territoriales.
   - Todo identificador de zona horaria en el sistema debe ser un identificador canónico IANA (ej. `America/Lima`).
   - Zona horaria predeterminada de Camargo PMS: `America/Lima` (administrada en el parámetro central `operacion.zona_horaria_predeterminada`).

3. Zona horaria por propiedad:
   - La arquitectura soporta que cada propiedad física pueda operar en su propia zona horaria local.
   - En la fase `DISPONIBILIDAD-1`, la tabla `propiedades` incorporará una columna `zona_horaria VARCHAR(50) NULL DEFAULT NULL`.
   - Semántica resolutiva: si `propiedad.zona_horaria IS NOT NULL`, se utiliza dicha zona; si es `NULL`, se hereda la zona horaria predeterminada del PMS (`operacion.zona_horaria_predeterminada`).

4. Definición matemática del intervalo hotelero y cálculo de noches:
   - La estancia se modela como un **intervalo semiabierto**:
     $$\text{Estancia} = [\text{fecha\_entrada}, \text{fecha\_salida})$$
   - El huésped ocupa las noches comprendidas desde `fecha_entrada` (inclusive) hasta el día previo a `fecha_salida`. La noche correspondiente a `fecha_salida` **NO** se consume, quedando disponible para el check-in de una nueva reserva ese mismo día.
   - Contrato matemático de noches:
     $$\text{noches} = \text{fecha\_salida} - \text{fecha\_entrada}$$
   - Restricción estricta de dominio para el motor ordinario: `fecha_salida > fecha_entrada` ($\text{noches} \ge 1$). Se rechazan estancias de 0 noches o fechas invertidas. Casos especiales futuros (day use, early check-in, late checkout) serán modelados como servicios complementarios o estados operativos, sin alterar el contrato base del intervalo semiabierto.

### D-067 — Estrategia de Concurrencia para Disponibilidad e Inventario Diario (cierra P-006)

1. Arquitectura y Modelo Híbrido:
   - Se adopta el **Modelo Híbrido** compuesto por:
     a) **Entidad Comercial/Operacional (`reservas` / `bloqueos`):** Representa el contrato comercial, titular, estado administrativo y metadatos de la operación.
     b) **Inventario Diario Físico (`inventario_diario_unidades`):** Representa la ocupación atómica de cada noche individual para cada unidad física.
     c) **Transacción ACID Relacional:** Garantiza la coherencia total entre la entidad comercial y el inventario diario.

2. Semántica del Inventario Diario Sparse:
   - Se adopta el modelo **Sparse (disperso / bajo demanda)**: la tabla de inventario diario contiene únicamente filas para las noches efectivamente ocupadas o bloqueadas. No se pregeneran millones de filas vacías para años futuros.
   - Disponibilidad se define formalmente como la **ausencia de fila de bloqueo/ocupación** para la tupla `(unidad_id, fecha)` en el rango semiabierto solicitado.

3. Restricción UNIQUE como Última Línea Defensiva Inviolable:
   - La tabla `inventario_diario_unidades` implementa la restricción única:
     `UNIQUE KEY uq_unidad_fecha (unidad_id, fecha)`
   - Ninguna reserva puede consolidarse basándose exclusivamente en consultas previas (`SELECT ... WHERE`), dado que existe una ventana de carrera (*race condition*) entre el check y el insert.
   - La restricción `UNIQUE` en el motor InnoDB de la base de datos es la garantía última e inviolable que impide que dos transacciones simultáneas inserten la misma noche para la misma unidad.

4. Atomicidad Transaccional y Rollback Completo:
   - La ocupación de un intervalo multinoche se ejecuta bajo una única transacción de base de datos.
   - Si alguna noche del intervalo colisiona con una ocupación existente (error de clave duplicada 1062 / conflicto de lock), la transacción ejecuta un `ROLLBACK` total inmediato.
   - Queda terminantemente prohibido que una reserva quede parcialmente persistida o que queden noches huérfanas en el inventario diario.

5. Orden Determinista de Bloqueos para Mitigación de Deadlocks:
   - En cualquier operación que involucre múltiples noches o múltiples unidades, las inserciones/bloqueos deben procesarse obligatoriamente en orden determinista:
     `ORDER BY unidad_id ASC, fecha ASC`
   - Este ordenamiento reduce sustancialmente el riesgo de bloqueos mutuos (*deadlocks*) y minimiza patrones de adquisición cruzada de locks entre transacciones concurrentes que soliciten las mismas unidades, aunque en un sistema transaccional complejo no permite asumir inmunidad absoluta ante deadlocks.

6. Manejo de Conflictos y Excepciones de Dominio:
   - Precisamente porque pueden ocurrir colisiones de concurrencia o bloqueos, los errores de clave duplicada (`1062`), lock wait timeouts (`1205`) o deadlocks (`1213`) deben capturarse explícitamente en la capa de servicio.
   - La transacción debe ejecutar `ROLLBACK` total inmediato y traducir el error a una excepción de dominio específica: `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict), indicando con precisión la unidad y fecha en disputa, evitando la propagación de errores HTTP 500 genéricos.

7. Liberación Atómica de Noches:
   - La cancelación de una reserva o la expiración de un hold temporal de pago ejecuta la eliminación atómica de sus noches en el inventario diario (`DELETE FROM inventario_diario_unidades WHERE reserva_id = ?`), dejando las fechas inmediatamente disponibles para otros clientes sin residuos lógicos.

8. Centralización Arquitectónica y Camargo PMS como Única Fuente de Verdad:
   - Todos los canales de venta (PMS administrativo, WordPress, App móvil, canales OTA / Airbnb, APIs externas y Webhooks) deben consumir forzosamente el **mismo servicio de disponibilidad**, la **misma transacción** y las **mismas restricciones de concurrencia**.
   - Ningún canal externo mantiene inventario autoritativo paralelo.

### D-068 — Implementación del Motor Central de Disponibilidad, Bloqueos Operativos e Inventario Diario Sparse (DISPONIBILIDAD-1)

1. Principio ontológico de disponibilidad y delimitación de dominio:
   - `UNIDAD ≠ DISPONIBILIDAD ≠ RESERVA ≠ TARIFA`.
   - `ACTIVO ≠ DISPONIBLE`: Una unidad activa dentro de una propiedad activa es potencialmente comercializable, pero su disponibilidad física real depende estrictamente de la ausencia de bloqueos o reservas para la tupla `(unidad_id, fecha)` en el rango de noches solicitado.
   - Unidades en estado `INACTIVO` o pertenecientes a propiedades en estado `INACTIVO` son automáticamente excluidas de la disponibilidad comercial y rechazan cualquier intento de bloqueo operativo con `ValidacionExcepcion` (HTTP 422).
   - Preservación estricta de P-005: Se prohíbe categóricamente incorporar tarifas, precios, costos, monedas, redondeos o impuestos en esta fase; las entidades `bloqueos_unidad` e `inventario_diario_unidades` carecen deliberadamente de columnas monetarias.

2. Persistencia relacional en dos niveles (D-067 implementada):
   - **Registro Maestro (`bloqueos_unidad`):** Almacena la entidad administrativa de indisponibilidad técnica/operativa (`id`, `unidad_id`, `fecha_inicio`, `fecha_fin`, `noches`, `motivo`, `tipo`, `estado`, `creado_por_actor_id`, `liberado_por_actor_id`, `creado_en`, `liberado_en`). No se destruye físicamente tras la liberación para salvaguardar el historial y auditoría bajo D-061.
   - **Inventario Diario Físico Sparse (`inventario_diario_unidades`):** Persiste atómicamente cada noche individual con su clave única defensiva `UNIQUE KEY uq_inventario_unidad_fecha (unidad_id, fecha)` y origen polimórfico (`origen_tipo`, `origen_id`).
   - Los tipos de bloqueo válidos son: `BLOQUEO_MANUAL` y `MANTENIMIENTO`.

3. Concurrencia, orden determinista y manejo de excepciones:
   - Toda creación de bloqueo o reserva multinoche se procesa bajo una única transacción ACID de base de datos.
   - Inserción ordenada deterministamente: `ORDER BY unidad_id ASC, fecha ASC`, mitigando deadlocks y bloqueos cruzados.
   - Captura estricta de errores de integridad y contención (códigos MySQL 1062 Clave Duplicada, 1205 Lock Wait Timeout y 1213 Deadlock), ejecutando `ROLLBACK` total inmediato y emitiendo `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict), verificados empíricamente en el motor MySQL Community Server 8.4.3 LTS (GPL), garantizando cero noches huérfanas o transacciones inconsistentes.

4. Semántica temporal del intervalo semiabierto y husos horarios (D-066 implementada):
   - Intervalo hotelero: $[\text{fecha\_inicio}, \text{fecha\_fin})$ con $\text{fecha\_fin} > \text{fecha\_inicio}$ y $\text{noches} = \text{fecha\_fin} - \text{fecha\_inicio} \ge 1$.
   - La noche de salida queda formalmente disponible para el check-in de una operación sucesiva ese mismo día, sin falso conflicto.
   - Resolución de huso horario IANA: la propiedad física puede definir su huso en `propiedades.zona_horaria`; en caso de ser nulo, recurre de forma segura al parámetro central del PMS `operacion.zona_horaria_predeterminada` (`America/Lima`). Identificadores no reconocidos por PHP recurren de forma segura a `America/Lima`.

5. Ciclo de vida y liberación atómica:
   - La liberación de un bloqueo muta su estado administrativo de `ACTIVO` a `LIBERADO`, registra el actor ejecutor humano en `liberado_por_actor_id`, la marca temporal `liberado_en`, y elimina atómicamente todas sus filas en `inventario_diario_unidades` (`DELETE ... WHERE origen_tipo = 'BLOQUEO_MANUAL' AND origen_id = ?`).
   - Una vez liberado, la disponibilidad del rango queda restaurada de forma inmediata para nuevas operaciones.
   - Intentos de re-liberar un bloqueo ya inactivo son rechazados con `ValidacionExcepcion` (HTTP 422).

### D-069 — Contrato Monetario y Financiero Base (cierra P-005)

1. Moneda Canónica y Desacoplamiento de Presentación:
   - La moneda operativa y canónica de Camargo PMS es el Sol peruano, representado bajo el código oficial ISO 4217: `PEN` (tres letras alfabéticas en mayúsculas).
   - Separación conceptual: `MONEDA ≠ SÍMBOLO`. El símbolo comercial `S/` es estrictamente un recurso de formato visual en la capa de interfaz y presentación (`Vistas/`). Se prohíbe terminantemente almacenar el símbolo `S/` en columnas de base de datos o concatenarlo en variables de dominio o lógica de negocio.
   - Preparación multimoneda: Toda entidad o tabla que almacene importes monetarios debe persistir explícitamente la columna `moneda_codigo VARCHAR(3) NOT NULL` (o `CHAR(3)`) según la norma ISO 4217, garantizando que el sistema esté preparado para operar con múltiples divisas (PEN, USD, EUR) sin cifras anónimas.

2. Prohibición Absoluta de Coma Flotante (Float / Double):
   - Se prohíbe terminantemente el uso de tipos de coma flotante binaria (`FLOAT`, `DOUBLE`, `REAL`) para almacenar o calcular importes, tarifas, precios, saldos o impuestos.
   - En MySQL 8.4: Todo valor monetario debe almacenarse obligatoriamente bajo el tipo exacto `DECIMAL` (representación empaquetada de base 10 fija).
   - En PHP 8.3: Todo cálculo financiero en capa de dominio y servicios debe ejecutarse utilizando aritmética de precisión arbitraria mediante la extensión `BCMath` (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`) sobre valores tipados como cadenas de texto (`string`), o mediante enteros que representen centésimas (céntimos).

3. Escala y Precisión Numérica Diferenciada:
   - **Importes Comerciales y Saldos Finales:** Se modelan como `DECIMAL(15,2)`, soportando importes de hasta 999,999,999,999.99 con 2 decimales para céntimos.
   - **Tarifas Unitarias Base, Tasas de Impuestos y Consumos:** Se modelan con una precisión mínima de 4 decimales: `DECIMAL(15,4)` (ej. tasa impositiva `0.1800` para 18% IGV, lecturas de suministros kWh o m³), garantizando granularidad en prorrateos y previniendo pérdidas por truncamiento.

4. Estándar de Redondeo y Precisión Intermedia:
   - Se adopta como regla obligatoria de redondeo comercial el método aritmético `ROUND_HALF_UP` (redondeo a la centésima más cercana, resolviendo el empate en exactamente `.005` hacia arriba para importes positivos, conforme a los usos mercantiles y de la autoridad tributaria SUNAT).
   - **Prohibición de Redondeo Prematuro Acumulativo:** En transacciones con múltiples conceptos, noches o ítems de consumo, los cómputos intermedios deben mantener su precisión (al menos 4 decimales). El redondeo comercial a 2 decimales se aplica sobre el total de la línea contractual o sobre el total consolidado de la operación según la reglamentación fiscal aplicable, impidiendo descuadres sistemáticos de céntimos.

5. Autoridad Centralizada del Cálculo Financiero:
   - El backend de Camargo PMS (`Servicios` de dominio) es la única entidad autoritativa con capacidad para calcular tarifas, descuentos, recargos, impuestos y montos totales.
   - El frontend (JavaScript, formularios web, WordPress, aplicaciones cliente, OTAs) es puramente estimativo y de visualización. Queda terminantemente prohibido confiar o aceptar montos totales o precios finales enviados directamente por el cliente; el backend siempre recalcula y valida los importes contra las tarifas maestras vigentes o cotizaciones formales.

6. Inmutabilidad Histórica y Congelamiento de Snapshots:
   - Principio vinculante: `VALOR ACTUAL ≠ VALOR HISTÓRICO CONGELADO`.
   - Al confirmarse o emitirse una reserva, contrato, consumo o cobro, el sistema debe registrar un snapshot inmutable de los valores pactados: tarifa unitaria aplicada, desglose de base imponible, tasas tributarias vigentes y total exigible.
   - Modificaciones futuras en el catálogo maestro de tarifas o en la normativa fiscal (ej. ajustes temporales de tasas de IGV) aplican exclusivamente a operaciones futuras; jamás recalculan ni alteran registros históricos pasados.

7. Determinismo de Saldos y Pagos Parciales:
   - El sistema soporta estructuralmente pagos parciales. El saldo pendiente de una operación es determinista y computable en todo momento mediante la relación:
     $$\text{Saldo Pendiente} = \text{Total Contratado} - \sum(\text{Pagos Válidos Confirmados})$$
   - El saldo pendiente no se almacena como una columna editable de mutación libre, sino como un valor sincronizado o calculado a partir del histórico de transacciones válidas.

8. Preservación Contable y Cero Eliminación Física:
   - Principio: `ANULACIÓN / REVERSO ≠ DELETE`.
   - Se prohíbe la eliminación física (`DELETE FROM`) de cobros, pagos, asientos o movimientos de caja.
   - Cualquier corrección, devolución o cancelación operativa se implementa mediante transacciones de reverso (contra-asientos) o transiciones explícitas a estados `ANULADO` con registro de motivo, actor ejecutor humano y fecha (bajo D-061), preservando la trazabilidad contable y tributaria.

### D-070 — Núcleo Transaccional de Reservas Directas Multiunidad, Snapshot Económico Inmutable y Ciclo de Hold (RESERVAS-1)

1. Delimitación ontológica estricta de dominio:
   - `RESERVA ≠ DISPONIBILIDAD ≠ INVENTARIO ≠ ESTANCIA ≠ PAGO`.
   - La reserva formaliza el acuerdo comercial de alojamiento para un huésped titular (`persona_titular_id`) sobre una o varias unidades físicas (`reserva_unidades`) dentro de un intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$ con $\text{noches} \ge 1$ (D-066).
   - Se excluyen rigurosamente de esta fase: pagos, cobros, caja, pasarelas, webhooks, check-in, check-out, llaves, contratos PDF, recibos y facturación electrónica.

2. Soporte nativo de multiunidad (1 Reserva : N Unidades):
   - Una reserva puede agrupar múltiples unidades físicas de una o varias propiedades dentro del mismo rango temporal hotelero.
   - La persistencia se normaliza en dos niveles:
     - Cabecera comercial (`reservas`): código único alfanumérico (`RES-YYYYMMDD-XXXX`), titular, fechas, noches, estado, canal, origen, snapshot económico acumulado y auditoría.
      - Detalle de unidades (`reserva_unidades`): vínculo a la unidad física, precio unitario por noche, noches asignadas, subtotal, impuesto y total individual.

3. Snapshot financiero inmutable y autoridad del backend (D-069):
   - En el instante de creación, el backend calcula y congela el desglose económico de la reserva en base a las noches y tarifas unitarias aplicables:
     - Moneda canónica: `PEN` (ISO 4217).
     - Columnas: `subtotal`, `impuesto` y `total` en `DECIMAL(15,2)`.
   - **Contrato Fiscal Provisional (RESERVAS-1A):** D-069 otorga capacidad tributaria, pero no establece que toda reserva aplique universalmente IGV 18%. Al no existir aún una fuente impositiva formal ni categorización tributaria en el PMS, se establece provisoriamente `impuesto = 0.00` (ningún impuesto aplicado por el PMS, sin calificar la operación como exonerada o inafecta) y $\text{total} = \text{subtotal}$. El snapshot conserva las columnas tributarias preparadas para cuando se incorpore una fuente fiscal legítima.
   - Los importes son inmutables: cambios posteriores en el catálogo de tarifas o normativas tributarias futuras jamás alteran ni recalculan reservas existentes.
   - El frontend es puramente estimativo; el backend es la única autoridad financiera que valida y computa los importes con aritmética exacta (BCMath) y redondeo `ROUND_HALF_UP`.

4. Ciclo de vida de estados y gestión de inventario:
   - Estados válidos: `PENDIENTE`, `CONFIRMADA`, `CANCELADA`, `EXPIRADA`.
   - Estados activos que retienen inventario en `inventario_diario_unidades`: `PENDIENTE` y `CONFIRMADA`.
   - Estados terminales que liberan inventario: `CANCELADA` y `EXPIRADA`.
   - **Gestión del Parámetro de Hold (RESERVAS-1A):** El parámetro operacional `reservas.duracion_hold_minutos` está formalmente soportado en el catálogo de `configuraciones`, pero no posee ningún valor por defecto inventado en código ni en base de datos.
   - Si se intenta registrar una reserva en estado `PENDIENTE` y `reservas.duracion_hold_minutos` carece de un valor numérico positivo configurado explícitamente por el negocio, la operación es rechazada con `ConfiguracionFaltanteExcepcion` (HTTP 422). Las reservas creadas directamente en estado `CONFIRMADA` no requieren hold (`expira_en = NULL`).
   - Al contar con un valor configurado válido, se calcula el instante técnico $\text{expira\_en} = \text{ahora} + \text{duracion\_hold\_minutos}$, manteniéndose la distinción de D-066 entre fecha hotelera e instante técnico de expiración.

5. Atomicidad transaccional, orden determinista y concurrencia (D-067):
   - Toda creación, confirmación, cancelación y expiración de reservas se ejecuta bajo una única transacción ACID en el motor MySQL 8.4 InnoDB.
   - El inventario diario sparse se bloquea con ordenamiento determinista estricto: `ORDER BY unidad_id ASC, fecha ASC`.
   - La restricción `UNIQUE (unidad_id, fecha)` en `inventario_diario_unidades` garantiza la prevención inviolable de sobreventas (*overbooking*).
   - Cualquier error de concurrencia (códigos MySQL 1062 Clave Duplicada, 1205 Lock Wait Timeout, 1213 Deadlock) es capturado explícitamente en la capa de servicio, provocando `ROLLBACK` total inmediato y emitiendo `ConflictoDisponibilidadExcepcion` (HTTP 409 Conflict).
   - Atomicidad multiunidad completa: Si la unidad $N$ de una reserva colisiona, la transacción revierte íntegramente las unidades previas sin dejar noches huérfanas en inventario.

6. Liberación atómica e inmediata:
   - La cancelación voluntaria o la expiración de un hold vencido elimina atómicamente todas las filas correspondientes en `inventario_diario_unidades` (`DELETE FROM inventario_diario_unidades WHERE origen_tipo = 'RESERVA' AND origen_id = ?`).
   - Las fechas quedan liberadas y disponibles de forma instantánea para otros clientes y canales comerciales.

7. Preservación histórica y trazabilidad transversal de autoría (D-061):
   - **Preservación Histórica del Ciclo de Vida:** Principio `CANCELACIÓN / EXPIRACIÓN ≠ DELETE`. Las tablas `reservas` y `reserva_unidades` preservan todos sus registros históricos sin operaciones de eliminación física (`DELETE` = 0). Las cancelaciones exigen un motivo explicativo obligatorio (1 a 255 caracteres).
   - **Trazabilidad Canónica de Autoría bajo D-061 (`ACTOR ≠ USUARIO`):** Toda mutación de estado registra eventos de auditoría (`REGISTRAR`, `EDITAR`, `CANCELAR`, `EXPIRAR`) resolviendo el actor ejecutor correspondiente: asociando `USR_X` cuando el ejecutor es un usuario humano autenticado (`usuario_id`) o el actor de sistema `CAMARGO_PMS` en procesos automáticos de hold, garantizando estrictamente que jamás se use un `usuario_id` como `actor_id` directo por coincidencia numérica.

### D-071 — Estandarización transversal de recursos de interfaz (Iconografía Font Awesome 6 y Selectores Flatpickr Alina)

1. **Librería de Iconos Oficial y Exclusiva:**
   - Font Awesome 6 Free (v6.3.0) es la única librería de iconos permitida en todo el código propio de Camargo PMS (`app/`, `public/assets/`, esquemas y datos de menú en base de datos).
   - Queda estrictamente prohibido el uso de Tabler Icons (`ti ti-*`, `ti-*`) o librerías alternativas en vistas, layouts, JavaScript propio o registros de menú.
   - El catálogo de menú en base de datos (`opciones_menu.icono`) y el esquema SQL base (`SQL/camargo_pms.sql`) se migran y estandarizan con clases de Font Awesome (`fa-solid fa-*`).

2. **Selectores de Fecha y Hora Alina (Flatpickr):**
   - El componente **Date Picker** de Alina (basado en Flatpickr v4.6.13 local) es de adopción obligatoria para todo campo de entrada de fecha individual en la interfaz de Camargo PMS.
   - El componente **Range Picker** de Alina (Flatpickr en modo `range`) es de adopción obligatoria para toda selección de intervalo temporal o rango de fechas (`fecha_entrada` y `fecha_salida` en módulos de Disponibilidad, Reservas y filtros cronológicos).

3. **Preservación Inviolable del Contrato D-066:**
   - El Range Picker opera exclusivamente como un componente de experiencia de usuario (UX) en el cliente.
   - La arquitectura backend preserva de forma estricta e independiente los campos canónicos `fecha_entrada` y `fecha_salida` en formato ISO `YYYY-MM-DD` (`DATE`), con intervalo semiabierto $[ \text{entrada}, \text{salida} )$, donde la noche de salida permanece liberada para un nuevo ingreso en esa misma fecha hotelera.
   - Los campos de entrada canónicos (`#consulta-fecha-entrada`, `#consulta-fecha-salida`, `#crear-fecha-entrada`, `#crear-fecha-salida`, etc.) se mantienen como elementos subyacentes sincronizados por el controlador `public/assets/js/camargo-pickers.js`, garantizando compatibilidad total con validadores, serializadores y payloads HTTP.

4. **Assets 100% Locales (Local Assets First):**
   - Todos los recursos requeridos (CSS de Font Awesome 6, fuentes web asociadas en formato WOFF2/TTF, Flatpickr JS y CSS) residen localmente en el repositorio bajo `public/assets/vendor/` y `public/assets/fonts/`.
   - Queda prohibida la dependencia de CDN externas para componentes base de la interfaz.
   - La distribución original de la plantilla bajo `admin-dashboard/` permanece 100% intacta e inmutable como catálogo y referencia histórica.

5. **Cero Dependencia de jQuery:**
   - Toda la inicialización, sincronización de eventos y manipulación de selectores se realiza exclusivamente mediante JavaScript moderno nativo (Vanilla JS ES6+).

6. **Estandarización de Badges y Chips de Alina (UI-2A):**
   - **Componentes Permitidos y Obligatorios:**
     - *Variants of badge* de Alina (`badge bg-light-*`, `badge text-bg-*`, etc.) para estados compactos en tablas, listados, modales y contadores numéricos.
     - *Variants of chip* de Alina (`chip bg-light-*`, etc.) para categorías, atributos de entidad, clasificaciones de rol, principios arquitectónicos y tags interactivos/removibles.
   - **Prohibiciones Estrictas:**
     - Prohibido el uso de badges o chips con bordes punteados o discontinuos (`dotted`, `dashed`, `border-dotted`, `border-dashed`).
     - Prohibido el uso de badges o chips personalizados fuera del estándar Alina o clases Bootstrap crudas (`bg-*-subtle`).
   - **Iconografía Interna:**
     - Los iconos insertados dentro de badges y chips provienen exclusivamente de Font Awesome 6 Free (v6.3.0). Queda prohibido Tabler Icons u otras librerías.
   - **Mapa Semántico Vinculante:**
     - `SUCCESS` (`bg-light-success`): positivo / disponible / activo / confirmado (`ACTIVO`, `CONFIRMADA`, `DISPONIBLE`).
     - `WARNING` (`bg-light-warning`): pendiente / atención / mantenimiento / superadministrador (`PENDIENTE`, `MANTENIMIENTO`, `SUPERADMIN`).
     - `DANGER` (`bg-light-danger`): cancelado / error / bloqueo / suspendido (`CANCELADA`, `BLOQUEADO`, `OCUPADO`, `SUSPENDIDO`).
     - `INFO` (`bg-light-info`): informativo neutral / sistema / manual (`MANUAL`, `SISTEMA`, tipo de unidad).
     - `SECONDARY` (`bg-light-secondary`): neutral / inactivo / expirado / liberado / contadores y códigos auxiliares (`INACTIVO`, `EXPIRADA`, `LIBERADO`).
     - `LIGHT` / `DARK` (`bg-light text-dark`, `bg-dark text-white`): contraste puntual o códigos de unidad/propiedad destacados.
   - **Infraestructura Centralizada:**
     - Backend: Clase `\CamargoPMS\Nucleo\Insignia` y funciones globales `insignia_badge()`, `insignia_chip()` e `insignia_estado()`.
     - Frontend: Objeto global Vanilla JS `window.CamargoInsignia` (`badge()`, `chip()`, `estado()`, `resolverClase()`) en `camargo-layout.js`.

### D-072 — Núcleo Operativo de Estadías, Check-in y Huéspedes (ESTADÍAS-1)

1. **Delimitación Ontológica Estricta de Dominio:**
   - Principio fundamental: `RESERVA ≠ ESTADÍA ≠ ARRENDAMIENTO`.
   - La reserva formaliza el acuerdo comercial directo de alojamiento; la estadía formaliza la ocupación física real de una unidad hotelera.
   - El arrendamiento es un contrato patrimonial de mediano o largo plazo regido por normativa inmobiliaria y lógica contractual independiente.
   - Se excluyen taxativamente de esta fase: pagos, cobros, caja, pasarelas, facturación electrónica, consumos, servicios adicionales y arrendamientos.

2. **Multiunidad Operativa (1 Reserva : N Estadías Físicas Independientes):**
   - Una reserva multiunidad genera **una estadía física independiente por cada unidad alojable asignada** (`reserva_unidades`).
   - Restricción única en base de datos: `UNIQUE KEY uq_estadias_reserva_unidad (reserva_unidad_id)`, garantizando que cada unidad de reserva genere a lo sumo una estadía histórica.
   - Cada unidad asignada posee su propio momento de check-in, asignación de llave física, lista de ocupantes y check-out. Si una familia o grupo ocupa múltiples unidades y unos huéspedes llegan antes que otros, cada unidad opera su ciclo de manera autónoma (`Dpto 101 → EN_CURSO` mientras `Dpto 102 → pendiente de check-in`).
   - El agregado comercial `reservas` permanece inmutable en estado `CONFIRMADA`: el estado operativo de la reserva es derivado (consultando sus estadías: `SIN_CHECKIN`, `PARCIAL_EN_CURSO`, `COMPLETA_EN_CURSO`, `FINALIZADA`).

3. **Walk-in Diferido:**
   - Se aprueba conceptualmente el principio `WALK-IN → RESERVA CONFIRMADA → ESTADÍA`.
   - No se implementa walk-in automático en esta fase: todo check-in requiere obligatoriamente una `reserva_unidad` de una reserva comercial en estado `CONFIRMADA`. Intentos de check-in sobre reservas `PENDIENTE`, `CANCELADA` o `EXPIRADA` son rechazados con `EstadoReservaInvalidoExcepcion` (HTTP 422).

4. **Bloqueo Estricto de Capacidad y Huésped Responsable:**
   - **Capacidad Física Estricta:** Se valida inexorablemente que $1 \le \text{count}(\text{huéspedes}) \le \text{unidad.capacidad\_personas}$. Si la cantidad de huéspedes excede la capacidad máxima de la unidad, la operación se rechaza con `CapacidadExcedidaExcepcion` (HTTP 422), sin sobrecapacidad autorizada.
   - Si la lista de huéspedes está vacía, se rechaza con `HuespedInvalidoExcepcion` (HTTP 422).
   - **Exactamente Un Huésped Responsable:** Toda estadía debe contar obligatoriamente con **exactamente 1 huésped responsable** (`es_responsable = 1`) perteneciente a la lista de ocupantes de la estadía. Configuraciones con 0 o >1 responsables son rechazadas con `HuespedInvalidoExcepcion` (HTTP 422).
   - **Vínculo al Maestro Central de Personas:** Todos los huéspedes se vinculan a personas naturales registradas (`estadia_huespedes.persona_id`). Se prohíbe duplicar a la misma persona dentro de una misma estadía (`UNIQUE(estadia_id, persona_id)`).

5. **Ciclo de Vida Operativo e Inmutabilidad Histórica:**
   - Estados válidos: `EN_CURSO`, `FINALIZADA`, `ANULADA`.
   - Principios: `CHECK-OUT ≠ DELETE`, `ANULACIÓN ≠ DELETE`. Cero eliminación física (`DELETE = 0`) sobre `estadias` y `estadia_huespedes`; restricciones de clave foránea con `ON DELETE RESTRICT` (cero borrado en cascada).
   - La anulación de una estadía es excepcional, exige un motivo justificativo obligatorio (1 a 255 caracteres) y preserva el registro histórico para auditoría.

6. **Semántica Temporal y Check-out Anticipado/Tardío (D-066):**
   - `fecha_entrada` y `fecha_salida_prevista` representan fechas hoteleras locales de tipo `DATE`.
   - `checkin_en`, `checkout_en` y `anulada_en` representan instantes técnicos reales en UTC (`DATETIME` con `gmdate('Y-m-d H:i:s')`).
   - Check-out anticipado o tardío: registra el instante técnico real físico de salida sin recalcular noches comerciales, tarifas, snapshots económicos ni devoluciones financieras.

7. **Identificación de Llaves y Accesos:**
   - Campo plano `identificador_llave VARCHAR(50) NULL`, registrando código de tarjeta magnética o número de llave física, sin acoplamiento a domótica o cerraduras electrónicas en esta fase.

8. **Trazabilidad Transversal de Autoría (D-061) y Seguridad RBAC:**
   - Todas las mutaciones de check-in, check-out, anulación y modificación de huéspedes registran eventos de auditoría (`ESTADIA_CHECKIN`, `ESTADIA_CHECKOUT`, `ESTADIA_ANULADA`, `ESTADIA_HUESPEDES_ACTUALIZADOS`) vinculando al actor ejecutor humano correspondiente (`checkin_por_actor_id`, etc.).
   - Permisos RBAC granulares: `estadias.ver`, `estadias.checkin`, `estadias.checkout`, `estadias.huespedes`, `estadias.anular`.

### D-073 — Catálogo Maestro de Servicios, Matriz de Proveedores Homologados, Consumos Imputados y Traslados (SERVICIOS-1)

1. **Separación Ontológica y Delimitación de Dominio:**
   - Principio rector: `PROVEEDOR ≠ SERVICIO ≠ SERVICIO CONTRATADO ≠ RESERVA ≠ ESTADÍA`.
   - El proveedor es una entidad externa que presta un servicio o suministro; el servicio es el concepto o plantilla comercializable; el servicio contratado es la instancia ejecutada o comprometida para una reserva; la reserva es el contrato comercial de hospedaje; la estadía es la ocupación física real.
   - Exclusiones estrictas: pagos, caja, transacciones financieras de cobro, facturación SUNAT y arrendamientos quedan fuera de esta fase.

2. **Maestro Independiente de Proveedores y Cero Proveedor "Interno":**
   - La tabla `proveedores` es un catálogo autónomo para prestadores externos de tipo `EMPRESA` (RUC / razón social) o `PERSONA_NATURAL` (vinculada opcionalmente al registro central de `personas` para no duplicar identidad).
   - Principio `PROVEEDOR ≠ OPERACIÓN INTERNA`: no se crea un proveedor ficticio llamado "Interno" ni se inventa una empresa Camargo en la tabla de proveedores. Cuando el servicio es realizado directamente por Camargo Hostelería, `es_operacion_interna = 1` y `proveedor_id` permanece en `NULL`.

3. **Integridad Estructural en Base de Datos (CHECK Constraint):**
   - El motor de base de datos MySQL 8.4 InnoDB impone estructuralmente la coherencia de procedencia mediante:
     ```sql
     CONSTRAINT chk_sc_coherencia_operacion_interna CHECK (
       (es_operacion_interna = 1 AND proveedor_id IS NULL)
       OR (es_operacion_interna = 0 AND proveedor_id IS NOT NULL)
     )
     ```
   - No se permite depender exclusivamente de validaciones PHP: cualquier estado contradictorio es rechazado a nivel de motor.

4. **Matriz de Homologación de Proveedores y Unicidad de Preferente:**
   - La relación entre servicios y proveedores se modela en `servicio_proveedores`, registrando costos pactados (`costo_pactado DECIMAL(15,2)`), plazos de pago en días y referencias comerciales.
   - Cada servicio puede homologar múltiples proveedores, pero solo uno puede ser `preferente` activo a la vez. Esta regla se garantiza en base de datos mediante una columna virtual generada:
     ```sql
     es_preferente_virt TINYINT GENERATED ALWAYS AS (CASE WHEN es_preferente = 1 THEN 1 ELSE NULL END) STORED,
     UNIQUE KEY uq_sp_servicio_preferente (servicio_id, es_preferente_virt)
     ```

5. **Contratación e Imputación de Consumos:**
   - Toda contratación exige anclarse a una reserva comercial (`reserva_id NOT NULL`), garantizando que no existan consumos huérfanos.
   - La imputación a una estadía física es opcional (`estadia_id NULL`), pero si se especifica, debe pertenecer obligatoriamente a la misma reserva y encontrarse en estado válido (no anulada), verificado bajo transacción con bloqueo pesimista `FOR UPDATE`.
   - Snapshots inmutables (D-010 / D-069): se congelan inmutablemente en el registro contratado: `concepto_servicio`, `cantidad`, `precio_unitario`, `costo_unitario`, `subtotal`, `tasa_impuesto = 0.0000`, `impuesto_total = 0.00` y `total = subtotal` (`moneda_codigo = 'PEN'`). Modificaciones posteriores del catálogo o tarifas de proveedores no alteran consumos emitidos.

6. **Ciclo Operativo y Prohibición Estricta en Servicios Ejecutados:**
   - Estados: `SOLICITADO` → `CONFIRMADO` → `EJECUTADO`, y `CANCELADO`.
   - **Regla Vinculante:** PROHIBIDO cancelar un servicio que ya ha sido `EJECUTADO` físicamente. Toda solicitud de cancelación sobre un servicio en estado `EJECUTADO` es rechazada con `EstadoServicioInvalidoExcepcion` (HTTP 422).
   - Cancelación justificada sobre servicios no ejecutados requiere motivo obligatorio (1-255 caracteres), registrando marca técnica UTC y actor cancelador.
   - Cero borrado físico: `DELETE = 0` en tablas transaccionales; claves foráneas con `ON DELETE RESTRICT`.

7. **Extensión Especializada 1:1 de Traslados (Transfers):**
   - Los traslados se modelan en tabla dedicada `servicio_traslados` con relación 1:1 estricta (`UNIQUE(servicio_contratado_id)`).
   - Soporta operaciones de `LLEGADA` y `SALIDA`, con origen y destino generalizados (aeropuerto, terminal, estación, propiedad, centro o dirección libre), número de vuelo/transporte, pasajeros, equipaje, vehículo y chofer asignado.
   - La persistencia es atómica: cualquier error de validación logística revierte completamente la transacción.

### D-074 — Tríada Financiera, Modelo de Cuentas/Folios, Aplicaciones de Pago, Arqueos Deterministas y Tesorería (FINANCIERO-2)

1. **La Tríada Financiera y Desacoplamiento de Cobros:**
   - Principios ontológicos inviolables: `CARGO ≠ PAGO ≠ MOVIMIENTO DE CAJA` y `PAGO ≠ APLICACIÓN DE PAGO`.
   - **Cargo:** Representa una deuda u obligación contractual devengada contra la cuenta/folio (por alojamiento o consumo de servicios).
   - **Pago:** Representa un ingreso formal de fondos a favor del establecimiento, capturado a través de un método de pago. El pago puede ingresar sin estar aplicado inmediatamente a un cargo específico (anticipo o saldo no aplicado).
   - **Movimiento de Caja:** Representa una afectación física del dinero en efectivo bajo custodia de un cajero en un turno de recepción.
   - **Aplicación de Pago:** Entidad relacional desacoplada que formaliza la amortización total o parcial de un cargo con un pago específico.
   - **Cero banderas booleanas (`pagado = 1`):** El balance de la cuenta, el saldo pendiente de cada cargo y el saldo no aplicado de cada pago son derivados matemáticos estrictos calculados en tiempo real mediante `BCMath` con dos decimales (`DECIMAL(15,2)`).

2. **Folios Comerciales 1:1 por Reserva:**
   - Cada reserva comercial dispone de exactamente una cuenta/folio financiero principal (`cuentas_folios.reserva_id UNIQUE`).
   - Los cargos se imputan obligatoriamente a la cuenta de la reserva, pero conservan un puntero opcional `estadia_id NULL` para responder con exactitud:
     - "¿Cuánto debe esta reserva en total?" (Balance global de la cuenta).
     - "¿Qué consumió específicamente la habitación/estadía X?" (Filtro por `estadia_id`).

3. **Ciclo de Vida de Cargos y Sincronización Automática:**
   - **Cargos de Alojamiento:** Nacerán en estado `DEVENGADO` de forma transaccional automática cuando la reserva comercial pasa al estado `CONFIRMADA`.
   - **Cargos de Servicios:**
     - Al contratarse el servicio (`SOLICITADO` / `CONFIRMADO`): se genera automáticamente un cargo en estado `PROVISIONAL`.
     - Al ejecutarse físicamente el servicio (`EJECUTADO`): el cargo transiciona de inmediato a `DEVENGADO`.
     - Si el servicio es cancelado antes de su ejecución: el cargo transiciona a `ANULADO`, liberando la deuda asociada.

4. **Caja Física, Turnos y Arqueo Determinista:**
   - Todo cobro o devolución liquidado con método `EFECTIVO` exige indispensablemente una sesión de caja física abierta (`sesiones_caja.estado = 'ABIERTO'`).
   - El arqueo de cierre de turno es estrictamente determinista:
     $$\Delta = \text{Monto Contado Declarado} - \text{Monto Esperado del Sistema}$$
     - Si $\Delta = 0.00$ $\rightarrow$ `resultado_arqueo = 'CUADRADA'`.
     - Si $\Delta > 0.00$ $\rightarrow$ `resultado_arqueo = 'SOBRANTE'`. Justificación obligatoria de 10 a 500 caracteres (`motivo_diferencia`).
     - Si $\Delta < 0.00$ $\rightarrow$ `resultado_arqueo = 'FALTANTE'`. Justificación obligatoria de 10 a 500 caracteres (`motivo_diferencia`).
   - Movimientos manuales de caja: Estrictamente tipados (`INGRESO_AJUSTE`, `EGRESO_GASTO_MENOR`) con motivo obligatorio y registro del actor responsable.

5. **Cuentas Bancarias vs Medios de Pago Electrónicos:**
   - Pagos de tipo `TRANSFERENCIA` y `DEPOSITO` exigen obligatoriamente vincular una `cuenta_bancaria_id` activa, generando un movimiento bancario de entrada.
   - Pagos electrónicos de tipo `TARJETA` y `BILLETERA` registran código de referencia externa; la cuenta bancaria de destino permanece como `NULL` opcional para permitir la liquidación posterior a través de pasarelas adquirentes y conciliación diferida.

6. **Reversiones y Devoluciones Formalizadas:**
   - Las aplicaciones de pago son reversibles de forma compensatoria (`reversada = 1`, `reversada_en`, `reversado_por_actor_id`), restaurando inmediatamente el saldo pendiente del cargo y el saldo no aplicado del pago sin alterar los registros históricos.
   - Las devoluciones formalizadas (`devoluciones_cuenta`) reducen el saldo del pago original, exigen motivo de 10 a 500 caracteres y, en caso de ejecutarse en efectivo, registran un egreso automático en la caja física activa.

7. **Integridad Relacional y Seguridad:**
   - Cero borrado físico: `DELETE = 0` en todas las tablas financieras transaccionales; claves foráneas protegidas con `ON DELETE RESTRICT`.
   - Permisos RBAC granulares: `caja.ver`, `caja.aperturar`, `caja.cerrar`, `caja.movimientos`, `caja.cobrar`, `caja.aplicar`, `caja.devolver`, `caja.reversar`.
   - Trazabilidad y autoría D-061 (`ACTOR ≠ USUARIO`) registrada en todas las aperturas, cierres, cobros, aplicaciones y devoluciones.
   - Interfaz Alina D-071: Font Awesome 6.3.0 exclusivo, Flatpickr, Variants of badge de Alina (`bg-light-*`), 0 dotted, 0 dashed, Vanilla JS nativo, PristineJS y SweetAlert2.

## Pendientes de decisión

| ID | Tema | Momento límite | Estado |
|---|---|---|---|
| P-003 | Framework de pruebas PHP/JS | Antes de pruebas automatizadas de dominio | Pendiente |
| P-004 | Estrategia de zona horaria y fecha hotelera | Antes de disponibilidad | **Cerrada en D-066** |
| P-005 | Moneda, redondeo e impuestos | Antes de tarifas/caja | **Cerrada en D-069** |
| P-006 | Estrategia de concurrencia para disponibilidad | Antes de reservas | **Cerrada en D-067** |
| P-007 | Librería PDF | Antes de contratos/recibos | Pendiente |
| P-008 | Proveedor inicial de pagos | Antes de integración de pagos | Pendiente |
| P-009 | Retención de datos y auditoría | Antes de producción | Pendiente |

