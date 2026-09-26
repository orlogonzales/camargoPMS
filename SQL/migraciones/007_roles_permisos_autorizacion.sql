-- ============================================================================
-- Camargo PMS — Migración 007: Roles, Permisos y Autorización RBAC de Backend
-- ============================================================================
-- Define las estructuras de base de datos para el control de acceso basado en
-- roles (RBAC) muchos a muchos:
--   USUARIO <---> usuarios_roles <---> ROL <---> roles_permisos <---> PERMISO
--
-- Principios vinculantes:
--   PERSONA != COLABORADOR != USUARIO != CARGO != ROL
--   CARGO (laboral) != ROL (autorización de software)
--   OCULTAR UNA OPCIÓN DEL MENÚ NO ES AUTORIZACIÓN
--   SUPERADMINISTRADOR es un rol estructural protegido con autoridad total
-- ============================================================================

-- 1. Tabla de Roles de Autorización
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `es_superadministrador` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_roles_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_roles_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_roles_codigo` (`codigo`),
    INDEX `idx_roles_estado` (`estado`),
    INDEX `idx_roles_superadmin` (`es_superadministrador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Roles de autorización RBAC del sistema';

-- 2. Tabla de Permisos Atómicos (recurso.accion)
CREATE TABLE IF NOT EXISTS `permisos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(100) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `modulo` VARCHAR(50) NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `es_sistema` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_permisos_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_permisos_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_permisos_codigo` (`codigo`),
    INDEX `idx_permisos_modulo` (`modulo`),
    INDEX `idx_permisos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo de permisos atómicos recurso.accion';

-- 3. Relación Muchos a Muchos: Roles <---> Permisos
CREATE TABLE IF NOT EXISTS `roles_permisos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `permiso_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_roles_permisos_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_roles_permisos_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_roles_permisos_rol_permiso` (`rol_id`, `permiso_id`),
    INDEX `idx_roles_permisos_permiso` (`permiso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relación N:M de asignación de permisos a roles';

-- 4. Relación Muchos a Muchos: Usuarios <---> Roles
CREATE TABLE IF NOT EXISTS `usuarios_roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `asignado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `asignado_por_usuario_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `fk_usuarios_roles_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_roles_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_roles_asignado_por` FOREIGN KEY (`asignado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_usuarios_roles_usuario_rol` (`usuario_id`, `rol_id`),
    INDEX `idx_usuarios_roles_rol` (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relación N:M de asignación de roles a usuarios';

-- ----------------------------------------------------------------------------
-- SEMILLAS ESTRUCTURALES DEL SISTEMA
-- ----------------------------------------------------------------------------

-- Rol estructural SUPERADMINISTRADOR
INSERT INTO `roles` (`codigo`, `nombre`, `descripcion`, `estado`, `es_sistema`, `es_superadministrador`) VALUES
('SUPERADMINISTRADOR', 'Superadministrador', 'Acceso total e irrestricto a todas las funciones y recursos del sistema', 'ACTIVO', 1, 1);

-- Catálogo inicial de permisos atómicos de administración de usuarios y roles
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('usuarios.ver', 'Ver usuarios', 'Permite consultar el listado y detalle de cuentas de usuario', 'usuarios', 'ACTIVO', 1),
('usuarios.crear', 'Crear usuarios', 'Permite registrar nuevas cuentas de usuario en el sistema', 'usuarios', 'ACTIVO', 1),
('usuarios.editar', 'Editar usuarios', 'Permite modificar datos de cuentas de usuario existentes', 'usuarios', 'ACTIVO', 1),
('usuarios.bloquear', 'Bloquear usuarios', 'Permite bloquear o desactivar cuentas de usuario', 'usuarios', 'ACTIVO', 1),
('roles.ver', 'Ver roles', 'Permite consultar los roles y sus permisos asociados', 'roles', 'ACTIVO', 1),
('roles.crear', 'Crear roles', 'Permite definir nuevos roles de autorización en el sistema', 'roles', 'ACTIVO', 1),
('roles.editar', 'Editar roles', 'Permite modificar nombres y descripciones de roles', 'roles', 'ACTIVO', 1),
('roles.asignar', 'Asignar roles', 'Permite asignar roles a cuentas de usuario', 'roles', 'ACTIVO', 1),
('roles.revocar', 'Revocar roles', 'Permite revocar roles previamente asignados a usuarios', 'roles', 'ACTIVO', 1),
('permisos.ver', 'Ver permisos', 'Permite consultar el catálogo general de permisos del sistema', 'permisos', 'ACTIVO', 1);

-- ----------------------------------------------------------------------------
-- TRANSICIÓN DETERMINISTA PARA INSTALACIÓN EXISTENTE
-- ----------------------------------------------------------------------------
-- Si existe exactamente 1 usuario registrado en `usuarios` y aún no tiene asignado
-- el rol de Superadministrador, se le vincula automáticamente de forma segura.
-- En instalaciones limpias (0 usuarios) o con múltiples usuarios (>1 ambiguos), no inserta filas.
INSERT INTO `usuarios_roles` (`usuario_id`, `rol_id`, `asignado_en`)
SELECT u.`id`, r.`id`, NOW()
FROM `usuarios` u
CROSS JOIN `roles` r
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND (SELECT COUNT(*) FROM `usuarios`) = 1
  AND NOT EXISTS (
      SELECT 1 FROM `usuarios_roles` ur
      JOIN `roles` r2 ON ur.`rol_id` = r2.`id`
      WHERE r2.`es_superadministrador` = 1
  );
