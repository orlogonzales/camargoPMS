<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class TarifaFaltanteExcepcion extends RuntimeException
{
    protected $code = 422;
}
