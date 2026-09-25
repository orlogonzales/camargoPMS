-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 005
-- Fase: AUTH-1 (Autenticación Humana, Cuentas de Usuario y Sesiones)
-- ============================================================================
-- Principio vinculante:
-- PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
--
-- La identidad de acceso (Usuario) pertenece directamente a la identidad humana (Persona).
-- Un Colaborador es la relación laboral de una Persona.
-- Se modelan cuentas humanas seguras, sesiones persistentes revocables
-- y registro preventivo de intentos para mitigación de fuerza bruta.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Tabla: usuarios
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `nombre_usuario` VARCHAR(50) NOT NULL,
    `contrasena_hash` VARCHAR(255) NOT NULL,
    `estado` ENUM('ACTIVO', 'BLOQUEADO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `ultimo_acceso_en` DATETIME NULL,
    `contrasena_cambiada_en` DATETIME NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Restricciones de unicidad
    CONSTRAINT `uq_usuarios_persona` UNIQUE (`persona_id`),
    CONSTRAINT `uq_usuarios_nombre_usuario` UNIQUE (`nombre_usuario`),

    -- Integridad referencial: un usuario debe pertenecer a una persona existente
    CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`)
        REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,

    -- Restricción de dominio sobre el estado
    CONSTRAINT `chk_usuarios_estado` CHECK (`estado` IN ('ACTIVO', 'BLOQUEADO', 'INACTIVO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabla: sesiones_usuario
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

    -- Restricción de unicidad del token criptográfico (hash SHA-256)
    CONSTRAINT `uq_sesiones_token_hash` UNIQUE (`token_hash`),

    -- Integridad referencial
    CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`)
        REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,

    -- Índices para optimizar consultas de seguridad, expiración y sesiones activas
    INDEX `idx_sesiones_usuario_id` (`usuario_id`),
    INDEX `idx_sesiones_activas` (`usuario_id`, `revocada_en`, `expira_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabla: intentos_autenticacion (Throttling / Protección contra Fuerza Bruta)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intentos_autenticacion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre_usuario_normalizado` VARCHAR(50) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `exitoso` TINYINT(1) NOT NULL DEFAULT 0,
    `intentado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Índices para consulta por ventanas temporales y limpieza
    INDEX `idx_intentos_ip_fecha` (`ip`, `intentado_en`),
    INDEX `idx_intentos_usuario_fecha` (`nombre_usuario_normalizado`, `intentado_en`),
    INDEX `idx_intentos_limpieza` (`intentado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
