<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando un comprobante de pago electrónico solicitado no existe.
 */
class ComprobanteNoEncontradoExcepcion extends CpeExcepcion
{
    protected $code = 404;
}
