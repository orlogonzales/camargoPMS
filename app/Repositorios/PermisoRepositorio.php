<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Permiso;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para permisos atómicos del sistema (tabla `permisos`).
 */
class PermisoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Busca un permiso por su ID primario.
     */
    public function buscarPorId(int $id): ?Permiso
    {
        $sql = 'SELECT * FROM permisos WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Permiso::hidratar($fila) : null;
    }

    /**
     * Busca un permiso por su código atómico normalizado (ej. 'usuarios.ver').
     */
    public function buscarPorCodigo(string $codigo): ?Permiso
    {
        $sql = 'SELECT * FROM permisos WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', strtolower(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        return $fila ? Permiso::hidratar($fila) : null;
    }

    /**
     * Alias semántico para buscarPorCodigo.
     */
    public function buscarPorClave(string $clave): ?Permiso
    {
        return $this->buscarPorCodigo($clave);
    }

    /**
     * Lista todos los permisos ordenados por módulo y código.
     *
     * @return array<int, Permiso>
     */
    public function listarTodos(): array
    {
        $sql = 'SELECT * FROM permisos ORDER BY modulo ASC, codigo ASC';
        $stmt = $this->pdo->query($sql);

        $permisos = [];
        while ($fila = $stmt->fetch()) {
            $permisos[] = Permiso::hidratar($fila);
        }

        return $permisos;
    }

    /**
     * Lista permisos correspondientes a un módulo específico.
     *
     * @param string $modulo
     * @return array<int, Permiso>
     */
    public function listarPorModulo(string $modulo): array
    {
        $sql = 'SELECT * FROM permisos WHERE modulo = :modulo ORDER BY codigo ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':modulo', strtolower(trim($modulo)), PDO::PARAM_STR);
        $stmt->execute();

        $permisos = [];
        while ($fila = $stmt->fetch()) {
            $permisos[] = Permiso::hidratar($fila);
        }

        return $permisos;
    }

    /**
     * Inserta un nuevo permiso en la base de datos.
     */
    public function insertar(Permiso $permiso): Permiso
    {
        $sql = 'INSERT INTO permisos (
                    codigo,
                    nombre,
                    descripcion,
                    modulo,
                    estado,
                    es_sistema,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :codigo,
                    :nombre,
                    :descripcion,
                    :modulo,
                    :estado,
                    :es_sistema,
                    NOW(),
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo', $permiso->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $permiso->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $permiso->obtenerDescripcion(), $permiso->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':modulo', $permiso->obtenerModulo(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $permiso->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':es_sistema', $permiso->esSistema() ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId) ?? $permiso;
    }

    /**
     * Obtiene todos los permisos asociados a un rol específico.
     *
     * @param int $rolId
     * @return array<int, Permiso>
     */
    public function buscarPorRolId(int $rolId): array
    {
        $sql = 'SELECT p.* 
                FROM permisos p
                JOIN roles_permisos rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = :rol_id
                ORDER BY p.modulo ASC, p.codigo ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();

        $permisos = [];
        while ($fila = $stmt->fetch()) {
            $permisos[] = Permiso::hidratar($fila);
        }

        return $permisos;
    }
}
