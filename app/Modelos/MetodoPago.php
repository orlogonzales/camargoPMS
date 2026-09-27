<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un método o medio de pago.
 */
class MetodoPago
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private string $nombre,
        private string $tipoDestino,
        private bool $requiereReferencia,
        private bool $activo,
        private ?string $creadoEn = null
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

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerTipoDestino(): string
    {
        return $this->tipoDestino;
    }

    public function requiereReferencia(): bool
    {
        return $this->requiereReferencia;
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function esCajaFisica(): bool
    {
        return $this->tipoDestino === 'CAJA_FISICA';
    }

    public function esCuentaBancaria(): bool
    {
        return $this->tipoDestino === 'CUENTA_BANCARIA';
    }

    public function esPasarela(): bool
    {
        return $this->tipoDestino === 'PASARELA_INTERMEDIARIO';
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo_destino' => $this->tipoDestino,
            'requiere_referencia' => $this->requiereReferencia,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
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
            (string) ($datos['nombre'] ?? ''),
            (string) ($datos['tipo_destino'] ?? 'CAJA_FISICA'),
            !empty($datos['requiere_referencia']),
            !empty($datos['activo']),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
