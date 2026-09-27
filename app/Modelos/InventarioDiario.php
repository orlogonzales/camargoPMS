<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa una noche ocupada o bloqueada en el
 * inventario diario sparse (DISPONIBILIDAD-1 / D-067).
 *
 * La presencia de una fila en esta entidad significa que la noche (fecha)
 * no está disponible para la unidad correspondiente.
 */
class InventarioDiario
{
    private ?int $id;
    private int $unidadId;
    private string $fecha;
    private string $tipoBloqueo;
    private string $origenTipo;
    private int $origenId;
    private ?string $creadoEn;

    public function __construct(
        ?int $id,
        int $unidadId,
        string $fecha,
        string $tipoBloqueo = 'BLOQUEO_MANUAL',
        string $origenTipo = 'BLOQUEO_MANUAL',
        int $origenId = 0,
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->unidadId = $unidadId;
        $this->fecha = trim($fecha);
        $this->tipoBloqueo = trim($tipoBloqueo);
        $this->origenTipo = trim($origenTipo);
        $this->origenId = $origenId;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerFecha(): string
    {
        return $this->fecha;
    }

    public function obtenerTipoBloqueo(): string
    {
        return $this->tipoBloqueo;
    }

    public function obtenerOrigenTipo(): string
    {
        return $this->origenTipo;
    }

    public function obtenerOrigenId(): int
    {
        return $this->origenId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * Hidrata una entidad InventarioDiario desde base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function hidratar(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['unidad_id'] ?? 0),
            (string) ($datos['fecha'] ?? ''),
            (string) ($datos['tipo_bloqueo'] ?? 'BLOQUEO_MANUAL'),
            (string) ($datos['origen_tipo'] ?? 'BLOQUEO_MANUAL'),
            (int) ($datos['origen_id'] ?? 0),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );
    }

    /**
     * Serializa a arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'unidad_id' => $this->unidadId,
            'fecha' => $this->fecha,
            'tipo_bloqueo' => $this->tipoBloqueo,
            'origen_tipo' => $this->origenTipo,
            'origen_id' => $this->origenId,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
