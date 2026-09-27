<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una entrada o salida inmutable en el Kardex de inventario.
 * 
 * Reglas vinculantes (D-078):
 * - MOVIMIENTO es la verdad histórica inmutable (append-only). Cero UPDATE o DELETE.
 * - Traslados generan dos patas: TRASLADO_SALIDA y TRASLADO_ENTRADA enlazadas por correlativo_operacion.
 * - SALDO_INICIAL es el único mecanismo legítimo para fijar stock inicial.
 * - Integración con MANTENIMIENTO-1: tipo SALIDA_MANTENIMIENTO con referencia_tipo 'MANTENIMIENTO_ORDEN'.
 * - Cantidad > 0, costos no negativos.
 */
class InventarioMovimiento
{
    public const TIPO_SALDO_INICIAL = 'SALDO_INICIAL';
    public const TIPO_ENTRADA_COMPRA = 'ENTRADA_COMPRA';
    public const TIPO_SALIDA_CONSUMO = 'SALIDA_CONSUMO';
    public const TIPO_SALIDA_MANTENIMIENTO = 'SALIDA_MANTENIMIENTO';
    public const TIPO_TRASLADO_SALIDA = 'TRASLADO_SALIDA';
    public const TIPO_TRASLADO_ENTRADA = 'TRASLADO_ENTRADA';
    public const TIPO_AJUSTE_POSITIVO = 'AJUSTE_POSITIVO';
    public const TIPO_AJUSTE_NEGATIVO = 'AJUSTE_NEGATIVO';
    public const TIPO_REVERSO = 'REVERSO';

    private ?int $id;
    private string $codigo;
    private string $tipoMovimiento;
    private int $articuloId;
    private int $ubicacionId;
    private string $cantidad;
    private string $costoUnitarioHistorico;
    private string $costoTotalHistorico;
    private string $monedaCodigo;
    private ?string $referenciaTipo;
    private ?int $referenciaId;
    private ?int $movimientoReferenciaId;
    private ?string $correlativoOperacion;
    private string $motivo;
    private int $creadoPorActorId;
    private ?string $creadoEn;

    // Proyecciones
    private ?string $articuloSku = null;
    private ?string $articuloNombre = null;
    private ?string $unidadMedidaSimbolo = null;
    private ?string $ubicacionCodigo = null;
    private ?string $ubicacionNombre = null;
    private ?string $actorCodigo = null;
    private ?string $actorNombre = null;

    public function __construct(
        ?int $id,
        string $codigo,
        string $tipoMovimiento,
        int $articuloId,
        int $ubicacionId,
        string $cantidad,
        string $costoUnitarioHistorico = '0.0000',
        string $costoTotalHistorico = '0.00',
        string $monedaCodigo = 'PEN',
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        ?int $movimientoReferenciaId = null,
        ?string $correlativoOperacion = null,
        string $motivo = '',
        int $creadoPorActorId = 0,
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim($codigo);
        $this->tipoMovimiento = strtoupper(trim($tipoMovimiento));
        $this->articuloId = $articuloId;
        $this->ubicacionId = $ubicacionId;
        $this->cantidad = $cantidad;
        $this->costoUnitarioHistorico = $costoUnitarioHistorico;
        $this->costoTotalHistorico = $costoTotalHistorico;
        $this->monedaCodigo = strtoupper(trim($monedaCodigo));
        $this->referenciaTipo = $referenciaTipo !== null ? trim($referenciaTipo) : null;
        $this->referenciaId = $referenciaId;
        $this->movimientoReferenciaId = $movimientoReferenciaId;
        $this->correlativoOperacion = $correlativoOperacion !== null ? trim($correlativoOperacion) : null;
        $this->motivo = trim($motivo);
        $this->creadoPorActorId = $creadoPorActorId;
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

    public function obtenerTipoMovimiento(): string
    {
        return $this->tipoMovimiento;
    }

    public function esIncremento(): bool
    {
        return in_array($this->tipoMovimiento, [
            self::TIPO_SALDO_INICIAL,
            self::TIPO_ENTRADA_COMPRA,
            self::TIPO_TRASLADO_ENTRADA,
            self::TIPO_AJUSTE_POSITIVO,
        ], true);
    }

    public function esDecremento(): bool
    {
        return in_array($this->tipoMovimiento, [
            self::TIPO_SALIDA_CONSUMO,
            self::TIPO_SALIDA_MANTENIMIENTO,
            self::TIPO_TRASLADO_SALIDA,
            self::TIPO_AJUSTE_NEGATIVO,
        ], true);
    }

    public function obtenerArticuloId(): int
    {
        return $this->articuloId;
    }

    public function obtenerUbicacionId(): int
    {
        return $this->ubicacionId;
    }

    public function obtenerCantidad(): string
    {
        return $this->cantidad;
    }

    public function obtenerCostoUnitarioHistorico(): string
    {
        return $this->costoUnitarioHistorico;
    }

    public function obtenerCostoTotalHistorico(): string
    {
        return $this->costoTotalHistorico;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerReferenciaTipo(): ?string
    {
        return $this->referenciaTipo;
    }

    public function obtenerReferenciaId(): ?int
    {
        return $this->referenciaId;
    }

    public function obtenerMovimientoReferenciaId(): ?int
    {
        return $this->movimientoReferenciaId;
    }

    public function obtenerCorrelativoOperacion(): ?string
    {
        return $this->correlativoOperacion;
    }

    public function obtenerMotivo(): string
    {
        return $this->motivo;
    }

    public function obtenerCreadoPorActorId(): int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function asignarArticuloInfo(string $sku, string $nombre, string $simbolo): void
    {
        $this->articuloSku = $sku;
        $this->articuloNombre = $nombre;
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

    public function obtenerUnidadMedidaSimbolo(): ?string
    {
        return $this->unidadMedidaSimbolo;
    }

    public function asignarUbicacionInfo(string $codigo, string $nombre): void
    {
        $this->ubicacionCodigo = $codigo;
        $this->ubicacionNombre = $nombre;
    }

    public function obtenerUbicacionCodigo(): ?string
    {
        return $this->ubicacionCodigo;
    }

    public function obtenerUbicacionNombre(): ?string
    {
        return $this->ubicacionNombre;
    }

    public function asignarActorInfo(string $codigo, string $nombre): void
    {
        $this->actorCodigo = $codigo;
        $this->actorNombre = $nombre;
    }

    public function obtenerActorCodigo(): ?string
    {
        return $this->actorCodigo;
    }

    public function obtenerActorNombre(): ?string
    {
        return $this->actorNombre;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo_movimiento' => $this->tipoMovimiento,
            'articulo_id' => $this->articuloId,
            'articulo_sku' => $this->articuloSku,
            'articulo_nombre' => $this->articuloNombre,
            'unidad_medida_simbolo' => $this->unidadMedidaSimbolo,
            'ubicacion_id' => $this->ubicacionId,
            'ubicacion_codigo' => $this->ubicacionCodigo,
            'ubicacion_nombre' => $this->ubicacionNombre,
            'cantidad' => $this->cantidad,
            'costo_unitario_historico' => $this->costoUnitarioHistorico,
            'costo_total_historico' => $this->costoTotalHistorico,
            'moneda_codigo' => $this->monedaCodigo,
            'referencia_tipo' => $this->referenciaTipo,
            'referencia_id' => $this->referenciaId,
            'movimiento_referencia_id' => $this->movimientoReferenciaId,
            'correlativo_operacion' => $this->correlativoOperacion,
            'motivo' => $this->motivo,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'actor_codigo' => $this->actorCodigo,
            'actor_nombre' => $this->actorNombre,
            'creado_en' => $this->creadoEn,
        ];
    }
}
