<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO para el resultado de consulta de una transacción (cargo o cobro) en la pasarela externa.
 */
class ConsultaTransaccionResultado
{
    public const ESTADO_APROBADA = 'APROBADA';
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_FALLIDA = 'FALLIDA';
    public const ESTADO_REEMBOLSADA = 'REEMBOLSADA';

    public function __construct(
        public bool $encontrado,
        public string $proveedor,
        public string $proveedorTransaccionId,
        public string $estadoTransaccion,
        public ?string $monto = null,
        public ?string $montoNeto = null,
        public ?string $comision = null,
        public ?string $moneda = null,
        public ?string $pagadoEn = null,
        public array $metadatos = [],
        public ?string $mensajeError = null
    ) {
    }

    public static function noEncontrada(string $proveedor, string $proveedorTransaccionId, string $mensaje): self
    {
        return new self(
            encontrado: false,
            proveedor: $proveedor,
            proveedorTransaccionId: $proveedorTransaccionId,
            estadoTransaccion: self::ESTADO_FALLIDA,
            mensajeError: $mensaje
        );
    }
}
