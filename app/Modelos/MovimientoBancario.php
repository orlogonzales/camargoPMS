<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un movimiento en el libro de cuentas bancarias de la empresa.
 */
class MovimientoBancario
{
    public function __construct(
        private ?int $id,
        private int $cuentaBancariaId,
        private string $tipoMovimiento,
        private ?int $pagoId,
        private ?int $devolucionId,
        private string $monto,
        private string $monedaCodigo,
        private string $numeroOperacion,
        private string $concepto,
        private string $fechaOperacion,
        private int $actorId,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCuentaBancariaId(): int
    {
        return $this->cuentaBancariaId;
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

    public function obtenerNumeroOperacion(): string
    {
        return $this->numeroOperacion;
    }

    public function obtenerConcepto(): string
    {
        return $this->concepto;
    }

    public function obtenerFechaOperacion(): string
    {
        return $this->fechaOperacion;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'cuenta_bancaria_id' => $this->cuentaBancariaId,
            'tipo_movimiento' => $this->tipoMovimiento,
            'pago_id' => $this->pagoId,
            'devolucion_id' => $this->devolucionId,
            'monto' => $this->monto,
            'moneda_codigo' => $this->monedaCodigo,
            'numero_operacion' => $this->numeroOperacion,
            'concepto' => $this->concepto,
            'fecha_operacion' => $this->fechaOperacion,
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
            (int) ($datos['cuenta_bancaria_id'] ?? 0),
            (string) ($datos['tipo_movimiento'] ?? 'INGRESO_TRANSFERENCIA'),
            isset($datos['pago_id']) ? (int) $datos['pago_id'] : null,
            isset($datos['devolucion_id']) ? (int) $datos['devolucion_id'] : null,
            (string) ($datos['monto'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['numero_operacion'] ?? ''),
            (string) ($datos['concepto'] ?? ''),
            (string) ($datos['fecha_operacion'] ?? date('Y-m-d')),
            (int) ($datos['actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
