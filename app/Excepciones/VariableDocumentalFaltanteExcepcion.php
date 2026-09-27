<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una variable requerida para emitir el documento no está disponible o está vacía (D-079 #11).
 */
class VariableDocumentalFaltanteExcepcion extends RuntimeException
{
    private string $variable;
    private string $origenTipo;
    private int $origenId;

    public function __construct(string $variable, string $origenTipo = '', int $origenId = 0, string $mensaje = '')
    {
        $this->variable = $variable;
        $this->origenTipo = $origenTipo;
        $this->origenId = $origenId;

        $msg = $mensaje !== ''
            ? $mensaje
            : "La variable requerida [{{{$variable}}}] no cuenta con un valor válido en la entidad origen [{$origenTipo} #{$origenId}].";

        parent::__construct($msg, 422);
    }

    public function obtenerVariable(): string
    {
        return $this->variable;
    }

    public function obtenerOrigenTipo(): string
    {
        return $this->origenTipo;
    }

    public function obtenerOrigenId(): int
    {
        return $this->origenId;
    }
}
