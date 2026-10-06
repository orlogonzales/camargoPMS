<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando se intenta utilizar un algoritmo criptográfico débil o no permitido en Camargo PMS.
 */
final class AlgoritmoNoPermitidoExcepcion extends FirmaCpeExcepcion
{
    protected $code = 422;
}
