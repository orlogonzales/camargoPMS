<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una entrada o seguimiento de bitácora no es encontrado.
 */
class BitacoraNoEncontradaExcepcion extends RuntimeException
{
    protected $code = 404;
}
