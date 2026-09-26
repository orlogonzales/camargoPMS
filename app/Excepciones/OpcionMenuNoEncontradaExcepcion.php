<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una opción de menú solicitada no existe.
 */
class OpcionMenuNoEncontradaExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'La opción de menú solicitada no existe.')
    {
        parent::__construct($mensaje, 404);
    }
}
