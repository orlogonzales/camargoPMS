<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraRecepcionLinea
{
    private ?int $id;
    private int $recepcionId;
    private int $ordenLineaId;
    private int $articuloId;
    private string $cantidadRecibida;
    private string $cantidadAceptada;
    private string $cantidadRechazada;
    private ?string $motivoRechazo;
    private ?int $movimientoInventarioId;
    private ?DateTimeImmutable $creadoEn;

    public function __construct(
        ?int $id,
        int $recepcionId,
        int $ordenLineaId,
        int $articuloId,
        string $cantidadRecibida,
        string $cantidadAceptada,
        string $cantidadRechazada = '0.0000',
        ?string $motivoRechazo = null,
        ?int $movimientoInventarioId = null,
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->recepcionId = $recepcionId;
        $this->ordenLineaId = $ordenLineaId;
        $this->articuloId = $articuloId;
        $this->cantidadRecibida = $cantidadRecibida;
        $this->cantidadAceptada = $cantidadAceptada;
        $this->cantidadRechazada = $cantidadRechazada;
        $this->motivoRechazo = $motivoRechazo;
        $this->movimientoInventarioId = $movimientoInventarioId;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerRecepcionId(): int { return $this->recepcionId; }
    public function obtenerOrdenLineaId(): int { return $this->ordenLineaId; }
    public function obtenerArticuloId(): int { return $this->articuloId; }
    public function obtenerCantidadRecibida(): string { return $this->cantidadRecibida; }
    public function obtenerCantidadAceptada(): string { return $this->cantidadAceptada; }
    public function obtenerCantidadRechazada(): string { return $this->cantidadRechazada; }
    public function obtenerMotivoRechazo(): ?string { return $this->motivoRechazo; }
    public function obtenerMovimientoInventarioId(): ?int { return $this->movimientoInventarioId; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'recepcion_id' => $this->recepcionId,
            'orden_linea_id' => $this->ordenLineaId,
            'articulo_id' => $this->articuloId,
            'cantidad_recibida' => $this->cantidadRecibida,
            'cantidad_aceptada' => $this->cantidadAceptada,
            'cantidad_rechazada' => $this->cantidadRechazada,
            'motivo_rechazo' => $this->motivoRechazo,
            'movimiento_inventario_id' => $this->movimientoInventarioId,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
        ];
    }
}
