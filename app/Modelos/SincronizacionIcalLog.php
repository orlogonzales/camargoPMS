<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Telemetría y Logs Técnicos de Sincronización iCal.
 */
class SincronizacionIcalLog
{
    public const TIPO_IMPORTACION = 'IMPORTACION';
    public const TIPO_EXPORTACION = 'EXPORTACION';

    public const ORIGEN_MANUAL = 'MANUAL';
    public const ORIGEN_CLI = 'CLI';
    public const ORIGEN_CRON = 'CRON';
    public const ORIGEN_WEBHOOK = 'WEBHOOK';

    public const RESULTADO_EXITO = 'EXITO';
    public const RESULTADO_CON_ADVERTENCIA = 'CON_ADVERTENCIA';
    public const RESULTADO_ERROR = 'ERROR';

    public function __construct(
        private ?int $id,
        private int $conexionIcalId,
        private string $tipoOperacion,
        private string $origenEjecucion = self::ORIGEN_MANUAL,
        private string $iniciadoEn = '',
        private ?string $finalizadoEn = null,
        private ?int $duracionMs = null,
        private ?int $httpCodigo = null,
        private string $resultado = self::RESULTADO_EXITO,
        private int $eventosRecibidos = 0,
        private int $eventosCreados = 0,
        private int $eventosActualizados = 0,
        private int $eventosCancelados = 0,
        private int $eventosAusentes = 0,
        private int $conflictosDetectados = 0,
        private ?string $mensajeResultado = null,
        private ?int $actorId = null,
        private ?string $ipOrigen = null,
        private ?string $creadoEn = null
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

    public function obtenerTipoOperacion(): string
    {
        return $this->tipoOperacion;
    }

    public function obtenerOrigenEjecucion(): string
    {
        return $this->origenEjecucion;
    }

    public function obtenerIniciadoEn(): string
    {
        return $this->iniciadoEn;
    }

    public function obtenerFinalizadoEn(): ?string
    {
        return $this->finalizadoEn;
    }

    public function obtenerDuracionMs(): ?int
    {
        return $this->duracionMs;
    }

    public function obtenerHttpCodigo(): ?int
    {
        return $this->httpCodigo;
    }

    public function obtenerResultado(): string
    {
        return $this->resultado;
    }

    public function obtenerEventosRecibidos(): int
    {
        return $this->eventosRecibidos;
    }

    public function obtenerEventosCreados(): int
    {
        return $this->eventosCreados;
    }

    public function obtenerEventosActualizados(): int
    {
        return $this->eventosActualizados;
    }

    public function obtenerEventosCancelados(): int
    {
        return $this->eventosCancelados;
    }

    public function obtenerEventosAusentes(): int
    {
        return $this->eventosAusentes;
    }

    public function obtenerConflictosDetectados(): int
    {
        return $this->conflictosDetectados;
    }

    public function obtenerMensajeResultado(): ?string
    {
        return $this->mensajeResultado;
    }

    public function obtenerActorId(): ?int
    {
        return $this->actorId;
    }

    public function obtenerIpOrigen(): ?string
    {
        return $this->ipOrigen;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'conexion_ical_id' => $this->conexionIcalId,
            'tipo_operacion' => $this->tipoOperacion,
            'origen_ejecucion' => $this->origenEjecucion,
            'iniciado_en' => $this->iniciadoEn,
            'finalizado_en' => $this->finalizadoEn,
            'duracion_ms' => $this->duracionMs,
            'http_codigo' => $this->httpCodigo,
            'resultado' => $this->resultado,
            'eventos_recibidos' => $this->eventosRecibidos,
            'eventos_creados' => $this->eventosCreados,
            'eventos_actualizados' => $this->eventosActualizados,
            'eventos_cancelados' => $this->eventosCancelados,
            'eventos_ausentes' => $this->eventosAusentes,
            'conflictos_detectados' => $this->conflictosDetectados,
            'mensaje_resultado' => $this->mensajeResultado,
            'actor_id' => $this->actorId,
            'ip_origen' => $this->ipOrigen,
            'creado_en' => $this->creadoEn,
        ];
    }
}
