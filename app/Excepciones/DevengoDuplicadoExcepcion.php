<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada ante colisión o intento de duplicar un devengo económico de alojamiento.
 */
class DevengoDuplicadoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'Ya existe un devengo de alojamiento activo para la estadía y fecha hotelera especificada.', ?\Throwable $previous = null)
    {
        parent::__construct($mensaje, 409, $previous);
    }
}
