<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una unidad de medida normalizada para inventario.
 */
class InventarioUnidadMedida
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private string $simbolo;
    private bool $admiteDecimales;
    private ?string $creadoEn;

    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        string $simbolo,
        bool $admiteDecimales = false,
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim($codigo);
        $this->nombre = trim($nombre);
        $this->simbolo = trim($simbolo);
        $this->admiteDecimales = $admiteDecimales;
        $this->creadoEn = $creadoEn;
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

    public function obtenerSimbolo(): string
    {
        return $this->simbolo;
    }

    public function admiteDecimales(): bool
    {
        return $this->admiteDecimales;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'simbolo' => $this->simbolo,
            'admite_decimales' => $this->admiteDecimales ? 1 : 0,
            'creado_en' => $this->creadoEn,
        ];
    }
}
