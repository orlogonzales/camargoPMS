<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un tramo de consumo y tarifa aplicada en una liquidación (SUMINISTROS-1 / D-081).
 *
 * Permite el cálculo multitramo cuando ocurren cambios de tarifa a mitad de período,
 * cambios/reemplazos de medidor o lecturas intermedias de corte.
 */
class SuministroLiquidacionTramo
{
    public function __construct(
        private ?int $id,
        private int $liquidacionId,
        private int $numeroTramo,
        private ?int $medidorId,
        private ?int $lecturaAnteriorId,
        private ?int $lecturaActualId,
        private ?string $lecturaAnteriorValor,
        private ?string $lecturaActualValor,
        private string $cantidad,
        private int $tarifaId,
        private string $tarifaValor,
        private string $subtotal,
        private string $impuestoMonto = '0.00',
        private string $total = '0.00',
        private string $fechaDesde = '',
        private string $fechaHasta = '',
        private ?string $creadoEn = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['liquidacion_id'] ?? 0),
            (int) ($datos['numero_tramo'] ?? 1),
            isset($datos['medidor_id']) && $datos['medidor_id'] !== '' ? (int) $datos['medidor_id'] : null,
            isset($datos['lectura_anterior_id']) && $datos['lectura_anterior_id'] !== '' ? (int) $datos['lectura_anterior_id'] : null,
            isset($datos['lectura_actual_id']) && $datos['lectura_actual_id'] !== '' ? (int) $datos['lectura_actual_id'] : null,
            isset($datos['lectura_anterior_valor']) ? (string) $datos['lectura_anterior_valor'] : null,
            isset($datos['lectura_actual_valor']) ? (string) $datos['lectura_actual_valor'] : null,
            (string) ($datos['cantidad'] ?? '0.0000'),
            (int) ($datos['tarifa_id'] ?? 0),
            (string) ($datos['tarifa_valor'] ?? '0.0000'),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['impuesto_monto'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            (string) ($datos['fecha_desde'] ?? ''),
            (string) ($datos['fecha_hasta'] ?? ''),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'liquidacion_id' => $this->liquidacionId,
            'numero_tramo' => $this->numeroTramo,
            'medidor_id' => $this->medidorId,
            'lectura_anterior_id' => $this->lecturaAnteriorId,
            'lectura_actual_id' => $this->lecturaActualId,
            'lectura_anterior_valor' => $this->lecturaAnteriorValor,
            'lectura_actual_valor' => $this->lecturaActualValor,
            'cantidad' => $this->cantidad,
            'tarifa_id' => $this->tarifaId,
            'tarifa_valor' => $this->tarifaValor,
            'subtotal' => $this->subtotal,
            'impuesto_monto' => $this->impuestoMonto,
            'total' => $this->total,
            'fecha_desde' => $this->fechaDesde,
            'fecha_hasta' => $this->fechaHasta,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerLiquidacionId(): int
    {
        return $this->liquidacionId;
    }

    public function obtenerNumeroTramo(): int
    {
        return $this->numeroTramo;
    }

    public function obtenerMedidorId(): ?int
    {
        return $this->medidorId;
    }

    public function obtenerLecturaAnteriorId(): ?int
    {
        return $this->lecturaAnteriorId;
    }

    public function obtenerLecturaActualId(): ?int
    {
        return $this->lecturaActualId;
    }

    public function obtenerLecturaAnteriorValor(): ?string
    {
        return $this->lecturaAnteriorValor;
    }

    public function obtenerLecturaActualValor(): ?string
    {
        return $this->lecturaActualValor;
    }

    public function obtenerCantidad(): string
    {
        return $this->cantidad;
    }

    public function obtenerTarifaId(): int
    {
        return $this->tarifaId;
    }

    public function obtenerTarifaValor(): string
    {
        return $this->tarifaValor;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerImpuestoMonto(): string
    {
        return $this->impuestoMonto;
    }

    public function obtenerTotal(): string
    {
        return $this->total;
    }

    public function obtenerFechaDesde(): string
    {
        return $this->fechaDesde;
    }

    public function obtenerFechaHasta(): string
    {
        return $this->fechaHasta;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }
}
