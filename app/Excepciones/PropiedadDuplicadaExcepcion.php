<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando ya existe una propiedad con el mismo código técnico.
 */
class PropiedadDuplicadaExcepcion extends DomainException
{
    private string $campo;
    private string $valor;

    public function __construct(string $campoOValor, ?string $valor = null, string $mensaje = '', int $codigo = 409)
    {
        if ($valor === null) {
            $this->campo = 'código';
            $this->valor = $campoOValor;
        } else {
            $this->campo = $campoOValor;
            $this->valor = $valor;
        }

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "Ya existe una propiedad registrada con el {$this->campo} '{$this->valor}'.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerCampo(): string
    {
        return $this->campo;
    }

    public function obtenerValor(): string
    {
        return $this->valor;
    }
}
