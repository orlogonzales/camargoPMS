<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Eventos iCalendar Externos (VEVENT).
 */
class EventoIcalExterno
{
    public const ESTADO_EVENTO_ACTIVO = 'ACTIVO';
    public const ESTADO_EVENTO_CANCELADO = 'CANCELADO';
    public const ESTADO_EVENTO_AUSENTE = 'AUSENTE';
    public const ESTADO_EVENTO_EN_CONFLICTO = 'EN_CONFLICTO';

    public const ESTADO_BLOQUEO_APLICADO = 'APLICADO';
    public const ESTADO_BLOQUEO_EN_CONFLICTO = 'EN_CONFLICTO';
    public const ESTADO_BLOQUEO_LIBERADO = 'LIBERADO';
    public const ESTADO_BLOQUEO_APLICADO_CON_SOLAPAMIENTO = 'APLICADO_CON_SOLAPAMIENTO';
    public const ESTADO_BLOQUEO_IGNORADO = 'IGNORADO';

    public function __construct(
        private ?int $id,
        private int $conexionIcalId,
        private string $uidExterno,
        private string $fechaInicio,
        private string $fechaFin,
        private int $noches,
        private string $resumen = 'Bloqueo Canal Externo',
        private ?string $descripcion = null,
        private string $estadoEvento = self::ESTADO_EVENTO_ACTIVO,
        private string $estadoBloqueo = self::ESTADO_BLOQUEO_APLICADO,
        private ?string $detalleConflicto = null,
        private ?string $ultimaModificacionExterna = null,
        private ?int $secuenciaExterna = null,
        private bool $esRecurrente = false,
        private ?string $recurrenciaRrule = null,
        private ?int $ultimoSyncRunId = null,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerConexionIcalId(): int
    {
        return $this->conexionIcalId;
    }

    public function obtenerUidExterno(): string
    {
        return $this->uidExterno;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): string
    {
        return $this->fechaFin;
    }

    public function obtenerNoches(): int
    {
        return $this->noches;
    }

    public function obtenerResumen(): string
    {
        return $this->resumen;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerEstadoEvento(): string
    {
        return $this->estadoEvento;
    }

    public function obtenerEstadoBloqueo(): string
    {
        return $this->estadoBloqueo;
    }

    public function obtenerDetalleConflicto(): ?string
    {
        return $this->detalleConflicto;
    }

    public function obtenerUltimaModificacionExterna(): ?string
    {
        return $this->ultimaModificacionExterna;
    }

    public function obtenerSecuenciaExterna(): ?int
    {
        return $this->secuenciaExterna;
    }

    public function esRecurrente(): bool
    {
        return $this->esRecurrente;
    }

    public function obtenerRecurrenciaRrule(): ?string
    {
        return $this->recurrenciaRrule;
    }

    public function obtenerUltimoSyncRunId(): ?int
    {
        return $this->ultimoSyncRunId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'conexion_ical_id' => $this->conexionIcalId,
            'uid_externo' => $this->uidExterno,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'noches' => $this->noches,
            'resumen' => $this->resumen,
            'descripcion' => $this->descripcion,
            'estado_evento' => $this->estadoEvento,
            'estado_bloqueo' => $this->estadoBloqueo,
            'detalle_conflicto' => $this->detalleConflicto,
            'ultima_modificacion_externa' => $this->ultimaModificacionExterna,
            'secuencia_externa' => $this->secuenciaExterna,
            'es_recurrente' => $this->esRecurrente,
            'recurrencia_rrule' => $this->recurrenciaRrule,
            'ultimo_sync_run_id' => $this->ultimoSyncRunId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
