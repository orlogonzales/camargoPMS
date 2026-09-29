<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando se intenta una transición de estado inválida en una reclamación.
 */
class TransicionReclamacionInvalidaExcepcion extends Exception
{
    private string $estadoActual;
    private string $operacionIntentada;

    public function __construct(string $estadoActual, string $operacionIntentada, string $mensaje = '', int $codigoHttp = 409, ?Throwable $anterior = null)
    {
        $this->estadoActual = $estadoActual;
        $this->operacionIntentada = $operacionIntentada;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "No es posible ejecutar la operación '{$operacionIntentada}' sobre una reclamación en estado '{$estadoActual}'.";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
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
