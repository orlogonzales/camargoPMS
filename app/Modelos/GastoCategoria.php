<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una Categoría Operativa de Gasto.
 * GASTOS-1 / D-086.
 */
class GastoCategoria
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private string $nombre,
        private ?string $descripcion = null,
        private bool $requiereComprobanteFiscal = true,
        private bool $activo = true,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
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

    public function requiereComprobanteFiscal(): bool
    {
        return $this->requiereComprobanteFiscal;
    }

    public function estaActivo(): bool
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

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'requiere_comprobante_fiscal' => $this->requiereComprobanteFiscal ? 1 : 0,
            'activo' => $this->activo ? 1 : 0,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) && $datos['descripcion'] !== '' ? (string) $datos['descripcion'] : null,
            !empty($datos['requiere_comprobante_fiscal']),
            isset($datos['activo']) ? (bool) $datos['activo'] : true,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
