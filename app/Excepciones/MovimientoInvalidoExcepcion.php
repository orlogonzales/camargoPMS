<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una operación o movimiento de inventario es inválido según las reglas de negocio.
 */
class MovimientoInvalidoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'La operación de inventario solicitada es inválida.')
    {
        parent::__construct($mensaje, 422);
    }
}
