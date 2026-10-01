<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio que representa una Transacción de Pasarela de Pago Externa.
 *
 * Mantiene tres ejes de estado ortogonales (D-107):
 * - estado_pago: ciclo de vida técnico del intento de pago (INICIADO, PENDIENTE, PROCESANDO, APROBADO, FALLIDO, EXPIRADO, ANULADO)
 * - estado_conciliacion: correlación de negocio con inventario y reservas (PENDIENTE, CONCILIADO, DISCREPANCIA_*, NO_REQUERIDA)
 * - estado_reembolso: estado de devoluciones monetarias (NO_APLICA, PENDIENTE, PROCESANDO, REEMBOLSADO_*, FALLIDO)
 */
class PagoTransaccionPasarela
{
    // Eje 1: Estados de Pago
    public const ESTADO_PAGO_INICIADO = 'INICIADO';
    public const ESTADO_PAGO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PAGO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_PAGO_APROBADO = 'APROBADO';
    public const ESTADO_PAGO_FALLIDO = 'FALLIDO';
    public const ESTADO_PAGO_EXPIRADO = 'EXPIRADO';
    public const ESTADO_PAGO_ANULADO = 'ANULADO';

    // Eje 2: Estados de Conciliación
    public const ESTADO_CONCILIACION_PENDIENTE = 'PENDIENTE';
    public const ESTADO_CONCILIACION_CONCILIADO = 'CONCILIADO';
    public const ESTADO_CONCILIACION_DISCREPANCIA_HOLD_EXPIRADO = 'DISCREPANCIA_HOLD_EXPIRADO';
    public const ESTADO_CONCILIACION_DISCREPANCIA_MONTO = 'DISCREPANCIA_MONTO';
    public const ESTADO_CONCILIACION_DISCREPANCIA_MONEDA = 'DISCREPANCIA_MONEDA';
    public const ESTADO_CONCILIACION_DISCREPANCIA_SOBREVENTA = 'DISCREPANCIA_SOBREVENTA';
    public const ESTADO_CONCILIACION_NO_REQUERIDA = 'NO_REQUERIDA';

    // Eje 3: Estados de Reembolso
    public const ESTADO_REEMBOLSO_NO_APLICA = 'NO_APLICA';
    public const ESTADO_REEMBOLSO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_REEMBOLSO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_REEMBOLSO_TOTAL = 'REEMBOLSADO_TOTAL';
    public const ESTADO_REEMBOLSO_PARCIAL = 'REEMBOLSADO_PARCIAL';
    public const ESTADO_REEMBOLSO_FALLIDO = 'FALLIDO';

    // Tipos de Operación
    public const OPERACION_ORDEN_CHECKOUT = 'ORDEN_CHECKOUT';
    public const OPERACION_CARGO_DIRECTO = 'CARGO_DIRECTO';
    public const OPERACION_AUTORIZACION_PREVIA = 'AUTORIZACION_PREVIA';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $reservaId,
        private ?int $cuentaFolioId,
        private ?int $pagoCuentaId,
        private string $proveedor,
        private string $tipoOperacion,
        private ?string $proveedorOrdenId,
        private ?string $proveedorTransaccionId,
        private ?string $proveedorReferencia,
        private string $estadoPago,
        private string $estadoConciliacion,
        private string $estadoReembolso,
        private string $monedaCodigo,
        private string $montoEsperado,
        private ?string $montoCobrado,
        private string $montoReembolsado,
        private ?array $metadatosProveedor,
        private ?string $motivoDiscrepancia,
        private ?string $motivoReembolso,
        private int $actorId,
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

    public function obtenerReservaId(): int
    {
        return $this->reservaId;
    }

    public function obtenerCuentaFolioId(): ?int
    {
        return $this->cuentaFolioId;
    }

    public function obtenerPagoCuentaId(): ?int
    {
        return $this->pagoCuentaId;
    }

    public function obtenerProveedor(): string
    {
        return $this->proveedor;
    }

    public function obtenerTipoOperacion(): string
    {
        return $this->tipoOperacion;
    }

    public function obtenerProveedorOrdenId(): ?string
    {
        return $this->proveedorOrdenId;
    }

    public function obtenerProveedorTransaccionId(): ?string
    {
        return $this->proveedorTransaccionId;
    }

    public function obtenerProveedorReferencia(): ?string
    {
        return $this->proveedorReferencia;
    }

    public function obtenerEstadoPago(): string
    {
        return $this->estadoPago;
    }

    public function obtenerEstadoConciliacion(): string
    {
        return $this->estadoConciliacion;
    }

    public function obtenerEstadoReembolso(): string
    {
        return $this->estadoReembolso;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerMontoEsperado(): string
    {
        return $this->montoEsperado;
    }

    public function obtenerMontoCobrado(): ?string
    {
        return $this->montoCobrado;
    }

    public function obtenerMontoReembolsado(): string
    {
        return $this->montoReembolsado;
    }

    public function obtenerMetadatosProveedor(): ?array
    {
        return $this->metadatosProveedor;
    }

    public function obtenerMotivoDiscrepancia(): ?string
    {
        return $this->motivoDiscrepancia;
    }

    public function obtenerMotivoReembolso(): ?string
    {
        return $this->motivoReembolso;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function estaAprobado(): bool
    {
        return $this->estadoPago === self::ESTADO_PAGO_APROBADO;
    }

    public function estaPendiente(): bool
    {
        return in_array($this->estadoPago, [self::ESTADO_PAGO_INICIADO, self::ESTADO_PAGO_PENDIENTE, self::ESTADO_PAGO_PROCESANDO], true);
    }

    public function estaConciliado(): bool
    {
        return $this->estadoConciliacion === self::ESTADO_CONCILIACION_CONCILIADO;
    }

    public function tieneDiscrepancia(): bool
    {
        return str_starts_with($this->estadoConciliacion, 'DISCREPANCIA_');
    }

    public function esReembolsable(): bool
    {
        return $this->estaAprobado() && in_array($this->estadoReembolso, [self::ESTADO_REEMBOLSO_NO_APLICA, self::ESTADO_REEMBOLSO_FALLIDO, self::ESTADO_REEMBOLSO_PARCIAL], true);
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'reserva_id' => $this->reservaId,
            'cuenta_folio_id' => $this->cuentaFolioId,
            'pago_cuenta_id' => $this->pagoCuentaId,
            'proveedor' => $this->proveedor,
            'tipo_operacion' => $this->tipoOperacion,
            'proveedor_orden_id' => $this->proveedorOrdenId,
            'proveedor_transaccion_id' => $this->proveedorTransaccionId,
            'proveedor_referencia' => $this->proveedorReferencia,
            'estado_pago' => $this->estadoPago,
            'estado_conciliacion' => $this->estadoConciliacion,
            'estado_reembolso' => $this->estadoReembolso,
            'moneda_codigo' => $this->monedaCodigo,
            'monto_esperado' => $this->montoEsperado,
            'monto_cobrado' => $this->montoCobrado,
            'monto_reembolsado' => $this->montoReembolsado,
            'metadatos_proveedor' => $this->metadatosProveedor,
            'motivo_discrepancia' => $this->motivoDiscrepancia,
            'motivo_reembolso' => $this->motivoReembolso,
            'actor_id' => $this->actorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public static function desdeArray(array $datos): self
    {
        $metadatos = $datos['metadatos_proveedor'] ?? null;
        if (is_string($metadatos)) {
            $decodificado = json_decode($metadatos, true);
            $metadatos = is_array($decodificado) ? $decodificado : null;
        }

        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            codigo: (string) ($datos['codigo'] ?? ''),
            reservaId: (int) ($datos['reserva_id'] ?? 0),
            cuentaFolioId: isset($datos['cuenta_folio_id']) && $datos['cuenta_folio_id'] !== null ? (int) $datos['cuenta_folio_id'] : null,
            pagoCuentaId: isset($datos['pago_cuenta_id']) && $datos['pago_cuenta_id'] !== null ? (int) $datos['pago_cuenta_id'] : null,
            proveedor: (string) ($datos['proveedor'] ?? ''),
            tipoOperacion: (string) ($datos['tipo_operacion'] ?? self::OPERACION_ORDEN_CHECKOUT),
            proveedorOrdenId: isset($datos['proveedor_orden_id']) ? (string) $datos['proveedor_orden_id'] : null,
            proveedorTransaccionId: isset($datos['proveedor_transaccion_id']) ? (string) $datos['proveedor_transaccion_id'] : null,
            proveedorReferencia: isset($datos['proveedor_referencia']) ? (string) $datos['proveedor_referencia'] : null,
            estadoPago: (string) ($datos['estado_pago'] ?? self::ESTADO_PAGO_INICIADO),
            estadoConciliacion: (string) ($datos['estado_conciliacion'] ?? self::ESTADO_CONCILIACION_PENDIENTE),
            estadoReembolso: (string) ($datos['estado_reembolso'] ?? self::ESTADO_REEMBOLSO_NO_APLICA),
            monedaCodigo: (string) ($datos['moneda_codigo'] ?? 'PEN'),
            montoEsperado: (string) ($datos['monto_esperado'] ?? '0.00'),
            montoCobrado: isset($datos['monto_cobrado']) && $datos['monto_cobrado'] !== null ? (string) $datos['monto_cobrado'] : null,
            montoReembolsado: (string) ($datos['monto_reembolsado'] ?? '0.00'),
            metadatosProveedor: $metadatos,
            motivoDiscrepancia: isset($datos['motivo_discrepancia']) ? (string) $datos['motivo_discrepancia'] : null,
            motivoReembolso: isset($datos['motivo_reembolso']) ? (string) $datos['motivo_reembolso'] : null,
            actorId: (int) ($datos['actor_id'] ?? 0),
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
