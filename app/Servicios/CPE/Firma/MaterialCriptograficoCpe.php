<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\Firma;

use CamargoPMS\Excepciones\CertificadoInvalidoExcepcion;
use CamargoPMS\Excepciones\ClavePrivadaInvalidaExcepcion;
use JsonSerializable;

/**
 * Value Object inmutable que transporta el material criptográfico (Certificado X.509 y Clave Privada)
 * de forma segura en memoria para el proceso de firma digital XMLDSig.
 *
 * Principios de seguridad aplicados:
 * - La clave privada nunca se expone en __debugInfo(), var_dump ni serialización JSON.
 * - Validación fail-closed de coincidencia matemática entre la clave privada y el certificado público.
 * - Validación estricta de estructura PEM.
 */
final class MaterialCriptograficoCpe implements JsonSerializable
{
    private string $certificadoX509Pem;
    private string $clavePrivadaPem;

    /**
     * @param string $certificadoX509Pem Certificado público X.509 en formato PEM.
     * @param string $clavePrivadaPem Clave privada RSA en formato PEM.
     * @param string $passphrase Contraseña opcional para descifrar la clave privada si está encriptada.
     *
     * @throws CertificadoInvalidoExcepcion Si el certificado está mal formado o no es parseable.
     * @throws ClavePrivadaInvalidaExcepcion Si la clave privada es inválida o no corresponde al certificado.
     */
    public function __construct(
        string $certificadoX509Pem,
        string $clavePrivadaPem,
        string $passphrase = ''
    ) {
        $certTrim = trim($certificadoX509Pem);
        if (!str_contains($certTrim, '-----BEGIN CERTIFICATE-----') || !str_contains($certTrim, '-----END CERTIFICATE-----')) {
            throw new CertificadoInvalidoExcepcion(
                'El certificado X.509 provisto no tiene una estructura PEM válida.'
            );
        }

        $keyTrim = trim($clavePrivadaPem);
        if (!str_contains($keyTrim, '-----BEGIN') || !str_contains($keyTrim, 'PRIVATE KEY-----')) {
            throw new ClavePrivadaInvalidaExcepcion(
                'La clave privada provista no tiene una estructura PEM válida.'
            );
        }

        // Validar validez sintáctica del certificado con OpenSSL
        $certParsed = @openssl_x509_read($certTrim);
        if ($certParsed === false) {
            throw new CertificadoInvalidoExcepcion(
                'OpenSSL no pudo interpretar el certificado X.509 provisto: formato corrupto o inválido.'
            );
        }

        // Validar clave privada y coincidencia con el certificado
        $privKeyObj = @openssl_pkey_get_private($keyTrim, $passphrase);
        if ($privKeyObj === false) {
            throw new ClavePrivadaInvalidaExcepcion(
                'OpenSSL no pudo abrir la clave privada provista (clave corrupta o contraseña incorrecta).'
            );
        }

        if (!@openssl_x509_check_private_key($certParsed, $privKeyObj)) {
            throw new ClavePrivadaInvalidaExcepcion(
                'La clave privada provista no se corresponde matemáticamente con el certificado público X.509.'
            );
        }

        $this->certificadoX509Pem = $certTrim;
        $this->clavePrivadaPem = $keyTrim;
    }

    /**
     * Retorna el certificado X.509 en formato PEM.
     */
    public function obtenerCertificadoX509Pem(): string
    {
        return $this->certificadoX509Pem;
    }

    /**
     * Retorna la clave privada RSA en formato PEM.
     *
     * Este método solo debe ser invocado por el firmador criptográfico en el instante de la firma.
     */
    public function obtenerClavePrivadaPem(): string
    {
        return $this->clavePrivadaPem;
    }

    /**
     * Extrae y retorna la clave pública RSA en formato PEM a partir del certificado.
     */
    public function obtenerClavePublicaPem(): string
    {
        $certParsed = openssl_x509_read($this->certificadoX509Pem);
        if ($certParsed === false) {
            throw new CertificadoInvalidoExcepcion('No se pudo extraer la clave pública del certificado.');
        }

        $pubKeyObj = openssl_pkey_get_public($certParsed);
        if ($pubKeyObj === false) {
            throw new CertificadoInvalidoExcepcion('No se pudo obtener el recurso de clave pública del certificado.');
        }

        $keyDetails = openssl_pkey_get_details($pubKeyObj);
        if ($keyDetails === false || !isset($keyDetails['key'])) {
            throw new CertificadoInvalidoExcepcion('Detalles de clave pública no disponibles en OpenSSL.');
        }

        return $keyDetails['key'];
    }

    /**
     * Oculta rigurosamente la clave privada en volcados de depuración.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'certificadoX509Pem' => substr($this->certificadoX509Pem, 0, 40) . '... [PROTECTED]',
            'clavePrivadaPem' => '[PROTECTED_PRIVATE_KEY]',
        ];
    }

    /**
     * Impide serializar la clave privada a JSON.
     *
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'certificadoX509Pem' => $this->certificadoX509Pem,
            'clavePrivadaPem' => '[PROTECTED_PRIVATE_KEY]',
        ];
    }
}
