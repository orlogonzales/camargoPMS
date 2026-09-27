<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando se intenta cobrar en efectivo sin una sesión de caja física abierta.
 */
class CajaNoAbiertaExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'No existe una sesión de caja física abierta para el cajero receptor. Toda operación en efectivo exige un turno abierto.')
    {
        parent::__construct($mensaje, 422);
    }
}
