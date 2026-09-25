<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando una sesión de usuario es inexistente, expiró o fue revocada.
 */
class SesionInvalidaExcepcion extends DomainException
{
    private ?string $motivo;

    public function __construct(string $mensaje = 'Sesión no válida o expirada.', ?string $motivo = null, int $codigo = 401)
    {
        $this->motivo = $motivo;
        parent::__construct($mensaje, $codigo);
    }

    public function obtenerMotivo(): ?string
    {
        return $this->motivo;
    }
}
