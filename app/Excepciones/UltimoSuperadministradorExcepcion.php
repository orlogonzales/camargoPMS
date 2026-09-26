<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando una operación administrativa violaría el invariante
 * fundamental de mantener al menos un Superadministrador activo en el sistema.
 */
class UltimoSuperadministradorExcepcion extends DomainException
{
    public function __construct(string $mensaje = 'No se puede revocar, bloquear o desactivar al único Superadministrador activo del sistema.', int $codigo = 422)
    {
        parent::__construct($mensaje, $codigo);
    }
}
