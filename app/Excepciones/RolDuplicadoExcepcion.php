<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando ya existe un rol con la misma clave o nombre.
 */
class RolDuplicadoExcepcion extends DomainException
{
    private string $campo;
    private string $valor;

    public function __construct(string $campo, string $valor, string $mensaje = '', int $codigo = 409)
    {
        $this->campo = $campo;
        $this->valor = $valor;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "Ya existe un rol registrado con el {$campo} '{$valor}'.";

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
