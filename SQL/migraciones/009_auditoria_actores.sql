-- ============================================================================
-- Camargo PMS — Migración 009: Núcleo Transversal de Auditoría y Trazabilidad
-- ============================================================================
-- Define las estructuras de base de datos para la auditoría transversal del sistema:
--   actores: Principales y emisores de operaciones (USUARIO, SISTEMA, INTEGRACION, PROVEEDOR_PAGO).
--   auditoria: Registro histórico inmutable de trazabilidad y eventos de negocio.
--
-- Principios vinculantes:
--   ACTOR != USUARIO: Un usuario es una cuenta humana; los actores abarcan sistemas,
--                     integraciones, webhooks y humanos sin crear usuarios ficticios.
--   INMUTABILIDAD: Los registros de auditoría no se modifican ni eliminan desde la aplicación.
--   INTEGRIDAD: Eliminaciones operativas no borran el historial (ON DELETE RESTRICT en actor,
--               ON DELETE SET NULL en usuario).
-- ============================================================================

-- 1. Tabla de Actores
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

-- ----------------------------------------------------------------------------
-- SEMILLAS ESTRUCTURALES DE ACTORES
-- ----------------------------------------------------------------------------

-- Actor estructural de sistema para procesos internos automáticos
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

-- 2. Tabla de Auditoría Transversal
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
