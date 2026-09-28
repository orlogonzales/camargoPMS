<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una Línea de Imputación de Recibo (RECIBOS-1 / D-082).
 *
 * Snapshot inmutable en T0 de una obligación amortizada por el pago registrado en el recibo.
 */
class ReciboLinea
{
    public function __construct(
        private ?int $id,
        private int $reciboId,
        private int $numeroLinea,
        private ?int $aplicacionId,
        private int $cargoId,
        private string $cargoCodigo,
        private string $cargoConcepto,
        private string $cargoOrigenTipo,
        private string $cargoMontoTotal,
        private string $montoAplicado,
        private string $cargoSaldoRestante,
        private ?string $creadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['recibo_id'] ?? 0),
            (int) ($datos['numero_linea'] ?? 1),
            isset($datos['aplicacion_id']) && $datos['aplicacion_id'] !== null ? (int) $datos['aplicacion_id'] : null,
            (int) ($datos['cargo_id'] ?? 0),
            (string) ($datos['cargo_codigo'] ?? ''),
            (string) ($datos['cargo_concepto'] ?? ''),
            (string) ($datos['cargo_origen_tipo'] ?? ''),
            (string) ($datos['cargo_monto_total'] ?? '0.00'),
            (string) ($datos['monto_aplicado'] ?? '0.00'),
            (string) ($datos['cargo_saldo_restante'] ?? '0.00'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'recibo_id' => $this->reciboId,
            'numero_linea' => $this->numeroLinea,
            'aplicacion_id' => $this->aplicacionId,
            'cargo_id' => $this->cargoId,
            'cargo_codigo' => $this->cargoCodigo,
            'cargo_concepto' => $this->cargoConcepto,
            'cargo_origen_tipo' => $this->cargoOrigenTipo,
            'cargo_monto_total' => $this->cargoMontoTotal,
            'monto_aplicado' => $this->montoAplicado,
            'cargo_saldo_restante' => $this->cargoSaldoRestante,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerReciboId(): int
    {
        return $this->reciboId;
    }

    public function obtenerNumeroLinea(): int
    {
        return $this->numeroLinea;
    }

    public function obtenerAplicacionId(): ?int
    {
        return $this->aplicacionId;
    }

    public function obtenerCargoId(): int
    {
        return $this->cargoId;
    }

    public function obtenerCargoCodigo(): string
    {
        return $this->cargoCodigo;
    }

    public function obtenerCargoConcepto(): string
    {
        return $this->cargoConcepto;
    }

    public function obtenerCargoOrigenTipo(): string
    {
        return $this->cargoOrigenTipo;
    }

    public function obtenerCargoMontoTotal(): string
    {
        return $this->cargoMontoTotal;
    }

    public function obtenerMontoAplicado(): string
    {
        return $this->montoAplicado;
    }

    public function obtenerCargoSaldoRestante(): string
    {
        return $this->cargoSaldoRestante;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }
}
