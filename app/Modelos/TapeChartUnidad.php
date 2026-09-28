<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de datos en memoria para una fila de unidad física en el Tape Chart (TAPE-CHART-1 / D-084).
 */
final class TapeChartUnidad
{
    /**
     * @param int $unidadId ID primario de la unidad física
     * @param string $codigo Código alfanumérico visible de la unidad (ej. "101", "204")
     * @param string $nombre Denominación o nombre descriptivo
     * @param int $propiedadId ID de la propiedad contenedora
     * @param string $propiedadNombre Nombre del predio contenedor
     * @param int $tipoUnidadId ID de la tipología
     * @param string $tipoUnidadNombre Nombre de la tipología (ej. "HABITACION", "DEPARTAMENTO")
     * @param string|null $pisoNivel Piso o ala (ej. "1", "2", "PB")
     * @param int $capacidadPersonas Capacidad máxima de ocupantes
     * @param string $limpiezaHoyCodigo Código de estado de limpieza hoy ('VR', 'VD', 'VCL', 'OOO')
     * @param string $limpiezaHoyTexto Etiqueta legible de limpieza hoy
     * @param string $limpiezaHoyClase Clases CSS de badge Alina para el estado de hoy
     * @param int|null $tareaActivaId ID de la tarea de housekeeping en curso si existe
     * @param array<string, TapeChartCelda> $celdas Mapa asociativo [fecha_ymd => TapeChartCelda]
     */
    public function __construct(
        private int $unidadId,
        private string $codigo,
        private string $nombre,
        private int $propiedadId,
        private string $propiedadNombre,
        private int $tipoUnidadId,
        private string $tipoUnidadNombre,
        private ?string $pisoNivel = null,
        private int $capacidadPersonas = 2,
        private string $limpiezaHoyCodigo = 'VR',
        private string $limpiezaHoyTexto = 'Limpia / Lista',
        private string $limpiezaHoyClase = 'bg-light-success text-success',
        private ?int $tareaActivaId = null,
        private array $celdas = []
    ) {
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerPropiedadNombre(): string
    {
        return $this->propiedadNombre;
    }

    public function obtenerTipoUnidadId(): int
    {
        return $this->tipoUnidadId;
    }

    public function obtenerTipoUnidadNombre(): string
    {
        return $this->tipoUnidadNombre;
    }

    public function obtenerPisoNivel(): ?string
    {
        return $this->pisoNivel;
    }

    public function obtenerCapacidadPersonas(): int
    {
        return $this->capacidadPersonas;
    }

    public function obtenerLimpiezaHoyCodigo(): string
    {
        return $this->limpiezaHoyCodigo;
    }

    public function obtenerLimpiezaHoyTexto(): string
    {
        return $this->limpiezaHoyTexto;
    }

    public function obtenerLimpiezaHoyClase(): string
    {
        return $this->limpiezaHoyClase;
    }

    public function obtenerTareaActivaId(): ?int
    {
        return $this->tareaActivaId;
    }

    /**
     * @return array<string, TapeChartCelda>
     */
    public function obtenerCeldas(): array
    {
        return $this->celdas;
    }

    public function obtenerCelda(string $fecha): ?TapeChartCelda
    {
        return $this->celdas[$fecha] ?? null;
    }

    public function agregarCelda(TapeChartCelda $celda): void
    {
        $this->celdas[$celda->obtenerFecha()] = $celda;
    }

    /**
     * Serializa la fila de unidad y sus celdas a un arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        $celdasArreglo = [];
        foreach ($this->celdas as $fecha => $celda) {
            $celdasArreglo[$fecha] = $celda->aArreglo();
        }

        return [
            'unidad_id' => $this->unidadId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'tipo_unidad_id' => $this->tipoUnidadId,
            'tipo_unidad_nombre' => $this->tipoUnidadNombre,
            'piso_nivel' => $this->pisoNivel,
            'capacidad_personas' => $this->capacidadPersonas,
            'limpieza_hoy' => [
                'codigo' => $this->limpiezaHoyCodigo,
                'texto' => $this->limpiezaHoyTexto,
                'clase' => $this->limpiezaHoyClase,
                'tarea_activa_id' => $this->tareaActivaId,
            ],
            'celdas' => $celdasArreglo,
        ];
    }
}
