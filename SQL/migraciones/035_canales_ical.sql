-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 035: INFRAESTRUCTURA MULTICANAL ICALENDAR (AIRBNB-ICAL-1B)
-- ============================================================================
-- Principios Arquitectónicos y Ontológicos Vinculantes:
-- 1. DESACOPLAMIENTO DE DOMINIO:
--    EVENTO ICAL EXTERNO ≠ RESERVA PMS.
--    Un VEVENT externo es una indisponibilidad de calendario, no genera automáticamente
--    Persona, Cliente, Reserva, Estadía, Folio, Contrato ni Factura.
-- 2. SEPARACIÓN DE RESPONSABILIDADES:
--    CANAL (Catálogo) ≠ CONEXIÓN (1:N por Unidad) ≠ EVENTO (VEVENT) ≠ LOG DE SINCRONIZACIÓN.
-- 3. INTEGRIDAD DE INVENTARIO:
--    `inventario_diario_unidades` se mantiene 100% INTACTA sin ALTER TABLE ni mutación de ENUM:
--    tipo_bloqueo = 'BLOQUEO_MANUAL', origen_tipo = 'EVENTO_ICAL_EXTERNO', origen_id = evento_externo.id.
-- 4. SEGURIDAD CRIPTOGRÁFICA Y DEFENSA EN PROFUNDIDAD:
--    - URLs externas cifradas en reposo con AES-256-GCM mediante ICAL_ENCRYPTION_KEY.
--    - Tokens de exportación con almacenamiento híbrido: SHA-256 (búsqueda O(1) segura)
--      y AES-256-GCM (recuperabilidad administrativa sin almacenar texto plano).
-- 5. ANTI-ECHO Y ASINCRONÍA ICAL:
--    - Feeds de exportación específicos por conexión destino para suprimir el eco de la misma OTA.
--    - Protocolo asíncrono que mitiga sobreventa pero no garantiza overbooking cero en tiempo real.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Maestro de Canales de Distribución
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `canales_distribucion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código canónico del canal (AIRBNB, BOOKING, VRBO, GENERICO)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre comercial del canal',
    `protocolo` VARCHAR(20) NOT NULL DEFAULT 'ICAL' COMMENT 'Protocolo técnico de integración (ICAL)',
    `frecuencia_defecto_minutos` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Frecuencia de sondeo sugerida en minutos',
    `color_badge` VARCHAR(30) NOT NULL DEFAULT 'badge-light-primary' COMMENT 'Clase visual Alina para badges',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_canales_distribucion_codigo` (`codigo`),
    INDEX `idx_canales_distribucion_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de canales de distribución y OTAs';

-- Semillas oficiales de Canales Iniciales
INSERT INTO `canales_distribucion` (`codigo`, `nombre`, `protocolo`, `frecuencia_defecto_minutos`, `color_badge`, `estado`) VALUES
('AIRBNB', 'Airbnb', 'ICAL', 60, 'badge-light-danger', 'ACTIVO'),
('BOOKING', 'Booking.com', 'ICAL', 60, 'badge-light-primary', 'ACTIVO'),
('VRBO', 'VRBO / HomeAway', 'ICAL', 60, 'badge-light-info', 'ACTIVO'),
('GENERICO', 'Canal iCal Genérico', 'ICAL', 60, 'badge-light-secondary', 'ACTIVO');

-- ----------------------------------------------------------------------------
-- 2. Conexiones iCalendar por Unidad Habitacional (1:N)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conexiones_ical` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Unidad habitacional vinculada',
    `canal_id` INT UNSIGNED NOT NULL COMMENT 'Canal de distribución asociado',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo de la conexión (ej. Airbnb Suite 101)',
    `url_importacion_cifrada` TEXT DEFAULT NULL COMMENT 'URL externa del feed iCal cifrada con AES-256-GCM',
    `token_exportacion_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del token de exportación para búsqueda indexada O(1)',
    `token_exportacion_cifrado` TEXT NOT NULL COMMENT 'Token en claro cifrado con AES-256-GCM para visualización en panel',
    `token_prefijo` VARCHAR(16) NOT NULL COMMENT 'Prefijo legible del token para identificación segura sin descifrar',
    `importacion_habilitada` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si permite importar eventos desde la URL externa',
    `exportacion_habilitada` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si el feed de salida responde con bloqueos',
    `frecuencia_minutos` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Frecuencia de sincronización programada',
    `estado` ENUM('ACTIVO', 'PAUSADO', 'REVOCADO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado operativo de la conexión',
    `ultima_sincronizacion_en` DATETIME DEFAULT NULL COMMENT 'Timestamp de la última sincronización ejecutada',
    `ultimo_resultado` ENUM('EXITO', 'CON_ADVERTENCIA', 'ERROR', 'NO_EJECUTADO') NOT NULL DEFAULT 'NO_EJECUTADO',
    `ultimo_error` TEXT DEFAULT NULL COMMENT 'Detalle del último error técnico o advertencia',
    `creado_por` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Actor o usuario que creó la conexión',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_conexiones_ical_token_hash` (`token_exportacion_hash`),
    INDEX `idx_conexiones_ical_unidad` (`unidad_id`),
    INDEX `idx_conexiones_ical_canal` (`canal_id`),
    INDEX `idx_conexiones_ical_estado` (`estado`),
    CONSTRAINT `fk_conexiones_ical_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_conexiones_ical_canal` FOREIGN KEY (`canal_id`) REFERENCES `canales_distribucion` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Conexiones iCalendar 1:N por unidad habitacional';

-- ----------------------------------------------------------------------------
-- 3. Eventos iCalendar Externos (VEVENT)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventos_ical_externos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conexion_ical_id` BIGINT UNSIGNED NOT NULL COMMENT 'Conexión que importó el evento',
    `uid_externo` VARCHAR(255) NOT NULL COMMENT 'UID original del VEVENT en el feed externo',
    `fecha_inicio` DATE NOT NULL COMMENT 'Fecha de inicio del bloqueo hotelero (inclusive)',
    `fecha_fin` DATE NOT NULL COMMENT 'Fecha de fin del bloqueo hotelero [inicio, fin) (exclusive)',
    `noches` INT UNSIGNED NOT NULL COMMENT 'Cantidad de noches bloqueadas',
    `resumen` VARCHAR(255) NOT NULL DEFAULT 'Bloqueo Canal Externo' COMMENT 'SUMMARY normalizado',
    `descripcion` TEXT DEFAULT NULL COMMENT 'DESCRIPTION original si viene provista',
    `estado_evento` ENUM('ACTIVO', 'CANCELADO', 'AUSENTE', 'EN_CONFLICTO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado ontológico del evento externo',
    `estado_bloqueo` ENUM('APLICADO', 'EN_CONFLICTO', 'LIBERADO', 'APLICADO_CON_SOLAPAMIENTO', 'IGNORADO') NOT NULL DEFAULT 'APLICADO' COMMENT 'Estado físico del bloqueo en inventario_diario_unidades',
    `detalle_conflicto` TEXT DEFAULT NULL COMMENT 'Explicación del conflicto en caso de colisión local o error',
    `ultima_modificacion_externa` DATETIME DEFAULT NULL COMMENT 'LAST-MODIFIED del VEVENT',
    `secuencia_externa` INT DEFAULT NULL COMMENT 'SEQUENCE del VEVENT',
    `es_recurrente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el evento proviene de expansión de RRULE',
    `recurrencia_rrule` TEXT DEFAULT NULL COMMENT 'Regla RRULE original si aplica',
    `ultimo_sync_run_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'ID de la última corrida de sincronización que lo observó',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_evento_conexion_uid` (`conexion_ical_id`, `uid_externo`),
    INDEX `idx_eventos_ical_fechas` (`conexion_ical_id`, `fecha_inicio`, `fecha_fin`),
    INDEX `idx_eventos_ical_estado_evento` (`conexion_ical_id`, `estado_evento`),
    INDEX `idx_eventos_ical_estado_bloqueo` (`conexion_ical_id`, `estado_bloqueo`),
    CONSTRAINT `fk_eventos_ical_conexion` FOREIGN KEY (`conexion_ical_id`) REFERENCES `conexiones_ical` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Eventos VEVENT externos importados desde feeds iCal';

-- ----------------------------------------------------------------------------
-- 4. Log y Telemetría Técnica de Sincronizaciones iCalendar
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sincronizaciones_ical_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conexion_ical_id` BIGINT UNSIGNED NOT NULL COMMENT 'Conexión sincronizada',
    `tipo_operacion` ENUM('IMPORTACION', 'EXPORTACION') NOT NULL COMMENT 'Sentido del intercambio',
    `origen_ejecucion` ENUM('MANUAL', 'CLI', 'CRON', 'WEBHOOK') NOT NULL DEFAULT 'MANUAL' COMMENT 'Disparador de la ejecución',
    `iniciado_en` DATETIME NOT NULL COMMENT 'Timestamp de inicio del sondeo o entrega',
    `finalizado_en` DATETIME DEFAULT NULL COMMENT 'Timestamp de finalización',
    `duracion_ms` INT UNSIGNED DEFAULT NULL COMMENT 'Duración total en milisegundos',
    `http_codigo` INT DEFAULT NULL COMMENT 'Código de respuesta HTTP obtenido del feed externo',
    `resultado` ENUM('EXITO', 'CON_ADVERTENCIA', 'ERROR') NOT NULL COMMENT 'Resultado de la ejecución',
    `eventos_recibidos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Conteo de VEVENTs parseados',
    `eventos_creados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nuevos eventos insertados',
    `eventos_actualizados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Eventos modificados',
    `eventos_cancelados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Eventos marcados CANCELLED',
    `eventos_ausentes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Eventos ausentes en feed',
    `conflictos_detectados` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Colisiones con reservas locales detectadas',
    `mensaje_resultado` TEXT DEFAULT NULL COMMENT 'Mensaje explicativo o advertencia técnica',
    `actor_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Actor técnico o usuario responsable',
    `ip_origen` VARCHAR(45) DEFAULT NULL COMMENT 'IP cliente en caso de exportación o webhook',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sync_log_conexion` (`conexion_ical_id`, `iniciado_en`),
    INDEX `idx_sync_log_resultado` (`resultado`),
    CONSTRAINT `fk_sync_log_conexion` FOREIGN KEY (`conexion_ical_id`) REFERENCES `conexiones_ical` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Telemetría y log técnico de sincronizaciones iCalendar';

SET FOREIGN_KEY_CHECKS = 1;
