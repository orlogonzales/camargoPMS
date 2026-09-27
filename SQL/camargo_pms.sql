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
('reservas.expirar', 'Expirar reservas vencidas', 'Permite ejecutar la expiración manual o por comando de reservas con hold vencido', 'reservas', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` IN ('reservas.ver', 'reservas.crear', 'reservas.confirmar', 'reservas.cancelar', 'reservas.expirar')
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
    `tipo_bloqueo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO', 'RESERVA') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
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

SET FOREIGN_KEY_CHECKS = 1;

