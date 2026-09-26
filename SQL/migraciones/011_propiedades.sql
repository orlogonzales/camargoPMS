-- ============================================================================
-- Camargo PMS — Migración 011: Maestro Central de Propiedades (PROPIEDADES-1)
-- ============================================================================
-- Define la estructura de datos para el maestro de inmuebles/propiedades:
--   propiedades: Catálogo de inmuebles/contenedores físicos gestionados.
--
-- Principios vinculantes:
--   PROPIEDAD != UNIDAD: La propiedad es el inmueble/contenedor físico, no la
--                        unidad alquilable (departamento/habitación).
--   PROPIEDAD != REGISTRO DESECHABLE: Ciclo de vida exclusivo ACTIVO <-> INACTIVO.
--                                     Cero DELETE físico.
--   P-004 / P-005 / P-006 PENDIENTES: Cero fechas de reserva, disponibilidad,
--                                     tarifas, precios o inventario en esta fase.
-- ============================================================================

-- 1. Nuevos Permisos RBAC para Gestión de Propiedades
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('propiedades.ver', 'Ver catálogo y detalle de propiedades', 'Permite consultar el catálogo y los detalles de las propiedades', 'propiedades', 'ACTIVO', 1),
('propiedades.crear', 'Crear nuevas propiedades', 'Permite registrar nuevas propiedades en el sistema', 'propiedades', 'ACTIVO', 1),
('propiedades.editar', 'Modificar propiedades existentes', 'Permite editar la información de las propiedades', 'propiedades', 'ACTIVO', 1),
('propiedades.cambiar_estado', 'Activar o desactivar propiedades', 'Permite alternar el estado operacional entre ACTIVO e INACTIVO de una propiedad', 'propiedades', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- 2. Tabla de Propiedades
CREATE TABLE IF NOT EXISTS `propiedades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `pais_id` INT UNSIGNED NOT NULL,
    `departamento` VARCHAR(100) NULL,
    `provincia` VARCHAR(100) NULL,
    `distrito` VARCHAR(100) NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `referencia` VARCHAR(255) NULL,
    `latitud` DECIMAL(10, 7) NULL,
    `longitud` DECIMAL(10, 7) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_propiedades_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_propiedades_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_propiedades_direccion_no_vacia` CHECK (`direccion` <> ''),
    CONSTRAINT `chk_propiedades_latitud` CHECK (`latitud` IS NULL OR (`latitud` >= -90.0000000 AND `latitud` <= 90.0000000)),
    CONSTRAINT `chk_propiedades_longitud` CHECK (`longitud` IS NULL OR (`longitud` >= -180.0000000 AND `longitud` <= 180.0000000)),
    CONSTRAINT `fk_propiedades_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_propiedades_codigo` (`codigo`),
    INDEX `idx_propiedades_pais_id` (`pais_id`),
    INDEX `idx_propiedades_estado` (`estado`),
    INDEX `idx_propiedades_nombre` (`nombre`),
    INDEX `idx_propiedades_departamento` (`departamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de propiedades e inmuebles físicos administrados por Camargo PMS';

-- 3. Semillas Estructurales de Menú Dinámico
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'propiedades', 'Propiedades', 'ti ti-building', NULL, 10, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `icono` = VALUES(`icono`), `orden` = VALUES(`orden`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'propiedades_catalogo', 'Propiedades', 'ti ti-building', '/propiedades', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'propiedades' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'propiedades.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);
