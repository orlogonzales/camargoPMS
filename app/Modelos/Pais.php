<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un país y su nacionalidad asociada.
 *
 * Estructura pura de datos de dominio; no ejecuta SQL ni interactúa con PDO.
 */
final class Pais
{
    private ?int $id;
    private string $codigoIso2;
    private string $codigoIso3;
    private string $nombre;
    private string $nacionalidad;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        string $codigoIso2,
        string $codigoIso3,
        string $nombre,
        string $nacionalidad,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigoIso2 = strtoupper(trim($codigoIso2));
        $this->codigoIso3 = strtoupper(trim($codigoIso3));
        $this->nombre = trim($nombre);
        $this->nacionalidad = trim($nacionalidad);
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    /**
     * Instancia una entidad a partir de una fila asociativa de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo_iso2'] ?? ''),
            (string) ($datos['codigo_iso3'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            (string) ($datos['nacionalidad'] ?? ''),
            (bool) ($datos['activo'] ?? true),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigoIso2(): string
    {
        return $this->codigoIso2;
    }

    public function obtenerCodigoIso3(): string
    {
        return $this->codigoIso3;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerNacionalidad(): string
    {
        return $this->nacionalidad;
    }

    public function esActivo(): bool
    {
        return $this->activo;
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
     * Convierte la entidad a un arreglo asociativo estándar.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo_iso2' => $this->codigoIso2,
            'codigo_iso3' => $this->codigoIso3,
            'nombre' => $this->nombre,
            'nacionalidad' => $this->nacionalidad,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
