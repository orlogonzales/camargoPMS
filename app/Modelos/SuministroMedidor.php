<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un medidor físico de suministros (SUMINISTROS-1 / D-081).
 *
 * Características:
 * - Identidad física propia: serie/placa, fechas de instalación y retiro.
 * - Una unidad física no puede tener dos medidores activos para el mismo suministro.
 * - Soporta rollover metrológico solo si permiteRollover = true y capacidadMaxima > 0.
 */
class SuministroMedidor
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_EN_MANTENIMIENTO = 'EN_MANTENIMIENTO';
    public const ESTADO_REEMPLAZADO = 'REEMPLAZADO';
    public const ESTADO_DE_BAJA = 'DE_BAJA';
    public const ESTADO_RETIRADO = self::ESTADO_REEMPLAZADO;
    public const ESTADO_INACTIVO = self::ESTADO_DE_BAJA;

    public function __construct(
        private ?int $id,
        private int $suministroId,
        private int $propiedadId,
        private ?int $unidadId,
        private string $codigo,
        private ?string $marca,
        private ?string $modelo,
        private string $fechaInstalacion,
        private ?string $fechaRetiro,
        private string $lecturaInicial = '0.0000',
        private ?string $capacidadMaxima = null,
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $observaciones = null,
        private ?int $creadoPorActorId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        // Proyecciones
        private ?string $suministroNombre = null,
        private ?string $propiedadNombre = null,
        private ?string $unidadCodigo = null,
        private ?string $unidadNombre = null,
        private ?string $ultimaLecturaValor = null,
        private ?string $ultimaLecturaFecha = null,
        private ?string $lecturaFinal = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $estado = (string) ($datos['estado'] ?? self::ESTADO_ACTIVO);
        if ($estado === 'RETIRADO') {
            $estado = self::ESTADO_REEMPLAZADO;
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['suministro_id'] ?? 0),
            (int) ($datos['propiedad_id'] ?? 0),
            isset($datos['unidad_id']) && $datos['unidad_id'] !== '' ? (int) $datos['unidad_id'] : null,
            (string) ($datos['codigo'] ?? $datos['numero_serie'] ?? $datos['serie'] ?? ''),
            isset($datos['marca']) && $datos['marca'] !== '' ? (string) $datos['marca'] : null,
            isset($datos['modelo']) && $datos['modelo'] !== '' ? (string) $datos['modelo'] : null,
            (string) ($datos['fecha_instalacion'] ?? ''),
            isset($datos['fecha_retiro']) && $datos['fecha_retiro'] !== '' ? (string) $datos['fecha_retiro'] : null,
            (string) ($datos['lectura_inicial'] ?? '0.0000'),
            isset($datos['capacidad_maxima']) && $datos['capacidad_maxima'] !== '' ? (string) $datos['capacidad_maxima'] : (isset($datos['lectura_maxima']) && $datos['lectura_maxima'] !== '' ? (string) $datos['lectura_maxima'] : null),
            $estado,
            isset($datos['observaciones']) && $datos['observaciones'] !== '' ? (string) $datos['observaciones'] : (isset($datos['notas']) && $datos['notas'] !== '' ? (string) $datos['notas'] : null),
            isset($datos['creado_por_actor_id']) ? (int) $datos['creado_por_actor_id'] : (isset($datos['actor_id']) ? (int) $datos['actor_id'] : null),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            isset($datos['suministro_nombre']) ? (string) $datos['suministro_nombre'] : null,
            isset($datos['propiedad_nombre']) ? (string) $datos['propiedad_nombre'] : null,
            isset($datos['unidad_codigo']) ? (string) $datos['unidad_codigo'] : (isset($datos['unidad_numero']) ? (string) $datos['unidad_numero'] : null),
            isset($datos['unidad_nombre']) ? (string) $datos['unidad_nombre'] : null,
            isset($datos['ultima_lectura_valor']) ? (string) $datos['ultima_lectura_valor'] : null,
            isset($datos['ultima_lectura_fecha']) ? (string) $datos['ultima_lectura_fecha'] : null,
            isset($datos['lectura_final']) ? (string) $datos['lectura_final'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'suministro_id' => $this->suministroId,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'codigo' => $this->codigo,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'fecha_instalacion' => $this->fechaInstalacion,
            'fecha_retiro' => $this->fechaRetiro,
            'lectura_inicial' => $this->lecturaInicial,
            'capacidad_maxima' => $this->capacidadMaxima,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'suministro_nombre' => $this->suministroNombre,
            'propiedad_nombre' => $this->propiedadNombre,
            'unidad_codigo' => $this->unidadCodigo,
            'unidad_nombre' => $this->unidadNombre,
            'ultima_lectura_valor' => $this->ultimaLecturaValor,
            'ultima_lectura_fecha' => $this->ultimaLecturaFecha,
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerSuministroId(): int
    {
        return $this->suministroId;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerMarca(): ?string
    {
        return $this->marca;
    }

    public function obtenerModelo(): ?string
    {
        return $this->modelo;
    }

    public function obtenerFechaInstalacion(): string
    {
        return $this->fechaInstalacion;
    }

    public function obtenerFechaRetiro(): ?string
    {
        return $this->fechaRetiro;
    }

    public function obtenerLecturaInicial(): string
    {
        return $this->lecturaInicial;
    }

    public function obtenerCapacidadMaxima(): ?string
    {
        return $this->capacidadMaxima;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaReemplazado(): bool
    {
        return $this->estado === self::ESTADO_REEMPLAZADO;
    }

    public function estaDeBaja(): bool
    {
        return $this->estado === self::ESTADO_DE_BAJA;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerSuministroNombre(): ?string
    {
        return $this->suministroNombre;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function obtenerUnidadCodigo(): ?string
    {
        return $this->unidadCodigo;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerUltimaLecturaValor(): ?string
    {
        return $this->ultimaLecturaValor;
    }

    public function obtenerUltimaLecturaFecha(): ?string
    {
        return $this->ultimaLecturaFecha;
    }

    public function obtenerNumeroSerie(): string
    {
        return $this->codigo;
    }

    public function obtenerCodigoInterno(): ?string
    {
        return $this->codigo;
    }

    public function obtenerLecturaMaxima(): ?string
    {
        return $this->capacidadMaxima;
    }

    public function permiteRollover(): bool
    {
        return $this->capacidadMaxima !== null && bccomp($this->capacidadMaxima, '0.0000', 4) > 0;
    }

    public function esActivo(): bool
    {
        return $this->estaActivo();
    }

    public function obtenerSerie(): string
    {
        return $this->codigo;
    }

    public function obtenerLecturaFinal(): ?string
    {
        return $this->lecturaFinal ?? $this->ultimaLecturaValor;
    }

    public function obtenerNotas(): ?string
    {
        return $this->observaciones;
    }
}
