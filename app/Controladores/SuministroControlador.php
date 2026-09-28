<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ConflictoSuministroExcepcion;
use CamargoPMS\Excepciones\SuministroNoEncontradoExcepcion;
use CamargoPMS\Excepciones\TarifaFaltanteExcepcion;
use CamargoPMS\Excepciones\ValidacionSuministroExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\SuministroRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\SesionServicio;
use CamargoPMS\Servicios\SuministroServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el módulo de Suministros y Servicios Periódicos (SUMINISTROS-1 / D-081).
 */
class SuministroControlador
{
    private Vista $vista;
    private SuministroServicio $suministroServicio;
    private SuministroRepositorio $suministroRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?SuministroServicio $suministroServicio = null,
        ?SuministroRepositorio $suministroRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->vista = $vista ?? new Vista();
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->suministroRepo = $suministroRepo ?? new SuministroRepositorio($this->pdo);
        $this->suministroServicio = $suministroServicio ?? new SuministroServicio($this->pdo, $this->suministroRepo);
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

        if (!$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return new Respuesta(
                $this->vista->renderizar('errores/error', [
                    'codigo' => 403,
                    'mensaje' => 'No cuenta con permisos para acceder al módulo de suministros.',
                    'usuario' => $usuario,
                ]),
                403
            );
        }

        // Métricas de KPIs
        $stmtSum = $this->pdo->query("SELECT COUNT(*) FROM suministros WHERE estado = 'ACTIVO'");
        $totalSuministrosActivos = (int) $stmtSum->fetchColumn();

        $stmtMed = $this->pdo->query("SELECT COUNT(*) FROM suministro_medidores WHERE estado = 'ACTIVO'");
        $totalMedidoresActivos = (int) $stmtMed->fetchColumn();

        $mesActual = (int) date('n');
        $anioActual = (int) date('Y');

        $stmtLiq = $this->pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total), 0.00) FROM suministro_liquidaciones WHERE periodo_anio = :anio AND periodo_mes = :mes AND estado = 'DEVENGADO'");
        $stmtLiq->execute(['anio' => $anioActual, 'mes' => $mesActual]);
        $rowLiq = $stmtLiq->fetch(PDO::FETCH_NUM);
        $liquidacionesMes = (int) ($rowLiq[0] ?? 0);
        $montoDevengadoMes = (string) ($rowLiq[1] ?? '0.00');

        $contenido = $this->vista->renderizar('suministros/index', [
            'usuario' => $usuario,
            'permisos' => $this->autorizacionServicio->obtenerPermisosEfectivos((int) $usuario->obtenerId()),
            'csrf_token' => $this->csrfServicio->generarToken(),
            'kpis' => [
                'suministros_activos' => $totalSuministrosActivos,
                'medidores_activos' => $totalMedidoresActivos,
                'liquidaciones_mes' => $liquidacionesMes,
                'monto_devengado_pen' => number_format((float) $montoDevengadoMes, 2, '.', ','),
            ],
        ]);

        return new Respuesta($contenido);
    }

    // =========================================================================
    // API: CATÁLOGOS AUXILIARES
    // =========================================================================

    public function apiCatalogos(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $suministros = $this->suministroRepo->listarSuministros();
        $suministrosArr = array_map(static fn($s) => $s->aArreglo(), $suministros);

        $stmtProp = $this->pdo->query('SELECT id, nombre, codigo FROM propiedades WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        $propiedades = $stmtProp->fetchAll(PDO::FETCH_ASSOC);

        $stmtUnid = $this->pdo->query('SELECT u.id, u.codigo AS numero, u.nombre, u.propiedad_id, p.nombre AS propiedad_nombre FROM unidades u JOIN propiedades p ON p.id = u.propiedad_id WHERE u.estado = "ACTIVO" ORDER BY u.codigo ASC');
        $unidades = $stmtUnid->fetchAll(PDO::FETCH_ASSOC);

        // Arrendamientos activos para asociar consumos
        $stmtArr = $this->pdo->query('SELECT a.id, a.codigo, u.propiedad_id, a.unidad_id, a.fecha_inicio, a.fecha_fin, a.estado, u.codigo AS unidad_numero, p.nombre AS propiedad_nombre FROM arrendamientos a JOIN unidades u ON u.id = a.unidad_id JOIN propiedades p ON p.id = u.propiedad_id WHERE a.estado = "VIGENTE" ORDER BY a.id DESC');
        $arrendamientos = $stmtArr->fetchAll(PDO::FETCH_ASSOC);

        return Respuesta::json([
            'exito' => true,
            'datos' => [
                'suministros' => $suministrosArr,
                'propiedades' => $propiedades,
                'unidades' => $unidades,
                'arrendamientos' => $arrendamientos,
            ],
        ]);
    }

    // =========================================================================
    // API: SUMINISTROS
    // =========================================================================

    public function apiListarSuministros(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $estado = $_GET['estado'] ?? null;
        $items = $this->suministroServicio->listarSuministros($estado);
        $resultado = array_map(static fn($s) => $s->aArreglo(), $items);

        return Respuesta::json(['exito' => true, 'datos' => $resultado]);
    }

    public function apiCrearSuministro(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $sum = $this->suministroServicio->crearSuministro($datos);

            $this->auditoriaServicio->registrar(
                'CREAR',
                'suministros',
                'suministro',
                (int) $sum->obtenerId(),
                "Creación de suministro {$sum->obtenerCodigo()}",
                null,
                $sum->aArreglo(),
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'datos' => $sum->aArreglo(), 'mensaje' => 'Suministro registrado exitosamente.'], 201);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiObtenerSuministro(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $sum = $this->suministroServicio->obtenerSuministro((int) $id);
            return Respuesta::json(['exito' => true, 'datos' => $sum->aArreglo()]);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function apiActualizarSuministro(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $sum = $this->suministroServicio->actualizarSuministro((int) $id, $datos);

            return Respuesta::json(['exito' => true, 'datos' => $sum->aArreglo(), 'mensaje' => 'Suministro actualizado exitosamente.']);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: TARIFAS
    // =========================================================================

    public function apiListarTarifas(mixed $suministroId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $items = $this->suministroServicio->listarTarifasPorSuministro((int) $suministroId);
        $resultado = array_map(static fn($t) => $t->aArreglo(), $items);

        return Respuesta::json(['exito' => true, 'datos' => $resultado]);
    }

    public function apiCrearTarifa(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.tarifas.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $tarifa = $this->suministroServicio->crearTarifa($datos);

            $this->auditoriaServicio->registrar(
                'CREAR',
                'suministros',
                'suministro_tarifa',
                (int) $tarifa->obtenerId(),
                "Registro de tarifa para suministro ID {$tarifa->obtenerSuministroId()}",
                null,
                $tarifa->aArreglo(),
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'datos' => $tarifa->aArreglo(), 'mensaje' => 'Tarifa registrada exitosamente.'], 201);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: MEDIDORES FÍSICOS
    // =========================================================================

    public function apiListarMedidores(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $suministroId = isset($_GET['suministro_id']) ? (int) $_GET['suministro_id'] : null;
        $propiedadId = isset($_GET['propiedad_id']) ? (int) $_GET['propiedad_id'] : null;
        $unidadId = isset($_GET['unidad_id']) ? (int) $_GET['unidad_id'] : null;

        $items = $this->suministroServicio->listarMedidores($suministroId, $propiedadId, $unidadId);
        $resultado = array_map(static fn($m) => $m->aArreglo(), $items);

        return Respuesta::json(['exito' => true, 'datos' => $resultado]);
    }

    public function apiInstalarMedidor(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.medidores.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $medidor = $this->suministroServicio->instalarMedidor($datos, $this->obtenerActorIdActual());

            $this->auditoriaServicio->registrar(
                'INSTALAR',
                'suministros',
                'suministro_medidor',
                (int) $medidor->obtenerId(),
                "Instalación de medidor {$medidor->obtenerNumeroSerie()}",
                null,
                $medidor->aArreglo(),
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'datos' => $medidor->aArreglo(), 'mensaje' => 'Medidor instalado y registrado exitosamente.'], 201);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiReemplazarMedidor(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.medidores.gestionar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $fechaCorte = (string) ($datos['fecha_corte'] ?? date('Y-m-d'));
            $lecturaFinal = (string) ($datos['lectura_final'] ?? '');
            $datosNuevo = $datos['nuevo_medidor'] ?? [];

            if ($lecturaFinal === '') {
                return Respuesta::json(['exito' => false, 'error' => 'La lectura final del medidor saliente es requerida'], 422);
            }

            $resultado = $this->suministroServicio->reemplazarMedidor(
                (int) $id,
                $datosNuevo,
                $fechaCorte,
                $lecturaFinal,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json([
                'exito' => true,
                'datos' => [
                    'medidor_retirado' => $resultado['medidor_retirado']->aArreglo(),
                    'medidor_nuevo' => $resultado['medidor_nuevo']->aArreglo(),
                ],
                'mensaje' => 'Reemplazo de medidor ejecutado exitosamente.',
            ]);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: LECTURAS Y CORRECCIONES
    // =========================================================================

    public function apiListarLecturas(mixed $medidorId): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $soloVigentes = !isset($_GET['todas']);
        $items = $this->suministroServicio->listarLecturasPorMedidor((int) $medidorId, $soloVigentes);
        $resultado = array_map(static fn($l) => $l->aArreglo(), $items);

        return Respuesta::json(['exito' => true, 'datos' => $resultado]);
    }

    public function apiRegistrarLectura(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.lecturas.registrar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $lectura = $this->suministroServicio->registrarLectura($datos, $this->obtenerActorIdActual());

            $this->auditoriaServicio->registrar(
                'REGISTRAR',
                'suministros',
                'suministro_lectura',
                (int) $lectura->obtenerId(),
                "Lectura registrada en medidor ID {$lectura->obtenerMedidorId()}",
                null,
                $lectura->aArreglo(),
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'datos' => $lectura->aArreglo(), 'mensaje' => 'Lectura registrada exitosamente.'], 201);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiCorregirLectura(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.lecturas.corregir')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $nuevoValor = (string) ($datos['nuevo_valor'] ?? '');
            $motivo = (string) ($datos['motivo'] ?? '');

            $nueva = $this->suministroServicio->corregirLectura((int) $id, $nuevoValor, $motivo, $this->obtenerActorIdActual());

            $this->auditoriaServicio->registrar(
                'CORREGIR',
                'suministros',
                'suministro_lectura',
                (int) $nueva->obtenerId(),
                "Corrección de lectura ID {$id}: {$motivo}",
                null,
                $nueva->aArreglo(),
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'datos' => $nueva->aArreglo(), 'mensaje' => 'Lectura corregida exitosamente bajo trazabilidad inmutable.']);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API: LIQUIDACIONES Y DEVENGO FINANCIERO
    // =========================================================================

    public function apiListarLiquidaciones(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $arrendamientoId = isset($_GET['arrendamiento_id']) ? (int) $_GET['arrendamiento_id'] : null;
        $suministroId = isset($_GET['suministro_id']) ? (int) $_GET['suministro_id'] : null;
        $estado = $_GET['estado'] ?? null;

        $items = $this->suministroServicio->listarLiquidaciones($arrendamientoId, $suministroId, $estado);

        return Respuesta::json(['exito' => true, 'datos' => $items]);
    }

    public function apiObtenerLiquidacion(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.ver')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        try {
            $liq = $this->suministroServicio->obtenerLiquidacion((int) $id);
            return Respuesta::json(['exito' => true, 'datos' => $liq->aArreglo()]);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        }
    }

    public function apiLiquidarPeriodo(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.liquidar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $arrendamientoId = (int) ($datos['arrendamiento_id'] ?? 0);
            $suministroId = (int) ($datos['suministro_id'] ?? 0);
            $periodoDesde = (string) ($datos['periodo_desde'] ?? '');
            $periodoHasta = (string) ($datos['periodo_hasta'] ?? '');
            $fechaVencimiento = isset($datos['fecha_vencimiento']) && trim((string) $datos['fecha_vencimiento']) !== '' ? (string) $datos['fecha_vencimiento'] : null;

            $liq = $this->suministroServicio->liquidarPeriodoArrendamiento(
                $arrendamientoId,
                $suministroId,
                $periodoDesde,
                $periodoHasta,
                $fechaVencimiento,
                $this->obtenerActorIdActual()
            );

            $this->auditoriaServicio->registrar(
                'LIQUIDAR',
                'suministros',
                'suministro_liquidacion',
                (int) $liq->obtenerId(),
                "Liquidación de suministro folio {$liq->obtenerFolio()}",
                null,
                $liq->aArreglo(),
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'datos' => $liq->aArreglo(), 'mensaje' => 'Liquidación computada y cargo devengado formalmente en cuenta folio.'], 201);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (TarifaFaltanteExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiAnularLiquidacion(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.anular')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $motivo = (string) ($datos['motivo'] ?? '');

            $this->suministroServicio->anularLiquidacion((int) $id, $motivo, $this->obtenerActorIdActual());

            $this->auditoriaServicio->registrar(
                'ANULAR',
                'suministros',
                'suministro_liquidacion',
                (int) $id,
                "Anulación de liquidación ID {$id}: {$motivo}",
                null,
                null,
                null,
                $this->obtenerActorIdActual()
            );

            return Respuesta::json(['exito' => true, 'mensaje' => 'Liquidación y cargo asociados anulados formalmente.']);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiReliquidar(mixed $id): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if (!$usuario || !$this->autorizacionServicio->puede((int) $usuario->obtenerId(), 'suministros.liquidar')) {
            return Respuesta::json(['exito' => false, 'error' => 'No autorizado'], 403);
        }

        $errorCsrf = $this->validarCsrf();
        if ($errorCsrf) {
            return $errorCsrf;
        }

        try {
            $datos = $this->obtenerCuerpoJson();
            $motivo = (string) ($datos['motivo'] ?? 'Corrección de lecturas/tarifas');

            $nueva = $this->suministroServicio->reliquidarPorCorreccion((int) $id, $motivo, $this->obtenerActorIdActual());

            return Respuesta::json([
                'exito' => true,
                'datos' => $nueva->aArreglo(),
                'mensaje' => 'Reliquidación completada exitosamente. Se anuló el cargo previo y se devengó la revisión corregida.',
            ]);
        } catch (SuministroNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 404);
        } catch (ValidacionSuministroExcepcion $e) {
            return Respuesta::json(['exito' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictoSuministroExcepcion $e) {
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
