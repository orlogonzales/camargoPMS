-- ============================================================================
-- CAMARGO PMS — Migración 021: Motor Documental, Plantillas Versionadas y PDF
-- Gobernanza: D-079 (GATE P-007 / DOCUMENTOS-1)
-- Tablas:
--   1. documento_secuencias           (Generador atómico concurrency-safe de folios)
--   2. documento_plantillas           (Catálogo maestro de documentos del PMS)
--   3. documento_plantilla_versiones  (Versiones inmutables con activación única en InnoDB)
--   4. documentos_emitidos            (Snapshots inmutables, hash SHA-256 de PDF y metadatos)
--   5. documento_incidencias          (Auditoría formal de discrepancias físicas de archivos)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Secuencias Concurrency-Safe para Folios Documentales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_secuencias` (
    `tipo_documento` VARCHAR(40) NOT NULL COMMENT 'CONTRATO_ARRENDAMIENTO, RECIBO_PAGO, etc.',
    `periodo_ym` CHAR(6) NOT NULL COMMENT 'YYYYMM para reinicio mensual ordenado',
    `ultimo_correlativo` INT UNSIGNED NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tipo_documento`, `periodo_ym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Secuencias concurrency-safe para folios documentales (DOC-ARR-YYYYMM-XXXX)';

-- ----------------------------------------------------------------------------
-- 2. Catálogo Maestro de Plantillas Documentales
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_plantillas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(40) NOT NULL COMMENT 'Identificador único canónico (CONTRATO_ARRENDAMIENTO)',
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` VARCHAR(500) NULL,
    `origen_tipo_permitido` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO') NOT NULL,
    `orientacion` ENUM('PORTRAIT', 'LANDSCAPE') NOT NULL DEFAULT 'PORTRAIT',
    `tamano_papel` ENUM('A4', 'LETTER', 'TICKET_80MM') NOT NULL DEFAULT 'A4',
    `requiere_membrete` TINYINT(1) NOT NULL DEFAULT 1,
    `archivo_membrete_fondo` VARCHAR(255) NULL COMMENT 'Ruta inmutable en storage/membretes/',
    `margen_superior_mm` INT NOT NULL DEFAULT 35,
    `margen_inferior_mm` INT NOT NULL DEFAULT 28,
    `margen_izquierdo_mm` INT NOT NULL DEFAULT 20,
    `margen_derecho_mm` INT NOT NULL DEFAULT 20,
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_docp_codigo_no_vacio` CHECK (`codigo` <> ''),
    CONSTRAINT `chk_docp_nombre_no_vacio` CHECK (`nombre` <> ''),
    CONSTRAINT `chk_docp_m_sup_no_negativo` CHECK (`margen_superior_mm` >= 0),
    CONSTRAINT `chk_docp_m_inf_no_negativo` CHECK (`margen_inferior_mm` >= 0),
    CONSTRAINT `chk_docp_m_izq_no_negativo` CHECK (`margen_izquierdo_mm` >= 0),
    CONSTRAINT `chk_docp_m_der_no_negativo` CHECK (`margen_derecho_mm` >= 0),
    UNIQUE KEY `uq_docp_codigo` (`codigo`),
    INDEX `idx_docp_origen_tipo` (`origen_tipo_permitido`),
    INDEX `idx_docp_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Catálogo maestro de plantillas documentales';

-- ----------------------------------------------------------------------------
-- 3. Versiones Inmutables de Contenido de Plantillas
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_plantilla_versiones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plantilla_id` BIGINT UNSIGNED NOT NULL,
    `numero_version` INT NOT NULL,
    `titulo_documento` VARCHAR(200) NOT NULL,
    `cuerpo_html` MEDIUMTEXT NOT NULL COMMENT 'Plantilla HTML con shortcodes {{entidad.propiedad}}',
    `estilos_css` TEXT NULL COMMENT 'CSS documental específico para print/Dompdf',
    `notas_version` VARCHAR(500) NULL,
    `es_activa` TINYINT(1) NOT NULL DEFAULT 0,
    `version_activa_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `es_activa` = 1 THEN `plantilla_id` ELSE NULL END
    ) VIRTUAL COMMENT 'Garantiza a nivel InnoDB que solo exista una versión activa por plantilla',
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_dpv_version_positiva` CHECK (`numero_version` > 0),
    CONSTRAINT `chk_dpv_titulo_no_vacio` CHECK (`titulo_documento` <> ''),
    CONSTRAINT `fk_dpv_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `documento_plantillas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dpv_actor` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_dpv_plantilla_version` (`plantilla_id`, `numero_version`),
    UNIQUE KEY `uq_dpv_plantilla_activa` (`version_activa_idx`),
    INDEX `idx_dpv_plantilla` (`plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Versiones inmutables de contenido de plantillas documentales';

-- ----------------------------------------------------------------------------
-- 4. Documentos Emitidos con Snapshots e Integridad Criptográfica
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documentos_emitidos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_folio` VARCHAR(50) NOT NULL COMMENT 'DOC-ARR-YYYYMM-XXXX',
    `plantilla_id` BIGINT UNSIGNED NOT NULL,
    `plantilla_version_id` BIGINT UNSIGNED NOT NULL,
    `origen_tipo` ENUM('ARRENDAMIENTO', 'RESERVA', 'ESTADIA', 'PAGO', 'RECIBO') NOT NULL,
    `origen_id` BIGINT UNSIGNED NOT NULL,
    `snapshot_datos_json` JSON NOT NULL COMMENT 'Valores crudos de los shortcodes en orden determinista',
    `snapshot_html` MEDIUMTEXT NOT NULL COMMENT 'HTML resuelto exactamente como fue renderizado',
    `ruta_archivo_pdf` VARCHAR(255) NOT NULL COMMENT 'Ruta física relativa a storage/documentos/',
    `tamano_bytes` INT UNSIGNED NOT NULL,
    `hash_pdf_sha256` CHAR(64) NOT NULL COMMENT 'Hash criptográfico del binario PDF almacenado',
    `hash_snapshot_sha256` CHAR(64) NOT NULL COMMENT 'Hash del HTML compilado congelado',
    `numero_paginas` INT UNSIGNED NOT NULL DEFAULT 1,
    `emitido_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `emitido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM('VALIDO', 'ANULADO') NOT NULL DEFAULT 'VALIDO',
    `motivo_anulacion` VARCHAR(500) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    CONSTRAINT `chk_demit_folio_no_vacio` CHECK (`codigo_folio` <> ''),
    CONSTRAINT `chk_demit_tamano_positivo` CHECK (`tamano_bytes` > 0),
    CONSTRAINT `fk_demit_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `documento_plantillas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_version` FOREIGN KEY (`plantilla_version_id`) REFERENCES `documento_plantilla_versiones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_emisor` FOREIGN KEY (`emitido_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_demit_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY `uq_demit_codigo_folio` (`codigo_folio`),
    INDEX `idx_demit_origen` (`origen_tipo`, `origen_id`),
    INDEX `idx_demit_hash` (`hash_pdf_sha256`),
    INDEX `idx_demit_estado` (`estado`),
    INDEX `idx_demit_plantilla` (`plantilla_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Documentos emitidos con snapshot inmutable y hash SHA-256';

-- ----------------------------------------------------------------------------
-- 5. Incidencias Documentales (Auditoría de Archivos Ausentes o Corruptos)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documento_incidencias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `documento_emitido_id` BIGINT UNSIGNED NOT NULL,
    `tipo_incidencia` ENUM('ARCHIVO_FALTANTE', 'HASH_NO_COINCIDE', 'ERROR_LECTURA') NOT NULL,
    `descripcion` VARCHAR(500) NOT NULL,
    `detectado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `detectado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resuelto` TINYINT(1) NOT NULL DEFAULT 0,
    `resuelto_en` DATETIME NULL,
    `resolucion_notas` VARCHAR(500) NULL,
    CONSTRAINT `fk_dinc_documento` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dinc_actor` FOREIGN KEY (`detectado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_dinc_documento` (`documento_emitido_id`),
    INDEX `idx_dinc_resuelto` (`resuelto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro de auditoría de inconsistencias físicas documentales';

-- ----------------------------------------------------------------------------
-- 6. Permisos RBAC para Gestión Documental
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`)
VALUES 
    ('documentos.ver', 'Ver documentos', 'DOCUMENTOS', 'Ver catálogo de plantillas y documentos emitidos', NOW()),
    ('documentos.emitir', 'Emitir documentos', 'DOCUMENTOS', 'Emitir documentos oficiales y contratos PDF', NOW()),
    ('documentos.plantillas.gestionar', 'Gestionar plantillas', 'DOCUMENTOS', 'Crear y editar versiones de plantillas documentales', NOW()),
    ('documentos.descargar', 'Descargar documentos', 'DOCUMENTOS', 'Descargar archivos PDF emitidos', NOW()),
    ('documentos.anular', 'Anular documentos', 'DOCUMENTOS', 'Anular documentos oficiales emitidos', NOW()),
    ('documentos.regenerar', 'Regenerar documentos', 'DOCUMENTOS', 'Regenerar archivos PDF desde snapshot ante incidencias', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- Asignar permisos al rol 1 (SUPERADMINISTRADOR) y rol 2 (ADMINISTRADOR)
INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'documentos.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'documentos.%';

-- ----------------------------------------------------------------------------
-- 7. Semilla Canónica: Plantilla de Contrato de Arrendamiento Inmobiliario (V1)
-- ----------------------------------------------------------------------------
INSERT INTO `documento_plantillas` (
    `id`, `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`
) VALUES (
    1,
    'CONTRATO_ARRENDAMIENTO',
    'Contrato de Arrendamiento Inmobiliario',
    'Plantilla oficial para contratos de arrendamiento temporal o prolongado',
    'ARRENDAMIENTO',
    'PORTRAIT',
    'A4',
    1,
    'membrete_a4_canonica_v1.png',
    35, 28, 20, 20,
    'ACTIVO'
) ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT INTO `documento_plantilla_versiones` (
    `id`, `plantilla_id`, `numero_version`, `titulo_documento`,
    `cuerpo_html`, `estilos_css`, `notas_version`, `es_activa`, `creado_por_actor_id`
) VALUES (
    1,
    1,
    1,
    'CONTRATO PRIVADO DE ARRENDAMIENTO DE BIEN INMUEBLE',
    '<div class="documento-contrato">
    <div class="encabezado-contrato">
        <h1 class="titulo-principal">CONTRATO PRIVADO DE ARRENDAMIENTO DE BIEN INMUEBLE</h1>
        <p class="subtitulo-folio">FOLIO N°: <strong>{{documento.folio}}</strong> | CONTRATO REF: <strong>{{contrato.numero}}</strong></p>
    </div>

    <div class="seccion-clausula">
        <p class="parrafo-introductorio">
            Conste por el presente documento privado, el <strong>CONTRATO DE ARRENDAMIENTO</strong> que celebran de una parte:
        </p>
        <p class="parrafo-parte">
            <strong>EL ARRENDADOR:</strong> <strong>{{arrendador.razon_social}}</strong>, con R.U.C. N° <strong>{{arrendador.ruc}}</strong>, debidamente representada por don(ña) <strong>{{arrendador.representante_legal}}</strong>, identificado(a) con D.N.I. N° <strong>{{arrendador.representante_dni}}</strong>, con domicilio legal en {{arrendador.domicilio_legal}}; y de la otra parte:
        </p>
        <p class="parrafo-parte">
            <strong>EL ARRENDATARIO:</strong> don(ña) <strong>{{arrendatario.nombre_completo}}</strong>, identificado(a) con {{arrendatario.tipo_documento}} N° <strong>{{arrendatario.numero_documento}}</strong>, con correo electrónico <strong>{{arrendatario.email}}</strong> y teléfono de contacto <strong>{{arrendatario.telefono}}</strong>;
        </p>
        <p class="parrafo-texto">
            En los términos y estipulaciones contenidos en las siguientes cláusulas:
        </p>
    </div>

    <div class="seccion-clausula">
        <h2 class="clausula-titulo">CLÁUSULA PRIMERA. — DEL INMUEBLE OBJETO DEL CONTRATO</h2>
        <p class="parrafo-texto">
            EL ARRENDADOR da en arrendamiento a favor de EL ARRENDATARIO la unidad inmobiliaria denominada <strong>{{unidad.nombre}}</strong> (tipología {{unidad.tipologia}}), ubicada dentro de la propiedad <strong>{{propiedad.nombre}}</strong>, con dirección en <strong>{{propiedad.direccion}}</strong>. EL ARRENDATARIO declara recibir el inmueble en óptimo estado de conservación, higiene y habitabilidad.
        </p>
    </div>

    <div class="seccion-clausula">
        <h2 class="clausula-titulo">CLÁUSULA SEGUNDA. — DEL PLAZO Y VIGENCIA</h2>
        <p class="parrafo-texto">
            El plazo de duración del presente contrato es de <strong>{{contrato.duracion_meses}} mes(es)</strong>, computados a partir del <strong>{{contrato.fecha_inicio}}</strong> hasta el <strong>{{contrato.fecha_fin}}</strong> inclusive. A su vencimiento, EL ARRENDATARIO se obliga a desocupar y devolver el inmueble en las mismas condiciones recibidas, salvo acuerdo previo por escrito de prórroga formal.
        </p>
    </div>

    <div class="seccion-clausula">
        <h2 class="clausula-titulo">CLÁUSULA TERCERA. — DE LA RENTA (CANON MENSUAL) Y FORMA DE PAGO</h2>
        <p class="parrafo-texto">
            La renta mensual pactada de común acuerdo asciende a la suma de <strong>{{contrato.canon_moneda}} {{contrato.canon_monto}}</strong> ({{contrato.canon_texto}}), la cual será abonada por mensualidades adelantadas a más tardar el día <strong>{{contrato.dia_corte_pago}}</strong> de cada mes calendario a través de los canales de pago autorizados por EL ARRENDADOR.
        </p>
    </div>

    <div class="seccion-clausula">
        <h2 class="clausula-titulo">CLÁUSULA CUARTA. — DEL DEPÓSITO EN GARANTÍA</h2>
        <p class="parrafo-texto">
            En este acto, EL ARRENDATARIO entrega a EL ARRENDADOR la suma de <strong>{{contrato.canon_moneda}} {{garantia.monto}}</strong> ({{garantia.texto}}) en calidad de depósito en garantía. Dicha suma no genera intereses ni podrá ser imputada al pago de alquileres devengados, y será restituida dentro de los 30 días posteriores a la restitución del inmueble, previa deducción de daños o servicios impagos si los hubiere.
        </p>
    </div>

    <div class="seccion-clausula">
        <h2 class="clausula-titulo">CLÁUSULA QUINTA. — DE LA DOTACIÓN FÍSICA Y BIENES ENTREGADOS</h2>
        <p class="parrafo-texto">
            Forma parte integrante del presente contrato el inventario físico y dotación de activos asignados a la unidad que se detalla a continuación:
        </p>
        <div class="tabla-dotacion-contenedor">
            {{bloque.inventario_dotacion}}
        </div>
    </div>

    <div class="seccion-clausula">
        <h2 class="clausula-titulo">CLÁUSULA SEXTA. — PROHIBICIÓN DE SUBARRENDAMIENTO Y CESIÓN</h2>
        <p class="parrafo-texto">
            Queda expresamente prohibido a EL ARRENDATARIO subarrendar total o parcialmente el bien, ceder su posición contractual o destinarlo a fines distintos a la habitación personal y pacífica. El incumplimiento dará lugar a la resolución inmediata de pleno derecho.
        </p>
    </div>

    <div class="seccion-firmas">
        <p class="parrafo-cierre">
            Suscrito en Lima, a los <strong>{{emision.fecha}}</strong>, en dos ejemplares de idéntico tenor.
        </p>
        <table class="tabla-firmas">
            <tr>
                <td class="col-firma">
                    <div class="linea-firma"></div>
                    <strong>EL ARRENDADOR</strong><br>
                    {{arrendador.razon_social}}<br>
                    R.U.C. {{arrendador.ruc}}
                </td>
                <td class="col-firma">
                    <div class="linea-firma"></div>
                    <strong>EL ARRENDATARIO</strong><br>
                    {{arrendatario.nombre_completo}}<br>
                    {{arrendatario.tipo_documento}} {{arrendatario.numero_documento}}
                </td>
            </tr>
        </table>
    </div>
</div>',
    'body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5pt; color: #1a1a1a; line-height: 1.4; }
.documento-contrato { width: 100%; }
.encabezado-contrato { text-align: center; margin-bottom: 20px; border-bottom: 1.5px solid #1B2A4A; padding-bottom: 8px; }
.titulo-principal { font-size: 13pt; color: #1B2A4A; margin: 0 0 4px 0; text-transform: uppercase; font-weight: bold; }
.subtitulo-folio { font-size: 8.5pt; color: #666; margin: 0; }
.seccion-clausula { margin-bottom: 14px; text-align: justify; }
.clausula-titulo { font-size: 9.5pt; color: #1B2A4A; margin: 0 0 5px 0; font-weight: bold; }
.parrafo-texto, .parrafo-parte, .parrafo-introductorio { margin: 0 0 6px 0; }
.tabla-dotacion-contenedor { margin: 8px 0; }
.tabla-dotacion { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 4px; }
.tabla-dotacion th { background-color: #1B2A4A; color: #ffffff; padding: 5px 8px; text-align: left; font-weight: bold; }
.tabla-dotacion td { border: 1px solid #d0d5dd; padding: 4px 8px; }
.tabla-dotacion tr:nth-child(even) { background-color: #f8f9fa; }
.seccion-firmas { margin-top: 30px; page-break-inside: avoid; }
.parrafo-cierre { font-size: 9pt; margin-bottom: 45px; text-align: center; }
.tabla-firmas { width: 100%; border-collapse: collapse; margin-top: 20px; }
.col-firma { width: 50%; text-align: center; vertical-align: top; padding: 0 30px; }
.linea-firma { border-top: 1.5px solid #333333; margin-bottom: 8px; width: 85%; margin-left: auto; margin-right: auto; }',
    'Versión inicial homologada de Contrato de Arrendamiento Inmobiliario',
    1,
    1
) ON DUPLICATE KEY UPDATE `titulo_documento` = VALUES(`titulo_documento`);

-- ----------------------------------------------------------------------------
-- 7. Opción de Menú Dinámico bajo 'reservas' (Operaciones)
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'documentos_motor', 'Documentos', 'fa-solid fa-file-shield', '/documentos', 8, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'documentos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

SET FOREIGN_KEY_CHECKS = 1;
