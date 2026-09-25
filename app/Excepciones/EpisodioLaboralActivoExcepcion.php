<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando una operación laboral entra en conflicto con el estado abierto/cerrado de un episodio.
 */
class EpisodioLaboralActivoExcepcion extends DomainException
{
    private int $colaboradorId;

    public function __construct(int $colaboradorId, string $mensaje = '', int $codigo = 409)
    {
        $this->colaboradorId = $colaboradorId;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "El colaborador con ID {$colaboradorId} ya cuenta con un episodio laboral abierto y activo.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerColaboradorId(): int
    {
        return $this->colaboradorId;
    }
}
