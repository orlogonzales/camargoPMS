<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando una cadena fiscal contiene caracteres de control o secuencias no permitidas por la especificación XML 1.0.
 */
class CaracterInvalidoXmlExcepcion extends ValidacionFiscalExcepcion
{
    protected $code = 422;
}
