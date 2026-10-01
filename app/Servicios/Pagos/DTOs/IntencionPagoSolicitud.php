<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO agnóstico para solicitar la creación de una intención u orden de pago al proveedor.
 */
class IntencionPagoSolicitud
{
    public function __construct(
        public int $reservaId,
        public string $reservaCodigo,
        public string $monto,
        public string $moneda,
        public string $descripcion,
        public string $clienteEmail,
        public string $clienteNombre,
        public ?string $clienteTelefono = null,
        public ?string $expiraEn = null,
        public array $metadatos = []
    ) {
    }
}
