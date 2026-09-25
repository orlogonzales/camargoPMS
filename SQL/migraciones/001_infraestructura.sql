-- ============================================================================
-- CAMARGO PMS — Migración 001: Infraestructura Base
-- ============================================================================
-- Crea la tabla técnica de control de migraciones para Camargo PMS.
-- Motor objetivo: MySQL 8.4.3 LTS (InnoDB, utf8mb4_0900_ai_ci)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `migraciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL UNIQUE,
    `lote` INT UNSIGNED NOT NULL DEFAULT 1,
    `ejecutado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Control técnico de migraciones aplicadas en Camargo PMS';
