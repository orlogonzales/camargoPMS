<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio para los Documentos Emitidos con Snapshot e Integridad Criptográfica.
 */
class DocumentoEmitido
{
    public const ESTADO_VALIDO = 'VALIDO';
    public const ESTADO_ANULADO = 'ANULADO';

    private ?int $id;
    private string $codigoFolio;
    private int $plantillaId;
    private int $plantillaVersionId;
    private string $origenTipo;
    private int $origenId;
    private array $snapshotDatosJson;
    private string $snapshotHtml;
    private string $rutaArchivoPdf;
    private int $tamanoBytes;
    private string $hashPdfSha256;
    private string $hashSnapshotSha256;
    private int $numeroPaginas;
    private int $emitidoPorActorId;
    private ?string $emitidoEn;
    private string $estado;
    private ?string $motivoAnulacion;
    private ?string $anuladoEn;
    private ?int $anuladoPorActorId;

    // Metadatos adicionales para lectura
    private ?string $plantillaCodigo;
    private ?string $plantillaNombre;
    private ?int $plantillaNumeroVersion;
    private ?string $emisorNombre;

    public function __construct(
        ?int $id,
        string $codigoFolio,
        int $plantillaId,
        int $plantillaVersionId,
        string $origenTipo,
        int $origenId,
        array $snapshotDatosJson,
        string $snapshotHtml,
        string $rutaArchivoPdf,
        int $tamanoBytes,
        string $hashPdfSha256,
        string $hashSnapshotSha256,
        int $numeroPaginas = 1,
        int $emitidoPorActorId = 1,
        ?string $emitidoEn = null,
        string $estado = self::ESTADO_VALIDO,
        ?string $motivoAnulacion = null,
        ?string $anuladoEn = null,
        ?int $anuladoPorActorId = null,
        ?string $plantillaCodigo = null,
        ?string $plantillaNombre = null,
        ?int $plantillaNumeroVersion = null,
        ?string $emisorNombre = null
    ) {
        $this->id = $id;
        $this->codigoFolio = trim($codigoFolio);
        $this->plantillaId = $plantillaId;
        $this->plantillaVersionId = $plantillaVersionId;
        $this->origenTipo = strtoupper(trim($origenTipo));
        $this->origenId = $origenId;
        $this->snapshotDatosJson = $snapshotDatosJson;
        $this->snapshotHtml = $snapshotHtml;
        $this->rutaArchivoPdf = trim($rutaArchivoPdf);
        $this->tamanoBytes = $tamanoBytes;
        $this->hashPdfSha256 = trim($hashPdfSha256);
        $this->hashSnapshotSha256 = trim($hashSnapshotSha256);
        $this->numeroPaginas = $numeroPaginas;
        $this->emitidoPorActorId = $emitidoPorActorId;
        $this->emitidoEn = $emitidoEn;
        $this->estado = strtoupper(trim($estado));
        $this->motivoAnulacion = $motivoAnulacion ? trim($motivoAnulacion) : null;
        $this->anuladoEn = $anuladoEn;
        $this->anuladoPorActorId = $anuladoPorActorId;
        $this->plantillaCodigo = $plantillaCodigo;
        $this->plantillaNombre = $plantillaNombre;
        $this->plantillaNumeroVersion = $plantillaNumeroVersion;
        $this->emisorNombre = $emisorNombre;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigoFolio(): string
    {
        return $this->codigoFolio;
    }

    public function obtenerPlantillaId(): int
    {
        return $this->plantillaId;
    }

    public function obtenerPlantillaVersionId(): int
    {
        return $this->plantillaVersionId;
    }

    public function obtenerOrigenTipo(): string
    {
        return $this->origenTipo;
    }

    public function obtenerOrigenId(): int
    {
        return $this->origenId;
    }

    public function obtenerSnapshotDatosJson(): array
    {
        return $this->snapshotDatosJson;
    }

    public function obtenerSnapshotHtml(): string
    {
        return $this->snapshotHtml;
    }

    public function obtenerRutaArchivoPdf(): string
    {
        return $this->rutaArchivoPdf;
    }

    public function obtenerTamanoBytes(): int
    {
        return $this->tamanoBytes;
    }

    public function obtenerHashPdfSha256(): string
    {
        return $this->hashPdfSha256;
    }

    public function obtenerHashSnapshotSha256(): string
    {
        return $this->hashSnapshotSha256;
    }

    public function obtenerNumeroPaginas(): int
    {
        return $this->numeroPaginas;
    }

    public function obtenerEmitidoPorActorId(): int
    {
        return $this->emitidoPorActorId;
    }

    public function obtenerEmitidoEn(): ?string
    {
        return $this->emitidoEn;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esValido(): bool
    {
        return $this->estado === self::ESTADO_VALIDO;
    }

    public function esAnulado(): bool
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

    public function obtenerPlantillaCodigo(): ?string
    {
        return $this->plantillaCodigo;
    }

    public function obtenerPlantillaNombre(): ?string
    {
        return $this->plantillaNombre;
    }

    public function obtenerPlantillaNumeroVersion(): ?int
    {
        return $this->plantillaNumeroVersion;
    }

    public function obtenerEmisorNombre(): ?string
    {
        return $this->emisorNombre;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo_folio' => $this->codigoFolio,
            'plantilla_id' => $this->plantillaId,
            'plantilla_codigo' => $this->plantillaCodigo,
            'plantilla_nombre' => $this->plantillaNombre,
            'plantilla_version_id' => $this->plantillaVersionId,
            'plantilla_numero_version' => $this->plantillaNumeroVersion,
            'origen_tipo' => $this->origenTipo,
            'origen_id' => $this->origenId,
            'snapshot_datos_json' => $this->snapshotDatosJson,
            'ruta_archivo_pdf' => $this->rutaArchivoPdf,
            'tamano_bytes' => $this->tamanoBytes,
            'hash_pdf_sha256' => $this->hashPdfSha256,
            'hash_snapshot_sha256' => $this->hashSnapshotSha256,
            'numero_paginas' => $this->numeroPaginas,
            'emitido_por_actor_id' => $this->emitidoPorActorId,
            'emisor_nombre' => $this->emisorNombre,
            'emitido_en' => $this->emitidoEn,
            'estado' => $this->estado,
            'motivo_anulacion' => $this->motivoAnulacion,
            'anulado_en' => $this->anuladoEn,
        ];
    }
}
