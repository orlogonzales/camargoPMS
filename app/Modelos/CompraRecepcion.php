<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraRecepcion
{
    private ?int $id;
    private string $codigo;
    private int $ordenCompraId;
    private int $almacenId;
    private ?string $numeroGuiaRemision;
    private DateTimeImmutable $fechaRecepcion;
    private ?string $observaciones;
    private int $recibidoPorActorId;
    private ?DateTimeImmutable $creadoEn;

    /**
     * @var array<CompraRecepcionLinea>
     */
    private array $lineas = [];

    public function __construct(
        ?int $id,
        string $codigo,
        int $ordenCompraId,
        int $almacenId,
        ?string $numeroGuiaRemision,
        DateTimeImmutable $fechaRecepcion,
        ?string $observaciones,
        int $recibidoPorActorId,
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->ordenCompraId = $ordenCompraId;
        $this->almacenId = $almacenId;
        $this->numeroGuiaRemision = $numeroGuiaRemision;
        $this->fechaRecepcion = $fechaRecepcion;
        $this->observaciones = $observaciones;
        $this->recibidoPorActorId = $recibidoPorActorId;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerOrdenCompraId(): int { return $this->ordenCompraId; }
    public function obtenerAlmacenId(): int { return $this->almacenId; }
    public function obtenerNumeroGuiaRemision(): ?string { return $this->numeroGuiaRemision; }
    public function obtenerFechaRecepcion(): DateTimeImmutable { return $this->fechaRecepcion; }
    public function obtenerObservaciones(): ?string { return $this->observaciones; }
    public function obtenerRecibidoPorActorId(): int { return $this->recibidoPorActorId; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    /**
     * @return array<CompraRecepcionLinea>
     */
    public function obtenerLineas(): array { return $this->lineas; }

    /**
     * @param array<CompraRecepcionLinea> $lineas
     */
    public function asignarLineas(array $lineas): void { $this->lineas = $lineas; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'orden_compra_id' => $this->ordenCompraId,
            'almacen_id' => $this->almacenId,
            'numero_guia_remision' => $this->numeroGuiaRemision,
            'fecha_recepcion' => $this->fechaRecepcion->format('Y-m-d H:i:s'),
            'observaciones' => $this->observaciones,
            'recibido_por_actor_id' => $this->recibidoPorActorId,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
            'lineas' => array_map(fn($l) => $l->aArreglo(), $this->lineas),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
