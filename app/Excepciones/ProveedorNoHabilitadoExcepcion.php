<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando un proveedor asignado no se encuentra habilitado
 * o no cumple las condiciones operativas de prestación.
 */
class ProveedorNoHabilitadoExcepcion extends Exception
{
    public function __construct(string $mensaje = "El proveedor asignado no está activo o no se encuentra habilitado para este servicio.", int $codigoHttp = 422, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigoHttp, $anterior);
    }
}
