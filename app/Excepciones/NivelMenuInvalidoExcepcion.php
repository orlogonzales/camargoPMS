<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use InvalidArgumentException;

/**
 * Excepción lanzada cuando una operación viola la estructura jerárquica estricta de hasta tres niveles
 * (Dominio, Módulo, Función/Submódulo), autorreferencia o ciclos.
 */
class NivelMenuInvalidoExcepcion extends InvalidArgumentException
{
    public function __construct(string $mensaje = 'La jerarquía de menú excede los tres niveles permitidos o es inválida.')
    {
        parent::__construct($mensaje, 422);
    }
}
