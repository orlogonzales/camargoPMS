<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ValidacionCompraExcepcion extends RuntimeException
{
    protected $code = 422;
}
