<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un punto de control del checklist congelado en una tarea.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingChecklistItem
{
    public const RESULTADO_CONFORME = 'CONFORME';
    public const RESULTADO_NO_CONFORME = 'NO_CONFORME';
    public const RESULTADO_NO_APLICA = 'NO_APLICA';

    public function __construct(
        private ?int $id,
        private int $tareaId,
        private string $codigoItemSnapshot,
        private string $categoriaSnapshot,
        private string $descripcionSnapshot,
        private bool $esCriticoSnapshot = false,
        private string $resultado = self::RESULTADO_CONFORME,
        private ?string $observacion = null,
        private ?string $verificadoEn = null,
        private ?int $verificadoPorActorId = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['tarea_id'] ?? 0),
            (string) ($datos['codigo_item_snapshot'] ?? ''),
            (string) ($datos['categoria_snapshot'] ?? 'GENERAL'),
            (string) ($datos['descripcion_snapshot'] ?? ''),
            !empty($datos['es_critico_snapshot']),
            (string) ($datos['resultado'] ?? self::RESULTADO_CONFORME),
            $datos['observacion'] ?? null,
            $datos['verificado_en'] ?? null,
            isset($datos['verificado_por_actor_id']) && $datos['verificado_por_actor_id'] !== null ? (int) $datos['verificado_por_actor_id'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'tarea_id' => $this->tareaId,
            'codigo_item_snapshot' => $this->codigoItemSnapshot,
            'categoria_snapshot' => $this->categoriaSnapshot,
            'descripcion_snapshot' => $this->descripcionSnapshot,
            'es_critico_snapshot' => $this->esCriticoSnapshot ? 1 : 0,
            'resultado' => $this->resultado,
            'observacion' => $this->observacion,
            'verificado_en' => $this->verificadoEn,
            'verificado_por_actor_id' => $this->verificadoPorActorId,
        ];
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerTareaId(): int { return $this->tareaId; }
    public function obtenerCodigoItemSnapshot(): string { return $this->codigoItemSnapshot; }
    public function obtenerCategoriaSnapshot(): string { return $this->categoriaSnapshot; }
    public function obtenerDescripcionSnapshot(): string { return $this->descripcionSnapshot; }
    public function esCriticoSnapshot(): bool { return $this->esCriticoSnapshot; }
    public function obtenerResultado(): string { return $this->resultado; }
    public function obtenerObservacion(): ?string { return $this->observacion; }
    public function obtenerVerificadoEn(): ?string { return $this->verificadoEn; }
    public function obtenerVerificadoPorActorId(): ?int { return $this->verificadoPorActorId; }
}
