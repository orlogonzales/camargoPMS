<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción de dominio lanzada ante un conflicto de disponibilidad o concurrencia
 * en el inventario diario (D-067 / HTTP 409 Conflict).
 *
 * Se produce cuando se intenta bloquear u ocupar una unidad para una fecha
 * en la que ya existe una fila en `inventario_diario_unidades` (violación de
 * restricción UNIQUE) o cuando ocurre un conflicto de bloqueo/deadlock (1205/1213).
 */
class ConflictoDisponibilidadExcepcion extends Exception
{
    private int $unidadId;
    private ?string $fechaConflicto;

    public function __construct(
        int $unidadId,
        ?string $fechaConflicto = null,
        string $mensaje = '',
        int $codigoHttp = 409,
        ?Throwable $anterior = null
    ) {
        $this->unidadId = $unidadId;
        $this->fechaConflicto = $fechaConflicto;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : ($fechaConflicto !== null
                ? "La unidad ID {$unidadId} ya se encuentra ocupada o bloqueada para la fecha {$fechaConflicto}."
                : "Conflicto de disponibilidad en la unidad ID {$unidadId} para el rango solicitado.");

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerFechaConflicto(): ?string
    {
        return $this->fechaConflicto;
    }
}
