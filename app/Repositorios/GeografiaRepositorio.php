<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use PDO;

/**
 * Repositorio de persistencia y consultas para la jerarquía territorial (INEI UBIGEO).
 */
class GeografiaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lista los departamentos activos de un país (por defecto Perú, pais_id = 1).
     *
     * @param int|null $paisId
     * @return array<int, array<string, mixed>>
     */
    public function listarDepartamentos(?int $paisId = null): array
    {
        $sql = "SELECT id, pais_id, codigo_ubigeo, nombre, activo
                FROM departamentos
                WHERE activo = 1";
        $params = [];

        if ($paisId !== null) {
            $sql .= " AND pais_id = :pais_id";
            $params[':pais_id'] = $paisId;
        }

        $sql .= " ORDER BY nombre ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista las provincias activas que pertenecen a un departamento.
     *
     * @param int $departamentoId
     * @return array<int, array<string, mixed>>
     */
    public function listarProvincias(int $departamentoId): array
    {
        $sql = "SELECT id, departamento_id, codigo_ubigeo, nombre, activo
                FROM provincias
                WHERE departamento_id = :departamento_id AND activo = 1
                ORDER BY nombre ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':departamento_id' => $departamentoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista los distritos activos que pertenecen a una provincia.
     *
     * @param int $provinciaId
     * @return array<int, array<string, mixed>>
     */
    public function listarDistritos(int $provinciaId): array
    {
        $sql = "SELECT id, provincia_id, codigo_ubigeo, nombre, activo
                FROM distritos
                WHERE provincia_id = :provincia_id AND activo = 1
                ORDER BY nombre ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':provincia_id' => $provinciaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los metadatos completos y la jerarquía territorial a partir de un distrito_id.
     *
     * @param int $distritoId
     * @return array<string, mixed>|null
     */
    public function buscarJerarquiaPorDistritoId(int $distritoId): ?array
    {
        $sql = "SELECT
                    d.id AS distrito_id,
                    d.nombre AS distrito_nombre,
                    d.codigo_ubigeo AS distrito_codigo_ubigeo,
                    p.id AS provincia_id,
                    p.nombre AS provincia_nombre,
                    p.codigo_ubigeo AS provincia_codigo_ubigeo,
                    dep.id AS departamento_id,
                    dep.nombre AS departamento_nombre,
                    dep.codigo_ubigeo AS departamento_codigo_ubigeo,
                    pa.id AS pais_id,
                    pa.codigo_iso2 AS pais_codigo_iso2,
                    pa.nombre AS pais_nombre
                FROM distritos d
                INNER JOIN provincias p ON p.id = d.provincia_id
                INNER JOIN departamentos dep ON dep.id = p.departamento_id
                INNER JOIN paises pa ON pa.id = dep.pais_id
                WHERE d.id = :distrito_id AND d.activo = 1
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':distrito_id' => $distritoId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Valida la consistencia relacional estricta de una combinación territorial:
     * El distrito debe pertenecer a la provincia dada, y la provincia al departamento dado.
     *
     * @param int $distritoId
     * @param int|null $provinciaId
     * @param int|null $departamentoId
     * @param int|null $paisId
     * @return bool
     */
    public function validarConsistenciaJerarquia(
        int $distritoId,
        ?int $provinciaId = null,
        ?int $departamentoId = null,
        ?int $paisId = null
    ): bool {
        $jerarquia = $this->buscarJerarquiaPorDistritoId($distritoId);
        if ($jerarquia === null) {
            return false;
        }

        if ($provinciaId !== null && (int) $jerarquia['provincia_id'] !== $provinciaId) {
            return false;
        }

        if ($departamentoId !== null && (int) $jerarquia['departamento_id'] !== $departamentoId) {
            return false;
        }

        if ($paisId !== null && (int) $jerarquia['pais_id'] !== $paisId) {
            return false;
        }

        return true;
    }

    /**
     * Busca un país por su código ISO-2 (ej. 'PE' o 'US') de forma dinámica.
     *
     * @param string $codigoIso2
     * @return array<string, mixed>|null
     */
    public function buscarPaisPorCodigo(string $codigoIso2): ?array
    {
        $sql = "SELECT id, codigo_iso2, codigo_iso3, nombre, nacionalidad, activo
                FROM paises
                WHERE codigo_iso2 = :codigo AND activo = 1
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':codigo' => strtoupper(trim($codigoIso2))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Busca un país por su ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarPaisPorId(int $id): ?array
    {
        $sql = "SELECT id, codigo_iso2, codigo_iso3, nombre, nacionalidad, activo
                FROM paises
                WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}
