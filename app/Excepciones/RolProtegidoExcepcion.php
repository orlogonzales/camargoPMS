<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando se intenta eliminar, desactivar o mutar el rol estructural protegido SUPERADMINISTRADOR.
 */
class RolProtegidoExcepcion extends DomainException
{
    public function __construct(string $mensaje = 'El rol del sistema SUPERADMINISTRADOR está protegido contra eliminación o desactivación.', int $codigo = 422)
    {
        parent::__construct($mensaje, $codigo);
    }
}
