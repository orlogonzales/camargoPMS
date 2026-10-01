-- =====================================================================
-- Migración 038: Dominio Neutral de Pasarelas de Pago y Webhooks (PAGOS-1B)
-- Modela transacciones desacopladas con 3 ejes ortogonales de estado:
--   - estado_pago (INICIADO, PENDIENTE, PROCESANDO, APROBADO, FALLIDO, EXPIRADO, ANULADO)
--   - estado_conciliacion (PENDIENTE, CONCILIADO, DISCREPANCIA_HOLD_EXPIRADO, DISCREPANCIA_MONTO, DISCREPANCIA_MONEDA, DISCREPANCIA_SOBREVENTA, NO_REQUERIDA)
--   - estado_reembolso (NO_APLICA, PENDIENTE, PROCESANDO, REEMBOLSADO_TOTAL, REEMBOLSADO_PARCIAL, FALLIDO)
-- Y registro de eventos webhook para idempotencia multidimensional.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `pagos_transacciones_pasarela` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Código único del PMS (ej. TRX-202610-0001)',
    `reserva_id` BIGINT UNSIGNED NOT NULL COMMENT 'Reserva comercial asociada',
    `cuenta_folio_id` BIGINT UNSIGNED NULL COMMENT 'NULL si no está conciliada; FK a cuentas_folios',
    `pago_cuenta_id` BIGINT UNSIGNED NULL COMMENT 'NULL si no está asentada; FK a pagos_cuenta',
    `proveedor` VARCHAR(30) NOT NULL COMMENT 'Código canónico: CULQI, PAYPAL, IZIPAY',
    `tipo_operacion` ENUM('CARGO_DIRECTO', 'ORDEN_CHECKOUT', 'AUTORIZACION_PREVIA') NOT NULL DEFAULT 'ORDEN_CHECKOUT',
    `proveedor_orden_id` VARCHAR(100) NULL COMMENT 'ID de orden o intención en el proveedor (ord_..., order_id)',
    `proveedor_transaccion_id` VARCHAR(100) NULL COMMENT 'ID de cargo o captura (chr_..., capture_id)',
    `proveedor_referencia` VARCHAR(100) NULL COMMENT 'Referencia cruzada enviada al proveedor (ej. código reserva)',
    `estado_pago` ENUM('INICIADO', 'PENDIENTE', 'PROCESANDO', 'APROBADO', 'FALLIDO', 'EXPIRADO', 'ANULADO') NOT NULL DEFAULT 'INICIADO',
    `estado_conciliacion` ENUM('PENDIENTE', 'CONCILIADO', 'DISCREPANCIA_HOLD_EXPIRADO', 'DISCREPANCIA_MONTO', 'DISCREPANCIA_MONEDA', 'DISCREPANCIA_SOBREVENTA', 'NO_REQUERIDA') NOT NULL DEFAULT 'PENDIENTE',
    `estado_reembolso` ENUM('NO_APLICA', 'PENDIENTE', 'PROCESANDO', 'REEMBOLSADO_TOTAL', 'REEMBOLSADO_PARCIAL', 'FALLIDO') NOT NULL DEFAULT 'NO_APLICA',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `monto_esperado` DECIMAL(15,2) NOT NULL COMMENT 'Total formal de la reserva en PMS',
    `monto_cobrado` DECIMAL(15,2) NULL COMMENT 'Total efectivamente confirmado por pasarela',
    `monto_reembolsado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `metadatos_proveedor` JSON NULL COMMENT 'Marca, last4, comisiones, datos del medio',
    `motivo_discrepancia` VARCHAR(255) NULL COMMENT 'Detalle técnico si estado_conciliacion es DISCREPANCIA_*',
    `motivo_reembolso` VARCHAR(255) NULL COMMENT 'Causa de la devolución si aplica',
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor técnico de tipo PROVEEDOR_PAGO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_pagos_trx_codigo` (`codigo`),
    UNIQUE KEY `uk_proveedor_transaccion` (`proveedor`, `proveedor_transaccion_id`),
    KEY `idx_pagos_trx_reserva` (`reserva_id`),
    KEY `idx_pagos_trx_orden` (`proveedor`, `proveedor_orden_id`),
    KEY `idx_pagos_trx_folio` (`cuenta_folio_id`),
    KEY `idx_pagos_trx_pago` (`pago_cuenta_id`),
    KEY `idx_pagos_trx_estados` (`estado_pago`, `estado_conciliacion`, `estado_reembolso`),
    CONSTRAINT `fk_pagos_trx_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagos_trx_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagos_trx_pago` FOREIGN KEY (`pago_cuenta_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pagos_trx_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Transacciones y órdenes de pasarelas de pago externas';

CREATE TABLE IF NOT EXISTS `pagos_webhooks_eventos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transaccion_pasarela_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional hacia pagos_transacciones_pasarela',
    `proveedor` VARCHAR(30) NOT NULL COMMENT 'CULQI, PAYPAL, IZIPAY',
    `proveedor_evento_id` VARCHAR(100) NOT NULL COMMENT 'ID único de evento provisto por la pasarela (evt_...)',
    `tipo_evento` VARCHAR(100) NOT NULL COMMENT 'order.status.changed, PAYMENT.CAPTURE.COMPLETED, etc.',
    `cuerpo_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del cuerpo HTTP crudo para integridad',
    `payload_raw` LONGTEXT NOT NULL COMMENT 'Cuerpo crudo recibido para auditoría',
    `cabeceras` JSON NULL COMMENT 'Cabeceras HTTP relevantes de la petición',
    `estado_procesamiento` ENUM('PROCESADO', 'DUPLICADO_OMITIDO', 'ERROR_VERIFICACION', 'ERROR_PROCESAMIENTO') NOT NULL DEFAULT 'PROCESADO',
    `codigo_http_respuesta` SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    `error_detalle` VARCHAR(500) NULL,
    `recibido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `procesado_en` DATETIME NULL,
    UNIQUE KEY `uk_webhook_proveedor_evento` (`proveedor`, `proveedor_evento_id`),
    KEY `idx_webhook_transaccion` (`transaccion_pasarela_id`),
    KEY `idx_webhook_proveedor_tipo` (`proveedor`, `tipo_evento`),
    KEY `idx_webhook_recibido` (`recibido_en`),
    CONSTRAINT `fk_webhook_transaccion` FOREIGN KEY (`transaccion_pasarela_id`) REFERENCES `pagos_transacciones_pasarela` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Bitácora de eventos y notificaciones webhook de pasarelas';

-- Actor técnico estructural para Culqi
INSERT INTO `actores` (`tipo`, `codigo`, `nombre`, `usuario_id`, `estado`) VALUES
('PROVEEDOR_PAGO', 'PASARELA_CULQI', 'Pasarela de Pago Culqi (Webhooks y API)', NULL, 'ACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `estado` = VALUES(`estado`);
