<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad inmutable de trazabilidad para los cambios de estado en un arrendamiento.
 */
class ArrendamientoHistorialEstado
{
    private ?int $id;
    private int $arrendamientoId;
    private string $estadoAnterior;
    private string $estadoNuevo;
    private ?string $motivo;
    private int $actorId;
    private ?string $cambiadoEn;

    // Metadatos auxiliares del actor
    private ?string $actorNombre = null;
    private ?string $actorTipo = null;

    public function __construct(
        ?int $id,
        int $arrendamientoId,
        string $estadoAnterior,
        string $estadoNuevo,
        ?string $motivo,
        int $actorId,
        ?string $cambiadoEn = null
    ) {
        $this->id = $id;
        $this->arrendamientoId = $arrendamientoId;
        $this->estadoAnterior = $estadoAnterior;
        $this->estadoNuevo = $estadoNuevo;
        $this->motivo = $motivo;
        $this->actorId = $actorId;
        $this->cambiadoEn = $cambiadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerArrendamientoId(): int { return $this->arrendamientoId; }
    public function obtenerEstadoAnterior(): string { return $this->estadoAnterior; }
    public function obtenerEstadoNuevo(): string { return $this->estadoNuevo; }
    public function obtenerMotivo(): ?string { return $this->motivo; }
    public function obtenerActorId(): int { return $this->actorId; }
    public function obtenerCambiadoEn(): ?string { return $this->cambiadoEn; }

    public function obtenerActorNombre(): ?string { return $this->actorNombre; }
    public function fijarActorNombre(?string $val): void { $this->actorNombre = $val; }
    public function obtenerActorTipo(): ?string { return $this->actorTipo; }
    public function fijarActorTipo(?string $val): void { $this->actorTipo = $val; }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'arrendamiento_id' => $this->arrendamientoId,
            'estado_anterior' => $this->estadoAnterior,
            'estado_nuevo' => $this->estadoNuevo,
            'motivo' => $this->motivo,
            'actor_id' => $this->actorId,
            'cambiado_en' => $this->cambiadoEn,
            'actor_nombre' => $this->actorNombre,
            'actor_tipo' => $this->actorTipo,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $historial = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['arrendamiento_id'] ?? 0),
            (string) ($datos['estado_anterior'] ?? ''),
            (string) ($datos['estado_nuevo'] ?? ''),
            isset($datos['motivo']) && $datos['motivo'] !== null ? (string) $datos['motivo'] : null,
            (int) ($datos['actor_id'] ?? 0),
            isset($datos['cambiado_en']) ? (string) $datos['cambiado_en'] : null
        );

        if (isset($datos['actor_nombre'])) {
            $historial->fijarActorNombre((string) $datos['actor_nombre']);
        }
        if (isset($datos['actor_tipo'])) {
            $historial->fijarActorTipo((string) $datos['actor_tipo']);
        }

        return $historial;
    }
}
