<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

/**
 * Entidad que modela el vínculo causal y legal de una nota de crédito, nota de débito o anticipo.
 */
class CpeDocumentoRelacionado
{
    public function __construct(
        private ?int $id,
        private ?int $cpeId,
        private ?int $cpeRelacionadoId,
        private string $tipoDocumentoRelacionado,
        private string $serieRelacionada,
        private int $correlativoRelacionado,
        private ?string $fechaEmisionRelacionada,
        private string $codigoTipoRelacion,
        private string $descripcionMotivo,
        private ?string $montoAjustado = null,
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCpeId(): ?int
    {
        return $this->cpeId;
    }

    public function fijarCpeId(int $cpeId): void
    {
        $this->cpeId = $cpeId;
    }

    public function obtenerCpeRelacionadoId(): ?int
    {
        return $this->cpeRelacionadoId;
    }

    public function obtenerTipoDocumentoRelacionado(): string
    {
        return $this->tipoDocumentoRelacionado;
    }

    public function obtenerSerieRelacionada(): string
    {
        return $this->serieRelacionada;
    }

    public function obtenerCorrelativoRelacionado(): int
    {
        return $this->correlativoRelacionado;
    }

    public function formatearFolioRelacionado(): string
    {
        return sprintf('%s-%08d', $this->serieRelacionada, $this->correlativoRelacionado);
    }

    public function obtenerFechaEmisionRelacionada(): ?string
    {
        return $this->fechaEmisionRelacionada;
    }

    public function obtenerCodigoTipoRelacion(): string
    {
        return $this->codigoTipoRelacion;
    }

    public function obtenerDescripcionMotivo(): string
    {
        return $this->descripcionMotivo;
    }

    public function obtenerMontoAjustado(): ?string
    {
        return $this->montoAjustado;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'cpe_id' => $this->cpeId,
            'cpe_relacionado_id' => $this->cpeRelacionadoId,
            'tipo_documento_relacionado' => $this->tipoDocumentoRelacionado,
            'serie_relacionada' => $this->serieRelacionada,
            'correlativo_relacionado' => $this->correlativoRelacionado,
            'fecha_emision_relacionada' => $this->fechaEmisionRelacionada,
            'codigo_tipo_relacion' => $this->codigoTipoRelacion,
            'descripcion_motivo' => $this->descripcionMotivo,
            'monto_ajustado' => $this->montoAjustado,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            isset($datos['cpe_id']) && $datos['cpe_id'] !== null ? (int) $datos['cpe_id'] : null,
            isset($datos['cpe_relacionado_id']) && $datos['cpe_relacionado_id'] !== null ? (int) $datos['cpe_relacionado_id'] : null,
            (string) ($datos['tipo_documento_relacionado'] ?? '01'),
            (string) ($datos['serie_relacionada'] ?? ''),
            (int) ($datos['correlativo_relacionado'] ?? 0),
            isset($datos['fecha_emision_relacionada']) && $datos['fecha_emision_relacionada'] !== null ? (string) $datos['fecha_emision_relacionada'] : null,
            (string) ($datos['codigo_tipo_relacion'] ?? '01'),
            (string) ($datos['descripcion_motivo'] ?? ''),
            isset($datos['monto_ajustado']) && $datos['monto_ajustado'] !== null ? (string) $datos['monto_ajustado'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
