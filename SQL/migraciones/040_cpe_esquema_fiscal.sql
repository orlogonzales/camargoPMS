-- ============================================================================
-- Camargo PMS — Migración 040: Esquema Relacional de Comprobantes de Pago
-- Electrónicos (CPE / SUNAT) y Facturación Fiscal (SUNAT-1C1)
-- ============================================================================
-- Principios Arquitectónicos y Vinculantes:
-- 1. SEPARACIÓN ONTOLÓGICA SOBERANA:
--    CARGO != PAGO != APLICACIÓN != RECIBO != FOLIO != CPE.
--    El CPE es una entidad fiscal soberana que no muta los saldos contables,
--    devengos de Night Audit ni aplicaciones financieras de cobro.
-- 2. SNAPSHOTS FISCALES INMUTABLES T0:
--    Congela datos legales del emisor y receptor al momento exacto de emisión.
--    Modificaciones futuras en empresas, propiedades o personas no alteran el CPE.
-- 3. SECUENCIA FISCAL ATÓMICA Y AISLADA:
--    Correlativos administrados en cpe_series por establecimiento, tipo y serie.
-- 4. DETALLE TRIBUTARIO EN DECIMAL ESTRICTO:
--    Líneas fiscales con bases imponibles, afectaciones IGV (Catálogo 07) y totales.
-- 5. TRAZABILIDAD M:N ENTRE LÍNEAS CPE Y CARGOS OPERATIVOS:
--    Tabla cpe_linea_cargos para vincular importes documentados y evitar doble facturación.
-- 6. CARDINALIDAD FLEXIBLE DE DOCUMENTOS RELACIONADOS:
--    cpe_documentos_relacionados soporta notas de crédito/débito internas y externas (Catálogo 09/10).
-- 7. DESACOPLE RIGUROSO DE ENVÍOS Y RESPUESTAS (1 -> 0..N):
--    cpe_envios registra cada intento técnico de transmisión; cpe_respuestas almacena CDRs y resultados.
-- 8. INTEGRIDAD REFERENCIAL Y CERO SECRETOS:
--    Todas las FK hacia entidades históricas usan ON DELETE RESTRICT.
--    No se almacenan certificados, llaves privadas, passwords ni claves SOL en base de datos.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Configuración de Establecimientos Fiscales del Emisor
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_establecimientos_configuracion` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia empresa titular',
    `propiedad_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional hacia propiedad hotelera si corresponde a un inmueble físico',
    `codigo_establecimiento_sunat` CHAR(4) NOT NULL DEFAULT '0000' COMMENT 'Código de anexo SUNAT de 4 dígitos (0000 = matriz/domicilio fiscal)',
    `razon_social_snapshot` VARCHAR(255) NOT NULL COMMENT 'Razón social legal del emisor',
    `nombre_comercial` VARCHAR(255) NULL COMMENT 'Nombre comercial del emisor',
    `direccion_fiscal` VARCHAR(255) NOT NULL COMMENT 'Dirección fiscal declarada del establecimiento',
    `ubigeo` VARCHAR(10) NOT NULL COMMENT 'Código de ubigeo INEI (6 dígitos)',
    `departamento` VARCHAR(100) NOT NULL COMMENT 'Departamento geográfico',
    `provincia` VARCHAR(100) NOT NULL COMMENT 'Provincia geográfica',
    `distrito` VARCHAR(100) NOT NULL COMMENT 'Distrito geográfico',
    `modo_entorno` ENUM('BETA', 'PRODUCCION') NOT NULL DEFAULT 'BETA' COMMENT 'Entorno operativo SUNAT',
    `proveedor_transporte_default` VARCHAR(50) NOT NULL DEFAULT 'SUNAT_SOAP_DIRECTO' COMMENT 'Adaptador técnico de transporte por defecto',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado operativo del establecimiento',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cpe_estab_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_estab_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_estab_anexo` CHECK (`codigo_establecimiento_sunat` REGEXP '^[0-9]{4}$'),
    UNIQUE KEY `uq_cpe_estab_anexo` (`empresa_id`, `codigo_establecimiento_sunat`),
    UNIQUE KEY `uq_cpe_estab_propiedad` (`propiedad_id`),
    INDEX `idx_cpe_estab_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Parámetros y establecimientos fiscales del emisor ante SUNAT';

-- ----------------------------------------------------------------------------
-- 2. Series y Correlativos Fiscales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_series` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `emisor_establecimiento_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia establecimiento emisor',
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'NOTA_CREDITO', 'NOTA_DEBITO') NOT NULL COMMENT 'Tipo de comprobante CPE',
    `serie` CHAR(4) NOT NULL COMMENT 'Serie alfanumérica de 4 caracteres (F### o B###)',
    `ultimo_correlativo` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Último correlativo emitido, evoluciona monótonamente',
    `prefijo_tipo` CHAR(1) NOT NULL COMMENT 'F para facturas y notas vinculadas; B para boletas y notas vinculadas',
    `descripcion` VARCHAR(150) NULL COMMENT 'Descripción operativa de la serie',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado operativo de la serie',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cpe_series_estab` FOREIGN KEY (`emisor_establecimiento_id`) REFERENCES `cpe_establecimientos_configuracion` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_serie_formato` CHECK (`serie` REGEXP '^[FB][A-Z0-9]{3}$'),
    CONSTRAINT `chk_cpe_serie_prefijo` CHECK (`prefijo_tipo` IN ('F', 'B')),
    UNIQUE KEY `uq_cpe_serie_tipo` (`emisor_establecimiento_id`, `tipo_comprobante`, `serie`),
    INDEX `idx_cpe_series_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Series fiscales y control de numeración correlativa concurrente';

-- ----------------------------------------------------------------------------
-- 3. Agregado Raíz: Comprobantes de Pago Electrónicos (CPE)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_comprobantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `emisor_establecimiento_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_establecimientos_configuracion',
    `serie_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_series',
    `cuenta_folio_id` BIGINT UNSIGNED NULL COMMENT 'FK opcional hacia cuentas_folios de origen',
    `tipo_comprobante` ENUM('FACTURA', 'BOLETA', 'NOTA_CREDITO', 'NOTA_DEBITO') NOT NULL COMMENT 'Tipo oficial de comprobante',
    `serie` CHAR(4) NOT NULL COMMENT 'Serie fiscal del comprobante',
    `correlativo` INT UNSIGNED NOT NULL COMMENT 'Número correlativo asignado desde 1',
    `codigo_folio_completo` VARCHAR(20) NOT NULL COMMENT 'Concatenación canónica (ej. F001-00000001)',
    `clave_idempotencia` VARCHAR(128) NOT NULL COMMENT 'Token criptográfico para prevenir emisiones duplicadas',

    -- Snapshot Fiscal del Emisor (T0)
    `emisor_ruc` CHAR(11) NOT NULL COMMENT 'RUC de 11 dígitos',
    `emisor_razon_social` VARCHAR(255) NOT NULL COMMENT 'Razón social del emisor al momento de emisión',
    `emisor_nombre_comercial` VARCHAR(255) NULL COMMENT 'Nombre comercial del emisor',
    `emisor_direccion_fiscal` VARCHAR(255) NOT NULL COMMENT 'Dirección fiscal del emisor',
    `emisor_ubigeo` VARCHAR(10) NOT NULL COMMENT 'Código de ubigeo INEI (6 dígitos)',
    `emisor_codigo_establecimiento` CHAR(4) NOT NULL DEFAULT '0000' COMMENT 'Código anexo SUNAT (4 dígitos)',
    `emisor_departamento` VARCHAR(100) NOT NULL,
    `emisor_provincia` VARCHAR(100) NOT NULL,
    `emisor_distrito` VARCHAR(100) NOT NULL,

    -- Snapshot Fiscal del Receptor (T0)
    `receptor_tipo_documento` CHAR(1) NOT NULL COMMENT 'Catálogo 06 SUNAT (6=RUC, 1=DNI, 4=Carnet Ext, 7=Pasaporte, 0=Sin Doc)',
    `receptor_numero_documento` VARCHAR(30) NOT NULL COMMENT 'Número de documento de identidad fiscal',
    `receptor_razon_social` VARCHAR(255) NOT NULL COMMENT 'Razón social o apellidos y nombres del cliente adquirente',
    `receptor_direccion_fiscal` VARCHAR(255) NULL COMMENT 'Dirección declarada del receptor',
    `receptor_ubigeo` VARCHAR(10) NULL COMMENT 'Ubigeo del receptor',
    `receptor_email` VARCHAR(150) NULL COMMENT 'Correo electrónico para entrega del comprobante',
    `receptor_pais_codigo` CHAR(2) NOT NULL DEFAULT 'PE' COMMENT 'Código ISO 3166-1 alfa-2 del país del receptor',

    -- Snapshot Régimen de Hospedaje No Domiciliado (DL 919)
    `es_exportacion_hospedaje` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 si califica como exportación de servicio de hospedaje',
    `hospedaje_tam_virtual_numero` VARCHAR(50) NULL COMMENT 'Número de TAM Virtual o registro migratorio',
    `hospedaje_fecha_ingreso_pais` DATE NULL COMMENT 'Fecha de ingreso al país acreditada',
    `hospedaje_dias_permanencia` SMALLINT UNSIGNED NULL COMMENT 'Días acumulados en el país (<= 60)',
    `hospedaje_leyenda_tributaria` VARCHAR(255) NULL COMMENT 'Leyenda legal obligatoria para exportación de hospedaje',

    -- Moneda y Bases Imponibles / Totales
    `moneda_codigo` CHAR(3) NOT NULL DEFAULT 'PEN' COMMENT 'PEN, USD, EUR (ISO 4217)',
    `tipo_cambio` DECIMAL(10,4) NULL COMMENT 'Tipo de cambio oficial SUNAT si la moneda no es PEN',
    `total_operaciones_gravadas` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Base imponible gravada con IGV',
    `total_operaciones_exoneradas` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto de operaciones exoneradas',
    `total_operaciones_inafectas` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto de operaciones inafectas',
    `total_operaciones_exportacion` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto de operaciones de exportación de servicios',
    `total_operaciones_gratuitas` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto de transferencias a título gratuito',
    `total_igv` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Impuesto General a las Ventas (18%)',
    `total_descuentos` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de descuentos globales aplicados',
    `total_venta` DECIMAL(15,2) NOT NULL COMMENT 'Importe total a pagar del comprobante',

    -- Ejes Ortogonales de Estado
    `estado_generacion` ENUM('BORRADOR', 'EMITIDO') NOT NULL DEFAULT 'BORRADOR' COMMENT 'Eje 1: Ciclo de vida interno del comprobante',
    `estado_transmision` ENUM('NO_INICIADO', 'EN_PROCESO', 'TRANSMITIDO', 'FALLO_CONEXION') NOT NULL DEFAULT 'NO_INICIADO' COMMENT 'Eje 2: Despacho técnico al proveedor o SUNAT',
    `estado_fiscal_sunat` ENUM('PENDIENTE_ENVIO', 'ACEPTADO', 'ACEPTADO_OBSERVADO', 'RECHAZADO', 'BAJA_ACEPTADA', 'EXCEPCION_SISTEMA') NOT NULL DEFAULT 'PENDIENTE_ENVIO' COMMENT 'Eje 3: Calificación tributaria oficial según CDR',
    `estado_rectificacion` ENUM('ORIGINAL', 'RECTIFICADO_PARCIAL', 'ANULADO_TOTAL') NOT NULL DEFAULT 'ORIGINAL' COMMENT 'Eje 4: Afectación documental por notas vinculadas',

    -- Huellas Técnicas y Rutas de Archivos en Storage
    `codigo_hash_cpe` VARCHAR(100) NULL COMMENT 'DigestValue de la firma digital XML',
    `hash_xml_sha256` CHAR(64) NULL COMMENT 'Hash SHA-256 del XML firmado',
    `hash_pdf_sha256` CHAR(64) NULL COMMENT 'Hash SHA-256 de la representación impresa PDF',
    `ruta_archivo_xml` VARCHAR(255) NULL COMMENT 'Ruta relativa en storage del XML firmado',
    `ruta_archivo_pdf` VARCHAR(255) NULL COMMENT 'Ruta relativa en storage del PDF generado',

    -- Fechas, Usuario y Auditoría
    `fecha_emision` DATETIME NOT NULL COMMENT 'Fecha y hora oficial de emisión del comprobante',
    `fecha_vencimiento` DATE NULL COMMENT 'Fecha de vencimiento para operaciones al crédito',
    `creado_por_usuario_id` BIGINT UNSIGNED NOT NULL COMMENT 'Usuario del PMS responsable de la emisión',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_cpe_estab` FOREIGN KEY (`emisor_establecimiento_id`) REFERENCES `cpe_establecimientos_configuracion` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_serie` FOREIGN KEY (`serie_id`) REFERENCES `cpe_series` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_usuario` FOREIGN KEY (`creado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_correlativo_positivo` CHECK (`correlativo` > 0),
    CONSTRAINT `chk_cpe_total_no_negativo` CHECK (`total_venta` >= 0.00),
    UNIQUE KEY `uq_cpe_numero_fiscal` (`emisor_establecimiento_id`, `tipo_comprobante`, `serie`, `correlativo`),
    UNIQUE KEY `uq_cpe_idempotencia` (`emisor_establecimiento_id`, `clave_idempotencia`),
    INDEX `idx_cpe_folio` (`cuenta_folio_id`),
    INDEX `idx_cpe_emision` (`fecha_emision`),
    INDEX `idx_cpe_receptor_doc` (`receptor_tipo_documento`, `receptor_numero_documento`),
    INDEX `idx_cpe_estados` (`estado_generacion`, `estado_transmision`, `estado_fiscal_sunat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Agregado raíz de Comprobantes de Pago Electrónicos (Facturas, Boletas, Notas)';

-- ----------------------------------------------------------------------------
-- 4. Detalle de Ítems y Líneas Fiscales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cpe_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia comprobante contenedor',
    `numero_orden` INT UNSIGNED NOT NULL COMMENT 'Secuencia ordinal de la línea (1..N)',
    `codigo_producto_interno` VARCHAR(50) NULL COMMENT 'Código de producto o servicio en catálogo PMS',
    `codigo_producto_sunat` VARCHAR(50) NULL COMMENT 'Código de producto/servicio según catálogo UNSPSC SUNAT',
    `descripcion` VARCHAR(500) NOT NULL COMMENT 'Descripción del bien entregado o servicio prestado',
    `unidad_medida` VARCHAR(10) NOT NULL DEFAULT 'ZZ' COMMENT 'Código Catálogo 03 SUNAT (NIU=Unidad, ZZ=Servicio)',
    `cantidad` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 COMMENT 'Cantidad de unidades documentadas',
    `valor_unitario` DECIMAL(15,4) NOT NULL COMMENT 'Valor unitario de venta sin impuestos',
    `precio_unitario` DECIMAL(15,4) NOT NULL COMMENT 'Precio unitario de venta con impuestos incluidos',
    `descuento_monto` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Descuento monetario aplicado a la línea',
    `base_imponible` DECIMAL(15,2) NOT NULL COMMENT 'Base imponible tributaria de la línea',
    `tipo_afectacion_igv` CHAR(2) NOT NULL COMMENT 'Código Catálogo 07 SUNAT (10=Gravado, 20=Exonerado, 30=Inafecto, 40=Exportación)',
    `tasa_igv` DECIMAL(5,2) NOT NULL DEFAULT 18.00 COMMENT 'Porcentaje de IGV aplicado (18.00%)',
    `monto_igv` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Importe de IGV calculado',
    `total_linea` DECIMAL(15,2) NOT NULL COMMENT 'Importe total de la línea',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cpe_lineas_cpe` FOREIGN KEY (`cpe_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_linea_cant_pos` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_cpe_linea_tot_no_neg` CHECK (`total_linea` >= 0.00),
    UNIQUE KEY `uq_cpe_linea_orden` (`cpe_id`, `numero_orden`),
    INDEX `idx_cpe_linea_afectacion` (`tipo_afectacion_igv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Detalle tributario inmutable de líneas de bienes y servicios documentados';

-- ----------------------------------------------------------------------------
-- 5. Trazabilidad M:N entre Líneas Fiscales y Cargos Operativos del PMS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_linea_cargos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cpe_linea_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia línea fiscal cpe_lineas',
    `cargo_cuenta_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cargo operativo documentado cargos_cuenta',
    `cantidad_atribuida` DECIMAL(12,4) NOT NULL COMMENT 'Cantidad del cargo documentada por esta línea fiscal',
    `monto_atribuido` DECIMAL(15,2) NOT NULL COMMENT 'Monto monetario del cargo absorbido por esta línea fiscal',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cpe_lc_linea` FOREIGN KEY (`cpe_linea_id`) REFERENCES `cpe_lineas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_lc_cargo` FOREIGN KEY (`cargo_cuenta_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_lc_monto_pos` CHECK (`monto_atribuido` > 0),
    CONSTRAINT `chk_cpe_lc_cant_pos` CHECK (`cantidad_atribuida` > 0),
    INDEX `idx_cpe_lc_linea` (`cpe_linea_id`),
    INDEX `idx_cpe_lc_cargo` (`cargo_cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Trazabilidad contable e historial de atribución entre cargos del PMS y líneas CPE';

-- ----------------------------------------------------------------------------
-- 6. Documentos Fiscales Relacionados y Rectificaciones (Notas de Crédito / Débito)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_documentos_relacionados` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cpe_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia comprobante emisor (ej. Nota de Crédito o Débito)',
    `cpe_relacionado_id` BIGINT UNSIGNED NULL COMMENT 'FK hacia comprobante original en el PMS (NULL si es referencia externa o contingencia)',
    `tipo_documento_relacionado` CHAR(2) NOT NULL COMMENT 'Tipo de comprobante vinculado (01=Factura, 03=Boleta)',
    `serie_relacionada` CHAR(4) NOT NULL COMMENT 'Serie del comprobante vinculado',
    `correlativo_relacionado` INT UNSIGNED NOT NULL COMMENT 'Correlativo del comprobante vinculado',
    `fecha_emision_relacionada` DATE NULL COMMENT 'Fecha de emisión del comprobante vinculado',
    `codigo_tipo_relacion` CHAR(2) NOT NULL COMMENT 'Código de motivo: Catálogo 09 SUNAT (NC) o Catálogo 10 SUNAT (ND)',
    `descripcion_motivo` VARCHAR(255) NOT NULL COMMENT 'Sustento o descripción formal del motivo de la nota',
    `monto_ajustado` DECIMAL(15,2) NULL COMMENT 'Monto monetario de ajuste en rectificaciones parciales',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cpe_dr_cpe` FOREIGN KEY (`cpe_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_dr_relacionado` FOREIGN KEY (`cpe_relacionado_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_cpe_dr_no_autoreferencia` CHECK (`cpe_id` != `cpe_relacionado_id`),
    CONSTRAINT `chk_cpe_dr_motivo_no_vacio` CHECK (`descripcion_motivo` <> ''),
    UNIQUE KEY `uq_cpe_doc_relacionado` (`cpe_id`, `tipo_documento_relacionado`, `serie_relacionada`, `correlativo_relacionado`, `codigo_tipo_relacion`),
    INDEX `idx_cpe_dr_cpe` (`cpe_id`),
    INDEX `idx_cpe_dr_relacionado` (`cpe_relacionado_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Vínculos documentales y motivos para notas de crédito, débito y anticipos';

-- ----------------------------------------------------------------------------
-- 7. Histórico Append-Only de Intentos Técnicos de Envío
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_envios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cpe_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_comprobantes',
    `numero_intento` INT UNSIGNED NOT NULL COMMENT 'Número secuencial de intento (1, 2, 3...)',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL COMMENT 'Actor técnico o humano que ordenó el despacho',
    `adaptador_transporte` VARCHAR(50) NOT NULL COMMENT 'Nombre del adaptador técnico (SUNAT_SOAP_DIRECTO, PSE, OSE)',
    `tipo_operacion` ENUM('ENVIO_INDIVIDUAL', 'CONSULTA_ESTADO_CDR', 'ENVIO_RESUMEN', 'ENVIO_BAJA') NOT NULL DEFAULT 'ENVIO_INDIVIDUAL' COMMENT 'Tipo de operación técnica',
    `endpoint_url` VARCHAR(255) NOT NULL COMMENT 'URL del servicio web consumido',
    `peticion_hash_sha256` CHAR(64) NULL COMMENT 'Hash SHA-256 de la petición transmitida',
    `ticket_remoto` VARCHAR(100) NULL COMMENT 'Ticket retornado para consultas asíncronas',
    `estado_transmision` ENUM('ENVIANDO', 'TRANSMITIDO', 'TIMEOUT', 'FALLO_CONEXION', 'ERROR_CLIENTE', 'ERROR_SERVIDOR') NOT NULL DEFAULT 'ENVIANDO' COMMENT 'Resultado técnico del despacho',
    `codigo_http_recibido` SMALLINT UNSIGNED NULL COMMENT 'Código HTTP devuelto por el canal',
    `error_tecnico_sanitizado` VARCHAR(500) NULL COMMENT 'Mensaje de error técnico sanitizado (sin credenciales)',
    `iniciado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de inicio del intento',
    `finalizado_en` DATETIME NULL COMMENT 'Timestamp de finalización del intento',
    CONSTRAINT `fk_cpe_env_cpe` FOREIGN KEY (`cpe_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_env_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY `uq_cpe_envio_intento` (`cpe_id`, `numero_intento`),
    INDEX `idx_cpe_envio_estado` (`estado_transmision`),
    INDEX `idx_cpe_envio_ticket` (`ticket_remoto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial append-only de intentos y transmisiones técnicas de comprobantes';

-- ----------------------------------------------------------------------------
-- 8. Respuestas Fiscales, Constancias de Recepción y CDRs Oficiales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cpe_respuestas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cpe_envio_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_envios (intento específico origen)',
    `cpe_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK hacia cpe_comprobantes',
    `tipo_respuesta` ENUM('CDR_OFICIAL', 'CONSULTA_TICKET', 'ERROR_VALIDACION_PREVIA', 'OBSERVACION') NOT NULL COMMENT 'Naturaleza de la respuesta procesada',
    `codigo_respuesta_sunat` VARCHAR(20) NOT NULL COMMENT 'Código de respuesta fiscal (0 = Aceptado, 2xxx/3xxx = Rechazo)',
    `descripcion_sunat` TEXT NOT NULL COMMENT 'Descripción oficial contenida en el CDR o respuesta',
    `observaciones_json` JSON NULL COMMENT 'Observaciones técnicas de advertencia en formato JSON',
    `ruta_archivo_cdr_zip` VARCHAR(255) NULL COMMENT 'Ruta relativa en storage del archivo CDR ZIP firmado',
    `hash_cdr_sha256` CHAR(64) NULL COMMENT 'Hash SHA-256 del CDR ZIP para auditoría de integridad',
    `firmado_por_sunat` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 si incluye firma digital oficial de SUNAT',
    `recibido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de recepción de la respuesta',
    CONSTRAINT `fk_cpe_resp_envio` FOREIGN KEY (`cpe_envio_id`) REFERENCES `cpe_envios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cpe_resp_cpe` FOREIGN KEY (`cpe_id`) REFERENCES `cpe_comprobantes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX `idx_cpe_respuesta_codigo` (`codigo_respuesta_sunat`),
    INDEX `idx_cpe_respuesta_cpe` (`cpe_id`),
    INDEX `idx_cpe_respuesta_envio` (`cpe_envio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Respuestas técnicas, constancias de recepción y CDRs oficiales de SUNAT';

SET FOREIGN_KEY_CHECKS = 1;
