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

SET FOREIGN_KEY_CHECKS = 1;
