<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción de dominio lanzada cuando un rango de fechas hotelero viola las
 * reglas temporales definidas en D-066 (HTTP 422 Unprocessable Entity).
 *
 * Reglas vinculantes:
 * - Formato estricto Y-m-d (DATE local hotelero).
 * - Intervalo semiabierto [fecha_entrada, fecha_salida).
 * - Restricción: fecha_salida > fecha_entrada (noches >= 1).
 */
class IntervaloInvalidoExcepcion extends Exception
{
    private string $fechaInicio;
    private string $fechaFin;

    public function __construct(
        string $fechaInicio,
        string $fechaFin,
        string $mensaje = '',
        int $codigoHttp = 422,
        ?Throwable $anterior = null
    ) {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "El intervalo de fechas [{$fechaInicio}, {$fechaFin}) es inválido. La fecha de salida debe ser posterior a la fecha de entrada (mínimo 1 noche).";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): string
    {
        return $this->fechaFin;
    }
}
