<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO raíz para la proyección operacional consolidada del Tape Chart y Rack de Hoy (TAPE-CHART-1 / D-084).
 *
 * Invariante vinculante:
 * PROYECCIÓN CALENDARIO DE HOY = FUENTE DEL RACK DE HOY.
 * La agregación en memoria garantiza consistencia matemática absoluta entre el tablero y los KPIs.
 */
final class TapeChartProyeccion
{
    /**
     * @param int $propiedadId ID de la propiedad consultada
     * @param string $propiedadNombre Nombre oficial de la propiedad
     * @param string $zonaHoraria Zona horaria canónica IANA
     * @param string $fechaHoteleraHoy Fecha hotelera actual resuelta bajo D-066
     * @param string $fechaDesde Fecha de inicio del intervalo (inclusive)
     * @param string $fechaHasta Fecha de fin del intervalo (exclusive)
     * @param array<int, array<string, mixed>> $columnasFechas Columnas de días con metadatos
     * @param array<int, TapeChartUnidad> $filasUnidades Colección de unidades con sus celdas
     * @param array<string, mixed> $kpisHoy Métricas operacionales para la fecha hotelera actual
     */
    public function __construct(
        private int $propiedadId,
        private string $propiedadNombre,
        private string $zonaHoraria,
        private string $fechaHoteleraHoy,
        private string $fechaDesde,
        private string $fechaHasta,
        private array $columnasFechas,
        private array $filasUnidades,
        private array $kpisHoy
    ) {
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerPropiedadNombre(): string
    {
        return $this->propiedadNombre;
    }

    public function obtenerZonaHoraria(): string
    {
        return $this->zonaHoraria;
    }

    public function obtenerFechaHoteleraHoy(): string
    {
        return $this->fechaHoteleraHoy;
    }

    public function obtenerFechaDesde(): string
    {
        return $this->fechaDesde;
    }

    public function obtenerFechaHasta(): string
    {
        return $this->fechaHasta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerColumnasFechas(): array
    {
        return $this->columnasFechas;
    }

    /**
     * @return array<int, TapeChartUnidad>
     */
    public function obtenerFilasUnidades(): array
    {
        return $this->filasUnidades;
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerKpisHoy(): array
    {
        return $this->kpisHoy;
    }

    /**
     * Serializa la proyección completa para la API JSON.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        $filas = [];
        foreach ($this->filasUnidades as $unidad) {
            $filas[] = $unidad->aArreglo();
        }

        return [
            'propiedad' => [
                'id' => $this->propiedadId,
                'nombre' => $this->propiedadNombre,
                'zona_horaria' => $this->zonaHoraria,
                'fecha_hotelera_hoy' => $this->fechaHoteleraHoy,
            ],
            'horizonte' => [
                'fecha_desde' => $this->fechaDesde,
                'fecha_hasta' => $this->fechaHasta,
                'total_dias' => count($this->columnasFechas),
            ],
            'columnas_fechas' => $this->columnasFechas,
            'filas_unidades' => $filas,
            'kpis_hoy' => $this->kpisHoy,
        ];
    }
}
