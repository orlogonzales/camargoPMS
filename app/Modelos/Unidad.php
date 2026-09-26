<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una unidad física habitacional o alojable.
 *
 * Principios vinculantes:
 * - PROPIEDAD ≠ UNIDAD: La propiedad es el inmueble/edificación raíz; la unidad es
 *                       la división física comercializable/alojable.
 * - UNIDAD ≠ RESERVA / TARIFA / DISPONIBILIDAD: Cero campos de disponibilidad,
 *                       fechas de estancia, precios, impuestos, bloqueos o contratos.
 * - UNIDAD ≠ REGISTRO DESECHABLE: Cero eliminación física. Ciclo de vida gobernado
 *                       exclusivamente por la alternancia operativa ACTIVO ↔ INACTIVO.
 */
class Unidad
{
    private ?int $id;
    private int $propiedadId;
    private int $tipoUnidadId;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private ?string $pisoNivel;
    private int $capacidadPersonas;
    private int $dormitorios;
    private float $banos;
    private ?float $areaM2;
    private string $estado;
    private ?string $observaciones;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos de agregación relacional (JOINs de lectura)
    private ?string $propiedadNombre = null;
    private ?string $propiedadCodigo = null;
    private ?string $propiedadEstado = null;
    private ?string $tipoUnidadCodigo = null;
    private ?string $tipoUnidadNombre = null;

    public function __construct(
        ?int $id = null,
        int $propiedadId = 0,
        int $tipoUnidadId = 0,
        string $codigo = '',
        string $nombre = '',
        ?string $descripcion = null,
        ?string $pisoNivel = null,
        int $capacidadPersonas = 1,
        int $dormitorios = 1,
        float $banos = 1.0,
        ?float $areaM2 = null,
        string $estado = 'ACTIVO',
        ?string $observaciones = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->propiedadId = $propiedadId;
        $this->tipoUnidadId = $tipoUnidadId;
        $this->codigo = trim($codigo);
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->pisoNivel = $pisoNivel !== null && trim($pisoNivel) !== '' ? trim($pisoNivel) : null;
        $this->capacidadPersonas = max(1, $capacidadPersonas);
        $this->dormitorios = max(0, $dormitorios);
        $this->banos = max(0.0, $banos);
        $this->areaM2 = $areaM2 !== null && $areaM2 > 0 ? round($areaM2, 2) : null;
        $this->estado = in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO'], true)
            ? strtoupper(trim($estado))
            : 'ACTIVO';
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function fijarPropiedadId(int $propiedadId): self
    {
        $this->propiedadId = $propiedadId;
        return $this;
    }

    public function obtenerTipoUnidadId(): int
    {
        return $this->tipoUnidadId;
    }

    public function fijarTipoUnidadId(int $tipoUnidadId): self
    {
        $this->tipoUnidadId = $tipoUnidadId;
        return $this;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function fijarCodigo(string $codigo): self
    {
        $this->codigo = trim($codigo);
        return $this;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function fijarNombre(string $nombre): self
    {
        $this->nombre = trim($nombre);
        return $this;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function fijarDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        return $this;
    }

    public function obtenerPisoNivel(): ?string
    {
        return $this->pisoNivel;
    }

    public function fijarPisoNivel(?string $pisoNivel): self
    {
        $this->pisoNivel = $pisoNivel !== null && trim($pisoNivel) !== '' ? trim($pisoNivel) : null;
        return $this;
    }

    public function obtenerCapacidadPersonas(): int
    {
        return $this->capacidadPersonas;
    }

    public function fijarCapacidadPersonas(int $capacidad): self
    {
        $this->capacidadPersonas = max(1, $capacidad);
        return $this;
    }

    public function obtenerDormitorios(): int
    {
        return $this->dormitorios;
    }

    public function fijarDormitorios(int $dormitorios): self
    {
        $this->dormitorios = max(0, $dormitorios);
        return $this;
    }

    public function obtenerBanos(): float
    {
        return $this->banos;
    }

    public function fijarBanos(float $banos): self
    {
        $this->banos = max(0.0, $banos);
        return $this;
    }

    public function obtenerAreaM2(): ?float
    {
        return $this->areaM2;
    }

    public function fijarAreaM2(?float $areaM2): self
    {
        $this->areaM2 = $areaM2 !== null && $areaM2 > 0 ? round($areaM2, 2) : null;
        return $this;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function fijarEstado(string $estado): self
    {
        $estadoNormalizado = strtoupper(trim($estado));
        if (in_array($estadoNormalizado, ['ACTIVO', 'INACTIVO'], true)) {
            $this->estado = $estadoNormalizado;
        }
        return $this;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function fijarObservaciones(?string $observaciones): self
    {
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        return $this;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    // Getters y Setters para metadatos relacionales
    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function fijarPropiedadNombre(?string $propiedadNombre): self
    {
        $this->propiedadNombre = $propiedadNombre;
        return $this;
    }

    public function obtenerPropiedadCodigo(): ?string
    {
        return $this->propiedadCodigo;
    }

    public function fijarPropiedadCodigo(?string $propiedadCodigo): self
    {
        $this->propiedadCodigo = $propiedadCodigo;
        return $this;
    }

    public function obtenerPropiedadEstado(): ?string
    {
        return $this->propiedadEstado;
    }

    public function fijarPropiedadEstado(?string $propiedadEstado): self
    {
        $this->propiedadEstado = $propiedadEstado;
        return $this;
    }

    public function obtenerTipoUnidadCodigo(): ?string
    {
        return $this->tipoUnidadCodigo;
    }

    public function fijarTipoUnidadCodigo(?string $tipoUnidadCodigo): self
    {
        $this->tipoUnidadCodigo = $tipoUnidadCodigo;
        return $this;
    }

    public function obtenerTipoUnidadNombre(): ?string
    {
        return $this->tipoUnidadNombre;
    }

    public function fijarTipoUnidadNombre(?string $tipoUnidadNombre): self
    {
        $this->tipoUnidadNombre = $tipoUnidadNombre;
        return $this;
    }

    /**
     * Construye un resumen legible de las especificaciones físicas de la unidad.
     */
    public function obtenerResumenFisico(): string
    {
        $partes = [];
        if ($this->pisoNivel !== null) {
            $partes[] = "Piso/Nivel: {$this->pisoNivel}";
        }
        $partes[] = "Capacidad: {$this->capacidadPersonas} " . ($this->capacidadPersonas === 1 ? 'persona' : 'personas');
        $partes[] = "{$this->dormitorios} " . ($this->dormitorios === 1 ? 'dormitorio' : 'dormitorios');
        $partes[] = "{$this->banos} " . ($this->banos == 1.0 ? 'baño' : 'baños');
        if ($this->areaM2 !== null) {
            $partes[] = "{$this->areaM2} m²";
        }
        return implode(' · ', $partes);
    }

    /**
     * Serializa la entidad a un arreglo asociativo con tipos canónicos.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'propiedad_id' => $this->propiedadId,
            'tipo_unidad_id' => $this->tipoUnidadId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'piso_nivel' => $this->pisoNivel,
            'capacidad_personas' => $this->capacidadPersonas,
            'dormitorios' => $this->dormitorios,
            'banos' => $this->banos,
            'area_m2' => $this->areaM2,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            // Metadatos relacionales opcionales
            'propiedad_nombre' => $this->propiedadNombre,
            'propiedad_codigo' => $this->propiedadCodigo,
            'propiedad_estado' => $this->propiedadEstado,
            'tipo_unidad_codigo' => $this->tipoUnidadCodigo,
            'tipo_unidad_nombre' => $this->tipoUnidadNombre,
            'resumen_fisico' => $this->obtenerResumenFisico(),
        ];
    }

    /**
     * Alias de compatibilidad canónica.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return $this->aArreglo();
    }

    /**
     * Alias de compatibilidad canónica.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return $this->aArreglo();
    }

    /**
     * Reconstruye una entidad desde un arreglo asociativo.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        $unidad = new self(
            id: isset($datos['id']) ? (int)$datos['id'] : null,
            propiedadId: (int)($datos['propiedad_id'] ?? 0),
            tipoUnidadId: (int)($datos['tipo_unidad_id'] ?? 0),
            codigo: (string)($datos['codigo'] ?? ''),
            nombre: (string)($datos['nombre'] ?? ''),
            descripcion: isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string)$datos['descripcion'] : null,
            pisoNivel: isset($datos['piso_nivel']) && $datos['piso_nivel'] !== null ? (string)$datos['piso_nivel'] : null,
            capacidadPersonas: isset($datos['capacidad_personas']) ? (int)$datos['capacidad_personas'] : 1,
            dormitorios: isset($datos['dormitorios']) ? (int)$datos['dormitorios'] : 1,
            banos: isset($datos['banos']) ? (float)$datos['banos'] : 1.0,
            areaM2: isset($datos['area_m2']) && $datos['area_m2'] !== null ? (float)$datos['area_m2'] : null,
            estado: (string)($datos['estado'] ?? 'ACTIVO'),
            observaciones: isset($datos['observaciones']) && $datos['observaciones'] !== null ? (string)$datos['observaciones'] : null,
            creadoEn: isset($datos['creado_en']) ? (string)$datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string)$datos['actualizado_en'] : null
        );

        if (isset($datos['propiedad_nombre'])) {
            $unidad->fijarPropiedadNombre((string)$datos['propiedad_nombre']);
        }
        if (isset($datos['propiedad_codigo'])) {
            $unidad->fijarPropiedadCodigo((string)$datos['propiedad_codigo']);
        }
        if (isset($datos['propiedad_estado'])) {
            $unidad->fijarPropiedadEstado((string)$datos['propiedad_estado']);
        }
        if (isset($datos['tipo_unidad_codigo'])) {
            $unidad->fijarTipoUnidadCodigo((string)$datos['tipo_unidad_codigo']);
        }
        if (isset($datos['tipo_unidad_nombre'])) {
            $unidad->fijarTipoUnidadNombre((string)$datos['tipo_unidad_nombre']);
        }

        return $unidad;
    }
}
