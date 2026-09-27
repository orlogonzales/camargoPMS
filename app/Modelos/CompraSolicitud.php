<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use DateTimeImmutable;

class CompraSolicitud
{
    public const ESTADO_PENDIENTE = 'PENDIENTE_APROBACION';
    public const ESTADO_APROBADA = 'APROBADA';
    public const ESTADO_RECHAZADA = 'RECHAZADA';

    private ?int $id;
    private string $codigo;
    private string $departamentoArea;
    private ?int $almacenDestinoId;
    private ?int $unidadDestinoId;
    private ?string $fechaLimiteRequerida;
    private ?string $justificacion;
    private string $estado;
    private ?string $motivoRechazo;
    private int $solicitadoPorActorId;
    private ?int $aprobadoPorActorId;
    private ?DateTimeImmutable $creadoEn;
    private ?DateTimeImmutable $actualizadoEn;

    /**
     * @var array<CompraSolicitudLinea>
     */
    private array $lineas = [];

    public function __construct(
        ?int $id,
        string $codigo,
        string $departamentoArea,
        ?int $almacenDestinoId,
        ?int $unidadDestinoId,
        ?string $fechaLimiteRequerida,
        ?string $justificacion,
        string $estado,
        ?string $motivoRechazo,
        int $solicitadoPorActorId,
        ?int $aprobadoPorActorId = null,
        ?DateTimeImmutable $creadoEn = null,
        ?DateTimeImmutable $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = $codigo;
        $this->departamentoArea = $departamentoArea;
        $this->almacenDestinoId = $almacenDestinoId;
        $this->unidadDestinoId = $unidadDestinoId;
        $this->fechaLimiteRequerida = $fechaLimiteRequerida;
        $this->justificacion = $justificacion;
        $this->estado = $estado;
        $this->motivoRechazo = $motivoRechazo;
        $this->solicitadoPorActorId = $solicitadoPorActorId;
        $this->aprobadoPorActorId = $aprobadoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerDepartamentoArea(): string { return $this->departamentoArea; }
    public function obtenerAlmacenDestinoId(): ?int { return $this->almacenDestinoId; }
    public function obtenerUnidadDestinoId(): ?int { return $this->unidadDestinoId; }
    public function obtenerFechaLimiteRequerida(): ?string { return $this->fechaLimiteRequerida; }
    public function obtenerJustificacion(): ?string { return $this->justificacion; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerMotivoRechazo(): ?string { return $this->motivoRechazo; }
    public function obtenerSolicitadoPorActorId(): int { return $this->solicitadoPorActorId; }
    public function obtenerAprobadoPorActorId(): ?int { return $this->aprobadoPorActorId; }
    public function obtenerCreadoEn(): ?DateTimeImmutable { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?DateTimeImmutable { return $this->actualizadoEn; }

    /**
     * @return array<CompraSolicitudLinea>
     */
    public function obtenerLineas(): array { return $this->lineas; }

    /**
     * @param array<CompraSolicitudLinea> $lineas
     */
    public function asignarLineas(array $lineas): void { $this->lineas = $lineas; }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'departamento_area' => $this->departamentoArea,
            'almacen_destino_id' => $this->almacenDestinoId,
            'unidad_destino_id' => $this->unidadDestinoId,
            'fecha_limite_requerida' => $this->fechaLimiteRequerida,
            'justificacion' => $this->justificacion,
            'estado' => $this->estado,
            'motivo_rechazo' => $this->motivoRechazo,
            'solicitado_por_actor_id' => $this->solicitadoPorActorId,
            'aprobado_por_actor_id' => $this->aprobadoPorActorId,
            'creado_en' => $this->creadoEn?->format('Y-m-d H:i:s'),
            'actualizado_en' => $this->actualizadoEn?->format('Y-m-d H:i:s'),
            'lineas' => array_map(fn($l) => $l->aArreglo(), $this->lineas),
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }
}
