<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada ante colisiones concurrentes en activación de versiones o generación de folios (D-079 #13, #14).
 */
class ConflictoDocumentalExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'Conflicto de concurrencia al procesar el documento o plantilla.')
    {
        parent::__construct($mensaje, 409);
    }
}
