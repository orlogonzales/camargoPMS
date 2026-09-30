<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Conexiones iCalendar (1:N por Unidad).
 */
class ConexionIcal
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_PAUSADO = 'PAUSADO';
    public const ESTADO_REVOCADO = 'REVOCADO';

    public const RESULTADO_EXITO = 'EXITO';
    public const RESULTADO_CON_ADVERTENCIA = 'CON_ADVERTENCIA';
    public const RESULTADO_ERROR = 'ERROR';
    public const RESULTADO_NO_EJECUTADO = 'NO_EJECUTADO';

    public function __construct(
        private ?int $id,
        private int $unidadId,
        private int $canalId,
        private string $nombre,
        private ?string $urlImportacionCifrada,
        private string $tokenExportacionHash,
        private string $tokenExportacionCifrado,
        private string $tokenPrefijo,
        private bool $importacionHabilitada = true,
        private bool $exportacionHabilitada = true,
        private int $frecuenciaMinutos = 60,
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $ultimaSincronizacionEn = null,
        private string $ultimoResultado = self::RESULTADO_NO_EJECUTADO,
        private ?string $ultimoError = null,
        private ?int $creadoPor = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        // Metadatos auxiliares de join
        private ?string $canalCodigo = null,
        private ?string $canalNombre = null,
        private ?string $canalColorBadge = null,
        private ?string $unidadNombre = null,
        private ?string $unidadCodigo = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerUnidadId(): int
    {
        return $this->unidadId;
    }

    public function obtenerCanalId(): int
    {
        return $this->canalId;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerUrlImportacionCifrada(): ?string
    {
        return $this->urlImportacionCifrada;
    }

    public function tieneUrlImportacion(): bool
    {
        return $this->urlImportacionCifrada !== null && trim($this->urlImportacionCifrada) !== '';
    }

    public function obtenerTokenExportacionHash(): string
    {
        return $this->tokenExportacionHash;
    }

    public function obtenerTokenExportacionCifrado(): string
    {
        return $this->tokenExportacionCifrado;
    }

    public function obtenerTokenPrefijo(): string
    {
        return $this->tokenPrefijo;
    }

    public function importacionHabilitada(): bool
    {
        return $this->importacionHabilitada;
    }

    public function exportacionHabilitada(): bool
    {
        return $this->exportacionHabilitada;
    }

    public function obtenerFrecuenciaMinutos(): int
    {
        return $this->frecuenciaMinutos;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaPausada(): bool
    {
        return $this->estado === self::ESTADO_PAUSADO;
    }

    public function estaRevocada(): bool
    {
        return $this->estado === self::ESTADO_REVOCADO;
    }

    public function obtenerUltimaSincronizacionEn(): ?string
    {
        return $this->ultimaSincronizacionEn;
    }

    public function obtenerUltimoResultado(): string
    {
        return $this->ultimoResultado;
    }

    public function obtenerUltimoError(): ?string
    {
        return $this->ultimoError;
    }

    public function obtenerCreadoPor(): ?int
    {
        return $this->creadoPor;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerCanalCodigo(): ?string
    {
        return $this->canalCodigo;
    }

    public function obtenerCanalNombre(): ?string
    {
        return $this->canalNombre;
    }

    public function obtenerCanalColorBadge(): ?string
    {
        return $this->canalColorBadge;
    }

    public function obtenerUnidadNombre(): ?string
    {
        return $this->unidadNombre;
    }

    public function obtenerUnidadCodigo(): ?string
    {
        return $this->unidadCodigo;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'unidad_id' => $this->unidadId,
            'canal_id' => $this->canalId,
            'nombre' => $this->nombre,
            'token_prefijo' => $this->tokenPrefijo,
            'importacion_habilitada' => $this->importacionHabilitada,
            'exportacion_habilitada' => $this->exportacionHabilitada,
            'frecuencia_minutos' => $this->frecuenciaMinutos,
            'estado' => $this->estado,
            'ultima_sincronizacion_en' => $this->ultimaSincronizacionEn,
            'ultimo_resultado' => $this->ultimoResultado,
            'ultimo_error' => $this->ultimoError,
            'creado_por' => $this->creadoPor,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'canal_codigo' => $this->canalCodigo,
            'canal_nombre' => $this->canalNombre,
            'canal_color_badge' => $this->canalColorBadge,
            'unidad_nombre' => $this->unidadNombre,
            'unidad_codigo' => $this->unidadCodigo,
        ];
    }
}
