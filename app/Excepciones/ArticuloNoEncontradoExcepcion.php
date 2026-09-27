<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza un artículo de inventario.
 */
class ArticuloNoEncontradoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'El artículo de inventario solicitado no existe.')
    {
        parent::__construct($mensaje, 404);
    }
}
