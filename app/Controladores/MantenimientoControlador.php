<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoMantenimientoInvalidoExcepcion;
use CamargoPMS\Excepciones\IncidenciaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\OrdenTrabajoNoEncontradaExcepcion;
use CamargoPMS\Excepciones\PropiedadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ProveedorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ProveedorRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\MantenimientoServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el módulo de Mantenimiento Preventivo, Correctivo e Incidencias Técnicas (MANTENIMIENTO-1 / D-077).
 */
class MantenimientoControlador
{
    private Vista $vista;
    private MantenimientoServicio $mantenimientoServicio;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private PersonaRepositorio $personaRepo;
    private ColaboradorRepositorio $colaboradorRepo;
    private ProveedorRepositorio $proveedorRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?MantenimientoServicio $mantenimientoServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?ColaboradorRepositorio $colaboradorRepo = null,
        ?ProveedorRepositorio $proveedorRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->vista = $vista ?? new Vista();
        $this->mantenimientoServicio = $mantenimientoServicio ?? new MantenimientoServicio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->colaboradorRepo = $colaboradorRepo ?? new ColaboradorRepositorio($this->pdo);
        $this->proveedorRepo = $proveedorRepo ?? new ProveedorRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Renderiza la vista principal del módulo de Mantenimiento.
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de mantenimiento.',
            ], 'error'), 403);
        }

        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO');
        $unidades = $this->unidadRepo->listar(['estado' => 'ACTIVO'], 1, 100);
        $personas = $this->personaRepo->listarActivos(100, 0);
        $colaboradores = $this->colaboradorRepo->listarActivos(100, 0);
        $proveedores = $this->proveedorRepo->listar(['estado' => 'ACTIVO']);
        $csrfToken = $this->csrfServicio->obtenerToken();

        $capacidades = [
            'puede_ver' => true,
            'puede_reportar' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.incidencias.reportar'),
            'puede_gestionar_incidencias' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.incidencias.gestionar'),
            'puede_crear_ordenes' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ordenes.crear'),
            'puede_programar_ordenes' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ordenes.programar'),
            'puede_ejecutar_ordenes' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ordenes.ejecutar'),
            'puede_cerrar_ordenes' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ordenes.cerrar'),
            'puede_cancelar_ordenes' => $this->autorizacionServicio->puede($usuarioActualId, 'mantenimiento.ordenes.cancelar'),
        ];

        $html = $this->vista->renderizar('mantenimiento/index', [
            'titulo' => 'Mantenimiento e Incidencias Técnicas',
            'subtitulo' => 'Gestión técnica, control de desperfectos, órdenes de trabajo y bloqueo operativo de unidades',
            'propiedades' => $propiedades,
            'unidades' => $unidades,
            'personas' => $personas,
            'colaboradores' => $colaboradores,
            'proveedores' => $proveedores,
            'csrf_token' => $csrfToken,
            'capacidades' => $capacidades,
            'usuario_actual' => $usuarioActual,
        ], 'principal');

        return new Respuesta($html);
    }

    // =========================================================================
    // ENDPOINTS API: INCIDENCIAS
    // =========================================================================

    public function listarIncidencias(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $filtros = [];
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = (string) $_GET['estado'];
        }
        if (!empty($_GET['propiedad_id'])) {
            $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        }
        if (!empty($_GET['unidad_id'])) {
            $filtros['unidad_id'] = (int) $_GET['unidad_id'];
        }
        if (!empty($_GET['severidad'])) {
            $filtros['severidad'] = (string) $_GET['severidad'];
        }
        if (!empty($_GET['categoria'])) {
            $filtros['categoria'] = (string) $_GET['categoria'];
        }
        if (!empty($_GET['q'])) {
            $filtros['busqueda'] = (string) $_GET['q'];
        }

        $incidencias = $this->mantenimientoServicio->listarIncidencias($filtros);
        $datos = array_map(static fn($i) => $i->aArreglo(), $incidencias);

        return Respuesta::json(['ok' => true, 'datos' => $datos]);
    }

    public function obtenerIncidencia(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        try {
            $incidencia = $this->mantenimientoServicio->obtenerIncidencia($id);
            return Respuesta::json(['ok' => true, 'datos' => $incidencia->aArreglo()]);
        } catch (IncidenciaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        }
    }

    public function reportarIncidencia(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.incidencias.reportar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para reportar incidencias.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $incidencia = $this->mantenimientoServicio->reportarIncidencia($datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Incidencia reportada exitosamente.',
                'datos' => $incidencia->aArreglo(),
            ], 201);
        } catch (ValidacionExcepcion | PropiedadNoEncontradaExcepcion | UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al reportar incidencia: ' . $e->getMessage()], 500);
        }
    }

    public function evaluarIncidencia(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.incidencias.gestionar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para gestionar incidencias.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $incidencia = $this->mantenimientoServicio->evaluarIncidencia($id, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Incidencia en evaluación.', 'datos' => $incidencia->aArreglo()]);
        } catch (IncidenciaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoMantenimientoInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function resolverIncidenciaDirecta(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.incidencias.gestionar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para resolver incidencias.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $motivo = (string) ($datos['motivo_cierre'] ?? '');
        $actorId = $this->resolverActorId($usuario);

        try {
            $incidencia = $this->mantenimientoServicio->resolverIncidenciaDirecta($id, $motivo, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Incidencia resuelta directamente.', 'datos' => $incidencia->aArreglo()]);
        } catch (IncidenciaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoMantenimientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function desestimarIncidencia(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.incidencias.gestionar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para desestimar incidencias.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $motivo = (string) ($datos['motivo_cierre'] ?? '');
        $actorId = $this->resolverActorId($usuario);

        try {
            $incidencia = $this->mantenimientoServicio->desestimarIncidencia($id, $motivo, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Incidencia desestimada.', 'datos' => $incidencia->aArreglo()]);
        } catch (IncidenciaNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoMantenimientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // ENDPOINTS API: ÓRDENES DE TRABAJO
    // =========================================================================

    public function listarOrdenes(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $filtros = [];
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = (string) $_GET['estado'];
        }
        if (!empty($_GET['propiedad_id'])) {
            $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        }
        if (!empty($_GET['unidad_id'])) {
            $filtros['unidad_id'] = (int) $_GET['unidad_id'];
        }
        if (!empty($_GET['tipo'])) {
            $filtros['tipo'] = (string) $_GET['tipo'];
        }
        if (!empty($_GET['prioridad'])) {
            $filtros['prioridad'] = (string) $_GET['prioridad'];
        }
        if (!empty($_GET['tipo_asignacion'])) {
            $filtros['tipo_asignacion'] = (string) $_GET['tipo_asignacion'];
        }
        if (isset($_GET['requiere_bloqueo']) && $_GET['requiere_bloqueo'] !== '') {
            $filtros['requiere_bloqueo'] = (int) $_GET['requiere_bloqueo'];
        }
        if (!empty($_GET['q'])) {
            $filtros['busqueda'] = (string) $_GET['q'];
        }

        $ordenes = $this->mantenimientoServicio->listarOrdenes($filtros);
        $datos = array_map(static fn($o) => $o->aArreglo(), $ordenes);

        return Respuesta::json(['ok' => true, 'datos' => $datos]);
    }

    public function obtenerOrden(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        try {
            $orden = $this->mantenimientoServicio->obtenerOrden($id);
            $datos = $orden->aArreglo();
            $historial = $this->mantenimientoServicio->obtenerHistorial('ORDEN_TRABAJO', $id);
            $datos['historial_estados'] = array_map(static fn($h) => $h->aArreglo(), $historial);
            return Respuesta::json(['ok' => true, 'datos' => $datos]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        }
    }

    public function crearOrden(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.crear')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para crear órdenes de trabajo.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->crearOrden($datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Orden de trabajo formulada exitosamente en estado BORRADOR.',
                'datos' => $orden->aArreglo(),
            ], 201);
        } catch (ValidacionExcepcion | PropiedadNoEncontradaExcepcion | UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al formular orden: ' . $e->getMessage()], 500);
        }
    }

    public function programarOrden(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.programar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para programar órdenes.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->programarOrden($id, $datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Orden programada exitosamente y bloqueo de disponibilidad asegurado.',
                'datos' => $orden->aArreglo(),
            ]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (EstadoMantenimientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al programar orden: ' . $e->getMessage()], 500);
        }
    }

    public function iniciarEjecucion(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.ejecutar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para iniciar trabajos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->iniciarEjecucion($id, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Trabajos iniciados formalmente (EN PROCESO).',
                'datos' => $orden->aArreglo(),
            ]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoMantenimientoInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function registrarCostos(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.ejecutar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para registrar costos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $materiales = (string) ($datos['costo_materiales'] ?? '0.00');
        $manoObra = (string) ($datos['costo_mano_obra'] ?? '0.00');
        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->registrarCostos($id, $materiales, $manoObra, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Costos asentados exitosamente.',
                'datos' => $orden->aArreglo(),
            ]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function completarOrden(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.cerrar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para cerrar órdenes.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $notas = (string) ($datos['notas_cierre'] ?? '');
        $fechaLiberacion = !empty($datos['fecha_efectiva_liberacion']) ? (string) $datos['fecha_efectiva_liberacion'] : null;
        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->completarOrden($id, $notas, $fechaLiberacion, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Orden de trabajo completada y noches futuras liberadas.',
                'datos' => $orden->aArreglo(),
            ]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoMantenimientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cerrar orden: ' . $e->getMessage()], 500);
        }
    }

    public function cancelarOrden(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.cancelar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para cancelar órdenes.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $motivo = (string) ($datos['motivo_cancelacion'] ?? '');
        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->cancelarOrden($id, $motivo, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Orden de trabajo cancelada y noches liberadas.',
                'datos' => $orden->aArreglo(),
            ]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoMantenimientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cancelar orden: ' . $e->getMessage()], 500);
        }
    }

    public function prorrogarBloqueo(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ordenes.programar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para extender bloqueos de órdenes.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevaFechaFin = (string) ($datos['nueva_fecha_fin'] ?? $datos['nueva_fecha_bloqueo_fin'] ?? '');
        $actorId = $this->resolverActorId($usuario);

        try {
            $orden = $this->mantenimientoServicio->prorrogarBloqueo($id, $nuevaFechaFin, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Bloqueo operativo extendido exitosamente.',
                'datos' => $orden->aArreglo(),
            ]);
        } catch (OrdenTrabajoNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoDisponibilidadExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (EstadoMantenimientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al extender bloqueo: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerEstadisticas(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $stats = $this->mantenimientoServicio->obtenerEstadisticas();
        return Respuesta::json(['ok' => true, 'datos' => $stats]);
    }

    public function obtenerHistorial(string $tipo, string|int $id): Respuesta
    {
        $id = (int) $id;
        $tipo = strtoupper($tipo);
        if (!in_array($tipo, ['INCIDENCIA', 'ORDEN_TRABAJO'], true)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Tipo de entidad no válido.'], 422);
        }

        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'mantenimiento.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $historial = $this->mantenimientoServicio->obtenerHistorial($tipo, $id);
        $datos = array_map(static fn($h) => $h->aArreglo(), $historial);

        return Respuesta::json(['ok' => true, 'datos' => $datos]);
    }

    // =========================================================================
    // Helpers Privados
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function obtenerCuerpoPeticion(): array
    {
        $contenido = file_get_contents('php://input');
        if ($contenido !== false && $contenido !== '') {
            $decodificado = json_decode($contenido, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }
        return $_POST;
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function validarCsrf(array $datos): bool
    {
        $token = $datos['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token === null || !is_string($token) || trim($token) === '') {
            return false;
        }
        return $this->csrfServicio->validarToken($token);
    }

    private function resolverActorId(?Usuario $usuario = null): int
    {
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario((int) $usuario->obtenerId(), $this->pdo);
                if ($actor !== null && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback defensivo
            }
        }

        $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
        $actorSistema = $actorRepo->buscarPorCodigo('CAMARGO_PMS');
        return $actorSistema !== null && $actorSistema->obtenerId() !== null ? (int) $actorSistema->obtenerId() : 1;
    }
}
