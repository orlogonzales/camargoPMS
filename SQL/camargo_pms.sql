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

SET FOREIGN_KEY_CHECKS = 1;
