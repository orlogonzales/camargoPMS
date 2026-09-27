<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio para las Incidencias Documentales (Auditoría de Inconsistencias Físicas).
 */
class DocumentoIncidencia
{
    public const TIPO_ARCHIVO_FALTANTE = 'ARCHIVO_FALTANTE';
    public const TIPO_HASH_NO_COINCIDE = 'HASH_NO_COINCIDE';
    public const TIPO_ERROR_LECTURA = 'ERROR_LECTURA';

    private ?int $id;
    private int $documentoEmitidoId;
    private string $tipoIncidencia;
    private string $descripcion;
    private int $detectadoPorActorId;
    private ?string $detectadoEn;
    private bool $resuelto;
    private ?string $resueltoEn;
    private ?string $resolucionNotas;

    public function __construct(
        ?int $id,
        int $documentoEmitidoId,
        string $tipoIncidencia,
        string $descripcion,
        int $detectadoPorActorId = 1,
        ?string $detectadoEn = null,
        bool $resuelto = false,
        ?string $resueltoEn = null,
        ?string $resolucionNotas = null
    ) {
        $this->id = $id;
        $this->documentoEmitidoId = $documentoEmitidoId;
        $this->tipoIncidencia = strtoupper(trim($tipoIncidencia));
        $this->descripcion = trim($descripcion);
        $this->detectadoPorActorId = $detectadoPorActorId;
        $this->detectadoEn = $detectadoEn;
        $this->resuelto = $resuelto;
        $this->resueltoEn = $resueltoEn;
        $this->resolucionNotas = $resolucionNotas ? trim($resolucionNotas) : null;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerDocumentoEmitidoId(): int
    {
        return $this->documentoEmitidoId;
    }

    public function obtenerTipoIncidencia(): string
    {
        return $this->tipoIncidencia;
    }

    public function obtenerDescripcion(): string
    {
        return $this->descripcion;
    }

    public function obtenerDetectadoPorActorId(): int
    {
        return $this->detectadoPorActorId;
    }

    public function obtenerDetectadoEn(): ?string
    {
        return $this->detectadoEn;
    }

    public function estaResuelto(): bool
    {
        return $this->resuelto;
    }

    public function obtenerResueltoEn(): ?string
    {
        return $this->resueltoEn;
    }

    public function obtenerResolucionNotas(): ?string
    {
        return $this->resolucionNotas;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'documento_emitido_id' => $this->documentoEmitidoId,
            'tipo_incidencia' => $this->tipoIncidencia,
            'descripcion' => $this->descripcion,
            'detectado_por_actor_id' => $this->detectadoPorActorId,
            'detectado_en' => $this->detectadoEn,
            'resuelto' => $this->resuelto ? 1 : 0,
            'resuelto_en' => $this->resueltoEn,
            'resolucion_notas' => $this->resolucionNotas,
        ];
    }
}
