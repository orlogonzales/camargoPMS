<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una tipología arquitectónica de unidad alojable.
 *
 * Catálogo administrable de tipos de unidad (ej. DEPARTAMENTO, HABITACION, CASA, SUITE, BUNGALOW).
 */
class TipoUnidad
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id = null,
        string $codigo = '',
        string $nombre = '',
        ?string $descripcion = null,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim($codigo);
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function fijarCodigo(string $codigo): self
    {
        $this->codigo = trim($codigo);
        return $this;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function fijarNombre(string $nombre): self
    {
        $this->nombre = trim($nombre);
        return $this;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function fijarDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        return $this;
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function fijarActivo(bool $activo): self
    {
        $this->activo = $activo;
        return $this;
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
     * Serializa la entidad a un arreglo asociativo con tipos canónicos.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * Alias de compatibilidad canónica.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return $this->aArreglo();
    }

    /**
     * Alias de compatibilidad canónica.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return $this->aArreglo();
    }

    /**
     * Reconstruye una entidad desde un arreglo de datos de persistencia.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            codigo: (string)($datos['codigo'] ?? ''),
            nombre: (string)($datos['nombre'] ?? ''),
            descripcion: isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string)$datos['descripcion'] : null,
            activo: isset($datos['activo']) ? (bool)$datos['activo'] : true,
            creadoEn: isset($datos['creado_en']) ? (string)$datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string)$datos['actualizado_en'] : null
        );
    }
}
