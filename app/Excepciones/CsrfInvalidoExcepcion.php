<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando la validación del token CSRF falla en una petición sensible.
 */
class CsrfInvalidoExcepcion extends DomainException
{
    public function __construct(string $mensaje = 'Token de seguridad inválido o ausente.', int $codigo = 419)
    {
        parent::__construct($mensaje, $codigo);
    }
}
