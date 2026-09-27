<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando el arqueo o cierre de caja no cumple con los requisitos obligatorios.
 */
class ArqueoCajaInvalidoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje, 422);
    }
}
