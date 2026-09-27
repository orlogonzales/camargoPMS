-- ============================================================================
-- Camargo PMS — Migración 014: Núcleo Transaccional de Reservas Directas
-- ============================================================================
-- Implementa las estructuras para la gestión de reservas comerciales directas:
--   1. Extensión de inventario_diario_unidades para soportar tipo 'RESERVA' (D-067).
--   2. Tabla reservas: registro maestro de operaciones comerciales.
--   3. Tabla reserva_unidades: detalle multiunidad y snapshot económico (1 Reserva : N Unidades).
--   4. Parámetro de configuración para duración de hold en reservas pendientes.
--   5. Permisos RBAC para el módulo de reservas.
--   6. Opción de menú dinámico para reservas (Nivel 1 y Nivel 2).
--
-- Principios vinculantes:
--   RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO != CONTRATO != PERSONA.
--   1 RESERVA : N UNIDADES (Arquitectura Multiunidad obligatoria).
--   Intervalo semiabierto [fecha_entrada, fecha_salida) (D-066).
--   Restricción UNIQUE(unidad_id, fecha) como defensa contra colisiones (D-067).
--   Contrato Monetario D-069: PEN, DECIMAL(15,2), redondeo comercial, snapshot inmutable.
--   Preservación histórica: PENDIENTE -> CONFIRMADA | CANCELADA | EXPIRADA (Cero DELETE).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. EXTENSIÓN DE INVENTARIO DIARIO PARA TIPO 'RESERVA' (D-067)
-- ----------------------------------------------------------------------------
ALTER TABLE `inventario_diario_unidades`
    MODIFY COLUMN `tipo_bloqueo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO', 'RESERVA') NOT NULL DEFAULT 'BLOQUEO_MANUAL';

-- ----------------------------------------------------------------------------
-- 2. TABLA DE RESERVAS (Registro maestro de operaciones comerciales)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `persona_titular_id` BIGINT UNSIGNED NOT NULL,
    `fecha_entrada` DATE NOT NULL,
    `fecha_salida` DATE NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `estado` ENUM('PENDIENTE', 'CONFIRMADA', 'CANCELADA', 'EXPIRADA') NOT NULL DEFAULT 'PENDIENTE',
    `expira_en` DATETIME NULL COMMENT 'Instante técnico absoluto de vencimiento de hold para reservas PENDIENTES',
    `canal` ENUM('PMS', 'WEB', 'APP', 'OTA') NOT NULL DEFAULT 'PMS',
    `origen` VARCHAR(50) NOT NULL DEFAULT 'DIRECTO',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuesto_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `observaciones` TEXT NULL,
    `motivo_cancelacion` VARCHAR(255) NULL,
    `cancelada_en` DATETIME NULL,
    `cancelada_por_actor_id` BIGINT UNSIGNED NULL,
    `confirmada_en` DATETIME NULL,
    `confirmada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reservas_persona_titular` FOREIGN KEY (`persona_titular_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_confirmador` FOREIGN KEY (`confirmada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reservas_actor_cancelador` FOREIGN KEY (`cancelada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_reservas_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_reservas_fechas` CHECK (`fecha_salida` > `fecha_entrada`),
    CONSTRAINT `chk_reservas_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_reservas_subtotal_no_negativo` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_reservas_impuesto_no_negativo` CHECK (`impuesto_total` >= 0.00),
    CONSTRAINT `chk_reservas_total_no_negativo` CHECK (`total` >= 0.00),
    UNIQUE KEY `uq_reservas_codigo` (`codigo`),
    INDEX `idx_reservas_persona_titular` (`persona_titular_id`),
    INDEX `idx_reservas_estado` (`estado`),
    INDEX `idx_reservas_fechas` (`fecha_entrada`, `fecha_salida`),
    INDEX `idx_reservas_expira_en` (`expira_en`),
    INDEX `idx_reservas_canal` (`canal`),
    INDEX `idx_reservas_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro maestro de reservas directas y contratos comerciales';

-- ----------------------------------------------------------------------------
-- 3. TABLA DE ASIGNACIÓN MULTIUNIDAD Y SNAPSHOT ECONÓMICO (1 Reserva : N Unidades)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reserva_unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reserva_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `precio_unitario_noche` DECIMAL(15,2) NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reserva_unidades_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reserva_unidades_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_reserva_unidades_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_reserva_unidades_precio` CHECK (`precio_unitario_noche` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_subtotal` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_impuesto` CHECK (`impuesto` >= 0.00),
    CONSTRAINT `chk_reserva_unidades_total` CHECK (`total` >= 0.00),
    UNIQUE KEY `uq_reserva_unidad` (`reserva_id`, `unidad_id`),
    INDEX `idx_reserva_unidades_reserva` (`reserva_id`),
    INDEX `idx_reserva_unidades_unidad` (`unidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de unidades asignadas y snapshot económico por unidad';

-- ----------------------------------------------------------------------------
-- 4. PARÁMETRO DE CONFIGURACIÓN PARA HOLD DE RESERVAS PENDIENTES
-- ----------------------------------------------------------------------------
INSERT INTO `configuraciones` (`clave`, `grupo`, `nombre`, `descripcion`, `tipo`, `valor`, `valor_predeterminado`, `editable`, `es_sensible`, `orden`, `estado`) VALUES
('reservas.duracion_hold_minutos', 'OPERACION', 'Duración de Hold para Reservas Pendientes (minutos)', 'Tiempo en minutos que una reserva en estado PENDIENTE retiene el inventario antes de expirar automáticamente', 'ENTERO', '30', '30', 1, 0, 6, 'ACTIVO')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`), `valor_predeterminado` = VALUES(`valor_predeterminado`);

-- ----------------------------------------------------------------------------
-- 5. PERMISOS RBAC PARA EL MÓDULO DE RESERVAS
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('reservas.ver', 'Ver reservas', 'Permite consultar el catálogo, filtros y detalles de reservas', 'reservas', 'ACTIVO', 1),
('reservas.crear', 'Crear reservas directas', 'Permite crear nuevas reservas directas multiunidad en el sistema', 'reservas', 'ACTIVO', 1),
('reservas.confirmar', 'Confirmar reservas', 'Permite cambiar el estado de reservas de PENDIENTE a CONFIRMADA', 'reservas', 'ACTIVO', 1),
('reservas.cancelar', 'Cancelar reservas', 'Permite cancelar reservas y liberar el inventario diario asociado', 'reservas', 'ACTIVO', 1),
('reservas.expirar', 'Expirar reservas vencidas', 'Permite ejecutar la expiración manual o por comando de reservas con hold vencido', 'reservas', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` IN ('reservas.ver', 'reservas.crear', 'reservas.confirmar', 'reservas.cancelar', 'reservas.expirar')
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 6. OPCIÓN DE MENÚ DINÁMICO PARA RESERVAS
-- ----------------------------------------------------------------------------
-- Nivel 1: Categoría Principal 'Reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'reservas', 'Reservas', 'ti ti-calendar-check', NULL, 15, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `orden` = VALUES(`orden`);

-- Nivel 2: Opción Secundaria 'Gestión de Reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'reservas_catalogo', 'Reservas', 'ti ti-list-check', '/reservas', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reservas.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);
