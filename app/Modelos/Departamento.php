<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un Departamento territorial (INEI UBIGEO Nivel 1).
 */
final class Departamento
{
    private ?int $id;
    private int $paisId;
    private string $codigoUbigeo;
    private string $nombre;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        int $paisId,
        string $codigoUbigeo,
        string $nombre,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->paisId = $paisId;
        $this->codigoUbigeo = trim($codigoUbigeo);
        $this->nombre = trim($nombre);
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['pais_id'] ?? 1),
            (string) ($datos['codigo_ubigeo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            (bool) ($datos['activo'] ?? true),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPaisId(): int
    {
        return $this->paisId;
    }

    public function obtenerCodigoUbigeo(): string
    {
        return $this->codigoUbigeo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'pais_id' => $this->paisId,
            'codigo_ubigeo' => $this->codigoUbigeo,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
