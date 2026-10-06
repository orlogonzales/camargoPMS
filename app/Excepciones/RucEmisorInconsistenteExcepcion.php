<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando el RUC configurado en el contexto de firma no coincide
 * exactamente con el RUC emisor declarado en el AccountingSupplierParty del comprobante.
 */
class RucEmisorInconsistenteExcepcion extends PreparacionFirmaExcepcion
{
    protected $code = 422;
}
