<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un Pago de Egreso Soberano en Tesorería.
 * GASTOS-1 / FINANCIERO-2 / D-086.
 */
class PagoEgreso
{
    public const ESTADO_CONFIRMADO = 'CONFIRMADO';
    public const ESTADO_REVERSADO = 'REVERSADO';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $metodoPagoId,
        private string $montoTotal,
        private string $monedaCodigo = 'PEN',
        private string $fechaPago = '',
        private ?int $sesionCajaId = null,
        private ?int $movimientoCajaId = null,
        private ?int $cuentaBancariaId = null,
        private ?int $movimientoBancarioId = null,
        private ?string $referenciaOperacion = null,
        private string $estado = self::ESTADO_CONFIRMADO,
        private ?string $motivoReverso = null,
        private ?string $reversadoEn = null,
        private ?int $reversadoPorActorId = null,
        private int $registradoPorActorId = 1,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerMetodoPagoId(): int { return $this->metodoPagoId; }
    public function obtenerMontoTotal(): string { return $this->montoTotal; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerFechaPago(): string { return $this->fechaPago; }
    public function obtenerSesionCajaId(): ?int { return $this->sesionCajaId; }
    public function obtenerMovimientoCajaId(): ?int { return $this->movimientoCajaId; }
    public function obtenerCuentaBancariaId(): ?int { return $this->cuentaBancariaId; }
    public function obtenerMovimientoBancarioId(): ?int { return $this->movimientoBancarioId; }
    public function obtenerReferenciaOperacion(): ?string { return $this->referenciaOperacion; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerMotivoReverso(): ?string { return $this->motivoReverso; }
    public function obtenerReversadoEn(): ?string { return $this->reversadoEn; }
    public function obtenerReversadoPorActorId(): ?int { return $this->reversadoPorActorId; }
    public function obtenerRegistradoPorActorId(): int { return $this->registradoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }

    public function estaConfirmado(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADO;
    }

    public function estaReversado(): bool
    {
        return $this->estado === self::ESTADO_REVERSADO;
    }

    public function esEnEfectivo(): bool
    {
        return $this->sesionCajaId !== null;
    }

    public function esBancario(): bool
    {
        return $this->cuentaBancariaId !== null;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'metodo_pago_id' => $this->metodoPagoId,
            'monto_total' => $this->montoTotal,
            'moneda_codigo' => $this->monedaCodigo,
            'fecha_pago' => $this->fechaPago,
            'sesion_caja_id' => $this->sesionCajaId,
            'movimiento_caja_id' => $this->movimientoCajaId,
            'cuenta_bancaria_id' => $this->cuentaBancariaId,
            'movimiento_bancario_id' => $this->movimientoBancarioId,
            'referencia_operacion' => $this->referenciaOperacion,
            'estado' => $this->estado,
            'motivo_reverso' => $this->motivoReverso,
            'reversado_en' => $this->reversadoEn,
            'reversado_por_actor_id' => $this->reversadoPorActorId,
            'registrado_por_actor_id' => $this->registradoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['metodo_pago_id'] ?? 0),
            (string) ($datos['monto_total'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['fecha_pago'] ?? date('Y-m-d H:i:s')),
            isset($datos['sesion_caja_id']) && $datos['sesion_caja_id'] !== '' ? (int) $datos['sesion_caja_id'] : null,
            isset($datos['movimiento_caja_id']) && $datos['movimiento_caja_id'] !== '' ? (int) $datos['movimiento_caja_id'] : null,
            isset($datos['cuenta_bancaria_id']) && $datos['cuenta_bancaria_id'] !== '' ? (int) $datos['cuenta_bancaria_id'] : null,
            isset($datos['movimiento_bancario_id']) && $datos['movimiento_bancario_id'] !== '' ? (int) $datos['movimiento_bancario_id'] : null,
            isset($datos['referencia_operacion']) && $datos['referencia_operacion'] !== '' ? (string) $datos['referencia_operacion'] : null,
            (string) ($datos['estado'] ?? self::ESTADO_CONFIRMADO),
            isset($datos['motivo_reverso']) ? (string) $datos['motivo_reverso'] : null,
            isset($datos['reversado_en']) ? (string) $datos['reversado_en'] : null,
            isset($datos['reversado_por_actor_id']) ? (int) $datos['reversado_por_actor_id'] : null,
            isset($datos['registrado_por_actor_id']) ? (int) $datos['registrado_por_actor_id'] : 1,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
