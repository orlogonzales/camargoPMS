<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción base para errores ocurridos durante el proceso de firma digital XMLDSig de CPEs.
 */
class FirmaCpeExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
