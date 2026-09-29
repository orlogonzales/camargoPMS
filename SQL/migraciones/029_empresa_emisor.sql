-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 029: MAESTRO DE EMPRESA / EMISOR LEGAL (EMPRESA-1)
-- ============================================================================
-- Principios vinculantes:
-- 1. EMPRESA/EMISOR != PROPIEDAD != UNIDAD (Decisión D-091).
-- 2. Multiempresa extensible (1..N empresas) sin sobrediseño ni SaaS multitenancy.
-- 3. Empresa (1) <---> (N) Propiedades mediante FK directa nullable en propiedades.
-- 4. Representante legal reutiliza el registro central de personas (personas.id).
-- 5. Catálogo RUC agregado a tipos_documento para identificación tributaria peruana.
-- 6. Trazabilidad completa mediante RBAC y auditoría append-only.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Asegurar tipo de documento RUC en el catálogo de documentos
INSERT INTO `tipos_documento` (
    `codigo`, `nombre`, `descripcion`, `longitud_exacta`, `longitud_minima`,
    `longitud_maxima`, `formato_regex`, `pais_fijo_id`, `pais_emisor_obligatorio`, `activo`
) VALUES (
    'RUC', 'Registro Único de Contribuyentes',
    'Documento de identificación tributaria para personas jurídicas y naturales con negocio en Perú',
    11, 11, 11, '^[0-9]{11}$', 1, 0, 1
) ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `activo` = 1;

-- 2. Tabla maestra de Empresas Operadoras y Emisores Legales
CREATE TABLE IF NOT EXISTS `empresas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código técnico único, ej. EMP-001 o CAMARGO-HOSTELERIA',
    `tipo_documento_id` INT UNSIGNED NOT NULL COMMENT 'FK a tipos_documento (ej. RUC)',
    `numero_documento` VARCHAR(30) NOT NULL COMMENT 'Número de identificación fiscal (ej. RUC de 11 dígitos)',
    `razon_social` VARCHAR(255) NOT NULL COMMENT 'Razón social formal legal',
    `nombre_comercial` VARCHAR(255) NULL COMMENT 'Nombre comercial / marca para difusión y branding',
    `direccion_fiscal` VARCHAR(255) NOT NULL COMMENT 'Domicilio fiscal formal',
    `pais_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK a paises (1 = Perú)',
    `departamento` VARCHAR(100) NULL,
    `provincia` VARCHAR(100) NULL,
    `distrito` VARCHAR(100) NULL,
    `ubigeo` VARCHAR(10) NULL COMMENT 'Código de ubigeo 6 dígitos',
    `telefono` VARCHAR(50) NULL COMMENT 'Teléfono de contacto institucional',
    `email` VARCHAR(150) NULL COMMENT 'Correo electrónico corporativo / facturación',
    `sitio_web` VARCHAR(255) NULL COMMENT 'Portal web oficial',
    `logo_url` VARCHAR(255) NULL COMMENT 'Ruta relativa del logo corporativo almacenado en storage',
    `representante_persona_id` BIGINT UNSIGNED NULL COMMENT 'FK al registro central de personas para el representante legal',
    `representante_cargo` VARCHAR(100) NULL DEFAULT 'Gerente General',
    `representante_poder_partida` VARCHAR(100) NULL COMMENT 'Partida registral / poder notarial de representación',
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 si es la empresa operadora principal/default del sistema',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_empresas_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_empresas_razon_social_no_vacia` CHECK (`razon_social` <> ''),
    CONSTRAINT `chk_empresas_num_doc_no_vacio` CHECK (`numero_documento` <> ''),
    CONSTRAINT `chk_empresas_dir_fiscal_no_vacia` CHECK (`direccion_fiscal` <> ''),
    CONSTRAINT `chk_empresas_es_principal` CHECK (`es_principal` IN (0, 1)),
    CONSTRAINT `fk_empresas_tipo_documento` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_empresas_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_empresas_representante` FOREIGN KEY (`representante_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_empresas_codigo` (`codigo`),
    UNIQUE KEY `uq_empresas_num_doc` (`numero_documento`),
    INDEX `idx_empresas_estado` (`estado`),
    INDEX `idx_empresas_principal` (`es_principal`),
    INDEX `idx_empresas_representante` (`representante_persona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro central de empresas operadoras y emisores legales';

-- 3. Extender tabla de propiedades con relación a Empresa Operadora
-- Comprobamos si la columna ya existe para idempotencia en ejecuciones repetidas
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'propiedades'
      AND COLUMN_NAME = 'empresa_id'
);

SET @sql_add_col = IF(
    @col_exists = 0,
    'ALTER TABLE `propiedades`
        ADD COLUMN `empresa_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`,
        ADD CONSTRAINT `fk_propiedades_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD INDEX `idx_propiedades_empresa` (`empresa_id`)',
    'SELECT 1'
);

PREPARE stmt_alter_prop FROM @sql_add_col;
EXECUTE stmt_alter_prop;
DEALLOCATE PREPARE stmt_alter_prop;

-- 4. Permisos RBAC para el Módulo de Empresa
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`) VALUES
('empresa.ver', 'Ver catálogo y detalle de empresas / emisores', 'empresa', 'Permite consultar el maestro y detalle de empresas y emisores legales'),
('empresa.crear', 'Crear nuevas empresas / emisores', 'empresa', 'Permite registrar nuevas entidades empresariales en el sistema'),
('empresa.editar', 'Modificar datos de empresa / emisor', 'empresa', 'Permite actualizar datos societarios, fiscales, representante y branding'),
('empresa.cambiar_estado', 'Activar o desactivar empresas', 'empresa', 'Permite cambiar el estado operativo entre ACTIVO e INACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR (rol_id = 1)
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.`id`
FROM `permisos` p
WHERE p.`modulo` = 'empresa'
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- 5. Opción de Menú en Navegación Administrativa (bajo Configuración)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    2, -- Padre: configuracion
    'config_empresa',
    'Empresa / Emisor',
    'fa-solid fa-building',
    '/empresas',
    25,
    'ACTIVO',
    p.`id`,
    0
FROM `permisos` p
WHERE p.`codigo` = 'empresa.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`);

SET FOREIGN_KEY_CHECKS = 1;
