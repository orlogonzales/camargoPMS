<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando no se localiza un servicio en el catálogo maestro.
 */
class ServicioNoEncontradoExcepcion extends Exception
{
    public function __construct(string $mensaje = "El servicio solicitado no existe o ha sido deshabilitado.", int $codigoHttp = 404, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigoHttp, $anterior);
    }
}
