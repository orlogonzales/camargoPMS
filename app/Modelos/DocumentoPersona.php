<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un documento de identidad perteneciente a una persona natural.
 */
final class DocumentoPersona
{
    private ?int $id;
    private int $personaId;
    private int $tipoDocumentoId;
    private string $numeroDocumento;
    private ?int $paisEmisorId;
    private bool $esPrincipal;
    private ?string $fechaEmision;
    private ?string $fechaVencimiento;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relaciones enriquecidas opcionales
    private ?TipoDocumento $tipoDocumento;
    private ?Pais $paisEmisor;

    public function __construct(
        ?int $id,
        int $personaId,
        int $tipoDocumentoId,
        string $numeroDocumento,
        ?int $paisEmisorId = null,
        bool $esPrincipal = false,
        ?string $fechaEmision = null,
        ?string $fechaVencimiento = null,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?TipoDocumento $tipoDocumento = null,
        ?Pais $paisEmisor = null
    ) {
        $this->id = $id;
        $this->personaId = $personaId;
        $this->tipoDocumentoId = $tipoDocumentoId;
        $this->numeroDocumento = strtoupper(trim($numeroDocumento));
        $this->paisEmisorId = $paisEmisorId;
        $this->esPrincipal = $esPrincipal;
        $this->fechaEmision = $fechaEmision;
        $this->fechaVencimiento = $fechaVencimiento;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->tipoDocumento = $tipoDocumento;
        $this->paisEmisor = $paisEmisor;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['persona_id'] ?? 0),
            (int) ($datos['tipo_documento_id'] ?? 0),
            (string) ($datos['numero_documento'] ?? ''),
            isset($datos['pais_emisor_id']) && $datos['pais_emisor_id'] !== null ? (int) $datos['pais_emisor_id'] : null,
            (bool) ($datos['es_principal'] ?? false),
            isset($datos['fecha_emision']) && $datos['fecha_emision'] !== null ? (string) $datos['fecha_emision'] : null,
            isset($datos['fecha_vencimiento']) && $datos['fecha_vencimiento'] !== null ? (string) $datos['fecha_vencimiento'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerTipoDocumentoId(): int
    {
        return $this->tipoDocumentoId;
    }

    public function obtenerNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function obtenerPaisEmisorId(): ?int
    {
        return $this->paisEmisorId;
    }

    public function obtenerPaisEmisorEfectivo(): int
    {
        return $this->paisEmisorId ?? 0;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function obtenerFechaEmision(): ?string
    {
        return $this->fechaEmision;
    }

    public function obtenerFechaVencimiento(): ?string
    {
        return $this->fechaVencimiento;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerTipoDocumento(): ?TipoDocumento
    {
        return $this->tipoDocumento;
    }

    public function asignarTipoDocumento(?TipoDocumento $tipoDocumento): void
    {
        $this->tipoDocumento = $tipoDocumento;
    }

    public function obtenerPaisEmisor(): ?Pais
    {
        return $this->paisEmisor;
    }

    public function asignarPaisEmisor(?Pais $pais): void
    {
        $this->paisEmisor = $pais;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'tipo_documento_id' => $this->tipoDocumentoId,
            'numero_documento' => $this->numeroDocumento,
            'pais_emisor_id' => $this->paisEmisorId,
            'es_principal' => $this->esPrincipal,
            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
