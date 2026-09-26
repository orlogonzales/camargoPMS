<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ActorAuditoria;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para la entidad ActorAuditoria (tabla `actores`).
 */
class ActorAuditoriaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Busca un actor por su identificador primario.
     *
     * @param int $id
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria|null
     */
    public function buscarPorId(int $id, ?PDO $pdoTransaccional = null): ?ActorAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM actores WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Busca un actor por su código único estable (ej. 'CAMARGO_PMS', 'USR_1').
     *
     * @param string $codigo
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria|null
     */
    public function buscarPorCodigo(string $codigo, ?PDO $pdoTransaccional = null): ?ActorAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM actores WHERE codigo = :codigo LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim($codigo), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Busca el actor humano vinculado a una cuenta de usuario específica.
     *
     * @param int $usuarioId
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria|null
     */
    public function buscarPorUsuarioId(int $usuarioId, ?PDO $pdoTransaccional = null): ?ActorAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM actores WHERE usuario_id = :usuario_id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Verifica si un código de actor ya se encuentra registrado.
     *
     * @param string $codigo
     * @param int|null $excluirId
     * @param PDO|null $pdoTransaccional
     * @return bool
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null, ?PDO $pdoTransaccional = null): bool
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT 1 FROM actores WHERE codigo = :codigo';
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':codigo', trim($codigo), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Inserta un nuevo actor en la base de datos.
     *
     * @param ActorAuditoria $actor
     * @param PDO|null $pdoTransaccional
     * @return ActorAuditoria
     */
    public function insertar(ActorAuditoria $actor, ?PDO $pdoTransaccional = null): ActorAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'INSERT INTO actores (tipo, codigo, nombre, usuario_id, estado)
                VALUES (:tipo, :codigo, :nombre, :usuario_id, :estado)';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo', $actor->obtenerTipo(), PDO::PARAM_STR);
        $stmt->bindValue(':codigo', $actor->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $actor->obtenerNombre(), PDO::PARAM_STR);
        if ($actor->obtenerUsuarioId() !== null) {
            $stmt->bindValue(':usuario_id', $actor->obtenerUsuarioId(), PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':usuario_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':estado', $actor->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $id = (int) $pdo->lastInsertId();
        return $this->buscarPorId($id, $pdo) ?? new ActorAuditoria(
            $id,
            $actor->obtenerTipo(),
            $actor->obtenerCodigo(),
            $actor->obtenerNombre(),
            $actor->obtenerUsuarioId(),
            $actor->obtenerEstado()
        );
    }

    /**
     * Actualiza los datos de un actor existente.
     *
     * @param ActorAuditoria $actor
     * @param PDO|null $pdoTransaccional
     * @return bool
     */
    public function actualizar(ActorAuditoria $actor, ?PDO $pdoTransaccional = null): bool
    {
        if ($actor->obtenerId() === null) {
            return false;
        }

        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'UPDATE actores 
                SET tipo = :tipo, codigo = :codigo, nombre = :nombre, usuario_id = :usuario_id, estado = :estado
                WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $actor->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $actor->obtenerTipo(), PDO::PARAM_STR);
        $stmt->bindValue(':codigo', $actor->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $actor->obtenerNombre(), PDO::PARAM_STR);
        if ($actor->obtenerUsuarioId() !== null) {
            $stmt->bindValue(':usuario_id', $actor->obtenerUsuarioId(), PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':usuario_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':estado', $actor->obtenerEstado(), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Hidrata una fila de base de datos a una entidad ActorAuditoria.
     *
     * @param array<string, mixed> $fila
     * @return ActorAuditoria
     */
    private function hidratar(array $fila): ActorAuditoria
    {
        return new ActorAuditoria(
            (int) $fila['id'],
            (string) $fila['tipo'],
            (string) $fila['codigo'],
            (string) $fila['nombre'],
            $fila['usuario_id'] !== null ? (int) $fila['usuario_id'] : null,
            (string) $fila['estado'],
            (string) $fila['creado_en'],
            isset($fila['actualizado_en']) && $fila['actualizado_en'] !== null ? (string) $fila['actualizado_en'] : null
        );
    }
}
