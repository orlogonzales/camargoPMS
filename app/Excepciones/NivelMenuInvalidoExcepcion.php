<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use InvalidArgumentException;

/**
 * Excepción lanzada cuando una operación viola la estructura jerárquica estricta de dos niveles
 * (ej. anidar una opción bajo otra opción secundaria, o autorreferencia).
 */
class NivelMenuInvalidoExcepcion extends InvalidArgumentException
{
    public function __construct(string $mensaje = 'La jerarquía de menú excede los dos niveles permitidos o es inválida.')
    {
        parent::__construct($mensaje, 422);
    }
}
