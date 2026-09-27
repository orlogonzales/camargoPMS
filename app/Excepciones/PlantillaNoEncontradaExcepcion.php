<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una plantilla o versión no existe en la base de datos.
 */
class PlantillaNoEncontradaExcepcion extends RuntimeException
{
    public function __construct(string $identificador, string $mensaje = '')
    {
        $msg = $mensaje !== ''
            ? $mensaje
            : "La plantilla documental o versión [{$identificador}] no fue encontrada.";

        parent::__construct($msg, 404);
    }
}
