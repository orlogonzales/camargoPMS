<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando un usuario autenticado carece de autorización suficiente para una acción.
 */
class AutorizacionDenegadaExcepcion extends DomainException
{
    private ?string $permiso;

    public function __construct(string $permiso = '', string $mensaje = '', int $codigo = 403)
    {
        $this->permiso = $permiso !== '' ? $permiso : null;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : ($permiso !== ''
                ? "No cuentas con la autorización requerida ('{$permiso}')."
                : 'No cuentas con autorización suficiente para realizar esta acción.');

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerPermiso(): ?string
    {
        return $this->permiso;
    }
}
