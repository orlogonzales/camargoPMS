<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio puro que representa una opción en la jerarquía de navegación dinámica.
 *
 * Satisface el contrato de navegación Alina de 2 niveles:
 *   - Nivel 1: Categoría Principal (padreId = null) -> navbar-menu-list con [data-target="clave"]
 *   - Nivel 2: Opción Secundaria (padreId != null) -> main-side-menu con [id="clave"]
 */
class OpcionMenu
{
    private ?int $id;
    private ?int $padreId;
    private string $clave;
    private string $nombre;
    private ?string $icono;
    private ?string $ruta;
    private int $orden;
    private string $estado;
    private ?int $permisoId;
    private bool $esSistema;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /** @var Permiso|null Entidad permiso asociada */
    private ?Permiso $permiso = null;

    /** @var OpcionMenu|null Entidad categoría padre (si es secundaria) */
    private ?OpcionMenu $padre = null;

    /** @var array<int, OpcionMenu> Opciones secundarias hijas (si es principal) */
    private array $hijos = [];

    /**
     * @param int|null $id
     * @param int|null $padreId
     * @param string $clave Identificador técnico único y estable
     * @param string $nombre Etiqueta visible
     * @param string|null $icono Clase de icono (ej. 'ti ti-smart-home')
     * @param string|null $ruta Ruta local interna (ej. '/usuarios')
     * @param int $orden Posición relativa entre hermanos
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param int|null $permisoId ID del permiso RBAC requerido para visibilidad
     * @param bool $esSistema Indica si es un elemento estructural protegido
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        ?int $id,
        ?int $padreId,
        string $clave,
        string $nombre,
        ?string $icono = null,
        ?string $ruta = null,
        int $orden = 1,
        string $estado = 'ACTIVO',
        ?int $permisoId = null,
        bool $esSistema = false,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->padreId = $padreId !== null && $padreId > 0 ? $padreId : null;
        $this->clave = trim(strtolower($clave));
        $this->nombre = trim($nombre);
        $this->icono = $icono !== null && trim($icono) !== '' ? trim($icono) : null;
        $this->ruta = $ruta !== null && trim($ruta) !== '' ? trim($ruta) : null;
        $this->orden = $orden;
        $this->estado = strtoupper(trim($estado)) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $this->permisoId = $permisoId !== null && $permisoId > 0 ? $permisoId : null;
        $this->esSistema = $esSistema;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPadreId(): ?int
    {
        return $this->padreId;
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerIcono(): ?string
    {
        return $this->icono;
    }

    public function obtenerRuta(): ?string
    {
        return $this->ruta;
    }

    public function obtenerOrden(): int
    {
        return $this->orden;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerPermisoId(): ?int
    {
        return $this->permisoId;
    }

    public function esSistema(): bool
    {
        return $this->esSistema;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function esPrincipal(): bool
    {
        return $this->padreId === null;
    }

    public function esSecundaria(): bool
    {
        return $this->padreId !== null;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerPermiso(): ?Permiso
    {
        return $this->permiso;
    }

    public function asignarPermiso(?Permiso $permiso): void
    {
        $this->permiso = $permiso;
        $this->permisoId = $permiso !== null ? $permiso->obtenerId() : null;
    }

    public function obtenerPadre(): ?self
    {
        return $this->padre;
    }

    public function asignarPadre(?self $padre): void
    {
        $this->padre = $padre;
        $this->padreId = $padre !== null ? $padre->obtenerId() : null;
    }

    /**
     * @return array<int, self>
     */
    public function obtenerHijos(): array
    {
        return $this->hijos;
    }

    /**
     * @param array<int, self> $hijos
     */
    public function asignarHijos(array $hijos): void
    {
        $this->hijos = $hijos;
    }

    public function agregarHijo(self $hijo): void
    {
        $this->hijos[] = $hijo;
    }

    /**
     * Hidrata una instancia del modelo desde un arreglo asociativo de BD.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function hidratar(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            isset($datos['padre_id']) && $datos['padre_id'] !== null ? (int) $datos['padre_id'] : null,
            (string) ($datos['clave'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['icono']) && $datos['icono'] !== null ? (string) $datos['icono'] : null,
            isset($datos['ruta']) && $datos['ruta'] !== null ? (string) $datos['ruta'] : null,
            isset($datos['orden']) ? (int) $datos['orden'] : 1,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['permiso_id']) && $datos['permiso_id'] !== null ? (int) $datos['permiso_id'] : null,
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

    /**
     * Convierte la entidad a un arreglo asociativo para respuestas JSON o vistas.
     *
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'padre_id' => $this->padreId,
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            'ruta' => $this->ruta,
            'orden' => $this->orden,
            'estado' => $this->estado,
            'permiso_id' => $this->permisoId,
            'es_sistema' => $this->esSistema,
            'es_principal' => $this->esPrincipal(),
            'permiso_codigo' => $this->permiso ? $this->permiso->obtenerCodigo() : null,
            'permiso_nombre' => $this->permiso ? $this->permiso->obtenerNombre() : null,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'hijos' => array_map(static fn(self $hijo) => $hijo->haciaArreglo(), $this->hijos)
        ];
    }
}
