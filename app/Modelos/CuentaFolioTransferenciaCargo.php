<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio inmutable que representa la trazabilidad y auditoría
 * de una transferencia o división (split) de cargo entre folios.
 */
class CuentaFolioTransferenciaCargo
{
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $cargoOrigenId,
        private ?int $cargoDestinoId,
        private int $cuentaFolioOrigenId,
        private int $cuentaFolioDestinoId,
        private string $montoTransferido,
        private string $tipoOperacion, // 'TOTAL' | 'SPLIT_PARCIAL'
        private string $motivo,
        private int $actorId,
        private ?string $transferidoEn = null
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

    public function obtenerCargoOrigenId(): int
    {
        return $this->cargoOrigenId;
    }

    public function obtenerCargoDestinoId(): ?int
    {
        return $this->cargoDestinoId;
    }

    public function obtenerCuentaFolioOrigenId(): int
    {
        return $this->cuentaFolioOrigenId;
    }

    public function obtenerCuentaFolioDestinoId(): int
    {
        return $this->cuentaFolioDestinoId;
    }

    public function obtenerMontoTransferido(): string
    {
        return $this->montoTransferido;
    }

    public function obtenerTipoOperacion(): string
    {
        return $this->tipoOperacion;
    }

    public function esTotal(): bool
    {
        return $this->tipoOperacion === 'TOTAL';
    }

    public function esSplitParcial(): bool
    {
        return $this->tipoOperacion === 'SPLIT_PARCIAL';
    }

    public function obtenerMotivo(): string
    {
        return $this->motivo;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerTransferidoEn(): ?string
    {
        return $this->transferidoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cargo_origen_id' => $this->cargoOrigenId,
            'cargo_destino_id' => $this->cargoDestinoId,
            'cuenta_folio_origen_id' => $this->cuentaFolioOrigenId,
            'cuenta_folio_destino_id' => $this->cuentaFolioDestinoId,
            'monto_transferido' => $this->montoTransferido,
            'tipo_operacion' => $this->tipoOperacion,
            'motivo' => $this->motivo,
            'actor_id' => $this->actorId,
            'transferido_en' => $this->transferidoEn,
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
            (int) ($datos['cargo_origen_id'] ?? 0),
            isset($datos['cargo_destino_id']) && $datos['cargo_destino_id'] !== null ? (int) $datos['cargo_destino_id'] : null,
            (int) ($datos['cuenta_folio_origen_id'] ?? 0),
            (int) ($datos['cuenta_folio_destino_id'] ?? 0),
            (string) ($datos['monto_transferido'] ?? '0.00'),
            (string) ($datos['tipo_operacion'] ?? 'TOTAL'),
            (string) ($datos['motivo'] ?? ''),
            (int) ($datos['actor_id'] ?? 0),
            isset($datos['transferido_en']) ? (string) $datos['transferido_en'] : null
        );
    }
}
