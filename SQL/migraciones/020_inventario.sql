-- ============================================================================
-- CAMARGO PMS - MIGRACIÓN 020
-- Inventario Físico, Ubicaciones Polimórficas, Existencias, Kardex Inmutable,
-- Activos Serializables y Dotaciones de Unidades
-- Fase: INVENTARIO-1 (Decisión D-078)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Unidades de Medida Normalizadas
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_unidades_medida` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'UND, PAR, JGO, LT, KG, MTR, ROLLO, CAJA, PQTE',
    `nombre` VARCHAR(60) NOT NULL,
    `simbolo` VARCHAR(10) NOT NULL,
    `admite_decimales` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si admite fraccionamiento (ej: KG, LT, MTR)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ium_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_ium_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_ium_simbolo_no_vacio` CHECK (`simbolo` <> ''),
    UNIQUE KEY `uq_ium_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo normalizado de unidades de medida para inventario';

-- Semillas canónicas de unidades de medida
INSERT INTO `inventario_unidades_medida` (`codigo`, `nombre`, `simbolo`, `admite_decimales`) VALUES
('UND', 'Unidad', 'und', 0),
('PAR', 'Par', 'par', 0),
('JGO', 'Juego', 'jgo', 0),
('LT', 'Litro', 'L', 1),
('KG', 'Kilogramo', 'kg', 1),
('MTR', 'Metro', 'm', 1),
('ROLLO', 'Rollo', 'rll', 0),
('CAJA', 'Caja', 'cj', 0),
('PQTE', 'Paquete', 'paq', 0)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `simbolo` = VALUES(`simbolo`), `admite_decimales` = VALUES(`admite_decimales`);

-- ----------------------------------------------------------------------------
-- 2. Ubicaciones Polimórficas de Inventario
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_ubicaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'UBI-BOD-01, UBI-HAB-101, UBI-LAV-01',
    `nombre` VARCHAR(100) NOT NULL,
    `tipo` ENUM('ALMACEN', 'UNIDAD', 'CUSTODIA_EXTERNA') NOT NULL DEFAULT 'ALMACEN',
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si tipo = UNIDAD, NULL en otros tipos',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor externo si tipo = CUSTODIA_EXTERNA (ej: lavandería/taller)',
    `responsable_colaborador_id` BIGINT UNSIGNED NULL COMMENT 'Colaborador a cargo',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iubi_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_iubi_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_iubi_unidad_coherencia` CHECK (
        (`tipo` = 'UNIDAD' AND `unidad_id` IS NOT NULL) OR
        (`tipo` <> 'UNIDAD' AND `unidad_id` IS NULL)
    ),
    CONSTRAINT `fk_iubi_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iubi_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_iubi_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iubi_responsable` FOREIGN KEY (`responsable_colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iubi_codigo` (`codigo`),
    UNIQUE KEY `uq_iubi_unidad_id` (`unidad_id`),
    INDEX `idx_iubi_propiedad_tipo` (`propiedad_id`, `tipo`),
    INDEX `idx_iubi_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ubicaciones físicas y externas de inventario (almacenes, habitaciones, lavanderías)';

-- ----------------------------------------------------------------------------
-- 3. Catálogo de Artículos de Inventario
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_articulos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_sku` VARCHAR(30) NOT NULL COMMENT 'SKU-AMN-001, SKU-LEN-001, SKU-REP-001, SKU-ACT-001',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `categoria` ENUM(
        'CONSUMIBLE_OPERATIVO',
        'LENCERIA_BLANCOS',
        'REPUESTO_MANTENIMIENTO',
        'ACTIVO_SERIALIZABLE',
        'HERRAMIENTA',
        'OTRO'
    ) NOT NULL DEFAULT 'CONSUMIBLE_OPERATIVO',
    `unidad_medida_id` BIGINT UNSIGNED NOT NULL,
    `costo_referencial` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `stock_minimo_alerta` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iart_sku_no_vacio` CHECK (`codigo_sku` <> ''),
    CONSTRAINT `chk_iart_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_iart_costo_no_negativo` CHECK (`costo_referencial` >= 0),
    CONSTRAINT `chk_iart_stock_min_no_negativo` CHECK (`stock_minimo_alerta` >= 0),
    CONSTRAINT `fk_iart_unidad_medida` FOREIGN KEY (`unidad_medida_id`) REFERENCES `inventario_unidades_medida` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iart_sku` (`codigo_sku`),
    INDEX `idx_iart_categoria` (`categoria`),
    INDEX `idx_iart_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de artículos y bienes de inventario';

-- ----------------------------------------------------------------------------
-- 4. Existencias por Ubicación (Proyección Operacional Materializada)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_existencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_actual` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cantidad_reservada` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ie_cantidad_no_negativa` CHECK (`cantidad_actual` >= 0),
    CONSTRAINT `chk_ie_reservada_no_negativa` CHECK (`cantidad_reservada` >= 0),
    CONSTRAINT `fk_ie_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ie_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_ie_articulo_ubicacion` (`articulo_id`, `ubicacion_id`),
    INDEX `idx_ie_ubicacion` (`ubicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Proyección operacional de stock materializado por artículo y ubicación';

-- ----------------------------------------------------------------------------
-- 5. Movimientos de Inventario (Kardex Append-Only Inmutable)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_movimientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'MOV-YYYYMMDD-XXXX',
    `tipo_movimiento` ENUM(
        'SALDO_INICIAL',
        'ENTRADA_COMPRA',
        'SALIDA_CONSUMO',
        'SALIDA_MANTENIMIENTO',
        'TRASLADO_SALIDA',
        'TRASLADO_ENTRADA',
        'AJUSTE_POSITIVO',
        'AJUSTE_NEGATIVO',
        'REVERSO'
    ) NOT NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `costo_unitario_historico` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `costo_total_historico` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `referencia_tipo` VARCHAR(50) NULL COMMENT 'MANTENIMIENTO_ORDEN, TRASLADO, AJUSTE_FISICO, COMPRA, etc.',
    `referencia_id` BIGINT UNSIGNED NULL COMMENT 'ID de la entidad vinculada',
    `movimiento_referencia_id` BIGINT UNSIGNED NULL COMMENT 'Enlace a movimiento original en caso de REVERSO o traslado',
    `correlativo_operacion` VARCHAR(40) NULL COMMENT 'Identificador único compartido para ambas patas de un traslado',
    `motivo` VARCHAR(500) NOT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `reverso_movimiento_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `tipo_movimiento` = 'REVERSO' THEN `movimiento_referencia_id` ELSE NULL END
    ) VIRTUAL COMMENT 'Garantiza a nivel de motor MySQL que un movimiento no pueda ser neutralizado por más de un REVERSO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_imov_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_imov_cantidad_positiva` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_imov_costo_unit_no_negativo` CHECK (`costo_unitario_historico` >= 0),
    CONSTRAINT `chk_imov_costo_tot_no_negativo` CHECK (`costo_total_historico` >= 0),
    CONSTRAINT `fk_imov_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_imov_mov_ref` FOREIGN KEY (`movimiento_referencia_id`) REFERENCES `inventario_movimientos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_imov_codigo` (`codigo`),
    UNIQUE KEY `uq_imov_reverso_unico` (`reverso_movimiento_id`),
    INDEX `idx_imov_articulo_ubicacion_fecha` (`articulo_id`, `ubicacion_id`, `creado_en`),
    INDEX `idx_imov_referencia` (`referencia_tipo`, `referencia_id`),
    INDEX `idx_imov_correlativo` (`correlativo_operacion`),
    INDEX `idx_imov_tipo` (`tipo_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Kardex inmutable y bitácora física append-only de inventario';

-- ----------------------------------------------------------------------------
-- 6. Activos Fijos Individuales y Serializables
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_activos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `codigo_placa` VARCHAR(40) NOT NULL COMMENT 'Placa patrimonial / código de barras único',
    `numero_serie_fabricante` VARCHAR(60) NULL,
    `marca` VARCHAR(60) NULL,
    `modelo` VARCHAR(60) NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `ubicacion_id` BIGINT UNSIGNED NOT NULL,
    `estado` ENUM('DISPONIBLE', 'ASIGNADO', 'EN_MANTENIMIENTO', 'DE_BAJA') NOT NULL DEFAULT 'DISPONIBLE',
    `fecha_adquisicion` DATE NULL,
    `costo_adquisicion` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN',
    `garantia_vence_en` DATE NULL,
    `notas` TEXT NULL,
    `motivo_baja` VARCHAR(500) NULL,
    `baja_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_iact_placa_no_vacia` CHECK (`codigo_placa` <> ''),
    CONSTRAINT `chk_iact_costo_no_negativo` CHECK (`costo_adquisicion` >= 0),
    CONSTRAINT `fk_iact_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `inventario_ubicaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_iact_baja_actor` FOREIGN KEY (`baja_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_iact_codigo_placa` (`codigo_placa`),
    INDEX `idx_iact_articulo` (`articulo_id`),
    INDEX `idx_iact_propiedad_ubicacion` (`propiedad_id`, `ubicacion_id`),
    INDEX `idx_iact_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ejemplares individuales de activos serializables (Smart TVs, frigobares, etc.)';

-- ----------------------------------------------------------------------------
-- 7. Dotaciones Estándar de Unidades (Reglamentarias / Teóricas)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventario_dotaciones_estandar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_unidad_id` INT UNSIGNED NULL,
    `unidad_id` BIGINT UNSIGNED NULL,
    `articulo_id` BIGINT UNSIGNED NOT NULL,
    `cantidad_estandar` DECIMAL(15,4) NOT NULL,
    `notas` VARCHAR(255) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_ide_destino_xor` CHECK (
        (`tipo_unidad_id` IS NOT NULL AND `unidad_id` IS NULL) OR
        (`tipo_unidad_id` IS NULL AND `unidad_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_ide_cantidad_positiva` CHECK (`cantidad_estandar` > 0),
    CONSTRAINT `fk_ide_tipo_unidad` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ide_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ide_articulo` FOREIGN KEY (`articulo_id`) REFERENCES `inventario_articulos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_ide_tipo_unidad_articulo` (`tipo_unidad_id`, `articulo_id`),
    UNIQUE KEY `uq_ide_unidad_articulo` (`unidad_id`, `articulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dotación esperada o reglamentaria por tipo de unidad o por unidad específica';

-- ----------------------------------------------------------------------------
-- 8. Permisos RBAC de Inventario
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('inventario.ver', 'Ver panel y existencias de inventario', 'Consultar artículos, existencias, kardex, activos y dotaciones', 'inventario', 'ACTIVO', 1),
('inventario.articulos.gestionar', 'Gestionar catálogo de artículos', 'Crear y editar artículos y unidades de medida', 'inventario', 'ACTIVO', 1),
('inventario.ubicaciones.gestionar', 'Gestionar almacenes y ubicaciones', 'Crear y administrar almacenes, bodegas y ubicaciones', 'inventario', 'ACTIVO', 1),
('inventario.movimientos.registrar', 'Registrar movimientos de inventario', 'Registrar entradas, consumos, mermas y ajustes de stock', 'inventario', 'ACTIVO', 1),
('inventario.traslados.ejecutar', 'Ejecutar traslados entre ubicaciones', 'Transferir stock entre almacenes, unidades y custodias externas', 'inventario', 'ACTIVO', 1),
('inventario.activos.gestionar', 'Gestionar activos serializables', 'Registrar, asignar, transferir y dar de baja activos fijos', 'inventario', 'ACTIVO', 1),
('inventario.dotaciones.gestionar', 'Gestionar dotaciones estándar', 'Configurar dotaciones reglamentarias de unidades', 'inventario', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación de permisos al rol SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo LIKE 'inventario.%'
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

-- ----------------------------------------------------------------------------
-- 9. Opción de Menú Dinámico bajo 'reservas' (Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'inventario_catalogo', 'Inventario', 'fa-solid fa-boxes-stacked', '/inventario', 7, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'inventario.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
