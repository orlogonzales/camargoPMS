-- ============================================================================
-- CAMARGO PMS - MIGRACIÓN 019
-- Mantenimiento Preventivo, Correctivo, Incidencias Técnicas y Bloqueos
-- Fase: MANTENIMIENTO-1 (Decisión D-077)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Incidencias Técnicas (Tickets de Reporte)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'INC-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si la incidencia ocurre en áreas comunes o infraestructura general',
    `reportado_por_persona_id` BIGINT UNSIGNED NOT NULL,
    `categoria` ENUM(
        'PLOMERIA',
        'ELECTRICIDAD',
        'CERRAJERIA',
        'CLIMATIZACION',
        'PINTURA',
        'MOBILIARIO',
        'LIMPIEZA_PROFUNDA',
        'ESTRUCTURAL',
        'OTRO'
    ) NOT NULL DEFAULT 'OTRO',
    `severidad` ENUM('BAJA', 'MEDIA', 'ALTA', 'CRITICA') NOT NULL DEFAULT 'MEDIA',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NOT NULL,
    `ubicacion_detallada` VARCHAR(255) NULL,
    `estado` ENUM(
        'REPORTADA',
        'EN_EVALUACION',
        'CONVERTIDA_A_ORDEN',
        'RESUELTA_DIRECTA',
        'DESESTIMADA'
    ) NOT NULL DEFAULT 'REPORTADA',
    `motivo_cierre` VARCHAR(500) NULL COMMENT 'Explicación si se desestima o se resuelve directamente',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `cerrado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resuelto_en` DATETIME NULL,
    CONSTRAINT `chk_minc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_minc_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `fk_minc_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_reportador` FOREIGN KEY (`reportado_por_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_minc_actor_cerrador` FOREIGN KEY (`cerrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_minc_codigo` (`codigo`),
    INDEX `idx_minc_propiedad_unidad` (`propiedad_id`, `unidad_id`),
    INDEX `idx_minc_estado` (`estado`),
    INDEX `idx_minc_severidad` (`severidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reportes e incidencias físicas y técnicas en propiedades y unidades';

-- ----------------------------------------------------------------------------
-- 2. Órdenes de Trabajo (Instrumentos de Ejecución Técnica)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_ordenes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'OT-YYYYMMDD-XXXX',
    `tipo` ENUM('CORRECTIVO', 'PREVENTIVO') NOT NULL DEFAULT 'CORRECTIVO',
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si el trabajo es de áreas comunes o infraestructura general',
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NOT NULL,
    `tipo_asignacion` ENUM('INTERNO', 'EXTERNO', 'MIXTO') NOT NULL DEFAULT 'INTERNO',
    `colaborador_asignado_id` BIGINT UNSIGNED NULL COMMENT 'Personal interno responsable',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor externo homologado',
    `numero_comprobante_proveedor` VARCHAR(50) NULL COMMENT 'Nro de factura o boleta del proveedor',
    `requiere_bloqueo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si inhabilita físicamente la unidad para venta',
    `fecha_programada_inicio` DATE NOT NULL,
    `fecha_programada_fin` DATE NOT NULL,
    `fecha_bloqueo_inicio` DATE NULL COMMENT 'Inicio del bloqueo en inventario diario',
    `fecha_bloqueo_fin` DATE NULL COMMENT 'Fin del bloqueo (exclusivo) en inventario diario',
    `fecha_ejecucion_inicio` DATETIME NULL,
    `fecha_ejecucion_fin` DATETIME NULL,
    `costo_estimado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_mano_obra` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_materiales` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `costo_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM(
        'BORRADOR',
        'PROGRAMADA',
        'EN_PROCESO',
        'COMPLETADA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'BORRADOR',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `completado_por_actor_id` BIGINT UNSIGNED NULL,
    `cancelado_por_actor_id` BIGINT UNSIGNED NULL,
    `motivo_cancelacion` VARCHAR(500) NULL,
    `notas_cierre` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_mord_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_mord_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `chk_mord_fechas_programadas` CHECK (`fecha_programada_fin` >= `fecha_programada_inicio`),
    CONSTRAINT `chk_mord_bloqueo_coherente` CHECK (
        (`requiere_bloqueo` = 0 AND `fecha_bloqueo_inicio` IS NULL AND `fecha_bloqueo_fin` IS NULL) OR 
        (`requiere_bloqueo` = 1 AND `unidad_id` IS NOT NULL AND `fecha_bloqueo_inicio` IS NOT NULL AND `fecha_bloqueo_fin` IS NOT NULL AND `fecha_bloqueo_fin` > `fecha_bloqueo_inicio`)
    ),
    CONSTRAINT `chk_mord_costos_no_negativos` CHECK (`costo_estimado` >= 0 AND `costo_materiales` >= 0 AND `costo_mano_obra` >= 0 AND `costo_total` >= 0),
    CONSTRAINT `fk_mord_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_mord_colaborador` FOREIGN KEY (`colaborador_asignado_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_completador` FOREIGN KEY (`completado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_mord_actor_cancelador` FOREIGN KEY (`cancelado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_mord_codigo` (`codigo`),
    INDEX `idx_mord_propiedad_unidad` (`propiedad_id`, `unidad_id`),
    INDEX `idx_mord_estado` (`estado`),
    INDEX `idx_mord_fechas_programadas` (`fecha_programada_inicio`, `fecha_programada_fin`),
    INDEX `idx_mord_bloqueo` (`requiere_bloqueo`, `fecha_bloqueo_inicio`, `fecha_bloqueo_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Órdenes de trabajo preventivas y correctivas de mantenimiento';

-- ----------------------------------------------------------------------------
-- 3. Tabla Pivote Órdenes <-> Incidencias (Relación N:M)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_orden_incidencias` (
    `orden_id` BIGINT UNSIGNED NOT NULL,
    `incidencia_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`orden_id`, `incidencia_id`),
    CONSTRAINT `fk_moi_orden` FOREIGN KEY (`orden_id`) REFERENCES `mantenimiento_ordenes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_moi_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `mantenimiento_incidencias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Asociación entre órdenes de trabajo e incidencias atendidas';

-- ----------------------------------------------------------------------------
-- 4. Trazabilidad Histórica Inmutable de Estados (D-061)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mantenimiento_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entidad_tipo` ENUM('INCIDENCIA', 'ORDEN_TRABAJO') NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(30) NOT NULL,
    `estado_nuevo` VARCHAR(30) NOT NULL,
    `motivo` VARCHAR(500) NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `cambiado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mhe_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_mhe_entidad` (`entidad_tipo`, `entidad_id`, `cambiado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial inmutable de cambios de estado en mantenimiento e incidencias';

-- ----------------------------------------------------------------------------
-- 5. Permisos RBAC de Mantenimiento
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('mantenimiento.ver', 'Ver panel y órdenes de mantenimiento', 'Consultar incidencias, órdenes de trabajo y estados', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.incidencias.reportar', 'Reportar incidencias', 'Registrar desperfectos físicos en propiedades o unidades', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.incidencias.gestionar', 'Gestionar incidencias', 'Evaluar, clasificar y desestimar reportes de incidencias', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.crear', 'Crear órdenes de trabajo', 'Formular órdenes preventivas o correctivas', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.programar', 'Programar y asignar órdenes', 'Fijar fechas, asignar técnico/proveedor y autorizar bloqueo', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.ejecutar', 'Ejecutar órdenes de trabajo', 'Iniciar trabajos y registrar costos reales de materiales y mano de obra', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.cerrar', 'Cerrar y aprobar órdenes', 'Finalizar trabajos, registrar notas de cierre y liberar inventario', 'mantenimiento', 'ACTIVO', 1),
('mantenimiento.ordenes.cancelar', 'Cancelar órdenes de trabajo', 'Cancelar órdenes con motivo y liberar inventario bloqueado', 'mantenimiento', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación de permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo LIKE 'mantenimiento.%'
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

-- ----------------------------------------------------------------------------
-- 6. Opción de Menú Dinámico bajo 'reservas' (Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'mantenimiento_catalogo', 'Mantenimiento', 'fa-solid fa-screwdriver-wrench', '/mantenimiento', 6, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'mantenimiento.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
