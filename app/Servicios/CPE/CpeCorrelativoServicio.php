<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios\CPE;

use CamargoPMS\Excepciones\EstablecimientoNoEncontradoExcepcion;
use CamargoPMS\Excepciones\EstablecimientoSerieIncompatibleExcepcion;
use CamargoPMS\Excepciones\SerieFiscalInactivaExcepcion;
use CamargoPMS\Excepciones\SerieFiscalNoEncontradaExcepcion;
use CamargoPMS\Excepciones\SerieTipoIncompatibleExcepcion;
use CamargoPMS\Excepciones\ValidacionFiscalExcepcion;
use CamargoPMS\Modelos\CPE\CpeComprobante;
use CamargoPMS\Repositorios\CPE\CpeComprobanteRepositorio;
use CamargoPMS\Repositorios\CPE\CpeEstablecimientoRepositorio;
use CamargoPMS\Repositorios\CPE\CpeSerieRepositorio;
use PDO;
use PDOException;
use Throwable;

/**
 * Servicio soberano para la asignación concurrente y determinista de numeración correlativa fiscal SUNAT.
 * Implementa el protocolo anti-colisión estricto y la validación de reglas de Schema Hardening (R.S. 193-2020 y D.L. 919).
 */
class CpeCorrelativoServicio
{
    private CpeSerieRepositorio $serieRepo;
    private CpeComprobanteRepositorio $comprobanteRepo;
    private CpeEstablecimientoRepositorio $establecimientoRepo;

    public function __construct(
        private PDO $pdo,
        ?CpeSerieRepositorio $serieRepo = null,
        ?CpeComprobanteRepositorio $comprobanteRepo = null,
        ?CpeEstablecimientoRepositorio $establecimientoRepo = null
    ) {
        $this->serieRepo = $serieRepo ?? new CpeSerieRepositorio($pdo);
        $this->comprobanteRepo = $comprobanteRepo ?? new CpeComprobanteRepositorio($pdo);
        $this->establecimientoRepo = $establecimientoRepo ?? new CpeEstablecimientoRepositorio($pdo);
    }

    /**
     * Asigna el correlativo fiscal de forma segura y atómica, persistiendo el agregado del CPE.
     * Valida integralmente las reglas fiscales de Crédito (R.S. 193-2020) y Beneficio de Hospedaje (D.L. 919).
     *
     * @throws EstablecimientoNoEncontradoExcepcion Si el establecimiento no existe.
     * @throws SerieFiscalNoEncontradaExcepcion Si la serie no existe.
     * @throws SerieFiscalInactivaExcepcion Si la serie se encuentra inactiva.
     * @throws SerieTipoIncompatibleExcepcion Si el tipo de comprobante no coincide con la serie.
     * @throws EstablecimientoSerieIncompatibleExcepcion Si la serie no pertenece al establecimiento emisor.
     * @throws ValidacionFiscalExcepcion Si los datos de validación básica no son conformes.
     * @throws Throwable En cualquier otro fallo durante la transacción.
     */
    public function emitirBorradorOAsignarCorrelativo(CpeComprobante $comprobante, int $usuarioId): CpeComprobante
    {
        // 1. Ajuste Vinculante C2-02: Determinación de propiedad de transacción (quien abre, cierra)
        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $estabId = $comprobante->obtenerEmisorEstablecimientoId();
            $claveIdempotencia = $comprobante->obtenerClaveIdempotencia();

            if (trim($claveIdempotencia) === '') {
                throw new ValidacionFiscalExcepcion('La clave de idempotencia no puede ser una cadena vacía.');
            }

            // 2. Validación de Invariantes Fiscales de Dominio (Crédito, Cuotas, Hospedaje DL 919, Cross-CPE)
            $this->validarReglasFiscalesHardening($comprobante);

            // 3. Pre-check 1: Idempotencia rápida fuera de contención de bloqueo
            $existentePrevio = $this->comprobanteRepo->buscarPorIdempotencia($estabId, $claveIdempotencia);
            if ($existentePrevio !== null) {
                if ($debeCerrarTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return $existentePrevio;
            }

            // 4. Validación de Establecimiento Emisor
            $estab = $this->establecimientoRepo->obtenerPorId($estabId);
            if ($estab === null) {
                throw new EstablecimientoNoEncontradoExcepcion(
                    "Establecimiento fiscal ID {$estabId} no encontrado."
                );
            }
            if (!$estab->estaActivo()) {
                throw new ValidacionFiscalExcepcion(
                    "El establecimiento fiscal emisor '{$estab->obtenerCodigoEstablecimientoSunat()}' está inactivo."
                );
            }

            // 5. Adquisición del bloqueo pesimista de fila (SELECT ... FOR UPDATE) sobre cpe_series
            $serieId = $comprobante->obtenerSerieId();
            $serie = $this->serieRepo->obtenerPorId($serieId, true);

            if ($serie === null) {
                throw new SerieFiscalNoEncontradaExcepcion(
                    "Serie fiscal ID {$serieId} no encontrada."
                );
            }
            if (!$serie->estaActiva()) {
                throw new SerieFiscalInactivaExcepcion(
                    "La serie fiscal '{$serie->obtenerSerie()}' se encuentra inactiva."
                );
            }
            if ($serie->obtenerTipoComprobante() !== $comprobante->obtenerTipoComprobante()) {
                throw new SerieTipoIncompatibleExcepcion(
                    "Tipo de comprobante '{$comprobante->obtenerTipoComprobante()}' incompatible con la serie '{$serie->obtenerSerie()}' ({$serie->obtenerTipoComprobante()})."
                );
            }

            // 6. Cross-Validation vinculante: Serie debe pertenecer al Establecimiento Emisor
            if ($serie->obtenerEmisorEstablecimientoId() !== $estabId) {
                throw new EstablecimientoSerieIncompatibleExcepcion(
                    "La serie '{$serie->obtenerSerie()}' pertenece al establecimiento ID {$serie->obtenerEmisorEstablecimientoId()}, pero el comprobante declara establecimiento ID {$estabId}."
                );
            }

            // 7. Ajuste Vinculante C2-01: Revalidación autoritativa de idempotencia DENTRO de la sección crítica
            $existenteEnSeccionCritica = $this->comprobanteRepo->buscarPorIdempotencia($estabId, $claveIdempotencia);
            if ($existenteEnSeccionCritica !== null) {
                if ($debeCerrarTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return $existenteEnSeccionCritica;
            }

            // 8. Cálculo atómico del siguiente correlativo e incremento de la serie
            $siguienteCorrelativo = $serie->obtenerUltimoCorrelativo() + 1;
            $this->serieRepo->incrementarCorrelativo($serieId, $siguienteCorrelativo);

            // 9. Asignación del correlativo, folio canónico, estado y auditoría al agregado
            $folioCompleto = $serie->formatearFolio($siguienteCorrelativo);
            $comprobante->asignarCorrelativo($siguienteCorrelativo, $folioCompleto);
            $comprobante->fijarEstadoGeneracion('EMITIDO');
            $comprobante->fijarCreadoPorUsuarioId($usuarioId);

            if ($comprobante->obtenerFechaEmision() === null) {
                $comprobante->fijarFechaEmision(date('Y-m-d H:i:s'));
            }

            // 10. Persistencia atómica del agregado CPE completo (incluyendo cuotas, hospedajes y líneas)
            try {
                $cpeId = $this->comprobanteRepo->guardar($comprobante);
            } catch (PDOException $pe) {
                // Defensa final ante colisión extrema por clave de idempotencia
                if (str_contains($pe->getMessage(), 'uq_cpe_idempotencia') ||
                    ($pe->getCode() === '23000' && str_contains($pe->getMessage(), '1062') && str_contains($pe->getMessage(), 'uq_cpe_idempotencia'))
                ) {
                    if ($debeCerrarTx && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    for ($intento = 0; $intento < 5; $intento++) {
                        $existentePostError = $this->comprobanteRepo->buscarPorIdempotencia($estabId, $claveIdempotencia);
                        if ($existentePostError !== null) {
                            return $existentePostError;
                        }
                        usleep(10000); // 10ms
                    }
                }
                throw $pe;
            }

            // 11. Si este servicio inició la transacción, la consolida
            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            // Retornar el comprobante completamente reconstruido desde el repositorio
            return $this->comprobanteRepo->obtenerPorId($cpeId) ?? $comprobante;
        } catch (Throwable $e) {
            // Solo ejecuta rollback si este servicio abrió la transacción (Ajuste Vinculante C2-02)
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Valida integralmente las reglas e invariantes fiscales de Schema Hardening (Crédito, Cuotas y Hospedaje DL 919).
     *
     * @throws ValidacionFiscalExcepcion Si se viola alguna invariante fiscal.
     */
    private function validarReglasFiscalesHardening(CpeComprobante $comprobante): void
    {
        $formaPago = $comprobante->obtenerFormaPago();
        if (!in_array($formaPago, ['CONTADO', 'CREDITO'], true)) {
            throw new ValidacionFiscalExcepcion("Forma de pago '{$formaPago}' no válida. Valores permitidos: CONTADO, CREDITO.");
        }

        // Reglas para CONTADO
        if ($formaPago === 'CONTADO') {
            if (count($comprobante->obtenerCuotas()) > 0) {
                throw new ValidacionFiscalExcepcion('Un comprobante con forma de pago CONTADO no puede tener cuotas fiscales asociadas.');
            }
            $montoPendiente = $comprobante->obtenerMontoNetoPendiente();
            if ($montoPendiente !== null && bccomp($montoPendiente, '0.00', 2) > 0) {
                throw new ValidacionFiscalExcepcion("Un comprobante con forma de pago CONTADO no puede declarar un monto neto pendiente positivo ({$montoPendiente}).");
            }
        }

        // Reglas para CRÉDITO
        if ($formaPago === 'CREDITO') {
            $cuotas = $comprobante->obtenerCuotas();
            if (empty($cuotas)) {
                throw new ValidacionFiscalExcepcion('Un comprobante con forma de pago CREDITO debe incluir al menos una cuota fiscal.');
            }
            $montoPendiente = $comprobante->obtenerMontoNetoPendiente();
            if ($montoPendiente === null || bccomp($montoPendiente, '0.00', 2) <= 0) {
                throw new ValidacionFiscalExcepcion('Un comprobante con forma de pago CREDITO debe declarar un monto neto pendiente estrictamente positivo.');
            }

            $sumaCuotas = '0.00';
            $numerosVistos = [];
            foreach ($cuotas as $cuota) {
                $num = $cuota->obtenerNumeroCuota();
                if (isset($numerosVistos[$num])) {
                    throw new ValidacionFiscalExcepcion("Número de cuota {$num} duplicado en el comprobante.");
                }
                $numerosVistos[$num] = true;
                $sumaCuotas = bcadd($sumaCuotas, $cuota->obtenerMonto(), 2);
            }

            if (bccomp($sumaCuotas, $montoPendiente, 2) !== 0) {
                throw new ValidacionFiscalExcepcion(
                    "La suma de las cuotas ({$sumaCuotas}) no coincide con el monto neto pendiente ({$montoPendiente})."
                );
            }
        }

        // Reglas para Régimen de Hospedaje No Domiciliado (DL 919 / Catálogo 55)
        if ($comprobante->esExportacionHospedaje()) {
            $hospedajes = $comprobante->obtenerHospedajes();
            if (empty($hospedajes)) {
                throw new ValidacionFiscalExcepcion('Un comprobante con beneficio de hospedaje DL 919 debe contener al menos un registro de huésped fiscal.');
            }

            $hospedajesPorOrden = [];
            foreach ($hospedajes as $h) {
                $orden = $h->obtenerNumeroOrden();
                if (isset($hospedajesPorOrden[$orden])) {
                    throw new ValidacionFiscalExcepcion("Número de orden {$orden} duplicado en huéspedes fiscales del comprobante.");
                }
                $hospedajesPorOrden[$orden] = $h;
            }

            // Validar que las fechas de consumo de líneas conexas estén dentro del rango de estancia
            foreach ($comprobante->obtenerLineas() as $linea) {
                $fechaConsumo = $linea->obtenerFechaConsumo();
                $hospedajeId = $linea->obtenerCpeHospedajeId();

                if ($fechaConsumo !== null) {
                    $hospedajeAsociado = null;
                    if ($hospedajeId !== null) {
                        foreach ($hospedajes as $h) {
                            if ($h->obtenerId() === $hospedajeId || ($h->obtenerId() === null && $h->obtenerNumeroOrden() === $hospedajeId)) {
                                $hospedajeAsociado = $h;
                                break;
                            }
                        }
                    }
                    if ($hospedajeAsociado === null && count($hospedajes) === 1) {
                        $hospedajeAsociado = reset($hospedajes);
                    }

                    if ($hospedajeAsociado !== null) {
                        $checkin = $hospedajeAsociado->obtenerFechaCheckin();
                        $checkout = $hospedajeAsociado->obtenerFechaCheckout();

                        if ($fechaConsumo < $checkin) {
                            throw new ValidacionFiscalExcepcion(
                                "La fecha de consumo '{$fechaConsumo}' no puede ser anterior al check-in '{$checkin}' del huésped."
                            );
                        }
                        if ($fechaConsumo > $checkout) {
                            throw new ValidacionFiscalExcepcion(
                                "La fecha de consumo '{$fechaConsumo}' no puede ser posterior al check-out '{$checkout}' del huésped."
                            );
                        }
                    }
                }
            }
        }

        // Defensa contra cross-CPE a nivel de servicio
        foreach ($comprobante->obtenerLineas() as $linea) {
            $hospId = $linea->obtenerCpeHospedajeId();
            if ($hospId !== null) {
                $esOrdenInterno = false;
                foreach ($comprobante->obtenerHospedajes() as $h) {
                    if ($h->obtenerId() === null && $h->obtenerNumeroOrden() === $hospId) {
                        $esOrdenInterno = true;
                        break;
                    }
                }
                if ($esOrdenInterno) {
                    continue;
                }
                $stmtCheck = $this->pdo->prepare('SELECT cpe_id FROM cpe_hospedajes WHERE id = :id');
                $stmtCheck->execute(['id' => $hospId]);
                $rowHosp = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                if ($rowHosp && (int) $rowHosp['cpe_id'] !== $comprobante->obtenerId()) {
                    throw new ValidacionFiscalExcepcion(
                        "Violación de integridad cross-CPE: la línea hace referencia al hospedaje ID {$hospId} perteneciente a otro comprobante (CPE ID {$rowHosp['cpe_id']})."
                    );
                }
            }
        }
    }

    /**
     * Consulta el siguiente correlativo tentativo de una serie sin bloquearla ni incrementar la secuencia.
     */
    public function obtenerSiguienteCorrelativoTentativo(int $serieId): int
    {
        $serie = $this->serieRepo->obtenerPorId($serieId, false);
        if ($serie === null) {
            throw new SerieFiscalNoEncontradaExcepcion("Serie fiscal ID {$serieId} no encontrada.");
        }

        return $serie->obtenerSiguienteCorrelativo();
    }

    /**
     * Busca un comprobante emitido a partir de su número fiscal completo.
     */
    public function buscarComprobantePorFolio(
        int $establecimientoId,
        string $tipoComprobante,
        string $serie,
        int $correlativo
    ): ?CpeComprobante {
        return $this->comprobanteRepo->obtenerPorNumeroFiscal($establecimientoId, $tipoComprobante, $serie, $correlativo);
    }

    /**
     * Busca un comprobante emitido a partir de su clave de idempotencia.
     */
    public function buscarComprobantePorIdempotencia(int $establecimientoId, string $claveIdempotencia): ?CpeComprobante
    {
        return $this->comprobanteRepo->buscarPorIdempotencia($establecimientoId, $claveIdempotencia);
    }

    /**
     * Alias semántico para emitir un comprobante o asignar su correlativo fiscal.
     */
    public function asignarCorrelativoYEmitir(
        CpeComprobante $comprobante,
        int $usuarioId = 1,
        ?string $claveIdempotencia = null
    ): CpeComprobante {
        return $this->emitirBorradorOAsignarCorrelativo($comprobante, $usuarioId, $claveIdempotencia);
    }
}
