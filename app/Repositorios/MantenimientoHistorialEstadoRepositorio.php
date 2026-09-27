<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\MantenimientoHistorialEstado;
use PDO;

/**
 * Repositorio para la bitácora inmutable de cambios de estado en mantenimiento e incidencias (D-061 / D-077).
 */
class MantenimientoHistorialEstadoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function registrar(MantenimientoHistorialEstado $historial): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mantenimiento_historial_estados (
                entidad_tipo, entidad_id, estado_anterior, estado_nuevo, motivo, actor_id, cambiado_en
            ) VALUES (
                :entidad_tipo, :entidad_id, :estado_anterior, :estado_nuevo, :motivo, :actor_id, NOW()
            )'
        );

        $stmt->execute([
            'entidad_tipo' => $historial->obtenerEntidadTipo(),
            'entidad_id' => $historial->obtenerEntidadId(),
            'estado_anterior' => $historial->obtenerEstadoAnterior(),
            'estado_nuevo' => $historial->obtenerEstadoNuevo(),
            'motivo' => $historial->obtenerMotivo(),
            'actor_id' => $historial->obtenerActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, MantenimientoHistorialEstado>
     */
    public function obtenerPorEntidad(string $entidadTipo, int $entidadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT 
                h.*,
                act.nombre AS actor_nombre
             FROM mantenimiento_historial_estados h
             INNER JOIN actores act ON act.id = h.actor_id
             WHERE h.entidad_tipo = :entidad_tipo AND h.entidad_id = :entidad_id
             ORDER BY h.id ASC'
        );

        $stmt->execute([
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
        ]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];

        foreach ($filas as $fila) {
            $resultado[] = MantenimientoHistorialEstado::desdeArreglo($fila);
        }

        return $resultado;
    }
}
