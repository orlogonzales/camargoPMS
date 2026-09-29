-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 031: PERFIL COMERCIAL DE CLIENTES Y FICHA 360° (CLIENTES-1)
-- ============================================================================
-- Principios vinculantes:
-- 1. PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA.
--    - Persona: Soberana de la identidad civil y biológica (DNI, nombres, residencia, contactos).
--    - Cliente: Modela exclusivamente la relación comercial para Personas Naturales (persona_id UNIQUE).
-- 2. CLIENTE CORPORATIVO ≠ EMPRESA EMISORA (D-091):
--    - Diferido a fase futura. La tabla `empresas` queda reservada para operadoras del PMS.
-- 3. Categorías comerciales nativas: ESTANDAR, FRECUENTE, VIP (Sin CORPORATIVO ni EVENTUAL).
-- 4. Estados comerciales: ACTIVO, INACTIVO, BLOQUEADO (con motivo obligatorio y sin borrado físico).
-- 5. Trazabilidad RBAC y auditoría append-only (D-061).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Tabla de Categorías Comerciales de Cliente
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cliente_categorias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Identificador único canónico: ESTANDAR, FRECUENTE, VIP',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre visual descriptivo de la categoría',
    `descripcion` VARCHAR(255) NULL,
    `color_badge` VARCHAR(30) NOT NULL DEFAULT 'secondary' COMMENT 'Clase CSS o variante Bootstrap/Alina para chips/badges',
    `es_predeterminada` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es la categoría asignada automáticamente a nuevos clientes',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_clic_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_clic_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_cliente_categorias_codigo` (`codigo`),
    INDEX `idx_cliente_categorias_activo` (`activo`),
    INDEX `idx_cliente_categorias_predeterminada` (`es_predeterminada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de categorías comerciales de cliente';

-- Semillas oficiales de Categorías (Estrictamente ESTANDAR, FRECUENTE, VIP)
INSERT INTO `cliente_categorias` (`codigo`, `nombre`, `descripcion`, `color_badge`, `es_predeterminada`, `activo`) VALUES
('ESTANDAR', 'Estándar', 'Cliente estándar o nuevo sin historial comercial preferente', 'secondary', 1, 1),
('FRECUENTE', 'Frecuente', 'Cliente con estancias o arrendamientos recurrentes', 'info', 0, 1),
('VIP', 'VIP', 'Cliente distinguido de alto valor y atención prioritaria', 'warning', 0, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `color_badge` = VALUES(`color_badge`),
    `es_predeterminada` = VALUES(`es_predeterminada`),
    `activo` = VALUES(`activo`);

-- ----------------------------------------------------------------------------
-- 2. Tabla Central de Perfiles de Clientes Comerciales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clientes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'Código comercial secuencial con prefijo CLI-XXXXX',
    `persona_id` BIGINT UNSIGNED NOT NULL COMMENT 'Relación 1:1 estricta con el maestro soberano de Personas',
    `categoria_id` INT UNSIGNED NOT NULL COMMENT 'FK a cliente_categorias',
    `estado` ENUM('ACTIVO', 'INACTIVO', 'BLOQUEADO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado comercial del cliente',
    `motivo_bloqueo` TEXT NULL COMMENT 'Obligatorio cuando estado = BLOQUEADO',
    `canal_captacion` VARCHAR(50) NULL COMMENT 'DIRECTO, WEB, OTA_BOOKING, AIRBNB, RECOMENDACION, WALK_IN, etc.',
    `preferencias` TEXT NULL COMMENT 'Preferencias declaradas del cliente (habitación alta, almohadas, etc.)',
    `observaciones` TEXT NULL COMMENT 'Notas operativas y comerciales internas',
    `creado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'Actor responsable del alta (D-061)',
    `actualizado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'Actor responsable de última modificación (D-061)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_clientes_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `fk_clientes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_clientes_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `cliente_categorias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_clientes_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_clientes_actor_actualizador` FOREIGN KEY (`actualizado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_clientes_codigo` (`codigo`),
    UNIQUE KEY `uq_clientes_persona` (`persona_id`),
    INDEX `idx_clientes_categoria` (`categoria_id`),
    INDEX `idx_clientes_estado` (`estado`),
    INDEX `idx_clientes_canal` (`canal_captacion`),
    INDEX `idx_clientes_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de perfiles comerciales de clientes (Personas Naturales)';

-- ----------------------------------------------------------------------------
-- 3. Permisos RBAC para el Módulo de Clientes
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`) VALUES
('clientes.ver', 'Ver catálogo y ficha integral 360 de clientes', 'clientes', 'Permite consultar el directorio y la ficha integral 360 de clientes'),
('clientes.crear', 'Crear perfil comercial de cliente', 'clientes', 'Permite registrar un nuevo cliente vinculado a una persona'),
('clientes.editar', 'Modificar perfil comercial de cliente', 'clientes', 'Permite editar categoría, preferencias, observaciones y canal de captación'),
('clientes.bloquear', 'Bloquear o desbloquear clientes', 'clientes', 'Permite cambiar el estado comercial a BLOQUEADO con motivo obligatorio'),
('clientes.gestionar_categorias', 'Gestionar categorías de clientes', 'clientes', 'Permite administrar el catálogo de categorías comerciales de cliente')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR (rol_id = 1)
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.`id`
FROM `permisos` p
WHERE p.`modulo` = 'clientes'
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 4. Opción de Menú en Navegación Administrativa (bajo Reservas / Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    p.`id`,
    'clientes_catalogo',
    'Clientes',
    'fa-solid fa-users',
    '/clientes',
    4,
    'ACTIVO',
    perm.`id`,
    1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'clientes.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
