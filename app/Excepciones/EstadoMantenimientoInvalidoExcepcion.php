<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

class EstadoMantenimientoInvalidoExcepcion extends Exception
{
    private string $entidadTipo;
    private string $estadoActual;
    private string $operacionIntentada;

    public function __construct(
        string $entidadTipo,
        string $estadoActual,
        string $operacionIntentada,
        string $mensaje = '',
        int $codigo = 422,
        ?Throwable $anterior = null
    ) {
        $this->entidadTipo = $entidadTipo;
        $this->estadoActual = $estadoActual;
        $this->operacionIntentada = $operacionIntentada;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "No es posible realizar la operación '{$operacionIntentada}' sobre una entidad '{$entidadTipo}' en estado '{$estadoActual}'.";

        parent::__construct($mensajeFinal, $codigo, $anterior);
    }

    public function obtenerEntidadTipo(): string { return $this->entidadTipo; }
    public function obtenerEstadoActual(): string { return $this->estadoActual; }
    public function obtenerOperacionIntentada(): string { return $this->operacionIntentada; }
}
