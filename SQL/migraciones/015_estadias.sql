-- ============================================================================
-- Camargo PMS — Migración 015: Núcleo Operativo de Estadías y Huéspedes (ESTADÍAS-1)
-- ============================================================================
-- Implementa las estructuras para el registro y ciclo operativo de ocupación física:
--   1. Tabla estadias: registro maestro de check-in, llaves, fechas y check-out.
--   2. Tabla estadia_huespedes: registro de ocupantes y responsable vinculados a personas.
--   3. Permisos RBAC para el módulo de estadías.
--   4. Opción de menú dinámico bajo 'reservas'.
--
-- Principios vinculantes:
--   RESERVA != ESTADÍA != ARRENDAMIENTO.
--   1 RESERVA : N ESTADÍAS FÍSICAS (una estadía independiente por reserva_unidad).
--   UNIQUE(reserva_unidad_id): una reserva_unidad genera como máximo una estadía histórica.
--   CHECK-OUT != DELETE | ANULACIÓN != DELETE (Cero DELETE, ON DELETE RESTRICT).
--   EXACTAMENTE 1 huésped responsable por estadía.
--   BLOQUEO ESTRICTO DE CAPACIDAD: 1 <= huéspedes <= unidad.capacidad_personas.
--   D-066: fechas hoteleras DATE vs instantes técnicos reales UTC.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. TABLA DE ESTADÍAS (Registro operativo de ocupación física de unidades)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estadias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `reserva_unidad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_entrada` DATE NOT NULL COMMENT 'Fecha hotelera local de entrada (D-066)',
    `fecha_salida_prevista` DATE NOT NULL COMMENT 'Fecha hotelera local prevista de salida (D-066)',
    `estado` ENUM('EN_CURSO', 'FINALIZADA', 'ANULADA') NOT NULL DEFAULT 'EN_CURSO',
    `checkin_en` DATETIME NOT NULL COMMENT 'Instante técnico UTC real de check-in',
    `checkin_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `checkout_en` DATETIME NULL COMMENT 'Instante técnico UTC real de check-out',
    `checkout_por_actor_id` BIGINT UNSIGNED NULL,
    `identificador_llave` VARCHAR(50) NULL COMMENT 'Código de tarjeta magnética o número de llave física',
    `observaciones_checkin` TEXT NULL,
    `observaciones_checkout` TEXT NULL,
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulada_en` DATETIME NULL COMMENT 'Instante técnico UTC real de anulación',
    `anulada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_estadias_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_reserva_unidad` FOREIGN KEY (`reserva_unidad_id`) REFERENCES `reserva_unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_checkin` FOREIGN KEY (`checkin_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_checkout` FOREIGN KEY (`checkout_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_estadias_actor_anulador` FOREIGN KEY (`anulada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_estadias_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_estadias_fechas` CHECK (`fecha_salida_prevista` > `fecha_entrada`),
    UNIQUE KEY `uq_estadias_codigo` (`codigo`),
    UNIQUE KEY `uq_estadias_reserva_unidad` (`reserva_unidad_id`),
    INDEX `idx_estadias_reserva_id` (`reserva_id`),
    INDEX `idx_estadias_unidad_id` (`unidad_id`),
    INDEX `idx_estadias_estado` (`estado`),
    INDEX `idx_estadias_fechas` (`fecha_entrada`, `fecha_salida_prevista`),
    INDEX `idx_estadias_checkin_en` (`checkin_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro operativo de ocupación física real de unidades (Check-in / Check-out)';

-- ----------------------------------------------------------------------------
-- 2. TABLA DE HUÉSPEDES DE LA ESTADÍA (Ocupantes físicos y responsable)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estadia_huespedes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `estadia_id` BIGINT UNSIGNED NOT NULL,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `es_responsable` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es el huésped responsable de la estadía (exactamente 1 por estadía)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_estadia_huespedes_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_estadia_huespedes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_estadia_persona` (`estadia_id`, `persona_id`),
    INDEX `idx_estadia_huespedes_estadia` (`estadia_id`),
    INDEX `idx_estadia_huespedes_persona` (`persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Huéspedes y acompañantes alojados físicamente en la unidad';

-- ----------------------------------------------------------------------------
-- 3. PERMISOS RBAC PARA EL MÓDULO DE ESTADÍAS
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('estadias.ver', 'Ver estadías y ocupación', 'Permite consultar el catálogo, filtros y detalles de estadías', 'estadias', 'ACTIVO', 1),
('estadias.checkin', 'Realizar check-in de estadías', 'Permite efectuar el check-in físico de unidades reservadas', 'estadias', 'ACTIVO', 1),
('estadias.checkout', 'Realizar check-out de estadías', 'Permite registrar la salida física y devolución de llaves de unidades en curso', 'estadias', 'ACTIVO', 1),
('estadias.huespedes', 'Gestionar huéspedes de estadía', 'Permite actualizar acompañantes y responsable en estadías en curso', 'estadias', 'ACTIVO', 1),
('estadias.anular', 'Anular check-in de estadía', 'Permite anular excepcionalmente una estadía en curso con justificación obligatoria', 'estadias', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` IN ('estadias.ver', 'estadias.checkin', 'estadias.checkout', 'estadias.huespedes', 'estadias.anular')
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 4. OPCIÓN DE MENÚ DINÁMICO PARA ESTADÍAS (Nivel 2 bajo 'reservas')
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'estadias_catalogo', 'Estadías (Check-in)', 'fa-solid fa-bell-concierge', '/estadias', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'estadias.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);
