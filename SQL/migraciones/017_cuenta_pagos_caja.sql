-- ============================================================================
-- Camargo PMS — Migración 017: Cuentas, Cargos, Pagos, Aplicaciones y Caja
-- Fase: FINANCIERO-2 / FINANCIERO-2A
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Normalizado de Métodos de Pago
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `metodos_pago` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'EFECTIVO, TARJETA_CREDITO, TARJETA_DEBITO, TRANSFERENCIA, BILLETERA_DIGITAL',
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_destino` ENUM('CAJA_FISICA', 'CUENTA_BANCARIA', 'PASARELA_INTERMEDIARIO') NOT NULL DEFAULT 'CAJA_FISICA',
    `requiere_referencia` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si exige nro de voucher u operación',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_metp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_metp_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_metodos_pago_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de medios de pago soportados';

-- ----------------------------------------------------------------------------
-- 2. Cuentas Bancarias y Financieras de la Empresa
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_bancarias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador interno (ej. BCP_CORRIENTE_PEN)',
    `banco_nombre` VARCHAR(100) NOT NULL COMMENT 'Entidad bancaria o financiera',
    `tipo_cuenta` ENUM('CORRIENTE', 'AHORROS', 'RECAUDADORA') NOT NULL DEFAULT 'CORRIENTE',
    `numero_cuenta` VARCHAR(50) NOT NULL,
    `numero_cci` VARCHAR(50) NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `titular` VARCHAR(150) NOT NULL COMMENT 'Razón social titular de la cuenta',
    `saldo_contable` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo contable referencial',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ctab_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ctab_numero_no_vacio` CHECK (`numero_cuenta` <> ''),
    UNIQUE KEY `uq_cuentas_bancarias_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cuentas bancarias de Camargo Hostelería para recaudación y transferencias';

-- ----------------------------------------------------------------------------
-- 3. Cajas Físicas de Custodia en Predios
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cajas_fisicas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador único (ej. CAJA_RECEPCION_1)',
    `nombre` VARCHAR(100) NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Predio o propiedad física a la que pertenece la gaveta',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cajf_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_cajf_codigo_no_vacio` CHECK (`codigo` <> ''),
    UNIQUE KEY `uq_cajas_fisicas_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Puntos de recaudación y custodia de efectivo físico';

-- ----------------------------------------------------------------------------
-- 4. Sesiones de Turno de Caja (Arqueo y Custodia de Efectivo)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `caja_fisica_id` INT UNSIGNED NOT NULL,
    `actor_apertura_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor cajero humano que inicia el turno (D-061)',
    `actor_cierre_id` BIGINT UNSIGNED NULL COMMENT 'Actor cajero o supervisor que sella el turno (D-061)',
    `monto_apertura` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Fondo de cambio inicial en efectivo',
    `total_ingresos_efectivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma acumulada de cobros en efectivo',
    `total_egresos_efectivo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma acumulada de devoluciones o salidas',
    `monto_esperado` DECIMAL(15,2) NULL COMMENT 'monto_apertura + ingresos - egresos',
    `monto_contado_declarado` DECIMAL(15,2) NULL COMMENT 'Conteo físico final de billetes y monedas',
    `diferencia` DECIMAL(15,2) NULL COMMENT 'declarado - esperado',
    `resultado_arqueo` ENUM('CUADRADA', 'SOBRANTE', 'FALTANTE') NULL,
    `estado` ENUM('ABIERTA', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    `abierta_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cerrada_en` DATETIME NULL,
    `observaciones_apertura` TEXT NULL,
    `observaciones_cierre` TEXT NULL COMMENT 'Obligatorio cuando diferencia != 0',
    CONSTRAINT `fk_sesc_caja_fisica` FOREIGN KEY (`caja_fisica_id`) REFERENCES `cajas_fisicas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sesc_actor_apertura` FOREIGN KEY (`actor_apertura_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sesc_actor_cierre` FOREIGN KEY (`actor_cierre_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_sesc_monto_apertura_no_negativo` CHECK (`monto_apertura` >= 0),
    CONSTRAINT `chk_sesc_totales_no_negativos` CHECK (`total_ingresos_efectivo` >= 0 AND `total_egresos_efectivo` >= 0),
    INDEX `idx_sesc_caja_estado` (`caja_fisica_id`, `estado`),
    INDEX `idx_sesc_actor_apertura` (`actor_apertura_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Turnos de trabajo y arqueos de gaveta de efectivo';

-- ----------------------------------------------------------------------------
-- 5. Folios Financieros / Cuentas de Reserva
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_folios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato FOL-YYYYMMDD-XXXX',
    `reserva_id` BIGINT UNSIGNED NOT NULL COMMENT '1:1 estricto con la reserva raíz comercial',
    `persona_titular_id` BIGINT UNSIGNED NOT NULL COMMENT 'Titular principal de la cuenta',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ABIERTA', 'CONGELADA', 'CERRADA', 'ANULADA') NOT NULL DEFAULT 'ABIERTA',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ctaf_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ctaf_persona_titular` FOREIGN KEY (`persona_titular_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ctaf_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_ctaf_codigo_no_vacio` CHECK (`codigo` <> ''),
    UNIQUE KEY `uq_cuentas_folios_codigo` (`codigo`),
    UNIQUE KEY `uq_cuentas_folios_reserva` (`reserva_id`),
    INDEX `idx_ctaf_estado` (`estado`),
    INDEX `idx_ctaf_titular` (`persona_titular_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Contenedor financiero consolidado de la reserva comercial';

-- ----------------------------------------------------------------------------
-- 6. Cargos a la Cuenta (Devengo de Alojamiento, Servicios o Penalidades)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cargos_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CRG-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('ALOJAMIENTO_NOCHES', 'SERVICIO_CONTRATADO', 'PENALIDAD', 'AJUSTE_MANUAL') NOT NULL,
    `origen_id` BIGINT UNSIGNED NULL COMMENT 'ID de reserva_unidades o servicios_contratados',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Habitación física que consumió (NULL si es preventa o general)',
    `concepto` VARCHAR(255) NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_aplicado_acumulado` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de amortizaciones activas',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('PROVISIONAL', 'DEVENGADO', 'ANULADO') NOT NULL DEFAULT 'DEVENGADO' COMMENT 'PROVISIONAL para servicios confirmados pero no ejecutados',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `devengado_en` DATETIME NULL COMMENT 'Instante UTC en que pasó a DEVENGADO irrevocable',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_crgc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_crgc_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_crgc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_crgc_total_positivo` CHECK (`total` >= 0),
    CONSTRAINT `chk_crgc_aplicado_rango` CHECK (`monto_aplicado_acumulado` >= 0 AND `monto_aplicado_acumulado` <= `total`),
    UNIQUE KEY `uq_cargos_cuenta_codigo` (`codigo`),
    INDEX `idx_crgc_folio_estado` (`cuenta_folio_id`, `estado`),
    INDEX `idx_crgc_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_crgc_estadia` (`estadia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Obligaciones y consumos devengados o provisionales en el folio';

-- ----------------------------------------------------------------------------
-- 7. Pagos y Cobros a la Cuenta
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato PAG-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL COMMENT 'Monto total recaudado en este pago',
    `monto_aplicado` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de aplicaciones a cargos específicos',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si método es EFECTIVO',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si método es TRANSFERENCIA',
    `referencia_operacion` VARCHAR(100) NULL COMMENT 'Nro voucher POS, nro operación bancaria',
    `estado` ENUM('CONFIRMADO', 'REVERSADO') NOT NULL DEFAULT 'CONFIRMADO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pagc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagc_actor_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_pagc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_pagc_monto_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_pagc_aplicado_rango` CHECK (`monto_aplicado` >= 0 AND `monto_aplicado` <= `monto_total`),
    UNIQUE KEY `uq_pagos_cuenta_codigo` (`codigo`),
    INDEX `idx_pagc_folio_estado` (`cuenta_folio_id`, `estado`),
    INDEX `idx_pagc_metodo` (`metodo_pago_id`),
    INDEX `idx_pagc_sesion` (`sesion_caja_id`),
    INDEX `idx_pagc_cuenta_bancaria` (`cuenta_bancaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dinero recibido o reconocido para la cuenta';

-- ----------------------------------------------------------------------------
-- 8. Aplicaciones de Pago (Imputación Detallada Pago ↔ Cargo)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `aplicaciones_pago` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato APL-YYYYMMDD-XXXX',
    `pago_id` BIGINT UNSIGNED NOT NULL,
    `cargo_id` BIGINT UNSIGNED NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVA', 'REVERTIDA') NOT NULL DEFAULT 'ACTIVA',
    `revertida_en` DATETIME NULL,
    `revertida_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_aplp_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aplp_actor_reversor` FOREIGN KEY (`revertida_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_aplp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_aplp_monto_positivo` CHECK (`monto_aplicado` > 0),
    UNIQUE KEY `uq_aplicaciones_pago_codigo` (`codigo`),
    INDEX `idx_aplp_pago_estado` (`pago_id`, `estado`),
    INDEX `idx_aplp_cargo_estado` (`cargo_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Imputación formal entre fondos recaudados y obligaciones devengadas';

-- ----------------------------------------------------------------------------
-- 9. Devoluciones de Fondos (Reembolsos al Huésped)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones_cuenta` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato DEV-YYYYMMDD-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `pago_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Pago original que se reembolsa',
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si se reembolsa en EFECTIVO',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si se transfiere a cliente',
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `motivo` VARCHAR(255) NOT NULL,
    `estado` ENUM('CONFIRMADA', 'ANULADA') NOT NULL DEFAULT 'CONFIRMADA',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_devc_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_pago_origen` FOREIGN KEY (`pago_origen_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_devc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_devc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_devc_monto_positivo` CHECK (`monto` > 0),
    UNIQUE KEY `uq_devoluciones_cuenta_codigo` (`codigo`),
    INDEX `idx_devc_folio` (`cuenta_folio_id`),
    INDEX `idx_devc_pago` (`pago_origen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reembolsos reales entregados o transferidos al huésped';

-- ----------------------------------------------------------------------------
-- 10. Libro Mayor de Movimientos de Caja Física (Efectivo)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_caja` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sesion_caja_id` BIGINT UNSIGNED NOT NULL,
    `tipo_movimiento` ENUM('INGRESO_COBRO', 'INGRESO_AJUSTE', 'EGRESO_DEVOLUCION', 'EGRESO_GASTO_MENOR', 'EGRESO_REMESA') NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL COMMENT 'Vinculado a pagos_cuenta si fue cobro a huésped',
    `devolucion_id` BIGINT UNSIGNED NULL COMMENT 'Vinculado a devoluciones_cuenta si fue reembolso',
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `concepto` VARCHAR(255) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor responsable del movimiento (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movc_sesion_caja` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_devolucion` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movc_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_movc_monto_positivo` CHECK (`monto` > 0),
    INDEX `idx_movc_sesion` (`sesion_caja_id`),
    INDEX `idx_movc_pago` (`pago_id`),
    INDEX `idx_movc_devolucion` (`devolucion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro mayor de entradas y salidas de billetes y monedas en gaveta';

-- ----------------------------------------------------------------------------
-- 11. Libro Mayor de Movimientos Bancarios (Cuentas Financieras)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_bancarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cuenta_bancaria_id` INT UNSIGNED NOT NULL,
    `tipo_movimiento` ENUM('INGRESO_TRANSFERENCIA', 'INGRESO_LIQUIDACION_POS', 'EGRESO_DEVOLUCION', 'EGRESO_TRANSFERENCIA', 'COMISION_BANCARIA') NOT NULL,
    `pago_id` BIGINT UNSIGNED NULL,
    `devolucion_id` BIGINT UNSIGNED NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `numero_operacion` VARCHAR(100) NOT NULL COMMENT 'Código de operación en extracto bancario',
    `concepto` VARCHAR(255) NOT NULL,
    `fecha_operacion` DATE NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor que concilia o asienta el movimiento (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movb_cuenta_bancaria` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_devolucion` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones_cuenta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movb_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_movb_monto_positivo` CHECK (`monto` > 0),
    INDEX `idx_movb_cuenta` (`cuenta_bancaria_id`),
    INDEX `idx_movb_operacion` (`numero_operacion`),
    INDEX `idx_movb_pago` (`pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro de movimientos en cuentas bancarias para posterior conciliación';

-- ----------------------------------------------------------------------------
-- 12. Semillas Estructurales Iniciales
-- ----------------------------------------------------------------------------

-- Métodos de Pago
INSERT INTO `metodos_pago` (`codigo`, `nombre`, `tipo_destino`, `requiere_referencia`, `activo`) VALUES
('EFECTIVO', 'Efectivo en Recepción', 'CAJA_FISICA', 0, 1),
('TARJETA_CREDITO', 'Tarjeta de Crédito (POS / Pasarela)', 'PASARELA_INTERMEDIARIO', 1, 1),
('TARJETA_DEBITO', 'Tarjeta de Débito (POS / Pasarela)', 'PASARELA_INTERMEDIARIO', 1, 1),
('TRANSFERENCIA', 'Transferencia Bancaria Directa', 'CUENTA_BANCARIA', 1, 1),
('BILLETERA_DIGITAL', 'Billetera Digital (Yape / Plin)', 'PASARELA_INTERMEDIARIO', 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `tipo_destino` = VALUES(`tipo_destino`), `requiere_referencia` = VALUES(`requiere_referencia`);

-- Cuenta Bancaria Inicial
INSERT INTO `cuentas_bancarias` (`codigo`, `banco_nombre`, `tipo_cuenta`, `numero_cuenta`, `numero_cci`, `moneda_codigo`, `titular`, `saldo_contable`, `estado`) VALUES
('BCP_CORRIENTE_PEN', 'Banco de Crédito del Perú (BCP)', 'CORRIENTE', '191-99887766-0-12', '002-191-0099887766012-55', 'PEN', 'Camargo Hostelería S.A.C.', 0.00, 'ACTIVO')
ON DUPLICATE KEY UPDATE `titular` = VALUES(`titular`);

-- Caja Física Inicial
INSERT INTO `cajas_fisicas` (`codigo`, `nombre`, `propiedad_id`, `moneda_codigo`, `estado`)
SELECT 'CAJA_RECEPCION_1', 'Caja Recepción Principal', id, 'PEN', 'ACTIVO'
FROM `propiedades`
ORDER BY id ASC
LIMIT 1
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ----------------------------------------------------------------------------
-- 13. Permisos RBAC Iniciales
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('caja.ver', 'Ver módulos de caja, folios y finanzas', 'Consultar folios, cargos, pagos y sesiones de caja', 'caja', 'ACTIVO', 1),
('caja.aperturar', 'Aperturar sesiones de caja', 'Iniciar turnos de caja física con fondo inicial', 'caja', 'ACTIVO', 1),
('caja.cerrar', 'Arqueo y cierre de sesiones de caja', 'Conteo de efectivo y cierre irreversible de turnos', 'caja', 'ACTIVO', 1),
('caja.movimientos', 'Registrar movimientos de caja', 'Registrar ingresos y egresos de efectivo manuales', 'caja', 'ACTIVO', 1),
('caja.cobrar', 'Registrar cobros y pagos a cuentas', 'Recibir fondos en efectivo, tarjeta o banco', 'caja', 'ACTIVO', 1),
('caja.aplicar', 'Imputar pagos a cargos específicos', 'Vincular cobros a cargos de alojamiento o servicios', 'caja', 'ACTIVO', 1),
('caja.devolver', 'Registrar devoluciones y reembolsos', 'Emitir devoluciones reales de fondos al cliente', 'caja', 'ACTIVO', 1),
('caja.reversar', 'Reversar pagos y anular cargos', 'Operaciones compensatorias y correcciones de auditoría', 'caja', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación a SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo IN (
      'caja.ver',
      'caja.aperturar',
      'caja.cerrar',
      'caja.movimientos',
      'caja.cobrar',
      'caja.aplicar',
      'caja.devolver',
      'caja.reversar'
  )
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

-- Nivel 2: Opción Secundaria 'Caja y Cuentas' bajo 'reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'caja_cuentas', 'Caja y Cuentas', 'fa-solid fa-cash-register', '/caja', 4, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'caja.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
