<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando la clave privada no puede ser cargada, es ilegible o no coincide con el certificado.
 */
final class ClavePrivadaInvalidaExcepcion extends FirmaCpeExcepcion
{
    protected $code = 422;
}
