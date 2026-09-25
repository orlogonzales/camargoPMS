<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando los rangos de fechas de episodios laborales o asignaciones de cargo
 * entran en solapamiento o conflicto de continuidad temporal.
 */
class SolapamientoLaboralExcepcion extends DomainException
{
    private string $fechaInicio;
    private ?string $fechaFin;

    public function __construct(string $fechaInicio, ?string $fechaFin = null, string $mensaje = '', int $codigo = 422)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $rango = $fechaFin !== null ? "{$fechaInicio} al {$fechaFin}" : "desde {$fechaInicio}";
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "El período indicado ({$rango}) entra en solapamiento con un registro laboral preexistente.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->fechaFin;
    }
}
