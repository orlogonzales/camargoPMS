<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando ocurre un conflicto de concurrencia, bloqueo (1205) o deadlock (1213) en inventario.
 */
class ConflictoInventarioExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'Conflicto de concurrencia al procesar existencias de inventario.', ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, 409, $anterior);
    }
}
