<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\AmbitoTarifaAlojamiento;
use CamargoPMS\Modelos\TarifaAlojamiento;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para el Catálogo Soberano de Tarifas de Alojamiento.
 */
class TarifaAlojamientoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?TarifaAlojamiento
    {
        $sql = 'SELECT * FROM tarifas_alojamiento WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function crearTarifa(TarifaAlojamiento $tarifa): int
    {
        $sql = 'INSERT INTO tarifas_alojamiento (
                    propiedad_id, ambito_tipo, tipo_unidad_id, unidad_id, nombre,
                    moneda_codigo, precio_noche, vigencia_desde, vigencia_hasta,
                    estado, creado_por_actor_id, creado_en
                ) VALUES (
                    :propiedad_id, :ambito_tipo, :tipo_unidad_id, :unidad_id, :nombre,
                    :moneda_codigo, :precio_noche, :vigencia_desde, :vigencia_hasta,
                    :estado, :creado_por_actor_id, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $tarifa->obtenerPropiedadId(),
            'ambito_tipo' => $tarifa->obtenerAmbitoTipo(),
            'tipo_unidad_id' => $tarifa->obtenerTipoUnidadId(),
            'unidad_id' => $tarifa->obtenerUnidadId(),
            'nombre' => $tarifa->obtenerNombre(),
            'moneda_codigo' => $tarifa->obtenerMonedaCodigo(),
            'precio_noche' => $tarifa->obtenerPrecioNoche(),
            'vigencia_desde' => $tarifa->obtenerVigenciaDesde(),
            'vigencia_hasta' => $tarifa->obtenerVigenciaHasta(),
            'estado' => $tarifa->obtenerEstado(),
            'creado_por_actor_id' => $tarifa->obtenerCreadoPorActorId(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarTarifa(TarifaAlojamiento $tarifa): bool
    {
        $sql = 'UPDATE tarifas_alojamiento SET
                    propiedad_id = :propiedad_id,
                    ambito_tipo = :ambito_tipo,
                    tipo_unidad_id = :tipo_unidad_id,
                    unidad_id = :unidad_id,
                    nombre = :nombre,
                    moneda_codigo = :moneda_codigo,
                    precio_noche = :precio_noche,
                    vigencia_desde = :vigencia_desde,
                    vigencia_hasta = :vigencia_hasta,
                    estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $tarifa->obtenerId(),
            'propiedad_id' => $tarifa->obtenerPropiedadId(),
            'ambito_tipo' => $tarifa->obtenerAmbitoTipo(),
            'tipo_unidad_id' => $tarifa->obtenerTipoUnidadId(),
            'unidad_id' => $tarifa->obtenerUnidadId(),
            'nombre' => $tarifa->obtenerNombre(),
            'moneda_codigo' => $tarifa->obtenerMonedaCodigo(),
            'precio_noche' => $tarifa->obtenerPrecioNoche(),
            'vigencia_desde' => $tarifa->obtenerVigenciaDesde(),
            'vigencia_hasta' => $tarifa->obtenerVigenciaHasta(),
            'estado' => $tarifa->obtenerEstado(),
        ]);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $sql = 'UPDATE tarifas_alojamiento SET estado = :estado, actualizado_en = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'estado' => $estado,
        ]);
    }

    /**
     * Bloquea pesimistamente las tarifas activas existentes dentro de un ámbito exacto para validación anti-solapamiento.
     *
     * @return array<int, TarifaAlojamiento>
     */
    public function bloquearTarifasAmbitoParaValidacion(
        int $propiedadId,
        string $ambito,
        ?int $tipoUnidadId,
        ?int $unidadId,
        ?int $excluirTarifaId = null
    ): array {
        $sql = "SELECT * FROM tarifas_alojamiento
                WHERE propiedad_id = :propiedad_id
                  AND ambito_tipo = :ambito_tipo
                  AND estado = 'ACTIVO'
                  AND (tipo_unidad_id <=> :tipo_unidad_id)
                  AND (unidad_id <=> :unidad_id)";

        $params = [
            'propiedad_id' => $propiedadId,
            'ambito_tipo' => $ambito,
            'tipo_unidad_id' => $tipoUnidadId,
            'unidad_id' => $unidadId,
        ];

        if ($excluirTarifaId !== null) {
            $sql .= ' AND id != :excluir_id';
            $params['excluir_id'] = $excluirTarifaId;
        }

        $sql .= ' FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * Busca las tarifas candidatas activas vigentes para una fecha dada, ordenadas por jerarquía estricta:
     * UNIDAD (1) > TIPO_UNIDAD (2) > PROPIEDAD (3).
     *
     * @return array<int, TarifaAlojamiento>
     */
    public function buscarTarifasCandidatasEnFecha(
        int $propiedadId,
        ?int $tipoUnidadId,
        ?int $unidadId,
        string $fecha
    ): array {
        $sql = "SELECT * FROM tarifas_alojamiento
                WHERE propiedad_id = :propiedad_id
                  AND estado = 'ACTIVO'
                  AND vigencia_desde <= :fecha_desde
                  AND (vigencia_hasta IS NULL OR vigencia_hasta >= :fecha_hasta)
                  AND (
                      (ambito_tipo = 'UNIDAD' AND unidad_id = :unidad_id)
                      OR
                      (ambito_tipo = 'TIPO_UNIDAD' AND tipo_unidad_id = :tipo_unidad_id AND unidad_id IS NULL)
                      OR
                      (ambito_tipo = 'PROPIEDAD' AND tipo_unidad_id IS NULL AND unidad_id IS NULL)
                  )
                ORDER BY
                  CASE ambito_tipo
                    WHEN 'UNIDAD' THEN 1
                    WHEN 'TIPO_UNIDAD' THEN 2
                    WHEN 'PROPIEDAD' THEN 3
                    ELSE 4
                  END ASC,
                  vigencia_desde DESC,
                  id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'propiedad_id' => $propiedadId,
            'fecha_desde' => $fecha,
            'fecha_hasta' => $fecha,
            'unidad_id' => $unidadId,
            'tipo_unidad_id' => $tipoUnidadId,
        ]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @return array<int, TarifaAlojamiento>
     */
    public function listarPorPropiedad(int $propiedadId, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM tarifas_alojamiento WHERE propiedad_id = :propiedad_id';
        $params = ['propiedad_id' => $propiedadId];

        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY vigencia_desde DESC, id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @param array<string, mixed> $fila
     */
    public function mapearFila(array $fila): TarifaAlojamiento
    {
        return TarifaAlojamiento::desdeArreglo($fila);
    }
}
