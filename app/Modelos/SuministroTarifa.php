<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una tarifa con vigencia histórica y ámbito jerárquico (SUMINISTROS-1 / D-081).
 *
 * Precedencia vinculante:
 * UNIDAD > PROPIEDAD > GLOBAL
 */
class SuministroTarifa
{
    public const AMBITO_GLOBAL = 'GLOBAL';
    public const AMBITO_PROPIEDAD = 'PROPIEDAD';
    public const AMBITO_UNIDAD = 'UNIDAD';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public function __construct(
        private ?int $id,
        private int $suministroId,
        private string $ambitoTipo,
        private ?int $propiedadId,
        private ?int $unidadId,
        private string $vigenciaDesde,
        private ?string $vigenciaHasta,
        private string $valorUnitario,
        private string $monedaCodigo = 'PEN',
        private string $estado = self::ESTADO_ACTIVO,
        private ?int $creadoPorActorId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        // Proyecciones
        private ?string $suministroNombre = null,
        private ?string $propiedadNombre = null,
        private ?string $unidadNombre = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['suministro_id'] ?? 0),
            (string) ($datos['ambito_tipo'] ?? $datos['ambito'] ?? self::AMBITO_GLOBAL),
            isset($datos['propiedad_id']) && $datos['propiedad_id'] !== '' ? (int) $datos['propiedad_id'] : null,
            isset($datos['unidad_id']) && $datos['unidad_id'] !== '' ? (int) $datos['unidad_id'] : null,
            (string) ($datos['vigencia_desde'] ?? $datos['fecha_inicio'] ?? ''),
            isset($datos['vigencia_hasta']) && $datos['vigencia_hasta'] !== '' ? (string) $datos['vigencia_hasta'] : (isset($datos['fecha_fin']) && $datos['fecha_fin'] !== '' ? (string) $datos['fecha_fin'] : null),
            (string) ($datos['valor_unitario'] ?? $datos['precio_unitario'] ?? '0.0000'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['creado_por_actor_id']) ? (int) $datos['creado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            isset($datos['suministro_nombre']) ? (string) $datos['suministro_nombre'] : null,
            isset($datos['propiedad_nombre']) ? (string) $datos['propiedad_nombre'] : null,
            isset($datos['unidad_nombre']) ? (string) $datos['unidad_nombre'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'suministro_id' => $this->suministroId,
            'ambito_tipo' => $this->ambitoTipo,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'vigencia_desde' => $this->vigenciaDesde,
            'vigencia_hasta' => $this->vigenciaHasta,
            'valor_unitario' => $this->valorUnitario,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'suministro_nombre' => $this->suministroNombre,
            'propiedad_nombre' => $this->propiedadNombre,
            'unidad_nombre' => $this->unidadNombre,
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerSuministroId(): int
    {
        return $this->suministroId;
    }

    public function obtenerAmbitoTipo(): string
    {
        return $this->ambitoTipo;
    }

    public function obtenerPropiedadId(): ?int
    {
        return $this->propiedadId;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function obtenerVigenciaDesde(): string
    {
        return $this->vigenciaDesde;
    }

    public function obtenerVigenciaHasta(): ?string
    {
        return $this->vigenciaHasta;
    }

    public function obtenerValorUnitario(): string
    {
        return $this->valorUnitario;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function esGlobal(): bool
    {
        return $this->ambitoTipo === self::AMBITO_GLOBAL;
    }

    public function esDePropiedad(): bool
    {
        return $this->ambitoTipo === self::AMBITO_PROPIEDAD;
    }

    public function esDeUnidad(): bool
    {
        return $this->ambitoTipo === self::AMBITO_UNIDAD;
    }

    public function estaVigenteEn(string $fecha): bool
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

    public function obtenerSuministroNombre(): ?string
    {
        return $this->suministroNombre;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerAmbito(): string
    {
        return $this->ambitoTipo;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->vigenciaDesde;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->vigenciaHasta;
    }

    public function obtenerPrecioUnitario(): string
    {
        return $this->valorUnitario;
    }

    public function esPropiedad(): bool
    {
        return $this->esDePropiedad();
    }

    public function esUnidad(): bool
    {
        return $this->esDeUnidad();
    }
}
