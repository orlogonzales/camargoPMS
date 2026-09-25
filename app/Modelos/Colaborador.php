<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad central de dominio que representa la identidad laboral estable de una Persona.
 *
 * Principio Vinculante:
 * PERSONA ≠ COLABORADOR ≠ EPISODIO LABORAL ≠ CARGO ≠ USUARIO ≠ ROL DEL SISTEMA
 */
final class Colaborador
{
    private ?int $id;
    private int $personaId;
    private string $codigo;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relaciones enriquecidas
    private ?Persona $persona;
    /** @var array<int, EpisodioLaboral> */
    private array $episodios;

    /**
     * @param int|null $id
     * @param int $personaId
     * @param string $codigo
     * @param string $estado
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     * @param Persona|null $persona
     * @param array<int, EpisodioLaboral> $episodios
     */
    public function __construct(
        ?int $id,
        int $personaId,
        string $codigo,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?Persona $persona = null,
        array $episodios = []
    ) {
        $this->id = $id;
        $this->personaId = $personaId;
        $this->codigo = strtoupper(trim($codigo));
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->persona = $persona;
        $this->episodios = $episodios;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['persona_id'] ?? 0),
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerPersona(): ?Persona
    {
        return $this->persona;
    }

    public function asignarPersona(?Persona $persona): void
    {
        $this->persona = $persona;
    }

    /**
     * @return array<int, EpisodioLaboral>
     */
    public function obtenerEpisodios(): array
    {
        return $this->episodios;
    }

    /**
     * @param array<int, EpisodioLaboral> $episodios
     */
    public function asignarEpisodios(array $episodios): void
    {
        $this->episodios = $episodios;
    }

    /**
     * Obtiene el episodio laboral actualmente abierto y activo del colaborador.
     *
     * @return EpisodioLaboral|null
     */
    public function obtenerEpisodioAbierto(): ?EpisodioLaboral
    {
        foreach ($this->episodios as $episodio) {
            if ($episodio->esAbierto()) {
                return $episodio;
            }
        }

        return null;
    }

    /**
     * Determina si el colaborador tiene actualmente un vínculo laboral activo abierto.
     *
     * @return bool
     */
    public function tieneEpisodioAbierto(): bool
    {
        return $this->obtenerEpisodioAbierto() !== null;
    }

    /**
     * Obtiene el cargo actualmente vigente del colaborador derivado de su episodio abierto.
     * Evita almacenar fuentes de verdad duplicadas en base de datos.
     *
     * @return Cargo|null
     */
    public function obtenerCargoActual(): ?Cargo
    {
        $episodioAbierto = $this->obtenerEpisodioAbierto();
        if (!$episodioAbierto) {
            return null;
        }

        $cargoVigente = $episodioAbierto->obtenerCargoVigente();
        return $cargoVigente ? $cargoVigente->obtenerCargo() : null;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'codigo' => $this->codigo,
            'estado' => $this->estado,
            'tiene_episodio_abierto' => $this->tieneEpisodioAbierto(),
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
