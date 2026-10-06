<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando la firma recién generada o verificada no pasa la post-verificación matemática W3C.
 */
final class VerificacionFirmaFallidaExcepcion extends FirmaCpeExcepcion
{
    protected $code = 422;
}
