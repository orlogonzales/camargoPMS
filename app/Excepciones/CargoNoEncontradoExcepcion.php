<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza un cargo en cuenta.
 */
class CargoNoEncontradoExcepcion extends RuntimeException
{
    public function __construct(string $identificador = '')
    {
        $mensaje = $identificador !== ''
            ? "No se encontró el cargo en cuenta con identificador [{$identificador}]."
            : 'No se encontró el cargo en cuenta solicitado.';
        parent::__construct($mensaje, 404);
    }
}
