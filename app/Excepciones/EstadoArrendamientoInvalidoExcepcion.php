<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

class EstadoArrendamientoInvalidoExcepcion extends Exception
{
    private string $estadoActual;
    private string $operacionIntentada;

    public function __construct(string $estadoActual, string $operacionIntentada, string $mensaje = '', int $codigo = 422, ?Throwable $anterior = null)
    {
        $this->estadoActual = $estadoActual;
        $this->operacionIntentada = $operacionIntentada;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "No es posible realizar la operación '{$operacionIntentada}' sobre un arrendamiento en estado '{$estadoActual}'.";

        parent::__construct($mensajeFinal, $codigo, $anterior);
    }

    public function obtenerEstadoActual(): string
    {
        return $this->estadoActual;
    }

    public function obtenerOperacionIntentada(): string
    {
        return $this->operacionIntentada;
    }
}
