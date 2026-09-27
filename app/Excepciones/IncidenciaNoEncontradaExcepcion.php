<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;

class IncidenciaNoEncontradaExcepcion extends Exception
{
    public function __construct(string $mensaje = 'La incidencia técnica solicitada no fue encontrada.', int $codigo = 404)
    {
        parent::__construct($mensaje, $codigo);
    }
}
