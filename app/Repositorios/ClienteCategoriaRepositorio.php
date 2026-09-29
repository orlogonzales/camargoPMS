<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ClienteCategoria;
use PDO;

/**
 * Repositorio de persistencia para Categorías Comerciales de Cliente.
 */
class ClienteCategoriaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function buscarPorId(int $id): ?ClienteCategoria
    {
        $sql = "SELECT * FROM cliente_categorias WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ClienteCategoria::desdeArreglo($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?ClienteCategoria
    {
        $sql = "SELECT * FROM cliente_categorias WHERE UPPER(codigo) = UPPER(:codigo) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => trim($codigo)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ClienteCategoria::desdeArreglo($fila) : null;
    }

    public function obtenerPredeterminada(): ?ClienteCategoria
    {
        $sql = "SELECT * FROM cliente_categorias WHERE es_predeterminada = 1 AND activo = 1 ORDER BY id ASC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            // Fallback a ESTANDAR
            return $this->buscarPorCodigo(ClienteCategoria::CODIGO_ESTANDAR);
        }

        return ClienteCategoria::desdeArreglo($fila);
    }

    /**
     * @param bool $soloActivos
     * @return array<int, ClienteCategoria>
     */
    public function listar(bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM cliente_categorias";
        if ($soloActivos) {
            $sql .= " WHERE activo = 1";
        }
        $sql .= " ORDER BY es_predeterminada DESC, id ASC";

        $stmt = $this->pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = ClienteCategoria::desdeArreglo($fila);
        }

        return $resultado;
    }

    public function insertar(ClienteCategoria $cat): int
    {
        $sql = "INSERT INTO cliente_categorias (codigo, nombre, descripcion, color_badge, es_predeterminada, activo)
                VALUES (:codigo, :nombre, :descripcion, :color_badge, :es_predeterminada, :activo)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'codigo' => $cat->obtenerCodigo(),
            'nombre' => $cat->obtenerNombre(),
            'descripcion' => $cat->obtenerDescripcion(),
            'color_badge' => $cat->obtenerColorBadge(),
            'es_predeterminada' => $cat->esPredeterminada() ? 1 : 0,
            'activo' => $cat->estaActivo() ? 1 : 0,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $cat->fijarId($id);

        return $id;
    }

    public function actualizar(ClienteCategoria $cat): bool
    {
        if ($cat->obtenerId() === null) {
            return false;
        }

        $sql = "UPDATE cliente_categorias
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    color_badge = :color_badge,
                    es_predeterminada = :es_predeterminada,
                    activo = :activo
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $cat->obtenerId(),
            'nombre' => $cat->obtenerNombre(),
            'descripcion' => $cat->obtenerDescripcion(),
            'color_badge' => $cat->obtenerColorBadge(),
            'es_predeterminada' => $cat->esPredeterminada() ? 1 : 0,
            'activo' => $cat->estaActivo() ? 1 : 0,
        ]);
    }
}
