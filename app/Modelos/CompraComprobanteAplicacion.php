<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraComprobanteAplicacion
{
    private ?int $id;
    private int $comprobanteId;
    private ?int $recepcionLineaId;
    private ?int $conformidadId;
    private string $montoAplicado;
    private ?DateTimeImmutable $creadoEn;

    public function __construct(
        ?int $id,
        int $comprobanteId,
        ?int $recepcionLineaId,
        ?int $conformidadId,
        string $montoAplicado,
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->comprobanteId = $comprobanteId;
        $this->recepcionLineaId = $recepcionLineaId;
        $this->conformidadId = $conformidadId;
        $this->montoAplicado = $montoAplicado;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerComprobanteId(): int { return $this->comprobanteId; }
    public function obtenerRecepcionLineaId(): ?int { return $this->recepcionLineaId; }
    public function obtenerConformidadId(): ?int { return $this->conformidadId; }
    public function obtenerMontoAplicado(): string { return $this->montoAplicado; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'comprobante_id' => $this->comprobanteId,
            'recepcion_linea_id' => $this->recepcionLineaId,
            'conformidad_id' => $this->conformidadId,
            'monto_aplicado' => $this->montoAplicado,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
        ];
    }
}
