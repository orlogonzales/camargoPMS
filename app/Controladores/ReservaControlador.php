<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoReservaInvalidoExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\ReservaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ReservaServicio;
use CamargoPMS\Servicios\SesionServicio;
use Throwable;

/**
 * Controlador de gestión comercial de reservas directas (RESERVAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != DISPONIBILIDAD != INVENTARIO != ESTANCIA != PAGO.
 * - RBAC vinculante: reservas.ver, reservas.crear, reservas.confirmar, reservas.cancelar, reservas.expirar.
 * - Conflicto de disponibilidad traducido a HTTP 409 Conflict.
 * - Invalidez de estado traducida a HTTP 422 Unprocessable Entity.
 * - Validación CSRF en mutaciones POST/PUT.
 */
class ReservaControlador
{
    private ReservaServicio $reservaServicio;
    private PropiedadRepositorio $propiedadRepositorio;
    private UnidadRepositorio $unidadRepositorio;
    private PersonaRepositorio $personaRepositorio;
    private SesionServicio $sesionServicio;
    private CsrfServicio $csrfServicio;
    private AutorizacionServicio $autorizacionServicio;
    private Vista $vista;

    public function __construct(
        ?ReservaServicio $reservaServicio = null,
        ?PropiedadRepositorio $propiedadRepositorio = null,
        ?UnidadRepositorio $unidadRepositorio = null,
        ?PersonaRepositorio $personaRepositorio = null,
        ?SesionServicio $sesionServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?Vista $vista = null
    ) {
        $this->reservaServicio = $reservaServicio ?? new ReservaServicio();
        $this->propiedadRepositorio = $propiedadRepositorio ?? new PropiedadRepositorio();
        $this->unidadRepositorio = $unidadRepositorio ?? new UnidadRepositorio();
        $this->personaRepositorio = $personaRepositorio ?? new PersonaRepositorio();
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->vista = $vista ?? new Vista();
    }

    /**
     * Renderiza la vista principal del catálogo de reservas (GET /reservas).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el catálogo de reservas.',
            ], 'error'), 403);
        }

        $capacidades = [
            'puede_ver' => true,
            'puede_crear' => $this->autorizacionServicio->puede($usuarioActualId, 'reservas.crear'),
            'puede_confirmar' => $this->autorizacionServicio->puede($usuarioActualId, 'reservas.confirmar'),
            'puede_cancelar' => $this->autorizacionServicio->puede($usuarioActualId, 'reservas.cancelar'),
            'puede_expirar' => $this->autorizacionServicio->puede($usuarioActualId, 'reservas.expirar'),
        ];

        // Propiedades activas para filtros y selector de modal
        $propiedades = $this->propiedadRepositorio->listar(null, 'ACTIVO', null, 100, 0);
        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
            ];
        }, $propiedades);

        $datos = [
            'titulo' => 'Camargo PMS — Gestión de Reservas',
            'categoriaActiva' => 'reservas',
            'subcategoriaActiva' => 'reservas_catalogo',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Reservas', 'url' => url_ruta('/reservas'), 'activo' => true],
            ],
            'propiedades' => $propiedadesFormateadas,
            'capacidades' => $capacidades,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        return new Respuesta($this->vista->renderizar('reservas/index', $datos, 'principal'), 200);
    }

    /**
     * Endpoint JSON para obtener listado paginado y filtrado de reservas (GET /reservas/datos).
     */
    public function datos(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para consultar reservas.'], 403);
        }

        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $limite = max(1, min(100, (int) ($_GET['limite'] ?? 20)));
        $offset = ($pagina - 1) * $limite;

        $filtros = [];
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = trim((string) $_GET['estado']);
        }
        if (!empty($_GET['canal'])) {
            $filtros['canal'] = trim((string) $_GET['canal']);
        }
        if (!empty($_GET['propiedad_id'])) {
            $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        }
        if (!empty($_GET['unidad_id'])) {
            $filtros['unidad_id'] = (int) $_GET['unidad_id'];
        }
        if (!empty($_GET['fecha_desde'])) {
            $filtros['fecha_desde'] = trim((string) $_GET['fecha_desde']);
        }
        if (!empty($_GET['fecha_hasta'])) {
            $filtros['fecha_hasta'] = trim((string) $_GET['fecha_hasta']);
        }
        if (!empty($_GET['busqueda'])) {
            $filtros['busqueda'] = trim((string) $_GET['busqueda']);
        }

        try {
            $resultado = $this->reservaServicio->listarReservas($filtros, $limite, $offset);

            $itemsFormateados = array_map(static function ($reserva) {
                return $reserva->haciaArreglo();
            }, $resultado['items']);

            $totalPaginas = (int) ceil($resultado['total'] / $limite);

            return Respuesta::json([
                'ok' => true,
                'datos' => $itemsFormateados,
                'paginacion' => [
                    'total' => $resultado['total'],
                    'pagina_actual' => $pagina,
                    'limite' => $limite,
                    'total_paginas' => $totalPaginas,
                ],
            ]);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al consultar reservas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint JSON para consultar el detalle completo de una reserva (GET /reservas/{id}).
     *
     * @param string|int|array $id
     */
    public function detalle(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para ver reservas.'], 403);
        }

        $identificador = is_array($id) ? ($id['id'] ?? '') : $id;

        try {
            $reserva = $this->reservaServicio->consultarReserva($identificador);

            return Respuesta::json([
                'ok' => true,
                'datos' => $reserva->haciaArreglo(),
            ]);
        } catch (ReservaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener la reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Procesa la creación de una nueva reserva comercial directa (POST /reservas).
     */
    public function crear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.crear')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para registrar reservas.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $reserva = $this->reservaServicio->crearReserva($datos, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Reserva '{$reserva->obtenerCodigo()}' creada exitosamente en estado {$reserva->obtenerEstado()}.",
                'datos' => $reserva->haciaArreglo(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (IntervaloInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'conflicto' => [
                    'unidad_id' => $e->obtenerUnidadId(),
                    'fecha' => $e->obtenerFechaConflicto(),
                ],
            ], 409);
        } catch (UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al crear la reserva: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la confirmación de una reserva (POST /reservas/{id}/confirmar).
     *
     * @param string|int|array $id
     */
    public function confirmar(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.confirmar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para confirmar reservas.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($idInt <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Identificador de reserva inválido.'], 400);
        }

        try {
            $reserva = $this->reservaServicio->confirmarReserva($idInt, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "La reserva '{$reserva->obtenerCodigo()}' fue confirmada exitosamente.",
                'datos' => $reserva->haciaArreglo(),
            ]);
        } catch (ReservaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoReservaInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'estado_actual' => $e->obtenerEstadoActual(),
                'operacion' => $e->obtenerOperacionIntentada(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al confirmar la reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Procesa la cancelación de una reserva (POST /reservas/{id}/cancelar).
     *
     * @param string|int|array $id
     */
    public function cancelar(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.cancelar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para cancelar reservas.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $idInt = is_array($id) ? (int) ($id['id'] ?? 0) : (int) $id;
        if ($idInt <= 0) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Identificador de reserva inválido.'], 400);
        }

        $motivo = (string) ($datos['motivo'] ?? '');

        try {
            $reserva = $this->reservaServicio->cancelarReserva($idInt, $motivo, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "La reserva '{$reserva->obtenerCodigo()}' fue cancelada y su inventario fue liberado.",
                'datos' => $reserva->haciaArreglo(),
            ]);
        } catch (ReservaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoReservaInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'estado_actual' => $e->obtenerEstadoActual(),
            ], 422);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cancelar la reserva: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Procesa la expiración por lotes de reservas pendientes vencidas (POST /reservas/expirar).
     */
    public function expirar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.expirar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para expirar reservas.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $resultado = $this->reservaServicio->expirarReservasPendientes();

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Proceso completado. Se expiraron {$resultado['total_expiradas']} reserva(s) pendiente(s).",
                'datos' => $resultado,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al expirar reservas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Proporciona catálogos auxiliares (personas activas, unidades activas) para la interfaz de creación (GET /reservas/auxiliares).
     */
    public function auxiliares(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'reservas.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        try {
            // Unidades activas
            $unidades = $this->unidadRepositorio->listar(['estado' => 'ACTIVO'], 1, 500);
            $unidadesFormateadas = array_map(static function ($u) {
                return [
                    'id' => (int) $u->obtenerId(),
                    'codigo' => $u->obtenerCodigo(),
                    'nombre' => $u->obtenerNombre(),
                    'propiedad_id' => $u->obtenerPropiedadId(),
                    'propiedad_nombre' => $u->obtenerPropiedadNombre(),
                    'tipo_unidad_nombre' => $u->obtenerTipoUnidadNombre(),
                    'capacidad_personas' => $u->obtenerCapacidadPersonas(),
                ];
            }, $unidades);

            // Personas (titulares potenciales)
            $sqlPersonas = "SELECT p.id,
                                   TRIM(CONCAT(COALESCE(p.nombres, ''), ' ', COALESCE(p.apellido_paterno, ''), ' ', COALESCE(p.apellido_materno, ''))) AS nombre_completo,
                                   COALESCE(d.numero_documento, '') AS documento
                            FROM personas p
                            LEFT JOIN personas_documentos d ON p.id = d.persona_id AND d.estado = 'ACTIVO'
                            ORDER BY p.nombres ASC
                            LIMIT 300";
            $stmt = \CamargoPMS\Nucleo\BaseDatos::conexion()->query($sqlPersonas);
            $personas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return Respuesta::json([
                'ok' => true,
                'unidades' => $unidadesFormateadas,
                'personas' => $personas,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cargar auxiliares: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Extrae el payload de la petición (JSON o Form-data).
     *
     * @return array<string, mixed>
     */
    private function obtenerCuerpoPeticion(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $contenido = file_get_contents('php://input');
            $decodificado = json_decode($contenido, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        return $_POST;
    }

    /**
     * Valida el token CSRF presente en el payload o cabeceras HTTP.
     *
     * @param array<string, mixed> $payload
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
