<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un movimiento en el libro mayor de caja física (efectivo).
 */
class MovimientoCaja
{
    public function __construct(
        private ?int $id,
        private int $sesionCajaId,
        private string $tipoMovimiento,
        private ?int $pagoId,
        private ?int $devolucionId,
        private string $monto,
        private string $monedaCodigo,
        private string $concepto,
        private int $actorId,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerSesionCajaId(): int
    {
        return $this->sesionCajaId;
    }

    public function obtenerTipoMovimiento(): string
    {
        return $this->tipoMovimiento;
    }

    public function obtenerPagoId(): ?int
    {
        return $this->pagoId;
    }

    public function obtenerDevolucionId(): ?int
    {
        return $this->devolucionId;
    }

    public function obtenerMonto(): string
    {
        return $this->monto;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerConcepto(): string
    {
        return $this->concepto;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function esIngreso(): bool
    {
        return str_starts_with($this->tipoMovimiento, 'INGRESO_');
    }

    public function esEgreso(): bool
    {
        return str_starts_with($this->tipoMovimiento, 'EGRESO_');
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'sesion_caja_id' => $this->sesionCajaId,
            'tipo_movimiento' => $this->tipoMovimiento,
            'pago_id' => $this->pagoId,
            'devolucion_id' => $this->devolucionId,
            'monto' => $this->monto,
            'moneda_codigo' => $this->monedaCodigo,
            'concepto' => $this->concepto,
            'actor_id' => $this->actorId,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['sesion_caja_id'] ?? 0),
            (string) ($datos['tipo_movimiento'] ?? 'INGRESO_COBRO'),
            isset($datos['pago_id']) ? (int) $datos['pago_id'] : null,
            isset($datos['devolucion_id']) ? (int) $datos['devolucion_id'] : null,
            (string) ($datos['monto'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['concepto'] ?? ''),
            (int) ($datos['actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
