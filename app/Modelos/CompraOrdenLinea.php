<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraOrdenLinea
{
    private ?int $id;
    private int $ordenCompraId;
    private string $tipoLinea;
    private ?int $articuloId;
    private ?string $descripcionServicio;
    private string $cantidadPactada;
    private string $precioUnitario;
    private string $subtotalLinea;
    private string $impuestoLinea;
    private string $totalLinea;
    private string $cantidadAceptada;
    private ?DateTimeImmutable $creadoEn;

    public function __construct(
        ?int $id,
        int $ordenCompraId,
        string $tipoLinea,
        ?int $articuloId,
        ?string $descripcionServicio,
        string $cantidadPactada,
        string $precioUnitario,
        string $subtotalLinea,
        string $impuestoLinea,
        string $totalLinea,
        string $cantidadAceptada = '0.0000',
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->ordenCompraId = $ordenCompraId;
        $this->tipoLinea = $tipoLinea;
        $this->articuloId = $articuloId;
        $this->descripcionServicio = $descripcionServicio;
        $this->cantidadPactada = $cantidadPactada;
        $this->precioUnitario = $precioUnitario;
        $this->subtotalLinea = $subtotalLinea;
        $this->impuestoLinea = $impuestoLinea;
        $this->totalLinea = $totalLinea;
        $this->cantidadAceptada = $cantidadAceptada;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerOrdenCompraId(): int { return $this->ordenCompraId; }
    public function obtenerTipoLinea(): string { return $this->tipoLinea; }
    public function obtenerArticuloId(): ?int { return $this->articuloId; }
    public function obtenerDescripcionServicio(): ?string { return $this->descripcionServicio; }
    public function obtenerCantidadPactada(): string { return $this->cantidadPactada; }
    public function obtenerPrecioUnitario(): string { return $this->precioUnitario; }
    public function obtenerSubtotalLinea(): string { return $this->subtotalLinea; }
    public function obtenerImpuestoLinea(): string { return $this->impuestoLinea; }
    public function obtenerTotalLinea(): string { return $this->totalLinea; }
    public function obtenerCantidadAceptada(): string { return $this->cantidadAceptada; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    public function obtenerCantidadPendiente(): string
    {
        return bcsub($this->cantidadPactada, $this->cantidadAceptada, 4);
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'orden_compra_id' => $this->ordenCompraId,
            'tipo_linea' => $this->tipoLinea,
            'articulo_id' => $this->articuloId,
            'descripcion_servicio' => $this->descripcionServicio,
            'cantidad_pactada' => $this->cantidadPactada,
            'precio_unitario' => $this->precioUnitario,
            'subtotal_linea' => $this->subtotalLinea,
            'impuesto_linea' => $this->impuestoLinea,
            'total_linea' => $this->totalLinea,
            'cantidad_aceptada' => $this->cantidadAceptada,
            'cantidad_pendiente' => $this->obtenerCantidadPendiente(),
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
        ];
    }
}
