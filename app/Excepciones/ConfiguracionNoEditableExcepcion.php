<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando se intenta mutar un parámetro de configuración protegido (editable = 0).
 */
class ConfiguracionNoEditableExcepcion extends DomainException
{
    private string $clave;

    public function __construct(string $clave, string $mensaje = '', int $codigo = 422)
    {
        $this->clave = $clave;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "El parámetro de configuración '{$clave}' está protegido por el sistema y no permite edición.";
        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }
}
