<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CxpPago
{
    private ?int $id;
    private int $cuentaPagarId;
    private string $medioPago;
    private string $monto;
    private DateTimeImmutable $fechaPago;
    private ?string $numeroOperacionBancaria;
    private ?int $movimientoCajaId;
    private ?int $movimientoBancarioId;
    private ?string $notas;
    private int $registradoPorActorId;
    private ?DateTimeImmutable $creadoEn;

    public function __construct(
        ?int $id,
        int $cuentaPagarId,
        string $medioPago,
        string $monto,
        DateTimeImmutable $fechaPago,
        ?string $numeroOperacionBancaria = null,
        ?int $movimientoCajaId = null,
        ?int $movimientoBancarioId = null,
        ?string $notas = null,
        int $registradoPorActorId = 1,
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->cuentaPagarId = $cuentaPagarId;
        $this->medioPago = $medioPago;
        $this->monto = $monto;
        $this->fechaPago = $fechaPago;
        $this->numeroOperacionBancaria = $numeroOperacionBancaria;
        $this->movimientoCajaId = $movimientoCajaId;
        $this->movimientoBancarioId = $movimientoBancarioId;
        $this->notas = $notas;
        $this->registradoPorActorId = $registradoPorActorId;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCuentaPagarId(): int { return $this->cuentaPagarId; }
    public function obtenerMedioPago(): string { return $this->medioPago; }
    public function obtenerMonto(): string { return $this->monto; }
    public function obtenerFechaPago(): DateTimeImmutable { return $this->fechaPago; }
    public function obtenerNumeroOperacionBancaria(): ?string { return $this->numeroOperacionBancaria; }
    public function obtenerMovimientoCajaId(): ?int { return $this->movimientoCajaId; }
    public function obtenerMovimientoBancarioId(): ?int { return $this->movimientoBancarioId; }
    public function obtenerNotas(): ?string { return $this->notas; }
    public function obtenerRegistradoPorActorId(): int { return $this->registradoPorActorId; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'cuenta_pagar_id' => $this->cuentaPagarId,
            'medio_pago' => $this->medioPago,
            'monto' => $this->monto,
            'fecha_pago' => $this->fechaPago->format('Y-m-d H:i:s'),
            'numero_operacion_bancaria' => $this->numeroOperacionBancaria,
            'movimiento_caja_id' => $this->movimientoCajaId,
            'movimiento_bancario_id' => $this->movimientoBancarioId,
            'notas' => $this->notas,
            'registrado_por_actor_id' => $this->registradoPorActorId,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
