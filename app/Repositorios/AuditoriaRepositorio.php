<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Excepciones\AuditoriaExcepcion;
use CamargoPMS\Modelos\RegistroAuditoria;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;
use Throwable;

/**
 * Repositorio de persistencia inmutable para el registro histórico de auditoría (tabla `auditoria`).
 *
 * Principio de Inmutabilidad:
 * Este repositorio expone exclusivamente operaciones de inserción y consulta histórica.
 * No contiene ni permite métodos de actualización ni eliminación de registros de auditoría.
 */
class AuditoriaRepositorio
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
     * Inserta un nuevo registro inmutable de auditoría en la base de datos.
     *
     * @param RegistroAuditoria $registro
     * @param PDO|null $pdoTransaccional
     * @return RegistroAuditoria
     * @throws AuditoriaExcepcion
     */
    public function insertar(RegistroAuditoria $registro, ?PDO $pdoTransaccional = null): RegistroAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;

        $sql = 'INSERT INTO auditoria (
                    actor_id, usuario_id, accion, modulo, entidad, entidad_id,
                    descripcion, valores_anteriores, valores_nuevos, contexto,
                    ip, user_agent, correlacion_id
                ) VALUES (
                    :actor_id, :usuario_id, :accion, :modulo, :entidad, :entidad_id,
                    :descripcion, :valores_anteriores, :valores_nuevos, :contexto,
                    :ip, :user_agent, :correlacion_id
                )';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':actor_id', $registro->obtenerActorId(), PDO::PARAM_INT);
            if ($registro->obtenerUsuarioId() !== null) {
                $stmt->bindValue(':usuario_id', $registro->obtenerUsuarioId(), PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':usuario_id', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':accion', $registro->obtenerAccion(), PDO::PARAM_STR);
            $stmt->bindValue(':modulo', $registro->obtenerModulo(), PDO::PARAM_STR);
            $stmt->bindValue(':entidad', $registro->obtenerEntidad(), PDO::PARAM_STR);

            if ($registro->obtenerEntidadId() !== null) {
                $stmt->bindValue(':entidad_id', $registro->obtenerEntidadId(), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':entidad_id', null, PDO::PARAM_NULL);
            }

            if ($registro->obtenerDescripcion() !== null) {
                $stmt->bindValue(':descripcion', $registro->obtenerDescripcion(), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':descripcion', null, PDO::PARAM_NULL);
            }

            $ant = $registro->obtenerValoresAnteriores();
            $stmt->bindValue(
                ':valores_anteriores',
                $ant !== null ? json_encode($ant, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $ant !== null ? PDO::PARAM_STR : PDO::PARAM_NULL
            );

            $nue = $registro->obtenerValoresNuevos();
            $stmt->bindValue(
                ':valores_nuevos',
                $nue !== null ? json_encode($nue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $nue !== null ? PDO::PARAM_STR : PDO::PARAM_NULL
            );

            $ctx = $registro->obtenerContexto();
            $stmt->bindValue(
                ':contexto',
                $ctx !== null ? json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $ctx !== null ? PDO::PARAM_STR : PDO::PARAM_NULL
            );

            if ($registro->obtenerIp() !== null) {
                $stmt->bindValue(':ip', $registro->obtenerIp(), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':ip', null, PDO::PARAM_NULL);
            }

            if ($registro->obtenerUserAgent() !== null) {
                $stmt->bindValue(':user_agent', $registro->obtenerUserAgent(), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':user_agent', null, PDO::PARAM_NULL);
            }

            if ($registro->obtenerCorrelacionId() !== null) {
                $stmt->bindValue(':correlacion_id', $registro->obtenerCorrelacionId(), PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':correlacion_id', null, PDO::PARAM_NULL);
            }

            $stmt->execute();
            $id = (int) $pdo->lastInsertId();

            return $this->buscarPorId($id, $pdo) ?? new RegistroAuditoria(
                $id,
                $registro->obtenerActorId(),
                $registro->obtenerUsuarioId(),
                $registro->obtenerAccion(),
                $registro->obtenerModulo(),
                $registro->obtenerEntidad(),
                $registro->obtenerEntidadId(),
                $registro->obtenerDescripcion(),
                $registro->obtenerValoresAnteriores(),
                $registro->obtenerValoresNuevos(),
                $registro->obtenerContexto(),
                $registro->obtenerIp(),
                $registro->obtenerUserAgent(),
                $registro->obtenerCorrelacionId()
            );
        } catch (Throwable $e) {
            throw new AuditoriaExcepcion("Fallo al persistir registro de auditoría: {$e->getMessage()}", 500, $e);
        }
    }

    /**
     * Busca un registro de auditoría por su identificador primario.
     *
     * @param int $id
     * @param PDO|null $pdoTransaccional
     * @return RegistroAuditoria|null
     */
    public function buscarPorId(int $id, ?PDO $pdoTransaccional = null): ?RegistroAuditoria
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM auditoria WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Lista todos los eventos de auditoría vinculados a un identificador de correlación común.
     *
     * @param string $correlacionId
     * @param PDO|null $pdoTransaccional
     * @return array<int, RegistroAuditoria>
     */
    public function listarPorCorrelacion(string $correlacionId, ?PDO $pdoTransaccional = null): array
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM auditoria WHERE correlacion_id = :correlacion_id ORDER BY id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':correlacion_id', trim($correlacionId), PDO::PARAM_STR);
        $stmt->execute();

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = $this->hidratar($fila);
        }
        return $resultados;
    }

    /**
     * Lista los eventos de auditoría asociados a una entidad de negocio específica.
     *
     * @param string $entidad
     * @param string|int $entidadId
     * @param PDO|null $pdoTransaccional
     * @return array<int, RegistroAuditoria>
     */
    public function listarPorEntidad(string $entidad, string|int $entidadId, ?PDO $pdoTransaccional = null): array
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM auditoria WHERE entidad = :entidad AND entidad_id = :entidad_id ORDER BY id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':entidad', trim($entidad), PDO::PARAM_STR);
        $stmt->bindValue(':entidad_id', (string) $entidadId, PDO::PARAM_STR);
        $stmt->execute();

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = $this->hidratar($fila);
        }
        return $resultados;
    }

    /**
     * Lista los registros históricos más recientes de auditoría de forma paginada.
     *
     * @param int $limite
     * @param int $offset
     * @param PDO|null $pdoTransaccional
     * @return array<int, RegistroAuditoria>
     */
    public function listarRecientes(int $limite = 50, int $offset = 0, ?PDO $pdoTransaccional = null): array
    {
        $pdo = $pdoTransaccional ?? $this->pdo;
        $sql = 'SELECT * FROM auditoria ORDER BY id DESC LIMIT :limite OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $resultados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultados[] = $this->hidratar($fila);
        }
        return $resultados;
    }

    /**
     * Hidrata una fila de base de datos a una entidad RegistroAuditoria.
     *
     * @param array<string, mixed> $fila
     * @return RegistroAuditoria
     */
    private function hidratar(array $fila): RegistroAuditoria
    {
        $ant = isset($fila['valores_anteriores']) && $fila['valores_anteriores'] !== null
            ? json_decode((string) $fila['valores_anteriores'], true)
            : null;

        $nue = isset($fila['valores_nuevos']) && $fila['valores_nuevos'] !== null
            ? json_decode((string) $fila['valores_nuevos'], true)
            : null;

        $ctx = isset($fila['contexto']) && $fila['contexto'] !== null
            ? json_decode((string) $fila['contexto'], true)
            : null;

        return new RegistroAuditoria(
            (int) $fila['id'],
            (int) $fila['actor_id'],
            $fila['usuario_id'] !== null ? (int) $fila['usuario_id'] : null,
            (string) $fila['accion'],
            (string) $fila['modulo'],
            (string) $fila['entidad'],
            $fila['entidad_id'] !== null ? (string) $fila['entidad_id'] : null,
            $fila['descripcion'] !== null ? (string) $fila['descripcion'] : null,
            is_array($ant) ? $ant : null,
            is_array($nue) ? $nue : null,
            is_array($ctx) ? $ctx : null,
            $fila['ip'] !== null ? (string) $fila['ip'] : null,
            $fila['user_agent'] !== null ? (string) $fila['user_agent'] : null,
            $fila['correlacion_id'] !== null ? (string) $fila['correlacion_id'] : null,
            (string) $fila['creado_en']
        );
    }
}
