<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una línea de lencería textil en un lote de lavandería.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingLoteLinea
{
    public function __construct(
        private ?int $id,
        private int $loteId,
        private int $articuloId,
        private string $cantidadEnviada,
        private string $cantidadRecibida = '0.0000',
        private string $cantidadBajaMerma = '0.0000',
        private ?string $cantidadDiferencia = null,
        private ?string $observaciones = null,
        private ?string $articuloNombre = null,
        private ?string $articuloCodigo = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['lote_id'] ?? 0),
            (int) ($datos['articulo_id'] ?? 0),
            (string) ($datos['cantidad_enviada'] ?? '0.0000'),
            (string) ($datos['cantidad_recibida'] ?? '0.0000'),
            (string) ($datos['cantidad_baja_merma'] ?? '0.0000'),
            isset($datos['cantidad_diferencia']) ? (string) $datos['cantidad_diferencia'] : null,
            $datos['observaciones'] ?? null,
            $datos['articulo_nombre'] ?? null,
            $datos['articulo_codigo'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'lote_id' => $this->loteId,
            'articulo_id' => $this->articuloId,
            'cantidad_enviada' => $this->cantidadEnviada,
            'cantidad_recibida' => $this->cantidadRecibida,
            'cantidad_baja_merma' => $this->cantidadBajaMerma,
            'cantidad_diferencia' => $this->calcularDiferencia(),
            'observaciones' => $this->observaciones,
            'articulo_nombre' => $this->articuloNombre,
            'articulo_codigo' => $this->articuloCodigo,
        ];
    }

    public function calcularDiferencia(): string
    {
        if ($this->cantidadDiferencia !== null) {
            return $this->cantidadDiferencia;
        }
        $enviada = (float) $this->cantidadEnviada;
        $recibida = (float) $this->cantidadRecibida;
        $merma = (float) $this->cantidadBajaMerma;
        return number_format($enviada - ($recibida + $merma), 4, '.', '');
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerLoteId(): int { return $this->loteId; }
    public function obtenerArticuloId(): int { return $this->articuloId; }
    public function obtenerCantidadEnviada(): string { return $this->cantidadEnviada; }
    public function obtenerCantidadRecibida(): string { return $this->cantidadRecibida; }
    public function obtenerCantidadBajaMerma(): string { return $this->cantidadBajaMerma; }
    public function obtenerCantidadDiferencia(): string { return $this->calcularDiferencia(); }
    public function obtenerObservaciones(): ?string { return $this->observaciones; }
    public function obtenerArticuloNombre(): ?string { return $this->articuloNombre; }
    public function obtenerArticuloCodigo(): ?string { return $this->articuloCodigo; }
}
