<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando los datos tributarios, importes o snapshots de un CPE infringen reglas de validación.
 */
class ValidacionFiscalExcepcion extends CpeExcepcion
{
    protected $code = 422;
}
