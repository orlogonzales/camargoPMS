<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando cac:Signature cbc:URI no coincide con el identificador ds:Signature/@Id.
 */
final class CrossLinkFirmaInvalidoExcepcion extends FirmaCpeExcepcion
{
    protected $code = 422;
}
