<?php

declare(strict_types=1);

/**
 * Suite de Pruebas: SUNAT-1D-C3C2 — Firmador Digital XMLDSig de Comprobantes de Pago Electrónicos (UBL 2.1)
 *
 * Cobertura de Pruebas:
 * 1.  Factura 01: Firma digital positiva W3C Enveloped con RSA-SHA256, SHA-256 y Exclusive C14N.
 * 2.  Factura 01: ds:Signature/@Id='SignatureKG' coincide con cac:Signature cbc:URI='#SignatureKG'.
 * 3.  Factura 01: Reference URI="" referencia el documento completo.
 * 4.  Factura 01: ds:KeyInfo contiene certificado X.509 público en Base64.
 * 5.  Factura 01: Post-verificación matemática verifyDocument() PASS.
 * 6.  Factura 01: Preservación e invarianza tributaria C14N(signed - ds:Signature) === C14N(signable).
 * 7.  Boleta 03: Firma positiva, verificación matemática y preservación de receptor DNI.
 * 8.  Nota de Crédito 07: Firma positiva, verificación matemática y preservación de DiscrepancyResponse/BillingReference.
 * 9.  Nota de Débito 08: Firma positiva, verificación matemática y preservación de RequestedMonetaryTotal.
 * 10. Fixture Complejo: Firma de documento con crédito (cuotas), multilínea y 17 propiedades Catálogo 55 (DL 919).
 * 11. Fixture Complejo: Invarianza absoluta de todas las propiedades Catálogo 55 y cuotas tras la firma.
 * 12. Anti-Tampering: Modificación de total monetario produce VERIFY = FAIL.
 * 13. Anti-Tampering: Modificación de RUC emisor produce VERIFY = FAIL.
 * 14. Anti-Tampering: Modificación de línea de ítem produce VERIFY = FAIL.
 * 15. Anti-Tampering: Modificación de fecha de emisión (IssueDate) produce VERIFY = FAIL.
 * 16. Anti-Tampering: Modificación de cuota de crédito produce VERIFY = FAIL.
 * 17. Anti-Tampering: Modificación de propiedad Catálogo 55 produce VERIFY = FAIL.
 * 18. Anti-Tampering: Modificación de DigestValue produce VERIFY = FAIL.
 * 19. Anti-Tampering: Modificación de SignatureValue produce VERIFY = FAIL.
 * 20. Negativa: XML malformado sintácticamente lanza XmlMalformadoCpeExcepcion (400).
 * 21. Negativa: Inyección DOCTYPE lanza XmlMalformadoCpeExcepcion (400).
 * 22. Negativa: Inyección ENTITY lanza XmlMalformadoCpeExcepcion (400).
 * 23. Negativa: XML sin bloque cac:Signature lanza PreparacionFirmaExcepcion (422).
 * 24. Negativa: cac:Signature sin prefijo '#' en cbc:URI lanza CrossLinkFirmaInvalidoExcepcion (422).
 * 25. Negativa: cac:Signature con NCName inválido lanza CrossLinkFirmaInvalidoExcepcion (422).
 * 26. Negativa: Documento ya firmado lanza FirmaCpeExcepcion (409, fail-fast idempotencia).
 * 27. Negativa: XML sin ext:ExtensionContent lanza XmlMalformadoCpeExcepcion (400).
 * 28. Negativa: Clave privada corrupta lanza ClavePrivadaInvalidaExcepcion (422).
 * 29. Negativa: Certificado corrupto lanza CertificadoInvalidoExcepcion (422).
 * 30. Negativa: Disparidad entre clave privada y certificado lanza ClavePrivadaInvalidaExcepcion (422).
 * 31. Negativa: Verificación con clave pública no coincidente lanza VerificacionFirmaFallidaExcepcion (422).
 * 32. Seguridad Safe-by-Default: DOCTYPE en documento firmado es rechazado por verifyDocument().
 * 33. Seguridad Safe-by-Default: Legacy Mode estrictamente inhabilitado (forbidDoctype = true).
 * 34. Determinismo: Mismo signable XML y misma clave producen idéntico DigestValue.
 * 35. Auditoría de Cero Secretos: Cero contraseñas, credenciales técnicas SUNAT o certificados reales en código nuevo.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/soporte/GeneradorCertificadoSinteticoTest.php';
require_once __DIR__ . '/soporte/ProveedorMaterialCriptograficoEnMemoria.php';

use CamargoPMS\Excepciones\CertificadoInvalidoExcepcion;
use CamargoPMS\Excepciones\ClavePrivadaInvalidaExcepcion;
use CamargoPMS\Excepciones\CrossLinkFirmaInvalidoExcepcion;
use CamargoPMS\Excepciones\FirmaCpeExcepcion;
use CamargoPMS\Excepciones\PreparacionFirmaExcepcion;
use CamargoPMS\Excepciones\VerificacionFirmaFallidaExcepcion;
use CamargoPMS\Excepciones\XmlMalformadoCpeExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Modelos\CPE\CpeCuota;
use CamargoPMS\Modelos\CPE\CpeDocumentoRelacionado;
use CamargoPMS\Modelos\CPE\CpeHospedajeFiscal;
use CamargoPMS\Modelos\CPE\CpeLinea;
use CamargoPMS\Servicios\CPE\ConstantesUbl;
use CamargoPMS\Servicios\CPE\DTO\ContextoFirmaCpe;
use CamargoPMS\Servicios\CPE\Firma\FirmadorCpeXmlSec;
use CamargoPMS\Servicios\CPE\Firma\MaterialCriptograficoCpe;
use CamargoPMS\Servicios\CPE\Firma\PreparadorFirmaUbl;
use CamargoPMS\Servicios\CPE\GeneradorUblServicio;
use CamargoPMS\Tests\Soporte\GeneradorCertificadoSinteticoTest;
use CamargoPMS\Tests\Soporte\ProveedorMaterialCriptograficoEnMemoria;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

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
echo "EJECUTANDO SUITE SUNAT-1D-C3C2 — FIRMADOR DIGITAL XMLDSig (xmlseclibs ^4.0)\n";
echo "====================================================================\n\n";

$generadorUbl = new GeneradorUblServicio();
$preparador = new PreparadorFirmaUbl();
$firmador = new FirmadorCpeXmlSec();

$contextoValido = new ContextoFirmaCpe(
    rucFirmante: '20609998881',
    razonSocialFirmante: 'CAMARGO HOSTELERIA S.A.C.',
    identificadorFirma: 'SignatureKG',
    identificadorDescriptivo: 'IDSignKG'
);

// Generar material criptográfico sintético de prueba efímero
echo "[-] Generando material criptográfico sintético efímero (RSA 2048 / X.509)...\n";
$materialSintetico = GeneradorCertificadoSinteticoTest::generar(
    ruc: '20609998881',
    razonSocial: 'CAMARGO HOSTELERIA S.A.C.'
);
$proveedorSintetico = new ProveedorMaterialCriptograficoEnMemoria($materialSintetico);
echo "    Material generado en memoria con éxito.\n\n";

// Helper para crear comprobante base
function crearFixtureComprobante(
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

function crearFixtureLinea(int $id = 1, int $orden = 1): CpeLinea
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

// Función de verificación de preservación canónica (Signed - ds:Signature === Signable)
function verificarPreservacionCanonicaFirma(string $xmlSignable, string $xmlFirmado): bool
{
    $domSignable = new DOMDocument('1.0', 'UTF-8');
    $domSignable->preserveWhiteSpace = false;
    $domSignable->formatOutput = true;
    $domSignable->loadXML($xmlSignable);
    $c14nSignable = $domSignable->C14N();

    $domFirmado = new DOMDocument('1.0', 'UTF-8');
    $domFirmado->preserveWhiteSpace = false;
    $domFirmado->formatOutput = true;
    $domFirmado->loadXML($xmlFirmado);

    $firmas = $domFirmado->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature');
    if ($firmas->length === 1) {
        $sigNode = $firmas->item(0);
        $sigNode->parentNode->removeChild($sigNode);
    }

    $c14nRestaurado = $domFirmado->C14N();

    return $c14nSignable === $c14nRestaurado;
}

// Función de auditoría de invarianza de nodos fiscales entre Signable y Signed
function auditarInvarianzaNodosFiscalesFirma(string $tipoCpe, string $xmlSignable, string $xmlSigned): array
{
    $domSignable = new DOMDocument('1.0', 'UTF-8');
    $domSignable->preserveWhiteSpace = false;
    $domSignable->formatOutput = true;
    $domSignable->loadXML($xmlSignable);

    $domSigned = new DOMDocument('1.0', 'UTF-8');
    $domSigned->preserveWhiteSpace = false;
    $domSigned->formatOutput = true;
    $domSigned->loadXML($xmlSigned);

    $anomalias = [];

    // 1. cbc:ID
    $idSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'ID')->item(0)?->textContent;
    $idSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'ID')->item(0)?->textContent;
    if ($idSignable !== $idSigned) {
        $anomalias[] = 'cbc:ID modificado';
    }

    // 2. cbc:IssueDate
    $dateSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueDate')->item(0)?->textContent;
    $dateSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueDate')->item(0)?->textContent;
    if ($dateSignable !== $dateSigned) {
        $anomalias[] = 'cbc:IssueDate modificado';
    }

    // 3. cbc:IssueTime
    $timeSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueTime')->item(0)?->textContent;
    $timeSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueTime')->item(0)?->textContent;
    if ($timeSignable !== $timeSigned) {
        $anomalias[] = 'cbc:IssueTime modificado';
    }

    // 4. cbc:DocumentCurrencyCode
    $currSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'DocumentCurrencyCode')->item(0)?->textContent;
    $currSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'DocumentCurrencyCode')->item(0)?->textContent;
    if ($currSignable !== $currSigned) {
        $anomalias[] = 'cbc:DocumentCurrencyCode modificado';
    }

    // 5. cac:AccountingSupplierParty (C14N)
    $suppSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0)?->C14N();
    $suppSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty')->item(0)?->C14N();
    if ($suppSignable !== $suppSigned) {
        $anomalias[] = 'cac:AccountingSupplierParty modificado';
    }

    // 6. cac:AccountingCustomerParty (C14N)
    $custSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingCustomerParty')->item(0)?->C14N();
    $custSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingCustomerParty')->item(0)?->C14N();
    if ($custSignable !== $custSigned) {
        $anomalias[] = 'cac:AccountingCustomerParty modificado';
    }

    // 7. cac:TaxTotal (C14N)
    $taxSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'TaxTotal')->item(0)?->C14N();
    $taxSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'TaxTotal')->item(0)?->C14N();
    if ($taxSignable !== $taxSigned) {
        $anomalias[] = 'cac:TaxTotal modificado';
    }

    // 8. Totales monetarios
    $monetaryTag = ($tipoCpe === '08') ? 'RequestedMonetaryTotal' : 'LegalMonetaryTotal';
    $monSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $monetaryTag)->item(0)?->C14N();
    $monSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $monetaryTag)->item(0)?->C14N();
    if ($monSignable !== $monSigned) {
        $anomalias[] = "cac:{$monetaryTag} modificado";
    }

    // 9. cac:PaymentTerms
    $ptSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PaymentTerms');
    $ptSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PaymentTerms');
    if ($ptSignable->length !== $ptSigned->length) {
        $anomalias[] = "cac:PaymentTerms cantidad modificada ({$ptSignable->length} != {$ptSigned->length})";
    } else {
        for ($i = 0; $i < $ptSignable->length; $i++) {
            if ($ptSignable->item($i)->C14N() !== $ptSigned->item($i)->C14N()) {
                $anomalias[] = "cac:PaymentTerms índice {$i} modificado";
            }
        }
    }

    // 10. Líneas
    $lineTag = match ($tipoCpe) {
        '07' => 'CreditNoteLine',
        '08' => 'DebitNoteLine',
        default => 'InvoiceLine'
    };
    $linesSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $lineTag);
    $linesSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, $lineTag);
    if ($linesSignable->length !== $linesSigned->length) {
        $anomalias[] = "{$lineTag} cantidad modificada ({$linesSignable->length} != {$linesSigned->length})";
    } else {
        for ($i = 0; $i < $linesSignable->length; $i++) {
            if ($linesSignable->item($i)->C14N() !== $linesSigned->item($i)->C14N()) {
                $anomalias[] = "{$lineTag} índice {$i} modificado";
            }
        }
    }

    // 11. cac:Signature de C3C1 (invariante)
    $cacSigSignable = $domSignable->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->item(0)?->C14N();
    $cacSigSigned = $domSigned->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature')->item(0)?->C14N();
    if ($cacSigSignable !== $cacSigSigned) {
        $anomalias[] = 'cac:Signature modificado por C3C2';
    }

    return $anomalias;
}

// ====================================================================
// BLOQUE 1: PRUEBAS POSITIVAS DE FIRMA DIGITAL EN LOS 4 CPES OFICIALES
// ====================================================================
echo "[1] Pruebas Positivas de Firma Digital en Comprobantes UBL 2.1...\n";

// --- 01 FACTURA ---
$cpeFactura = crearFixtureComprobante('FACTURA', 'F001', 101, 'CONTADO');
$cpeFactura->agregarLinea(crearFixtureLinea(1, 1));
$xmlUnsignedFactura = $generadorUbl->generarXml($cpeFactura);
$xmlSignableFactura = $preparador->preparar($xmlUnsignedFactura, $contextoValido);
$xmlSignedFactura = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);

verificar('01 Factura: XML firmado generado no está vacío', !empty($xmlSignedFactura));
verificar('01 Factura: Contiene exactamente 1 elemento ds:Signature', (bool) preg_match('/<ds:Signature\b/', $xmlSignedFactura) && substr_count($xmlSignedFactura, '</ds:Signature>') === 1);
verificar('01 Factura: ds:Signature contiene Id="SignatureKG"', str_contains($xmlSignedFactura, 'Id="SignatureKG"'));
verificar('01 Factura: SignatureMethod es rsa-sha256', str_contains($xmlSignedFactura, 'Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"'));
verificar('01 Factura: DigestMethod es sha256', str_contains($xmlSignedFactura, 'Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"'));
verificar('01 Factura: CanonicalizationMethod es xml-exc-c14n#', str_contains($xmlSignedFactura, 'Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"'));
verificar('01 Factura: Transform es enveloped-signature', str_contains($xmlSignedFactura, 'Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"'));
verificar('01 Factura: Reference URI es exactamente vacío (URI="")', str_contains($xmlSignedFactura, '<ds:Reference URI="">'));
verificar('01 Factura: ds:DigestValue existe y tiene longitud Base64 SHA-256 (44 chars)', (bool) preg_match('/<ds:DigestValue>[A-Za-z0-9+\/]{43}=<\/ds:DigestValue>/', $xmlSignedFactura));
verificar('01 Factura: ds:SignatureValue existe y no está vacío', str_contains($xmlSignedFactura, '<ds:SignatureValue>') && !str_contains($xmlSignedFactura, '<ds:SignatureValue></ds:SignatureValue>'));
verificar('01 Factura: ds:X509Certificate existe y contiene certificado sintético', str_contains($xmlSignedFactura, '<ds:X509Certificate>'));
verificar('01 Factura: Cross-link coincidente entre cac:Signature cbc:URI y ds:Signature/@Id', str_contains($xmlSignedFactura, '<cbc:URI>#SignatureKG</cbc:URI>') && str_contains($xmlSignedFactura, 'Id="SignatureKG"'));
verificar('01 Factura: Preservación canónica C14N(signed - ds:Signature) === C14N(signable)', verificarPreservacionCanonicaFirma($xmlSignableFactura, $xmlSignedFactura));
$anomalias01Firma = auditarInvarianzaNodosFiscalesFirma('01', $xmlSignableFactura, $xmlSignedFactura);
verificar('01 Factura: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias01Firma));

// --- 03 BOLETA ---
$cpeBoleta = crearFixtureComprobante('BOLETA', 'B001', 201, 'CONTADO', null, 'PEN', '1', '44556677', 'JUAN PEREZ');
$cpeBoleta->agregarLinea(crearFixtureLinea(1, 1));
$xmlUnsignedBoleta = $generadorUbl->generarXml($cpeBoleta);
$xmlSignableBoleta = $preparador->preparar($xmlUnsignedBoleta, $contextoValido);
$xmlSignedBoleta = $firmador->firmar($xmlSignableBoleta, $proveedorSintetico);

verificar('03 Boleta: XML firmado contiene exactamente 1 ds:Signature', (bool) preg_match('/<ds:Signature\b/', $xmlSignedBoleta) && substr_count($xmlSignedBoleta, '</ds:Signature>') === 1);
verificar('03 Boleta: ds:Signature/@Id coincide con SignatureKG', str_contains($xmlSignedBoleta, 'Id="SignatureKG"'));
verificar('03 Boleta: Preserva receptor DNI 44556677 intacto', str_contains($xmlSignedBoleta, '44556677'));
verificar('03 Boleta: Preservación canónica C14N(signed - ds:Signature) === C14N(signable)', verificarPreservacionCanonicaFirma($xmlSignableBoleta, $xmlSignedBoleta));
$anomalias03Firma = auditarInvarianzaNodosFiscalesFirma('03', $xmlSignableBoleta, $xmlSignedBoleta);
verificar('03 Boleta: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias03Firma));

// --- 07 NOTA DE CRÉDITO ---
$cpeNc = crearFixtureComprobante('NOTA_CREDITO', 'FC01', 301, 'CONTADO');
$cpeNc->agregarLinea(crearFixtureLinea(1, 1));
$cpeNc->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 1,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 101,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'ANULACION DE LA OPERACION'
));
$xmlUnsignedNc = $generadorUbl->generarXml($cpeNc);
$xmlSignableNc = $preparador->preparar($xmlUnsignedNc, $contextoValido);
$xmlSignedNc = $firmador->firmar($xmlSignableNc, $proveedorSintetico);

verificar('07 Nota de Crédito: XML firmado contiene exactamente 1 ds:Signature', (bool) preg_match('/<ds:Signature\b/', $xmlSignedNc) && substr_count($xmlSignedNc, '</ds:Signature>') === 1);
verificar('07 Nota de Crédito: Preserva DiscrepancyResponse motivo 01', str_contains($xmlSignedNc, '<cbc:ResponseCode') && str_contains($xmlSignedNc, '>01<'));
verificar('07 Nota de Crédito: Preserva BillingReference F001-00000101', str_contains($xmlSignedNc, 'F001-00000101'));
verificar('07 Nota de Crédito: Preservación canónica C14N(signed - ds:Signature) === C14N(signable)', verificarPreservacionCanonicaFirma($xmlSignableNc, $xmlSignedNc));
$anomalias07Firma = auditarInvarianzaNodosFiscalesFirma('07', $xmlSignableNc, $xmlSignedNc);
verificar('07 Nota de Crédito: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias07Firma));

// --- 08 NOTA DE DÉBITO ---
$cpeNd = crearFixtureComprobante('NOTA_DEBITO', 'FD01', 401, 'CONTADO');
$cpeNd->agregarLinea(crearFixtureLinea(1, 1));
$cpeNd->agregarDocumentoRelacionado(new CpeDocumentoRelacionado(
    id: 2,
    cpeId: 100,
    cpeRelacionadoId: 99,
    tipoDocumentoRelacionado: '01',
    serieRelacionada: 'F001',
    correlativoRelacionado: 101,
    fechaEmisionRelacionada: '2026-10-05',
    codigoTipoRelacion: '01',
    descripcionMotivo: 'PENALIDAD POR MORA'
));
$xmlUnsignedNd = $generadorUbl->generarXml($cpeNd);
$xmlSignableNd = $preparador->preparar($xmlUnsignedNd, $contextoValido);
$xmlSignedNd = $firmador->firmar($xmlSignableNd, $proveedorSintetico);

verificar('08 Nota de Débito: XML firmado contiene exactamente 1 ds:Signature', (bool) preg_match('/<ds:Signature\b/', $xmlSignedNd) && substr_count($xmlSignedNd, '</ds:Signature>') === 1);
verificar('08 Nota de Débito: Preserva RequestedMonetaryTotal', str_contains($xmlSignedNd, '<cac:RequestedMonetaryTotal>'));
verificar('08 Nota de Débito: Preservación canónica C14N(signed - ds:Signature) === C14N(signable)', verificarPreservacionCanonicaFirma($xmlSignableNd, $xmlSignedNd));
$anomalias08Firma = auditarInvarianzaNodosFiscalesFirma('08', $xmlSignableNd, $xmlSignedNd);
verificar('08 Nota de Débito: Cero mutaciones no autorizadas en nodos fiscales', empty($anomalias08Firma));

// ====================================================================
// BLOQUE 2: FIXTURE COMPLEJO (DL 919, CUOTAS, MULTILÍNEA, CATÁLOGO 55)
// ====================================================================
echo "\n[2] Pruebas en Fixture Complejo (DL 919 Hospedaje, Crédito, Multilínea, Catálogo 55)...\n";

$cpeComplejo = crearFixtureComprobante('FACTURA', 'F001', 303, 'CREDITO', '1500.00', 'USD', '6', '20444555666', 'AGENCIA DE VIAJES INTERNACIONAL S.A.');
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

$xmlUnsignedComplejo = $generadorUbl->generarXml($cpeComplejo);
$xmlSignableComplejo = $preparador->preparar($xmlUnsignedComplejo, $contextoValido);
$xmlSignedComplejo = $firmador->firmar($xmlSignableComplejo, $proveedorSintetico);

verificar('Complejo: Preservación canónica C14N(signed - ds:Signature) === C14N(signable)', verificarPreservacionCanonicaFirma($xmlSignableComplejo, $xmlSignedComplejo));

// Validar nodos fiscales en XML firmado complejo
$domSignedComplejo = new DOMDocument();
$domSignedComplejo->loadXML($xmlSignedComplejo);
$xpathComp = new DOMXPath($domSignedComplejo);
$xpathComp->registerNamespace('cac', ConstantesUbl::XMLNS_CAC);
$xpathComp->registerNamespace('cbc', ConstantesUbl::XMLNS_CBC);

$propsCat55Signed = $xpathComp->query('//cac:AdditionalItemProperty');
verificar('Complejo: Preserva exactamente 17 propiedades del Catálogo 55 tras la firma', $propsCat55Signed->length === 17);

$cuotasSigned = $xpathComp->query('//cac:PaymentTerms[starts-with(cbc:PaymentMeansID, "Cuota")]');
verificar('Complejo: Preserva exactamente 2 cuotas de crédito Cuota001 y Cuota002', $cuotasSigned->length === 2);

$montoNetoSigned = $xpathComp->query('//cac:PaymentTerms[cbc:Amount]/cbc:Amount');
verificar('Complejo: Preserva monto neto pendiente 1500.00 en PaymentTerms', $montoNetoSigned->length > 0 && $montoNetoSigned->item(0)->textContent === '1500.00');

$anomaliasComplejoFirma = auditarInvarianzaNodosFiscalesFirma('01', $xmlSignableComplejo, $xmlSignedComplejo);
verificar('Complejo: Cero mutaciones no autorizadas en nodos fiscales', empty($anomaliasComplejoFirma));

// ====================================================================
// BLOQUE 3: PRUEBAS ANTI-TAMPERING (ALTERACIÓN MALICIOSA POST-FIRMA)
// ====================================================================
echo "\n[3] Pruebas Anti-Tampering (Modificaciones Maliciosas Post-Firma)...\n";

$clavePublicaTest = new XMLSecurityKey(FirmadorCpeXmlSec::ALGORITMO_FIRMA, ['type' => 'public']);
$clavePublicaTest->loadKey($materialSintetico->obtenerCertificadoX509Pem(), false, true);

// Función auxiliar para probar fallo en tamper
function assertTamperFails(string $xmlBase, callable $tamperFn, string $descripcion): void
{
    global $clavePublicaTest;
    $dom = new DOMDocument();
    $dom->loadXML($xmlBase);

    $tamperFn($dom);

    $falloDetectado = false;
    try {
        $verificador = new XMLSecurityDSig();
        $verificador->verifyDocument($clavePublicaTest, $dom);
    } catch (Throwable $e) {
        $falloDetectado = true;
    }

    verificar("Anti-Tampering: {$descripcion} produce VERIFY = FAIL", $falloDetectado);
}

// 1. Modificar total monetario
assertTamperFails($xmlSignedFactura, function (DOMDocument $d) {
    $totals = $d->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'PayableAmount');
    if ($totals->length > 0) {
        $totals->item(0)->textContent = '119.00';
    }
}, 'Modificación de total monetario (+1.00)');

// 2. Modificar RUC emisor
assertTamperFails($xmlSignedFactura, function (DOMDocument $d) {
    $xpath = new DOMXPath($d);
    $xpath->registerNamespace('cac', ConstantesUbl::XMLNS_CAC);
    $xpath->registerNamespace('cbc', ConstantesUbl::XMLNS_CBC);
    $rucs = $xpath->query('//cac:AccountingSupplierParty//cbc:ID');
    if ($rucs->length > 0) {
        $rucs->item(0)->textContent = '20999999999';
    }
}, 'Modificación de RUC emisor');

// 3. Modificar descripción de línea
assertTamperFails($xmlSignedFactura, function (DOMDocument $d) {
    $descs = $d->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'Description');
    if ($descs->length > 0) {
        $descs->item(0)->textContent = 'TEXTO MODIFICADO TRAS LA FIRMA';
    }
}, 'Modificación de descripción de producto en línea');

// 4. Modificar fecha de emisión (IssueDate)
assertTamperFails($xmlSignedFactura, function (DOMDocument $d) {
    $dates = $d->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'IssueDate');
    if ($dates->length > 0) {
        $dates->item(0)->textContent = '2026-12-31';
    }
}, 'Modificación de cbc:IssueDate');

// 5. Modificar cuota en comprobante complejo
assertTamperFails($xmlSignedComplejo, function (DOMDocument $d) {
    $xpath = new DOMXPath($d);
    $xpath->registerNamespace('cac', ConstantesUbl::XMLNS_CAC);
    $xpath->registerNamespace('cbc', ConstantesUbl::XMLNS_CBC);
    $cuotas = $xpath->query('//cac:PaymentTerms[cbc:PaymentMeansID="Cuota001"]/cbc:Amount');
    if ($cuotas->length > 0) {
        $cuotas->item(0)->textContent = '999.00';
    }
}, 'Modificación de monto de cuota Cuota001');

// 6. Modificar propiedad Catálogo 55
assertTamperFails($xmlSignedComplejo, function (DOMDocument $d) {
    $xpath = new DOMXPath($d);
    $xpath->registerNamespace('cac', ConstantesUbl::XMLNS_CAC);
    $xpath->registerNamespace('cbc', ConstantesUbl::XMLNS_CBC);
    $props = $xpath->query('//cac:AdditionalItemProperty[cbc:NameCode="4000"]/cbc:Value');
    if ($props->length > 0) {
        $props->item(0)->textContent = 'XX';
    }
}, 'Modificación de país emisor de pasaporte (4000) en Catálogo 55');

// 7. Modificar DigestValue directamente
assertTamperFails($xmlSignedFactura, function (DOMDocument $d) {
    $digs = $d->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'DigestValue');
    if ($digs->length > 0) {
        $actual = $digs->item(0)->textContent;
        $digs->item(0)->textContent = 'X' . substr($actual, 1);
    }
}, 'Modificación directa de ds:DigestValue');

// 8. Modificar SignatureValue directamente
assertTamperFails($xmlSignedFactura, function (DOMDocument $d) {
    $sigs = $d->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'SignatureValue');
    if ($sigs->length > 0) {
        $actual = $sigs->item(0)->textContent;
        $sigs->item(0)->textContent = 'Z' . substr($actual, 1);
    }
}, 'Modificación directa de ds:SignatureValue');

// ====================================================================
// BLOQUE 4: PRUEBAS NEGATIVAS Y VALIDACIONES DEFENSIVAS
// ====================================================================
echo "\n[4] Pruebas Negativas y Validaciones Defensivas...\n";

// 1. XML malformado sintácticamente
$errorDetectado = false;
try {
    $firmador->firmar('<Invoice><invalido', $proveedorSintetico);
} catch (XmlMalformadoCpeExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: XML malformado lanza XmlMalformadoCpeExcepcion', true);
    verificar('Negativa: Código de excepción de XML malformado es 400', $e->getCode() === 400);
}
if (!$errorDetectado) {
    verificar('Negativa: XML malformado lanza XmlMalformadoCpeExcepcion', false);
}

// 2. Inyección DOCTYPE
$errorDetectado = false;
try {
    $firmador->firmar('<!DOCTYPE foo SYSTEM "http://malicioso.com"><Invoice/>', $proveedorSintetico);
} catch (XmlMalformadoCpeExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: Inyección DOCTYPE rechazada con XmlMalformadoCpeExcepcion', true);
    verificar('Negativa: Mensaje advierte sobre riesgo XXE', str_contains($e->getMessage(), 'XXE'));
}
if (!$errorDetectado) {
    verificar('Negativa: Inyección DOCTYPE rechazada con XmlMalformadoCpeExcepcion', false);
}

// 3. Inyección ENTITY
$errorDetectado = false;
try {
    $firmador->firmar('<Invoice><!ENTITY test "foo"></Invoice>', $proveedorSintetico);
} catch (XmlMalformadoCpeExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: Inyección ENTITY rechazada con XmlMalformadoCpeExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: Inyección ENTITY rechazada con XmlMalformadoCpeExcepcion', false);
}

// 4. XML sin cac:Signature
$errorDetectado = false;
try {
    $xmlSinCac = $xmlUnsignedFactura; // aún no tiene cac:Signature
    $firmador->firmar($xmlSinCac, $proveedorSintetico);
} catch (PreparacionFirmaExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: XML sin cac:Signature lanza PreparacionFirmaExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: XML sin cac:Signature lanza PreparacionFirmaExcepcion', false);
}

// 5. cac:Signature sin prefijo '#' en cbc:URI
$errorDetectado = false;
try {
    $xmlUriSinHash = str_replace('<cbc:URI>#SignatureKG</cbc:URI>', '<cbc:URI>SignatureKG</cbc:URI>', $xmlSignableFactura);
    $firmador->firmar($xmlUriSinHash, $proveedorSintetico);
} catch (CrossLinkFirmaInvalidoExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: cbc:URI sin prefijo # lanza CrossLinkFirmaInvalidoExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: cbc:URI sin prefijo # lanza CrossLinkFirmaInvalidoExcepcion', false);
}

// 6. cac:Signature con NCName inválido en cbc:URI
$errorDetectado = false;
try {
    $xmlUriInvalido = str_replace('<cbc:URI>#SignatureKG</cbc:URI>', '<cbc:URI>#123 Inválido</cbc:URI>', $xmlSignableFactura);
    $firmador->firmar($xmlUriInvalido, $proveedorSintetico);
} catch (CrossLinkFirmaInvalidoExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: cbc:URI con NCName inválido lanza CrossLinkFirmaInvalidoExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: cbc:URI con NCName inválido lanza CrossLinkFirmaInvalidoExcepcion', false);
}

// 7. Re-firma sobre documento ya firmado (idempotencia defensiva)
$errorDetectado = false;
try {
    $firmador->firmar($xmlSignedFactura, $proveedorSintetico);
} catch (FirmaCpeExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: Re-firma sobre XML ya firmado lanza FirmaCpeExcepcion', true);
    verificar('Negativa: Código de idempotencia es 409', $e->getCode() === 409);
}
if (!$errorDetectado) {
    verificar('Negativa: Re-firma sobre XML ya firmado lanza FirmaCpeExcepcion', false);
}

// 8. XML sin ext:ExtensionContent
$errorDetectado = false;
try {
    $xmlSinExt = str_replace('<ext:ExtensionContent/>', '', $xmlSignableFactura);
    $firmador->firmar($xmlSinExt, $proveedorSintetico);
} catch (XmlMalformadoCpeExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: XML sin ext:ExtensionContent lanza XmlMalformadoCpeExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: XML sin ext:ExtensionContent lanza XmlMalformadoCpeExcepcion', false);
}

// 9. Clave privada corrupta
$errorDetectado = false;
try {
    new MaterialCriptograficoCpe(
        $materialSintetico->obtenerCertificadoX509Pem(),
        "-----BEGIN RSA PRIVATE KEY-----\nCORRUPTO\n-----END RSA PRIVATE KEY-----"
    );
} catch (ClavePrivadaInvalidaExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: Clave privada corrupta lanza ClavePrivadaInvalidaExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: Clave privada corrupta lanza ClavePrivadaInvalidaExcepcion', false);
}

// 10. Certificado corrupto
$errorDetectado = false;
try {
    new MaterialCriptograficoCpe(
        "-----BEGIN CERTIFICATE-----\nCORRUPTO\n-----END CERTIFICATE-----",
        $materialSintetico->obtenerClavePrivadaPem()
    );
} catch (CertificadoInvalidoExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: Certificado corrupto lanza CertificadoInvalidoExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: Certificado corrupto lanza CertificadoInvalidoExcepcion', false);
}

// 11. Disparidad entre clave privada y certificado (Key Mismatch)
$errorDetectado = false;
try {
    $parDispar = GeneradorCertificadoSinteticoTest::generarParDispar();
    new MaterialCriptograficoCpe($parDispar['certPem'], $parDispar['privPemMismatch']);
} catch (ClavePrivadaInvalidaExcepcion $e) {
    $errorDetectado = true;
    verificar('Negativa: Disparidad clave/certificado detectada fail-closed con ClavePrivadaInvalidaExcepcion', true);
}
if (!$errorDetectado) {
    verificar('Negativa: Disparidad clave/certificado detectada fail-closed con ClavePrivadaInvalidaExcepcion', false);
}

// 12. Verificación de firma contra clave pública ajena / dispar
$errorDetectado = false;
try {
    $materialAjeno = GeneradorCertificadoSinteticoTest::generar(ruc: '20609998889', razonSocial: 'EMPRESA AJENA');
    $clavePublicaAjena = new XMLSecurityKey(FirmadorCpeXmlSec::ALGORITMO_FIRMA, ['type' => 'public']);
    $clavePublicaAjena->loadKey($materialAjeno->obtenerCertificadoX509Pem(), false, true);

    $domVerifAjena = new DOMDocument();
    $domVerifAjena->loadXML($xmlSignedFactura);
    $verificadorAjeno = new XMLSecurityDSig();
    $verificadorAjeno->verifyDocument($clavePublicaAjena, $domVerifAjena);
} catch (Throwable $e) {
    $errorDetectado = true;
    verificar('Negativa: Verificación con clave pública ajena produce FAIL', true);
}
if (!$errorDetectado) {
    verificar('Negativa: Verificación con clave pública ajena produce FAIL', false);
}

// 13. Inyección de algoritmo débil en SignatureMethod (RSA-SHA1)
$xmlFresh13 = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);
$domWeakSig = new DOMDocument();
$domWeakSig->loadXML($xmlFresh13);
$smNode = $domWeakSig->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'SignatureMethod')->item(0);
$smNode?->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
$rechazoWeakSig = false;
$msgWeakSig = '';
try {
    $verificadorWeakSig = new XMLSecurityDSig();
    $verificadorWeakSig->verifyDocument($clavePublicaTest, $domWeakSig);
} catch (Throwable $e) {
    $rechazoWeakSig = true;
    $msgWeakSig = $e->getMessage();
}
verificar('Negativa: Inyección SignatureMethod débil RSA-SHA1 rechazada fail-closed', $rechazoWeakSig);
verificar('Negativa: Rechazo criptográfico para SignatureMethod RSA-SHA1 no permitido', !empty($msgWeakSig));

// 14. Inyección de algoritmo débil en DigestMethod (SHA-1)
$xmlFresh14 = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);
$domWeakDig = new DOMDocument();
$domWeakDig->loadXML($xmlFresh14);
$dmNode = $domWeakDig->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'DigestMethod')->item(0);
$dmNode?->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
$rechazoWeakDig = false;
$msgWeakDig = '';
try {
    $verificadorWeakDig = new XMLSecurityDSig();
    $verificadorWeakDig->verifyDocument($clavePublicaTest, $domWeakDig);
} catch (Throwable $e) {
    $rechazoWeakDig = true;
    $msgWeakDig = $e->getMessage();
}
verificar('Negativa: Inyección DigestMethod débil SHA-1 rechazada fail-closed', $rechazoWeakDig);
verificar('Negativa: Motivo exacto de rechazo para SHA-1 en DigestMethod', str_contains($msgWeakDig, "DigestMethod algorithm is not allowed: 'http://www.w3.org/2000/09/xmldsig#sha1'"));

// 15. External Reference URI (https://example.invalid/cpe-externo.xml) con NETWORK REQUEST = 0
$xmlFresh15 = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);
$domExtUri = new DOMDocument();
$domExtUri->loadXML($xmlFresh15);
$refExtNode = $domExtUri->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Reference')->item(0);
$refExtNode?->setAttribute('URI', 'https://example.invalid/cpe-externo.xml');
$rechazoExtUri = false;
$msgExtUri = '';
try {
    $verificadorExtUri = new XMLSecurityDSig();
    $verificadorExtUri->verifyDocument($clavePublicaTest, $domExtUri);
} catch (Throwable $e) {
    $rechazoExtUri = true;
    $msgExtUri = $e->getMessage();
}
verificar('Negativa: External Reference URI rechazada fail-closed sin acceso a red', $rechazoExtUri);
verificar('Negativa: Motivo exacto de rechazo para External Reference URI (same-document reference required)', str_contains($msgExtUri, 'Reference URI must be a same-document reference'));

// 16. Duplicate ID en elemento referenciado (XSW / ID collision)
$xmlFresh16 = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);
$domDupId = new DOMDocument();
$domDupId->loadXML($xmlFresh16);
$refDupNode = $domDupId->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Reference')->item(0);
$refDupNode?->setAttribute('URI', '#dupElementId');
$elemA = $domDupId->createElement('ItemA');
$elemA->setAttribute('Id', 'dupElementId');
$domDupId->documentElement?->appendChild($elemA);
$elemB = $domDupId->createElement('ItemB');
$elemB->setAttribute('Id', 'dupElementId');
$domDupId->documentElement?->appendChild($elemB);
$rechazoDupId = false;
$msgDupId = '';
try {
    $verificadorDupId = new XMLSecurityDSig();
    $verificadorDupId->verifyDocument($clavePublicaTest, $domDupId);
} catch (Throwable $e) {
    $rechazoDupId = true;
    $msgDupId = $e->getMessage();
}
verificar('Negativa: Duplicate ID en elemento referenciado rechazado fail-closed (XSW)', $rechazoDupId);
verificar('Negativa: Motivo exacto de rechazo para Duplicate ID (Reference URI identifies multiple nodes)', str_contains($msgDupId, 'Reference URI identifies multiple nodes'));

// 17. Duplicate Signature Element / Firma Clonada (XSW / Multiple Signatures)
$xmlFresh17 = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);
$domDupSig = new DOMDocument();
$domDupSig->loadXML($xmlFresh17);
$sigOrig = $domDupSig->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature')->item(0);
if ($sigOrig !== null) {
    $sigClon = $sigOrig->cloneNode(true);
    $domDupSig->documentElement?->appendChild($sigClon);
}
$rechazoDupSig = false;
$msgDupSig = '';
try {
    $refMethod = new ReflectionMethod($firmador, 'ejecutarPostVerificacion');
    $refMethod->setAccessible(true);
    $refMethod->invoke($firmador, $domDupSig, $materialSintetico, 'SignatureKG');
} catch (Throwable $e) {
    $rechazoDupSig = true;
    $msgDupSig = $e->getMessage();
}
verificar('Negativa: Documento con múltiples elementos ds:Signature rechazado fail-closed en post-verificación (XSW)', $rechazoDupSig);
verificar('Negativa: Motivo exacto de rechazo para Signature duplicada', str_contains($msgDupSig, 'Se esperaba exactamente un elemento ds:Signature tras el firmado; encontrados: 2.'));

// ====================================================================
// BLOQUE 5: SEGURIDAD SAFE-BY-DEFAULT Y PRUEBA DE NO LEGACY MODE
// ====================================================================
echo "\n[5] Pruebas de Seguridad Safe-by-Default y Verificación de No Legacy Mode...\n";

// 1. forbidDoctype está activo por defecto en xmlseclibs 4.0
$dsigControl = new XMLSecurityDSig();
verificar('Safe-by-Default: forbidDoctype es true por defecto en xmlseclibs 4.0', $dsigControl->forbidDoctype === true);

// 2. Inyección de DOCTYPE en documento firmado antes de verificar
$errorDoctypeDetectado = false;
try {
    $xmlSignedConDoctype = str_replace(
        '<Invoice',
        '<!DOCTYPE Invoice SYSTEM "http://malicioso.com/dtd"><Invoice',
        $xmlSignedFactura
    );
    $domDoctype = new DOMDocument();
    $domDoctype->loadXML($xmlSignedConDoctype);

    $verifDoctype = new XMLSecurityDSig();
    $verifDoctype->verifyDocument($clavePublicaTest, $domDoctype);
} catch (Throwable $e) {
    $errorDoctypeDetectado = true;
    verificar('Safe-by-Default: verifyDocument() rechaza categóricamente documento firmado con DOCTYPE', true);
}
if (!$errorDoctypeDetectado) {
    verificar('Safe-by-Default: verifyDocument() rechaza categóricamente documento firmado con DOCTYPE', false);
}

// 3. Confirmar que el código de producción no contiene llamadas a enableLegacyMode() ni relajaciones de seguridad
$archivoFirmador = file_get_contents(__DIR__ . '/../app/Servicios/CPE/Firma/FirmadorCpeXmlSec.php');
verificar('Legacy Mode: Ausencia absoluta de llamada a enableLegacyMode() en código de producción', !str_contains($archivoFirmador, '->enableLegacyMode'));
verificar('Legacy Mode: Ausencia de relajación forbidDoctype = false en firmador', !str_contains($archivoFirmador, 'forbidDoctype = false') && !str_contains($archivoFirmador, 'forbidDoctype=false'));
verificar('Legacy Mode: Ausencia de allowXPathTransforms en firmador', !str_contains($archivoFirmador, 'allowXPathTransforms'));

// ====================================================================
// BLOQUE 6: DETERMINISMO ESTRUCTURAL Y SEGURIDAD DE SECRETOS
// ====================================================================
echo "\n[6] Pruebas de Determinismo Estructural y Cero Secretos...\n";

// 1. Para el mismo material y signable XML, el DigestValue SHA-256 es idéntico
$xmlSignedBis = $firmador->firmar($xmlSignableFactura, $proveedorSintetico);
preg_match('/<ds:DigestValue>(.*?)<\/ds:DigestValue>/', $xmlSignedFactura, $m1);
preg_match('/<ds:DigestValue>(.*?)<\/ds:DigestValue>/', $xmlSignedBis, $m2);
verificar('Determinismo: Dos firmas con el mismo material producen idéntico ds:DigestValue SHA-256', !empty($m1[1]) && $m1[1] === $m2[1]);

// 2. Comprobar que MaterialCriptograficoCpe enmascara la clave privada
$volcadoDebug = print_r($materialSintetico, true);
verificar('Seguridad: MaterialCriptograficoCpe no expone la clave privada en print_r/var_dump', !str_contains($volcadoDebug, '-----BEGIN RSA PRIVATE KEY-----') && str_contains($volcadoDebug, '[PROTECTED_PRIVATE_KEY]'));

// 3. Comprobar que json_encode enmascara la clave privada
$jsonSerializado = json_encode($materialSintetico);
verificar('Seguridad: json_encode no serializa la clave privada', !str_contains($jsonSerializado, 'BEGIN RSA') && str_contains($jsonSerializado, '[PROTECTED_PRIVATE_KEY]'));

// 4. Ausencia de credenciales técnicas o certificados reales en código nuevo
$archivoMaterial = file_get_contents(__DIR__ . '/../app/Servicios/CPE/Firma/MaterialCriptograficoCpe.php');
$termSol = 'Clave' . ' SOL';
$termMod = 'MOD' . 'DATOS';
verificar('Seguridad: Cero credenciales técnicas en FirmadorCpeXmlSec', !str_contains($archivoFirmador, $termSol) && !str_contains($archivoFirmador, $termMod));
verificar('Seguridad: Cero credenciales técnicas en MaterialCriptograficoCpe', !str_contains($archivoMaterial, $termSol) && !str_contains($archivoMaterial, $termMod));

// ====================================================================
// RESUMEN FINAL
// ====================================================================
echo "\n====================================================\n";
echo "RESULTADO SUITE SUNAT-1D-C3C2:\n";
echo "TOTAL CHECKS:     {$totalChecks}\n";
echo "CHECKS PASADOS:   {$passedChecks}\n";
echo "FALLOS:           {$failedChecks}\n";
echo "====================================================\n";

if ($failedChecks > 0) {
    exit(1);
}
exit(0);
