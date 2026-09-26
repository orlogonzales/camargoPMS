<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio puro que representa un Permiso atómico del software.
 *
 * Convención obligatoria de nomenclatura:
 * Formato: recurso.accion (ej. 'usuarios.ver', 'roles.crear')
 */
class Permiso
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private string $modulo;
    private string $estado;
    private bool $esSistema;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @param int|null $id
     * @param string $codigo Formato recurso.accion (ej. 'usuarios.ver')
     * @param string $nombre Nombre descriptivo
     * @param string|null $descripcion
     * @param string $modulo Módulo al que pertenece
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param bool $esSistema Indica si es estructural del sistema
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        string $modulo = 'general',
        string $estado = 'ACTIVO',
        bool $esSistema = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtolower($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->modulo = trim(strtolower($modulo));
        $this->estado = strtoupper($estado);
        $this->esSistema = $esSistema;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    /**
     * Alias semántico para obtenerCodigo().
     */
    public function obtenerClave(): string
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

    public function obtenerModulo(): string
    {
        return $this->modulo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function esSistema(): bool
    {
        return $this->esSistema;
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
     * Hidrata una instancia del modelo desde un array de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function hidratar(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? $datos['clave'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string) $datos['descripcion'] : null,
            (string) ($datos['modulo'] ?? 'general'),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            !empty($datos['es_sistema']),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
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
}
