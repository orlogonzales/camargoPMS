<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class SuministroNoEncontradoExcepcion extends RuntimeException
{
    protected $code = 404;
}
