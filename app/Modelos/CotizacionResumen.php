<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Snapshot reproducible de cotización soberana de alojamiento.
 *
 * Principio Vinculante D-069:
 * Representa la autoridad de cálculo monetario del backend.
 * Una cotización NO bloquea inventario físico en `inventario_diario_unidades`.
 */
class CotizacionResumen
{
    /**
     * @param array<int, DesgloseNocheCotizacion> $desgloseNoches
     */
    public function __construct(
        private string $idCotizacion,
        private int $propiedadId,
        private string $propiedadNombre,
        private ?int $tipoUnidadId,
        private ?string $tipoUnidadNombre,
        private ?int $unidadId,
        private ?string $unidadCodigo,
        private string $fechaEntrada,
        private string $fechaSalida,
        private int $noches,
        private int $huespedes,
        private array $desgloseNoches,
        private string $tarifaPromedioNoche,
        private string $subtotal,
        private string $tasaImpuesto,
        private string $impuesto,
        private string $total,
        private string $monedaCodigo,
        private string $expiraEn,
        private string $tokenCotizacion = '',
        private ?string $creadoEn = null
    ) {
    }

    public function obtenerIdCotizacion(): string
    {
        return $this->idCotizacion;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerPropiedadNombre(): string
    {
        return $this->propiedadNombre;
    }

    public function obtenerTipoUnidadId(): ?int
    {
        return $this->tipoUnidadId;
    }

    public function obtenerTipoUnidadNombre(): ?string
    {
        return $this->tipoUnidadNombre;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function obtenerUnidadCodigo(): ?string
    {
        return $this->unidadCodigo;
    }

    public function obtenerFechaEntrada(): string
    {
        return $this->fechaEntrada;
    }

    public function obtenerFechaSalida(): string
    {
        return $this->fechaSalida;
    }

    public function obtenerNoches(): int
    {
        return $this->noches;
    }

    public function obtenerHuespedes(): int
    {
        return $this->huespedes;
    }

    /**
     * @return array<int, DesgloseNocheCotizacion>
     */
    public function obtenerDesgloseNoches(): array
    {
        return $this->desgloseNoches;
    }

    public function obtenerTarifaPromedioNoche(): string
    {
        return $this->tarifaPromedioNoche;
    }

    public function obtenerSubtotal(): string
    {
        return $this->subtotal;
    }

    public function obtenerTasaImpuesto(): string
    {
        return $this->tasaImpuesto;
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

    public function obtenerExpiraEn(): string
    {
        return $this->expiraEn;
    }

    public function obtenerTokenCotizacion(): string
    {
        return $this->tokenCotizacion;
    }

    public function fijarTokenCotizacion(string $token): void
    {
        $this->tokenCotizacion = $token;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function haExpirado(): bool
    {
        return $this->expiraEn < date('Y-m-d H:i:s');
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id_cotizacion' => $this->idCotizacion,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'tipo_unidad_id' => $this->tipoUnidadId,
            'tipo_unidad_nombre' => $this->tipoUnidadNombre,
            'unidad_id' => $this->unidadId,
            'unidad_codigo' => $this->unidadCodigo,
            'fecha_entrada' => $this->fechaEntrada,
            'fecha_salida' => $this->fechaSalida,
            'noches' => $this->noches,
            'huespedes' => $this->huespedes,
            'desglose_noches' => array_map(static fn(DesgloseNocheCotizacion $d) => $d->aArreglo(), $this->desgloseNoches),
            'tarifa_promedio_noche' => $this->tarifaPromedioNoche,
            'subtotal' => $this->subtotal,
            'tasa_impuesto' => $this->tasaImpuesto,
            'impuesto' => $this->impuesto,
            'total' => $this->total,
            'moneda_codigo' => $this->monedaCodigo,
            'expira_en' => $this->expiraEn,
            'token_cotizacion' => $this->tokenCotizacion,
            'creado_en' => $this->creadoEn,
        ];
    }
}
