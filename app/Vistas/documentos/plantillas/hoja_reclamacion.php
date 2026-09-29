<?php

declare(strict_types=1);

/**
 * Plantilla HTML A4 oficial para la Hoja de Reclamación (RECLAMACIONES-1).
 * Conforme al D.S. 011-2011-PCM, Ley 29571 y Ley 31435.
 *
 * Variables esperadas:
 * @var array<string, mixed> $datos
 */

$snapConsumidor = $datos['snapshot_consumidor'] ?? [];
$snapProveedor = $datos['snapshot_proveedor'] ?? [];
$esMenor = !empty($datos['es_menor_edad']);
$apoderado = $snapConsumidor['apoderado'] ?? [];

$tipoReclamo = ($datos['tipo'] ?? '') === 'RECLAMO';
$tipoQueja = ($datos['tipo'] ?? '') === 'QUEJA';
$tipoProducto = ($datos['tipo_bien'] ?? '') === 'PRODUCTO';
$tipoServicio = ($datos['tipo_bien'] ?? '') === 'SERVICIO';

$fechaInterposicion = !empty($datos['fecha_interposicion'])
    ? date('d/m/Y H:i', strtotime($datos['fecha_interposicion']))
    : date('d/m/Y H:i');

$fechaLimite = !empty($datos['fecha_limite_legal'])
    ? date('d/m/Y', strtotime($datos['fecha_limite_legal']))
    : '-';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de Reclamación N° <?= htmlspecialchars((string) ($datos['codigo_hoja'] ?? '')) ?></title>
    <style>
        @page {
            margin: 12mm 15mm 12mm 15mm;
            size: A4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            line-height: 1.25;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-logo {
            font-size: 15pt;
            font-weight: bold;
            color: #1a365d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-sublogo {
            font-size: 7.5pt;
            color: #4a5568;
        }
        .header-box {
            border: 1.5pt solid #1a365d;
            border-radius: 4px;
            padding: 6px 10px;
            text-align: center;
            background-color: #f7fafc;
        }
        .header-box h2 {
            margin: 0;
            font-size: 10pt;
            font-weight: bold;
            color: #1a365d;
            text-transform: uppercase;
        }
        .header-box .numero-hoja {
            font-size: 11pt;
            font-weight: bold;
            color: #c53030;
            margin-top: 3px;
        }
        .header-box .codigo-interno {
            font-size: 7.5pt;
            color: #718096;
            margin-top: 2px;
        }
        .seccion-titulo {
            background-color: #2b6cb0;
            color: #ffffff;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px 6px;
            margin-top: 6px;
            margin-bottom: 3px;
            border-radius: 2px;
        }
        table.datos-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        table.datos-tabla td {
            padding: 2.5px 4px;
            vertical-align: top;
            border: 0.5pt solid #cbd5e0;
        }
        table.datos-tabla .lbl {
            background-color: #f7fafc;
            font-weight: bold;
            color: #2d3748;
            width: 22%;
        }
        table.datos-tabla .val {
            color: #1a202c;
            width: 28%;
        }
        .box-check {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1pt solid #2d3748;
            text-align: center;
            line-height: 9px;
            font-size: 8pt;
            font-weight: bold;
            margin-right: 4px;
        }
        .detalle-caja {
            border: 0.5pt solid #cbd5e0;
            background-color: #ffffff;
            padding: 6px;
            min-height: 42px;
            font-size: 8pt;
            color: #2d3748;
            margin-bottom: 4px;
            line-height: 1.35;
        }
        .aviso-legal {
            border: 0.5pt solid #e2e8f0;
            background-color: #edf2f7;
            padding: 5px 8px;
            font-size: 6.8pt;
            color: #4a5568;
            text-align: justify;
            margin-top: 8px;
            border-radius: 3px;
        }
        .firmas-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        .firmas-tabla td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 25px;
        }
        .linea-firma {
            border-top: 1pt solid #718096;
            margin-top: 30px;
            padding-top: 4px;
            font-size: 7.5pt;
            color: #4a5568;
        }
    </style>
</head>
<body>

    <!-- Encabezado Oficial -->
    <table class="header-table">
        <tr>
            <td style="width: 58%; vertical-align: middle;">
                <div class="header-logo"><?= htmlspecialchars((string) ($snapProveedor['razon_social'] ?? 'CAMARGO HOSTELERÍA')) ?></div>
                <div class="header-sublogo">
                    <strong>RUC:</strong> <?= htmlspecialchars((string) ($snapProveedor['ruc'] ?? '-')) ?> &bull; 
                    <strong>Sede:</strong> <?= htmlspecialchars((string) ($snapProveedor['sede_nombre'] ?? '-')) ?>
                </div>
                <div class="header-sublogo">
                    <?= htmlspecialchars((string) ($snapProveedor['sede_direccion'] ?? $snapProveedor['direccion_fiscal'] ?? '')) ?>
                </div>
            </td>
            <td style="width: 42%; vertical-align: middle;">
                <div class="header-box">
                    <h2>Libro de Reclamaciones</h2>
                    <div style="font-size: 7.5pt; color: #4a5568;">HOJA DE RECLAMACIÓN</div>
                    <div class="numero-hoja">N° <?= htmlspecialchars((string) ($datos['codigo_hoja'] ?? '')) ?></div>
                    <div class="codigo-interno">Ref: <?= htmlspecialchars((string) ($datos['codigo_interno'] ?? '')) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="datos-tabla" style="margin-bottom: 6px;">
        <tr>
            <td class="lbl" style="width: 25%;">Fecha y Hora de Registro:</td>
            <td class="val" style="width: 25%; font-weight: bold;"><?= $fechaInterposicion ?></td>
            <td class="lbl" style="width: 25%;">Plazo Legal de Atención:</td>
            <td class="val" style="width: 25%; color: #c53030; font-weight: bold;">Hasta el <?= $fechaLimite ?></td>
        </tr>
    </table>

    <!-- 1. Identificación del Consumidor Reclamante -->
    <div class="seccion-titulo">1. Identificación del Consumidor Reclamante</div>
    <table class="datos-tabla">
        <tr>
            <td class="lbl">Nombre / Razón Social:</td>
            <td class="val" colspan="3"><strong><?= htmlspecialchars((string) ($snapConsumidor['nombre_completo'] ?? '-')) ?></strong></td>
        </tr>
        <tr>
            <td class="lbl">Documento de Identidad:</td>
            <td class="val"><?= htmlspecialchars((string) ($snapConsumidor['tipo_documento'] ?? 'DOC')) ?>: <?= htmlspecialchars((string) ($snapConsumidor['numero_documento'] ?? '-')) ?></td>
            <td class="lbl">Nacionalidad:</td>
            <td class="val"><?= htmlspecialchars((string) ($snapConsumidor['nacionalidad'] ?? 'Peruana')) ?></td>
        </tr>
        <tr>
            <td class="lbl">Domicilio:</td>
            <td class="val" colspan="3"><?= htmlspecialchars((string) ($snapConsumidor['direccion'] ?? 'No especificado')) ?></td>
        </tr>
        <tr>
            <td class="lbl">Teléfono / Celular:</td>
            <td class="val"><?= htmlspecialchars((string) ($snapConsumidor['telefono'] ?? '-')) ?></td>
            <td class="lbl">Correo Electrónico:</td>
            <td class="val"><?= htmlspecialchars((string) ($snapConsumidor['email'] ?? '-')) ?></td>
        </tr>
        <?php if ($esMenor && !empty($apoderado)): ?>
        <tr>
            <td class="lbl" style="background-color: #feebc8; color: #7b341e;">Padre / Madre / Apoderado:</td>
            <td class="val" colspan="3">
                <strong><?= htmlspecialchars((string) ($apoderado['nombre_completo'] ?? '-')) ?></strong>
                (<?= htmlspecialchars((string) ($apoderado['tipo_documento'] ?? 'DOC')) ?>: <?= htmlspecialchars((string) ($apoderado['numero_documento'] ?? '-')) ?>
                &bull; Tel: <?= htmlspecialchars((string) ($apoderado['telefono'] ?? '-')) ?>
                &bull; Email: <?= htmlspecialchars((string) ($apoderado['email'] ?? '-')) ?>)
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- 2. Identificación del Bien Contratado -->
    <div class="seccion-titulo">2. Identificación del Bien Contratado</div>
    <table class="datos-tabla">
        <tr>
            <td class="lbl">Naturaleza del Bien:</td>
            <td class="val">
                <span class="box-check"><?= $tipoProducto ? 'X' : '&nbsp;' ?></span> Producto &nbsp;&nbsp;&nbsp;
                <span class="box-check"><?= $tipoServicio ? 'X' : '&nbsp;' ?></span> Servicio
            </td>
            <td class="lbl">Monto Reclamado:</td>
            <td class="val"><strong><?= htmlspecialchars((string) ($datos['moneda'] ?? 'PEN')) ?> <?= number_format((float) ($datos['monto_reclamado'] ?? 0), 2) ?></strong></td>
        </tr>
        <tr>
            <td class="lbl">Descripción del Bien/Servicio:</td>
            <td class="val" colspan="3"><?= nl2br(htmlspecialchars((string) ($datos['descripcion_bien'] ?? '-'))) ?></td>
        </tr>
    </table>

    <!-- 3. Detalle de la Reclamación y Pedido del Consumidor -->
    <div class="seccion-titulo">3. Detalle de la Reclamación y Pedido del Consumidor</div>
    <table class="datos-tabla">
        <tr>
            <td class="lbl">Tipo de Solicitud:</td>
            <td class="val" colspan="3">
                <span class="box-check"><?= $tipoReclamo ? 'X' : '&nbsp;' ?></span> <strong>RECLAMO:</strong> Disconformidad relacionada a los productos o servicios expendidos.<br>
                <span class="box-check"><?= $tipoQueja ? 'X' : '&nbsp;' ?></span> <strong>QUEJA:</strong> Disconformidad no relacionada a los productos o servicios; malestar o descontento respecto a la atención al público.
            </td>
        </tr>
    </table>

    <div style="font-weight: bold; font-size: 7.5pt; color: #2d3748; margin-top: 4px; margin-bottom: 2px;">Detalle de los Hechos Expuestos:</div>
    <div class="detalle-caja">
        <?= nl2br(htmlspecialchars((string) ($datos['detalle_reclamacion'] ?? '-'))) ?>
    </div>

    <div style="font-weight: bold; font-size: 7.5pt; color: #2d3748; margin-top: 4px; margin-bottom: 2px;">Pedido Concreto del Consumidor:</div>
    <div class="detalle-caja">
        <?= nl2br(htmlspecialchars((string) ($datos['pedido_consumidor'] ?? '-'))) ?>
    </div>

    <!-- 4. Observaciones y Acciones Adoptadas por el Proveedor -->
    <div class="seccion-titulo">4. Observaciones y Acciones Adoptadas por el Proveedor</div>
    <div class="detalle-caja" style="min-height: 48px; color: #718096; font-style: italic;">
        El proveedor atenderá el presente expediente en un plazo no mayor a quince (15) días hábiles improrrogables (Ley 31435), notificando su respuesta formal por escrito al correo electrónico o domicilio declarado por el consumidor.
    </div>

    <!-- Firmas -->
    <table class="firmas-tabla">
        <tr>
            <td>
                <div class="linea-firma">
                    <strong>Firma del Consumidor / Acreditación Digital</strong><br>
                    <?= htmlspecialchars((string) ($snapConsumidor['nombre_completo'] ?? '')) ?><br>
                    <?= htmlspecialchars((string) ($snapConsumidor['tipo_documento'] ?? 'DOC')) ?>: <?= htmlspecialchars((string) ($snapConsumidor['numero_documento'] ?? '')) ?>
                </div>
            </td>
            <td>
                <div class="linea-firma">
                    <strong>Firma del Proveedor / Emisor Autorizado</strong><br>
                    <?= htmlspecialchars((string) ($snapProveedor['razon_social'] ?? 'Camargo Hostelería')) ?><br>
                    RUC N° <?= htmlspecialchars((string) ($snapProveedor['ruc'] ?? '')) ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Aviso Legal Regulatorio -->
    <div class="aviso-legal">
        <strong>Aviso Legal Importante:</strong> La formulación del presente reclamo o queja no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia administrativa ante el INDECOPI (Ley N° 29571 y D.S. N° 011-2011-PCM modificado por D.S. N° 101-2022-PCM). El proveedor debe dar respuesta al reclamo en un plazo no mayor a quince (15) días hábiles improrrogables conforme a la Ley N° 31435.
    </div>

</body>
</html>
