<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando se intenta registrar una opción de menú con una clave ya existente.
 */
class OpcionMenuDuplicadaExcepcion extends RuntimeException
{
    public function __construct(string $clave)
    {
        parent::__construct("Ya existe una opción de menú con la clave '{$clave}'.", 409);
    }
}
