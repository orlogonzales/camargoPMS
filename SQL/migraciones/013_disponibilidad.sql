-- ============================================================================
-- Camargo PMS — Migración 013: Motor Central de Disponibilidad e Inventario Diario
-- ============================================================================
-- Implementa las estructuras para el motor de disponibilidad y tiempo hotelero:
--   1. Zona horaria en tabla propiedades (D-066 / P-004).
--   2. Parámetro de zona horaria predeterminada en configuraciones (D-066).
--   3. Tabla bloqueos_unidad: registro maestro de bloqueos administrativos/técnicos.
--   4. Tabla inventario_diario_unidades: inventario sparse con UNIQUE(unidad_id, fecha) (D-067 / P-006).
--   5. Permisos RBAC y opción de menú autorizada.
--
-- Principios vinculantes:
--   INSTANTE != FECHA HOTELERA != HORARIO OPERACIONAL (D-066).
--   UNIDAD != DISPONIBILIDAD != RESERVA != TARIFA.
--   MODELO HÍBRIDO SPARSE con UNIQUE(unidad_id, fecha) (D-067).
--   P-005 (Moneda, redondeo e impuestos) permanece PENDIENTE.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. ZONA HORARIA EN PROPIEDADES (D-066 / P-004)
-- ----------------------------------------------------------------------------
-- Permite que cada propiedad declare su propio huso horario IANA. Si es NULL,
-- el sistema recurre en cascada al parámetro central del PMS.
-- ----------------------------------------------------------------------------
ALTER TABLE `propiedades`
    ADD COLUMN `zona_horaria` VARCHAR(50) NULL DEFAULT NULL AFTER `direccion`;

-- ----------------------------------------------------------------------------
-- 2. PARÁMETRO DE ZONA HORARIA PREDETERMINADA EN CONFIGURACIONES (D-066)
-- ----------------------------------------------------------------------------
INSERT INTO `configuraciones` (`clave`, `grupo`, `nombre`, `descripcion`, `tipo`, `valor`, `valor_predeterminado`, `editable`, `es_sensible`, `orden`, `estado`) VALUES
('operacion.zona_horaria_predeterminada', 'OPERACION', 'Zona Horaria Predeterminada', 'Identificador IANA de la zona horaria central del sistema (ej. America/Lima)', 'TEXTO', 'America/Lima', 'America/Lima', 1, 0, 5, 'ACTIVO')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`), `valor_predeterminado` = VALUES(`valor_predeterminado`);

-- ----------------------------------------------------------------------------
-- 3. PERMISOS RBAC PARA DISPONIBILIDAD E INVENTARIO
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('disponibilidad.ver', 'Ver disponibilidad e inventario', 'Permite consultar el calendario, estados de ocupación y unidades disponibles', 'disponibilidad', 'ACTIVO', 1),
('disponibilidad.bloquear', 'Crear bloqueos de inventario', 'Permite aplicar bloqueos manuales y técnicos sobre unidades para fechas determinadas', 'disponibilidad', 'ACTIVO', 1),
('disponibilidad.liberar', 'Liberar bloqueos de inventario', 'Permite levantar bloqueos previamente aplicados y restablecer la disponibilidad', 'disponibilidad', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` IN ('disponibilidad.ver', 'disponibilidad.bloquear', 'disponibilidad.liberar')
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 4. TABLA DE BLOQUEOS DE UNIDAD (Registro maestro operacional)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bloqueos_unidad` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `noches` INT UNSIGNED NOT NULL,
    `motivo` VARCHAR(255) NOT NULL,
    `tipo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `estado` ENUM('ACTIVO', 'LIBERADO') NOT NULL DEFAULT 'ACTIVO',
    `creado_por_actor_id` BIGINT UNSIGNED NULL,
    `liberado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `liberado_en` DATETIME NULL,
    CONSTRAINT `chk_bloqueos_fechas` CHECK (`fecha_fin` > `fecha_inicio`),
    CONSTRAINT `chk_bloqueos_noches` CHECK (`noches` >= 1),
    CONSTRAINT `chk_bloqueos_motivo_no_vacio` CHECK (`motivo` <> ''),
    CONSTRAINT `fk_bloqueos_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_actor_liberador` FOREIGN KEY (`liberado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_bloqueos_unidad_id` (`unidad_id`),
    INDEX `idx_bloqueos_estado` (`estado`),
    INDEX `idx_bloqueos_rango` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro maestro de bloqueos administrativos y técnicos de unidades';

-- ----------------------------------------------------------------------------
-- 5. TABLA DE INVENTARIO DIARIO DE UNIDADES (Modelo Sparse con UNIQUE)
-- ----------------------------------------------------------------------------
-- Representa únicamente noches comprometidas/ocupadas. La ausencia de fila
-- para (unidad_id, fecha) en [fecha_entrada, fecha_salida) significa disponible.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_diario_unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `tipo_bloqueo` ENUM('BLOQUEO_MANUAL', 'MANTENIMIENTO') NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `origen_tipo` VARCHAR(50) NOT NULL DEFAULT 'BLOQUEO_MANUAL',
    `origen_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventario_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_inventario_unidad_fecha` (`unidad_id`, `fecha`),
    INDEX `idx_inventario_fecha` (`fecha`),
    INDEX `idx_inventario_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_inventario_unidad_fecha` (`unidad_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Inventario diario sparse de ocupación y bloqueos por noche y unidad';

-- ----------------------------------------------------------------------------
-- 6. OPCIÓN DE MENÚ DINÁMICO PARA DISPONIBILIDAD (Nivel 2 bajo 'propiedades')
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'disponibilidad_calendario', 'Disponibilidad', 'ti ti-calendar-event', '/disponibilidad', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'disponibilidad.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);
