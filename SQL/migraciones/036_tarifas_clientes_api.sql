-- ============================================================================
-- Camargo PMS — Migración 036: Modelo Soberano de Tarifas de Alojamiento,
-- Clientes API y Credenciales Técnicas con Scopes Normalizados (WORDPRESS-1B)
-- ============================================================================
-- Principios vinculantes:
-- 1. UNIDAD ≠ TARIFA: Las tarifas por noche se modelan soberanamente sin alterar
--    las tablas físicas de `unidades` ni `tipos_unidad`.
-- 2. JERARQUÍA DE PRECEDENCIA TARIFARIA:
--    UNIDAD (Override físico) > TIPO_UNIDAD (Tarifa de categoría) > PROPIEDAD (General).
-- 3. ANTI-SOLAPAMIENTO TEMPORAL: Bloqueo pesimista y verificación estricta que impide
--    vigencias ambiguas o solapadas para un mismo ámbito tarifario.
-- 4. SEPARACIÓN TÉCNICA DE INTEGRACIÓN:
--    ACTOR INTEGRACION -> API_CLIENT -> CREDENCIAL TÉCNICA -> SCOPES.
--    Cero usuarios humanos ficticios; almacenamiento seguro con hash SHA-256;
--    credenciales CSPRNG emitidas una sola vez; revocación y rotación atómica.
-- 5. POLÍTICA MONETARIA Y EXACTITUD DECIMAL (D-069):
--    Moneda canónica 'PEN', precisión intermedia DECIMAL(15,4), redondeo final DECIMAL(15,2).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Soberano de Tarifas de Alojamiento
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarifas_alojamiento` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Propiedad hotelera a la que pertenece la tarifa',
    `ambito_tipo` ENUM('PROPIEDAD', 'TIPO_UNIDAD', 'UNIDAD') NOT NULL DEFAULT 'TIPO_UNIDAD' COMMENT 'Nivel de granularidad tarifaria',
    `tipo_unidad_id` INT UNSIGNED NULL COMMENT 'Obligatorio si ambito_tipo es TIPO_UNIDAD',
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'Obligatorio si ambito_tipo es UNIDAD (override sobre unidad específica)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Denominación descriptiva de la tarifa (ej. Estándar 2026, Temporada Alta)',
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN' COMMENT 'Código ISO 4217 de la moneda canónica',
    `precio_noche` DECIMAL(15,4) NOT NULL COMMENT 'Precio base por noche con precisión intermedia D-069',
    `vigencia_desde` DATE NOT NULL COMMENT 'Fecha inicial de vigencia (inclusiva)',
    `vigencia_hasta` DATE NULL COMMENT 'Fecha final de vigencia (inclusiva). NULL indica vigencia indefinida',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor emisor del registro para auditoría transversal',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_talo_vigencia` CHECK (`vigencia_hasta` IS NULL OR `vigencia_hasta` >= `vigencia_desde`),
    CONSTRAINT `chk_talo_precio_positivo` CHECK (`precio_noche` >= 0),
    CONSTRAINT `chk_talo_nombre_no_vacio` CHECK (`nombre` <> ''),
    KEY `idx_talo_resolucion` (`propiedad_id`, `ambito_tipo`, `tipo_unidad_id`, `unidad_id`, `estado`, `vigencia_desde`, `vigencia_hasta`),
    KEY `idx_talo_vigencia` (`vigencia_desde`, `vigencia_hasta`),
    CONSTRAINT `fk_talo_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_talo_tipo_unidad` FOREIGN KEY (`tipo_unidad_id`) REFERENCES `tipos_unidad` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_talo_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_talo_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo soberano de tarifas por noche para cotización y reservas directas';

-- ----------------------------------------------------------------------------
-- 2. Clientes API (Integraciones Externas / WordPress / Canales Directos)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_clientes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor técnico de auditoría vinculado (tipo INTEGRACION)',
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Identificador único legible del cliente API (ej. CLI_WP_OFICIAL)',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo de la integración',
    `descripcion` VARCHAR(255) NULL,
    `contacto_email` VARCHAR(150) NULL COMMENT 'Correo del administrador técnico responsable',
    `ips_permitidas` TEXT NULL COMMENT 'JSON o lista blanca de IPs/CIDRs autorizadas (NULL = sin restricción)',
    `limite_peticiones_minuto` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Tasa máxima de peticiones por minuto para rate limiting',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_apicli_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_apicli_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_api_clientes_actor_id` (`actor_id`),
    UNIQUE KEY `uq_api_clientes_codigo` (`codigo`),
    KEY `idx_api_clientes_estado` (`estado`),
    CONSTRAINT `fk_api_clientes_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de clientes API integrados con Camargo PMS';

-- ----------------------------------------------------------------------------
-- 3. Credenciales Técnicas de Clientes API (Rotación, Revocación y Hash Seguro)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_credenciales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `api_cliente_id` BIGINT UNSIGNED NOT NULL COMMENT 'Cliente API propietario de la credencial',
    `identificador_publico` VARCHAR(32) NOT NULL COMMENT 'ID público de la llave (ej. key_...) para trazabilidad',
    `token_hash` CHAR(64) NOT NULL COMMENT 'Hash SHA-256 del secreto Bearer para búsqueda O(1) segura',
    `token_prefijo` VARCHAR(16) NOT NULL COMMENT 'Prefijo legible para identificación visual sin revelar el secreto',
    `nombre` VARCHAR(100) NOT NULL DEFAULT 'Credencial Principal' COMMENT 'Etiqueta identificadora (ej. Producción 2026)',
    `estado` ENUM('ACTIVO', 'REVOCADO', 'EXPIRADO') NOT NULL DEFAULT 'ACTIVO',
    `ultimo_uso_en` DATETIME NULL COMMENT 'Marca de tiempo del último uso exitoso',
    `expira_en` DATETIME NULL COMMENT 'Fecha de expiración o NULL si no caduca',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revocado_en` DATETIME NULL COMMENT 'Fecha y hora en que fue revocada explícitamente',
    CONSTRAINT `chk_apicred_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_api_credenciales_identificador` (`identificador_publico`),
    UNIQUE KEY `uq_api_credenciales_token_hash` (`token_hash`),
    KEY `idx_api_credenciales_cliente` (`api_cliente_id`),
    KEY `idx_api_credenciales_estado` (`estado`),
    CONSTRAINT `fk_api_credenciales_cliente` FOREIGN KEY (`api_cliente_id`) REFERENCES `api_clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Credenciales criptográficas Bearer asociadas a clientes API';

-- ----------------------------------------------------------------------------
-- 4. Catálogo Normalizado de Scopes API
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_scopes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(60) NOT NULL COMMENT 'Código canónico del scope (ej. disponibilidad.leer)',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre legible del alcance',
    `descripcion` VARCHAR(255) NULL,
    `modulo` VARCHAR(60) NOT NULL DEFAULT 'reservas' COMMENT 'Módulo funcional al que pertenece',
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_apiscope_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_apiscope_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_api_scopes_codigo` (`codigo`),
    KEY `idx_api_scopes_modulo` (`modulo`),
    KEY `idx_api_scopes_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo normalizado de scopes y capacidades de la API';

-- ----------------------------------------------------------------------------
-- 5. Relación Normalizada Credencial <-> Scopes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_credencial_scopes` (
    `api_credencial_id` BIGINT UNSIGNED NOT NULL,
    `api_scope_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`api_credencial_id`, `api_scope_id`),
    CONSTRAINT `fk_apics_credencial` FOREIGN KEY (`api_credencial_id`) REFERENCES `api_credenciales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_apics_scope` FOREIGN KEY (`api_scope_id`) REFERENCES `api_scopes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Asignación de alcances específicos por credencial técnica';

-- ----------------------------------------------------------------------------
-- SEMILLAS ESTRUCTURALES: Scopes Canónicos del Motor de Reservas y Disponibilidad
-- ----------------------------------------------------------------------------
INSERT INTO `api_scopes` (`codigo`, `nombre`, `descripcion`, `modulo`, `activo`) VALUES
('disponibilidad.leer', 'Consultar disponibilidad', 'Permite consultar disponibilidad de propiedades y unidades en rangos de fechas', 'reservas', 1),
('cotizacion.crear', 'Solicitar cotización soberana', 'Permite obtener desglose tarifario oficial y reproducible para estancias', 'reservas', 1),
('reservas.hold', 'Crear retención temporal de inventario', 'Permite solicitar pre-reservas con bloqueo temporal de inventario (hold)', 'reservas', 1),
('reservas.confirmar', 'Confirmar reserva directa', 'Permite confirmar una reserva previa en hold tras validación de pago', 'reservas', 1),
('reservas.cancelar', 'Cancelar reserva directa', 'Permite solicitar la cancelación de reservas directas creadas por el cliente', 'reservas', 1),
('reservas.leer', 'Consultar estado de reserva', 'Permite consultar el estado y voucher de reservas por código comercial', 'reservas', 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `modulo` = VALUES(`modulo`);

-- ----------------------------------------------------------------------------
-- PERMISOS RBAC DEL PMS: Gestión de Tarifas y Clientes API en Panel Interno
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`) VALUES
('tarifas.ver', 'Ver catálogo de tarifas de alojamiento', 'tarifas', 'Permite consultar el catálogo y vigencias de tarifas hoteleras'),
('tarifas.gestionar', 'Gestionar tarifas de alojamiento', 'tarifas', 'Permite crear, actualizar y desactivar tarifas hoteleras y overrides'),
('api_clientes.ver', 'Ver clientes API y credenciales', 'api_clientes', 'Permite consultar el directorio de integraciones y credenciales técnicas'),
('api_clientes.gestionar', 'Gestionar clientes API y credenciales', 'api_clientes', 'Permite registrar clientes API, generar llaves, rotar y revocar credenciales')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- Asignar nuevos permisos al rol Superadministrador (rol_id = 1)
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.`id`
FROM `permisos` p
WHERE p.`codigo` IN ('tarifas.ver', 'tarifas.gestionar', 'api_clientes.ver', 'api_clientes.gestionar')
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
