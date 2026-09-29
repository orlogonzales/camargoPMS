-- ============================================================================
-- CAMARGO PMS — MIGRACIÓN 032: LIBRO DE RECLAMACIONES INTEGRAL (RECLAMACIONES-1)
-- ============================================================================
-- Normativa Regulatoria Vinculante:
-- - Ley N° 29571 (Código de Protección y Defensa del Consumidor, arts. 150-152)
-- - D.S. N° 011-2011-PCM (Reglamento del Libro de Reclamaciones) y modif. D.S. 101-2022-PCM
-- - Ley N° 31435: Plazo legal máximo de 15 días hábiles improrrogables para respuesta
-- - D.S. N° 101-2022-PCM (art. 4-B): Suspensión de hasta 5 días hábiles por ofrecimiento de solución
-- - Ley N° 32495: Inclusión explícita de plataformas digitales de comercio electrónico
--
-- Principios Arquitectónicos Vinculantes:
-- 1. RECLAMO ≠ QUEJA (Tipificación legal excluyente)
-- 2. RECLAMANTE → PERSONA (Núcleo soberano civil)
-- 3. PERSONA ACTUAL ≠ SNAPSHOT LEGAL T0 (Inmutabilidad histórica probatoria en T0)
-- 4. EXPEDIENTE INMUTABLE Y ACTUACIONES APPEND-ONLY (Cero DELETE, cero sobrescritura de relatos)
-- 5. ASIENTO TRANSACCIONAL PREVIO AL PDF (El fallo documental no invalida el reclamo presentado)
-- 6. CALENDARIO LEGAL EXPANDIBLE (Feriados legales vs no laborables, mantenimiento indefinido)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Catálogo Soberano de Feriados y Días No Laborables
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calendario_feriados` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL COMMENT 'Fecha del feriado o día no laborable',
    `descripcion` VARCHAR(150) NOT NULL COMMENT 'Descripción de la festividad o motivo',
    `tipo` ENUM('FERIADO_LEGAL', 'NO_LABORABLE_COMPENSABLE') NOT NULL DEFAULT 'FERIADO_LEGAL' COMMENT 'Feriado nacional de ley vs día no laborable administrativo',
    `aplica_sector_privado` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 si excluye cómputo legal en sector privado; 0 si solo rige para sector público',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cal_feriados_desc_no_vacia` CHECK (`descripcion` <> ''),
    UNIQUE KEY `uq_calendario_feriados_fecha` (`fecha`),
    INDEX `idx_cal_feriados_busqueda` (`fecha`, `activo`, `aplica_sector_privado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Calendario administrable de feriados oficiales para cómputo de plazos';

-- Semillas oficiales de Feriados Nacionales Peruanos (2026 y 2027)
INSERT INTO `calendario_feriados` (`fecha`, `descripcion`, `tipo`, `aplica_sector_privado`, `activo`) VALUES
-- 2026
('2026-01-01', 'Año Nuevo', 'FERIADO_LEGAL', 1, 1),
('2026-04-02', 'Jueves Santo', 'FERIADO_LEGAL', 1, 1),
('2026-04-03', 'Viernes Santo', 'FERIADO_LEGAL', 1, 1),
('2026-05-01', 'Día del Trabajo', 'FERIADO_LEGAL', 1, 1),
('2026-06-07', 'Batalla de Arica y Día de la Bandera', 'FERIADO_LEGAL', 1, 1),
('2026-06-29', 'San Pedro y San Pablo', 'FERIADO_LEGAL', 1, 1),
('2026-07-23', 'Día de la Fuerza Aérea del Perú', 'FERIADO_LEGAL', 1, 1),
('2026-07-28', 'Fiestas Patrias — Día de la Independencia', 'FERIADO_LEGAL', 1, 1),
('2026-07-29', 'Fiestas Patrias — Homenaje a la Patria', 'FERIADO_LEGAL', 1, 1),
('2026-08-06', 'Batalla de Junín', 'FERIADO_LEGAL', 1, 1),
('2026-08-30', 'Santa Rosa de Lima', 'FERIADO_LEGAL', 1, 1),
('2026-10-08', 'Combate de Angamos', 'FERIADO_LEGAL', 1, 1),
('2026-11-01', 'Día de Todos los Santos', 'FERIADO_LEGAL', 1, 1),
('2026-12-08', 'Inmaculada Concepción', 'FERIADO_LEGAL', 1, 1),
('2026-12-09', 'Batalla de Ayacucho', 'FERIADO_LEGAL', 1, 1),
('2026-12-25', 'Navidad', 'FERIADO_LEGAL', 1, 1),
-- 2027
('2027-01-01', 'Año Nuevo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-03-25', 'Jueves Santo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-03-26', 'Viernes Santo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-05-01', 'Día del Trabajo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-06-07', 'Batalla de Arica y Día de la Bandera 2027', 'FERIADO_LEGAL', 1, 1),
('2027-06-29', 'San Pedro y San Pablo 2027', 'FERIADO_LEGAL', 1, 1),
('2027-07-23', 'Día de la Fuerza Aérea del Perú 2027', 'FERIADO_LEGAL', 1, 1),
('2027-07-28', 'Fiestas Patrias 2027', 'FERIADO_LEGAL', 1, 1),
('2027-07-29', 'Fiestas Patrias 2027 (Segundo día)', 'FERIADO_LEGAL', 1, 1),
('2027-08-06', 'Batalla de Junín 2027', 'FERIADO_LEGAL', 1, 1),
('2027-08-30', 'Santa Rosa de Lima 2027', 'FERIADO_LEGAL', 1, 1),
('2027-10-08', 'Combate de Angamos 2027', 'FERIADO_LEGAL', 1, 1),
('2027-11-01', 'Día de Todos los Santos 2027', 'FERIADO_LEGAL', 1, 1),
('2027-12-08', 'Inmaculada Concepción 2027', 'FERIADO_LEGAL', 1, 1),
('2027-12-09', 'Batalla de Ayacucho 2027', 'FERIADO_LEGAL', 1, 1),
('2027-12-25', 'Navidad 2027', 'FERIADO_LEGAL', 1, 1)
ON DUPLICATE KEY UPDATE
    `descripcion` = VALUES(`descripcion`),
    `tipo` = VALUES(`tipo`),
    `aplica_sector_privado` = VALUES(`aplica_sector_privado`),
    `activo` = VALUES(`activo`);

-- ----------------------------------------------------------------------------
-- 2. Control Concurrente de Correlativos por Sede y Año Fiscal
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reclamacion_secuencias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al establecimiento comercial físico',
    `anio` INT UNSIGNED NOT NULL COMMENT 'Año fiscal de la secuencia (ej. 2026)',
    `ultimo_numero` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Correlativo incremental asegurado con SELECT FOR UPDATE',
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rec_secuencias_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_rec_secuencia_propiedad_anio` (`propiedad_id`, `anio`),
    INDEX `idx_rec_secuencias_propiedad` (`propiedad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Secuencias correlativas atómicas de hojas de reclamación por sede y año';

-- ----------------------------------------------------------------------------
-- 3. Empresa Operadora Principal (Emisor Legal para Reclamaciones D-091)
-- ----------------------------------------------------------------------------
INSERT INTO `empresas` (
    `id`, `codigo`, `tipo_documento_id`, `numero_documento`, `razon_social`, `nombre_comercial`,
    `direccion_fiscal`, `pais_id`, `departamento`, `provincia`, `distrito`, `es_principal`, `estado`
)
SELECT 1, 'CAMARGO-HOSTELERIA', td.`id`, '20601234567', 'CAMARGO HOSTELERÍA S.A.C.', 'Camargo Hostelería',
       'Av. Larco 101, Miraflores', 1, 'Lima', 'Lima', 'Miraflores', 1, 'ACTIVO'
FROM `tipos_documento` td
WHERE td.`codigo` = 'RUC'
ON DUPLICATE KEY UPDATE `codigo` = VALUES(`codigo`);

UPDATE `propiedades` SET `empresa_id` = 1 WHERE `empresa_id` IS NULL;

-- ----------------------------------------------------------------------------
-- 4. Tabla Maestra de Expedientes de Reclamaciones
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reclamaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_hoja` VARCHAR(30) NOT NULL COMMENT 'Numeración correlativa exigida por el Libro para el establecimiento',
    `codigo_interno` VARCHAR(40) NOT NULL COMMENT 'Identificador técnico estable Camargo PMS: LR-{SEDE}-{AAAA}-{NNNNN}',
    `empresa_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al proveedor emisor responsable legal',
    `propiedad_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a la sede física o establecimiento donde ocurrió el hecho',
    `consumidor_persona_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al consumidor reclamante en el maestro soberano de personas',
    `es_menor_edad` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el consumidor es menor de edad y se registró con apoderado',
    `apoderado_persona_id` BIGINT UNSIGNED NULL COMMENT 'FK a la persona del padre/madre/tutor o apoderado legal',
    `tipo` ENUM('RECLAMO', 'QUEJA') NOT NULL COMMENT 'Tipificación legal excluyente según D.S. 011-2011-PCM',
    `tipo_bien` ENUM('PRODUCTO', 'SERVICIO') NOT NULL DEFAULT 'SERVICIO' COMMENT 'Bien contratado en disputa',
    `monto_reclamado` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe económico reclamado (0.00 si no aplica)',
    `moneda` VARCHAR(3) NOT NULL DEFAULT 'PEN' COMMENT 'Código ISO de la moneda (PEN, USD)',
    `descripcion_bien` VARCHAR(255) NOT NULL COMMENT 'Descripción sucinta del producto o servicio contratado',
    `detalle_reclamacion` TEXT NOT NULL COMMENT 'Relato inmutable de los hechos expuestos por el consumidor',
    `pedido_consumidor` TEXT NOT NULL COMMENT 'Pretensión concreta que solicita el consumidor',
    `canal_entrada` ENUM('VIRTUAL', 'PRESENCIAL') NOT NULL DEFAULT 'VIRTUAL' COMMENT 'Canal de interposición del reclamo',
    `estado` ENUM('REGISTRADO', 'EN_PROCESO', 'SUSPENDIDO_OFRECIMIENTO', 'ATENDIDO', 'CONCLUIDO_POR_ACUERDO', 'ANULADO') NOT NULL DEFAULT 'REGISTRADO' COMMENT 'Estado regulatorio del expediente',
    `fecha_interposicion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora exacta de presentación de la Hoja',
    `fecha_limite_legal` DATE NOT NULL COMMENT 'Fecha límite legal computable (15 días hábiles según Ley 31435)',
    `dias_habiles_consumidos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Días hábiles transcurridos hasta la fecha o suspensión',
    `fecha_suspension` DATE NULL COMMENT 'Fecha en que se formuló ofrecimiento de solución',
    `fecha_limite_ofrecimiento` DATE NULL COMMENT 'Plazo máximo de 5 días hábiles para pronunciamiento del consumidor',
    `documento_emitido_id` BIGINT UNSIGNED NULL COMMENT 'FK a documentos_emitidos para la representación PDF oficial',
    `motivo_anulacion` TEXT NULL COMMENT 'Obligatorio ante anulación formal supervisada',
    `anulado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'FK a actores para el responsable de anulación',
    `anulado_en` DATETIME NULL COMMENT 'Momento en que se anuló el expediente',
    `snapshot_consumidor_json` JSON NOT NULL COMMENT 'Snapshot legal inmutable T0 de identidad civil, domicilio y contactos del consumidor y apoderado',
    `snapshot_proveedor_json` JSON NOT NULL COMMENT 'Snapshot legal inmutable T0 de razón social, RUC, domicilio fiscal y datos del establecimiento',
    `creado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'FK a actores creador (null si es autoservicio público)',
    `actualizado_por_actor_id` BIGINT UNSIGNED NULL COMMENT 'FK a actores que modificó el estado o expediente',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_rec_codigo_hoja_no_vacio` CHECK (`codigo_hoja` <> ''),
    CONSTRAINT `chk_rec_codigo_interno_no_vacio` CHECK (`codigo_interno` <> ''),
    CONSTRAINT `chk_rec_descripcion_no_vacia` CHECK (`descripcion_bien` <> ''),
    CONSTRAINT `chk_rec_detalle_no_vacio` CHECK (`detalle_reclamacion` <> ''),
    CONSTRAINT `chk_rec_pedido_no_vacio` CHECK (`pedido_consumidor` <> ''),
    CONSTRAINT `fk_reclamaciones_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_consumidor` FOREIGN KEY (`consumidor_persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_apoderado` FOREIGN KEY (`apoderado_persona_id`) REFERENCES `personas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_doc_emitido` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_reclamaciones_actor_actualizador` FOREIGN KEY (`actualizado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uq_reclamaciones_codigo_interno` (`codigo_interno`),
    INDEX `idx_reclamaciones_propiedad_hoja` (`propiedad_id`, `codigo_hoja`),
    INDEX `idx_reclamaciones_consumidor` (`consumidor_persona_id`),
    INDEX `idx_reclamaciones_estado` (`estado`),
    INDEX `idx_reclamaciones_tipo` (`tipo`),
    INDEX `idx_reclamaciones_fecha_interposicion` (`fecha_interposicion`),
    INDEX `idx_reclamaciones_fecha_limite` (`fecha_limite_legal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Expedientes regulatorios del Libro de Reclamaciones';

-- ----------------------------------------------------------------------------
-- 5. Trazabilidad Append-Only de Actuaciones del Expediente
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reclamacion_actuaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reclamacion_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al expediente regulatorio',
    `actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK al actor que ejecuta la actuación',
    `tipo_actuacion` ENUM(
        'RECEPCION_INICIAL',
        'NOTA_INTERNA',
        'OFRECIMIENTO_SOLUCION',
        'RESPUESTA_OFRECIMIENTO_ACEPTADO',
        'RESPUESTA_OFRECIMIENTO_RECHAZADO',
        'EXPIRACION_OFRECIMIENTO',
        'RESPUESTA_FORMAL',
        'NOTIFICACION_ENVIADA',
        'ANULACION_SUPERVISADA'
    ) NOT NULL COMMENT 'Tipificación de la actuación legal o administrativa',
    `descripcion` TEXT NOT NULL COMMENT 'Detalle del acto, descargo, contenido de la respuesta u observaciones',
    `medio_notificacion` ENUM('CORREO_ELECTRONICO', 'CARTA_NOTARIAL', 'FISICO_RECEPCION', 'SISTEMA_WEB') NULL COMMENT 'Medio probatorio de notificación al consumidor',
    `destinatario_notificacion` VARCHAR(150) NULL COMMENT 'Correo o dirección probatoria notificada',
    `fecha_notificacion` DATETIME NULL COMMENT 'Fecha y hora en que se materializó la notificación',
    `documento_emitido_id` BIGINT UNSIGNED NULL COMMENT 'FK a documentos_emitidos en caso de cartas o resoluciones formales generadas',
    `archivo_adjunto_path` VARCHAR(255) NULL COMMENT 'Ruta interna controlada en almacenamiento local para evidencias',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_rec_actuaciones_desc_no_vacia` CHECK (`descripcion` <> ''),
    CONSTRAINT `fk_rec_actuaciones_reclamacion` FOREIGN KEY (`reclamacion_id`) REFERENCES `reclamaciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rec_actuaciones_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rec_actuaciones_doc_emitido` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_rec_actuaciones_reclamacion` (`reclamacion_id`),
    INDEX `idx_rec_actuaciones_tipo` (`tipo_actuacion`),
    INDEX `idx_rec_actuaciones_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial append-only de actuaciones y respuestas del expediente';

-- ----------------------------------------------------------------------------
-- 6. Adaptación del Motor Documental (D-079 / RECLAMACIONES-1)
-- ----------------------------------------------------------------------------
ALTER TABLE `documento_plantillas`
    MODIFY COLUMN `origen_tipo_permitido` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA', 'RECLAMACION') NOT NULL;

ALTER TABLE `documentos_emitidos`
    MODIFY COLUMN `origen_tipo` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO', 'COMPRA', 'RECLAMACION') NOT NULL;

INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`
) VALUES (
    'HOJA_RECLAMACION',
    'Hoja de Reclamación Oficial A4',
    'Formato regulatorio estandarizado de Hoja de Reclamación según D.S. 011-2011-PCM y Ley 29571',
    'RECLAMACION',
    'PORTRAIT',
    'A4',
    0,
    15, 15, 15, 15,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`, `cuerpo_html`, `es_activa`, `creado_por_actor_id`
)
SELECT p.`id`, 1, 'Hoja de Reclamación Oficial', '<!-- Template gestionado por ReclamacionDocumentoServicio -->', 1, 1
FROM `documento_plantillas` p
WHERE p.`codigo` = 'HOJA_RECLAMACION'
ON DUPLICATE KEY UPDATE `titulo_documento` = VALUES(`titulo_documento`);

-- ----------------------------------------------------------------------------
-- 7. Permisos RBAC para Reclamaciones y Feriados
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`) VALUES
('reclamaciones.ver', 'Ver libro y expedientes de reclamaciones', 'reclamaciones', 'Permite consultar el listado y fichas de expedientes de reclamos y quejas'),
('reclamaciones.crear', 'Registrar reclamación asistida en recepción', 'reclamaciones', 'Permite ingresar reclamaciones presenciales desde la consola interna'),
('reclamaciones.gestionar', 'Gestionar expedientes y calendario de feriados', 'reclamaciones', 'Permite administrar el estado de expedientes y el catálogo de días no laborables'),
('reclamaciones.actuar', 'Añadir notas y actuaciones al expediente', 'reclamaciones', 'Permite asentar notas internas y diligencias append-only'),
('reclamaciones.responder', 'Formular ofrecimientos y emitir respuesta formal', 'reclamaciones', 'Permite formular ofrecimientos de solución y emitir la respuesta oficial al consumidor'),
('reclamaciones.anular', 'Anulación supervisada de expedientes', 'reclamaciones', 'Permite anular expedientes por duplicidad técnica comprobada con motivo obligatorio')
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

-- Asignar permisos al rol SUPERADMINISTRADOR (rol_id = 1)
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.`id`
FROM `permisos` p
WHERE p.`modulo` = 'reclamaciones'
ON DUPLICATE KEY UPDATE `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- 8. Opciones de Menú en Navegación Administrativa Alina
-- ----------------------------------------------------------------------------
-- Opción 1: Directorio del Libro de Reclamaciones (Bajo categoría Reservas / Operaciones)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    p.`id`,
    'reclamaciones_directorio',
    'Libro de Reclamaciones',
    'fa-solid fa-book-open-reader',
    '/reclamaciones',
    17,
    'ACTIVO',
    perm.`id`,
    1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reclamaciones.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- Opción 2: Calendario de Feriados (Bajo categoría Configuración)
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT
    p.`id`,
    'config_feriados',
    'Calendario de Feriados',
    'fa-solid fa-calendar-days',
    '/configuracion/feriados',
    30,
    'ACTIVO',
    perm.`id`,
    1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'configuracion' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'reclamaciones.gestionar'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
