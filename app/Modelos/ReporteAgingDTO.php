<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO para el Reporte de Morosidad y Antigüedad de Deuda (Aging Report).
 * Gobernanza: D-087 / REPORTES-1.
 * Estricta separación entre Cuentas por Cobrar (CxC) y Cuentas por Pagar (CxP).
 * Jamás mezcla derechos de cobro con obligaciones de pago.
 */
class ReporteAgingDTO
{
    public const TIPO_CXC = 'CXC';
    public const TIPO_CXP = 'CXP';

    /**
     * @param array<string, string> $totalesPorBucket
     * @param array<int, array<string, mixed>> $partidas
     */
    public function __construct(
        private string $tipo,
        private string $fechaCorte,
        private ?int $propiedadId,
        private string $montoTotal,
        private array $totalesPorBucket,
        private array $partidas = []
    ) {
    }

    public function obtenerTipo(): string { return $this->tipo; }
    public function esCuentasPorCobrar(): bool { return $this->tipo === self::TIPO_CXC; }
    public function esCuentasPorPagar(): bool { return $this->tipo === self::TIPO_CXP; }
    public function obtenerFechaCorte(): string { return $this->fechaCorte; }
    public function obtenerPropiedadId(): ?int { return $this->propiedadId; }
    public function obtenerMontoTotal(): string { return $this->montoTotal; }
    public function obtenerTotalesPorBucket(): array { return $this->totalesPorBucket; }
    public function obtenerPartidas(): array { return $this->partidas; }

    public function aArreglo(): array
    {
        return [
            'tipo' => $this->tipo,
            'fecha_corte' => $this->fechaCorte,
            'propiedad_id' => $this->propiedadId,
            'monto_total' => $this->montoTotal,
            'totales_por_bucket' => $this->totalesPorBucket,
            'partidas' => $this->partidas,
        ];
    }
}
