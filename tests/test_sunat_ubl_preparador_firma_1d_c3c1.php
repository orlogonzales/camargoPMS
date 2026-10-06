<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1D-C3C1 — Preparador de Firma UBL (cac:Signature) y Frontera XML Signable
 *
 * Cobertura de Pruebas:
 * 1.  Factura 01: Preparación estructural positiva de cac:Signature con cbc:URI='#SignatureKG'.
 * 2.  Factura 01: cac:Signature posicionado estrictamente antes de cac:AccountingSupplierParty.
 * 3.  Factura 01: Identificador descriptivo cbc:ID y RUC/Nombre del firmante correctos.
 * 4.  Boleta 03: Preparación positiva y preservación de receptor DNI y metadatos.
 * 5.  Nota de Crédito 07: Preparación positiva preservando DiscrepancyResponse y BillingReference.
 * 6.  Nota de Débito 08: Preparación positiva preservando RequestedMonetaryTotal.
 * 7.  Frontera de firma: ext:UBLExtensions y ext:ExtensionContent preservados sin duplicación.
 * 8.  Ausencia criptográfica estricta: NO ds:Signature, NO DigestValue, NO SignatureValue, NO X509Certificate.
 * 9.  Idempotencia (Fail-Fast): Re-preparación de XML ya preparado lanza DocumentoYaPreparadoExcepcion.
 * 10. Idempotencia en DOM: Re-preparación sobre DOM existente lanza DocumentoYaPreparadoExcepcion.
 * 11. Coherencia de identidad (Fail-Closed): RUC firmante != RUC emisor lanza RucEmisorInconsistenteExcepcion.
 * 12. XML malformado: Sintaxis corrupta lanza XmlMalformadoCpeExcepcion.
 * 13. XML incompleto: Ausencia de AccountingSupplierParty lanza XmlMalformadoCpeExcepcion.
 * 14. Seguridad XXE: Declaración <!DOCTYPE lanza XmlMalformadoCpeExcepcion (rechazo inmediato).
 * 15. Seguridad XXE: Declaración <!ENTITY lanza XmlMalformadoCpeExcepcion (rechazo inmediato).
 * 16. Tipo no soportado: Elemento raíz distinto a Invoice/CreditNote/DebitNote lanza TipoDocumentoNoSoportadoExcepcion.
 * 17. Preservación tributaria total: Líneas, importes, IGV y totales monetarios 100% idénticos.
 * 18. Preservación de crédito y cuotas: cac:PaymentTerms y montos idénticos antes y después.
 * 19. Preservación de Catálogo 55 (DL 919): Propiedades 4000-4009 de hospedaje idénticas tras preparación.
 * 20. Determinismo absoluto: Mismo snapshot y contexto generan idéntico hash SHA-256 byte a byte.
 * 21. ContextoFirmaCpe: Validación de RUC 11 dígitos, razón social no vacía y NCNames válidos.
 * 22. Ausencia de secretos: Cero llaves privadas, certificados reales/sintéticos o contraseñas en código.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CamargoPMS\Excepciones\DocumentoYaPreparadoExcepcion;
use CamargoPMS\Excepciones\RucEmisorInconsistenteExcepcion;
use CamargoPMS\Excepciones\TipoDocumentoNoSoportadoExcepcion;
use CamargoPMS\Excepciones\XmlMalformadoCpeExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeCuota;
use CamargoPMS\Modelos\CPE\CpeDocumentoRelacionado;
use CamargoPMS\Modelos\CPE\CpeHospedajeFiscal;
use CamargoPMS\Modelos\CPE\CpeLinea;
use CamargoPMS\Servicios\CPE\ConstantesUbl;
use CamargoPMS\Servicios\CPE\DTO\ContextoFirmaCpe;
use CamargoPMS\Servicios\CPE\Firma\PreparadorFirmaUbl;
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
echo "EJECUTANDO SUITE SUNAT-1D-C3C1 — PREPARADOR DE FIRMA UBL (cac:Signature)\n";
echo "====================================================================\n\n";

$generadorUbl = new GeneradorUblServicio();
$preparador = new PreparadorFirmaUbl();

$contextoValido = new ContextoFirmaCpe(
    rucFirmante: '20609998881',
    razonSocialFirmante: 'CAMARGO HOSTELERIA S.A.C.',
    identificadorFirma: 'SignatureKG',
    identificadorDescriptivo: 'IDSignKG'
);

// Helper para crear agregados de prueba base
function crearComprobanteFixture(
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

function crearLineaEstandar(int $id = 1, int $orden = 1): CpeLinea
{
    return new CpeLinea(
        id: $id,
        cpeId: 100,
        numeroOrden: $orden,
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
}

// -----------------------------------------------------------------------------
// BLOQUE 1: PRUEBAS POSITIVAS DE PREPARACIÓN DE FIRMA (01, 03, 07, 08)
// -----------------------------------------------------------------------------
echo "[1] Pruebas Positivas de Preparación de Firma UBL 2.1...\n";

// 1. Factura Contado (01)
$cpeFactura = crearComprobanteFixture('FACTURA', 'F001', 101, 'CONTADO');
$cpeFactura->agregarLinea(crearLineaEstandar());
$xmlUnsigned01 = $generadorUbl->generarXml($cpeFactura);

$xmlSignable01 = $preparador->preparar($xmlUnsigned01, $contextoValido);

verificar('01 Factura: XML signable generado no está vacío', strlen($xmlSignable01) > strlen($xmlUnsigned01));
verificar('01 Factura: Contiene bloque cac:Signature', str_contains($xmlSignable01, '<cac:Signature>'));
verificar('01 Factura: cac:Signature contiene cbc:ID IDSignKG', str_contains($xmlSignable01, '<cbc:ID>IDSignKG</cbc:ID>'));
verificar('01 Factura: Contiene RUC firmante en SignatoryParty', str_contains($xmlSignable01, '<cbc:ID>20609998881</cbc:ID>'));
verificar('01 Factura: Contiene razón social del firmante', str_contains($xmlSignable01, 'CAMARGO HOSTELERIA S.A.C.'));
verificar('01 Factura: cbc:URI apunta a #SignatureKG', str_contains($xmlSignable01, '<cbc:URI>#SignatureKG</cbc:URI>'));

// Posición UBL en Factura 01: cac:Signature inmediatamente antes de cac:AccountingSupplierParty
$dom01 = new DOMDocument();
$dom01->loadXML($xmlSignable01);
$sigNode01 = $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->item(0);
$supplierNode01 = $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0);

// Verificar orden de hermanos en el árbol DOM
$siguiente = $sigNode01->nextSibling;
while ($siguiente !== null && $siguiente->nodeType === XML_TEXT_NODE && trim($siguiente->textContent) === '') {
    $siguiente = $siguiente->nextSibling;
}
verificar('01 Factura: cac:Signature precede inmediatamente a cac:AccountingSupplierParty', $siguiente === $supplierNode01);
verificar('01 Factura: Contiene ext:UBLExtensions', $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_EXT, 'UBLExtensions')->length === 1);
verificar('01 Factura: Contiene ext:ExtensionContent', $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_EXT, 'ExtensionContent')->length === 1);

// Ausencia de criptografía
verificar('01 Factura: NO contiene ds:Signature', $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'Signature')->length === 0);
verificar('01 Factura: NO contiene ds:DigestValue', $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'DigestValue')->length === 0);
verificar('01 Factura: NO contiene ds:SignatureValue', $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'SignatureValue')->length === 0);
verificar('01 Factura: NO contiene ds:X509Certificate', $dom01->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'X509Certificate')->length === 0);

// 2. Boleta de Venta (03)
$cpeBoleta = crearComprobanteFixture('BOLETA', 'B001', 202, 'CONTADO', null, 'PEN', '1', '44556677', 'ANA MARTINEZ LOPEZ');
$cpeBoleta->agregarLinea(crearLineaEstandar());
$xmlUnsigned03 = $generadorUbl->generarXml($cpeBoleta);

$xmlSignable03 = $preparador->preparar($xmlUnsigned03, $contextoValido);
$dom03 = new DOMDocument();
$dom03->loadXML($xmlSignable03);
$sigNode03 = $dom03->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->item(0);
$supplierNode03 = $dom03->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0);

$sigNext03 = $sigNode03->nextSibling;
while ($sigNext03 !== null && $sigNext03->nodeType === XML_TEXT_NODE && trim($sigNext03->textContent) === '') {
    $sigNext03 = $sigNext03->nextSibling;
}

verificar('03 Boleta: XML signable contiene exactamente 1 cac:Signature', $dom03->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->length === 1);
verificar('03 Boleta: cac:Signature precede a cac:AccountingSupplierParty', $sigNext03 === $supplierNode03);
verificar('03 Boleta: Preserva DNI de receptor 44556677', str_contains($xmlSignable03, '44556677'));
verificar('03 Boleta: NO contiene firma criptográfica', $dom03->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'Signature')->length === 0);

// 3. Nota de Crédito (07)
$cpeNotaCredito = crearComprobanteFixture('NOTA_CREDITO', 'FC01', 50, 'CONTADO');
$cpeNotaCredito->agregarLinea(crearLineaEstandar());
$cpeNotaCredito->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 101,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'ANULACION TOTAL DE LA OPERACION'
));
$xmlUnsigned07 = $generadorUbl->generarXml($cpeNotaCredito);

$xmlSignable07 = $preparador->preparar($xmlUnsigned07, $contextoValido);
$dom07 = new DOMDocument();
$dom07->loadXML($xmlSignable07);
$sigNode07 = $dom07->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->item(0);
$supplierNode07 = $dom07->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0);

$sigNext07 = $sigNode07->nextSibling;
while ($sigNext07 !== null && $sigNext07->nodeType === XML_TEXT_NODE && trim($sigNext07->textContent) === '') {
    $sigNext07 = $sigNext07->nextSibling;
}

verificar('07 Nota de Crédito: XML signable contiene exactamente 1 cac:Signature', $dom07->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->length === 1);
verificar('07 Nota de Crédito: cac:Signature precede a cac:AccountingSupplierParty', $sigNext07 === $supplierNode07);
verificar('07 Nota de Crédito: Preserva DiscrepancyResponse motivo 01', str_contains($xmlSignable07, '<cbc:ResponseCode') && str_contains($xmlSignable07, '>01<'));
verificar('07 Nota de Crédito: Preserva BillingReference F001-00000101', str_contains($xmlSignable07, 'F001-00000101'));
verificar('07 Nota de Crédito: NO contiene firma criptográfica', $dom07->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'Signature')->length === 0);

// 4. Nota de Débito (08)
$cpeNotaDebito = crearComprobanteFixture('NOTA_DEBITO', 'FD01', 30, 'CONTADO');
$cpeNotaDebito->agregarLinea(crearLineaEstandar());
$cpeNotaDebito->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 101,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'PENALIDAD POR MORA'
));
$xmlUnsigned08 = $generadorUbl->generarXml($cpeNotaDebito);

$xmlSignable08 = $preparador->preparar($xmlUnsigned08, $contextoValido);
$dom08 = new DOMDocument();
$dom08->loadXML($xmlSignable08);
$sigNode08 = $dom08->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->item(0);
$supplierNode08 = $dom08->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0);

$sigNext08 = $sigNode08->nextSibling;
while ($sigNext08 !== null && $sigNext08->nodeType === XML_TEXT_NODE && trim($sigNext08->textContent) === '') {
    $sigNext08 = $sigNext08->nextSibling;
}

verificar('08 Nota de Débito: XML signable contiene exactamente 1 cac:Signature', $dom08->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->length === 1);
verificar('08 Nota de Débito: cac:Signature precede a cac:AccountingSupplierParty', $sigNext08 === $supplierNode08);
verificar('08 Nota de Débito: Preserva RequestedMonetaryTotal', str_contains($xmlSignable08, '<cac:RequestedMonetaryTotal>'));
verificar('08 Nota de Débito: NO contiene firma criptográfica', $dom08->getElementsByTagNameNS(ConstantesUbl::XMLNS_DS, 'Signature')->length === 0);

// -----------------------------------------------------------------------------
// BLOQUE 2: TEST DE IDEMPOTENCIA DEFENSIVA (FAIL-FAST)
// -----------------------------------------------------------------------------
echo "\n[2] Pruebas de Idempotencia y Fail-Fast...\n";

// Ejecutar preparación sobre un XML que ya contiene cac:Signature
$lanzoIdempotenciaString = false;
try {
    $preparador->preparar($xmlSignable01, $contextoValido);
} catch (DocumentoYaPreparadoExcepcion $e) {
    $lanzoIdempotenciaString = true;
    verificar('Idempotencia string: Lanza DocumentoYaPreparadoExcepcion ante XML ya preparado', true);
    verificar('Idempotencia string: Código de excepción es 409', $e->getCode() === 409);
}
if (!$lanzoIdempotenciaString) {
    verificar('Idempotencia string: Lanza DocumentoYaPreparadoExcepcion ante XML ya preparado', false);
}

// Ejecutar preparación sobre un DOM que ya contiene cac:Signature
$lanzoIdempotenciaDom = false;
try {
    $preparador->prepararDom($dom01, $contextoValido);
} catch (DocumentoYaPreparadoExcepcion $e) {
    $lanzoIdempotenciaDom = true;
    verificar('Idempotencia DOM: Lanza DocumentoYaPreparadoExcepcion ante DOM ya preparado', true);
}
if (!$lanzoIdempotenciaDom) {
    verificar('Idempotencia DOM: Lanza DocumentoYaPreparadoExcepcion ante DOM ya preparado', false);
}

// -----------------------------------------------------------------------------
// BLOQUE 3: TEST DE COHERENCIA DE IDENTIDAD FISCAL (RUC MISMATCH)
// -----------------------------------------------------------------------------
echo "\n[3] Pruebas de Coherencia de Identidad Fiscal...\n";

$contextoRucInvalido = new ContextoFirmaCpe(
    rucFirmante: '20999999999', // RUC diferente al del comprobante (20609998881)
    razonSocialFirmante: 'OTRA EMPRESA S.A.C.',
    identificadorFirma: 'SignatureKG'
);

$lanzoRucInconsistente = false;
try {
    $preparador->preparar($xmlUnsigned01, $contextoRucInvalido);
} catch (RucEmisorInconsistenteExcepcion $e) {
    $lanzoRucInconsistente = true;
    verificar('Coherencia RUC: Lanza RucEmisorInconsistenteExcepcion ante RUC no coincidente', true);
    verificar('Coherencia RUC: Código de excepción es 422', $e->getCode() === 422);
    verificar('Coherencia RUC: Mensaje detalla ambos RUCs', str_contains($e->getMessage(), '20999999999') && str_contains($e->getMessage(), '20609998881'));
}
if (!$lanzoRucInconsistente) {
    verificar('Coherencia RUC: Lanza RucEmisorInconsistenteExcepcion ante RUC no coincidente', false);
}

// -----------------------------------------------------------------------------
// BLOQUE 4: TEST DE XML MALFORMADO Y ESTRUCTURA INCOMPLETA
// -----------------------------------------------------------------------------
echo "\n[4] Pruebas de Robustez ante XML Malformado...\n";

// XML sintácticamente inválido
$lanzoXmlSintaxis = false;
try {
    $preparador->preparar('<Invoice><cac:Party>nodo no cerrado', $contextoValido);
} catch (XmlMalformadoCpeExcepcion $e) {
    $lanzoXmlSintaxis = true;
    verificar('XML Malformado: Lanza XmlMalformadoCpeExcepcion ante error sintáctico', true);
    verificar('XML Malformado: Código de excepción es 400', $e->getCode() === 400);
}
if (!$lanzoXmlSintaxis) {
    verificar('XML Malformado: Lanza XmlMalformadoCpeExcepcion ante error sintáctico', false);
}

// XML sin cac:AccountingSupplierParty
$xmlSinSupplier = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>F001-00000001</cbc:ID>
</Invoice>
XML;

$lanzoSinSupplier = false;
try {
    $preparador->preparar($xmlSinSupplier, $contextoValido);
} catch (XmlMalformadoCpeExcepcion $e) {
    $lanzoSinSupplier = true;
    verificar('XML Incompleto: Lanza XmlMalformadoCpeExcepcion ante ausencia de AccountingSupplierParty', true);
}
if (!$lanzoSinSupplier) {
    verificar('XML Incompleto: Lanza XmlMalformadoCpeExcepcion ante ausencia de AccountingSupplierParty', false);
}

// -----------------------------------------------------------------------------
// BLOQUE 5: SEGURIDAD XML Y DEFENSAS CONTRA XXE
// -----------------------------------------------------------------------------
echo "\n[5] Pruebas de Seguridad XML y Defensas XXE...\n";

// DOCTYPE Malicioso
$xmlDoctype = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE Invoice SYSTEM "http://malicioso.test/cpe.dtd">
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID>F001-00000001</cbc:ID>
</Invoice>
XML;

$lanzoDoctype = false;
try {
    $preparador->preparar($xmlDoctype, $contextoValido);
} catch (XmlMalformadoCpeExcepcion $e) {
    $lanzoDoctype = true;
    verificar('Seguridad XXE: Rechazo categórico de <!DOCTYPE', true);
    verificar('Seguridad XXE: Mensaje indica riesgo XXE', str_contains($e->getMessage(), 'XXE'));
}
if (!$lanzoDoctype) {
    verificar('Seguridad XXE: Rechazo categórico de <!DOCTYPE', false);
}

// ENTITY Malicioso
$xmlEntity = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE test [ <!ENTITY xxe SYSTEM "file:///c:/windows/win.ini"> ]>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID>&xxe;</cbc:ID>
</Invoice>
XML;

$lanzoEntity = false;
try {
    $preparador->preparar($xmlEntity, $contextoValido);
} catch (XmlMalformadoCpeExcepcion $e) {
    $lanzoEntity = true;
    verificar('Seguridad XXE: Rechazo categórico de <!ENTITY', true);
}
if (!$lanzoEntity) {
    verificar('Seguridad XXE: Rechazo categórico de <!ENTITY', false);
}

// -----------------------------------------------------------------------------
// BLOQUE 6: TEST DE DOCUMENTO RAÍZ NO SOPORTADO
// -----------------------------------------------------------------------------
echo "\n[6] Pruebas de Documentos Raíz No Soportados...\n";

$xmlOrder = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"
       xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
       xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>ORD-001</cbc:ID>
    <cac:AccountingSupplierParty>
        <cac:Party><cac:PartyIdentification><cbc:ID>20609998881</cbc:ID></cac:PartyIdentification></cac:Party>
    </cac:AccountingSupplierParty>
</Order>
XML;

$lanzoNoSoportado = false;
try {
    $preparador->preparar($xmlOrder, $contextoValido);
} catch (TipoDocumentoNoSoportadoExcepcion $e) {
    $lanzoNoSoportado = true;
    verificar('Tipo no soportado: Lanza TipoDocumentoNoSoportadoExcepcion ante raíz <Order>', true);
}
if (!$lanzoNoSoportado) {
    verificar('Tipo no soportado: Lanza TipoDocumentoNoSoportadoExcepcion ante raíz <Order>', false);
}

// -----------------------------------------------------------------------------
// BLOQUE 7: PRESERVACIÓN RIGUROSA DE CONTENIDO FISCAL SOBERANO
// -----------------------------------------------------------------------------
echo "\n[7] Pruebas de Preservación de Contenido Fiscal Soberano...\n";

/**
 * Valida formalmente que al retirar exclusivamente la mutación autorizada de C3C1
 * (el elemento cac:Signature), el documento signable resultante es idéntico
 * canónicamente (C14N) byte a byte al documento unsigned original generado por C2.
 */
function verificarPreservacionCanonica(
    string $nombreCpe,
    string $xmlUnsigned,
    string $xmlSignable
): bool {
    $domUnsigned = new DOMDocument('1.0', 'UTF-8');
    $domUnsigned->preserveWhiteSpace = false;
    $domUnsigned->formatOutput = true;
    $domUnsigned->loadXML($xmlUnsigned);

    $domSignable = new DOMDocument('1.0', 'UTF-8');
    $domSignable->preserveWhiteSpace = false;
    $domSignable->formatOutput = true;
    $domSignable->loadXML($xmlSignable);

    $sigNodes = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature');
    if ($sigNodes->length !== 1) {
        return false;
    }
    $sigNode = $sigNodes->item(0);
    $sigNode->parentNode->removeChild($sigNode);

    return $domUnsigned->C14N() === $domSignable->C14N();
}

/**
 * Audita granularmente la invariabilidad de cada nodo tributario de negocio
 * entre el XML unsigned y el signable para certificar cero mutaciones no autorizadas.
 *
 * @return string[] Lista de anomalías detectadas (vacía si es 100% fiel).
 */
function auditarInvarianzaNodosFiscales(
    string $tipoCpe,
    string $xmlUnsigned,
    string $xmlSignable
): array {
    $domUnsigned = new DOMDocument('1.0', 'UTF-8');
    $domUnsigned->preserveWhiteSpace = false;
    $domUnsigned->formatOutput = true;
    $domUnsigned->loadXML($xmlUnsigned);

    $domSignable = new DOMDocument('1.0', 'UTF-8');
    $domSignable->preserveWhiteSpace = false;
    $domSignable->formatOutput = true;
    $domSignable->loadXML($xmlSignable);

    $anomalias = [];

    // 1. cbc:ID del comprobante
    $idUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'ID')->item(0)?->textContent;
    $idSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'ID')->item(0)?->textContent;
    if ($idUnsigned !== $idSignable) {
        $anomalias[] = "cbc:ID modificado: '{$idUnsigned}' != '{$idSignable}'";
    }

    // 2. cbc:IssueDate
    $dateUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueDate')->item(0)?->textContent;
    $dateSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueDate')->item(0)?->textContent;
    if ($dateUnsigned !== $dateSignable) {
        $anomalias[] = 'cbc:IssueDate modificado';
    }

    // 3. cbc:IssueTime
    $timeUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueTime')->item(0)?->textContent;
    $timeSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueTime')->item(0)?->textContent;
    if ($timeUnsigned !== $timeSignable) {
        $anomalias[] = 'cbc:IssueTime modificado';
    }

    // 4. cbc:DocumentCurrencyCode
    $currUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'DocumentCurrencyCode')->item(0)?->textContent;
    $currSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'DocumentCurrencyCode')->item(0)?->textContent;
    if ($currUnsigned !== $currSignable) {
        $anomalias[] = 'cbc:DocumentCurrencyCode modificado';
    }

    // 5. cac:AccountingSupplierParty (C14N)
    $suppUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0)?->C14N();
    $suppSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0)?->C14N();
    if ($suppUnsigned !== $suppSignable) {
        $anomalias[] = 'cac:AccountingSupplierParty modificado';
    }

    // 6. cac:AccountingCustomerParty (C14N)
    $custUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingCustomerParty')->item(0)?->C14N();
    $custSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingCustomerParty')->item(0)?->C14N();
    if ($custUnsigned !== $custSignable) {
        $anomalias[] = 'cac:AccountingCustomerParty modificado';
    }

    // 7. cac:TaxTotal (C14N)
    $taxUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'TaxTotal')->item(0)?->C14N();
    $taxSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'TaxTotal')->item(0)?->C14N();
    if ($taxUnsigned !== $taxSignable) {
        $anomalias[] = 'cac:TaxTotal modificado';
    }

    // 8. Totales monetarios (LegalMonetaryTotal o RequestedMonetaryTotal)
    $monetaryTag = ($tipoCpe === '08') ? 'RequestedMonetaryTotal' : 'LegalMonetaryTotal';
    $monUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $monetaryTag)->item(0)?->C14N();
    $monSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $monetaryTag)->item(0)?->C14N();
    if ($monUnsigned !== $monSignable) {
        $anomalias[] = "cac:{$monetaryTag} modificado";
    }

    // 9. cac:PaymentTerms (cuotas y forma de pago)
    $ptUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PaymentTerms');
    $ptSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PaymentTerms');
    if ($ptUnsigned->length !== $ptSignable->length) {
        $anomalias[] = "cac:PaymentTerms cantidad modificada ({$ptUnsigned->length} != {$ptSignable->length})";
    } else {
        for ($i = 0; $i < $ptUnsigned->length; $i++) {
            if ($ptUnsigned->item($i)->C14N() !== $ptSignable->item($i)->C14N()) {
                $anomalias[] = "cac:PaymentTerms índice {$i} modificado";
            }
        }
    }

    // 10. Líneas del comprobante (InvoiceLine / CreditNoteLine / DebitNoteLine)
    $lineTag = match ($tipoCpe) {
        '07' => 'CreditNoteLine',
        '08' => 'DebitNoteLine',
        default => 'InvoiceLine'
    };
    $linesUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $lineTag);
    $linesSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $lineTag);
    if ($linesUnsigned->length !== $linesSignable->length) {
        $anomalias[] = "{$lineTag} cantidad modificada ({$linesUnsigned->length} != {$linesSignable->length})";
    } else {
        for ($i = 0; $i < $linesUnsigned->length; $i++) {
            if ($linesUnsigned->item($i)->C14N() !== $linesSignable->item($i)->C14N()) {
                $anomalias[] = "{$lineTag} índice {$i} modificado";
            }
        }
    }

    // 11. DiscrepancyResponse y BillingReference para NC y ND
    if ($tipoCpe === '07' || $tipoCpe === '08') {
        $discUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'DiscrepancyResponse')->item(0)?->C14N();
        $discSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'DiscrepancyResponse')->item(0)?->C14N();
        if ($discUnsigned !== $discSignable) {
            $anomalias[] = 'cac:DiscrepancyResponse modificado';
        }

        $billUnsigned = $domUnsigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'BillingReference');
        $billSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'BillingReference');
        if ($billUnsigned->length !== $billSignable->length) {
            $anomalias[] = 'cac:BillingReference cantidad modificada';
        }
    }

    return $anomalias;
}

// 7.1 Preservación Canónica en Factura 01
$pres01Canonica = verificarPreservacionCanonica('01_FACTURA', $xmlUnsigned01, $xmlSignable01);
verificar('Preservación 01: C14N(signable - cac:Signature) === C14N(unsigned)', $pres01Canonica);
$anomalias01 = auditarInvarianzaNodosFiscales('01', $xmlUnsigned01, $xmlSignable01);
verificar('Preservación 01: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias01));

// 7.2 Preservación Canónica en Boleta 03
$pres03Canonica = verificarPreservacionCanonica('03_BOLETA', $xmlUnsigned03, $xmlSignable03);
verificar('Preservación 03: C14N(signable - cac:Signature) === C14N(unsigned)', $pres03Canonica);
$anomalias03 = auditarInvarianzaNodosFiscales('03', $xmlUnsigned03, $xmlSignable03);
verificar('Preservación 03: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias03));

// 7.3 Preservación Canónica en Nota de Crédito 07
$pres07Canonica = verificarPreservacionCanonica('07_NOTA_CREDITO', $xmlUnsigned07, $xmlSignable07);
verificar('Preservación 07: C14N(signable - cac:Signature) === C14N(unsigned)', $pres07Canonica);
$anomalias07 = auditarInvarianzaNodosFiscales('07', $xmlUnsigned07, $xmlSignable07);
verificar('Preservación 07: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias07));

// 7.4 Preservación Canónica en Nota de Débito 08
$pres08Canonica = verificarPreservacionCanonica('08_NOTA_DEBITO', $xmlUnsigned08, $xmlSignable08);
verificar('Preservación 08: C14N(signable - cac:Signature) === C14N(unsigned)', $pres08Canonica);
$anomalias08 = auditarInvarianzaNodosFiscales('08', $xmlUnsigned08, $xmlSignable08);
verificar('Preservación 08: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias08));

// 7.5 Factura compleja con Crédito, Cuotas, Hospedaje DL 919 (Catálogo 55) y varias líneas
$cpeComplejo = crearComprobanteFixture('FACTURA', 'F001', 303, 'CREDITO', '1500.00', 'USD', '6', '20444555666', 'AGENCIA DE VIAJES INTERNACIONAL S.A.');
$refCpeComp = new ReflectionClass($cpeComplejo);

$propTotExpComp = $refCpeComp->getProperty('totalOperacionesExportacion');
$propTotExpComp->setAccessible(true);
$propTotExpComp->setValue($cpeComplejo, '1500.00');

$propTotGravComp = $refCpeComp->getProperty('totalOperacionesGravadas');
$propTotGravComp->setAccessible(true);
$propTotGravComp->setValue($cpeComplejo, '0.00');

$propTotIgvComp = $refCpeComp->getProperty('totalIgv');
$propTotIgvComp->setAccessible(true);
$propTotIgvComp->setValue($cpeComplejo, '0.00');

$propTotVentaComp = $refCpeComp->getProperty('totalVenta');
$propTotVentaComp->setAccessible(true);
$propTotVentaComp->setValue($cpeComplejo, '1500.00');

$propEsHospComp = $refCpeComp->getProperty('esExportacionHospedaje');
$propEsHospComp->setAccessible(true);
$propEsHospComp->setValue($cpeComplejo, true);

// Agregar Hospedaje DL 919
$hospedajeFiscal = new CpeHospedajeFiscal(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    nombresApellidos: 'JEAN FRANCOIS DUPONT',
    tipoDocumento: '7',
    numeroDocumento: 'FR-99887766',
    paisEmisionPasaporte: 'FR',
    paisResidencia: 'FR',
    fechaIngresoPais: '2026-10-01',
    fechaCheckin: '2026-10-05',
    fechaCheckout: '2026-10-10',
    diasPermanencia: 9,
    tamVirtualNumero: 'TAM-12345678'
);
$cpeComplejo->agregarHospedaje($hospedajeFiscal);

// Línea Alojamiento
$lineaAloj = new CpeLinea(
    id: 1,
    cpeId: 100,
    numeroOrden: 1,
    codigoProductoInterno: 'HAB-SUITE',
    codigoProductoSunat: null,
    descripcion: 'SUITE MATRIMONIAL 5 NOCHES DL 919',
    unidadMedida: 'ZZ',
    cantidad: '5.0000',
    valorUnitario: '200.0000',
    precioUnitario: '200.0000',
    descuentoMonto: '0.00',
    baseImponible: '1000.00',
    tipoAfectacionIgv: '40',
    tasaIgv: '0.00',
    montoIgv: '0.00',
    totalLinea: '1000.00',
    cpeHospedajeId: 1
);
$cpeComplejo->agregarLinea($lineaAloj);

// Línea Consumo Restaurante
$lineaCons = new CpeLinea(
    id: 2,
    cpeId: 100,
    numeroOrden: 2,
    codigoProductoInterno: 'REST-01',
    codigoProductoSunat: null,
    descripcion: 'CONSUMO DE RESTAURANTE HUÉSPED DL 919',
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
    cpeHospedajeId: 1,
    fechaConsumo: '2026-10-06'
);
$cpeComplejo->agregarLinea($lineaCons);

// Cuotas de Crédito
$cpeComplejo->agregarCuota(new CpeCuota(id: 1, cpeId: 100, numeroCuota: 1, monto: '750.00', fechaVencimiento: '2026-10-25'));
$cpeComplejo->agregarCuota(new CpeCuota(id: 2, cpeId: 100, numeroCuota: 2, monto: '750.00', fechaVencimiento: '2026-11-25'));

$xmlUnsignedComp = $generadorUbl->generarXml($cpeComplejo);
$xmlSignableComp = $preparador->preparar($xmlUnsignedComp, $contextoValido);

// 7.6 Preservación Canónica en Documento Complejo (DL 919 Hospedaje, Catálogo 55, Cuotas)
$presCompCanonica = verificarPreservacionCanonica('05_COMPLEJO', $xmlUnsignedComp, $xmlSignableComp);
verificar('Preservación Compleja: C14N(signable - cac:Signature) === C14N(unsigned)', $presCompCanonica);
$anomaliasComp = auditarInvarianzaNodosFiscales('01', $xmlUnsignedComp, $xmlSignableComp);
verificar('Preservación Compleja: Cero mutaciones no autorizadas en nodos fiscales', empty($anomaliasComp));

$domUnsignedComp = new DOMDocument();
$domUnsignedComp->loadXML($xmlUnsignedComp);
$domSignableComp = new DOMDocument();
$domSignableComp->loadXML($xmlSignableComp);

$props55Unsigned = $domUnsignedComp->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AdditionalItemProperty');
$props55Signable = $domSignableComp->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AdditionalItemProperty');
verificar('Preservación Compleja: Total de 17 propiedades Catálogo 55 idéntico', $props55Unsigned->length === $props55Signable->length && $props55Signable->length === 17);

$cuotasUnsigned = $domUnsignedComp->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PaymentTerms');
$cuotasSignable = $domSignableComp->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PaymentTerms');
verificar('Preservación Compleja: Bloques cac:PaymentTerms idénticos (3)', $cuotasUnsigned->length === $cuotasSignable->length && $cuotasSignable->length === 3);

// -----------------------------------------------------------------------------
// BLOQUE 8: DETERMINISMO ABSOLUTO (BYTE-A-BYTE)
// -----------------------------------------------------------------------------
echo "\n[8] Pruebas de Determinismo Absoluto...\n";

$signableA = $preparador->preparar($xmlUnsignedComp, $contextoValido);
$signableB = $preparador->preparar($xmlUnsignedComp, $contextoValido);

$hashA = hash('sha256', $signableA);
$hashB = hash('sha256', $signableB);

verificar('Determinismo: Dos ejecuciones independientes generan idéntico hash SHA-256', $hashA === $hashB);
verificar('Determinismo: Ambas cadenas son idénticas byte a byte', $signableA === $signableB);

// -----------------------------------------------------------------------------
// BLOQUE 9: PRUEBAS DEL DTO ContextoFirmaCpe
// -----------------------------------------------------------------------------
echo "\n[9] Pruebas del DTO ContextoFirmaCpe...\n";

verificar('ContextoFirmaCpe: Retorna RUC correcto', $contextoValido->obtenerRucFirmante() === '20609998881');
verificar('ContextoFirmaCpe: Retorna razón social correcta', $contextoValido->obtenerRazonSocialFirmante() === 'CAMARGO HOSTELERIA S.A.C.');
verificar('ContextoFirmaCpe: Retorna ID descriptivo IDSignKG', $contextoValido->obtenerIdentificadorDescriptivo() === 'IDSignKG');
verificar('ContextoFirmaCpe: Retorna ID firma SignatureKG', $contextoValido->obtenerIdentificadorFirma() === 'SignatureKG');
verificar('ContextoFirmaCpe: Retorna URI firma con prefijo # (#SignatureKG)', $contextoValido->obtenerUriFirma() === '#SignatureKG');

// Validación de RUC inválido
$lanzoRucDto = false;
try {
    new ContextoFirmaCpe('12345', 'RAZON SOCIAL');
} catch (InvalidArgumentException $e) {
    $lanzoRucDto = true;
    verificar('ContextoFirmaCpe: Rechaza RUC con longitud diferente a 11 dígitos', true);
}
if (!$lanzoRucDto) {
    verificar('ContextoFirmaCpe: Rechaza RUC con longitud diferente a 11 dígitos', false);
}

// Validación de razón social vacía
$lanzoRazonDto = false;
try {
    new ContextoFirmaCpe('20609998881', '   ');
} catch (InvalidArgumentException $e) {
    $lanzoRazonDto = true;
    verificar('ContextoFirmaCpe: Rechaza razón social vacía', true);
}
if (!$lanzoRazonDto) {
    verificar('ContextoFirmaCpe: Rechaza razón social vacía', false);
}

// Validación de identificador XML no válido
$lanzoNcNameDto = false;
try {
    new ContextoFirmaCpe('20609998881', 'EMPRESA S.A.C.', '123-INVALID-START');
} catch (InvalidArgumentException $e) {
    $lanzoNcNameDto = true;
    verificar('ContextoFirmaCpe: Rechaza identificador XML no conforme a NCName', true);
}
if (!$lanzoNcNameDto) {
    verificar('ContextoFirmaCpe: Rechaza identificador XML no conforme a NCName', false);
}

// -----------------------------------------------------------------------------
// BLOQUE 10: AUDITORÍA DE SEGURIDAD Y AUSENCIA DE SECRETOS
// -----------------------------------------------------------------------------
echo "\n[10] Auditoría de Seguridad y Cero Secretos...\n";

$archivoPreparador = file_get_contents(__DIR__ . '/../app/Servicios/CPE/Firma/PreparadorFirmaUbl.php');
$archivoDto = file_get_contents(__DIR__ . '/../app/Servicios/CPE/DTO/ContextoFirmaCpe.php');

verificar('Seguridad: Ausencia de openssl_sign en PreparadorFirmaUbl', !str_contains($archivoPreparador, 'openssl_sign'));
verificar('Seguridad: Ausencia de XMLSecurityDSig en PreparadorFirmaUbl', !str_contains($archivoPreparador, 'XMLSecurityDSig'));
verificar('Seguridad: Ausencia de clave privada en PreparadorFirmaUbl', !str_contains($archivoPreparador, 'PRIVATE KEY'));
verificar('Seguridad: Ausencia de clave privada en ContextoFirmaCpe', !str_contains($archivoDto, 'PRIVATE KEY'));
verificar('Seguridad: Ausencia de Clave SOL en código nuevo', !str_contains($archivoPreparador, 'Clave SOL') && !str_contains($archivoDto, 'Clave SOL'));

echo "\n====================================================\n";
echo "RESULTADO SUITE SUNAT-1D-C3C1:\n";
echo "TOTAL CHECKS:     {$totalChecks}\n";
echo "CHECKS PASADOS:   {$passedChecks}\n";
echo "FALLOS:           {$failedChecks}\n";
echo "====================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
