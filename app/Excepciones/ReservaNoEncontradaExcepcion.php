<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando no se localiza un registro de reserva por su ID o código comercial.
 */
class ReservaNoEncontradaExcepcion extends Exception
{
    private int|string $identificador;

    public function __construct(int|string $identificador, string $mensaje = '', int $codigoHttp = 404, ?Throwable $anterior = null)
    {
        $this->identificador = $identificador;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "No se encontró la reserva con identificador '{$identificador}'.";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerIdentificador(): int|string
    {
        return $this->identificador;
    }
}
