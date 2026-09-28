<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class GastoNoEncontradoExcepcion extends RuntimeException
{
    protected $code = 404;
}
