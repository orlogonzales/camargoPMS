<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ArrendamientoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoDisponibilidadExcepcion;
use CamargoPMS\Excepciones\EstadoArrendamientoInvalidoExcepcion;
use CamargoPMS\Excepciones\GarantiaInvalidaExcepcion;
use CamargoPMS\Excepciones\UnidadNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\ArrendamientoRepositorio;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\ArrendamientoServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el subsistema de Arrendamientos de Mediana y Larga Estancia (ARRENDAMIENTOS-1 / D-076).
 */
class ArrendamientoControlador
{
    private Vista $vista;
    private ArrendamientoServicio $arrendamientoServicio;
    private ArrendamientoRepositorio $arrendamientoRepo;
    private UnidadRepositorio $unidadRepo;
    private PropiedadRepositorio $propiedadRepo;
    private PersonaRepositorio $personaRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?ArrendamientoServicio $arrendamientoServicio = null,
        ?ArrendamientoRepositorio $arrendamientoRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->vista = $vista ?? new Vista();
        $this->arrendamientoServicio = $arrendamientoServicio ?? new ArrendamientoServicio($this->pdo);
        $this->arrendamientoRepo = $arrendamientoRepo ?? new ArrendamientoRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Renderiza la vista principal del catálogo de contratos de arrendamiento.
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'arrendamientos.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de arrendamientos.',
            ], 'error'), 403);
        }

        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO');
        $unidades = $this->unidadRepo->listar(['estado' => 'ACTIVO']);
        $csrfToken = $this->csrfServicio->obtenerToken();

        $capacidades = [
            'puede_ver' => true,
            'puede_crear' => $this->autorizacionServicio->puede($usuarioActualId, 'arrendamientos.crear'),
            'puede_activar' => $this->autorizacionServicio->puede($usuarioActualId, 'arrendamientos.activar'),
            'puede_gestionar' => $this->autorizacionServicio->puede($usuarioActualId, 'arrendamientos.gestionar'),
            'puede_rescindir' => $this->autorizacionServicio->puede($usuarioActualId, 'arrendamientos.rescindir'),
            'puede_generar_cargos' => $this->autorizacionServicio->puede($usuarioActualId, 'arrendamientos.generar_cargos'),
        ];

        $html = $this->vista->renderizar('arrendamientos/index', [
            'titulo' => 'Contratos de Arrendamiento',
            'subtitulo' => 'Gestión patrimonial de mediana y larga estancia, disponibilidad y obligaciones periódicas',
            'propiedades' => $propiedades,
            'unidades' => $unidades,
            'csrf_token' => $csrfToken,
            'capacidades' => $capacidades,
            'usuario_actual' => $usuarioActual,
        ], 'principal');

        return new Respuesta($html);
    }

    /**
     * GET /api/arrendamientos: Listado filtrable de contratos de arrendamiento.
     */
    public function listar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        $filtros = [];
        if (!empty($_GET['estado'])) {
            $filtros['estado'] = (string) $_GET['estado'];
        }
        if (!empty($_GET['unidad_id'])) {
            $filtros['unidad_id'] = (int) $_GET['unidad_id'];
        }
        if (!empty($_GET['propiedad_id'])) {
            $filtros['propiedad_id'] = (int) $_GET['propiedad_id'];
        }
        if (!empty($_GET['q'])) {
            $filtros['q'] = trim((string) $_GET['q']);
        }

        $lista = $this->arrendamientoRepo->listar($filtros);
        return Respuesta::json(['ok' => true, 'datos' => $lista]);
    }

    /**
     * GET /api/arrendamientos/{id}: Detalle completo con sujetos, cuotas, custodia e historial.
     */
    public function obtenerDetalle(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.ver')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
        }

        try {
            $detalle = $this->arrendamientoServicio->obtenerDetalleCompleto($id);
            return Respuesta::json(['ok' => true, 'datos' => $detalle]);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al consultar contrato: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos: Crea un nuevo borrador contractual de arrendamiento.
     */
    public function crear(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.crear')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para formular contratos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $arrendamiento = $this->arrendamientoServicio->crearArrendamiento($datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Contrato formulado exitosamente en estado BORRADOR.',
                'datos' => $arrendamiento->haciaArreglo(),
            ], 201);
        } catch (ValidacionExcepcion | UnidadNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al crear contrato: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/activar: Activa el contrato y materializa disponibilidad.
     */
    public function activar(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.activar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para activar contratos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $resultado = $this->arrendamientoServicio->activarArrendamiento($id, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Contrato activado con éxito. Se materializó el inventario diario y se apertura el folio financiero.',
                'datos' => $resultado,
            ]);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoDisponibilidadExcepcion | EstadoArrendamientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al activar contrato: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/personas: Agrega cotitular u ocupante.
     */
    public function agregarPersona(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.gestionar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para gestionar sujetos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $personaId = (int) ($datos['persona_id'] ?? 0);
        $tipoRelacion = (string) ($datos['tipo_relacion'] ?? 'OCUPANTE');
        $observaciones = isset($datos['observaciones']) ? (string) $datos['observaciones'] : null;

        try {
            $this->arrendamientoServicio->agregarPersona($id, $personaId, $tipoRelacion, $observaciones, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Sujeto incorporado correctamente al contrato.']);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoArrendamientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al incorporar sujeto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/personas/{personaId}/eliminar: Remueve un cotitular u ocupante.
     */
    public function quitarPersona(string|int $id, string|int $personaId): Respuesta
    {
        $id = (int) $id;
        $personaId = (int) $personaId;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.gestionar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para remover sujetos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $this->arrendamientoServicio->quitarPersona($id, $personaId, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Sujeto desvinculado del contrato.']);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al remover sujeto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/prorrogar: Amplía la vigencia del contrato.
     */
    public function prorrogar(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.gestionar')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para prorrogar contratos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $nuevaFechaFin = (string) ($datos['nueva_fecha_fin'] ?? '');

        try {
            $this->arrendamientoServicio->prorrogarArrendamiento($id, $nuevaFechaFin, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => "Contrato prorrogado exitosamente hasta {$nuevaFechaFin}."]);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoDisponibilidadExcepcion | EstadoArrendamientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al prorrogar contrato: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/rescindir: Rescinde anticipadamente liberando noches futuras.
     */
    public function rescindir(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.rescindir')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para rescindir contratos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $fechaEfectiva = (string) ($datos['fecha_efectiva'] ?? date('Y-m-d'));
        $motivo = (string) ($datos['motivo'] ?? '');

        try {
            $this->arrendamientoServicio->rescindirArrendamiento($id, $fechaEfectiva, $motivo, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Contrato rescindido anticipadamente y noches futuras liberadas.']);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoArrendamientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al rescindir contrato: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/finalizar: Cierre regular de contrato vencido.
     */
    public function finalizar(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.rescindir')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para finalizar contratos.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);

        try {
            $this->arrendamientoServicio->finalizarArrendamiento($id, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Contrato de arrendamiento finalizado regularmente.']);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoArrendamientoInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al finalizar contrato: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/cancelar: Cancela un borrador contractual.
     */
    public function cancelarBorrador(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.crear')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para cancelar borradores.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $motivo = (string) ($datos['motivo'] ?? 'Desistimiento de partes');

        try {
            $this->arrendamientoServicio->cancelarBorrador($id, $motivo, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Borrador cancelado exitosamente.']);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoArrendamientoInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cancelar borrador: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/cuotas: Genera cuota recurrente mensual de forma IDEMPOTENTE.
     */
    public function generarCuota(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.generar_cargos')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para emitir cuotas.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $anio = (int) ($datos['anio'] ?? date('Y'));
        $mes = (int) ($datos['mes'] ?? date('n'));

        try {
            $cuota = $this->arrendamientoServicio->generarCuotaMensual($id, $anio, $mes, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Cuota de renta para {$cuota->obtenerPeriodoCodigo()} procesada correctamente.",
                'datos' => $cuota->haciaArreglo(),
            ]);
        } catch (ArrendamientoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (EstadoArrendamientoInvalidoExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al emitir cuota: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/garantia/recibir: Registra ingreso en custodia de fondos de garantía.
     */
    public function recibirGarantia(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.generar_cargos')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para gestionar garantías.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $monto = (string) ($datos['monto'] ?? '0.00');

        try {
            $this->arrendamientoServicio->registrarRecepcionGarantia($id, $monto, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Fondo de garantía recibido en custodia.']);
        } catch (GarantiaInvalidaExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al registrar garantía: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/garantia/compensar: Compensa garantía por daños o rentas insolutas.
     */
    public function compensarGarantia(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.generar_cargos')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para liquidar garantías.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $monto = (string) ($datos['monto'] ?? '0.00');
        $tipo = (string) ($datos['tipo_compensacion'] ?? 'DANOS');
        $motivo = (string) ($datos['motivo'] ?? '');

        try {
            $this->arrendamientoServicio->compensarGarantia($id, $monto, $tipo, $motivo, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Compensación aplicada contra fondos en custodia.']);
        } catch (GarantiaInvalidaExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al compensar garantía: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/arrendamientos/{id}/garantia/devolver: Liquida y devuelve garantía retenida.
     */
    public function devolverGarantia(string|int $id): Respuesta
    {
        $id = (int) $id;
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $usuarioId = $usuario !== null ? (int) $usuario->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioId, 'arrendamientos.generar_cargos')) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'No tiene permisos para devolver garantías.'], 403);
        }

        $datos = $this->obtenerCuerpoPeticion();
        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $actorId = $this->resolverActorId($usuario);
        $monto = (string) ($datos['monto'] ?? '0.00');
        $motivo = (string) ($datos['motivo'] ?? 'Devolución de garantía al cierre');

        try {
            $this->arrendamientoServicio->devolverGarantia($id, $monto, $motivo, $actorId);
            return Respuesta::json(['ok' => true, 'mensaje' => 'Fondos devueltos al arrendatario correctamente.']);
        } catch (GarantiaInvalidaExcepcion | ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al devolver garantía: ' . $e->getMessage()], 500);
        }
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
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
                // Fallback continuo
            }
        }

        try {
            $repo = new ActorAuditoriaRepositorio($this->pdo);
            $sistema = $repo->buscarPorCodigo('CAMARGO_PMS');
            if ($sistema && $sistema->obtenerId() !== null) {
                return (int) $sistema->obtenerId();
            }
        } catch (Throwable) {
            // Fallback
        }

        return 1;
    }
}
