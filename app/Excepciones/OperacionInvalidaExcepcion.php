<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una operación solicitada es incompatible con el estado de dominio actual.
 */
class OperacionInvalidaExcepcion extends RuntimeException
{
    protected $code = 409;
}
