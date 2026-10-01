-- ============================================================================
-- Camargo PMS — Migración 039: Modelo Soberano de Multi-Folio, Folio Split y
-- Trazabilidad de Transferencias de Cargos entre Cuentas (FINANCIERO-3A)
-- ============================================================================
-- Principios Arquitectónicos y Vinculantes:
-- 1. FOLIO MAESTRO SOBERANO:
--    Toda reserva o arrendamiento posee exactamente un folio principal activo (es_principal = 1).
--    Se erradica la restricción limitante uq_cuentas_folios_reserva (1:1 rígido)
--    y se sustituye por columnas virtuales generadas indexadas con UNIQUE:
--    folio_principal_reserva_idx y folio_principal_arrendamiento_idx.
-- 2. PRESERVACIÓN RETROACTIVA DEL HISTORIAL:
--    Todos los folios existentes se promueven automáticamente a folios principales
--    (es_principal = 1, etiqueta = 'FOLIO PRINCIPAL', folio_padre_id = NULL).
-- 3. PERMISIVIDAD 1:N PARA FOLIOS SECUNDARIOS:
--    Una reserva o arrendamiento puede aperturar N folios secundarios (es_principal = 0),
--    vinculados a su folio maestro mediante `folio_padre_id` y con etiqueta descriptiva.
-- 4. ATOMICIDAD Y TRAZABILIDAD DE SPLIT / TRANSFERENCIA:
--    Se incorpora `cargo_padre_id` en `cargos_cuenta` para vincular cargos hijos derivados
--    de divisiones parciales, y la tabla inmutable `cuenta_folio_transferencias_cargos`
--    para auditoría append-only de todo movimiento inter-folios.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Evolución de la tabla `cuentas_folios` (Multi-Folio 1:N)
-- ----------------------------------------------------------------------------
ALTER TABLE `cuentas_folios`
    ADD COLUMN `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = folio maestro/principal, 0 = secundario' AFTER `persona_titular_id`,
    ADD COLUMN `etiqueta` VARCHAR(100) NOT NULL DEFAULT 'FOLIO PRINCIPAL' COMMENT 'Etiqueta descriptiva del folio (ej. FOLIO PRINCIPAL, EXTRAS HUÉSPED, EMPRESA)' AFTER `es_principal`,
    ADD COLUMN `folio_padre_id` BIGINT UNSIGNED NULL COMMENT 'Puntero al folio maestro si es secundario' AFTER `etiqueta`,
    ADD COLUMN `folio_principal_reserva_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(`es_principal` = 1 AND `estado` != 'ANULADA', `reserva_id`, NULL)
    ) VIRTUAL AFTER `folio_padre_id`,
    ADD COLUMN `folio_principal_arrendamiento_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(`es_principal` = 1 AND `estado` != 'ANULADA', `arrendamiento_id`, NULL)
    ) VIRTUAL AFTER `folio_principal_reserva_idx`;

-- Índices y claves foráneas en `cuentas_folios`
ALTER TABLE `cuentas_folios`
    ADD INDEX `idx_ctaf_reserva` (`reserva_id`),
    ADD INDEX `idx_ctaf_arrendamiento` (`arrendamiento_id`),
    ADD INDEX `idx_ctaf_folio_padre` (`folio_padre_id`),
    ADD CONSTRAINT `fk_ctaf_folio_padre` FOREIGN KEY (`folio_padre_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD UNIQUE KEY `uq_ctaf_folio_principal_reserva` (`folio_principal_reserva_idx`),
    ADD UNIQUE KEY `uq_ctaf_folio_principal_arrendamiento` (`folio_principal_arrendamiento_idx`);

-- Eliminación de restricciones UNIQUE 1:1 restrictivas
ALTER TABLE `cuentas_folios`
    DROP INDEX `uq_cuentas_folios_reserva`,
    DROP INDEX `uq_cuentas_folios_arrendamiento`;

-- ----------------------------------------------------------------------------
-- 2. Evolución de la tabla `cargos_cuenta` (Soporte de Folio Split)
-- ----------------------------------------------------------------------------
ALTER TABLE `cargos_cuenta`
    ADD COLUMN `cargo_padre_id` BIGINT UNSIGNED NULL COMMENT 'Puntero al cargo original si fue producto de un split parcial' AFTER `cuenta_folio_id`,
    ADD INDEX `idx_crgc_cargo_padre` (`cargo_padre_id`),
    ADD CONSTRAINT `fk_crgc_cargo_padre` FOREIGN KEY (`cargo_padre_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- 3. Tabla Inmutable de Trazabilidad: `cuenta_folio_transferencias_cargos`
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuenta_folio_transferencias_cargos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato canónico TRC-YYYYMMDD-XXXX',
    `cargo_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Cargo original en el folio origen',
    `cargo_destino_id` BIGINT UNSIGNED NULL COMMENT 'Nuevo cargo en folio destino si fue split parcial (o NULL si fue transferencia total del mismo cargo)',
    `cuenta_folio_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Folio desde donde se transfiere',
    `cuenta_folio_destino_id` BIGINT UNSIGNED NOT NULL COMMENT 'Folio hacia donde se transfiere',
    `monto_transferido` DECIMAL(15,2) NOT NULL COMMENT 'Monto transferido en la moneda del folio',
    `tipo_operacion` ENUM('TOTAL', 'SPLIT_PARCIAL') NOT NULL DEFAULT 'TOTAL' COMMENT 'Tipo de traslado contable',
    `motivo` VARCHAR(255) NOT NULL COMMENT 'Justificación obligatoria de la transferencia',
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor responsable de la operación (D-061)',
    `transferido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Instante UTC de la transferencia',
    CONSTRAINT `fk_trc_cargo_origen` FOREIGN KEY (`cargo_origen_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_trc_cargo_destino` FOREIGN KEY (`cargo_destino_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `fk_trc_folio_origen` FOREIGN KEY (`cuenta_folio_origen_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_trc_folio_destino` FOREIGN KEY (`cuenta_folio_destino_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_trc_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_trc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_trc_motivo_no_vacio` CHECK (`motivo` <> ''),
    CONSTRAINT `chk_trc_monto_positivo` CHECK (`monto_transferido` > 0),
    CONSTRAINT `chk_trc_folios_distintos` CHECK (`cuenta_folio_origen_id` <> `cuenta_folio_destino_id`),
    UNIQUE KEY `uq_trc_codigo` (`codigo`),
    INDEX `idx_trc_cargo_origen` (`cargo_origen_id`),
    INDEX `idx_trc_cargo_destino` (`cargo_destino_id`),
    INDEX `idx_trc_folio_origen` (`cuenta_folio_origen_id`),
    INDEX `idx_trc_folio_destino` (`cuenta_folio_destino_id`),
    INDEX `idx_trc_transferido_en` (`transferido_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad e historial inmutable de transferencias y splits de cargos entre folios';

SET FOREIGN_KEY_CHECKS = 1;
