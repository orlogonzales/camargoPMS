<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando se intenta aplicar un monto superior al saldo disponible/no aplicado de un pago.
 */
class SaldoInsuficientePagoExcepcion extends RuntimeException
{
    public function __construct(string $montoRequerido, string $saldoDisponible)
    {
        parent::__construct(
            "El monto a aplicar [{$montoRequerido}] excede el saldo no aplicado disponible del pago [{$saldoDisponible}].",
            422
        );
    }
}
