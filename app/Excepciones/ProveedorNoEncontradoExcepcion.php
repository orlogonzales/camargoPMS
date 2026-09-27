<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando no se localiza un proveedor en el maestro de proveedores.
 */
class ProveedorNoEncontradoExcepcion extends Exception
{
    public function __construct(string $mensaje = "El proveedor solicitado no existe o no se encuentra registrado.", int $codigoHttp = 404, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigoHttp, $anterior);
    }
}
