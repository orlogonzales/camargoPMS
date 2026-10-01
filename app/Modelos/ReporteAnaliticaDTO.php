<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO consolidado para la capa de Analítica y Rendimiento de Camargo PMS (REPORTES-1A).
 *
 * Principios vinculantes:
 * - D-069: Precisión de punto fijo BCMath, moneda canónica 'PEN', redondeo ROUND_HALF_UP.
 * - D-087: Proyección de solo lectura, sin persistencia ni mutaciones de estado.
 * - D-090: Pureza de métricas ADR/RevPAR, respeto de cierres hoteleros auditados,
 *   y exclusión obligatoria de cortesías del divisor de tarifa media.
 * - Separación estricta entre Ingresos Devengados (base contable) e Ingresos Percibidos (caja/bancos).
 * - Distinción explícita de canales: reservas con ingreso vs bloqueos externos iCal.
 */
class ReporteAnaliticaDTO
{
    /**
     * @param array<string, mixed> $resumenKpis
     * @param array<string, mixed> $desgloseIngresosDevengados
     * @param array<string, mixed> $desgloseIngresosPercibidos
     * @param array<RendimientoCanalDTO> $rendimientoCanales
     * @param array<string, mixed> $resumenIcal
     * @param array<PuntoSerieTemporalDTO> $serieTemporal
     */
    public function __construct(
        private string $fechaDesde,
        private string $fechaHasta,
        private ?int $propiedadId,
        private ?string $propiedadNombre,
        private int $totalDias,
        private array $resumenKpis,
        private array $desgloseIngresosDevengados,
        private array $desgloseIngresosPercibidos,
        private array $rendimientoCanales,
        private array $resumenIcal,
        private array $serieTemporal,
        private string $monedaCodigo = 'PEN',
        private ?string $generadoEn = null
    ) {
        $this->generadoEn = $generadoEn ?? gmdate('Y-m-d H:i:s');
    }

    public function obtenerFechaDesde(): string { return $this->fechaDesde; }
    public function obtenerFechaHasta(): string { return $this->fechaHasta; }
    public function obtenerPropiedadId(): ?int { return $this->propiedadId; }
    public function obtenerPropiedadNombre(): ?string { return $this->propiedadNombre; }
    public function obtenerTotalDias(): int { return $this->totalDias; }
    public function obtenerResumenKpis(): array { return $this->resumenKpis; }
    public function obtenerKpis(): array { return $this->resumenKpis; }
    public function obtenerDesgloseIngresosDevengados(): array { return $this->desgloseIngresosDevengados; }
    public function obtenerDesgloseIngresosPercibidos(): array { return $this->desgloseIngresosPercibidos; }
    /** @return array<RendimientoCanalDTO> */
    public function obtenerRendimientoCanales(): array { return $this->rendimientoCanales; }
    public function obtenerResumenIcal(): array { return $this->resumenIcal; }
    /** @return array<PuntoSerieTemporalDTO> */
    public function obtenerSerieTemporal(): array { return $this->serieTemporal; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerMoneda(): string { return $this->monedaCodigo; }
    public function obtenerGeneradoEn(): ?string { return $this->generadoEn; }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'periodo' => [
                'desde' => $this->fechaDesde,
                'hasta' => $this->fechaHasta,
                'total_dias' => $this->totalDias,
                'propiedad_id' => $this->propiedadId,
                'propiedad_nombre' => $this->propiedadNombre,
                'moneda_codigo' => $this->monedaCodigo,
                'generado_en' => $this->generadoEn,
            ],
            'kpis' => $this->resumenKpis,
            'ingresos_devengados' => $this->desgloseIngresosDevengados,
            'ingresos_percibidos' => $this->desgloseIngresosPercibidos,
            'rendimiento_canales' => array_map(
                static fn(RendimientoCanalDTO $c) => $c->aArreglo(),
                $this->rendimientoCanales
            ),
            'resumen_ical' => $this->resumenIcal,
            'serie_temporal' => array_map(
                static fn(PuntoSerieTemporalDTO $p) => $p->aArreglo(),
                $this->serieTemporal
            ),
        ];
    }
}
