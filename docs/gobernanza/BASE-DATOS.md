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
- Importes en `DECIMAL`, nunca `FLOAT`.
- Fechas y horas almacenadas con una política única; intercambio ISO 8601 con zona explícita.
- Estados mediante catálogos o códigos estables con transiciones controladas; no texto libre.
- No usar campos JSON para evitar relaciones que deben ser consultables o validadas.

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
   - Si todas las noches se persisten exitosamente, ejecutar `commit()`.
4. **Liberación atómica:**
   - La cancelación o liberación ejecuta `DELETE FROM inventario_diario_unidades WHERE origen_tipo = 'BLOQUEO_MANUAL' AND origen_id = ?`, liberando las noches de forma inmediata sin residuos.
5. **Estado de migraciones:**
   - Las migraciones productivas activas son estrictamente `001` a `013` (26 tablas, 29 Foreign Keys).

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
