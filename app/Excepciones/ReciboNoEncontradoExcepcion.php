<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ReciboNoEncontradoExcepcion extends RuntimeException
{
    protected $code = 404;
}
