<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use InvalidArgumentException;

class ValidacionTarifaExcepcion extends InvalidArgumentException
{
    protected $code = 422;
}
