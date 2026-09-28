-- ============================================================================
-- Camargo PMS — Migración 023: Suministros, Medidores, Lecturas,
-- Tarifas con Vigencia Histórica y Liquidaciones a Folios (SUMINISTROS-1 / D-081)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Maestro de Suministros y Servicios Periódicos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministros` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Identificador único en mayúsculas, ej. LUZ_ELECTRICA, AGUA_POTABLE, INTERNET_FIBRA',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Denominación descriptiva para vistas y reportes',
    `modalidad` ENUM('MEDIDO', 'FIJO_PERIODICO') NOT NULL COMMENT 'MEDIDO exige medidor y lecturas, FIJO_PERIODICO opera por cuota mensual o periódica',
    `unidad_medida` VARCHAR(20) NOT NULL COMMENT 'kWh, m3, MES, etc.',
    `descripcion` TEXT NULL,
    `permite_rollover` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el medidor asociado admite reinicio de dial con valor máximo, 0 rechaza cualquier lectura decreciente',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_sum_codigo` (`codigo`),
    CONSTRAINT `chk_sum_codigo_no_vacio` CHECK (`codigo` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de suministros y servicios periódicos';

-- ----------------------------------------------------------------------------
-- 2. Tarifas con Vigencia Histórica y Precedencia Jerárquica
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_tarifas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `suministro_id` BIGINT UNSIGNED NOT NULL,
    `ambito_tipo` ENUM('GLOBAL', 'PROPIEDAD', 'UNIDAD') NOT NULL DEFAULT 'GLOBAL',
    `propiedad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si ambito_tipo es PROPIEDAD o UNIDAD',
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si ambito_tipo es UNIDAD',
    `vigencia_desde` DATE NOT NULL COMMENT 'Fecha inicial de vigencia (inclusiva)',
    `vigencia_hasta` DATE NULL COMMENT 'Fecha final de vigencia (inclusiva). NULL indica vigente indefinidamente',
    `valor_unitario` DECIMAL(15,4) NOT NULL COMMENT 'Costo unitario por kWh, m3 o cuota fija en moneda funcional',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_star_suministro_vigencia` (`suministro_id`, `vigencia_desde`, `vigencia_hasta`),
    KEY `idx_star_ambito` (`ambito_tipo`, `propiedad_id`, `unidad_id`),
    CONSTRAINT `fk_star_suministro` FOREIGN KEY (`suministro_id`) REFERENCES `suministros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_star_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_star_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_star_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_star_vigencia` CHECK (`vigencia_hasta` IS NULL OR `vigencia_hasta` >= `vigencia_desde`),
    CONSTRAINT `chk_star_valor_positivo` CHECK (`valor_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tarifas de suministros con vigencia histórica y ámbito jerárquico';

-- ----------------------------------------------------------------------------
-- 3. Medidores Físicos e Historial de Reemplazos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_medidores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `suministro_id` BIGINT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si es medidor general del edificio o inmueble',
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código de placa, serie o identificación física',
    `marca` VARCHAR(100) NULL,
    `modelo` VARCHAR(100) NULL,
    `fecha_instalacion` DATE NOT NULL,
    `fecha_retiro` DATE NULL,
    `lectura_inicial` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `capacidad_maxima` DECIMAL(15,4) NULL COMMENT 'Capacidad máxima de dial para rollover (ej. 99999.9999)',
    `estado` ENUM('ACTIVO', 'EN_MANTENIMIENTO', 'REEMPLAZADO', 'DE_BAJA') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual para impedir que una unidad tenga 2 medidores activos del mismo suministro simultáneamente
    `medidor_activo_idx` VARCHAR(80) GENERATED ALWAYS AS (
        IF(`estado` = 'ACTIVO' AND `unidad_id` IS NOT NULL, CONCAT(`suministro_id`, '_', `unidad_id`), NULL)
    ) STORED,
    UNIQUE KEY `uq_med_propiedad_codigo` (`propiedad_id`, `codigo`),
    UNIQUE KEY `uq_med_unidad_suministro_activo` (`medidor_activo_idx`),
    KEY `idx_med_suministro_unidad` (`suministro_id`, `unidad_id`),
    CONSTRAINT `fk_med_suministro` FOREIGN KEY (`suministro_id`) REFERENCES `suministros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_med_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_med_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_med_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_med_fechas` CHECK (`fecha_retiro` IS NULL OR `fecha_retiro` >= `fecha_instalacion`),
    CONSTRAINT `chk_med_lectura_inicial` CHECK (`lectura_inicial` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Medidores físicos instalados en unidades o áreas comunes';

-- ----------------------------------------------------------------------------
-- 4. Historial Append-Only Inmutable de Lecturas de Medidores
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_lecturas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `medidor_id` BIGINT UNSIGNED NOT NULL,
    `fecha_lectura` DATE NOT NULL,
    `hora_lectura` TIME NULL,
    `valor_lectura` DECIMAL(15,4) NOT NULL,
    `tipo_evento` ENUM('ORDINARIA', 'CORTE_CONTRACTUAL', 'CORTE_TARIFARIO', 'INSTALACION', 'RETIRO', 'REINICIO_DIAL', 'CORRECCION') NOT NULL DEFAULT 'ORDINARIA',
    `lectura_referencia_id` BIGINT UNSIGNED NULL COMMENT 'Lectura original sustituida cuando tipo_evento = CORRECCION',
    `motivo` VARCHAR(255) NULL,
    `arrendamiento_id` BIGINT UNSIGNED NULL COMMENT 'Vinculación si fue tomada como corte de un contrato específico',
    `estado` ENUM('VALIDA', 'CORREGIDA', 'ANULADA') NOT NULL DEFAULT 'VALIDA',
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Columna virtual para impedir bifurcaciones (no dos correcciones vigentes para la misma lectura original)
    `correccion_activa_idx` VARCHAR(50) GENERATED ALWAYS AS (
        IF(`estado` = 'VALIDA' AND `tipo_evento` = 'CORRECCION', CAST(`lectura_referencia_id` AS CHAR), NULL)
    ) STORED,
    UNIQUE KEY `uq_lec_correccion_unica` (`correccion_activa_idx`),
    KEY `idx_lec_medidor_fecha` (`medidor_id`, `fecha_lectura`, `id`),
    CONSTRAINT `fk_slec_medidor` FOREIGN KEY (`medidor_id`) REFERENCES `suministro_medidores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slec_referencia` FOREIGN KEY (`lectura_referencia_id`) REFERENCES `suministro_lecturas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slec_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slec_actor_creador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_slec_valor_no_negativo` CHECK (`valor_lectura` >= 0),
    CONSTRAINT `chk_slec_motivo_correccion` CHECK (`tipo_evento` <> 'CORRECCION' OR (`lectura_referencia_id` IS NOT NULL AND `motivo` IS NOT NULL AND `motivo` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro inmutable append-only de lecturas físicas de medidores';

-- ----------------------------------------------------------------------------
-- 5. Liquidaciones de Suministro por Arrendamiento y Período
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_liquidaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `folio` VARCHAR(30) NOT NULL,
    `suministro_id` BIGINT UNSIGNED NOT NULL,
    `modalidad` ENUM('MEDIDO', 'FIJO_PERIODICO') NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NOT NULL,
    `unidad_id` BIGINT UNSIGNED NOT NULL,
    `arrendamiento_id` BIGINT UNSIGNED NOT NULL,
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `cargo_cuenta_id` BIGINT UNSIGNED NOT NULL,
    `periodo_anio` SMALLINT UNSIGNED NOT NULL,
    `periodo_mes` TINYINT UNSIGNED NOT NULL,
    `periodo_desde` DATE NOT NULL,
    `periodo_hasta` DATE NOT NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `cantidad_total` DECIMAL(15,4) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `revision` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `liquidacion_previa_id` BIGINT UNSIGNED NULL,
    `estado` ENUM('DEVENGADO', 'ANULADO') NOT NULL DEFAULT 'DEVENGADO',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Columna virtual para impedir dobles liquidaciones activas en el mismo período exacto
    `liquidacion_activa_idx` VARCHAR(120) GENERATED ALWAYS AS (
        IF(`estado` = 'DEVENGADO', CONCAT(`arrendamiento_id`, '_', `suministro_id`, '_', `periodo_desde`, '_', `periodo_hasta`), NULL)
    ) STORED,
    UNIQUE KEY `uq_sliq_folio` (`folio`),
    UNIQUE KEY `uq_sliq_periodo_activo` (`liquidacion_activa_idx`),
    UNIQUE KEY `uq_sliq_cargo_cuenta` (`cargo_cuenta_id`),
    KEY `idx_sliq_arrendamiento` (`arrendamiento_id`, `estado`),
    KEY `idx_sliq_cuenta_folio` (`cuenta_folio_id`),
    CONSTRAINT `fk_sliq_suministro` FOREIGN KEY (`suministro_id`) REFERENCES `suministros` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_cargo_cuenta` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_previa` FOREIGN KEY (`liquidacion_previa_id`) REFERENCES `suministro_liquidaciones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sliq_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_sliq_periodo` CHECK (`periodo_hasta` > `periodo_desde`),
    CONSTRAINT `chk_sliq_total_positivo` CHECK (`total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Liquidaciones de suministros imputadas a contratos de arrendamiento';

-- ----------------------------------------------------------------------------
-- 6. Tramos de Liquidación de Suministros (Detalle Multitramo)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suministro_liquidacion_tramos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `liquidacion_id` BIGINT UNSIGNED NOT NULL,
    `numero_tramo` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `medidor_id` BIGINT UNSIGNED NULL,
    `lectura_anterior_id` BIGINT UNSIGNED NULL,
    `lectura_actual_id` BIGINT UNSIGNED NULL,
    `lectura_anterior_valor` DECIMAL(15,4) NULL,
    `lectura_actual_valor` DECIMAL(15,4) NULL,
    `cantidad` DECIMAL(15,4) NOT NULL,
    `tarifa_id` BIGINT UNSIGNED NOT NULL,
    `tarifa_valor` DECIMAL(15,4) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `impuesto_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `fecha_desde` DATE NOT NULL,
    `fecha_hasta` DATE NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_slt_liq_tramo` (`liquidacion_id`, `numero_tramo`),
    CONSTRAINT `fk_slt_liquidacion` FOREIGN KEY (`liquidacion_id`) REFERENCES `suministro_liquidaciones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_medidor` FOREIGN KEY (`medidor_id`) REFERENCES `suministro_medidores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_lec_ant` FOREIGN KEY (`lectura_anterior_id`) REFERENCES `suministro_lecturas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_lec_act` FOREIGN KEY (`lectura_actual_id`) REFERENCES `suministro_lecturas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_slt_tarifa` FOREIGN KEY (`tarifa_id`) REFERENCES `suministro_tarifas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_slt_cantidad_positiva` CHECK (`cantidad` >= 0),
    CONSTRAINT `chk_slt_total_positivo` CHECK (`total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tramos de consumo y tarifas aplicadas en una liquidación';

-- ----------------------------------------------------------------------------
-- 7. Ampliación del ENUM origen_tipo en cargos_cuenta (FINANCIERO-2)
-- ----------------------------------------------------------------------------
ALTER TABLE `cargos_cuenta`
    MODIFY COLUMN `origen_tipo` ENUM(
        'ALOJAMIENTO_NOCHES',
        'SERVICIO_CONTRATADO',
        'PENALIDAD',
        'AJUSTE_MANUAL',
        'RENTA_ARRENDAMIENTO',
        'DEPOSITO_GARANTIA',
        'SUMINISTRO_CONSUMO',
        'SUMINISTRO_CUOTA_FIJA',
        'AJUSTE_SUMINISTRO'
    ) NOT NULL;

-- ----------------------------------------------------------------------------
-- 8. Permisos RBAC de Suministros
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('suministros.ver', 'Ver suministros', 'SUMINISTROS', 'Permite consultar suministros, medidores, tarifas y liquidaciones', NOW()),
('suministros.gestionar', 'Gestionar suministros', 'SUMINISTROS', 'Permite crear y editar suministros y parámetros del catálogo', NOW()),
('suministros.tarifas.gestionar', 'Gestionar tarifas', 'SUMINISTROS', 'Permite definir tarifas con vigencia y ámbitos jerárquicos', NOW()),
('suministros.medidores.gestionar', 'Gestionar medidores', 'SUMINISTROS', 'Permite instalar, reemplazar y dar de baja medidores físicos', NOW()),
('suministros.lecturas.registrar', 'Registrar lecturas', 'SUMINISTROS', 'Permite ingresar lecturas periódicas, de corte e instalación', NOW()),
('suministros.lecturas.corregir', 'Corregir lecturas', 'SUMINISTROS', 'Permite emitir correcciones auditadas sobre lecturas previas', NOW()),
('suministros.liquidar', 'Liquidar suministros', 'SUMINISTROS', 'Permite computar consumos periódicos y devengar cargos en folios', NOW()),
('suministros.anular', 'Anular liquidaciones', 'SUMINISTROS', 'Permite anular liquidaciones y sus cargos asociados de forma auditada', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Asignar automáticamente permisos de suministros a SUPERADMINISTRADOR (1) y ADMINISTRADOR (2)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'suministros.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'suministros.%';

-- ----------------------------------------------------------------------------
-- 9. Opciones de Menú Dinámico
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'suministros', 'Suministros', 'fa-solid fa-bolt', '/suministros', 10, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'suministros.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 10. Semillas de Suministros Frecuentes
-- ----------------------------------------------------------------------------
INSERT INTO `suministros` (`codigo`, `nombre`, `modalidad`, `unidad_medida`, `descripcion`, `permite_rollover`, `estado`) VALUES
('LUZ_ELECTRICA', 'Energía Eléctrica', 'MEDIDO', 'kWh', 'Consumo eléctrico individual por medidor físico', 0, 'ACTIVO'),
('AGUA_POTABLE', 'Agua Potable', 'MEDIDO', 'm3', 'Consumo de agua medido por flujómetro', 0, 'ACTIVO'),
('INTERNET_FIBRA', 'Internet Dedicado Fibra', 'FIJO_PERIODICO', 'MES', 'Servicio recurrente mensual de conectividad de alta velocidad', 0, 'ACTIVO'),
('MANTENIMIENTO_COMUN', 'Cuota de Mantenimiento Común', 'FIJO_PERIODICO', 'MES', 'Cuota periódica para áreas comunes y vigilancia', 0, 'ACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `modalidad` = VALUES(`modalidad`),
    `unidad_medida` = VALUES(`unidad_medida`),
    `descripcion` = VALUES(`descripcion`);

SET FOREIGN_KEY_CHECKS = 1;
