<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio para Feriados y Días No Laborables (RECLAMACIONES-1).
 *
 * Principio regulatorio:
 * FERIADO LEGAL ≠ DÍA NO LABORABLE ADMINISTRATIVO
 * - FERIADO_LEGAL: Festividad nacional de descanso obligatorio por ley (sector público y privado).
 * - NO_LABORABLE_COMPENSABLE: Decretado administrativamente (habitualmente obligatorio para sector público y opcional/compensable en sector privado).
 * El cómputo legal del plazo de reclamaciones solo excluye fechas con activo = 1 y aplica_sector_privado = 1.
 */
class Feriado
{
    public const TIPO_FERIADO_LEGAL = 'FERIADO_LEGAL';
    public const TIPO_NO_LABORABLE_COMPENSABLE = 'NO_LABORABLE_COMPENSABLE';

    public const TIPOS_VALIDOS = [
        self::TIPO_FERIADO_LEGAL,
        self::TIPO_NO_LABORABLE_COMPENSABLE,
    ];

    private ?int $id;
    private string $fecha;
    private string $descripcion;
    private string $tipo;
    private bool $aplicaSectorPrivado;
    private bool $activo;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        string $fecha,
        string $descripcion,
        string $tipo = self::TIPO_FERIADO_LEGAL,
        bool $aplicaSectorPrivado = true,
        bool $activo = true,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $fechaLimpia = trim($fecha);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaLimpia)) {
            throw new InvalidArgumentException("Formato de fecha inválido para feriado (esperado YYYY-MM-DD): {$fecha}");
        }

        $descLimpia = trim($descripcion);
        if ($descLimpia === '') {
            throw new InvalidArgumentException("La descripción del feriado no puede estar vacía.");
        }

        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de feriado inválido: {$tipo}");
        }

        $this->id = $id;
        $this->fecha = $fechaLimpia;
        $this->descripcion = $descLimpia;
        $this->tipo = $tipo;
        $this->aplicaSectorPrivado = $aplicaSectorPrivado;
        $this->activo = $activo;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerFecha(): string
    {
        return $this->fecha;
    }

    public function obtenerDescripcion(): string
    {
        return $this->descripcion;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function aplicaSectorPrivado(): bool
    {
        return $this->aplicaSectorPrivado;
    }

    public function esActivo(): bool
    {
        return $this->activo;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function establecerDescripcion(string $descripcion): void
    {
        $descLimpia = trim($descripcion);
        if ($descLimpia === '') {
            throw new InvalidArgumentException("La descripción no puede estar vacía.");
        }
        $this->descripcion = $descLimpia;
    }

    public function establecerTipo(string $tipo): void
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de feriado inválido: {$tipo}");
        }
        $this->tipo = $tipo;
    }

    public function establecerAplicaSectorPrivado(bool $aplica): void
    {
        $this->aplicaSectorPrivado = $aplica;
    }

    public function activar(): void
    {
        $this->activo = true;
    }

    public function desactivar(): void
    {
        $this->activo = false;
    }

    /**
     * Determina si la fecha debe descontarse en el cómputo de días hábiles del sector privado.
     */
    public function esExcluyenteParaSectorPrivado(): bool
    {
        return $this->activo && $this->aplicaSectorPrivado;
    }

    /**
     * Serializa a arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'aplica_sector_privado' => $this->aplicaSectorPrivado,
            'activo' => $this->activo,
            'es_excluyente_privado' => $this->esExcluyenteParaSectorPrivado(),
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * Reconstruye la entidad desde arreglo de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['fecha'] ?? ''),
            (string) ($datos['descripcion'] ?? ''),
            (string) ($datos['tipo'] ?? self::TIPO_FERIADO_LEGAL),
            !empty($datos['aplica_sector_privado']),
            !empty($datos['activo']),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
