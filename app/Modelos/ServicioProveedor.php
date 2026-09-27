<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa la asociación N:M entre un servicio y un proveedor homologado,
 * incluyendo costo pactado, preaviso y condición de preferente.
 */
class ServicioProveedor
{
    private ?int $id;
    private int $servicioId;
    private int $proveedorId;
    private ?string $costoPactado;
    private bool $esPreferente;
    private int $tiempoAnticipacionHoras;
    private string $estado;
    private ?string $creadoEn;

    // Metadatos auxiliares de consulta
    private ?string $proveedorCodigo = null;
    private ?string $proveedorRazonSocial = null;
    private ?string $proveedorNombreComercial = null;
    private ?string $proveedorTipo = null;
    private ?string $servicioNombre = null;

    public function __construct(
        ?int $id,
        int $servicioId,
        int $proveedorId,
        ?string $costoPactado = null,
        bool $esPreferente = false,
        int $tiempoAnticipacionHoras = 0,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->servicioId = $servicioId;
        $this->proveedorId = $proveedorId;
        $this->costoPactado = $costoPactado !== null ? number_format((float) $costoPactado, 2, '.', '') : null;
        $this->esPreferente = $esPreferente;
        $this->tiempoAnticipacionHoras = $tiempoAnticipacionHoras;
        $this->estado = trim(strtoupper($estado));
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerServicioId(): int
    {
        return $this->servicioId;
    }

    public function obtenerProveedorId(): int
    {
        return $this->proveedorId;
    }

    public function obtenerCostoPactado(): ?string
    {
        return $this->costoPactado;
    }

    public function esPreferente(): bool
    {
        return $this->esPreferente;
    }

    public function obtenerTiempoAnticipacionHoras(): int
    {
        return $this->tiempoAnticipacionHoras;
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

    public function asignarMetadatos(
        ?string $proveedorCodigo,
        ?string $proveedorRazonSocial,
        ?string $proveedorNombreComercial,
        ?string $proveedorTipo,
        ?string $servicioNombre
    ): void {
        $this->proveedorCodigo = $proveedorCodigo;
        $this->proveedorRazonSocial = $proveedorRazonSocial;
        $this->proveedorNombreComercial = $proveedorNombreComercial;
        $this->proveedorTipo = $proveedorTipo;
        $this->servicioNombre = $servicioNombre;
    }

    public function obtenerProveedorCodigo(): ?string
    {
        return $this->proveedorCodigo;
    }

    public function obtenerProveedorRazonSocial(): ?string
    {
        return $this->proveedorRazonSocial;
    }

    public function obtenerProveedorNombreComercial(): ?string
    {
        return $this->proveedorNombreComercial;
    }

    public function obtenerProveedorTipo(): ?string
    {
        return $this->proveedorTipo;
    }

    public function obtenerServicioNombre(): ?string
    {
        return $this->servicioNombre;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $relacion = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['servicio_id'] ?? 0),
            (int) ($datos['proveedor_id'] ?? 0),
            isset($datos['costo_pactado']) && $datos['costo_pactado'] !== '' ? (string) $datos['costo_pactado'] : null,
            !empty($datos['es_preferente']),
            (int) ($datos['tiempo_anticipacion_horas'] ?? 0),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );

        $relacion->asignarMetadatos(
            isset($datos['proveedor_codigo']) ? (string) $datos['proveedor_codigo'] : null,
            isset($datos['proveedor_razon_social']) ? (string) $datos['proveedor_razon_social'] : null,
            isset($datos['proveedor_nombre_comercial']) ? (string) $datos['proveedor_nombre_comercial'] : null,
            isset($datos['proveedor_tipo']) ? (string) $datos['proveedor_tipo'] : null,
            isset($datos['servicio_nombre']) ? (string) $datos['servicio_nombre'] : null
        );

        return $relacion;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'servicio_id' => $this->servicioId,
            'proveedor_id' => $this->proveedorId,
            'costo_pactado' => $this->costoPactado,
            'es_preferente' => $this->esPreferente,
            'tiempo_anticipacion_horas' => $this->tiempoAnticipacionHoras,
            'estado' => $this->estado,
            'proveedor_codigo' => $this->proveedorCodigo,
            'proveedor_razon_social' => $this->proveedorRazonSocial,
            'proveedor_nombre_comercial' => $this->proveedorNombreComercial,
            'proveedor_tipo' => $this->proveedorTipo,
            'servicio_nombre' => $this->servicioNombre,
            'creado_en' => $this->creadoEn,
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
