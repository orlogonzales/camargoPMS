<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO para el Flujo de Caja Consolidado de Tesorería (Cash Flow).
 * Gobernanza: D-087 / REPORTES-1.
 * Cuadre Algebraico: Saldo Inicial + Ingresos - Egresos = Saldo Final.
 * Separación estricta: Efectivo, Bancos y Consolidado (sin transferencias internas).
 */
class ReporteFlujoCajaDTO
{
    /**
     * @param array<string, mixed> $totalesEfectivo
     * @param array<string, mixed> $totalesBanco
     * @param array<string, mixed> $totalesConsolidado
     * @param array<int, array<string, mixed>> $movimientosDetalle
     * @param array<string, array<string, string>> $resumenPorOrigen
     */
    public function __construct(
        private string $fechaDesde,
        private string $fechaHasta,
        private ?int $propiedadId,
        private array $totalesEfectivo,
        private array $totalesBanco,
        private array $totalesConsolidado,
        private array $movimientosDetalle = [],
        private array $resumenPorOrigen = []
    ) {
    }

    public function obtenerFechaDesde(): string { return $this->fechaDesde; }
    public function obtenerFechaHasta(): string { return $this->fechaHasta; }
    public function obtenerPropiedadId(): ?int { return $this->propiedadId; }
    public function obtenerTotalesEfectivo(): array { return $this->totalesEfectivo; }
    public function obtenerTotalesBanco(): array { return $this->totalesBanco; }
    public function obtenerTotalesConsolidado(): array { return $this->totalesConsolidado; }
    public function obtenerMovimientosDetalle(): array { return $this->movimientosDetalle; }
    public function obtenerResumenPorOrigen(): array { return $this->resumenPorOrigen; }

    public function aArreglo(): array
    {
        return [
            'fecha_desde' => $this->fechaDesde,
            'fecha_hasta' => $this->fechaHasta,
            'propiedad_id' => $this->propiedadId,
            'totales_efectivo' => $this->totalesEfectivo,
            'totales_banco' => $this->totalesBanco,
            'totales_consolidado' => $this->totalesConsolidado,
            'movimientos_detalle' => $this->movimientosDetalle,
            'resumen_por_origen' => $this->resumenPorOrigen,
        ];
    }
}
