<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\CapacidadExcedidaExcepcion;
use CamargoPMS\Excepciones\ConflictoEstadiaExcepcion;
use CamargoPMS\Excepciones\EstadiaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\EstadoEstadiaInvalidoExcepcion;
use CamargoPMS\Excepciones\EstadoReservaInvalidoExcepcion;
use CamargoPMS\Excepciones\HuespedInvalidoExcepcion;
use CamargoPMS\Excepciones\IntervaloInvalidoExcepcion;
use CamargoPMS\Excepciones\ReservaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\EstadiaServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el módulo operativo de recepción, check-in, estadías y huéspedes (ESTADÍAS-1).
 *
 * Principios vinculantes:
 * - RESERVA != ESTADÍA != ARRENDAMIENTO.
 * - Validación de permisos RBAC ('estadias.ver', 'estadias.checkin', 'estadias.checkout', etc.).
 * - Respuestas JSON asíncronas para la interfaz Alina.
 * - Trazabilidad D-061 y seguridad CSRF estricta en mutaciones.
 */
class EstadiaControlador
{
    private Vista $vista;
    private EstadiaServicio $estadiaServicio;
    private ReservaRepositorio $reservaRepo;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private PersonaRepositorio $personaRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?EstadiaServicio $estadiaServicio = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $pdo = BaseDatos::conexion();
        $this->vista = $vista ?? new Vista();
        $this->estadiaServicio = $estadiaServicio ?? new EstadiaServicio($pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Renderiza el tablero de recepción y catálogo operativo de estadías (GET /estadias).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de estadías.',
            ], 'error'), 403);
        }

        $capacidades = [
            'puede_ver' => true,
            'puede_checkin' => $this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkin'),
            'puede_checkout' => $this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkout'),
            'puede_huespedes' => $this->autorizacionServicio->puede($usuarioActualId, 'estadias.huespedes'),
            'puede_anular' => $this->autorizacionServicio->puede($usuarioActualId, 'estadias.anular'),
        ];

        // Propiedades activas para filtros
        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO', null, 100, 0);
        $propiedadesFormateadas = array_map(static function ($p) {
            return [
                'id' => (int) $p->obtenerId(),
                'codigo' => $p->obtenerCodigo(),
                'nombre' => $p->obtenerNombre(),
            ];
        }, $propiedades);

        $datos = [
            'titulo' => 'Camargo PMS — Ocupación Física y Estadías',
            'categoriaActiva' => 'reservas',
            'subcategoriaActiva' => 'estadias_catalogo',
            'migasPan' => [
                ['etiqueta' => 'Panel', 'url' => url_ruta('/'), 'activo' => false],
                ['etiqueta' => 'Reservas', 'url' => url_ruta('/reservas'), 'activo' => false],
                ['etiqueta' => 'Estadías (Check-in)', 'url' => url_ruta('/estadias'), 'activo' => true],
            ],
            'propiedades' => $propiedadesFormateadas,
            'capacidades' => $capacidades,
            'csrf_token' => $this->csrfServicio->obtenerToken(),
        ];

        return new Respuesta($this->vista->renderizar('estadias/index', $datos, 'principal'), 200);
    }

    /**
     * Endpoint JSON para listado paginado y filtrado de estadías (GET /estadias/datos).
     */
    public function datos(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para consultar estadías.'], 403);
        }

        try {
            $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
            $limite = max(1, min(100, (int) ($_GET['limite'] ?? 20)));

            $filtros = [];
            if (!empty($_GET['estado'])) {
                $filtros['estado'] = trim((string) $_GET['estado']);
            }
            if (!empty($_GET['propiedad_id'])) {
                $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
            }
            if (!empty($_GET['fecha_entrada'])) {
                $filtros['fecha_entrada'] = trim((string) $_GET['fecha_entrada']);
            }
            if (!empty($_GET['fecha_salida_prevista'])) {
                $filtros['fecha_salida_prevista'] = trim((string) $_GET['fecha_salida_prevista']);
            }
            if (!empty($_GET['termino'])) {
                $filtros['termino'] = trim((string) $_GET['termino']);
            }

            $resultado = $this->estadiaServicio->listarEstadias($filtros, $pagina, $limite);

            return Respuesta::json([
                'ok' => true,
                'datos' => $resultado['items'],
                'paginacion' => [
                    'total' => $resultado['total'],
                    'pagina_actual' => $resultado['pagina'],
                    'limite' => $resultado['por_pagina'],
                    'total_paginas' => $resultado['total_paginas'],
                ],
            ]);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error al consultar estadías: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint JSON para consultar el detalle completo de una estadía (GET /estadias/{id}).
     *
     * @param string|int|array $id
     */
    public function detalle(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para ver estadías.'], 403);
        }

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        try {
            $estadia = $this->estadiaServicio->obtenerEstadia($identificador);

            return Respuesta::json([
                'ok' => true,
                'datos' => $estadia,
            ]);
        } catch (EstadiaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener la estadía: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint JSON para realizar el check-in físico de una unidad (POST /estadias/checkin).
     */
    public function checkin(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkin')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para realizar check-in.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $estadia = $this->estadiaServicio->realizarCheckin($datos, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Check-in realizado exitosamente para la estadía '{$estadia->obtenerCodigo()}'.",
                'datos' => $estadia->haciaArreglo(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores(),
            ], 422);
        } catch (HuespedInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'campo' => $e->obtenerCampo(),
            ], 422);
        } catch (CapacidadExcedidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'capacidad' => [
                    'huespedes' => $e->obtenerCantidadHuespedes(),
                    'capacidad_maxima' => $e->obtenerCapacidadMaxima(),
                    'unidad_id' => $e->obtenerUnidadId(),
                ],
            ], 422);
        } catch (EstadoReservaInvalidoExcepcion|IntervaloInvalidoExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (ConflictoEstadiaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'reserva_unidad_id' => $e->obtenerReservaUnidadId(),
            ], 409);
        } catch (ReservaNoEncontradaExcepcion|UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => 'Error inesperado al realizar check-in: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint JSON para registrar el check-out de una estadía (POST /estadias/{id}/checkout).
     *
     * @param string|int|array $id
     */
    public function checkout(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkout')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para registrar check-out.'], 403);
        }

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $observaciones = isset($datos['observaciones_checkout']) ? (string) $datos['observaciones_checkout'] : null;

        try {
            $estadia = $this->estadiaServicio->realizarCheckout($identificador, $observaciones, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Check-out completado exitosamente para la estadía '{$estadia->obtenerCodigo()}'.",
                'datos' => $estadia->haciaArreglo(),
            ]);
        } catch (EstadiaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoEstadiaInvalidoExcepcion|ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al registrar check-out: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint JSON para anular operativamente una estadía en curso (POST /estadias/{id}/anular).
     *
     * @param string|int|array $id
     */
    public function anular(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.anular')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para anular estadías.'], 403);
        }

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $motivo = isset($datos['motivo_anulacion']) ? (string) $datos['motivo_anulacion'] : '';

        try {
            $estadia = $this->estadiaServicio->anularEstadia($identificador, $motivo, $usuarioActualId);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Estadía '{$estadia->obtenerCodigo()}' anulada correctamente.",
                'datos' => $estadia->haciaArreglo(),
            ]);
        } catch (EstadiaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoEstadiaInvalidoExcepcion|ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al anular la estadía: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint JSON para actualizar la lista de huéspedes de una estadía en curso (POST /estadias/{id}/huespedes).
     *
     * @param string|int|array $id
     */
    public function actualizarHuespedes(string|int|array $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.huespedes')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para modificar huéspedes.'], 403);
        }

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $huespedesRaw = $datos['huespedes'] ?? [];
        if (!is_array($huespedesRaw)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'La lista de huéspedes debe ser un arreglo.'], 422);
        }

        try {
            $huespedesActualizados = $this->estadiaServicio->actualizarHuespedes($identificador, $huespedesRaw, $usuarioActualId);
            $huespedesArreglo = array_map(fn($h) => $h->haciaArreglo(), $huespedesActualizados);

            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Lista de huéspedes actualizada correctamente.',
                'datos' => $huespedesArreglo,
                'huespedes' => $huespedesArreglo,
            ]);
        } catch (EstadiaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoEstadiaInvalidoExcepcion|HuespedInvalidoExcepcion|ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (CapacidadExcedidaExcepcion $e) {
            return Respuesta::json([
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'capacidad' => [
                    'huespedes' => $e->obtenerCantidadHuespedes(),
                    'capacidad_maxima' => $e->obtenerCapacidadMaxima(),
                ],
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al actualizar huéspedes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint JSON para consultar las unidades elegibles para check-in de una reserva confirmada (GET /estadias/elegibles/{reservaId}).
     *
     * @param string|int|array $reservaId
     */
    public function elegibles(string|int|array $reservaId): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkin')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para consultar elegibilidad de unidades.'], 403);
        }

        $id = (int) (is_array($reservaId) ? ($reservaId['id'] ?? 0) : $reservaId);

        try {
            $unidades = $this->estadiaServicio->obtenerUnidadesElegiblesParaCheckin($id);

            return Respuesta::json([
                'ok' => true,
                'reserva_id' => $id,
                'unidades' => $unidades,
            ]);
        } catch (ReservaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al consultar unidades elegibles: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint JSON para consultar el resumen operativo de una reserva (GET /estadias/resumen-reserva/{reservaId}).
     *
     * @param string|int|array $reservaId
     */
    public function resumenReserva(string|int|array $reservaId): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para ver resumen operativo.'], 403);
        }

        $id = (int) (is_array($reservaId) ? ($reservaId['id'] ?? 0) : $reservaId);

        try {
            $resumen = $this->estadiaServicio->obtenerResumenOperativoReserva($id);

            return Respuesta::json([
                'ok' => true,
                'resumen' => $resumen,
            ]);
        } catch (ReservaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al consultar resumen: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint JSON auxiliar para alimentar selectores y autocompletado en el modal de check-in (GET /estadias/auxiliares).
     */
    public function auxiliares(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'estadias.ver')
            && !$this->autorizacionServicio->puede($usuarioActualId, 'estadias.checkin')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para consultar datos auxiliares.'], 403);
        }

        try {
            $pdo = BaseDatos::conexion();

            // Reservas confirmadas con al menos una unidad pendiente de check-in
            $sqlReservas = "SELECT r.id, r.codigo, r.fecha_entrada, r.fecha_salida,
                                   TRIM(CONCAT(COALESCE(p.nombres, ''), ' ', COALESCE(p.apellido_paterno, ''), ' ', COALESCE(p.apellido_materno, ''))) AS titular_nombre,
                                   COUNT(ru.id) AS total_unidades,
                                   COUNT(e.id) AS total_estadias
                            FROM reservas r
                            INNER JOIN personas p ON r.persona_titular_id = p.id
                            INNER JOIN reserva_unidades ru ON r.id = ru.reserva_id
                            LEFT JOIN estadias e ON ru.id = e.reserva_unidad_id
                            WHERE r.estado = 'CONFIRMADA'
                            GROUP BY r.id, r.codigo, r.fecha_entrada, r.fecha_salida, titular_nombre
                            HAVING total_unidades > total_estadias
                            ORDER BY r.fecha_entrada ASC
                            LIMIT 100";
            $reservas = $pdo->query($sqlReservas)->fetchAll(PDO::FETCH_ASSOC);

            // Personas activas para huéspedes
            $sqlPersonas = "SELECT p.id,
                                   TRIM(CONCAT(COALESCE(p.nombres, ''), ' ', COALESCE(p.apellido_paterno, ''), ' ', COALESCE(p.apellido_materno, ''))) AS nombre_completo,
                                   COALESCE(d.numero_documento, '') AS documento
                            FROM personas p
                            LEFT JOIN personas_documentos d ON p.id = d.persona_id AND d.estado = 'ACTIVO'
                            WHERE p.estado = 'ACTIVO'
                            ORDER BY p.nombres ASC
                            LIMIT 300";
            $personas = $pdo->query($sqlPersonas)->fetchAll(PDO::FETCH_ASSOC);

            return Respuesta::json([
                'ok' => true,
                'reservas' => $reservas,
                'reservas_confirmadas' => $reservas,
                'personas' => $personas,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cargar auxiliares de estadía: ' . $e->getMessage()], 500);
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
