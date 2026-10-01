<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando se intenta transferir o dividir un cargo que posee amortizaciones o pagos aplicados.
 */
class CargoConPagosAplicadosExcepcion extends RuntimeException
{
    public function __construct(string $cargoCodigo, string $montoAplicado)
    {
        parent::__construct(
            "El cargo [{$cargoCodigo}] posee amortizaciones o aplicaciones de pago activas por [{$montoAplicado}]. "
            . "Para transferir o dividir este cargo, primero debe revertir formalmente las aplicaciones de pago vinculadas.",
            422
        );
    }
}
