<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraOrden
{
    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_APROBADA = 'APROBADA';
    public const ESTADO_CERRADA = 'CERRADA';
    public const ESTADO_CANCELADA = 'CANCELADA';

    public const RECEPCION_SIN_RECEPCION = 'SIN_RECEPCION';
    public const RECEPCION_RECEPCION_PARCIAL = 'RECEPCION_PARCIAL';
    public const RECEPCION_RECEPCION_TOTAL = 'RECEPCION_TOTAL';

    public const FACTURACION_SIN_FACTURAR = 'SIN_FACTURAR';
    public const FACTURACION_FACTURADA_PARCIAL = 'FACTURADA_PARCIAL';
    public const FACTURACION_FACTURADA_TOTAL = 'FACTURADA_TOTAL';

    public const PAGO_PENDIENTE = 'PENDIENTE';
    public const PAGO_PAGADO_PARCIAL = 'PAGADO_PARCIAL';
    public const PAGO_PAGADO_TOTAL = 'PAGADO_TOTAL';

    private ?int $id;
    private string $codigo;
    private ?int $solicitudId;
    private int $proveedorId;
    private string $monedaCodigo;
    private string $condicionPago;
    private ?int $almacenEntregaId;
    private ?string $fechaEntregaEsperada;
    private ?string $notasComerciales;
    private string $subtotal;
    private string $impuestoTotal;
    private string $descuentoTotal;
    private string $total;
    private string $estadoComercial;
    private string $estadoRecepcion;
    private string $estadoFacturacion;
    private string $estadoPago;
    private int $creadoPorActorId;
    private ?int $aprobadoPorActorId;
    private ?string $motivoCancelacion;
    private ?DateTimeImmutable $creadoEn;
    private ?DateTimeImmutable $actualizadoEn;

    /**
     * @var array<CompraOrdenLinea>
     */
    private array $lineas = [];

    public function __construct(
        ?int $id,
        string $codigo,
        ?int $solicitudId,
        int $proveedorId,
        string $monedaCodigo,
        string $condicionPago,
        ?int $almacenEntregaId,
        ?string $fechaEntregaEsperada,
        ?string $notasComerciales,
        string $subtotal,
        string $impuestoTotal,
        string $descuentoTotal,
        string $total,
        string $estadoComercial,
        string $estadoRecepcion,
        string $estadoFacturacion,
        string $estadoPago,
        int $creadoPorActorId,
        ?int $aprobadoPorActorId = null,
        ?string $motivoCancelacion = null,
        ?DateTimeImmutable $creadoEn = null,
        ?DateTimeImmutable $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->solicitudId = $solicitudId;
        $this->proveedorId = $proveedorId;
        $this->monedaCodigo = $monedaCodigo;
        $this->condicionPago = $condicionPago;
        $this->almacenEntregaId = $almacenEntregaId;
        $this->fechaEntregaEsperada = $fechaEntregaEsperada;
        $this->notasComerciales = $notasComerciales;
        $this->subtotal = $subtotal;
        $this->impuestoTotal = $impuestoTotal;
        $this->descuentoTotal = $descuentoTotal;
        $this->total = $total;
        $this->estadoComercial = $estadoComercial;
        $this->estadoRecepcion = $estadoRecepcion;
        $this->estadoFacturacion = $estadoFacturacion;
        $this->estadoPago = $estadoPago;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->aprobadoPorActorId = $aprobadoPorActorId;
        $this->motivoCancelacion = $motivoCancelacion;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerSolicitudId(): ?int { return $this->solicitudId; }
    public function obtenerProveedorId(): int { return $this->proveedorId; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerCondicionPago(): string { return $this->condicionPago; }
    public function obtenerAlmacenEntregaId(): ?int { return $this->almacenEntregaId; }
    public function obtenerFechaEntregaEsperada(): ?string { return $this->fechaEntregaEsperada; }
    public function obtenerNotasComerciales(): ?string { return $this->notasComerciales; }
    public function obtenerSubtotal(): string { return $this->subtotal; }
    public function obtenerImpuestoTotal(): string { return $this->impuestoTotal; }
    public function obtenerDescuentoTotal(): string { return $this->descuentoTotal; }
    public function obtenerTotal(): string { return $this->total; }
    public function obtenerEstadoComercial(): string { return $this->estadoComercial; }
    public function obtenerEstadoRecepcion(): string { return $this->estadoRecepcion; }
    public function obtenerEstadoFacturacion(): string { return $this->estadoFacturacion; }
    public function obtenerEstadoPago(): string { return $this->estadoPago; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerAprobadoPorActorId(): ?int { return $this->aprobadoPorActorId; }
    public function obtenerMotivoCancelacion(): ?string { return $this->motivoCancelacion; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?DateTimeImmutable { return $this->actualizadoEn; }

    /**
     * @return array<CompraOrdenLinea>
     */
    public function obtenerLineas(): array { return $this->lineas; }

    /**
     * @param array<CompraOrdenLinea> $lineas
     */
    public function asignarLineas(array $lineas): void { $this->lineas = $lineas; }

    public function estaAprobada(): bool
    {
        return $this->estadoComercial === 'APROBADA';
    }

    public function puedeModificarse(): bool
    {
        return $this->estadoComercial === 'BORRADOR';
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'solicitud_id' => $this->solicitudId,
            'proveedor_id' => $this->proveedorId,
            'moneda_codigo' => $this->monedaCodigo,
            'condicion_pago' => $this->condicionPago,
            'almacen_entrega_id' => $this->almacenEntregaId,
            'fecha_entrega_esperada' => $this->fechaEntregaEsperada,
            'notas_comerciales' => $this->notasComerciales,
            'subtotal' => $this->subtotal,
            'impuesto_total' => $this->impuestoTotal,
            'descuento_total' => $this->descuentoTotal,
            'total' => $this->total,
            'estado_comercial' => $this->estadoComercial,
            'estado_recepcion' => $this->estadoRecepcion,
            'estado_facturacion' => $this->estadoFacturacion,
            'estado_pago' => $this->estadoPago,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'aprobado_por_actor_id' => $this->aprobadoPorActorId,
            'motivo_cancelacion' => $this->motivoCancelacion,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
            'actualizado_en' => $this->actualizadoEn?->format('Y-m-d H:i:s'),
            'lineas' => array_map(fn($l) => $l->aArreglo(), $this->lineas),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
