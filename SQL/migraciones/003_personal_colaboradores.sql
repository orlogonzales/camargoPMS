-- ============================================================================
-- Camargo PMS — Migración 003: Colaboradores, Cargos, Episodios Laborales
-- y Ajuste Evolutivo de Identidad
-- ============================================================================

-- ----------------------------------------------------------------------------
-- PARTE A: Correcciones Evolutivas de Identidad (IDENTIDAD-1)
-- ----------------------------------------------------------------------------

-- 1. Flexibilización para monónimos y nombres internacionales en personas
ALTER TABLE `personas` DROP CHECK `chk_personas_al_menos_un_apellido`;

-- 2. Evolución de unicidad documental: considerar jurisdicción y país emisor
-- Primero se indexa tipo_documento_id para garantizar el respaldo de fk_documentos_tipo
ALTER TABLE `personas_documentos` ADD INDEX `idx_documentos_tipo` (`tipo_documento_id`);
ALTER TABLE `personas_documentos` DROP INDEX `uq_documentos_tipo_numero`;

-- Columna virtual generada para jurisdicción efectiva (evita debilidad de NULL en índices UNIQUE)
ALTER TABLE `personas_documentos` 
    ADD COLUMN `pais_emisor_efectivo` INT UNSIGNED GENERATED ALWAYS AS (COALESCE(`pais_emisor_id`, 0)) VIRTUAL AFTER `pais_emisor_id`;

ALTER TABLE `personas_documentos` 
    ADD CONSTRAINT `uq_documentos_tipo_emisor_numero` UNIQUE (`tipo_documento_id`, `pais_emisor_efectivo`, `numero_documento`);

-- ----------------------------------------------------------------------------
-- PARTE B: Núcleo de Gestión de Personal y Colaboradores (PERSONAL-1)
-- ----------------------------------------------------------------------------

-- 3. Catálogo dinámico de cargos laborales
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
('MANTENIMIENTO', 'Personal de Mantenimiento', 'Mantenimiento técnico e infraestructura física', 1);

-- 4. Entidad Colaborador (Identidad laboral estable de una Persona)
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

-- 5. Episodios Laborales (Historial inmutable de períodos de vinculación)
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

-- 6. Historial de Asignaciones de Cargos por Episodio
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
