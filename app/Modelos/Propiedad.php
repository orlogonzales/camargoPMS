<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio puro que representa una Propiedad o Inmueble Físico (PROPIEDADES-1).
 *
 * Principios vinculantes:
 * - PROPIEDAD != UNIDAD: La propiedad es el contenedor físico general (edificio/casa/complejo).
 * - PROPIEDAD != REGISTRO DESECHABLE: Su ciclo de vida es exclusivamente ACTIVO <-> INACTIVO.
 * - P-004 / P-005 / P-006: Sin fechas de reserva, precios, tarifas, monedas ni disponibilidad.
 */
class Propiedad
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private int $paisId;
    private ?string $departamento;
    private ?string $provincia;
    private ?string $distrito;
    private ?string $direccion;
    private ?string $zonaHoraria;
    private ?string $referencia;
    private ?float $latitud;
    private ?float $longitud;
    private string $estado;
    private ?string $observaciones;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos auxiliares de relaciones
    private ?string $paisNombre = null;

    /**
     * @param int|null $id
     * @param string $codigo Identificador técnico estable único (ej. 'AYUDA-MUTUA', 'PROP-001')
     * @param string $nombre Denominación administrativa visible
     * @param string|null $descripcion
     * @param int $paisId Clave foránea a paises.id
     * @param string|null $departamento
     * @param string|null $provincia
     * @param string|null $distrito
     * @param string|null $direccion Dirección física precisa
     * @param string|null $zonaHoraria Identificador IANA de huso horario (ej. 'America/Lima') o null para usar default del PMS
     * @param string|null $referencia
     * @param float|null $latitud Coordenada GPS [-90, 90]
     * @param float|null $longitud Coordenada GPS [-180, 180]
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param string|null $observaciones Notas internas
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        int $paisId = 1,
        ?string $departamento = null,
        ?string $provincia = null,
        ?string $distrito = null,
        ?string $direccion = null,
        ?string $referencia = null,
        ?float $latitud = null,
        ?float $longitud = null,
        string $estado = 'ACTIVO',
        ?string $observaciones = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?string $zonaHoraria = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->paisId = $paisId;
        $this->departamento = $departamento !== null && trim($departamento) !== '' ? trim($departamento) : null;
        $this->provincia = $provincia !== null && trim($provincia) !== '' ? trim($provincia) : null;
        $this->distrito = $distrito !== null && trim($distrito) !== '' ? trim($distrito) : null;
        $this->direccion = $direccion !== null && trim($direccion) !== '' ? trim($direccion) : null;
        $this->referencia = $referencia !== null && trim($referencia) !== '' ? trim($referencia) : null;
        $this->latitud = $latitud !== null ? (float) $latitud : null;
        $this->longitud = $longitud !== null ? (float) $longitud : null;
        $this->estado = strtoupper(trim($estado)) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->zonaHoraria = $zonaHoraria !== null && trim($zonaHoraria) !== '' ? trim($zonaHoraria) : null;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerPaisId(): int
    {
        return $this->paisId;
    }

    public function obtenerDepartamento(): ?string
    {
        return $this->departamento;
    }

    public function obtenerProvincia(): ?string
    {
        return $this->provincia;
    }

    public function obtenerDistrito(): ?string
    {
        return $this->distrito;
    }

    public function obtenerDireccion(): ?string
    {
        return $this->direccion;
    }

    public function obtenerZonaHoraria(): ?string
    {
        return $this->zonaHoraria;
    }

    public function asignarZonaHoraria(?string $zonaHoraria): void
    {
        $this->zonaHoraria = $zonaHoraria !== null && trim($zonaHoraria) !== '' ? trim($zonaHoraria) : null;
    }

    public function obtenerReferencia(): ?string
    {
        return $this->referencia;
    }

    public function obtenerLatitud(): ?float
    {
        return $this->latitud;
    }

    public function obtenerLongitud(): ?float
    {
        return $this->longitud;
    }

    public function tieneCoordenadas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * @return array{latitud: ?float, longitud: ?float}
     */
    public function obtenerCoordenadas(): array
    {
        return [
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
        ];
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarPaisNombre(?string $paisNombre): void
    {
        $this->paisNombre = $paisNombre;
    }

    public function obtenerPaisNombre(): ?string
    {
        return $this->paisNombre;
    }

    /**
     * Retorna una representación consolidada y legible de la ubicación geográfica.
     */
    public function obtenerUbicacionCompleta(): string
    {
        $partes = array_filter([
            $this->direccion,
            $this->distrito,
            $this->provincia,
            $this->departamento,
            $this->paisNombre,
        ], static fn(?string $val) => $val !== null && trim($val) !== '');

        return implode(', ', $partes);
    }

    /**
     * Hidrata una entidad Propiedad a partir de un arreglo asociativo de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function hidratar(array $datos): self
    {
        $propiedad = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string) $datos['descripcion'] : null,
            isset($datos['pais_id']) ? (int) $datos['pais_id'] : 1,
            isset($datos['departamento']) && $datos['departamento'] !== null ? (string) $datos['departamento'] : null,
            isset($datos['provincia']) && $datos['provincia'] !== null ? (string) $datos['provincia'] : null,
            isset($datos['distrito']) && $datos['distrito'] !== null ? (string) $datos['distrito'] : null,
            isset($datos['direccion']) && $datos['direccion'] !== null && trim((string) $datos['direccion']) !== '' ? (string) $datos['direccion'] : null,
            isset($datos['referencia']) && $datos['referencia'] !== null ? (string) $datos['referencia'] : null,
            isset($datos['latitud']) && $datos['latitud'] !== null ? (float) $datos['latitud'] : null,
            isset($datos['longitud']) && $datos['longitud'] !== null ? (float) $datos['longitud'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['observaciones']) && $datos['observaciones'] !== null ? (string) $datos['observaciones'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null,
            isset($datos['zona_horaria']) && $datos['zona_horaria'] !== null && trim((string) $datos['zona_horaria']) !== '' ? (string) $datos['zona_horaria'] : null
        );

        if (isset($datos['pais_nombre'])) {
            $propiedad->asignarPaisNombre((string) $datos['pais_nombre']);
        }

        return $propiedad;
    }

    /**
     * Alias de hidratar().
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return self::hidratar($datos);
    }

    /**
     * Convierte la entidad a un arreglo asociativo para respuestas JSON o vistas.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'pais_id' => $this->paisId,
            'pais_nombre' => $this->paisNombre,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'direccion' => $this->direccion,
            'zona_horaria' => $this->zonaHoraria,
            'referencia' => $this->referencia,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'coordenadas' => $this->obtenerCoordenadas(),
            'estado' => $this->estado,
            'activa' => $this->estaActiva(),
            'observaciones' => $this->observaciones,
            'ubicacion_completa' => $this->obtenerUbicacionCompleta(),
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
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

    /**
     * Alias de haciaArreglo().
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return $this->haciaArreglo();
    }
}
