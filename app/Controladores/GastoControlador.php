<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\CajaNoAbiertaExcepcion;
use CamargoPMS\Excepciones\ConflictoGastoExcepcion;
use CamargoPMS\Excepciones\GastoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\PagoEgresoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionGastoExcepcion;
use CamargoPMS\Modelos\Usuario;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\GastoRepositorio;
use CamargoPMS\Repositorios\PagoEgresoRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\GastoServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para la gestión de Gastos Operativos, Egresos Administrativos y Tesorería.
 * GASTOS-1 / D-086.
 */
class GastoControlador
{
    private Vista $vista;
    private GastoServicio $gastoServicio;
    private GastoRepositorio $gastoRepo;
    private PagoEgresoRepositorio $pagoRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;
    private PDO $pdo;

    public function __construct(
        ?Vista $vista = null,
        ?GastoServicio $gastoServicio = null,
        ?GastoRepositorio $gastoRepo = null,
        ?PagoEgresoRepositorio $pagoRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null,
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->vista = $vista ?? new Vista();
        $this->gastoRepo = $gastoRepo ?? new GastoRepositorio($this->pdo);
        $this->pagoRepo = $pagoRepo ?? new PagoEgresoRepositorio($this->pdo);
        $this->gastoServicio = $gastoServicio ?? new GastoServicio($this->pdo, $this->gastoRepo, $this->pagoRepo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->pdo);
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    private function obtenerUsuarioAutenticado(): ?Usuario
    {
        SesionServicio::iniciarSesionPhp();
        return $this->sesionServicio->validarSesionActual();
    }

    private function obtenerActorIdActual(): int
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario !== null && $usuario->obtenerId() !== null) {
            $actorRepo = new ActorAuditoriaRepositorio($this->pdo);
            $actor = $actorRepo->buscarPorUsuarioId($usuario->obtenerId());
            if ($actor !== null && $actor->obtenerId() !== null) {
                return (int) $actor->obtenerId();
            }
        }
        return 1;
    }

    private function leerEntradaJson(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                return $dec;
            }
        }
        return $_POST;
    }

    /**
     * Vista principal del módulo de Gastos.
     */
    public function index(): Respuesta
    {
        $usuario = $this->obtenerUsuarioAutenticado();
        if ($usuario === null) {
            return Respuesta::redireccionar('/login');
        }

        $categorias = $this->gastoServicio->listarCategorias(true);

        $stmtP = $this->pdo->query('SELECT id, nombre, codigo FROM propiedades WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        $propiedades = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        $stmtU = $this->pdo->query('SELECT id, propiedad_id, codigo, nombre FROM unidades WHERE estado = "ACTIVO" ORDER BY codigo ASC');
        $unidades = $stmtU->fetchAll(PDO::FETCH_ASSOC);

        $stmtM = $this->pdo->query('SELECT id, codigo, nombre, tipo_destino FROM metodos_pago WHERE activo = 1 ORDER BY id ASC');
        $metodosPago = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        $stmtB = $this->pdo->query('SELECT id, codigo, banco_nombre, numero_cuenta, moneda_codigo FROM cuentas_bancarias WHERE estado = "ACTIVO" ORDER BY id ASC');
        $cuentasBancarias = $stmtB->fetchAll(PDO::FETCH_ASSOC);

        $contenido = $this->vista->renderizar('gastos/index', [
            'titulo' => 'Gastos Operativos, Egresos y Tesorería — Camargo PMS',
            'usuario' => $usuario,
            'categorias' => $categorias,
            'propiedades' => $propiedades,
            'unidades' => $unidades,
            'metodosPago' => $metodosPago,
            'cuentasBancarias' => $cuentasBancarias,
            'csrf_token' => $this->csrfServicio->generarToken(),
        ]);

        return new Respuesta($contenido);
    }

    /**
     * API: Listar gastos con filtros y verdad reconstructible.
     */
    public function apiListar(): Respuesta
    {
        try {
            $filtros = [
                'categoria_id' => $_GET['categoria_id'] ?? null,
                'ambito' => $_GET['ambito'] ?? null,
                'propiedad_id' => $_GET['propiedad_id'] ?? null,
                'unidad_id' => $_GET['unidad_id'] ?? null,
                'estado' => $_GET['estado'] ?? null,
                'fecha_desde' => $_GET['fecha_desde'] ?? null,
                'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
            ];

            $gastos = $this->gastoServicio->listarGastos($filtros);
            $resumenes = [];
            foreach ($gastos as $g) {
                if ($g->obtenerId() !== null) {
                    $dto = $this->gastoServicio->obtenerResumenGasto($g->obtenerId());
                    if ($dto !== null) {
                        $resumenes[] = $dto->aArreglo();
                    }
                }
            }

            return Respuesta::json([
                'exito' => true,
                'total' => count($resumenes),
                'datos' => $resumenes,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Obtener detalle completo de un gasto y su proyección de saldo.
     */
    public function apiDetalle(array $parametros): Respuesta
    {
        try {
            $id = (int) ($parametros['id'] ?? 0);
            $resumen = $this->gastoServicio->obtenerResumenGasto($id);
            if ($resumen === null) {
                return Respuesta::json([
                    'exito' => false,
                    'mensaje' => "El gasto [{$id}] no existe.",
                ], 404);
            }

            $datos = $resumen->aArreglo();
            $datos['evidencias_lista'] = array_map(fn($e) => $e->aArreglo(), $resumen->obtenerEvidencias());
            $datos['aplicaciones_lista'] = array_map(fn($a) => $a->aArreglo(), $resumen->obtenerAplicaciones());

            return Respuesta::json([
                'exito' => true,
                'datos' => $datos,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Registrar un nuevo gasto.
     */
    public function apiCrear(): Respuesta
    {
        try {
            $entrada = $this->leerEntradaJson();
            $actorId = $this->obtenerActorIdActual();

            $gasto = $this->gastoServicio->crearGasto($entrada, $actorId);
            $resumen = $this->gastoServicio->obtenerResumenGasto((int) $gasto->obtenerId());

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Gasto [{$gasto->obtenerCodigo()}] registrado correctamente.",
                'datos' => $resumen ? $resumen->aArreglo() : $gasto->aArreglo(),
            ], 201);
        } catch (ValidacionGastoExcepcion | MontoInvalidoExcepcion $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return Respuesta::json([
                'exito' => false,
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Aprobar un gasto.
     */
    public function apiAprobar(array $parametros): Respuesta
    {
        try {
            $id = (int) ($parametros['id'] ?? 0);
            $entrada = $this->leerEntradaJson();
            $actorId = $this->obtenerActorIdActual();
            $motivo = $entrada['motivo'] ?? null;

            $gasto = $this->gastoServicio->aprobarGasto($id, $actorId, $motivo);
            $resumen = $this->gastoServicio->obtenerResumenGasto($id);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Gasto [{$gasto->obtenerCodigo()}] aprobado exitosamente.",
                'datos' => $resumen ? $resumen->aArreglo() : $gasto->aArreglo(),
            ]);
        } catch (GastoNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoGastoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Anular un gasto.
     */
    public function apiAnular(array $parametros): Respuesta
    {
        try {
            $id = (int) ($parametros['id'] ?? 0);
            $entrada = $this->leerEntradaJson();
            $actorId = $this->obtenerActorIdActual();
            $motivo = (string) ($entrada['motivo'] ?? '');

            $gasto = $this->gastoServicio->anularGasto($id, $actorId, $motivo);
            $resumen = $this->gastoServicio->obtenerResumenGasto($id);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Gasto [{$gasto->obtenerCodigo()}] anulado correctamente.",
                'datos' => $resumen ? $resumen->aArreglo() : $gasto->aArreglo(),
            ]);
        } catch (GastoNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoGastoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (ValidacionGastoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Pagar / Liquidar un gasto (ejecución atómica en Tesorería).
     */
    public function apiPagar(array $parametros): Respuesta
    {
        try {
            $id = (int) ($parametros['id'] ?? 0);
            $entrada = $this->leerEntradaJson();
            $actorId = $this->obtenerActorIdActual();

            // Si vino pago directo de 1 solo gasto
            if (empty($entrada['aplicaciones'])) {
                $monto = (string) ($entrada['monto'] ?? '0.00');
                $entrada['aplicaciones'] = [
                    ['gasto_id' => $id, 'monto' => $monto],
                ];
            }

            $resultado = $this->gastoServicio->ejecutarPagoEgreso($entrada, $actorId);
            $resumen = $this->gastoServicio->obtenerResumenGasto($id);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Pago [{$resultado['pago']->obtenerCodigo()}] aplicado exitosamente por S/ {$resultado['pago']->obtenerMontoTotal()}.",
                'datos' => [
                    'pago' => $resultado['pago']->aArreglo(),
                    'gasto' => $resumen ? $resumen->aArreglo() : null,
                ],
            ]);
        } catch (GastoNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (CajaNoAbiertaExcepcion | ConflictoGastoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (ValidacionGastoExcepcion | MontoInvalidoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Reversar un pago de egreso.
     */
    public function apiReversarPago(array $parametros): Respuesta
    {
        try {
            $id = (int) ($parametros['id'] ?? 0);
            $entrada = $this->leerEntradaJson();
            $actorId = $this->obtenerActorIdActual();
            $motivo = (string) ($entrada['motivo'] ?? 'Reversión autorizada');

            $this->gastoServicio->reversarPagoEgreso($id, $actorId, $motivo);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => "Pago de egreso [{$id}] reversado exitosamente. Los saldos fueron restituidos.",
            ]);
        } catch (PagoEgresoNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Adjuntar evidencia a un gasto.
     */
    public function apiAdjuntarEvidencia(array $parametros): Respuesta
    {
        try {
            $id = (int) ($parametros['id'] ?? 0);
            $entrada = $this->leerEntradaJson();
            $actorId = $this->obtenerActorIdActual();

            $evidencia = $this->gastoServicio->adjuntarEvidencia($id, $entrada, $actorId);

            return Respuesta::json([
                'exito' => true,
                'mensaje' => 'Evidencia documental adjuntada correctamente.',
                'datos' => $evidencia->aArreglo(),
            ]);
        } catch (GastoNoEncontradoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (ConflictoGastoExcepcion $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Listar categorías activas.
     */
    public function apiCategorias(): Respuesta
    {
        try {
            $categorias = $this->gastoServicio->listarCategorias(true);
            return Respuesta::json([
                'exito' => true,
                'datos' => array_map(fn($c) => $c->aArreglo(), $categorias),
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}
