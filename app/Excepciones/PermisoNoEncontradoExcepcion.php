<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando un permiso solicitado no existe.
 */
class PermisoNoEncontradoExcepcion extends EntidadNoEncontradaExcepcion
{
    public function __construct(string|int $identificador, string $mensaje = '', int $codigo = 404)
    {
        parent::__construct('Permiso', $identificador, $mensaje, $codigo);
    }
}
