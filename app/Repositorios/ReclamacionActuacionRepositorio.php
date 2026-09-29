<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ReclamacionActuacion;
use PDO;

/**
 * Repositorio para la bitácora de actuaciones inmutables y append-only (RECLAMACIONES-1).
 *
 * Principio:
 * EXPEDIENTE ≠ ACTUACIÓN
 * Las actuaciones son inmutables y cronológicas (CERO DELETE, CERO UPDATE).
 */
class ReclamacionActuacionRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Inserta una nueva actuación append-only en el expediente.
     */
    public function insertar(ReclamacionActuacion $actuacion): ReclamacionActuacion
    {
        $sql = 'INSERT INTO reclamacion_actuaciones (
                    reclamacion_id, actor_id, tipo_actuacion, descripcion,
                    medio_notificacion, destinatario_notificacion, fecha_notificacion,
                    documento_emitido_id, archivo_adjunto_path, creado_en
                ) VALUES (
                    :reclamacion_id, :actor_id, :tipo_actuacion, :descripcion,
                    :medio_notificacion, :destinatario_notificacion, :fecha_notificacion,
                    :documento_emitido_id, :archivo_adjunto_path, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'reclamacion_id' => $actuacion->obtenerReclamacionId(),
            'actor_id' => $actuacion->obtenerActorId(),
            'tipo_actuacion' => $actuacion->obtenerTipoActuacion(),
            'descripcion' => $actuacion->obtenerDescripcion(),
            'medio_notificacion' => $actuacion->obtenerMedioNotificacion(),
            'destinatario_notificacion' => $actuacion->obtenerDestinatarioNotificacion(),
            'fecha_notificacion' => $actuacion->obtenerFechaNotificacion(),
            'documento_emitido_id' => $actuacion->obtenerDocumentoEmitidoId(),
            'archivo_adjunto_path' => $actuacion->obtenerArchivoAdjuntoPath(),
        ]);

        $nuevoId = (int) $this->pdo->lastInsertId();

        return $this->buscarPorId($nuevoId) ?? $actuacion;
    }

    /**
     * Busca una actuación específica por su ID.
     */
    public function buscarPorId(int $id): ?ReclamacionActuacion
    {
        $sql = 'SELECT ra.*, a.nombre AS nombre_actor
                FROM reclamacion_actuaciones ra
                LEFT JOIN actores a ON a.id = ra.actor_id
                WHERE ra.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ReclamacionActuacion::desdeArreglo($fila) : null;
    }

    /**
     * Lista cronológicamente todas las actuaciones de un expediente.
     *
     * @param int $reclamacionId
     * @return array<ReclamacionActuacion>
     */
    public function listarPorReclamacionId(int $reclamacionId): array
    {
        $sql = 'SELECT ra.*, a.nombre AS nombre_actor
                FROM reclamacion_actuaciones ra
                LEFT JOIN actores a ON a.id = ra.actor_id
                WHERE ra.reclamacion_id = :reclamacion_id
                ORDER BY ra.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reclamacion_id' => $reclamacionId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $f) => ReclamacionActuacion::desdeArreglo($f), $filas);
    }
}
