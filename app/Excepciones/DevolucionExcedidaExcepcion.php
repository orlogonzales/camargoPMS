<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una devolución excede el importe disponible/devolvible del pago original.
 */
class DevolucionExcedidaExcepcion extends RuntimeException
{
    public function __construct(string $montoDevolucion, string $montoDevolvible)
    {
        parent::__construct(
            "El monto de devolución [{$montoDevolucion}] supera el importe devolvible disponible del pago [{$montoDevolvible}].",
            422
        );
    }
}
