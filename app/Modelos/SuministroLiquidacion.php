<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una Liquidación de Suministro (SUMINISTROS-1 / D-081).
 *
 * Registra el cálculo formal y determinista del consumo/cuota fija de un suministro
 * para un arrendamiento específico en un período temporal, y su vinculación
 * estricta con el cargo devengado en cuentas_folios (FINANCIERO-2).
 */
class SuministroLiquidacion
{
    public const ESTADO_DEVENGADO = 'DEVENGADO';
    public const ESTADO_ANULADO = 'ANULADO';

    /**
     * @param SuministroLiquidacionTramo[] $tramos
     */
    public function __construct(
        private ?int $id,
        private string $folio,
        private int $suministroId,
        private string $modalidad,
        private int $propiedadId,
        private int $unidadId,
        private int $arrendamientoId,
        private int $cuentaFolioId,
        private int $cargoCuentaId,
        private int $periodoAnio,
        private int $periodoMes,
        private string $periodoDesde,
        private string $periodoHasta,
        private string $fechaEmision,
        private string $fechaVencimiento,
        private string $cantidadTotal,
        private string $subtotal,
        private string $impuestoMonto = '0.00',
        private string $total = '0.00',
        private string $monedaCodigo = 'PEN',
        private int $revision = 1,
        private ?int $liquidacionPreviaId = null,
        private string $estado = self::ESTADO_DEVENGADO,
        private ?string $motivoAnulacion = null,
        private ?string $anuladoEn = null,
        private ?int $anuladoPorActorId = null,
        private ?int $creadoPorActorId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        private array $tramos = []
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $tramos = [];
        if (isset($datos['tramos']) && is_array($datos['tramos'])) {
            foreach ($datos['tramos'] as $t) {
                if ($t instanceof SuministroLiquidacionTramo) {
                    $tramos[] = $t;
                } elseif (is_array($t)) {
                    $tramos[] = SuministroLiquidacionTramo::desdeArreglo($t);
                }
            }
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['folio'] ?? ''),
            (int) ($datos['suministro_id'] ?? 0),
            (string) ($datos['modalidad'] ?? Suministro::MODALIDAD_MEDIDO),
            (int) ($datos['propiedad_id'] ?? 0),
            (int) ($datos['unidad_id'] ?? 0),
            (int) ($datos['arrendamiento_id'] ?? 0),
            (int) ($datos['cuenta_folio_id'] ?? 0),
            (int) ($datos['cargo_cuenta_id'] ?? 0),
            (int) ($datos['periodo_anio'] ?? 0),
            (int) ($datos['periodo_mes'] ?? 0),
            (string) ($datos['periodo_desde'] ?? ''),
            (string) ($datos['periodo_hasta'] ?? ''),
            (string) ($datos['fecha_emision'] ?? ''),
            (string) ($datos['fecha_vencimiento'] ?? ''),
            (string) ($datos['cantidad_total'] ?? '0.0000'),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['impuesto_monto'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (int) ($datos['revision'] ?? 1),
            isset($datos['liquidacion_previa_id']) && $datos['liquidacion_previa_id'] !== '' ? (int) $datos['liquidacion_previa_id'] : null,
            (string) ($datos['estado'] ?? self::ESTADO_DEVENGADO),
            isset($datos['motivo_anulacion']) && $datos['motivo_anulacion'] !== '' ? (string) $datos['motivo_anulacion'] : null,
            isset($datos['anulado_en']) ? (string) $datos['anulado_en'] : null,
            isset($datos['anulado_por_actor_id']) && $datos['anulado_por_actor_id'] !== '' ? (int) $datos['anulado_por_actor_id'] : null,
            isset($datos['creado_por_actor_id']) && $datos['creado_por_actor_id'] !== '' ? (int) $datos['creado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            $tramos
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'suministro_id' => $this->suministroId,
            'modalidad' => $this->modalidad,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'arrendamiento_id' => $this->arrendamientoId,
            'cuenta_folio_id' => $this->cuentaFolioId,
            'cargo_cuenta_id' => $this->cargoCuentaId,
            'periodo_anio' => $this->periodoAnio,
            'periodo_mes' => $this->periodoMes,
            'periodo_desde' => $this->periodoDesde,
            'periodo_hasta' => $this->periodoHasta,
            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'cantidad_total' => $this->cantidadTotal,
            'subtotal' => $this->subtotal,
            'impuesto_monto' => $this->impuestoMonto,
            'total' => $this->total,
            'moneda_codigo' => $this->monedaCodigo,
            'revision' => $this->revision,
            'liquidacion_previa_id' => $this->liquidacionPreviaId,
            'estado' => $this->estado,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulado_en' => $this->anuladoEn,
            'anulado_por_actor_id' => $this->anuladoPorActorId,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'tramos' => array_map(static fn(SuministroLiquidacionTramo $t): array => $t->aArreglo(), $this->tramos),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerFolio(): string
    {
        return $this->folio;
    }

    public function obtenerSuministroId(): int
    {
        return $this->suministroId;
    }

    public function obtenerModalidad(): string
    {
        return $this->modalidad;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerArrendamientoId(): int
    {
        return $this->arrendamientoId;
    }

    public function obtenerCuentaFolioId(): int
    {
        return $this->cuentaFolioId;
    }

    public function obtenerCargoCuentaId(): int
    {
        return $this->cargoCuentaId;
    }

    public function obtenerPeriodoAnio(): int
    {
        return $this->periodoAnio;
    }

    public function obtenerPeriodoMes(): int
    {
        return $this->periodoMes;
    }

    public function obtenerPeriodoDesde(): string
    {
        return $this->periodoDesde;
    }

    public function obtenerPeriodoHasta(): string
    {
        return $this->periodoHasta;
    }

    public function obtenerFechaEmision(): string
    {
        return $this->fechaEmision;
    }

    public function obtenerFechaVencimiento(): string
    {
        return $this->fechaVencimiento;
    }

    public function obtenerCantidadTotal(): string
    {
        return $this->cantidadTotal;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerImpuestoMonto(): string
    {
        return $this->impuestoMonto;
    }

    public function obtenerTotal(): string
    {
        return $this->total;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerRevision(): int
    {
        return $this->revision;
    }

    public function obtenerLiquidacionPreviaId(): ?int
    {
        return $this->liquidacionPreviaId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function obtenerAnuladoEn(): ?string
    {
        return $this->anuladoEn;
    }

    public function obtenerAnuladoPorActorId(): ?int
    {
        return $this->anuladoPorActorId;
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
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
     * @return SuministroLiquidacionTramo[]
     */
    public function obtenerTramos(): array
    {
        return $this->tramos;
    }

    public function esDevengado(): bool
    {
        return $this->estado === self::ESTADO_DEVENGADO;
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }
}
