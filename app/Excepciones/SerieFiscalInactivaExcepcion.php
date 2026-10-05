<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando se intenta emitir o asignar correlativo sobre una serie inactiva.
 */
class SerieFiscalInactivaExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
