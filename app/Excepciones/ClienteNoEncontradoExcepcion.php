<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza un perfil comercial de Cliente por ID o Código.
 */
class ClienteNoEncontradoExcepcion extends RuntimeException
{
    protected $code = 404;

    public function __construct(string $identificador = '', string $mensaje = '')
    {
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : ($identificador !== '' 
                ? "No se encontró el perfil de cliente con identificador '{$identificador}'." 
                : "Cliente no encontrado.");

        parent::__construct($mensajeFinal, 404);
    }
}
