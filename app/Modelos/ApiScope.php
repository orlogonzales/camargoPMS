<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Scopes Normalizados de la API.
 */
class ApiScope
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private string $nombre,
        private ?string $descripcion = null,
        private string $modulo = 'reservas',
        private bool $activo = true,
        private ?string $creadoEn = null
    ) {
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

    public function obtenerModulo(): string
    {
        return $this->modulo;
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'modulo' => $this->modulo,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            codigo: (string) ($datos['codigo'] ?? ''),
            nombre: (string) ($datos['nombre'] ?? ''),
            descripcion: isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            modulo: (string) ($datos['modulo'] ?? 'reservas'),
            activo: !empty($datos['activo']),
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
