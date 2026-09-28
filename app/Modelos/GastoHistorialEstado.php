<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio inmutable append-only para auditoría de estados de gasto.
 * GASTOS-1 / D-061 / D-086.
 */
class GastoHistorialEstado
{
    public function __construct(
        private ?int $id,
        private int $gastoId,
        private ?string $estadoAnterior,
        private string $estadoNuevo,
        private ?string $motivo = null,
        private int $actorId = 1,
        private ?string $correlationId = null,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerGastoId(): int { return $this->gastoId; }
    public function obtenerEstadoAnterior(): ?string { return $this->estadoAnterior; }
    public function obtenerEstadoNuevo(): string { return $this->estadoNuevo; }
    public function obtenerMotivo(): ?string { return $this->motivo; }
    public function obtenerActorId(): int { return $this->actorId; }
    public function obtenerCorrelationId(): ?string { return $this->correlationId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'gasto_id' => $this->gastoId,
            'estado_anterior' => $this->estadoAnterior,
            'estado_nuevo' => $this->estadoNuevo,
            'motivo' => $this->motivo,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
            'creado_en' => $this->creadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['gasto_id'] ?? 0),
            isset($datos['estado_anterior']) ? (string) $datos['estado_anterior'] : null,
            (string) ($datos['estado_nuevo'] ?? ''),
            isset($datos['motivo']) ? (string) $datos['motivo'] : null,
            isset($datos['actor_id']) ? (int) $datos['actor_id'] : 1,
            isset($datos['correlation_id']) ? (string) $datos['correlation_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
