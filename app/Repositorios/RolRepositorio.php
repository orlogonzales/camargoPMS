<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Rol;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para roles de usuario (tabla `roles`).
 *
 * Principio Vinculante: ROL DE AUTORIZACIÓN ≠ CARGO LABORAL.
 */
class RolRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Busca un rol por su ID primario.
     */
    public function buscarPorId(int $id): ?Rol
    {
        $sql = 'SELECT * FROM roles WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Rol::hidratar($fila) : null;
    }

    /**
     * Busca un rol por su código técnico único normalizado (ej. 'SUPERADMINISTRADOR', 'RECEPCION').
     */
    public function buscarPorCodigo(string $codigo): ?Rol
    {
        $sql = 'SELECT * FROM roles WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Rol::hidratar($fila) : null;
    }

    /**
     * Alias semántico para buscarPorCodigo.
     */
    public function buscarPorClave(string $clave): ?Rol
    {
        return $this->buscarPorCodigo($clave);
    }

    /**
     * Lista todos los roles del sistema ordenados por jerarquía estructural y nombre.
     *
     * @return array<int, Rol>
     */
    public function listarTodos(): array
    {
        $sql = 'SELECT * FROM roles ORDER BY es_superadministrador DESC, nombre ASC';
        $stmt = $this->pdo->query($sql);

        $roles = [];
        while ($fila = $stmt->fetch()) {
            $roles[] = Rol::hidratar($fila);
        }

        return $roles;
    }

    /**
     * Lista únicamente los roles en estado ACTIVO.
     *
     * @return array<int, Rol>
     */
    public function listarActivos(): array
    {
        $sql = "SELECT * FROM roles WHERE estado = 'ACTIVO' ORDER BY es_superadministrador DESC, nombre ASC";
        $stmt = $this->pdo->query($sql);

        $roles = [];
        while ($fila = $stmt->fetch()) {
            $roles[] = Rol::hidratar($fila);
        }

        return $roles;
    }

    /**
     * Inserta un nuevo rol en la base de datos.
     */
    public function insertar(Rol $rol): Rol
    {
        $sql = 'INSERT INTO roles (
                    codigo,
                    nombre,
                    descripcion,
                    es_sistema,
                    es_superadministrador,
                    estado,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :codigo,
                    :nombre,
                    :descripcion,
                    :es_sistema,
                    :es_superadministrador,
                    :estado,
                    NOW(),
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $rol->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $rol->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $rol->obtenerDescripcion(), $rol->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':es_sistema', $rol->esSistema() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':es_superadministrador', $rol->esSuperadministrador() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $rol->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId) ?? $rol;
    }

    /**
     * Actualiza los datos de un rol existente.
     */
    public function actualizar(Rol $rol): bool
    {
        $sql = 'UPDATE roles
                SET codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $rol->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $rol->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $rol->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $rol->obtenerDescripcion(), $rol->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $rol->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina físicamente un rol si no tiene dependencias.
     */
    public function eliminar(int $id): bool
    {
        $sql = 'DELETE FROM roles WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
