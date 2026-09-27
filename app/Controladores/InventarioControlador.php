<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ActivoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ArticuloNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ConflictoInventarioExcepcion;
use CamargoPMS\Excepciones\MovimientoInvalidoExcepcion;
use CamargoPMS\Excepciones\StockInsuficienteExcepcion;
use CamargoPMS\Excepciones\UbicacionNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\ColaboradorRepositorio;
use CamargoPMS\Repositorios\PropiedadRepositorio;
use CamargoPMS\Repositorios\ProveedorRepositorio;
use CamargoPMS\Repositorios\TipoUnidadRepositorio;
use CamargoPMS\Repositorios\UnidadRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\InventarioServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el módulo de inventario físico, existencias, Kardex,
 * activos serializables y dotaciones (INVENTARIO-1 / D-078).
 */
class InventarioControlador
{
    private Vista $vista;
    private InventarioServicio $inventarioServicio;
    private PropiedadRepositorio $propiedadRepo;
    private UnidadRepositorio $unidadRepo;
    private TipoUnidadRepositorio $tipoUnidadRepo;
    private ColaboradorRepositorio $colaboradorRepo;
    private ProveedorRepositorio $proveedorRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?InventarioServicio $inventarioServicio = null,
        ?PropiedadRepositorio $propiedadRepo = null,
        ?UnidadRepositorio $unidadRepo = null,
        ?TipoUnidadRepositorio $tipoUnidadRepo = null,
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
        $this->inventarioServicio = $inventarioServicio ?? new InventarioServicio($this->pdo);
        $this->propiedadRepo = $propiedadRepo ?? new PropiedadRepositorio($this->pdo);
        $this->unidadRepo = $unidadRepo ?? new UnidadRepositorio($this->pdo);
        $this->tipoUnidadRepo = $tipoUnidadRepo ?? new TipoUnidadRepositorio($this->pdo);
        $this->colaboradorRepo = $colaboradorRepo ?? new ColaboradorRepositorio($this->pdo);
        $this->proveedorRepo = $proveedorRepo ?? new ProveedorRepositorio($this->pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Renderiza la vista principal del módulo de Inventario.
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::redirigir('/login');
        }

        $usuarioId = (int) $usuario->obtenerId();
        if (!$this->autorizacionServicio->puede($usuarioId, 'inventario.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para consultar el módulo de inventario.',
            ], 'error'), 403);
        }

        $propiedades = $this->propiedadRepo->listar(null, 'ACTIVO');
        $unidades = $this->unidadRepo->listar(['estado' => 'ACTIVO'], 1, 100);
        $tiposUnidad = $this->tipoUnidadRepo->listarActivos();
        $colaboradores = $this->colaboradorRepo->listarActivos(100, 0);
        $proveedores = $this->proveedorRepo->listar(['estado' => 'ACTIVO']);
        $unidadesMedida = $this->inventarioServicio->obtenerRepositorio()->obtenerUnidadesMedida();
        $csrfToken = $this->csrfServicio->obtenerToken();

        $capacidades = [
            'puede_ver' => true,
            'puede_articulos' => $this->autorizacionServicio->puede($usuarioId, 'inventario.articulos.gestionar'),
            'puede_ubicaciones' => $this->autorizacionServicio->puede($usuarioId, 'inventario.ubicaciones.gestionar'),
            'puede_movimientos' => $this->autorizacionServicio->puede($usuarioId, 'inventario.movimientos.registrar'),
            'puede_traslados' => $this->autorizacionServicio->puede($usuarioId, 'inventario.traslados.ejecutar'),
            'puede_activos' => $this->autorizacionServicio->puede($usuarioId, 'inventario.activos.gestionar'),
            'puede_dotaciones' => $this->autorizacionServicio->puede($usuarioId, 'inventario.dotaciones.gestionar'),
        ];

        $html = $this->vista->renderizar('inventario/index', [
            'titulo' => 'Gestión de Inventario y Activos — Camargo PMS',
            'usuario' => $usuario,
            'propiedades' => $propiedades,
            'unidades' => $unidades,
            'tiposUnidad' => $tiposUnidad,
            'colaboradores' => $colaboradores,
            'proveedores' => $proveedores,
            'unidadesMedida' => $unidadesMedida,
            'csrf_token' => $csrfToken,
            'capacidades' => $capacidades,
        ], 'principal');

        return new Respuesta($html);
    }

    // =========================================================================
    // API: ARTÍCULOS
    // =========================================================================

    public function apiListarArticulos(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'categoria' => $_GET['categoria'] ?? null,
            'estado' => $_GET['estado'] ?? null,
            'termino' => $_GET['termino'] ?? null,
        ];

        $articulos = $this->inventarioServicio->obtenerRepositorio()->listarArticulos($filtros);
        $data = array_map(fn($a) => $a->aArray(), $articulos);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiCrearArticulo(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.articulos.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();

        try {
            $articulo = $this->inventarioServicio->crearArticulo($input);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Artículo registrado exitosamente.',
                'datos' => $articulo->aArray(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiActualizarArticulo(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.articulos.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;
        $input = $this->obtenerJsonInput();

        try {
            $articulo = $this->inventarioServicio->actualizarArticulo($id, $input);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Artículo actualizado exitosamente.',
                'datos' => $articulo->aArray(),
            ]);
        } catch (ArticuloNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: UBICACIONES
    // =========================================================================

    public function apiListarUbicaciones(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'propiedad_id' => $_GET['propiedad_id'] ?? null,
            'tipo' => $_GET['tipo'] ?? null,
            'estado' => $_GET['estado'] ?? null,
        ];

        $ubicaciones = $this->inventarioServicio->obtenerRepositorio()->listarUbicaciones($filtros);
        $data = array_map(fn($u) => $u->aArray(), $ubicaciones);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiCrearUbicacion(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ubicaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();

        try {
            $ubicacion = $this->inventarioServicio->crearUbicacion($input);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Ubicación registrada exitosamente.',
                'datos' => $ubicacion->aArray(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiActualizarUbicacion(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ubicaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;
        $input = $this->obtenerJsonInput();

        try {
            $ubicacion = $this->inventarioServicio->actualizarUbicacion($id, $input);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Ubicación actualizada exitosamente.',
                'datos' => $ubicacion->aArray(),
            ]);
        } catch (UbicacionNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: EXISTENCIAS
    // =========================================================================

    public function apiListarExistencias(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'articulo_id' => $_GET['articulo_id'] ?? null,
            'ubicacion_id' => $_GET['ubicacion_id'] ?? null,
            'propiedad_id' => $_GET['propiedad_id'] ?? null,
            'categoria' => $_GET['categoria'] ?? null,
            'solo_con_stock' => isset($_GET['solo_con_stock']) ? (bool) $_GET['solo_con_stock'] : null,
            'solo_bajo_minimo' => isset($_GET['solo_bajo_minimo']) ? (bool) $_GET['solo_bajo_minimo'] : null,
        ];

        $existencias = $this->inventarioServicio->obtenerRepositorio()->listarExistencias($filtros);
        $data = array_map(fn($ex) => $ex->aArray(), $existencias);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    // =========================================================================
    // API: MOVIMIENTOS Y KARDEX
    // =========================================================================

    public function apiListarMovimientos(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'articulo_id' => $_GET['articulo_id'] ?? null,
            'ubicacion_id' => $_GET['ubicacion_id'] ?? null,
            'tipo_movimiento' => $_GET['tipo_movimiento'] ?? null,
            'correlativo_operacion' => $_GET['correlativo_operacion'] ?? null,
            'limite' => $_GET['limite'] ?? 100,
        ];

        $movimientos = $this->inventarioServicio->obtenerRepositorio()->listarMovimientos($filtros);
        $data = array_map(fn($m) => $m->aArray(), $movimientos);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiRegistrarSaldoInicial(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.movimientos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $mov = $this->inventarioServicio->registrarSaldoInicial(
                (int) ($input['articulo_id'] ?? 0),
                (int) ($input['ubicacion_id'] ?? 0),
                (string) ($input['cantidad'] ?? '0'),
                (string) ($input['costo_unitario'] ?? '0'),
                $actorId
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Saldo inicial registrado exitosamente.',
                'datos' => $mov->aArray(),
            ], 201);
        } catch (ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiRegistrarEntrada(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.movimientos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $mov = $this->inventarioServicio->registrarEntradaCompra(
                (int) ($input['articulo_id'] ?? 0),
                (int) ($input['ubicacion_id'] ?? 0),
                (string) ($input['cantidad'] ?? '0'),
                (string) ($input['costo_unitario'] ?? '0'),
                $actorId,
                (string) ($input['motivo'] ?? 'Entrada por compra'),
                !empty($input['proveedor_id']) ? (int) $input['proveedor_id'] : null
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Entrada registrada exitosamente.',
                'datos' => $mov->aArray(),
            ], 201);
        } catch (ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiRegistrarConsumo(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.movimientos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $mov = $this->inventarioServicio->registrarSalidaConsumo(
                (int) ($input['articulo_id'] ?? 0),
                (int) ($input['ubicacion_id'] ?? 0),
                (string) ($input['cantidad'] ?? '0'),
                $actorId,
                (string) ($input['motivo'] ?? 'Consumo ordinario')
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Consumo registrado exitosamente.',
                'datos' => $mov->aArray(),
            ], 201);
        } catch (StockInsuficienteExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiRegistrarMantenimiento(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.movimientos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $mov = $this->inventarioServicio->registrarSalidaMantenimiento(
                (int) ($input['articulo_id'] ?? 0),
                (int) ($input['ubicacion_id'] ?? 0),
                (string) ($input['cantidad'] ?? '0'),
                (int) ($input['orden_trabajo_id'] ?? 0),
                $actorId,
                (string) ($input['motivo'] ?? 'Repuesto para orden de trabajo')
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Salida para mantenimiento registrada y costo de orden recalculado.',
                'datos' => $mov->aArray(),
            ], 201);
        } catch (StockInsuficienteExcepcion|ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiRegistrarTraslado(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.traslados.ejecutar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $resultado = $this->inventarioServicio->registrarTraslado(
                (int) ($input['articulo_id'] ?? 0),
                (int) ($input['ubicacion_origen_id'] ?? 0),
                (int) ($input['ubicacion_destino_id'] ?? 0),
                (string) ($input['cantidad'] ?? '0'),
                $actorId,
                (string) ($input['motivo'] ?? 'Traslado entre almacenes')
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Traslado de inventario ejecutado atómicamente.',
                'datos' => [
                    'salida' => $resultado[0]->aArray(),
                    'entrada' => $resultado[1]->aArray(),
                    'correlativo' => $resultado['correlativo'],
                ],
            ], 201);
        } catch (StockInsuficienteExcepcion|ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoInventarioExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiRegistrarAjuste(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.movimientos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $mov = $this->inventarioServicio->registrarAjusteFisico(
                (int) ($input['articulo_id'] ?? 0),
                (int) ($input['ubicacion_id'] ?? 0),
                (string) ($input['cantidad_diferencia'] ?? '0'),
                (string) ($input['tipo_ajuste'] ?? ''),
                $actorId,
                (string) ($input['motivo'] ?? 'Ajuste de inventario')
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Ajuste de inventario registrado exitosamente.',
                'datos' => $mov->aArray(),
            ], 201);
        } catch (StockInsuficienteExcepcion|ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiReversarMovimiento(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.movimientos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;
        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $reverso = $this->inventarioServicio->reversarMovimiento(
                $id,
                $actorId,
                (string) ($input['motivo'] ?? 'Reverso de movimiento')
            );

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Movimiento reversado exitosamente con trazabilidad inmutable.',
                'datos' => $reverso->aArray(),
            ], 201);
        } catch (StockInsuficienteExcepcion|ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: ACTIVOS SERIALIZADOS
    // =========================================================================

    public function apiListarActivos(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'articulo_id' => $_GET['articulo_id'] ?? null,
            'propiedad_id' => $_GET['propiedad_id'] ?? null,
            'ubicacion_id' => $_GET['ubicacion_id'] ?? null,
            'estado' => $_GET['estado'] ?? null,
            'termino' => $_GET['termino'] ?? null,
        ];

        $activos = $this->inventarioServicio->obtenerRepositorio()->listarActivos($filtros);
        $data = array_map(fn($act) => $act->aArray(), $activos);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiCrearActivo(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.activos.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $activo = $this->inventarioServicio->registrarActivo($input, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Activo serializable registrado exitosamente.',
                'datos' => $activo->aArray(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiAsignarActivo(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.activos.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;
        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $ubicacionUnidadId = (int) ($input['ubicacion_unidad_id'] ?? 0);
            $activo = $this->inventarioServicio->asignarActivoAUnidad($id, $ubicacionUnidadId, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Activo asignado a habitación física.',
                'datos' => $activo->aArray(),
            ]);
        } catch (ActivoNoEncontradoExcepcion|UbicacionNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiTransferirActivo(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.activos.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;
        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $nuevaUbicacionId = (int) ($input['nueva_ubicacion_id'] ?? 0);
            $nuevoEstado = !empty($input['nuevo_estado']) ? (string) $input['nuevo_estado'] : null;
            $motivo = !empty($input['motivo']) ? (string) $input['motivo'] : null;

            $activo = $this->inventarioServicio->transferirActivo($id, $nuevaUbicacionId, $actorId, $nuevoEstado, $motivo);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Activo transferido de ubicación.',
                'datos' => $activo->aArray(),
            ]);
        } catch (ActivoNoEncontradoExcepcion|UbicacionNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiDarDeBajaActivo(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.activos.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;
        $input = $this->obtenerJsonInput();
        $actorId = $this->obtenerActorId($usuario);

        try {
            $motivo = (string) ($input['motivo_baja'] ?? '');
            $activo = $this->inventarioServicio->darDeBajaActivo($id, $motivo, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Activo dado de baja formalmente con justificación registrada.',
                'datos' => $activo->aArray(),
            ]);
        } catch (ActivoNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionExcepcion|MovimientoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: DOTACIONES ESTÁNDAR
    // =========================================================================

    public function apiListarDotaciones(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $filtros = [
            'tipo_unidad_id' => $_GET['tipo_unidad_id'] ?? null,
            'unidad_id' => $_GET['unidad_id'] ?? null,
            'articulo_id' => $_GET['articulo_id'] ?? null,
        ];

        $dotaciones = $this->inventarioServicio->obtenerRepositorio()->listarDotacionesEstandar($filtros);
        $data = array_map(fn($d) => $d->aArray(), $dotaciones);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiGuardarDotacion(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.dotaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $input = $this->obtenerJsonInput();

        try {
            $dotacion = $this->inventarioServicio->guardarDotacionEstandar($input);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Dotación estándar guardada exitosamente.',
                'datos' => $dotacion->aArray(),
            ], 201);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiEliminarDotacion(mixed $id): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.dotaciones.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $id = (int) $id;

        try {
            $this->inventarioServicio->eliminarDotacionEstandar($id);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Dotación estándar eliminada exitosamente.',
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiAuditarDotacionUnidad(mixed $unidadId = null): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado.'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'inventario.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) ($unidadId ?? ($_GET['unidad_id'] ?? 0));
        if ($id <= 0) {
            return Respuesta::json(['exito' => false, 'error' => 'Identificador de unidad inválido.'], 400);
        }

        try {
            $auditoria = $this->inventarioServicio->auditarDotacionUnidad($id);
            return Respuesta::json([
                'exito' => true,
                'datos' => $auditoria,
            ]);
        } catch (ValidacionExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // HELPERS INTERNOS
    // =========================================================================

    private function validarCsrf(): ?Respuesta
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        if (!$token || !$this->csrfServicio->validarToken((string) $token)) {
            return Respuesta::json(['exito' => false, 'error' => 'Token CSRF inválido o expirado.'], 403);
        }

        return null;
    }

    private function obtenerJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST;
    }

    private function obtenerActorId(Usuario $usuario): int
    {
        $repo = new ActorAuditoriaRepositorio($this->pdo);
        $actor = $repo->buscarPorUsuarioId($usuario->obtenerId());
        if ($actor) {
            return (int) $actor->obtenerId();
        }

        return 1; // Fallback al actor de sistema
    }
}
