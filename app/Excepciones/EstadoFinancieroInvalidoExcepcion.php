<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una operación financiera es incompatible con el estado de la entidad.
 */
class EstadoFinancieroInvalidoExcepcion extends RuntimeException
{
    public function __construct(string $entidad, string $estadoActual, string $operacionIntentada)
    {
        parent::__construct(
            "No se puede realizar la operación [{$operacionIntentada}] sobre [{$entidad}] porque su estado actual es [{$estadoActual}].",
            422
        );
    }
}
