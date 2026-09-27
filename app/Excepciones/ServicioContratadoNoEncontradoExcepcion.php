<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando no se localiza un servicio contratado o consumo imputado.
 */
class ServicioContratadoNoEncontradoExcepcion extends Exception
{
    public function __construct(string $mensaje = "El servicio contratado o consumo imputado no fue encontrado.", int $codigoHttp = 404, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigoHttp, $anterior);
    }
}
