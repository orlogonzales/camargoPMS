<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO para el resultado de un reembolso emitido por la pasarela externa.
 */
class ReembolsoResultado
{
    public const ESTADO_REEMBOLSADO = 'REEMBOLSADO';
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_FALLIDO = 'FALLIDO';

    public function __construct(
        public bool $exitoso,
        public string $proveedor,
        public ?string $reembolsoId = null,
        public ?string $monto = null,
        public ?string $moneda = null,
        public string $estado = self::ESTADO_FALLIDO,
        public array $metadatos = [],
        public ?string $mensajeError = null
    ) {
    }

    public static function exito(
        string $proveedor,
        string $reembolsoId,
        string $monto,
        string $moneda,
        array $metadatos = []
    ): self {
        return new self(
            exitoso: true,
            proveedor: $proveedor,
            reembolsoId: $reembolsoId,
            monto: $monto,
            moneda: $moneda,
            estado: self::ESTADO_REEMBOLSADO,
            metadatos: $metadatos,
            mensajeError: null
        );
    }

    public static function fallo(string $proveedor, string $mensajeError): self
    {
        return new self(
            exitoso: false,
            proveedor: $proveedor,
            reembolsoId: null,
            monto: null,
            moneda: null,
            estado: self::ESTADO_FALLIDO,
            metadatos: [],
            mensajeError: $mensajeError
        );
    }
}
