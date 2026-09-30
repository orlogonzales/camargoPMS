<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class CredencialApiInvalidaExcepcion extends RuntimeException
{
    protected $code = 401;
}
