<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ConflictoTarifaExcepcion extends RuntimeException
{
    protected $code = 409;
}
