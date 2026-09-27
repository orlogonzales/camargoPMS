<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;

class OrdenTrabajoNoEncontradaExcepcion extends Exception
{
    public function __construct(string $mensaje = 'La orden de trabajo solicitada no fue encontrada.', int $codigo = 404)
    {
        parent::__construct($mensaje, $codigo);
    }
}
