<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa el estado físico de higiene y limpieza de una unidad (1:1).
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingEstadoLimpieza
{
    public const ESTADO_SUCIA = 'SUCIA';
    public const ESTADO_EN_LIMPIEZA = 'EN_LIMPIEZA';
    public const ESTADO_LIMPIA_POR_INSPECCIONAR = 'LIMPIA_POR_INSPECCIONAR';
    public const ESTADO_LIMPIA_INSPECCIONADA = 'LIMPIA_INSPECCIONADA';
    public const ESTADO_RETOQUE_REQUERIDO = 'RETOQUE_REQUERIDO';

    public function __construct(
        private int $unidadId,
        private string $estadoLimpieza = self::ESTADO_SUCIA,
        private ?int $tareaActivaId = null,
        private ?string $ultimaLimpiezaEn = null,
        private ?string $ultimaInspeccionEn = null,
        private ?int $inspeccionadoPorActorId = null,
        private ?string $observaciones = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            (int) ($datos['unidad_id'] ?? 0),
            (string) ($datos['estado_limpieza'] ?? self::ESTADO_SUCIA),
            isset($datos['tarea_activa_id']) && $datos['tarea_activa_id'] !== null ? (int) $datos['tarea_activa_id'] : null,
            $datos['ultima_limpieza_en'] ?? null,
            $datos['ultima_inspeccion_en'] ?? null,
            isset($datos['inspeccionado_por_actor_id']) && $datos['inspeccionado_por_actor_id'] !== null ? (int) $datos['inspeccionado_por_actor_id'] : null,
            $datos['observaciones'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'unidad_id' => $this->unidadId,
            'estado_limpieza' => $this->estadoLimpieza,
            'tarea_activa_id' => $this->tareaActivaId,
            'ultima_limpieza_en' => $this->ultimaLimpiezaEn,
            'ultima_inspeccion_en' => $this->ultimaInspeccionEn,
            'inspeccionado_por_actor_id' => $this->inspeccionadoPorActorId,
            'observaciones' => $this->observaciones,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerEstadoLimpieza(): string
    {
        return $this->estadoLimpieza;
    }

    public function obtenerTareaActivaId(): ?int
    {
        return $this->tareaActivaId;
    }

    public function obtenerUltimaLimpiezaEn(): ?string
    {
        return $this->ultimaLimpiezaEn;
    }

    public function obtenerUltimaInspeccionEn(): ?string
    {
        return $this->ultimaInspeccionEn;
    }

    public function obtenerInspeccionadoPorActorId(): ?int
    {
        return $this->inspeccionadoPorActorId;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function estaInspeccionada(): bool
    {
        return $this->estadoLimpieza === self::ESTADO_LIMPIA_INSPECCIONADA;
    }
}
