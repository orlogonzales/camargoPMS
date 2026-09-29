<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un Cierre Diario Hotelero (Night Audit) por propiedad.
 * Gobernanza: D-090 / DEVENGO-ALOJAMIENTO-1.
 */
class CierreHotelero
{
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_CERRADO = 'CERRADO';
    public const ESTADO_FALLIDO = 'FALLIDO';

    public function __construct(
        private ?int $id,
        private int $propiedadId,
        private string $fechaHotelera,
        private string $timezoneUtilizada,
        private string $estado,
        private int $totalEstadiasProcesadas,
        private int $totalNochesDevengadas,
        private string $ingresoAlojamientoNeto,
        private string $ingresoAlojamientoImpuestos,
        private string $ingresoAlojamientoTotal,
        private int $unidadesTotales,
        private int $unidadesOoo,
        private int $unidadesVendibles,
        private int $habitacionesVendidas,
        private int $habitacionesCortesia,
        private string $ocupacionPorcentaje,
        private string $adr,
        private string $revpar,
        private string $iniciadoEn,
        private ?string $cerradoEn,
        private int $ejecutadoPorActorId,
        private ?string $observaciones = null,
        private ?string $errorMensaje = null,
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

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerFechaHotelera(): string
    {
        return $this->fechaHotelera;
    }

    public function obtenerTimezoneUtilizada(): string
    {
        return $this->timezoneUtilizada;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaCerrado(): bool
    {
        return $this->estado === self::ESTADO_CERRADO;
    }

    public function estaEnProceso(): bool
    {
        return $this->estado === self::ESTADO_EN_PROCESO;
    }

    public function estaFallido(): bool
    {
        return $this->estado === self::ESTADO_FALLIDO;
    }

    public function obtenerTotalEstadiasProcesadas(): int
    {
        return $this->totalEstadiasProcesadas;
    }

    public function obtenerTotalNochesDevengadas(): int
    {
        return $this->totalNochesDevengadas;
    }

    public function obtenerIngresoAlojamientoNeto(): string
    {
        return $this->ingresoAlojamientoNeto;
    }

    public function obtenerIngresoAlojamientoImpuestos(): string
    {
        return $this->ingresoAlojamientoImpuestos;
    }

    public function obtenerIngresoAlojamientoTotal(): string
    {
        return $this->ingresoAlojamientoTotal;
    }

    public function obtenerUnidadesTotales(): int
    {
        return $this->unidadesTotales;
    }

    public function obtenerUnidadesOoo(): int
    {
        return $this->unidadesOoo;
    }

    public function obtenerUnidadesVendibles(): int
    {
        return $this->unidadesVendibles;
    }

    public function obtenerHabitacionesVendidas(): int
    {
        return $this->habitacionesVendidas;
    }

    public function obtenerHabitacionesCortesia(): int
    {
        return $this->habitacionesCortesia;
    }

    /**
     * Total de habitaciones físicamente ocupadas en la fecha hotelera (comerciales + cortesías).
     */
    public function obtenerHabitacionesOcupadas(): int
    {
        return $this->habitacionesVendidas + $this->habitacionesCortesia;
    }

    /**
     * Denominador de tarifa media diaria ADR (excluye cortesías y tarifa cero según convención D-090).
     */
    public function obtenerHabitacionesComputablesAdr(): int
    {
        return $this->habitacionesVendidas;
    }

    public function obtenerOcupacionPorcentaje(): string
    {
        return $this->ocupacionPorcentaje;
    }

    public function obtenerAdr(): string
    {
        return $this->adr;
    }

    public function obtenerRevpar(): string
    {
        return $this->revpar;
    }

    public function obtenerIniciadoEn(): string
    {
        return $this->iniciadoEn;
    }

    public function obtenerCerradoEn(): ?string
    {
        return $this->cerradoEn;
    }

    public function obtenerEjecutadoPorActorId(): int
    {
        return $this->ejecutadoPorActorId;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerErrorMensaje(): ?string
    {
        return $this->errorMensaje;
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
            'propiedad_id' => $this->propiedadId,
            'fecha_hotelera' => $this->fechaHotelera,
            'timezone_utilizada' => $this->timezoneUtilizada,
            'estado' => $this->estado,
            'total_estadias_procesadas' => $this->totalEstadiasProcesadas,
            'total_noches_devengadas' => $this->totalNochesDevengadas,
            'ingreso_alojamiento_neto' => $this->ingresoAlojamientoNeto,
            'ingreso_alojamiento_impuestos' => $this->ingresoAlojamientoImpuestos,
            'ingreso_alojamiento_total' => $this->ingresoAlojamientoTotal,
            'unidades_totales' => $this->unidadesTotales,
            'unidades_ooo' => $this->unidadesOoo,
            'unidades_vendibles' => $this->unidadesVendibles,
            'habitaciones_vendidas' => $this->habitacionesVendidas,
            'habitaciones_cortesia' => $this->habitacionesCortesia,
            'habitaciones_ocupadas' => $this->obtenerHabitacionesOcupadas(),
            'habitaciones_computables_adr' => $this->obtenerHabitacionesComputablesAdr(),
            'ocupacion_porcentaje' => $this->ocupacionPorcentaje,
            'adr' => $this->adr,
            'revpar' => $this->revpar,
            'iniciado_en' => $this->iniciadoEn,
            'cerrado_en' => $this->cerradoEn,
            'ejecutado_por_actor_id' => $this->ejecutadoPorActorId,
            'observaciones' => $this->observaciones,
            'error_mensaje' => $this->errorMensaje,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
