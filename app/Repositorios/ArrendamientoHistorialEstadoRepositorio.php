<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ArrendamientoHistorialEstado;
use PDO;

/**
 * Repositorio para la auditoría y trazabilidad histórica inmutable de estados de arrendamiento.
 */
class ArrendamientoHistorialEstadoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function registrar(ArrendamientoHistorialEstado $historial): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO arrendamiento_historial_estados (
                arrendamiento_id, estado_anterior, estado_nuevo, motivo, actor_id, cambiado_en
            ) VALUES (
                :arrendamiento_id, :estado_anterior, :estado_nuevo, :motivo, :actor_id, NOW()
            )'
        );

        $stmt->execute([
            'arrendamiento_id' => $historial->obtenerArrendamientoId(),
            'estado_anterior' => $historial->obtenerEstadoAnterior(),
            'estado_nuevo' => $historial->obtenerEstadoNuevo(),
            'motivo' => $historial->obtenerMotivo(),
            'actor_id' => $historial->obtenerActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<ArrendamientoHistorialEstado>
     */
    public function listarPorArrendamientoId(int $arrendamientoId): array
    {
        $sql = 'SELECT 
                    ahe.*,
                    act.nombre AS actor_nombre,
                    act.tipo AS actor_tipo
                FROM arrendamiento_historial_estados ahe
                INNER JOIN actores act ON act.id = ahe.actor_id
                WHERE ahe.arrendamiento_id = :arrendamiento_id
                ORDER BY ahe.cambiado_en ASC, ahe.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = ArrendamientoHistorialEstado::desdeArreglo($fila);
        }

        return $resultado;
    }
}
