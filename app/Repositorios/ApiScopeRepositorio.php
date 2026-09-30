<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ApiScope;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para el Catálogo de Scopes API.
 */
class ApiScopeRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?ApiScope
    {
        $sql = 'SELECT * FROM api_scopes WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?ApiScope
    {
        $sql = 'SELECT * FROM api_scopes WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => trim($codigo)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * @param array<int, string> $codigos
     * @return array<int, ApiScope>
     */
    public function buscarPorCodigos(array $codigos): array
    {
        if (empty($codigos)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $sql = "SELECT * FROM api_scopes WHERE codigo IN ({$placeholders}) AND activo = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($codigos));
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @return array<int, ApiScope>
     */
    public function listarActivos(): array
    {
        $sql = 'SELECT * FROM api_scopes WHERE activo = 1 ORDER BY modulo ASC, codigo ASC';
        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * Obtiene la lista de códigos de scopes asociados a una credencial.
     *
     * @return array<int, string>
     */
    public function listarCodigosPorCredencialId(int $credencialId): array
    {
        $sql = 'SELECT s.codigo
                FROM api_scopes s
                INNER JOIN api_credencial_scopes cs ON cs.api_scope_id = s.id
                WHERE cs.api_credencial_id = :credencial_id AND s.activo = 1
                ORDER BY s.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['credencial_id' => $credencialId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param array<string, mixed> $fila
     */
    public function mapearFila(array $fila): ApiScope
    {
        return ApiScope::desdeArreglo($fila);
    }
}
