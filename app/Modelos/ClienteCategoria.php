<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio para las Categorías Comerciales de Cliente (CLIENTES-1).
 *
 * Categorías oficiales estándar: ESTANDAR, FRECUENTE, VIP.
 * Restricción arquitectónica: No incluye CORPORATIVO ni EVENTUAL.
 */
class ClienteCategoria
{
    public const CODIGO_ESTANDAR = 'ESTANDAR';
    public const CODIGO_FRECUENTE = 'FRECUENTE';
    public const CODIGO_VIP = 'VIP';

    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private string $colorBadge;
    private bool $esPredeterminada;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        string $colorBadge = 'secondary',
        bool $esPredeterminada = false,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $codigoLimpio = strtoupper(trim($codigo));
        if ($codigoLimpio === '') {
            throw new InvalidArgumentException('El código de categoría no puede estar vacío.');
        }

        $nombreLimpio = trim($nombre);
        if ($nombreLimpio === '') {
            throw new InvalidArgumentException('El nombre de la categoría no puede estar vacío.');
        }

        $this->id = $id;
        $this->codigo = $codigoLimpio;
        $this->nombre = $nombreLimpio;
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->colorBadge = trim($colorBadge) !== '' ? trim($colorBadge) : 'secondary';
        $this->esPredeterminada = $esPredeterminada;
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function fijarNombre(string $nombre): void
    {
        $nombreLimpio = trim($nombre);
        if ($nombreLimpio === '') {
            throw new InvalidArgumentException('El nombre de la categoría no puede estar vacío.');
        }
        $this->nombre = $nombreLimpio;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function fijarDescripcion(?string $descripcion): void
    {
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
    }

    public function obtenerColorBadge(): string
    {
        return $this->colorBadge;
    }

    public function fijarColorBadge(string $colorBadge): void
    {
        $this->colorBadge = trim($colorBadge) !== '' ? trim($colorBadge) : 'secondary';
    }

    public function esPredeterminada(): bool
    {
        return $this->esPredeterminada;
    }

    public function marcarComoPredeterminada(bool $predeterminada): void
    {
        $this->esPredeterminada = $predeterminada;
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function fijarActivo(bool $activo): void
    {
        $this->activo = $activo;
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
     * Serializa la entidad a arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'color_badge' => $this->colorBadge,
            'es_predeterminada' => $this->esPredeterminada,
            'activo' => $this->activo,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * Instancia una categoría a partir de un arreglo asociativo.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            (string) ($datos['color_badge'] ?? 'secondary'),
            !empty($datos['es_predeterminada']),
            !isset($datos['activo']) || !empty($datos['activo']),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
