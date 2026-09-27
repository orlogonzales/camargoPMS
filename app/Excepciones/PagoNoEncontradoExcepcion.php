<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza un pago en cuenta.
 */
class PagoNoEncontradoExcepcion extends RuntimeException
{
    public function __construct(string $identificador = '')
    {
        $mensaje = $identificador !== ''
            ? "No se encontró el pago en cuenta con identificador [{$identificador}]."
            : 'No se encontró el pago en cuenta solicitado.';
        parent::__construct($mensaje, 404);
    }
}
