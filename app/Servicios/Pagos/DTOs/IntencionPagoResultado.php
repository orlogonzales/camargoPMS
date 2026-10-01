<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO que encapsula la respuesta a una solicitud de intención de pago.
 */
class IntencionPagoResultado
{
    public function __construct(
        public bool $exitoso,
        public string $proveedor,
        public ?string $proveedorOrdenId = null,
        public ?string $tokenTransaccion = null,
        public ?string $monto = null,
        public ?string $moneda = null,
        public ?string $fechaExpiracion = null,
        public array $metadatos = [],
        public ?string $mensajeError = null
    ) {
    }

    public static function exito(
        string $proveedor,
        string $proveedorOrdenId,
        ?string $tokenTransaccion,
        string $monto,
        string $moneda,
        ?string $fechaExpiracion = null,
        array $metadatos = []
    ): self {
        return new self(
            exitoso: true,
            proveedor: $proveedor,
            proveedorOrdenId: $proveedorOrdenId,
            tokenTransaccion: $tokenTransaccion,
            monto: $monto,
            moneda: $moneda,
            fechaExpiracion: $fechaExpiracion,
            metadatos: $metadatos,
            mensajeError: null
        );
    }

    public static function fallo(string $proveedor, string $mensajeError): self
    {
        return new self(
            exitoso: false,
            proveedor: $proveedor,
            proveedorOrdenId: null,
            tokenTransaccion: null,
            monto: null,
            moneda: null,
            fechaExpiracion: null,
            metadatos: [],
            mensajeError: $mensajeError
        );
    }
}
