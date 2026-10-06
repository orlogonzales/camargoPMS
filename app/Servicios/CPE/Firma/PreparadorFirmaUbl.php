<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Firma;

use CamargoPMS\Excepciones\DocumentoYaPreparadoExcepcion;
use CamargoPMS\Excepciones\RucEmisorInconsistenteExcepcion;
use CamargoPMS\Excepciones\TipoDocumentoNoSoportadoExcepcion;
use CamargoPMS\Excepciones\XmlMalformadoCpeExcepcion;
use CamargoPMS\Servicios\CPE\ConstantesUbl;
use CamargoPMS\Servicios\CPE\DTO\ContextoFirmaCpe;
use DOMDocument;
use DOMElement;

/**
 * Servicio soberano responsable de la preparación estructural previa a la firma digital (XML Signable).
 *
 * Responsabilidad exclusiva:
 * - Valida la integridad sintáctica y seguridad del XML unsigned generado por C2 (defensa XXE / DTD).
 * - Valida la coherencia de identidad fiscal entre el emisor del documento y el firmante.
 * - Inserta el bloque cac:Signature en la posición canónica requerida por la normativa UBL 2.1 / SUNAT
 *   (inmediatamente antes de cac:AccountingSupplierParty).
 * - Vincula el cbc:URI de referencia externa con el identificador descriptivo de firma (#SignatureId).
 * - Preserva rigurosamente todo el contenido tributario, líneas y extensiones preexistentes.
 * - Aplica fail-fast ante reintentos de preparación (idempotencia defensiva).
 *
 * Frontera arquitectónica:
 * - NO calcula valores hash (DigestValue).
 * - NO ejecuta firmas RSA (SignatureValue).
 * - NO inserta elementos ds:Signature ni certificados X.509.
 * - NO accede a llaves privadas, contraseñas ni canales de red.
 */
final class PreparadorFirmaUbl
{
    /** @var string[] Tipos de documentos raíz UBL 2.1 soportados y sus namespaces */
    private const array TIPOS_RAIZ_SOPORTADOS = [
        'Invoice' => ConstantesUbl::XMLNS_INVOICE,
        'CreditNote' => ConstantesUbl::XMLNS_CREDIT_NOTE,
        'DebitNote' => ConstantesUbl::XMLNS_DEBIT_NOTE,
    ];

    /**
     * Prepara una cadena XML sin firmar, insertando el bloque cac:Signature y produciendo el XML signable.
     *
     * @param string $xmlUnsigned Cadena XML válida producida por el GeneradorUblServicio.
     * @param ContextoFirmaCpe $contexto Parámetros descriptivos del firmante y referencias de firma.
     * @return string Cadena XML canónica y bien formada lista para la etapa de firma criptográfica.
     *
     * @throws XmlMalformadoCpeExcepcion Si el XML contiene sintaxis inválida o vectores XXE.
     * @throws TipoDocumentoNoSoportadoExcepcion Si el documento raíz no es Factura, Boleta, NC o ND.
     * @throws DocumentoYaPreparadoExcepcion Si el documento ya cuenta con un bloque cac:Signature.
     * @throws RucEmisorInconsistenteExcepcion Si el RUC del contexto no coincide con el emisor del XML.
     */
    public function preparar(string $xmlUnsigned, ContextoFirmaCpe $contexto): string
    {
        $this->validarSeguridadXml($xmlUnsigned);

        $dom = $this->cargarDomSeguro($xmlUnsigned);

        $this->prepararDom($dom, $contexto);

        $resultado = $dom->saveXML();
        if ($resultado === false) {
            throw new XmlMalformadoCpeExcepcion('Error al serializar el documento XML signable.');
        }

        return $resultado;
    }

    /**
     * Modifica el objeto DOMDocument proporcionado agregando la estructura cac:Signature requerida.
     *
     * @throws TipoDocumentoNoSoportadoExcepcion Si el documento raíz no es soportado.
     * @throws DocumentoYaPreparadoExcepcion Si el documento ya cuenta con cac:Signature.
     * @throws XmlMalformadoCpeExcepcion Si falta cac:AccountingSupplierParty o datos del emisor.
     * @throws RucEmisorInconsistenteExcepcion Si el RUC no coincide.
     */
    public function prepararDom(DOMDocument $dom, ContextoFirmaCpe $contexto): DOMDocument
    {
        $raiz = $dom->documentElement;
        if ($raiz === null) {
            throw new XmlMalformadoCpeExcepcion('El documento XML no posee un elemento raíz.');
        }

        $this->validarTipoDocumento($raiz);
        $this->validarIdempotencia($dom);

        $nodoSupplierParty = $this->obtenerNodoSupplierParty($dom);
        $this->validarCoherenciaRuc($nodoSupplierParty, $contexto);

        // Inserción top-down en el árbol DOM:
        // En UBL 2.1, cac:Signature precede estrictamente a cac:AccountingSupplierParty.
        // Al insertarlo en la raíz antes de crear sus hijos, los nodos heredan limpia y canónicamente
        // los namespaces xmlns:cac y xmlns:cbc declarados en la raíz sin redundancia local.
        $nodoSignature = $dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:Signature');
        $raiz->insertBefore($nodoSignature, $nodoSupplierParty);

        $this->construirCuerpoSignature($dom, $nodoSignature, $contexto);

        // Asegurar que ext:UBLExtensions exista como primer hijo (C2 ya lo genera)
        $this->asegurarUblExtensions($dom, $raiz);

        return $dom;
    }

    /**
     * Pre-check de seguridad contra inyecciones XXE y entidades externas (W3C / OWASP).
     */
    private function validarSeguridadXml(string $xml): void
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new XmlMalformadoCpeExcepcion(
                'El documento XML contiene declaraciones DTD/ENTITY no permitidas (riesgo de seguridad XXE).'
            );
        }
    }

    /**
     * Carga el XML en DOMDocument con banderas de máxima contención y sin resolución de red.
     */
    private function cargarDomSeguro(string $xml): DOMDocument
    {
        $prevErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $cargado = $dom->loadXML($xml, LIBXML_NONET);

        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        if (!$cargado || !empty($errores)) {
            $msg = !empty($errores) ? trim($errores[0]->message) : 'Error de sintaxis XML';
            throw new XmlMalformadoCpeExcepcion("XML malformado o no parseable: {$msg}");
        }

        return $dom;
    }

    /**
     * Valida que el elemento raíz y su namespace correspondan a un CPE admitido por SUNAT.
     */
    private function validarTipoDocumento(DOMElement $raiz): void
    {
        $localName = $raiz->localName;
        $namespaceUri = $raiz->namespaceURI;

        if (!isset(self::TIPOS_RAIZ_SOPORTADOS[$localName]) || self::TIPOS_RAIZ_SOPORTADOS[$localName] !== $namespaceUri) {
            throw new TipoDocumentoNoSoportadoExcepcion(
                "El elemento raíz '{$localName}' con namespace '{$namespaceUri}' no corresponde a un CPE UBL 2.1 soportado."
            );
        }
    }

    /**
     * Valida que el documento no contenga previamente un bloque cac:Signature (fail-fast de idempotencia).
     */
    private function validarIdempotencia(DOMDocument $dom): void
    {
        $firmasExistentes = $dom->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'Signature');
        if ($firmasExistentes->length > 0) {
            throw new DocumentoYaPreparadoExcepcion(
                'El documento XML ya contiene un bloque cac:Signature. No se admite preparación redundante.'
            );
        }
    }

    /**
     * Localiza el nodo obligatorio cac:AccountingSupplierParty en el documento.
     */
    private function obtenerNodoSupplierParty(DOMDocument $dom): DOMElement
    {
        $nodos = $dom->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'AccountingSupplierParty');
        if ($nodos->length === 0) {
            throw new XmlMalformadoCpeExcepcion(
                'El documento no contiene el elemento obligatorio cac:AccountingSupplierParty.'
            );
        }

        /** @var DOMElement $supplierParty */
        $supplierParty = $nodos->item(0);
        return $supplierParty;
    }

    /**
     * Valida que el RUC del emisor declarado en el documento coincida exactamente con el del contexto.
     */
    private function validarCoherenciaRuc(DOMElement $supplierParty, ContextoFirmaCpe $contexto): void
    {
        $rucXml = null;

        // Intentar obtener mediante cac:PartyIdentification/cbc:ID
        $partyIdNodes = $supplierParty->getElementsByTagNameNS(ConstantesUbl::XMLNS_CAC, 'PartyIdentification');
        if ($partyIdNodes->length > 0) {
            $idNodes = $partyIdNodes->item(0)->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'ID');
            if ($idNodes->length > 0) {
                $rucXml = trim($idNodes->item(0)->textContent);
            }
        }

        // Si no se encontró, intentar mediante cbc:CustomerAssignedAccountID
        if ($rucXml === null || $rucXml === '') {
            $caIdNodes = $supplierParty->getElementsByTagNameNS(ConstantesUbl::XMLNS_CBC, 'CustomerAssignedAccountID');
            if ($caIdNodes->length > 0) {
                $rucXml = trim($caIdNodes->item(0)->textContent);
            }
        }

        if ($rucXml === null || $rucXml === '') {
            throw new XmlMalformadoCpeExcepcion(
                'No fue posible extraer el RUC emisor de cac:AccountingSupplierParty.'
            );
        }

        if ($rucXml !== $contexto->obtenerRucFirmante()) {
            throw new RucEmisorInconsistenteExcepcion(
                "Inconsistencia fiscal: el RUC del firmante ('{$contexto->obtenerRucFirmante()}') " .
                "no coincide con el RUC emisor del comprobante ('{$rucXml}')."
            );
        }
    }

    /**
     * Construye determinísticamente los hijos de cac:Signature según la norma SUNAT bajo inserción top-down.
     */
    private function construirCuerpoSignature(DOMDocument $dom, DOMElement $sig, ContextoFirmaCpe $contexto): void
    {
        // 1. cbc:ID (identificador descriptivo, ej. IDSignKG)
        $id = $dom->createElementNS(ConstantesUbl::XMLNS_CBC, 'cbc:ID');
        $id->appendChild($dom->createTextNode($contexto->obtenerIdentificadorDescriptivo()));
        $sig->appendChild($id);

        // 2. cac:SignatoryParty
        $signatoryParty = $dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:SignatoryParty');
        $sig->appendChild($signatoryParty);

        $partyId = $dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyIdentification');
        $signatoryParty->appendChild($partyId);

        $partyIdVal = $dom->createElementNS(ConstantesUbl::XMLNS_CBC, 'cbc:ID');
        $partyIdVal->appendChild($dom->createTextNode($contexto->obtenerRucFirmante()));
        $partyId->appendChild($partyIdVal);

        $partyName = $dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:PartyName');
        $signatoryParty->appendChild($partyName);

        $name = $dom->createElementNS(ConstantesUbl::XMLNS_CBC, 'cbc:Name');
        $name->appendChild($dom->createCDATASection($contexto->obtenerRazonSocialFirmante()));
        $partyName->appendChild($name);

        // 3. cac:DigitalSignatureAttachment
        $attachment = $dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:DigitalSignatureAttachment');
        $sig->appendChild($attachment);

        $externalRef = $dom->createElementNS(ConstantesUbl::XMLNS_CAC, 'cac:ExternalReference');
        $attachment->appendChild($externalRef);

        $uri = $dom->createElementNS(ConstantesUbl::XMLNS_CBC, 'cbc:URI');
        $uri->appendChild($dom->createTextNode($contexto->obtenerUriFirma()));
        $externalRef->appendChild($uri);
    }

    /**
     * Garantiza que ext:UBLExtensions exista en el documento sin duplicar la estructura.
     */
    private function asegurarUblExtensions(DOMDocument $dom, DOMElement $raiz): void
    {
        $exts = $raiz->getElementsByTagNameNS(ConstantesUbl::XMLNS_EXT, 'UBLExtensions');
        if ($exts->length === 0) {
            $nuevoExts = $dom->createElementNS(ConstantesUbl::XMLNS_EXT, 'ext:UBLExtensions');
            $nuevoExt = $dom->createElementNS(ConstantesUbl::XMLNS_EXT, 'ext:UBLExtension');
            $nuevoExts->appendChild($nuevoExt);

            $nuevoContent = $dom->createElementNS(ConstantesUbl::XMLNS_EXT, 'ext:ExtensionContent');
            $nuevoExt->appendChild($nuevoContent);

            $raiz->insertBefore($nuevoExts, $raiz->firstChild);
        }
    }
}
