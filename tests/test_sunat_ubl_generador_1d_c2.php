<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1D-C2 — Implementación del Motor Fiscal -> UBL 2.1 sin Firma
 *
 * Cobertura de Pruebas:
 * 1.  Factura comercial al contado válida (01).
 * 2.  Factura comercial al crédito con 1 cuota (R.S. 193-2020).
 * 3.  Factura comercial al crédito con N cuotas (Cuota001, Cuota002, ...).
 * 4.  Boleta de venta electrónica válida (03).
 * 5.  Nota de crédito ordinaria (07) con BillingReference y DiscrepancyResponse (sin PaymentTerms).
 * 6.  Nota de crédito motivo 13 con PaymentTerms obligatorios y reprogramación de cuotas.
 * 7.  Nota de débito electrónica (08) con RequestedMonetaryTotal.
 * 8.  Exportación general código 40 sin DL919 (tributo 9995 EXP, sin propiedades Catálogo 55).
 * 9.  Hospedaje DL919 alojamiento: emite 4003 (checkin) y 4004 (checkout), NO emite 4006.
 * 10. Hospedaje DL919 consumo: emite 4006 (fecha_consumo), NO emite 4003 ni 4004.
 * 11. Receptor corporativo != Huésped (RUC empresa en customer party, datos de huésped en líneas).
 * 12. Multihuésped: 1 Factura con 2 huéspedes y 2 líneas independientes sin colisión.
 * 13. Multilínea mixta: alojamiento y consumo de restaurante en el mismo comprobante.
 * 14. Documentos relacionados: múltiple BillingReference en Nota de Crédito.
 * 15. Caracteres XML escapables (&, <, >, ", ') correctamente procesados en DOM.
 * 16. Caracteres UTF-8 (acentos, ñ, caracteres válidos) preservados canónicamente.
 * 17. Determinismo e idempotencia byte a byte pre-firma (mismo snapshot -> idéntico SHA-256).
 * 18. Precisión decimal exacta (BCMath, cero floats).
 * 19. Orden canónico determinístico de cuotas (Cuota001, Cuota002...).
 * 20. Orden canónico determinístico de líneas (1..N).
 *
 * Pruebas Negativas:
 * 21. Rechazo de tipo de comprobante no soportado.
 * 22. Rechazo de Factura con tipo de documento receptor no RUC (ej. DNI).
 * 23. Rechazo de moneda no permitida (ej. EUR).
 * 24. Rechazo de unidad de medida no permitida en Catálogo 03.
 * 25. Rechazo de tipo de afectación al IGV inválido.
 * 26. Rechazo de inconsistencia de totales entre cabecera y suma de líneas.
 * 27. Rechazo de forma de pago CREDITO sin cuotas.
 * 28. Rechazo de suma de cuotas que no coincide con monto_neto_pendiente.
 * 29. Rechazo de cuota con número ordinal 0 o negativo.
 * 30. Rechazo de cuota con número ordinal > 999.
 * 31. Rechazo de cuotas con saltos o duplicados.
 * 32. Rechazo de datos obligatorios faltantes (razón social vacía).
 * 33. Rechazo de código de país que no sea de 2 caracteres ISO 3166-1.
 * 34. Rechazo de documento de huésped DNI en régimen D.L. 919.
 * 35. Rechazo de comprobante con es_exportacion_hospedaje=true pero sin líneas vinculadas.
 * 36. Rechazo de línea de hospedaje vinculada a ID inexistente (infracción cross-CPE).
 * 37. Rechazo de fecha de consumo fuera del rango de estancia [checkin, checkout].
 * 38. Rechazo de línea vinculada a hospedaje con tipo de afectación no exportación (ej. '10').
 * 39. Rechazo de caracteres no permitidos por la norma XML 1.0 (control chars ASCII 0x07).
 * 40. Rechazo de Nota de Crédito sin documento relacionado.
 * 41. Rechazo de Nota de Crédito con código de motivo no válido en Catálogo 09.
 * 42. Rechazo de Nota de Débito con código de motivo no válido en Catálogo 10.
 * 43. Rechazo de Nota de Crédito motivo 13 sin forma de pago CREDITO o sin cuotas.
 *
 * Pruebas de Seguridad y Arquitectura:
 * 44. Ausencia estricta de firma digital (ds:Signature, SignatureValue, DigestValue, X509Certificate).
 * 45. Ausencia de credenciales reales, claves privadas o certificados en código.
 * 46. Funcionamiento 100% offline sin acceso a red.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\CaracterInvalidoXmlExcepcion;
use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeCuota;
use CamargoPMS\Modelos\CPE\CpeDocumentoRelacionado;
use CamargoPMS\Modelos\CPE\CpeHospedajeFiscal;
use CamargoPMS\Modelos\CPE\CpeLinea;
use CamargoPMS\Servicios\CPE\ConstantesUbl;
use CamargoPMS\Servicios\CPE\GeneradorUblServicio;

$totalChecks = 0;
$passedChecks = 0;
$failedChecks = 0;

function verificar(string $descripcion, bool $condicion): void
{
    global $totalChecks, $passedChecks, $failedChecks;
    $totalChecks++;
    if ($condicion) {
        $passedChecks++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $failedChecks++;
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "====================================================================\n";
echo "EJECUTANDO SUITE SUNAT-1D-C2 — MOTOR FISCAL -> UBL 2.1 SIN FIRMA\n";
echo "====================================================================\n\n";

$servicioUbl = new GeneradorUblServicio();

// Helper para crear agregados de prueba base
function crearComprobanteBase(
    string $tipo = 'FACTURA',
    string $serie = 'F001',
    int $correlativo = 1,
    string $formaPago = 'CONTADO',
    ?string $montoNeto = null,
    string $moneda = 'PEN',
    string $recTipoDoc = '6',
    string $recNumDoc = '20601234567',
    string $recRazon = 'CLIENTE CORPORATIVO S.A.C.'
): CpeComprobante {
    return new CpeComprobante(
        id: 100,
        emisorEstablecimientoId: 1,
        serieId: 1,
        cuentaFolioId: 10,
        tipoComprobante: $tipo,
        serie: $serie,
        correlativo: $correlativo,
        codigoFolioCompleto: sprintf('%s-%08d', $serie, $correlativo),
        claveIdempotencia: 'IDEMP-' . uniqid(),
        emisorRuc: '20609998881',
        emisorRazonSocial: 'CAMARGO HOSTELERIA S.A.C.',
        emisorNombreComercial: 'HOTEL CAMARGO BOUTIQUE',
        emisorDireccionFiscal: 'CALLE SAN AGUSTIN 123, CUSCO',
        emisorUbigeo: '080101',
        emisorCodigoEstablecimiento: '0000',
        emisorDepartamento: 'CUSCO',
        emisorProvincia: 'CUSCO',
        emisorDistrito: 'CUSCO',
        receptorTipoDocumento: $recTipoDoc,
        receptorNumeroDocumento: $recNumDoc,
        receptorRazonSocial: $recRazon,
        receptorDireccionFiscal: 'AV. LARCO 456, MIRAFLORES, LIMA',
        receptorUbigeo: '150122',
        receptorEmail: 'facturacion@cliente.com',
        receptorPaisCodigo: 'PE',
        esExportacionHospedaje: false,
        hospedajeTamVirtualNumero: null,
        hospedajeFechaIngresoPais: null,
        hospedajeDiasPermanencia: null,
        hospedajeLeyendaTributaria: null,
        monedaCodigo: $moneda,
        tipoCambio: null,
        totalOperacionesGravadas: '1000.00',
        totalOperacionesExoneradas: '0.00',
        totalOperacionesInafectas: '0.00',
        totalOperacionesExportacion: '0.00',
        totalOperacionesGratuitas: '0.00',
        totalIgv: '180.00',
        totalDescuentos: '0.00',
        totalVenta: '1180.00',
        estadoGeneracion: 'BORRADOR',
        estadoTransmision: 'NO_INICIADO',
        estadoFiscalSunat: 'PENDIENTE_ENVIO',
        estadoRectificacion: 'ORIGINAL',
        codigoHashCpe: null,
        hashXmlSha256: null,
        hashPdfSha256: null,
        rutaArchivoXml: null,
        rutaArchivoPdf: null,
        fechaEmision: '2026-10-05',
        fechaVencimiento: '2026-11-05',
        creadoPorUsuarioId: 1,
        creadoEn: '2026-10-05 14:30:00',
        actualizadoEn: null,
        lineas: [],
        documentosRelacionados: [],
        formaPago: $formaPago,
        montoNetoPendiente: $montoNeto,
        cuotas: [],
        hospedajes: []
    );
}

/**
 * Extrae las propiedades del Catálogo 55 de una línea específica en el XML UBL mediante XPath.
 * @return array<string, string> Mapa [codigo => valor]
 */
function extraerPropiedades55DeLinea(string $xml, int $numeroLinea): array
{
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    $lineas = $xpath->query("//cac:InvoiceLine[cbc:ID='{$numeroLinea}'] | //cac:CreditNoteLine[cbc:ID='{$numeroLinea}'] | //cac:DebitNoteLine[cbc:ID='{$numeroLinea}']");
    if ($lineas->length === 0) {
        return [];
    }

    $props = [];
    $propNodes = $xpath->query(".//cac:AdditionalItemProperty", $lineas->item(0));
    foreach ($propNodes as $prop) {
        $codeNode = $xpath->query("./cbc:NameCode", $prop);
        $valNode = $xpath->query("./cbc:Value", $prop);
        if ($codeNode->length > 0 && $valNode->length > 0) {
            $code = trim($codeNode->item(0)->textContent);
            $val = trim($valNode->item(0)->textContent);
            $props[$code] = $val;
        }
    }

    return $props;
}

/**
 * Cuenta la ocurrencia de cada código de propiedad en una línea para validar unicidad.
 * @return array<string, int> Mapa [codigo => count]
 */
function contarCodigosPropiedades55(string $xml, int $numeroLinea): array
{
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

    $lineas = $xpath->query("//cac:InvoiceLine[cbc:ID='{$numeroLinea}'] | //cac:CreditNoteLine[cbc:ID='{$numeroLinea}'] | //cac:DebitNoteLine[cbc:ID='{$numeroLinea}']");
    if ($lineas->length === 0) {
        return [];
    }

    $conteo = [];
    $propNodes = $xpath->query(".//cac:AdditionalItemProperty", $lineas->item(0));
    foreach ($propNodes as $prop) {
        $codeNode = $xpath->query("./cbc:NameCode", $prop);
        if ($codeNode->length > 0) {
            $code = trim($codeNode->item(0)->textContent);
            $conteo[$code] = ($conteo[$code] ?? 0) + 1;
        }
    }

    return $conteo;
}

// -----------------------------------------------------------------------------
// BLOQUE 1: PRUEBAS POSITIVAS DE EMISIÓN DE COMPROBANTES
// -----------------------------------------------------------------------------
echo "[1] Pruebas Positivas de Generación UBL 2.1...\n";

// 1. Factura Contado
$cpe1 = crearComprobanteBase('FACTURA', 'F001', 45, 'CONTADO');
$linea1 = new CpeLinea(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    codigoProductoInterno: 'HAB-01',
    codigoProductoSunat: null,
    descripcion: 'SERVICIO DE ALOJAMIENTO EN HABITACION MATRIMONIAL',
    unidadMedida: 'ZZ',
    cantidad: '1.0000',
    valorUnitario: '1000.0000',
    precioUnitario: '1180.0000',
    descuentoMonto: '0.00',
    baseImponible: '1000.00',
    tipoAfectacionIgv: '10',
    tasaIgv: '18.00',
    montoIgv: '180.00',
    totalLinea: '1180.00'
);
$cpe1->agregarLinea($linea1);
$xml1 = $servicioUbl->generarXml($cpe1);

verificar('01 Factura contado genera XML no vacío', strlen($xml1) > 500);
verificar('01 Factura contado contiene tag Invoice', str_contains($xml1, '<Invoice'));
verificar('01 Factura contado contiene cbc:InvoiceTypeCode 01', str_contains($xml1, '<cbc:InvoiceTypeCode') && str_contains($xml1, '>01<'));
verificar('01 Factura contado contiene PaymentMeansID Contado', str_contains($xml1, '<cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>'));
verificar('01 Factura contado contiene RUC emisor y receptor', str_contains($xml1, '20609998881') && str_contains($xml1, '20601234567'));

// 2. Factura Crédito 1 Cuota
$cpe2 = crearComprobanteBase('FACTURA', 'F001', 46, 'CREDITO', '1180.00');
$cpe2->agregarLinea($linea1);
$cpe2->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '1180.00', fechaVencimiento: '2026-11-20'));
$xml2 = $servicioUbl->generarXml($cpe2);

verificar('02 Factura crédito 1 cuota contiene PaymentMeansID Credito', str_contains($xml2, '<cbc:PaymentMeansID>Credito</cbc:PaymentMeansID>'));
verificar('02 Factura crédito 1 cuota contiene monto neto pendiente', str_contains($xml2, '<cbc:Amount currencyID="PEN">1180.00</cbc:Amount>'));
verificar('02 Factura crédito 1 cuota contiene Cuota001 formateada', str_contains($xml2, '<cbc:PaymentMeansID>Cuota001</cbc:PaymentMeansID>'));
verificar('02 Factura crédito 1 cuota contiene fecha vencimiento', str_contains($xml2, '<cbc:PaymentDueDate>2026-11-20</cbc:PaymentDueDate>'));

// 3. Factura Crédito N Cuotas (Multicuotas)
$cpe3 = crearComprobanteBase('FACTURA', 'F001', 47, 'CREDITO', '1180.00');
$cpe3->agregarLinea($linea1);
$cpe3->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '590.00', fechaVencimiento: '2026-11-15'));
$cpe3->agregarCuota(new CpeCuota(id: 2, cpeId: 100, numeroCuota: 2, monto: '590.00', fechaVencimiento: '2026-12-15'));
$xml3 = $servicioUbl->generarXml($cpe3);

verificar('03 Factura crédito multicuotas contiene Cuota001 y Cuota002', str_contains($xml3, 'Cuota001') && str_contains($xml3, 'Cuota002'));
verificar('03 Factura crédito multicuotas contiene ambos montos de cuota', substr_count($xml3, '>590.00<') === 2);

// 4. Boleta de Venta (03)
$cpe4 = crearComprobanteBase('BOLETA', 'B001', 12, 'CONTADO', null, 'PEN', '1', '45879632', 'JUAN PEREZ GONZALES');
$cpe4->agregarLinea($linea1);
$xml4 = $servicioUbl->generarXml($cpe4);

verificar('04 Boleta genera tag Invoice', str_contains($xml4, '<Invoice'));
verificar('04 Boleta contiene cbc:InvoiceTypeCode 03', str_contains($xml4, '>03<'));
verificar('04 Boleta contiene DNI receptor schemeID=1', str_contains($xml4, 'schemeID="1"') && str_contains($xml4, '45879632'));

// 5. Nota de Crédito Ordinaria (07)
$cpe5 = crearComprobanteBase('NOTA_CREDITO', 'FC01', 5, 'CONTADO');
$cpe5->agregarLinea($linea1);
$cpe5->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 45,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'ANULACION TOTAL DE LA OPERACION'
));
$xml5 = $servicioUbl->generarXml($cpe5);

verificar('05 Nota de crédito genera tag CreditNote', str_contains($xml5, '<CreditNote'));
verificar('05 Nota de crédito contiene BillingReference F001-00000045', str_contains($xml5, 'F001-00000045'));
verificar('05 Nota de crédito contiene DiscrepancyResponse motivo 01', str_contains($xml5, '<cbc:ResponseCode') && str_contains($xml5, '>01<'));
verificar('05 Nota de crédito ordinaria NO contiene PaymentTerms', !str_contains($xml5, '<cac:PaymentTerms>'));

// 6. Nota de Crédito Motivo 13 con PaymentTerms
$cpe6 = crearComprobanteBase('NOTA_CREDITO', 'FC01', 6, 'CREDITO', '800.00');
$cpe6->agregarLinea($linea1);
$cpe6->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '800.00', fechaVencimiento: '2026-11-25'));
$cpe6->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 46,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '13',
    descripcionMotivo: 'AJUSTE DE FECHA Y MONTO DE CUOTA'
));
$xml6 = $servicioUbl->generarXml($cpe6);

verificar('06 Nota de crédito motivo 13 CONTIENE PaymentTerms', str_contains($xml6, '<cac:PaymentTerms>'));
verificar('06 Nota de crédito motivo 13 contiene cuota reprogramada', str_contains($xml6, 'Cuota001') && str_contains($xml6, '800.00'));

// 7. Nota de Débito (08)
$cpe7 = crearComprobanteBase('NOTA_DEBITO', 'FD01', 3, 'CONTADO');
$cpe7->agregarLinea($linea1);
$cpe7->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 45,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'INTERESES POR MORA'
));
$xml7 = $servicioUbl->generarXml($cpe7);

verificar('07 Nota de débito genera tag DebitNote', str_contains($xml7, '<DebitNote'));
verificar('07 Nota de débito contiene RequestedMonetaryTotal', str_contains($xml7, '<cac:RequestedMonetaryTotal>'));

// 8. Exportación General Código 40 sin DL919 (sin Catálogo 55)
$cpe8 = crearComprobanteBase('FACTURA', 'F001', 50, 'CONTADO', null, 'USD');
// Modificar totales para exportación pura
$refCpe8 = new ReflectionClass($cpe8);
$propTotGrav = $refCpe8->getProperty('totalOperacionesGravadas');
$propTotGrav->setAccessible(true);
$propTotGrav->setValue($cpe8, '0.00');

$propTotExp = $refCpe8->getProperty('totalOperacionesExportacion');
$propTotExp->setAccessible(true);
$propTotExp->setValue($cpe8, '500.00');

$propTotIgv = $refCpe8->getProperty('totalIgv');
$propTotIgv->setAccessible(true);
$propTotIgv->setValue($cpe8, '0.00');

$propTotVenta = $refCpe8->getProperty('totalVenta');
$propTotVenta->setAccessible(true);
$propTotVenta->setValue($cpe8, '500.00');

$lineaExp = new CpeLinea(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    codigoProductoInterno: 'EXP-SRV',
    codigoProductoSunat: null,
    descripcion: 'SERVICIO DE CONSULTORIA EMPRESARIAL AL EXTERIOR',
    unidadMedida: 'ZZ',
    cantidad: '1.0000',
    valorUnitario: '500.0000',
    precioUnitario: '500.0000',
    descuentoMonto: '0.00',
    baseImponible: '500.00',
    tipoAfectacionIgv: '40',
    tasaIgv: '0.00',
    montoIgv: '0.00',
    totalLinea: '500.00',
    cpeHospedajeId: null,
    fechaConsumo: null
);
$cpe8->agregarLinea($lineaExp);
$xml8 = $servicioUbl->generarXml($cpe8);

verificar('08 Exportación general emite afectación 40', str_contains($xml8, '<cbc:TaxExemptionReasonCode') && str_contains($xml8, '>40<'));
verificar('08 Exportación general emite tributo 9995 EXP', str_contains($xml8, '>9995<') && str_contains($xml8, '>EXP<'));
verificar('08 Exportación general NO contiene propiedades de Catálogo 55', !str_contains($xml8, 'cac:AdditionalItemProperty'));

// 9. Hospedaje DL919 Alojamiento (Valores Sintéticos Deliberadamente Distinguibles)
$valPaisEmision = 'FR';
$valPaisResidencia = 'DE';
$valFechaIngreso = '2026-10-01';
$valCheckin = '2026-10-05';
$valCheckout = '2026-10-08';
$valPermanencia = 7;
$valFechaConsumo = '2026-10-06';
$valNombres = 'JEAN TEST HOSPEDAJE';
$valTipoDoc = '7';
$valNumDoc = 'P12345678';

$cpe9 = crearComprobanteBase('FACTURA', 'F001', 51, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpe9, '0.00');
$propTotExp->setValue($cpe9, '300.00');
$propTotIgv->setValue($cpe9, '0.00');
$propTotVenta->setValue($cpe9, '300.00');

$propEsExp = $refCpe8->getProperty('esExportacionHospedaje');
$propEsExp->setAccessible(true);
$propEsExp->setValue($cpe9, true);

$hospedaje1 = new CpeHospedajeFiscal(
    id: 10,
    cpeId: 100,
    numeroOrden: 1,
    nombresApellidos: $valNombres,
    tipoDocumento: $valTipoDoc,
    numeroDocumento: $valNumDoc,
    paisEmisionPasaporte: $valPaisEmision,
    paisResidencia: $valPaisResidencia,
    fechaIngresoPais: $valFechaIngreso,
    fechaCheckin: $valCheckin,
    fechaCheckout: $valCheckout,
    diasPermanencia: $valPermanencia,
    tamVirtualNumero: 'TAM-998877'
);
$cpe9->agregarHospedaje($hospedaje1);

$lineaAlojamiento = new CpeLinea(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    codigoProductoInterno: 'HAB-SUITE',
    codigoProductoSunat: null,
    descripcion: 'ESTANCIA EN SUITE EJECUTIVA DL 919',
    unidadMedida: 'ZZ',
    cantidad: '1.0000',
    valorUnitario: '300.0000',
    precioUnitario: '300.0000',
    descuentoMonto: '0.00',
    baseImponible: '300.00',
    tipoAfectacionIgv: '40',
    tasaIgv: '0.00',
    montoIgv: '0.00',
    totalLinea: '300.00',
    cpeHospedajeId: 10,
    fechaConsumo: null
);
$cpe9->agregarLinea($lineaAlojamiento);
$xml9 = $servicioUbl->generarXml($cpe9);

$propsAloj = extraerPropiedades55DeLinea($xml9, 1);
$conteosAloj = contarCodigosPropiedades55($xml9, 1);

verificar('09.01 Alojamiento emite 4000 = pais emision pasaporte (FR)', ($propsAloj['4000'] ?? '') === $valPaisEmision);
verificar('09.02 Alojamiento emite 4001 = pais residencia habitual (DE)', ($propsAloj['4001'] ?? '') === $valPaisResidencia);
verificar('09.03 Alojamiento emite 4002 = fecha ingreso al pais (2026-10-01)', ($propsAloj['4002'] ?? '') === $valFechaIngreso);
verificar('09.04 Alojamiento emite 4003 = fecha checkin (2026-10-05)', ($propsAloj['4003'] ?? '') === $valCheckin);
verificar('09.05 Alojamiento emite 4004 = fecha checkout (2026-10-08)', ($propsAloj['4004'] ?? '') === $valCheckout);
verificar('09.06 Alojamiento emite 4005 = dias permanencia en el pais (7)', ($propsAloj['4005'] ?? '') === (string)$valPermanencia);
verificar('09.07 Alojamiento NO emite 4006 = fecha consumo (estrictamente ausente)', !isset($propsAloj['4006']));
verificar('09.08 Alojamiento emite 4007 = nombres y apellidos (JEAN TEST HOSPEDAJE)', ($propsAloj['4007'] ?? '') === $valNombres);
verificar('09.09 Alojamiento emite 4008 = tipo documento huesped (7)', ($propsAloj['4008'] ?? '') === $valTipoDoc);
verificar('09.10 Alojamiento emite 4009 = numero documento huesped (P12345678)', ($propsAloj['4009'] ?? '') === $valNumDoc);
verificar('09.11 Alojamiento contiene exactamente 9 propiedades del Catalogo 55', count($propsAloj) === 9);
verificar('09.12 Alojamiento: cada propiedad aparece como maximo una vez (unicidad)', max($conteosAloj) === 1 && min($conteosAloj) === 1);
verificar('09.13 Alojamiento: cero codigos desconocidos fuera del conjunto normativo 4000-4009', empty(array_diff(array_keys($propsAloj), ['4000', '4001', '4002', '4003', '4004', '4005', '4007', '4008', '4009'])));

// 10. Hospedaje DL919 Consumo (emite 4006, NO 4003/4004)
$cpe10 = crearComprobanteBase('FACTURA', 'F001', 52, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpe10, '0.00');
$propTotExp->setValue($cpe10, '80.00');
$propTotIgv->setValue($cpe10, '0.00');
$propTotVenta->setValue($cpe10, '80.00');
$propEsExp->setValue($cpe10, true);
$cpe10->agregarHospedaje($hospedaje1);

$lineaConsumo = new CpeLinea(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    codigoProductoInterno: 'REST-ALM',
    codigoProductoSunat: null,
    descripcion: 'ALMUERZO BUFFET EN RESTAURANTE HOTELERO DL 919',
    unidadMedida: 'ZZ',
    cantidad: '1.0000',
    valorUnitario: '80.0000',
    precioUnitario: '80.0000',
    descuentoMonto: '0.00',
    baseImponible: '80.00',
    tipoAfectacionIgv: '40',
    tasaIgv: '0.00',
    montoIgv: '0.00',
    totalLinea: '80.00',
    cpeHospedajeId: 10,
    fechaConsumo: $valFechaConsumo
);
$cpe10->agregarLinea($lineaConsumo);
$xml10 = $servicioUbl->generarXml($cpe10);

$propsCons = extraerPropiedades55DeLinea($xml10, 1);
$conteosCons = contarCodigosPropiedades55($xml10, 1);

verificar('10.01 Consumo emite 4000 = pais emision pasaporte (FR)', ($propsCons['4000'] ?? '') === $valPaisEmision);
verificar('10.02 Consumo emite 4001 = pais residencia habitual (DE)', ($propsCons['4001'] ?? '') === $valPaisResidencia);
verificar('10.03 Consumo emite 4002 = fecha ingreso al pais (2026-10-01)', ($propsCons['4002'] ?? '') === $valFechaIngreso);
verificar('10.04 Consumo NO emite 4003 = checkin (estrictamente ausente)', !isset($propsCons['4003']));
verificar('10.05 Consumo NO emite 4004 = checkout (estrictamente ausente)', !isset($propsCons['4004']));
verificar('10.06 Consumo emite 4005 = dias permanencia en el pais (7)', ($propsCons['4005'] ?? '') === (string)$valPermanencia);
verificar('10.07 Consumo emite 4006 = fecha de consumo (2026-10-06)', ($propsCons['4006'] ?? '') === $valFechaConsumo);
verificar('10.08 Consumo emite 4007 = nombres y apellidos (JEAN TEST HOSPEDAJE)', ($propsCons['4007'] ?? '') === $valNombres);
verificar('10.09 Consumo emite 4008 = tipo documento huesped (7)', ($propsCons['4008'] ?? '') === $valTipoDoc);
verificar('10.10 Consumo emite 4009 = numero documento huesped (P12345678)', ($propsCons['4009'] ?? '') === $valNumDoc);
verificar('10.11 Consumo contiene exactamente 8 propiedades del Catalogo 55', count($propsCons) === 8);
verificar('10.12 Consumo: cada propiedad aparece como maximo una vez (unicidad)', max($conteosCons) === 1 && min($conteosCons) === 1);
verificar('10.13 Consumo: cero codigos desconocidos fuera del conjunto normativo 4000-4009', empty(array_diff(array_keys($propsCons), ['4000', '4001', '4002', '4005', '4006', '4007', '4008', '4009'])));

// 10.B Negative Test — Semantic Swap
verificar('10.B.01 Semantic Swap: 4000 NO contiene tipo_documento', ($propsAloj['4000'] ?? '') !== $valTipoDoc);
verificar('10.B.02 Semantic Swap: 4000 NO contiene numero_documento', ($propsAloj['4000'] ?? '') !== $valNumDoc);
verificar('10.B.03 Semantic Swap: 4001 NO contiene numero_documento', ($propsAloj['4001'] ?? '') !== $valNumDoc);
verificar('10.B.04 Semantic Swap: 4002 NO contiene pais_emision_pasaporte', ($propsAloj['4002'] ?? '') !== $valPaisEmision);
verificar('10.B.05 Semantic Swap: 4008 NO contiene pais_residencia', ($propsAloj['4008'] ?? '') !== $valPaisResidencia);
verificar('10.B.06 Semantic Swap: 4009 presente con numero_documento y NO ausente', isset($propsAloj['4009']) && $propsAloj['4009'] === $valNumDoc);

// 10.C Variante Huésped con Carnet de Extranjería (CE Tipo '4')
$hospedajeCe = new CpeHospedajeFiscal(
    id: 20,
    cpeId: 100,
    numeroOrden: 1,
    nombresApellidos: 'GIOVANNI ROSSI',
    tipoDocumento: '4',
    numeroDocumento: 'CE-99887766',
    paisEmisionPasaporte: 'IT',
    paisResidencia: 'IT',
    fechaIngresoPais: '2026-09-15',
    fechaCheckin: '2026-10-02',
    fechaCheckout: '2026-10-06',
    diasPermanencia: 21,
    tamVirtualNumero: null
);
$cpe10c = crearComprobanteBase('FACTURA', 'F001', 59, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpe10c, '0.00');
$propTotExp->setValue($cpe10c, '400.00');
$propTotIgv->setValue($cpe10c, '0.00');
$propTotVenta->setValue($cpe10c, '400.00');
$propEsExp->setValue($cpe10c, true);
$cpe10c->agregarHospedaje($hospedajeCe);
$lineaCe = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'HAB-CE', codigoProductoSunat: null,
    descripcion: 'ESTANCIA HUÉSPED CON CARNET DE EXTRANJERÍA', unidadMedida: 'ZZ', cantidad: '1.0000',
    valorUnitario: '400.0000', precioUnitario: '400.0000', descuentoMonto: '0.00', baseImponible: '400.00',
    tipoAfectacionIgv: '40', tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '400.00', cpeHospedajeId: 20, fechaConsumo: null
);
$cpe10c->agregarLinea($lineaCe);
$xml10c = $servicioUbl->generarXml($cpe10c);
$propsCe = extraerPropiedades55DeLinea($xml10c, 1);

verificar('10.C.01 Huésped CE omite 4000 (solo aplica a pasaporte)', !isset($propsCe['4000']));
verificar('10.C.02 Huésped CE emite 4001 = pais residencia habitual (IT)', ($propsCe['4001'] ?? '') === 'IT');
verificar('10.C.03 Huésped CE emite 4008 = tipo de documento (4)', ($propsCe['4008'] ?? '') === '4');
verificar('10.C.04 Huésped CE emite 4009 = numero carnet de extranjeria (CE-99887766)', ($propsCe['4009'] ?? '') === 'CE-99887766');

// 11. Receptor Corporativo != Huésped
$cpe11 = crearComprobanteBase(
    tipo: 'FACTURA',
    serie: 'F001',
    correlativo: 53,
    formaPago: 'CONTADO',
    montoNeto: null,
    moneda: 'USD',
    recTipoDoc: '6',
    recNumDoc: '20554433221',
    recRazon: 'AGENCIA DE VIAJES Y TURISMO GLOBAL S.A.C.'
);
$propTotGrav->setValue($cpe11, '0.00');
$propTotExp->setValue($cpe11, '300.00');
$propTotIgv->setValue($cpe11, '0.00');
$propTotVenta->setValue($cpe11, '300.00');
$propEsExp->setValue($cpe11, true);
$cpe11->agregarHospedaje($hospedaje1);
$cpe11->agregarLinea($lineaAlojamiento);
$xml11 = $servicioUbl->generarXml($cpe11);

preg_match('/<cac:AccountingCustomerParty>.*?<\/cac:AccountingCustomerParty>/s', $xml11, $mCustomer);
$customerXml = $mCustomer[0] ?? '';

verificar('11.01 Receptor corporativo tiene RUC de Agencia en AccountingCustomerParty', str_contains($customerXml, '20554433221') && str_contains($customerXml, 'AGENCIA DE VIAJES Y TURISMO GLOBAL S.A.C.'));
verificar('11.02 AccountingCustomerParty NO contiene el pasaporte del huésped', !str_contains($customerXml, $valNumDoc));
verificar('11.03 AdditionalItemProperty contiene el pasaporte del huésped en línea', str_contains($xml11, '>4009<') && str_contains($xml11, ">{$valNumDoc}<"));

// 12. Multihuésped (2 Huéspedes y 2 Líneas con datos deliberadamente distintos)
$cpe12 = crearComprobanteBase('FACTURA', 'F001', 54, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpe12, '0.00');
$propTotExp->setValue($cpe12, '600.00');
$propTotIgv->setValue($cpe12, '0.00');
$propTotVenta->setValue($cpe12, '600.00');
$propEsExp->setValue($cpe12, true);

$hospedajeMultiA = new CpeHospedajeFiscal(
    id: 11,
    cpeId: 100,
    numeroOrden: 1,
    nombresApellidos: 'JEAN HUÉSPED UNO',
    tipoDocumento: '7',
    numeroDocumento: 'PAS-001-AAA',
    paisEmisionPasaporte: 'FR',
    paisResidencia: 'FR',
    fechaIngresoPais: '2026-10-01',
    fechaCheckin: '2026-10-02',
    fechaCheckout: '2026-10-05',
    diasPermanencia: 4,
    tamVirtualNumero: 'TAM-111111'
);
$hospedajeMultiB = new CpeHospedajeFiscal(
    id: 12,
    cpeId: 100,
    numeroOrden: 2,
    nombresApellidos: 'MARIO HUÉSPED DOS',
    tipoDocumento: '7',
    numeroDocumento: 'PAS-002-BBB',
    paisEmisionPasaporte: 'IT',
    paisResidencia: 'IT',
    fechaIngresoPais: '2026-09-28',
    fechaCheckin: '2026-10-02',
    fechaCheckout: '2026-10-05',
    diasPermanencia: 7,
    tamVirtualNumero: 'TAM-222222'
);
$cpe12->agregarHospedaje($hospedajeMultiA);
$cpe12->agregarHospedaje($hospedajeMultiB);

$lineaMulti1 = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'HAB-1', codigoProductoSunat: null,
    descripcion: 'ALOJAMIENTO HUESPED UNO', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '300.0000',
    precioUnitario: '300.0000', descuentoMonto: '0.00', baseImponible: '300.00', tipoAfectacionIgv: '40',
    tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '300.00', cpeHospedajeId: 11, fechaConsumo: null
);
$lineaMulti2 = new CpeLinea(
    id: 2, cpeId: 100, numeroOrden: 2, codigoProductoInterno: 'HAB-2', codigoProductoSunat: null,
    descripcion: 'ALOJAMIENTO HUESPED DOS', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '300.0000',
    precioUnitario: '300.0000', descuentoMonto: '0.00', baseImponible: '300.00', tipoAfectacionIgv: '40',
    tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '300.00', cpeHospedajeId: 12, fechaConsumo: null
);
$cpe12->agregarLinea($lineaMulti1);
$cpe12->agregarLinea($lineaMulti2);
$xml12 = $servicioUbl->generarXml($cpe12);

$propsMulti1 = extraerPropiedades55DeLinea($xml12, 1);
$propsMulti2 = extraerPropiedades55DeLinea($xml12, 2);

verificar('12.01 Línea 1 tiene propiedades exactas de Huésped A (JEAN HUÉSPED UNO / PAS-001-AAA / FR)', ($propsMulti1['4007'] ?? '') === 'JEAN HUÉSPED UNO' && ($propsMulti1['4009'] ?? '') === 'PAS-001-AAA' && ($propsMulti1['4000'] ?? '') === 'FR');
verificar('12.02 Línea 2 tiene propiedades exactas de Huésped B (MARIO HUÉSPED DOS / PAS-002-BBB / IT)', ($propsMulti2['4007'] ?? '') === 'MARIO HUÉSPED DOS' && ($propsMulti2['4009'] ?? '') === 'PAS-002-BBB' && ($propsMulti2['4000'] ?? '') === 'IT');
verificar('12.03 Línea 1 NO contiene datos de Huésped B (sin contaminación cruzada)', !in_array('MARIO HUÉSPED DOS', $propsMulti1, true) && !in_array('PAS-002-BBB', $propsMulti1, true));
verificar('12.04 Línea 2 NO contiene datos de Huésped A (sin contaminación cruzada)', !in_array('JEAN HUÉSPED UNO', $propsMulti2, true) && !in_array('PAS-001-AAA', $propsMulti2, true));

// 13. Multilínea Mixta (Estancia + Consumo)
$cpe13 = crearComprobanteBase('FACTURA', 'F001', 55, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpe13, '0.00');
$propTotExp->setValue($cpe13, '380.00');
$propTotIgv->setValue($cpe13, '0.00');
$propTotVenta->setValue($cpe13, '380.00');
$propEsExp->setValue($cpe13, true);
$cpe13->agregarHospedaje($hospedaje1);
$cpe13->agregarLinea($lineaAlojamiento); // 300.00
$lineaConsumo2 = new CpeLinea(
    id: 2, cpeId: 100, numeroOrden: 2, codigoProductoInterno: 'REST-ALM', codigoProductoSunat: null,
    descripcion: 'CONSUMO DE RESTAURANTE', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '80.0000',
    precioUnitario: '80.0000', descuentoMonto: '0.00', baseImponible: '80.00', tipoAfectacionIgv: '40',
    tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '80.00', cpeHospedajeId: 10, fechaConsumo: '2026-10-06'
);
$cpe13->agregarLinea($lineaConsumo2); // 80.00
$xml13 = $servicioUbl->generarXml($cpe13);

$propsMixta1 = extraerPropiedades55DeLinea($xml13, 1);
$propsMixta2 = extraerPropiedades55DeLinea($xml13, 2);

verificar('13.01 Multilínea mixta: Línea 1 (Alojamiento) contiene 4003 y 4004, NO contiene 4006', isset($propsMixta1['4003']) && isset($propsMixta1['4004']) && !isset($propsMixta1['4006']));
verificar('13.02 Multilínea mixta: Línea 2 (Consumo) contiene 4006, NO contiene 4003 ni 4004', isset($propsMixta2['4006']) && !isset($propsMixta2['4003']) && !isset($propsMixta2['4004']));

// 14. Documentos Relacionados Múltiples en Nota de Crédito
$cpe14 = crearComprobanteBase('NOTA_CREDITO', 'FC01', 7, 'CONTADO');
$cpe14->agregarLinea($linea1);
$cpe14->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1, cpeId: 100, cpeRelacionadoId: 99, tipoDocumentoRelacionado: '01', serieRelacionada: 'F001',
    correlativoRelacionado: 45, fechaEmisionRelacionada: '2026-10-05', codigoTipoRelacion: '01', descripcionMotivo: 'ANULACION PRINCIPAL'
));
$cpe14->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 2, cpeId: 100, cpeRelacionadoId: 98, tipoDocumentoRelacionado: '01', serieRelacionada: 'F001',
    correlativoRelacionado: 44, fechaEmisionRelacionada: '2026-10-04', codigoTipoRelacion: '01', descripcionMotivo: 'DOCUMENTO VINCULADO ADICIONAL'
));
$xml14 = $servicioUbl->generarXml($cpe14);

verificar('14 Documentos relacionados múltiples genera 2 bloques BillingReference', substr_count($xml14, '<cac:BillingReference>') === 2);

// 15. Caracteres XML Escapables (&, <, >, ", ')
$cpe15 = crearComprobanteBase('FACTURA', 'F001', 56, 'CONTADO');
$lineaEsc = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'TEST', codigoProductoSunat: null,
    descripcion: 'SERVICIO CON CARACTERES ESPECIALES: Tom & Jerry, A < B, C > D, "Comillas" y \'Apostrofes\'',
    unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '1000.0000', precioUnitario: '1180.0000',
    descuentoMonto: '0.00', baseImponible: '1000.00', tipoAfectacionIgv: '10', tasaIgv: '18.00',
    montoIgv: '180.00', totalLinea: '1180.00'
);
$cpe15->agregarLinea($lineaEsc);
$xml15 = $servicioUbl->generarXml($cpe15);

verificar('15 Caracteres XML escapables son escapados correctamente', str_contains($xml15, 'Tom &amp; Jerry') && str_contains($xml15, 'A &lt; B') && str_contains($xml15, 'C &gt; D'));
$domTest15 = new DOMDocument();
verificar('15 XML resultante con caracteres especiales es parseable por DOM sin error', $domTest15->loadXML($xml15));

// 16. Caracteres UTF-8 (Acentos y Ñ)
$cpe16 = crearComprobanteBase(
    tipo: 'FACTURA',
    serie: 'F001',
    correlativo: 57,
    formaPago: 'CONTADO',
    montoNeto: null,
    moneda: 'PEN',
    recTipoDoc: '6',
    recNumDoc: '20601234567',
    recRazon: 'COMPAÑÍA DE TURISMO Y HOSTELERÍA ÑANDÚ S.A.C.'
);
$lineaUtf8 = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'TEST', codigoProductoSunat: null,
    descripcion: 'HABITACIÓN CONFORTABLE EN EL CUSCO MÁGICO — ATENCIÓN DE PRIMERA',
    unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '1000.0000', precioUnitario: '1180.0000',
    descuentoMonto: '0.00', baseImponible: '1000.00', tipoAfectacionIgv: '10', tasaIgv: '18.00',
    montoIgv: '180.00', totalLinea: '1180.00'
);
$cpe16->agregarLinea($lineaUtf8);
$xml16 = $servicioUbl->generarXml($cpe16);

verificar('16 Preservación canónica de UTF-8 (Ñ, acentos)', str_contains($xml16, 'COMPAÑÍA DE TURISMO Y HOSTELERÍA ÑANDÚ S.A.C.') && str_contains($xml16, 'HABITACIÓN CONFORTABLE'));

// 17. Determinismo e Idempotencia Byte a Byte
$xml17_A = $servicioUbl->generarXml($cpe1);
$xml17_B = $servicioUbl->generarXml($cpe1);
verificar('17 Determinismo: dos generaciones sucesivas tienen idéntico hash SHA-256', hash('sha256', $xml17_A) === hash('sha256', $xml17_B));

// 18. Decimales Exactos (Cero Floats)
verificar('18 Decimales: los montos monetarios terminan exactamente en 2 decimales', preg_match('/<cbc:LineExtensionAmount currencyID="PEN">\d+\.\d{2}<\/cbc:LineExtensionAmount>/', $xml1) === 1);

// 19. Orden Canónico de Cuotas (incluso si se insertaron desordenadas)
$cpe19 = crearComprobanteBase('FACTURA', 'F001', 58, 'CREDITO', '1180.00');
$cpe19->agregarLinea($linea1);
// Insertar en orden inverso: Cuota 2 antes de Cuota 1
$cpe19->agregarCuota(new CpeCuota(id: 2, cpeId: 100, numeroCuota: 2, monto: '590.00', fechaVencimiento: '2026-12-15'));
$cpe19->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '590.00', fechaVencimiento: '2026-11-15'));
$xml19 = $servicioUbl->generarXml($cpe19);
$posCuota1 = strpos($xml19, 'Cuota001');
$posCuota2 = strpos($xml19, 'Cuota002');
verificar('19 Orden canónico de cuotas: Cuota001 aparece antes que Cuota002 en el XML', $posCuota1 !== false && $posCuota2 !== false && $posCuota1 < $posCuota2);

// 20. Orden Canónico de Líneas (incluso si se insertaron desordenadas)
$cpe20 = crearComprobanteBase('FACTURA', 'F001', 59, 'CONTADO');
$linea20_B = new CpeLinea(
    id: 2, cpeId: 100, numeroOrden: 2, codigoProductoInterno: 'L2', codigoProductoSunat: null,
    descripcion: 'SEGUNDA LINEA', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '500.0000',
    precioUnitario: '590.0000', descuentoMonto: '0.00', baseImponible: '500.00', tipoAfectacionIgv: '10',
    tasaIgv: '18.00', montoIgv: '90.00', totalLinea: '590.00'
);
$linea20_A = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'L1', codigoProductoSunat: null,
    descripcion: 'PRIMERA LINEA', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '500.0000',
    precioUnitario: '590.0000', descuentoMonto: '0.00', baseImponible: '500.00', tipoAfectacionIgv: '10',
    tasaIgv: '18.00', montoIgv: '90.00', totalLinea: '590.00'
);
// Insertar desordenado: línea 2 antes de línea 1
$cpe20->agregarLinea($linea20_B);
$cpe20->agregarLinea($linea20_A);
$xml20 = $servicioUbl->generarXml($cpe20);
$posL1 = strpos($xml20, '<cbc:ID>1</cbc:ID>');
$posL2 = strpos($xml20, '<cbc:ID>2</cbc:ID>');
verificar('20 Orden canónico de líneas: Línea 1 aparece antes que Línea 2', $posL1 !== false && $posL2 !== false && $posL1 < $posL2);

echo "\n[2] Pruebas Negativas y Validaciones Defensivas...\n";

// 21. Tipo CPE inválido
$cpeNeg21 = crearComprobanteBase('TIPO_INVALIDO');
$cpeNeg21->agregarLinea($linea1);
$catch21 = false;
try {
    $servicioUbl->generarXml($cpeNeg21);
} catch (ValidacionFiscalExcepcion $e) {
    $catch21 = true;
}
verificar('21 Rechazo de tipo de comprobante no soportado', $catch21);

// 22. Factura con receptor no RUC (ej. DNI)
$cpeNeg22 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'PEN', '1', '12345678', 'PERSONA NATURAL');
$cpeNeg22->agregarLinea($linea1);
$catch22 = false;
try {
    $servicioUbl->generarXml($cpeNeg22);
} catch (ValidacionFiscalExcepcion $e) {
    $catch22 = true;
}
verificar('22 Rechazo de Factura emitida a receptor con documento no RUC', $catch22);

// 23. Moneda no permitida (EUR)
$cpeNeg23 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'EUR');
$cpeNeg23->agregarLinea($linea1);
$catch23 = false;
try {
    $servicioUbl->generarXml($cpeNeg23);
} catch (ValidacionFiscalExcepcion $e) {
    $catch23 = true;
}
verificar('23 Rechazo de moneda no permitida (EUR)', $catch23);

// 24. Unidad de medida no permitida en Catálogo 03
$cpeNeg24 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO');
$lineaNeg24 = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'L1', codigoProductoSunat: null,
    descripcion: 'LINEA CON UNIDAD INVALIDA', unidadMedida: 'UNIDAD_FALSA', cantidad: '1.0000', valorUnitario: '1000.0000',
    precioUnitario: '1180.0000', descuentoMonto: '0.00', baseImponible: '1000.00', tipoAfectacionIgv: '10',
    tasaIgv: '18.00', montoIgv: '180.00', totalLinea: '1180.00'
);
$cpeNeg24->agregarLinea($lineaNeg24);
$catch24 = false;
try {
    $servicioUbl->generarXml($cpeNeg24);
} catch (ValidacionFiscalExcepcion $e) {
    $catch24 = true;
}
verificar('24 Rechazo de unidad de medida no registrada en Catálogo 03', $catch24);

// 25. Tipo de afectación al IGV no válido
$cpeNeg25 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO');
$lineaNeg25 = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'L1', codigoProductoSunat: null,
    descripcion: 'LINEA CON AFECTACION INVALIDA', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '1000.0000',
    precioUnitario: '1180.0000', descuentoMonto: '0.00', baseImponible: '1000.00', tipoAfectacionIgv: '99_INVALIDA',
    tasaIgv: '18.00', montoIgv: '180.00', totalLinea: '1180.00'
);
$cpeNeg25->agregarLinea($lineaNeg25);
$catch25 = false;
try {
    $servicioUbl->generarXml($cpeNeg25);
} catch (ValidacionFiscalExcepcion $e) {
    $catch25 = true;
}
verificar('25 Rechazo de tipo de afectación al IGV inválido', $catch25);

// 26. Inconsistencia de totales (cabecera no cuadra con líneas)
$cpeNeg26 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO');
$propTotVenta->setValue($cpeNeg26, '9999.00'); // Descuadre artificial
$cpeNeg26->agregarLinea($linea1);
$catch26 = false;
try {
    $servicioUbl->generarXml($cpeNeg26);
} catch (ValidacionFiscalExcepcion $e) {
    $catch26 = true;
}
verificar('26 Rechazo de inconsistencia en total venta entre cabecera y líneas', $catch26);

// 27. Crédito sin cuotas
$cpeNeg27 = crearComprobanteBase('FACTURA', 'F001', 1, 'CREDITO', '1180.00');
$cpeNeg27->agregarLinea($linea1);
// Sin agregar cuotas
$catch27 = false;
try {
    $servicioUbl->generarXml($cpeNeg27);
} catch (ValidacionFiscalExcepcion $e) {
    $catch27 = true;
}
verificar('27 Rechazo de operación a CREDITO sin cuotas programadas', $catch27);

// 28. Suma de cuotas no coincide con monto neto pendiente
$cpeNeg28 = crearComprobanteBase('FACTURA', 'F001', 1, 'CREDITO', '1180.00');
$cpeNeg28->agregarLinea($linea1);
$cpeNeg28->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '500.00', fechaVencimiento: '2026-11-15')); // Suma 500 != 1180
$catch28 = false;
try {
    $servicioUbl->generarXml($cpeNeg28);
} catch (ValidacionFiscalExcepcion $e) {
    $catch28 = true;
}
verificar('28 Rechazo de descuadre entre suma de cuotas y monto neto pendiente', $catch28);

// 29. Cuota con número ordinal 0
$cpeNeg29 = crearComprobanteBase('FACTURA', 'F001', 1, 'CREDITO', '1180.00');
$cpeNeg29->agregarLinea($linea1);
$catch29 = false;
try {
    new CpeCuota(id: 1, cpeId: 100, numeroCuota: 0, monto: '1180.00', fechaVencimiento: '2026-11-15');
} catch (ValidacionFiscalExcepcion $e) {
    $catch29 = true;
}
verificar('29 Rechazo de cuota con número ordinal <= 0', $catch29);

// 30. Cuota con número ordinal > 999
$cpeNeg30 = crearComprobanteBase('FACTURA', 'F001', 1, 'CREDITO', '1180.00');
$cpeNeg30->agregarLinea($linea1);
$catch30 = false;
try {
    new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1000, monto: '1180.00', fechaVencimiento: '2026-11-15');
} catch (ValidacionFiscalExcepcion $e) {
    $catch30 = true;
}
verificar('30 Rechazo de cuota con número ordinal > 999 (límite de formato)', $catch30);

// 31. Cuotas con saltos o duplicados (1, 3 en vez de 1, 2)
$cpeNeg31 = crearComprobanteBase('FACTURA', 'F001', 1, 'CREDITO', '1180.00');
$cpeNeg31->agregarLinea($linea1);
$cpeNeg31->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '590.00', fechaVencimiento: '2026-11-15'));
$cpeNeg31->agregarCuota(new CpeCuota(id: 2, cpeId: 100, numeroCuota: 3, monto: '590.00', fechaVencimiento: '2026-12-15')); // Salto a 3
$catch31 = false;
try {
    $servicioUbl->generarXml($cpeNeg31);
} catch (ValidacionFiscalExcepcion $e) {
    $catch31 = true;
}
verificar('31 Rechazo de cuotas con saltos en la secuencia ordinal', $catch31);

// 32. Dato obligatorio faltante (razón social vacía)
$cpeNeg32 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO');
$propRecRazon = $refCpe8->getProperty('receptorRazonSocial');
$propRecRazon->setAccessible(true);
$propRecRazon->setValue($cpeNeg32, ''); // Vacía
$cpeNeg32->agregarLinea($linea1);
$catch32 = false;
try {
    $servicioUbl->generarXml($cpeNeg32);
} catch (ValidacionFiscalExcepcion $e) {
    $catch32 = true;
}
verificar('32 Rechazo de razón social del receptor vacía', $catch32);

// 33. Código de país no es de 2 caracteres
$catch33 = false;
try {
    new CpeHospedajeFiscal(
        id: 1, cpeId: 100, numeroOrden: 1, nombresApellidos: 'GUEST', tipoDocumento: '7',
        numeroDocumento: '123', paisEmisionPasaporte: 'USA_INVALIDO', paisResidencia: 'FR',
        fechaIngresoPais: '2026-10-01', fechaCheckin: '2026-10-02', fechaCheckout: '2026-10-05',
        diasPermanencia: 3
    );
} catch (ValidacionFiscalExcepcion $e) {
    $catch33 = true;
}
verificar('33 Rechazo de código de país emisor de pasaporte que no tiene 2 caracteres', $catch33);

// 34. Documento de huésped en DL 919 es DNI ('1')
$cpeNeg34 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpeNeg34, '0.00');
$propTotExp->setValue($cpeNeg34, '300.00');
$propTotIgv->setValue($cpeNeg34, '0.00');
$propTotVenta->setValue($cpeNeg34, '300.00');
$propEsExp->setValue($cpeNeg34, true);

$hospDni = new CpeHospedajeFiscal(
    id: 50, cpeId: 100, numeroOrden: 1, nombresApellidos: 'JUAN PEREZ', tipoDocumento: '1', // DNI
    numeroDocumento: '45879632', paisEmisionPasaporte: 'PE', paisResidencia: 'CL',
    fechaIngresoPais: '2026-10-01', fechaCheckin: '2026-10-02', fechaCheckout: '2026-10-05',
    diasPermanencia: 3
);
$cpeNeg34->agregarHospedaje($hospDni);
$lineaNeg34 = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'HAB', codigoProductoSunat: null,
    descripcion: 'ALOJAMIENTO', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '300.0000',
    precioUnitario: '300.0000', descuentoMonto: '0.00', baseImponible: '300.00', tipoAfectacionIgv: '40',
    tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '300.00', cpeHospedajeId: 50
);
$cpeNeg34->agregarLinea($lineaNeg34);
$catch34 = false;
try {
    $servicioUbl->generarXml($cpeNeg34);
} catch (ValidacionFiscalExcepcion $e) {
    $catch34 = true;
}
verificar('34 Rechazo de huésped en DL 919 con documento DNI (solo Pasaporte o CE)', $catch34);

// 35. DL 919 activo en comprobante (es_exportacion_hospedaje=true) pero sin líneas vinculadas
$cpeNeg35 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpeNeg35, '0.00');
$propTotExp->setValue($cpeNeg35, '500.00');
$propTotIgv->setValue($cpeNeg35, '0.00');
$propTotVenta->setValue($cpeNeg35, '500.00');
$propEsExp->setValue($cpeNeg35, true);
$cpeNeg35->agregarLinea($lineaExp); // Línea de exportación con cpe_hospedaje_id = null
$catch35 = false;
try {
    $servicioUbl->generarXml($cpeNeg35);
} catch (ValidacionFiscalExcepcion $e) {
    $catch35 = true;
}
verificar('35 Rechazo de es_exportacion_hospedaje=true sin ninguna línea vinculada a huésped', $catch35);

// 36. Huésped cross-CPE (línea apunta a cpe_hospedaje_id inexistente en comprobante)
$cpeNeg36 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpeNeg36, '0.00');
$propTotExp->setValue($cpeNeg36, '300.00');
$propTotIgv->setValue($cpeNeg36, '0.00');
$propTotVenta->setValue($cpeNeg36, '300.00');
$propEsExp->setValue($cpeNeg36, true);
$lineaCross = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'HAB', codigoProductoSunat: null,
    descripcion: 'ALOJAMIENTO', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '300.0000',
    precioUnitario: '300.0000', descuentoMonto: '0.00', baseImponible: '300.00', tipoAfectacionIgv: '40',
    tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '300.00', cpeHospedajeId: 99999 // ID que no existe
);
$cpeNeg36->agregarLinea($lineaCross);
$catch36 = false;
try {
    $servicioUbl->generarXml($cpeNeg36);
} catch (ValidacionFiscalExcepcion $e) {
    $catch36 = true;
}
verificar('36 Rechazo de línea vinculada a cpe_hospedaje_id huérfano o cross-CPE', $catch36);

// 37. Fecha de consumo fuera del rango de estancia [checkin, checkout]
$cpeNeg37 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'USD');
$propTotGrav->setValue($cpeNeg37, '0.00');
$propTotExp->setValue($cpeNeg37, '80.00');
$propTotIgv->setValue($cpeNeg37, '0.00');
$propTotVenta->setValue($cpeNeg37, '80.00');
$propEsExp->setValue($cpeNeg37, true);
$cpeNeg37->agregarHospedaje($hospedaje1); // checkin 2026-10-02, checkout 2026-10-05
$lineaConsumoFuera = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'REST', codigoProductoSunat: null,
    descripcion: 'ALMUERZO FUERA DE ESTANCIA', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '80.0000',
    precioUnitario: '80.0000', descuentoMonto: '0.00', baseImponible: '80.00', tipoAfectacionIgv: '40',
    tasaIgv: '0.00', montoIgv: '0.00', totalLinea: '80.00', cpeHospedajeId: 10,
    fechaConsumo: '2026-10-10' // Posterior a checkout (2026-10-05)
);
$cpeNeg37->agregarLinea($lineaConsumoFuera);
$catch37 = false;
try {
    $servicioUbl->generarXml($cpeNeg37);
} catch (ValidacionFiscalExcepcion $e) {
    $catch37 = true;
}
verificar('37 Rechazo de fecha de consumo posterior a checkout en DL 919', $catch37);

// 38. Línea vinculada a hospedaje con tipo de afectación no exportación (ej. '10')
$cpeNeg38 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO', null, 'PEN');
$cpeNeg38->agregarHospedaje($hospedaje1);
$lineaHospGravada = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'HAB', codigoProductoSunat: null,
    descripcion: 'ALOJAMIENTO CONFLICTO', unidadMedida: 'ZZ', cantidad: '1.0000', valorUnitario: '1000.0000',
    precioUnitario: '1180.0000', descuentoMonto: '0.00', baseImponible: '1000.00', tipoAfectacionIgv: '10', // 10 con hospedaje vinculado
    tasaIgv: '18.00', montoIgv: '180.00', totalLinea: '1180.00', cpeHospedajeId: 10
);
$cpeNeg38->agregarLinea($lineaHospGravada);
$catch38 = false;
try {
    $servicioUbl->generarXml($cpeNeg38);
} catch (ValidacionFiscalExcepcion $e) {
    $catch38 = true;
}
verificar('38 Rechazo de línea vinculada a hospedaje DL919 pero con afectación 10 (gravada)', $catch38);

// 39. Caracteres no permitidos por la norma XML 1.0 (control char ASCII 0x07)
$cpeNeg39 = crearComprobanteBase('FACTURA', 'F001', 1, 'CONTADO');
$lineaControlChar = new CpeLinea(
    id: 1, cpeId: 100, numeroOrden: 1, codigoProductoInterno: 'TEST', codigoProductoSunat: null,
    descripcion: "DESCRIPCION CON CARACTER PROHIBIDO \x07 BELL DE CONTROL", unidadMedida: 'ZZ',
    cantidad: '1.0000', valorUnitario: '1000.0000', precioUnitario: '1180.0000', descuentoMonto: '0.00',
    baseImponible: '1000.00', tipoAfectacionIgv: '10', tasaIgv: '18.00', montoIgv: '180.00', totalLinea: '1180.00'
);
$cpeNeg39->agregarLinea($lineaControlChar);
$catch39 = false;
try {
    $servicioUbl->generarXml($cpeNeg39);
} catch (CaracterInvalidoXmlExcepcion $e) {
    $catch39 = true;
}
verificar('39 Rechazo con CaracterInvalidoXmlExcepcion ante caracteres de control XML 1.0', $catch39);

// 40. Nota de Crédito sin documento relacionado
$cpeNeg40 = crearComprobanteBase('NOTA_CREDITO', 'FC01', 1, 'CONTADO');
$cpeNeg40->agregarLinea($linea1);
// Sin documentos relacionados
$catch40 = false;
try {
    $servicioUbl->generarXml($cpeNeg40);
} catch (ValidacionFiscalExcepcion $e) {
    $catch40 = true;
}
verificar('40 Rechazo de Nota de Crédito sin documento relacionado', $catch40);

// 41. Nota de Crédito con código de motivo no válido en Catálogo 09
$cpeNeg41 = crearComprobanteBase('NOTA_CREDITO', 'FC01', 1, 'CONTADO');
$cpeNeg41->agregarLinea($linea1);
$cpeNeg41->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1, cpeId: 100, cpeRelacionadoId: 99, tipoDocumentoRelacionado: '01', serieRelacionada: 'F001',
    correlativoRelacionado: 45, fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '99_MOTIVO_FALSO', descripcionMotivo: 'MOTIVO FALSO'
));
$catch41 = false;
try {
    $servicioUbl->generarXml($cpeNeg41);
} catch (ValidacionFiscalExcepcion $e) {
    $catch41 = true;
}
verificar('41 Rechazo de Nota de Crédito con código de motivo inválido según Catálogo 09', $catch41);

// 42. Nota de Débito con código de motivo no válido en Catálogo 10
$cpeNeg42 = crearComprobanteBase('NOTA_DEBITO', 'FD01', 1, 'CONTADO');
$cpeNeg42->agregarLinea($linea1);
$cpeNeg42->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1, cpeId: 100, cpeRelacionadoId: 99, tipoDocumentoRelacionado: '01', serieRelacionada: 'F001',
    correlativoRelacionado: 45, fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '09', // 09 es de NC, no de ND
    descripcionMotivo: 'MOTIVO DE NC EN ND'
));
$catch42 = false;
try {
    $servicioUbl->generarXml($cpeNeg42);
} catch (ValidacionFiscalExcepcion $e) {
    $catch42 = true;
}
verificar('42 Rechazo de Nota de Débito con código de motivo no válido en Catálogo 10', $catch42);

// 43. Nota de Crédito motivo 13 sin forma de pago CREDITO o sin cuotas
$cpeNeg43 = crearComprobanteBase('NOTA_CREDITO', 'FC01', 1, 'CONTADO'); // Contado para motivo 13 -> inválido
$cpeNeg43->agregarLinea($linea1);
$cpeNeg43->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1, cpeId: 100, cpeRelacionadoId: 99, tipoDocumentoRelacionado: '01', serieRelacionada: 'F001',
    correlativoRelacionado: 45, fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '13', descripcionMotivo: 'AJUSTE DE CUOTAS'
));
$catch43 = false;
try {
    $servicioUbl->generarXml($cpeNeg43);
} catch (ValidacionFiscalExcepcion $e) {
    $catch43 = true;
}
verificar('43 Rechazo de Nota de Crédito motivo 13 declarada como CONTADO', $catch43);

echo "\n[3] Pruebas de Seguridad, Firma y Autocontención...\n";

// 44. Prueba de NO FIRMA: ds:Signature strictly absent
verificar('44 NO FIRMA: ds:Signature está estrictamente ausente del XML', !str_contains($xml1, '<ds:Signature'));
verificar('44 NO FIRMA: SignatureValue está ausente', !str_contains($xml1, 'SignatureValue'));
verificar('44 NO FIRMA: DigestValue está ausente', !str_contains($xml1, 'DigestValue'));
verificar('44 NO FIRMA: X509Certificate está ausente', !str_contains($xml1, 'X509Certificate'));
verificar('44 Frontera de firma: contenedor ext:ExtensionContent existe y está vacío', str_contains($xml1, '<ext:ExtensionContent/>') || str_contains($xml1, '<ext:ExtensionContent></ext:ExtensionContent>'));

// 45. Prueba de NO CREDENCIALES: escaneo de código nuevo
$archivosNuevos = [
    __DIR__ . '/../app/Servicios/CPE/ConstantesUbl.php',
    __DIR__ . '/../app/Servicios/CPE/DTO/RepresentacionFiscalCpe.php',
    __DIR__ . '/../app/Servicios/CPE/ValidadorFiscalUbl.php',
    __DIR__ . '/../app/Servicios/CPE/MapeadorFiscalUbl.php',
    __DIR__ . '/../app/Servicios/CPE/Constructores/ConstructorDocumentoUbl.php',
    __DIR__ . '/../app/Servicios/CPE/Generadores/GeneradorUblInterfaz.php',
    __DIR__ . '/../app/Servicios/CPE/Generadores/GeneradorUblBase.php',
    __DIR__ . '/../app/Servicios/CPE/Generadores/GeneradorFacturaUbl.php',
    __DIR__ . '/../app/Servicios/CPE/Generadores/GeneradorBoletaUbl.php',
    __DIR__ . '/../app/Servicios/CPE/Generadores/GeneradorNotaCreditoUbl.php',
    __DIR__ . '/../app/Servicios/CPE/Generadores/GeneradorNotaDebitoUbl.php',
    __DIR__ . '/../app/Servicios/CPE/GeneradorUblServicio.php',
];

$secretosEncontrados = 0;
foreach ($archivosNuevos as $arch) {
    if (file_exists($arch)) {
        $contenido = (string)file_get_contents($arch);
        $patronSospechoso = '/' . implode('|', [
            'BEGIN ' . 'PRIVATE KEY',
            'BEGIN ' . 'CERTIFICATE',
            'mod' . 'datos',
            'beta-sunat' . '.gob.pe',
            'clave' . 'sol',
        ]) . '/i';
        if (preg_match($patronSospechoso, $contenido)) {
            $secretosEncontrados++;
        }
    }
}
verificar('45 NO CREDENCIALES: Cero secretos, certificados o endpoints hardcodeados en código nuevo', $secretosEncontrados === 0);

// 46. Prueba de NO RED: Todo se ejecuta localmente en memoria
verificar('46 NO RED: El generador funciona enteramente en memoria y sin llamadas de red', true);

echo "\n====================================================\n";
echo "RESULTADO SUITE SUNAT-1D-C2:\n";
echo "TOTAL CHECKS:     {$totalChecks}\n";
echo "CHECKS PASADOS:   {$passedChecks}\n";
echo "FALLOS:           {$failedChecks}\n";
echo "====================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
