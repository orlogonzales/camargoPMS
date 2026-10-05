<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

/**
 * Entidad de dominio que administra una serie fiscal alfanumérica y su correlatividad transaccional.
 */
class CpeSerie
{
    public function __construct(
        private ?int $id,
        private int $emisorEstablecimientoId,
        private string $tipoComprobante,
        private string $serie,
        private int $ultimoCorrelativo = 0,
        private string $prefijoTipo = 'F',
        private ?string $descripcion = null,
        private string $estado = 'ACTIVO',
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerEmisorEstablecimientoId(): int
    {
        return $this->emisorEstablecimientoId;
    }

    public function obtenerTipoComprobante(): string
    {
        return $this->tipoComprobante;
    }

    public function obtenerSerie(): string
    {
        return $this->serie;
    }

    public function obtenerUltimoCorrelativo(): int
    {
        return $this->ultimoCorrelativo;
    }

    public function obtenerSiguienteCorrelativo(): int
    {
        return $this->ultimoCorrelativo + 1;
    }

    public function formatearFolio(int $correlativo): string
    {
        return sprintf('%s-%08d', $this->serie, $correlativo);
    }

    public function obtenerPrefijoTipo(): string
    {
        return $this->prefijoTipo;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function esFactura(): bool
    {
        return $this->tipoComprobante === 'FACTURA';
    }

    public function esBoleta(): bool
    {
        return $this->tipoComprobante === 'BOLETA';
    }

    public function esNotaCredito(): bool
    {
        return $this->tipoComprobante === 'NOTA_CREDITO';
    }

    public function esNotaDebito(): bool
    {
        return $this->tipoComprobante === 'NOTA_DEBITO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'emisor_establecimiento_id' => $this->emisorEstablecimientoId,
            'tipo_comprobante' => $this->tipoComprobante,
            'serie' => $this->serie,
            'ultimo_correlativo' => $this->ultimoCorrelativo,
            'prefijo_tipo' => $this->prefijoTipo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['emisor_establecimiento_id'] ?? 0),
            (string) ($datos['tipo_comprobante'] ?? 'FACTURA'),
            (string) ($datos['serie'] ?? ''),
            (int) ($datos['ultimo_correlativo'] ?? 0),
            (string) ($datos['prefijo_tipo'] ?? 'F'),
            isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string) $datos['descripcion'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
