<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una cuenta bancaria de la empresa.
 */
class CuentaBancaria
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private string $bancoNombre,
        private string $tipoCuenta,
        private string $numeroCuenta,
        private ?string $numeroCci,
        private string $monedaCodigo,
        private string $titular,
        private string $saldoContable,
        private string $estado,
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

    public function obtenerBancoNombre(): string
    {
        return $this->bancoNombre;
    }

    public function obtenerBanco(): string
    {
        return $this->bancoNombre;
    }

    public function obtenerTipoCuenta(): string
    {
        return $this->tipoCuenta;
    }

    public function obtenerNumeroCuenta(): string
    {
        return $this->numeroCuenta;
    }

    public function obtenerNumeroCci(): ?string
    {
        return $this->numeroCci;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerTitular(): string
    {
        return $this->titular;
    }

    public function obtenerSaldoContable(): string
    {
        return $this->saldoContable;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'banco_nombre' => $this->bancoNombre,
            'tipo_cuenta' => $this->tipoCuenta,
            'numero_cuenta' => $this->numeroCuenta,
            'numero_cci' => $this->numeroCci,
            'moneda_codigo' => $this->monedaCodigo,
            'titular' => $this->titular,
            'saldo_contable' => $this->saldoContable,
            'estado' => $this->estado,
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
            (string) ($datos['banco_nombre'] ?? ''),
            (string) ($datos['tipo_cuenta'] ?? 'CORRIENTE'),
            (string) ($datos['numero_cuenta'] ?? ''),
            isset($datos['numero_cci']) ? (string) $datos['numero_cci'] : null,
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['titular'] ?? ''),
            (string) ($datos['saldo_contable'] ?? '0.00'),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
