<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Detalle individualizado del costo y tarifa resuelta para una noche de estancia.
 */
class DesgloseNocheCotizacion
{
    public function __construct(
        private string $fecha,
        private int $tarifaId,
        private string $tarifaNombre,
        private string $ambito,
        private string $precioNoche,
        private string $moneda = 'PEN'
    ) {
    }

    public function obtenerFecha(): string
    {
        return $this->fecha;
    }

    public function obtenerTarifaId(): int
    {
        return $this->tarifaId;
    }

    public function obtenerTarifaNombre(): string
    {
        return $this->tarifaNombre;
    }

    public function obtenerAmbito(): string
    {
        return $this->ambito;
    }

    public function obtenerPrecioNoche(): string
    {
        return $this->precioNoche;
    }

    public function obtenerMoneda(): string
    {
        return $this->moneda;
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'fecha' => $this->fecha,
            'tarifa_id' => $this->tarifaId,
            'tarifa_nombre' => $this->tarifaNombre,
            'ambito' => $this->ambito,
            'precio_noche' => $this->precioNoche,
            'moneda' => $this->moneda,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            fecha: (string) ($datos['fecha'] ?? ''),
            tarifaId: (int) ($datos['tarifa_id'] ?? 0),
            tarifaNombre: (string) ($datos['tarifa_nombre'] ?? ''),
            ambito: (string) ($datos['ambito'] ?? ''),
            precioNoche: (string) ($datos['precio_noche'] ?? '0.0000'),
            moneda: (string) ($datos['moneda'] ?? 'PEN')
        );
    }
}
