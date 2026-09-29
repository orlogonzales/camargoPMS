<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio puro para Empresas Operadoras y Emisores Legales (EMPRESA-1).
 *
 * Principios vinculantes:
 * - EMPRESA/EMISOR != PROPIEDAD != UNIDAD (D-091).
 * - Multiempresa extensible (1..N empresas) sin sobrediseño.
 * - Empresa (1) <---> (N) Propiedades.
 * - Representante legal vinculado al maestro central de Personas (personas.id).
 * - Ciclo de vida estricto: ACTIVO <-> INACTIVO (cero DELETE físico con dependencias).
 */
class Empresa
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private string $codigo;
    private int $tipoDocumentoId;
    private string $numeroDocumento;
    private string $razonSocial;
    private ?string $nombreComercial;
    private string $direccionFiscal;
    private int $paisId;
    private ?string $departamento;
    private ?string $provincia;
    private ?string $distrito;
    private ?string $ubigeo;
    private ?string $telefono;
    private ?string $email;
    private ?string $sitioWeb;
    private ?string $logoUrl;
    private ?int $representantePersonaId;
    private ?string $representanteCargo;
    private ?string $representantePoderPartida;
    private bool $esPrincipal;
    private string $estado;
    private ?string $observaciones;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos auxiliares de lectura / relaciones
    private ?string $representanteNombreCompleto = null;
    private ?string $representanteDocumento = null;
    private ?string $tipoDocumentoCodigo = null;
    private ?string $paisNombre = null;
    private int $totalPropiedades = 0;

    public function __construct(
        ?int $id,
        string $codigo,
        int $tipoDocumentoId,
        string $numeroDocumento,
        string $razonSocial,
        ?string $nombreComercial = null,
        string $direccionFiscal = '',
        int $paisId = 1,
        ?string $departamento = null,
        ?string $provincia = null,
        ?string $distrito = null,
        ?string $ubigeo = null,
        ?string $telefono = null,
        ?string $email = null,
        ?string $sitioWeb = null,
        ?string $logoUrl = null,
        ?int $representantePersonaId = null,
        ?string $representanteCargo = 'Gerente General',
        ?string $representantePoderPartida = null,
        bool $esPrincipal = false,
        string $estado = self::ESTADO_ACTIVO,
        ?string $observaciones = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $codigoTrim = strtoupper(trim($codigo));
        if ($codigoTrim === '') {
            throw new InvalidArgumentException('El código de la empresa no puede estar vacío.');
        }

        $razonSocialTrim = trim($razonSocial);
        if ($razonSocialTrim === '') {
            throw new InvalidArgumentException('La razón social de la empresa es obligatoria.');
        }

        $numeroDocTrim = trim($numeroDocumento);
        if ($numeroDocTrim === '') {
            throw new InvalidArgumentException('El número de documento / RUC no puede estar vacío.');
        }

        $direccionTrim = trim($direccionFiscal);
        if ($direccionTrim === '') {
            throw new InvalidArgumentException('El domicilio fiscal de la empresa es obligatorio.');
        }

        if (!in_array($estado, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de empresa inválido: '{$estado}'.");
        }

        $this->id = $id;
        $this->codigo = $codigoTrim;
        $this->tipoDocumentoId = $tipoDocumentoId;
        $this->numeroDocumento = $numeroDocTrim;
        $this->razonSocial = $razonSocialTrim;
        $this->nombreComercial = $nombreComercial ? trim($nombreComercial) : null;
        $this->direccionFiscal = $direccionTrim;
        $this->paisId = $paisId;
        $this->departamento = $departamento ? trim($departamento) : null;
        $this->provincia = $provincia ? trim($provincia) : null;
        $this->distrito = $distrito ? trim($distrito) : null;
        $this->ubigeo = $ubigeo ? trim($ubigeo) : null;
        $this->telefono = $telefono ? trim($telefono) : null;
        $this->email = $email ? strtolower(trim($email)) : null;
        $this->sitioWeb = $sitioWeb ? trim($sitioWeb) : null;
        $this->logoUrl = $logoUrl ? trim($logoUrl) : null;
        $this->representantePersonaId = $representantePersonaId;
        $this->representanteCargo = $representanteCargo ? trim($representanteCargo) : 'Gerente General';
        $this->representantePoderPartida = $representantePoderPartida ? trim($representantePoderPartida) : null;
        $this->esPrincipal = $esPrincipal;
        $this->estado = $estado;
        $this->observaciones = $observaciones ? trim($observaciones) : null;
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

    public function obtenerTipoDocumentoId(): int
    {
        return $this->tipoDocumentoId;
    }

    public function obtenerNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function obtenerRazonSocial(): string
    {
        return $this->razonSocial;
    }

    public function obtenerNombreComercial(): ?string
    {
        return $this->nombreComercial;
    }

    public function obtenerNombreMostrable(): string
    {
        return $this->nombreComercial ?: $this->razonSocial;
    }

    public function obtenerDireccionFiscal(): string
    {
        return $this->direccionFiscal;
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

    public function obtenerUbigeo(): ?string
    {
        return $this->ubigeo;
    }

    public function obtenerTelefono(): ?string
    {
        return $this->telefono;
    }

    public function obtenerEmail(): ?string
    {
        return $this->email;
    }

    public function obtenerSitioWeb(): ?string
    {
        return $this->sitioWeb;
    }

    public function obtenerLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function obtenerRepresentantePersonaId(): ?int
    {
        return $this->representantePersonaId;
    }

    public function obtenerRepresentanteCargo(): ?string
    {
        return $this->representanteCargo;
    }

    public function obtenerRepresentantePoderPartida(): ?string
    {
        return $this->representantePoderPartida;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
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

    // Setters / Getters para metadatos auxiliares
    public function asignarMetadatos(
        ?string $representanteNombreCompleto = null,
        ?string $representanteDocumento = null,
        ?string $tipoDocumentoCodigo = null,
        ?string $paisNombre = null,
        int $totalPropiedades = 0
    ): self {
        $this->representanteNombreCompleto = $representanteNombreCompleto;
        $this->representanteDocumento = $representanteDocumento;
        $this->tipoDocumentoCodigo = $tipoDocumentoCodigo;
        $this->paisNombre = $paisNombre;
        $this->totalPropiedades = $totalPropiedades;
        return $this;
    }

    public function obtenerRepresentanteNombreCompleto(): ?string
    {
        return $this->representanteNombreCompleto;
    }

    public function obtenerRepresentanteDocumento(): ?string
    {
        return $this->representanteDocumento;
    }

    public function obtenerTipoDocumentoCodigo(): ?string
    {
        return $this->tipoDocumentoCodigo;
    }

    public function obtenerPaisNombre(): ?string
    {
        return $this->paisNombre;
    }

    public function obtenerTotalPropiedades(): int
    {
        return $this->totalPropiedades;
    }

    /**
     * Serialización plana segura para API / JSON.
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo_documento_id' => $this->tipoDocumentoId,
            'tipo_documento_codigo' => $this->tipoDocumentoCodigo,
            'numero_documento' => $this->numeroDocumento,
            'razon_social' => $this->razonSocial,
            'nombre_comercial' => $this->nombreComercial,
            'nombre_mostrable' => $this->obtenerNombreMostrable(),
            'direccion_fiscal' => $this->direccionFiscal,
            'pais_id' => $this->paisId,
            'pais_nombre' => $this->paisNombre,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'ubigeo' => $this->ubigeo,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'sitio_web' => $this->sitioWeb,
            'logo_url' => $this->logoUrl,
            'representante_persona_id' => $this->representantePersonaId,
            'representante_nombre_completo' => $this->representanteNombreCompleto,
            'representante_documento' => $this->representanteDocumento,
            'representante_cargo' => $this->representanteCargo,
            'representante_poder_partida' => $this->representantePoderPartida,
            'es_principal' => $this->esPrincipal,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'total_propiedades' => $this->totalPropiedades,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
