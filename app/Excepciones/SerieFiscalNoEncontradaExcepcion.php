<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando una serie fiscal solicitada no existe.
 */
class SerieFiscalNoEncontradaExcepcion extends CpeExcepcion
{
    protected $code = 404;
}
