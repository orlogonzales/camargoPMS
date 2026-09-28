<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\TipoUnidad;
use PDO;

/**
 * Repositorio para la persistencia y consulta del catálogo de tipos de unidad.
 */
class TipoUnidadRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \CamargoPMS\Nucleo\BaseDatos::conexion();
    }

    /**
     * Busca un tipo de unidad por su ID.
     */
    public function buscarPorId(int $id): ?TipoUnidad
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tipos_unidad WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? TipoUnidad::desdeArreglo($fila) : null;
    }

    /**
     * Busca un tipo de unidad por su código único.
     */
    public function buscarPorCodigo(string $codigo): ?TipoUnidad
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tipos_unidad WHERE codigo = :codigo LIMIT 1');
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? TipoUnidad::desdeArreglo($fila) : null;
    }

    /**
     * Verifica si existe un tipo de unidad activo por ID.
     */
    public function existe(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tipos_unidad WHERE id = :id AND activo = 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Lista todos los tipos de unidad activos para selectores.
     *
     * @return array<TipoUnidad>
     */
    public function listarActivos(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tipos_unidad WHERE activo = 1 ORDER BY nombre ASC');
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $f): TipoUnidad => TipoUnidad::desdeArreglo($f), $filas);
    }

    /**
     * Lista el catálogo completo de tipos de unidad.
     *
     * @return array<TipoUnidad>
     */
    public function listarTodos(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tipos_unidad ORDER BY id ASC');
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $f): TipoUnidad => TipoUnidad::desdeArreglo($f), $filas);
    }
}
