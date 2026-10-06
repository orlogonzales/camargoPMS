<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE\DTO;

use InvalidArgumentException;

/**
 * Value Object inmutable que encapsula el contexto descriptivo y metadatos no sensibles
 * requeridos para la preparación estructural del bloque cac:Signature en UBL 2.1.
 *
 * No almacena ni procesa credenciales ni llaves criptográficas.
 */
final readonly class ContextoFirmaCpe
{
    public const string ID_FIRMA_DEFECTO = 'SignatureKG';
    public const string ID_DESCRIPTIVO_DEFECTO = 'IDSignKG';

    public function __construct(
        private string $rucFirmante,
        private string $razonSocialFirmante,
        private string $identificadorFirma = self::ID_FIRMA_DEFECTO,
        private string $identificadorDescriptivo = self::ID_DESCRIPTIVO_DEFECTO
    ) {
        $rucTrim = trim($this->rucFirmante);
        if (!preg_match('/^[0-9]{11}$/', $rucTrim)) {
            throw new InvalidArgumentException(
                "El RUC del firmante debe contener exactamente 11 dígitos numéricos. Proporcionado: '{$this->rucFirmante}'."
            );
        }

        if (trim($this->razonSocialFirmante) === '') {
            throw new InvalidArgumentException(
                'La razón social del firmante no puede estar vacía.'
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\-\.]*$/', $this->identificadorFirma)) {
            throw new InvalidArgumentException(
                "El identificador de firma '{$this->identificadorFirma}' no es un NCName XML válido."
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\-\.]*$/', $this->identificadorDescriptivo)) {
            throw new InvalidArgumentException(
                "El identificador descriptivo '{$this->identificadorDescriptivo}' no es un NCName XML válido."
            );
        }
    }

    public function obtenerRucFirmante(): string
    {
        return $this->rucFirmante;
    }

    public function obtenerRazonSocialFirmante(): string
    {
        return $this->razonSocialFirmante;
    }

    public function obtenerIdentificadorFirma(): string
    {
        return $this->identificadorFirma;
    }

    public function obtenerIdentificadorDescriptivo(): string
    {
        return $this->identificadorDescriptivo;
    }

    /**
     * Retorna el URI canónico para cac:ExternalReference/cbc:URI con el prefijo '#'.
     */
    public function obtenerUriFirma(): string
    {
        return '#' . $this->identificadorFirma;
    }
}
