<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza una ubicación o almacén de inventario.
 */
class UbicacionNoEncontradaExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'La ubicación de inventario solicitada no existe.')
    {
        parent::__construct($mensaje, 404);
    }
}
