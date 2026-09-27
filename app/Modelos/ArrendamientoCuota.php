<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una cuota periódica de renta generada para un arrendamiento.
 * 
 * Reglas vinculantes (D-076):
 * - Idempotencia estricta por (arrendamiento_id, periodo_anio, periodo_mes, tipo_cuota).
 * - Vinculación obligatoria 1:1 con un cargo en el folio financiero (`cargo_cuenta_id`).
 * - Tipos de cuota: RENTA_MENSUAL, CUOTA_PRORRATEADA, AJUSTE_PERIODICO.
 */
class ArrendamientoCuota
{
    private ?int $id;
    private int $arrendamientoId;
    private int $periodoAnio;
    private int $periodoMes;
    private string $periodoCodigo;
    private string $tipoCuota;
    private string $fechaEmision;
    private string $fechaVencimiento;
    private string $montoRenta;
    private int $cargoCuentaId;
    private string $estado;
    private ?string $creadoEn;

    // Metadatos auxiliares del cargo en folio
    private ?string $cargoCodigo = null;
    private ?string $montoAplicadoAcumulado = null;
    private ?string $saldoPendiente = null;

    public function __construct(
        ?int $id,
        int $arrendamientoId,
        int $periodoAnio,
        int $periodoMes,
        string $periodoCodigo,
        string $tipoCuota,
        string $fechaEmision,
        string $fechaVencimiento,
        string $montoRenta,
        int $cargoCuentaId,
        string $estado = 'PENDIENTE',
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->arrendamientoId = $arrendamientoId;
        $this->periodoAnio = $periodoAnio;
        $this->periodoMes = $periodoMes;
        $this->periodoCodigo = $periodoCodigo;
        $this->tipoCuota = $tipoCuota;
        $this->fechaEmision = $fechaEmision;
        $this->fechaVencimiento = $fechaVencimiento;
        $this->montoRenta = $montoRenta;
        $this->cargoCuentaId = $cargoCuentaId;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerArrendamientoId(): int { return $this->arrendamientoId; }
    public function obtenerPeriodoAnio(): int { return $this->periodoAnio; }
    public function obtenerPeriodoMes(): int { return $this->periodoMes; }
    public function obtenerPeriodoCodigo(): string { return $this->periodoCodigo; }
    public function obtenerTipoCuota(): string { return $this->tipoCuota; }
    public function obtenerFechaEmision(): string { return $this->fechaEmision; }
    public function obtenerFechaVencimiento(): string { return $this->fechaVencimiento; }
    public function obtenerMontoRenta(): string { return $this->montoRenta; }
    public function obtenerCargoCuentaId(): int { return $this->cargoCuentaId; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }

    public function esPendiente(): bool { return $this->estado === 'PENDIENTE'; }
    public function esPagadaParcial(): bool { return $this->estado === 'PAGADA_PARCIAL'; }
    public function esPagadaTotal(): bool { return $this->estado === 'PAGADA_TOTAL'; }
    public function esAnulada(): bool { return $this->estado === 'ANULADA'; }

    // Metadatos auxiliares
    public function obtenerCargoCodigo(): ?string { return $this->cargoCodigo; }
    public function fijarCargoCodigo(?string $val): void { $this->cargoCodigo = $val; }
    public function obtenerMontoAplicadoAcumulado(): ?string { return $this->montoAplicadoAcumulado; }
    public function fijarMontoAplicadoAcumulado(?string $val): void { $this->montoAplicadoAcumulado = $val; }
    public function obtenerSaldoPendiente(): ?string { return $this->saldoPendiente; }
    public function fijarSaldoPendiente(?string $val): void { $this->saldoPendiente = $val; }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'arrendamiento_id' => $this->arrendamientoId,
            'periodo_anio' => $this->periodoAnio,
            'periodo_mes' => $this->periodoMes,
            'periodo_codigo' => $this->periodoCodigo,
            'tipo_cuota' => $this->tipoCuota,
            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'monto_renta' => $this->montoRenta,
            'cargo_cuenta_id' => $this->cargoCuentaId,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'cargo_codigo' => $this->cargoCodigo,
            'monto_aplicado_acumulado' => $this->montoAplicadoAcumulado,
            'saldo_pendiente' => $this->saldoPendiente,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $cuota = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['arrendamiento_id'] ?? 0),
            (int) ($datos['periodo_anio'] ?? 0),
            (int) ($datos['periodo_mes'] ?? 0),
            (string) ($datos['periodo_codigo'] ?? ''),
            (string) ($datos['tipo_cuota'] ?? 'RENTA_MENSUAL'),
            (string) ($datos['fecha_emision'] ?? ''),
            (string) ($datos['fecha_vencimiento'] ?? ''),
            number_format((float) ($datos['monto_renta'] ?? 0), 2, '.', ''),
            (int) ($datos['cargo_cuenta_id'] ?? 0),
            (string) ($datos['estado'] ?? 'PENDIENTE'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );

        if (isset($datos['cargo_codigo'])) {
            $cuota->fijarCargoCodigo((string) $datos['cargo_codigo']);
        }
        if (isset($datos['monto_aplicado_acumulado'])) {
            $cuota->fijarMontoAplicadoAcumulado(number_format((float) $datos['monto_aplicado_acumulado'], 2, '.', ''));
        }
        if (isset($datos['saldo_pendiente'])) {
            $cuota->fijarSaldoPendiente(number_format((float) $datos['saldo_pendiente'], 2, '.', ''));
        }

        return $cuota;
    }
}
