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
use CamargoPMS\Modelos\AccionAuditoria;
use CamargoPMS\Modelos\ActorAuditoria;
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
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?PDO $pdo = null,
        ?RolRepositorio $rolRepo = null,
        ?PermisoRepositorio $permisoRepo = null,
        ?AutorizacionRepositorio $autorizacionRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->rolRepo = $rolRepo ?? new RolRepositorio($this->pdo);
        $this->permisoRepo = $permisoRepo ?? new PermisoRepositorio($this->pdo);
        $this->autorizacionRepo = $autorizacionRepo ?? new AutorizacionRepositorio($this->pdo);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
    }

    /**
     * Resuelve el actor de auditoría correspondiente al usuario ejecutor,
     * garantizando que nunca se use un usuario_id como actor_id directo (D-061).
     *
     * @param int|null $usuarioId
     * @return ActorAuditoria|null
     */
    private function resolverActorEjecutor(?int $usuarioId): ?ActorAuditoria
    {
        if ($usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        return $this->auditoriaServicio->obtenerOAsegurarActorUsuario($usuarioId, $this->pdo);
    }

    /**
     * Crea un nuevo rol en el sistema.
     *
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Rol
     * @throws ValidacionExcepcion
     * @throws RolDuplicadoExcepcion
     */
    public function crearRol(array $datos, ?int $ejecutadoPorUsuarioId = null): Rol
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

        $rolPersistido = $this->rolRepo->insertar($nuevoRol);

        // Auditoría transversal con D-061
        try {
            $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
            $this->auditoriaServicio->registrar(
                AccionAuditoria::CREAR,
                'seguridad',
                'rol',
                (string) $rolPersistido->obtenerId(),
                "Creación del rol '{$rolPersistido->obtenerCodigo()}'",
                null,
                $rolPersistido->aArreglo(),
                null,
                $actorEjecutor,
                $ejecutadoPorUsuarioId,
                null,
                $this->pdo
            );
        } catch (Throwable) {
            // Prevenir interrupción si la auditoría informativa falla
        }

        return $rolPersistido;
    }

    /**
     * Actualiza la información de un rol existente.
     *
     * @param int $id
     * @param array<string, mixed> $datos
     * @param int|null $ejecutadoPorUsuarioId
     * @return Rol
     * @throws RolNoEncontradoExcepcion
     * @throws RolProtegidoExcepcion
     * @throws ValidacionExcepcion
     * @throws RolDuplicadoExcepcion
     */
    public function actualizarRol(int $id, array $datos, ?int $ejecutadoPorUsuarioId = null): Rol
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

        if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 100) {
            throw new ValidacionExcepcion(
                'El nombre del rol debe tener entre 2 y 100 caracteres.',
                ['nombre' => 'Longitud fuera de rango (2 a 100)']
            );
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

        $rolFinal = $this->rolRepo->buscarPorId($id) ?? $rolActualizado;

        // Auditoría transversal con D-061
        try {
            $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
            $this->auditoriaServicio->registrar(
                AccionAuditoria::EDITAR,
                'seguridad',
                'rol',
                (string) $id,
                "Modificación del rol '{$rolFinal->obtenerCodigo()}'",
                $rolExistente->aArreglo(),
                $rolFinal->aArreglo(),
                null,
                $actorEjecutor,
                $ejecutadoPorUsuarioId,
                null,
                $this->pdo
            );
        } catch (Throwable) {
            // Prevenir interrupción si la auditoría informativa falla
        }

        return $rolFinal;
    }

    /**
     * Modifica el estado administrativo de un rol.
     *
     * @param int $id
     * @param string $nuevoEstado
     * @param int|null $ejecutadoPorUsuarioId
     * @return bool
     * @throws RolNoEncontradoExcepcion
     * @throws RolProtegidoExcepcion
     * @throws ValidacionExcepcion
     */
    public function cambiarEstadoRol(int $id, string $nuevoEstado, ?int $ejecutadoPorUsuarioId = null): bool
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

        $estadoAnterior = $rol->obtenerEstado();
        if ($estadoAnterior === $nuevoEstado) {
            return true;
        }

        $rolActualizado = new Rol(
            $rol->obtenerId(),
            $rol->obtenerCodigo(),
            $rol->obtenerNombre(),
            $rol->obtenerDescripcion(),
            $nuevoEstado,
            $rol->esSistema(),
            $rol->esSuperadministrador()
        );

        $resultado = $this->rolRepo->actualizar($rolActualizado);

        if ($resultado) {
            try {
                $accion = ($nuevoEstado === 'ACTIVO') ? AccionAuditoria::ACTIVAR : AccionAuditoria::DESACTIVAR;
                $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);
                $this->auditoriaServicio->registrar(
                    $accion,
                    'seguridad',
                    'rol',
                    (string) $id,
                    "Cambio de estado administrativo a {$nuevoEstado} para el rol '{$rol->obtenerCodigo()}'",
                    ['estado' => $estadoAnterior],
                    ['estado' => $nuevoEstado],
                    null,
                    $actorEjecutor,
                    $ejecutadoPorUsuarioId,
                    null,
                    $this->pdo
                );
            } catch (Throwable) {
                // Prevenir interrupción
            }
        }

        return $resultado;
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

        $resultado = $this->autorizacionRepo->asignarRolUsuario($usuarioId, $rolId, $asignadoPor);

        if ($resultado) {
            try {
                $actor = ($asignadoPor !== null && $asignadoPor > 0) ? $this->auditoriaServicio->obtenerOAsegurarActorUsuario($asignadoPor, $this->pdo) : null;
                $this->auditoriaServicio->registrar(
                    AccionAuditoria::ASIGNAR,
                    'seguridad',
                    'usuario_rol',
                    "{$usuarioId}:{$rolId}",
                    "Asignación de rol '{$rol->obtenerCodigo()}' al usuario '{$usuario->obtenerNombreUsuario()}'",
                    null,
                    [
                        'usuario_id' => $usuarioId,
                        'nombre_usuario' => $usuario->obtenerNombreUsuario(),
                        'rol_id' => $rolId,
                        'rol_codigo' => $rol->obtenerCodigo(),
                        'rol_nombre' => $rol->obtenerNombre(),
                        'asignado_por' => $asignadoPor,
                    ],
                    null,
                    $actor,
                    $usuarioId
                );
            } catch (Throwable) {
                // Prevenir interrupción de asignación si la auditoría informativa falla
            }
        }

        return $resultado;
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
    public function revocarRolDeUsuario(int $usuarioId, int $rolId, ?int $revocadoPor = null): bool
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

            if ($resultado) {
                // Registrar auditoría atómicamente dentro de la misma transacción
                $actor = ($revocadoPor !== null && $revocadoPor > 0) ? $this->auditoriaServicio->obtenerOAsegurarActorUsuario($revocadoPor, $this->pdo) : null;
                $this->auditoriaServicio->registrar(
                    AccionAuditoria::REVOCAR,
                    'seguridad',
                    'usuario_rol',
                    "{$usuarioId}:{$rolId}",
                    "Revocación de rol '{$rol->obtenerCodigo()}' al usuario '{$usuario->obtenerNombreUsuario()}'",
                    [
                        'usuario_id' => $usuarioId,
                        'nombre_usuario' => $usuario->obtenerNombreUsuario(),
                        'rol_id' => $rolId,
                        'rol_codigo' => $rol->obtenerCodigo(),
                        'rol_nombre' => $rol->obtenerNombre(),
                        'revocado_por' => $revocadoPor,
                    ],
                    null,
                    null,
                    $actor,
                    $usuarioId,
                    null,
                    $this->pdo
                );
            }

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
     * Sincroniza exhaustivamente la lista de permisos de un rol con protección
     * para SUPERADMINISTRADOR y trazabilidad transaccional de auditoría bajo D-061.
     *
     * @param int $rolId
     * @param array<int> $permisoIds
     * @param int|null $ejecutadoPorUsuarioId
     * @param string|null $correlacionId
     * @return array<string, mixed>
     * @throws RolNoEncontradoExcepcion
     * @throws PermisoNoEncontradoExcepcion
     * @throws RolProtegidoExcepcion
     * @throws Throwable
     */
    public function sincronizarPermisosDeRol(
        int $rolId,
        array $permisoIds,
        ?int $ejecutadoPorUsuarioId = null,
        ?string $correlacionId = null
    ): array {
        $rol = $this->rolRepo->buscarPorId($rolId);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($rolId);
        }

        // Normalizar lista de IDs (enteros únicos mayores a 0)
        $permisoIdsNormalizados = [];
        foreach ($permisoIds as $pId) {
            $pInt = (int) $pId;
            if ($pInt > 0) {
                $permisoIdsNormalizados[] = $pInt;
            }
        }
        $permisoIdsNormalizados = array_values(array_unique($permisoIdsNormalizados));

        // Validar que todos los permisos existan
        $permisosCatalogo = [];
        foreach ($permisoIdsNormalizados as $pId) {
            $p = $this->permisoRepo->buscarPorId($pId);
            if (!$p) {
                throw new PermisoNoEncontradoExcepcion($pId);
            }
            $permisosCatalogo[$pId] = $p;
        }

        // Si es SUPERADMINISTRADOR, proteger contra revocación de permisos críticos
        if ($rol->esSuperadministrador()) {
            $codigosPermisosNuevos = array_map(fn($p) => $p->obtenerCodigo(), $permisosCatalogo);
            $permisosCriticos = ['roles.ver', 'roles.editar', 'permisos.ver', 'usuarios.ver', 'usuarios.editar'];
            foreach ($permisosCriticos as $critico) {
                if (!in_array($critico, $codigosPermisosNuevos, true)) {
                    throw new RolProtegidoExcepcion(
                        "No se pueden revocar permisos críticos ('{$critico}') del rol SUPERADMINISTRADOR."
                    );
                }
            }
        }

        // Permisos actuales del rol
        $permisosActuales = $this->autorizacionRepo->obtenerPermisosRol($rolId);
        $actualesIds = array_map(fn($p) => (int) $p->obtenerId(), $permisosActuales);

        $agregados = array_values(array_diff($permisoIdsNormalizados, $actualesIds));
        $removidos = array_values(array_diff($actualesIds, $permisoIdsNormalizados));

        // Transacción para sincronización atómica y auditoría consistente
        $transaccionPropia = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $this->autorizacionRepo->sincronizarPermisosRol($rolId, $permisoIdsNormalizados);

            $correlacion = $correlacionId ?? $this->auditoriaServicio->generarCorrelacionId();
            $actorEjecutor = $this->resolverActorEjecutor($ejecutadoPorUsuarioId);

            // Auditar permisos asignados
            foreach ($agregados as $pId) {
                $pObj = $permisosCatalogo[$pId] ?? $this->permisoRepo->buscarPorId((int) $pId);
                $pCodigo = $pObj ? $pObj->obtenerCodigo() : (string) $pId;

                $this->auditoriaServicio->registrar(
                    AccionAuditoria::ASIGNAR,
                    'seguridad',
                    'rol_permiso',
                    "{$rolId}:{$pId}",
                    "Asignación de permiso '{$pCodigo}' al rol '{$rol->obtenerCodigo()}'",
                    null,
                    [
                        'rol_id' => $rolId,
                        'rol_codigo' => $rol->obtenerCodigo(),
                        'permiso_id' => $pId,
                        'permiso_codigo' => $pCodigo,
                    ],
                    null,
                    $actorEjecutor,
                    $ejecutadoPorUsuarioId,
                    $correlacion,
                    $this->pdo
                );
            }

            // Auditar permisos revocados
            foreach ($removidos as $pId) {
                $pObj = $this->permisoRepo->buscarPorId((int) $pId);
                $pCodigo = $pObj ? $pObj->obtenerCodigo() : (string) $pId;

                $this->auditoriaServicio->registrar(
                    AccionAuditoria::REVOCAR,
                    'seguridad',
                    'rol_permiso',
                    "{$rolId}:{$pId}",
                    "Revocación de permiso '{$pCodigo}' del rol '{$rol->obtenerCodigo()}'",
                    [
                        'rol_id' => $rolId,
                        'rol_codigo' => $rol->obtenerCodigo(),
                        'permiso_id' => $pId,
                        'permiso_codigo' => $pCodigo,
                    ],
                    null,
                    null,
                    $actorEjecutor,
                    $ejecutadoPorUsuarioId,
                    $correlacion,
                    $this->pdo
                );
            }

            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return [
                'rol_id' => $rolId,
                'agregados' => $agregados,
                'removidos' => $removidos,
                'total_asignados' => count($permisoIdsNormalizados),
                'correlacion_id' => $correlacion,
            ];
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
        return $this->rolRepo->listarRolesConConteos($busqueda, $estado);
    }

    /**
     * Obtiene el detalle consolidado de un rol, incluyendo sus permisos asignados
     * y los usuarios que lo tienen asignado actualmente.
     *
     * @param int $id
     * @return array<string, mixed>
     * @throws RolNoEncontradoExcepcion
     */
    public function obtenerDetalleRol(int $id): array
    {
        $rol = $this->rolRepo->buscarPorId($id);
        if (!$rol) {
            throw new RolNoEncontradoExcepcion($id);
        }

        $permisos = $this->autorizacionRepo->obtenerPermisosRol($id);
        $usuarios = $this->rolRepo->obtenerUsuariosPorRol($id);

        return [
            'rol' => $rol->aArreglo(),
            'permisos' => array_map(fn($p) => $p->aArreglo(), $permisos),
            'permisos_ids' => array_map(fn($p) => (int) $p->obtenerId(), $permisos),
            'usuarios' => $usuarios,
            'total_usuarios' => count($usuarios),
            'total_permisos' => count($permisos),
        ];
    }

    /**
     * Lista todos los permisos del catálogo agrupados jerárquicamente por módulo funcional.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function listarPermisosAgrupados(): array
    {
        return $this->permisoRepo->listarAgrupadosPorModulo();
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
