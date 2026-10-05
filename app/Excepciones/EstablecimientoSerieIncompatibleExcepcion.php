<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando una serie fiscal no pertenece al establecimiento emisor indicado.
 */
class EstablecimientoSerieIncompatibleExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
