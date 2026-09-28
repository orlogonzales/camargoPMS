<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO que encapsula los datos analíticos del Reporte Diario Gerencial (Manager's Daily Report).
 * Gobernanza: D-087 / REPORTES-1.
 * Solo Lectura / Proyección Analítica sin persistencia.
 */
class ReporteDiarioDTO
{
    /**
     * @param array<string, mixed> $operacionHotelera
     * @param array<string, mixed> $actividadComercial
     * @param array<string, mixed> $tesoreria
     * @param array<string, mixed> $controlOperativo
     * @param array<int, array<string, mixed>> $detalleUnidades
     */
    public function __construct(
        private string $fechaCorte,
        private ?int $propiedadId,
        private ?string $propiedadNombre,
        private array $operacionHotelera,
        private array $actividadComercial,
        private array $tesoreria,
        private array $controlOperativo,
        private array $detalleUnidades = []
    ) {
    }

    public function obtenerFechaCorte(): string { return $this->fechaCorte; }
    public function obtenerPropiedadId(): ?int { return $this->propiedadId; }
    public function obtenerPropiedadNombre(): ?string { return $this->propiedadNombre; }
    public function obtenerOperacionHotelera(): array { return $this->operacionHotelera; }
    public function obtenerActividadComercial(): array { return $this->actividadComercial; }
    public function obtenerTesoreria(): array { return $this->tesoreria; }
    public function obtenerControlOperativo(): array { return $this->controlOperativo; }
    public function obtenerDetalleUnidades(): array { return $this->detalleUnidades; }

    public function aArreglo(): array
    {
        return [
            'fecha_corte' => $this->fechaCorte,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'operacion_hotelera' => $this->operacionHotelera,
            'actividad_comercial' => $this->actividadComercial,
            'tesoreria' => $this->tesoreria,
            'control_operativo' => $this->controlOperativo,
            'detalle_unidades' => $this->detalleUnidades,
        ];
    }
}
