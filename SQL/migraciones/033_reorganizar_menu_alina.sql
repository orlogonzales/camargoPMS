-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 033: REORGANIZACIÓN FUNCIONAL DEL MENÚ ALINA (UI-ALINA-1A-C1)
-- ============================================================================
-- Principios de Gobernanza y Arquitectura Vinculantes (D-093 y D-093-C1):
-- 1. NAVEGACIÓN EN 9 DOMINIOS CANÓNICOS:
--    - Inicio (1)
--    - Propiedades (10)
--    - Comercial y Reservas (20, clave técnica 'reservas')
--    - Operaciones (30, clave 'operaciones')
--    - Caja y Finanzas (40, clave 'caja_finanzas')
--    - Abastecimiento (50, clave 'abastecimiento')
--    - Documentos (60, clave 'documentos')
--    - Atención al Cliente (70, clave 'atencion_cliente')
--    - Configuración (90, clave 'configuracion')
--
-- 2. REUBICACIÓN OPERATIVA Y FINANCIERA (UI-ALINA-1A-C1):
--    - Comercial y Reservas:
--      * Tape Chart / Rack (/tape-chart, orden 1)
--      * Reservas (/reservas, orden 2)
--      * Clientes (/clientes, orden 3)
--      * Arrendamientos (/arrendamientos, orden 4)
--    - Operaciones:
--      * Estadías / Check-in (/estadias, orden 1) [Reubicado desde Reservas]
--      * Servicios y Consumos (/servicios, orden 2) [Reubicado desde Reservas]
--      * Housekeeping / Pisos (/housekeeping, orden 3)
--      * Mantenimiento (/mantenimiento, orden 4)
--      * Libro de Guardia (/operaciones/bitacora, orden 5)
--    - Caja y Finanzas:
--      * Caja y Cuentas (/caja, orden 1)
--      * Recibos de Pago (/recibos, orden 2)
--      * Gastos y Egresos (/gastos, orden 3)
--      * Auditoría Nocturna (/operaciones/night-audit, orden 4) [Reubicado desde Operaciones]
--    - Abastecimiento:
--      * Inventario (/inventario, orden 1)
--      * Suministros (/suministros, orden 2)
--      * Compras (/compras, orden 3)
--    - Documentos:
--      * Documentos (/documentos, orden 1)
--    - Atención al Cliente:
--      * Libro de Reclamaciones (/reclamaciones, orden 1)
--    - Configuración:
--      * 8 módulos del sistema (órdenes 1 a 8)
--
-- 3. CERO MODIFICACIONES DDL:
--    - Tabla opciones_menu soporta jerarquía mediante padre_id desde migración 008.
--    - Migración 033 es una migración pura de transformación y sincronización de datos.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Asegurar o actualizar los 9 Dominios Principales (Nivel 1: padre_id IS NULL)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`) VALUES
(NULL, 'inicio', 'Inicio', 'fa-solid fa-house', NULL, 1, 'ACTIVO', NULL, 1),
(NULL, 'propiedades', 'Propiedades', 'fa-solid fa-building', NULL, 10, 'ACTIVO', NULL, 1),
(NULL, 'reservas', 'Comercial y Reservas', 'fa-solid fa-calendar-check', NULL, 20, 'ACTIVO', NULL, 1),
(NULL, 'operaciones', 'Operaciones', 'fa-solid fa-clipboard-check', NULL, 30, 'ACTIVO', NULL, 1),
(NULL, 'caja_finanzas', 'Caja y Finanzas', 'fa-solid fa-cash-register', NULL, 40, 'ACTIVO', NULL, 1),
(NULL, 'abastecimiento', 'Abastecimiento', 'fa-solid fa-boxes-stacked', NULL, 50, 'ACTIVO', NULL, 1),
(NULL, 'documentos', 'Documentos', 'fa-solid fa-file-invoice', NULL, 60, 'ACTIVO', NULL, 1),
(NULL, 'atencion_cliente', 'Atención al Cliente', 'fa-solid fa-headset', NULL, 70, 'ACTIVO', NULL, 1),
(NULL, 'configuracion', 'Configuración', 'fa-solid fa-gear', NULL, 90, 'ACTIVO', NULL, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `orden` = VALUES(`orden`),
    `estado` = 'ACTIVO';

-- 2. Variables de ID para los 9 dominios
SET @id_inicio = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'inicio' AND `padre_id` IS NULL LIMIT 1);
SET @id_propiedades = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'propiedades' AND `padre_id` IS NULL LIMIT 1);
SET @id_reservas = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'reservas' AND `padre_id` IS NULL LIMIT 1);
SET @id_operaciones = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'operaciones' AND `padre_id` IS NULL LIMIT 1);
SET @id_caja_finanzas = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'caja_finanzas' AND `padre_id` IS NULL LIMIT 1);
SET @id_abastecimiento = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'abastecimiento' AND `padre_id` IS NULL LIMIT 1);
SET @id_documentos = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'documentos' AND `padre_id` IS NULL LIMIT 1);
SET @id_atencion_cliente = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'atencion_cliente' AND `padre_id` IS NULL LIMIT 1);
SET @id_configuracion = (SELECT `id` FROM `opciones_menu` WHERE `clave` = 'configuracion' AND `padre_id` IS NULL LIMIT 1);

-- 3. Reasignar Opciones al Dominio 'inicio'
UPDATE `opciones_menu` SET `padre_id` = @id_inicio, `orden` = 1 WHERE `clave` = 'inicio_panel';

-- 4. Reasignar Opciones al Dominio 'propiedades'
UPDATE `opciones_menu` SET `padre_id` = @id_propiedades, `orden` = 1 WHERE `clave` = 'propiedades_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_propiedades, `orden` = 2 WHERE `clave` = 'unidades_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_propiedades, `orden` = 3 WHERE `clave` = 'disponibilidad_calendario';

-- 5. Reasignar Opciones al Dominio 'reservas' (Comercial y Reservas)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT @id_reservas, 'tape_chart', 'Tape Chart / Rack', 'fa-solid fa-table-cells', '/tape-chart', 1, 'ACTIVO', perm.`id`, 1
FROM `permisos` perm
WHERE perm.`codigo` = 'disponibilidad.ver'
ON DUPLICATE KEY UPDATE `padre_id` = @id_reservas, `orden` = 1, `estado` = 'ACTIVO';

UPDATE `opciones_menu` SET `padre_id` = @id_reservas, `orden` = 1 WHERE `clave` = 'tape_chart';
UPDATE `opciones_menu` SET `padre_id` = @id_reservas, `orden` = 2 WHERE `clave` = 'reservas_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_reservas, `orden` = 3 WHERE `clave` = 'clientes_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_reservas, `orden` = 4 WHERE `clave` = 'arrendamientos_catalogo';

-- 6. Reasignar Opciones al Dominio 'operaciones' (Operaciones)
UPDATE `opciones_menu` SET `padre_id` = @id_operaciones, `nombre` = 'Estadías / Check-in', `orden` = 1 WHERE `clave` = 'estadias_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_operaciones, `nombre` = 'Servicios y Consumos', `orden` = 2 WHERE `clave` = 'servicios_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_operaciones, `nombre` = 'Housekeeping / Pisos', `orden` = 3 WHERE `clave` = 'housekeeping_tablero';
UPDATE `opciones_menu` SET `padre_id` = @id_operaciones, `nombre` = 'Mantenimiento', `orden` = 4 WHERE `clave` = 'mantenimiento_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_operaciones, `nombre` = 'Libro de Guardia', `orden` = 5 WHERE `clave` = 'operaciones_bitacora';

-- 7. Reasignar Opciones al Dominio 'caja_finanzas' (Caja y Finanzas)
UPDATE `opciones_menu` SET `padre_id` = @id_caja_finanzas, `nombre` = 'Caja y Cuentas', `orden` = 1 WHERE `clave` = 'caja_cuentas';
UPDATE `opciones_menu` SET `padre_id` = @id_caja_finanzas, `nombre` = 'Recibos de Pago', `orden` = 2 WHERE `clave` = 'recibos';
UPDATE `opciones_menu` SET `padre_id` = @id_caja_finanzas, `nombre` = 'Gastos y Egresos', `orden` = 3 WHERE `clave` = 'gastos_modulo';
UPDATE `opciones_menu` SET `padre_id` = @id_caja_finanzas, `nombre` = 'Auditoría Nocturna', `orden` = 4 WHERE `clave` = 'operaciones_night_audit';

-- 8. Reasignar Opciones al Dominio 'abastecimiento' (Abastecimiento)
UPDATE `opciones_menu` SET `padre_id` = @id_abastecimiento, `nombre` = 'Inventario', `orden` = 1 WHERE `clave` = 'inventario_catalogo';
UPDATE `opciones_menu` SET `padre_id` = @id_abastecimiento, `nombre` = 'Suministros', `orden` = 2 WHERE `clave` = 'suministros';
UPDATE `opciones_menu` SET `padre_id` = @id_abastecimiento, `nombre` = 'Compras', `orden` = 3 WHERE `clave` = 'compras';

-- 9. Reasignar Opciones al Dominio 'documentos' (Documentos)
UPDATE `opciones_menu` SET `padre_id` = @id_documentos, `nombre` = 'Documentos', `orden` = 1 WHERE `clave` = 'documentos_motor';

-- 10. Reasignar Opciones al Dominio 'atencion_cliente' (Atención al Cliente)
UPDATE `opciones_menu` SET `padre_id` = @id_atencion_cliente, `nombre` = 'Libro de Reclamaciones', `orden` = 1 WHERE `clave` = 'reclamaciones_directorio';

-- 11. Reasignar y Ordenar Opciones del Dominio 'configuracion'
-- Asegurar permisos atómicos requeridos para opciones de seguridad y personal
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('sesiones.ver', 'Ver sesiones de usuario', 'Permite consultar el monitor y listado de sesiones de usuario en el sistema', 'seguridad', 'ACTIVO', 1),
('sesiones.revocar', 'Revocar sesiones de usuario', 'Permite revocar administrativamente sesiones activas de usuarios', 'seguridad', 'ACTIVO', 1),
('personal.ver', 'Ver directorio y legajo de personal', 'Permite consultar el catálogo y detalle del personal', 'personal', 'ACTIVO', 1),
('personal.gestionar', 'Gestionar personal y colaboradores', 'Permite registrar, actualizar y cesar personal', 'personal', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permisos` p ON p.`codigo` IN ('sesiones.ver', 'sesiones.revocar', 'personal.ver', 'personal.gestionar')
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT @id_configuracion, 'config_roles', 'Roles y Permisos', 'fa-solid fa-shield-halved', '/configuracion/roles', 4, 'ACTIVO', perm.`id`, 1
FROM `permisos` perm WHERE perm.`codigo` = 'roles.ver'
ON DUPLICATE KEY UPDATE `padre_id` = @id_configuracion, `orden` = 4, `estado` = 'ACTIVO';

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT @id_configuracion, 'seguridad_sesiones', 'Sesiones Activas', 'fa-solid fa-user-lock', '/seguridad/sesiones', 5, 'ACTIVO', perm.`id`, 1
FROM `permisos` perm WHERE perm.`codigo` = 'sesiones.ver'
ON DUPLICATE KEY UPDATE `padre_id` = @id_configuracion, `orden` = 5, `estado` = 'ACTIVO';

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT @id_configuracion, 'config_empresa', 'Empresa / Emisor', 'fa-solid fa-building', '/empresas', 6, 'ACTIVO', perm.`id`, 0
FROM `permisos` perm WHERE perm.`codigo` = 'empresa.ver'
ON DUPLICATE KEY UPDATE `padre_id` = @id_configuracion, `orden` = 6, `estado` = 'ACTIVO';

INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT @id_configuracion, 'config_personal', 'Personal / RR.HH.', 'fa-solid fa-user-tie', '/personal', 7, 'ACTIVO', perm.`id`, 1
FROM `permisos` perm WHERE perm.`codigo` = 'personal.ver'
ON DUPLICATE KEY UPDATE `padre_id` = @id_configuracion, `orden` = 7, `estado` = 'ACTIVO';

UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 1 WHERE `clave` = 'config_sistema';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 2 WHERE `clave` = 'config_menu';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 3 WHERE `clave` = 'config_usuarios';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 4 WHERE `clave` = 'config_roles';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 5 WHERE `clave` = 'seguridad_sesiones';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 6 WHERE `clave` = 'config_empresa';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 7 WHERE `clave` = 'config_personal';
UPDATE `opciones_menu` SET `padre_id` = @id_configuracion, `orden` = 8 WHERE `clave` = 'config_feriados';

SET FOREIGN_KEY_CHECKS = 1;
