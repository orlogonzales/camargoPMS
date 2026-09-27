<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa la custodia segregada del depósito de garantía.
 * 
 * Reglas vinculantes (D-076):
 * - Saldo reconstructible: monto_recibido = monto_retenido_actual + monto_compensado_danos + monto_compensado_renta + monto_devuelto.
 * - Segregación contable total respecto al flujo ordinario de renta.
 * - Estados: PENDIENTE -> CUSTODIADA -> COMPENSADA_PARCIAL | LIQUIDADA.
 */
class ArrendamientoGarantia
{
    private ?int $id;
    private int $arrendamientoId;
    private string $montoPactado;
    private string $montoRecibido;
    private string $montoRetenidoActual;
    private string $montoCompensadoDanos;
    private string $montoCompensadoRenta;
    private string $montoDevuelto;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        int $arrendamientoId,
        string $montoPactado,
        string $montoRecibido = '0.00',
        string $montoRetenidoActual = '0.00',
        string $montoCompensadoDanos = '0.00',
        string $montoCompensadoRenta = '0.00',
        string $montoDevuelto = '0.00',
        string $estado = 'PENDIENTE',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->arrendamientoId = $arrendamientoId;
        $this->montoPactado = $montoPactado;
        $this->montoRecibido = $montoRecibido;
        $this->montoRetenidoActual = $montoRetenidoActual;
        $this->montoCompensadoDanos = $montoCompensadoDanos;
        $this->montoCompensadoRenta = $montoCompensadoRenta;
        $this->montoDevuelto = $montoDevuelto;
        $this->estado = $estado;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerArrendamientoId(): int { return $this->arrendamientoId; }
    public function obtenerMontoPactado(): string { return $this->montoPactado; }
    public function obtenerMontoRecibido(): string { return $this->montoRecibido; }
    public function obtenerMontoRetenidoActual(): string { return $this->montoRetenidoActual; }
    public function obtenerMontoCompensadoDanos(): string { return $this->montoCompensadoDanos; }
    public function obtenerMontoCompensadoRenta(): string { return $this->montoCompensadoRenta; }
    public function obtenerMontoDevuelto(): string { return $this->montoDevuelto; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }

    public function esPendiente(): bool { return $this->estado === 'PENDIENTE'; }
    public function esCustodiada(): bool { return $this->estado === 'CUSTODIADA'; }
    public function esCompensadaParcial(): bool { return $this->estado === 'COMPENSADA_PARCIAL'; }
    public function esLiquidada(): bool { return $this->estado === 'LIQUIDADA'; }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'arrendamiento_id' => $this->arrendamientoId,
            'monto_pactado' => $this->montoPactado,
            'monto_recibido' => $this->montoRecibido,
            'monto_retenido_actual' => $this->montoRetenidoActual,
            'monto_compensado_danos' => $this->montoCompensadoDanos,
            'monto_compensado_renta' => $this->montoCompensadoRenta,
            'monto_devuelto' => $this->montoDevuelto,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['arrendamiento_id'] ?? 0),
            number_format((float) ($datos['monto_pactado'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['monto_recibido'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['monto_retenido_actual'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['monto_compensado_danos'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['monto_compensado_renta'] ?? 0), 2, '.', ''),
            number_format((float) ($datos['monto_devuelto'] ?? 0), 2, '.', ''),
            (string) ($datos['estado'] ?? 'PENDIENTE'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
