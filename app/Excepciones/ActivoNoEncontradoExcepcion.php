<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza un activo serializable de inventario.
 */
class ActivoNoEncontradoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'El activo individual solicitado no existe.')
    {
        parent::__construct($mensaje, 404);
    }
}
