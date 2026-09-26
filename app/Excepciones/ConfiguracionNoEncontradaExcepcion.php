<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando un parámetro de configuración solicitado no existe.
 */
class ConfiguracionNoEncontradaExcepcion extends EntidadNoEncontradaExcepcion
{
    public function __construct(string|int $identificador, string $mensaje = '', int $codigo = 404)
    {
        parent::__construct('Configuracion', $identificador, $mensaje, $codigo);
    }
}
