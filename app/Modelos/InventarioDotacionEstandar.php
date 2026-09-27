<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una dotación reglamentaria esperada para una tipología o unidad específica.
 * 
 * Reglas vinculantes (D-078):
 * - ESTÁNDAR vs REALIDAD: Esta tabla define lo que la unidad DEBERÍA tener.
 * - La realidad se deriva dinámicamente de activos asignados y existencias en la ubicación UNIDAD.
 * - Exclusión XOR: tipoUnidadId NOT NULL xor unidadId NOT NULL.
 */
class InventarioDotacionEstandar
{
    private ?int $id;
    private ?int $tipoUnidadId;
    private ?int $unidadId;
    private int $articuloId;
    private string $cantidadEstandar;
    private ?string $notas;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Proyecciones
    private ?string $articuloSku = null;
    private ?string $articuloNombre = null;
    private ?string $articuloCategoria = null;
    private ?string $unidadMedidaSimbolo = null;
    private ?string $tipoUnidadNombre = null;
    private ?string $unidadNumero = null;

    public function __construct(
        ?int $id,
        ?int $tipoUnidadId,
        ?int $unidadId,
        int $articuloId,
        string $cantidadEstandar,
        ?string $notas = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->tipoUnidadId = $tipoUnidadId;
        $this->unidadId = $unidadId;
        $this->articuloId = $articuloId;
        $this->cantidadEstandar = $cantidadEstandar;
        $this->notas = $notas !== null ? trim($notas) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerTipoUnidadId(): ?int
    {
        return $this->tipoUnidadId;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function esPorTipoUnidad(): bool
    {
        return $this->tipoUnidadId !== null;
    }

    public function esPorUnidad(): bool
    {
        return $this->unidadId !== null;
    }

    public function obtenerArticuloId(): int
    {
        return $this->articuloId;
    }

    public function obtenerCantidadEstandar(): string
    {
        return $this->cantidadEstandar;
    }

    public function obtenerNotas(): ?string
    {
        return $this->notas;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarArticuloInfo(string $sku, string $nombre, string $categoria, string $simbolo): void
    {
        $this->articuloSku = $sku;
        $this->articuloNombre = $nombre;
        $this->articuloCategoria = $categoria;
        $this->unidadMedidaSimbolo = $simbolo;
    }

    public function obtenerArticuloSku(): ?string
    {
        return $this->articuloSku;
    }

    public function obtenerArticuloNombre(): ?string
    {
        return $this->articuloNombre;
    }

    public function obtenerArticuloCategoria(): ?string
    {
        return $this->articuloCategoria;
    }

    public function obtenerUnidadMedidaSimbolo(): ?string
    {
        return $this->unidadMedidaSimbolo;
    }

    public function asignarTipoUnidadNombre(?string $nombre): void
    {
        $this->tipoUnidadNombre = $nombre;
    }

    public function obtenerTipoUnidadNombre(): ?string
    {
        return $this->tipoUnidadNombre;
    }

    public function asignarUnidadNumero(?string $numero): void
    {
        $this->unidadNumero = $numero;
    }

    public function obtenerUnidadNumero(): ?string
    {
        return $this->unidadNumero;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'tipo_unidad_id' => $this->tipoUnidadId,
            'tipo_unidad_nombre' => $this->tipoUnidadNombre,
            'unidad_id' => $this->unidadId,
            'unidad_numero' => $this->unidadNumero,
            'articulo_id' => $this->articuloId,
            'articulo_sku' => $this->articuloSku,
            'articulo_nombre' => $this->articuloNombre,
            'articulo_categoria' => $this->articuloCategoria,
            'unidad_medida_simbolo' => $this->unidadMedidaSimbolo,
            'cantidad_estandar' => $this->cantidadEstandar,
            'notas' => $this->notas,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
