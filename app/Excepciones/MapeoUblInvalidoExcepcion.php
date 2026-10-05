<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando los datos del snapshot fiscal no pueden ser mapeados válidamente a UBL 2.1.
 */
class MapeoUblInvalidoExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
