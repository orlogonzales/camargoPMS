-- ============================================================================
-- Camargo PMS — Migración 012: Maestro Central de Unidades (UNIDADES-1)
-- ============================================================================
-- Define la estructura de datos para el maestro de unidades habitacionales/físicas:
--   tipos_unidad: Catálogo de tipos de unidad (Departamento, Habitación, Casa, etc.).
--   unidades: Maestro de divisiones físicas alojables dependientes de una propiedad.
--
-- Principios vinculantes:
--   PROPIEDAD != UNIDAD: La propiedad es el contenedor físico; la unidad es la
--                        división física comercializable/alojable.
--   UNIDAD != RESERVA / TARIFA / DISPONIBILIDAD: Cero campos de disponibilidad,
--                        fechas, precios, monedas, bloqueos o contratos.
--   UNIDAD != REGISTRO DESECHABLE: Ciclo de vida exclusivo ACTIVO <-> INACTIVO.
--                                  Cero DELETE físico.
--   P-004 / P-005 / P-006 PENDIENTES: Se preserva la apertura deliberada de decisiones.
-- ============================================================================

-- 1. Nuevos Permisos RBAC para Gestión de Unidades
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('unidades.ver', 'Ver catálogo y detalle de unidades', 'Permite consultar el catálogo y los detalles de las unidades', 'unidades', 'ACTIVO', 1),
('unidades.crear', 'Crear nuevas unidades', 'Permite registrar nuevas unidades habitacionales en el sistema', 'unidades', 'ACTIVO', 1),
('unidades.editar', 'Modificar unidades existentes', 'Permite editar la información física y descriptiva de las unidades', 'unidades', 'ACTIVO', 1),
('unidades.cambiar_estado', 'Activar o desactivar unidades', 'Permite alternar el estado operacional entre ACTIVO e INACTIVO de una unidad', 'unidades', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- 2. Catálogo de Tipos de Unidad
CREATE TABLE IF NOT EXISTS `tipos_unidad` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_tipos_unidad_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_tipos_unidad_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_tipos_unidad_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de tipologías arquitectónicas de unidades alojables';

-- Semillas base para tipos de unidad
INSERT INTO `tipos_unidad` (`codigo`, `nombre`, `descripcion`, `activo`) VALUES
('DEPARTAMENTO', 'Departamento', 'Unidad habitacional independiente en edificio o condominio', 1),
('HABITACION', 'Habitación', 'Espacio privado para alojamiento dentro de un inmueble', 1),
('CASA', 'Casa', 'Inmueble unifamiliar completo o vivienda independiente', 1),
('SUITE', 'Suite', 'Habitación premium con sala de estar o ambientes integrados', 1),
('BUNGALOW', 'Bungalow', 'Cabaña o módulo campestre/playero independiente', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- 3. Tabla Principal de Unidades
CREATE TABLE IF NOT EXISTS `unidades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `tipo_unidad_id` INT UNSIGNED NOT NULL,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `piso_nivel` VARCHAR(30) NULL,
    `capacidad_personas` INT UNSIGNED NOT NULL DEFAULT 1,
    `dormitorios` INT UNSIGNED NOT NULL DEFAULT 1,
    `banos` DECIMAL(3, 1) NOT NULL DEFAULT 1.0,
    `area_m2` DECIMAL(8, 2) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_unidades_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_unidades_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_unidades_capacidad_positiva` CHECK (`capacidad_personas` >= 1),
    CONSTRAINT `chk_unidades_dormitorios` CHECK (`dormitorios` >= 0),
    CONSTRAINT `chk_unidades_banos` CHECK (`banos` >= 0.0),
    CONSTRAINT `chk_unidades_area_positiva` CHECK (`area_m2` IS NULL OR `area_m2` > 0.00),
    CONSTRAINT `fk_unidades_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_unidades_tipo` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_unidades_propiedad_codigo` (`propiedad_id`, `codigo`),
    INDEX `idx_unidades_propiedad_id` (`propiedad_id`),
    INDEX `idx_unidades_tipo_unidad_id` (`tipo_unidad_id`),
    INDEX `idx_unidades_estado` (`estado`),
    INDEX `idx_unidades_codigo` (`codigo`),
    INDEX `idx_unidades_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de unidades físicas habitacionales y alojables';

-- 4. Semilla de Menú Dinámico para Unidades (Nivel 2 bajo 'propiedades')
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'unidades_catalogo', 'Unidades', 'ti ti-door', '/unidades', 2, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'unidades.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);
