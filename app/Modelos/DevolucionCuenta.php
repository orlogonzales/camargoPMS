<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una devolución o reembolso real de fondos al huésped.
 */
class DevolucionCuenta
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $cuentaFolioId,
        private int $pagoOrigenId,
        private int $metodoPagoId,
        private ?int $sesionCajaId,
        private ?int $cuentaBancariaId,
        private string $monto,
        private string $monedaCodigo,
        private string $motivo,
        private string $estado,
        private int $creadoPorActorId,
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

    public function obtenerCuentaFolioId(): int
    {
        return $this->cuentaFolioId;
    }

    public function obtenerPagoOrigenId(): int
    {
        return $this->pagoOrigenId;
    }

    public function obtenerMetodoPagoId(): int
    {
        return $this->metodoPagoId;
    }

    public function obtenerSesionCajaId(): ?int
    {
        return $this->sesionCajaId;
    }

    public function obtenerCuentaBancariaId(): ?int
    {
        return $this->cuentaBancariaId;
    }

    public function obtenerMonto(): string
    {
        return $this->monto;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerMotivo(): string
    {
        return $this->motivo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === 'CONFIRMADA';
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

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cuenta_folio_id' => $this->cuentaFolioId,
            'pago_origen_id' => $this->pagoOrigenId,
            'metodo_pago_id' => $this->metodoPagoId,
            'sesion_caja_id' => $this->sesionCajaId,
            'cuenta_bancaria_id' => $this->cuentaBancariaId,
            'monto' => $this->monto,
            'moneda_codigo' => $this->monedaCodigo,
            'motivo' => $this->motivo,
            'estado' => $this->estado,
            'creado_por_actor_id' => $this->creadoPorActorId,
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
            (int) ($datos['cuenta_folio_id'] ?? 0),
            (int) ($datos['pago_origen_id'] ?? 0),
            (int) ($datos['metodo_pago_id'] ?? 0),
            isset($datos['sesion_caja_id']) ? (int) $datos['sesion_caja_id'] : null,
            isset($datos['cuenta_bancaria_id']) ? (int) $datos['cuenta_bancaria_id'] : null,
            (string) ($datos['monto'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['motivo'] ?? ''),
            (string) ($datos['estado'] ?? 'CONFIRMADA'),
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
