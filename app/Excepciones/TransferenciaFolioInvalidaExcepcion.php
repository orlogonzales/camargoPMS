<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando los parámetros o entidades de una transferencia o split de folio son inválidos.
 */
class TransferenciaFolioInvalidaExcepcion extends RuntimeException
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje, 422);
    }
}
