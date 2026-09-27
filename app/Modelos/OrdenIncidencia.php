<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad asociativa que vincula una orden de trabajo con una incidencia técnica atendida.
 */
class OrdenIncidencia
{
    private int $ordenId;
    private int $incidenciaId;
    private ?string $creadoEn;

    public function __construct(int $ordenId, int $incidenciaId, ?string $creadoEn = null)
    {
        $this->ordenId = $ordenId;
        $this->incidenciaId = $incidenciaId;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerOrdenId(): int { return $this->ordenId; }
    public function obtenerIncidenciaId(): int { return $this->incidenciaId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }

    public function aArreglo(): array
    {
        return [
            'orden_id' => $this->ordenId,
            'incidencia_id' => $this->incidenciaId,
            'creado_en' => $this->creadoEn,
        ];
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            (int) ($datos['orden_id'] ?? 0),
            (int) ($datos['incidencia_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }
}
