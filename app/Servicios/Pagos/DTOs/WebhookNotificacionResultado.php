<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\Pagos\DTOs;

/**
 * DTO con el resultado de la verificación y parseo agnóstico de un webhook entrante.
 */
class WebhookNotificacionResultado
{
    public const ESTADO_APROBADO = 'APROBADO';
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_FALLIDO = 'FALLIDO';
    public const ESTADO_EXPIRADO = 'EXPIRADO';
    public const ESTADO_IGNORADO = 'IGNORADO';

    public function __construct(
        public bool $valido,
        public string $proveedor,
        public string $proveedorEventoId,
        public string $tipoEvento,
        public ?string $proveedorOrdenId = null,
        public ?string $proveedorTransaccionId = null,
        public string $estadoNormalizado = self::ESTADO_IGNORADO,
        public ?string $monto = null,
        public ?string $montoNeto = null,
        public ?string $comisionProveedor = null,
        public ?string $comisionImpuesto = null,
        public ?string $moneda = null,
        public ?string $pagadoEn = null,
        public array $metadatos = [],
        public ?string $motivoError = null
    ) {
    }

    public static function invalido(string $proveedor, string $motivoError): self
    {
        return new self(
            valido: false,
            proveedor: $proveedor,
            proveedorEventoId: '',
            tipoEvento: '',
            estadoNormalizado: self::ESTADO_FALLIDO,
            motivoError: $motivoError
        );
    }
}
