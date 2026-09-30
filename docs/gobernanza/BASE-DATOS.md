# Base de datos

## Enfoque y Motor Confirmado

- **Motor oficial:** **MySQL Community Server 8.4.3 LTS** (GPL).
- **Engine predeterminado:** `InnoDB` con soporte pleno de transacciones ACID y restricciones de integridad foránea.
- **Codificación:** Charset `utf8mb4` con collation `utf8mb4_0900_ai_ci`.
- **Modo SQL:** Estricto (`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`).
- **Persistencia en aplicación:** Acceso centralizado vía PDO nativo (`app/Nucleo/BaseDatos.php`) con opciones estrictas: `ATTR_ERRMODE => ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`, y `ATTR_EMULATE_PREPARES => false`.

## Esquema Consolidado y Migraciones

La gestión estructural del esquema sigue el principio de doble representación sincronizada:

1. **`SQL/camargo_pms.sql` (Esquema Consolidado Oficial):**
   - Representación íntegra, canónica y versionada del esquema vigente.
   - Permite recrear la base de datos estructuralmente desde cero en entornos limpios.
   - Libre de datos operativos, datos personales, contraseñas o volcados de producción.
   - Debe iniciar con `SET NAMES utf8mb4; SET FOREIGN_KEY_CHECKS = 0;` y finalizar obligatoriamente con `SET FOREIGN_KEY_CHECKS = 1;`.
2. **`SQL/migraciones/` (Evolución Incremental):**
   - Scripts secuenciales numerados deterministas (ej. `001_infraestructura.sql`, `002_identidad_personas.sql`).
   - Registrados y controlados mediante la tabla técnica `migraciones` (`id`, `migracion`, `lote`, `ejecutado_en`).
   - El campo `lote` representa exclusivamente metadatos de trazabilidad y agrupación cronológica por microfase (no asume reversibilidad automática hasta que se implemente una estrategia down/compensatoria formal).
   - Las migraciones ordinarias respetan el orden de dependencias relacionales y no desactivan `FOREIGN_KEY_CHECKS` de forma indiscriminada.
   - Ejecutados exclusivamente vía CLI mediante `php migrar.php`.

**Regla vinculante:** Todo cambio estructural de base de datos debe nacer de una migración versionada y reflejarse simultáneamente en `SQL/camargo_pms.sql`. Nunca se aplican cambios manuales en producción como sustituto de una migración.

## Esquema del Núcleo de Identidad (IDENTIDAD-1 y ajustes evolutivos 003 y 004 / PERSONAL-1A)

- `paises`: Catálogo normalizado (`id`, `codigo_iso2`, `codigo_iso3`, `nombre`, `nacionalidad`, `activo`). Restricciones UNIQUE en códigos ISO. Semilla base: Perú ('PE', 'PER').
- `tipos_documento`: Catálogo extensible para personas naturales (`id`, `codigo`, `nombre`, `descripcion`, `longitud_exacta`, `longitud_minima`, `longitud_maxima`, `formato_regex`, `pais_fijo_id`, `pais_emisor_obligatorio`, `activo`). Semillas base: DNI (`pais_fijo_id = 1`, `pais_emisor_obligatorio = 0`), Carné de Extranjería (`pais_fijo_id = 1`, `pais_emisor_obligatorio = 0`) y Pasaporte (`pais_fijo_id = NULL`, `pais_emisor_obligatorio = 1`). Clave foránea `fk_tipos_documento_pais_fijo` hacia `paises(id)`. Sin RUC (reservado a personas jurídicas / fiscalidad).
- `personas`: Maestro de personas naturales (`id` BIGINT, `nombres`, `apellido_paterno`, `apellido_materno`, `fecha_nacimiento`, `pais_nacionalidad_id`, `direccion`, `estado`). Nombres obligatorios; apellidos con nulabilidad para monónimos legales y personas extranjeras (evolución migración 003). Restricción CHECK de estado (`ACTIVO`, `INACTIVO`).
- `personas_documentos`: Colección de documentos (`id` BIGINT, `persona_id`, `tipo_documento_id`, `numero_documento`, `pais_emisor_id` INT UNSIGNED NOT NULL, `es_principal`, `fecha_emision`, `fecha_vencimiento`, `estado`). Se eliminan centinelas artificiales y columnas virtuales (evolución migración 004): `pais_emisor_id` es obligatorio y respaldado por la clave foránea íntegra `fk_documentos_pais_emisor` e indexado con `UNIQUE (tipo_documento_id, pais_emisor_id, numero_documento)` para permitir coincidencia de números entre países distintos y prevenir duplicados dentro de la misma jurisdicción. Columna virtual `uq_persona_principal` para garantizar a lo sumo un principal activo por persona.
- `personas_contactos`: Colección de medios de contacto (`id` BIGINT, `persona_id`, `tipo_contacto`, `valor`, `es_whatsapp`, `es_principal`, `estado`). Columna virtual `uq_contacto_tipo_principal` para garantizar un único principal por tipo y persona. Indicador `es_whatsapp` para vincular WhatsApp a un número telefónico sin duplicación física de registros.

## Esquema del Núcleo de Personal y Colaboradores (PERSONAL-1 y PERSONAL-1A)

- `cargos`: Catálogo administrable de puestos laborales (`id` INT, `codigo`, `nombre`, `descripcion`, `activo`). Restricción `UNIQUE (codigo)`. Semillas: ADMINISTRADOR, RECEPCIONISTA, RESERVAS, LIMPIEZA, MANTENIMIENTO.
- `colaboradores`: Identidad laboral estable vinculada a personas (`id` BIGINT, `persona_id`, `codigo`, `estado`, `creado_en`, `actualizado_en`). Restricción `UNIQUE (persona_id)` para cardinalidad 1:1 lógica estricta y `UNIQUE (codigo)` para código interno estable (`COL-XXXX`). Estado: `'ACTIVO'`, `'INACTIVO'`.
- `episodios_laborales`: Períodos continuos de relación laboral (`id` BIGINT, `colaborador_id`, `fecha_inicio`, `fecha_fin`, `motivo_cese`, `observaciones`, `estado`). Columna virtual generada `uq_colaborador_abierto = IF(fecha_fin IS NULL AND estado = 'ACTIVO', colaborador_id, NULL)` con restricción `UNIQUE` para forzar a nivel de motor máximo un episodio abierto activo por colaborador. `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)` y `CHECK (estado IN ('ACTIVO', 'INACTIVO'))`.
- `episodios_laborales_cargos`: Historial inmutable de funciones ocupadas por episodio (`id` BIGINT, `episodio_laboral_id`, `cargo_id`, `fecha_inicio`, `fecha_fin`, `observaciones`). Columna virtual generada `uq_episodio_cargo_abierto = IF(fecha_fin IS NULL, episodio_laboral_id, NULL)` con restricción `UNIQUE` para forzar máximo una asignación de cargo vigente por episodio. `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)`. Invariante temporal de no solapamiento integral protegida por servicio con `FOR UPDATE` (PERSONAL-1A). Transición temporal continua en $D-1$ / $D$.

## Esquema de Autenticación, Usuarios y Sesiones (AUTH-1 y ajuste evolutivo 006 / AUTH-1A)

- `usuarios`: Cuentas de acceso humano vinculadas 1:1 al núcleo de personas (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `persona_id` BIGINT UNSIGNED NOT NULL UNIQUE, `nombre_usuario` VARCHAR(50) NOT NULL, `nombre_usuario_normalizado` VARCHAR(50) NOT NULL UNIQUE, `contrasena_hash` VARCHAR(255) NOT NULL COMMENT 'Hash criptográfico de la contraseña (soporta PASSWORD_DEFAULT y algoritmos futuros)', `estado` ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') NOT NULL DEFAULT 'ACTIVO', `ultimo_acceso_en` DATETIME NULL, `contrasena_cambiada_en` DATETIME NULL, `creado_en` DATETIME NOT NULL, `actualizado_en` DATETIME NOT NULL). Clave foránea `fk_usuarios_persona` con restricción de borrado `ON DELETE RESTRICT`. Prohibición de relación con `colaboradores` (`colaborador_id`). Longitud `VARCHAR(255)` formalizada mediante la migración evolutiva 006 (AUTH-1A) para garantizar extensibilidad a cualquier algoritmo presente o futuro de `PASSWORD_DEFAULT`.
- `sesiones_usuario`: Sesiones activas y trazabilidad de tokens opacos (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `usuario_id` BIGINT UNSIGNED NOT NULL, `token_hash` CHAR(64) NOT NULL UNIQUE, `ip` VARCHAR(45) NULL, `user_agent` VARCHAR(255) NULL, `iniciada_en` DATETIME NOT NULL, `ultima_actividad_en` DATETIME NOT NULL, `expira_en` DATETIME NOT NULL, `revocada_en` DATETIME NULL, `motivo_cierre` ENUM('LOGOUT','EXPIRACION_INACTIVIDAD','EXPIRACION_ABSOLUTA','REVOCACION_ADMINISTRATIVA','CAMBIO_CONTRASENA','CAMBIO_ESTADO_USUARIO','DESACTIVACION_PERSONA','OTRO') NULL, `creado_en` DATETIME NOT NULL). Clave foránea `fk_sesiones_usuario` con `ON DELETE RESTRICT ON UPDATE CASCADE`. Índices para búsqueda por hash, usuario activo y limpieza por expiración.
- `intentos_autenticacion`: Registro de intentos de acceso para control de fuerza bruta y rate limiting (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `ip` VARCHAR(45) NOT NULL, `nombre_usuario_normalizado` VARCHAR(50) NOT NULL, `exitoso` TINYINT(1) NOT NULL DEFAULT 0, `intentado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP). Índices en `(ip, intentado_en)`, `(nombre_usuario_normalizado, intentado_en)` e `idx_intentos_limpieza`.

## Esquema de Autorización RBAC, Roles y Permisos (ROLES-1 y Migración 007)

- `roles`: Catálogo de roles de autorización del sistema (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `codigo` VARCHAR(50) NOT NULL UNIQUE, `nombre` VARCHAR(100) NOT NULL, `descripcion` VARCHAR(255) NULL, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `es_superadministrador` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP). Restricciones CHECK de cadenas no vacías en `codigo` y `nombre`. Índices en `estado` y `es_superadministrador`. Semilla base: `SUPERADMINISTRADOR` (`es_sistema = 1`, `es_superadministrador = 1`).
- `permisos`: Catálogo de capacidades atómicas (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `codigo` VARCHAR(100) NOT NULL UNIQUE, `nombre` VARCHAR(100) NOT NULL, `descripcion` VARCHAR(255) NULL, `modulo` VARCHAR(50) NOT NULL, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP). Convención canónica `recurso.accion`. Índices en `modulo` y `estado`. Semillas base: 10 permisos atómicos iniciales (`usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.bloquear`, `roles.ver`, `roles.crear`, `roles.editar`, `roles.asignar`, `roles.revocar`, `permisos.ver`).
- `roles_permisos`: Asociación N:M entre roles y permisos (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `rol_id` BIGINT UNSIGNED NOT NULL, `permiso_id` BIGINT UNSIGNED NOT NULL, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP). Restricción `UNIQUE (rol_id, permiso_id)`. Claves foráneas con `ON DELETE CASCADE ON UPDATE CASCADE`.
- `usuarios_roles`: Asignación N:M de roles a usuarios (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `usuario_id` BIGINT UNSIGNED NOT NULL, `rol_id` BIGINT UNSIGNED NOT NULL, `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `asignado_por_usuario_id` BIGINT UNSIGNED NULL). Restricción `UNIQUE (usuario_id, rol_id)`. Clave foránea `fk_usuarios_roles_usuario` con `ON DELETE CASCADE`, `fk_usuarios_roles_rol` con `ON DELETE RESTRICT` y `fk_usuarios_roles_asignado_por` con `ON DELETE SET NULL`. Transición determinista en migración 007 para asignar `SUPERADMINISTRADOR` al usuario inicial existente solo si `COUNT(usuarios) = 1`.

## Esquema de Menú Dinámico y Navegación (MENÚ-1 y Migración 008)

- `opciones_menu`: Estructura jerárquica de dos niveles para navegación autorizada de interfaz (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `padre_id` BIGINT UNSIGNED NULL, `clave` VARCHAR(50) NOT NULL UNIQUE, `nombre` VARCHAR(100) NOT NULL, `ruta` VARCHAR(255) NULL, `icono` VARCHAR(50) NULL, `permiso_id` BIGINT UNSIGNED NULL, `orden` INT UNSIGNED NOT NULL DEFAULT 1, `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
- Restricciones e Integridad:
  - Clave foránea autorreferencial `fk_opciones_menu_padre` hacia `opciones_menu(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE`. Impide eliminar o reordenar categorías huérfanas de forma destructiva.
  - Clave foránea `fk_opciones_menu_permiso` hacia `permisos(id)` con `ON DELETE SET NULL ON UPDATE CASCADE`. Si se elimina o depura un permiso opcional, la opción no se destruye sino que desacopla la exigencia de autorización.
  - Restricciones CHECK para cadenas no vacías en `clave` y `nombre`.
  - Índices optimizados: `fk_opciones_menu_padre_idx` en `padre_id`, `fk_opciones_menu_permiso_idx` en `permiso_id`, `idx_opciones_menu_padre_orden` en `(padre_id, orden)` e `idx_opciones_menu_estado_orden` en `(estado, orden)`.
- Semillas base en Migración 008 y `SQL/camargo_pms.sql`:
  - Permisos RBAC: `menu.ver` ("Ver menú de navegación") y `menu.gestionar` ("Gestionar opciones de menú").
  - Opciones de menú:
    - Nivel 1 (Principales): `inicio` (Icono `ti ti-home`), `configuracion` (Icono `ti ti-settings`).
    - Nivel 2 (Secundarias): `inicio_panel` (bajo `inicio`, ruta `/`), `config_menu` (bajo `configuracion`, ruta `/configuracion/menu`, permiso `menu.ver`, estructural `es_sistema = 1`), `config_usuarios` (bajo `configuracion`, ruta `/usuarios`, permiso `usuarios.ver`).

## Esquema del Núcleo Transversal de Auditoría y Actores (AUDITORÍA-1 / AUDITORÍA-1A y Migración 009)

- `actores`: Catálogo polimórfico de sujetos de acción (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `tipo` ENUM('USUARIO','SISTEMA','INTEGRACION','PROVEEDOR_PAGO') NOT NULL, `codigo` VARCHAR(60) NOT NULL UNIQUE, `nombre` VARCHAR(150) NOT NULL, `usuario_id` BIGINT UNSIGNED NULL UNIQUE, `estado` ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO', `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
  - Restricciones CHECK para cadenas no vacías en `codigo` y `nombre`.
  - Clave foránea `fk_actores_usuario` hacia `usuarios(id)` con `ON DELETE SET NULL ON UPDATE CASCADE` (garantiza que el actor humano preserva su existencia ante depuración física de usuario).
  - Semilla base estructural: `CAMARGO_PMS` (`id = 1`, `tipo = 'SISTEMA'`, `codigo = 'CAMARGO_PMS'`, `nombre = 'Camargo PMS — Sistema Central'`).
- `auditoria`: Registro histórico inmutable de trazabilidad y operaciones (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `actor_id` BIGINT UNSIGNED NOT NULL, `usuario_id` BIGINT UNSIGNED NULL, `accion` VARCHAR(60) NOT NULL, `modulo` VARCHAR(60) NOT NULL, `entidad` VARCHAR(60) NOT NULL, `entidad_id` VARCHAR(100) NULL, `descripcion` TEXT NULL, `valores_anteriores` JSON NULL, `valores_nuevos` JSON NULL, `contexto` JSON NULL, `ip` VARCHAR(45) NULL, `user_agent` VARCHAR(500) NULL, `correlacion_id` VARCHAR(64) NULL, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP).
  - Restricciones e Integridad:
    - Clave foránea `fk_auditoria_actor` hacia `actores(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE` (impide borrar actores con historial de auditoría).
    - Clave foránea `fk_auditoria_usuario` hacia `usuarios(id)` con `ON DELETE SET NULL ON UPDATE CASCADE` (desacopla auditoría del ciclo de vida del usuario).
    - Restricciones CHECK: `chk_auditoria_accion_no_vacia`, `chk_auditoria_modulo_no_vacio`, `chk_auditoria_entidad_no_vacia`.
    - Índices: `idx_auditoria_actor_id`, `idx_auditoria_usuario_id`, `idx_auditoria_accion`, `idx_auditoria_modulo`, `idx_auditoria_entidad`, `idx_auditoria_correlacion_id`, `idx_auditoria_creado_en`.
    - Política de ciclo de vida: las cuentas de usuario no se eliminan físicamente en operación normal; se gestionan mediante estados `ACTIVO`, `INACTIVO`, `BLOQUEADO`. La combinación de `SET NULL` en usuario y `RESTRICT` en actor asegura inmutabilidad estricta del historial de auditoría.

## Esquema del Núcleo Central de Configuración (CONFIGURACIÓN-1 y Migración 010)

- `configuraciones`: Catálogo tipado y normalizado de parámetros funcionales del PMS (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `clave` VARCHAR(100) NOT NULL UNIQUE, `grupo` VARCHAR(50) NOT NULL, `nombre` VARCHAR(100) NOT NULL, `descripcion` TEXT NULL, `tipo` ENUM('TEXTO', 'ENTERO', 'DECIMAL', 'BOOLEANO', 'FECHA', 'HORA', 'JSON') NOT NULL DEFAULT 'TEXTO', `valor` LONGTEXT NULL, `valor_predeterminado` LONGTEXT NULL, `editable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1, `es_sensible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, `orden` INT UNSIGNED NOT NULL DEFAULT 1, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
- Restricciones e Integridad:
  - Restricciones CHECK de cadenas no vacías: `chk_configuraciones_clave_no_vacia`, `chk_configuraciones_nombre_no_vacio`, `chk_configuraciones_grupo_no_vacio`.
  - Índices: `idx_configuraciones_grupo`, `idx_configuraciones_estado`, `idx_configuraciones_orden (grupo, orden)`.
  - Parámetros protegidos por el sistema (`editable = 0`) no son mutables por usuarios ni API.
  - Parámetros sensibles (`es_sensible = 1`) no exponen su contenido en auditoría.
- Permisos RBAC introducidos: `configuracion.ver`, `configuracion.editar`.
- Opción de menú introducida: `config_sistema` ('Configuración General', `/configuracion/sistema`, orden 1 bajo `configuracion`).

## Esquema del Maestro Central de Propiedades (PROPIEDADES-1 y Migración 011)

- `propiedades`: Catálogo físico de inmuebles y predios contenedores (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `codigo` VARCHAR(50) NOT NULL UNIQUE, `nombre` VARCHAR(150) NOT NULL, `descripcion` TEXT NULL, `pais_id` INT UNSIGNED NOT NULL, `departamento` VARCHAR(100) NULL, `provincia` VARCHAR(100) NULL, `distrito` VARCHAR(100) NULL, `direccion` VARCHAR(255) NOT NULL, `referencia` VARCHAR(255) NULL, `latitud` DECIMAL(10, 7) NULL, `longitud` DECIMAL(10, 7) NULL, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `observaciones` TEXT NULL, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
- Restricciones e Integridad:
  - Clave foránea `fk_propiedades_pais` hacia `paises(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE`. Impide desasociar o eliminar países con propiedades vinculadas.
  - Restricciones CHECK para cadenas no vacías: `chk_propiedades_codigo_no_vacio`, `chk_propiedades_nombre_no_vacio`, `chk_propiedades_direccion_no_vacia`. La dirección física es obligatoria para garantizar la existencia material del inmueble.
  - Restricciones CHECK para coordenadas GPS geográficas: `chk_propiedades_latitud` `(latitud IS NULL OR (latitud >= -90.0000000 AND latitud <= 90.0000000))` y `chk_propiedades_longitud` `(longitud IS NULL OR (longitud >= -180.0000000 AND longitud <= 180.0000000))`.
  - Índices: `idx_propiedades_estado`, `idx_propiedades_pais_id`, `idx_propiedades_nombre`, `idx_propiedades_departamento`.
  - Principio `PROPIEDAD ≠ REGISTRO DESECHABLE`: Cero eliminación física (`DELETE FROM propiedades` = 0). Preservación histórica mediante alternancia operativa `ACTIVO` ↔ `INACTIVO`.
  - Principio `PROPIEDAD ≠ UNIDAD`: La tabla modela exclusivamente la edificación raíz; no contiene columnas de unidades arrendables, tipologías ni disponibilidad.
- Permisos RBAC introducidos: `propiedades.ver`, `propiedades.crear`, `propiedades.editar`, `propiedades.cambiar_estado`.
- Opciones de menú introducidas:
  - Nivel 1 (Principal): `propiedades` ('Propiedades', icono `ti ti-building`, orden 2, entre Inicio y Configuración).
  - Nivel 2 (Secundaria): `propiedades_catalogo` ('Catálogo de Inmuebles', bajo `propiedades`, ruta `/propiedades`, permiso `propiedades.ver`, orden 1).

## Esquema del Maestro de Unidades y Tipologías (UNIDADES-1 y Migración 012)

- `tipos_unidad`: Catálogo maestro de tipologías arquitectónicas habitacionales y arrendables (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `codigo` VARCHAR(30) NOT NULL UNIQUE, `nombre` VARCHAR(100) NOT NULL, `descripcion` TEXT NULL, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
  - Semillas arquitectónicas iniciales: `DEPARTAMENTO`, `HABITACION`, `CASA`, `SUITE`, `BUNGALOW`.
  - Restricciones CHECK: `chk_tipos_unidad_codigo_no_vacio`, `chk_tipos_unidad_nombre_no_vacio`.
- `unidades`: Catálogo físico de divisiones habitacionales por propiedad (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `propiedad_id` BIGINT UNSIGNED NOT NULL, `tipo_unidad_id` INT UNSIGNED NOT NULL, `codigo` VARCHAR(50) NOT NULL, `nombre` VARCHAR(150) NOT NULL, `descripcion` TEXT NULL, `piso_nivel` VARCHAR(30) NULL, `capacidad_personas` SMALLINT UNSIGNED NOT NULL DEFAULT 1, `dormitorios` SMALLINT UNSIGNED NOT NULL DEFAULT 1, `banos` DECIMAL(3, 1) NOT NULL DEFAULT 1.0, `area_m2` DECIMAL(8, 2) NULL, `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO', `observaciones` TEXT NULL, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
  - Claves foráneas:
    - `fk_unidades_propiedad`: Hacia `propiedades(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE`. Impide eliminar o desasociar propiedades que contengan unidades registradas.
    - `fk_unidades_tipo`: Hacia `tipos_unidad(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE`. Impide eliminar tipologías asignadas.
  - Unicidad:
    - `uq_unidades_propiedad_codigo`: `UNIQUE KEY (propiedad_id, codigo)`. Unicidad scoped por propiedad; el código técnico es único dentro de cada inmueble pero admisible entre propiedades distintas (D-065).
  - Restricciones CHECK:
    - `chk_unidades_codigo_no_vacio`: `codigo <> ''`.
    - `chk_unidades_nombre_no_vacio`: `nombre <> ''`.
    - `chk_unidades_capacidad_positiva`: `capacidad_personas >= 1 AND capacidad_personas <= 100`.
    - `chk_unidades_dormitorios`: `dormitorios >= 0 AND dormitorios <= 50` (permite 0 dormitorios para tipo estudio).
    - `chk_unidades_banos`: `banos >= 0.0 AND banos <= 50.0` (permite fracciones para medios baños).
    - `chk_unidades_area_positiva`: `area_m2 IS NULL OR (area_m2 > 0.00 AND area_m2 <= 99999.99)`.
  - Índices: `idx_unidades_propiedad_id`, `idx_unidades_tipo_unidad_id`, `idx_unidades_estado`, `idx_unidades_capacidad`.
  - Principio `PROPIEDAD ≠ UNIDAD`: La unidad se subordina estructuralmente al inmueble raíz.
  - Principio `UNIDAD ≠ REGISTRO DESECHABLE`: Cero eliminación física (`DELETE FROM unidades` = 0). Ciclo de vida gobernado por `ACTIVO` / `INACTIVO`.
  - Principio `UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD`: Cero fechas, calendarios, precios ni bloqueos en esta fase (P-004, P-005, P-006 abiertas).
- Permisos RBAC introducidos: `unidades.ver`, `unidades.crear`, `unidades.editar`, `unidades.cambiar_estado`.
- Opciones de menú introducidas:
  - Nivel 2 (Secundaria): `unidades_catalogo` ('Unidades Habitacionales', bajo `propiedades`, ruta `/unidades`, icono `ti ti-door`, permiso `unidades.ver`, orden 2).

## Esquema del Motor de Disponibilidad e Inventario Diario (DISPONIBILIDAD-1 y Migración 013)

- `propiedades.zona_horaria`: Columna agregada a la tabla `propiedades` (`VARCHAR(50) NULL DEFAULT NULL AFTER direccion`) para almacenar identificadores IANA de huso horario local (ej. `America/Lima`). Si es `NULL`, hereda la zona predeterminada del PMS (`operacion.zona_horaria_predeterminada`).
- `configuraciones`: Parámetro añadido `'operacion.zona_horaria_predeterminada'` (Grupo `OPERACION`, Tipo `TEXTO`, Valor `'America/Lima'`).
- `bloqueos_unidad`: Maestro de indisponibilidades técnicas y administrativas de unidades (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `unidad_id` BIGINT UNSIGNED NOT NULL, `fecha_inicio` DATE NOT NULL, `fecha_fin` DATE NOT NULL, `noches` INT UNSIGNED NOT NULL, `motivo` VARCHAR(255) NOT NULL, `tipo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL', `estado` ENUM('ACTIVO', 'LIBERADO') NOT NULL DEFAULT 'ACTIVO', `creado_por_actor_id` BIGINT UNSIGNED NULL, `liberado_por_actor_id` BIGINT UNSIGNED NULL, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `liberado_en` DATETIME NULL).
  - Claves foráneas:
    - `fk_bloqueos_unidad`: Hacia `unidades(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE`. Impide eliminar unidades con bloqueos asociados.
    - `fk_bloqueos_actor_creador`: Hacia `actores(id)` con `ON DELETE SET NULL ON UPDATE CASCADE`. Trazabilidad D-061 del actor creador.
    - `fk_bloqueos_actor_liberador`: Hacia `actores(id)` con `ON DELETE SET NULL ON UPDATE CASCADE`. Trazabilidad D-061 del actor liberador.
  - Restricciones CHECK:
    - `chk_bloqueos_fechas`: `fecha_fin > fecha_inicio`.
    - `chk_bloqueos_noches`: `noches >= 1`.
    - `chk_bloqueos_motivo_no_vacio`: `motivo <> ''`.
  - Índices: `idx_bloqueos_unidad_id`, `idx_bloqueos_estado`, `idx_bloqueos_rango (fecha_inicio, fecha_fin)`.
  - Principio de preservación histórica: Cero eliminación física del registro maestro (`DELETE FROM bloqueos_unidad` inexistente); el ciclo de vida transita de `ACTIVO` a `LIBERADO` preservando auditoría y fechas.
- `inventario_diario_unidades`: Inventario físico sparse noche por noche (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `unidad_id` BIGINT UNSIGNED NOT NULL, `fecha` DATE NOT NULL, `tipo_bloqueo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL', `origen_tipo` VARCHAR(50) NOT NULL DEFAULT 'BLOQUEO_MANUAL', `origen_id` BIGINT UNSIGNED NOT NULL, `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP).
  - Claves foráneas:
    - `fk_inventario_unidad`: Hacia `unidades(id)` con `ON DELETE RESTRICT ON UPDATE CASCADE`.
  - Unicidad inviolable en base de datos (D-067):
    - `uq_inventario_unidad_fecha`: `UNIQUE KEY (unidad_id, fecha)`. Garantía matemática absoluta contra colisiones de concurrencia y sobreventa a nivel de motor InnoDB.
  - Índices: `idx_inventario_fecha`, `idx_inventario_origen (origen_tipo, origen_id)`, `idx_inventario_unidad_fecha (unidad_id, fecha)`.
  - Principio sparse y liberación atómica: Solo contiene registros de noches bloqueadas. La liberación o cancelación ejecuta `DELETE ... WHERE origen_tipo = 'BLOQUEO_MANUAL' AND origen_id = ?`, restituyendo la disponibilidad de forma inmediata.
  - Principio de preservación de P-005: Cero columnas de tarifa, precio, costo, moneda, impuesto o recargo.
- Permisos RBAC introducidos: `disponibilidad.ver`, `disponibilidad.bloquear`, `disponibilidad.liberar`.
- Opciones de menú introducidas:
  - Nivel 2 (Secundaria): `disponibilidad_calendario` ('Disponibilidad', bajo `propiedades`, ruta `/disponibilidad`, icono `ti ti-calendar-event`, permiso `disponibilidad.ver`, orden 3).

## Reglas

- Claves primarias estables y claves foráneas explícitas.
- Restricciones `NOT NULL`, `UNIQUE`, `CHECK` y FK cuando expresen una invariante real y sean compatibles con la versión seleccionada.
- Índices derivados de consultas justificadas; evitar índices especulativos.
- Importes obligatoriamente en `DECIMAL`, prohibición terminante de `FLOAT` y `DOUBLE`.
- Fechas y horas almacenadas con una política única; intercambio ISO 8601 con zona explícita.
- Estados mediante catálogos o códigos estables con transiciones controladas; no texto libre.
- No usar campos JSON para evitar relaciones que deben ser consultables o validadas.

## Contrato de Tipos Monetarios y Financieros (GATE FINANCIERO-1 / D-069 — cierra P-005)

En preparación para los módulos transaccionales (`RESERVAS-1`, `TARIFAS`, `CAJA`, `COBROS`), el esquema relacional de Camargo PMS formaliza las siguientes reglas vinculantes de persistencia financiera:

1. **Tipos de Columna y Escala:**
   - **Importes Comerciales y Saldos:** `DECIMAL(15,2)` obligatorio (representando céntimos con rango hasta $999,999,999,999.99$).
   - **Tarifas Unitarias Base, Tasas Tributarias y Consumos:** `DECIMAL(15,4)` obligatorio para evitar pérdidas de precisión por truncamiento en cálculos intermedios (ej. lecturas de medidor kWh, m³, coeficientes de descuento y tasas impositivas como `0.1800` para 18% IGV).
   - **Código de Moneda Explícito:** Toda tabla que contenga columnas de importe o tarifa debe incluir la columna `moneda_codigo VARCHAR(3) NOT NULL` (o `CHAR(3)`) con código alfabético canónico según la norma ISO 4217 (semilla base: `'PEN'`). Se prohíben importes sin divisa explícita.
   - **Desacoplamiento de Símbolo:** El símbolo `'S/'` no se almacena en base de datos; pertenece exclusivamente a la capa de formateo y presentación visual.

2. **Prohibición de Coma Flotante:**
   - Queda prohibido el uso de `FLOAT`, `DOUBLE` o `REAL` para valores monetarios tanto en DDL como en expresiones SQL.

3. **Aritmética y Redondeo en Base de Datos:**
   - En MySQL 8.4, operaciones con literales y columnas `DECIMAL` conservan precisión exacta. La multiplicación `DECIMAL(M1,D1) * DECIMAL(M2,D2)` expande automáticamente la escala a $D1+D2$ decimales preservando precisión intermedia.
   - Redondeo comercial estándar: función `ROUND(expr, 2)` implementa `ROUND_HALF_UP` en aritmética exacta sobre números positivos.

4. **Inmutabilidad y Preservación Histórica:**
   - Las operaciones emitidas (reservas, contratos, consumos, recibos) persisten snapshots congelados de: tarifa unitaria aplicada, base imponible, tasa impositiva, monto de impuesto y total.
   - Los cambios futuros en catálogos de tarifas o reformas en tasas fiscales no alteran ni recalculan operaciones históricas emitidas.
   - **Preservación Contable:** Cero eliminación física (`DELETE FROM`) en cobros, pagos, asientos o movimientos de caja; las anulaciones operativas se registran mediante transiciones a estado `ANULADO` con motivo justificado o mediante contra-asientos compensatorios (D-061).

## Históricos

Las operaciones emitidas congelan los valores necesarios para reproducirlas: tarifa, descripción, precio, costo, proveedor, versión contractual, impuestos y datos relevantes. Cambiar el catálogo afecta operaciones futuras, no documentos pasados.

La configuración y tarifas sensibles a vigencia usan `vigente_desde`, `vigente_hasta` o versión equivalente. No sobrescribir una fila histórica si altera el significado de registros ya emitidos.

## Disponibilidad y concurrencia (Implementado — DISPONIBILIDAD-1 / D-066, D-067 y D-068)

Confirmar reserva, estancia, arrendamiento o bloqueo es una operación crítica. En DISPONIBILIDAD-1 opera el **Modelo Híbrido con Inventario Diario Sparse**:

1. **Tabla de inventario diario (`inventario_diario_unidades`):**
   - Estructura: `id BIGINT AUTO_INCREMENT PRIMARY KEY`, `unidad_id BIGINT UNSIGNED NOT NULL`, `fecha DATE NOT NULL`, `tipo_bloqueo ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL`, `origen_tipo VARCHAR(50) NOT NULL`, `origen_id BIGINT UNSIGNED NOT NULL`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`.
   - Restricción defensiva inviolable: `UNIQUE KEY uq_inventario_unidad_fecha (unidad_id, fecha)`.
   - Modelo *sparse*: solo contiene filas para noches ocupadas o bloqueadas (no pregenera millones de filas vacías).
   - Disponibilidad formal = ausencia de registro para `(unidad_id, fecha)` en el intervalo semiabierto $[\text{fecha\_entrada}, \text{fecha\_salida})$.
2. **Zona horaria por propiedad:**
   - Columna nullable `zona_horaria VARCHAR(50) NULL DEFAULT NULL` en tabla `propiedades` para almacenar identificadores IANA (ej. `America/Lima`). Si es `NULL`, hereda el valor central del PMS (`operacion.zona_horaria_predeterminada`).
3. **Flujo transaccional obligatorio:**
   - Iniciar transacción PDO (`beginTransaction`).
   - Ordenar inserciones deterministamente: `ORDER BY unidad_id ASC, fecha ASC` (reducción sustancial del riesgo de deadlocks y patrones de bloqueo cruzado).
   - Insertar cada noche del intervalo semiabierto en el inventario.
   - Si colisiona alguna fecha (error de clave duplicada 1062, lock wait timeout 1205 o deadlock 1213), capturar y ejecutar `rollBack()` total inmediato. Mapear a `ConflictoDisponibilidadExcepcion` (HTTP 409).
   - Verificación empírica en MySQL 8.4.3 LTS: comprobado que tanto el error 1205 (lock wait timeout) como el 1213 (deadlock detectado por InnoDB) ejecutan rollback completo e inmediato y emiten `ConflictoDisponibilidadExcepcion` (HTTP 409).
   - Si todas las noches se persisten exitosamente, ejecutar `commit()`.
4. **Liberación atómica:**
   - La cancelación o liberación ejecuta `DELETE FROM inventario_diario_unidades WHERE origen_tipo IN ('BLOQUEO_MANUAL', 'RESERVA') AND origen_id = ?`, liberando las noches de forma inmediata sin residuos.

## Reservas directas y multiunidad (Implementado — RESERVAS-1 / D-070)

En RESERVAS-1 se introduce el núcleo transaccional comercial de reservas directas con soporte multiunidad y snapshot financiero inmutable:

1. **Tabla de reservas cabecera (`reservas`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(32) NOT NULL UNIQUE`, `persona_titular_id BIGINT UNSIGNED NOT NULL`, `fecha_entrada DATE NOT NULL`, `fecha_salida DATE NOT NULL`, `noches INT UNSIGNED NOT NULL`, `estado ENUM('PENDIENTE','CONFIRMADA','CANCELADA','EXPIRADA') NOT NULL DEFAULT 'PENDIENTE'`, `expira_en DATETIME NULL DEFAULT NULL`, `canal ENUM('DIRECTO_PMS','DIRECTO_WEB','DIRECTO_WHATSAPP','DIRECTO_TELEFONO','OTRO') NOT NULL DEFAULT 'DIRECTO_PMS'`, `origen VARCHAR(50) NOT NULL DEFAULT 'PMS'`, `moneda_codigo VARCHAR(3) NOT NULL DEFAULT 'PEN'`, `subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `impuesto DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `total DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `observaciones TEXT NULL`, `motivo_cancelacion VARCHAR(255) NULL`, `cancelado_en DATETIME NULL`, `cancelado_por_actor_id BIGINT UNSIGNED NULL`, `confirmado_en DATETIME NULL`, `confirmado_por_actor_id BIGINT UNSIGNED NULL`, `creado_por_actor_id BIGINT UNSIGNED NOT NULL`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - Restricciones FK:
     - `fk_reservas_persona_titular`: `FOREIGN KEY (persona_titular_id) REFERENCES personas(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_reservas_creado_actor`: `FOREIGN KEY (creado_por_actor_id) REFERENCES actores(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_reservas_confirmado_actor`: `FOREIGN KEY (confirmado_por_actor_id) REFERENCES actores(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_reservas_cancelado_actor`: `FOREIGN KEY (cancelado_por_actor_id) REFERENCES actores(id) ON DELETE RESTRICT ON UPDATE CASCADE`
   - Índices para alto desempeño: `idx_reservas_fechas (fecha_entrada, fecha_salida)`, `idx_reservas_estado (estado)`, `idx_reservas_titular (persona_titular_id)`, `idx_reservas_expiracion (estado, expira_en)`.

2. **Tabla de unidades de reserva (`reserva_unidades`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `reserva_id BIGINT UNSIGNED NOT NULL`, `unidad_id BIGINT UNSIGNED NOT NULL`, `precio_unitario_noche DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `noches INT UNSIGNED NOT NULL DEFAULT 1`, `subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `impuesto DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `total DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`.
   - Restricciones FK:
     - `fk_reserva_unidades_reserva`: `FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_reserva_unidades_unidad`: `FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE RESTRICT ON UPDATE CASCADE`
   - Clave única de protección: `UNIQUE KEY uq_reserva_unidad (reserva_id, unidad_id)`.

3. **Modificación a `inventario_diario_unidades`:**
   - La columna `tipo_bloqueo` se amplía a: `ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO', 'RESERVA') NOT NULL DEFAULT 'BLOQUEO_MANUAL'`.

4. **Parámetro de configuración de hold (RESERVAS-1A):**
   - Parámetro operacional `reservas.duracion_hold_minutos` registrado en tabla `configuraciones` (tipo `ENTERO`, grupo `OPERACION`) con valor inicial `NULL`. El sistema no asume ni inventa valores predeterminados (como 30 minutos). Si no se encuentra configurado explícitamente por el negocio, la creación de reservas `PENDIENTE` es rechazada de forma segura (`ConfiguracionFaltanteExcepcion`, HTTP 422).

5. **Contrato fiscal y snapshot provisional (RESERVAS-1A):**
   - Al no existir aún un motor o fuente tributaria formal en Camargo PMS, toda reserva almacena provisoriamente `impuesto = 0.00` (ningún impuesto aplicado por el PMS, sin asignar clasificaciones tributarias prematuras como gravada, exonerada o inafecta) y $\text{total} = \text{subtotal}$ en `DECIMAL(15,2)`. El snapshot conserva las columnas tributarias preparadas para cuando exista un proveedor o regla impositiva formal.

## Estadías, Check-in y Registro de Huéspedes (Implementado — ESTADÍAS-1 / D-072)

En ESTADÍAS-1 se introduce la gestión operativa de ocupación física real, check-in, llaves, huéspedes y check-out:

1. **Tabla de estadías (`estadias`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(30) NOT NULL UNIQUE`, `reserva_id BIGINT UNSIGNED NOT NULL`, `reserva_unidad_id BIGINT UNSIGNED NOT NULL UNIQUE`, `unidad_id BIGINT UNSIGNED NOT NULL`, `fecha_entrada DATE NOT NULL`, `fecha_salida_prevista DATE NOT NULL`, `estado ENUM('EN_CURSO', 'FINALIZADA', 'ANULADA') NOT NULL DEFAULT 'EN_CURSO'`, `checkin_en DATETIME NOT NULL`, `checkin_por_actor_id BIGINT UNSIGNED NOT NULL`, `checkout_en DATETIME NULL`, `checkout_por_actor_id BIGINT UNSIGNED NULL`, `identificador_llave VARCHAR(50) NULL`, `observaciones_checkin TEXT NULL`, `observaciones_checkout TEXT NULL`, `motivo_anulacion VARCHAR(255) NULL`, `anulada_en DATETIME NULL`, `anulada_por_actor_id BIGINT UNSIGNED NULL`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - Restricciones FK:
     - `fk_estadias_reserva`: `FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_estadias_reserva_unidad`: `FOREIGN KEY (reserva_unidad_id) REFERENCES reserva_unidades(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_estadias_unidad`: `FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_estadias_actor_checkin`: `FOREIGN KEY (checkin_por_actor_id) REFERENCES actores(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_estadias_actor_checkout`: `FOREIGN KEY (checkout_por_actor_id) REFERENCES actores(id) ON DELETE SET NULL ON UPDATE CASCADE`
     - `fk_estadias_actor_anulador`: `FOREIGN KEY (anulada_por_actor_id) REFERENCES actores(id) ON DELETE SET NULL ON UPDATE CASCADE`
   - Restricciones CHECK:
     - `chk_estadias_codigo_no_vacio`: `CHECK (codigo <> '')`
     - `chk_estadias_fechas`: `CHECK (fecha_salida_prevista > fecha_entrada)`
   - Claves e Índices:
     - `uq_estadias_codigo`: `UNIQUE KEY (codigo)`
     - `uq_estadias_reserva_unidad`: `UNIQUE KEY (reserva_unidad_id)`
     - `idx_estadias_reserva_id (reserva_id)`, `idx_estadias_unidad_id (unidad_id)`, `idx_estadias_estado (estado)`, `idx_estadias_fechas (fecha_entrada, fecha_salida_prevista)`, `idx_estadias_checkin_en (checkin_en)`.

2. **Tabla de huéspedes de la estadía (`estadia_huespedes`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `estadia_id BIGINT UNSIGNED NOT NULL`, `persona_id BIGINT UNSIGNED NOT NULL`, `es_responsable TINYINT(1) NOT NULL DEFAULT 0`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`.
   - Restricciones FK:
     - `fk_estadia_huespedes_estadia`: `FOREIGN KEY (estadia_id) REFERENCES estadias(id) ON DELETE RESTRICT ON UPDATE CASCADE`
     - `fk_estadia_huespedes_persona`: `FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE RESTRICT ON UPDATE CASCADE`
   - Clave única de prevención de duplicados:
     - `uq_estadia_persona`: `UNIQUE KEY (estadia_id, persona_id)`
   - Índices: `idx_estadia_huespedes_estadia (estadia_id)`, `idx_estadia_huespedes_persona (persona_id)`.

3. **Estado general de la base de datos tras ESTADÍAS-1:**
   - **30 tablas** físicas consolidadas.
   - **43 Foreign Keys** referenciales inviolables (todas con `RESTRICT` o `SET NULL` justificado, cero cascada destructiva en entidades centrales).
   - **35 permisos** RBAC en catálogo (`estadias.ver`, `estadias.checkin`, `estadias.checkout`, `estadias.huespedes`, `estadias.anular`).
   - **15 migraciones** aplicadas (`001` a `015`), cero pendientes.

## Catálogo de Servicios, Proveedores, Consumos y Traslados (Implementado — SERVICIOS-1 / D-073)

En SERVICIOS-1 se implementa el catálogo de servicios complementarios, proveedores homologados, consumos imputados y traslados (Migración `016_servicios.sql`):

1. **Tabla de categorías de servicio (`categorias_servicio`):**
   - Columnas: `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(50) NOT NULL UNIQUE`, `nombre VARCHAR(100) NOT NULL`, `descripcion VARCHAR(255) NULL`, `orden INT UNSIGNED NOT NULL DEFAULT 0`, `activo TINYINT(1) NOT NULL DEFAULT 1`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - Semillas (8): `TRASLADOS`, `ALIMENTACION`, `LIMPIEZA_EXTRA`, `TOURS`, `LAVANDERIA`, `BIENESTAR`, `EQUIPAMIENTO`, `OTROS`.

2. **Tabla de modalidades de cobro (`modalidades_cobro_servicio`):**
   - Columnas: `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(50) NOT NULL UNIQUE`, `nombre VARCHAR(100) NOT NULL`, `descripcion VARCHAR(255) NULL`, `activo TINYINT(1) NOT NULL DEFAULT 1`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`.
   - Semillas (6): `POR_EVENTO`, `POR_PERSONA`, `POR_NOCHE`, `POR_PERSONA_NOCHE`, `POR_HORA`, `POR_UNIDAD`.

3. **Tabla de proveedores externos (`proveedores`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(30) NOT NULL UNIQUE`, `tipo_proveedor ENUM('EMPRESA', 'PERSONA_NATURAL') NOT NULL DEFAULT 'EMPRESA'`, `persona_id BIGINT UNSIGNED NULL`, `razon_social VARCHAR(255) NOT NULL`, `nombre_comercial VARCHAR(255) NULL`, `tipo_documento ENUM('RUC', 'DNI', 'CE', 'PASAPORTE', 'OTRO') NOT NULL DEFAULT 'RUC'`, `numero_documento VARCHAR(30) NOT NULL UNIQUE`, `telefono VARCHAR(50) NULL`, `email VARCHAR(255) NULL`, `direccion VARCHAR(255) NULL`, `contacto_nombre VARCHAR(150) NULL`, `contacto_telefono VARCHAR(50) NULL`, `estado ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO'`, `observaciones TEXT NULL`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - FK: `fk_proveedores_persona`: `FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
   - Cero tipo "INTERNO" ficticio: prestadores propios se marcan en `servicios_contratados` con `es_operacion_interna = 1` y `proveedor_id = NULL`.

4. **Tabla de catálogo de servicios (`servicios`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(30) NOT NULL UNIQUE`, `categoria_id INT UNSIGNED NOT NULL`, `modalidad_cobro_id INT UNSIGNED NOT NULL`, `nombre VARCHAR(150) NOT NULL`, `descripcion TEXT NULL`, `moneda_codigo VARCHAR(3) NOT NULL DEFAULT 'PEN'`, `precio_venta_referencial DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `requiere_proveedor_externo TINYINT(1) NOT NULL DEFAULT 0`, `es_traslado TINYINT(1) NOT NULL DEFAULT 0`, `activo TINYINT(1) NOT NULL DEFAULT 1`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - FKs:
     - `fk_servicios_categoria`: `FOREIGN KEY (categoria_id) REFERENCES categorias_servicio(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_servicios_modalidad`: `FOREIGN KEY (modalidad_cobro_id) REFERENCES modalidades_cobro_servicio(id) ON DELETE RESTRICT ON UPDATE CASCADE`.

5. **Tabla de homologación servicio-proveedor (`servicio_proveedores`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `servicio_id BIGINT UNSIGNED NOT NULL`, `proveedor_id BIGINT UNSIGNED NOT NULL`, `costo_pactado DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `moneda_codigo VARCHAR(3) NOT NULL DEFAULT 'PEN'`, `plazo_pago_dias INT UNSIGNED NOT NULL DEFAULT 0`, `codigo_referencia_proveedor VARCHAR(50) NULL`, `es_preferente TINYINT(1) NOT NULL DEFAULT 0`, `es_preferente_virt TINYINT GENERATED ALWAYS AS (CASE WHEN es_preferente = 1 THEN 1 ELSE NULL END) STORED`, `activo TINYINT(1) NOT NULL DEFAULT 1`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - FKs:
     - `fk_sp_servicio`: `FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_sp_proveedor`: `FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
   - Restricciones UNIQUE:
     - `uq_sp_servicio_proveedor`: `UNIQUE KEY (servicio_id, proveedor_id)`.
     - `uq_sp_servicio_preferente`: `UNIQUE KEY (servicio_id, es_preferente_virt)` (garantía en BD de máximo un preferente).

6. **Tabla de servicios contratados y consumos (`servicios_contratados`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `codigo VARCHAR(30) NOT NULL UNIQUE`, `reserva_id BIGINT UNSIGNED NOT NULL`, `estadia_id BIGINT UNSIGNED NULL`, `servicio_id BIGINT UNSIGNED NOT NULL`, `proveedor_id BIGINT UNSIGNED NULL`, `es_operacion_interna TINYINT(1) NOT NULL DEFAULT 1`, `concepto_servicio VARCHAR(200) NOT NULL`, `cantidad DECIMAL(10,2) NOT NULL DEFAULT 1.00`, `moneda_codigo VARCHAR(3) NOT NULL DEFAULT 'PEN'`, `precio_unitario DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `costo_unitario DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `tasa_impuesto DECIMAL(5,4) NOT NULL DEFAULT 0.0000`, `impuesto_total DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `total DECIMAL(15,2) NOT NULL DEFAULT 0.00`, `fecha_servicio DATE NOT NULL`, `hora_servicio TIME NULL`, `estado ENUM('SOLICITADO', 'CONFIRMADO', 'EJECUTADO', 'CANCELADO') NOT NULL DEFAULT 'SOLICITADO'`, `observaciones TEXT NULL`, `motivo_cancelacion VARCHAR(255) NULL`, `cancelado_en DATETIME NULL`, `cancelado_por_actor_id BIGINT UNSIGNED NULL`, `ejecutado_en DATETIME NULL`, `ejecutado_por_actor_id BIGINT UNSIGNED NULL`, `creado_por_actor_id BIGINT UNSIGNED NOT NULL`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - FKs:
     - `fk_sc_reserva`: `FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_sc_estadia`: `FOREIGN KEY (estadia_id) REFERENCES estadias(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_sc_servicio`: `FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_sc_proveedor`: `FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_sc_actor_creador`: `FOREIGN KEY (creado_por_actor_id) REFERENCES actores(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
     - `fk_sc_actor_cancelador`: `FOREIGN KEY (cancelado_por_actor_id) REFERENCES actores(id) ON DELETE SET NULL ON UPDATE CASCADE`.
     - `fk_sc_actor_ejecutor`: `FOREIGN KEY (ejecutado_por_actor_id) REFERENCES actores(id) ON DELETE SET NULL ON UPDATE CASCADE`.
   - CHECK Constraint:
     - `chk_sc_coherencia_operacion_interna`: `CHECK (((es_operacion_interna = 1 AND proveedor_id IS NULL) OR (es_operacion_interna = 0 AND proveedor_id IS NOT NULL)))`.

7. **Tabla de extensión 1:1 de traslados (`servicio_traslados`):**
   - Columnas: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `servicio_contratado_id BIGINT UNSIGNED NOT NULL UNIQUE`, `tipo_traslado ENUM('LLEGADA', 'SALIDA') NOT NULL`, `origen VARCHAR(255) NOT NULL`, `destino VARCHAR(255) NOT NULL`, `numero_vuelo_transporte VARCHAR(50) NULL`, `pasajeros INT UNSIGNED NOT NULL DEFAULT 1`, `equipaje_piezas INT UNSIGNED NOT NULL DEFAULT 0`, `conductor_nombre VARCHAR(150) NULL`, `vehiculo_placa VARCHAR(20) NULL`, `vehiculo_modelo VARCHAR(100) NULL`, `observaciones_logistica TEXT NULL`, `creado_en DATETIME DEFAULT CURRENT_TIMESTAMP`, `actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
   - FK: `fk_st_servicio_contratado`: `FOREIGN KEY (servicio_contratado_id) REFERENCES servicios_contratados(id) ON DELETE RESTRICT ON UPDATE CASCADE`.

8. **Esquema de Cuentas, Cargos, Pagos y Caja Física (FINANCIERO-2 y Migración 017):**
   - **`cajas_fisicas`:** Catálogo de puntos físicos de custodia de efectivo por propiedad (`id`, `propiedad_id`, `codigo`, `nombre`, `estado`, `creado_en`, `actualizado_en`). FK: `fk_cajas_propiedad`.
   - **`cuentas_bancarias`:** Cuentas corrientes y recaudadoras de la empresa (`id`, `banco_nombre`, `tipo_cuenta`, `moneda_codigo`, `numero_cuenta`, `cci`, `alias`, `estado`, `creado_en`, `actualizado_en`). Semilla: BCP Corriente Soles.
   - **`metodos_pago`:** Catálogo administrable de modalidades de cobro (`id`, `codigo`, `nombre`, `tipo`, `requiere_caja_fisica`, `requiere_cuenta_bancaria`, `requiere_referencia`, `activo`). Semillas: `EFECTIVO`, `TARJETA`, `TRANSFERENCIA`, `DEPOSITO`, `BILLETERA`.
   - **`sesiones_caja`:** Turnos de caja de recepción con control de apertura y cierre (`id`, `caja_fisica_id`, `aperturada_por_actor_id`, `cerrada_por_actor_id`, `aperturada_en`, `cerrada_en`, `monto_apertura`, `monto_ventas_efectivo`, `monto_ingresos_manuales`, `monto_egresos_manuales`, `monto_devoluciones_efectivo`, `monto_cierre_esperado`, `monto_cierre_real`, `diferencia_arqueo`, `resultado_arqueo`, `motivo_diferencia`, `estado`).
   - **`movimientos_caja`:** Libro diario de caja física (`id`, `sesion_caja_id`, `tipo_movimiento`, `origen`, `origen_id`, `monto`, `concepto`, `creado_por_actor_id`, `anulado`, `anulado_por_actor_id`).
   - **`movimientos_bancarios`:** Libro auxiliar bancario (`id`, `cuenta_bancaria_id`, `tipo_movimiento`, `origen`, `origen_id`, `monto`, `numero_operacion`, `concepto`, `fecha_operacion`, `creado_por_actor_id`, `anulado`, `anulado_por_actor_id`).
   - **`cuentas_folios`:** Cuentas maestras de huéspedes vinculadas 1:1 a la reserva (`id`, `reserva_id UNIQUE`, `codigo`, `estado`, `total_cargos`, `total_pagos`, `total_devoluciones`, `saldo_pendiente`, `creado_por_actor_id`, `creado_en`).
   - **`cargos_cuenta`:** Deudas y consumos devengados o provisionales (`id`, `cuenta_folio_id`, `estadia_id NULL`, `servicio_contratado_id NULL`, `tipo_cargo`, `concepto`, `cantidad`, `precio_unitario`, `subtotal`, `tasa_impuesto`, `impuesto_total`, `total`, `monto_aplicado`, `saldo_pendiente`, `estado`, `creado_por_actor_id`).
   - **`pagos_cuenta`:** Ingresos formalizados de dinero (`id`, `cuenta_folio_id`, `metodo_pago_id`, `cuenta_bancaria_id NULL`, `sesion_caja_id NULL`, `movimiento_caja_id NULL`, `movimiento_bancario_id NULL`, `monto_total`, `monto_aplicado`, `monto_devuelto`, `saldo_no_aplicado`, `codigo_transaccion_externa`, `estado`, `creado_por_actor_id`).
   - **`aplicaciones_pago`:** Entidad desacoplada de amortización (`id`, `pago_id`, `cargo_id`, `monto_aplicado`, `reversada`, `reversada_en`, `reversado_por_actor_id`, `motivo_reversion`, `creado_por_actor_id`).
   - **`devoluciones_cuenta`:** Salidas de fondos registradas contra un pago (`id`, `pago_id`, `cuenta_folio_id`, `metodo_pago_id`, `cuenta_bancaria_id NULL`, `sesion_caja_id NULL`, `movimiento_caja_id NULL`, `movimiento_bancario_id NULL`, `monto`, `motivo`, `creado_por_actor_id`).

9. **Estado general de la base de datos tras FINANCIERO-2:**
   - **48 tablas** físicas consolidadas.
   - **92 Foreign Keys** referenciales inviolables.
   - **48 permisos** RBAC en catálogo (`caja.ver`, `caja.aperturar`, `caja.cerrar`, `caja.movimientos`, `caja.cobrar`, `caja.aplicar`, `caja.devolver`, `caja.reversar`).
   - **17 migraciones** aplicadas (`001` a `017`), cero pendientes.

10. **Esquema de Canales de Distribución e iCalendar RFC 5545 (AIRBNB-ICAL-1B y Migración 035):**
    - **`canales_distribucion`:** Catálogo soberano de canales de distribución y OTAs (`id`, `codigo UNIQUE`, `nombre`, `tipo`, `descripcion`, `activo`, `creado_en`, `actualizado_en`). Semillas iniciales: `AIRBNB`, `BOOKING`, `VRBO`, `EXPEDIA`, `DIRECTO`, `OTRO`.
    - **`conexiones_ical`:** Conexiones bilaterales de sincronización por unidad física (`id`, `canal_id`, `unidad_id`, `nombre`, `url_importacion_cifrada`, `token_exportacion_hash`, `token_exportacion_cifrado`, `importacion_habilitada`, `exportacion_habilitada`, `frecuencia_minutos`, `ultima_sincronizacion_en`, `ultimo_estado`, `ultimo_mensaje_error`, `estado`, `creado_por_actor_id`, `creado_en`, `actualizado_en`). Claves foráneas: `fk_cical_canal`, `fk_cical_unidad`, `fk_cical_actor`. Índices únicos: `uq_cical_canal_unidad` (un canal por unidad activa), `uq_cical_token_hash` ($O(1)$ para exportación segura).
    - **`eventos_ical_externos`:** Entidades de bloqueo iCalendar desacopladas (`id`, `conexion_id`, `uid_externo`, `resumen`, `fecha_inicio`, `fecha_fin`, `noches`, `es_todo_el_dia`, `estado_evento`, `estado_bloqueo`, `rrule`, `payload_bruto`, `primera_sincronizacion_en`, `ultima_sincronizacion_en`, `creado_en`, `actualizado_en`). Claves foráneas: `fk_eical_conexion`. Clave única de idempotencia: `uq_eical_conexion_uid (conexion_id, uid_externo)`.
    - **`sincronizaciones_ical_log`:** Telemetría inmutable de sincronización (`id`, `conexion_id`, `direccion`, `iniciada_en`, `finalizada_en`, `duracion_ms`, `eventos_detectados`, `eventos_creados`, `eventos_modificados`, `eventos_cancelados`, `eventos_en_conflicto`, `resultado`, `mensaje`, `http_status`, `bytes_procesados`, `creado_en`). Clave foránea: `fk_slog_conexion`.
    - **Cero DDL sobre `inventario_diario_unidades`:** El inventario físico conserva su enumeración canónica; los bloqueos se persisten con `tipo_bloqueo = 'BLOQUEO_MANUAL'`, `origen_tipo = 'EVENTO_ICAL_EXTERNO'` y `origen_id = eventos_ical_externos.id`.
    - **Estado general de la base de datos tras AIRBNB-ICAL-1B / 1D2:**
      - **122 tablas relacionales** físicas consolidadas (118 base + 4 iCal).
      - **Migración 035 (`035_canales_ical.sql`) aplicada**.

11. **Esquema de Tarifas de Alojamiento y Clientes API (WORDPRESS-1B y Migración 036):**
    - **`tarifas_alojamiento`:** Modelo soberano jerárquico de precios por noche (`id`, `propiedad_id`, `tipo_unidad_id`, `unidad_id`, `ambito`, `nombre`, `precio_noche DECIMAL(12,4)`, `moneda CHAR(3)`, `fecha_inicio`, `fecha_fin`, `dias_semana_mascara`, `estancia_minima_noches`, `estado`, `creado_por_actor_id`, `creado_en`, `actualizado_en`). Precedencia: `UNIDAD` > `TIPO_UNIDAD` > `PROPIEDAD`. Claves foráneas: `fk_tarifa_propiedad`, `fk_tarifa_tipo_unidad`, `fk_tarifa_unidad`, `fk_tarifa_actor`.
    - **`api_clientes`:** Directorio de aplicaciones y consumidores externos desacoplados (`id`, `actor_id`, `codigo UNIQUE`, `nombre`, `descripcion`, `contacto_email`, `ips_permitidas`, `limite_peticiones_minuto`, `estado`, `creado_en`, `actualizado_en`). Clave foránea `fk_apiclient_actor` vinculada a `actores(id)` de tipo `INTEGRACION`.
    - **`api_credenciales`:** Credenciales técnicas Bearer seguras (`id`, `api_cliente_id`, `identificador_publico UNIQUE`, `token_hash CHAR(64) UNIQUE`, `token_prefijo VARCHAR(16)`, `nombre`, `estado`, `ultimo_uso_en`, `expira_en`, `creado_en`, `revocado_en`). Búsqueda indexada $O(1)$ por hash SHA-256; token plano jamás almacenado.
    - **`api_scopes`:** Catálogo de alcances/permisos API canónicos (`id`, `codigo UNIQUE`, `nombre`, `descripcion`, `modulo`, `estado`, `creado_en`). Semillas: `disponibilidad.leer`, `cotizacion.crear`, `reservas.hold`, `reservas.confirmar`, `reservas.cancelar`, `reservas.leer`.
    - **`api_credencial_scopes`:** Asociación relacional N:M de alcances por credencial técnica (`credencial_id`, `scope_id`, `asignado_en`). Clave primaria compuesta `(credencial_id, scope_id)`.
    - **Cero DDL sobre inventario ni cotizaciones:** La cotización no inserta registros en la base de datos. Las reservas nacidas de cotización bloquean inventario mediante `inventario_diario_unidades` bajo reglas existentes.
    - **Estado general de la base de datos tras WORDPRESS-1B:**
      - **127 tablas relacionales** físicas consolidadas (122 previas + 5 nuevas de tarifas/API).
      - **Migración 036 (`036_tarifas_clientes_api.sql`) aplicada** con paridad absoluta en `SQL/camargo_pms.sql`.
      - **Ranura de migración 037 estrictamente LIBRE** para fases posteriores.



## Migraciones

- Toda modificación de esquema entra por una migración versionada y revisada.
- Una migración tiene propósito único, precondiciones y reversión o plan de recuperación.
- No editar una migración aplicada en entornos compartidos; crear una nueva.
- Cambios destructivos requieren autorización, respaldo verificado y plan de restauración.
- Semillas contienen catálogos mínimos, nunca datos productivos o credenciales.
- Probar migración desde cero y actualización desde la baseline soportada.

## PDO y repositorios

- Excepciones habilitadas y charset explícito.
- Parámetros enlazados con tipo correcto.
- Consultas en repositorios, no en vistas ni controladores.
- Paginación y límites obligatorios para colecciones potencialmente grandes.
- Evitar `SELECT *` en contratos estables y prevenir consultas N+1.

## Respaldo

Antes de la primera fase con datos reales se debe aprobar política de respaldo, cifrado, retención, pruebas de restauración y recuperación ante fallos. Tener un archivo de respaldo sin prueba de restauración no satisface este requisito.
