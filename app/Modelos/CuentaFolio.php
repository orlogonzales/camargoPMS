<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un folio o cuenta financiera consolidada de una reserva o arrendamiento.
 */
class CuentaFolio
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private ?int $reservaId,
        private int $personaTitularId,
        private string $monedaCodigo,
        private string $estado,
        private int $creadoPorActorId,
        private ?int $arrendamientoId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
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

    public function obtenerReservaId(): ?int
    {
        return $this->reservaId;
    }

    public function obtenerArrendamientoId(): ?int
    {
        return $this->arrendamientoId;
    }

    public function esDeReserva(): bool
    {
        return $this->reservaId !== null;
    }

    public function esDeArrendamiento(): bool
    {
        return $this->arrendamientoId !== null;
    }

    public function obtenerPersonaTitularId(): int
    {
        return $this->personaTitularId;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaAbierta(): bool
    {
        return $this->estado === 'ABIERTA';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'CERRADA';
    }

    public function estaCongelada(): bool
    {
        return $this->estado === 'CONGELADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function obtenerCreadoPorActorId(): int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
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
            'reserva_id' => $this->reservaId,
            'arrendamiento_id' => $this->arrendamientoId,
            'persona_titular_id' => $this->personaTitularId,
            'moneda_codigo' => $this->monedaCodigo,
            'estado' => $this->estado,
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
            isset($datos['reserva_id']) && $datos['reserva_id'] !== null ? (int) $datos['reserva_id'] : null,
            (int) ($datos['persona_titular_id'] ?? 0),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['estado'] ?? 'ABIERTA'),
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['arrendamiento_id']) && $datos['arrendamiento_id'] !== null ? (int) $datos['arrendamiento_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
