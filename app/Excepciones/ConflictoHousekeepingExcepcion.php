<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ConflictoHousekeepingExcepcion extends RuntimeException
{
    protected $code = 409;
}
