<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO para el resultado de consulta de una orden en la pasarela externa.
 */
class ConsultaOrdenResultado
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PAGADA = 'PAGADA';
    public const ESTADO_EXPIRADA = 'EXPIRADA';
    public const ESTADO_FALLIDA = 'FALLIDA';

    public function __construct(
        public bool $encontrado,
        public string $proveedor,
        public string $proveedorOrdenId,
        public string $estadoOrden,
        public ?string $monto = null,
        public ?string $moneda = null,
        public ?string $transaccionId = null,
        public ?string $pagadoEn = null,
        public array $metadatos = [],
        public ?string $mensajeError = null
    ) {
    }

    public static function noEncontrada(string $proveedor, string $proveedorOrdenId, string $mensaje): self
    {
        return new self(
            encontrado: false,
            proveedor: $proveedor,
            proveedorOrdenId: $proveedorOrdenId,
            estadoOrden: self::ESTADO_FALLIDA,
            mensajeError: $mensaje
        );
    }
}
