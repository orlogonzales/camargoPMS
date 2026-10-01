<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO para solicitar un reembolso o devolución a la pasarela externa.
 */
class ReembolsoSolicitud
{
    public function __construct(
        public string $proveedorTransaccionId,
        public string $monto,
        public string $moneda,
        public string $motivo,
        public array $metadatos = []
    ) {
    }
}
