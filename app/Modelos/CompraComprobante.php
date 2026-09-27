<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraComprobante
{
    public const MATCHING_CONFORME = 'CONFORME';
    public const MATCHING_CON_DIFERENCIA = 'CON_DIFERENCIA';
    public const MATCHING_OBSERVADO = 'OBSERVADO';

    private ?int $id;
    private int $proveedorId;
    private int $ordenCompraId;
    private string $tipoComprobante;
    private string $serie;
    private string $numero;
    private string $fechaEmision;
    private string $fechaVencimiento;
    private string $monedaCodigo;
    private string $subtotal;
    private string $impuesto;
    private string $total;
    private string $estadoMatching;
    private ?string $observacionesMatching;
    private int $registradoPorActorId;
    private ?DateTimeImmutable $creadoEn;
    private ?DateTimeImmutable $actualizadoEn;

    /**
     * @var array<CompraComprobanteAplicacion>
     */
    private array $aplicaciones = [];

    public function __construct(
        ?int $id,
        int $proveedorId,
        int $ordenCompraId,
        string $tipoComprobante,
        string $serie,
        string $numero,
        string $fechaEmision,
        string $fechaVencimiento,
        string $monedaCodigo,
        string $subtotal,
        string $impuesto,
        string $total,
        string $estadoMatching,
        ?string $observacionesMatching,
        int $registradoPorActorId,
        ?DateTimeImmutable $creadoEn = null,
        ?DateTimeImmutable $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->proveedorId = $proveedorId;
        $this->ordenCompraId = $ordenCompraId;
        $this->tipoComprobante = $tipoComprobante;
        $this->serie = $serie;
        $this->numero = $numero;
        $this->fechaEmision = $fechaEmision;
        $this->fechaVencimiento = $fechaVencimiento;
        $this->monedaCodigo = $monedaCodigo;
        $this->subtotal = $subtotal;
        $this->impuesto = $impuesto;
        $this->total = $total;
        $this->estadoMatching = $estadoMatching;
        $this->observacionesMatching = $observacionesMatching;
        $this->registradoPorActorId = $registradoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerProveedorId(): int { return $this->proveedorId; }
    public function obtenerOrdenCompraId(): int { return $this->ordenCompraId; }
    public function obtenerTipoComprobante(): string { return $this->tipoComprobante; }
    public function obtenerSerie(): string { return $this->serie; }
    public function obtenerNumero(): string { return $this->numero; }
    public function obtenerFechaEmision(): string { return $this->fechaEmision; }
    public function obtenerFechaVencimiento(): string { return $this->fechaVencimiento; }
    public function obtenerMonedaCodigo(): string { return $this->monedaCodigo; }
    public function obtenerSubtotal(): string { return $this->subtotal; }
    public function obtenerImpuesto(): string { return $this->impuesto; }
    public function obtenerTotal(): string { return $this->total; }
    public function obtenerEstadoMatching(): string { return $this->estadoMatching; }
    public function obtenerObservacionesMatching(): ?string { return $this->observacionesMatching; }
    public function obtenerRegistradoPorActorId(): int { return $this->registradoPorActorId; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?DateTimeImmutable { return $this->actualizadoEn; }

    /**
     * @return array<CompraComprobanteAplicacion>
     */
    public function obtenerAplicaciones(): array { return $this->aplicaciones; }

    /**
     * @param array<CompraComprobanteAplicacion> $aplicaciones
     */
    public function asignarAplicaciones(array $aplicaciones): void { $this->aplicaciones = $aplicaciones; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedorId,
            'orden_compra_id' => $this->ordenCompraId,
            'tipo_comprobante' => $this->tipoComprobante,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'moneda_codigo' => $this->monedaCodigo,
            'subtotal' => $this->subtotal,
            'impuesto' => $this->impuesto,
            'total' => $this->total,
            'estado_matching' => $this->estadoMatching,
            'observaciones_matching' => $this->observacionesMatching,
            'registrado_por_actor_id' => $this->registradoPorActorId,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
            'actualizado_en' => $this->actualizadoEn?->format('Y-m-d H:i:s'),
            'aplicaciones' => array_map(fn($a) => $a->aArreglo(), $this->aplicaciones),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
