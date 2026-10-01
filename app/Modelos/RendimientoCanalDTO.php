<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO que encapsula el rendimiento analítico y comercial de un canal de distribución (REPORTES-1A).
 *
 * Principio de pureza de canal:
 * - Separa explícitamente la producción comercial demostrable (reservas, ingresos, ADR)
 *   de los bloqueos de calendario externos importados vía iCalendar (Airbnb, Booking, VRBO),
 *   los cuales denotan indisponibilidad de inventario pero no acreditan ingresos comerciales soberanos.
 */
class RendimientoCanalDTO
{
    public const TIPO_COMERCIAL_DIRECTO = 'COMERCIAL_DIRECTO';
    public const TIPO_COMERCIAL_WEB = 'COMERCIAL_WEB';
    public const TIPO_EXTERNO_ICAL = 'EXTERNO_ICAL';
    public const TIPO_OTA = 'OTA';

    public function __construct(
        private string $codigoCanal,
        private string $nombreCanal,
        private string $tipoCanal,
        private int $reservasTotales,
        private int $reservasConfirmadas,
        private int $reservasCanceladas,
        private int $nochesVendidas,
        private float $cuotaNochesPorcentaje,
        private string $ingresosTotales,
        private float $cuotaIngresosPorcentaje,
        private string $adrMedio,
        private float $alosNoches,
        private float $leadTimeDias,
        private float $tasaCancelacionPorcentaje,
        private int $nochesBloqueadasIcal = 0,
        private bool $esProduccionDemostrable = true,
        private string $colorBadge = 'bg-light-secondary'
    ) {
    }

    public function obtenerCodigoCanal(): string { return $this->codigoCanal; }
    public function obtenerNombreCanal(): string { return $this->nombreCanal; }
    public function obtenerTipoCanal(): string { return $this->tipoCanal; }
    public function obtenerReservasTotales(): int { return $this->reservasTotales; }
    public function obtenerReservasConfirmadas(): int { return $this->reservasConfirmadas; }
    public function obtenerReservasCanceladas(): int { return $this->reservasCanceladas; }
    public function obtenerNochesVendidas(): int { return $this->nochesVendidas; }
    public function obtenerCuotaNochesPorcentaje(): float { return $this->cuotaNochesPorcentaje; }
    public function obtenerIngresosTotales(): string { return $this->ingresosTotales; }
    public function obtenerCuotaIngresosPorcentaje(): float { return $this->cuotaIngresosPorcentaje; }
    public function obtenerAdrMedio(): string { return $this->adrMedio; }
    public function obtenerAlosNoches(): float { return $this->alosNoches; }
    public function obtenerLeadTimeDias(): float { return $this->leadTimeDias; }
    public function obtenerTasaCancelacionPorcentaje(): float { return $this->tasaCancelacionPorcentaje; }
    public function obtenerNochesBloqueadasIcal(): int { return $this->nochesBloqueadasIcal; }
    public function esProduccionDemostrable(): bool { return $this->esProduccionDemostrable; }
    public function obtenerColorBadge(): string { return $this->colorBadge; }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'codigo_canal' => $this->codigoCanal,
            'nombre_canal' => $this->nombreCanal,
            'tipo_canal' => $this->tipoCanal,
            'reservas_totales' => $this->reservasTotales,
            'reservas_confirmadas' => $this->reservasConfirmadas,
            'reservas_canceladas' => $this->reservasCanceladas,
            'noches_vendidas' => $this->nochesVendidas,
            'cuota_noches_porcentaje' => $this->cuotaNochesPorcentaje,
            'ingresos_totales' => $this->ingresosTotales,
            'cuota_ingresos_porcentaje' => $this->cuotaIngresosPorcentaje,
            'adr_medio' => $this->adrMedio,
            'alos_noches' => $this->alosNoches,
            'lead_time_dias' => $this->leadTimeDias,
            'tasa_cancelacion_porcentaje' => $this->tasaCancelacionPorcentaje,
            'noches_bloqueadas_ical' => $this->nochesBloqueadasIcal,
            'es_produccion_demostrable' => $this->esProduccionDemostrable,
            'color_badge' => $this->colorBadge,
        ];
    }
}
