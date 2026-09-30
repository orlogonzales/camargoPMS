<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Tarifas Soberanas de Alojamiento por Noche.
 *
 * Principio Vinculante:
 * UNIDAD ≠ TARIFA. Las tarifas habitacionales se gestionan con vigencia temporal
 * y jerarquía de resolución (UNIDAD > TIPO_UNIDAD > PROPIEDAD).
 */
class TarifaAlojamiento
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public function __construct(
        private ?int $id,
        private int $propiedadId,
        private string $ambitoTipo,
        private ?int $tipoUnidadId,
        private ?int $unidadId,
        private string $nombre,
        private string $precioNoche,
        private string $vigenciaDesde,
        private ?string $vigenciaHasta = null,
        private string $monedaCodigo = 'PEN',
        private string $estado = self::ESTADO_ACTIVO,
        private ?int $creadoPorActorId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerAmbitoTipo(): string
    {
        return $this->ambitoTipo;
    }

    public function obtenerTipoUnidadId(): ?int
    {
        return $this->tipoUnidadId;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerPrecioNoche(): string
    {
        return $this->precioNoche;
    }

    public function obtenerVigenciaDesde(): string
    {
        return $this->vigenciaDesde;
    }

    public function obtenerVigenciaHasta(): ?string
    {
        return $this->vigenciaHasta;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function esVigenteEnFecha(string $fecha): bool
    {
        if ($this->estado !== self::ESTADO_ACTIVO) {
            return false;
        }

        if ($fecha < $this->vigenciaDesde) {
            return false;
        }

        if ($this->vigenciaHasta !== null && $fecha > $this->vigenciaHasta) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'propiedad_id' => $this->propiedadId,
            'ambito_tipo' => $this->ambitoTipo,
            'tipo_unidad_id' => $this->tipoUnidadId,
            'unidad_id' => $this->unidadId,
            'nombre' => $this->nombre,
            'precio_noche' => $this->precioNoche,
            'vigencia_desde' => $this->vigenciaDesde,
            'vigencia_hasta' => $this->vigenciaHasta,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            propiedadId: (int) ($datos['propiedad_id'] ?? 0),
            ambitoTipo: (string) ($datos['ambito_tipo'] ?? AmbitoTarifaAlojamiento::TIPO_UNIDAD),
            tipoUnidadId: isset($datos['tipo_unidad_id']) && $datos['tipo_unidad_id'] !== null ? (int) $datos['tipo_unidad_id'] : null,
            unidadId: isset($datos['unidad_id']) && $datos['unidad_id'] !== null ? (int) $datos['unidad_id'] : null,
            nombre: (string) ($datos['nombre'] ?? ''),
            precioNoche: (string) ($datos['precio_noche'] ?? '0.0000'),
            vigenciaDesde: (string) ($datos['vigencia_desde'] ?? ''),
            vigenciaHasta: isset($datos['vigencia_hasta']) && trim((string) $datos['vigencia_hasta']) !== '' ? (string) $datos['vigencia_hasta'] : null,
            monedaCodigo: (string) ($datos['moneda_codigo'] ?? 'PEN'),
            estado: (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            creadoPorActorId: isset($datos['creado_por_actor_id']) ? (int) $datos['creado_por_actor_id'] : null,
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
