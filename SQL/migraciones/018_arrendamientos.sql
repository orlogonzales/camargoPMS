-- ============================================================================
-- Camargo PMS — Migración 018: Arrendamientos de Mediana y Larga Estancia
-- Fase: ARRENDAMIENTOS-1 / D-076
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Tabla Central de Arrendamientos
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
-- 2. Sujetos del Arrendamiento: Titular Único, Cotitulares y Ocupantes
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
-- 3. Cuotas Periódicas Idempotentes de Renta
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
-- 4. Custodia Segregada de Garantía (Saldo Reconstructible)
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
-- 5. Historial Inmutable de Transiciones de Estado
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
-- 6. Modificaciones a Cuentas Folios para Soportar Arrendamientos (Compatibilidad FINANCIERO-2)
-- ----------------------------------------------------------------------------
-- Ajustar fk_ctaf_reserva a RESTRICT para permitir check constraint en MySQL 8.4
SET @fk_reserva_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ctaf_reserva' AND TABLE_NAME = 'cuentas_folios' AND TABLE_SCHEMA = DATABASE());
SET @sql_drop_fk = IF(@fk_reserva_exists > 0, 'ALTER TABLE `cuentas_folios` DROP FOREIGN KEY `fk_ctaf_reserva`', 'SELECT 1');
PREPARE stmt_drop_fk FROM @sql_drop_fk;
EXECUTE stmt_drop_fk;
DEALLOCATE PREPARE stmt_drop_fk;

ALTER TABLE `cuentas_folios`
    MODIFY COLUMN `reserva_id` BIGINT UNSIGNED NULL;

SET @col_arr_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE COLUMN_NAME = 'arrendamiento_id' AND TABLE_NAME = 'cuentas_folios' AND TABLE_SCHEMA = DATABASE());
SET @sql_arr_col = IF(@col_arr_exists = 0, 'ALTER TABLE `cuentas_folios` ADD COLUMN `arrendamiento_id` BIGINT UNSIGNED NULL AFTER `reserva_id`', 'SELECT 1');
PREPARE stmt_arr_col FROM @sql_arr_col;
EXECUTE stmt_arr_col;
DEALLOCATE PREPARE stmt_arr_col;

SET @fk_res_new = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ctaf_reserva' AND TABLE_NAME = 'cuentas_folios' AND TABLE_SCHEMA = DATABASE());
SET @sql_res_fk = IF(@fk_res_new = 0, 'ALTER TABLE `cuentas_folios` ADD CONSTRAINT `fk_ctaf_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT', 'SELECT 1');
PREPARE stmt_res_fk FROM @sql_res_fk;
EXECUTE stmt_res_fk;
DEALLOCATE PREPARE stmt_res_fk;

SET @fk_arr_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_ctaf_arrendamiento' AND TABLE_NAME = 'cuentas_folios' AND TABLE_SCHEMA = DATABASE());
SET @sql_arr_fk = IF(@fk_arr_exists = 0, 'ALTER TABLE `cuentas_folios` ADD CONSTRAINT `fk_ctaf_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT', 'SELECT 1');
PREPARE stmt_arr_fk FROM @sql_arr_fk;
EXECUTE stmt_arr_fk;
DEALLOCATE PREPARE stmt_arr_fk;

SET @idx_arr_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE INDEX_NAME = 'uq_cuentas_folios_arrendamiento' AND TABLE_NAME = 'cuentas_folios' AND TABLE_SCHEMA = DATABASE());
SET @sql_arr_idx = IF(@idx_arr_exists = 0, 'ALTER TABLE `cuentas_folios` ADD UNIQUE KEY `uq_cuentas_folios_arrendamiento` (`arrendamiento_id`)', 'SELECT 1');
PREPARE stmt_arr_idx FROM @sql_arr_idx;
EXECUTE stmt_arr_idx;
DEALLOCATE PREPARE stmt_arr_idx;

SET @chk_arr_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'chk_ctaf_sujeto_exclusivo' AND TABLE_NAME = 'cuentas_folios' AND TABLE_SCHEMA = DATABASE());
SET @sql_arr_chk = IF(@chk_arr_exists = 0, 'ALTER TABLE `cuentas_folios` ADD CONSTRAINT `chk_ctaf_sujeto_exclusivo` CHECK (((`reserva_id` IS NOT NULL AND `arrendamiento_id` IS NULL) OR (`reserva_id` IS NULL AND `arrendamiento_id` IS NOT NULL)))', 'SELECT 1');
PREPARE stmt_arr_chk FROM @sql_arr_chk;
EXECUTE stmt_arr_chk;
DEALLOCATE PREPARE stmt_arr_chk;

-- ----------------------------------------------------------------------------
-- 7. Ampliación de Origen en Cargos de Cuenta
-- ----------------------------------------------------------------------------
ALTER TABLE `cargos_cuenta`
    MODIFY COLUMN `origen_tipo` ENUM(
        'ALOJAMIENTO_NOCHES',
        'SERVICIO_CONTRATADO',
        'PENALIDAD',
        'AJUSTE_MANUAL',
        'RENTA_ARRENDAMIENTO',
        'DEPOSITO_GARANTIA'
    ) NOT NULL;

-- ----------------------------------------------------------------------------
-- 7b. Ampliación de Tipo de Bloqueo en Inventario Diario
-- ----------------------------------------------------------------------------
ALTER TABLE `inventario_diario_unidades`
    MODIFY COLUMN `tipo_bloqueo` ENUM(
        'BLOQUEO_MANUAL',
        'MANTENIMIENTO',
        'RESERVA',
        'ARRENDAMIENTO'
    ) NOT NULL DEFAULT 'BLOQUEO_MANUAL';

-- ----------------------------------------------------------------------------
-- 8. Permisos RBAC Iniciales
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('arrendamientos.ver', 'Ver contratos de arrendamiento', 'Consultar listado, detalle y estados de arrendamientos', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.crear', 'Crear borradores de arrendamiento', 'Formular nuevos contratos con unidad, fechas y sujetos', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.activar', 'Activar contratos de arrendamiento', 'Poner en vigencia contratos y materializar bloqueos de inventario', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.gestionar', 'Gestionar sujetos y prórrogas', 'Agregar cotitulares, ocupantes y extender plazos contractuales', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.rescindir', 'Rescindir o finalizar contratos', 'Terminación anticipada con causa o cierre regular de contrato', 'arrendamientos', 'ACTIVO', 1),
('arrendamientos.generar_cargos', 'Generar cuotas periódicas de renta', 'Emitir cargos mensuales y liquidar fondos de custodia', 'arrendamientos', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación a SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo IN (
      'arrendamientos.ver',
      'arrendamientos.crear',
      'arrendamientos.activar',
      'arrendamientos.gestionar',
      'arrendamientos.rescindir',
      'arrendamientos.generar_cargos'
  )
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

-- ----------------------------------------------------------------------------
-- 9. Opción de Menú Dinámico bajo 'reservas' (Operaciones)
-- ----------------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;
