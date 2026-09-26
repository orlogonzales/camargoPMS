-- ============================================================================
-- Camargo PMS — Migración 010: Núcleo Central de Configuración y Parámetros
-- ============================================================================
-- Define las estructuras de base de datos para la configuración funcional y
-- parámetros administrables del sistema:
--   configuraciones: Catálogo tipado y agrupado de parámetros del PMS.
--
-- Principios vinculantes:
--   CONFIGURACIÓN FUNCIONAL (BD) != ENTORNO/SECRETOS (.env).
--   CONTRATO DE TIPADO: Conversión y validación canónica en backend.
--   VALOR PREDETERMINADO != VALOR ACTUAL: Capacidad de restauración.
--   MENÚ != AUTORIZACIÓN: Opciones de navegación sujetas a permisos RBAC.
-- ============================================================================

-- 1. Nuevos Permisos RBAC para Gestión de Configuración
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('configuracion.ver', 'Ver configuraciones del sistema', 'Permite consultar los parámetros y configuraciones del sistema', 'configuracion', 'ACTIVO', 1),
('configuracion.editar', 'Modificar configuraciones del sistema', 'Permite editar y restaurar valores de configuración del sistema', 'configuracion', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- 2. Tabla de Configuraciones del Sistema
CREATE TABLE IF NOT EXISTS `configuraciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `clave` VARCHAR(100) NOT NULL,
    `grupo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `tipo` ENUM('TEXTO', 'ENTERO', 'DECIMAL', 'BOOLEANO', 'FECHA', 'HORA', 'JSON') NOT NULL DEFAULT 'TEXTO',
    `valor` LONGTEXT NULL,
    `valor_predeterminado` LONGTEXT NULL,
    `editable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `es_sensible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `orden` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_configuraciones_clave_no_vacia` CHECK (`clave` <> ''),
    CONSTRAINT `chk_configuraciones_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_configuraciones_grupo_no_vacio` CHECK (`grupo` <> ''),
    UNIQUE KEY `uq_configuraciones_clave` (`clave`),
    INDEX `idx_configuraciones_grupo` (`grupo`),
    INDEX `idx_configuraciones_estado` (`estado`),
    INDEX `idx_configuraciones_orden` (`grupo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Parámetros funcionales y configuración del sistema';

-- ----------------------------------------------------------------------------
-- SEMILLAS ESTRUCTURALES DE PARÁMETROS DE CONFIGURACIÓN
-- ----------------------------------------------------------------------------
-- Nota de gobernanza: Decisiones P-004 (Timezone hotelero) y P-005 (Moneda e
-- impuestos) continúan formalmente pendientes. No se siembran parámetros
-- especulativos dependientes de dichas decisiones para evitar resolverlas
-- accidentalmente.
-- ----------------------------------------------------------------------------

INSERT INTO `configuraciones` (`clave`, `grupo`, `nombre`, `descripcion`, `tipo`, `valor`, `valor_predeterminado`, `editable`, `es_sensible`, `orden`, `estado`) VALUES
('sistema.nombre', 'GENERAL', 'Nombre del Sistema', 'Nombre visible del PMS para encabezados y comunicaciones', 'TEXTO', 'Camargo Hostelería', 'Camargo Hostelería', 1, 0, 1, 'ACTIVO'),
('sistema.descripcion', 'GENERAL', 'Descripción del Sistema', 'Lema o descripción general de la organización hotelera', 'TEXTO', 'Gestión Hotelera y Extrahotelera', 'Gestión Hotelera y Extrahotelera', 1, 0, 2, 'ACTIVO'),
('sistema.version_instalada', 'GENERAL', 'Versión Instalada', 'Versión de software del núcleo Camargo PMS (parámetro protegido)', 'TEXTO', '1.0.0', '1.0.0', 0, 0, 99, 'ACTIVO'),
('sistema.idioma', 'LOCALIZACION', 'Idioma Principal', 'Código de idioma predeterminado para la interfaz de usuario (ISO 639-1)', 'TEXTO', 'es', 'es', 1, 0, 1, 'ACTIVO'),
('sistema.formato_fecha', 'LOCALIZACION', 'Formato de Visualización de Fecha', 'Patrón visual estándar para representación de fechas', 'TEXTO', 'd/m/Y', 'd/m/Y', 1, 0, 2, 'ACTIVO'),
('sistema.formato_hora', 'LOCALIZACION', 'Formato de Visualización de Hora', 'Patrón visual estándar para representación horaria', 'TEXTO', 'H:i', 'H:i', 1, 0, 3, 'ACTIVO'),
('operacion.modo_mantenimiento', 'OPERACION', 'Modo Mantenimiento', 'Indica si el sistema se encuentra en ventana de mantenimiento operativo', 'BOOLEANO', '0', '0', 1, 0, 1, 'ACTIVO'),
('operacion.paginacion_predeterminada', 'OPERACION', 'Paginación Predeterminada', 'Cantidad de registros predeterminada por página en listados administrativos', 'ENTERO', '15', '15', 1, 0, 2, 'ACTIVO')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `tipo` = VALUES(`tipo`),
    `editable` = VALUES(`editable`),
    `es_sensible` = VALUES(`es_sensible`),
    `orden` = VALUES(`orden`);

-- ----------------------------------------------------------------------------
-- SEMILLAS ESTRUCTURALES DE MENÚ: Opción 'Configuración General'
-- ----------------------------------------------------------------------------

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'config_sistema', 'Configuración General', 'ti ti-adjustments', '/configuracion/sistema', 1, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'configuracion.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);
