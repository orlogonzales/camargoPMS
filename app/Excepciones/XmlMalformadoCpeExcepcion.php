<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando el XML recibido para preparación de firma está malformado,
 * infringe la sintaxis XML 1.0, o contiene vectores DTD/ENTITY no permitidos (XXE).
 */
class XmlMalformadoCpeExcepcion extends PreparacionFirmaExcepcion
{
    protected $code = 400;
}
