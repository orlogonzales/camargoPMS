<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa la imputación / aplicación formal de un Pago de Egreso a un Gasto.
 * GASTOS-1 / D-086.
 */
class GastoAplicacionPago
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_REVERSADO = 'REVERSADO';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $gastoId,
        private int $pagoEgresoId,
        private string $montoAplicado,
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $motivoReverso = null,
        private ?string $reversadoEn = null,
        private ?int $reversadoPorActorId = null,
        private int $creadoPorActorId = 1,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerGastoId(): int { return $this->gastoId; }
    public function obtenerPagoEgresoId(): int { return $this->pagoEgresoId; }
    public function obtenerMontoAplicado(): string { return $this->montoAplicado; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerMotivoReverso(): ?string { return $this->motivoReverso; }
    public function obtenerReversadoEn(): ?string { return $this->reversadoEn; }
    public function obtenerReversadoPorActorId(): ?int { return $this->reversadoPorActorId; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'gasto_id' => $this->gastoId,
            'pago_egreso_id' => $this->pagoEgresoId,
            'monto_aplicado' => $this->montoAplicado,
            'estado' => $this->estado,
            'motivo_reverso' => $this->motivoReverso,
            'reversado_en' => $this->reversadoEn,
            'reversado_por_actor_id' => $this->reversadoPorActorId,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['gasto_id'] ?? 0),
            (int) ($datos['pago_egreso_id'] ?? 0),
            (string) ($datos['monto_aplicado'] ?? '0.00'),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['motivo_reverso']) ? (string) $datos['motivo_reverso'] : null,
            isset($datos['reversado_en']) ? (string) $datos['reversado_en'] : null,
            isset($datos['reversado_por_actor_id']) ? (int) $datos['reversado_por_actor_id'] : null,
            isset($datos['creado_por_actor_id']) ? (int) $datos['creado_por_actor_id'] : 1,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
