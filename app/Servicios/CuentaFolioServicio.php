<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\CajaNoAbiertaExcepcion;
use CamargoPMS\Excepciones\CargoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\CuentaFolioNoEncontradaExcepcion;
use CamargoPMS\Excepciones\DevolucionExcedidaExcepcion;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Excepciones\PagoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\SaldoInsuficienteCargoExcepcion;
use CamargoPMS\Excepciones\SaldoInsuficientePagoExcepcion;
use CamargoPMS\Modelos\AplicacionPago;
use CamargoPMS\Modelos\CargoCuenta;
use CamargoPMS\Modelos\CuentaFolio;
use CamargoPMS\Modelos\DevolucionCuenta;
use CamargoPMS\Modelos\MovimientoBancario;
use CamargoPMS\Modelos\MovimientoCaja;
use CamargoPMS\Modelos\PagoCuenta;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\CajaRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\DevolucionCuentaRepositorio;
use CamargoPMS\Repositorios\ReservaRepositorio;
use CamargoPMS\Repositorios\ServicioContratadoRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión del libro de folios, cargos, pagos, imputaciones y saldos.
 */
class CuentaFolioServicio
{
    private CuentaFolioRepositorio $folioRepo;
    private CargoCuentaRepositorio $cargoRepo;
    private PagoCuentaRepositorio $pagoRepo;
    private AplicacionPagoRepositorio $aplicacionRepo;
    private DevolucionCuentaRepositorio $devolucionRepo;
    private CajaRepositorio $cajaRepo;
    private ReservaRepositorio $reservaRepo;
    private ServicioContratadoRepositorio $servicioContratadoRepo;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(private PDO $pdo)
    {
        $this->folioRepo = new CuentaFolioRepositorio($pdo);
        $this->cargoRepo = new CargoCuentaRepositorio($pdo);
        $this->pagoRepo = new PagoCuentaRepositorio($pdo);
        $this->aplicacionRepo = new AplicacionPagoRepositorio($pdo);
        $this->devolucionRepo = new DevolucionCuentaRepositorio($pdo);
        $this->cajaRepo = new CajaRepositorio($pdo);
        $this->reservaRepo = new ReservaRepositorio($pdo);
        $this->servicioContratadoRepo = new ServicioContratadoRepositorio($pdo);
        $this->actorRepo = new ActorAuditoriaRepositorio($pdo);
    }

    private function resolverActorId(?int $actorOUsuarioId): int
    {
        if ($actorOUsuarioId !== null && $actorOUsuarioId > 0) {
            $actor = $this->actorRepo->buscarPorId($actorOUsuarioId);
            if ($actor !== null) {
                return (int) $actor->obtenerId();
            }
            $actorHumano = $this->actorRepo->buscarPorUsuarioId($actorOUsuarioId);
            if ($actorHumano !== null) {
                return (int) $actorHumano->obtenerId();
            }
        }

        $sistema = $this->actorRepo->buscarPorCodigo('CAMARGO_PMS');
        if ($sistema !== null) {
            return (int) $sistema->obtenerId();
        }

        return 1;
    }

    /**
     * Asegura o crea el folio financiero raíz 1:1 para una reserva confirmada.
     */
    public function crearOAsegurarFolioReserva(int $reservaId, ?int $actorId = null): CuentaFolio
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        $existente = $this->folioRepo->obtenerPorReservaId($reservaId);
        if ($existente !== null) {
            return $existente;
        }

        $reserva = $this->reservaRepo->buscarPorId($reservaId);
        if ($reserva === null) {
            throw new CuentaFolioNoEncontradaExcepcion("Reserva ID {$reservaId} inexistente");
        }

        $codigo = $this->folioRepo->generarSiguienteCodigo();
        $nuevoFolio = new CuentaFolio(
            null,
            $codigo,
            $reservaId,
            $reserva->obtenerPersonaTitularId(),
            'PEN',
            'ABIERTA',
            $actorIdFinal
        );

        $id = $this->folioRepo->crear($nuevoFolio);
        return $this->folioRepo->obtenerPorId($id);
    }

    public function obtenerFolioReserva(int $reservaId): ?CuentaFolio
    {
        return $this->folioRepo->obtenerPorReservaId($reservaId);
    }

    public function obtenerFolioPrincipalReserva(int $reservaId): ?CuentaFolio
    {
        return $this->folioRepo->obtenerPrincipalPorReservaId($reservaId);
    }

    /**
     * @return array<CuentaFolio>
     */
    public function obtenerFoliosReserva(int $reservaId): array
    {
        return $this->folioRepo->listarPorReservaId($reservaId);
    }

    public function crearFolioSecundario(
        int $reservaId,
        int $personaTitularId,
        string $etiqueta = 'FOLIO SECUNDARIO',
        ?int $actorId = null
    ): CuentaFolio {
        $actorIdFinal = $this->resolverActorId($actorId);

        $folioPrincipal = $this->folioRepo->obtenerPrincipalPorReservaId($reservaId);
        if ($folioPrincipal === null) {
            throw new CuentaFolioNoEncontradaExcepcion("No existe un folio principal activo para la reserva ID {$reservaId}");
        }

        if ($folioPrincipal->estaCerrada() || $folioPrincipal->estaAnulada()) {
            throw new EstadoFinancieroInvalidoExcepcion("No se puede aperturar un folio secundario si el folio principal está {$folioPrincipal->obtenerEstado()}");
        }

        $codigo = $this->folioRepo->generarSiguienteCodigo();
        $nuevoFolio = new CuentaFolio(
            null,
            $codigo,
            $reservaId,
            $personaTitularId,
            $folioPrincipal->obtenerMonedaCodigo(),
            'ABIERTA',
            $actorIdFinal,
            null,
            null,
            null,
            false,
            trim($etiqueta) !== '' ? trim($etiqueta) : 'FOLIO SECUNDARIO',
            $folioPrincipal->obtenerId()
        );

        $id = $this->folioRepo->crear($nuevoFolio);
        $creado = $this->folioRepo->obtenerPorId($id);
        if ($creado === null) {
            throw new CuentaFolioNoEncontradaExcepcion("Error al recuperar el folio secundario recién creado ID {$id}");
        }

        return $creado;
    }

    public function obtenerFolioPorId(int $folioId): ?CuentaFolio
    {
        return $this->folioRepo->obtenerPorId($folioId);
    }

    /**
     * Genera los cargos devengados correspondientes a las noches de hospedaje de la reserva.
     *
     * @return array<CargoCuenta>
     */
    public function generarCargosAlojamiento(int $reservaId, ?int $actorId = null): array
    {
        $actorIdFinal = $this->resolverActorId($actorId);
        $folio = $this->crearOAsegurarFolioReserva($reservaId, $actorIdFinal);
        $unidades = $this->reservaRepo->obtenerUnidadesPorReservaId($reservaId);

        $cargosCreados = [];
        $ahoraUtc = gmdate('Y-m-d H:i:s');

        foreach ($unidades as $u) {
            $uId = (int) $u->obtenerId();
            $cargoExistente = $this->cargoRepo->obtenerPorOrigen('ALOJAMIENTO_NOCHES', $uId);
            if ($cargoExistente !== null) {
                $cargosCreados[] = $cargoExistente;
                continue;
            }

            $codigo = $this->cargoRepo->generarSiguienteCodigo();
            $totalStr = bcadd($u->obtenerTotal(), '0.00', 2);
            $subtotalStr = bcadd($u->obtenerSubtotal(), '0.00', 2);
            $impuestoStr = bcadd($u->obtenerImpuesto(), '0.00', 2);

            $unidadLabel = $u->obtenerUnidadCodigo() ?? (string) $u->obtenerUnidadId();

            $cargo = new CargoCuenta(
                null,
                $codigo,
                (int) $folio->obtenerId(),
                'ALOJAMIENTO_NOCHES',
                $uId,
                null,
                "Alojamiento Unidad {$unidadLabel} ({$u->obtenerNoches()} noches)",
                (string) $u->obtenerNoches(),
                $u->obtenerPrecioUnitarioNoche(),
                $subtotalStr,
                $impuestoStr,
                $totalStr,
                '0.00',
                'PEN',
                'DEVENGADO',
                null,
                null,
                null,
                $ahoraUtc,
                $actorIdFinal
            );

            $id = $this->cargoRepo->crear($cargo);
            $cargosCreados[] = $this->cargoRepo->obtenerPorId($id);
        }

        return $cargosCreados;
    }

    /**
     * Sincroniza transaccionalmente el cargo en cuenta derivado de un servicio contratado.
     * CONFIRMADO -> PROVISIONAL
     * EJECUTADO  -> DEVENGADO
     * CANCELADO  -> ANULADO
     */
    public function sincronizarCargoServicioContratado(
        int $servicioContratadoId,
        string $estadoServicio,
        ?int $actorId = null
    ): ?CargoCuenta {
        $actorIdFinal = $this->resolverActorId($actorId);

        $sc = $this->servicioContratadoRepo->buscarPorId($servicioContratadoId);
        if ($sc === null) {
            return null;
        }

        $folio = $this->crearOAsegurarFolioReserva($sc->obtenerReservaId(), $actorIdFinal);
        $cargo = $this->cargoRepo->obtenerPorOrigen('SERVICIO_CONTRATADO', $servicioContratadoId, true);
        $ahoraUtc = gmdate('Y-m-d H:i:s');

        if ($estadoServicio === 'SOLICITADO') {
            return $cargo;
        }

        if ($cargo === null) {
            // Se crea el cargo
            $codigo = $this->cargoRepo->generarSiguienteCodigo();
            $estadoInicial = ($estadoServicio === 'EJECUTADO') ? 'DEVENGADO' : 'PROVISIONAL';
            $devengadoEn = ($estadoServicio === 'EJECUTADO') ? $ahoraUtc : null;

            $nuevoCargo = new CargoCuenta(
                null,
                $codigo,
                (int) $folio->obtenerId(),
                'SERVICIO_CONTRATADO',
                $servicioContratadoId,
                $sc->obtenerEstadiaId(),
                $sc->obtenerDescripcionServicioSnapshot(),
                $sc->obtenerCantidad(),
                $sc->obtenerPrecioUnitario(),
                $sc->obtenerSubtotal(),
                $sc->obtenerImpuestoTotal(),
                $sc->obtenerTotal(),
                '0.00',
                'PEN',
                $estadoInicial,
                null,
                null,
                null,
                $devengadoEn,
                $actorIdFinal
            );

            $id = $this->cargoRepo->crear($nuevoCargo);
            return $this->cargoRepo->obtenerPorId($id);
        }

        // Si ya existía, sincronizar según estado
        if ($estadoServicio === 'EJECUTADO') {
            if ($cargo->obtenerEstado() !== 'DEVENGADO') {
                $this->cargoRepo->actualizarEstado((int) $cargo->obtenerId(), 'DEVENGADO', $ahoraUtc);
            }
        } elseif ($estadoServicio === 'CANCELADO') {
            if ($cargo->obtenerEstado() === 'PROVISIONAL') {
                $this->cargoRepo->actualizarEstado(
                    (int) $cargo->obtenerId(),
                    'ANULADO',
                    null,
                    'Servicio cancelado antes de ejecución',
                    $actorIdFinal,
                    $ahoraUtc
                );
            }
        }

        return $this->cargoRepo->obtenerPorId((int) $cargo->obtenerId());
    }

    /**
     * Registra un pago reconocido en el folio financiero.
     */
    public function registrarPago(
        int $folioId,
        int $metodoPagoId,
        string $montoTotal,
        ?int $sesionCajaId = null,
        ?int $cuentaBancariaId = null,
        ?string $referenciaOperacion = null,
        ?int $actorId = null
    ): PagoCuenta {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($montoTotal) || bccomp($montoTotal, '0.00', 2) <= 0) {
            throw new MontoInvalidoExcepcion('monto_total', $montoTotal);
        }
        $montoNorm = bcadd($montoTotal, '0.00', 2);

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }
        try {
            $folio = $this->folioRepo->obtenerPorId($folioId, true);
            if ($folio === null) {
                throw new CuentaFolioNoEncontradaExcepcion((string) $folioId);
            }

            if ($folio->estaCerrada() || $folio->estaAnulada()) {
                throw new EstadoFinancieroInvalidoExcepcion('CuentaFolio', $folio->obtenerEstado(), 'REGISTRAR_PAGO');
            }

            $metodo = $this->cajaRepo->obtenerMetodoPago($metodoPagoId);
            if ($metodo === null || !$metodo->estaActivo()) {
                throw new EstadoFinancieroInvalidoExcepcion('MetodoPago', 'INACTIVO', 'REGISTRAR_PAGO');
            }

            // Validar tesorería según método
            if ($metodo->esCajaFisica()) {
                if ($sesionCajaId === null) {
                    throw new CajaNoAbiertaExcepcion('El cobro en efectivo exige indicar la sesión de caja abierta.');
                }
                $sesion = $this->cajaRepo->obtenerSesionCaja($sesionCajaId, true);
                if ($sesion === null || !$sesion->estaAbierta()) {
                    throw new CajaNoAbiertaExcepcion("La sesión de caja ID [{$sesionCajaId}] no se encuentra abierta.");
                }
            } elseif ($metodo->esCuentaBancaria()) {
                if ($cuentaBancariaId === null) {
                    throw new MontoInvalidoExcepcion('cuenta_bancaria_id', 'Obligatoria para transferencias bancarias');
                }
            }

            $codigo = $this->pagoRepo->generarSiguienteCodigo();
            $nuevoPago = new PagoCuenta(
                null,
                $codigo,
                $folioId,
                $metodoPagoId,
                $montoNorm,
                '0.00',
                'PEN',
                $sesionCajaId,
                $cuentaBancariaId,
                $referenciaOperacion,
                'CONFIRMADO',
                null,
                null,
                null,
                $actorIdFinal
            );

            $pagoId = $this->pagoRepo->crear($nuevoPago);

            // Generar asiento en libro mayor correspondiente
            if ($metodo->esCajaFisica() && $sesionCajaId !== null) {
                $movCaja = new MovimientoCaja(
                    null,
                    $sesionCajaId,
                    'INGRESO_COBRO',
                    $pagoId,
                    null,
                    $montoNorm,
                    'PEN',
                    "Cobro a cuenta {$folio->obtenerCodigo()} ({$metodo->obtenerNombre()})",
                    $actorIdFinal
                );
                $this->cajaRepo->crearMovimientoCaja($movCaja);
                $this->cajaRepo->actualizarTotalesSesionCaja($sesionCajaId, $montoNorm, '0.00');
            } elseif ($metodo->esCuentaBancaria() && $cuentaBancariaId !== null) {
                $movBanco = new MovimientoBancario(
                    null,
                    $cuentaBancariaId,
                    'INGRESO_TRANSFERENCIA',
                    $pagoId,
                    null,
                    $montoNorm,
                    'PEN',
                    $referenciaOperacion ?? 'SIN_REF',
                    "Abono en cuenta bancaria para folio {$folio->obtenerCodigo()}",
                    date('Y-m-d'),
                    $actorIdFinal
                );
                $this->cajaRepo->crearMovimientoBancario($movBanco);
            }

            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $this->pagoRepo->obtenerPorId($pagoId);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Aplica transaccionalmente un monto de un pago contra un cargo devengado específico.
     */
    public function aplicarPago(
        int $pagoId,
        int $cargoId,
        string $montoAplicar,
        ?int $actorId = null
    ): AplicacionPago {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($montoAplicar) || bccomp($montoAplicar, '0.00', 2) <= 0) {
            throw new MontoInvalidoExcepcion('monto_aplicar', $montoAplicar);
        }
        $montoNorm = bcadd($montoAplicar, '0.00', 2);

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }
        try {
            // Bloqueo pesimista ordenado de pago y cargo para prevenir deadlocks en concurrencia
            $pago = $this->pagoRepo->obtenerPorId($pagoId, true);
            if ($pago === null) {
                throw new PagoNoEncontradoExcepcion((string) $pagoId);
            }
            if (!$pago->estaConfirmado()) {
                throw new EstadoFinancieroInvalidoExcepcion('PagoCuenta', $pago->obtenerEstado(), 'APLICAR_PAGO');
            }

            $cargo = $this->cargoRepo->obtenerPorId($cargoId, true);
            if ($cargo === null) {
                throw new CargoNoEncontradoExcepcion((string) $cargoId);
            }
            if (!$cargo->esDevengado()) {
                throw new EstadoFinancieroInvalidoExcepcion('CargoCuenta', $cargo->obtenerEstado(), 'APLICAR_PAGO (Solo cargos DEVENGADOS)');
            }

            if ($pago->obtenerCuentaFolioId() !== $cargo->obtenerCuentaFolioId()) {
                throw new EstadoFinancieroInvalidoExcepcion('AplicacionPago', 'CUENTAS_DISTINTAS', 'APLICAR_PAGO');
            }

            // Validar saldo no aplicado disponible del pago
            $saldoDisponiblePago = $pago->calcularSaldoDisponible();
            if (bccomp($montoNorm, $saldoDisponiblePago, 2) > 0) {
                throw new SaldoInsuficientePagoExcepcion($montoNorm, $saldoDisponiblePago);
            }

            // Validar saldo pendiente del cargo
            $saldoPendienteCargo = $cargo->calcularSaldoPendiente();
            if (bccomp($montoNorm, $saldoPendienteCargo, 2) > 0) {
                throw new SaldoInsuficienteCargoExcepcion($montoNorm, $saldoPendienteCargo);
            }

            $codigo = $this->aplicacionRepo->generarSiguienteCodigo();
            $nuevaAplicacion = new AplicacionPago(
                null,
                $codigo,
                $pagoId,
                $cargoId,
                $montoNorm,
                'PEN',
                'ACTIVA',
                null,
                null,
                $actorIdFinal
            );

            $id = $this->aplicacionRepo->crear($nuevaAplicacion);

            // Actualizar atómicamente acumulados
            $this->pagoRepo->actualizarMontoAplicado($pagoId, $montoNorm);
            $this->cargoRepo->actualizarMontoAplicado($cargoId, $montoNorm);

            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $this->aplicacionRepo->obtenerPorId($id);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Revierte compensatoriamente un pago, des-aplicando sus cargos asociados sin borrado físico.
     */
    public function reversarPago(int $pagoId, string $motivo, ?int $actorId = null): PagoCuenta
    {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (trim($motivo) === '') {
            throw new MontoInvalidoExcepcion('motivo_reverso', 'El motivo de reverso es obligatorio');
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }
        try {
            $pago = $this->pagoRepo->obtenerPorId($pagoId, true);
            if ($pago === null) {
                throw new PagoNoEncontradoExcepcion((string) $pagoId);
            }
            if ($pago->estaReversado()) {
                throw new EstadoFinancieroInvalidoExcepcion('PagoCuenta', 'REVERSADO', 'REVERSAR_PAGO');
            }

            $ahoraUtc = gmdate('Y-m-d H:i:s');

            // Revertir todas las aplicaciones activas y restaurar la deuda en los cargos
            $aplicaciones = $this->aplicacionRepo->revertirPorPago($pagoId, $actorIdFinal, $ahoraUtc);
            foreach ($aplicaciones as $ap) {
                $deltaNegativo = '-' . $ap['monto_aplicado'];
                $this->cargoRepo->actualizarMontoAplicado((int) $ap['cargo_id'], $deltaNegativo);
            }

            // Marcar pago como REVERSADO
            $this->pagoRepo->reversar($pagoId, $motivo, $actorIdFinal, $ahoraUtc);

            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $this->pagoRepo->obtenerPorId($pagoId);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra una devolución real de fondos al huésped.
     */
    public function registrarDevolucion(
        int $folioId,
        int $pagoOrigenId,
        int $metodoPagoId,
        string $montoDevolucion,
        string $motivo,
        ?int $sesionCajaId = null,
        ?int $cuentaBancariaId = null,
        ?int $actorId = null
    ): DevolucionCuenta {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($montoDevolucion) || bccomp($montoDevolucion, '0.00', 2) <= 0) {
            throw new MontoInvalidoExcepcion('monto_devolucion', $montoDevolucion);
        }
        $montoNorm = bcadd($montoDevolucion, '0.00', 2);

        if (trim($motivo) === '') {
            throw new MontoInvalidoExcepcion('motivo', 'El motivo de devolución es obligatorio');
        }

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }
        try {
            $pago = $this->pagoRepo->obtenerPorId($pagoOrigenId, true);
            if ($pago === null) {
                throw new PagoNoEncontradoExcepcion((string) $pagoOrigenId);
            }
            if (!$pago->estaConfirmado()) {
                throw new EstadoFinancieroInvalidoExcepcion('PagoCuenta', $pago->obtenerEstado(), 'REGISTRAR_DEVOLUCION');
            }

            // Validar que la suma de devoluciones no exceda el monto total del pago
            $devolucionesPrevias = $this->devolucionRepo->sumarDevolucionesConfirmadasPorPago($pagoOrigenId);
            $totalDevueltoFuturo = bcadd($devolucionesPrevias, $montoNorm, 2);

            if (bccomp($totalDevueltoFuturo, $pago->obtenerMontoTotal(), 2) > 0) {
                $devolvibleRemanente = bcsub($pago->obtenerMontoTotal(), $devolucionesPrevias, 2);
                throw new DevolucionExcedidaExcepcion($montoNorm, $devolvibleRemanente);
            }

            $metodo = $this->cajaRepo->obtenerMetodoPago($metodoPagoId);
            if ($metodo === null || !$metodo->estaActivo()) {
                throw new EstadoFinancieroInvalidoExcepcion('MetodoPago', 'INACTIVO', 'REGISTRAR_DEVOLUCION');
            }

            // Validar tesorería
            if ($metodo->esCajaFisica()) {
                if ($sesionCajaId === null) {
                    throw new CajaNoAbiertaExcepcion('La devolución en efectivo exige indicar la sesión de caja abierta.');
                }
                $sesion = $this->cajaRepo->obtenerSesionCaja($sesionCajaId, true);
                if ($sesion === null || !$sesion->estaAbierta()) {
                    throw new CajaNoAbiertaExcepcion("La sesión de caja ID [{$sesionCajaId}] no se encuentra abierta.");
                }
            }

            $codigo = $this->devolucionRepo->generarSiguienteCodigo();
            $nuevaDevolucion = new DevolucionCuenta(
                null,
                $codigo,
                $folioId,
                $pagoOrigenId,
                $metodoPagoId,
                $sesionCajaId,
                $cuentaBancariaId,
                $montoNorm,
                'PEN',
                $motivo,
                'CONFIRMADA',
                $actorIdFinal
            );

            $devId = $this->devolucionRepo->crear($nuevaDevolucion);

            // Generar asiento de egreso en el libro mayor correspondiente
            if ($metodo->esCajaFisica() && $sesionCajaId !== null) {
                $movCaja = new MovimientoCaja(
                    null,
                    $sesionCajaId,
                    'EGRESO_DEVOLUCION',
                    null,
                    $devId,
                    $montoNorm,
                    'PEN',
                    "Reembolso a huésped por pago {$pago->obtenerCodigo()}: {$motivo}",
                    $actorIdFinal
                );
                $this->cajaRepo->crearMovimientoCaja($movCaja);
                $this->cajaRepo->actualizarTotalesSesionCaja($sesionCajaId, '0.00', $montoNorm);
            } elseif ($metodo->esCuentaBancaria() && $cuentaBancariaId !== null) {
                $movBanco = new MovimientoBancario(
                    null,
                    $cuentaBancariaId,
                    'EGRESO_DEVOLUCION',
                    null,
                    $devId,
                    $montoNorm,
                    'PEN',
                    'DEV-' . $devId,
                    "Reembolso bancario a huésped: {$motivo}",
                    date('Y-m-d'),
                    $actorIdFinal
                );
                $this->cajaRepo->crearMovimientoBancario($movBanco);
            }

            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $this->devolucionRepo->obtenerPorId($devId);
        } catch (Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Computa de forma determinista el balance y estado contable del folio.
     *
     * @return array<string, mixed>
     */
    public function obtenerEstadoCuenta(int $folioId): array
    {
        $folio = $this->folioRepo->obtenerPorId($folioId);
        if ($folio === null) {
            throw new CuentaFolioNoEncontradaExcepcion((string) $folioId);
        }

        $cargos = $this->cargoRepo->listarPorFolio($folioId);
        $pagos = $this->pagoRepo->listarPorFolio($folioId, 'CONFIRMADO');
        $devoluciones = $this->devolucionRepo->listarPorFolio($folioId);

        $totalCargosDevengados = '0.00';
        $totalCargosProvisionales = '0.00';
        $totalCargosAplicados = '0.00';

        foreach ($cargos as $c) {
            if ($c->esDevengado()) {
                $totalCargosDevengados = bcadd($totalCargosDevengados, $c->obtenerTotal(), 2);
                $totalCargosAplicados = bcadd($totalCargosAplicados, $c->obtenerMontoAplicadoAcumulado(), 2);
            } elseif ($c->esProvisional()) {
                $totalCargosProvisionales = bcadd($totalCargosProvisionales, $c->obtenerTotal(), 2);
            }
        }

        $totalPagosConfirmados = '0.00';
        $totalPagosAplicados = '0.00';
        $totalPagosDisponibles = '0.00';

        foreach ($pagos as $p) {
            $totalPagosConfirmados = bcadd($totalPagosConfirmados, $p->obtenerMontoTotal(), 2);
            $totalPagosAplicados = bcadd($totalPagosAplicados, $p->obtenerMontoAplicado(), 2);
            $totalPagosDisponibles = bcadd($totalPagosDisponibles, $p->calcularSaldoDisponible(), 2);
        }

        $totalDevolucionesConfirmadas = '0.00';
        foreach ($devoluciones as $d) {
            if ($d->estaConfirmada()) {
                $totalDevolucionesConfirmadas = bcadd($totalDevolucionesConfirmadas, $d->obtenerMonto(), 2);
            }
        }

        // Saldo neto exigible = devengado - aplicado
        $saldoNetoExigible = bcsub($totalCargosDevengados, $totalCargosAplicados, 2);

        // Estado financiero
        $compExigible = bccomp($saldoNetoExigible, '0.00', 2);
        $compDisponible = bccomp($totalPagosDisponibles, '0.00', 2);

        if ($compExigible > 0) {
            $estadoFinanciero = 'PENDIENTE_PAGO';
        } elseif ($compDisponible > 0) {
            $estadoFinanciero = 'SALDO_A_FAVOR';
        } else {
            $estadoFinanciero = 'SALDADA';
        }

        return [
            'folio' => $folio->haciaArreglo(),
            'total_cargos_devengados' => $totalCargosDevengados,
            'total_cargos_provisionales' => $totalCargosProvisionales,
            'total_pagos_confirmados' => $totalPagosConfirmados,
            'total_pagos_aplicados' => $totalPagosAplicados,
            'total_pagos_disponibles' => $totalPagosDisponibles,
            'total_devoluciones_confirmadas' => $totalDevolucionesConfirmadas,
            'saldo_neto_exigible' => $saldoNetoExigible,
            'estado_financiero' => $estadoFinanciero,
            'cargos' => array_map(fn($c) => $c->haciaArreglo(), $cargos),
            'pagos' => array_map(fn($p) => $p->haciaArreglo(), $pagos),
            'devoluciones' => array_map(fn($d) => $d->haciaArreglo(), $devoluciones),
        ];
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<array<string, mixed>>
     */
    public function listarFolios(array $filtros = []): array
    {
        return $this->folioRepo->listar($filtros);
    }
}

