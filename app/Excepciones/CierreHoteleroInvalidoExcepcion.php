<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada ante operaciones inválidas o transiciones conflictivas de Cierre Hotelero / Night Audit.
 */
class CierreHoteleroInvalidoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'Operación de cierre hotelero no válida o en estado conflictivo.')
    {
        parent::__construct($mensaje, 422);
    }
}
