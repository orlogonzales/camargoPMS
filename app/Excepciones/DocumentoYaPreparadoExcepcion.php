<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

/**
 * Excepción lanzada cuando se intenta preparar para firma un documento que ya contiene
 * un bloque cac:Signature o estructura de preparación preexistente (fail-fast de idempotencia).
 */
class DocumentoYaPreparadoExcepcion extends PreparacionFirmaExcepcion
{
    protected $code = 409;
}
