<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa la imputación formal de un pago contra un cargo específico.
 */
class AplicacionPago
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $pagoId,
        private int $cargoId,
        private string $montoAplicado,
        private string $monedaCodigo,
        private string $estado,
        private ?string $revertidaEn,
        private ?int $revertidaPorActorId,
        private int $creadoPorActorId,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerPagoId(): int
    {
        return $this->pagoId;
    }

    public function obtenerCargoId(): int
    {
        return $this->cargoId;
    }

    public function obtenerMontoAplicado(): string
    {
        return $this->montoAplicado;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVA';
    }

    public function estaRevertida(): bool
    {
        return $this->estado === 'REVERTIDA';
    }

    public function obtenerRevertidaEn(): ?string
    {
        return $this->revertidaEn;
    }

    public function obtenerRevertidaPorActorId(): ?int
    {
        return $this->revertidaPorActorId;
    }

    public function obtenerCreadoPorActorId(): int
    {
        return $this->creadoPorActorId;
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
            'codigo' => $this->codigo,
            'pago_id' => $this->pagoId,
            'cargo_id' => $this->cargoId,
            'monto_aplicado' => $this->montoAplicado,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'revertida_en' => $this->revertidaEn,
            'revertida_por_actor_id' => $this->revertidaPorActorId,
            'creado_por_actor_id' => $this->creadoPorActorId,
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
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['pago_id'] ?? 0),
            (int) ($datos['cargo_id'] ?? 0),
            (string) ($datos['monto_aplicado'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? 'ACTIVA'),
            isset($datos['revertida_en']) ? (string) $datos['revertida_en'] : null,
            isset($datos['revertida_por_actor_id']) ? (int) $datos['revertida_por_actor_id'] : null,
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
