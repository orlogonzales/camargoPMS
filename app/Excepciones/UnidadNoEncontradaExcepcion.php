<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando una unidad no existe o no pudo ser localizada en el sistema.
 */
class UnidadNoEncontradaExcepcion extends Exception
{
    public function __construct(string $mensaje = 'No se encontró la unidad especificada.', int $codigoHttp = 404, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigoHttp, $anterior);
    }
}
