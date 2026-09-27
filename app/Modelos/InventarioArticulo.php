<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un artículo o catálogo maestro de inventario.
 * 
 * Reglas vinculantes (D-078):
 * - ARTÍCULO != EXISTENCIA != MOVIMIENTO != ACTIVO INDIVIDUAL.
 * - Categorías:
 *   - CONSUMIBLE_OPERATIVO (amenities de gasto directo).
 *   - LENCERIA_BLANCOS (textiles rotativos).
 *   - REPUESTO_MANTENIMIENTO (piezas para OTs técnicas).
 *   - ACTIVO_SERIALIZABLE (bienes con placa/serie individual sin stock cuantitativo).
 *   - HERRAMIENTA
 *   - OTRO
 * - Cantidades y stock mínimo en DECIMAL(15,4).
 * - Costos referenciales en DECIMAL(15,4). Moneda desacoplada (PEN).
 */
class InventarioArticulo
{
    public const CAT_CONSUMIBLE_OPERATIVO = 'CONSUMIBLE_OPERATIVO';
    public const CAT_LENCERIA_BLANCOS = 'LENCERIA_BLANCOS';
    public const CAT_REPUESTO_MANTENIMIENTO = 'REPUESTO_MANTENIMIENTO';
    public const CAT_ACTIVO_SERIALIZABLE = 'ACTIVO_SERIALIZABLE';
    public const CAT_HERRAMIENTA = 'HERRAMIENTA';
    public const CAT_OTRO = 'OTRO';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private string $codigoSku;
    private string $nombre;
    private ?string $descripcion;
    private string $categoria;
    private int $unidadMedidaId;
    private string $costoReferencial;
    private string $monedaCodigo;
    private string $stockMinimoAlerta;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Proyecciones
    private ?string $unidadMedidaCodigo = null;
    private ?string $unidadMedidaNombre = null;
    private ?string $unidadMedidaSimbolo = null;
    private bool $admiteDecimales = false;
    private string $stockTotalCalculado = '0.0000';

    public function __construct(
        ?int $id,
        string $codigoSku,
        string $nombre,
        ?string $descripcion,
        string $categoria,
        int $unidadMedidaId,
        string $costoReferencial = '0.0000',
        string $monedaCodigo = 'PEN',
        string $stockMinimoAlerta = '0.0000',
        string $estado = self::ESTADO_ACTIVO,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigoSku = trim($codigoSku);
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->categoria = strtoupper(trim($categoria));
        $this->unidadMedidaId = $unidadMedidaId;
        $this->costoReferencial = $costoReferencial;
        $this->monedaCodigo = strtoupper(trim($monedaCodigo));
        $this->stockMinimoAlerta = $stockMinimoAlerta;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigoSku(): string
    {
        return $this->codigoSku;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerCategoria(): string
    {
        return $this->categoria;
    }

    public function esSerializable(): bool
    {
        return $this->categoria === self::CAT_ACTIVO_SERIALIZABLE;
    }

    public function esCuantificable(): bool
    {
        return $this->categoria !== self::CAT_ACTIVO_SERIALIZABLE;
    }

    public function esLenceria(): bool
    {
        return $this->categoria === self::CAT_LENCERIA_BLANCOS;
    }

    public function esRepuesto(): bool
    {
        return $this->categoria === self::CAT_REPUESTO_MANTENIMIENTO;
    }

    public function esConsumible(): bool
    {
        return $this->categoria === self::CAT_CONSUMIBLE_OPERATIVO;
    }

    public function obtenerUnidadMedidaId(): int
    {
        return $this->unidadMedidaId;
    }

    public function obtenerCostoReferencial(): string
    {
        return $this->costoReferencial;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerStockMinimoAlerta(): string
    {
        return $this->stockMinimoAlerta;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarUnidadMedidaInfo(string $codigo, string $nombre, string $simbolo, bool $admiteDecimales): void
    {
        $this->unidadMedidaCodigo = $codigo;
        $this->unidadMedidaNombre = $nombre;
        $this->unidadMedidaSimbolo = $simbolo;
        $this->admiteDecimales = $admiteDecimales;
    }

    public function obtenerUnidadMedidaCodigo(): ?string
    {
        return $this->unidadMedidaCodigo;
    }

    public function obtenerUnidadMedidaNombre(): ?string
    {
        return $this->unidadMedidaNombre;
    }

    public function obtenerUnidadMedidaSimbolo(): ?string
    {
        return $this->unidadMedidaSimbolo;
    }

    public function admiteDecimales(): bool
    {
        return $this->admiteDecimales;
    }

    public function asignarStockTotalCalculado(string $stock): void
    {
        $this->stockTotalCalculado = $stock;
    }

    public function obtenerStockTotalCalculado(): string
    {
        return $this->stockTotalCalculado;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo_sku' => $this->codigoSku,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'categoria' => $this->categoria,
            'es_serializable' => $this->esSerializable() ? 1 : 0,
            'unidad_medida_id' => $this->unidadMedidaId,
            'unidad_medida_codigo' => $this->unidadMedidaCodigo,
            'unidad_medida_nombre' => $this->unidadMedidaNombre,
            'unidad_medida_simbolo' => $this->unidadMedidaSimbolo,
            'admite_decimales' => $this->admiteDecimales ? 1 : 0,
            'costo_referencial' => $this->costoReferencial,
            'moneda_codigo' => $this->monedaCodigo,
            'stock_minimo_alerta' => $this->stockMinimoAlerta,
            'stock_total' => $this->stockTotalCalculado,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
