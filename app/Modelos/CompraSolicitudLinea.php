<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraSolicitudLinea
{
    private ?int $id;
    private int $solicitudId;
    private string $tipoLinea;
    private ?int $articuloId;
    private ?string $descripcionServicio;
    private string $cantidadSolicitada;
    private ?string $especificacionesTecnicas;
    private ?DateTimeImmutable $creadoEn;

    public function __construct(
        ?int $id,
        int $solicitudId,
        string $tipoLinea,
        ?int $articuloId,
        ?string $descripcionServicio,
        string $cantidadSolicitada,
        ?string $especificacionesTecnicas = null,
        ?DateTimeImmutable $creadoEn = null
    ) {
        $this->id = $id;
        $this->solicitudId = $solicitudId;
        $this->tipoLinea = $tipoLinea;
        $this->articuloId = $articuloId;
        $this->descripcionServicio = $descripcionServicio;
        $this->cantidadSolicitada = $cantidadSolicitada;
        $this->especificacionesTecnicas = $especificacionesTecnicas;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerSolicitudId(): int { return $this->solicitudId; }
    public function obtenerTipoLinea(): string { return $this->tipoLinea; }
    public function obtenerArticuloId(): ?int { return $this->articuloId; }
    public function obtenerDescripcionServicio(): ?string { return $this->descripcionServicio; }
    public function obtenerCantidadSolicitada(): string { return $this->cantidadSolicitada; }
    public function obtenerEspecificacionesTecnicas(): ?string { return $this->especificacionesTecnicas; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitudId,
            'tipo_linea' => $this->tipoLinea,
            'articulo_id' => $this->articuloId,
            'descripcion_servicio' => $this->descripcionServicio,
            'cantidad_solicitada' => $this->cantidadSolicitada,
            'especificaciones_tecnicas' => $this->especificacionesTecnicas,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
        ];
    }
}
