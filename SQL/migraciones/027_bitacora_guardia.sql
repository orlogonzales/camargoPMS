-- ============================================================================
-- CAMARGO PMS — Migración 027: Módulo de Bitácora Operativa y Libro de Guardia
-- Gobernanza: D-089 (BITÁCORA-1)
-- Axioma: BITÁCORA ≠ AUDITORÍA TÉCNICA ≠ TURNO CAJA ≠ MANTENIMIENTO ≠ HOUSEKEEPING
-- Tablas:
--   1. bitacora_entradas      (Novedades, consignas, incidencias y relevos de guardia)
--   2. bitacora_seguimientos  (Historial cronológico append-only de comentarios, enmiendas y resoluciones)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Bitácora de Entradas Operacionales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bitacora_entradas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'Propiedad o sede física del turno',
    `usuario_creador_id` BIGINT UNSIGNED NOT NULL COMMENT 'Colaborador humano autor del registro',
    `tipo` ENUM('NOVEDAD', 'CONSIGNA', 'INCIDENCIA', 'RELEVO', 'AVISO_GENERAL') NOT NULL DEFAULT 'NOVEDAD',
    `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
    `titulo` VARCHAR(200) NOT NULL COMMENT 'Resumen o asunto breve del hecho operativo',
    `contenido` TEXT NOT NULL COMMENT 'Relato inmutable del hecho u orden de guardia',
    `turno` ENUM('MANANA', 'TARDE', 'NOCHE', 'GENERAL') NOT NULL DEFAULT 'GENERAL',
    `fecha_operativa` DATE NOT NULL COMMENT 'Fecha hotelera/operativa del suceso',
    `estado` ENUM('REGISTRADA', 'PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'ANULADA') NOT NULL DEFAULT 'REGISTRADA',

    -- Referencias operacionales opcionales (trazabilidad cruzada sin acoplamiento rígido)
    `unidad_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional si ocurrió en una habitación/departamento',
    `reserva_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional a reserva vinculada',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional a estadía/huésped vinculado',
    `mantenimiento_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional a orden de mantenimiento',
    `sesion_caja_id` BIGINT UNSIGNED NULL COMMENT 'Referencia opcional al turno de caja coincidente',

    -- Trazabilidad de resolución de consignas e incidencias
    `resuelta_por_usuario_id` BIGINT UNSIGNED NULL,
    `resuelta_en` DATETIME NULL,
    `nota_resolucion` TEXT NULL,

    -- Trazabilidad de anulación supervisada (ANULAR != DELETE)
    `anulada_por_usuario_id` BIGINT UNSIGNED NULL,
    `anulada_en` DATETIME NULL,
    `motivo_anulacion` VARCHAR(255) NULL,

    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `chk_bitacora_titulo_no_vacio` CHECK (`titulo` <> ''),
    CONSTRAINT `chk_bitacora_contenido_no_vacio` CHECK (`contenido` <> ''),
    CONSTRAINT `fk_bitacora_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_usuario_creador` FOREIGN KEY (`usuario_creador_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_usuario_resolutor` FOREIGN KEY (`resuelta_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_usuario_anulador` FOREIGN KEY (`anulada_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX `idx_bitacora_propiedad_fecha` (`propiedad_id`, `fecha_operativa`),
    INDEX `idx_bitacora_estado_prioridad` (`estado`, `prioridad`),
    INDEX `idx_bitacora_tipo` (`tipo`),
    INDEX `idx_bitacora_unidad` (`unidad_id`),
    INDEX `idx_bitacora_creador` (`usuario_creador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Libro de guardia y bitácora de novedades operacionales';

-- ----------------------------------------------------------------------------
-- 2. Historial de Seguimientos y Enmiendas (Append-Only)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bitacora_seguimientos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entrada_id` BIGINT UNSIGNED NOT NULL COMMENT 'Entrada de bitácora vinculada',
    `usuario_id` BIGINT UNSIGNED NOT NULL COMMENT 'Colaborador autor del seguimiento o enmienda',
    `tipo_evento` ENUM('COMENTARIO', 'ENMIENDA', 'CAMBIO_ESTADO', 'RESOLUCION', 'REAPERTURA', 'ANULACION') NOT NULL DEFAULT 'COMENTARIO',
    `contenido` TEXT NOT NULL COMMENT 'Texto del comentario, aclaración o nota de seguimiento',
    `estado_anterior` ENUM('REGISTRADA', 'PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'ANULADA') NULL,
    `estado_nuevo` ENUM('REGISTRADA', 'PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'ANULADA') NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `chk_bitacora_seg_contenido_no_vacio` CHECK (`contenido` <> ''),
    CONSTRAINT `fk_bitacora_seg_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `bitacora_entradas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bitacora_seg_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,

    INDEX `idx_bitacora_seg_entrada_fecha` (`entrada_id`, `creado_en`),
    INDEX `idx_bitacora_seg_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad append-only de comentarios y transiciones de bitácora';

-- ----------------------------------------------------------------------------
-- 3. Catálogo de Permisos RBAC de Bitácora (ROLES-1)
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('bitacora.ver', 'Ver libro de guardia', 'Permite consultar el feed y entradas de la bitácora operativa', 'operaciones', 'ACTIVO', 1),
('bitacora.crear', 'Crear entradas en bitácora', 'Permite registrar novedades, consignas e incidencias de guardia', 'operaciones', 'ACTIVO', 1),
('bitacora.seguir', 'Añadir seguimientos y notas', 'Permite comentar o enmendar entradas en el libro de guardia', 'operaciones', 'ACTIVO', 1),
('bitacora.resolver', 'Resolver y gestionar consignas', 'Permite marcar consignas e incidencias como resueltas o en proceso', 'operaciones', 'ACTIVO', 1),
('bitacora.anular', 'Anular entradas de guardia', 'Permite anulación supervisada con motivo justificado (ANULAR != DELETE)', 'operaciones', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- ----------------------------------------------------------------------------
-- 4. Asignación de Permisos al Rol SUPERADMINISTRADOR
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`codigo` = 'SUPERADMINISTRADOR'
  AND p.`codigo` LIKE 'bitacora.%';

-- ----------------------------------------------------------------------------
-- 5. Opción de Menú Alina: Libro de Guardia (Bajo Reservas/Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'operaciones_bitacora', 'Libro de Guardia', 'fa-solid fa-book-bookmark', '/operaciones/bitacora', 15, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'bitacora.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
