<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa la asignación de una unidad física alojable
 * a una reserva comercial directa y su snapshot económico inmutable (RESERVAS-1 / D-069).
 *
 * Principios vinculantes:
 * - 1 RESERVA : N UNIDADES.
 * - Snapshot económico inmutable: precio, subtotal, impuesto, total y moneda capturados al reservar.
 * - DECIMAL(15,2) representado como strings normalizados con 2 decimales para precisión exacta BCMath.
 */
class ReservaUnidad
{
    private ?int $id;
    private int $reservaId;
    private int $unidadId;
    private string $precioUnitarioNoche;
    private int $noches;
    private string $subtotal;
    private string $impuesto;
    private string $total;
    private string $monedaCodigo;
    private ?string $creadoEn;

    // Metadatos auxiliares de presentación y lectura
    private ?string $unidadCodigo = null;
    private ?string $unidadNombre = null;
    private ?int $propiedadId = null;
    private ?string $propiedadNombre = null;
    private ?string $tipoUnidadNombre = null;

    public function __construct(
        ?int $id,
        int $reservaId,
        int $unidadId,
        string $precioUnitarioNoche,
        int $noches,
        string $subtotal,
        string $impuesto = '0.00',
        string $total = '0.00',
        string $monedaCodigo = 'PEN',
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->reservaId = $reservaId;
        $this->unidadId = $unidadId;
        $this->precioUnitarioNoche = number_format((float) $precioUnitarioNoche, 2, '.', '');
        $this->noches = $noches;
        $this->subtotal = number_format((float) $subtotal, 2, '.', '');
        $this->impuesto = number_format((float) $impuesto, 2, '.', '');
        $this->total = number_format((float) $total, 2, '.', '');
        $this->monedaCodigo = trim(strtoupper($monedaCodigo));
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerReservaId(): int
    {
        return $this->reservaId;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerPrecioUnitarioNoche(): string
    {
        return $this->precioUnitarioNoche;
    }

    public function obtenerNoches(): int
    {
        return $this->noches;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerImpuesto(): string
    {
        return $this->impuesto;
    }

    public function obtenerTotal(): string
    {
        return $this->total;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerUnidadCodigo(): ?string
    {
        return $this->unidadCodigo;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerPropiedadId(): ?int
    {
        return $this->propiedadId;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function obtenerTipoUnidadNombre(): ?string
    {
        return $this->tipoUnidadNombre;
    }

    public function asignarMetadatosUnidad(
        ?string $unidadCodigo,
        ?string $unidadNombre,
        ?int $propiedadId = null,
        ?string $propiedadNombre = null,
        ?string $tipoUnidadNombre = null
    ): void {
        $this->unidadCodigo = $unidadCodigo;
        $this->unidadNombre = $unidadNombre;
        $this->propiedadId = $propiedadId;
        $this->propiedadNombre = $propiedadNombre;
        $this->tipoUnidadNombre = $tipoUnidadNombre;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $reservaUnidad = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['reserva_id'] ?? 0),
            (int) ($datos['unidad_id'] ?? 0),
            (string) ($datos['precio_unitario_noche'] ?? '0.00'),
            (int) ($datos['noches'] ?? 0),
            (string) ($datos['subtotal'] ?? '0.00'),
            (string) ($datos['impuesto'] ?? '0.00'),
            (string) ($datos['total'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );

        if (isset($datos['unidad_codigo']) || isset($datos['unidad_nombre'])) {
            $reservaUnidad->asignarMetadatosUnidad(
                isset($datos['unidad_codigo']) ? (string) $datos['unidad_codigo'] : null,
                isset($datos['unidad_nombre']) ? (string) $datos['unidad_nombre'] : null,
                isset($datos['propiedad_id']) ? (int) $datos['propiedad_id'] : null,
                isset($datos['propiedad_nombre']) ? (string) $datos['propiedad_nombre'] : null,
                isset($datos['tipo_unidad_nombre']) ? (string) $datos['tipo_unidad_nombre'] : null
            );
        }

        return $reservaUnidad;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'reserva_id' => $this->reservaId,
            'unidad_id' => $this->unidadId,
            'unidad_codigo' => $this->unidadCodigo,
            'unidad_nombre' => $this->unidadNombre,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'tipo_unidad_nombre' => $this->tipoUnidadNombre,
            'precio_unitario_noche' => $this->precioUnitarioNoche,
            'noches' => $this->noches,
            'subtotal' => $this->subtotal,
            'impuesto' => $this->impuesto,
            'total' => $this->total,
            'moneda_codigo' => $this->monedaCodigo,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * Alias de haciaArreglo().
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
