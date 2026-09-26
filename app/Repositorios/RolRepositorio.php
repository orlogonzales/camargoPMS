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

    /**
     * Lista roles con agregaciones de conteo de usuarios y permisos vinculados,
     * admitiendo filtros opcionales de búsqueda y estado.
     *
     * @param string|null $busqueda
     * @param string|null $estado
     * @return array<int, array<string, mixed>>
     */
    public function listarRolesConConteos(?string $busqueda = null, ?string $estado = null): array
    {
        $sql = 'SELECT 
                    r.id,
                    r.codigo,
                    r.nombre,
                    r.descripcion,
                    r.estado,
                    r.es_sistema,
                    r.es_superadministrador,
                    r.creado_en,
                    r.actualizado_en,
                    COUNT(DISTINCT ur.usuario_id) AS total_usuarios,
                    COUNT(DISTINCT rp.permiso_id) AS total_permisos
                FROM roles r
                LEFT JOIN usuarios_roles ur ON r.id = ur.rol_id
                LEFT JOIN roles_permisos rp ON r.id = rp.rol_id
                WHERE 1=1';

        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= ' AND (r.nombre LIKE :b1 OR r.codigo LIKE :b2 OR r.descripcion LIKE :b3)';
            $term = '%' . trim($busqueda) . '%';
            $params[':b1'] = $term;
            $params[':b2'] = $term;
            $params[':b3'] = $term;
        }

        if ($estado !== null && in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO'], true)) {
            $sql .= ' AND r.estado = :estado';
            $params[':estado'] = strtoupper(trim($estado));
        }

        $sql .= ' GROUP BY r.id, r.codigo, r.nombre, r.descripcion, r.estado, r.es_sistema, r.es_superadministrador, r.creado_en, r.actualizado_en
                  ORDER BY r.es_superadministrador DESC, r.nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $param => $val) {
            $stmt->bindValue($param, $val, PDO::PARAM_STR);
        }
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = [
                'id' => (int) $f['id'],
                'codigo' => (string) $f['codigo'],
                'clave' => (string) $f['codigo'],
                'nombre' => (string) $f['nombre'],
                'descripcion' => $f['descripcion'] !== null ? (string) $f['descripcion'] : null,
                'estado' => (string) $f['estado'],
                'es_sistema' => (bool) $f['es_sistema'],
                'es_superadministrador' => (bool) $f['es_superadministrador'],
                'creado_en' => (string) $f['creado_en'],
                'actualizado_en' => (string) $f['actualizado_en'],
                'total_usuarios' => (int) $f['total_usuarios'],
                'total_permisos' => (int) $f['total_permisos'],
            ];
        }

        return $resultado;
    }

    /**
     * Obtiene la lista de usuarios asociados a un rol específico con detalle de su persona vinculada.
     *
     * @param int $rolId
     * @return array<int, array<string, mixed>>
     */
    public function obtenerUsuariosPorRol(int $rolId): array
    {
        $sql = "SELECT 
                    u.id AS usuario_id,
                    u.nombre_usuario,
                    u.estado AS estado_usuario,
                    p.id AS persona_id,
                    CONCAT_WS(' ', p.nombres, p.apellido_paterno, p.apellido_materno) AS nombre_completo_persona,
                    p.estado AS estado_persona,
                    ur.asignado_en
                FROM usuarios_roles ur
                JOIN usuarios u ON ur.usuario_id = u.id
                JOIN personas p ON u.persona_id = p.id
                WHERE ur.rol_id = :rol_id
                ORDER BY u.nombre_usuario ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];
        foreach ($filas as $f) {
            $resultado[] = [
                'usuario_id' => (int) $f['usuario_id'],
                'nombre_usuario' => (string) $f['nombre_usuario'],
                'estado_usuario' => (string) $f['estado_usuario'],
                'persona_id' => (int) $f['persona_id'],
                'nombre_completo_persona' => trim((string) $f['nombre_completo_persona']),
                'estado_persona' => (string) $f['estado_persona'],
                'asignado_en' => (string) $f['asignado_en'],
            ];
        }

        return $resultado;
    }
}
