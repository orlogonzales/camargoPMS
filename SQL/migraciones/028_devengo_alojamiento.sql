-- ============================================================================
-- CAMARGO PMS - MIGRACIÓN 028: Devengo Diario de Alojamiento y Auditoría Nocturna
-- Microfase: DEVENGO-ALOJAMIENTO-1
-- Gobernanza: D-090
-- Tablas creadas: `cierres_hoteleros`, `devengos_alojamiento`
-- Total tablas en BD tras ejecución: 108 tablas
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Tabla: cierres_hoteleros (Auditoría Nocturna / Cierre de Día Hotelero)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cierres_hoteleros` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_hotelera` DATE NOT NULL,
    `timezone_utilizada` VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    `estado` ENUM('EN_PROCESO', 'CERRADO', 'FALLIDO') NOT NULL DEFAULT 'EN_PROCESO',
    `total_estadias_procesadas` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_noches_devengadas` INT UNSIGNED NOT NULL DEFAULT 0,
    `ingreso_alojamiento_neto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `ingreso_alojamiento_impuestos` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `ingreso_alojamiento_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `unidades_totales` INT UNSIGNED NOT NULL DEFAULT 0,
    `unidades_ooo` INT UNSIGNED NOT NULL DEFAULT 0,
    `unidades_vendibles` INT UNSIGNED NOT NULL DEFAULT 0,
    `habitaciones_vendidas` INT UNSIGNED NOT NULL DEFAULT 0,
    `habitaciones_cortesia` INT UNSIGNED NOT NULL DEFAULT 0,
    `ocupacion_porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `adr` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `revpar` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `iniciado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrado_en` DATETIME NULL,
    `ejecutado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `observaciones` TEXT NULL,
    `error_mensaje` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cierre_propiedad_fecha` (`propiedad_id`, `fecha_hotelera`),
    INDEX `idx_ch_estado` (`estado`),
    INDEX `idx_ch_fecha` (`fecha_hotelera`),
    CONSTRAINT `fk_ch_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ch_actor` FOREIGN KEY (`ejecutado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_ch_ocupacion` CHECK (`ocupacion_porcentaje` >= 0.00 AND `ocupacion_porcentaje` <= 100.00),
    CONSTRAINT `chk_ch_ingreso_neto` CHECK (`ingreso_alojamiento_neto` >= 0.00),
    CONSTRAINT `chk_ch_ingreso_total` CHECK (`ingreso_alojamiento_total` >= 0.00),
    CONSTRAINT `chk_ch_adr` CHECK (`adr` >= 0.00),
    CONSTRAINT `chk_ch_revpar` CHECK (`revpar` >= 0.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cierres soberanos de fecha hotelera y snapshots de inventario vendible';

-- ----------------------------------------------------------------------------
-- 2. Tabla: devengos_alojamiento (Libro Diario de Devengos por Noche Hotelera)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devengos_alojamiento` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(30) NOT NULL,
    `cierre_hotelero_id` BIGINT UNSIGNED NULL,
    `estadia_id` BIGINT UNSIGNED NOT NULL,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `reserva_unidad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `cargo_cuenta_id` BIGINT UNSIGNED NULL,
    `fecha_hotelera` DATE NOT NULL,
    `noche_indice` INT UNSIGNED NOT NULL,
    `total_noches_estadia` INT UNSIGNED NOT NULL,
    `secuencia` INT UNSIGNED NOT NULL DEFAULT 1,
    `tarifa_base_noche` DECIMAL(15,2) NOT NULL,
    `descuento_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `importe_neto` DECIMAL(15,2) NOT NULL,
    `importe_total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `es_cortesia` TINYINT(1) NOT NULL DEFAULT 0,
    `origen_tarifa` ENUM('TARIFA_NOCTURNA_PACTADA', 'DISTRIBUCION_CONTRATO_UNIFORME', 'AJUSTE_OPERACIONAL') NOT NULL DEFAULT 'DISTRIBUCION_CONTRATO_UNIFORME',
    `metodo_distribucion` ENUM('TARIFA_EXPLICITA', 'DISTRIBUCION_UNIFORME', 'AJUSTE_RESIDUAL') NOT NULL DEFAULT 'DISTRIBUCION_UNIFORME',
    `timezone_utilizada` VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    `tarifa_snapshot` JSON NULL,
    `estado` ENUM('DEVENGADO', 'REVERTIDO') NOT NULL DEFAULT 'DEVENGADO',
    `metodo_devengo` ENUM('NIGHT_AUDIT', 'CHECKOUT_ANTICIPADO', 'MANUAL_SUPERVISADO') NOT NULL DEFAULT 'NIGHT_AUDIT',
    `reverso_de_id` BIGINT UNSIGNED NULL,
    `motivo_reversion` VARCHAR(255) NULL,
    `devengado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `devengado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `revertido_en` DATETIME NULL,
    `revertido_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dev_codigo` (`codigo`),
    UNIQUE KEY `uq_devengo_estadia_fecha_sec` (`estadia_id`, `fecha_hotelera`, `secuencia`),
    INDEX `idx_dev_prop_fecha` (`propiedad_id`, `fecha_hotelera`, `estado`),
    INDEX `idx_dev_unidad_fecha` (`unidad_id`, `fecha_hotelera`),
    INDEX `idx_dev_cierre` (`cierre_hotelero_id`),
    INDEX `idx_dev_cargo` (`cargo_cuenta_id`),
    INDEX `idx_dev_reserva` (`reserva_id`),
    INDEX `idx_dev_reverso` (`reverso_de_id`),
    CONSTRAINT `fk_dev_cierre` FOREIGN KEY (`cierre_hotelero_id`) REFERENCES `cierres_hoteleros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_reserva_uni` FOREIGN KEY (`reserva_unidad_id`) REFERENCES `reserva_unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_cargo` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_reverso` FOREIGN KEY (`reverso_de_id`) REFERENCES `devengos_alojamiento` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_actor_dev` FOREIGN KEY (`devengado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_dev_actor_rev` FOREIGN KEY (`revertido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_dev_neto` CHECK (`importe_neto` >= 0.00),
    CONSTRAINT `chk_dev_total` CHECK (`importe_total` >= 0.00),
    CONSTRAINT `chk_dev_noche_idx` CHECK (`noche_indice` >= 1),
    CONSTRAINT `chk_dev_sec` CHECK (`secuencia` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro diario de reconocimiento económico de alojamiento por noche';

-- ----------------------------------------------------------------------------
-- 3. Catálogo de Permisos RBAC de Devengo y Night Audit (D-090)
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('devengo.ver', 'Ver devengos de alojamiento', 'Permite consultar el libro diario de devengos y detalle por estancia', 'operaciones', 'ACTIVO', 1),
('devengo.ejecutar', 'Devengar alojamiento manualmente', 'Permite ejecutar el devengo manual o por checkout anticipado', 'operaciones', 'ACTIVO', 1),
('devengo.revertir', 'Revertir devengos de alojamiento', 'Permite reversión supervisada con motivo justificado (REVERTIR != DELETE)', 'operaciones', 'ACTIVO', 1),
('night_audit.ver', 'Ver auditoría nocturna', 'Permite consultar el historial de cierres hoteleros y KPIs de ocupación', 'operaciones', 'ACTIVO', 1),
('night_audit.ejecutar', 'Ejecutar auditoría nocturna', 'Permite procesar el cierre diario hotelero y congelar métricas ADR/RevPAR', 'operaciones', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 4. Asignación de Permisos al Rol SUPERADMINISTRADOR
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND (p.`codigo` LIKE 'devengo.%' OR p.`codigo` LIKE 'night_audit.%');

-- ----------------------------------------------------------------------------
-- 5. Opción de Menú Alina: Auditoría Nocturna (Bajo Reservas/Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'operaciones_night_audit', 'Auditoría Nocturna', 'fa-solid fa-moon', '/operaciones/night-audit', 16, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'night_audit.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
