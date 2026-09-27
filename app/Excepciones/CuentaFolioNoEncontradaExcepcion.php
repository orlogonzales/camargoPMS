<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando no se localiza una cuenta o folio financiero.
 */
class CuentaFolioNoEncontradaExcepcion extends RuntimeException
{
    public function __construct(string $identificador = '')
    {
        $mensaje = $identificador !== ''
            ? "No se encontró la cuenta/folio financiero con identificador [{$identificador}]."
            : 'No se encontró la cuenta/folio financiero solicitada.';
        parent::__construct($mensaje, 404);
    }
}
