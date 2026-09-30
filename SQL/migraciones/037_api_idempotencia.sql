-- =====================================================================
-- Migración 037: Tabla para idempotencia del perímetro API (WORDPRESS-1C)
-- Identidad lógica vinculante: api_cliente_id + ruta + clave_idempotencia
-- Previene ejecuciones concurrentes colisionantes y sobreventas por reintentos.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `api_idempotencia` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `api_cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'Cliente API titular de la petición',
    `clave_idempotencia` VARCHAR(128) NOT NULL COMMENT 'Clave única provista por el cliente en la cabecera Idempotency-Key',
    `ruta` VARCHAR(255) NOT NULL COMMENT 'Ruta relativa de la operación (ej. /api/v1/reservas)',
    `metodo` VARCHAR(10) NOT NULL COMMENT 'Método HTTP mutable (POST, PUT, PATCH)',
    `cuerpo_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del cuerpo recibido para detectar colisiones de payload',
    `estado` ENUM('PROCESANDO', 'COMPLETADO', 'ERROR') NOT NULL DEFAULT 'PROCESANDO' COMMENT 'Estado del procesamiento idempotente',
    `codigo_http` SMALLINT UNSIGNED NULL COMMENT 'Código de respuesta HTTP almacenado',
    `cabeceras_json` TEXT NULL COMMENT 'Encabezados relevantes de la respuesta para el replay',
    `respuesta_json` MEDIUMTEXT NULL COMMENT 'Cuerpo de la respuesta serializado para replay determinista',
    `bloqueado_hasta` DATETIME NOT NULL COMMENT 'Límite temporal de exclusión para evitar carreras paralelas de la misma clave',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_api_idemp_cliente` FOREIGN KEY (`api_cliente_id`) REFERENCES `api_clientes` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_api_idemp_cliente_ruta_clave` (`api_cliente_id`, `ruta`, `clave_idempotencia`),
    KEY `idx_api_idemp_limpieza` (`creado_en`),
    KEY `idx_api_idemp_bloqueo` (`estado`, `bloqueado_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de idempotencia y control de replay del perímetro API';
