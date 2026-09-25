<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un tipo de documento de identidad para personas naturales.
 * Incorpora la configuración de alcance jurisdiccional estructural (país fijo o país emisor obligatorio).
 */
final class TipoDocumento
{
    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private ?int $longitudExacta;
    private ?int $longitudMinima;
    private ?int $longitudMaxima;
    private ?string $formatoRegex;
    private ?int $paisFijoId;
    private bool $paisEmisorObligatorio;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        ?int $longitudExacta = null,
        ?int $longitudMinima = null,
        ?int $longitudMaxima = null,
        ?string $formatoRegex = null,
        ?int $paisFijoId = null,
        bool $paisEmisorObligatorio = false,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = strtoupper(trim($codigo));
        $this->nombre = trim($nombre);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->longitudExacta = $longitudExacta;
        $this->longitudMinima = $longitudMinima;
        $this->longitudMaxima = $longitudMaxima;
        $this->formatoRegex = $formatoRegex;
        $this->paisFijoId = $paisFijoId;
        $this->paisEmisorObligatorio = $paisEmisorObligatorio;
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) && $datos['descripcion'] !== null ? (string) $datos['descripcion'] : null,
            isset($datos['longitud_exacta']) && $datos['longitud_exacta'] !== null ? (int) $datos['longitud_exacta'] : null,
            isset($datos['longitud_minima']) && $datos['longitud_minima'] !== null ? (int) $datos['longitud_minima'] : null,
            isset($datos['longitud_maxima']) && $datos['longitud_maxima'] !== null ? (int) $datos['longitud_maxima'] : null,
            isset($datos['formato_regex']) && $datos['formato_regex'] !== null ? (string) $datos['formato_regex'] : null,
            isset($datos['pais_fijo_id']) && $datos['pais_fijo_id'] !== null ? (int) $datos['pais_fijo_id'] : null,
            (bool) ($datos['pais_emisor_obligatorio'] ?? false),
            (bool) ($datos['activo'] ?? true),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
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

    public function obtenerLongitudExacta(): ?int
    {
        return $this->longitudExacta;
    }

    public function obtenerLongitudMinima(): ?int
    {
        return $this->longitudMinima;
    }

    public function obtenerLongitudMaxima(): ?int
    {
        return $this->longitudMaxima;
    }

    public function obtenerFormatoRegex(): ?string
    {
        return $this->formatoRegex;
    }

    public function obtenerPaisFijoId(): ?int
    {
        return $this->paisFijoId;
    }

    public function tienePaisFijo(): bool
    {
        return $this->paisFijoId !== null;
    }

    public function requierePaisEmisor(): bool
    {
        return $this->paisEmisorObligatorio;
    }

    public function esActivo(): bool
    {
        return $this->activo;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'longitud_exacta' => $this->longitudExacta,
            'longitud_minima' => $this->longitudMinima,
            'longitud_maxima' => $this->longitudMaxima,
            'formato_regex' => $this->formatoRegex,
            'pais_fijo_id' => $this->paisFijoId,
            'pais_emisor_obligatorio' => $this->paisEmisorObligatorio,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
