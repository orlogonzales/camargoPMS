<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando el número de huéspedes excede la capacidad máxima física de la unidad (HTTP 422).
 */
class CapacidadExcedidaExcepcion extends Exception
{
    private int $cantidadHuespedes;
    private int $capacidadMaxima;
    private int $unidadId;

    public function __construct(int $cantidadHuespedes, int $capacidadMaxima, int $unidadId, string $mensaje = '', int $codigoHttp = 422, ?Throwable $anterior = null)
    {
        $this->cantidadHuespedes = $cantidadHuespedes;
        $this->capacidadMaxima = $capacidadMaxima;
        $this->unidadId = $unidadId;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "La cantidad de huéspedes ({$cantidadHuespedes}) excede la capacidad máxima de la unidad ({$capacidadMaxima} personas).";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerCantidadHuespedes(): int
    {
        return $this->cantidadHuespedes;
    }

    public function obtenerCapacidadMaxima(): int
    {
        return $this->capacidadMaxima;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }
}
