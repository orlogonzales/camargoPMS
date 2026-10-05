<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción base para el dominio de Comprobantes de Pago Electrónicos (CPE / SUNAT).
 */
class CpeExcepcion extends RuntimeException
{
    protected $code = 400;
}
