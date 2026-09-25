<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Cargo;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para el catálogo de cargos laborales.
 *
 * Principio Vinculante: CARGO LABORAL ≠ ROL DEL SISTEMA.
 */
class CargoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Busca un cargo por su ID primario.
     *
     * @param int $id
     * @return Cargo|null
     */
    public function buscarPorId(int $id): ?Cargo
    {
        $sql = "SELECT * FROM cargos WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Cargo::desdeArreglo($fila) : null;
    }

    /**
     * Busca un cargo por su código único normalizado (ej. 'ADMINISTRADOR', 'RECEPCIONISTA').
     *
     * @param string $codigo
     * @return Cargo|null
     */
    public function buscarPorCodigo(string $codigo): ?Cargo
    {
        $sql = "SELECT * FROM cargos WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Cargo::desdeArreglo($fila) : null;
    }

    /**
     * Alias explícito para cumplimiento de especificación.
     *
     * @param string $codigo
     * @return Cargo|null
     */
    public function buscarCargoPorCodigo(string $codigo): ?Cargo
    {
        return $this->buscarPorCodigo($codigo);
    }

    /**
     * Lista todos los cargos activos en el sistema.
     *
     * @return array<int, Cargo>
     */
    public function listarActivos(): array
    {
        $sql = "SELECT * FROM cargos WHERE activo = 1 ORDER BY nombre ASC";
        $stmt = $this->pdo->query($sql);

        $cargos = [];
        while ($fila = $stmt->fetch()) {
            $cargos[] = Cargo::desdeArreglo($fila);
        }

        return $cargos;
    }

    /**
     * Alias explícito para cumplimiento de especificación.
     *
     * @return array<int, Cargo>
     */
    public function listarCargosActivos(): array
    {
        return $this->listarActivos();
    }

    /**
     * Inserta un nuevo cargo en el catálogo.
     *
     * @param Cargo $cargo
     * @return Cargo
     */
    public function insertar(Cargo $cargo): Cargo
    {
        $sql = "INSERT INTO cargos (
                    codigo,
                    nombre,
                    descripcion,
                    activo,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :codigo,
                    :nombre,
                    :descripcion,
                    :activo,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $cargo->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $cargo->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $cargo->obtenerDescripcion(), PDO::PARAM_STR);
        $stmt->bindValue(':activo', $cargo->esActivo() ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId) ?? $cargo;
    }
}
