<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios\CPE;

use CamargoPMS\Modelos\CPE\CpeSerie;
use PDO;

/**
 * Repositorio soberano para la gestión de series fiscales y control de numeración correlativa concurrente.
 * Cumple con la regla vinculante: jamás ejecuta commit() o rollBack() de forma autónoma.
 */
class CpeSerieRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Obtiene una serie por su ID de clave primaria.
     * Permite bloqueo pesimista en sección crítica (SELECT ... FOR UPDATE).
     */
    public function obtenerPorId(int $id, bool $bloquear = false): ?CpeSerie
    {
        $sql = 'SELECT * FROM cpe_series WHERE id = :id' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return CpeSerie::desdeArreglo($fila);
    }

    /**
     * Busca una serie fiscal específica por establecimiento, tipo de comprobante y nombre de serie.
     */
    public function buscarPorEstablecimientoYTipo(
        int $establecimientoId,
        string $tipoComprobante,
        string $serie,
        bool $bloquear = false
    ): ?CpeSerie {
        $sql = 'SELECT * FROM cpe_series
                WHERE emisor_establecimiento_id = :estab_id
                  AND tipo_comprobante = :tipo
                  AND serie = :serie' . ($bloquear ? ' FOR UPDATE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'estab_id' => $establecimientoId,
            'tipo' => $tipoComprobante,
            'serie' => $serie,
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return CpeSerie::desdeArreglo($fila);
    }

    /**
     * Incrementa atómicamente el último correlativo de una serie.
     * Debe ser ejecutado habiendo adquirido previamente el bloqueo de la fila en la misma transacción.
     */
    public function incrementarCorrelativo(int $serieId, int $nuevoCorrelativo): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cpe_series
             SET ultimo_correlativo = :nuevo, actualizado_en = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'nuevo' => $nuevoCorrelativo,
            'id' => $serieId,
        ]);
    }

    /**
     * Lista todas las series asociadas a un establecimiento fiscal emisor.
     *
     * @return array<CpeSerie>
     */
    public function listarPorEstablecimiento(int $establecimientoId, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM cpe_series WHERE emisor_establecimiento_id = :estab_id';
        $params = ['estab_id' => $establecimientoId];

        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY tipo_comprobante ASC, serie ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $series = [];
        foreach ($filas as $fila) {
            $series[] = CpeSerie::desdeArreglo($fila);
        }

        return $series;
    }

    /**
     * Registra una nueva serie fiscal en el sistema.
     */
    public function crear(CpeSerie $serie): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cpe_series (
                emisor_establecimiento_id, tipo_comprobante, serie, ultimo_correlativo,
                prefijo_tipo, descripcion, estado, creado_en, actualizado_en
            ) VALUES (
                :emisor_establecimiento_id, :tipo_comprobante, :serie, :ultimo_correlativo,
                :prefijo_tipo, :descripcion, :estado, NOW(), NOW()
            )'
        );

        $stmt->execute([
            'emisor_establecimiento_id' => $serie->obtenerEmisorEstablecimientoId(),
            'tipo_comprobante' => $serie->obtenerTipoComprobante(),
            'serie' => $serie->obtenerSerie(),
            'ultimo_correlativo' => $serie->obtenerUltimoCorrelativo(),
            'prefijo_tipo' => $serie->obtenerPrefijoTipo(),
            'descripcion' => $serie->obtenerDescripcion(),
            'estado' => $serie->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
