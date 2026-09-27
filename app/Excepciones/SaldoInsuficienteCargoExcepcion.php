<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando se intenta aplicar un monto superior a la deuda remanente de un cargo.
 */
class SaldoInsuficienteCargoExcepcion extends RuntimeException
{
    public function __construct(string $montoRequerido, string $deudaPendiente)
    {
        parent::__construct(
            "El monto a aplicar [{$montoRequerido}] excede la deuda exigible pendiente del cargo [{$deudaPendiente}].",
            422
        );
    }
}
