<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\PermisoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\RolDuplicadoExcepcion;
use CamargoPMS\Excepciones\RolNoEncontradoExcepcion;
use CamargoPMS\Excepciones\RolProtegidoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\RolServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador administrativo y de endpoints JSON para la gestión visual del
 * catálogo de Roles, Matriz de Permisos por módulo y auditoría transversal.
 *
 * Principios vinculantes:
 * - ROL DE AUTORIZACIÓN ≠ CARGO LABORAL
 * - ACTOR ≠ USUARIO (D-061)
 * - MENÚ ≠ AUTORIZACIÓN
 * - Protección inviolable de SUPERADMINISTRADOR.
 */
class RolControlador
{
    private RolServicio $rolServicio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?RolServicio $rolServicio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->rolServicio = $rolServicio ?? new RolServicio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la vista principal de administración de roles y permisos (GET /configuracion/roles).
     *
     * @return Respuesta
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        // Capacidades del usuario autenticado para adaptar la experiencia visual (UX)
        $capacidades = [
            'puede_crear' => $this->autorizacionServicio->puede($usuarioActualId, 'roles.crear'),
            'puede_editar' => $this->autorizacionServicio->puede($usuarioActualId, 'roles.editar'),
            'puede_asignar' => $this->autorizacionServicio->puede($usuarioActualId, 'roles.asignar'),
            'puede_revocar' => $this->autorizacionServicio->puede($usuarioActualId, 'roles.revocar'),
            'puede_ver_permisos' => $this->autorizacionServicio->puede($usuarioActualId, 'permisos.ver'),
        ];

        $datos = [
            'titulo' => 'Camargo PMS — Gestión de Roles y Permisos',
            'categoriaActiva' => 'configuracion',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Configuración', 'url' => '#', 'activo' => false],
                ['etiqueta' => 'Roles y Permisos', 'url' => url_ruta('/configuracion/roles'), 'activo' => true],
            ],
            'capacidades' => $capacidades,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        $html = $this->vista->renderizar('roles/index', $datos, 'principal');

        return new Respuesta($html, 200);
    }

    /**
     * Retorna el listado de roles con conteos agregados en formato JSON (GET /configuracion/roles/datos).
     *
     * @return Respuesta
     */
    public function datosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $busqueda = isset($_GET['busqueda']) ? (string) $_GET['busqueda'] : null;
        $estado = isset($_GET['estado']) ? (string) $_GET['estado'] : null;

        try {
            $roles = $this->rolServicio->listarRolesConConteos($busqueda, $estado);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $roles,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar listado de roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el catálogo completo de permisos agrupados por módulo funcional (GET /configuracion/roles/permisos-catalogo).
     *
     * @return Respuesta
     */
    public function catalogoPermisosJson(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        try {
            $agrupados = $this->rolServicio->listarPermisosAgrupados();

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $agrupados,
            ], 200);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar catálogo de permisos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el detalle consolidado de un rol específico (GET /configuracion/roles/{id}).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function detalle(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $rolId = (int) $id;
        if ($rolId <= 0) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Identificador de rol inválido.',
            ], 400);
        }

        try {
            $detalle = $this->rolServicio->obtenerDetalleRol($rolId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'datos' => $detalle,
            ], 200);
        } catch (RolNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error al recuperar detalle del rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registra un nuevo rol de autorización en el sistema (POST /configuracion/roles).
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

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $rol = $this->rolServicio->crearRol($payload, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Rol '{$rol->obtenerNombre()}' creado exitosamente.",
                'datos' => $rol->aArreglo(),
            ], 201);
        } catch (RolDuplicadoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al crear el rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza los datos informativos de un rol existente (POST/PUT /configuracion/roles/{id}).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function actualizar(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $rolId = (int) $id;
        if ($rolId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de rol inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $rol = $this->rolServicio->actualizarRol($rolId, $payload, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Rol '{$rol->obtenerNombre()}' actualizado exitosamente.",
                'datos' => $rol->aArreglo(),
            ], 200);
        } catch (RolNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (RolProtegidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 403);
        } catch (RolDuplicadoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 409);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al actualizar el rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Modifica el estado operativo (ACTIVO/INACTIVO) de un rol (POST/PATCH /configuracion/roles/{id}/estado).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function cambiarEstado(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $rolId = (int) $id;
        if ($rolId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de rol inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevoEstado = isset($payload['nuevo_estado'])
            ? (string) $payload['nuevo_estado']
            : (isset($payload['estado']) ? (string) $payload['estado'] : '');

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->rolServicio->cambiarEstadoRol($rolId, $nuevoEstado, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => "Estado del rol actualizado a {$nuevoEstado}.",
                'datos' => [
                    'rol_id' => $rolId,
                    'nuevo_estado' => strtoupper(trim($nuevoEstado)),
                ],
            ], 200);
        } catch (RolNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (RolProtegidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 403);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al cambiar el estado del rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sincroniza atómicamente la matriz de permisos asociados a un rol (POST/PUT /configuracion/roles/{id}/permisos).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function guardarPermisos(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $rolId = (int) $id;
        if ($rolId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de rol inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $permisoIds = isset($payload['permisos']) && is_array($payload['permisos'])
            ? $payload['permisos']
            : (isset($payload['permiso_ids']) && is_array($payload['permiso_ids']) ? $payload['permiso_ids'] : []);

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $resultado = $this->rolServicio->sincronizarPermisosDeRol($rolId, $permisoIds, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Matriz de permisos actualizada exitosamente.',
                'datos' => $resultado,
            ], 200);
        } catch (RolNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (PermisoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (RolProtegidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 403);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al actualizar permisos del rol: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina físicamente un rol no protegido y sin usuarios asociados (POST/DELETE /configuracion/roles/{id}/eliminar).
     *
     * @param string|int $id
     * @return Respuesta
     */
    public function eliminar(string|int $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $rolId = (int) $id;
        if ($rolId <= 0) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Identificador de rol inválido.'], 400);
        }

        $payload = $this->obtenerPayload();
        if (!$this->validarCsrf($payload)) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => 'Token CSRF inválido o ausente.'], 403);
        }

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $ejecutorId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : null;

        try {
            $this->rolServicio->eliminarRol($rolId, $ejecutorId);

            return Respuesta::json([
                'ok' => true,
                'exito' => true,
                'mensaje' => 'Rol eliminado exitosamente.',
                'datos' => ['rol_id' => $rolId],
            ], 200);
        } catch (RolNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 404);
        } catch (RolProtegidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'exito' => false, 'error' => $e->getMessage()], 403);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'exito' => false,
                'error' => 'Error interno al eliminar el rol: ' . $e->getMessage(),
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
