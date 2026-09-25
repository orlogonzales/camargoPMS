<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando se intenta crear un usuario para una persona que ya tiene cuenta
 * o cuando el nombre de usuario ya se encuentra registrado.
 */
class UsuarioDuplicadoExcepcion extends DomainException
{
    private string $campo;
    private string $valor;

    public function __construct(string $campo, string $valor, string $mensaje = '', int $codigo = 409)
    {
        $this->campo = $campo;
        $this->valor = $valor;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "Ya existe un usuario registrado con el {$campo} '{$valor}'.";

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
