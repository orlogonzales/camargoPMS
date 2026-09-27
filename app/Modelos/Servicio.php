<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa un concepto o ítem del catálogo maestro de servicios adicionales.
 */
class Servicio
{
    private ?int $id;
    private string $codigo;
    private int $categoriaId;
    private int $modalidadCobroId;
    private ?int $propiedadId;
    private string $nombre;
    private ?string $descripcion;
    private string $precioVentaReferencial;
    private string $costoReferencial;
    private string $monedaCodigo;
    private bool $esOperacionInternaHabitual;
    private bool $requiereTrasladoDetalle;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos auxiliares de consulta
    private ?string $categoriaCodigo = null;
    private ?string $categoriaNombre = null;
    private ?string $categoriaIcono = null;
    private ?string $modalidadCobroCodigo = null;
    private ?string $modalidadCobroNombre = null;
    private ?string $propiedadNombre = null;
    /** @var array<int, array<string, mixed>> */
    private array $proveedoresHomologados = [];

    public function __construct(
        ?int $id,
        string $codigo,
        int $categoriaId,
        int $modalidadCobroId,
        ?int $propiedadId,
        string $nombre,
        ?string $descripcion = null,
        string $precioVentaReferencial = '0.00',
        string $costoReferencial = '0.00',
        string $monedaCodigo = 'PEN',
        bool $esOperacionInternaHabitual = false,
        bool $requiereTrasladoDetalle = false,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->categoriaId = $categoriaId;
        $this->modalidadCobroId = $modalidadCobroId;
        $this->propiedadId = $propiedadId;
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->precioVentaReferencial = number_format((float) $precioVentaReferencial, 2, '.', '');
        $this->costoReferencial = number_format((float) $costoReferencial, 2, '.', '');
        $this->monedaCodigo = trim(strtoupper($monedaCodigo));
        $this->esOperacionInternaHabitual = $esOperacionInternaHabitual;
        $this->requiereTrasladoDetalle = $requiereTrasladoDetalle;
        $this->estado = trim(strtoupper($estado));
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

    public function obtenerCategoriaId(): int
    {
        return $this->categoriaId;
    }

    public function obtenerModalidadCobroId(): int
    {
        return $this->modalidadCobroId;
    }

    public function obtenerPropiedadId(): ?int
    {
        return $this->propiedadId;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerPrecioVentaReferencial(): string
    {
        return $this->precioVentaReferencial;
    }

    public function obtenerCostoReferencial(): string
    {
        return $this->costoReferencial;
    }

    public function obtenerMonedaCodigo(): string
    {
        return $this->monedaCodigo;
    }

    public function esOperacionInternaHabitual(): bool
    {
        return $this->esOperacionInternaHabitual;
    }

    public function requiereTrasladoDetalle(): bool
    {
        return $this->requiereTrasladoDetalle;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarMetadatos(
        ?string $categoriaCodigo,
        ?string $categoriaNombre,
        ?string $categoriaIcono,
        ?string $modalidadCodigo,
        ?string $modalidadNombre,
        ?string $propiedadNombre
    ): void {
        $this->categoriaCodigo = $categoriaCodigo;
        $this->categoriaNombre = $categoriaNombre;
        $this->categoriaIcono = $categoriaIcono;
        $this->modalidadCobroCodigo = $modalidadCodigo;
        $this->modalidadCobroNombre = $modalidadNombre;
        $this->propiedadNombre = $propiedadNombre;
    }

    public function obtenerCategoriaCodigo(): ?string
    {
        return $this->categoriaCodigo;
    }

    public function obtenerCategoriaNombre(): ?string
    {
        return $this->categoriaNombre;
    }

    public function obtenerCategoriaIcono(): ?string
    {
        return $this->categoriaIcono;
    }

    public function obtenerModalidadCobroCodigo(): ?string
    {
        return $this->modalidadCobroCodigo;
    }

    public function obtenerModalidadCobroNombre(): ?string
    {
        return $this->modalidadCobroNombre;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    /**
     * @param array<int, array<string, mixed>> $proveedores
     */
    public function asignarProveedoresHomologados(array $proveedores): void
    {
        $this->proveedoresHomologados = $proveedores;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerProveedoresHomologados(): array
    {
        return $this->proveedoresHomologados;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $servicio = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['categoria_id'] ?? 0),
            (int) ($datos['modalidad_cobro_id'] ?? 0),
            isset($datos['propiedad_id']) && $datos['propiedad_id'] !== '' ? (int) $datos['propiedad_id'] : null,
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            (string) ($datos['precio_venta_referencial'] ?? '0.00'),
            (string) ($datos['costo_referencial'] ?? '0.00'),
            (string) ($datos['moneda_codigo'] ?? 'PEN'),
            !empty($datos['es_operacion_interna_habitual']),
            !empty($datos['requiere_traslado_detalle']),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        $servicio->asignarMetadatos(
            isset($datos['categoria_codigo']) ? (string) $datos['categoria_codigo'] : null,
            isset($datos['categoria_nombre']) ? (string) $datos['categoria_nombre'] : null,
            isset($datos['categoria_icono']) ? (string) $datos['categoria_icono'] : null,
            isset($datos['modalidad_cobro_codigo']) ? (string) $datos['modalidad_cobro_codigo'] : null,
            isset($datos['modalidad_cobro_nombre']) ? (string) $datos['modalidad_cobro_nombre'] : null,
            isset($datos['propiedad_nombre']) ? (string) $datos['propiedad_nombre'] : null
        );

        if (isset($datos['proveedores_homologados']) && is_array($datos['proveedores_homologados'])) {
            $servicio->asignarProveedoresHomologados($datos['proveedores_homologados']);
        }

        return $servicio;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'categoria_id' => $this->categoriaId,
            'categoria_codigo' => $this->categoriaCodigo,
            'categoria_nombre' => $this->categoriaNombre,
            'categoria_icono' => $this->categoriaIcono,
            'modalidad_cobro_id' => $this->modalidadCobroId,
            'modalidad_cobro_codigo' => $this->modalidadCobroCodigo,
            'modalidad_cobro_nombre' => $this->modalidadCobroNombre,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio_venta_referencial' => $this->precioVentaReferencial,
            'costo_referencial' => $this->costoReferencial,
            'moneda_codigo' => $this->monedaCodigo,
            'es_operacion_interna_habitual' => $this->esOperacionInternaHabitual,
            'requiere_traslado_detalle' => $this->requiereTrasladoDetalle,
            'estado' => $this->estado,
            'proveedores_homologados' => $this->proveedoresHomologados,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
