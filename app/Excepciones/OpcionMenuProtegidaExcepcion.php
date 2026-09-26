<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando se intenta eliminar, inactivar o modificar indebidamente
 * un elemento estructural protegido del menú que dejaría al sistema sin acceso administrativo.
 */
class OpcionMenuProtegidaExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'No se puede modificar ni desactivar un elemento estructural protegido del sistema.')
    {
        parent::__construct($mensaje, 422);
    }
}
