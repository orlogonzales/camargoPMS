-- ============================================================================
-- CAMARGO PMS — Migración 026: Módulo de Gastos Operativos, Egresos Administrativos y Tesorería
-- Gobernanza: D-086 (GATE GASTOS-1)
-- Axioma: GASTO ≠ COMPRA ≠ CxP ≠ PAGO ≠ MOVIMIENTO DE TESORERÍA
-- Regla de Oro: GASTOS-1 NO CREA UNA SEGUNDA TESORERÍA NI UNA SEGUNDA CxP
-- Tablas:
--   1. gasto_categorias           (Catálogo maestro de clasificación de gastos)
--   2. gastos                     (Hecho económico soberano y centro de costo)
--   3. gasto_evidencias           (Soporte documental N evidencias por gasto)
--   4. pagos_egreso               (Extensión soberana de FINANCIERO-2 para egresos)
--   5. gasto_aplicaciones_pago    (Imputación formal M:N entre pago y gasto)
--   6. gasto_historial_estados    (Auditoría append-only D-061)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Maestro de Categorías de Gasto
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_categorias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único, ej. SERV_ELECTRICIDAD',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `requiere_comprobante_fiscal` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si exige comprobante formal SUNAT, 0 si admite recibo interno/DJ',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gcat_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_gcat_nombre_no_vacio` CHECK (`nombre` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de categorías operativas de gasto';

-- ----------------------------------------------------------------------------
-- 2. Hecho Económico Soberano: Gastos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gastos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato GST-YYYYMM-XXXX',
    `categoria_id` BIGINT UNSIGNED NOT NULL,
    `ambito` ENUM('CORPORATIVO', 'PROPIEDAD', 'UNIDAD') NOT NULL DEFAULT 'PROPIEDAD',
    `propiedad_id` BIGINT UNSIGNED NULL,
    `unidad_id` BIGINT UNSIGNED NULL,
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional a tabla maestros proveedores',
    `acreedor_nombre` VARCHAR(200) NOT NULL COMMENT 'Nombre o razón social del acreedor/beneficiario',
    `acreedor_documento` VARCHAR(30) NULL COMMENT 'RUC, DNI o documento del acreedor',
    `descripcion_concepto` VARCHAR(255) NOT NULL,
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'RECIBO_SERVICIO_PUBLICO', 'DECLARACION_JURADA_CAJA_CHICA', 'TICKET_MAQUINA', 'OTRO_NO_TRIBUTARIO') NOT NULL,
    `comprobante_serie` VARCHAR(10) NULL,
    `comprobante_numero` VARCHAR(20) NULL,
    `fecha_emision` DATE NOT NULL,
    `fecha_vencimiento` DATE NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN' COMMENT 'Moneda funcional oficial estricta',
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `impuestos` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(15,2) NOT NULL,
    `estado` ENUM('BORRADOR', 'REGISTRADO', 'APROBADO', 'ANULADO') NOT NULL DEFAULT 'REGISTRADO',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gst_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_gst_concepto_no_vacio` CHECK (`descripcion_concepto` <> ''),
    CONSTRAINT `chk_gst_acreedor_no_vacio` CHECK (`acreedor_nombre` <> ''),
    CONSTRAINT `chk_gst_total_positivo` CHECK (`total` >= 0),
    CONSTRAINT `chk_gst_coherencia_ambito` CHECK (
        (`ambito` = 'CORPORATIVO' AND `propiedad_id` IS NULL AND `unidad_id` IS NULL) OR
        (`ambito` = 'PROPIEDAD' AND `propiedad_id` IS NOT NULL AND `unidad_id` IS NULL) OR
        (`ambito` = 'UNIDAD' AND `propiedad_id` IS NOT NULL AND `unidad_id` IS NOT NULL)
    ),
    CONSTRAINT `fk_gst_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `gasto_categorias` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gst_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gst_ambito` (`ambito`, `propiedad_id`, `unidad_id`),
    INDEX `idx_gst_estado` (`estado`),
    INDEX `idx_gst_fechas` (`fecha_emision`, `fecha_vencimiento`),
    INDEX `idx_gst_acreedor` (`acreedor_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Hecho económico soberano de gasto y centro de costo';

-- ----------------------------------------------------------------------------
-- 3. Evidencias Múltiples de Sustento Documental (N archivos por gasto)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_evidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `tipo_evidencia` ENUM('COMPROBANTE_FISCAL', 'VOUCHER_PAGO', 'FOTO_BIEN_REPARADO', 'INFORME_TECNICO', 'OTRO') NOT NULL,
    `nombre_original` VARCHAR(255) NOT NULL,
    `ruta_archivo` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `tamano_bytes` INT UNSIGNED NOT NULL,
    `hash_sha256` CHAR(64) NOT NULL,
    `subido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gevid_nombre_no_vacio` CHECK (`nombre_original` <> ''),
    CONSTRAINT `chk_gevid_ruta_no_vacia` CHECK (`ruta_archivo` <> ''),
    CONSTRAINT `chk_gevid_tamano_positivo` CHECK (`tamano_bytes` > 0),
    CONSTRAINT `fk_gevid_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gevid_actor` FOREIGN KEY (`subido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gevid_gasto` (`gasto_id`),
    INDEX `idx_gevid_hash` (`hash_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Evidencias y comprobantes digitalizados vinculados al gasto';

-- ----------------------------------------------------------------------------
-- 4. Extensión Soberana de Tesorería: Pagos de Egreso (FINANCIERO-2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos_egreso` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato PAG-EGR-YYYYMM-XXXX',
    `metodo_pago_id` INT UNSIGNED NOT NULL,
    `monto_total` DECIMAL(15,2) NOT NULL,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si método es EFECTIVO',
    `movimiento_caja_id` BIGINT UNSIGNED NULL COMMENT 'Enlace directo al libro mayor de caja',
    `cuenta_bancaria_id` INT UNSIGNED NULL COMMENT 'Obligatorio si método es TRANSFERENCIA/BANCO',
    `movimiento_bancario_id` BIGINT UNSIGNED NULL COMMENT 'Enlace directo al libro mayor bancario',
    `referencia_operacion` VARCHAR(100) NULL,
    `estado` ENUM('CONFIRMADO', 'REVERSADO') NOT NULL DEFAULT 'CONFIRMADO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `registrado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_pegr_monto_positivo` CHECK (`monto_total` > 0),
    CONSTRAINT `chk_pegr_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_pegr_metodo` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_sesion` FOREIGN KEY (`sesion_caja_id`) REFERENCES `sesiones_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_mov_caja` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_cuenta_banc` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_mov_banc` FOREIGN KEY (`movimiento_bancario_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_registrador` FOREIGN KEY (`registrado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pegr_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_pegr_estado` (`estado`),
    INDEX `idx_pegr_sesion` (`sesion_caja_id`),
    INDEX `idx_pegr_banco` (`cuenta_bancaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Pagos soberanos de egreso en tesorería';

-- ----------------------------------------------------------------------------
-- 5. Imputación / Aplicación de Pagos a Gastos (M:N)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_aplicaciones_pago` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Formato APL-GST-YYYYMM-XXXX',
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `pago_egreso_id` BIGINT UNSIGNED NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `estado` ENUM('ACTIVO', 'REVERSADO') NOT NULL DEFAULT 'ACTIVO',
    `motivo_reverso` VARCHAR(255) NULL,
    `reversado_en` DATETIME NULL,
    `reversado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_gapp_monto_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `chk_gapp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_gapp_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gapp_pago` FOREIGN KEY (`pago_egreso_id`) REFERENCES `pagos_egreso` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gapp_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_gapp_reversor` FOREIGN KEY (`reversado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_gapp_gasto_estado` (`gasto_id`, `estado`),
    INDEX `idx_gapp_pago_estado` (`pago_egreso_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Imputación formal entre pagos de egreso y gastos';

-- ----------------------------------------------------------------------------
-- 6. Auditoría Append-Only D-061
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gasto_historial_estados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gasto_id` BIGINT UNSIGNED NOT NULL,
    `estado_anterior` VARCHAR(30) NULL,
    `estado_nuevo` VARCHAR(30) NOT NULL,
    `motivo` TEXT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `correlation_id` VARCHAR(100) NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ghist_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ghist_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_ghist_gasto` (`gasto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial append-only de cambios de estado en gastos';

-- ----------------------------------------------------------------------------
-- Semillas: Categorías Operativas de Gasto Iniciales
-- ----------------------------------------------------------------------------
INSERT INTO `gasto_categorias` (`codigo`, `nombre`, `descripcion`, `requiere_comprobante_fiscal`, `activo`) VALUES
('SERV_ELECTRICIDAD', 'Servicios de Energía Eléctrica', 'Facturación mensual de electricidad para predios y oficinas', 1, 1),
('SERV_AGUA', 'Servicios de Agua Potable y Alcantarillado', 'Facturación mensual de agua potable y saneamiento', 1, 1),
('SERV_TELECOM', 'Telecomunicaciones e Internet', 'Conexiones de fibra óptica, telefonía fija/móvil y televisión', 1, 1),
('MANT_MENOR', 'Mantenimiento y Reparaciones Urgentes', 'Reparaciones menores in-situ de gasfitería, cerrajería, vidriería y electricidad', 0, 1),
('HONORARIOS_PROF', 'Honorarios y Asesorías Profesionales', 'Servicios contables, legales, tributarios e informáticos externos con RxH', 1, 1),
('TRIBUTOS_TASAS', 'Tributos y Arbitrios Municipales', 'Arbitrios municipales de predios, licencias y tasas gubernamentales', 1, 1),
('TRANSPORTE_VIATICOS', 'Movilidad y Viáticos Operativos', 'Pasajes locales, combustible y viáticos para personal operativo', 0, 1),
('COMISIONES_BANCARIAS', 'Comisiones y Portes Bancarios', 'Mantenimiento de cuentas bancarias y portes no deducibles en POS', 1, 1),
('SUMINISTROS_DIRECTOS', 'Suministros de Consumo Directo', 'Café, azúcar, artículos menores de oficina y limpieza para uso inmediato', 0, 1),
('OTROS_OPERATIVOS', 'Otros Gastos Operativos Diversos', 'Erogaciones operativas no clasificadas en otras categorías', 0, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- Permisos RBAC para Gastos y Egresos
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('gastos.ver', 'Ver módulo de gastos y egresos', 'GASTOS', 'Acceso al listado de gastos, detalles y reportes de erogaciones', NOW()),
('gastos.crear', 'Registrar nuevos gastos', 'GASTOS', 'Permite crear gastos en estado borrador o registrado con sus evidencias', NOW()),
('gastos.aprobar', 'Aprobar gastos', 'GASTOS', 'Autorizar erogaciones para habilitar su desembolso y pago', NOW()),
('gastos.anular', 'Anular gastos', 'GASTOS', 'Permite anular gastos que no cuenten con aplicaciones de pago activas', NOW()),
('gastos.pagar', 'Pagar y aplicar egresos', 'GASTOS', 'Emitir pagos de egreso en efectivo (caja chica) o banco y aplicarlos a gastos', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación a SUPERADMINISTRADOR (1) y ADMINISTRADOR (2)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'gastos.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'gastos.%';

-- ----------------------------------------------------------------------------
-- Opción de Menú Alina (Bajo Operaciones / Reservas id 42)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'gastos_modulo', 'Gastos y Egresos', 'fa-solid fa-file-invoice-dollar', '/gastos', 14, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'gastos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
