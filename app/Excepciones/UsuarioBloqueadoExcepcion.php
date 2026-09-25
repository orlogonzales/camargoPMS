<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando una cuenta de usuario se encuentra administrativamente bloqueada o inactiva.
 */
class UsuarioBloqueadoExcepcion extends DomainException
{
    private string $estado;

    public function __construct(string $estado, string $mensaje = '', int $codigo = 403)
    {
        $this->estado = $estado;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "La cuenta de usuario no está habilitada para operar (estado: {$estado}).";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }
}
