-- =============================================================================
-- Camargo PMS — Migración 041
-- Hardening Fiscal: Crédito (R.S. 193-2020), Hospedaje DL 919 (Catálogo 55) y Defensa Cross-CPE
-- =============================================================================

-- 1. Ampliación de cpe_comprobantes con atributos de crédito comercial
ALTER TABLE `cpe_comprobantes`
    ADD COLUMN `forma_pago` ENUM('CONTADO', 'CREDITO') NOT NULL DEFAULT 'CONTADO' COMMENT 'Modalidad de pago comercial según R.S. 193-2020' AFTER `fecha_vencimiento`,
    ADD COLUMN `monto_neto_pendiente` DECIMAL(15,2) NULL COMMENT 'Monto neto pendiente de cobro para operaciones a crédito' AFTER `forma_pago`,
    ADD CONSTRAINT `chk_cpe_monto_pendiente_no_neg` CHECK (`monto_neto_pendiente` IS NULL OR `monto_neto_pendiente` >= 0.00);

-- 2. Creación de cpe_cuotas para calendario fiscal de pagos diferidos
CREATE TABLE IF NOT EXISTS `cpe_cuotas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cpe_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_comprobantes',
    `numero_cuota` INT UNSIGNED NOT NULL COMMENT 'Secuencia ordinal de la cuota (1..N)',
    `monto` DECIMAL(15,2) NOT NULL COMMENT 'Importe exigible de la cuota',
    `fecha_vencimiento` DATE NOT NULL COMMENT 'Fecha límite exigible de pago de la cuota',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cpe_cuota_numero` (`cpe_id`, `numero_cuota`),
    KEY `idx_cpe_cuotas_vencimiento` (`fecha_vencimiento`),
    CONSTRAINT `fk_cpe_cuotas_cpe` FOREIGN KEY (`cpe_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_cuota_num_pos` CHECK (`numero_cuota` > 0),
    CONSTRAINT `chk_cpe_cuota_monto_pos` CHECK (`monto` > 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Calendario fiscal T0 inmutable de cuotas para ventas al crédito (R.S. 193-2020)';

-- 3. Creación de cpe_hospedajes para snapshot T0 de huéspedes y estancias con beneficio DL 919 (Catálogo 55)
CREATE TABLE IF NOT EXISTS `cpe_hospedajes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cpe_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_comprobantes',
    `numero_orden` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Secuencia ordinal del huésped/estancia (1..N)',
    `nombres_apellidos` VARCHAR(200) NOT NULL COMMENT '4007: Nombres y apellidos completos del huésped no domiciliado',
    `tipo_documento` CHAR(1) NOT NULL COMMENT '4008: Catálogo 06 SUNAT (7=Pasaporte, 4=Carnet Extranjería)',
    `numero_documento` VARCHAR(30) NOT NULL COMMENT '4009: Número de pasaporte o documento de identidad',
    `pais_emision_pasaporte` CHAR(2) NOT NULL COMMENT '4000: Código ISO 3166-1 alfa-2 del país emisor del pasaporte',
    `pais_residencia` CHAR(2) NOT NULL COMMENT '4001: Código ISO 3166-1 alfa-2 del país de residencia habitual',
    `fecha_ingreso_pais` DATE NOT NULL COMMENT '4002: Fecha de ingreso al país acreditada (TAM / sello)',
    `fecha_checkin` DATE NOT NULL COMMENT '4003: Fecha de inicio de la estancia en el establecimiento',
    `fecha_checkout` DATE NOT NULL COMMENT '4004: Fecha de finalización de la estancia en el establecimiento',
    `dias_permanencia` SMALLINT UNSIGNED NOT NULL COMMENT '4005: Días acumulados de permanencia en el país (<= 60)',
    `tam_virtual_numero` VARCHAR(50) DEFAULT NULL COMMENT 'Número de TAM Virtual o registro migratorio',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cpe_hospedaje_cpe_id` (`cpe_id`, `id`),
    UNIQUE KEY `uq_cpe_hospedaje_orden` (`cpe_id`, `numero_orden`),
    KEY `idx_cpe_hosp_doc` (`tipo_documento`, `numero_documento`),
    CONSTRAINT `fk_cpe_hospedajes_cpe` FOREIGN KEY (`cpe_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_hosp_orden_pos` CHECK (`numero_orden` > 0),
    CONSTRAINT `chk_cpe_hosp_dias_max` CHECK (`dias_permanencia` > 0 AND `dias_permanencia` <= 60),
    CONSTRAINT `chk_cpe_hosp_fechas` CHECK (`fecha_checkout` >= `fecha_checkin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Snapshot T0 inmutable de huéspedes y estancias con beneficio DL 919 (Catálogo 55)';

-- 4. Ampliación de cpe_lineas para asociar servicios a huéspedes y fechas de consumo con protección cross-CPE
ALTER TABLE `cpe_lineas`
    ADD COLUMN `cpe_hospedaje_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK compuesta hacia cpe_hospedajes(cpe_id, id) para atribución DL 919' AFTER `cpe_id`,
    ADD COLUMN `fecha_consumo` DATE DEFAULT NULL COMMENT '4006: Fecha de consumo para servicios de alimentación y conexos' AFTER `total_linea`,
    ADD KEY `idx_cpe_lineas_cpe_hospedaje` (`cpe_id`, `cpe_hospedaje_id`),
    ADD CONSTRAINT `fk_cpe_lineas_hospedaje_cross` FOREIGN KEY (`cpe_id`, `cpe_hospedaje_id`) REFERENCES `cpe_hospedajes` (`cpe_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
