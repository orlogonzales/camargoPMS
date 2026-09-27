<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un reporte o ticket de incidencia técnica o desperfecto físico.
 * 
 * Reglas vinculantes (D-077):
 * - INCIDENCIA != ORDEN DE TRABAJO != BLOQUEO OPERATIVO.
 * - Una incidencia por sí misma JAMÁS bloquea una unidad física ni genera registros en inventario_diario_unidades.
 * - Severidad: BAJA, MEDIA, ALTA, CRITICA.
 * - Ciclo de vida: REPORTADA -> EN_EVALUACION -> CONVERTIDA_A_ORDEN | RESUELTA_DIRECTA | DESESTIMADA.
 */
class Incidencia
{
    private ?int $id;
    private string $codigo;
    private int $propiedadId;
    private ?int $unidadId;
    private int $reportadoPorPersonaId;
    private string $categoria;
    private string $severidad;
    private string $titulo;
    private string $descripcion;
    private ?string $ubicacionDetallada;
    private string $estado;
    private ?string $motivoCierre;
    private int $creadoPorActorId;
    private ?int $cerradoPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;
    private ?string $resueltoEn;

    // Proyecciones y datos auxiliares
    private ?string $propiedadNombre = null;
    private ?string $unidadNumero = null;
    private ?string $unidadNombre = null;
    private ?string $reportadoPorNombre = null;
    private ?string $reportadoPorDocumento = null;
    private int $ordenesAsociadasCount = 0;

    public function __construct(
        ?int $id,
        string $codigo,
        int $propiedadId,
        ?int $unidadId,
        int $reportadoPorPersonaId,
        string $categoria,
        string $severidad,
        string $titulo,
        string $descripcion,
        ?string $ubicacionDetallada,
        string $estado,
        ?string $motivoCierre,
        int $creadoPorActorId,
        ?int $cerradoPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?string $resueltoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->propiedadId = $propiedadId;
        $this->unidadId = $unidadId;
        $this->reportadoPorPersonaId = $reportadoPorPersonaId;
        $this->categoria = $categoria;
        $this->severidad = $severidad;
        $this->titulo = $titulo;
        $this->descripcion = $descripcion;
        $this->ubicacionDetallada = $ubicacionDetallada;
        $this->estado = $estado;
        $this->motivoCierre = $motivoCierre;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->cerradoPorActorId = $cerradoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->resueltoEn = $resueltoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerPropiedadId(): int { return $this->propiedadId; }
    public function obtenerUnidadId(): ?int { return $this->unidadId; }
    public function obtenerReportadoPorPersonaId(): int { return $this->reportadoPorPersonaId; }
    public function obtenerCategoria(): string { return $this->categoria; }
    public function obtenerSeveridad(): string { return $this->severidad; }
    public function obtenerTitulo(): string { return $this->titulo; }
    public function obtenerDescripcion(): string { return $this->descripcion; }
    public function obtenerUbicacionDetallada(): ?string { return $this->ubicacionDetallada; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerMotivoCierre(): ?string { return $this->motivoCierre; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCerradoPorActorId(): ?int { return $this->cerradoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }
    public function obtenerResueltoEn(): ?string { return $this->resueltoEn; }

    public function obtenerPropiedadNombre(): ?string { return $this->propiedadNombre; }
    public function fijarPropiedadNombre(?string $nombre): void { $this->propiedadNombre = $nombre; }

    public function obtenerUnidadNumero(): ?string { return $this->unidadNumero; }
    public function fijarUnidadNumero(?string $numero): void { $this->unidadNumero = $numero; }

    public function obtenerUnidadNombre(): ?string { return $this->unidadNombre; }
    public function fijarUnidadNombre(?string $nombre): void { $this->unidadNombre = $nombre; }

    public function obtenerReportadoPorNombre(): ?string { return $this->reportadoPorNombre; }
    public function fijarReportadoPorNombre(?string $nombre): void { $this->reportadoPorNombre = $nombre; }

    public function obtenerReportadoPorDocumento(): ?string { return $this->reportadoPorDocumento; }
    public function fijarReportadoPorDocumento(?string $doc): void { $this->reportadoPorDocumento = $doc; }

    public function obtenerOrdenesAsociadasCount(): int { return $this->ordenesAsociadasCount; }
    public function fijarOrdenesAsociadasCount(int $count): void { $this->ordenesAsociadasCount = $count; }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, ['REPORTADA', 'EN_EVALUACION'], true);
    }

    public function estaCerrada(): bool
    {
        return in_array($this->estado, ['CONVERTIDA_A_ORDEN', 'RESUELTA_DIRECTA', 'DESESTIMADA'], true);
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'reportado_por_persona_id' => $this->reportadoPorPersonaId,
            'categoria' => $this->categoria,
            'severidad' => $this->severidad,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'ubicacion_detallada' => $this->ubicacionDetallada,
            'estado' => $this->estado,
            'motivo_cierre' => $this->motivoCierre,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'cerrado_por_actor_id' => $this->cerradoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'resuelto_en' => $this->resueltoEn,
            'propiedad_nombre' => $this->propiedadNombre,
            'unidad_numero' => $this->unidadNumero,
            'unidad_nombre' => $this->unidadNombre,
            'reportado_por_nombre' => $this->reportadoPorNombre,
            'reportado_por_documento' => $this->reportadoPorDocumento,
            'ordenes_asociadas_count' => $this->ordenesAsociadasCount,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        $inc = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['propiedad_id'] ?? 0),
            isset($datos['unidad_id']) && $datos['unidad_id'] !== null ? (int) $datos['unidad_id'] : null,
            (int) ($datos['reportado_por_persona_id'] ?? 0),
            (string) ($datos['categoria'] ?? 'OTRO'),
            (string) ($datos['severidad'] ?? 'MEDIA'),
            (string) ($datos['titulo'] ?? ''),
            (string) ($datos['descripcion'] ?? ''),
            isset($datos['ubicacion_detallada']) ? (string) $datos['ubicacion_detallada'] : null,
            (string) ($datos['estado'] ?? 'REPORTADA'),
            isset($datos['motivo_cierre']) ? (string) $datos['motivo_cierre'] : null,
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['cerrado_por_actor_id']) && $datos['cerrado_por_actor_id'] !== null ? (int) $datos['cerrado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            isset($datos['resuelto_en']) ? (string) $datos['resuelto_en'] : null
        );

        if (isset($datos['propiedad_nombre'])) {
            $inc->fijarPropiedadNombre((string) $datos['propiedad_nombre']);
        }
        if (isset($datos['unidad_numero'])) {
            $inc->fijarUnidadNumero((string) $datos['unidad_numero']);
        }
        if (isset($datos['unidad_nombre'])) {
            $inc->fijarUnidadNombre((string) $datos['unidad_nombre']);
        }
        if (isset($datos['reportado_por_nombre'])) {
            $inc->fijarReportadoPorNombre((string) $datos['reportado_por_nombre']);
        }
        if (isset($datos['reportado_por_documento'])) {
            $inc->fijarReportadoPorDocumento((string) $datos['reportado_por_documento']);
        }
        if (isset($datos['ordenes_asociadas_count'])) {
            $inc->fijarOrdenesAsociadasCount((int) $datos['ordenes_asociadas_count']);
        }

        return $inc;
    }
}
