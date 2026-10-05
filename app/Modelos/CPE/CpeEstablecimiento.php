<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos\CPE;

/**
 * Entidad de dominio que representa la configuración fiscal de un establecimiento o anexo emisor ante SUNAT.
 */
class CpeEstablecimiento
{
    public function __construct(
        private ?int $id,
        private int $empresaId,
        private ?int $propiedadId,
        private string $codigoEstablecimientoSunat,
        private string $razonSocialSnapshot,
        private ?string $nombreComercial,
        private string $direccionFiscal,
        private string $ubigeo,
        private string $departamento,
        private string $provincia,
        private string $distrito,
        private string $modoEntorno = 'BETA',
        private string $proveedorTransporteDefault = 'SUNAT_SOAP_DIRECTO',
        private string $estado = 'ACTIVO',
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerEmpresaId(): int
    {
        return $this->empresaId;
    }

    public function obtenerPropiedadId(): ?int
    {
        return $this->propiedadId;
    }

    public function obtenerCodigoEstablecimientoSunat(): string
    {
        return $this->codigoEstablecimientoSunat;
    }

    public function obtenerRazonSocialSnapshot(): string
    {
        return $this->razonSocialSnapshot;
    }

    public function obtenerNombreComercial(): ?string
    {
        return $this->nombreComercial;
    }

    public function obtenerDireccionFiscal(): string
    {
        return $this->direccionFiscal;
    }

    public function obtenerUbigeo(): string
    {
        return $this->ubigeo;
    }

    public function obtenerDepartamento(): string
    {
        return $this->departamento;
    }

    public function obtenerProvincia(): string
    {
        return $this->provincia;
    }

    public function obtenerDistrito(): string
    {
        return $this->distrito;
    }

    public function obtenerModoEntorno(): string
    {
        return $this->modoEntorno;
    }

    public function esBeta(): bool
    {
        return $this->modoEntorno === 'BETA';
    }

    public function esProduccion(): bool
    {
        return $this->modoEntorno === 'PRODUCCION';
    }

    public function obtenerProveedorTransporteDefault(): string
    {
        return $this->proveedorTransporteDefault;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
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

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresaId,
            'propiedad_id' => $this->propiedadId,
            'codigo_establecimiento_sunat' => $this->codigoEstablecimientoSunat,
            'razon_social_snapshot' => $this->razonSocialSnapshot,
            'nombre_comercial' => $this->nombreComercial,
            'direccion_fiscal' => $this->direccionFiscal,
            'ubigeo' => $this->ubigeo,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'modo_entorno' => $this->modoEntorno,
            'proveedor_transporte_default' => $this->proveedorTransporteDefault,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['empresa_id'] ?? 0),
            isset($datos['propiedad_id']) && $datos['propiedad_id'] !== null ? (int) $datos['propiedad_id'] : null,
            (string) ($datos['codigo_establecimiento_sunat'] ?? '0000'),
            (string) ($datos['razon_social_snapshot'] ?? ''),
            isset($datos['nombre_comercial']) && $datos['nombre_comercial'] !== null ? (string) $datos['nombre_comercial'] : null,
            (string) ($datos['direccion_fiscal'] ?? ''),
            (string) ($datos['ubigeo'] ?? ''),
            (string) ($datos['departamento'] ?? ''),
            (string) ($datos['provincia'] ?? ''),
            (string) ($datos['distrito'] ?? ''),
            (string) ($datos['modo_entorno'] ?? 'BETA'),
            (string) ($datos['proveedor_transporte_default'] ?? 'SUNAT_SOAP_DIRECTO'),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
