<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Canales de Distribución y Plataformas Externas.
 */
class CanalDistribucion
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public function __construct(
        private ?int $id,
        private string $codigo,
        private string $nombre,
        private string $protocolo = 'ICAL',
        private int $frecuenciaDefectoMinutos = 60,
        private string $colorBadge = 'badge-light-primary',
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
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

    public function obtenerProtocolo(): string
    {
        return $this->protocolo;
    }

    public function obtenerFrecuenciaDefectoMinutos(): int
    {
        return $this->frecuenciaDefectoMinutos;
    }

    public function obtenerColorBadge(): string
    {
        return $this->colorBadge;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'protocolo' => $this->protocolo,
            'frecuencia_defecto_minutos' => $this->frecuenciaDefectoMinutos,
            'color_badge' => $this->colorBadge,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
