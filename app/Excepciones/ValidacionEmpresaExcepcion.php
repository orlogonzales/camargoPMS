<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ValidacionEmpresaExcepcion extends RuntimeException
{
    protected $code = 422;
}
