<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando ya existe un permiso con la misma clave.
 */
class PermisoDuplicadoExcepcion extends DomainException
{
    private string $clave;

    public function __construct(string $clave, string $mensaje = '', int $codigo = 409)
    {
        $this->clave = $clave;

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "Ya existe un permiso registrado con la clave '{$clave}'.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }
}
