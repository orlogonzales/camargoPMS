<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ConflictoEmpresaExcepcion extends RuntimeException
{
    protected $code = 409;
}
