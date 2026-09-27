<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa una modalidad de cobro y tarificación de servicios.
 */
class ModalidadCobroServicio
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private ?string $unidadMedidaSugerida;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        ?string $unidadMedidaSugerida = null,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->unidadMedidaSugerida = $unidadMedidaSugerida !== null ? trim($unidadMedidaSugerida) : null;
        $this->estado = trim(strtoupper($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
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

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerUnidadMedidaSugerida(): ?string
    {
        return $this->unidadMedidaSugerida;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActiva(): bool
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

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            isset($datos['unidad_medida_sugerida']) ? (string) $datos['unidad_medida_sugerida'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'unidad_medida_sugerida' => $this->unidadMedidaSugerida,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
