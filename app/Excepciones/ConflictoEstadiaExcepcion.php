<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando se intenta registrar una estadía para una unidad de reserva
 * que ya cuenta con un registro histórico de estadía (HTTP 409 Conflict).
 */
class ConflictoEstadiaExcepcion extends Exception
{
    private int $reservaUnidadId;

    public function __construct(int $reservaUnidadId, string $mensaje = '', int $codigoHttp = 409, ?Throwable $anterior = null)
    {
        $this->reservaUnidadId = $reservaUnidadId;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "La unidad de reserva '{$reservaUnidadId}' ya cuenta con una estadía registrada.";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerReservaUnidadId(): int
    {
        return $this->reservaUnidadId;
    }
}
