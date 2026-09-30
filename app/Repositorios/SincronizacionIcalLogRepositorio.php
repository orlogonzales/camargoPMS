<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\SincronizacionIcalLog;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para Telemetría y Logs Técnicos de Sincronización iCal.
 */
class SincronizacionIcalLogRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function iniciarLog(
        int $conexionId,
        string $tipoOperacion,
        string $origenEjecucion = SincronizacionIcalLog::ORIGEN_MANUAL,
        ?int $actorId = null,
        ?string $ipOrigen = null
    ): int {
        $sql = 'INSERT INTO sincronizaciones_ical_log (
                    conexion_ical_id, tipo_operacion, origen_ejecucion,
                    iniciado_en, resultado, actor_id, ip_origen
                ) VALUES (
                    :conexion_id, :tipo, :origen,
                    NOW(), :resultado, :actor_id, :ip
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'conexion_id' => $conexionId,
            'tipo' => $tipoOperacion,
            'origen' => $origenEjecucion,
            'resultado' => SincronizacionIcalLog::RESULTADO_EXITO,
            'actor_id' => $actorId,
            'ip' => $ipOrigen,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function finalizarLog(
        int $logId,
        int $httpCodigo,
        string $resultado,
        int $duracionMs,
        int $recibidos,
        int $creados,
        int $actualizados,
        int $cancelados,
        int $ausentes,
        int $conflictos,
        ?string $mensaje = null
    ): bool {
        $sql = 'UPDATE sincronizaciones_ical_log SET
                    finalizado_en = NOW(),
                    duracion_ms = :duracion,
                    http_codigo = :http_codigo,
                    resultado = :resultado,
                    eventos_recibidos = :recibidos,
                    eventos_creados = :creados,
                    eventos_actualizados = :actualizados,
                    eventos_cancelados = :cancelados,
                    eventos_ausentes = :ausentes,
                    conflictos_detectados = :conflictos,
                    mensaje_resultado = :mensaje
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $logId,
            'duracion' => $duracionMs,
            'http_codigo' => $httpCodigo,
            'resultado' => $resultado,
            'recibidos' => $recibidos,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'cancelados' => $cancelados,
            'ausentes' => $ausentes,
            'conflictos' => $conflictos,
            'mensaje' => $mensaje,
        ]);
    }

    public function buscarPorId(int $id): ?SincronizacionIcalLog
    {
        $sql = 'SELECT * FROM sincronizaciones_ical_log WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function obtenerUltimoPorConexion(int $conexionId): ?SincronizacionIcalLog
    {
        $sql = 'SELECT * FROM sincronizaciones_ical_log WHERE conexion_ical_id = :conexion_id ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conexion_id' => $conexionId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function mapearFila(array $fila): SincronizacionIcalLog
    {
        return new SincronizacionIcalLog(
            id: (int) $fila['id'],
            conexionIcalId: (int) $fila['conexion_ical_id'],
            tipoOperacion: (string) $fila['tipo_operacion'],
            origenEjecucion: (string) ($fila['origen_ejecucion'] ?? SincronizacionIcalLog::ORIGEN_MANUAL),
            iniciadoEn: (string) $fila['iniciado_en'],
            finalizadoEn: $fila['finalizado_en'] ? (string) $fila['finalizado_en'] : null,
            duracionMs: isset($fila['duracion_ms']) && $fila['duracion_ms'] !== null ? (int) $fila['duracion_ms'] : null,
            httpCodigo: isset($fila['http_codigo']) && $fila['http_codigo'] !== null ? (int) $fila['http_codigo'] : null,
            resultado: (string) ($fila['resultado'] ?? SincronizacionIcalLog::RESULTADO_EXITO),
            eventosRecibidos: (int) ($fila['eventos_recibidos'] ?? 0),
            eventosCreados: (int) ($fila['eventos_creados'] ?? 0),
            eventosActualizados: (int) ($fila['eventos_actualizados'] ?? 0),
            eventosCancelados: (int) ($fila['eventos_cancelados'] ?? 0),
            eventosAusentes: (int) ($fila['eventos_ausentes'] ?? 0),
            conflictosDetectados: (int) ($fila['conflictos_detectados'] ?? 0),
            mensajeResultado: $fila['mensaje_resultado'] ? (string) $fila['mensaje_resultado'] : null,
            actorId: isset($fila['actor_id']) && $fila['actor_id'] !== null ? (int) $fila['actor_id'] : null,
            ipOrigen: $fila['ip_origen'] ? (string) $fila['ip_origen'] : null,
            creadoEn: (string) ($fila['creado_en'] ?? '')
        );
    }
}
