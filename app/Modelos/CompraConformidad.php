<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraConformidad
{
    private ?int $id;
    private string $codigo;
    private int $ordenCompraId;
    private int $ordenLineaId;
    private DateTimeImmutable $fechaConformidad;
    private string $informeTrabajoRealizado;
    private int $aprobadoPorActorId;
    private ?DateTimeImmutable $creadoEn;

    public function __construct(
        ?int $id,
        string $codigo,
        int $ordenCompraId,
        int $ordenLineaId,
        DateTimeImmutable $fechaConformidad,
        string $informeTrabajoRealizado,
        int $aprobadoPorActorId,
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->ordenCompraId = $ordenCompraId;
        $this->ordenLineaId = $ordenLineaId;
        $this->fechaConformidad = $fechaConformidad;
        $this->informeTrabajoRealizado = $informeTrabajoRealizado;
        $this->aprobadoPorActorId = $aprobadoPorActorId;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerOrdenCompraId(): int { return $this->ordenCompraId; }
    public function obtenerOrdenLineaId(): int { return $this->ordenLineaId; }
    public function obtenerFechaConformidad(): DateTimeImmutable { return $this->fechaConformidad; }
    public function obtenerInformeTrabajoRealizado(): string { return $this->informeTrabajoRealizado; }
    public function obtenerAprobadoPorActorId(): int { return $this->aprobadoPorActorId; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'orden_compra_id' => $this->ordenCompraId,
            'orden_linea_id' => $this->ordenLineaId,
            'fecha_conformidad' => $this->fechaConformidad->format('Y-m-d H:i:s'),
            'informe_trabajo_realizado' => $this->informeTrabajoRealizado,
            'aprobado_por_actor_id' => $this->aprobadoPorActorId,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
