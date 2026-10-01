<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un cargo económico a la cuenta (alojamiento, servicio, penalidad).
 */
class CargoCuenta
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $cuentaFolioId,
        private string $origenTipo,
        private ?int $origenId,
        private ?int $estadiaId,
        private string $concepto,
        private string $cantidad,
        private string $precioUnitario,
        private string $subtotal,
        private string $impuestoTotal,
        private string $total,
        private string $montoAplicadoAcumulado,
        private string $monedaCodigo,
        private string $estado,
        private ?string $motivoAnulacion,
        private ?string $anuladoEn,
        private ?int $anuladoPorActorId,
        private ?string $devengadoEn,
        private int $creadoPorActorId,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        private ?int $cargoPadreId = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerCuentaFolioId(): int
    {
        return $this->cuentaFolioId;
    }

    public function obtenerOrigenTipo(): string
    {
        return $this->origenTipo;
    }

    public function obtenerOrigenId(): ?int
    {
        return $this->origenId;
    }

    public function obtenerEstadiaId(): ?int
    {
        return $this->estadiaId;
    }

    public function obtenerConcepto(): string
    {
        return $this->concepto;
    }

    public function obtenerCantidad(): string
    {
        return $this->cantidad;
    }

    public function obtenerPrecioUnitario(): string
    {
        return $this->precioUnitario;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerImpuestoTotal(): string
    {
        return $this->impuestoTotal;
    }

    public function obtenerTotal(): string
    {
        return $this->total;
    }

    public function obtenerMontoAplicadoAcumulado(): string
    {
        return $this->montoAplicadoAcumulado;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esProvisional(): bool
    {
        return $this->estado === 'PROVISIONAL';
    }

    public function esDevengado(): bool
    {
        return $this->estado === 'DEVENGADO';
    }

    public function estaDevengado(): bool
    {
        return $this->esDevengado();
    }

    public function esAnulado(): bool
    {
        return $this->estado === 'ANULADO';
    }

    public function estaAnulado(): bool
    {
        return $this->esAnulado();
    }

    /**
     * Calcula la deuda pendiente exigible con BCMath.
     */
    public function calcularSaldoPendiente(): string
    {
        if (!$this->esDevengado()) {
            return '0.00';
        }
        $diff = bcsub($this->total, $this->montoAplicadoAcumulado, 2);
        return bccomp($diff, '0.00', 2) > 0 ? $diff : '0.00';
    }

    public function obtenerMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function obtenerAnuladoEn(): ?string
    {
        return $this->anuladoEn;
    }

    public function obtenerAnuladoPorActorId(): ?int
    {
        return $this->anuladoPorActorId;
    }

    public function obtenerDevengadoEn(): ?string
    {
        return $this->devengadoEn;
    }

    public function obtenerCreadoPorActorId(): int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerCargoPadreId(): ?int
    {
        return $this->cargoPadreId;
    }

    public function esCargoHijo(): bool
    {
        return $this->cargoPadreId !== null;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cuenta_folio_id' => $this->cuentaFolioId,
            'cargo_padre_id' => $this->cargoPadreId,
            'origen_tipo' => $this->origenTipo,
            'origen_id' => $this->origenId,
            'estadia_id' => $this->estadiaId,
            'concepto' => $this->concepto,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precioUnitario,
            'subtotal' => $this->subtotal,
            'impuesto_total' => $this->impuestoTotal,
            'total' => $this->total,
            'monto_aplicado_acumulado' => $this->montoAplicadoAcumulado,
            'saldo_pendiente' => $this->calcularSaldoPendiente(),
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulado_en' => $this->anuladoEn,
            'anulado_por_actor_id' => $this->anuladoPorActorId,
            'devengado_en' => $this->devengadoEn,
            'creado_por_actor_id' => $this->creadoPorActorId,
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
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['cuenta_folio_id'] ?? 0),
            (string) ($datos['origen_tipo'] ?? 'ALOJAMIENTO_NOCHES'),
            isset($datos['origen_id']) ? (int) $datos['origen_id'] : null,
            isset($datos['estadia_id']) ? (int) $datos['estadia_id'] : null,
            (string) ($datos['concepto'] ?? ''),
            (string) ($datos['cantidad'] ?? '1.00'),
            (string) ($datos['precio_unitario'] ?? '0.00'),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['impuesto_total'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            (string) ($datos['monto_aplicado_acumulado'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? 'DEVENGADO'),
            isset($datos['motivo_anulacion']) ? (string) $datos['motivo_anulacion'] : null,
            isset($datos['anulado_en']) ? (string) $datos['anulado_en'] : null,
            isset($datos['anulado_por_actor_id']) ? (int) $datos['anulado_por_actor_id'] : null,
            isset($datos['devengado_en']) ? (string) $datos['devengado_en'] : null,
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            isset($datos['cargo_padre_id']) && $datos['cargo_padre_id'] !== null ? (int) $datos['cargo_padre_id'] : null
        );
    }
}
