-- ============================================================================
-- Camargo PMS — Migración 016: Catálogo de Servicios, Proveedores y Consumos
-- Fase: SERVICIOS-1
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Maestro Extensible de Categorías de Servicio
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias_servicio` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único estable, ej. TRASLADOS',
    `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo visible',
    `descripcion` TEXT NULL,
    `icono` VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-bell-concierge' COMMENT 'Icono Font Awesome 6 (D-071)',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_categorias_servicio_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_categorias_servicio_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_categorias_servicio_codigo` (`codigo`),
    INDEX `idx_categorias_servicio_estado` (`estado`),
    INDEX `idx_categorias_servicio_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de categorías de servicios adicionales';

-- ----------------------------------------------------------------------------
-- 2. Maestro Extensible de Modalidades de Cobro
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `modalidades_cobro_servicio` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código canónico: FIJO, POR_PERSONA, POR_NOCHE, POR_DIA, POR_UNIDAD, POR_UNIDAD_CONSUMO',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `unidad_medida_sugerida` VARCHAR(30) NULL COMMENT 'Sugerencia para UI: servicio, persona, noche, día, unidad, etc.',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_modalidades_cobro_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_modalidades_cobro_nombre_no_vacio` CHECK (`nombre` <> ''),
    UNIQUE KEY `uq_modalidades_cobro_codigo` (`codigo`),
    INDEX `idx_modalidades_cobro_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo administrable de modalidades de cobro y tarificación';

-- ----------------------------------------------------------------------------
-- 3. Maestro de Proveedores Externos
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proveedores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único de negocio, ej. PROV-001',
    `tipo` ENUM('EMPRESA', 'PERSONA_NATURAL') NOT NULL DEFAULT 'EMPRESA',
    `persona_id` BIGINT UNSIGNED NULL COMMENT 'FK a personas si es persona natural del registro central',
    `razon_social` VARCHAR(200) NOT NULL COMMENT 'Razón social formal o nombre legal',
    `nombre_comercial` VARCHAR(200) NULL COMMENT 'Nombre de fantasía o marca',
    `numero_documento` VARCHAR(30) NULL COMMENT 'RUC de 11 dígitos u otro documento fiscal',
    `email` VARCHAR(150) NULL,
    `telefono` VARCHAR(50) NULL,
    `direccion` TEXT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `observaciones` TEXT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_proveedores_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_proveedores_razon_social_no_vacia` CHECK (`razon_social` <> ''),
    CONSTRAINT `fk_proveedores_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_proveedores_codigo` (`codigo`),
    INDEX `idx_proveedores_tipo` (`tipo`),
    INDEX `idx_proveedores_estado` (`estado`),
    INDEX `idx_proveedores_documento` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Maestro de proveedores externos de servicios (empresas y personas naturales)';

-- ----------------------------------------------------------------------------
-- 4. Catálogo Maestro de Servicios
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único del servicio, ej. SERV-TRF-AERO',
    `categoria_id` INT UNSIGNED NOT NULL,
    `modalidad_cobro_id` INT UNSIGNED NOT NULL,
    `propiedad_id` BIGINT UNSIGNED NULL COMMENT 'NULL si es global, o ID específico si aplica solo a un predio',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `precio_venta_referencial` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `costo_referencial` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `es_operacion_interna_habitual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si habitualmente lo presta Camargo con staff propio',
    `requiere_traslado_detalle` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si exige captura operativa en servicio_traslados',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_servicios_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_servicios_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_servicios_precio_no_negativo` CHECK (`precio_venta_referencial` >= 0.00),
    CONSTRAINT `chk_servicios_costo_no_negativo` CHECK (`costo_referencial` >= 0.00),
    CONSTRAINT `fk_servicios_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_servicio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_modalidad` FOREIGN KEY (`modalidad_cobro_id`) REFERENCES `modalidades_cobro_servicio` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_servicios_codigo` (`codigo`),
    INDEX `idx_servicios_categoria` (`categoria_id`),
    INDEX `idx_servicios_modalidad` (`modalidad_cobro_id`),
    INDEX `idx_servicios_propiedad` (`propiedad_id`),
    INDEX `idx_servicios_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de conceptos de servicios adicionales';

-- ----------------------------------------------------------------------------
-- 5. Matriz Homologada N:M Proveedor ↔ Servicio
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicio_proveedores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `servicio_id` BIGINT UNSIGNED NOT NULL,
    `proveedor_id` BIGINT UNSIGNED NOT NULL,
    `costo_pactado` DECIMAL(15, 2) NULL COMMENT 'Costo de compra específico acordado con este proveedor',
    `es_preferente` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es el proveedor sugerido por defecto',
    `tiempo_anticipacion_horas` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Horas mínimas de preaviso para coordinar',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `uq_preferente` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`es_preferente` = 1, `servicio_id`, NULL)) VIRTUAL COMMENT 'Garantiza máximo un proveedor preferente activo por servicio',
    CONSTRAINT `chk_sp_costo_no_negativo` CHECK (`costo_pactado` IS NULL OR `costo_pactado` >= 0.00),
    CONSTRAINT `fk_sp_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_servicio_proveedor` (`servicio_id`, `proveedor_id`),
    UNIQUE KEY `uq_sp_servicio_preferente` (`uq_preferente`),
    INDEX `idx_sp_servicio` (`servicio_id`),
    INDEX `idx_sp_proveedor` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Matriz N:M de proveedores externos homologados por servicio';

-- ----------------------------------------------------------------------------
-- 6. Servicios Contratados y Consumos Imputados
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicios_contratados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código único de contratación, ej. SC-20261015-0001',
    `reserva_id` BIGINT UNSIGNED NOT NULL COMMENT 'Reserva comercial titular obligatoria (fuente de verdad del folio)',
    `estadia_id` BIGINT UNSIGNED NULL COMMENT 'Estadía física opcional si fue consumido in situ en una habitación',
    `servicio_id` BIGINT UNSIGNED NOT NULL COMMENT 'Concepto del catálogo',
    `proveedor_id` BIGINT UNSIGNED NULL COMMENT 'Proveedor asignado. NULL indica operación interna por staff de Camargo',
    
    -- Snapshots históricos inmutables congelados al contratar (D-010 / D-069)
    `descripcion_servicio_snapshot` VARCHAR(150) NOT NULL,
    `categoria_codigo_snapshot` VARCHAR(50) NOT NULL,
    `modalidad_cobro_codigo_snapshot` VARCHAR(50) NOT NULL,
    `es_operacion_interna` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si proveedor_id IS NULL (personal propio)',
    `cantidad` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `precio_unitario` DECIMAL(15, 2) NOT NULL,
    `costo_unitario` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15, 2) NOT NULL,
    `tasa_impuesto` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000 COMMENT 'Tasa tributaria porcentual congelada (0.0000 bajo política fiscal actual)',
    `impuesto_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT '0.00 actual sin motor tributario',
    `total` DECIMAL(15, 2) NOT NULL,
    `costo_total` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    
    -- Ciclo de vida y tiempos operativos
    `estado` ENUM('SOLICITADO', 'CONFIRMADO', 'EJECUTADO', 'CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
    `fecha_servicio` DATE NOT NULL COMMENT 'Fecha prevista para la prestación',
    `hora_servicio` TIME NULL COMMENT 'Hora prevista (si aplica)',
    `ejecutado_en` DATETIME NULL COMMENT 'Instante técnico UTC en que se ejecutó/entregó físicamente',
    `cancelada_en` DATETIME NULL COMMENT 'Instante técnico UTC en que se canceló',
    `motivo_cancelacion` VARCHAR(255) NULL,
    `observaciones` TEXT NULL,
    
    -- Trazabilidad y autoría D-061 (ACTOR != USUARIO)
    `solicitado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `ejecutado_por_actor_id` BIGINT UNSIGNED NULL,
    `cancelada_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `chk_sc_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_sc_cantidad_positiva` CHECK (`cantidad` > 0.00),
    CONSTRAINT `chk_sc_precio_no_negativo` CHECK (`precio_unitario` >= 0.00),
    CONSTRAINT `chk_sc_costo_no_negativo` CHECK (`costo_unitario` >= 0.00),
    CONSTRAINT `chk_sc_coherencia_operacion_interna` CHECK (
        (`es_operacion_interna` = 1 AND `proveedor_id` IS NULL) OR 
        (`es_operacion_interna` = 0 AND `proveedor_id` IS NOT NULL)
    ),
    CONSTRAINT `chk_sc_cancelacion_coherente` CHECK (
        (`estado` <> 'CANCELADO' AND `cancelada_en` IS NULL AND `cancelada_por_actor_id` IS NULL AND `motivo_cancelacion` IS NULL) OR
        (`estado` = 'CANCELADO' AND `cancelada_en` IS NOT NULL AND `cancelada_por_actor_id` IS NOT NULL AND `motivo_cancelacion` IS NOT NULL)
    ),
    CONSTRAINT `fk_sc_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_estadia` FOREIGN KEY (`estadia_id`) REFERENCES `estadias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sc_actor_solicitado` FOREIGN KEY (`solicitado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_actor_ejecutado` FOREIGN KEY (`ejecutado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sc_actor_cancelado` FOREIGN KEY (`cancelada_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY `uq_sc_codigo` (`codigo`),
    INDEX `idx_sc_reserva` (`reserva_id`),
    INDEX `idx_sc_estadia` (`estadia_id`),
    INDEX `idx_sc_servicio` (`servicio_id`),
    INDEX `idx_sc_proveedor` (`proveedor_id`),
    INDEX `idx_sc_estado` (`estado`),
    INDEX `idx_sc_fecha_servicio` (`fecha_servicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro transaccional de servicios contratados y consumos';

-- ----------------------------------------------------------------------------
-- 7. Extensión Logística Especializada 1:1 de Traslados
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicio_traslados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `servicio_contratado_id` BIGINT UNSIGNED NOT NULL COMMENT 'Relación 1:1 estricta con el servicio contratado',
    `tipo_traslado` ENUM('LLEGADA', 'SALIDA') NOT NULL COMMENT 'LLEGADA al predio o SALIDA hacia terminal/aeropuerto/otro',
    `origen` VARCHAR(150) NOT NULL COMMENT 'Punto de partida físico (Aeropuerto, Terminal, Edificio, Dirección)',
    `destino` VARCHAR(150) NOT NULL COMMENT 'Punto de llegada físico',
    `fecha_hora_recogida` DATETIME NOT NULL COMMENT 'Fecha y hora local acordada para esperar al huésped',
    `aerolinea_empresa` VARCHAR(100) NULL COMMENT 'Aerolínea o empresa de transporte (LATAM, Sky, Cruz del Sur, etc.)',
    `numero_vuelo_viaje` VARCHAR(50) NULL COMMENT 'Número de vuelo o viaje (ej. LA2045)',
    `cantidad_pasajeros` INT UNSIGNED NOT NULL DEFAULT 1,
    `cantidad_maletas` INT UNSIGNED NOT NULL DEFAULT 0,
    `datos_conductor_vehiculo` VARCHAR(255) NULL COMMENT 'Nombre del chofer, teléfono, modelo y placa del vehículo',
    `instrucciones_recogida` TEXT NULL COMMENT 'Detalles para el encuentro (cartel con nombre en puerta de salida, etc.)',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_st_origen_no_vacio` CHECK (`origen` <> ''),
    CONSTRAINT `chk_st_destino_no_vacio` CHECK (`destino` <> ''),
    CONSTRAINT `chk_st_pasajeros_minimo` CHECK (`cantidad_pasajeros` >= 1),
    CONSTRAINT `fk_st_servicio_contratado` FOREIGN KEY (`servicio_contratado_id`) REFERENCES `servicios_contratados` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_st_servicio_contratado` (`servicio_contratado_id`),
    INDEX `idx_st_fecha_hora_recogida` (`fecha_hora_recogida`),
    INDEX `idx_st_numero_vuelo` (`numero_vuelo_viaje`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Extensión 1:1 con detalles logísticos para servicios de traslado';

-- ----------------------------------------------------------------------------
-- 8. Datos Estructurales Iniciales (Semillas)
-- ----------------------------------------------------------------------------

-- Categorías iniciales administrables
INSERT INTO `categorias_servicio` (`codigo`, `nombre`, `descripcion`, `icono`, `orden`, `estado`) VALUES
('TRASLADOS', 'Traslados y Transporte', 'Servicios de transfer de llegada y salida (aeropuerto, terminales, movilidad privada)', 'fa-solid fa-van-shuttle', 10, 'ACTIVO'),
('ALIMENTOS_BEBIDAS', 'Alimentos y Bebidas', 'Desayunos, cafetería, minibar, snacks y botellas de agua', 'fa-solid fa-utensils', 20, 'ACTIVO'),
('LAVANDERIA', 'Lavandería y Planchado', 'Lavado, secado y planchado por prenda o docena', 'fa-solid fa-shirt', 30, 'ACTIVO'),
('LIMPIEZA', 'Limpieza y Mantenimiento Extra', 'Limpieza profunda adicional o recambio extra de toallas y sábanas', 'fa-solid fa-broom', 40, 'ACTIVO'),
('TURISMO_TOURS', 'Tours y Excursiones', 'Tours turísticos, paquetes guiados y paseos en la ciudad y alrededores', 'fa-solid fa-map-location-dot', 50, 'ACTIVO'),
('ESTACIONAMIENTO', 'Estacionamiento y Cochera', 'Uso de cocheras y espacios de parqueo vehicular', 'fa-solid fa-square-parking', 60, 'ACTIVO'),
('BIENESTAR_SPA', 'Bienestar y Spa', 'Masajes relajantes, terapias y cuidado personal', 'fa-solid fa-spa', 70, 'ACTIVO'),
('OTROS', 'Otros Servicios', 'Early check-in fee, late check-out flat, alquiler de cunas y equipamiento adicional', 'fa-solid fa-ellipsis', 99, 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Modalidades de cobro iniciales administrables
INSERT INTO `modalidades_cobro_servicio` (`codigo`, `nombre`, `descripcion`, `unidad_medida_sugerida`, `estado`) VALUES
('FIJO', 'Monto Fijo por Servicio', 'Cobro único por servicio, independiente de personas o duración', 'servicio', 'ACTIVO'),
('POR_PERSONA', 'Por Persona', 'Multiplicado por el número de personas beneficiarias', 'persona', 'ACTIVO'),
('POR_NOCHE', 'Por Noche', 'Multiplicado por las noches de la estadía o reserva', 'noche', 'ACTIVO'),
('POR_DIA', 'Por Día', 'Multiplicado por los días de duración del alquiler o servicio', 'día', 'ACTIVO'),
('POR_UNIDAD', 'Por Unidad Medida', 'Cobro genérico por unidades cuantificables (prendas, maletas, vehículos)', 'unidad', 'ACTIVO'),
('POR_UNIDAD_CONSUMO', 'Por Consumo Medido', 'Cobro por cantidades consumidas registradas (bebidas, snacks de minibar)', 'consumo', 'ACTIVO')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Permisos RBAC iniciales
INSERT INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `estado`, `es_sistema`) VALUES
('servicios.ver', 'Ver catálogo y consumos de servicios', 'Consultar catálogo de servicios, proveedores y consumos contratados', 'servicios', 'ACTIVO', 1),
('servicios.gestionar', 'Gestionar catálogo de servicios y proveedores', 'Crear y modificar servicios del catálogo y proveedores homologados', 'servicios', 'ACTIVO', 1),
('servicios.contratar', 'Contratar servicios y consumos', 'Registrar contratación de servicios para reservas y estadías', 'servicios', 'ACTIVO', 1),
('servicios.ejecutar', 'Ejecutar servicios contratados', 'Marcar servicios como ejecutados/entregados físicamente', 'servicios', 'ACTIVO', 1),
('servicios.cancelar', 'Cancelar contratación de servicios', 'Anular contratación de servicios con registro de motivo', 'servicios', 'ACTIVO', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Asignación de permisos a SUPERADMINISTRADOR
INSERT INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.codigo = 'SUPERADMINISTRADOR'
  AND p.codigo IN (
      'servicios.ver',
      'servicios.gestionar',
      'servicios.contratar',
      'servicios.ejecutar',
      'servicios.cancelar'
  )
ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`);

-- Nivel 2: Opción Secundaria 'Servicios y Consumos' bajo 'reservas'
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'servicios_catalogo', 'Servicios y Consumos', 'fa-solid fa-concierge-bell', '/servicios', 3, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'servicios.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
