<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando un rol solicitado no existe.
 */
class RolNoEncontradoExcepcion extends EntidadNoEncontradaExcepcion
{
    public function __construct(string|int $identificador, string $mensaje = '', int $codigo = 404)
    {
        parent::__construct('Rol', $identificador, $mensaje, $codigo);
    }
}
