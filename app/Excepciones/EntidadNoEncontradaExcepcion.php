<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una entidad solicitada no existe en la persistencia.
 */
class EntidadNoEncontradaExcepcion extends RuntimeException
{
    private string $entidad;
    private string|int $identificador;

    public function __construct(string $entidad, string|int $identificador, string $mensaje = '', int $codigo = 404)
    {
        $this->entidad = $entidad;
        $this->identificador = $identificador;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "No se encontró el registro de {$entidad} con identificador '{$identificador}'.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerEntidad(): string
    {
        return $this->entidad;
    }

    public function obtenerIdentificador(): string|int
    {
        return $this->identificador;
    }
}
