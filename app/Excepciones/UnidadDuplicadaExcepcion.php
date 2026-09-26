<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando se intenta registrar o actualizar una unidad
 * con un código que ya existe dentro de la misma propiedad.
 */
class UnidadDuplicadaExcepcion extends Exception
{
    private string $codigo;
    private int $propiedadId;

    public function __construct(string $codigo, int $propiedadId, string $mensaje = '', int $codigoHttp = 409, ?Throwable $anterior = null)
    {
        $this->codigo = $codigo;
        $this->propiedadId = $propiedadId;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "Ya existe una unidad con el código '{$codigo}' en la propiedad ID {$propiedadId}.";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerCodigoDuplicado(): string
    {
        return $this->codigo;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }
}
