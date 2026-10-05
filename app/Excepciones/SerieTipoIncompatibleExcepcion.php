<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando el tipo de comprobante no coincide con el tipo autorizado para la serie.
 */
class SerieTipoIncompatibleExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
