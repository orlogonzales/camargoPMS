<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Permiso;
use CamargoPMS\Modelos\Rol;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio para la gestión de relaciones RBAC y consultas de autorización.
 *
 * Administra las tablas de asociación `usuarios_roles` y `roles_permisos`, así como
 * el cómputo de permisos efectivos y la verificación del rol Superadministrador.
 */
class AutorizacionRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    /**
     * Asigna un rol a un usuario.
     *
     * @param int $usuarioId
     * @param int $rolId
     * @param int|null $asignadoPor Usuario que realiza la asignación
     * @return bool True si se asignó o ya existía
     */
    public function asignarRolUsuario(int $usuarioId, int $rolId, ?int $asignadoPor = null): bool
    {
        $sql = 'INSERT INTO usuarios_roles (usuario_id, rol_id, asignado_por_usuario_id, asignado_en)
                VALUES (:usuario_id, :rol_id, :asignado_por, NOW())
                ON DUPLICATE KEY UPDATE asignado_por_usuario_id = VALUES(asignado_por_usuario_id)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->bindValue(':asignado_por', $asignadoPor, $asignadoPor !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

        return $stmt->execute();
    }

    /**
     * Revoca un rol a un usuario.
     *
     * @param int $usuarioId
     * @param int $rolId
     * @return bool
     */
    public function revocarRolUsuario(int $usuarioId, int $rolId): bool
    {
        $sql = 'DELETE FROM usuarios_roles WHERE usuario_id = :usuario_id AND rol_id = :rol_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si un usuario tiene asignado un rol específico.
     */
    public function usuarioTieneRol(int $usuarioId, int $rolId): bool
    {
        $sql = 'SELECT 1 FROM usuarios_roles WHERE usuario_id = :usuario_id AND rol_id = :rol_id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Obtiene los roles asignados a un usuario.
     *
     * @param int $usuarioId
     * @param bool $soloActivos Si es true, solo retorna roles con estado ACTIVO
     * @return array<int, Rol>
     */
    public function obtenerRolesUsuario(int $usuarioId, bool $soloActivos = true): array
    {
        $sql = 'SELECT r.* 
                FROM roles r
                JOIN usuarios_roles ur ON r.id = ur.rol_id
                WHERE ur.usuario_id = :usuario_id';

        if ($soloActivos) {
            $sql .= " AND r.estado = 'ACTIVO'";
        }

        $sql .= ' ORDER BY r.es_superadministrador DESC, r.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $roles = [];
        while ($fila = $stmt->fetch()) {
            $roles[] = Rol::hidratar($fila);
        }

        return $roles;
    }

    /**
     * Asocia un permiso a un rol.
     */
    public function asignarPermisoRol(int $rolId, int $permisoId): bool
    {
        $sql = 'INSERT INTO roles_permisos (rol_id, permiso_id, creado_en)
                VALUES (:rol_id, :permiso_id, NOW())
                ON DUPLICATE KEY UPDATE rol_id = VALUES(rol_id)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->bindValue(':permiso_id', $permisoId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Desvincula un permiso de un rol.
     */
    public function revocarPermisoRol(int $rolId, int $permisoId): bool
    {
        $sql = 'DELETE FROM roles_permisos WHERE rol_id = :rol_id AND permiso_id = :permiso_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->bindValue(':permiso_id', $permisoId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Sincroniza exhaustivamente la lista de permisos de un rol.
     *
     * @param int $rolId
     * @param array<int> $permisoIds
     * @return void
     */
    public function sincronizarPermisosRol(int $rolId, array $permisoIds): void
    {
        // Limpiar permisos actuales del rol
        $sqlDelete = 'DELETE FROM roles_permisos WHERE rol_id = :rol_id';
        $stmtDelete = $this->pdo->prepare($sqlDelete);
        $stmtDelete->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmtDelete->execute();

        if (empty($permisoIds)) {
            return;
        }

        // Insertar los nuevos permisos
        $sqlInsert = 'INSERT INTO roles_permisos (rol_id, permiso_id, creado_en) VALUES (:rol_id, :permiso_id, NOW())';
        $stmtInsert = $this->pdo->prepare($sqlInsert);

        foreach (array_unique($permisoIds) as $permisoId) {
            $stmtInsert->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':permiso_id', (int) $permisoId, PDO::PARAM_INT);
            $stmtInsert->execute();
        }
    }

    /**
     * Obtiene los permisos asociados a un rol específico.
     *
     * @param int $rolId
     * @return array<int, Permiso>
     */
    public function obtenerPermisosRol(int $rolId): array
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

    /**
     * Determina si el usuario es un Superadministrador activo efectivo.
     *
     * Requiere que:
     * 1. Tenga asignado un rol con es_superadministrador = 1
     * 2. Dicho rol esté ACTIVO
     * 3. El usuario esté ACTIVO
     * 4. La persona asociada esté ACTIVA
     */
    public function esUsuarioSuperadministradorActivo(int $usuarioId): bool
    {
        $sql = "SELECT COUNT(*)
                FROM usuarios_roles ur
                JOIN roles r ON ur.rol_id = r.id
                JOIN usuarios u ON ur.usuario_id = u.id
                JOIN personas p ON u.persona_id = p.id
                WHERE ur.usuario_id = :usuario_id
                  AND r.es_superadministrador = 1
                  AND r.estado = 'ACTIVO'
                  AND u.estado = 'ACTIVO'
                  AND p.estado = 'ACTIVO'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene la lista unificada de códigos de permisos efectivos para un usuario.
     *
     * Se computa como la unión de permisos de todos sus roles ACTIVOS, siempre
     * que tanto el usuario como su persona asociada estén en estado ACTIVO.
     *
     * @param int $usuarioId
     * @return array<int, string> Lista de códigos ('usuarios.ver', etc.)
     */
    public function obtenerCodigosPermisosEfectivos(int $usuarioId): array
    {
        $sql = "SELECT DISTINCT p.codigo
                FROM permisos p
                JOIN roles_permisos rp ON p.id = rp.permiso_id
                JOIN roles r ON rp.rol_id = r.id
                JOIN usuarios_roles ur ON r.id = ur.rol_id
                JOIN usuarios u ON ur.usuario_id = u.id
                JOIN personas per ON u.persona_id = per.id
                WHERE ur.usuario_id = :usuario_id
                  AND p.estado = 'ACTIVO'
                  AND r.estado = 'ACTIVO'
                  AND u.estado = 'ACTIVO'
                  AND per.estado = 'ACTIVO'
                ORDER BY p.codigo ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $codigos = [];
        while ($fila = $stmt->fetch()) {
            $codigos[] = (string) $fila['codigo'];
        }

        return $codigos;
    }

    /**
     * Alias semántico para obtenerCodigosPermisosEfectivos().
     *
     * @param int $usuarioId
     * @return array<int, string>
     */
    public function obtenerClavesPermisosEfectivos(int $usuarioId): array
    {
        return $this->obtenerCodigosPermisosEfectivos($usuarioId);
    }

    /**
     * Retorna los IDs de los usuarios que son Superadministradores activos.
     *
     * @param bool $bloqueoPesimista Si es true, añade FOR UPDATE a la consulta (debe estar dentro de una transacción)
     * @return array<int, int>
     */
    public function listarIdsSuperadministradoresActivos(bool $bloqueoPesimista = false): array
    {
        $sql = "SELECT DISTINCT ur.usuario_id
                FROM usuarios_roles ur
                JOIN roles r ON ur.rol_id = r.id
                JOIN usuarios u ON ur.usuario_id = u.id
                JOIN personas p ON u.persona_id = p.id
                WHERE r.es_superadministrador = 1
                  AND r.estado = 'ACTIVO'
                  AND u.estado = 'ACTIVO'
                  AND p.estado = 'ACTIVO'";

        if ($bloqueoPesimista) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->query($sql);
        $ids = [];
        while ($fila = $stmt->fetch()) {
            $ids[] = (int) $fila['usuario_id'];
        }

        return $ids;
    }

    /**
     * Cuenta cuántos Superadministradores activos existen actualmente.
     *
     * @param bool $bloqueoPesimista Si es true, añade FOR UPDATE
     * @return int
     */
    public function contarSuperadministradoresActivos(bool $bloqueoPesimista = false): int
    {
        return count($this->listarIdsSuperadministradoresActivos($bloqueoPesimista));
    }
}
