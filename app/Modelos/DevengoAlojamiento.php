<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa el Devengo Económico Diario de una noche de alojamiento.
 * Gobernanza: D-090 / DEVENGO-ALOJAMIENTO-1.
 */
class DevengoAlojamiento
{
    public const ESTADO_DEVENGADO = 'DEVENGADO';
    public const ESTADO_REVERTIDO = 'REVERTIDO';

    public const ORIGEN_TARIFA_NOCTURNA_PACTADA = 'TARIFA_NOCTURNA_PACTADA';
    public const ORIGEN_DISTRIBUCION_CONTRATO_UNIFORME = 'DISTRIBUCION_CONTRATO_UNIFORME';
    public const ORIGEN_AJUSTE_OPERACIONAL = 'AJUSTE_OPERACIONAL';

    public const METODO_DIST_TARIFA_EXPLICITA = 'TARIFA_EXPLICITA';
    public const METODO_DIST_DISTRIBUCION_UNIFORME = 'DISTRIBUCION_UNIFORME';
    public const METODO_DIST_AJUSTE_RESIDUAL = 'AJUSTE_RESIDUAL';

    public const METODO_DEV_NIGHT_AUDIT = 'NIGHT_AUDIT';
    public const METODO_DEV_CHECKOUT_ANTICIPADO = 'CHECKOUT_ANTICIPADO';
    public const METODO_DEV_MANUAL_SUPERVISADO = 'MANUAL_SUPERVISADO';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private ?int $cierreHoteleroId,
        private int $estadiaId,
        private int $reservaId,
        private int $reservaUnidadId,
        private int $unidadId,
        private int $propiedadId,
        private ?int $cargoCuentaId,
        private string $fechaHotelera,
        private int $nocheIndice,
        private int $totalNochesEstadia,
        private int $secuencia,
        private string $tarifaBaseNoche,
        private string $descuentoMonto,
        private string $impuestoMonto,
        private string $importeNeto,
        private string $importeTotal,
        private string $monedaCodigo,
        private bool $esCortesia,
        private string $origenTarifa,
        private string $metodoDistribucion,
        private string $timezoneUtilizada,
        private ?array $tarifaSnapshot,
        private string $estado,
        private string $metodoDevengo,
        private ?int $reversoDeId,
        private ?string $motivoReversion,
        private string $devengadoEn,
        private int $devengadoPorActorId,
        private ?string $revertidoEn = null,
        private ?int $revertidoPorActorId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerCierreHoteleroId(): ?int
    {
        return $this->cierreHoteleroId;
    }

    public function obtenerEstadiaId(): int
    {
        return $this->estadiaId;
    }

    public function obtenerReservaId(): int
    {
        return $this->reservaId;
    }

    public function obtenerReservaUnidadId(): int
    {
        return $this->reservaUnidadId;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerCargoCuentaId(): ?int
    {
        return $this->cargoCuentaId;
    }

    public function obtenerFechaHotelera(): string
    {
        return $this->fechaHotelera;
    }

    public function obtenerNocheIndice(): int
    {
        return $this->nocheIndice;
    }

    public function obtenerTotalNochesEstadia(): int
    {
        return $this->totalNochesEstadia;
    }

    public function obtenerSecuencia(): int
    {
        return $this->secuencia;
    }

    public function obtenerTarifaBaseNoche(): string
    {
        return $this->tarifaBaseNoche;
    }

    public function obtenerDescuentoMonto(): string
    {
        return $this->descuentoMonto;
    }

    public function obtenerImpuestoMonto(): string
    {
        return $this->impuestoMonto;
    }

    public function obtenerImporteNeto(): string
    {
        return $this->importeNeto;
    }

    public function obtenerImporteTotal(): string
    {
        return $this->importeTotal;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function esCortesia(): bool
    {
        return $this->esCortesia;
    }

    public function obtenerOrigenTarifa(): string
    {
        return $this->origenTarifa;
    }

    public function obtenerMetodoDistribucion(): string
    {
        return $this->metodoDistribucion;
    }

    public function obtenerTimezoneUtilizada(): string
    {
        return $this->timezoneUtilizada;
    }

    public function obtenerTarifaSnapshot(): ?array
    {
        return $this->tarifaSnapshot;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaDevengado(): bool
    {
        return $this->estado === self::ESTADO_DEVENGADO;
    }

    public function estaRevertido(): bool
    {
        return $this->estado === self::ESTADO_REVERTIDO;
    }

    public function obtenerMetodoDevengo(): string
    {
        return $this->metodoDevengo;
    }

    public function obtenerReversoDeId(): ?int
    {
        return $this->reversoDeId;
    }

    public function obtenerMotivoReversion(): ?string
    {
        return $this->motivoReversion;
    }

    public function obtenerDevengadoEn(): string
    {
        return $this->devengadoEn;
    }

    public function obtenerDevengadoPorActorId(): int
    {
        return $this->devengadoPorActorId;
    }

    public function obtenerRevertidoEn(): ?string
    {
        return $this->revertidoEn;
    }

    public function obtenerRevertidoPorActorId(): ?int
    {
        return $this->revertidoPorActorId;
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
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cierre_hotelero_id' => $this->cierreHoteleroId,
            'estadia_id' => $this->estadiaId,
            'reserva_id' => $this->reservaId,
            'reserva_unidad_id' => $this->reservaUnidadId,
            'unidad_id' => $this->unidadId,
            'propiedad_id' => $this->propiedadId,
            'cargo_cuenta_id' => $this->cargoCuentaId,
            'fecha_hotelera' => $this->fechaHotelera,
            'noche_indice' => $this->nocheIndice,
            'total_noches_estadia' => $this->totalNochesEstadia,
            'secuencia' => $this->secuencia,
            'tarifa_base_noche' => $this->tarifaBaseNoche,
            'descuento_monto' => $this->descuentoMonto,
            'impuesto_monto' => $this->impuestoMonto,
            'importe_neto' => $this->importeNeto,
            'importe_total' => $this->importeTotal,
            'moneda_codigo' => $this->monedaCodigo,
            'es_cortesia' => $this->esCortesia,
            'origen_tarifa' => $this->origenTarifa,
            'metodo_distribucion' => $this->metodoDistribucion,
            'timezone_utilizada' => $this->timezoneUtilizada,
            'tarifa_snapshot' => $this->tarifaSnapshot,
            'estado' => $this->estado,
            'metodo_devengo' => $this->metodoDevengo,
            'reverso_de_id' => $this->reversoDeId,
            'motivo_reversion' => $this->motivoReversion,
            'devengado_en' => $this->devengadoEn,
            'devengado_por_actor_id' => $this->devengadoPorActorId,
            'revertido_en' => $this->revertidoEn,
            'revertido_por_actor_id' => $this->revertidoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
