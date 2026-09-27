<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

class ArrendamientoNoEncontradoExcepcion extends Exception
{
    public function __construct(string $mensaje = 'El contrato de arrendamiento solicitado no existe.', int $codigo = 404, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigo, $anterior);
    }
}
