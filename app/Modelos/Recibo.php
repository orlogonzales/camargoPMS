<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un Recibo de Cobranza (RECIBOS-1 / D-082).
 *
 * Constancia histórica e inmutable de un hecho económico de cobro en T0.
 * Congela fehacientemente los datos del titular, medio de recaudación,
 * las obligaciones amortizadas y los saldos del folio resultantes.
 */
class Recibo
{
    public const ESTADO_EMITIDO = 'EMITIDO';
    public const ESTADO_ANULADO = 'ANULADO';

    /**
     * @param ReciboLinea[] $lineas
     */
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $cuentaFolioId,
        private int $pagoId,
        private int $personaId,
        private string $personaNombreSnapshot,
        private string $personaDocumentoTipoSnapshot,
        private string $personaDocumentoNumeroSnapshot,
        private ?int $arrendamientoId = null,
        private ?int $reservaId = null,
        private ?int $documentoEmitidoId = null,
        private string $montoRecaudado = '0.00',
        private string $montoImputado = '0.00',
        private string $montoNoAplicadoPago = '0.00',
        private string $saldoPendienteFolioDespues = '0.00',
        private string $saldoFavorFolioDespues = '0.00',
        private string $monedaCodigo = 'PEN',
        private string $metodoPagoNombre = 'Efectivo',
        private ?string $referenciaCobro = null,
        private string $conceptoGeneral = 'Cobranza a cuenta folio',
        private ?string $notas = null,
        private string $fechaEmision = '',
        private string $estado = self::ESTADO_EMITIDO,
        private ?string $motivoAnulacion = null,
        private ?string $anuladoEn = null,
        private ?int $anuladoPorActorId = null,
        private int $creadoPorActorId = 1,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        private array $lineas = []
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $lineas = [];
        if (isset($datos['lineas']) && is_array($datos['lineas'])) {
            foreach ($datos['lineas'] as $l) {
                $lineas[] = $l instanceof ReciboLinea ? $l : ReciboLinea::desdeArreglo($l);
            }
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['cuenta_folio_id'] ?? 0),
            (int) ($datos['pago_id'] ?? 0),
            (int) ($datos['persona_id'] ?? 0),
            (string) ($datos['persona_nombre_snapshot'] ?? ''),
            (string) ($datos['persona_documento_tipo_snapshot'] ?? ''),
            (string) ($datos['persona_documento_numero_snapshot'] ?? ''),
            isset($datos['arrendamiento_id']) && $datos['arrendamiento_id'] !== null ? (int) $datos['arrendamiento_id'] : null,
            isset($datos['reserva_id']) && $datos['reserva_id'] !== null ? (int) $datos['reserva_id'] : null,
            isset($datos['documento_emitido_id']) && $datos['documento_emitido_id'] !== null ? (int) $datos['documento_emitido_id'] : null,
            (string) ($datos['monto_recaudado'] ?? '0.00'),
            (string) ($datos['monto_imputado'] ?? '0.00'),
            (string) ($datos['monto_no_aplicado_pago'] ?? '0.00'),
            (string) ($datos['saldo_pendiente_folio_despues'] ?? '0.00'),
            (string) ($datos['saldo_favor_folio_despues'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            (string) ($datos['metodo_pago_nombre'] ?? 'Efectivo'),
            isset($datos['referencia_cobro']) ? (string) $datos['referencia_cobro'] : null,
            (string) ($datos['concepto_general'] ?? 'Cobranza a cuenta folio'),
            isset($datos['notas']) ? (string) $datos['notas'] : null,
            (string) ($datos['fecha_emision'] ?? date('Y-m-d H:i:s')),
            (string) ($datos['estado'] ?? self::ESTADO_EMITIDO),
            isset($datos['motivo_anulacion']) ? (string) $datos['motivo_anulacion'] : null,
            isset($datos['anulado_en']) ? (string) $datos['anulado_en'] : null,
            isset($datos['anulado_por_actor_id']) && $datos['anulado_por_actor_id'] !== null ? (int) $datos['anulado_por_actor_id'] : null,
            (int) ($datos['creado_por_actor_id'] ?? 1),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            $lineas
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cuenta_folio_id' => $this->cuentaFolioId,
            'pago_id' => $this->pagoId,
            'persona_id' => $this->personaId,
            'persona_nombre_snapshot' => $this->personaNombreSnapshot,
            'persona_documento_tipo_snapshot' => $this->personaDocumentoTipoSnapshot,
            'persona_documento_numero_snapshot' => $this->personaDocumentoNumeroSnapshot,
            'arrendamiento_id' => $this->arrendamientoId,
            'reserva_id' => $this->reservaId,
            'documento_emitido_id' => $this->documentoEmitidoId,
            'monto_recaudado' => $this->montoRecaudado,
            'monto_imputado' => $this->montoImputado,
            'monto_no_aplicado_pago' => $this->montoNoAplicadoPago,
            'saldo_pendiente_folio_despues' => $this->saldoPendienteFolioDespues,
            'saldo_favor_folio_despues' => $this->saldoFavorFolioDespues,
            'moneda_codigo' => $this->monedaCodigo,
            'metodo_pago_nombre' => $this->metodoPagoNombre,
            'referencia_cobro' => $this->referenciaCobro,
            'concepto_general' => $this->conceptoGeneral,
            'notas' => $this->notas,
            'fecha_emision' => $this->fechaEmision,
            'estado' => $this->estado,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulado_en' => $this->anuladoEn,
            'anulado_por_actor_id' => $this->anuladoPorActorId,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'lineas' => array_map(static fn(ReciboLinea $l) => $l->aArreglo(), $this->lineas),
        ];
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

    public function obtenerPagoId(): int
    {
        return $this->pagoId;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerPersonaNombreSnapshot(): string
    {
        return $this->personaNombreSnapshot;
    }

    public function obtenerPersonaDocumentoTipoSnapshot(): string
    {
        return $this->personaDocumentoTipoSnapshot;
    }

    public function obtenerPersonaDocumentoNumeroSnapshot(): string
    {
        return $this->personaDocumentoNumeroSnapshot;
    }

    public function obtenerArrendamientoId(): ?int
    {
        return $this->arrendamientoId;
    }

    public function obtenerReservaId(): ?int
    {
        return $this->reservaId;
    }

    public function obtenerDocumentoEmitidoId(): ?int
    {
        return $this->documentoEmitidoId;
    }

    public function obtenerMontoRecaudado(): string
    {
        return $this->montoRecaudado;
    }

    public function obtenerMontoImputado(): string
    {
        return $this->montoImputado;
    }

    public function obtenerMontoNoAplicadoPago(): string
    {
        return $this->montoNoAplicadoPago;
    }

    public function obtenerSaldoPendienteFolioDespues(): string
    {
        return $this->saldoPendienteFolioDespues;
    }

    public function obtenerSaldoFavorFolioDespues(): string
    {
        return $this->saldoFavorFolioDespues;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerMetodoPagoNombre(): string
    {
        return $this->metodoPagoNombre;
    }

    public function obtenerReferenciaCobro(): ?string
    {
        return $this->referenciaCobro;
    }

    public function obtenerConceptoGeneral(): string
    {
        return $this->conceptoGeneral;
    }

    public function obtenerNotas(): ?string
    {
        return $this->notas;
    }

    public function obtenerFechaEmision(): string
    {
        return $this->fechaEmision;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaEmitido(): bool
    {
        return $this->estado === self::ESTADO_EMITIDO;
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    public function obtenerMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function obtenerAnuladoEn(): ?string
    {
        return $this->anuladoEn;
    }

    public function obtenerAnuladoPorActorId(): ?int
    {
        return $this->anuladoPorActorId;
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
     * @return ReciboLinea[]
     */
    public function obtenerLineas(): array
    {
        return $this->lineas;
    }

    /**
     * @param ReciboLinea[] $lineas
     */
    public function asignarLineas(array $lineas): void
    {
        $this->lineas = $lineas;
    }

    public function asignarDocumentoEmitidoId(int $docId): void
    {
        $this->documentoEmitidoId = $docId;
    }
}
