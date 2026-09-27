<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un pago o cobro recibido para una cuenta/folio.
 */
class PagoCuenta
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $cuentaFolioId,
        private int $metodoPagoId,
        private string $montoTotal,
        private string $montoAplicado,
        private string $monedaCodigo,
        private ?int $sesionCajaId,
        private ?int $cuentaBancariaId,
        private ?string $referenciaOperacion,
        private string $estado,
        private ?string $motivoReverso,
        private ?string $reversadoEn,
        private ?int $reversadoPorActorId,
        private int $creadoPorActorId,
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

    public function obtenerCuentaFolioId(): int
    {
        return $this->cuentaFolioId;
    }

    public function obtenerMetodoPagoId(): int
    {
        return $this->metodoPagoId;
    }

    public function obtenerMontoTotal(): string
    {
        return $this->montoTotal;
    }

    public function obtenerMontoAplicado(): string
    {
        return $this->montoAplicado;
    }

    public function obtenerMontoAplicadoAcumulado(): string
    {
        return $this->montoAplicado;
    }

    public function obtenerSaldoDisponible(): string
    {
        return $this->calcularSaldoDisponible();
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerSesionCajaId(): ?int
    {
        return $this->sesionCajaId;
    }

    public function obtenerCuentaBancariaId(): ?int
    {
        return $this->cuentaBancariaId;
    }

    public function obtenerReferenciaOperacion(): ?string
    {
        return $this->referenciaOperacion;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaConfirmado(): bool
    {
        return $this->estado === 'CONFIRMADO';
    }

    public function estaReversado(): bool
    {
        return $this->estado === 'REVERSADO';
    }

    /**
     * Calcula el saldo no aplicado disponible del pago con BCMath.
     */
    public function calcularSaldoDisponible(): string
    {
        if (!$this->estaConfirmado()) {
            return '0.00';
        }
        $diff = bcsub($this->montoTotal, $this->montoAplicado, 2);
        return bccomp($diff, '0.00', 2) > 0 ? $diff : '0.00';
    }

    public function obtenerMotivoReverso(): ?string
    {
        return $this->motivoReverso;
    }

    public function obtenerReversadoEn(): ?string
    {
        return $this->reversadoEn;
    }

    public function obtenerReversadoPorActorId(): ?int
    {
        return $this->reversadoPorActorId;
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
            'cuenta_folio_id' => $this->cuentaFolioId,
            'metodo_pago_id' => $this->metodoPagoId,
            'monto_total' => $this->montoTotal,
            'monto_aplicado' => $this->montoAplicado,
            'saldo_no_aplicado' => $this->calcularSaldoDisponible(),
            'moneda_codigo' => $this->monedaCodigo,
            'sesion_caja_id' => $this->sesionCajaId,
            'cuenta_bancaria_id' => $this->cuentaBancariaId,
            'referencia_operacion' => $this->referenciaOperacion,
            'estado' => $this->estado,
            'motivo_reverso' => $this->motivoReverso,
            'reversado_en' => $this->reversadoEn,
            'reversado_por_actor_id' => $this->reversadoPorActorId,
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
            (int) ($datos['metodo_pago_id'] ?? 0),
            (string) ($datos['monto_total'] ?? '0.00'),
            (string) ($datos['monto_aplicado'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            isset($datos['sesion_caja_id']) ? (int) $datos['sesion_caja_id'] : null,
            isset($datos['cuenta_bancaria_id']) ? (int) $datos['cuenta_bancaria_id'] : null,
            isset($datos['referencia_operacion']) ? (string) $datos['referencia_operacion'] : null,
            (string) ($datos['estado'] ?? 'CONFIRMADO'),
            isset($datos['motivo_reverso']) ? (string) $datos['motivo_reverso'] : null,
            isset($datos['reversado_en']) ? (string) $datos['reversado_en'] : null,
            isset($datos['reversado_por_actor_id']) ? (int) $datos['reversado_por_actor_id'] : null,
            (int) ($datos['creado_por_actor_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
