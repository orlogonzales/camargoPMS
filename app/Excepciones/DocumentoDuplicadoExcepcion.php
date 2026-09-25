<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando se intenta registrar un documento ya existente para otra persona.
 */
class DocumentoDuplicadoExcepcion extends DomainException
{
    private string $tipoDocumento;
    private string $numeroDocumento;

    public function __construct(string $tipoDocumento, string $numeroDocumento, string $mensaje = '', int $codigo = 409)
    {
        $this->tipoDocumento = $tipoDocumento;
        $this->numeroDocumento = $numeroDocumento;

        $mensajeFinal = $mensaje !== '' 
            ? $mensaje 
            : "El documento {$tipoDocumento} con número {$numeroDocumento} ya se encuentra registrado en el sistema.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerTipoDocumento(): string
    {
        return $this->tipoDocumento;
    }

    public function obtenerNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }
}
