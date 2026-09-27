<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa la proyección materializada de existencias por artículo y ubicación.
 * 
 * Reglas vinculantes (D-078):
 * - cantidad_actual es una proyección operacional para lecturas ágiles y SELECT ... FOR UPDATE.
 * - La verdad soberana inmutable reside estrictamente en inventario_movimientos.
 * - cantidad_actual >= 0 blindado en DDL y en servicio.
 * - Cero mutaciones vía CRUD directo.
 */
class InventarioExistencia
{
    private ?int $id;
    private int $articuloId;
    private int $ubicacionId;
    private string $cantidadActual;
    private string $cantidadReservada;
    private ?string $actualizadoEn;

    // Proyecciones
    private ?string $articuloSku = null;
    private ?string $articuloNombre = null;
    private ?string $articuloCategoria = null;
    private ?string $unidadMedidaSimbolo = null;
    private bool $admiteDecimales = false;
    private ?string $ubicacionCodigo = null;
    private ?string $ubicacionNombre = null;
    private ?string $ubicacionTipo = null;
    private ?string $propiedadNombre = null;

    public function __construct(
        ?int $id,
        int $articuloId,
        int $ubicacionId,
        string $cantidadActual = '0.0000',
        string $cantidadReservada = '0.0000',
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->articuloId = $articuloId;
        $this->ubicacionId = $ubicacionId;
        $this->cantidadActual = $cantidadActual;
        $this->cantidadReservada = $cantidadReservada;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerArticuloId(): int
    {
        return $this->articuloId;
    }

    public function obtenerUbicacionId(): int
    {
        return $this->ubicacionId;
    }

    public function obtenerCantidadActual(): string
    {
        return $this->cantidadActual;
    }

    public function obtenerCantidadReservada(): string
    {
        return $this->cantidadReservada;
    }

    public function obtenerCantidadDisponible(): string
    {
        return bcsub($this->cantidadActual, $this->cantidadReservada, 4);
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarArticuloInfo(string $sku, string $nombre, string $categoria, string $simbolo, bool $admiteDecimales): void
    {
        $this->articuloSku = $sku;
        $this->articuloNombre = $nombre;
        $this->articuloCategoria = $categoria;
        $this->unidadMedidaSimbolo = $simbolo;
        $this->admiteDecimales = $admiteDecimales;
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

    public function admiteDecimales(): bool
    {
        return $this->admiteDecimales;
    }

    public function asignarUbicacionInfo(string $codigo, string $nombre, string $tipo, ?string $propiedadNombre = null): void
    {
        $this->ubicacionCodigo = $codigo;
        $this->ubicacionNombre = $nombre;
        $this->ubicacionTipo = $tipo;
        $this->propiedadNombre = $propiedadNombre;
    }

    public function obtenerUbicacionCodigo(): ?string
    {
        return $this->ubicacionCodigo;
    }

    public function obtenerUbicacionNombre(): ?string
    {
        return $this->ubicacionNombre;
    }

    public function obtenerUbicacionTipo(): ?string
    {
        return $this->ubicacionTipo;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'articulo_id' => $this->articuloId,
            'articulo_sku' => $this->articuloSku,
            'articulo_nombre' => $this->articuloNombre,
            'articulo_categoria' => $this->articuloCategoria,
            'unidad_medida_simbolo' => $this->unidadMedidaSimbolo,
            'admite_decimales' => $this->admiteDecimales ? 1 : 0,
            'ubicacion_id' => $this->ubicacionId,
            'ubicacion_codigo' => $this->ubicacionCodigo,
            'ubicacion_nombre' => $this->ubicacionNombre,
            'ubicacion_tipo' => $this->ubicacionTipo,
            'propiedad_nombre' => $this->propiedadNombre,
            'cantidad_actual' => $this->cantidadActual,
            'cantidad_reservada' => $this->cantidadReservada,
            'cantidad_disponible' => $this->obtenerCantidadDisponible(),
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
