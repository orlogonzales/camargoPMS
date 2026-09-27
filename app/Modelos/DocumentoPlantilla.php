<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio para el Catálogo Maestro de Plantillas Documentales.
 */
class DocumentoPlantilla
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public const ORIGEN_ARRENDAMIENTO = 'ARRENDAMIENTO';
    public const ORIGEN_RESERVA = 'RESERVA';
    public const ORIGEN_ESTADIA = 'ESTADIA';
    public const ORIGEN_PAGO = 'PAGO';
    public const ORIGEN_RECIBO = 'RECIBO';

    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private string $origenTipoPermitido;
    private string $orientacion;
    private string $tamanoPapel;
    private bool $requiereMembrete;
    private ?string $archivoMembreteFondo;
    private int $margenSuperiorMm;
    private int $margenInferiorMm;
    private int $margenIzquierdoMm;
    private int $margenDerechoMm;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        string $origenTipoPermitido = self::ORIGEN_ARRENDAMIENTO,
        string $orientacion = 'PORTRAIT',
        string $tamanoPapel = 'A4',
        bool $requiereMembrete = true,
        ?string $archivoMembreteFondo = null,
        int $margenSuperiorMm = 35,
        int $margenInferiorMm = 28,
        int $margenIzquierdoMm = 20,
        int $margenDerechoMm = 20,
        string $estado = self::ESTADO_ACTIVO,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion ? trim($descripcion) : null;
        $this->origenTipoPermitido = strtoupper(trim($origenTipoPermitido));
        $this->orientacion = strtoupper(trim($orientacion));
        $this->tamanoPapel = strtoupper(trim($tamanoPapel));
        $this->requiereMembrete = $requiereMembrete;
        $this->archivoMembreteFondo = $archivoMembreteFondo ? trim($archivoMembreteFondo) : null;
        $this->margenSuperiorMm = $margenSuperiorMm;
        $this->margenInferiorMm = $margenInferiorMm;
        $this->margenIzquierdoMm = $margenIzquierdoMm;
        $this->margenDerechoMm = $margenDerechoMm;
        $this->estado = strtoupper(trim($estado));
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

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerOrigenTipoPermitido(): string
    {
        return $this->origenTipoPermitido;
    }

    public function obtenerOrientacion(): string
    {
        return $this->orientacion;
    }

    public function obtenerTamanoPapel(): string
    {
        return $this->tamanoPapel;
    }

    public function requiereMembrete(): bool
    {
        return $this->requiereMembrete;
    }

    public function obtenerArchivoMembreteFondo(): ?string
    {
        return $this->archivoMembreteFondo;
    }

    public function obtenerMargenSuperiorMm(): int
    {
        return $this->margenSuperiorMm;
    }

    public function obtenerMargenInferiorMm(): int
    {
        return $this->margenInferiorMm;
    }

    public function obtenerMargenIzquierdoMm(): int
    {
        return $this->margenIzquierdoMm;
    }

    public function obtenerMargenDerechoMm(): int
    {
        return $this->margenDerechoMm;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'origen_tipo_permitido' => $this->origenTipoPermitido,
            'orientacion' => $this->orientacion,
            'tamano_papel' => $this->tamanoPapel,
            'requiere_membrete' => $this->requiereMembrete ? 1 : 0,
            'archivo_membrete_fondo' => $this->archivoMembreteFondo,
            'margen_superior_mm' => $this->margenSuperiorMm,
            'margen_inferior_mm' => $this->margenInferiorMm,
            'margen_izquierdo_mm' => $this->margenIzquierdoMm,
            'margen_derecho_mm' => $this->margenDerechoMm,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
