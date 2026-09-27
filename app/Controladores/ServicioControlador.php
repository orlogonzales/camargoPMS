<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\EstadoServicioInvalidoExcepcion;
use CamargoPMS\Excepciones\ProveedorNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ProveedorNoHabilitadoExcepcion;
use CamargoPMS\Excepciones\ReservaNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ServicioContratadoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ServicioNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\PersonaRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\ProveedorServicio;
use CamargoPMS\Servicios\ServicioCatalogoServicio;
use CamargoPMS\Servicios\ServicioContratadoServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el módulo de Catálogo de Servicios, Proveedores, Consumos y Traslados (SERVICIOS-1).
 */
class ServicioControlador
{
    private Vista $vista;
    private ServicioCatalogoServicio $catalogoServicio;
    private ProveedorServicio $proveedorServicio;
    private ServicioContratadoServicio $contratadoServicio;
    private ReservaRepositorio $reservaRepo;
    private PropiedadRepositorio $propiedadRepo;
    private PersonaRepositorio $personaRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?ServicioCatalogoServicio $catalogoServicio = null,
        ?ProveedorServicio $proveedorServicio = null,
        ?ServicioContratadoServicio $contratadoServicio = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?PersonaRepositorio $personaRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $pdo = BaseDatos::conexion();
        $this->vista = $vista ?? new Vista();
        $this->catalogoServicio = $catalogoServicio ?? new ServicioCatalogoServicio($pdo);
        $this->proveedorServicio = $proveedorServicio ?? new ProveedorServicio($pdo);
        $this->contratadoServicio = $contratadoServicio ?? new ServicioContratadoServicio($pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($pdo);
        $this->personaRepo = $personaRepo ?? new PersonaRepositorio($pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Renderiza el tablero de control de servicios y consumos (GET /servicios).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'servicios.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de servicios.',
            ], 'error'), 403);
        }

        $categorias = $this->catalogoServicio->listarCategorias(true);
        $modalidades = $this->catalogoServicio->listarModalidades(true);
        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO');
        $csrfToken = $this->csrfServicio->obtenerToken();

        $puedeGestionar = $this->autorizacionServicio->puede($usuarioActualId, 'servicios.gestionar');
        $puedeContratar = $this->autorizacionServicio->puede($usuarioActualId, 'servicios.contratar');
        $puedeEjecutar = $this->autorizacionServicio->puede($usuarioActualId, 'servicios.ejecutar');
        $puedeCancelar = $this->autorizacionServicio->puede($usuarioActualId, 'servicios.cancelar');

        $html = $this->vista->renderizar('servicios/index', [
            'titulo' => 'Servicios, Consumos y Proveedores',
            'subtitulo' => 'Catálogo maestro, proveedores homologados, consumos imputados y traslados',
            'categorias' => $categorias,
            'modalidades' => $modalidades,
            'propiedades' => $propiedades,
            'csrf_token' => $csrfToken,
            'puede_gestionar' => $puedeGestionar,
            'puede_contratar' => $puedeContratar,
            'puede_ejecutar' => $puedeEjecutar,
            'puede_cancelar' => $puedeCancelar,
            'usuario_actual' => $usuarioActual,
        ], 'principal');

        return new Respuesta($html);
    }

    // =========================================================================
    // Endpoints JSON: Consumos y Contrataciones
    // =========================================================================

    public function listarContratados(): Respuesta
    {
        try {
            $filtros = [
                'reserva_id' => $_GET['reserva_id'] ?? null,
                'estadia_id' => $_GET['estadia_id'] ?? null,
                'servicio_id' => $_GET['servicio_id'] ?? null,
                'proveedor_id' => $_GET['proveedor_id'] ?? null,
                'estado' => $_GET['estado'] ?? null,
                'fecha_desde' => $_GET['fecha_desde'] ?? null,
                'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
                'q' => $_GET['q'] ?? null,
            ];

            $items = $this->contratadoServicio->listar($filtros);
            $datos = array_map(fn($item) => $item->haciaArreglo(), $items);

            return Respuesta::json([
                'ok' => true,
                'datos' => $datos,
                'total' => count($datos),
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al listar servicios contratados: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerContratado(mixed $id): Respuesta
    {
        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        try {
            $item = $this->contratadoServicio->obtenerPorId($identificador);
            return Respuesta::json([
                'ok' => true,
                'datos' => $item->haciaArreglo(),
            ]);
        } catch (ServicioContratadoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener servicio contratado: ' . $e->getMessage()], 500);
        }
    }

    public function contratar(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $item = $this->contratadoServicio->contratarServicio($datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Servicio contratado exitosamente con código '{$item->obtenerCodigo()}'.",
                'datos' => $item->haciaArreglo(),
            ], 201);
        } catch (ValidacionExcepcion|ProveedorNoHabilitadoExcepcion|EstadoServicioInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ReservaNoEncontradaExcepcion|ServicioNoEncontradoExcepcion|ProveedorNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al contratar servicio: ' . $e->getMessage()], 500);
        }
    }

    public function confirmar(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $item = $this->contratadoServicio->confirmarServicio($identificador, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Servicio '{$item->obtenerCodigo()}' confirmado exitosamente.",
                'datos' => $item->haciaArreglo(),
            ]);
        } catch (EstadoServicioInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ServicioContratadoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al confirmar servicio: ' . $e->getMessage()], 500);
        }
    }

    public function ejecutar(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $item = $this->contratadoServicio->ejecutarServicio($identificador, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Servicio '{$item->obtenerCodigo()}' marcado como EJECUTADO físicamente.",
                'datos' => $item->haciaArreglo(),
            ]);
        } catch (EstadoServicioInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ServicioContratadoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al ejecutar servicio: ' . $e->getMessage()], 500);
        }
    }

    public function cancelar(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $motivo = (string) ($datos['motivo'] ?? $datos['motivo_cancelacion'] ?? '');

        try {
            $item = $this->contratadoServicio->cancelarServicio($identificador, $motivo, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Servicio '{$item->obtenerCodigo()}' cancelado exitosamente.",
                'datos' => $item->haciaArreglo(),
            ]);
        } catch (ValidacionExcepcion|EstadoServicioInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ServicioContratadoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cancelar servicio: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Endpoints JSON: Catálogo Maestro de Servicios
    // =========================================================================

    public function listarCatalogo(): Respuesta
    {
        try {
            $filtros = [
                'categoria_id' => $_GET['categoria_id'] ?? null,
                'modalidad_cobro_id' => $_GET['modalidad_cobro_id'] ?? null,
                'propiedad_id' => $_GET['propiedad_id'] ?? null,
                'estado' => $_GET['estado'] ?? null,
                'q' => $_GET['q'] ?? null,
            ];

            $servicios = $this->catalogoServicio->listarServicios($filtros);
            $datos = array_map(fn($s) => $s->haciaArreglo(), $servicios);

            return Respuesta::json([
                'ok' => true,
                'datos' => $datos,
                'total' => count($datos),
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al listar catálogo: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerServicio(mixed $id): Respuesta
    {
        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        try {
            $servicio = $this->catalogoServicio->obtenerServicioPorId($identificador, true);
            return Respuesta::json([
                'ok' => true,
                'datos' => $servicio->haciaArreglo(),
            ]);
        } catch (ServicioNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener servicio: ' . $e->getMessage()], 500);
        }
    }

    public function crearServicio(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $servicio = $this->catalogoServicio->crearServicio($datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Servicio '{$servicio->obtenerNombre()}' creado exitosamente en el catálogo.",
                'datos' => $servicio->haciaArreglo(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al crear servicio: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarServicio(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $servicio = $this->catalogoServicio->actualizarServicio($identificador, $datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Servicio '{$servicio->obtenerNombre()}' actualizado exitosamente.",
                'datos' => $servicio->haciaArreglo(),
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ServicioNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al actualizar servicio: ' . $e->getMessage()], 500);
        }
    }

    public function cambiarEstadoServicio(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevoEstado = (string) ($datos['estado'] ?? '');

        try {
            $this->catalogoServicio->cambiarEstadoServicio($identificador, $nuevoEstado, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Estado del servicio actualizado a {$nuevoEstado}.",
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ServicioNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cambiar estado: ' . $e->getMessage()], 500);
        }
    }

    public function homologarProveedor(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $proveedorId = (int) ($datos['proveedor_id'] ?? 0);
        $costoPactado = isset($datos['costo_pactado']) && $datos['costo_pactado'] !== '' ? (string) $datos['costo_pactado'] : null;
        $esPreferente = !empty($datos['es_preferente']);
        $tiempoAnticipacion = (int) ($datos['tiempo_anticipacion_horas'] ?? 0);

        try {
            $this->catalogoServicio->homologarProveedor(
                servicioId: $identificador,
                proveedorId: $proveedorId,
                costoPactado: $costoPactado,
                esPreferente: $esPreferente,
                tiempoAnticipacionHoras: $tiempoAnticipacion,
                actorId: $actorId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Proveedor homologado correctamente para el servicio.",
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ServicioNoEncontradoExcepcion|ProveedorNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al homologar proveedor: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Endpoints JSON: Maestro de Proveedores
    // =========================================================================

    public function listarProveedores(): Respuesta
    {
        try {
            $filtros = [
                'tipo' => $_GET['tipo'] ?? null,
                'estado' => $_GET['estado'] ?? null,
                'q' => $_GET['q'] ?? null,
            ];

            $proveedores = $this->proveedorServicio->listar($filtros);
            $datos = array_map(fn($p) => $p->haciaArreglo(), $proveedores);

            return Respuesta::json([
                'ok' => true,
                'datos' => $datos,
                'total' => count($datos),
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al listar proveedores: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerProveedor(mixed $id): Respuesta
    {
        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        try {
            $proveedor = $this->proveedorServicio->obtenerPorId($identificador);
            return Respuesta::json([
                'ok' => true,
                'datos' => $proveedor->haciaArreglo(),
            ]);
        } catch (ProveedorNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener proveedor: ' . $e->getMessage()], 500);
        }
    }

    public function crearProveedor(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $proveedor = $this->proveedorServicio->crear($datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Proveedor '{$proveedor->obtenerRazonSocial()}' registrado exitosamente.",
                'datos' => $proveedor->haciaArreglo(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al registrar proveedor: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarProveedor(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        try {
            $proveedor = $this->proveedorServicio->actualizar($identificador, $datos, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Proveedor '{$proveedor->obtenerRazonSocial()}' actualizado exitosamente.",
                'datos' => $proveedor->haciaArreglo(),
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ProveedorNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al actualizar proveedor: ' . $e->getMessage()], 500);
        }
    }

    public function cambiarEstadoProveedor(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);
        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $nuevoEstado = (string) ($datos['estado'] ?? '');

        try {
            $this->proveedorServicio->cambiarEstado($identificador, $nuevoEstado, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Estado del proveedor actualizado a {$nuevoEstado}.",
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (ProveedorNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cambiar estado del proveedor: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Endpoints JSON: Metadatos Auxiliares
    // =========================================================================

    public function listarCategorias(): Respuesta
    {
        try {
            $categorias = $this->catalogoServicio->listarCategorias(false);
            $datos = array_map(fn($c) => $c->haciaArreglo(), $categorias);
            return Respuesta::json(['ok' => true, 'datos' => $datos]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al listar categorías: ' . $e->getMessage()], 500);
        }
    }

    public function listarModalidades(): Respuesta
    {
        try {
            $modalidades = $this->catalogoServicio->listarModalidades(false);
            $datos = array_map(fn($m) => $m->haciaArreglo(), $modalidades);
            return Respuesta::json(['ok' => true, 'datos' => $datos]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al listar modalidades: ' . $e->getMessage()], 500);
        }
    }

    public function auxiliares(): Respuesta
    {
        try {
            $categorias = array_map(fn($c) => $c->haciaArreglo(), $this->catalogoServicio->listarCategorias(true));
            $modalidades = array_map(fn($m) => $m->haciaArreglo(), $this->catalogoServicio->listarModalidades(true));
            $proveedores = array_map(fn($p) => $p->haciaArreglo(), $this->proveedorServicio->listar(['estado' => 'ACTIVO']));
            $servicios = array_map(fn($s) => $s->haciaArreglo(), $this->catalogoServicio->listarServicios(['estado' => 'ACTIVO']));
            $propiedades = array_map(fn($p) => $p->haciaArreglo(), $this->propiedadRepo->listar(null, 'ACTIVO'));

            // Lista reducida de reservas activas / confirmadas para imputación rápida
            $reservas = $this->reservaRepo->listar(['estado' => 'CONFIRMADA']);
            $reservasDatos = array_map(fn($r) => [
                'id' => $r->obtenerId(),
                'codigo' => $r->obtenerCodigo(),
                'titular' => $r->obtenerTitularNombreCompleto(),
                'fecha_entrada' => $r->obtenerFechaEntrada(),
                'fecha_salida' => $r->obtenerFechaSalida(),
            ], $reservas);

            return Respuesta::json([
                'ok' => true,
                'categorias' => $categorias,
                'modalidades' => $modalidades,
                'proveedores' => $proveedores,
                'servicios' => $servicios,
                'propiedades' => $propiedades,
                'reservas' => $reservasDatos,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cargar auxiliares de servicios: ' . $e->getMessage()], 500);
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

    /**
     * Resuelve canónicamente el ID de actor ejecutor (D-061: ACTOR != USUARIO).
     */
    private function resolverActorId(?\CamargoPMS\Modelos\Usuario $usuario = null): int
    {
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            try {
                $actor = $this->auditoriaServicio->obtenerOAsegurarActorUsuario((int) $usuario->obtenerId(), BaseDatos::conexion());
                if ($actor && $actor->obtenerId() !== null) {
                    return (int) $actor->obtenerId();
                }
            } catch (Throwable) {
            }
        }
        $actorActual = $this->auditoriaServicio->obtenerActorActual(BaseDatos::conexion());
        return (int) ($actorActual->obtenerId() ?? 1);
    }
}
