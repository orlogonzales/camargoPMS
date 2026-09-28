<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un suministro o servicio periódico (SUMINISTROS-1 / D-081).
 *
 * Desacoplamiento ontológico:
 * SUMINISTRO != MEDIDOR != LECTURA != TARIFA != CONSUMO VALORIZADO != CARGO != PAGO
 */
class Suministro
{
    public const MODALIDAD_MEDIDO = 'MEDIDO';
    public const MODALIDAD_FIJO_PERIODICO = 'FIJO_PERIODICO';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private string $nombre,
        private string $modalidad,
        private string $unidadMedida,
        private ?string $descripcion = null,
        private bool $permiteRollover = false,
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            (string) ($datos['modalidad'] ?? self::MODALIDAD_MEDIDO),
            (string) ($datos['unidad_medida'] ?? ''),
            isset($datos['descripcion']) && $datos['descripcion'] !== '' ? (string) $datos['descripcion'] : null,
            !empty($datos['permite_rollover']),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'modalidad' => $this->modalidad,
            'unidad_medida' => $this->unidadMedida,
            'descripcion' => $this->descripcion,
            'permite_rollover' => $this->permiteRollover,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
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

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerModalidad(): string
    {
        return $this->modalidad;
    }

    public function obtenerUnidadMedida(): string
    {
        return $this->unidadMedida;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function permiteRollover(): bool
    {
        return $this->permiteRollover;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function esMedido(): bool
    {
        return $this->modalidad === self::MODALIDAD_MEDIDO;
    }

    public function esFijoPeriodico(): bool
    {
        return $this->modalidad === self::MODALIDAD_FIJO_PERIODICO;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }
}
