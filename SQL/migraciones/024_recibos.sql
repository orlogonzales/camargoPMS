-- ============================================================================
-- CAMARGO PMS — Migración 024: Recibos de Cobranza, Snapshots Financieros T0 y Preservación Documental
-- Gobernanza: D-082 (GATE RECIBOS-1)
-- Tablas:
--   79. recibos         (Constancia histórica formal de recaudación en T0)
--   80. recibo_lineas   (Snapshot inmutable de obligaciones amortizadas)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 79. Recibos Administrativos de Cobro (Constancia Histórica en T0)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recibos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL COMMENT 'Formato REC-YYYYMM-XXXX',
    `cuenta_folio_id` BIGINT UNSIGNED NOT NULL,
    `pago_id` BIGINT UNSIGNED NOT NULL COMMENT 'Pago confirmado que originó el recibo',
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `persona_nombre_snapshot` VARCHAR(255) NOT NULL,
    `persona_documento_tipo_snapshot` VARCHAR(20) NOT NULL,
    `persona_documento_numero_snapshot` VARCHAR(30) NOT NULL,
    `arrendamiento_id` BIGINT UNSIGNED NULL,
    `reserva_id` BIGINT UNSIGNED NULL,
    `documento_emitido_id` BIGINT UNSIGNED NULL COMMENT 'Enlace a DOCUMENTOS-1',
    `monto_recaudado` DECIMAL(15,2) NOT NULL,
    `monto_imputado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `monto_no_aplicado_pago` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_pendiente_folio_despues` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `saldo_favor_folio_despues` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `moneda_codigo` VARCHAR(3) NOT NULL DEFAULT 'PEN',
    `metodo_pago_nombre` VARCHAR(50) NOT NULL,
    `referencia_cobro` VARCHAR(100) NULL,
    `concepto_general` VARCHAR(255) NOT NULL,
    `notas` VARCHAR(500) NULL,
    `fecha_emision` DATETIME NOT NULL,
    `estado` ENUM('EMITIDO', 'ANULADO') NOT NULL DEFAULT 'EMITIDO',
    `motivo_anulacion` VARCHAR(255) NULL,
    `anulado_en` DATETIME NULL,
    `anulado_por_actor_id` BIGINT UNSIGNED NULL,
    `creado_por_actor_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `recibo_activo_idx` BIGINT UNSIGNED GENERATED ALWAYS AS (
        IF(`estado` = 'EMITIDO', `pago_id`, NULL)
    ) VIRTUAL COMMENT 'Garantiza en InnoDB un único recibo activo por pago',
    UNIQUE KEY `uq_rec_codigo` (`codigo`),
    UNIQUE KEY `uq_rec_pago_activo` (`recibo_activo_idx`),
    KEY `idx_rec_cuenta_folio` (`cuenta_folio_id`, `estado`),
    KEY `idx_rec_pago` (`pago_id`),
    KEY `idx_rec_persona` (`persona_id`),
    KEY `idx_rec_arrendamiento` (`arrendamiento_id`),
    KEY `idx_rec_reserva` (`reserva_id`),
    KEY `idx_rec_documento` (`documento_emitido_id`),
    CONSTRAINT `fk_rec_cuenta_folio` FOREIGN KEY (`cuenta_folio_id`) REFERENCES `cuentas_folios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_arrendamiento` FOREIGN KEY (`arrendamiento_id`) REFERENCES `arrendamientos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_documento` FOREIGN KEY (`documento_emitido_id`) REFERENCES `documentos_emitidos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_actor_creador` FOREIGN KEY (`creado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_rec_actor_anulador` FOREIGN KEY (`anulado_por_actor_id`) REFERENCES `actores` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
    CONSTRAINT `chk_rec_recaudado_positivo` CHECK (`monto_recaudado` > 0),
    CONSTRAINT `chk_rec_imputado_rango` CHECK (`monto_imputado` >= 0 AND `monto_imputado` <= `monto_recaudado`),
    CONSTRAINT `chk_rec_no_aplicado_no_negativo` CHECK (`monto_no_aplicado_pago` >= 0),
    CONSTRAINT `chk_rec_saldo_pendiente_folio` CHECK (`saldo_pendiente_folio_despues` >= 0),
    CONSTRAINT `chk_rec_saldo_favor_folio` CHECK (`saldo_favor_folio_despues` >= 0),
    CONSTRAINT `chk_rec_balance_algebraico` CHECK (`monto_recaudado` = `monto_imputado` + `monto_no_aplicado_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Recibos de cobranza con snapshot financiero en T0';

-- ----------------------------------------------------------------------------
-- 80. Líneas de Imputación del Recibo (Snapshot de Amortizaciones en T0)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recibo_lineas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recibo_id` BIGINT UNSIGNED NOT NULL,
    `numero_linea` INT UNSIGNED NOT NULL DEFAULT 1,
    `aplicacion_id` BIGINT UNSIGNED NULL,
    `cargo_id` BIGINT UNSIGNED NOT NULL,
    `cargo_codigo` VARCHAR(30) NOT NULL,
    `cargo_concepto` VARCHAR(255) NOT NULL,
    `cargo_origen_tipo` VARCHAR(40) NOT NULL,
    `cargo_monto_total` DECIMAL(15,2) NOT NULL,
    `monto_aplicado` DECIMAL(15,2) NOT NULL,
    `cargo_saldo_restante` DECIMAL(15,2) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_reclin_recibo_num` (`recibo_id`, `numero_linea`),
    KEY `idx_reclin_cargo` (`cargo_id`),
    CONSTRAINT `fk_reclin_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_reclin_aplicacion` FOREIGN KEY (`aplicacion_id`) REFERENCES `aplicaciones_pago` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_reclin_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_cuenta` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_reclin_aplicado_positivo` CHECK (`monto_aplicado` > 0),
    CONSTRAINT `chk_reclin_saldo_no_negativo` CHECK (`cargo_saldo_restante` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Líneas del snapshot de amortizaciones cubiertas en el recibo';

-- ----------------------------------------------------------------------------
-- Permisos RBAC de Recibos
-- ----------------------------------------------------------------------------
INSERT INTO `permisos` (`codigo`, `nombre`, `modulo`, `descripcion`, `creado_en`) VALUES
('recibos.ver', 'Ver recibos de cobro', 'RECIBOS', 'Permite consultar el listado y detalle de recibos emitidos', NOW()),
('recibos.emitir', 'Emitir recibos de cobro', 'RECIBOS', 'Permite generar recibos oficiales y PDFs con snapshot T0', NOW()),
('recibos.anular', 'Anular recibos de cobro', 'RECIBOS', 'Permite revocar formalmente la validez de un recibo emitido', NOW()),
('recibos.descargar', 'Descargar PDF de recibos', 'RECIBOS', 'Permite descargar el documento PDF soberano con verificación SHA-256', NOW())
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 1, p.id FROM `permisos` p WHERE p.codigo LIKE 'recibos.%';

INSERT IGNORE INTO `roles_permisos` (`rol_id`, `permiso_id`)
SELECT 2, p.id FROM `permisos` p WHERE p.codigo LIKE 'recibos.%';

-- ----------------------------------------------------------------------------
-- Opción de Menú Recibos
-- ----------------------------------------------------------------------------
INSERT INTO `opciones_menu` (`padre_id`, `clave`, `nombre`, `icono`, `ruta`, `orden`, `estado`, `permiso_id`, `es_sistema`)
SELECT p.`id`, 'recibos', 'Recibos de Pago', 'fa-solid fa-receipt', '/recibos', 11, 'ACTIVO', perm.`id`, 1
FROM `opciones_menu` p
CROSS JOIN `permisos` perm
WHERE p.`clave` = 'reservas' AND p.`padre_id` IS NULL
  AND perm.`codigo` = 'recibos.ver'
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `icono` = VALUES(`icono`),
    `ruta` = VALUES(`ruta`),
    `orden` = VALUES(`orden`),
    `permiso_id` = VALUES(`permiso_id`);

-- ----------------------------------------------------------------------------
-- Plantilla Oficial RECIBO_PAGO en DOCUMENTOS-1
-- ----------------------------------------------------------------------------
INSERT INTO `documento_plantillas` (
    `codigo`, `nombre`, `descripcion`, `origen_tipo_permitido`,
    `orientacion`, `tamano_papel`, `requiere_membrete`, `archivo_membrete_fondo`,
    `margen_superior_mm`, `margen_inferior_mm`, `margen_izquierdo_mm`, `margen_derecho_mm`,
    `estado`, `creado_en`
) VALUES (
    'RECIBO_PAGO',
    'Recibo de Cobranza Oficial',
    'Constancia histórica administrativa de pago e imputación a folio en T0',
    'RECIBO',
    'PORTRAIT',
    'A4',
    0,
    NULL,
    20, 20, 20, 20,
    'ACTIVO',
    NOW()
) ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`);

SET @plantilla_recibo_id = (SELECT `id` FROM `documento_plantillas` WHERE `codigo` = 'RECIBO_PAGO' LIMIT 1);

INSERT INTO `documento_plantilla_versiones` (
    `plantilla_id`, `numero_version`, `titulo_documento`, `cuerpo_html`, `estilos_css`,
    `notas_version`, `es_activa`, `creado_por_actor_id`, `creado_en`
) VALUES (
    @plantilla_recibo_id,
    1,
    'Constancia Oficial de Pago',
    '<div class="documento-recibo">
    <div class="encabezado-recibo">
        <h1 class="titulo-empresa">CAMARGO HOSTELERÍA S.A.C.</h1>
        <p class="subtitulo-empresa">R.U.C. 20600000001 — Gestión Inmobiliaria y Turística</p>
        <div class="caja-folio">
            <h2 class="titulo-recibo">RECIBO DE COBRANZA</h2>
            <p class="folio-numero">N° {{documento.folio}}</p>
            <p class="fecha-emision">Fecha y Hora: {{emision.fecha}} {{emision.hora}}</p>
        </div>
    </div>

    <div class="seccion-cliente">
        <table class="tabla-info">
            <tr>
                <td class="campo-etiqueta"><strong>Recibido de:</strong></td>
                <td class="campo-valor" colspan="3"><strong>{{cliente.nombre_completo}}</strong></td>
            </tr>
            <tr>
                <td class="campo-etiqueta"><strong>Documento:</strong></td>
                <td class="campo-valor">{{cliente.tipo_documento}} {{cliente.numero_documento}}</td>
                <td class="campo-etiqueta"><strong>Folio Comercial:</strong></td>
                <td class="campo-valor">{{folio.codigo}}</td>
            </tr>
            <tr>
                <td class="campo-etiqueta"><strong>Inmueble / Unidad:</strong></td>
                <td class="campo-valor">{{propiedad.nombre}} - {{unidad.nombre}}</td>
                <td class="campo-etiqueta"><strong>Contrato / Reserva:</strong></td>
                <td class="campo-valor">{{contrato.codigo}} {{reserva.codigo}}</td>
            </tr>
        </table>
    </div>

    <div class="seccion-cobro">
        <h3 class="seccion-subtitulo">Hecho Económico de Cobro</h3>
        <table class="tabla-info">
            <tr>
                <td class="campo-etiqueta"><strong>Código Pago:</strong></td>
                <td class="campo-valor">{{pago.codigo}}</td>
                <td class="campo-etiqueta"><strong>Método de Pago:</strong></td>
                <td class="campo-valor">{{pago.metodo}} ({{pago.medio_detalle}})</td>
            </tr>
            <tr>
                <td class="campo-etiqueta"><strong>N° Operación / Ref:</strong></td>
                <td class="campo-valor">{{pago.referencia_operacion}}</td>
                <td class="campo-etiqueta"><strong>Monto Recaudado:</strong></td>
                <td class="campo-valor font-bold text-primary">{{pago.moneda}} {{pago.monto_recaudado}}</td>
            </tr>
        </table>
    </div>

    <div class="seccion-amortizaciones">
        <h3 class="seccion-subtitulo">Detalle de Obligaciones Amortizadas (Imputación T0)</h3>
        {{tabla_amortizaciones}}
    </div>

    <div class="seccion-resumen">
        <table class="tabla-resumen">
            <tr>
                <td class="res-etiqueta">Total Recaudado en Pago:</td>
                <td class="res-valor font-bold">{{pago.moneda}} {{pago.monto_recaudado}}</td>
            </tr>
            <tr>
                <td class="res-etiqueta">Total Imputado a Cargos:</td>
                <td class="res-valor">{{pago.moneda}} {{totales.monto_imputado}}</td>
            </tr>
            <tr>
                <td class="res-etiqueta">Monto No Aplicado (Saldo a Favor en Folio):</td>
                <td class="res-valor text-success">{{pago.moneda}} {{totales.monto_no_aplicado_pago}}</td>
            </tr>
            <tr class="linea-separador">
                <td class="res-etiqueta font-bold">Saldo Pendiente Exigible del Folio:</td>
                <td class="res-valor font-bold">{{pago.moneda}} {{totales.saldo_pendiente_folio_despues}}</td>
            </tr>
            <tr>
                <td class="res-etiqueta">Saldo a Favor Acumulado del Folio:</td>
                <td class="res-valor">{{pago.moneda}} {{totales.saldo_favor_folio_despues}}</td>
            </tr>
        </table>
        <p class="monto-texto">SON: {{pago.monto_texto}}</p>
    </div>

    <div class="seccion-pie">
        <p class="aviso-legal">
            <strong>AVISO LEGAL:</strong> El presente documento constituye una constancia administrativa interna de recaudación y no representa un comprobante de pago electrónico con efectos tributarios ante la SUNAT.
        </p>
        <table class="tabla-firmas">
            <tr>
                <td class="firma-col">
                    <div class="linea-firma"></div>
                    <p class="firma-texto">Recibido Conforme<br>Caja / Administración Camargo Hostelería</p>
                </td>
            </tr>
        </table>
    </div>
</div>',
    'body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #222; }
.documento-recibo { width: 100%; margin: 0 auto; }
.encabezado-recibo { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 15px; }
.titulo-empresa { font-size: 16pt; margin: 0; color: #0d6efd; }
.subtitulo-empresa { font-size: 8.5pt; color: #666; margin: 3px 0 10px 0; }
.caja-folio { text-align: right; margin-top: -45px; }
.titulo-recibo { font-size: 13pt; margin: 0; color: #222; }
.folio-numero { font-size: 11pt; font-weight: bold; color: #0d6efd; margin: 2px 0; }
.fecha-emision { font-size: 8pt; color: #777; margin: 0; }
.tabla-info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.tabla-info td { padding: 4px 6px; font-size: 9pt; }
.campo-etiqueta { width: 20%; color: #444; }
.campo-valor { width: 30%; }
.seccion-subtitulo { font-size: 10.5pt; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin: 12px 0 8px 0; color: #0d6efd; }
.tabla-amort-doc { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.tabla-amort-doc th { background-color: #f1f4f9; border: 1px solid #ccc; padding: 6px; font-size: 8.5pt; text-align: center; }
.tabla-amort-doc td { border: 1px solid #ddd; padding: 5px 6px; font-size: 8.5pt; }
.seccion-resumen { width: 100%; margin-top: 10px; }
.tabla-resumen { float: right; width: 55%; border-collapse: collapse; margin-bottom: 8px; }
.tabla-resumen td { padding: 3px 8px; font-size: 9pt; }
.res-etiqueta { text-align: right; }
.res-valor { text-align: right; width: 35%; }
.linea-separador td { border-top: 1px dashed #aaa; padding-top: 5px; }
.monto-texto { clear: both; font-size: 8.5pt; font-style: italic; color: #333; margin-top: 5px; }
.aviso-legal { font-size: 7.5pt; color: #777; border-top: 1px solid #eee; padding-top: 8px; margin-top: 25px; text-align: justify; }
.tabla-firmas { width: 100%; margin-top: 25px; }
.firma-col { width: 45%; margin: 0 auto; text-align: center; }
.linea-firma { width: 220px; border-bottom: 1px solid #333; margin: 0 auto 5px auto; }
.firma-texto { font-size: 8pt; color: #444; margin: 0; }
.font-bold { font-weight: bold; }
.text-primary { color: #0d6efd; }
.text-success { color: #198754; }',
    'Versión inicial canónica del recibo de cobranza con snapshot T0',
    1,
    1,
    NOW()
) ON DUPLICATE KEY UPDATE
    `cuerpo_html` = VALUES(`cuerpo_html`),
    `estilos_css` = VALUES(`estilos_css`);

SET FOREIGN_KEY_CHECKS = 1;
