<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un activo serializable individual (Smart TV, frigobar, aire acondicionado).
 * 
 * Reglas vinculantes (D-078):
 * - ACTIVO SERIALIZABLE != STOCK CUANTITATIVO. Cero doble contador en existencias.
 * - Estados: DISPONIBLE, ASIGNADO, EN_MANTENIMIENTO, DE_BAJA.
 * - Cero DELETE físico. Las bajas se justifican formalmente con motivo_baja y actor trazable.
 */
class InventarioActivo
{
    public const ESTADO_DISPONIBLE = 'DISPONIBLE';
    public const ESTADO_ASIGNADO = 'ASIGNADO';
    public const ESTADO_EN_MANTENIMIENTO = 'EN_MANTENIMIENTO';
    public const ESTADO_DE_BAJA = 'DE_BAJA';

    private ?int $id;
    private int $articuloId;
    private string $codigoPlaca;
    private ?string $numeroSerieFabricante;
    private ?string $marca;
    private ?string $modelo;
    private int $propiedadId;
    private int $ubicacionId;
    private string $estado;
    private ?string $fechaAdquisicion;
    private string $costoAdquisicion;
    private string $monedaCodigo;
    private ?string $garantiaVenceEn;
    private ?string $notas;
    private ?string $motivoBaja;
    private ?int $bajaPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Proyecciones
    private ?string $articuloNombre = null;
    private ?string $articuloSku = null;
    private ?string $propiedadNombre = null;
    private ?string $ubicacionCodigo = null;
    private ?string $ubicacionNombre = null;
    private ?string $ubicacionTipo = null;
    private ?string $unidadNumero = null;

    public function __construct(
        ?int $id,
        int $articuloId,
        string $codigoPlaca,
        ?string $numeroSerieFabricante = null,
        ?string $marca = null,
        ?string $modelo = null,
        int $propiedadId = 0,
        int $ubicacionId = 0,
        string $estado = self::ESTADO_DISPONIBLE,
        ?string $fechaAdquisicion = null,
        string $costoAdquisicion = '0.00',
        string $monedaCodigo = 'PEN',
        ?string $garantiaVenceEn = null,
        ?string $notas = null,
        ?string $motivoBaja = null,
        ?int $bajaPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->articuloId = $articuloId;
        $this->codigoPlaca = trim($codigoPlaca);
        $this->numeroSerieFabricante = $numeroSerieFabricante !== null ? trim($numeroSerieFabricante) : null;
        $this->marca = $marca !== null ? trim($marca) : null;
        $this->modelo = $modelo !== null ? trim($modelo) : null;
        $this->propiedadId = $propiedadId;
        $this->ubicacionId = $ubicacionId;
        $this->estado = strtoupper(trim($estado));
        $this->fechaAdquisicion = $fechaAdquisicion;
        $this->costoAdquisicion = $costoAdquisicion;
        $this->monedaCodigo = strtoupper(trim($monedaCodigo));
        $this->garantiaVenceEn = $garantiaVenceEn;
        $this->notas = $notas !== null ? trim($notas) : null;
        $this->motivoBaja = $motivoBaja !== null ? trim($motivoBaja) : null;
        $this->bajaPorActorId = $bajaPorActorId;
        $this->creadoEn = $creadoEn;
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

    public function obtenerCodigoPlaca(): string
    {
        return $this->codigoPlaca;
    }

    public function obtenerNumeroSerieFabricante(): ?string
    {
        return $this->numeroSerieFabricante;
    }

    public function obtenerMarca(): ?string
    {
        return $this->marca;
    }

    public function obtenerModelo(): ?string
    {
        return $this->modelo;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerUbicacionId(): int
    {
        return $this->ubicacionId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaDisponible(): bool
    {
        return $this->estado === self::ESTADO_DISPONIBLE;
    }

    public function estaAsignado(): bool
    {
        return $this->estado === self::ESTADO_ASIGNADO;
    }

    public function estaEnMantenimiento(): bool
    {
        return $this->estado === self::ESTADO_EN_MANTENIMIENTO;
    }

    public function estaDeBaja(): bool
    {
        return $this->estado === self::ESTADO_DE_BAJA;
    }

    public function obtenerFechaAdquisicion(): ?string
    {
        return $this->fechaAdquisicion;
    }

    public function obtenerCostoAdquisicion(): string
    {
        return $this->costoAdquisicion;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerGarantiaVenceEn(): ?string
    {
        return $this->garantiaVenceEn;
    }

    public function obtenerNotas(): ?string
    {
        return $this->notas;
    }

    public function obtenerMotivoBaja(): ?string
    {
        return $this->motivoBaja;
    }

    public function obtenerBajaPorActorId(): ?int
    {
        return $this->bajaPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarArticuloInfo(string $sku, string $nombre): void
    {
        $this->articuloSku = $sku;
        $this->articuloNombre = $nombre;
    }

    public function obtenerArticuloSku(): ?string
    {
        return $this->articuloSku;
    }

    public function obtenerArticuloNombre(): ?string
    {
        return $this->articuloNombre;
    }

    public function asignarPropiedadNombre(?string $nombre): void
    {
        $this->propiedadNombre = $nombre;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function asignarUbicacionInfo(string $codigo, string $nombre, string $tipo, ?string $unidadNumero = null): void
    {
        $this->ubicacionCodigo = $codigo;
        $this->ubicacionNombre = $nombre;
        $this->ubicacionTipo = $tipo;
        $this->unidadNumero = $unidadNumero;
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

    public function obtenerUnidadNumero(): ?string
    {
        return $this->unidadNumero;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'articulo_id' => $this->articuloId,
            'articulo_sku' => $this->articuloSku,
            'articulo_nombre' => $this->articuloNombre,
            'codigo_placa' => $this->codigoPlaca,
            'numero_serie_fabricante' => $this->numeroSerieFabricante,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'ubicacion_id' => $this->ubicacionId,
            'ubicacion_codigo' => $this->ubicacionCodigo,
            'ubicacion_nombre' => $this->ubicacionNombre,
            'ubicacion_tipo' => $this->ubicacionTipo,
            'unidad_numero' => $this->unidadNumero,
            'estado' => $this->estado,
            'fecha_adquisicion' => $this->fechaAdquisicion,
            'costo_adquisicion' => $this->costoAdquisicion,
            'moneda_codigo' => $this->monedaCodigo,
            'garantia_vence_en' => $this->garantiaVenceEn,
            'notas' => $this->notas,
            'motivo_baja' => $this->motivoBaja,
            'baja_por_actor_id' => $this->bajaPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
