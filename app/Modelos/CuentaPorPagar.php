<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CuentaPorPagar
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_AMORTIZADA_PARCIAL = 'AMORTIZADA_PARCIAL';
    public const ESTADO_LIQUIDADA = 'LIQUIDADA';
    public const ESTADO_ANULADA = 'ANULADA';

    private ?int $id;
    private string $codigo;
    private int $proveedorId;
    private int $comprobanteId;
    private int $ordenCompraId;
    private string $montoTotal;
    private string $montoAmortizado;
    private string $saldoPendiente;
    private string $fechaVencimiento;
    private string $estado;
    private ?DateTimeImmutable $creadoEn;
    private ?DateTimeImmutable $actualizadoEn;

    /**
     * @var array<CxpPago>
     */
    private array $pagos = [];

    public function __construct(
        ?int $id,
        string $codigo,
        int $proveedorId,
        int $comprobanteId,
        int $ordenCompraId,
        string $montoTotal,
        string $montoAmortizado,
        string $saldoPendiente,
        string $fechaVencimiento,
        string $estado,
        ?DateTimeImmutable $creadoEn = null,
        ?DateTimeImmutable $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->proveedorId = $proveedorId;
        $this->comprobanteId = $comprobanteId;
        $this->ordenCompraId = $ordenCompraId;
        $this->montoTotal = $montoTotal;
        $this->montoAmortizado = $montoAmortizado;
        $this->saldoPendiente = $saldoPendiente;
        $this->fechaVencimiento = $fechaVencimiento;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerProveedorId(): int { return $this->proveedorId; }
    public function obtenerComprobanteId(): int { return $this->comprobanteId; }
    public function obtenerOrdenCompraId(): int { return $this->ordenCompraId; }
    public function obtenerMontoTotal(): string { return $this->montoTotal; }
    public function obtenerMontoAmortizado(): string { return $this->montoAmortizado; }
    public function obtenerSaldoPendiente(): string { return $this->saldoPendiente; }
    public function obtenerFechaVencimiento(): string { return $this->fechaVencimiento; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?DateTimeImmutable { return $this->actualizadoEn; }

    /**
     * @return array<CxpPago>
     */
    public function obtenerPagos(): array { return $this->pagos; }

    /**
     * @param array<CxpPago> $pagos
     */
    public function asignarPagos(array $pagos): void { $this->pagos = $pagos; }

    public function estaLiquidada(): bool
    {
        return $this->estado === 'LIQUIDADA';
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'proveedor_id' => $this->proveedorId,
            'comprobante_id' => $this->comprobanteId,
            'orden_compra_id' => $this->ordenCompraId,
            'monto_total' => $this->montoTotal,
            'monto_amortizado' => $this->montoAmortizado,
            'saldo_pendiente' => $this->saldoPendiente,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
            'actualizado_en' => $this->actualizadoEn?->format('Y-m-d H:i:s'),
            'pagos' => array_map(fn($p) => $p->aArreglo(), $this->pagos),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
