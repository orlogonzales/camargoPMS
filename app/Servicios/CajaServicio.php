<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ArqueoCajaInvalidoExcepcion;
use CamargoPMS\Excepciones\CajaNoAbiertaExcepcion;
use CamargoPMS\Excepciones\EstadoFinancieroInvalidoExcepcion;
use CamargoPMS\Excepciones\MontoInvalidoExcepcion;
use CamargoPMS\Modelos\CajaFisica;
use CamargoPMS\Modelos\MovimientoCaja;
use CamargoPMS\Modelos\SesionCaja;
use CamargoPMS\Repositorios\ActorAuditoriaRepositorio;
use CamargoPMS\Repositorios\CajaRepositorio;
use PDO;
use Throwable;

/**
 * Servicio de dominio para la gestión operativa y de tesorería de cajas físicas y sesiones de turno.
 */
class CajaServicio
{
    private CajaRepositorio $cajaRepo;
    private ActorAuditoriaRepositorio $actorRepo;

    public function __construct(private PDO $pdo)
    {
        $this->cajaRepo = new CajaRepositorio($pdo);
        $this->actorRepo = new ActorAuditoriaRepositorio($pdo);
    }

    /**
     * Resuelve canónicamente el actor técnico o humano (D-061).
     */
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
     * Inicia un turno de trabajo en una caja física con fondo de cambio.
     */
    public function aperturarSesion(
        int $cajaFisicaId,
        string $montoApertura,
        ?string $observaciones = null,
        ?int $actorId = null
    ): SesionCaja {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($montoApertura) || bccomp($montoApertura, '0.00', 2) < 0) {
            throw new MontoInvalidoExcepcion('monto_apertura', $montoApertura);
        }

        $montoAperturaNorm = bcadd($montoApertura, '0.00', 2);

        $this->pdo->beginTransaction();
        try {
            $caja = $this->cajaRepo->obtenerCajaFisica($cajaFisicaId);
            if ($caja === null || !$caja->estaActiva()) {
                throw new EstadoFinancieroInvalidoExcepcion('CajaFisica', 'INACTIVO', 'APERTURA');
            }

            // Verificar si el cajero ya tiene una sesión abierta en esta caja
            $sesionExistente = $this->cajaRepo->obtenerSesionAbierta($cajaFisicaId, $actorIdFinal, true);
            if ($sesionExistente !== null) {
                throw new EstadoFinancieroInvalidoExcepcion(
                    'SesionCaja',
                    'ABIERTA',
                    "APERTURA (El cajero ya cuenta con la sesión abierta ID {$sesionExistente->obtenerId()})"
                );
            }

            $ahoraUtc = gmdate('Y-m-d H:i:s');
            $nuevaSesion = new SesionCaja(
                null,
                $cajaFisicaId,
                $actorIdFinal,
                null,
                $montoAperturaNorm,
                '0.00',
                '0.00',
                null,
                null,
                null,
                null,
                'ABIERTA',
                $ahoraUtc,
                null,
                $observaciones,
                null
            );

            $id = $this->cajaRepo->crearSesionCaja($nuevaSesion);
            $this->pdo->commit();

            return $this->cajaRepo->obtenerSesionCaja($id);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cierra de forma irreversible un turno de caja calculando el arqueo de efectivo.
     */
    public function cerrarSesion(
        int $sesionId,
        string $montoContadoDeclarado,
        ?string $observacionesCierre = null,
        ?int $actorId = null
    ): SesionCaja {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($montoContadoDeclarado) || bccomp($montoContadoDeclarado, '0.00', 2) < 0) {
            throw new MontoInvalidoExcepcion('monto_contado_declarado', $montoContadoDeclarado);
        }

        $declaradoNorm = bcadd($montoContadoDeclarado, '0.00', 2);

        $this->pdo->beginTransaction();
        try {
            $sesion = $this->cajaRepo->obtenerSesionCaja($sesionId, true);
            if ($sesion === null) {
                throw new EstadoFinancieroInvalidoExcepcion('SesionCaja', 'INEXISTENTE', 'CIERRE');
            }

            if ($sesion->estaCerrada()) {
                throw new EstadoFinancieroInvalidoExcepcion('SesionCaja', 'CERRADA', 'CIERRE (La sesión ya se encuentra cerrada)');
            }

            // Cálculo determinista: esperado = apertura + ingresos - egresos
            $ingresosNetos = bcsub($sesion->obtenerTotalIngresosEfectivo(), $sesion->obtenerTotalEgresosEfectivo(), 2);
            $montoEsperado = bcadd($sesion->obtenerMontoApertura(), $ingresosNetos, 2);

            // Diferencia = declarado - esperado
            $diferencia = bcsub($declaradoNorm, $montoEsperado, 2);
            $comp = bccomp($diferencia, '0.00', 2);

            if ($comp === 0) {
                $resultadoArqueo = 'CUADRADA';
            } elseif ($comp > 0) {
                $resultadoArqueo = 'SOBRANTE';
            } else {
                $resultadoArqueo = 'FALTANTE';
            }

            // Exigencia obligatoria de justificación ante descuadres
            if ($comp !== 0 && ($observacionesCierre === null || trim($observacionesCierre) === '')) {
                throw new ArqueoCajaInvalidoExcepcion(
                    "El arqueo presenta una diferencia de [{$diferencia}]. Es obligatorio ingresar una justificación/observación en el cierre."
                );
            }

            $ahoraUtc = gmdate('Y-m-d H:i:s');
            $this->cajaRepo->cerrarSesionCaja(
                $sesionId,
                $actorIdFinal,
                $montoEsperado,
                $declaradoNorm,
                $diferencia,
                $resultadoArqueo,
                $observacionesCierre,
                $ahoraUtc
            );

            // Si hay sobrante, se registra un movimiento de ajuste en la caja
            if ($comp > 0) {
                $movAjuste = new MovimientoCaja(
                    null,
                    $sesionId,
                    'INGRESO_AJUSTE',
                    null,
                    null,
                    $diferencia,
                    'PEN',
                    'Sobrante de caja en arqueo de cierre: ' . ($observacionesCierre ?? ''),
                    $actorIdFinal
                );
                $this->cajaRepo->crearMovimientoCaja($movAjuste);
            }

            $this->pdo->commit();
            return $this->cajaRepo->obtenerSesionCaja($sesionId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerSesionActiva(int $cajaFisicaId, ?int $actorId = null): ?SesionCaja
    {
        $actorIdFinal = $this->resolverActorId($actorId);
        return $this->cajaRepo->obtenerSesionAbierta($cajaFisicaId, $actorIdFinal);
    }

    public function obtenerSesionActivaPorCaja(int $cajaFisicaId): ?SesionCaja
    {
        return $this->cajaRepo->obtenerSesionAbiertaPorCaja($cajaFisicaId);
    }

    public function obtenerSesion(int $sesionId): ?SesionCaja
    {
        return $this->cajaRepo->obtenerSesionCaja($sesionId);
    }

    /**
     * @return array<MovimientoCaja>
     */
    public function listarMovimientosSesion(int $sesionId): array
    {
        return $this->cajaRepo->listarMovimientosCaja($sesionId);
    }

    /**
     * Registra un movimiento de efectivo manual (ingreso o egreso) en la sesión activa.
     */
    public function registrarMovimientoManual(
        int $sesionId,
        string $tipo,
        string $monto,
        string $concepto,
        ?int $actorId = null
    ): MovimientoCaja {
        $actorIdFinal = $this->resolverActorId($actorId);

        if (!is_numeric($monto) || bccomp($monto, '0.00', 2) <= 0) {
            throw new MontoInvalidoExcepcion('monto', $monto);
        }
        $montoNorm = bcadd($monto, '0.00', 2);

        if (trim($concepto) === '') {
            throw new MontoInvalidoExcepcion('concepto', 'El concepto del movimiento es obligatorio');
        }

        $this->pdo->beginTransaction();
        try {
            $sesion = $this->cajaRepo->obtenerSesionCaja($sesionId, true);
            if ($sesion === null || !$sesion->estaAbierta()) {
                throw new CajaNoAbiertaExcepcion("La sesión de caja ID [{$sesionId}] no se encuentra abierta.");
            }

            $tipoUpper = strtoupper($tipo);
            if (in_array($tipoUpper, ['INGRESO_COBRO', 'INGRESO_AJUSTE', 'EGRESO_DEVOLUCION', 'EGRESO_GASTO_MENOR', 'EGRESO_REMESA'], true)) {
                $tipoNormalizado = $tipoUpper;
            } else {
                $tipoNormalizado = str_starts_with($tipoUpper, 'INGRESO') ? 'INGRESO_AJUSTE' : 'EGRESO_GASTO_MENOR';
            }
            $esIngreso = str_starts_with($tipoNormalizado, 'INGRESO');

            $mov = new MovimientoCaja(
                null,
                $sesionId,
                $tipoNormalizado,
                null,
                null,
                $montoNorm,
                'PEN',
                $concepto,
                $actorIdFinal
            );
            $movId = $this->cajaRepo->crearMovimientoCaja($mov);

            if ($esIngreso) {
                $this->cajaRepo->actualizarTotalesSesionCaja($sesionId, $montoNorm, '0.00');
            } else {
                $this->cajaRepo->actualizarTotalesSesionCaja($sesionId, '0.00', $montoNorm);
            }

            $this->pdo->commit();
            $creado = $this->cajaRepo->obtenerMovimientoCaja($movId);
            if ($creado === null) {
                throw new EstadoFinancieroInvalidoExcepcion('MovimientoCaja', 'INEXISTENTE', 'REGISTRAR_MOVIMIENTO');
            }
            return $creado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}

