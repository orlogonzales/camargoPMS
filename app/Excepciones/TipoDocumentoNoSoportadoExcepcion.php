<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando el elemento raíz o namespace del documento XML
 * no corresponde a un tipo de comprobante UBL 2.1 admitido por SUNAT (Invoice, CreditNote, DebitNote).
 */
class TipoDocumentoNoSoportadoExcepcion extends PreparacionFirmaExcepcion
{
    protected $code = 422;
}
