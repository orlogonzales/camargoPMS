<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia para cuentas de usuario humano (tabla `usuarios`).
 *
 * Utiliza consultas preparadas con PDO sin interpolación directa.
 */
class UsuarioRepositorio
{
    private PDO $pdo;
    private ?PersonaRepositorio $personaRepo;

    public function __construct(?PDO $pdo = null, ?PersonaRepositorio $personaRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
    }

    /**
     * Busca un usuario por su identificador primario.
     *
     * @param int $id
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorId(int $id, bool $cargarPersona = true): ?Usuario
    {
        $sql = 'SELECT * FROM usuarios WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::desdeArreglo($fila);
        if ($cargarPersona && $usuario->obtenerPersonaId() > 0) {
            $usuario->asignarPersona($this->personaRepo->buscarPorId($usuario->obtenerPersonaId(), true));
        }

        return $usuario;
    }

    /**
     * Busca el usuario asociado a una persona específica (cardinalidad 1:1 lógica).
     *
     * @param int $personaId
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorPersonaId(int $personaId, bool $cargarPersona = true): ?Usuario
    {
        $sql = 'SELECT * FROM usuarios WHERE persona_id = :persona_id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::desdeArreglo($fila);
        if ($cargarPersona) {
            $usuario->asignarPersona($this->personaRepo->buscarPorId($personaId, true));
        }

        return $usuario;
    }

    /**
     * Busca un usuario por su nombre de usuario normalizado.
     *
     * @param string $nombreUsuario
     * @param bool $cargarPersona
     * @return Usuario|null
     */
    public function buscarPorNombreUsuario(string $nombreUsuario, bool $cargarPersona = true): ?Usuario
    {
        $sql = 'SELECT * FROM usuarios WHERE nombre_usuario = :nombre_usuario LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nombre_usuario', trim($nombreUsuario), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $usuario = Usuario::desdeArreglo($fila);
        if ($cargarPersona && $usuario->obtenerPersonaId() > 0) {
            $usuario->asignarPersona($this->personaRepo->buscarPorId($usuario->obtenerPersonaId(), true));
        }

        return $usuario;
    }

    /**
     * Inserta un nuevo usuario en la base de datos.
     *
     * @param Usuario $usuario
     * @return Usuario
     */
    public function insertar(Usuario $usuario): Usuario
    {
        $sql = 'INSERT INTO usuarios (
                    persona_id,
                    nombre_usuario,
                    contrasena_hash,
                    estado,
                    ultimo_acceso_en,
                    contrasena_cambiada_en,
                    creado_en,
                    actualizado_en
                ) VALUES (
                    :persona_id,
                    :nombre_usuario,
                    :contrasena_hash,
                    :estado,
                    :ultimo_acceso_en,
                    :contrasena_cambiada_en,
                    NOW(),
                    NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':persona_id', $usuario->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':nombre_usuario', $usuario->obtenerNombreUsuario(), PDO::PARAM_STR);
        $stmt->bindValue(':contrasena_hash', $usuario->obtenerContrasenaHash(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $usuario->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':ultimo_acceso_en', $usuario->obtenerUltimoAccesoEn(), PDO::PARAM_STR);
        $stmt->bindValue(':contrasena_cambiada_en', $usuario->obtenerContrasenaCambiadaEn(), PDO::PARAM_STR);
        $stmt->execute();

        $nuevoId = (int) $this->pdo->lastInsertId();
        return $this->buscarPorId($nuevoId, true) ?? $usuario;
    }

    /**
     * Actualiza el hash de la contraseña de un usuario y registra la fecha de cambio.
     *
     * @param int $id
     * @param string $nuevoHash
     * @return bool
     */
    public function actualizarHashContrasena(int $id, string $nuevoHash): bool
    {
        $sql = 'UPDATE usuarios
                SET contrasena_hash = :hash,
                    contrasena_cambiada_en = NOW(),
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':hash', $nuevoHash, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Actualiza la fecha y hora del último acceso exitoso del usuario.
     *
     * @param int $id
     * @param string|null $fechaHora Formato 'Y-m-d H:i:s' o null para NOW()
     * @return bool
     */
    public function actualizarUltimoAcceso(int $id, ?string $fechaHora = null): bool
    {
        $sql = 'UPDATE usuarios
                SET ultimo_acceso_en = COALESCE(:fecha_hora, NOW()),
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_hora', $fechaHora, $fechaHora !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Actualiza el estado administrativo del usuario ('ACTIVO', 'BLOQUEADO', 'INACTIVO').
     *
     * @param int $id
     * @param string $nuevoEstado
     * @return bool
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Lista usuarios paginados aplicando filtros de búsqueda, estado y rol.
     *
     * @param array<string, mixed> $filtros
     * @param int $limite
     * @param int $desplazamiento
     * @return array<int, array<string, mixed>>
     */
    public function listarConFiltros(array $filtros = [], int $limite = 20, int $desplazamiento = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filtros['busqueda'])) {
            $termino = '%' . trim((string) $filtros['busqueda']) . '%';
            $where[] = '(u.nombre_usuario LIKE :busqueda_u
                        OR p.nombres LIKE :busqueda_n
                        OR p.apellido_paterno LIKE :busqueda_ap
                        OR p.apellido_materno LIKE :busqueda_am
                        OR EXISTS (
                            SELECT 1 FROM personas_documentos pd
                            WHERE pd.persona_id = p.id AND pd.numero_documento LIKE :busqueda_doc
                        ))';
            $params[':busqueda_u'] = $termino;
            $params[':busqueda_n'] = $termino;
            $params[':busqueda_ap'] = $termino;
            $params[':busqueda_am'] = $termino;
            $params[':busqueda_doc'] = $termino;
        }

        if (!empty($filtros['estado']) && in_array($filtros['estado'], ['ACTIVO', 'INACTIVO', 'BLOQUEADO'], true)) {
            $where[] = 'u.estado = :estado';
            $params[':estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['rol_id']) && (int) $filtros['rol_id'] > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM usuarios_roles ur WHERE ur.usuario_id = u.id AND ur.rol_id = :rol_id)';
            $params[':rol_id'] = (int) $filtros['rol_id'];
        }

        $sqlWhere = implode(' AND ', $where);

        $sql = "SELECT
                    u.id,
                    u.persona_id,
                    u.nombre_usuario,
                    u.estado,
                    u.ultimo_acceso_en,
                    u.contrasena_cambiada_en,
                    u.creado_en,
                    u.actualizado_en,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    p.estado AS persona_estado,
                    (
                        SELECT CONCAT(td.codigo, ': ', pd1.numero_documento)
                        FROM personas_documentos pd1
                        JOIN tipos_documento td ON td.id = pd1.tipo_documento_id
                        WHERE pd1.persona_id = p.id AND pd1.estado = 'ACTIVO'
                        ORDER BY pd1.es_principal DESC, pd1.id ASC
                        LIMIT 1
                    ) AS documento_principal
                FROM usuarios u
                JOIN personas p ON u.persona_id = p.id
                WHERE {$sqlWhere}
                ORDER BY u.id DESC
                LIMIT :limite OFFSET :desplazamiento";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $param => $valor) {
            $stmt->bindValue($param, $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($filas)) {
            return [];
        }

        $usuarioIds = array_column($filas, 'id');
        $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));

        // Cargar roles asignados para estos usuarios
        $sqlRoles = "SELECT ur.usuario_id, r.id AS rol_id, r.codigo AS rol_codigo, r.nombre AS rol_nombre, r.es_superadministrador
                     FROM usuarios_roles ur
                     JOIN roles r ON ur.rol_id = r.id
                     WHERE ur.usuario_id IN ({$placeholders})
                     ORDER BY r.es_superadministrador DESC, r.nombre ASC";
        $stmtRoles = $this->pdo->prepare($sqlRoles);
        $stmtRoles->execute($usuarioIds);
        $rolesPorUsuario = [];
        while ($r = $stmtRoles->fetch(PDO::FETCH_ASSOC)) {
            $rolesPorUsuario[(int) $r['usuario_id']][] = [
                'id' => (int) $r['rol_id'],
                'codigo' => (string) $r['rol_codigo'],
                'nombre' => (string) $r['rol_nombre'],
                'es_superadministrador' => (bool) $r['es_superadministrador'],
            ];
        }

        // Cargar conteo de sesiones activas para estos usuarios
        $sqlSesiones = "SELECT usuario_id, COUNT(*) AS total_activas
                        FROM sesiones_usuario
                        WHERE revocada_en IS NULL AND expira_en > NOW() AND usuario_id IN ({$placeholders})
                        GROUP BY usuario_id";
        $stmtSesiones = $this->pdo->prepare($sqlSesiones);
        $stmtSesiones->execute($usuarioIds);
        $sesionesPorUsuario = [];
        while ($s = $stmtSesiones->fetch(PDO::FETCH_ASSOC)) {
            $sesionesPorUsuario[(int) $s['usuario_id']] = (int) $s['total_activas'];
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $uId = (int) $fila['id'];
            $apellidos = trim(($fila['apellido_paterno'] ?? '') . ' ' . ($fila['apellido_materno'] ?? ''));
            $nombreCompleto = trim(($fila['nombres'] ?? '') . ' ' . $apellidos);

            $resultado[] = [
                'id' => $uId,
                'persona_id' => (int) $fila['persona_id'],
                'nombre_usuario' => (string) $fila['nombre_usuario'],
                'estado' => (string) $fila['estado'],
                'ultimo_acceso_en' => $fila['ultimo_acceso_en'] !== null ? (string) $fila['ultimo_acceso_en'] : null,
                'contrasena_cambiada_en' => $fila['contrasena_cambiada_en'] !== null ? (string) $fila['contrasena_cambiada_en'] : null,
                'creado_en' => (string) $fila['creado_en'],
                'actualizado_en' => (string) $fila['actualizado_en'],
                'persona' => [
                    'id' => (int) $fila['persona_id'],
                    'nombres' => (string) $fila['nombres'],
                    'apellido_paterno' => $fila['apellido_paterno'] !== null ? (string) $fila['apellido_paterno'] : '',
                    'apellido_materno' => $fila['apellido_materno'] !== null ? (string) $fila['apellido_materno'] : '',
                    'nombre_completo' => $nombreCompleto,
                    'documento' => $fila['documento_principal'] !== null ? (string) $fila['documento_principal'] : null,
                    'estado' => (string) $fila['persona_estado'],
                ],
                'roles' => $rolesPorUsuario[$uId] ?? [],
                'sesiones_activas' => $sesionesPorUsuario[$uId] ?? 0,
            ];
        }

        return $resultado;
    }

    /**
     * Cuenta el total de usuarios según los filtros aplicados (para paginación).
     *
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contarConFiltros(array $filtros = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filtros['busqueda'])) {
            $termino = '%' . trim((string) $filtros['busqueda']) . '%';
            $where[] = '(u.nombre_usuario LIKE :busqueda_u
                        OR p.nombres LIKE :busqueda_n
                        OR p.apellido_paterno LIKE :busqueda_ap
                        OR p.apellido_materno LIKE :busqueda_am
                        OR EXISTS (
                            SELECT 1 FROM personas_documentos pd
                            WHERE pd.persona_id = p.id AND pd.numero_documento LIKE :busqueda_doc
                        ))';
            $params[':busqueda_u'] = $termino;
            $params[':busqueda_n'] = $termino;
            $params[':busqueda_ap'] = $termino;
            $params[':busqueda_am'] = $termino;
            $params[':busqueda_doc'] = $termino;
        }

        if (!empty($filtros['estado']) && in_array($filtros['estado'], ['ACTIVO', 'INACTIVO', 'BLOQUEADO'], true)) {
            $where[] = 'u.estado = :estado';
            $params[':estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['rol_id']) && (int) $filtros['rol_id'] > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM usuarios_roles ur WHERE ur.usuario_id = u.id AND ur.rol_id = :rol_id)';
            $params[':rol_id'] = (int) $filtros['rol_id'];
        }

        $sqlWhere = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM usuarios u JOIN personas p ON u.persona_id = p.id WHERE {$sqlWhere}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $param => $valor) {
            $stmt->bindValue($param, $valor);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene el detalle estructurado de un usuario específico sin exponer secretos técnicos.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarDetallePorId(int $id): ?array
    {
        $sql = "SELECT
                    u.id,
                    u.persona_id,
                    u.nombre_usuario,
                    u.estado,
                    u.ultimo_acceso_en,
                    u.contrasena_cambiada_en,
                    u.creado_en,
                    u.actualizado_en,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    p.estado AS persona_estado,
                    (
                        SELECT CONCAT(td.codigo, ': ', pd1.numero_documento)
                        FROM personas_documentos pd1
                        JOIN tipos_documento td ON td.id = pd1.tipo_documento_id
                        WHERE pd1.persona_id = p.id AND pd1.estado = 'ACTIVO'
                        ORDER BY pd1.es_principal DESC, pd1.id ASC
                        LIMIT 1
                    ) AS documento_principal
                FROM usuarios u
                JOIN personas p ON u.persona_id = p.id
                WHERE u.id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        // Roles del usuario
        $sqlRoles = "SELECT r.id AS rol_id, r.codigo AS rol_codigo, r.nombre AS rol_nombre, r.es_superadministrador
                     FROM usuarios_roles ur
                     JOIN roles r ON ur.rol_id = r.id
                     WHERE ur.usuario_id = :id
                     ORDER BY r.es_superadministrador DESC, r.nombre ASC";
        $stmtRoles = $this->pdo->prepare($sqlRoles);
        $stmtRoles->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtRoles->execute();
        $roles = [];
        while ($r = $stmtRoles->fetch(PDO::FETCH_ASSOC)) {
            $roles[] = [
                'id' => (int) $r['rol_id'],
                'codigo' => (string) $r['rol_codigo'],
                'nombre' => (string) $r['rol_nombre'],
                'es_superadministrador' => (bool) $r['es_superadministrador'],
            ];
        }

        // Sesiones activas del usuario (sin token/hash)
        $sqlSesiones = "SELECT id, iniciada_en, ultima_actividad_en, expira_en, ip, user_agent
                        FROM sesiones_usuario
                        WHERE usuario_id = :id AND revocada_en IS NULL AND expira_en > NOW()
                        ORDER BY ultima_actividad_en DESC";
        $stmtSesiones = $this->pdo->prepare($sqlSesiones);
        $stmtSesiones->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtSesiones->execute();
        $sesiones = [];
        while ($s = $stmtSesiones->fetch(PDO::FETCH_ASSOC)) {
            $sesiones[] = [
                'id' => (int) $s['id'],
                'iniciada_en' => (string) $s['iniciada_en'],
                'ultima_actividad_en' => (string) $s['ultima_actividad_en'],
                'expira_en' => (string) $s['expira_en'],
                'ip' => $s['ip'] !== null ? (string) $s['ip'] : null,
                'user_agent' => $s['user_agent'] !== null ? (string) $s['user_agent'] : null,
            ];
        }

        $apellidos = trim(($fila['apellido_paterno'] ?? '') . ' ' . ($fila['apellido_materno'] ?? ''));
        $nombreCompleto = trim(($fila['nombres'] ?? '') . ' ' . $apellidos);

        return [
            'id' => (int) $fila['id'],
            'persona_id' => (int) $fila['persona_id'],
            'nombre_usuario' => (string) $fila['nombre_usuario'],
            'estado' => (string) $fila['estado'],
            'ultimo_acceso_en' => $fila['ultimo_acceso_en'] !== null ? (string) $fila['ultimo_acceso_en'] : null,
            'contrasena_cambiada_en' => $fila['contrasena_cambiada_en'] !== null ? (string) $fila['contrasena_cambiada_en'] : null,
            'creado_en' => (string) $fila['creado_en'],
            'actualizado_en' => (string) $fila['actualizado_en'],
            'persona' => [
                'id' => (int) $fila['persona_id'],
                'nombres' => (string) $fila['nombres'],
                'apellido_paterno' => $fila['apellido_paterno'] !== null ? (string) $fila['apellido_paterno'] : '',
                'apellido_materno' => $fila['apellido_materno'] !== null ? (string) $fila['apellido_materno'] : '',
                'nombre_completo' => $nombreCompleto,
                'documento' => $fila['documento_principal'] !== null ? (string) $fila['documento_principal'] : null,
                'estado' => (string) $fila['persona_estado'],
            ],
            'roles' => $roles,
            'sesiones' => $sesiones,
            'sesiones_activas' => count($sesiones),
        ];
    }

    /**
     * Lista personas naturales activas que aún no cuentan con una cuenta de usuario asignada.
     *
     * @param string $busqueda
     * @param int $limite
     * @return array<int, array<string, mixed>>
     */
    public function listarPersonasDisponibles(string $busqueda = '', int $limite = 20): array
    {
        $where = ["p.estado = 'ACTIVO'", "NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.persona_id = p.id)"];
        $params = [];

        if (trim($busqueda) !== '') {
            $termino = '%' . trim($busqueda) . '%';
            $where[] = '(p.nombres LIKE :b_n
                        OR p.apellido_paterno LIKE :b_ap
                        OR p.apellido_materno LIKE :b_am
                        OR EXISTS (
                            SELECT 1 FROM personas_documentos pd
                            WHERE pd.persona_id = p.id AND pd.numero_documento LIKE :b_doc
                        ))';
            $params[':b_n'] = $termino;
            $params[':b_ap'] = $termino;
            $params[':b_am'] = $termino;
            $params[':b_doc'] = $termino;
        }

        $sqlWhere = implode(' AND ', $where);

        $sql = "SELECT
                    p.id,
                    p.nombres,
                    p.apellido_paterno,
                    p.apellido_materno,
                    (
                        SELECT CONCAT(td.codigo, ': ', pd1.numero_documento)
                        FROM personas_documentos pd1
                        JOIN tipos_documento td ON td.id = pd1.tipo_documento_id
                        WHERE pd1.persona_id = p.id AND pd1.estado = 'ACTIVO'
                        ORDER BY pd1.es_principal DESC, pd1.id ASC
                        LIMIT 1
                    ) AS documento_principal
                FROM personas p
                WHERE {$sqlWhere}
                ORDER BY p.apellido_paterno ASC, p.nombres ASC
                LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $param => $valor) {
            $stmt->bindValue($param, $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = [];

        foreach ($filas as $fila) {
            $apellidos = trim(($fila['apellido_paterno'] ?? '') . ' ' . ($fila['apellido_materno'] ?? ''));
            $nombreCompleto = trim(($fila['nombres'] ?? '') . ' ' . $apellidos);

            $resultado[] = [
                'id' => (int) $fila['id'],
                'nombres' => (string) $fila['nombres'],
                'apellido_paterno' => $fila['apellido_paterno'] !== null ? (string) $fila['apellido_paterno'] : '',
                'apellido_materno' => $fila['apellido_materno'] !== null ? (string) $fila['apellido_materno'] : '',
                'nombre_completo' => $nombreCompleto,
                'documento' => $fila['documento_principal'] !== null ? (string) $fila['documento_principal'] : null,
            ];
        }

        return $resultado;
    }
}
