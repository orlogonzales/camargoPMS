<?php

declare(strict_types=1);

namespace CamargoPMS\Controladores;

use CamargoPMS\Excepciones\ArqueoCajaInvalidoExcepcion;
use CamargoPMS\Excepciones\CajaNoAbiertaExcepcion;
use CamargoPMS\Excepciones\CargoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\CuentaFolioNoEncontradaExcepcion;
use CamargoPMS\Excepciones\DevolucionExcedidaExcepcion;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\PagoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\SaldoInsuficienteCargoExcepcion;
use CamargoPMS\Excepciones\SaldoInsuficientePagoExcepcion;
use CamargoPMS\Excepciones\ValidacionExcepcion;
use CamargoPMS\Nucleo\BaseDatos;
use CamargoPMS\Nucleo\Respuesta;
use CamargoPMS\Nucleo\Vista;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CajaRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Servicios\AuditoriaServicio;
use CamargoPMS\Servicios\AutorizacionServicio;
use CamargoPMS\Servicios\CajaServicio;
use CamargoPMS\Servicios\CsrfServicio;
use CamargoPMS\Servicios\CuentaFolioServicio;
use CamargoPMS\Servicios\SesionServicio;
use PDO;
use Throwable;

/**
 * Controlador HTTP para el subsistema financiero de tesorería, folios, cargos, cobros y arqueos de caja (FINANCIERO-2).
 *
 * Principios vinculantes:
 * - Tríada Financiera: CARGO != PAGO != MOVIMIENTO DE CAJA.
 * - Desacoplamiento de cobro y deuda: PAGO != APLICACIÓN.
 * - Cero flags booleanos (pagado=1) y cero DELETE físico.
 * - Manejo riguroso de precisión decimal con BCMath.
 * - Trazabilidad D-061 (ACTOR != USUARIO).
 * - Protección CSRF en mutaciones y RBAC granular.
 */
class CajaControlador
{
    private Vista $vista;
    private CajaServicio $cajaServicio;
    private CuentaFolioServicio $cuentaFolioServicio;
    private CajaRepositorio $cajaRepo;
    private CuentaFolioRepositorio $folioRepo;
    private ReservaRepositorio $reservaRepo;
    private SesionServicio $sesionServicio;
    private AutorizacionServicio $autorizacionServicio;
    private AuditoriaServicio $auditoriaServicio;
    private CsrfServicio $csrfServicio;

    public function __construct(
        ?Vista $vista = null,
        ?CajaServicio $cajaServicio = null,
        ?CuentaFolioServicio $cuentaFolioServicio = null,
        ?CajaRepositorio $cajaRepo = null,
        ?CuentaFolioRepositorio $folioRepo = null,
        ?ReservaRepositorio $reservaRepo = null,
        ?SesionServicio $sesionServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?CsrfServicio $csrfServicio = null
    ) {
        $pdo = BaseDatos::conexion();
        $this->vista = $vista ?? new Vista();
        $this->cajaServicio = $cajaServicio ?? new CajaServicio($pdo);
        $this->cuentaFolioServicio = $cuentaFolioServicio ?? new CuentaFolioServicio($pdo);
        $this->cajaRepo = $cajaRepo ?? new CajaRepositorio($pdo);
        $this->folioRepo = $folioRepo ?? new CuentaFolioRepositorio($pdo);
        $this->reservaRepo = $reservaRepo ?? new ReservaRepositorio($pdo);
        $this->sesionServicio = $sesionServicio ?? new SesionServicio();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio();
        $this->csrfServicio = $csrfServicio ?? new CsrfServicio();
    }

    /**
     * Renderiza el tablero principal de tesorería y cuentas folios (GET /caja).
     */
    public function index(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();

        $usuarioActual = $this->sesionServicio->validarSesionActual();
        $usuarioActualId = $usuarioActual !== null ? (int) $usuarioActual->obtenerId() : 0;

        if (!$this->autorizacionServicio->puede($usuarioActualId, 'caja.ver')) {
            return new Respuesta($this->vista->renderizar('errores/error', [
                'titulo' => '403 — Acceso denegado',
                'codigo' => 403,
                'mensaje' => 'No tiene permisos para acceder al módulo financiero y de caja.',
            ], 'error'), 403);
        }

        $cajasFisicas = $this->cajaRepo->listarCajasFisicas();
        $metodosPago = $this->cajaRepo->listarMetodosPago();
        $cuentasBancarias = $this->cajaRepo->listarCuentasBancarias();

        // Sesión activa para el actor actual en la caja principal o primera activa
        $actorId = $this->resolverActorId($usuarioActual);
        $cajaPredeterminadaId = !empty($cajasFisicas) ? (int) $cajasFisicas[0]->obtenerId() : 1;
        $sesionActiva = $this->cajaServicio->obtenerSesionActiva($cajaPredeterminadaId, $actorId);

        $csrfToken = $this->csrfServicio->obtenerToken();

        $capacidades = [
            'puede_ver' => true,
            'puede_abrir' => $this->autorizacionServicio->puede($usuarioActualId, 'caja.abrir'),
            'puede_cerrar' => $this->autorizacionServicio->puede($usuarioActualId, 'caja.cerrar'),
            'puede_cobrar' => $this->autorizacionServicio->puede($usuarioActualId, 'caja.cobrar'),
            'puede_aplicar' => $this->autorizacionServicio->puede($usuarioActualId, 'caja.aplicar'),
            'puede_devolver' => $this->autorizacionServicio->puede($usuarioActualId, 'caja.devolver'),
            'puede_movimiento' => $this->autorizacionServicio->puede($usuarioActualId, 'caja.movimiento'),
        ];

        $html = $this->vista->renderizar('caja/index', [
            'titulo' => 'Caja y Cuentas',
            'subtitulo' => 'Gestión de folios, cargos, cobros, aplicaciones de pago, devoluciones y arqueo de caja',
            'cajas_fisicas' => $cajasFisicas,
            'metodos_pago' => $metodosPago,
            'cuentas_bancarias' => $cuentasBancarias,
            'sesion_activa' => $sesionActiva ? $sesionActiva->haciaArreglo() : null,
            'csrf_token' => $csrfToken,
            'capacidades' => $capacidades,
            'usuario_actual' => $usuarioActual,
        ], 'principal');

        return new Respuesta($html);
    }

    // =========================================================================
    // Endpoints JSON: Cuentas y Folios
    // =========================================================================

    public function listarFolios(): Respuesta
    {
        try {
            $filtros = [
                'reserva_id' => $_GET['reserva_id'] ?? null,
                'estado' => $_GET['estado'] ?? null,
                'q' => $_GET['q'] ?? null,
            ];

            $datos = $this->cuentaFolioServicio->listarFolios($filtros);

            return Respuesta::json([
                'ok' => true,
                'datos' => $datos,
                'total' => count($datos),
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al listar folios: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerEstadoCuenta(mixed $id): Respuesta
    {
        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        try {
            $estadoCuenta = $this->cuentaFolioServicio->obtenerEstadoCuenta($identificador);
            return Respuesta::json([
                'ok' => true,
                'datos' => $estadoCuenta,
            ]);
        } catch (CuentaFolioNoEncontradaExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener estado de cuenta: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerFolioPorReserva(mixed $id): Respuesta
    {
        $reservaId = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        try {
            $folio = $this->cuentaFolioServicio->crearOAsegurarFolioReserva($reservaId, $actorId);
            // Sincronizar noches de alojamiento
            $this->cuentaFolioServicio->generarCargosAlojamiento($reservaId, $actorId);

            $estadoCuenta = $this->cuentaFolioServicio->obtenerEstadoCuenta((int) $folio->obtenerId());
            return Respuesta::json([
                'ok' => true,
                'datos' => $estadoCuenta,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al obtener folio de reserva: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Endpoints JSON: Sesiones de Caja y Arqueo
    // =========================================================================

    public function aperturarSesion(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $cajaFisicaId = (int) ($datos['caja_fisica_id'] ?? 1);
        $montoApertura = (string) ($datos['monto_apertura'] ?? '0.00');
        $observaciones = !empty($datos['observaciones']) ? (string) $datos['observaciones'] : null;

        try {
            $sesion = $this->cajaServicio->aperturarSesion($cajaFisicaId, $montoApertura, $observaciones, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Turno de caja aperturado correctamente con fondo de cambio.',
                'datos' => $sesion->haciaArreglo(),
            ]);
        } catch (MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al aperturar sesión de caja: ' . $e->getMessage()], 500);
        }
    }

    public function cerrarSesion(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $sesionId = (int) ($datos['sesion_id'] ?? 0);
        $montoDeclarado = (string) ($datos['monto_contado_declarado'] ?? ($datos['monto_cierre_real'] ?? ($datos['monto_declarado'] ?? '0.00')));
        $observacionesCierre = !empty($datos['observaciones_cierre']) ? (string) $datos['observaciones_cierre'] : (!empty($datos['observaciones']) ? (string) $datos['observaciones'] : null);

        try {
            $sesion = $this->cajaServicio->cerrarSesion($sesionId, $montoDeclarado, $observacionesCierre, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Cierre de caja completado con arqueo: {$sesion->obtenerResultadoArqueo()}.",
                'datos' => $sesion->haciaArreglo(),
            ]);
        } catch (ArqueoCajaInvalidoExcepcion | MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cerrar sesión de caja: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerSesionActiva(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $cajaId = (int) ($_GET['caja_fisica_id'] ?? 1);

        try {
            $sesion = $this->cajaServicio->obtenerSesionActiva($cajaId, $actorId);
            return Respuesta::json([
                'ok' => true,
                'datos' => $sesion ? $sesion->haciaArreglo() : null,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al consultar sesión activa: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerSesion(mixed $id): Respuesta
    {
        $identificador = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        try {
            $sesion = $this->cajaServicio->obtenerSesion($identificador);
            if ($sesion === null) {
                return Respuesta::json(['ok' => false, 'mensaje' => "Sesión de caja ID {$identificador} no encontrada."], 404);
            }

            $movimientos = $this->cajaServicio->listarMovimientosSesion($identificador);

            return Respuesta::json([
                'ok' => true,
                'datos' => [
                    'sesion' => $sesion->haciaArreglo(),
                    'movimientos' => array_map(fn($m) => $m->haciaArreglo(), $movimientos),
                ],
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al consultar sesión de caja: ' . $e->getMessage()], 500);
        }
    }

    public function registrarMovimiento(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $sesionId = (int) ($datos['sesion_id'] ?? 0);
        $tipo = (string) ($datos['tipo'] ?? 'INGRESO_MANUAL');
        $monto = (string) ($datos['monto'] ?? '0.00');
        $concepto = (string) ($datos['concepto'] ?? ($datos['motivo'] ?? ''));

        try {
            $mov = $this->cajaServicio->registrarMovimientoManual($sesionId, $tipo, $monto, $concepto, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => 'Movimiento de efectivo registrado exitosamente.',
                'datos' => $mov->haciaArreglo(),
            ]);
        } catch (CajaNoAbiertaExcepcion | MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al registrar movimiento: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Endpoints JSON: Cobros / Pagos
    // =========================================================================

    public function registrarPago(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $folioId = (int) ($datos['cuenta_folio_id'] ?? 0);
        $metodoPagoId = (int) ($datos['metodo_pago_id'] ?? 0);
        $montoTotal = (string) ($datos['monto_total'] ?? ($datos['monto'] ?? '0.00'));
        $sesionCajaId = !empty($datos['sesion_caja_id']) ? (int) $datos['sesion_caja_id'] : null;
        $cuentaBancariaId = !empty($datos['cuenta_bancaria_id']) ? (int) $datos['cuenta_bancaria_id'] : null;
        $referenciaOperacion = !empty($datos['referencia_operacion']) ? (string) $datos['referencia_operacion'] : null;

        try {
            $pago = $this->cuentaFolioServicio->registrarPago(
                $folioId,
                $metodoPagoId,
                $montoTotal,
                $sesionCajaId,
                $cuentaBancariaId,
                $referenciaOperacion,
                $actorId
            );

            // Si se especificó un cargo para aplicar automáticamente en la misma transacción
            if (!empty($datos['cargo_id_autoaplica'])) {
                $cargoId = (int) $datos['cargo_id_autoaplica'];
                $montoAplica = !empty($datos['monto_autoaplica']) ? (string) $datos['monto_autoaplica'] : $montoTotal;
                $this->cuentaFolioServicio->aplicarPago((int) $pago->obtenerId(), $cargoId, $montoAplica, $actorId);
            }

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Pago [{$pago->obtenerCodigo()}] registrado satisfactoriamente.",
                'datos' => $pago->haciaArreglo(),
            ]);
        } catch (CajaNoAbiertaExcepcion | MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion |
                 SaldoInsuficienteCargoExcepcion | SaldoInsuficientePagoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (CuentaFolioNoEncontradaExcepcion | CargoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al registrar cobro: ' . $e->getMessage()], 500);
        }
    }

    public function aplicarPago(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $pagoId = (int) ($datos['pago_id'] ?? 0);
        $cargoId = (int) ($datos['cargo_id'] ?? 0);
        $montoAplicar = (string) ($datos['monto_aplicar'] ?? ($datos['monto_aplicado'] ?? ($datos['monto'] ?? '0.00')));

        try {
            $aplicacion = $this->cuentaFolioServicio->aplicarPago($pagoId, $cargoId, $montoAplicar, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Aplicación de pago registrada [{$aplicacion->obtenerCodigo()}].",
                'datos' => $aplicacion->haciaArreglo(),
            ]);
        } catch (SaldoInsuficientePagoExcepcion | SaldoInsuficienteCargoExcepcion |
                 MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (PagoNoEncontradoExcepcion | CargoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al aplicar pago: ' . $e->getMessage()], 500);
        }
    }

    public function reversarPago(mixed $id): Respuesta
    {
        $pagoId = (int) (is_array($id) ? ($id['id'] ?? 0) : $id);

        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $motivo = (string) ($datos['motivo'] ?? '');

        try {
            $pago = $this->cuentaFolioServicio->reversarPago($pagoId, $motivo, $actorId);
            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Pago [{$pago->obtenerCodigo()}] reversado y aplicaciones anuladas compensatoriamente.",
                'datos' => $pago->haciaArreglo(),
            ]);
        } catch (MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (PagoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al reversar pago: ' . $e->getMessage()], 500);
        }
    }

    public function registrarDevolucion(): Respuesta
    {
        SesionServicio::iniciarSesionPhp();
        $usuario = $this->sesionServicio->validarSesionActual();
        $actorId = $this->resolverActorId($usuario);

        $datos = $this->obtenerCuerpoPeticion();

        if (!$this->validarCsrf($datos)) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Token CSRF inválido o ausente.'], 403);
        }

        $folioId = (int) ($datos['cuenta_folio_id'] ?? 0);
        $pagoOrigenId = (int) ($datos['pago_id'] ?? ($datos['pago_origen_id'] ?? 0));
        $metodoPagoId = (int) ($datos['metodo_pago_id'] ?? 0);
        $montoDevolucion = (string) ($datos['monto_devolucion'] ?? ($datos['monto'] ?? '0.00'));
        $motivo = (string) ($datos['motivo'] ?? '');
        $sesionCajaId = !empty($datos['sesion_caja_id']) ? (int) $datos['sesion_caja_id'] : null;
        $cuentaBancariaId = !empty($datos['cuenta_bancaria_id']) ? (int) $datos['cuenta_bancaria_id'] : null;

        try {
            $dev = $this->cuentaFolioServicio->registrarDevolucion(
                $folioId,
                $pagoOrigenId,
                $metodoPagoId,
                $montoDevolucion,
                $motivo,
                $sesionCajaId,
                $cuentaBancariaId,
                $actorId
            );

            return Respuesta::json([
                'ok' => true,
                'mensaje' => "Devolución [{$dev->obtenerCodigo()}] registrada y egreso imputado.",
                'datos' => $dev->haciaArreglo(),
            ]);
        } catch (DevolucionExcedidaExcepcion | CajaNoAbiertaExcepcion |
                 MontoInvalidoExcepcion | EstadoFinancieroInvalidoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (PagoNoEncontradoExcepcion $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al registrar devolución: ' . $e->getMessage()], 500);
        }
    }

    public function auxiliares(): Respuesta
    {
        try {
            $cajas = array_map(fn($c) => $c->haciaArreglo(), $this->cajaRepo->listarCajasFisicas());
            $metodos = array_map(fn($m) => $m->haciaArreglo(), $this->cajaRepo->listarMetodosPago());
            $bancos = array_map(fn($b) => $b->haciaArreglo(), $this->cajaRepo->listarCuentasBancarias());

            return Respuesta::json([
                'ok' => true,
                'cajas' => $cajas,
                'metodos' => $metodos,
                'bancos' => $bancos,
            ]);
        } catch (Throwable $e) {
            return Respuesta::json(['ok' => false, 'mensaje' => 'Error al cargar auxiliares financieros: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Métodos Auxiliares
    // =========================================================================

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
                // Fallback continuo
            }
        }

        try {
            $repo = new ActorAuditoriaRepositorio(BaseDatos::conexion());
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
