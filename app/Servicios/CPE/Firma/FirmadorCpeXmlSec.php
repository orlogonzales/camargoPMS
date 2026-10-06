<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Firma;

use CamargoPMS\Excepciones\CrossLinkFirmaInvalidoExcepcion;
use CamargoPMS\Excepciones\FirmaCpeExcepcion;
use CamargoPMS\Excepciones\PreparacionFirmaExcepcion;
use CamargoPMS\Excepciones\VerificacionFirmaFallidaExcepcion;
use CamargoPMS\Excepciones\XmlMalformadoCpeExcepcion;
use CamargoPMS\Servicios\CPE\ConstantesUbl;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;

/**
 * Servicio de producción para el firmado digital XMLDSig de Comprobantes de Pago Electrónicos (CPE).
 *
 * Implementa el estándar W3C XMLDSig (Enveloped Signature) sobre documentos UBL 2.1 preparados (C3C1),
 * utilizando la librería robrichards/xmlseclibs ^4.0 y el acelerador nativo OpenSSL.
 *
 * Contrato criptográfico soberano de Camargo PMS:
 * - Algoritmo de firma: RSA-SHA256 (http://www.w3.org/2001/04/xmldsig-more#rsa-sha256)
 * - Algoritmo de resumen: SHA-256 (http://www.w3.org/2001/04/xmlenc#sha256)
 * - Canonicalización: Exclusive C14N 1.0 (http://www.w3.org/2001/10/xml-exc-c14n#)
 * - Transformación: Enveloped Signature (http://www.w3.org/2000/09/xmldsig#enveloped-signature)
 * - Referencia URI: "" (Documento XML completo)
 * - Vinculación referencial: ds:Signature/@Id coincide exactamente con cac:Signature//cbc:URI sin '#'
 *
 * Garantías de seguridad:
 * - Fail-Closed: Cero fallback silencioso a algoritmos débiles (SHA-1 / MD5).
 * - Legacy Mode: Estrictamente PROHIBIDO (enableLegacyMode() nunca se invoca).
 * - Post-Verificación Obligatoria: SIGN -> VERIFY -> RETURN mediante verifyDocument() de xmlseclibs 4.
 * - Invarianza tributaria: Solo se añade ds:Signature dentro de ext:ExtensionContent; cero mutaciones ajenas.
 */
final class FirmadorCpeXmlSec implements FirmadorCpeInterfaz
{
    public const string ALGORITMO_FIRMA = XMLSecurityKey::RSA_SHA256;
    public const string ALGORITMO_DIGEST = XMLSecurityDSig::SHA256;
    public const string ALGORITMO_CANONICALIZACION = XMLSecurityDSig::EXC_C14N;
    public const string ALGORITMO_TRANSFORM = XMLSecurityDSig::ENVELOPED;
    public const string ID_FIRMA_DEFECTO = 'SignatureKG';

    /**
     * Firma una cadena XML signable UBL 2.1 y verifica su integridad matemática.
     *
     * @param string $xmlSignable Cadena XML producida por PreparadorFirmaUbl.
     * @param ProveedorMaterialCriptograficoInterfaz $proveedor Proveedor del certificado y clave privada.
     * @return string Cadena XML firmada y validada.
     *
     * @throws FirmaCpeExcepcion Si ocurre algún error de validación, firma o verificación.
     */
    public function firmar(string $xmlSignable, ProveedorMaterialCriptograficoInterfaz $proveedor): string
    {
        $this->validarSeguridadXml($xmlSignable);

        $dom = $this->cargarDomSeguro($xmlSignable);

        $this->firmarDom($dom, $proveedor);

        $xmlFirmado = $dom->saveXML();
        if ($xmlFirmado === false) {
            throw new FirmaCpeExcepcion('No se pudo serializar el documento XML firmado a cadena.');
        }

        return $xmlFirmado;
    }

    /**
     * Firma una instancia DOMDocument signable UBL 2.1 en memoria.
     *
     * @param DOMDocument $dom Documento DOM signable conteniendo cac:Signature.
     * @param ProveedorMaterialCriptograficoInterfaz $proveedor Proveedor del certificado y clave privada.
     * @return DOMDocument Documento DOM firmado conteniendo ds:Signature validada.
     *
     * @throws FirmaCpeExcepcion Si ocurre algún error de validación, firma o verificación.
     */
    public function firmarDom(DOMDocument $dom, ProveedorMaterialCriptograficoInterfaz $proveedor): DOMDocument
    {
        // 1. Validar que no exista ya una firma incrustada (fail-fast)
        $this->validarAusenciaFirmaPrevia($dom);

        // 2. Validar estructura cac:Signature y extraer el ID de firma esperado
        $idFirmaEsperado = $this->validarYExtraerIdFirmaCac($dom);

        // 3. Localizar contenedor ext:ExtensionContent
        $extContentNode = $this->localizarExtensionContent($dom);

        // 4. Obtener material criptográfico
        $material = $proveedor->obtenerMaterial();

        try {
            // 5. Configurar objeto de firma con prefijo 'ds' y sin modo legacy
            $dsig = new XMLSecurityDSig('ds');
            $dsig->setSignatureId($idFirmaEsperado);
            $dsig->setCanonicalMethod(self::ALGORITMO_CANONICALIZACION);

            // Agregar referencia al documento completo con transformación enveloped
            $dsig->addReference(
                $dom,
                self::ALGORITMO_DIGEST,
                [self::ALGORITMO_TRANSFORM],
                ['force_uri' => true] // Garantiza <ds:Reference URI="">
            );

            // 6. Cargar clave privada RSA para la firma
            $clavePrivada = new XMLSecurityKey(self::ALGORITMO_FIRMA, ['type' => 'private']);
            $clavePrivada->loadKey($material->obtenerClavePrivadaPem(), false, false);

            // 7. Ejecutar firma e incrustar dentro de ext:ExtensionContent
            $dsig->sign($clavePrivada, $extContentNode);

            // 8. Incrustar certificado público X.509 en KeyInfo
            $dsig->add509Cert($material->obtenerCertificadoX509Pem(), true, false);

            // 9. POST-SIGN VERIFICATION: Verificar matemáticamente la firma recién calculada
            $this->ejecutarPostVerificacion($dom, $material, $idFirmaEsperado);

        } catch (FirmaCpeExcepcion $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FirmaCpeExcepcion(
                'Fallo durante el proceso criptográfico de firma XMLDSig: ' . $e->getMessage(),
                422,
                $e
            );
        }

        return $dom;
    }

    /**
     * Valida sintácticamente la ausencia de inyecciones DOCTYPE y ENTITY (XXE).
     */
    private function validarSeguridadXml(string $xml): void
    {
        if (str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!doctype')) {
            throw new XmlMalformadoCpeExcepcion(
                'El documento XML contiene una declaración DOCTYPE prohibida (riesgo de inyección XXE).'
            );
        }

        if (str_contains($xml, '<!ENTITY') || str_contains($xml, '<!entity')) {
            throw new XmlMalformadoCpeExcepcion(
                'El documento XML contiene una declaración ENTITY prohibida (riesgo de inyección XXE).'
            );
        }
    }

    /**
     * Carga de forma segura un XML en un DOMDocument aplicando LIBXML_NONET.
     */
    private function cargarDomSeguro(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $cargado = $dom->loadXML($xml, LIBXML_NONET);
        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if (!$cargado || !empty($errores)) {
            $detalle = !empty($errores) ? trim($errores[0]->message) : 'Error sintáctico desconocido';
            throw new XmlMalformadoCpeExcepcion(
                "El XML provisto es sintácticamente inválido y no pudo ser parseado: {$detalle}"
            );
        }

        return $dom;
    }

    /**
     * Valida que no exista ya un elemento ds:Signature en el documento.
     */
    private function validarAusenciaFirmaPrevia(DOMDocument $dom): void
    {
        $firmas = $dom->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature');
        if ($firmas->length > 0) {
            throw new FirmaCpeExcepcion(
                'El documento XML ya contiene una firma digital ds:Signature incrustada (idempotencia defensiva).',
                409
            );
        }
    }

    /**
     * Valida la presencia de cac:Signature y extrae el identificador requerido para ds:Signature/@Id.
     */
    private function validarYExtraerIdFirmaCac(DOMDocument $dom): string
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', ConstantesUbl::XMLNS_CAC);
        $xpath->registerNamespace('cbc', ConstantesUbl::XMLNS_CBC);

        $cacSignatureNodes = $xpath->query('//cac:Signature');
        if ($cacSignatureNodes === false || $cacSignatureNodes->length === 0) {
            throw new PreparacionFirmaExcepcion(
                'El documento XML no contiene la estructura descriptiva cac:Signature requerida antes de la firma.'
            );
        }

        $uriNodes = $xpath->query('//cac:Signature/cac:DigitalSignatureAttachment/cac:ExternalReference/cbc:URI');
        if ($uriNodes === false || $uriNodes->length === 0) {
            throw new CrossLinkFirmaInvalidoExcepcion(
                'El bloque cac:Signature no contiene el elemento cbc:URI de referencia a la firma digital.'
            );
        }

        $uriValor = trim($uriNodes->item(0)->textContent ?? '');
        if ($uriValor === '' || !str_starts_with($uriValor, '#')) {
            throw new CrossLinkFirmaInvalidoExcepcion(
                "El cbc:URI de referencia en cac:Signature debe comenzar con '#' y hacer referencia a un Id. Valor: '{$uriValor}'."
            );
        }

        $idEsperado = substr($uriValor, 1);
        if ($idEsperado === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_\-\.]*$/', $idEsperado)) {
            throw new CrossLinkFirmaInvalidoExcepcion(
                "El identificador de firma '{$idEsperado}' derivado de cbc:URI no es un NCName XML válido."
            );
        }

        return $idEsperado;
    }

    /**
     * Localiza el nodo ext:ExtensionContent donde debe incrustarse la firma.
     */
    private function localizarExtensionContent(DOMDocument $dom): DOMElement
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ext', ConstantesUbl::XMLNS_EXT);

        $nodos = $xpath->query('//ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent');
        if ($nodos === false || $nodos->length === 0) {
            throw new XmlMalformadoCpeExcepcion(
                'No se localizó el contenedor ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent en el comprobante.'
            );
        }

        $nodo = $nodos->item(0);
        if (!$nodo instanceof DOMElement) {
            throw new XmlMalformadoCpeExcepcion(
                'El contenedor ext:ExtensionContent no es un elemento DOM válido.'
            );
        }

        return $nodo;
    }

    /**
     * Ejecuta la post-verificación obligatoria de la firma recién calculada.
     */
    private function ejecutarPostVerificacion(DOMDocument $dom, MaterialCriptograficoCpe $material, string $idFirmaEsperado): void
    {
        // 1. Verificar coincidencia del Id de la firma generada
        $firmas = $dom->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Signature');
        if ($firmas->length !== 1) {
            throw new VerificacionFirmaFallidaExcepcion(
                "Se esperaba exactamente un elemento ds:Signature tras el firmado; encontrados: {$firmas->length}."
            );
        }

        $sigNode = $firmas->item(0);
        if (!$sigNode instanceof DOMElement || $sigNode->getAttribute('Id') !== $idFirmaEsperado) {
            $idGenerado = $sigNode instanceof DOMElement ? $sigNode->getAttribute('Id') : '';
            throw new CrossLinkFirmaInvalidoExcepcion(
                "El identificador generado ds:Signature/@Id='{$idGenerado}' no coincide con el cross-link '#{$idFirmaEsperado}'."
            );
        }

        // 2. Verificar correspondencia del URI en Reference (debe ser estrictamente "")
        $references = $sigNode->getElementsByTagNameNS(XMLSecurityDSig::XMLDSIGNS, 'Reference');
        if ($references->length !== 1) {
            throw new VerificacionFirmaFallidaExcepcion(
                "Se esperaba exactamente un elemento ds:Reference; encontrados: {$references->length}."
            );
        }

        $refNode = $references->item(0);
        if (!$refNode instanceof DOMElement || $refNode->getAttribute('URI') !== '') {
            $uriGen = $refNode instanceof DOMElement ? $refNode->getAttribute('URI') : '';
            throw new VerificacionFirmaFallidaExcepcion(
                "El atributo URI de ds:Reference debe ser exactamente vacío (''). Encontrado: '{$uriGen}'."
            );
        }

        // 3. Ejecutar post-verificación matemática safe-by-default con verifyDocument()
        $clavePublica = new XMLSecurityKey(self::ALGORITMO_FIRMA, ['type' => 'public']);
        $clavePublica->loadKey($material->obtenerCertificadoX509Pem(), false, true);

        $verificador = new XMLSecurityDSig();
        // Nota vinculante: Legacy Mode permanece estrictamente desactivado (forbidDoctype = true por defecto)

        try {
            $nodosValidados = $verificador->verifyDocument($clavePublica, $dom);
            if (empty($nodosValidados)) {
                throw new VerificacionFirmaFallidaExcepcion(
                    'La firma digital fue generada pero verifyDocument() no validó ningún nodo.'
                );
            }
        } catch (Throwable $e) {
            throw new VerificacionFirmaFallidaExcepcion(
                'La firma digital recién generada no superó la post-verificación matemática: ' . $e->getMessage(),
                422,
                $e
            );
        }
    }
}
