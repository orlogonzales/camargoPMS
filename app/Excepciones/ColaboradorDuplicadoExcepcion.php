<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando se intenta crear un registro de Colaborador para una Persona
 * que ya cuenta con un registro laboral en el sistema.
 */
class ColaboradorDuplicadoExcepcion extends DomainException
{
    private int $personaId;

    public function __construct(int $personaId, string $mensaje = '', int $codigo = 409)
    {
        $this->personaId = $personaId;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "La persona con ID {$personaId} ya está registrada como colaborador en Camargo PMS.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }
}
