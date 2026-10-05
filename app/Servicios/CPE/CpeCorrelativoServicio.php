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
 * Implementa el protocolo anti-colisión estricto (Ajustes Vinculantes C2-01 y C2-02):
 * - Revalidación de idempotencia en sección crítica bajo bloqueo pesimista de serie.
 * - Respeto irrestricto de la propiedad de transacciones externas ($debeCerrarTx).
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
     * Asigna correlativos crecientes concurrency-safe e idempotentes en ambientes de alta concurrencia (continuidad absoluta sin huecos no garantizada).
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

            // 2. Pre-check 1: Idempotencia rápida fuera de contención de bloqueo
            $existentePrevio = $this->comprobanteRepo->buscarPorIdempotencia($estabId, $claveIdempotencia);
            if ($existentePrevio !== null) {
                if ($debeCerrarTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return $existentePrevio;
            }

            // 3. Validación de Establecimiento Emisor
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

            // 4. Adquisición del bloqueo pesimista de fila (SELECT ... FOR UPDATE) sobre cpe_series
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

            // 5. Cross-Validation vinculante: Serie debe pertenecer al Establecimiento Emisor
            if ($serie->obtenerEmisorEstablecimientoId() !== $estabId) {
                throw new EstablecimientoSerieIncompatibleExcepcion(
                    "La serie '{$serie->obtenerSerie()}' pertenece al establecimiento ID {$serie->obtenerEmisorEstablecimientoId()}, pero el comprobante declara establecimiento ID {$estabId}."
                );
            }

            // 6. Ajuste Vinculante C2-01: Revalidación autoritativa de idempotencia DENTRO de la sección crítica
            // Si otra petición concurrente avanzó y emitió con esta misma clave mientras esperábamos el lock,
            // la encontramos aquí sin incrementar el correlativo ni generar huecos en la serie.
            $existenteEnSeccionCritica = $this->comprobanteRepo->buscarPorIdempotencia($estabId, $claveIdempotencia);
            if ($existenteEnSeccionCritica !== null) {
                if ($debeCerrarTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return $existenteEnSeccionCritica;
            }

            // 7. Cálculo atómico del siguiente correlativo e incremento de la serie
            $siguienteCorrelativo = $serie->obtenerUltimoCorrelativo() + 1;
            $this->serieRepo->incrementarCorrelativo($serieId, $siguienteCorrelativo);

            // 8. Asignación del correlativo, folio canónico, estado y auditoría al agregado
            $folioCompleto = $serie->formatearFolio($siguienteCorrelativo);
            $comprobante->asignarCorrelativo($siguienteCorrelativo, $folioCompleto);
            $comprobante->fijarEstadoGeneracion('EMITIDO');
            $comprobante->fijarCreadoPorUsuarioId($usuarioId);

            if ($comprobante->obtenerFechaEmision() === null) {
                $comprobante->fijarFechaEmision(date('Y-m-d H:i:s'));
            }

            // 9. Persistencia atómica del agregado CPE completo
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

            // 10. Si este servicio inició la transacción, la consolida
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
}
