<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Firma;

use CamargoPMS\Excepciones\FirmaCpeExcepcion;
use DOMDocument;

/**
 * Contrato principal para servicios de firma digital XMLDSig de Comprobantes de Pago Electrónicos (CPE).
 *
 * Exige cumplimiento estricto del pipeline:
 * SIGN -> VERIFY -> RETURN
 */
interface FirmadorCpeInterfaz
{
    /**
     * Firma un documento XML signable y verifica su validez matemática antes de retornarlo.
     *
     * @param string $xmlSignable Cadena XML generada por PreparadorFirmaUbl conteniendo cac:Signature.
     * @param ProveedorMaterialCriptograficoInterfaz $proveedor Proveedor del certificado y clave privada.
     * @return string Cadena XML firmada conteniendo ds:Signature validada.
     *
     * @throws FirmaCpeExcepcion Si ocurre algún error durante la preparación, firma o post-verificación.
     */
    public function firmar(string $xmlSignable, ProveedorMaterialCriptograficoInterfaz $proveedor): string;

    /**
     * Variante que opera directamente sobre una instancia DOMDocument en memoria.
     *
     * @param DOMDocument $dom Documento DOM signable conteniendo cac:Signature.
     * @param ProveedorMaterialCriptograficoInterfaz $proveedor Proveedor del certificado y clave privada.
     * @return DOMDocument Documento DOM firmado y validado.
     *
     * @throws FirmaCpeExcepcion Si ocurre algún error durante la preparación, firma o post-verificación.
     */
    public function firmarDom(DOMDocument $dom, ProveedorMaterialCriptograficoInterfaz $proveedor): DOMDocument;
}
