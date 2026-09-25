<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un episodio cronológico de vinculación laboral.
 * Soporta cese y posterior reingreso sin sobreescribir la historia previa.
 */
final class EpisodioLaboral
{
    private ?int $id;
    private int $colaboradorId;
    private string $fechaInicio;
    private ?string $fechaFin;
    private ?string $motivoCese;
    private ?string $observaciones;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /** @var array<int, AsignacionCargo> */
    private array $asignacionesCargos;

    /**
     * @param int|null $id
     * @param int $colaboradorId
     * @param string $fechaInicio
     * @param string|null $fechaFin
     * @param string|null $motivoCese
     * @param string|null $observaciones
     * @param string $estado
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     * @param array<int, AsignacionCargo> $asignacionesCargos
     */
    public function __construct(
        ?int $id,
        int $colaboradorId,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?string $motivoCese = null,
        ?string $observaciones = null,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        array $asignacionesCargos = []
    ) {
        $this->id = $id;
        $this->colaboradorId = $colaboradorId;
        $this->fechaInicio = trim($fechaInicio);
        $this->fechaFin = $fechaFin !== null && trim($fechaFin) !== '' ? trim($fechaFin) : null;
        $this->motivoCese = $motivoCese !== null && trim($motivoCese) !== '' ? trim($motivoCese) : null;
        $this->observaciones = $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->asignacionesCargos = $asignacionesCargos;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['colaborador_id'] ?? 0),
            (string) ($datos['fecha_inicio'] ?? ''),
            isset($datos['fecha_fin']) && $datos['fecha_fin'] !== null ? (string) $datos['fecha_fin'] : null,
            isset($datos['motivo_cese']) && $datos['motivo_cese'] !== null ? (string) $datos['motivo_cese'] : null,
            isset($datos['observaciones']) && $datos['observaciones'] !== null ? (string) $datos['observaciones'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerColaboradorId(): int
    {
        return $this->colaboradorId;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->fechaFin;
    }

    public function obtenerMotivoCese(): ?string
    {
        return $this->motivoCese;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    /**
     * Determina si el episodio laboral se encuentra actualmente abierto y activo.
     *
     * @return bool
     */
    public function esAbierto(): bool
    {
        return $this->fechaFin === null && $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @return array<int, AsignacionCargo>
     */
    public function obtenerAsignacionesCargos(): array
    {
        return $this->asignacionesCargos;
    }

    /**
     * @param array<int, AsignacionCargo> $asignaciones
     */
    public function asignarHistorialCargos(array $asignaciones): void
    {
        $this->asignacionesCargos = $asignaciones;
    }

    /**
     * Obtiene la asignación de cargo actualmente vigente dentro del episodio.
     *
     * @return AsignacionCargo|null
     */
    public function obtenerCargoVigente(): ?AsignacionCargo
    {
        foreach ($this->asignacionesCargos as $asignacion) {
            if ($asignacion->esVigente()) {
                return $asignacion;
            }
        }

        return null;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'colaborador_id' => $this->colaboradorId,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'es_abierto' => $this->esAbierto(),
            'motivo_cese' => $this->motivoCese,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
