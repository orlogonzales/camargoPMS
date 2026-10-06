<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción base para errores ocurridos durante la etapa de preparación de firma UBL 2.1.
 */
class PreparacionFirmaExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
