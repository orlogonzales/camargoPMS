<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa la asignación de un cargo dentro de un episodio laboral.
 * Permite registrar ascensos o cambios de puesto conservando la historia completa.
 */
final class AsignacionCargo
{
    private ?int $id;
    private int $episodioLaboralId;
    private int $cargoId;
    private string $fechaInicio;
    private ?string $fechaFin;
    private ?string $observaciones;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relación enriquecida
    private ?Cargo $cargo;

    public function __construct(
        ?int $id,
        int $episodioLaboralId,
        int $cargoId,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?string $observaciones = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?Cargo $cargo = null
    ) {
        $this->id = $id;
        $this->episodioLaboralId = $episodioLaboralId;
        $this->cargoId = $cargoId;
        $this->fechaInicio = trim($fechaInicio);
        $this->fechaFin = $fechaFin !== null && trim($fechaFin) !== '' ? trim($fechaFin) : null;
        $this->observaciones = $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->cargo = $cargo;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['episodio_laboral_id'] ?? 0),
            (int) ($datos['cargo_id'] ?? 0),
            (string) ($datos['fecha_inicio'] ?? ''),
            isset($datos['fecha_fin']) && $datos['fecha_fin'] !== null ? (string) $datos['fecha_fin'] : null,
            isset($datos['observaciones']) && $datos['observaciones'] !== null ? (string) $datos['observaciones'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerEpisodioLaboralId(): int
    {
        return $this->episodioLaboralId;
    }

    public function obtenerCargoId(): int
    {
        return $this->cargoId;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->fechaFin;
    }

    /**
     * Determina si la asignación de cargo está actualmente vigente (abierta).
     *
     * @return bool
     */
    public function esVigente(): bool
    {
        return $this->fechaFin === null;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerCargo(): ?Cargo
    {
        return $this->cargo;
    }

    public function asignarCargo(?Cargo $cargo): void
    {
        $this->cargo = $cargo;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'episodio_laboral_id' => $this->episodioLaboralId,
            'cargo_id' => $this->cargoId,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'es_vigente' => $this->esVigente(),
            'observaciones' => $this->observaciones,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
