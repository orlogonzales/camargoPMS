-- ============================================================================
-- CAMARGO PMS — Migración 022: Módulo de Compras, Abastecimiento y Cuentas por Pagar
-- Gobernanza: D-080 (GATE COMPRAS-1)
-- Axioma: SOLICITUD ≠ ORDEN DE COMPRA ≠ RECEPCIÓN/CONFORMIDAD ≠ COMPROBANTE ≠ CxP ≠ PAGO
-- Tablas:
--   1. compra_solicitudes
--   2. compra_solicitud_lineas
--   3. compra_ordenes
--   4. compra_orden_lineas
--   5. compra_recepciones
--   6. compra_recepcion_lineas
--   7. compra_conformidades
--   8. compra_comprobantes
--   9. compra_comprobante_aplicaciones
--   10. cuentas_por_pagar
--   11. cxp_pagos
--   12. compra_historial_estados
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Solicitudes de Compra / Requerimientos Internos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_solicitudes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato SOL-YYYYMM-XXXX',
    `departamento_area` VARCHAR(80) NOT NULL COMMENT 'Pisos, Recepción, Mantenimiento, etc.',
    `almacen_destino_id` BIGINT UNSIGNED NULL COMMENT 'Almacén físico sugerido',
    `unidad_destino_id` BIGINT UNSIGNED NULL COMMENT 'Habitación/unidad específica si aplica',
    `fecha_limite_requerida` DATE NOT NULL,
    `justificacion` TEXT NOT NULL,
    `estado` ENUM('BORRADOR', 'PENDIENTE_APROBACION', 'APROBADA', 'RECHAZADA', 'ATENDIDA', 'ANULADA') NOT NULL DEFAULT 'BORRADOR',
    `motivo_rechazo` TEXT NULL,
    `solicitado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_csol_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_csol_area_no_vacia` CHECK (`departamento_area` <> ''),
    CONSTRAINT `chk_csol_justificacion_no_vacia` CHECK (`justificacion` <> ''),
    CONSTRAINT `fk_csol_almacen` FOREIGN KEY (`almacen_destino_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_unidad` FOREIGN KEY (`unidad_destino_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_solicitante` FOREIGN KEY (`solicitado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_csol_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_csol_codigo` (`codigo`),
    INDEX `idx_csol_estado` (`estado`),
    INDEX `idx_csol_area` (`departamento_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Requerimientos internos de compras de bienes o servicios';

-- ----------------------------------------------------------------------------
-- 2. Líneas de Solicitud de Compra
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_solicitud_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `solicitud_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('BIEN', 'SERVICIO') NOT NULL DEFAULT 'BIEN',
    `articulo_id` BIGINT UNSIGNED NULL,
    `descripcion_servicio` VARCHAR(255) NULL,
    `cantidad_solicitada` DECIMAL(15,4) NOT NULL,
    `especificaciones_tecnicas` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cslin_tipo_consistente` CHECK (
        (`tipo_linea` = 'BIEN' AND `articulo_id` IS NOT NULL) OR
        (`tipo_linea` = 'SERVICIO' AND `descripcion_servicio` IS NOT NULL AND `descripcion_servicio` <> '')
    ),
    CONSTRAINT `chk_cslin_cant_positiva` CHECK (`cantidad_solicitada` > 0),
    CONSTRAINT `fk_cslin_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `compra_solicitudes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cslin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_cslin_solicitud` (`solicitud_id`),
    INDEX `idx_cslin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de bienes o servicios solicitados';

-- ----------------------------------------------------------------------------
-- 3. Órdenes de Compra
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_ordenes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato OC-YYYYMM-XXXX',
    `solicitud_id` BIGINT UNSIGNED NULL COMMENT 'Opcional, NULL para órdenes directas',
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `condicion_pago` ENUM('CONTADO', 'CREDITO_15D', 'CREDITO_30D', 'ADELANTADO') NOT NULL DEFAULT 'CONTADO',
    `almacen_entrega_id` BIGINT UNSIGNED NULL COMMENT 'Almacén físico receptor para bienes',
    `fecha_entrega_esperada` DATE NOT NULL,
    `notas_comerciales` TEXT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `descuento_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estado_comercial` ENUM('BORRADOR', 'APROBADA', 'CERRADA', 'CANCELADA') NOT NULL DEFAULT 'BORRADOR',
    `estado_recepcion` ENUM('SIN_RECEPCION', 'RECEPCION_PARCIAL', 'RECEPCION_TOTAL') NOT NULL DEFAULT 'SIN_RECEPCION',
    `estado_facturacion` ENUM('SIN_FACTURAR', 'FACTURADA_PARCIAL', 'FACTURADA_TOTAL') NOT NULL DEFAULT 'SIN_FACTURAR',
    `estado_pago` ENUM('PENDIENTE', 'PAGADO_PARCIAL', 'PAGADO_TOTAL') NOT NULL DEFAULT 'PENDIENTE',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NULL,
    `motivo_cancelacion` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cord_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cord_subtotal_no_negativo` CHECK (`subtotal` >= 0),
    CONSTRAINT `chk_cord_impuesto_no_negativo` CHECK (`impuesto_total` >= 0),
    CONSTRAINT `chk_cord_descuento_no_negativo` CHECK (`descuento_total` >= 0),
    CONSTRAINT `chk_cord_total_no_negativo` CHECK (`total` >= 0),
    CONSTRAINT `fk_cord_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `compra_solicitudes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cord_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_almacen` FOREIGN KEY (`almacen_entrega_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cord_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cord_codigo` (`codigo`),
    INDEX `idx_cord_proveedor` (`proveedor_id`),
    INDEX `idx_cord_estado_comercial` (`estado_comercial`),
    INDEX `idx_cord_estado_recepcion` (`estado_recepcion`),
    INDEX `idx_cord_estado_facturacion` (`estado_facturacion`),
    INDEX `idx_cord_estado_pago` (`estado_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Compromisos contractuales y comerciales de compra formal';

-- ----------------------------------------------------------------------------
-- 4. Líneas de Orden de Compra
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_orden_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `tipo_linea` ENUM('BIEN', 'SERVICIO') NOT NULL DEFAULT 'BIEN',
    `articulo_id` BIGINT UNSIGNED NULL,
    `descripcion_servicio` VARCHAR(255) NULL,
    `cantidad_pactada` DECIMAL(15,4) NOT NULL,
    `precio_unitario` DECIMAL(15,4) NOT NULL,
    `subtotal_linea` DECIMAL(15,2) NOT NULL,
    `impuesto_linea` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_linea` DECIMAL(15,2) NOT NULL,
    `cantidad_aceptada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Acumulado aceptado en recepciones/conformidades',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_colin_tipo_consistente` CHECK (
        (`tipo_linea` = 'BIEN' AND `articulo_id` IS NOT NULL) OR
        (`tipo_linea` = 'SERVICIO' AND `descripcion_servicio` IS NOT NULL AND `descripcion_servicio` <> '')
    ),
    CONSTRAINT `chk_colin_cant_positiva` CHECK (`cantidad_pactada` > 0),
    CONSTRAINT `chk_colin_precio_no_negativo` CHECK (`precio_unitario` >= 0),
    CONSTRAINT `chk_colin_subtotal_no_negativo` CHECK (`subtotal_linea` >= 0),
    CONSTRAINT `chk_colin_total_no_negativo` CHECK (`total_linea` >= 0),
    CONSTRAINT `chk_colin_aceptada_no_negativa` CHECK (`cantidad_aceptada` >= 0),
    CONSTRAINT `fk_colin_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_colin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_colin_orden` (`orden_compra_id`),
    INDEX `idx_colin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Líneas tipadas de la orden de compra con cantidades y precios';

-- ----------------------------------------------------------------------------
-- 5. Recepciones Físicas en Almacén (Bienes)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_recepciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato REC-YYYYMM-XXXX',
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `almacen_id` BIGINT UNSIGNED NOT NULL,
    `numero_guia_remision` VARCHAR(50) NULL,
    `fecha_recepcion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `observaciones` TEXT NULL,
    `recibido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_crec_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_crec_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crec_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crec_receptor` FOREIGN KEY (`recibido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_crec_codigo` (`codigo`),
    INDEX `idx_crec_orden` (`orden_compra_id`),
    INDEX `idx_crec_almacen` (`almacen_id`),
    INDEX `idx_crec_fecha` (`fecha_recepcion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ingresos físicos de bienes a almacén contrastados con la orden';

-- ----------------------------------------------------------------------------
-- 6. Líneas de Recepción Física
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_recepcion_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recepcion_id` BIGINT UNSIGNED NOT NULL,
    `orden_linea_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_recibida` DECIMAL(15,4) NOT NULL,
    `cantidad_aceptada` DECIMAL(15,4) NOT NULL,
    `cantidad_rechazada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `motivo_rechazo` VARCHAR(255) NULL,
    `movimiento_inventario_id` BIGINT UNSIGNED NULL COMMENT 'FK a Kardex ENTRADA_COMPRA bajo D-078',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_crlin_cant_consistente` CHECK (`cantidad_recibida` = `cantidad_aceptada` + `cantidad_rechazada`),
    CONSTRAINT `chk_crlin_recibida_positiva` CHECK (`cantidad_recibida` > 0),
    CONSTRAINT `chk_crlin_aceptada_no_neg` CHECK (`cantidad_aceptada` >= 0),
    CONSTRAINT `chk_crlin_rechazada_no_neg` CHECK (`cantidad_rechazada` >= 0),
    CONSTRAINT `fk_crlin_recepcion` FOREIGN KEY (`recepcion_id`) REFERENCES `compra_recepciones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_crlin_orden_linea` FOREIGN KEY (`orden_linea_id`) REFERENCES `compra_orden_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crlin_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_crlin_mov_inv` FOREIGN KEY (`movimiento_inventario_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_crlin_recepcion` (`recepcion_id`),
    INDEX `idx_crlin_orden_linea` (`orden_linea_id`),
    INDEX `idx_crlin_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle físico de bienes aceptados hacia Kardex y rechazados';

-- ----------------------------------------------------------------------------
-- 7. Actas de Conformidad de Servicio
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_conformidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CONF-YYYYMM-XXXX',
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `orden_linea_id` BIGINT UNSIGNED NOT NULL,
    `fecha_conformidad` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `informe_trabajo_realizado` TEXT NOT NULL,
    `aprobado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cconf_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cconf_informe_no_vacio` CHECK (`informe_trabajo_realizado` <> ''),
    CONSTRAINT `fk_cconf_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cconf_orden_linea` FOREIGN KEY (`orden_linea_id`) REFERENCES `compra_orden_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cconf_aprobador` FOREIGN KEY (`aprobado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cconf_codigo` (`codigo`),
    INDEX `idx_cconf_orden` (`orden_compra_id`),
    INDEX `idx_cconf_orden_linea` (`orden_linea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Actas de conformidad técnica para líneas de servicios (cero Kardex)';

-- ----------------------------------------------------------------------------
-- 8. Comprobantes Fiscales del Proveedor
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_comprobantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'NOTA_CREDITO', 'NOTA_DEBITO') NOT NULL,
    `serie` VARCHAR(10) NOT NULL,
    `numero` VARCHAR(20) NOT NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `estado_matching` ENUM('CONFORME', 'CON_DIFERENCIA', 'OBSERVADO') NOT NULL DEFAULT 'CONFORME',
    `observaciones_matching` TEXT NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ccomp_serie_no_vacia` CHECK (`serie` <> ''),
    CONSTRAINT `chk_ccomp_numero_no_vacio` CHECK (`numero` <> ''),
    CONSTRAINT `chk_ccomp_total_positivo` CHECK (`total` > 0),
    CONSTRAINT `fk_ccomp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccomp_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccomp_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_ccomp_fiscal` (`proveedor_id`, `tipo_comprobante`, `serie`, `numero`),
    INDEX `idx_ccomp_orden` (`orden_compra_id`),
    INDEX `idx_ccomp_matching` (`estado_matching`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Comprobantes tributarios de compras recibidos de proveedores';

-- ----------------------------------------------------------------------------
-- 9. Aplicaciones y Matching M:N Comprobante vs Recepciones/Conformidades
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_comprobante_aplicaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `comprobante_id` BIGINT UNSIGNED NOT NULL,
    `recepcion_linea_id` BIGINT UNSIGNED NULL,
    `conformidad_id` BIGINT UNSIGNED NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ccapp_origen_exclusivo` CHECK (
        (`recepcion_linea_id` IS NOT NULL AND `conformidad_id` IS NULL) OR
        (`recepcion_linea_id` IS NULL AND `conformidad_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_ccapp_monto_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `fk_ccapp_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `compra_comprobantes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ccapp_recepcion_linea` FOREIGN KEY (`recepcion_linea_id`) REFERENCES `compra_recepcion_lineas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ccapp_conformidad` FOREIGN KEY (`conformidad_id`) REFERENCES `compra_conformidades` (`id`) ON DELETE RESTRICT,
    INDEX `idx_ccapp_comprobante` (`comprobante_id`),
    INDEX `idx_ccapp_rec_linea` (`recepcion_linea_id`),
    INDEX `idx_ccapp_conformidad` (`conformidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Vínculo formal 3-way matching entre comprobante y recepciones/conformidades';

-- ----------------------------------------------------------------------------
-- 10. Cuentas por Pagar (Obligaciones Financieras Devengadas)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_por_pagar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato CXP-YYYYMM-XXXX',
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `comprobante_id` BIGINT UNSIGNED NOT NULL,
    `orden_compra_id` BIGINT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL,
    `monto_amortizado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_pendiente` DECIMAL(15,2) NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `estado` ENUM('PENDIENTE', 'AMORTIZADA_PARCIAL', 'LIQUIDADA', 'ANULADA') NOT NULL DEFAULT 'PENDIENTE',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cxp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_cxp_total_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_cxp_amort_no_negativa` CHECK (`monto_amortizado` >= 0),
    CONSTRAINT `chk_cxp_saldo_no_negativo` CHECK (`saldo_pendiente` >= 0),
    CONSTRAINT `chk_cxp_coherencia_saldos` CHECK (`saldo_pendiente` = `monto_total` - `monto_amortizado`),
    CONSTRAINT `fk_cxp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxp_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `compra_comprobantes` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxp_orden` FOREIGN KEY (`orden_compra_id`) REFERENCES `compra_ordenes` (`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_cxp_codigo` (`codigo`),
    UNIQUE KEY `uq_cxp_comprobante` (`comprobante_id`),
    INDEX `idx_cxp_proveedor` (`proveedor_id`),
    INDEX `idx_cxp_orden` (`orden_compra_id`),
    INDEX `idx_cxp_estado` (`estado`),
    INDEX `idx_cxp_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Pasivos formales devengados con proveedores';

-- ----------------------------------------------------------------------------
-- 11. Pagos y Amortizaciones de Cuentas por Pagar
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cxp_pagos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cuenta_pagar_id` BIGINT UNSIGNED NOT NULL,
    `medio_pago` ENUM('EFECTIVO_CAJA', 'TRANSFERENCIA_BANCARIA', 'CHEQUE', 'BILLETERA_DIGITAL') NOT NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `numero_operacion_bancaria` VARCHAR(100) NULL,
    `movimiento_caja_id` BIGINT UNSIGNED NULL COMMENT 'FK a movimientos_caja si se pagó desde caja chica',
    `movimiento_bancario_id` BIGINT UNSIGNED NULL COMMENT 'FK a movimientos_bancarios si fue transferencia/banco',
    `notas` TEXT NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cxpp_monto_positivo` CHECK (`monto` > 0),
    CONSTRAINT `fk_cxpp_cxp` FOREIGN KEY (`cuenta_pagar_id`) REFERENCES `cuentas_por_pagar` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_mov_banco` FOREIGN KEY (`movimiento_bancario_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cxpp_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_cxpp_cxp` (`cuenta_pagar_id`),
    INDEX `idx_cxpp_fecha` (`fecha_pago`),
    INDEX `idx_cxpp_mov_caja` (`movimiento_caja_id`),
    INDEX `idx_cxpp_mov_banco` (`movimiento_bancario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Egresos financieros aplicados a cuentas por pagar';

-- ----------------------------------------------------------------------------
-- 12. Historial Inmutable de Estados y Auditoría D-061
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entidad_tipo` ENUM('SOLICITUD', 'ORDEN_COMPRA', 'CUENTA_POR_PAGAR') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(50) NULL,
    `estado_nuevo` VARCHAR(50) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `motivo` TEXT NULL,
    `correlacion_id` VARCHAR(64) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_chist_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_chist_entidad` (`entidad_tipo`, `entidad_id`),
    INDEX `idx_chist_actor` (`actor_id`),
    INDEX `idx_chist_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad inmutable de transiciones y auditoría D-061 para compras';

-- ----------------------------------------------------------------------------
-- 13. RBAC y Menú Dinámico
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('compras.ver', 'Ver módulo de compras', 'COMPRAS', 'Acceso a la vista y listados de abastecimiento y compras', NOW()),
('compras.solicitudes.crear', 'Crear solicitudes de compra', 'COMPRAS', 'Registrar requerimientos internos de compra', NOW()),
('compras.solicitudes.aprobar', 'Aprobar solicitudes de compra', 'COMPRAS', 'Aprobar o rechazar requerimientos internos', NOW()),
('compras.ordenes.crear', 'Crear órdenes de compra', 'COMPRAS', 'Formular órdenes de compra directas o desde solicitud', NOW()),
('compras.ordenes.aprobar', 'Aprobar órdenes de compra', 'COMPRAS', 'Aprobar formalmente y congelar condiciones de la OC', NOW()),
('compras.recepciones.registrar', 'Registrar recepciones físicas', 'COMPRAS', 'Recibir bienes en almacén y afectar Kardex', NOW()),
('compras.conformidad.registrar', 'Registrar conformidad de servicios', 'COMPRAS', 'Emitir actas de conformidad técnica para servicios', NOW()),
('compras.comprobantes.registrar', 'Registrar comprobantes de proveedor', 'COMPRAS', 'Registrar facturas/boletas y ejecutar 3-way matching', NOW()),
('compras.cuentas_pagar.ver', 'Ver cuentas por pagar', 'COMPRAS', 'Consultar obligaciones financieras con proveedores', NOW()),
('compras.pagos.registrar', 'Registrar pagos a proveedores', 'COMPRAS', 'Amortizar cuentas por pagar con egresos de caja o banco', NOW()),
('compras.anular', 'Anular operaciones de compra', 'COMPRAS', 'Cancelar órdenes o anular comprobantes justificados', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Asignar automáticamente permisos de compras a SUPERADMINISTRADOR (1) y ADMINISTRADOR (2)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'compras.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'compras.%';

-- Inserción de opción de menú bajo categoría 'reservas' (Operaciones)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'compras', 'Compras', 'fa-solid fa-cart-shopping', '/compras', 9, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'compras.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Ampliar ENUM de origen_tipo_permitido y documentos_emitidos para soportar COMPRA
ALTER TABLE `documento_plantillas`
MODIFY COLUMN `origen_tipo_permitido` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA') NOT NULL;

ALTER TABLE `documentos_emitidos`
MODIFY COLUMN `origen_tipo` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA') NOT NULL;

-- Registro de plantilla canónica de Orden de Compra para DOCUMENTOS-1
INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`, `orientacion`,
    `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`, `estado`
) VALUES (
    'ORDEN_COMPRA',
    'Orden de Compra Oficial A4',
    'Documento comercial institucional emitido a proveedores con detalle de bienes/servicios pactados',
    'COMPRA',
    'PORTRAIT',
    'A4',
    1,
    'storage/membretes/membrete_a4_canonica_v1.png',
    35, 28, 20, 20,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Versión 1 activa para la plantilla ORDEN_COMPRA
INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`,
    `cuerpo_html`, `estilos_css`, `notas_version`, `es_activa`, `creado_por_actor_id`
)
SELECT
    p.`id`,
    1,
    'ORDEN DE COMPRA OFICIAL',
    '<div class=\"documento-orden-compra\">
    <div class=\"encabezado-orden\">
        <h1 class=\"titulo-principal\">ORDEN DE COMPRA</h1>
        <p class=\"subtitulo-folio\">FOLIO N°: <strong>{{documento.folio}}</strong> | CÓDIGO OC: <strong>{{orden.codigo}}</strong></p>
    </div>
    <div class=\"seccion-datos\">
        <table class=\"tabla-info\">
            <tr>
                <td class=\"campo-etiqueta\"><strong>Proveedor:</strong></td>
                <td class=\"campo-valor\">{{proveedor.razon_social}} (RUC/DOC: {{proveedor.numero_documento}})</td>
                <td class=\"campo-etiqueta\"><strong>Fecha Emisión:</strong></td>
                <td class=\"campo-valor\">{{orden.fecha}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Contacto:</strong></td>
                <td class=\"campo-valor\">{{proveedor.contacto}} - {{proveedor.telefono}}</td>
                <td class=\"campo-etiqueta\"><strong>Fecha Entrega:</strong></td>
                <td class=\"campo-valor\">{{orden.fecha_entrega}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Dirección:</strong></td>
                <td class=\"campo-valor\">{{proveedor.direccion}}</td>
                <td class=\"campo-etiqueta\"><strong>Condición Pago:</strong></td>
                <td class=\"campo-valor\">{{orden.condicion_pago}}</td>
            </tr>
            <tr>
                <td class=\"campo-etiqueta\"><strong>Almacén Entrega:</strong></td>
                <td class=\"campo-valor\" colspan=\"3\">{{almacen.nombre}} - {{almacen.direccion}}</td>
            </tr>
        </table>
    </div>
    <div class=\"seccion-lineas\">
        <h3 class=\"seccion-subtitulo\">Detalle de Bienes y Servicios Solicitados</h3>
        {{tabla_lineas}}
    </div>
    <div class=\"seccion-totales\">
        <table class=\"tabla-totales\">
            <tr>
                <td class=\"tot-etiqueta\">Subtotal:</td>
                <td class=\"tot-valor\">{{totales.moneda}} {{totales.subtotal}}</td>
            </tr>
            <tr>
                <td class=\"tot-etiqueta\">Impuestos (IGV):</td>
                <td class=\"tot-valor\">{{totales.moneda}} {{totales.impuesto}}</td>
            </tr>
            <tr>
                <td class=\"tot-etiqueta font-bold\">TOTAL:</td>
                <td class=\"tot-valor font-bold\">{{totales.moneda}} {{totales.total}}</td>
            </tr>
        </table>
        <p class=\"monto-texto\">SON: {{totales.texto}}</p>
    </div>
    <div class=\"seccion-observaciones\">
        <p><strong>Observaciones / Notas:</strong> {{orden.notas}}</p>
    </div>
    <div class=\"seccion-firmas\">
        <table class=\"tabla-firmas\">
            <tr>
                <td class=\"firma-caja\">
                    <div class=\"linea-firma\"></div>
                    <p>Emitido por: Compras y Abastecimiento<br>Camargo Hostelería S.A.C.</p>
                </td>
                <td class=\"firma-caja\">
                    <div class=\"linea-firma\"></div>
                    <p>Aprobado por: Gerencia / Administración<br>Camargo Hostelería S.A.C.</p>
                </td>
            </tr>
        </table>
    </div>
</div>',
    'body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #222; }
.documento-orden-compra { width: 100%; margin: 0 auto; }
.encabezado-orden { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
.titulo-principal { font-size: 18pt; margin: 0; color: #0d6efd; text-transform: uppercase; }
.subtitulo-folio { font-size: 10pt; margin: 5px 0 0 0; color: #555; }
.tabla-info { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
.tabla-info td { padding: 4px 6px; font-size: 9pt; }
.campo-etiqueta { width: 18%; color: #333; }
.campo-valor { width: 32%; }
.seccion-subtitulo { font-size: 11pt; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; color: #333; }
.tabla-lineas-doc { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
.tabla-lineas-doc th { background-color: #f2f5f9; border: 1px solid #ccc; padding: 6px; font-size: 8.5pt; text-align: center; }
.tabla-lineas-doc td { border: 1px solid #ddd; padding: 5px 6px; font-size: 8.5pt; }
.seccion-totales { width: 100%; margin-top: 10px; }
.tabla-totales { float: right; width: 40%; border-collapse: collapse; margin-bottom: 10px; }
.tabla-totales td { padding: 4px 8px; font-size: 9.5pt; }
.tot-etiqueta { text-align: right; }
.tot-valor { text-align: right; }
.font-bold { font-weight: bold; }
.monto-texto { clear: both; font-style: italic; font-size: 8.5pt; color: #444; padding-top: 5px; }
.seccion-observaciones { margin-top: 15px; font-size: 8.5pt; border-left: 3px solid #0d6efd; padding-left: 8px; }
.seccion-firmas { margin-top: 50px; width: 100%; }
.tabla-firmas { width: 100%; border-collapse: collapse; }
.firma-caja { width: 50%; text-align: center; padding: 0 40px; font-size: 8.5pt; }
.linea-firma { border-top: 1px solid #333; margin-bottom: 5px; }',
    'Versión canónica inicial para emisión oficial de órdenes de compra con membrete A4',
    1,
    1
FROM `documento_plantillas` p
WHERE p.`codigo` = 'ORDEN_COMPRA'
ON DUPLICATE KEY UPDATE `titulo_documento` = VALUES(`titulo_documento`);
