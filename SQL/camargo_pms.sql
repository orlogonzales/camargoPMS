-- ============================================================================
-- CAMARGO PMS
-- Esquema consolidado oficial
-- Base de datos: camargo_pms
-- Motor: MySQL 8.4.3 LTS
-- Collation: utf8mb4_0900_ai_ci
-- ============================================================================
--
-- Este archivo representa el esquema estructural oficial vigente.
--
-- REGLAS:
-- - Mantener sincronizado con las migraciones oficiales.
-- - No almacenar credenciales ni secretos.
-- - No almacenar datos operativos reales.
-- - Todo cambio estructural debe realizarse mediante una migración
--   controlada cuando corresponda.
-- - Aplicar las convenciones de gobernanza de Camargo PMS.
--
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Tabla técnica: migraciones
-- Control de versiones e historial de migraciones estructurales ejecutadas.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migraciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL UNIQUE,
    `lote` INT UNSIGNED NOT NULL DEFAULT 1,
    `ejecutado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Control técnico de migraciones aplicadas en Camargo PMS';

-- ----------------------------------------------------------------------------
-- 1. Catálogo normalizado de países y nacionalidades
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paises` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_iso2` CHAR(2) NOT NULL,
    `codigo_iso3` CHAR(3) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `nacionalidad` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_paises_iso2` (`codigo_iso2`),
    UNIQUE KEY `uq_paises_iso3` (`codigo_iso3`),
    INDEX `idx_paises_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo normalizado de países y nacionalidades';

-- Semilla estructural: Perú como país base predeterminado
INSERT INTO `paises` (`codigo_iso2`, `codigo_iso3`, `nombre`, `nacionalidad`, `activo`)
VALUES ('PE', 'PER', 'Perú', 'Peruana', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ----------------------------------------------------------------------------
-- 2. Catálogo de tipos de documento de identidad personal (sin RUC)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_documento` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `longitud_exacta` SMALLINT UNSIGNED NULL,
    `longitud_minima` SMALLINT UNSIGNED NULL,
    `longitud_maxima` SMALLINT UNSIGNED NULL,
    `formato_regex` VARCHAR(100) NULL,
    `pais_fijo_id` INT UNSIGNED NULL,
    `pais_emisor_obligatorio` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tipos_documento_pais_fijo` FOREIGN KEY (`pais_fijo_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_tipos_documento_codigo` (`codigo`),
    INDEX `idx_tipos_documento_activo` (`activo`),
    INDEX `idx_tipos_documento_pais_fijo` (`pais_fijo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo dinámico de tipos de documento de identidad para personas naturales';

-- Semilla estructural: Tipos de documento base para personas naturales con su alcance jurisdiccional
INSERT INTO `tipos_documento` (`codigo`, `nombre`, `descripcion`, `longitud_exacta`, `longitud_minima`, `longitud_maxima`, `formato_regex`, `pais_fijo_id`, `pais_emisor_obligatorio`, `activo`) VALUES
('DNI', 'Documento Nacional de Identidad', 'Documento nacional de identidad peruano para personas naturales', 8, 8, 8, '^[0-9]{8}$', 1, 0, 1),
('PASAPORTE', 'Pasaporte', 'Documento de identidad internacional para viajes', NULL, 6, 20, '^[A-Z0-9]{6,20}$', NULL, 1, 1),
('CE', 'Carné de Extranjería', 'Documento oficial para extranjeros residentes en Perú', NULL, 6, 15, '^[A-Z0-9]{6,15}$', 1, 0, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `pais_fijo_id` = VALUES(`pais_fijo_id`),
    `pais_emisor_obligatorio` = VALUES(`pais_emisor_obligatorio`);

-- ----------------------------------------------------------------------------
-- 3. Maestro de personas naturales (Soporta monónimos y nombres internacionales)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombres` VARCHAR(100) NOT NULL,
    `apellido_paterno` VARCHAR(100) NULL,
    `apellido_materno` VARCHAR(100) NULL,
    `fecha_nacimiento` DATE NULL,
    `pais_nacionalidad_id` INT UNSIGNED NULL,
    `direccion` VARCHAR(255) NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_personas_pais_nacionalidad` FOREIGN KEY (`pais_nacionalidad_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_personas_nombres_no_vacio` CHECK (`nombres` <> ''),
    CONSTRAINT `chk_personas_estado_valido` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    INDEX `idx_personas_estado` (`estado`),
    INDEX `idx_personas_pais_nacionalidad` (`pais_nacionalidad_id`),
    INDEX `idx_personas_apellidos_nombres` (`apellido_paterno`, `apellido_materno`, `nombres`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro central de personas naturales';

-- ----------------------------------------------------------------------------
-- 4. Documentos de identificación personal (Con jurisdicción real obligatoria)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas_documentos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_documento_id` INT UNSIGNED NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `pais_emisor_id` INT UNSIGNED NOT NULL,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `fecha_emision` DATE NULL,
    `fecha_vencimiento` DATE NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual generada para garantizar un único documento principal activo por persona
    `uq_persona_principal` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`es_principal` = 1 AND `estado` = 'ACTIVO', `persona_id`, NULL)) VIRTUAL,
    CONSTRAINT `fk_documentos_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_documentos_tipo` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_documentos_pais_emisor` FOREIGN KEY (`pais_emisor_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_documentos_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT `chk_documentos_numero_no_vacio` CHECK (`numero_documento` <> ''),
    -- Unicidad documental internacional: distingue por tipo, país emisor real y número
    UNIQUE KEY `uq_documentos_tipo_pais_numero` (`tipo_documento_id`, `pais_emisor_id`, `numero_documento`),
    UNIQUE KEY `uq_documentos_persona_principal` (`uq_persona_principal`),
    INDEX `idx_documentos_persona` (`persona_id`),
    INDEX `idx_documentos_tipo` (`tipo_documento_id`),
    INDEX `idx_documentos_pais_emisor` (`pais_emisor_id`),
    INDEX `idx_documentos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Documentos de identidad asociados a personas naturales';

-- ----------------------------------------------------------------------------
-- 5. Medios de contacto de personas naturales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas_contactos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_contacto` VARCHAR(20) NOT NULL,
    `valor` VARCHAR(150) NOT NULL,
    `es_whatsapp` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual para garantizar un único contacto principal activo por tipo y persona
    `uq_contacto_tipo_principal` VARCHAR(50) GENERATED ALWAYS AS (IF(`es_principal` = 1 AND `estado` = 'ACTIVO', CONCAT(`persona_id`, '-', `tipo_contacto`), NULL)) VIRTUAL,
    CONSTRAINT `fk_contactos_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_contactos_tipo` CHECK (`tipo_contacto` IN ('TELEFONO', 'EMAIL')),
    CONSTRAINT `chk_contactos_es_whatsapp` CHECK (`es_whatsapp` IN (0, 1)),
    CONSTRAINT `chk_contactos_es_principal` CHECK (`es_principal` IN (0, 1)),
    CONSTRAINT `chk_contactos_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT `chk_contactos_valor_no_vacio` CHECK (`valor` <> ''),
    UNIQUE KEY `uq_contactos_tipo_principal` (`uq_contacto_tipo_principal`),
    INDEX `idx_contactos_persona` (`persona_id`),
    INDEX `idx_contactos_tipo` (`tipo_contacto`),
    INDEX `idx_contactos_valor` (`valor`),
    INDEX `idx_contactos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Medios de contacto para personas naturales';

-- ----------------------------------------------------------------------------
-- 6. Catálogo dinámico de cargos laborales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cargos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cargos_codigo` (`codigo`),
    INDEX `idx_cargos_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo dinámico de cargos laborales de Camargo PMS';

-- Semillas estructurales base de cargos
INSERT INTO `cargos` (`codigo`, `nombre`, `descripcion`, `activo`) VALUES
('ADMINISTRADOR', 'Administrador', 'Responsable general de operación y administración', 1),
('RECEPCIONISTA', 'Recepcionista', 'Atención directa a huéspedes, check-in y check-out', 1),
('RESERVAS', 'Encargado de Reservas', 'Gestión de canales, reservas y ocupación', 1),
('LIMPIEZA', 'Personal de Limpieza', 'Mantenimiento de áreas comunes y habitaciones', 1),
('MANTENIMIENTO', 'Personal de Mantenimiento', 'Mantenimiento técnico e infraestructura física', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ----------------------------------------------------------------------------
-- 7. Entidad Colaborador (Identidad laboral estable de una Persona)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `colaboradores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(20) NOT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_colaboradores_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_colaboradores_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT `chk_colaboradores_codigo_no_vacio` CHECK (`codigo` <> ''),
    -- Cardinalidad estricta 1:1 lógica entre Persona y su registro de Colaborador
    UNIQUE KEY `uq_colaboradores_persona` (`persona_id`),
    UNIQUE KEY `uq_colaboradores_codigo` (`codigo`),
    INDEX `idx_colaboradores_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Identidad laboral estable de personas en Camargo PMS';

-- ----------------------------------------------------------------------------
-- 8. Episodios Laborales (Historial inmutable de períodos de vinculación)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `episodios_laborales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `colaborador_id` BIGINT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `motivo_cese` VARCHAR(255) NULL,
    `observaciones` TEXT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual generada para garantizar exactamente un episodio abierto activo por colaborador
    `uq_colaborador_abierto` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`fecha_fin` IS NULL AND `estado` = 'ACTIVO', `colaborador_id`, NULL)) VIRTUAL,
    CONSTRAINT `fk_episodios_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_episodios_fechas` CHECK (`fecha_fin` IS NULL OR `fecha_fin` >= `fecha_inicio`),
    CONSTRAINT `chk_episodios_estado` CHECK (`estado` IN ('ACTIVO', 'INACTIVO')),
    UNIQUE KEY `uq_episodios_colaborador_abierto` (`uq_colaborador_abierto`),
    INDEX `idx_episodios_colaborador` (`colaborador_id`),
    INDEX `idx_episodios_fechas` (`fecha_inicio`, `fecha_fin`),
    INDEX `idx_episodios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Episodios cronológicos de vinculación laboral';

-- ----------------------------------------------------------------------------
-- 9. Historial de Asignaciones de Cargos por Episodio
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `episodios_laborales_cargos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `episodio_laboral_id` BIGINT UNSIGNED NOT NULL,
    `cargo_id` INT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual generada para garantizar exactamente un cargo abierto por episodio
    `uq_episodio_cargo_abierto` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`fecha_fin` IS NULL, `episodio_laboral_id`, NULL)) VIRTUAL,
    CONSTRAINT `fk_cargos_episodio` FOREIGN KEY (`episodio_laboral_id`) REFERENCES `episodios_laborales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cargos_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_cargos_fechas` CHECK (`fecha_fin` IS NULL OR `fecha_fin` >= `fecha_inicio`),
    UNIQUE KEY `uq_cargos_episodio_abierto` (`uq_episodio_cargo_abierto`),
    INDEX `idx_cargos_episodio` (`episodio_laboral_id`),
    INDEX `idx_cargos_cargo` (`cargo_id`),
    INDEX `idx_cargos_fechas` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial inmutable de cargos ejercidos dentro de cada episodio laboral';

-- ----------------------------------------------------------------------------
-- 10. Cuentas Humanas de Usuario (AUTH-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `nombre_usuario` VARCHAR(50) NOT NULL,
    `contrasena_hash` VARCHAR(255) NOT NULL COMMENT 'Hash criptográfico de la contraseña (soporta PASSWORD_DEFAULT y algoritmos futuros)',
    `estado` ENUM('ACTIVO', 'BLOQUEADO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `ultimo_acceso_en` DATETIME NULL,
    `contrasena_cambiada_en` DATETIME NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_usuarios_persona` UNIQUE (`persona_id`),
    CONSTRAINT `uq_usuarios_nombre_usuario` UNIQUE (`nombre_usuario`),
    CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_usuarios_estado` CHECK (`estado` IN ('ACTIVO', 'BLOQUEADO', 'INACTIVO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cuentas de usuario de acceso para personas humanas';

-- ----------------------------------------------------------------------------
-- 11. Sesiones de Usuario Persistentes y Revocables (AUTH-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones_usuario` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `iniciada_en` DATETIME NOT NULL,
    `ultima_actividad_en` DATETIME NOT NULL,
    `expira_en` DATETIME NOT NULL,
    `revocada_en` DATETIME NULL,
    `motivo_cierre` ENUM(
        'LOGOUT',
        'EXPIRACION_INACTIVIDAD',
        'EXPIRACION_ABSOLUTA',
        'REVOCACION_ADMINISTRATIVA',
        'CAMBIO_CONTRASENA',
        'CAMBIO_ESTADO_USUARIO',
        'DESACTIVACION_PERSONA',
        'OTRO'
    ) NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_sesiones_token_hash` UNIQUE (`token_hash`),
    CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_sesiones_usuario_id` (`usuario_id`),
    INDEX `idx_sesiones_activas` (`usuario_id`, `revocada_en`, `expira_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Sesiones de usuario activas y revocadas';

-- ----------------------------------------------------------------------------
-- 12. Intentos de Autenticación (Throttling y Prevención de Fuerza Bruta)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intentos_autenticacion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre_usuario_normalizado` VARCHAR(50) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
    `intentado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_intentos_ip_fecha` (`ip`, `intentado_en`),
    INDEX `idx_intentos_usuario_fecha` (`nombre_usuario_normalizado`, `intentado_en`),
    INDEX `idx_intentos_limpieza` (`intentado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de intentos de autenticación para rate limiting';

-- ----------------------------------------------------------------------------
-- 13. Roles de Autorización RBAC (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `es_superadministrador` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_roles_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_roles_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_roles_codigo` (`codigo`),
    INDEX `idx_roles_estado` (`estado`),
    INDEX `idx_roles_superadmin` (`es_superadministrador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Roles de autorización RBAC del sistema';

-- ----------------------------------------------------------------------------
-- 14. Catálogo de Permisos Atómicos (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permisos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(100) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `modulo` VARCHAR(50) NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_permisos_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_permisos_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_permisos_codigo` (`codigo`),
    INDEX `idx_permisos_modulo` (`modulo`),
    INDEX `idx_permisos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de permisos atómicos recurso.accion';

-- ----------------------------------------------------------------------------
-- 15. Asignación N:M de Permisos a Roles (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles_permisos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `permiso_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_roles_permisos_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_roles_permisos_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_roles_permisos_rol_permiso` (`rol_id`, `permiso_id`),
    INDEX `idx_roles_permisos_permiso` (`permiso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relación N:M de asignación de permisos a roles';

-- ----------------------------------------------------------------------------
-- 16. Asignación N:M de Roles a Usuarios (ROLES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios_roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `asignado_por_usuario_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `fk_usuarios_roles_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_roles_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_roles_asignado_por` FOREIGN KEY (`asignado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_usuarios_roles_usuario_rol` (`usuario_id`, `rol_id`),
    INDEX `idx_usuarios_roles_rol` (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relación N:M de asignación de roles a usuarios';

-- ----------------------------------------------------------------------------
-- Semillas de Roles y Permisos Iniciales
-- ----------------------------------------------------------------------------
INSERT INTO `roles` (`codigo`, `nombre`, `descripcion`, `estado`, `es_sistema`, `es_superadministrador`) VALUES
('SUPERADMINISTRADOR', 'Superadministrador', 'Acceso total e irrestricto a todas las funciones y recursos del sistema', 'ACTIVO', 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('usuarios.ver', 'Ver usuarios', 'Permite consultar el listado y detalle de cuentas de usuario', 'usuarios', 'ACTIVO', 1),
('usuarios.crear', 'Crear usuarios', 'Permite registrar nuevas cuentas de usuario en el sistema', 'usuarios', 'ACTIVO', 1),
('usuarios.editar', 'Editar usuarios', 'Permite modificar datos de cuentas de usuario existentes', 'usuarios', 'ACTIVO', 1),
('usuarios.bloquear', 'Bloquear usuarios', 'Permite bloquear o desactivar cuentas de usuario', 'usuarios', 'ACTIVO', 1),
('roles.ver', 'Ver roles', 'Permite consultar los roles y sus permisos asociados', 'roles', 'ACTIVO', 1),
('roles.crear', 'Crear roles', 'Permite definir nuevos roles de autorización en el sistema', 'roles', 'ACTIVO', 1),
('roles.editar', 'Editar roles', 'Permite modificar nombres y descripciones de roles', 'roles', 'ACTIVO', 1),
('roles.asignar', 'Asignar roles', 'Permite asignar roles a cuentas de usuario', 'roles', 'ACTIVO', 1),
('roles.revocar', 'Revocar roles', 'Permite revocar roles previamente asignados a usuarios', 'roles', 'ACTIVO', 1),
('permisos.ver', 'Ver permisos', 'Permite consultar el catálogo general de permisos del sistema', 'permisos', 'ACTIVO', 1),
('menu.ver', 'Ver gestión de menú', 'Permite consultar las opciones y estructura del menú de navegación', 'menu', 'ACTIVO', 1),
('menu.gestionar', 'Gestionar opciones de menú', 'Permite crear, modificar, activar, desactivar y ordenar opciones del menú', 'menu', 'ACTIVO', 1),
('configuracion.ver', 'Ver configuraciones del sistema', 'Permite consultar los parámetros y configuraciones del sistema', 'configuracion', 'ACTIVO', 1),
('configuracion.editar', 'Modificar configuraciones del sistema', 'Permite editar y restaurar valores de configuración del sistema', 'configuracion', 'ACTIVO', 1),
('propiedades.ver', 'Ver catálogo y detalle de propiedades', 'Permite consultar el catálogo y los detalles de las propiedades', 'propiedades', 'ACTIVO', 1),
('propiedades.crear', 'Crear nuevas propiedades', 'Permite registrar nuevas propiedades en el sistema', 'propiedades', 'ACTIVO', 1),
('propiedades.editar', 'Modificar propiedades existentes', 'Permite editar la información de las propiedades', 'propiedades', 'ACTIVO', 1),
('propiedades.cambiar_estado', 'Activar o desactivar propiedades', 'Permite alternar el estado operacional entre ACTIVO e INACTIVO de una propiedad', 'propiedades', 'ACTIVO', 1),
('unidades.ver', 'Ver catálogo y detalle de unidades', 'Permite consultar el catálogo y los detalles de las unidades', 'unidades', 'ACTIVO', 1),
('unidades.crear', 'Crear nuevas unidades', 'Permite registrar nuevas unidades habitacionales en el sistema', 'unidades', 'ACTIVO', 1),
('unidades.editar', 'Modificar unidades existentes', 'Permite editar la información física y descriptiva de las unidades', 'unidades', 'ACTIVO', 1),
('unidades.cambiar_estado', 'Activar o desactivar unidades', 'Permite alternar el estado operacional entre ACTIVO e INACTIVO de una unidad', 'unidades', 'ACTIVO', 1),
('disponibilidad.ver', 'Ver disponibilidad e inventario', 'Permite consultar el calendario, estados de ocupación y unidades disponibles', 'disponibilidad', 'ACTIVO', 1),
('disponibilidad.bloquear', 'Crear bloqueos de inventario', 'Permite aplicar bloqueos manuales y técnicos sobre unidades para fechas determinadas', 'disponibilidad', 'ACTIVO', 1),
('disponibilidad.liberar', 'Liberar bloqueos de inventario', 'Permite levantar bloqueos previamente aplicados y restablecer la disponibilidad', 'disponibilidad', 'ACTIVO', 1),
('reservas.ver', 'Ver reservas', 'Permite consultar el catálogo, filtros y detalles de reservas', 'reservas', 'ACTIVO', 1),
('reservas.crear', 'Crear reservas directas', 'Permite crear nuevas reservas directas multiunidad en el sistema', 'reservas', 'ACTIVO', 1),
('reservas.confirmar', 'Confirmar reservas', 'Permite cambiar el estado de reservas de PENDIENTE a CONFIRMADA', 'reservas', 'ACTIVO', 1),
('reservas.cancelar', 'Cancelar reservas', 'Permite cancelar reservas y liberar el inventario diario asociado', 'reservas', 'ACTIVO', 1),
('reservas.expirar', 'Expirar reservas vencidas', 'Permite ejecutar la expiración manual o por comando de reservas con hold vencido', 'reservas', 'ACTIVO', 1),
('estadias.ver', 'Ver estadías y ocupación', 'Permite consultar el catálogo, filtros y detalles de estadías', 'estadias', 'ACTIVO', 1),
('estadias.checkin', 'Realizar check-in de estadías', 'Permite efectuar el check-in físico de unidades reservadas', 'estadias', 'ACTIVO', 1),
('estadias.checkout', 'Realizar check-out de estadías', 'Permite registrar la salida física y devolución de llaves de unidades en curso', 'estadias', 'ACTIVO', 1),
('estadias.huespedes', 'Gestionar huéspedes de estadía', 'Permite actualizar acompañantes y responsable en estadías en curso', 'estadias', 'ACTIVO', 1),
('estadias.anular', 'Anular check-in de estadía', 'Permite anular excepcionalmente una estadía en curso con justificación obligatoria', 'estadias', 'ACTIVO', 1),
('servicios.ver', 'Ver catálogo y consumos de servicios', 'Consultar catálogo de servicios, proveedores y consumos contratados', 'servicios', 'ACTIVO', 1),
('servicios.gestionar', 'Gestionar catálogo de servicios y proveedores', 'Crear y modificar servicios del catálogo y proveedores homologados', 'servicios', 'ACTIVO', 1),
('servicios.contratar', 'Contratar servicios y consumos', 'Registrar contratación de servicios para reservas y estadías', 'servicios', 'ACTIVO', 1),
('servicios.ejecutar', 'Ejecutar servicios contratados', 'Marcar servicios como ejecutados/entregados físicamente', 'servicios', 'ACTIVO', 1),
('servicios.cancelar', 'Cancelar contratación de servicios', 'Anular contratación de servicios con registro de motivo', 'servicios', 'ACTIVO', 1),
('caja.ver', 'Ver módulos de caja, folios y finanzas', 'Consultar folios, cargos, pagos y sesiones de caja', 'caja', 'ACTIVO', 1),
('caja.aperturar', 'Aperturar sesiones de caja', 'Iniciar turnos de caja física con fondo inicial', 'caja', 'ACTIVO', 1),
('caja.cerrar', 'Arqueo y cierre de sesiones de caja', 'Conteo de efectivo y cierre irreversible de turnos', 'caja', 'ACTIVO', 1),
('caja.movimientos', 'Registrar movimientos de caja', 'Registrar ingresos y egresos de efectivo manuales', 'caja', 'ACTIVO', 1),
('caja.cobrar', 'Registrar cobros y pagos a cuentas', 'Recibir fondos en efectivo, tarjeta o banco', 'caja', 'ACTIVO', 1),
('caja.aplicar', 'Imputar pagos a cargos específicos', 'Vincular cobros a cargos de alojamiento o servicios', 'caja', 'ACTIVO', 1),
('caja.devolver', 'Registrar devoluciones y reembolsos', 'Emitir devoluciones reales de fondos al cliente', 'caja', 'ACTIVO', 1),
('caja.reversar', 'Reversar pagos y anular cargos', 'Operaciones compensatorias y correcciones de auditoría', 'caja', 'ACTIVO', 1),
('arrendamientos.ver', 'Ver contratos de arrendamiento', 'Consultar listado, detalle y estados de arrendamientos', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.crear', 'Crear borradores de arrendamiento', 'Formular nuevos contratos con unidad, fechas y sujetos', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.activar', 'Activar contratos de arrendamiento', 'Poner en vigencia contratos y materializar bloqueos de inventario', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.gestionar', 'Gestionar sujetos y prórrogas', 'Agregar cotitulares, ocupantes y extender plazos contractuales', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.rescindir', 'Rescindir o finalizar contratos', 'Terminación anticipada con causa o cierre regular de contrato', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.generar_cargos', 'Generar cuotas periódicas de renta', 'Emitir cargos mensuales y liquidar fondos de custodia', 'arrendamientos', 'ACTIVO', 1),
('mantenimiento.ver', 'Ver panel y órdenes de mantenimiento', 'Consultar incidencias, órdenes de trabajo y estados', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.incidencias.reportar', 'Reportar incidencias', 'Registrar desperfectos físicos en propiedades o unidades', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.incidencias.gestionar', 'Gestionar incidencias', 'Evaluar, clasificar y desestimar reportes de incidencias', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.crear', 'Crear órdenes de trabajo', 'Formular órdenes preventivas o correctivas', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.programar', 'Programar y asignar órdenes', 'Fijar fechas, asignar técnico/proveedor y autorizar bloqueo', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.ejecutar', 'Ejecutar órdenes de trabajo', 'Iniciar trabajos y registrar costos reales de materiales y mano de obra', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.cerrar', 'Cerrar y aprobar órdenes', 'Finalizar trabajos, registrar notas de cierre y liberar inventario', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.cancelar', 'Cancelar órdenes de trabajo', 'Cancelar órdenes con motivo y liberar inventario bloqueado', 'mantenimiento', 'ACTIVO', 1),
('inventario.ver', 'Ver panel y existencias de inventario', 'Consultar artículos, existencias, kardex, activos y dotaciones', 'inventario', 'ACTIVO', 1),
('inventario.articulos.gestionar', 'Gestionar catálogo de artículos', 'Crear y editar artículos y unidades de medida', 'inventario', 'ACTIVO', 1),
('inventario.ubicaciones.gestionar', 'Gestionar almacenes y ubicaciones', 'Crear y administrar almacenes, bodegas y ubicaciones', 'inventario', 'ACTIVO', 1),
('inventario.movimientos.registrar', 'Registrar movimientos de inventario', 'Registrar entradas, consumos, mermas y ajustes de stock', 'inventario', 'ACTIVO', 1),
('inventario.traslados.ejecutar', 'Ejecutar traslados entre ubicaciones', 'Transferir stock entre almacenes, unidades y custodias externas', 'inventario', 'ACTIVO', 1),
('inventario.activos.gestionar', 'Gestionar activos serializables', 'Registrar, asignar, transferir y dar de baja activos fijos', 'inventario', 'ACTIVO', 1),
('inventario.dotaciones.gestionar', 'Gestionar dotaciones estándar', 'Configurar dotaciones reglamentarias de unidades', 'inventario', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND (
      p.`codigo` IN (
          'reservas.ver', 'reservas.crear', 'reservas.confirmar', 'reservas.cancelar', 'reservas.expirar',
          'estadias.ver', 'estadias.checkin', 'estadias.checkout', 'estadias.huespedes', 'estadias.anular',
          'servicios.ver', 'servicios.gestionar', 'servicios.contratar', 'servicios.ejecutar', 'servicios.cancelar',
          'caja.ver', 'caja.aperturar', 'caja.cerrar', 'caja.movimientos', 'caja.cobrar', 'caja.aplicar', 'caja.devolver', 'caja.reversar',
          'arrendamientos.ver', 'arrendamientos.crear', 'arrendamientos.activar', 'arrendamientos.gestionar', 'arrendamientos.rescindir', 'arrendamientos.generar_cargos'
      )
      OR p.`codigo` LIKE 'mantenimiento.%'
      OR p.`codigo` LIKE 'inventario.%'
  )
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 17. Opciones de Menú y Navegación Dinámica (MENÚ-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `opciones_menu` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `padre_id` BIGINT UNSIGNED NULL,
    `clave` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `icono` VARCHAR(100) NULL,
    `ruta` VARCHAR(255) NULL,
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `permiso_id` BIGINT UNSIGNED NULL,
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_opciones_menu_clave_no_vacia` CHECK (`clave` <> ''),
    CONSTRAINT `chk_opciones_menu_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_opciones_menu_clave` (`clave`),
    INDEX `idx_opciones_menu_padre` (`padre_id`),
    INDEX `idx_opciones_menu_permiso` (`permiso_id`),
    INDEX `idx_opciones_menu_orden` (`padre_id`, `orden`),
    INDEX `idx_opciones_menu_estado` (`estado`),
    CONSTRAINT `fk_opciones_menu_padre` FOREIGN KEY (`padre_id`) REFERENCES `opciones_menu` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_opciones_menu_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Opciones y estructura de navegación dinámica autorizada del sistema';

-- Semillas Estructurales del Menú (Nivel 1 y Nivel 2)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'inicio', 'Inicio', 'fa-solid fa-house', NULL, 1, 'ACTIVO', NULL, 1),
(NULL, 'configuracion', 'Configuración', 'fa-solid fa-gear', NULL, 99, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'inicio_panel', 'Panel General', 'fa-solid fa-gauge-high', '/', 1, 'ACTIVO', NULL, 1
FROM `opciones_menu` p
WHERE p.`clave` = 'inicio' AND p.`padre_id` IS NULL
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_menu', 'Gestión de menú', 'fa-solid fa-bars', '/configuracion/menu', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'menu.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_sistema', 'Configuración General', 'fa-solid fa-sliders', '/configuracion/sistema', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'configuracion.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_usuarios', 'Usuarios', 'fa-solid fa-users', '/usuarios', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'usuarios.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_roles', 'Roles y Permisos', 'fa-solid fa-shield-halved', '/configuracion/roles', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'roles.ver'
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `ruta` = VALUES(`ruta`), `orden` = VALUES(`orden`), `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'propiedades', 'Propiedades', 'fa-solid fa-building', NULL, 10, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'propiedades_catalogo', 'Propiedades', 'fa-solid fa-building', '/propiedades', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'propiedades.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'unidades_catalogo', 'Unidades', 'fa-solid fa-door-open', '/unidades', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'unidades.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'disponibilidad_calendario', 'Disponibilidad', 'fa-solid fa-calendar-plus', '/disponibilidad', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'disponibilidad.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 1: Categoría Principal 'Reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'reservas', 'Reservas', 'fa-solid fa-calendar-check', NULL, 15, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `orden` = VALUES(`orden`);

-- Nivel 2: Opción Secundaria 'Gestión de Reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'reservas_catalogo', 'Reservas', 'fa-solid fa-list-check', '/reservas', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reservas.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Estadías (Check-in)'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'estadias_catalogo', 'Estadías (Check-in)', 'fa-solid fa-bell-concierge', '/estadias', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'estadias.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Servicios y Consumos'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'servicios_catalogo', 'Servicios y Consumos', 'fa-solid fa-concierge-bell', '/servicios', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'servicios.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Caja y Cuentas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'caja_cuentas', 'Caja y Cuentas', 'fa-solid fa-cash-register', '/caja', 4, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'caja.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Arrendamientos'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'arrendamientos_catalogo', 'Arrendamientos', 'fa-solid fa-file-contract', '/arrendamientos', 5, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'arrendamientos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Mantenimiento'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'mantenimiento_catalogo', 'Mantenimiento', 'fa-solid fa-screwdriver-wrench', '/mantenimiento', 6, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'mantenimiento.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Nivel 2: Opción Secundaria 'Inventario'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'inventario_catalogo', 'Inventario', 'fa-solid fa-boxes-stacked', '/inventario', 7, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'inventario.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 11. Actores y principales del sistema para auditoría y trazabilidad
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('USUARIO', 'SISTEMA', 'INTEGRACION', 'PROVEEDOR_PAGO') NOT NULL,
    `codigo` VARCHAR(60) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `usuario_id` BIGINT UNSIGNED NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_actores_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_actores_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_actores_codigo` (`codigo`),
    UNIQUE KEY `uq_actores_usuario_id` (`usuario_id`),
    INDEX `idx_actores_tipo` (`tipo`),
    INDEX `idx_actores_estado` (`estado`),
    CONSTRAINT `fk_actores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Actores y principales del sistema para auditoría y trazabilidad';

-- Semilla estructural del actor de sistema
INSERT INTO `actores` (`tipo`, `codigo`, `nombre`, `usuario_id`, `estado`) VALUES
('SISTEMA', 'CAMARGO_PMS', 'Camargo PMS — Sistema Central', NULL, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `estado` = VALUES(`estado`);

-- Vincular cuentas de usuario existentes con su actor humano correspondiente
INSERT INTO `actores` (`tipo`, `codigo`, `nombre`, `usuario_id`, `estado`)
SELECT 'USUARIO', CONCAT('USR_', u.`id`), u.`nombre_usuario`, u.`id`, 'ACTIVO'
FROM `usuarios` u
WHERE NOT EXISTS (
    SELECT 1 FROM `actores` a WHERE a.`usuario_id` = u.`id`
);

-- ----------------------------------------------------------------------------
-- 12. Registro histórico inmutable de auditoría transversal y trazabilidad
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auditoria` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `usuario_id` BIGINT UNSIGNED NULL,
    `accion` VARCHAR(60) NOT NULL,
    `modulo` VARCHAR(60) NOT NULL,
    `entidad` VARCHAR(60) NOT NULL,
    `entidad_id` VARCHAR(100) NULL,
    `descripcion` TEXT NULL,
    `valores_anteriores` JSON NULL,
    `valores_nuevos` JSON NULL,
    `contexto` JSON NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `correlacion_id` VARCHAR(64) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_auditoria_accion_no_vacia` CHECK (`accion` <> ''),
    CONSTRAINT `chk_auditoria_modulo_no_vacio` CHECK (`modulo` <> ''),
    CONSTRAINT `chk_auditoria_entidad_no_vacia` CHECK (`entidad` <> ''),
    INDEX `idx_auditoria_actor_id` (`actor_id`),
    INDEX `idx_auditoria_usuario_id` (`usuario_id`),
    INDEX `idx_auditoria_accion` (`accion`),
    INDEX `idx_auditoria_modulo` (`modulo`),
    INDEX `idx_auditoria_entidad` (`entidad`, `entidad_id`),
    INDEX `idx_auditoria_correlacion_id` (`correlacion_id`),
    INDEX `idx_auditoria_creado_en` (`creado_en`),
    CONSTRAINT `fk_auditoria_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro histórico inmutable de auditoría transversal y trazabilidad';

-- ----------------------------------------------------------------------------
-- 13. Parámetros funcionales y configuración del sistema (CONFIGURACIÓN-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuraciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `clave` VARCHAR(100) NOT NULL,
    `grupo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `tipo` ENUM('TEXTO', 'ENTERO', 'DECIMAL', 'BOOLEANO', 'FECHA', 'HORA', 'JSON') NOT NULL DEFAULT 'TEXTO',
    `valor` LONGTEXT NULL,
    `valor_predeterminado` LONGTEXT NULL,
    `editable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `es_sensible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_configuraciones_clave_no_vacia` CHECK (`clave` <> ''),
    CONSTRAINT `chk_configuraciones_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_configuraciones_grupo_no_vacio` CHECK (`grupo` <> ''),
    UNIQUE KEY `uq_configuraciones_clave` (`clave`),
    INDEX `idx_configuraciones_grupo` (`grupo`),
    INDEX `idx_configuraciones_estado` (`estado`),
    INDEX `idx_configuraciones_orden` (`grupo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Parámetros funcionales y configuración del sistema';

-- Semillas Estructurales de Parámetros de Configuración
INSERT INTO `configuraciones` (`clave`, `grupo`, `nombre`, `descripcion`, `tipo`, `valor`, `valor_predeterminado`, `editable`, `es_sensible`, `orden`, `estado`) VALUES
('sistema.nombre', 'GENERAL', 'Nombre del Sistema', 'Nombre visible del PMS para encabezados y comunicaciones', 'TEXTO', 'Camargo Hostelería', 'Camargo Hostelería', 1, 0, 1, 'ACTIVO'),
('sistema.descripcion', 'GENERAL', 'Descripción del Sistema', 'Lema o descripción general de la organización hotelera', 'TEXTO', 'Gestión Hotelera y Extrahotelera', 'Gestión Hotelera y Extrahotelera', 1, 0, 2, 'ACTIVO'),
('sistema.version_instalada', 'GENERAL', 'Versión Instalada', 'Versión de software del núcleo Camargo PMS (parámetro protegido)', 'TEXTO', '1.0.0', '1.0.0', 0, 0, 99, 'ACTIVO'),
('sistema.idioma', 'LOCALIZACION', 'Idioma Principal', 'Código de idioma predeterminado para la interfaz de usuario (ISO 639-1)', 'TEXTO', 'es', 'es', 1, 0, 1, 'ACTIVO'),
('sistema.formato_fecha', 'LOCALIZACION', 'Formato de Visualización de Fecha', 'Patrón visual estándar para representación de fechas', 'TEXTO', 'd/m/Y', 'd/m/Y', 1, 0, 2, 'ACTIVO'),
('sistema.formato_hora', 'LOCALIZACION', 'Formato de Visualización de Hora', 'Patrón visual estándar para representación horaria', 'TEXTO', 'H:i', 'H:i', 1, 0, 3, 'ACTIVO'),
('operacion.modo_mantenimiento', 'OPERACION', 'Modo Mantenimiento', 'Indica si el sistema se encuentra en ventana de mantenimiento operativo', 'BOOLEANO', '0', '0', 1, 0, 1, 'ACTIVO'),
('operacion.paginacion_predeterminada', 'OPERACION', 'Paginación Predeterminada', 'Cantidad de registros predeterminada por página en listados administrativos', 'ENTERO', '15', '15', 1, 0, 2, 'ACTIVO'),
('operacion.zona_horaria_predeterminada', 'OPERACION', 'Zona Horaria Predeterminada', 'Identificador IANA de la zona horaria central del sistema (ej. America/Lima)', 'TEXTO', 'America/Lima', 'America/Lima', 1, 0, 5, 'ACTIVO'),
('reservas.duracion_hold_minutos', 'OPERACION', 'Duración de Hold para Reservas Pendientes (minutos)', 'Tiempo en minutos que una reserva en estado PENDIENTE retiene el inventario antes de expirar automáticamente (requiere configuración operacional explícita previa)', 'ENTERO', NULL, NULL, 1, 0, 6, 'ACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `tipo` = VALUES(`tipo`),
    `editable` = VALUES(`editable`),
    `es_sensible` = VALUES(`es_sensible`),
    `orden` = VALUES(`orden`);

-- ----------------------------------------------------------------------------
-- 13. Maestro de Propiedades e Inmuebles Físicos (PROPIEDADES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `propiedades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `pais_id` INT UNSIGNED NOT NULL,
    `departamento` VARCHAR(100) NULL,
    `provincia` VARCHAR(100) NULL,
    `distrito` VARCHAR(100) NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `zona_horaria` VARCHAR(50) NULL DEFAULT NULL,
    `referencia` VARCHAR(255) NULL,
    `latitud` DECIMAL(10, 7) NULL,
    `longitud` DECIMAL(10, 7) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_propiedades_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_propiedades_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_propiedades_direccion_no_vacia` CHECK (`direccion` <> ''),
    CONSTRAINT `chk_propiedades_latitud` CHECK (`latitud` IS NULL OR (`latitud` >= -90.0000000 AND `latitud` <= 90.0000000)),
    CONSTRAINT `chk_propiedades_longitud` CHECK (`longitud` IS NULL OR (`longitud` >= -180.0000000 AND `longitud` <= 180.0000000)),
    CONSTRAINT `fk_propiedades_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_propiedades_codigo` (`codigo`),
    INDEX `idx_propiedades_pais_id` (`pais_id`),
    INDEX `idx_propiedades_estado` (`estado`),
    INDEX `idx_propiedades_nombre` (`nombre`),
    INDEX `idx_propiedades_departamento` (`departamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de propiedades e inmuebles físicos administrados por Camargo PMS';

-- ----------------------------------------------------------------------------
-- 14. Catálogo de Tipos de Unidad (UNIDADES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_unidad` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_tipos_unidad_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_tipos_unidad_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_tipos_unidad_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de tipologías arquitectónicas de unidades alojables';

INSERT INTO `tipos_unidad` (`codigo`, `nombre`, `descripcion`, `activo`) VALUES
('DEPARTAMENTO', 'Departamento', 'Unidad habitacional independiente en edificio o condominio', 1),
('HABITACION', 'Habitación', 'Espacio privado para alojamiento dentro de un inmueble', 1),
('CASA', 'Casa', 'Inmueble unifamiliar completo o vivienda independiente', 1),
('SUITE', 'Suite', 'Habitación premium con sala de estar o ambientes integrados', 1),
('BUNGALOW', 'Bungalow', 'Cabaña o módulo campestre/playero independiente', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 15. Maestro de Unidades Físicas y Alojables (UNIDADES-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `tipo_unidad_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `piso_nivel` VARCHAR(30) NULL,
    `capacidad_personas` INT UNSIGNED NOT NULL DEFAULT 1,
    `dormitorios` INT UNSIGNED NOT NULL DEFAULT 1,
    `banos` DECIMAL(3, 1) NOT NULL DEFAULT 1.0,
    `area_m2` DECIMAL(8, 2) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_unidades_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_unidades_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_unidades_capacidad_positiva` CHECK (`capacidad_personas` >= 1),
    CONSTRAINT `chk_unidades_dormitorios` CHECK (`dormitorios` >= 0),
    CONSTRAINT `chk_unidades_banos` CHECK (`banos` >= 0.0),
    CONSTRAINT `chk_unidades_area_positiva` CHECK (`area_m2` IS NULL OR `area_m2` > 0.00),
    CONSTRAINT `fk_unidades_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_unidades_tipo` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_unidades_propiedad_codigo` (`propiedad_id`, `codigo`),
    INDEX `idx_unidades_propiedad_id` (`propiedad_id`),
    INDEX `idx_unidades_tipo_unidad_id` (`tipo_unidad_id`),
    INDEX `idx_unidades_estado` (`estado`),
    INDEX `idx_unidades_codigo` (`codigo`),
    INDEX `idx_unidades_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de unidades físicas habitacionales y alojables';

-- ----------------------------------------------------------------------------
-- 16. Maestro de Bloqueos de Unidad (DISPONIBILIDAD-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bloqueos_unidad` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `motivo` VARCHAR(255) NOT NULL,
    `tipo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `estado` ENUM('ACTIVO', 'LIBERADO') NOT NULL DEFAULT 'ACTIVO',
    `creado_por_actor_id` BIGINT UNSIGNED NULL,
    `liberado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `liberado_en` DATETIME NULL,
    CONSTRAINT `chk_bloqueos_fechas` CHECK (`fecha_fin` > `fecha_inicio`),
    CONSTRAINT `chk_bloqueos_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_bloqueos_motivo_no_vacio` CHECK (`motivo` <> ''),
    CONSTRAINT `fk_bloqueos_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_actor_liberador` FOREIGN KEY (`liberado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_bloqueos_unidad_id` (`unidad_id`),
    INDEX `idx_bloqueos_estado` (`estado`),
    INDEX `idx_bloqueos_rango` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro maestro de bloqueos administrativos y técnicos de unidades';

-- ----------------------------------------------------------------------------
-- 17. Inventario Diario de Unidades - Modelo Sparse (DISPONIBILIDAD-1 / D-067)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_diario_unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `tipo_bloqueo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO', 'RESERVA', 'ARRENDAMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `origen_tipo` VARCHAR(50) NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `origen_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventario_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_inventario_unidad_fecha` (`unidad_id`, `fecha`),
    INDEX `idx_inventario_fecha` (`fecha`),
    INDEX `idx_inventario_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_inventario_unidad_fecha` (`unidad_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Inventario diario sparse de ocupación y bloqueos por noche y unidad';

-- ----------------------------------------------------------------------------
-- 18. Maestro de Reservas Directas (RESERVAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `persona_titular_id` BIGINT UNSIGNED NOT NULL,
    `fecha_entrada` DATE NOT NULL,
    `fecha_salida` DATE NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `estado` ENUM('PENDIENTE', 'CONFIRMADA', 'CANCELADA', 'EXPIRADA') NOT NULL DEFAULT 'PENDIENTE',
    `expira_en` DATETIME NULL COMMENT 'Instante técnico absoluto de vencimiento de hold para reservas PENDIENTES',
    `canal` ENUM('PMS', 'WEB', 'APP', 'OTA') NOT NULL DEFAULT 'PMS',
    `origen` VARCHAR(50) NOT NULL DEFAULT 'DIRECTO',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `observaciones` TEXT NULL,
    `motivo_cancelacion` VARCHAR(255) NULL,
    `cancelada_en` DATETIME NULL,
    `cancelada_por_actor_id` BIGINT UNSIGNED NULL,
    `confirmada_en` DATETIME NULL,
    `confirmada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reservas_persona_titular` FOREIGN KEY (`persona_titular_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_confirmador` FOREIGN KEY (`confirmada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_cancelador` FOREIGN KEY (`cancelada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_reservas_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_reservas_fechas` CHECK (`fecha_salida` > `fecha_entrada`),
    CONSTRAINT `chk_reservas_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_reservas_subtotal_no_negativo` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_reservas_impuesto_no_negativo` CHECK (`impuesto_total` >= 0.00),
    CONSTRAINT `chk_reservas_total_no_negativo` CHECK (`total` >= 0.00),
    UNIQUE KEY `uq_reservas_codigo` (`codigo`),
    INDEX `idx_reservas_persona_titular` (`persona_titular_id`),
    INDEX `idx_reservas_estado` (`estado`),
    INDEX `idx_reservas_fechas` (`fecha_entrada`, `fecha_salida`),
    INDEX `idx_reservas_expira_en` (`expira_en`),
    INDEX `idx_reservas_canal` (`canal`),
    INDEX `idx_reservas_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro maestro de reservas directas y contratos comerciales';

-- ----------------------------------------------------------------------------
-- 19. Asignación Multiunidad y Snapshot Económico (RESERVAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reserva_unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `precio_unitario_noche` DECIMAL(15,2) NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reserva_unidades_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reserva_unidades_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_reserva_unidades_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_reserva_unidades_precio` CHECK (`precio_unitario_noche` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_subtotal` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_impuesto` CHECK (`impuesto` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_total` CHECK (`total` >= 0.00),
    UNIQUE KEY `uq_reserva_unidad` (`reserva_id`, `unidad_id`),
    INDEX `idx_reserva_unidades_reserva` (`reserva_id`),
    INDEX `idx_reserva_unidades_unidad` (`unidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de unidades asignadas y snapshot económico por unidad';

-- ----------------------------------------------------------------------------
-- 20. Estadías y Ocupación Física de Unidades (ESTADÍAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estadias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `reserva_unidad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_entrada` DATE NOT NULL COMMENT 'Fecha hotelera local de entrada (D-066)',
    `fecha_salida_prevista` DATE NOT NULL COMMENT 'Fecha hotelera local prevista de salida (D-066)',
    `estado` ENUM('EN_CURSO', 'FINALIZADA', 'ANULADA') NOT NULL DEFAULT 'EN_CURSO',
    `checkin_en` DATETIME NOT NULL COMMENT 'Instante técnico UTC real de check-in',
    `checkin_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `checkout_en` DATETIME NULL COMMENT 'Instante técnico UTC real de check-out',
    `checkout_por_actor_id` BIGINT UNSIGNED NULL,
    `identificador_llave` VARCHAR(50) NULL COMMENT 'Código de tarjeta magnética o número de llave física',
    `observaciones_checkin` TEXT NULL,
    `observaciones_checkout` TEXT NULL,
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulada_en` DATETIME NULL COMMENT 'Instante técnico UTC real de anulación',
    `anulada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_estadias_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_reserva_unidad` FOREIGN KEY (`reserva_unidad_id`) REFERENCES `reserva_unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_checkin` FOREIGN KEY (`checkin_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_checkout` FOREIGN KEY (`checkout_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_anulador` FOREIGN KEY (`anulada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_estadias_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_estadias_fechas` CHECK (`fecha_salida_prevista` > `fecha_entrada`),
    UNIQUE KEY `uq_estadias_codigo` (`codigo`),
    UNIQUE KEY `uq_estadias_reserva_unidad` (`reserva_unidad_id`),
    INDEX `idx_estadias_reserva_id` (`reserva_id`),
    INDEX `idx_estadias_unidad_id` (`unidad_id`),
    INDEX `idx_estadias_estado` (`estado`),
    INDEX `idx_estadias_fechas` (`fecha_entrada`, `fecha_salida_prevista`),
    INDEX `idx_estadias_checkin_en` (`checkin_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro operativo de ocupación física real de unidades (Check-in / Check-out)';

-- ----------------------------------------------------------------------------
-- 21. Huéspedes y Acompañantes de la Estadía (ESTADÍAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estadia_huespedes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `estadia_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `es_responsable` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es el huésped responsable de la estadía (exactamente 1 por estadía)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_estadia_huespedes_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadia_huespedes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_estadia_persona` (`estadia_id`, `persona_id`),
    INDEX `idx_estadia_huespedes_estadia` (`estadia_id`),
    INDEX `idx_estadia_huespedes_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Huéspedes y acompañantes alojados físicamente en la unidad';

-- ----------------------------------------------------------------------------
-- 22. Maestro Extensible de Categorías de Servicio (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias_servicio` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único estable, ej. TRASLADOS',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo visible',
    `descripcion` TEXT NULL,
    `icono` VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-bell-concierge' COMMENT 'Icono Font Awesome 6 (D-071)',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_categorias_servicio_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_categorias_servicio_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_categorias_servicio_codigo` (`codigo`),
    INDEX `idx_categorias_servicio_estado` (`estado`),
    INDEX `idx_categorias_servicio_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de categorías de servicios adicionales';

-- ----------------------------------------------------------------------------
-- 23. Maestro Extensible de Modalidades de Cobro (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `modalidades_cobro_servicio` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código canónico: FIJO, POR_PERSONA, POR_NOCHE, POR_DIA, POR_UNIDAD, POR_UNIDAD_CONSUMO',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `unidad_medida_sugerida` VARCHAR(30) NULL COMMENT 'Sugerencia para UI: servicio, persona, noche, día, unidad, etc.',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_modalidades_cobro_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_modalidades_cobro_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_modalidades_cobro_codigo` (`codigo`),
    INDEX `idx_modalidades_cobro_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de modalidades de cobro y tarificación';

-- ----------------------------------------------------------------------------
-- 24. Maestro de Proveedores Externos (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proveedores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único de negocio, ej. PROV-001',
    `tipo` ENUM('EMPRESA', 'PERSONA_NATURAL') NOT NULL DEFAULT 'EMPRESA',
    `persona_id` BIGINT UNSIGNED NULL COMMENT 'FK a personas si es persona natural del registro central',
    `razon_social` VARCHAR(200) NOT NULL COMMENT 'Razón social formal o nombre legal',
    `nombre_comercial` VARCHAR(200) NULL COMMENT 'Nombre de fantasía o marca',
    `numero_documento` VARCHAR(30) NULL COMMENT 'RUC de 11 dígitos u otro documento fiscal',
    `email` VARCHAR(150) NULL,
    `telefono` VARCHAR(50) NULL,
    `direccion` TEXT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_proveedores_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_proveedores_razon_social_no_vacia` CHECK (`razon_social` <> ''),
    CONSTRAINT `fk_proveedores_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_proveedores_codigo` (`codigo`),
    INDEX `idx_proveedores_tipo` (`tipo`),
    INDEX `idx_proveedores_estado` (`estado`),
    INDEX `idx_proveedores_documento` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de proveedores externos de servicios (empresas y personas naturales)';

-- ----------------------------------------------------------------------------
-- 25. Catálogo Maestro de Servicios (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único del servicio, ej. SERV-TRF-AERO',
    `categoria_id` INT UNSIGNED NOT NULL,
    `modalidad_cobro_id` INT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si es global, o ID específico si aplica solo a un predio',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `precio_venta_referencial` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `costo_referencial` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `es_operacion_interna_habitual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si habitualmente lo presta Camargo con staff propio',
    `requiere_traslado_detalle` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si exige captura operativa en servicio_traslados',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_servicios_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_servicios_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_servicios_precio_no_negativo` CHECK (`precio_venta_referencial` >= 0.00),
    CONSTRAINT `chk_servicios_costo_no_negativo` CHECK (`costo_referencial` >= 0.00),
    CONSTRAINT `fk_servicios_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_servicio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_modalidad` FOREIGN KEY (`modalidad_cobro_id`) REFERENCES `modalidades_cobro_servicio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_servicios_codigo` (`codigo`),
    INDEX `idx_servicios_categoria` (`categoria_id`),
    INDEX `idx_servicios_modalidad` (`modalidad_cobro_id`),
    INDEX `idx_servicios_propiedad` (`propiedad_id`),
    INDEX `idx_servicios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de conceptos de servicios adicionales';

-- ----------------------------------------------------------------------------
-- 26. Matriz Homologada N:M Proveedor ↔ Servicio (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicio_proveedores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `servicio_id` BIGINT UNSIGNED NOT NULL,
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `costo_pactado` DECIMAL(15, 2) NULL COMMENT 'Costo de compra específico acordado con este proveedor',
    `es_preferente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es el proveedor sugerido por defecto',
    `tiempo_anticipacion_horas` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Horas mínimas de preaviso para coordinar',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uq_preferente` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`es_preferente` = 1, `servicio_id`, NULL)) VIRTUAL COMMENT 'Garantiza máximo un proveedor preferente activo por servicio',
    CONSTRAINT `chk_sp_costo_no_negativo` CHECK (`costo_pactado` IS NULL OR `costo_pactado` >= 0.00),
    CONSTRAINT `fk_sp_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_servicio_proveedor` (`servicio_id`, `proveedor_id`),
    UNIQUE KEY `uq_sp_servicio_preferente` (`uq_preferente`),
    INDEX `idx_sp_servicio` (`servicio_id`),
    INDEX `idx_sp_proveedor` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Matriz N:M de proveedores externos homologados por servicio';

-- ----------------------------------------------------------------------------
-- 27. Servicios Contratados y Consumos Imputados (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios_contratados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único de contratación, ej. SC-20261015-0001',
    `reserva_id` BIGINT UNSIGNED NOT NULL COMMENT 'Reserva comercial titular obligatoria (fuente de verdad del folio)',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Estadía física opcional si fue consumido in situ en una habitación',
    `servicio_id` BIGINT UNSIGNED NOT NULL COMMENT 'Concepto del catálogo',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor asignado. NULL indica operación interna por staff de Camargo',
    
    -- Snapshots históricos inmutables congelados al contratar (D-010 / D-069)
    `descripcion_servicio_snapshot` VARCHAR(150) NOT NULL,
    `categoria_codigo_snapshot` VARCHAR(50) NOT NULL,
    `modalidad_cobro_codigo_snapshot` VARCHAR(50) NOT NULL,
    `es_operacion_interna` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si proveedor_id IS NULL (personal propio)',
    `cantidad` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(15, 2) NOT NULL,
    `costo_unitario` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15, 2) NOT NULL,
    `tasa_impuesto` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Tasa tributaria porcentual congelada (0.0000 bajo política fiscal actual)',
    `impuesto_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT '0.00 actual sin motor tributario',
    `total` DECIMAL(15, 2) NOT NULL,
    `costo_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    
    -- Ciclo de vida y tiempos operativos
    `estado` ENUM('SOLICITADO', 'CONFIRMADO', 'EJECUTADO', 'CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
    `fecha_servicio` DATE NOT NULL COMMENT 'Fecha prevista para la prestación',
    `hora_servicio` TIME NULL COMMENT 'Hora prevista (si aplica)',
    `ejecutado_en` DATETIME NULL COMMENT 'Instante técnico UTC en que se ejecutó/entregó físicamente',
    `cancelada_en` DATETIME NULL COMMENT 'Instante técnico UTC en que se canceló',
    `motivo_cancelacion` VARCHAR(255) NULL,
    `observaciones` TEXT NULL,
    
    -- Trazabilidad y autoría D-061 (ACTOR != USUARIO)
    `solicitado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `ejecutado_por_actor_id` BIGINT UNSIGNED NULL,
    `cancelada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `chk_sc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_sc_cantidad_positiva` CHECK (`cantidad` > 0.00),
    CONSTRAINT `chk_sc_precio_no_negativo` CHECK (`precio_unitario` >= 0.00),
    CONSTRAINT `chk_sc_costo_no_negativo` CHECK (`costo_unitario` >= 0.00),
    CONSTRAINT `chk_sc_coherencia_operacion_interna` CHECK (
        (`es_operacion_interna` = 1 AND `proveedor_id` IS NULL) OR 
        (`es_operacion_interna` = 0 AND `proveedor_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_sc_cancelacion_coherente` CHECK (
        (`estado` <> 'CANCELADO' AND `cancelada_en` IS NULL AND `cancelada_por_actor_id` IS NULL AND `motivo_cancelacion` IS NULL) OR
        (`estado` = 'CANCELADO' AND `cancelada_en` IS NOT NULL AND `cancelada_por_actor_id` IS NOT NULL AND `motivo_cancelacion` IS NOT NULL)
    ),
    CONSTRAINT `fk_sc_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sc_actor_solicitado` FOREIGN KEY (`solicitado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_actor_ejecutado` FOREIGN KEY (`ejecutado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_actor_cancelado` FOREIGN KEY (`cancelada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY `uq_sc_codigo` (`codigo`),
    INDEX `idx_sc_reserva` (`reserva_id`),
    INDEX `idx_sc_estadia` (`estadia_id`),
    INDEX `idx_sc_servicio` (`servicio_id`),
    INDEX `idx_sc_proveedor` (`proveedor_id`),
    INDEX `idx_sc_estado` (`estado`),
    INDEX `idx_sc_fecha_servicio` (`fecha_servicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro transaccional de servicios contratados y consumos';

-- ----------------------------------------------------------------------------
-- 28. Extensión Logística Especializada 1:1 de Traslados (SERVICIOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicio_traslados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `servicio_contratado_id` BIGINT UNSIGNED NOT NULL COMMENT 'Relación 1:1 estricta con el servicio contratado',
    `tipo_traslado` ENUM('LLEGADA', 'SALIDA') NOT NULL COMMENT 'LLEGADA al predio o SALIDA hacia terminal/aeropuerto/otro',
    `origen` VARCHAR(150) NOT NULL COMMENT 'Punto de partida físico (Aeropuerto, Terminal, Edificio, Dirección)',
    `destino` VARCHAR(150) NOT NULL COMMENT 'Punto de llegada físico',
    `fecha_hora_recogida` DATETIME NOT NULL COMMENT 'Fecha y hora local acordada para esperar al huésped',
    `aerolinea_empresa` VARCHAR(100) NULL COMMENT 'Aerolínea o empresa de transporte (LATAM, Sky, Cruz del Sur, etc.)',
    `numero_vuelo_viaje` VARCHAR(50) NULL COMMENT 'Número de vuelo o viaje (ej. LA2045)',
    `cantidad_pasajeros` INT UNSIGNED NOT NULL DEFAULT 1,
    `cantidad_maletas` INT UNSIGNED NOT NULL DEFAULT 0,
    `datos_conductor_vehiculo` VARCHAR(255) NULL COMMENT 'Nombre del chofer, teléfono, modelo y placa del vehículo',
    `instrucciones_recogida` TEXT NULL COMMENT 'Detalles para el encuentro (cartel con nombre en puerta de salida, etc.)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_st_origen_no_vacio` CHECK (`origen` <> ''),
    CONSTRAINT `chk_st_destino_no_vacio` CHECK (`destino` <> ''),
    CONSTRAINT `chk_st_pasajeros_minimo` CHECK (`cantidad_pasajeros` >= 1),
    CONSTRAINT `fk_st_servicio_contratado` FOREIGN KEY (`servicio_contratado_id`) REFERENCES `servicios_contratados` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_st_servicio_contratado` (`servicio_contratado_id`),
    INDEX `idx_st_fecha_hora_recogida` (`fecha_hora_recogida`),
    INDEX `idx_st_numero_vuelo` (`numero_vuelo_viaje`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Extensión 1:1 con detalles logísticos para servicios de traslado';

-- ----------------------------------------------------------------------------
-- 29. Catálogo Normalizado de Métodos de Pago (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metodos_pago` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'EFECTIVO, TARJETA_CREDITO, TARJETA_DEBITO, TRANSFERENCIA, BILLETERA_DIGITAL',
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_destino` ENUM('CAJA_FISICA', 'CUENTA_BANCARIA', 'PASARELA_INTERMEDIARIO') NOT NULL DEFAULT 'CAJA_FISICA',
    `requiere_referencia` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si exige nro de voucher u operación',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_metp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_metp_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_metodos_pago_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de medios de pago soportados';

-- ----------------------------------------------------------------------------
-- 30. Cuentas Bancarias y Financieras de la Empresa (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_bancarias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador interno (ej. BCP_CORRIENTE_PEN)',
    `banco_nombre` VARCHAR(100) NOT NULL COMMENT 'Entidad bancaria o financiera',
    `tipo_cuenta` ENUM('CORRIENTE', 'AHORROS', 'RECAUDADORA') NOT NULL DEFAULT 'CORRIENTE',
    `numero_cuenta` VARCHAR(50) NOT NULL,
    `numero_cci` VARCHAR(50) NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `titular` VARCHAR(150) NOT NULL COMMENT 'Razón social titular de la cuenta',
    `saldo_contable` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo contable referencial',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ctab_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ctab_numero_no_vacio` CHECK (`numero_cuenta` <> ''),
    UNIQUE KEY `uq_cuentas_bancarias_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cuentas bancarias de Camargo Hostelería para recaudación y transferencias';

-- ----------------------------------------------------------------------------
-- 31. Cajas Físicas de Custodia en Predios (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cajas_fisicas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador único (ej. CAJA_RECEPCION_1)',
    `nombre` VARCHAR(100) NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Predio o propiedad física a la que pertenece la gaveta',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cajf_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_cajf_codigo_no_vacio` CHECK (`codigo` <> ''),
    UNIQUE KEY `uq_cajas_fisicas_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Puntos de recaudación y custodia de efectivo físico';

-- ----------------------------------------------------------------------------
-- 32. Sesiones de Turno de Caja (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `caja_fisica_id` INT UNSIGNED NOT NULL,
    `actor_apertura_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor cajero humano que inicia el turno (D-061)',
    `actor_cierre_id` BIGINT UNSIGNED NULL COMMENT 'Actor cajero o supervisor que sella el turno (D-061)',
    `monto_apertura` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Fondo de cambio inicial en efectivo',
    `total_ingresos_efectivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma acumulada de cobros en efectivo',
    `total_egresos_efectivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma acumulada de devoluciones o salidas',
    `monto_esperado` DECIMAL(15,2) NULL COMMENT 'monto_apertura + ingresos - egresos',
    `monto_contado_declarado` DECIMAL(15,2) NULL COMMENT 'Conteo físico final de billetes y monedas',
    `diferencia` DECIMAL(15,2) NULL COMMENT 'declarado - esperado',
    `resultado_arqueo` ENUM('CUADRADA', 'SOBRANTE', 'FALTANTE') NULL,
    `estado` ENUM('ABIERTA', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    `abierta_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrada_en` DATETIME NULL,
    `observaciones_apertura` TEXT NULL,
    `observaciones_cierre` TEXT NULL COMMENT 'Obligatorio cuando diferencia != 0',
    CONSTRAINT `fk_sesc_caja_fisica` FOREIGN KEY (`caja_fisica_id`) REFERENCES `cajas_fisicas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sesc_actor_apertura` FOREIGN KEY (`actor_apertura_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sesc_actor_cierre` FOREIGN KEY (`actor_cierre_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_sesc_monto_apertura_no_negativo` CHECK (`monto_apertura` >= 0),
    CONSTRAINT `chk_sesc_totales_no_negativos` CHECK (`total_ingresos_efectivo` >= 0 AND `total_egresos_efectivo` >= 0),
    INDEX `idx_sesc_caja_estado` (`caja_fisica_id`, `estado`),
    INDEX `idx_sesc_actor_apertura` (`actor_apertura_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Turnos de trabajo y arqueos de gaveta de efectivo';

-- ----------------------------------------------------------------------------
-- 33. Folios Financieros / Cuentas de Reserva o Arrendamiento (FINANCIERO-2 / ARRENDAMIENTOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_folios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato FOL-YYYYMMDD-XXXX',
    `reserva_id` BIGINT UNSIGNED NULL COMMENT '1:1 con la reserva raíz comercial (NULL si es arrendamiento)',
    `arrendamiento_id` BIGINT UNSIGNED NULL COMMENT '1:1 con el arrendamiento patrimonial (NULL si es reserva)',
    `persona_titular_id` BIGINT UNSIGNED NOT NULL COMMENT 'Titular principal de la cuenta',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ABIERTA', 'CONGELADA', 'CERRADA', 'ANULADA') NOT NULL DEFAULT 'ABIERTA',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ctaf_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ctaf_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ctaf_persona_titular` FOREIGN KEY (`persona_titular_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ctaf_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_ctaf_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ctaf_sujeto_exclusivo` CHECK (
        (`reserva_id` IS NOT NULL AND `arrendamiento_id` IS NULL) OR
        (`reserva_id` IS NULL AND `arrendamiento_id` IS NOT NULL)
    ),
    UNIQUE KEY `uq_cuentas_folios_codigo` (`codigo`),
    UNIQUE KEY `uq_cuentas_folios_reserva` (`reserva_id`),
    UNIQUE KEY `uq_cuentas_folios_arrendamiento` (`arrendamiento_id`),
    INDEX `idx_ctaf_estado` (`estado`),
    INDEX `idx_ctaf_titular` (`persona_titular_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Contenedor financiero consolidado de la reserva comercial o contrato de arrendamiento';

-- ----------------------------------------------------------------------------
-- 34. Cargos a la Cuenta (FINANCIERO-2 / ARRENDAMIENTOS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cargos_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CRG-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('ALOJAMIENTO_NOCHES', 'SERVICIO_CONTRATADO', 'PENALIDAD', 'AJUSTE_MANUAL', 'RENTA_ARRENDAMIENTO', 'DEPOSITO_GARANTIA') NOT NULL,
    `origen_id` BIGINT UNSIGNED NULL COMMENT 'ID de reserva_unidades o servicios_contratados',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Habitación física que consumió (NULL si es preventa o general)',
    `concepto` VARCHAR(255) NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_aplicado_acumulado` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de amortizaciones activas',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('PROVISIONAL', 'DEVENGADO', 'ANULADO') NOT NULL DEFAULT 'DEVENGADO' COMMENT 'PROVISIONAL para servicios confirmados pero no ejecutados',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `devengado_en` DATETIME NULL COMMENT 'Instante UTC en que pasó a DEVENGADO irrevocable',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_crgc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_crgc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_crgc_total_positivo` CHECK (`total` >= 0),
    CONSTRAINT `chk_crgc_aplicado_rango` CHECK (`monto_aplicado_acumulado` >= 0 AND `monto_aplicado_acumulado` <= `total`),
    UNIQUE KEY `uq_cargos_cuenta_codigo` (`codigo`),
    INDEX `idx_crgc_folio_estado` (`cuenta_folio_id`, `estado`),
    INDEX `idx_crgc_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_crgc_estadia` (`estadia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Obligaciones y consumos devengados o provisionales en el folio';

-- ----------------------------------------------------------------------------
-- 35. Pagos y Cobros a la Cuenta (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato PAG-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL COMMENT 'Monto total recaudado en este pago',
    `monto_aplicado` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de aplicaciones a cargos específicos',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si método es EFECTIVO',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si método es TRANSFERENCIA',
    `referencia_operacion` VARCHAR(100) NULL COMMENT 'Nro voucher POS, nro operación bancaria',
    `estado` ENUM('CONFIRMADO', 'REVERSADO') NOT NULL DEFAULT 'CONFIRMADO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pagc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_actor_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_pagc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_pagc_monto_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_pagc_aplicado_rango` CHECK (`monto_aplicado` >= 0 AND `monto_aplicado` <= `monto_total`),
    UNIQUE KEY `uq_pagos_cuenta_codigo` (`codigo`),
    INDEX `idx_pagc_folio_estado` (`cuenta_folio_id`, `estado`),
    INDEX `idx_pagc_metodo` (`metodo_pago_id`),
    INDEX `idx_pagc_sesion` (`sesion_caja_id`),
    INDEX `idx_pagc_cuenta_bancaria` (`cuenta_bancaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dinero recibido o reconocido para la cuenta';

-- ----------------------------------------------------------------------------
-- 36. Aplicaciones de Pago (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `aplicaciones_pago` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato APL-YYYYMMDD-XXXX',
    `pago_id` BIGINT UNSIGNED NOT NULL,
    `cargo_id` BIGINT UNSIGNED NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVA', 'REVERTIDA') NOT NULL DEFAULT 'ACTIVA',
    `revertida_en` DATETIME NULL,
    `revertida_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_aplp_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_actor_reversor` FOREIGN KEY (`revertida_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_aplp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_aplp_monto_positivo` CHECK (`monto_aplicado` > 0),
    UNIQUE KEY `uq_aplicaciones_pago_codigo` (`codigo`),
    INDEX `idx_aplp_pago_estado` (`pago_id`, `estado`),
    INDEX `idx_aplp_cargo_estado` (`cargo_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Imputación formal entre fondos recaudados y obligaciones devengadas';

-- ----------------------------------------------------------------------------
-- 37. Devoluciones de Fondos (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato DEV-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `pago_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Pago original que se reembolsa',
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si se reembolsa en EFECTIVO',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si se transfiere a cliente',
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `motivo` VARCHAR(255) NOT NULL,
    `estado` ENUM('CONFIRMADA', 'ANULADA') NOT NULL DEFAULT 'CONFIRMADA',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_devc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_pago_origen` FOREIGN KEY (`pago_origen_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_devc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_devc_monto_positivo` CHECK (`monto` > 0),
    UNIQUE KEY `uq_devoluciones_cuenta_codigo` (`codigo`),
    INDEX `idx_devc_folio` (`cuenta_folio_id`),
    INDEX `idx_devc_pago` (`pago_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reembolsos reales entregados o transferidos al huésped';

-- ----------------------------------------------------------------------------
-- 38. Libro Mayor de Movimientos de Caja Física (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sesion_caja_id` BIGINT UNSIGNED NOT NULL,
    `tipo_movimiento` ENUM('INGRESO_COBRO', 'INGRESO_AJUSTE', 'EGRESO_DEVOLUCION', 'EGRESO_GASTO_MENOR', 'EGRESO_REMESA') NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL COMMENT 'Vinculado a pagos_cuenta si fue cobro a huésped',
    `devolucion_id` BIGINT UNSIGNED NULL COMMENT 'Vinculado a devoluciones_cuenta si fue reembolso',
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `concepto` VARCHAR(255) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor responsable del movimiento (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_devolucion` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_movc_monto_positivo` CHECK (`monto` > 0),
    INDEX `idx_movc_sesion` (`sesion_caja_id`),
    INDEX `idx_movc_pago` (`pago_id`),
    INDEX `idx_movc_devolucion` (`devolucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro mayor de entradas y salidas de billetes y monedas en gaveta';

-- ----------------------------------------------------------------------------
-- 39. Libro Mayor de Movimientos Bancarios (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_bancarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cuenta_bancaria_id` INT UNSIGNED NOT NULL,
    `tipo_movimiento` ENUM('INGRESO_TRANSFERENCIA', 'INGRESO_LIQUIDACION_POS', 'EGRESO_DEVOLUCION', 'EGRESO_TRANSFERENCIA', 'COMISION_BANCARIA') NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL,
    `devolucion_id` BIGINT UNSIGNED NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `numero_operacion` VARCHAR(100) NOT NULL COMMENT 'Código de operación en extracto bancario',
    `concepto` VARCHAR(255) NOT NULL,
    `fecha_operacion` DATE NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor que concilia o asienta el movimiento (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movb_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_devolucion` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_movb_monto_positivo` CHECK (`monto` > 0),
    INDEX `idx_movb_cuenta` (`cuenta_bancaria_id`),
    INDEX `idx_movb_operacion` (`numero_operacion`),
    INDEX `idx_movb_pago` (`pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro de movimientos en cuentas bancarias para posterior conciliación';

-- ----------------------------------------------------------------------------
-- 40. Arrendamientos de Mediana y Larga Estancia (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato ARR-YYYYMMDD-XXXX',
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `arrendamiento_anterior_id` BIGINT UNSIGNED NULL COMMENT 'Para contratos renovados',
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `dia_vencimiento` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Día contractual de vencimiento (1..31)',
    `renta_mensual` DECIMAL(15,2) NOT NULL,
    `deposito_garantia` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_primer_periodo` DECIMAL(15,2) NOT NULL COMMENT 'Monto congelado inicial (completo o prorrateado)',
    `es_primer_mes_prorrateado` TINYINT(1) NOT NULL DEFAULT 0,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('BORRADOR', 'VIGENTE', 'FINALIZADO', 'RESCINDIDO', 'CANCELADO') NOT NULL DEFAULT 'BORRADOR',
    `motivo_rescision` VARCHAR(500) NULL,
    `rescidido_en` DATETIME NULL,
    `rescidido_por_actor_id` BIGINT UNSIGNED NULL,
    `notas_adicionales` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_arr_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arr_arrendamiento_anterior` FOREIGN KEY (`arrendamiento_anterior_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arr_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arr_actor_rescisor` FOREIGN KEY (`rescidido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_arr_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_arr_fechas_coherentes` CHECK (`fecha_fin` > `fecha_inicio`),
    CONSTRAINT `chk_arr_dia_vencimiento_rango` CHECK (`dia_vencimiento` BETWEEN 1 AND 31),
    CONSTRAINT `chk_arr_renta_positiva` CHECK (`renta_mensual` > 0),
    CONSTRAINT `chk_arr_garantia_no_negativa` CHECK (`deposito_garantia` >= 0),
    UNIQUE KEY `uq_arrendamientos_codigo` (`codigo`),
    INDEX `idx_arr_unidad_fechas` (`unidad_id`, `fecha_inicio`, `fecha_fin`),
    INDEX `idx_arr_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Contratos patrimoniales de arrendamiento de unidades';

-- ----------------------------------------------------------------------------
-- 41. Sujetos del Arrendamiento: Titular Único, Cotitulares y Ocupantes (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_personas` (
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_relacion` ENUM('TITULAR', 'COTITULAR', 'OCUPANTE') NOT NULL,
    `es_titular_unico` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `tipo_relacion` = 'TITULAR' THEN `arrendamiento_id` ELSE NULL END
    ) VIRTUAL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`arrendamiento_id`, `persona_id`),
    CONSTRAINT `fk_arrp_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arrp_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_arrp_titular_unico` (`es_titular_unico`),
    INDEX `idx_arrp_persona` (`persona_id`),
    INDEX `idx_arrp_tipo` (`arrendamiento_id`, `tipo_relacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Partes contractuales y residentes de la unidad con unicidad de titular';

-- ----------------------------------------------------------------------------
-- 42. Cuotas Periódicas Idempotentes de Renta (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_cuotas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `periodo_anio` SMALLINT UNSIGNED NOT NULL,
    `periodo_mes` TINYINT UNSIGNED NOT NULL,
    `periodo_codigo` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `tipo_cuota` ENUM('RENTA_MENSUAL', 'CUOTA_PRORRATEADA', 'AJUSTE_PERIODICO') NOT NULL DEFAULT 'RENTA_MENSUAL',
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `monto_renta` DECIMAL(15,2) NOT NULL,
    `cargo_cuenta_id` BIGINT UNSIGNED NOT NULL,
    `estado` ENUM('PENDIENTE', 'PAGADA_PARCIAL', 'PAGADA_TOTAL', 'ANULADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_arrc_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_arrc_cargo_cuenta` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_arrc_periodo_mes` CHECK (`periodo_mes` BETWEEN 1 AND 12),
    CONSTRAINT `chk_arrc_monto_positivo` CHECK (`monto_renta` > 0),
    UNIQUE KEY `uq_arrc_arrendamiento_periodo_tipo` (`arrendamiento_id`, `periodo_anio`, `periodo_mes`, `tipo_cuota`),
    INDEX `idx_arrc_arrendamiento_estado` (`arrendamiento_id`, `estado`),
    INDEX `idx_arrc_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de obligaciones mensuales recurrentes por contrato';

-- ----------------------------------------------------------------------------
-- 43. Custodia Segregada de Garantía (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_garantias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `monto_pactado` DECIMAL(15,2) NOT NULL,
    `monto_recibido` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_retenido_actual` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_compensado_danos` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_compensado_renta` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_devuelto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('PENDIENTE', 'CUSTODIADA', 'COMPENSADA_PARCIAL', 'LIQUIDADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_arrg_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_arrg_montos_no_negativos` CHECK (
        `monto_pactado` >= 0 AND `monto_recibido` >= 0 AND `monto_retenido_actual` >= 0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Fondo de garantía en custodia con saldo reconstructible';

-- ----------------------------------------------------------------------------
-- 44. Historial Inmutable de Transiciones de Estado (ARRENDAMIENTOS-1 / D-076)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arrendamiento_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` ENUM('BORRADOR', 'VIGENTE', 'FINALIZADO', 'RESCINDIDO', 'CANCELADO') NOT NULL,
    `estado_nuevo` ENUM('BORRADOR', 'VIGENTE', 'FINALIZADO', 'RESCINDIDO', 'CANCELADO') NOT NULL,
    `motivo` VARCHAR(500) NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `cambiado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ahe_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ahe_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_ahe_arrendamiento` (`arrendamiento_id`, `cambiado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad histórica inmutable de cambios de estado';

-- ----------------------------------------------------------------------------
-- 45. Incidencias Técnicas y Desperfectos Físicos (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'INC-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si la incidencia ocurre en áreas comunes o infraestructura general',
    `reportado_por_persona_id` BIGINT UNSIGNED NOT NULL,
    `categoria` ENUM(
        'PLOMERIA',
        'ELECTRICIDAD',
        'CERRAJERIA',
        'CLIMATIZACION',
        'PINTURA',
        'MOBILIARIO',
        'LIMPIEZA_PROFUNDA',
        'ESTRUCTURAL',
        'OTRO'
    ) NOT NULL DEFAULT 'OTRO',
    `severidad` ENUM('BAJA', 'MEDIA', 'ALTA', 'CRITICA') NOT NULL DEFAULT 'MEDIA',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NOT NULL,
    `ubicacion_detallada` VARCHAR(255) NULL,
    `estado` ENUM(
        'REPORTADA',
        'EN_EVALUACION',
        'CONVERTIDA_A_ORDEN',
        'RESUELTA_DIRECTA',
        'DESESTIMADA'
    ) NOT NULL DEFAULT 'REPORTADA',
    `motivo_cierre` VARCHAR(500) NULL COMMENT 'Explicación si se desestima o se resuelve directamente',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `cerrado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resuelto_en` DATETIME NULL,
    CONSTRAINT `chk_minc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_minc_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `fk_minc_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_reportador` FOREIGN KEY (`reportado_por_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_actor_cerrador` FOREIGN KEY (`cerrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_minc_codigo` (`codigo`),
    INDEX `idx_minc_propiedad_unidad` (`propiedad_id`, `unidad_id`),
    INDEX `idx_minc_estado` (`estado`),
    INDEX `idx_minc_severidad` (`severidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reportes e incidencias físicas y técnicas en propiedades y unidades';

-- ----------------------------------------------------------------------------
-- 46. Órdenes de Trabajo de Mantenimiento (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_ordenes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'OT-YYYYMMDD-XXXX',
    `tipo` ENUM('CORRECTIVO', 'PREVENTIVO') NOT NULL DEFAULT 'CORRECTIVO',
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si el trabajo es de áreas comunes o infraestructura general',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NOT NULL,
    `tipo_asignacion` ENUM('INTERNO', 'EXTERNO', 'MIXTO') NOT NULL DEFAULT 'INTERNO',
    `colaborador_asignado_id` BIGINT UNSIGNED NULL COMMENT 'Personal interno responsable',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor externo homologado',
    `numero_comprobante_proveedor` VARCHAR(50) NULL COMMENT 'Nro de factura o boleta del proveedor',
    `requiere_bloqueo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si inhabilita físicamente la unidad para venta',
    `fecha_programada_inicio` DATE NOT NULL,
    `fecha_programada_fin` DATE NOT NULL,
    `fecha_bloqueo_inicio` DATE NULL COMMENT 'Inicio del bloqueo en inventario diario',
    `fecha_bloqueo_fin` DATE NULL COMMENT 'Fin del bloqueo (exclusivo) en inventario diario',
    `fecha_ejecucion_inicio` DATETIME NULL,
    `fecha_ejecucion_fin` DATETIME NULL,
    `costo_estimado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_mano_obra` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_materiales` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM(
        'BORRADOR',
        'PROGRAMADA',
        'EN_PROCESO',
        'COMPLETADA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'BORRADOR',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `completado_por_actor_id` BIGINT UNSIGNED NULL,
    `cancelado_por_actor_id` BIGINT UNSIGNED NULL,
    `motivo_cancelacion` VARCHAR(500) NULL,
    `notas_cierre` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_mord_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_mord_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `chk_mord_fechas_programadas` CHECK (`fecha_programada_fin` >= `fecha_programada_inicio`),
    CONSTRAINT `chk_mord_bloqueo_coherente` CHECK (
        (`requiere_bloqueo` = 0 AND `fecha_bloqueo_inicio` IS NULL AND `fecha_bloqueo_fin` IS NULL) OR
        (`requiere_bloqueo` = 1 AND `unidad_id` IS NOT NULL AND `fecha_bloqueo_inicio` IS NOT NULL AND `fecha_bloqueo_fin` IS NOT NULL AND `fecha_bloqueo_fin` > `fecha_bloqueo_inicio`)
    ),
    CONSTRAINT `chk_mord_costos_no_negativos` CHECK (`costo_estimado` >= 0 AND `costo_materiales` >= 0 AND `costo_mano_obra` >= 0 AND `costo_total` >= 0),
    CONSTRAINT `fk_mord_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_mord_colaborador` FOREIGN KEY (`colaborador_asignado_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_completador` FOREIGN KEY (`completado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_cancelador` FOREIGN KEY (`cancelado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_mord_codigo` (`codigo`),
    INDEX `idx_mord_propiedad_unidad` (`propiedad_id`, `unidad_id`),
    INDEX `idx_mord_estado` (`estado`),
    INDEX `idx_mord_fechas_programadas` (`fecha_programada_inicio`, `fecha_programada_fin`),
    INDEX `idx_mord_bloqueo` (`requiere_bloqueo`, `fecha_bloqueo_inicio`, `fecha_bloqueo_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Órdenes de trabajo preventivas y correctivas de mantenimiento';

-- ----------------------------------------------------------------------------
-- 47. Tabla Pivote Órdenes <-> Incidencias (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_orden_incidencias` (
    `orden_id` BIGINT UNSIGNED NOT NULL,
    `incidencia_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`orden_id`, `incidencia_id`),
    CONSTRAINT `fk_moi_orden` FOREIGN KEY (`orden_id`) REFERENCES `mantenimiento_ordenes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_moi_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `mantenimiento_incidencias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Asociación entre órdenes de trabajo e incidencias atendidas';

-- ----------------------------------------------------------------------------
-- 48. Historial Inmutable de Estados de Mantenimiento (MANTENIMIENTO-1 / D-077)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entidad_tipo` ENUM('INCIDENCIA', 'ORDEN_TRABAJO') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(30) NOT NULL,
    `estado_nuevo` VARCHAR(30) NOT NULL,
    `motivo` VARCHAR(500) NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `cambiado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mhe_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_mhe_entidad` (`entidad_tipo`, `entidad_id`, `cambiado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial inmutable de cambios de estado en mantenimiento e incidencias';

-- ----------------------------------------------------------------------------
-- 49. Unidades de Medida Normalizadas (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_unidades_medida` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'UND, PAR, JGO, LT, KG, MTR, ROLLO, CAJA, PQTE',
    `nombre` VARCHAR(60) NOT NULL,
    `simbolo` VARCHAR(10) NOT NULL,
    `admite_decimales` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si admite fraccionamiento (ej: KG, LT, MTR)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ium_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ium_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_ium_simbolo_no_vacio` CHECK (`simbolo` <> ''),
    UNIQUE KEY `uq_ium_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo normalizado de unidades de medida para inventario';

-- Semillas canónicas de unidades de medida
INSERT INTO `inventario_unidades_medida` (`codigo`, `nombre`, `simbolo`, `admite_decimales`) VALUES
('UND', 'Unidad', 'und', 0),
('PAR', 'Par', 'par', 0),
('JGO', 'Juego', 'jgo', 0),
('LT', 'Litro', 'L', 1),
('KG', 'Kilogramo', 'kg', 1),
('MTR', 'Metro', 'm', 1),
('ROLLO', 'Rollo', 'rll', 0),
('CAJA', 'Caja', 'cj', 0),
('PQTE', 'Paquete', 'paq', 0)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `simbolo` = VALUES(`simbolo`), `admite_decimales` = VALUES(`admite_decimales`);

-- ----------------------------------------------------------------------------
-- 50. Ubicaciones Polimórficas de Inventario (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_ubicaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'UBI-BOD-01, UBI-HAB-101, UBI-LAV-01',
    `nombre` VARCHAR(100) NOT NULL,
    `tipo` ENUM('ALMACEN', 'UNIDAD', 'CUSTODIA_EXTERNA') NOT NULL DEFAULT 'ALMACEN',
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si tipo = UNIDAD, NULL en otros tipos',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor externo si tipo = CUSTODIA_EXTERNA (ej: lavandería/taller)',
    `responsable_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador a cargo',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iubi_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_iubi_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_iubi_unidad_coherencia` CHECK (
        (`tipo` = 'UNIDAD' AND `unidad_id` IS NOT NULL) OR
        (`tipo` <> 'UNIDAD' AND `unidad_id` IS NULL)
    ),
    CONSTRAINT `fk_iubi_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iubi_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_iubi_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iubi_responsable` FOREIGN KEY (`responsable_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iubi_codigo` (`codigo`),
    UNIQUE KEY `uq_iubi_unidad_id` (`unidad_id`),
    INDEX `idx_iubi_propiedad_tipo` (`propiedad_id`, `tipo`),
    INDEX `idx_iubi_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ubicaciones físicas y externas de inventario (almacenes, habitaciones, lavanderías)';

-- ----------------------------------------------------------------------------
-- 51. Catálogo de Artículos de Inventario (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_articulos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_sku` VARCHAR(30) NOT NULL COMMENT 'SKU-AMN-001, SKU-LEN-001, SKU-REP-001, SKU-ACT-001',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `categoria` ENUM(
        'CONSUMIBLE_OPERATIVO',
        'LENCERIA_BLANCOS',
        'REPUESTO_MANTENIMIENTO',
        'ACTIVO_SERIALIZABLE',
        'HERRAMIENTA',
        'OTRO'
    ) NOT NULL DEFAULT 'CONSUMIBLE_OPERATIVO',
    `unidad_medida_id` BIGINT UNSIGNED NOT NULL,
    `costo_referencial` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `stock_minimo_alerta` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iart_sku_no_vacio` CHECK (`codigo_sku` <> ''),
    CONSTRAINT `chk_iart_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_iart_costo_no_negativo` CHECK (`costo_referencial` >= 0),
    CONSTRAINT `chk_iart_stock_min_no_negativo` CHECK (`stock_minimo_alerta` >= 0),
    CONSTRAINT `fk_iart_unidad_medida` FOREIGN KEY (`unidad_medida_id`) REFERENCES `inventario_unidades_medida` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iart_sku` (`codigo_sku`),
    INDEX `idx_iart_categoria` (`categoria`),
    INDEX `idx_iart_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de artículos y bienes de inventario';

-- ----------------------------------------------------------------------------
-- 52. Existencias por Ubicación (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_existencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_actual` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_reservada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ie_cantidad_no_negativa` CHECK (`cantidad_actual` >= 0),
    CONSTRAINT `chk_ie_reservada_no_negativa` CHECK (`cantidad_reservada` >= 0),
    CONSTRAINT `fk_ie_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ie_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_ie_articulo_ubicacion` (`articulo_id`, `ubicacion_id`),
    INDEX `idx_ie_ubicacion` (`ubicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Proyección operacional de stock materializado por artículo y ubicación';

-- ----------------------------------------------------------------------------
-- 53. Movimientos de Inventario (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_movimientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'MOV-YYYYMMDD-XXXX',
    `tipo_movimiento` ENUM(
        'SALDO_INICIAL',
        'ENTRADA_COMPRA',
        'SALIDA_CONSUMO',
        'SALIDA_MANTENIMIENTO',
        'TRASLADO_SALIDA',
        'TRASLADO_ENTRADA',
        'AJUSTE_POSITIVO',
        'AJUSTE_NEGATIVO',
        'REVERSO'
    ) NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `costo_unitario_historico` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `costo_total_historico` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `referencia_tipo` VARCHAR(50) NULL COMMENT 'MANTENIMIENTO_ORDEN, TRASLADO, AJUSTE_FISICO, COMPRA, etc.',
    `referencia_id` BIGINT UNSIGNED NULL COMMENT 'ID de la entidad vinculada',
    `movimiento_referencia_id` BIGINT UNSIGNED NULL COMMENT 'Enlace a movimiento original en caso de REVERSO o traslado',
    `correlativo_operacion` VARCHAR(40) NULL COMMENT 'Identificador único compartido para ambas patas de un traslado',
    `motivo` VARCHAR(500) NOT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `reverso_movimiento_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `tipo_movimiento` = 'REVERSO' THEN `movimiento_referencia_id` ELSE NULL END
    ) VIRTUAL COMMENT 'Garantiza a nivel de motor MySQL que un movimiento no pueda ser neutralizado por más de un REVERSO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_imov_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_imov_cantidad_positiva` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_imov_costo_unit_no_negativo` CHECK (`costo_unitario_historico` >= 0),
    CONSTRAINT `chk_imov_costo_tot_no_negativo` CHECK (`costo_total_historico` >= 0),
    CONSTRAINT `fk_imov_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_mov_ref` FOREIGN KEY (`movimiento_referencia_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_imov_codigo` (`codigo`),
    UNIQUE KEY `uq_imov_reverso_unico` (`reverso_movimiento_id`),
    INDEX `idx_imov_articulo_ubicacion_fecha` (`articulo_id`, `ubicacion_id`, `creado_en`),
    INDEX `idx_imov_referencia` (`referencia_tipo`, `referencia_id`),
    INDEX `idx_imov_correlativo` (`correlativo_operacion`),
    INDEX `idx_imov_tipo` (`tipo_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Kardex inmutable y bitácora física append-only de inventario';

-- ----------------------------------------------------------------------------
-- 54. Activos Fijos Individuales y Serializables (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_activos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `codigo_placa` VARCHAR(40) NOT NULL COMMENT 'Placa patrimonial / código de barras único',
    `numero_serie_fabricante` VARCHAR(60) NULL,
    `marca` VARCHAR(60) NULL,
    `modelo` VARCHAR(60) NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `estado` ENUM('DISPONIBLE', 'ASIGNADO', 'EN_MANTENIMIENTO', 'DE_BAJA') NOT NULL DEFAULT 'DISPONIBLE',
    `fecha_adquisicion` DATE NULL,
    `costo_adquisicion` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `garantia_vence_en` DATE NULL,
    `notas` TEXT NULL,
    `motivo_baja` VARCHAR(500) NULL,
    `baja_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iact_placa_no_vacia` CHECK (`codigo_placa` <> ''),
    CONSTRAINT `chk_iact_costo_no_negativo` CHECK (`costo_adquisicion` >= 0),
    CONSTRAINT `fk_iact_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_baja_actor` FOREIGN KEY (`baja_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iact_codigo_placa` (`codigo_placa`),
    INDEX `idx_iact_articulo` (`articulo_id`),
    INDEX `idx_iact_propiedad_ubicacion` (`propiedad_id`, `ubicacion_id`),
    INDEX `idx_iact_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ejemplares individuales de activos serializables (Smart TVs, frigobares, etc.)';

-- ----------------------------------------------------------------------------
-- 55. Dotaciones Estándar de Unidades (INVENTARIO-1 / D-078)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_dotaciones_estandar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_unidad_id` INT UNSIGNED NULL,
    `unidad_id` BIGINT UNSIGNED NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_estandar` DECIMAL(15,4) NOT NULL,
    `notas` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ide_destino_xor` CHECK (
        (`tipo_unidad_id` IS NOT NULL AND `unidad_id` IS NULL) OR
        (`tipo_unidad_id` IS NULL AND `unidad_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_ide_cantidad_positiva` CHECK (`cantidad_estandar` > 0),
    CONSTRAINT `fk_ide_tipo_unidad` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ide_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ide_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_ide_tipo_unidad_articulo` (`tipo_unidad_id`, `articulo_id`),
    UNIQUE KEY `uq_ide_unidad_articulo` (`unidad_id`, `articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dotación esperada o reglamentaria por tipo de unidad o por unidad específica';

-- ----------------------------------------------------------------------------
-- 56. Secuencias Concurrency-Safe para Folios (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_secuencias` (
    `tipo_documento` VARCHAR(40) NOT NULL COMMENT 'CONTRATO_ARRENDAMIENTO, RECIBO_PAGO, etc.',
    `periodo_ym` CHAR(6) NOT NULL COMMENT 'YYYYMM para reinicio mensual ordenado',
    `ultimo_correlativo` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tipo_documento`, `periodo_ym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Secuencias concurrency-safe para folios documentales (DOC-ARR-YYYYMM-XXXX)';

-- ----------------------------------------------------------------------------
-- 57. Catálogo Maestro de Plantillas Documentales (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_plantillas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(40) NOT NULL COMMENT 'Identificador único canónico (CONTRATO_ARRENDAMIENTO)',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` VARCHAR(500) NULL,
    `origen_tipo_permitido` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA') NOT NULL,
    `orientacion` ENUM('PORTRAIT', 'LANDSCAPE') NOT NULL DEFAULT 'PORTRAIT',
    `tamano_papel` ENUM('A4', 'LETTER', 'TICKET_80MM') NOT NULL DEFAULT 'A4',
    `requiere_membrete` TINYINT(1) NOT NULL DEFAULT 1,
    `archivo_membrete_fondo` VARCHAR(255) NULL COMMENT 'Ruta inmutable en storage/membretes/',
    `margen_superior_mm` INT NOT NULL DEFAULT 35,
    `margen_inferior_mm` INT NOT NULL DEFAULT 28,
    `margen_izquierdo_mm` INT NOT NULL DEFAULT 20,
    `margen_derecho_mm` INT NOT NULL DEFAULT 20,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_docp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_docp_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_docp_m_sup_no_negativo` CHECK (`margen_superior_mm` >= 0),
    CONSTRAINT `chk_docp_m_inf_no_negativo` CHECK (`margen_inferior_mm` >= 0),
    CONSTRAINT `chk_docp_m_izq_no_negativo` CHECK (`margen_izquierdo_mm` >= 0),
    CONSTRAINT `chk_docp_m_der_no_negativo` CHECK (`margen_derecho_mm` >= 0),
    UNIQUE KEY `uq_docp_codigo` (`codigo`),
    INDEX `idx_docp_origen_tipo` (`origen_tipo_permitido`),
    INDEX `idx_docp_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de plantillas documentales';

-- ----------------------------------------------------------------------------
-- 58. Versiones Inmutables de Contenido de Plantillas (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_plantilla_versiones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plantilla_id` BIGINT UNSIGNED NOT NULL,
    `numero_version` INT NOT NULL,
    `titulo_documento` VARCHAR(200) NOT NULL,
    `cuerpo_html` MEDIUMTEXT NOT NULL COMMENT 'Plantilla HTML con shortcodes {{entidad.propiedad}}',
    `estilos_css` TEXT NULL COMMENT 'CSS documental específico para print/Dompdf',
    `notas_version` VARCHAR(500) NULL,
    `es_activa` TINYINT(1) NOT NULL DEFAULT 0,
    `version_activa_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `es_activa` = 1 THEN `plantilla_id` ELSE NULL END
    ) VIRTUAL COMMENT 'Garantiza a nivel InnoDB que solo exista una versión activa por plantilla',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_dpv_version_positiva` CHECK (`numero_version` > 0),
    CONSTRAINT `chk_dpv_titulo_no_vacio` CHECK (`titulo_documento` <> ''),
    CONSTRAINT `fk_dpv_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `documento_plantillas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dpv_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_dpv_plantilla_version` (`plantilla_id`, `numero_version`),
    UNIQUE KEY `uq_dpv_plantilla_activa` (`version_activa_idx`),
    INDEX `idx_dpv_plantilla` (`plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Versiones inmutables de contenido de plantillas documentales';

-- ----------------------------------------------------------------------------
-- 59. Documentos Emitidos con Snapshots e Integridad Criptográfica (DOCUMENTOS-1 / D-079)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_emitidos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_folio` VARCHAR(50) NOT NULL COMMENT 'DOC-ARR-YYYYMM-XXXX',
    `plantilla_id` BIGINT UNSIGNED NOT NULL,
    `plantilla_version_id` BIGINT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA') NOT NULL,
    `origen_id` BIGINT UNSIGNED NOT NULL,
    `snapshot_datos_json` JSON NOT NULL COMMENT 'Valores crudos de los shortcodes en orden determinista',
    `snapshot_html` MEDIUMTEXT NOT NULL COMMENT 'HTML resuelto exactamente como fue renderizado',
    `ruta_archivo_pdf` VARCHAR(255) NOT NULL COMMENT 'Ruta física relativa a storage/documentos/',
    `tamano_bytes` INT UNSIGNED NOT NULL,
    `hash_pdf_sha256` CHAR(64) NOT NULL COMMENT 'Hash criptográfico del binario PDF almacenado',
    `hash_snapshot_sha256` CHAR(64) NOT NULL COMMENT 'Hash del HTML compilado congelado',
    `numero_paginas` INT UNSIGNED NOT NULL DEFAULT 1,
    `emitido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `emitido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM('VALIDO', 'ANULADO') NOT NULL DEFAULT 'VALIDO',
    `motivo_anulacion` VARCHAR(500) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `chk_demit_folio_no_vacio` CHECK (`codigo_folio` <> ''),
    CONSTRAINT `chk_demit_tamano_positivo` CHECK (`tamano_bytes` > 0),
    CONSTRAINT `fk_demit_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `documento_plantillas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_version` FOREIGN KEY (`plantilla_version_id`) REFERENCES `documento_plantilla_versiones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_emisor` FOREIGN KEY (`emitido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_demit_codigo_folio` (`codigo_folio`),
    INDEX `idx_demit_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_demit_hash` (`hash_pdf_sha256`),
    INDEX `idx_demit_estado` (`estado`),
    INDEX `idx_demit_plantilla` (`plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Documentos emitidos con snapshot inmutable y hash SHA-256';

-- ----------------------------------------------------------------------------
-- 60. Incidencias Documentales (Auditoría de Archivos Ausentes o Corruptos)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `documento_emitido_id` BIGINT UNSIGNED NOT NULL,
    `tipo_incidencia` ENUM('ARCHIVO_FALTANTE', 'HASH_NO_COINCIDE', 'ERROR_LECTURA') NOT NULL,
    `descripcion` VARCHAR(500) NOT NULL,
    `detectado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `detectado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resuelto` TINYINT(1) NOT NULL DEFAULT 0,
    `resuelto_en` DATETIME NULL,
    `resolucion_notas` VARCHAR(500) NULL,
    CONSTRAINT `fk_dinc_documento` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dinc_actor` FOREIGN KEY (`detectado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_dinc_documento` (`documento_emitido_id`),
    INDEX `idx_dinc_resuelto` (`resuelto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de auditoría de inconsistencias físicas documentales';

-- ----------------------------------------------------------------------------
-- SEMILLAS DOCUMENTOS-1 (D-079): Permisos, Plantilla Canónica V1 y Menú
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('documentos.ver', 'Ver módulo de documentos y descargas', 'Consultar plantillas, versiones y documentos emitidos', 'documentos', 'ACTIVO', 1),
('documentos.emitir', 'Emitir contratos y documentos oficiales', 'Generar versiones oficiales de contratos y actas con folio legal', 'documentos', 'ACTIVO', 1),
('documentos.descargar', 'Descargar archivos PDF emitidos', 'Descargar PDFs binarios con verificación criptográfica de hash', 'documentos', 'ACTIVO', 1),
('documentos.regenerar', 'Regenerar archivos PDF desde snapshot', 'Reconstruir binarios ante discrepancias de hash o faltantes físicos', 'documentos', 'ACTIVO', 1),
('documentos.anular', 'Anular formalmente documentos emitidos', 'Revocar la validez legal de folios emitidos preservando el histórico', 'documentos', 'ACTIVO', 1),
('documentos.plantillas.gestionar', 'Gestionar plantillas y versiones', 'Crear plantillas y publicar nuevas versiones inmutables', 'documentos', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo LIKE 'documentos.%'
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

INSERT INTO `documento_plantillas` (
    `id`, `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`
) VALUES (
    1,
    'CONTRATO_ARRENDAMIENTO',
    'Contrato de Arrendamiento Inmobiliario',
    'Plantilla oficial canónica A4 para formalización de contratos de arrendamiento',
    'ARRENDAMIENTO',
    'portrait',
    'A4',
    1,
    'membrete_a4_canonica_v1.png',
    35,
    28,
    20,
    20,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'documentos_motor', 'Documentos', 'fa-solid fa-file-shield', '/documentos', 8, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'documentos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 61. Solicitudes de Compra / Requerimientos Internos (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_solicitudes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato SOL-YYYYMM-XXXX',
    `departamento_area` VARCHAR(80) NOT NULL COMMENT 'Pisos, Recepción, Mantenimiento, etc.',
    `almacen_destino_id` BIGINT UNSIGNED NULL COMMENT 'Almacén físico sugerido',
    `unidad_destino_id` BIGINT UNSIGNED NULL COMMENT 'Habitación/unidad específica si aplica',
    `fecha_limite_requerida` DATE NOT NULL,
    `justificacion` TEXT NOT NULL,
    `estado` ENUM('BORRADOR', 'PENDIENTE_APROBACION', 'APROBADA', 'RECHAZADA', 'ATENDIDA', 'ANULADA') NOT NULL DEFAULT 'BORRADOR',
    `motivo_rechazo` TEXT NULL,
    `solicitado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_csol_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_csol_area_no_vacia` CHECK (`departamento_area` <> ''),
    CONSTRAINT `chk_csol_justificacion_no_vacia` CHECK (`justificacion` <> ''),
    CONSTRAINT `fk_csol_almacen` FOREIGN KEY (`almacen_destino_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_unidad` FOREIGN KEY (`unidad_destino_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_solicitante` FOREIGN KEY (`solicitado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_csol_codigo` (`codigo`),
    INDEX `idx_csol_estado` (`estado`),
    INDEX `idx_csol_area` (`departamento_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Requerimientos internos de compras de bienes o servicios';

-- ----------------------------------------------------------------------------
-- 62. Líneas de Solicitud de Compra (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_solicitud_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `solicitud_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('BIEN', 'SERVICIO') NOT NULL DEFAULT 'BIEN',
    `articulo_id` BIGINT UNSIGNED NULL,
    `descripcion_servicio` VARCHAR(255) NULL,
    `cantidad_solicitada` DECIMAL(15,4) NOT NULL,
    `especificaciones_tecnicas` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cslin_tipo_consistente` CHECK (
        (`tipo_linea` = 'BIEN' AND `articulo_id` IS NOT NULL) OR
        (`tipo_linea` = 'SERVICIO' AND `descripcion_servicio` IS NOT NULL AND `descripcion_servicio` <> '')
    ),
    CONSTRAINT `chk_cslin_cant_positiva` CHECK (`cantidad_solicitada` > 0),
    CONSTRAINT `fk_cslin_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `compra_solicitudes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cslin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_cslin_solicitud` (`solicitud_id`),
    INDEX `idx_cslin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de bienes o servicios solicitados';

-- ----------------------------------------------------------------------------
-- 63. Órdenes de Compra (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_ordenes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato OC-YYYYMM-XXXX',
    `solicitud_id` BIGINT UNSIGNED NULL COMMENT 'Opcional, NULL para órdenes directas',
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `condicion_pago` ENUM('CONTADO', 'CREDITO_15D', 'CREDITO_30D', 'ADELANTADO') NOT NULL DEFAULT 'CONTADO',
    `almacen_entrega_id` BIGINT UNSIGNED NULL COMMENT 'Almacén físico receptor para bienes',
    `fecha_entrega_esperada` DATE NOT NULL,
    `notas_comerciales` TEXT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `descuento_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estado_comercial` ENUM('BORRADOR', 'APROBADA', 'CERRADA', 'CANCELADA') NOT NULL DEFAULT 'BORRADOR',
    `estado_recepcion` ENUM('SIN_RECEPCION', 'RECEPCION_PARCIAL', 'RECEPCION_TOTAL') NOT NULL DEFAULT 'SIN_RECEPCION',
    `estado_facturacion` ENUM('SIN_FACTURAR', 'FACTURADA_PARCIAL', 'FACTURADA_TOTAL') NOT NULL DEFAULT 'SIN_FACTURAR',
    `estado_pago` ENUM('PENDIENTE', 'PAGADO_PARCIAL', 'PAGADO_TOTAL') NOT NULL DEFAULT 'PENDIENTE',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NULL,
    `motivo_cancelacion` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cord_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cord_subtotal_no_negativo` CHECK (`subtotal` >= 0),
    CONSTRAINT `chk_cord_impuesto_no_negativo` CHECK (`impuesto_total` >= 0),
    CONSTRAINT `chk_cord_descuento_no_negativo` CHECK (`descuento_total` >= 0),
    CONSTRAINT `chk_cord_total_no_negativo` CHECK (`total` >= 0),
    CONSTRAINT `fk_cord_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `compra_solicitudes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cord_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_almacen` FOREIGN KEY (`almacen_entrega_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cord_codigo` (`codigo`),
    INDEX `idx_cord_proveedor` (`proveedor_id`),
    INDEX `idx_cord_estado_comercial` (`estado_comercial`),
    INDEX `idx_cord_estado_recepcion` (`estado_recepcion`),
    INDEX `idx_cord_estado_facturacion` (`estado_facturacion`),
    INDEX `idx_cord_estado_pago` (`estado_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Compromisos contractuales y comerciales de compra formal';

-- ----------------------------------------------------------------------------
-- 64. Líneas de Orden de Compra (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_orden_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('BIEN', 'SERVICIO') NOT NULL DEFAULT 'BIEN',
    `articulo_id` BIGINT UNSIGNED NULL,
    `descripcion_servicio` VARCHAR(255) NULL,
    `cantidad_pactada` DECIMAL(15,4) NOT NULL,
    `precio_unitario` DECIMAL(15,4) NOT NULL,
    `subtotal_linea` DECIMAL(15,2) NOT NULL,
    `impuesto_linea` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_linea` DECIMAL(15,2) NOT NULL,
    `cantidad_aceptada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Acumulado aceptado en recepciones/conformidades',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_colin_tipo_consistente` CHECK (
        (`tipo_linea` = 'BIEN' AND `articulo_id` IS NOT NULL) OR
        (`tipo_linea` = 'SERVICIO' AND `descripcion_servicio` IS NOT NULL AND `descripcion_servicio` <> '')
    ),
    CONSTRAINT `chk_colin_cant_positiva` CHECK (`cantidad_pactada` > 0),
    CONSTRAINT `chk_colin_precio_no_negativo` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_colin_subtotal_no_negativo` CHECK (`subtotal_linea` >= 0),
    CONSTRAINT `chk_colin_total_no_negativo` CHECK (`total_linea` >= 0),
    CONSTRAINT `chk_colin_aceptada_no_negativa` CHECK (`cantidad_aceptada` >= 0),
    CONSTRAINT `fk_colin_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_colin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_colin_orden` (`orden_compra_id`),
    INDEX `idx_colin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Líneas tipadas de la orden de compra con cantidades y precios';

-- ----------------------------------------------------------------------------
-- 65. Recepciones Físicas en Almacén (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_recepciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato REC-YYYYMM-XXXX',
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `almacen_id` BIGINT UNSIGNED NOT NULL,
    `numero_guia_remision` VARCHAR(50) NULL,
    `fecha_recepcion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `observaciones` TEXT NULL,
    `recibido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_crec_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_crec_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crec_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crec_receptor` FOREIGN KEY (`recibido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_crec_codigo` (`codigo`),
    INDEX `idx_crec_orden` (`orden_compra_id`),
    INDEX `idx_crec_almacen` (`almacen_id`),
    INDEX `idx_crec_fecha` (`fecha_recepcion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ingresos físicos de bienes a almacén contrastados con la orden';

-- ----------------------------------------------------------------------------
-- 66. Líneas de Recepción Física (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_recepcion_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recepcion_id` BIGINT UNSIGNED NOT NULL,
    `orden_linea_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_recibida` DECIMAL(15,4) NOT NULL,
    `cantidad_aceptada` DECIMAL(15,4) NOT NULL,
    `cantidad_rechazada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `motivo_rechazo` VARCHAR(255) NULL,
    `movimiento_inventario_id` BIGINT UNSIGNED NULL COMMENT 'FK a Kardex ENTRADA_COMPRA bajo D-078',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_crlin_cant_consistente` CHECK (`cantidad_recibida` = `cantidad_aceptada` + `cantidad_rechazada`),
    CONSTRAINT `chk_crlin_recibida_positiva` CHECK (`cantidad_recibida` > 0),
    CONSTRAINT `chk_crlin_aceptada_no_neg` CHECK (`cantidad_aceptada` >= 0),
    CONSTRAINT `chk_crlin_rechazada_no_neg` CHECK (`cantidad_rechazada` >= 0),
    CONSTRAINT `fk_crlin_recepcion` FOREIGN KEY (`recepcion_id`) REFERENCES `compra_recepciones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_crlin_orden_linea` FOREIGN KEY (`orden_linea_id`) REFERENCES `compra_orden_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crlin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crlin_mov_inv` FOREIGN KEY (`movimiento_inventario_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_crlin_recepcion` (`recepcion_id`),
    INDEX `idx_crlin_orden_linea` (`orden_linea_id`),
    INDEX `idx_crlin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle físico de bienes aceptados hacia Kardex y rechazados';

-- ----------------------------------------------------------------------------
-- 67. Actas de Conformidad de Servicio (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_conformidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CONF-YYYYMM-XXXX',
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `orden_linea_id` BIGINT UNSIGNED NOT NULL,
    `fecha_conformidad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `informe_trabajo_realizado` TEXT NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cconf_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cconf_informe_no_vacio` CHECK (`informe_trabajo_realizado` <> ''),
    CONSTRAINT `fk_cconf_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cconf_orden_linea` FOREIGN KEY (`orden_linea_id`) REFERENCES `compra_orden_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cconf_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cconf_codigo` (`codigo`),
    INDEX `idx_cconf_orden` (`orden_compra_id`),
    INDEX `idx_cconf_orden_linea` (`orden_linea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Actas de conformidad técnica para líneas de servicios (cero Kardex)';

-- ----------------------------------------------------------------------------
-- 68. Comprobantes Fiscales del Proveedor (COMPRAS-1 / D-080)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_comprobantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'NOTA_CREDITO', 'NOTA_DEBITO') NOT NULL,
    `serie` VARCHAR(10) NOT NULL,
    `numero` VARCHAR(20) NOT NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `estado_matching` ENUM('CONFORME', 'CON_DIFERENCIA', 'OBSERVADO') NOT NULL DEFAULT 'CONFORME',
    `observaciones_matching` TEXT NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ccomp_serie_no_vacia` CHECK (`serie` <> ''),
    CONSTRAINT `chk_ccomp_numero_no_vacio` CHECK (`numero` <> ''),
    CONSTRAINT `chk_ccomp_total_positivo` CHECK (`total` > 0),
    CONSTRAINT `fk_ccomp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccomp_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccomp_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_ccomp_fiscal` (`proveedor_id`, `tipo_comprobante`, `serie`, `numero`),
    INDEX `idx_ccomp_orden` (`orden_compra_id`),
    INDEX `idx_ccomp_matching` (`estado_matching`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Comprobantes tributarios de compras recibidos de proveedores';

-- ----------------------------------------------------------------------------
-- 69. Aplicaciones y Matching M:N Comprobante vs Recepciones/Conformidades
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_comprobante_aplicaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `comprobante_id` BIGINT UNSIGNED NOT NULL,
    `recepcion_linea_id` BIGINT UNSIGNED NULL,
    `conformidad_id` BIGINT UNSIGNED NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ccapp_origen_exclusivo` CHECK (
        (`recepcion_linea_id` IS NOT NULL AND `conformidad_id` IS NULL) OR
        (`recepcion_linea_id` IS NULL AND `conformidad_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_ccapp_monto_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `fk_ccapp_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `compra_comprobantes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ccapp_recepcion_linea` FOREIGN KEY (`recepcion_linea_id`) REFERENCES `compra_recepcion_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccapp_conformidad` FOREIGN KEY (`conformidad_id`) REFERENCES `compra_conformidades` (`id`) ON DELETE RESTRICT,
    INDEX `idx_ccapp_comprobante` (`comprobante_id`),
    INDEX `idx_ccapp_rec_linea` (`recepcion_linea_id`),
    INDEX `idx_ccapp_conformidad` (`conformidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Vínculo formal 3-way matching entre comprobante y recepciones/conformidades';

-- ----------------------------------------------------------------------------
-- 70. Cuentas por Pagar (Obligaciones Financieras Devengadas) (COMPRAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_por_pagar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CXP-YYYYMM-XXXX',
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `comprobante_id` BIGINT UNSIGNED NOT NULL,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL,
    `monto_amortizado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_pendiente` DECIMAL(15,2) NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `estado` ENUM('PENDIENTE', 'AMORTIZADA_PARCIAL', 'LIQUIDADA', 'ANULADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cxp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cxp_total_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_cxp_amort_no_negativa` CHECK (`monto_amortizado` >= 0),
    CONSTRAINT `chk_cxp_saldo_no_negativo` CHECK (`saldo_pendiente` >= 0),
    CONSTRAINT `chk_cxp_coherencia_saldos` CHECK (`saldo_pendiente` = `monto_total` - `monto_amortizado`),
    CONSTRAINT `fk_cxp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxp_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `compra_comprobantes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxp_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cxp_codigo` (`codigo`),
    UNIQUE KEY `uq_cxp_comprobante` (`comprobante_id`),
    INDEX `idx_cxp_proveedor` (`proveedor_id`),
    INDEX `idx_cxp_orden` (`orden_compra_id`),
    INDEX `idx_cxp_estado` (`estado`),
    INDEX `idx_cxp_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Pasivos formales devengados con proveedores';

-- ----------------------------------------------------------------------------
-- 71. Pagos y Amortizaciones de Cuentas por Pagar (COMPRAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cxp_pagos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cuenta_pagar_id` BIGINT UNSIGNED NOT NULL,
    `medio_pago` ENUM('EFECTIVO_CAJA', 'TRANSFERENCIA_BANCARIA', 'CHEQUE', 'BILLETERA_DIGITAL') NOT NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `numero_operacion_bancaria` VARCHAR(100) NULL,
    `movimiento_caja_id` BIGINT UNSIGNED NULL COMMENT 'FK a movimientos_caja si se pagó desde caja chica',
    `movimiento_bancario_id` BIGINT UNSIGNED NULL COMMENT 'FK a movimientos_bancarios si fue transferencia/banco',
    `notas` TEXT NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cxpp_monto_positivo` CHECK (`monto` > 0),
    CONSTRAINT `fk_cxpp_cxp` FOREIGN KEY (`cuenta_pagar_id`) REFERENCES `cuentas_por_pagar` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_mov_banco` FOREIGN KEY (`movimiento_bancario_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_cxpp_cxp` (`cuenta_pagar_id`),
    INDEX `idx_cxpp_fecha` (`fecha_pago`),
    INDEX `idx_cxpp_mov_caja` (`movimiento_caja_id`),
    INDEX `idx_cxpp_mov_banco` (`movimiento_bancario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Egresos financieros aplicados a cuentas por pagar';

-- ----------------------------------------------------------------------------
-- 72. Historial Inmutable de Estados y Auditoría D-061 (COMPRAS-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entidad_tipo` ENUM('SOLICITUD', 'ORDEN_COMPRA', 'CUENTA_POR_PAGAR') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(50) NULL,
    `estado_nuevo` VARCHAR(50) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `motivo` TEXT NULL,
    `correlacion_id` VARCHAR(64) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_chist_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_chist_entidad` (`entidad_tipo`, `entidad_id`),
    INDEX `idx_chist_actor` (`actor_id`),
    INDEX `idx_chist_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad inmutable de transiciones y auditoría D-061 para compras';

-- Permisos y Menú para Compras
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('compras.ver', 'Ver módulo de compras', 'COMPRAS', 'Acceso a la vista y listados de abastecimiento y compras', NOW()),
('compras.solicitudes.crear', 'Crear solicitudes de compra', 'COMPRAS', 'Registrar requerimientos internos de compra', NOW()),
('compras.solicitudes.aprobar', 'Aprobar solicitudes de compra', 'COMPRAS', 'Aprobar o rechazar requerimientos internos', NOW()),
('compras.ordenes.crear', 'Crear órdenes de compra', 'COMPRAS', 'Formular órdenes de compra directas o desde solicitud', NOW()),
('compras.ordenes.aprobar', 'Aprobar órdenes de compra', 'COMPRAS', 'Aprobar formalmente y congelar condiciones de la OC', NOW()),
('compras.recepciones.registrar', 'Registrar recepciones físicas', 'COMPRAS', 'Recibir bienes en almacén y afectar Kardex', NOW()),
('compras.conformidad.registrar', 'Registrar conformidad de servicios', 'COMPRAS', 'Emitir actas de conformidad técnica para servicios', NOW()),
('compras.comprobantes.registrar', 'Registrar comprobantes de proveedor', 'COMPRAS', 'Registrar facturas/boletas y ejecutar 3-way matching', NOW()),
('compras.cuentas_pagar.ver', 'Ver cuentas por pagar', 'COMPRAS', 'Consultar obligaciones financieras con proveedores', NOW()),
('compras.pagos.registrar', 'Registrar pagos a proveedores', 'COMPRAS', 'Amortizar cuentas por pagar con egresos de caja o banco', NOW()),
('compras.anular', 'Anular operaciones de compra', 'COMPRAS', 'Cancelar órdenes o anular comprobantes justificados', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'compras.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'compras.%';

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'compras', 'Compras', 'fa-solid fa-cart-shopping', '/compras', 9, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'compras.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`, `orientacion`,
    `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`, `estado`
) VALUES (
    'ORDEN_COMPRA',
    'Orden de Compra Oficial A4',
    'Documento comercial institucional emitido a proveedores con detalle de bienes/servicios pactados',
    'COMPRA',
    'PORTRAIT',
    'A4',
    1,
    'storage/membretes/membrete_a4_canonica_v1.png',
    35, 28, 20, 20,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Versión 1 activa para la plantilla ORDEN_COMPRA
INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`,
    `cuerpo_html`, `estilos_css`, `notas_version`, `es_activa`, `creado_por_actor_id`
)
SELECT
    p.`id`,
    1,
    'ORDEN DE COMPRA OFICIAL',
    '<div class=\"documento-orden-compra\">
    <div class=\"encabezado-orden\">
        <h1 class=\"titulo-principal\">ORDEN DE COMPRA</h1>
        <p class=\"subtitulo-folio\">FOLIO N°: <strong>{{documento.folio}}</strong> | CÓDIGO OC: <strong>{{orden.codigo}}</strong></p>
    </div>
    <div class=\"seccion-datos\">
        <table class=\"tabla-info\">
            <tr>
                <td class=\"campo-etiqueta\"><strong>Proveedor:</strong></td>
                <td class=\"campo-valor\">{{proveedor.razon_social}} (RUC/DOC: {{proveedor.numero_documento}})</td>
                <td class=\"campo-etiqueta\"><strong>Fecha Emisión:</strong></td>
                <td class=\"campo-valor\">{{orden.fecha}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Contacto:</strong></td>
                <td class=\"campo-valor\">{{proveedor.contacto}} - {{proveedor.telefono}}</td>
                <td class=\"campo-etiqueta\"><strong>Fecha Entrega:</strong></td>
                <td class=\"campo-valor\">{{orden.fecha_entrega}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Dirección:</strong></td>
                <td class=\"campo-valor\">{{proveedor.direccion}}</td>
                <td class=\"campo-etiqueta\"><strong>Condición Pago:</strong></td>
                <td class=\"campo-valor\">{{orden.condicion_pago}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Almacén Entrega:</strong></td>
                <td class=\"campo-valor\" colspan=\"3\">{{almacen.nombre}} - {{almacen.direccion}}</td>
            </tr>
        </table>
    </div>
    <div class=\"seccion-lineas\">
        <h3 class=\"seccion-subtitulo\">Detalle de Bienes y Servicios Solicitados</h3>
        {{tabla_lineas}}
    </div>
    <div class=\"seccion-totales\">
        <table class=\"tabla-totales\">
            <tr>
                <td class=\"tot-etiqueta\">Subtotal:</td>
                <td class=\"tot-valor\">{{totales.moneda}} {{totales.subtotal}}</td>
            </tr>
            <tr>
                <td class=\"tot-etiqueta\">Impuestos (IGV):</td>
                <td class=\"tot-valor\">{{totales.moneda}} {{totales.impuesto}}</td>
            </tr>
            <tr>
                <td class=\"tot-etiqueta font-bold\">TOTAL:</td>
                <td class=\"tot-valor font-bold\">{{totales.moneda}} {{totales.total}}</td>
            </tr>
        </table>
        <p class=\"monto-texto\">SON: {{totales.texto}}</p>
    </div>
    <div class=\"seccion-observaciones\">
        <p><strong>Observaciones / Notas:</strong> {{orden.notas}}</p>
    </div>
    <div class=\"seccion-firmas\">
        <table class=\"tabla-firmas\">
            <tr>
                <td class=\"firma-caja\">
                    <div class=\"linea-firma\"></div>
                    <p>Emitido por: Compras y Abastecimiento<br>Camargo Hostelería S.A.C.</p>
                </td>
                <td class=\"firma-caja\">
                    <div class=\"linea-firma\"></div>
                    <p>Aprobado por: Gerencia / Administración<br>Camargo Hostelería S.A.C.</p>
                </td>
            </tr>
        </table>
    </div>
</div>',
    'body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #222; }
.documento-orden-compra { width: 100%; margin: 0 auto; }
.encabezado-orden { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
.titulo-principal { font-size: 18pt; margin: 0; color: #0d6efd; text-transform: uppercase; }
.subtitulo-folio { font-size: 10pt; margin: 5px 0 0 0; color: #555; }
.tabla-info { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
.tabla-info td { padding: 4px 6px; font-size: 9pt; }
.campo-etiqueta { width: 18%; color: #333; }
.campo-valor { width: 32%; }
.seccion-subtitulo { font-size: 11pt; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; color: #333; }
.tabla-lineas-doc { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
.tabla-lineas-doc th { background-color: #f2f5f9; border: 1px solid #ccc; padding: 6px; font-size: 8.5pt; text-align: center; }
.tabla-lineas-doc td { border: 1px solid #ddd; padding: 5px 6px; font-size: 8.5pt; }
.seccion-totales { width: 100%; margin-top: 10px; }
.tabla-totales { float: right; width: 40%; border-collapse: collapse; margin-bottom: 10px; }
.tabla-totales td { padding: 4px 8px; font-size: 9.5pt; }
.tot-etiqueta { text-align: right; }
.tot-valor { text-align: right; }
.font-bold { font-weight: bold; }
.monto-texto { clear: both; font-style: italic; font-size: 8.5pt; color: #444; padding-top: 5px; }
.seccion-observaciones { margin-top: 15px; font-size: 8.5pt; border-left: 3px solid #0d6efd; padding-left: 8px; }
.seccion-firmas { margin-top: 50px; width: 100%; }
.tabla-firmas { width: 100%; border-collapse: collapse; }
.firma-caja { width: 50%; text-align: center; padding: 0 40px; font-size: 8.5pt; }
.linea-firma { border-top: 1px solid #333; margin-bottom: 5px; }',
    'Versión canónica inicial para emisión oficial de órdenes de compra con membrete A4',
    1,
    1
FROM `documento_plantillas` p
WHERE p.`codigo` = 'ORDEN_COMPRA'
ON DUPLICATE KEY UPDATE `titulo_documento` = VALUES(`titulo_documento`);

SET FOREIGN_KEY_CHECKS = 1;
