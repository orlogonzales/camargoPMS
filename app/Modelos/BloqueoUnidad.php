<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa un Bloqueo de Unidad (DISPONIBILIDAD-1).
 *
 * Representa el registro maestro administrativo o técnico de un bloqueo temporal
 * que inhabilita comercialmente una unidad física para un rango de fechas determinado.
 */
class BloqueoUnidad
{
    private ?int $id;
    private int $unidadId;
    private string $fechaInicio;
    private string $fechaFin;
    private int $noches;
    private string $motivo;
    private string $tipo;
    private string $estado;
    private ?int $creadoPorActorId;
    private ?int $liberadoPorActorId;
    private ?string $creadoEn;
    private ?string $liberadoEn;

    // Metadatos auxiliares de relaciones
    private ?string $unidadCodigo = null;
    private ?string $unidadNombre = null;
    private ?int $propiedadId = null;
    private ?string $propiedadNombre = null;
    private ?string $creadorNombre = null;
    private ?string $liberadorNombre = null;

    public function __construct(
        ?int $id,
        int $unidadId,
        string $fechaInicio,
        string $fechaFin,
        int $noches,
        string $motivo,
        string $tipo = 'BLOQUEO_MANUAL',
        string $estado = 'ACTIVO',
        ?int $creadoPorActorId = null,
        ?int $liberadoPorActorId = null,
        ?string $creadoEn = null,
        ?string $liberadoEn = null
    ) {
        $this->id = $id;
        $this->unidadId = $unidadId;
        $this->fechaInicio = trim($fechaInicio);
        $this->fechaFin = trim($fechaFin);
        $this->noches = $noches;
        $this->motivo = trim($motivo);
        $this->tipo = in_array(strtoupper(trim($tipo)), ['BLOQUEO_MANUAL', 'MANTENIMIENTO'], true)
            ? strtoupper(trim($tipo))
            : 'BLOQUEO_MANUAL';
        $this->estado = strtoupper(trim($estado)) === 'LIBERADO' ? 'LIBERADO' : 'ACTIVO';
        $this->creadoPorActorId = $creadoPorActorId;
        $this->liberadoPorActorId = $liberadoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->liberadoEn = $liberadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): string
    {
        return $this->fechaFin;
    }

    public function obtenerNoches(): int
    {
        return $this->noches;
    }

    public function obtenerMotivo(): string
    {
        return $this->motivo;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerLiberadoPorActorId(): ?int
    {
        return $this->liberadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerLiberadoEn(): ?string
    {
        return $this->liberadoEn;
    }

    // Setters para relaciones auxiliares
    public function asignarUnidadDatos(string $codigo, string $nombre, ?int $propiedadId = null, ?string $propiedadNombre = null): void
    {
        $this->unidadCodigo = $codigo;
        $this->unidadNombre = $nombre;
        $this->propiedadId = $propiedadId;
        $this->propiedadNombre = $propiedadNombre;
    }

    public function asignarNombresActores(?string $creadorNombre, ?string $liberadorNombre = null): void
    {
        $this->creadorNombre = $creadorNombre;
        $this->liberadorNombre = $liberadorNombre;
    }

    public function obtenerUnidadCodigo(): ?string
    {
        return $this->unidadCodigo;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerPropiedadId(): ?int
    {
        return $this->propiedadId;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function obtenerCreadorNombre(): ?string
    {
        return $this->creadorNombre;
    }

    public function obtenerLiberadorNombre(): ?string
    {
        return $this->liberadorNombre;
    }

    /**
     * Hidrata una entidad BloqueoUnidad desde base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function hidratar(array $datos): self
    {
        $bloqueo = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['unidad_id'] ?? 0),
            (string) ($datos['fecha_inicio'] ?? ''),
            (string) ($datos['fecha_fin'] ?? ''),
            (int) ($datos['noches'] ?? 0),
            (string) ($datos['motivo'] ?? ''),
            (string) ($datos['tipo'] ?? 'BLOQUEO_MANUAL'),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_por_actor_id']) && $datos['creado_por_actor_id'] !== null ? (int) $datos['creado_por_actor_id'] : null,
            isset($datos['liberado_por_actor_id']) && $datos['liberado_por_actor_id'] !== null ? (int) $datos['liberado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['liberado_en']) ? (string) $datos['liberado_en'] : null
        );

        if (isset($datos['unidad_codigo']) || isset($datos['unidad_nombre'])) {
            $bloqueo->asignarUnidadDatos(
                (string) ($datos['unidad_codigo'] ?? ''),
                (string) ($datos['unidad_nombre'] ?? ''),
                isset($datos['propiedad_id']) ? (int) $datos['propiedad_id'] : null,
                isset($datos['propiedad_nombre']) ? (string) $datos['propiedad_nombre'] : null
            );
        }

        if (isset($datos['creador_nombre']) || isset($datos['liberador_nombre'])) {
            $bloqueo->asignarNombresActores(
                isset($datos['creador_nombre']) ? (string) $datos['creador_nombre'] : null,
                isset($datos['liberador_nombre']) ? (string) $datos['liberador_nombre'] : null
            );
        }

        return $bloqueo;
    }

    /**
     * Serializa a arreglo para respuestas JSON o vistas.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'unidad_id' => $this->unidadId,
            'unidad_codigo' => $this->unidadCodigo,
            'unidad_nombre' => $this->unidadNombre,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'noches' => $this->noches,
            'motivo' => $this->motivo,
            'tipo' => $this->tipo,
            'estado' => $this->estado,
            'activo' => $this->estaActivo(),
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creador_nombre' => $this->creadorNombre,
            'liberado_por_actor_id' => $this->liberadoPorActorId,
            'liberador_nombre' => $this->liberadorNombre,
            'creado_en' => $this->creadoEn,
            'liberado_en' => $this->liberadoEn,
        ];
    }

    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
