<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una Provincia territorial (INEI UBIGEO Nivel 2).
 */
final class Provincia
{
    private ?int $id;
    private int $departamentoId;
    private string $codigoUbigeo;
    private string $nombre;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        int $departamentoId,
        string $codigoUbigeo,
        string $nombre,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->departamentoId = $departamentoId;
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
            (int) ($datos['departamento_id'] ?? 0),
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

    public function obtenerDepartamentoId(): int
    {
        return $this->departamentoId;
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
            'departamento_id' => $this->departamentoId,
            'codigo_ubigeo' => $this->codigoUbigeo,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
