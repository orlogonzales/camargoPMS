<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\EventoIcalExterno;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para Eventos iCalendar Externos (VEVENT).
 */
class EventoIcalExternoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?EventoIcalExterno
    {
        $sql = 'SELECT * FROM eventos_ical_externos WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function buscarPorConexionYUid(int $conexionId, string $uid): ?EventoIcalExterno
    {
        $sql = 'SELECT * FROM eventos_ical_externos WHERE conexion_ical_id = :conexion_id AND uid_externo = :uid LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'conexion_id' => $conexionId,
            'uid' => $uid,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * @return array<EventoIcalExterno>
     */
    public function listarPorConexion(int $conexionId): array
    {
        $sql = 'SELECT * FROM eventos_ical_externos WHERE conexion_ical_id = :conexion_id ORDER BY fecha_inicio ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conexion_id' => $conexionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @return array<EventoIcalExterno>
     */
    public function listarActivosPorConexion(int $conexionId): array
    {
        $sql = "SELECT * FROM eventos_ical_externos WHERE conexion_ical_id = :conexion_id AND estado_evento = 'ACTIVO' ORDER BY fecha_inicio ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conexion_id' => $conexionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    public function contarActivosPorConexion(int $conexionId): int
    {
        $sql = "SELECT COUNT(*) FROM eventos_ical_externos WHERE conexion_ical_id = :conexion_id AND estado_evento = 'ACTIVO'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conexion_id' => $conexionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca si existe OTRO evento iCal activo para la misma unidad y fecha hotelera,
     * excluyendo el evento indicado. Esencial para reconciliación determinista de unión multi-OTA.
     */
    public function buscarOtroEventoActivoEnFecha(int $unidadId, string $fecha, int $excluirEventoId): ?EventoIcalExterno
    {
        $sql = "SELECT e.*
                FROM eventos_ical_externos e
                JOIN conexiones_ical c ON c.id = e.conexion_ical_id
                WHERE c.unidad_id = :unidad_id
                  AND e.id <> :excluir_id
                  AND e.estado_evento = 'ACTIVO'
                  AND :fecha1 >= e.fecha_inicio
                  AND :fecha2 < e.fecha_fin
                ORDER BY e.id ASC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'unidad_id' => $unidadId,
            'excluir_id' => $excluirEventoId,
            'fecha1' => $fecha,
            'fecha2' => $fecha,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * Obtiene los eventos que no fueron observados durante la corrida actual y cuya fecha de fin
     * es futura o actual (los del pasado se conservan inmutables para no alterar el histórico).
     *
     * @return array<EventoIcalExterno>
     */
    public function obtenerEventosCandidatosAusencia(int $conexionId, int $syncRunId, string $fechaHoteleraHoy): array
    {
        $sql = "SELECT * FROM eventos_ical_externos
                WHERE conexion_ical_id = :conexion_id
                  AND estado_evento = 'ACTIVO'
                  AND (ultimo_sync_run_id IS NULL OR ultimo_sync_run_id <> :sync_run_id)
                  AND fecha_fin >= :fecha_hoy
                ORDER BY fecha_inicio ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'conexion_id' => $conexionId,
            'sync_run_id' => $syncRunId,
            'fecha_hoy' => $fechaHoteleraHoy,
        ]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    public function crear(EventoIcalExterno $evento): int
    {
        $sql = 'INSERT INTO eventos_ical_externos (
                    conexion_ical_id, uid_externo, fecha_inicio, fecha_fin, noches,
                    resumen, descripcion, estado_evento, estado_bloqueo, detalle_conflicto,
                    ultima_modificacion_externa, secuencia_externa, es_recurrente,
                    recurrencia_rrule, ultimo_sync_run_id
                ) VALUES (
                    :conexion_id, :uid, :inicio, :fin, :noches,
                    :resumen, :descripcion, :estado_evento, :estado_bloqueo, :detalle,
                    :ultima_mod, :secuencia, :es_recurrente,
                    :rrule, :sync_run_id
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'conexion_id' => $evento->obtenerConexionIcalId(),
            'uid' => $evento->obtenerUidExterno(),
            'inicio' => $evento->obtenerFechaInicio(),
            'fin' => $evento->obtenerFechaFin(),
            'noches' => $evento->obtenerNoches(),
            'resumen' => $evento->obtenerResumen(),
            'descripcion' => $evento->obtenerDescripcion(),
            'estado_evento' => $evento->obtenerEstadoEvento(),
            'estado_bloqueo' => $evento->obtenerEstadoBloqueo(),
            'detalle' => $evento->obtenerDetalleConflicto(),
            'ultima_mod' => $evento->obtenerUltimaModificacionExterna(),
            'secuencia' => $evento->obtenerSecuenciaExterna(),
            'es_recurrente' => $evento->esRecurrente() ? 1 : 0,
            'rrule' => $evento->obtenerRecurrenciaRrule(),
            'sync_run_id' => $evento->obtenerUltimoSyncRunId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(EventoIcalExterno $evento): bool
    {
        $sql = 'UPDATE eventos_ical_externos SET
                    fecha_inicio = :inicio,
                    fecha_fin = :fin,
                    noches = :noches,
                    resumen = :resumen,
                    descripcion = :descripcion,
                    estado_evento = :estado_evento,
                    estado_bloqueo = :estado_bloqueo,
                    detalle_conflicto = :detalle,
                    ultima_modificacion_externa = :ultima_mod,
                    secuencia_externa = :secuencia,
                    es_recurrente = :es_recurrente,
                    recurrencia_rrule = :rrule,
                    ultimo_sync_run_id = :sync_run_id
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $evento->obtenerId(),
            'inicio' => $evento->obtenerFechaInicio(),
            'fin' => $evento->obtenerFechaFin(),
            'noches' => $evento->obtenerNoches(),
            'resumen' => $evento->obtenerResumen(),
            'descripcion' => $evento->obtenerDescripcion(),
            'estado_evento' => $evento->obtenerEstadoEvento(),
            'estado_bloqueo' => $evento->obtenerEstadoBloqueo(),
            'detalle' => $evento->obtenerDetalleConflicto(),
            'ultima_mod' => $evento->obtenerUltimaModificacionExterna(),
            'secuencia' => $evento->obtenerSecuenciaExterna(),
            'es_recurrente' => $evento->esRecurrente() ? 1 : 0,
            'rrule' => $evento->obtenerRecurrenciaRrule(),
            'sync_run_id' => $evento->obtenerUltimoSyncRunId(),
        ]);
    }

    public function marcarEstado(int $id, string $estadoEvento, string $estadoBloqueo, ?string $detalle = null): bool
    {
        $sql = 'UPDATE eventos_ical_externos SET
                    estado_evento = :estado_evento,
                    estado_bloqueo = :estado_bloqueo,
                    detalle_conflicto = :detalle
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado_evento' => $estadoEvento,
            'estado_bloqueo' => $estadoBloqueo,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Lista eventos en conflicto para una conexión específica.
     *
     * @return array<array<string, mixed>>
     */
    public function listarConflictosPorConexion(int $conexionId): array
    {
        $sql = "SELECT e.id,
                       e.conexion_ical_id,
                       e.uid_externo,
                       e.fecha_inicio,
                       e.fecha_fin,
                       e.noches,
                       e.resumen,
                       e.estado_evento,
                       e.estado_bloqueo,
                       e.detalle_conflicto,
                       e.creado_en,
                       c.nombre AS conexion_nombre,
                       cd.nombre AS canal_nombre,
                       cd.codigo AS canal_codigo,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo
                FROM eventos_ical_externos e
                JOIN conexiones_ical c ON c.id = e.conexion_ical_id
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                WHERE e.conexion_ical_id = :conexion_id
                  AND e.estado_bloqueo = 'EN_CONFLICTO'
                ORDER BY e.fecha_inicio ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conexion_id' => $conexionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todos los eventos en conflicto activos de todas las conexiones.
     *
     * @return array<array<string, mixed>>
     */
    public function listarTodosLosConflictos(): array
    {
        $sql = "SELECT e.id,
                       e.conexion_ical_id,
                       e.uid_externo,
                       e.fecha_inicio,
                       e.fecha_fin,
                       e.noches,
                       e.resumen,
                       e.estado_evento,
                       e.estado_bloqueo,
                       e.detalle_conflicto,
                       e.creado_en,
                       c.nombre AS conexion_nombre,
                       cd.nombre AS canal_nombre,
                       cd.codigo AS canal_codigo,
                       cd.color_badge AS canal_color_badge,
                       u.nombre AS unidad_nombre,
                       u.codigo AS unidad_codigo,
                       p.nombre AS propiedad_nombre
                FROM eventos_ical_externos e
                JOIN conexiones_ical c ON c.id = e.conexion_ical_id
                JOIN canales_distribucion cd ON cd.id = c.canal_id
                JOIN unidades u ON u.id = c.unidad_id
                JOIN propiedades p ON p.id = u.propiedad_id
                WHERE e.estado_bloqueo = 'EN_CONFLICTO'
                ORDER BY e.fecha_inicio ASC";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de eventos en conflicto en todo el sistema.
     */
    public function contarConflictosActivos(): int
    {
        $sql = "SELECT COUNT(*) FROM eventos_ical_externos WHERE estado_bloqueo = 'EN_CONFLICTO'";
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function mapearFila(array $fila): EventoIcalExterno
    {
        return new EventoIcalExterno(
            id: (int) $fila['id'],
            conexionIcalId: (int) $fila['conexion_ical_id'],
            uidExterno: (string) $fila['uid_externo'],
            fechaInicio: (string) $fila['fecha_inicio'],
            fechaFin: (string) $fila['fecha_fin'],
            noches: (int) $fila['noches'],
            resumen: (string) ($fila['resumen'] ?? 'Bloqueo Canal Externo'),
            descripcion: $fila['descripcion'] ? (string) $fila['descripcion'] : null,
            estadoEvento: (string) ($fila['estado_evento'] ?? EventoIcalExterno::ESTADO_EVENTO_ACTIVO),
            estadoBloqueo: (string) ($fila['estado_bloqueo'] ?? EventoIcalExterno::ESTADO_BLOQUEO_APLICADO),
            detalleConflicto: $fila['detalle_conflicto'] ? (string) $fila['detalle_conflicto'] : null,
            ultimaModificacionExterna: $fila['ultima_modificacion_externa'] ? (string) $fila['ultima_modificacion_externa'] : null,
            secuenciaExterna: isset($fila['secuencia_externa']) && $fila['secuencia_externa'] !== null ? (int) $fila['secuencia_externa'] : null,
            esRecurrente: (bool) ($fila['es_recurrente'] ?? false),
            recurrenciaRrule: $fila['recurrencia_rrule'] ? (string) $fila['recurrencia_rrule'] : null,
            ultimoSyncRunId: isset($fila['ultimo_sync_run_id']) && $fila['ultimo_sync_run_id'] !== null ? (int) $fila['ultimo_sync_run_id'] : null,
            creadoEn: (string) ($fila['creado_en'] ?? ''),
            actualizadoEn: (string) ($fila['actualizado_en'] ?? '')
        );
    }
}
