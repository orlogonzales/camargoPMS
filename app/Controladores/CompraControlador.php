<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\CompraNoEncontradaExcepcion;
use CamargoPMS\Excepciones\ConflictoCompraExcepcion;
use CamargoPMS\Excepciones\OrdenNoModificableExcepcion;
use CamargoPMS\Excepciones\ValidacionCompraExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CompraRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CompraServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el módulo de Abastecimiento y Compras (COMPRAS-1 / D-080).
 */
class CompraControlador
{
    private Vista $vista;
    private CompraServicio $compraServicio;
    private CompraRepositorio $compraRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?CompraServicio $compraServicio = null,
        ?CompraRepositorio $compraRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->compraRepo = $compraRepo ?? new CompraRepositorio($this->pdo);
        $this->compraServicio = $compraServicio ?? new CompraServicio($this->pdo, $this->compraRepo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio($this->pdo);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    // =========================================================================
    // VISTA PRINCIPAL (ALINA D-075)
    // =========================================================================

    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::redirigir('/login');
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return new Respuesta(
                $this->vista->renderizar('errores/error', [
                    'codigo' => 403,
                    'mensaje' => 'No cuenta con permisos para acceder al módulo de compras.',
                    'usuario' => $usuario,
                ]),
                403
            );
        }

        // Métricas para los KPIs
        $stmtOC = $this->pdo->query("SELECT COUNT(*) FROM compra_ordenes WHERE estado_comercial = 'APROBADA'");
        $totalOrdenesAprobadas = (int) $stmtOC->fetchColumn();

        $stmtPendRec = $this->pdo->query("SELECT COUNT(*) FROM compra_ordenes WHERE estado_comercial = 'APROBADA' AND estado_recepcion IN ('SIN_RECEPCION', 'RECEPCION_PARCIAL')");
        $ordenesPorRecibir = (int) $stmtPendRec->fetchColumn();

        $stmtCxpPend = $this->pdo->query("SELECT COUNT(*), COALESCE(SUM(saldo_pendiente), 0.00) FROM cuentas_por_pagar WHERE estado IN ('PENDIENTE', 'AMORTIZADA_PARCIAL')");
        $cxpData = $stmtCxpPend->fetch(PDO::FETCH_NUM);
        $totalCxpPendientes = (int) ($cxpData[0] ?? 0);
        $montoDeudaPendiente = (string) ($cxpData[1] ?? '0.00');

        $stmtSolPend = $this->pdo->query("SELECT COUNT(*) FROM compra_solicitudes WHERE estado = 'PENDIENTE'");
        $solicitudesPendientes = (int) $stmtSolPend->fetchColumn();

        $contenido = $this->vista->renderizar('compras/index', [
            'usuario' => $usuario,
            'permisos' => $this->autorizacionServicio->obtenerPermisosEfectivos((int) $usuario->obtenerId()),
            'csrf_token' => $this->csrfServicio->generarToken(),
            'kpis' => [
                'ordenes_activas' => $totalOrdenesAprobadas,
                'por_recibir' => $ordenesPorRecibir,
                'cxp_pendientes' => $totalCxpPendientes,
                'deuda_total_pen' => number_format((float) $montoDeudaPendiente, 2, '.', ','),
                'solicitudes_pendientes' => $solicitudesPendientes,
            ],
        ]);

        return new Respuesta($contenido);
    }

    // =========================================================================
    // API: CATÁLOGOS
    // =========================================================================

    public function apiCatalogos(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $stmtProv = $this->pdo->query("SELECT id, razon_social, numero_documento, email, telefono, direccion FROM proveedores WHERE estado = 'ACTIVO' ORDER BY razon_social ASC");
        $proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

        $stmtAlm = $this->pdo->query("SELECT u.id, u.codigo, u.nombre, prop.direccion FROM inventario_ubicaciones u LEFT JOIN propiedades prop ON prop.id = u.propiedad_id WHERE u.tipo = 'ALMACEN' AND u.estado = 'ACTIVO' ORDER BY u.nombre ASC");
        $almacenes = $stmtAlm->fetchAll(PDO::FETCH_ASSOC);

        $stmtArt = $this->pdo->query("SELECT ia.id, ia.codigo_sku, ia.nombre, ia.categoria, ia.costo_referencial, ia.moneda_codigo, um.codigo AS unidad_medida FROM inventario_articulos ia JOIN inventario_unidades_medida um ON um.id = ia.unidad_medida_id WHERE ia.estado = 'ACTIVO' ORDER BY ia.nombre ASC");
        $articulos = $stmtArt->fetchAll(PDO::FETCH_ASSOC);

        $stmtCaja = $this->pdo->query("SELECT sc.id, cf.codigo AS codigo, cf.nombre AS caja_nombre FROM sesiones_caja sc JOIN cajas_fisicas cf ON cf.id = sc.caja_fisica_id WHERE sc.estado = 'ABIERTA' ORDER BY sc.id DESC");
        $sesionesCaja = $stmtCaja->fetchAll(PDO::FETCH_ASSOC);

        $datosCatalogos = [
            'proveedores' => $proveedores,
            'almacenes' => $almacenes,
            'articulos' => $articulos,
            'sesiones_caja' => $sesionesCaja,
        ];

        return Respuesta::json(array_merge([
            'exito' => true,
            'datos' => $datosCatalogos,
        ], $datosCatalogos));
    }

    // =========================================================================
    // API: SOLICITUDES DE COMPRA
    // =========================================================================

    public function apiListarSolicitudes(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $filtros = [
            'estado' => $_GET['estado'] ?? null,
            'departamento_area' => $_GET['departamento_area'] ?? null,
        ];

        $solicitudes = $this->compraServicio->listarSolicitudes($filtros);
        $data = array_map(fn($s) => $s->aArreglo(), $solicitudes);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiCrearSolicitud(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.solicitudes.crear')) {
            return Respuesta::json(['exito' => false, 'error' => 'No tiene permisos para crear solicitudes de compra.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $actorId = $this->obtenerActorIdActual();

        try {
            $sol = $this->compraServicio->crearSolicitud($body, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Solicitud [{$sol->obtenerCodigo()}] registrada exitosamente.",
                'datos' => $sol->aArreglo(),
            ], 201);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiObtenerSolicitud(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $sol = $this->compraServicio->obtenerSolicitud((int) $id);
            return Respuesta::json(['exito' => true, 'datos' => $sol->aArreglo()]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function apiAprobarSolicitud(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.solicitudes.aprobar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para aprobar solicitudes de compra.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $actorId = $this->obtenerActorIdActual();

        try {
            $sol = $this->compraServicio->aprobarSolicitud((int) $id, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Solicitud [{$sol->obtenerCodigo()}] aprobada formalmente.",
                'datos' => $sol->aArreglo(),
            ]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiRechazarSolicitud(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.solicitudes.aprobar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para rechazar solicitudes de compra.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $motivo = trim((string) ($body['motivo'] ?? ''));
        $actorId = $this->obtenerActorIdActual();

        try {
            $sol = $this->compraServicio->rechazarSolicitud((int) $id, $motivo, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Solicitud [{$sol->obtenerCodigo()}] rechazada.",
                'datos' => $sol->aArreglo(),
            ]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: ÓRDENES DE COMPRA
    // =========================================================================

    public function apiListarOrdenes(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $filtros = [
            'estado_comercial' => $_GET['estado_comercial'] ?? null,
            'estado_recepcion' => $_GET['estado_recepcion'] ?? null,
            'estado_facturacion' => $_GET['estado_facturacion'] ?? null,
            'estado_pago' => $_GET['estado_pago'] ?? null,
            'proveedor_id' => $_GET['proveedor_id'] ?? null,
        ];

        $ordenes = $this->compraServicio->listarOrdenes($filtros);
        $data = array_map(fn($o) => $o->aArreglo(), $ordenes);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiCrearOrden(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ordenes.crear')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para crear órdenes de compra.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $actorId = $this->obtenerActorIdActual();

        try {
            $orden = $this->compraServicio->crearOrden($body, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Orden de compra [{$orden->obtenerCodigo()}] formulada exitosamente en BORRADOR.",
                'datos' => $orden->aArreglo(),
            ], 201);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiObtenerOrden(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $orden = $this->compraServicio->obtenerOrden((int) $id);
            return Respuesta::json(['exito' => true, 'datos' => $orden->aArreglo()]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function apiAprobarOrden(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ordenes.aprobar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para aprobar órdenes de compra.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $actorId = $this->obtenerActorIdActual();

        try {
            $orden = $this->compraServicio->aprobarOrden((int) $id, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Orden de compra [{$orden->obtenerCodigo()}] aprobada y condiciones comerciales congeladas.",
                'datos' => $orden->aArreglo(),
            ]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiCancelarOrden(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.anular')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para cancelar órdenes de compra.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $motivo = trim((string) ($body['motivo'] ?? ''));
        $actorId = $this->obtenerActorIdActual();

        try {
            $orden = $this->compraServicio->cancelarOrden((int) $id, $motivo, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Orden de compra [{$orden->obtenerCodigo()}] cancelada.",
                'datos' => $orden->aArreglo(),
            ]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionCompraExcepcion|OrdenNoModificableExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function descargarPdfOrden(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return new Respuesta('Acceso denegado', 403);
        }

        $esBorrador = isset($_GET['borrador']) && $_GET['borrador'] === '1';
        $actorId = $this->obtenerActorIdActual();

        try {
            $res = $this->compraServicio->emitirPdfOrden((int) $id, $actorId, $esBorrador);

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $res['nombre_archivo'] . '"');
            header('Content-Length: ' . strlen($res['binario_pdf']));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $res['binario_pdf'];
            exit;
        } catch (Throwable $e) {
            return new Respuesta('Error al generar PDF de la orden: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // API: RECEPCIONES FÍSICAS
    // =========================================================================

    public function apiListarRecepciones(mixed $ordenId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $recepciones = $this->compraServicio->listarRecepcionesPorOrden((int) $ordenId);
        $data = array_map(fn($r) => $r->aArreglo(), $recepciones);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiRegistrarRecepcion(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.recepciones.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para registrar recepciones físicas.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $actorId = $this->obtenerActorIdActual();

        try {
            $rec = $this->compraServicio->registrarRecepcion($body, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Recepción física [{$rec->obtenerCodigo()}] registrada y Kardex actualizado.",
                'datos' => $rec->aArreglo(),
            ], 201);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiObtenerRecepcion(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $rec = $this->compraServicio->obtenerRecepcion((int) $id);
            return Respuesta::json(['exito' => true, 'datos' => $rec->aArreglo()]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        }
    }

    // =========================================================================
    // API: CONFORMIDADES DE SERVICIO
    // =========================================================================

    public function apiListarConformidades(mixed $ordenId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $conformidades = $this->compraServicio->listarConformidadesPorOrden((int) $ordenId);
        $data = array_map(fn($c) => $c->aArreglo(), $conformidades);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiRegistrarConformidad(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.conformidad.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para emitir actas de conformidad.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $actorId = $this->obtenerActorIdActual();

        try {
            $conf = $this->compraServicio->registrarConformidad($body, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Acta de conformidad [{$conf->obtenerCodigo()}] registrada exitosamente (sin afectación de Kardex).",
                'datos' => $conf->aArreglo(),
            ], 201);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: COMPROBANTES Y 3-WAY MATCHING
    // =========================================================================

    public function apiListarComprobantes(mixed $ordenId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $comprobantes = $this->compraServicio->listarComprobantesPorOrden((int) $ordenId);
        $data = array_map(fn($c) => $c->aArreglo(), $comprobantes);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiRegistrarComprobante(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.comprobantes.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para registrar comprobantes fiscales.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $actorId = $this->obtenerActorIdActual();

        try {
            $resultado = $this->compraServicio->registrarComprobante($body, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Comprobante {$resultado['comprobante']->obtenerTipoComprobante()} {$resultado['comprobante']->obtenerSerie()}-{$resultado['comprobante']->obtenerNumero()} registrado y pasivo devengado [{$resultado['cuenta_por_pagar']->obtenerCodigo()}].",
                'comprobante' => $resultado['comprobante']->aArreglo(),
                'cuenta_por_pagar' => $resultado['cuenta_por_pagar']->aArreglo(),
            ], 201);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: CUENTAS POR PAGAR Y PAGOS
    // =========================================================================

    public function apiListarCuentasPorPagar(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.cuentas_pagar.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $filtros = [
            'estado' => $_GET['estado'] ?? null,
            'proveedor_id' => $_GET['proveedor_id'] ?? null,
            'orden_compra_id' => $_GET['orden_compra_id'] ?? null,
        ];

        $cxps = $this->compraServicio->listarCuentasPorPagar($filtros);
        $data = array_map(fn($c) => $c->aArreglo(), $cxps);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiObtenerCuentaPorPagar(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.cuentas_pagar.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $cxp = $this->compraServicio->obtenerCuentaPorPagar((int) $id);
            return Respuesta::json(['exito' => true, 'datos' => $cxp->aArreglo()]);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function apiListarPagosCxp(mixed $cxpId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.cuentas_pagar.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $pagos = $this->compraServicio->listarPagosPorCuentaPagar((int) $cxpId);
        $data = array_map(fn($p) => $p->aArreglo(), $pagos);

        return Respuesta::json(['exito' => true, 'datos' => $data]);
    }

    public function apiRegistrarPagoCxp(mixed $cxpId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario) {
            return Respuesta::json(['exito' => false, 'error' => 'No autenticado'], 401);
        }

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'compras.pagos.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No cuenta con permisos para registrar pagos a proveedores.'], 403);
        }

        $csrfErr = $this->validarCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $body = $this->obtenerCuerpoJson();
        $body['cuenta_pagar_id'] = (int) $cxpId;
        $actorId = $this->obtenerActorIdActual();

        try {
            $pago = $this->compraServicio->registrarPagoCxp($body, $actorId);
            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Pago de {$pago->obtenerMonto()} PEN registrado exitosamente.",
                'datos' => $pago->aArreglo(),
            ], 201);
        } catch (CompraNoEncontradaExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoCompraExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // HELPERS INTERNOS
    // =========================================================================

    private function obtenerUsuarioAutenticado(): ?\CamargoPMS\Modelos\Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function obtenerActorIdActual(): int
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
