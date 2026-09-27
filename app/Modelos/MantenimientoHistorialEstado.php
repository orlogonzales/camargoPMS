<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio para la trazabilidad inmutable de estados de incidencias y órdenes de trabajo (D-061 / D-077).
 */
class MantenimientoHistorialEstado
{
    private ?int $id;
    private string $entidadTipo;
    private int $entidadId;
    private string $estadoAnterior;
    private string $estadoNuevo;
    private ?string $motivo;
    private int $actorId;
    private ?string $cambiadoEn;
    private ?string $actorNombre = null;

    public function __construct(
        ?int $id,
        string $entidadTipo,
        int $entidadId,
        string $estadoAnterior,
        string $estadoNuevo,
        ?string $motivo,
        int $actorId,
        ?string $cambiadoEn = null
    ) {
        $this->id = $id;
        $this->entidadTipo = $entidadTipo;
        $this->entidadId = $entidadId;
        $this->estadoAnterior = $estadoAnterior;
        $this->estadoNuevo = $estadoNuevo;
        $this->motivo = $motivo;
        $this->actorId = $actorId;
        $this->cambiadoEn = $cambiadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerEntidadTipo(): string { return $this->entidadTipo; }
    public function obtenerEntidadId(): int { return $this->entidadId; }
    public function obtenerEstadoAnterior(): string { return $this->estadoAnterior; }
    public function obtenerEstadoNuevo(): string { return $this->estadoNuevo; }
    public function obtenerMotivo(): ?string { return $this->motivo; }
    public function obtenerActorId(): int { return $this->actorId; }
    public function obtenerCambiadoEn(): ?string { return $this->cambiadoEn; }

    public function obtenerActorNombre(): ?string { return $this->actorNombre; }
    public function fijarActorNombre(?string $nombre): void { $this->actorNombre = $nombre; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'entidad_tipo' => $this->entidadTipo,
            'entidad_id' => $this->entidadId,
            'estado_anterior' => $this->estadoAnterior,
            'estado_nuevo' => $this->estadoNuevo,
            'motivo' => $this->motivo,
            'actor_id' => $this->actorId,
            'cambiado_en' => $this->cambiadoEn,
            'actor_nombre' => $this->actorNombre,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        $h = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['entidad_tipo'] ?? 'INCIDENCIA'),
            (int) ($datos['entidad_id'] ?? 0),
            (string) ($datos['estado_anterior'] ?? ''),
            (string) ($datos['estado_nuevo'] ?? ''),
            isset($datos['motivo']) ? (string) $datos['motivo'] : null,
            (int) ($datos['actor_id'] ?? 0),
            isset($datos['cambiado_en']) ? (string) $datos['cambiado_en'] : null
        );

        if (isset($datos['actor_nombre'])) {
            $h->fijarActorNombre((string) $datos['actor_nombre']);
        }

        return $h;
    }
}
