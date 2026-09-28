-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 025: HOUSEKEEPING, PISOS Y CONTROL DE LENCERÍA
-- Decisión Vinculante: D-083 (GATE HOUSEKEEPING-1)
-- Tablas: 81 a 89 del esquema canónico
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 81. Estado Físico de Higiene y Limpieza de Unidades (HOUSEKEEPING-1 / D-083)
-- Relación 1:1 con unidades físicas. No duplica ocupación ni comercial.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_unidades_limpieza` (
    `unidad_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `estado_limpieza` ENUM(
        'SUCIA',
        'EN_LIMPIEZA',
        'LIMPIA_POR_INSPECCIONAR',
        'LIMPIA_INSPECCIONADA',
        'RETOQUE_REQUERIDO'
    ) NOT NULL DEFAULT 'SUCIA',
    `tarea_activa_id` BIGINT UNSIGNED NULL,
    `ultima_limpieza_en` DATETIME NULL,
    `ultima_inspeccion_en` DATETIME NULL,
    `inspeccionado_por_actor_id` BIGINT UNSIGNED NULL,
    `observaciones` TEXT NULL,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hku_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hku_actor` FOREIGN KEY (`inspeccionado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hku_estado` (`estado_limpieza`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Estado físico de higiene y sanitización de unidades (1:1 con unidades)';

-- ----------------------------------------------------------------------------
-- 82. Catálogo Maestro de Plantillas de Checklist de Inspección (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_plantillas_checklist` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_tarea` ENUM('TODAS', 'SALIDA', 'ESTADIA', 'PROFUNDA', 'RETOQUE') NOT NULL DEFAULT 'TODAS',
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `es_activa` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hkpc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_hkpc_nombre_no_vacio` CHECK (`nombre` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Estándares de inspección y checklists versionados';

-- ----------------------------------------------------------------------------
-- 83. Ítems Individuales de Plantilla de Checklist (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_plantilla_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plantilla_id` INT UNSIGNED NOT NULL,
    `categoria` VARCHAR(50) NOT NULL COMMENT 'BANO, DORMITORIO, SUPERFICIES, EQUIPAMIENTO, AMENITIES, GENERAL',
    `codigo_item` VARCHAR(30) NOT NULL,
    `descripcion` VARCHAR(200) NOT NULL,
    `es_critico` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el item es obligatorio para aprobar la habitación',
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hkpi_codigo_no_vacio` CHECK (`codigo_item` <> ''),
    CONSTRAINT `chk_hkpi_desc_no_vacia` CHECK (`descripcion` <> ''),
    CONSTRAINT `fk_hkpi_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `housekeeping_plantillas_checklist` (`id`) ON DELETE CASCADE,
    INDEX `idx_hkpi_plantilla_orden` (`plantilla_id`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Puntos de control individuales del estándar de inspección';

-- ----------------------------------------------------------------------------
-- 84. Órdenes Operativas de Trabajo de Housekeeping (HOUSEKEEPING-1 / D-083)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tareas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato HK-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Estadía vinculada si la tarea fue disparada por check-out o stay-over',
    `tipo_tarea` ENUM('SALIDA', 'ESTADIA', 'PROFUNDA', 'RETOQUE') NOT NULL,
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `estado` ENUM(
        'PENDIENTE',
        'ASIGNADA',
        'EN_PROCESO',
        'POR_INSPECCIONAR',
        'RECHAZADA',
        'COMPLETADA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'PENDIENTE',
    `camarera_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador asignado para limpiar',
    `supervisor_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador que inspecciona',
    `fecha_programada` DATE NOT NULL,
    `iniciado_en` DATETIME NULL,
    `terminado_en` DATETIME NULL COMMENT 'Momento en que camarera envía a inspección',
    `inspeccionado_en` DATETIME NULL COMMENT 'Momento en que supervisor completa inspección',
    `condicion_operacional` ENUM('NINGUNA', 'DND', 'SIN_ACCESO', 'RECHAZO_HUESPED') NOT NULL DEFAULT 'NINGUNA',
    `notas_operario` TEXT NULL,
    `notas_supervisor` TEXT NULL,
    `motivo_cancelacion` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `salida_estadia_activa_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(`tipo_tarea` = 'SALIDA' AND `estado` NOT IN ('CANCELADA'), `estadia_id`, NULL)
    ) VIRTUAL COMMENT 'Garantiza idempotencia estricta: 1 checkout -> máximo 1 tarea de salida activa',
    CONSTRAINT `chk_hkt_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_hkt_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_camarera` FOREIGN KEY (`camarera_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_supervisor` FOREIGN KEY (`supervisor_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_hkt_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_hkt_salida_estadia` (`salida_estadia_activa_idx`),
    INDEX `idx_hkt_unidad_estado` (`unidad_id`, `estado`),
    INDEX `idx_hkt_fecha_prioridad` (`fecha_programada`, `prioridad`),
    INDEX `idx_hkt_camarera` (`camarera_colaborador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tareas operativas de intervención de limpieza e inspección';

-- ----------------------------------------------------------------------------
-- 85. Snapshot Inmutable de Checklist por Tarea (HOUSEKEEPING-1 / D-083)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tarea_checklist` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `codigo_item_snapshot` VARCHAR(30) NOT NULL,
    `categoria_snapshot` VARCHAR(50) NOT NULL,
    `descripcion_snapshot` VARCHAR(200) NOT NULL,
    `es_critico_snapshot` TINYINT(1) NOT NULL DEFAULT 0,
    `resultado` ENUM('CONFORME', 'NO_CONFORME', 'NO_APLICA') NOT NULL DEFAULT 'CONFORME',
    `observacion` VARCHAR(255) NULL,
    `verificado_en` DATETIME NULL,
    `verificado_por_actor_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `fk_hktchk_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `housekeeping_tareas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hktchk_actor` FOREIGN KEY (`verificado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_hktchk_tarea` (`tarea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Evaluación congelada e inmutable de puntos de control por tarea';

-- ----------------------------------------------------------------------------
-- 86. Consumos de Amenities Repuestos Vinculados a Kardex (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tarea_consumos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `almacen_origen_id` BIGINT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `movimiento_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a inventario_movimientos con tipo SALIDA_CONSUMO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hktcon_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `fk_hktcon_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `housekeeping_tareas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hktcon_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hktcon_almacen` FOREIGN KEY (`almacen_origen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hktcon_movimiento` FOREIGN KEY (`movimiento_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hktcon_tarea` (`tarea_id`),
    INDEX `idx_hktcon_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Consumos reales de amenities en limpieza vinculados atómicamente a Kardex';

-- ----------------------------------------------------------------------------
-- 87. Historial Inmutable Append-Only de Estados de Tarea (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_tarea_historial` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tarea_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(50) NULL,
    `estado_nuevo` VARCHAR(50) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `motivo` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hkth_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `housekeeping_tareas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hkth_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hkth_tarea` (`tarea_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad append-only de transiciones de tarea y auditoría D-061';

-- ----------------------------------------------------------------------------
-- 88. Lotes de Despacho y Retorno de Lavandería Textil (HOUSEKEEPING-1 / D-083)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_lotes_lavanderia` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato LAV-YYYYMMDD-XXXX',
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `almacen_origen_id` BIGINT UNSIGNED NOT NULL COMMENT 'Almacén/office local de lencería sucia',
    `ubicacion_lavanderia_id` BIGINT UNSIGNED NOT NULL COMMENT 'Ubicación CUSTODIA_EXTERNA de lavandería',
    `fecha_despacho` DATE NOT NULL,
    `fecha_retorno_estimada` DATE NULL,
    `fecha_retorno_real` DATE NULL,
    `estado` ENUM('DESPACHADO', 'RETORNADO_TOTAL', 'RETORNADO_PARCIAL', 'CON_DISCREPANCIA', 'ANULADO') NOT NULL DEFAULT 'DESPACHADO',
    `notas_despacho` TEXT NULL,
    `notas_retorno` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_hkl_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_hkl_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_hkl_almacen` FOREIGN KEY (`almacen_origen_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hkl_lavanderia` FOREIGN KEY (`ubicacion_lavanderia_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_hkl_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hkl_estado` (`estado`),
    INDEX `idx_hkl_fecha` (`fecha_despacho`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Lotes de despacho y retorno de lencería textil con lavandería';

-- ----------------------------------------------------------------------------
-- 89. Líneas de Lote de Lavandería con Diferencias Explícitas (HOUSEKEEPING-1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `housekeeping_lote_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lote_id` BIGINT UNSIGNED NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL COMMENT 'Prenda textil (LENCERIA_BLANCOS)',
    `cantidad_enviada` DECIMAL(15,4) NOT NULL,
    `cantidad_recibida` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_baja_merma` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_diferencia` DECIMAL(15,4) GENERATED ALWAYS AS (
        `cantidad_enviada` - (`cantidad_recibida` + `cantidad_baja_merma`)
    ) VIRTUAL COMMENT 'Discrepancia física explícita preservada',
    `observaciones` VARCHAR(255) NULL,
    CONSTRAINT `chk_hkl_cant_enviada_positiva` CHECK (`cantidad_enviada` > 0),
    CONSTRAINT `chk_hkl_cant_recibida_no_neg` CHECK (`cantidad_recibida` >= 0),
    CONSTRAINT `chk_hkl_cant_baja_no_neg` CHECK (`cantidad_baja_merma` >= 0),
    CONSTRAINT `fk_hkll_lote` FOREIGN KEY (`lote_id`) REFERENCES `housekeeping_lotes_lavanderia` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_hkll_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT,
    INDEX `idx_hkll_lote` (`lote_id`),
    INDEX `idx_hkll_articulo` (`articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle de prendas textiles por lote de lavandería con cálculo de diferencias';

-- ----------------------------------------------------------------------------
-- Semillas: Plantilla Estándar Canónica de Inspección
-- ----------------------------------------------------------------------------
INSERT INTO `housekeeping_plantillas_checklist` (`codigo`, `nombre`, `tipo_tarea`, `version`, `es_activa`)
VALUES ('CHK_ESTANDAR_HOTEL', 'Checklist Estándar de Inspección Hotelera', 'TODAS', 1, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

SET @plantilla_id = (SELECT `id` FROM `housekeeping_plantillas_checklist` WHERE `codigo` = 'CHK_ESTANDAR_HOTEL' LIMIT 1);

INSERT INTO `housekeeping_plantilla_items` (`plantilla_id`, `categoria`, `codigo_item`, `descripcion`, `es_critico`, `orden`)
VALUES
(@plantilla_id, 'BANO', 'BANO_INODORO', 'Inodoro higienizado, descalcificado y con precinto', 1, 1),
(@plantilla_id, 'BANO', 'BANO_DUCHA', 'Ducha/Tina libre de sarro, cabellos y mampara seca', 1, 2),
(@plantilla_id, 'BANO', 'BANO_TOALLAS', 'Juego de toallas completo según estándar (cuerpo, mano, piso)', 1, 3),
(@plantilla_id, 'BANO', 'BANO_AMENITIES', 'Amenities de baño completos y repuestos', 0, 4),
(@plantilla_id, 'DORMITORIO', 'CAMA_SABANAS', 'Sábanas y fundas limpias, tensadas sin arrugas ni manchas', 1, 5),
(@plantilla_id, 'DORMITORIO', 'CAMA_ALMOHADAS', 'Almohadas alineadas y cubrecama estirado', 0, 6),
(@plantilla_id, 'SUPERFICIES', 'SUPERF_POLVO', 'Muebles, rodapiés y molduras libres de polvo', 0, 7),
(@plantilla_id, 'SUPERFICIES', 'PISO_LIMPIO', 'Piso aspirado y trapeado sin olores residuales', 1, 8),
(@plantilla_id, 'EQUIPAMIENTO', 'EQUIP_CLIMA_LUCES', 'Aire acondicionado/calefacción y luces operativas', 0, 9),
(@plantilla_id, 'EQUIPAMIENTO', 'EQUIP_CONTROL_TV', 'Control remoto de TV sanitizado y con funda', 0, 10)
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`), `es_critico` = VALUES(`es_critico`);

-- ----------------------------------------------------------------------------
-- Inicialización de Estado de Limpieza para Unidades Existentes
-- (Se inicializan en LIMPIA_INSPECCIONADA para preservar el funcionamiento existente)
-- ----------------------------------------------------------------------------
INSERT INTO `housekeeping_unidades_limpieza` (`unidad_id`, `estado_limpieza`, `ultima_inspeccion_en`)
SELECT u.`id`, 'LIMPIA_INSPECCIONADA', NOW()
FROM `unidades` u
ON DUPLICATE KEY UPDATE `actualizado_en` = NOW();

-- ----------------------------------------------------------------------------
-- Permisos RBAC para Housekeeping
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('housekeeping.ver', 'Ver módulo de Housekeeping', 'HOUSEKEEPING', 'Acceso al rack de pisos, tablero operativo y listados de tareas', NOW()),
('housekeeping.tareas.gestionar', 'Gestionar tareas de limpieza', 'HOUSEKEEPING', 'Crear, asignar, cancelar y reprogramar tareas de limpieza', NOW()),
('housekeeping.limpieza.ejecutar', 'Ejecutar labores de limpieza', 'HOUSEKEEPING', 'Iniciar limpieza, registrar amenities y enviar a inspección', NOW()),
('housekeeping.inspeccion.ejecutar', 'Inspeccionar y calificar habitaciones', 'HOUSEKEEPING', 'Aprobar o rechazar checklists de inspección de habitaciones', NOW()),
('housekeeping.lavanderia.gestionar', 'Gestionar lencería y lavandería', 'HOUSEKEEPING', 'Despachar y recibir lotes de lencería con lavandería interna/externa', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación de permisos a SUPERADMINISTRADOR (rol 1) y ADMINISTRADOR (rol 2)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'housekeeping.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'housekeeping.%';

-- ----------------------------------------------------------------------------
-- Opción de Menú Alina (Bajo Menú Reservas / Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'housekeeping_tablero', 'Housekeeping / Pisos', 'fa-solid fa-broom', '/housekeeping', 11, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'housekeeping.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
