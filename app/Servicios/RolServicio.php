<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\PermisoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\RolDuplicadoExcepcion;
use CamargoPMS\Excepciones\RolNoEncontradoExcepcion;
use CamargoPMS\Excepciones\RolProtegidoExcepcion;
use CamargoPMS\Excepciones\UltimoSuperadministradorExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Permiso;
use CamargoPMS\Modelos\Rol;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Repositorios\AutorizacionRepositorio;
use CamargoPMS\Repositorios\PermisoRepositorio;
use CamargoPMS\Repositorios\RolRepositorio;
use CamargoPMS\Repositorios\UsuarioRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del catálogo de roles, asignación de permisos
 * y asignación/revocación de roles a usuarios bajo el principio de invariante
 * del último Superadministrador activo y protección de roles de sistema.
 *
 * Principio Vinculante: ROL DE AUTORIZACIÓN ≠ CARGO LABORAL.
 */
class RolServicio
{
    private PDO $pdo;
    private RolRepositorio $rolRepo;
    private PermisoRepositorio $permisoRepo;
    private AutorizacionRepositorio $autorizacionRepo;
    private UsuarioRepositorio $usuarioRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?RolRepositorio $rolRepo = null,
        ?PermisoRepositorio $permisoRepo = null,
        ?AutorizacionRepositorio $autorizacionRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->rolRepo = $rolRepo ?? new RolRepositorio($this->pdo);
        $this->permisoRepo = $permisoRepo ?? new PermisoRepositorio($this->pdo);
        $this->autorizacionRepo = $autorizacionRepo ?? new AutorizacionRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
    }

    /**
     * Crea un nuevo rol en el sistema.
     *
     * @param array<string, mixed> $datos
     * @return Rol
     * @throws ValidacionExcepcion
     * @throws RolDuplicadoExcepcion
     */
    public function crearRol(array $datos): Rol
    {
        $clave = isset($datos['codigo']) ? strtoupper(trim((string) $datos['codigo'])) : (isset($datos['clave']) ? strtoupper(trim((string) $datos['clave'])) : '');
        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : '';
        $descripcion = isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== ''
            ? trim((string) $datos['descripcion'])
            : null;
        $estado = isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : 'ACTIVO';

        $this->validarClaveRol($clave);

        if ($clave === 'SUPERADMINISTRADOR') {
            throw new ValidacionExcepcion(
                'No se permite crear roles adicionales con la clave reservada SUPERADMINISTRADOR.',
                ['clave' => 'Clave reservada del sistema']
            );
        }

        if ($nombre === '') {
            throw new ValidacionExcepcion('El nombre del rol es obligatorio.', ['nombre' => 'Requerido']);
        }

        if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 100) {
            throw new ValidacionExcepcion(
                'El nombre del rol debe tener entre 2 y 100 caracteres.',
                ['nombre' => 'Longitud fuera de rango (2 a 100)']
            );
        }

        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion("El estado '{$estado}' no es válido para un rol.");
        }

        if ($this->rolRepo->buscarPorClave($clave) !== null) {
            throw new RolDuplicadoExcepcion('clave', $clave);
        }

        $nuevoRol = new Rol(
            null,
            $clave,
            $nombre,
            $descripcion,
            $estado,
            false,
            false
        );

        return $this->rolRepo->insertar($nuevoRol);
    }

    /**
     * Actualiza la información de un rol existente.
     *
     * @param int $id
     * @param array<string, mixed> $datos
     * @return Rol
     * @throws RolNoEncontradoExcepcion
     * @throws RolProtegidoExcepcion
     * @throws ValidacionExcepcion
     * @throws RolDuplicadoExcepcion
     */
    public function actualizarRol(int $id, array $datos): Rol
    {
        $rolExistente = $this->rolRepo->buscarPorId($id);
        if (!$rolExistente) {
            throw new RolNoEncontradoExcepcion($id);
        }

        $clave = isset($datos['codigo'])
            ? strtoupper(trim((string) $datos['codigo']))
            : (isset($datos['clave']) ? strtoupper(trim((string) $datos['clave'])) : $rolExistente->obtenerCodigo());
        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : $rolExistente->obtenerNombre();
        $descripcion = array_key_exists('descripcion', $datos)
            ? (trim((string) $datos['descripcion']) !== '' ? trim((string) $datos['descripcion']) : null)
            : $rolExistente->obtenerDescripcion();
        $estado = isset($datos['estado']) ? strtoupper(trim((string) $datos['estado'])) : $rolExistente->obtenerEstado();

        // Si es rol de sistema o superadministrador, proteger campos clave
        if ($rolExistente->esSuperadministrador() || $rolExistente->esSistema()) {
            if ($clave !== $rolExistente->obtenerClave()) {
                throw new RolProtegidoExcepcion(
                    "No se puede modificar la clave técnica de un rol protegido del sistema ('{$rolExistente->obtenerClave()}')."
                );
            }

            if ($rolExistente->esSuperadministrador() && $estado !== 'ACTIVO') {
                throw new RolProtegidoExcepcion(
                    'No se puede desactivar el rol estructural SUPERADMINISTRADOR.'
                );
            }
        }

        $this->validarClaveRol($clave);

        if ($nombre === '') {
            throw new ValidacionExcepcion('El nombre del rol es obligatorio.', ['nombre' => 'Requerido']);
        }

        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion("El estado '{$estado}' no es válido para un rol.");
        }

        // Si cambió la clave, verificar que no colisione con otro
        if ($clave !== $rolExistente->obtenerClave()) {
            $colision = $this->rolRepo->buscarPorClave($clave);
            if ($colision !== null && $colision->obtenerId() !== $id) {
                throw new RolDuplicadoExcepcion('clave', $clave);
            }
        }

        $rolActualizado = new Rol(
            $id,
            $clave,
            $nombre,
            $descripcion,
            $estado,
            $rolExistente->esSistema(),
            $rolExistente->esSuperadministrador()
        );

        $this->rolRepo->actualizar($rolActualizado);

        return $this->rolRepo->buscarPorId($id) ?? $rolActualizado;
    }

    /**
     * Modifica el estado administrativo de un rol.
     *
     * @param int $id
     * @param string $nuevoEstado
     * @return bool
     * @throws RolNoEncontradoExcepcion
     * @throws RolProtegidoExcepcion
     * @throws ValidacionExcepcion
     */
    public function cambiarEstadoRol(int $id, string $nuevoEstado): bool
    {
        $rol = $this->rolRepo->buscarPorId($id);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($id);
        }

        $nuevoEstado = strtoupper(trim($nuevoEstado));
        if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ValidacionExcepcion("El estado '{$nuevoEstado}' no es válido para un rol.");
        }

        if ($rol->esSuperadministrador() && $nuevoEstado !== 'ACTIVO') {
            throw new RolProtegidoExcepcion(
                'No se puede desactivar el rol estructural SUPERADMINISTRADOR.'
            );
        }

        return $this->rolRepo->actualizar(new Rol(
            $rol->obtenerId(),
            $rol->obtenerCodigo(),
            $rol->obtenerNombre(),
            $rol->obtenerDescripcion(),
            $nuevoEstado,
            $rol->esSistema(),
            $rol->esSuperadministrador()
        ));
    }

    /**
     * Elimina físicamente un rol no protegido y sin usuarios asociados.
     *
     * @param int $id
     * @return bool
     * @throws RolNoEncontradoExcepcion
     * @throws RolProtegidoExcepcion
     * @throws ValidacionExcepcion
     */
    public function eliminarRol(int $id): bool
    {
        $rol = $this->rolRepo->buscarPorId($id);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($id);
        }

        if ($rol->esSuperadministrador() || $rol->esSistema()) {
            throw new RolProtegidoExcepcion(
                "No se puede eliminar el rol protegido del sistema '{$rol->obtenerNombre()}'."
            );
        }

        $stmtCheck = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios_roles WHERE rol_id = :rol_id');
        $stmtCheck->bindValue(':rol_id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        $usuariosAsociados = (int) $stmtCheck->fetchColumn();

        if ($usuariosAsociados > 0) {
            throw new ValidacionExcepcion(
                "No se puede eliminar el rol '{$rol->obtenerNombre()}' porque tiene {$usuariosAsociados} usuario(s) asignado(s)."
            );
        }

        return $this->rolRepo->eliminar($id);
    }

    /**
     * Asigna un rol a un usuario, validando la vigencia de ambos.
     *
     * @param int $usuarioId
     * @param int $rolId
     * @param int|null $asignadoPor
     * @return bool
     * @throws EntidadNoEncontradaExcepcion
     * @throws ValidacionExcepcion
     */
    public function asignarRolAUsuario(int $usuarioId, int $rolId, ?int $asignadoPor = null): bool
    {
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId, false);
        if (!$usuario) {
            throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
        }

        if ($usuario->obtenerEstado() !== 'ACTIVO') {
            throw new ValidacionExcepcion('No se puede asignar un rol a un usuario inactivo o bloqueado.');
        }

        $rol = $this->rolRepo->buscarPorId($rolId);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($rolId);
        }

        if ($rol->obtenerEstado() !== 'ACTIVO') {
            throw new ValidacionExcepcion('No se puede asignar un rol inactivo.');
        }

        return $this->autorizacionRepo->asignarRolUsuario($usuarioId, $rolId, $asignadoPor);
    }

    /**
     * Revoca un rol a un usuario, impidiendo la revocación si se trata del último
     * Superadministrador activo del sistema.
     *
     * @param int $usuarioId
     * @param int $rolId
     * @return bool
     * @throws EntidadNoEncontradaExcepcion
     * @throws UltimoSuperadministradorExcepcion
     * @throws Throwable
     */
    public function revocarRolDeUsuario(int $usuarioId, int $rolId): bool
    {
        $usuario = $this->usuarioRepo->buscarPorId($usuarioId, false);
        if (!$usuario) {
            throw new EntidadNoEncontradaExcepcion('Usuario', $usuarioId);
        }

        $rol = $this->rolRepo->buscarPorId($rolId);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($rolId);
        }

        $this->pdo->beginTransaction();
        try {
            // Si el rol a revocar confiere facultades de Superadministrador,
            // verificar con bloqueo pesimista que no se destruya el invariante
            if ($rol->esSuperadministrador()) {
                $superadminsActivos = $this->autorizacionRepo->listarIdsSuperadministradoresActivos(true);

                if (in_array($usuarioId, $superadminsActivos, true) && count($superadminsActivos) <= 1) {
                    throw new UltimoSuperadministradorExcepcion(
                        'Operación denegada: no se puede revocar el rol al único Superadministrador activo del sistema.'
                    );
                }
            }

            $resultado = $this->autorizacionRepo->revocarRolUsuario($usuarioId, $rolId);

            $this->pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Vincula un permiso atómico a un rol.
     *
     * @param int $rolId
     * @param int $permisoId
     * @return bool
     * @throws RolNoEncontradoExcepcion
     * @throws PermisoNoEncontradoExcepcion
     */
    public function asignarPermisoARol(int $rolId, int $permisoId): bool
    {
        $rol = $this->rolRepo->buscarPorId($rolId);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($rolId);
        }

        $permiso = $this->permisoRepo->buscarPorId($permisoId);
        if (!$permiso) {
            throw new PermisoNoEncontradoExcepcion($permisoId);
        }

        return $this->autorizacionRepo->asignarPermisoRol($rolId, $permisoId);
    }

    /**
     * Desvincula un permiso atómico de un rol.
     *
     * @param int $rolId
     * @param int $permisoId
     * @return bool
     * @throws RolNoEncontradoExcepcion
     * @throws PermisoNoEncontradoExcepcion
     */
    public function revocarPermisoDeRol(int $rolId, int $permisoId): bool
    {
        $rol = $this->rolRepo->buscarPorId($rolId);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($rolId);
        }

        $permiso = $this->permisoRepo->buscarPorId($permisoId);
        if (!$permiso) {
            throw new PermisoNoEncontradoExcepcion($permisoId);
        }

        return $this->autorizacionRepo->revocarPermisoRol($rolId, $permisoId);
    }

    /**
     * Sincroniza exhaustivamente la lista de permisos de un rol.
     *
     * @param int $rolId
     * @param array<int> $permisoIds
     * @return void
     * @throws RolNoEncontradoExcepcion
     * @throws PermisoNoEncontradoExcepcion
     */
    public function sincronizarPermisosDeRol(int $rolId, array $permisoIds): void
    {
        $rol = $this->rolRepo->buscarPorId($rolId);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($rolId);
        }

        // Validar que todos los permisos existan
        foreach ($permisoIds as $pId) {
            $p = $this->permisoRepo->buscarPorId((int) $pId);
            if (!$p) {
                throw new PermisoNoEncontradoExcepcion($pId);
            }
        }

        $this->autorizacionRepo->sincronizarPermisosRol($rolId, $permisoIds);
    }

    /**
     * Lista todos los roles existentes.
     *
     * @return array<int, Rol>
     */
    public function listarRoles(): array
    {
        return $this->rolRepo->listarTodos();
    }

    /**
     * Lista todos los roles activos.
     *
     * @return array<int, Rol>
     */
    public function listarRolesActivos(): array
    {
        return $this->rolRepo->listarActivos();
    }

    /**
     * Busca un rol por su ID.
     */
    public function buscarRolPorId(int $id): ?Rol
    {
        return $this->rolRepo->buscarPorId($id);
    }

    /**
     * Busca un rol por su clave técnica.
     */
    public function buscarRolPorClave(string $clave): ?Rol
    {
        return $this->rolRepo->buscarPorClave($clave);
    }

    /**
     * Lista todos los permisos registrados en el catálogo.
     *
     * @return array<int, Permiso>
     */
    public function listarPermisos(): array
    {
        return $this->permisoRepo->listarTodos();
    }

    /**
     * Busca un rol por su código técnico.
     */
    public function buscarRolPorCodigo(string $codigo): ?Rol
    {
        return $this->rolRepo->buscarPorCodigo($codigo);
    }

    /**
     * Busca un permiso por su código técnico atómico (formato recurso.accion).
     */
    public function buscarPermisoPorCodigo(string $codigo): ?Permiso
    {
        return $this->permisoRepo->buscarPorCodigo($codigo);
    }

    /**
     * Busca un permiso por su clave técnica atómica.
     */
    public function buscarPermisoPorClave(string $clave): ?Permiso
    {
        return $this->permisoRepo->buscarPorClave($clave);
    }

    /**
     * Obtiene los roles asignados a un usuario.
     *
     * @param int $usuarioId
     * @param bool $soloActivos
     * @return array<int, Rol>
     */
    public function obtenerRolesDeUsuario(int $usuarioId, bool $soloActivos = true): array
    {
        return $this->autorizacionRepo->obtenerRolesUsuario($usuarioId, $soloActivos);
    }

    /**
     * Obtiene los permisos asociados a un rol.
     *
     * @param int $rolId
     * @return array<int, Permiso>
     */
    public function obtenerPermisosDeRol(int $rolId): array
    {
        return $this->autorizacionRepo->obtenerPermisosRol($rolId);
    }

    /**
     * Valida sintácticamente la clave técnica de un rol.
     *
     * @param string $clave
     * @throws ValidacionExcepcion
     */
    private function validarClaveRol(string $clave): void
    {
        if ($clave === '') {
            throw new ValidacionExcepcion('La clave del rol es obligatoria.', ['clave' => 'Requerido']);
        }

        if (strlen($clave) < 3 || strlen($clave) > 50) {
            throw new ValidacionExcepcion(
                'La clave del rol debe tener entre 3 y 50 caracteres.',
                ['clave' => 'Longitud fuera de rango (3 a 50)']
            );
        }

        if (!preg_match('/^[A-Z0-9_]+$/', $clave)) {
            throw new ValidacionExcepcion(
                'La clave del rol solo puede contener letras mayúsculas, números y guiones bajos.',
                ['clave' => 'Formato no permitido']
            );
        }
    }
}
