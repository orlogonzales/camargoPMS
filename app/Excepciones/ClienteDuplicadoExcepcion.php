<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando una Persona ya cuenta con un perfil de Cliente comercial (relación 1:1 estricta).
 */
class ClienteDuplicadoExcepcion extends DomainException
{
    private int $personaId;

    public function __construct(int $personaId, string $mensaje = '', int $codigo = 409)
    {
        $this->personaId = $personaId;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "La persona con ID {$personaId} ya cuenta con un perfil comercial de cliente registrado.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }
}
