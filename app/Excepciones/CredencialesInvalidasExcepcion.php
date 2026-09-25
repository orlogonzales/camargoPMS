<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando la autenticación falla por credenciales incorrectas.
 *
 * Mantiene un mensaje genérico por defecto para evitar enumeración de usuarios.
 */
class CredencialesInvalidasExcepcion extends DomainException
{
    public function __construct(string $mensaje = 'Credenciales inválidas.', int $codigo = 401)
    {
        parent::__construct($mensaje, $codigo);
    }
}
