<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando el establecimiento emisor fiscal no existe.
 */
class EstablecimientoNoEncontradoExcepcion extends CpeExcepcion
{
    protected $code = 404;
}
