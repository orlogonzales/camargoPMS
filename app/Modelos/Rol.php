<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio puro que representa un Rol de autorización del software.
 *
 * Principio vinculante:
 * CARGO != ROL
 * Un Cargo es un concepto laboral y organizacional.
 * Un Rol es una agrupación de permisos de software para el control de acceso (RBAC).
 */
class Rol
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private string $estado;
    private bool $esSistema;
    private bool $esSuperadministrador;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @param int|null $id
     * @param string $codigo Identificador estructural estable (ej. 'SUPERADMINISTRADOR')
     * @param string $nombre Nombre visible para la interfaz
     * @param string|null $descripcion
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param bool $esSistema Indica si es un rol base protegido
     * @param bool $esSuperadministrador Indica si ostenta autoridad total en el sistema
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        string $estado = 'ACTIVO',
        bool $esSistema = false,
        bool $esSuperadministrador = false,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->estado = strtoupper($estado);
        $this->esSistema = $esSistema;
        $this->esSuperadministrador = $esSuperadministrador;
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

    public function esSuperadministrador(): bool
    {
        return $this->esSuperadministrador;
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
     * Hidrata una instancia del modelo desde un array asociativo de base de datos.
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
            (string) ($datos['estado'] ?? 'ACTIVO'),
            !empty($datos['es_sistema']),
            !empty($datos['es_superadministrador']),
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
