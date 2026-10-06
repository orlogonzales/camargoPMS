<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando el certificado X.509 es inválido, corrupto, expirado o no conforme.
 */
final class CertificadoInvalidoExcepcion extends FirmaCpeExcepcion
{
    protected $code = 422;
}
