<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoHousekeepingExcepcion;
use CamargoPMS\Excepciones\HousekeepingNoEncontradoExcepcion;
use CamargoPMS\Excepciones\UnidadNoListaExcepcion;
use CamargoPMS\Excepciones\ValidacionHousekeepingExcepcion;
use CamargoPMS\Modelos\HousekeepingDerivacionOperativa;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\HousekeepingRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\HousekeepingServicio;
use CamargoPMS\Servicios\InventarioServicio;
use CamargoPMS\Servicios\MantenimientoServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el subsistema de Housekeeping, Pisos y Control de Lencería.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingControlador
{
    private Vista $vista;
    private HousekeepingServicio $housekeepingServicio;
    private HousekeepingRepositorio $housekeepingRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?HousekeepingServicio $housekeepingServicio = null,
        ?HousekeepingRepositorio $housekeepingRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->housekeepingRepo = $housekeepingRepo ?? new HousekeepingRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();

        if ($housekeepingServicio !== null) {
            $this->housekeepingServicio = $housekeepingServicio;
        } else {
            $inventarioServicio = class_exists(InventarioServicio::class) ? new InventarioServicio($this->pdo) : null;
            $mantenimientoServicio = class_exists(MantenimientoServicio::class) ? new MantenimientoServicio($this->pdo) : null;
            $this->housekeepingServicio = new HousekeepingServicio(
                $this->housekeepingRepo,
                $this->auditoriaServicio,
                $inventarioServicio,
                $mantenimientoServicio,
                $this->pdo
            );
        }
    }

    public function index(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.ver')) {
            return new Respuesta(
                $this->vista->renderizar('errores/403', [
                    'mensaje' => 'No cuenta con permisos para acceder al módulo de Housekeeping / Pisos.',
                    'usuario' => $usuario,
                ]),
                403
            );
        }

        // Propiedades para selector
        $stmtProp = $this->pdo->query('SELECT id, nombre, codigo FROM propiedades WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        $propiedades = $stmtProp->fetchAll(PDO::FETCH_ASSOC);

        $contenido = $this->vista->renderizar('housekeeping/index', [
            'titulo' => 'Housekeeping, Pisos y Control de Lencería — Camargo PMS',
            'usuario' => $usuario,
            'permisos' => $this->autorizacionServicio->obtenerPermisosEfectivos((int) $usuario->obtenerId()),
            'csrf_token' => $this->csrfServicio->generarToken(),
            'propiedades' => $propiedades,
        ]);

        return new Respuesta($contenido);
    }

    /**
     * GET /api/housekeeping/rack
     * Retorna el rack operacional en vivo derivando VR, VD, VCL, OD, OC, OOO, OOS.
     */
    public function apiRackOperacional(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $propiedadId = isset($_GET['propiedad_id']) && (int) $_GET['propiedad_id'] > 0 ? (int) $_GET['propiedad_id'] : null;
        $piso = isset($_GET['piso']) && $_GET['piso'] !== '' ? (int) $_GET['piso'] : null;

        $rack = $this->housekeepingServicio->obtenerRackOperacional($propiedadId, $piso);

        // Calcular KPIs operativos en caliente
        $kpis = [
            'vr' => 0,  // Vacant Ready
            'vd' => 0,  // Vacant Dirty
            'vcl' => 0, // Vacant Clean (por inspeccionar)
            'od' => 0,  // Occupied Dirty
            'oc' => 0,  // Occupied Clean
            'ooo' => 0, // Out of Order (Mantenimiento)
            'total' => count($rack),
        ];

        $rackArray = [];
        foreach ($rack as $item) {
            $arr = $item->aArreglo();
            $rackArray[] = $arr;
            $cond = strtolower($arr['condicion_derivada']);
            if (isset($kpis[$cond])) {
                $kpis[$cond]++;
            }
        }

        return Respuesta::json([
            'exito' => true,
            'rack' => $rackArray,
            'kpis' => $kpis,
        ]);
    }

    /**
     * GET /api/housekeeping/tareas
     * Lista de tareas de limpieza con filtros.
     */
    public function apiListarTareas(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $filtros = [];
        if (!empty($_GET['propiedad_id'])) $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        if (!empty($_GET['unidad_id'])) $filtros['unidad_id'] = (int) $_GET['unidad_id'];
        if (!empty($_GET['estado'])) $filtros['estado'] = (string) $_GET['estado'];
        if (!empty($_GET['tipo_tarea'])) $filtros['tipo_tarea'] = (string) $_GET['tipo_tarea'];
        if (!empty($_GET['fecha_programada'])) $filtros['fecha_programada'] = (string) $_GET['fecha_programada'];
        if (!empty($_GET['camarera_id'])) $filtros['camarera_colaborador_id'] = (int) $_GET['camarera_id'];

        $tareas = $this->housekeepingRepo->listarTareas($filtros);

        return Respuesta::json([
            'exito' => true,
            'tareas' => $tareas,
        ]);
    }

    /**
     * GET /api/housekeeping/tareas/{id}
     * Detalle de tarea con su checklist snapshot y consumos de amenities.
     */
    public function apiDetalleTarea(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $id = (int) ($params['id'] ?? 0);
        $tarea = $this->housekeepingRepo->obtenerTareaPorId($id);
        if (!$tarea) {
            return Respuesta::json(['exito' => false, 'error' => 'Tarea no encontrada'], 404);
        }

        $tarea['checklist'] = $this->housekeepingRepo->obtenerChecklistTarea($id);
        $tarea['consumos'] = $this->housekeepingRepo->obtenerConsumosTarea($id);

        return Respuesta::json([
            'exito' => true,
            'tarea' => $tarea,
        ]);
    }

    /**
     * POST /api/housekeeping/tareas
     * Creación manual de tarea de limpieza / retoque / profunda.
     */
    public function apiCrearTarea(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.tareas.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado para crear tareas'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $datos = $this->obtenerCuerpoJson();
        $actorId = $this->resolverActorId();

        try {
            $tarea = $this->housekeepingServicio->crearTareaManual($datos, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Tarea '{$tarea->obtenerCodigo()}' creada correctamente.",
                'tarea' => $tarea->aArreglo(),
            ], 201);
        } catch (ValidacionHousekeepingExcepcion|ConflictoHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => 'Error al crear la tarea: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/housekeeping/tareas/{id}/asignar
     */
    public function apiAsignarTarea(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.tareas.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado para asignar tareas'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $datos = $this->obtenerCuerpoJson();
        $camareraId = (int) ($datos['camarera_colaborador_id'] ?? 0);
        $supervisorId = !empty($datos['supervisor_colaborador_id']) ? (int) $datos['supervisor_colaborador_id'] : null;
        $actorId = $this->resolverActorId();

        if ($camareraId <= 0) {
            return Respuesta::json(['exito' => false, 'error' => 'Debe seleccionar una camarera válida.'], 422);
        }

        try {
            $tarea = $this->housekeepingServicio->asignarTarea($id, $camareraId, $supervisorId, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Tarea asignada satisfactoriamente.',
                'tarea' => $tarea->aArreglo(),
            ]);
        } catch (HousekeepingNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConflictoHousekeepingExcepcion|ValidacionHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/housekeeping/tareas/{id}/iniciar
     */
    public function apiIniciarLimpieza(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.limpieza.ejecutar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado para iniciar labores de limpieza'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $actorId = $this->resolverActorId();

        try {
            $tarea = $this->housekeepingServicio->iniciarLimpieza($id, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Limpieza iniciada. Unidad en estado EN_LIMPIEZA.',
                'tarea' => $tarea->aArreglo(),
            ]);
        } catch (HousekeepingNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConflictoHousekeepingExcepcion|ValidacionHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/housekeeping/tareas/{id}/finalizar
     */
    public function apiFinalizarLimpieza(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.limpieza.ejecutar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado para finalizar limpieza'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $datos = $this->obtenerCuerpoJson();
        $actorId = $this->resolverActorId();

        try {
            $tarea = $this->housekeepingServicio->finalizarLimpieza($id, $datos, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Limpieza terminada. Enviada a inspección.',
                'tarea' => $tarea->aArreglo(),
            ]);
        } catch (HousekeepingNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConflictoHousekeepingExcepcion|ValidacionHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/housekeeping/tareas/{id}/inspeccionar
     */
    public function apiInspeccionarTarea(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.inspeccion.ejecutar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado para inspeccionar habitaciones'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $datos = $this->obtenerCuerpoJson();
        $aprobada = !empty($datos['aprobada']);
        $checklist = $datos['checklist'] ?? [];
        $notasSupervisor = $datos['notas_supervisor'] ?? null;
        $actorId = $this->resolverActorId();

        try {
            $tarea = $this->housekeepingServicio->inspeccionarTarea($id, $aprobada, $checklist, $notasSupervisor, $actorId);
            $msg = $aprobada
                ? 'Habitación inspeccionada y APROBADA satisfactoriamente (VR - Vacant Ready).'
                : 'Habitación RECHAZADA. Requiere retoque de limpieza.';

            return Respuesta::json([
                'exito' => true,
                'mensaje' => $msg,
                'tarea' => $tarea->aArreglo(),
            ]);
        } catch (HousekeepingNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConflictoHousekeepingExcepcion|ValidacionHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/housekeeping/tareas/{id}/desperfecto
     */
    public function apiReportarDesperfecto(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.limpieza.ejecutar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $datos = $this->obtenerCuerpoJson();
        $actorId = $this->resolverActorId();

        try {
            $res = $this->housekeepingServicio->reportarDesperfectoMantenimiento($id, $datos, $actorId);
            return Respuesta::json(array_merge(['exito' => true], $res));
        } catch (HousekeepingNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionHousekeepingExcepcion|ConflictoHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/housekeeping/lavanderia/lotes
     */
    public function apiListarLotesLavanderia(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.lavanderia.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $filtros = [];
        if (!empty($_GET['propiedad_id'])) $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        if (!empty($_GET['estado'])) $filtros['estado'] = (string) $_GET['estado'];

        $lotes = $this->housekeepingRepo->listarLotesLavanderia($filtros);

        return Respuesta::json([
            'exito' => true,
            'lotes' => $lotes,
        ]);
    }

    /**
     * GET /api/housekeeping/lavanderia/lotes/{id}
     */
    public function apiDetalleLoteLavanderia(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.lavanderia.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $id = (int) ($params['id'] ?? 0);
        $lote = $this->housekeepingRepo->obtenerLotePorId($id);
        if (!$lote) {
            return Respuesta::json(['exito' => false, 'error' => 'Lote no encontrado'], 404);
        }

        $lote['lineas'] = $this->housekeepingRepo->obtenerLineasLote($id);

        return Respuesta::json([
            'exito' => true,
            'lote' => $lote,
        ]);
    }

    /**
     * POST /api/housekeeping/lavanderia/despachar
     */
    public function apiDespacharLoteLavanderia(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.lavanderia.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $datos = $this->obtenerCuerpoJson();
        $actorId = $this->resolverActorId();

        try {
            $lote = $this->housekeepingServicio->despacharLoteLavanderia($datos, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Lote de lencería '{$lote->obtenerCodigo()}' despachado exitosamente.",
                'lote' => $lote->aArreglo(),
            ], 201);
        } catch (ValidacionHousekeepingExcepcion|ConflictoHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/housekeeping/lavanderia/lotes/{id}/recibir
     */
    public function apiRecibirLoteLavanderia(array $params = []): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'housekeeping.lavanderia.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $csrf = $this->validarCsrf();
        if ($csrf) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $datos = $this->obtenerCuerpoJson();
        $recepciones = $datos['recepciones'] ?? [];
        $notasRetorno = $datos['notas_retorno'] ?? null;
        $actorId = $this->resolverActorId();

        try {
            $lote = $this->housekeepingServicio->recibirLoteLavanderia($id, $recepciones, $notasRetorno, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Retorno de lote '{$lote->obtenerCodigo()}' procesado. Estado: {$lote->obtenerEstado()}.",
                'lote' => $lote->aArreglo(),
            ]);
        } catch (HousekeepingNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionHousekeepingExcepcion|ConflictoHousekeepingExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    private function obtenerUsuarioAutenticado(): ?Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function resolverActorId(): int
    {
        $usr = $this->obtenerUsuarioAutenticado();
        if ($usr) {
            $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
            $actor = $actorRepo->buscarPorUsuarioId((int) $usr->obtenerId());
            if ($actor) {
                return (int) $actor->obtenerId();
            }
        }
        return 1;
    }

    private function validarCsrf(): ?Respuesta
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o expirado.'], 403);
        }
        return null;
    }

    private function obtenerCuerpoJson(): array
    {
        $crudo = file_get_contents('php://input');
        if (!$crudo) {
            return $_POST;
        }
        $datos = json_decode($crudo, true);
        return is_array($datos) ? array_merge($_POST, $datos) : $_POST;
    }
}
