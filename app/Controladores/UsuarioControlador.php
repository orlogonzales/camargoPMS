<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\EntidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\UltimoSuperadministradorExcepcion;
use CamargoPMS\Excepciones\UsuarioDuplicadoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\RolServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\UsuarioServicio;
use InvalidArgumentException;
use Throwable;

/**
 * Controlador administrativo y de endpoints JSON para la gestión integral de cuentas humanas de usuario.
 *
 * Principios vinculantes:
 * - PERSONA ≠ USUARIO ≠ COLABORADOR ≠ ROL
 * - ACTOR ≠ USUARIO
 * - MENÚ ≠ AUTORIZACIÓN
 * - Cero fugas de hashes, tokens, session IDs o secretos técnicos en respuestas HTTP.
 */
class UsuarioControlador
{
    private UsuarioServicio $usuarioServicio;
    private RolServicio $rolServicio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?UsuarioServicio $usuarioServicio = null,
        ?RolServicio $rolServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->usuarioServicio = $usuarioServicio ?? new UsuarioServicio();
        $this->rolServicio = $rolServicio ?? new RolServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Muestra la interfaz principal de administración de usuarios (GET /usuarios).
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        $rolesDisponibles = $this->rolServicio->listarRolesActivos();
        $rolesFormateados = array_map(static function ($r) {
            return [
                'id' => (int) $r->obtenerId(),
                'codigo' => $r->obtenerCodigo(),
                'nombre' => $r->obtenerNombre(),
                'es_superadministrador' => $r->esSuperadministrador(),
            ];
        }, $rolesDisponibles);

        // Capacidades del usuario autenticado para adaptar la experiencia visual (UX)
        $capacidades = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioActualId, 'usuarios.crear'),
            'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'usuarios.editar'),
            'puede_bloquear' => $this->autorizacionServicio->puede($usuarioActualId, 'usuarios.bloquear'),
            'puede_asignar_roles' => $this->autorizacionServicio->puede($usuarioActualId, 'roles.asignar'),
            'puede_revocar_roles' => $this->autorizacionServicio->puede($usuarioActualId, 'roles.revocar'),
        ];

        $datos = [
            'titulo' => 'Camargo PMS — Gestión de Usuarios',
            'categoriaActiva' => 'configuracion',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Configuración', 'url' => '#', 'activo' => false],
                ['etiqueta' => 'Usuarios', 'url' => url_ruta('/usuarios'), 'activo' => true],
            ],
            'roles' => $rolesFormateados,
            'capacidades' => $capacidades,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('usuarios/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Retorna el listado paginado de usuarios con filtros aplicados (GET /usuarios/datos).
     *
     * @return Respuesta
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $busqueda = isset($_GET['busqueda']) ? (string) $_GET['busqueda'] : '';
        $estado = isset($_GET['estado']) ? (string) $_GET['estado'] : '';
        $rolId = isset($_GET['rol_id']) && is_numeric($_GET['rol_id']) ? (int) $_GET['rol_id'] : null;
        $pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, min(100, (int) $_GET['limite'])) : 15;

        $filtros = [];
        if (trim($busqueda) !== '') {
            $filtros['busqueda'] = trim($busqueda);
        }
        if (in_array(strtoupper(trim($estado)), ['ACTIVO', 'INACTIVO', 'BLOQUEADO'], true)) {
            $filtros['estado'] = strtoupper(trim($estado));
        }
        if ($rolId !== null && $rolId > 0) {
            $filtros['rol_id'] = $rolId;
        }

        try {
            $resultado = $this->usuarioServicio->listarUsuarios($filtros, $limite, $pagina);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $resultado,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar listado de usuarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista personas naturales activas disponibles para vincular a una nueva cuenta (GET /usuarios/personas-disponibles).
     *
     * @return Respuesta
     */
    public function personasDisponibles(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $busqueda = isset($_GET['q']) ? (string) $_GET['q'] : '';
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, min(50, (int) $_GET['limite'])) : 20;

        try {
            $personas = $this->usuarioServicio->obtenerPersonasDisponibles($busqueda, $limite);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $personas,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al consultar personas disponibles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el detalle completo de un usuario sin exponer secretos (GET /usuarios/{id}).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function detalle(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        try {
            $detalle = $this->usuarioServicio->obtenerDetalleUsuario($usuarioId);
            if ($detalle === null) {
                return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Usuario no encontrado.'], 404);
            }

            // Añadir lista de roles activos del sistema para interfaz de asignación
            $rolesActivos = $this->rolServicio->listarRolesActivos();
            $detalle['roles_disponibles'] = array_map(static function ($r) {
                return [
                    'id' => (int) $r->obtenerId(),
                    'codigo' => $r->obtenerCodigo(),
                    'nombre' => $r->obtenerNombre(),
                    'es_superadministrador' => $r->esSuperadministrador(),
                ];
            }, $rolesActivos);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $detalle,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar detalle del usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la creación de un nuevo usuario humano (POST /usuarios).
     *
     * @return Respuesta
     */
    public function crear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $contrasena = isset($payload['contrasena']) ? (string) $payload['contrasena'] : '';
        $confirmar = isset($payload['confirmar_contrasena']) ? (string) $payload['confirmar_contrasena'] : '';

        if ($contrasena !== $confirmar) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La confirmación de la contraseña no coincide.',
                'errores' => ['confirmar_contrasena' => 'Las contraseñas ingresadas no coinciden.'],
            ], 422);
        }

        $rolInicialId = isset($payload['rol_id']) && is_numeric($payload['rol_id']) && (int) $payload['rol_id'] > 0
            ? (int) $payload['rol_id']
            : null;

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $creadoPor = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $nuevoUsuario = $this->usuarioServicio->crearUsuario($payload, $rolInicialId, $creadoPor);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Cuenta de usuario '{$nuevoUsuario->obtenerNombreUsuario()}' creada exitosamente.",
                'datos' => [
                    'id' => (int) $nuevoUsuario->obtenerId(),
                    'nombre_usuario' => $nuevoUsuario->obtenerNombreUsuario(),
                    'estado' => $nuevoUsuario->obtenerEstado(),
                ],
            ], 201);
        } catch (UsuarioDuplicadoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al crear usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alterna o asigna el estado administrativo del usuario (PATCH|POST /usuarios/{id}/estado).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function cambiarEstado(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevoEstado = isset($payload['estado']) ? strtoupper(trim((string) $payload['estado'])) : '';
        if (!in_array($nuevoEstado, UsuarioServicio::ESTADOS_VALIDOS, true)) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => "El estado especificado no es válido. Estados admitidos: ACTIVO, INACTIVO, BLOQUEADO.",
            ], 422);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutadoPor = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->usuarioServicio->cambiarEstado($usuarioId, $nuevoEstado, $ejecutadoPor);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Estado del usuario actualizado a '{$nuevoEstado}' exitosamente.",
                'datos' => [
                    'id' => $usuarioId,
                    'estado' => $nuevoEstado,
                ],
            ], 200);
        } catch (UltimoSuperadministradorExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al actualizar estado del usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa el restablecimiento administrativo de contraseña (POST /usuarios/{id}/restablecer-clave).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function restablecerClave(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevaContrasena = isset($payload['nueva_contrasena']) ? (string) $payload['nueva_contrasena'] : '';
        $confirmar = isset($payload['confirmar_contrasena'])
            ? (string) $payload['confirmar_contrasena']
            : (isset($payload['confirmar_nueva_contrasena']) ? (string) $payload['confirmar_nueva_contrasena'] : '');

        if ($nuevaContrasena !== $confirmar) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'La confirmación de la nueva contraseña no coincide.',
                'errores' => ['confirmar_contrasena' => 'Las contraseñas ingresadas no coinciden.'],
            ], 422);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutadoPor = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->usuarioServicio->restablecerContrasena($usuarioId, $nuevaContrasena, $ejecutadoPor);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Contraseña restablecida exitosamente. Las sesiones activas del usuario fueron revocadas.',
                'datos' => ['id' => $usuarioId],
            ], 200);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al restablecer contraseña: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asigna un rol a un usuario (POST /usuarios/{id}/roles).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function asignarRol(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $rolId = isset($payload['rol_id']) && is_numeric($payload['rol_id']) ? (int) $payload['rol_id'] : 0;
        if ($rolId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Debe seleccionar un rol válido.'], 422);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $asignadoPor = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->rolServicio->asignarRolAUsuario($usuarioId, $rolId, $asignadoPor);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Rol asignado al usuario exitosamente.',
                'datos' => [
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolId,
                ],
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al asignar rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoca un rol asignado a un usuario (DELETE|POST /usuarios/{id}/roles/{rolId} o POST /usuarios/{id}/roles/revocar).
     *
     * @param string|int $id
     * @param string|int|null $rolId
     * @return Respuesta
     */
    public function revocarRol(string|int $id, string|int|null $rolId = null): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $targetRolId = $rolId !== null ? (int) $rolId : (isset($payload['rol_id']) ? (int) $payload['rol_id'] : 0);
        if ($targetRolId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de rol inválido.'], 422);
        }

        try {
            $this->rolServicio->revocarRolDeUsuario($usuarioId, $targetRolId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Rol revocado del usuario exitosamente.',
                'datos' => [
                    'usuario_id' => $usuarioId,
                    'rol_id' => $targetRolId,
                ],
            ], 200);
        } catch (UltimoSuperadministradorExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al revocar rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna las sesiones activas de un usuario sin exponer secretos (GET /usuarios/{id}/sesiones).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function listarSesiones(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        try {
            $detalle = $this->usuarioServicio->obtenerDetalleUsuario($usuarioId);
            if ($detalle === null) {
                return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Usuario no encontrado.'], 404);
            }

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $detalle['sesiones'],
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al listar sesiones del usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cierra una sesión activa específica de un usuario (DELETE|POST /usuarios/{id}/sesiones/{sesionId}).
     *
     * @param string|int $id
     * @param string|int $sesionId
     * @return Respuesta
     */
    public function cerrarSesion(string|int $id, string|int $sesionId): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        $sesionTargetId = (int) $sesionId;

        if ($usuarioId <= 0 || $sesionTargetId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificadores inválidos.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutadoPor = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->usuarioServicio->cerrarSesion($sesionTargetId, $usuarioId, $ejecutadoPor);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Sesión cerrada exitosamente.',
                'datos' => [
                    'usuario_id' => $usuarioId,
                    'sesion_id' => $sesionTargetId,
                ],
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al cerrar sesión: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cierra todas las sesiones activas de un usuario (POST /usuarios/{id}/sesiones/cerrar-todas).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function cerrarTodasSesiones(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioId = (int) $id;
        if ($usuarioId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de usuario inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutadoPor = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $totalCerradas = $this->usuarioServicio->cerrarTodasSesiones($usuarioId, $ejecutadoPor);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Se cerraron {$totalCerradas} sesiones activas del usuario exitosamente.",
                'datos' => [
                    'usuario_id' => $usuarioId,
                    'sesiones_cerradas' => $totalCerradas,
                ],
            ], 200);
        } catch (EntidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al cerrar todas las sesiones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el payload de la petición parseando JSON o $_POST según corresponda.
     *
     * @return array<string, mixed>
     */
    private function obtenerPayload(): array
    {
        $tipoContenido = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipoContenido, 'application/json')) {
            $cuerpo = file_get_contents('php://input');
            if ($cuerpo !== false && trim($cuerpo) !== '') {
                $decodificado = json_decode($cuerpo, true);
                if (is_array($decodificado)) {
                    return $decodificado;
                }
            }
        }

        return $_POST;
    }

    /**
     * Valida el token CSRF presente en el payload o cabeceras HTTP.
     *
     * @param array<string, mixed> $payload
     * @return bool
     */
    private function validarCsrf(array $payload): bool
    {
        $token = $payload['_csrf_token']
            ?? $payload['csrf_token']
            ?? $payload['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if ($token === null || !is_string($token) || trim($token) === '') {
            return false;
        }

        return $this->csrfServicio->validarToken($token);
    }
}
