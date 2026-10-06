<?php

declare(strict_types=1);

namespace CamargoPMS\Tests\Soporte;

use CamargoPMS\Servicios\CPE\Firma\MaterialCriptograficoCpe;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;

/**
 * Utilidad de soporte exclusiva para el entorno de pruebas automatizadas.
 *
 * Genera de forma efímera en memoria pares de claves RSA (2048 bits) y certificados X.509
 * sintéticos autofirmados utilizando phpseclib3.
 *
 * ADVERTENCIA VINCULANTE:
 * - El material generado es criptográficamente válido para validar algoritmos W3C XMLDSig.
 * - Este material NO es un certificado emitido por una entidad acreditada ante INDECOPI ni
 *   es válido ante los servicios en producción de SUNAT.
 * - Cero almacenamiento o persistencia de archivos de claves (.pem, .key, .pfx) en Git o disco.
 */
final class GeneradorCertificadoSinteticoTest
{
    /**
     * Genera un par de clave RSA y certificado X.509 sintético válido en memoria.
     *
     * @param string $ruc RUC del firmante para el CommonName o serialNumber del certificado.
     * @param string $razonSocial Razón social del firmante para el OrganizationName.
     * @param int $diasValidez Días de validez del certificado (positivo para válido, negativo para vencido).
     * @return MaterialCriptograficoCpe
     */
    public static function generar(
        string $ruc = '20609998881',
        string $razonSocial = 'CAMARGO HOSTELERIA S.A.C.',
        int $diasValidez = 365
    ): MaterialCriptograficoCpe {
        $rsaKey = RSA::createKey(2048)->withPadding(RSA::SIGNATURE_PKCS1);
        $privPem = $rsaKey->toString('PKCS1');
        $pubKey = $rsaKey->getPublicKey();

        $subject = new X509();
        $subject->setPublicKey($pubKey);
        $subject->setDNProp('id-at-countryName', 'PE');
        $subject->setDNProp('id-at-organizationName', $razonSocial);
        $subject->setDNProp('id-at-commonName', $razonSocial . ' - TEST');
        $subject->setDNProp('id-at-serialNumber', 'RUC' . $ruc);

        $issuer = new X509();
        $issuer->setPrivateKey($rsaKey);
        $issuer->setDNProp('id-at-countryName', 'PE');
        $issuer->setDNProp('id-at-organizationName', $razonSocial);
        $issuer->setDNProp('id-at-commonName', $razonSocial . ' - TEST');
        $issuer->setDNProp('id-at-serialNumber', 'RUC' . $ruc);

        if ($diasValidez >= 0) {
            $issuer->setStartDate('-1 day');
            $issuer->setEndDate("+{$diasValidez} day");
        } else {
            // Certificado vencido
            $issuer->setStartDate('-60 day');
            $issuer->setEndDate('-1 day');
        }

        $newCert = $issuer->sign($issuer, $subject, 'sha256WithRSAEncryption');
        $certPem = $issuer->saveX509($newCert);

        return new MaterialCriptograficoCpe($certPem, $privPem);
    }

    /**
     * Genera un par no coincidente: clave privada A y certificado de clave B.
     *
     * @return array{certPem: string, privPemMismatch: string}
     */
    public static function generarParDispar(): array
    {
        $materialA = self::generar('20609998881', 'EMPRESA A');
        $materialB = self::generar('20609998882', 'EMPRESA B');

        return [
            'certPem' => $materialA->obtenerCertificadoX509Pem(),
            'privPemMismatch' => $materialB->obtenerClavePrivadaPem(),
        ];
    }
}
