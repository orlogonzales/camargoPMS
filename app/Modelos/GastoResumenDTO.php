<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Data Transfer Object que proyecta la verdad económica y financiera reconstructible de un Gasto.
 * GASTOS-1 / D-086.
 *
 * Invariante: Saldo Pendiente = Total - Sum(Aplicaciones Activas).
 * Cero campos soberanos de saldo mutable en base de datos.
 */
class GastoResumenDTO
{
    public const SITUACION_PENDIENTE = 'PENDIENTE';
    public const SITUACION_PARCIAL = 'PARCIAL';
    public const SITUACION_PAGADO = 'PAGADO';

    /**
     * @param GastoEvidencia[] $evidencias
     * @param GastoAplicacionPago[] $aplicaciones
     */
    public function __construct(
        private Gasto $gasto,
        private ?string $categoriaNombre = null,
        private ?string $propiedadNombre = null,
        private ?string $unidadNumero = null,
        private string $montoAplicadoAcumulado = '0.00',
        private string $saldoPendiente = '0.00',
        private string $situacionFinanciera = self::SITUACION_PENDIENTE,
        private array $evidencias = [],
        private array $aplicaciones = []
    ) {
    }

    public function obtenerGasto(): Gasto { return $this->gasto; }
    public function obtenerCategoriaNombre(): ?string { return $this->categoriaNombre; }
    public function obtenerPropiedadNombre(): ?string { return $this->propiedadNombre; }
    public function obtenerUnidadNumero(): ?string { return $this->unidadNumero; }
    public function obtenerMontoAplicadoAcumulado(): string { return $this->montoAplicadoAcumulado; }
    public function obtenerSaldoPendiente(): string { return $this->saldoPendiente; }
    public function obtenerSituacionFinanciera(): string { return $this->situacionFinanciera; }
    /** @return GastoEvidencia[] */
    public function obtenerEvidencias(): array { return $this->evidencias; }
    /** @return GastoAplicacionPago[] */
    public function obtenerAplicaciones(): array { return $this->aplicaciones; }

    public function estaTotalmentePagado(): bool
    {
        return $this->situacionFinanciera === self::SITUACION_PAGADO;
    }

    public function aArreglo(): array
    {
        return array_merge($this->gasto->aArreglo(), [
            'categoria_nombre' => $this->categoriaNombre,
            'propiedad_nombre' => $this->propiedadNombre,
            'unidad_numero' => $this->unidadNumero,
            'monto_aplicado_acumulado' => $this->montoAplicadoAcumulado,
            'saldo_pendiente' => $this->saldoPendiente,
            'situacion_financiera' => $this->situacionFinanciera,
            'total_evidencias' => count($this->evidencias),
            'total_aplicaciones' => count($this->aplicaciones),
        ]);
    }
}
