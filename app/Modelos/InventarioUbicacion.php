<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una ubicación polimórfica de inventario.
 * 
 * Reglas vinculantes (D-078):
 * - Tipos: ALMACEN, UNIDAD, CUSTODIA_EXTERNA.
 * - Si tipo = UNIDAD, unidadId es obligatorio (habitación física).
 * - Si tipo != UNIDAD, unidadId debe ser NULL.
 * - CUSTODIA_EXTERNA vincula a proveedorId (lavandería/taller de SERVICIOS-1).
 */
class InventarioUbicacion
{
    public const TIPO_ALMACEN = 'ALMACEN';
    public const TIPO_UNIDAD = 'UNIDAD';
    public const TIPO_CUSTODIA_EXTERNA = 'CUSTODIA_EXTERNA';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $propiedadId;
    private string $codigo;
    private string $nombre;
    private string $tipo;
    private ?int $unidadId;
    private ?int $proveedorId;
    private ?int $responsableColaboradorId;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Proyecciones
    private ?string $propiedadNombre = null;
    private ?string $unidadNumero = null;
    private ?string $proveedorRazonSocial = null;
    private ?string $responsableNombre = null;

    public function __construct(
        ?int $id,
        int $propiedadId,
        string $codigo,
        string $nombre,
        string $tipo,
        ?int $unidadId = null,
        ?int $proveedorId = null,
        ?int $responsableColaboradorId = null,
        string $estado = self::ESTADO_ACTIVO,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->propiedadId = $propiedadId;
        $this->codigo = trim($codigo);
        $this->nombre = trim($nombre);
        $this->tipo = strtoupper(trim($tipo));
        $this->unidadId = $unidadId;
        $this->proveedorId = $proveedorId;
        $this->responsableColaboradorId = $responsableColaboradorId;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function esAlmacen(): bool
    {
        return $this->tipo === self::TIPO_ALMACEN;
    }

    public function esUnidad(): bool
    {
        return $this->tipo === self::TIPO_UNIDAD;
    }

    public function esCustodiaExterna(): bool
    {
        return $this->tipo === self::TIPO_CUSTODIA_EXTERNA;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function obtenerProveedorId(): ?int
    {
        return $this->proveedorId;
    }

    public function obtenerResponsableColaboradorId(): ?int
    {
        return $this->responsableColaboradorId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarPropiedadNombre(?string $nombre): void
    {
        $this->propiedadNombre = $nombre;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function asignarUnidadNumero(?string $numero): void
    {
        $this->unidadNumero = $numero;
    }

    public function obtenerUnidadNumero(): ?string
    {
        return $this->unidadNumero;
    }

    public function asignarProveedorRazonSocial(?string $razonSocial): void
    {
        $this->proveedorRazonSocial = $razonSocial;
    }

    public function obtenerProveedorRazonSocial(): ?string
    {
        return $this->proveedorRazonSocial;
    }

    public function asignarResponsableNombre(?string $nombre): void
    {
        $this->responsableNombre = $nombre;
    }

    public function obtenerResponsableNombre(): ?string
    {
        return $this->responsableNombre;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'unidad_id' => $this->unidadId,
            'unidad_numero' => $this->unidadNumero,
            'proveedor_id' => $this->proveedorId,
            'proveedor_razon_social' => $this->proveedorRazonSocial,
            'responsable_colaborador_id' => $this->responsableColaboradorId,
            'responsable_nombre' => $this->responsableNombre,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
