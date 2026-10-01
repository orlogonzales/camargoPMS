<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO que representa un punto diario dentro de una serie temporal analítica (REPORTES-1A).
 * Permite graficar o tabular la evolución de Ocupación, ADR y RevPAR día por día.
 */
class PuntoSerieTemporalDTO
{
    public function __construct(
        private string $fecha,
        private int $unidadesTotales,
        private int $unidadesOoo,
        private int $unidadesVendibles,
        private int $unidadesOcupadas,
        private int $habitacionesVendidas,
        private int $habitacionesCortesia,
        private float $ocupacionPorcentaje,
        private string $adr,
        private string $revpar,
        private string $ingresoAlojamientoNeto,
        private bool $esAuditado = false
    ) {
    }

    public function obtenerFecha(): string { return $this->fecha; }
    public function obtenerUnidadesTotales(): int { return $this->unidadesTotales; }
    public function obtenerUnidadesOoo(): int { return $this->unidadesOoo; }
    public function obtenerUnidadesVendibles(): int { return $this->unidadesVendibles; }
    public function obtenerUnidadesOcupadas(): int { return $this->unidadesOcupadas; }
    public function obtenerHabitacionesVendidas(): int { return $this->habitacionesVendidas; }
    public function obtenerHabitacionesCortesia(): int { return $this->habitacionesCortesia; }
    public function obtenerOcupacionPorcentaje(): float { return $this->ocupacionPorcentaje; }
    public function obtenerAdr(): string { return $this->adr; }
    public function obtenerRevpar(): string { return $this->revpar; }
    public function obtenerIngresoAlojamientoNeto(): string { return $this->ingresoAlojamientoNeto; }
    public function esAuditado(): bool { return $this->esAuditado; }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'fecha' => $this->fecha,
            'unidades_totales' => $this->unidadesTotales,
            'unidades_ooo' => $this->unidadesOoo,
            'unidades_vendibles' => $this->unidadesVendibles,
            'unidades_ocupadas' => $this->unidadesOcupadas,
            'habitaciones_vendidas' => $this->habitacionesVendidas,
            'habitaciones_cortesia' => $this->habitacionesCortesia,
            'ocupacion_porcentaje' => $this->ocupacionPorcentaje,
            'adr' => $this->adr,
            'revpar' => $this->revpar,
            'ingreso_alojamiento_neto' => $this->ingresoAlojamientoNeto,
            'es_auditado' => $this->esAuditado,
        ];
    }
}
