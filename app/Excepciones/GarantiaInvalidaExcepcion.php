<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

class GarantiaInvalidaExcepcion extends Exception
{
    public function __construct(string $mensaje = 'Operación de custodia de garantía inválida.', int $codigo = 422, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigo, $anterior);
    }
}
