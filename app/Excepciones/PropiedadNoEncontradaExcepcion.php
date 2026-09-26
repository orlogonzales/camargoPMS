<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando una propiedad solicitada no existe en la persistencia.
 */
class PropiedadNoEncontradaExcepcion extends EntidadNoEncontradaExcepcion
{
    public function __construct(string|int $identificador, string $mensaje = '', int $codigo = 404)
    {
        parent::__construct('Propiedad', $identificador, $mensaje, $codigo);
    }
}
