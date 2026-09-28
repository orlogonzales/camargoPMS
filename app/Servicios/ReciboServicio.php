<?php

declare(strict_types=1);

namespace CamargoPMS\Servicios;

use CamargoPMS\Excepciones\ConflictoReciboExcepcion;
use CamargoPMS\Excepciones\DocumentoCorruptoExcepcion;
use CamargoPMS\Excepciones\ReciboNoEncontradoExcepcion;
use CamargoPMS\Excepciones\ValidacionReciboExcepcion;
use CamargoPMS\Modelos\DocumentoEmitido;
use CamargoPMS\Modelos\Recibo;
use CamargoPMS\Modelos\ReciboLinea;
use CamargoPMS\Repositorios\AplicacionPagoRepositorio;
use CamargoPMS\Repositorios\CargoCuentaRepositorio;
use CamargoPMS\Repositorios\CuentaFolioRepositorio;
use CamargoPMS\Repositorios\DocumentoRepositorio;
use CamargoPMS\Repositorios\PagoCuentaRepositorio;
use CamargoPMS\Repositorios\ReciboRepositorio;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Servicio de dominio principal para la emisión, certificación probatoria y consulta
 * de Recibos de Cobranza con snapshot financiero en T0 (RECIBOS-1 / D-082).
 */
class ReciboServicio
{
    public function __construct(
        private ReciboRepositorio $reciboRepo,
        private PagoCuentaRepositorio $pagoRepo,
        private AplicacionPagoRepositorio $aplicacionRepo,
        private CargoCuentaRepositorio $cargoRepo,
        private CuentaFolioRepositorio $cuentaFolioRepo,
        private DocumentoServicio $documentoServicio,
        private DocumentoRepositorio $documentoRepo,
        private ?PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? $this->reciboRepo->obtenerPdo();
    }

    /**
     * Emite un recibo oficial probatorio para un pago confirmado, congelando el snapshot
     * de amortizaciones y saldos del folio en T0 y generando el PDF soberano inmutable.
     *
     * @param int $pagoId ID del pago confirmado en pagos_cuenta
     * @param int $actorId ID del usuario/actor que emite el recibo
     * @param string|null $notas Notas administrativas opcionales
     * @param string|null $conceptoGeneral Concepto explicativo general
     * @return Recibo
     *
     * @throws ValidacionReciboExcepcion
     * @throws ConflictoReciboExcepcion
     * @throws Throwable
     */
    public function emitirReciboParaPago(
        int $pagoId,
        int $actorId,
        ?string $notas = null,
        ?string $conceptoGeneral = null
    ): Recibo {
        if ($pagoId <= 0) {
            throw new ValidacionReciboExcepcion(['pago_id' => 'El identificador del pago es inválido.']);
        }

        $debeGestionarTransaccion = !$this->pdo->inTransaction();
        if ($debeGestionarTransaccion) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Bloqueo pesimista del pago
            $pago = $this->pagoRepo->obtenerPorId($pagoId, true);
            if ($pago === null) {
                throw new ValidacionReciboExcepcion(['pago_id' => "El pago [{$pagoId}] no existe."]);
            }

            if ($pago->obtenerEstado() !== 'CONFIRMADO') {
                throw new ConflictoReciboExcepcion("Solo se pueden emitir recibos sobre pagos en estado CONFIRMADO. Estado actual: [{$pago->obtenerEstado()}].");
            }

            // 2. Verificar que no exista recibo activo para este pago (D-082 #2)
            $reciboExistente = $this->reciboRepo->obtenerPorPagoId($pagoId, true);
            if ($reciboExistente !== null) {
                throw new ConflictoReciboExcepcion("Ya existe un recibo activo emitido para el pago [{$pago->obtenerCodigo()}]: folio [{$reciboExistente->obtenerCodigo()}].");
            }

            // 3. Consultar cuenta folio asociada
            $folio = $this->cuentaFolioRepo->obtenerPorId($pago->obtenerCuentaFolioId(), true);
            if ($folio === null) {
                throw new ValidacionReciboExcepcion(['cuenta_folio_id' => "La cuenta folio vinculada al pago [{$pagoId}] no existe."]);
            }

            // 4. Extraer datos del titular desde personas y personas_documentos
            $titularDatos = $this->extraerDatosTitular($folio->obtenerPersonaTitularId());

            // 5. Extraer nombre del método de pago
            $metodoPagoNombre = $this->obtenerNombreMetodoPago($pago->obtenerMetodoPagoId());

            // 6. Consultar aplicaciones activas de este pago en T0 (D-082 #1)
            $aplicaciones = $this->aplicacionRepo->listarPorPago($pagoId);
            $lineasRecibo = [];
            $montoImputadoAcumulado = '0.00';
            $numLinea = 1;

            foreach ($aplicaciones as $apl) {
                if ($apl->obtenerEstado() !== 'ACTIVA') {
                    continue;
                }

                $cargo = $this->cargoRepo->obtenerPorId($apl->obtenerCargoId(), true);
                if ($cargo === null) {
                    continue;
                }

                $montoAplicado = $apl->obtenerMontoAplicado();
                $montoImputadoAcumulado = bcadd($montoImputadoAcumulado, $montoAplicado, 2);

                // En T0, el saldo restante del cargo tras la aplicación
                $saldoRestante = bcsub($cargo->obtenerTotal(), $cargo->obtenerMontoAplicadoAcumulado(), 2);
                if (bccomp($saldoRestante, '0.00', 2) < 0) {
                    $saldoRestante = '0.00';
                }

                $lineasRecibo[] = new ReciboLinea(
                    null,
                    0, // se asignará al crear cabecera
                    $numLinea,
                    $apl->obtenerId(),
                    $cargo->obtenerId(),
                    $cargo->obtenerCodigo(),
                    $cargo->obtenerConcepto(),
                    $cargo->obtenerOrigenTipo(),
                    $cargo->obtenerTotal(),
                    $montoAplicado,
                    $saldoRestante
                );
                $numLinea++;
            }

            $montoRecaudado = $pago->obtenerMontoTotal();
            $montoNoAplicadoPago = bcsub($montoRecaudado, $montoImputadoAcumulado, 2);

            // Validar que el monto no aplicado no sea negativo
            if (bccomp($montoNoAplicadoPago, '0.00', 2) < 0) {
                throw new ValidacionReciboExcepcion(['monto_imputado' => "El total imputado ({$montoImputadoAcumulado}) excede el monto recaudado ({$montoRecaudado})."]);
            }

            // Validar balance algebraico inviolable (D-082 #5)
            $sumaBalance = bcadd($montoImputadoAcumulado, $montoNoAplicadoPago, 2);
            if (bccomp($montoRecaudado, $sumaBalance, 2) !== 0) {
                throw new ValidacionReciboExcepcion(['balance' => "Inconsistencia de balance: recaudado [{$montoRecaudado}] != imputado [{$montoImputadoAcumulado}] + no aplicado [{$montoNoAplicadoPago}]."]);
            }

            // 7. Calcular saldos del folio resultantes en T0 (D-082 #6)
            $saldosFolio = $this->calcularSaldosFolioT0($folio->obtenerId());

            // 8. Generar siguiente folio oficial REC-YYYYMM-XXXX con exclusión mutua
            $fechaEmision = new DateTimeImmutable();
            $codigoFolio = $this->reciboRepo->generarSiguienteFolio($fechaEmision);

            // 9. Resolver concepto general descriptivo
            $conceptoFinal = $conceptoGeneral;
            if (empty($conceptoFinal)) {
                if (empty($lineasRecibo)) {
                    $conceptoFinal = "Cobranza de pago sin imputación a folio {$folio->obtenerCodigo()}";
                } elseif (count($lineasRecibo) === 1) {
                    $conceptoFinal = "Cobranza de {$lineasRecibo[0]->obtenerCargoConcepto()}";
                } else {
                    $conceptoFinal = "Cobranza de obligaciones múltiples en folio {$folio->obtenerCodigo()}";
                }
            }

            // 10. Construir entidad Recibo para persistencia
            $recibo = new Recibo(
                null,
                $codigoFolio,
                $folio->obtenerId(),
                $pagoId,
                $titularDatos['persona_id'],
                $titularDatos['nombre_completo'],
                $titularDatos['tipo_documento'],
                $titularDatos['numero_documento'],
                $folio->obtenerArrendamientoId(),
                $folio->obtenerReservaId(),
                null, // documento_emitido_id se asigna tras renderizar
                $montoRecaudado,
                $montoImputadoAcumulado,
                $montoNoAplicadoPago,
                $saldosFolio['saldo_pendiente_folio_despues'],
                $saldosFolio['saldo_favor_folio_despues'],
                $pago->obtenerMonedaCodigo(),
                $metodoPagoNombre,
                $pago->obtenerReferenciaOperacion(),
                $conceptoFinal,
                $notas,
                $fechaEmision->format('Y-m-d H:i:s'),
                Recibo::ESTADO_EMITIDO,
                null,
                null,
                null,
                $actorId,
                $fechaEmision->format('Y-m-d H:i:s'),
                null,
                $lineasRecibo
            );

            // 11. Insertar cabecera y líneas en base de datos
            $reciboId = $this->reciboRepo->crear($recibo);

            // 12. Integración documental: compilar plantilla RECIBO_PAGO y generar PDF soberano (D-082 #12)
            $resultadoDoc = $this->documentoServicio->emitirReciboPago($reciboId, $actorId);
            $documentoEmitido = $resultadoDoc['documento'];

            if ($documentoEmitido === null || $documentoEmitido->obtenerId() === null) {
                throw new ValidacionReciboExcepcion(['documento' => 'Falló la generación del documento emitido oficial.']);
            }

            // 13. Vincular documento_emitido_id en recibos
            $this->reciboRepo->actualizarDocumentoEmitidoId($reciboId, (int) $documentoEmitido->obtenerId());

            if ($debeGestionarTransaccion) {
                $this->pdo->commit();
            }

            // Retornar la entidad completa persistida
            return $this->reciboRepo->obtenerPorId($reciboId);
        } catch (Throwable $e) {
            if ($debeGestionarTransaccion && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula un recibo formalmente sin alterar el archivo binario PDF en disco
     * ni revertir el pago en FINANCIERO-2 (D-082 #3, #4).
     */
    public function anularRecibo(int $reciboId, string $motivo, int $actorId): bool
    {
        $motivoLimpio = trim($motivo);
        if ($reciboId <= 0 || $motivoLimpio === '') {
            throw new ValidacionReciboExcepcion(['motivo' => 'El identificador de recibo y el motivo de anulación son obligatorios.']);
        }

        $recibo = $this->reciboRepo->obtenerPorId($reciboId, true);
        if ($recibo === null) {
            throw new ReciboNoEncontradoExcepcion("Recibo con ID [{$reciboId}] no encontrado.");
        }

        if ($recibo->obtenerEstado() === Recibo::ESTADO_ANULADO) {
            throw new ConflictoReciboExcepcion("El recibo [{$recibo->obtenerCodigo()}] ya se encuentra ANULADO.");
        }

        $this->reciboRepo->anular($reciboId, $motivoLimpio, $actorId);
        return true;
    }

    /**
     * Descarga y valida la integridad física del PDF soberano contra su hash SHA-256 (D-082 #13).
     *
     * @return array{binario: string, nombre_archivo: string, mime: string, hash: string}
     */
    public function descargarPdfRecibo(int $reciboId, ?int $actorId = null): array
    {
        $recibo = $this->reciboRepo->obtenerPorId($reciboId);
        if ($recibo === null) {
            throw new ReciboNoEncontradoExcepcion("Recibo con ID [{$reciboId}] no encontrado.");
        }

        $docId = $recibo->obtenerDocumentoEmitidoId();
        if ($docId === null || $docId <= 0) {
            throw new ValidacionReciboExcepcion(['documento' => "El recibo [{$recibo->obtenerCodigo()}] no posee un documento emitido asociado."]);
        }

        $actorDescarga = $actorId ?? $recibo->obtenerCreadoPorActorId() ?? 1;

        // Descarga mediante el servicio documental con verificación criptográfica
        $descarga = $this->documentoServicio->descargarDocumento($docId, $actorDescarga);

        return [
            'binario' => $descarga['binario_pdf'],
            'nombre_archivo' => "{$recibo->obtenerCodigo()}.pdf",
            'mime' => 'application/pdf',
            'hash' => $descarga['documento']->obtenerHashPdfSha256(),
        ];
    }

    /**
     * Verifica el hash criptográfico SHA-256 del binario en disco contra el registro inmutable en BD.
     *
     * @return array{valido: bool, hash_esperado: string, hash_calculado: string}
     */
    public function verificarHashRecibo(int $reciboId): array
    {
        $recibo = $this->reciboRepo->obtenerPorId($reciboId);
        if ($recibo === null) {
            throw new ReciboNoEncontradoExcepcion("Recibo con ID [{$reciboId}] no encontrado.");
        }

        $docId = $recibo->obtenerDocumentoEmitidoId();
        if ($docId === null) {
            throw new ValidacionReciboExcepcion(['documento' => 'El recibo no tiene documento emitido asociado.']);
        }

        return $this->documentoServicio->verificarIntegridad($docId);
    }

    public function obtenerRecibo(int $id): Recibo
    {
        $recibo = $this->reciboRepo->obtenerPorId($id);
        if ($recibo === null) {
            throw new ReciboNoEncontradoExcepcion("Recibo con ID [{$id}] no encontrado.");
        }
        return $recibo;
    }

    public function obtenerReciboPorCodigo(string $codigo): Recibo
    {
        $recibo = $this->reciboRepo->obtenerPorCodigo($codigo);
        if ($recibo === null) {
            throw new ReciboNoEncontradoExcepcion("Recibo con código [{$codigo}] no encontrado.");
        }
        return $recibo;
    }

    public function obtenerReciboPorPago(int $pagoId, bool $soloActivo = true): ?Recibo
    {
        return $this->reciboRepo->obtenerPorPagoId($pagoId, $soloActivo);
    }

    /**
     * @return Recibo[]
     */
    public function listarRecibos(?int $cuentaFolioId = null, ?string $estado = null): array
    {
        return $this->reciboRepo->listar($cuentaFolioId, $estado);
    }

    /**
     * Retorna indicadores para los 4 KPIs superiores del panel Alina (D-082).
     *
     * @return array{emitidos_hoy: int, total_recaudado_hoy: string, anulados_total: int, con_saldo_favor: int}
     */
    public function obtenerEstadisticas(): array
    {
        $stmt = $this->pdo->query(
            'SELECT' . "\n" .
            '    COUNT(CASE WHEN DATE(fecha_emision) = CURDATE() AND estado = "EMITIDO" THEN 1 END) AS emitidos_hoy,' . "\n" .
            '    COALESCE(SUM(CASE WHEN DATE(fecha_emision) = CURDATE() AND estado = "EMITIDO" THEN monto_recaudado ELSE 0 END), 0.00) AS total_recaudado_hoy,' . "\n" .
            '    COUNT(CASE WHEN estado = "ANULADO" THEN 1 END) AS anulados_total,' . "\n" .
            '    COUNT(CASE WHEN monto_no_aplicado_pago > 0 AND estado = "EMITIDO" THEN 1 END) AS con_saldo_favor' . "\n" .
            ' FROM recibos'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'emitidos_hoy' => (int) ($row['emitidos_hoy'] ?? 0),
            'total_recaudado_hoy' => number_format((float) ($row['total_recaudado_hoy'] ?? 0.00), 2, '.', ''),
            'anulados_total' => (int) ($row['anulados_total'] ?? 0),
            'con_saldo_favor' => (int) ($row['con_saldo_favor'] ?? 0),
        ];
    }

    /**
     * Extrae información de nombre y documento del titular congelada para snapshot.
     */
    private function extraerDatosTitular(int $personaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id,
                    TRIM(CONCAT(p.nombres, " ", p.apellido_paterno, " ", COALESCE(p.apellido_materno, ""))) AS nombre_completo,
                    COALESCE(td.codigo, "DNI") AS tipo_documento,
                    COALESCE(pd.numero_documento, "S/D") AS numero_documento
             FROM personas p
             LEFT JOIN personas_documentos pd ON pd.persona_id = p.id
             LEFT JOIN tipos_documento td ON td.id = pd.tipo_documento_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $personaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'persona_id' => $personaId,
                'nombre_completo' => 'Cliente General',
                'tipo_documento' => 'DNI',
                'numero_documento' => '00000000',
            ];
        }

        return [
            'persona_id' => (int) $row['id'],
            'nombre_completo' => (string) $row['nombre_completo'],
            'tipo_documento' => (string) $row['tipo_documento'],
            'numero_documento' => (string) $row['numero_documento'],
        ];
    }

    private function obtenerNombreMetodoPago(int $metodoPagoId): string
    {
        $stmt = $this->pdo->prepare('SELECT nombre FROM metodos_pago WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $metodoPagoId]);
        $nombre = $stmt->fetchColumn();
        return $nombre ? (string) $nombre : 'Efectivo';
    }

    /**
     * Calcula los saldos de folio devengados, confirmados y aplicados en T0.
     *
     * @return array{saldo_pendiente_folio_despues: string, saldo_favor_folio_despues: string}
     */
    private function calcularSaldosFolioT0(int $folioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT' . "\n" .
            '    COALESCE((SELECT SUM(c.total) FROM cargos_cuenta c WHERE c.cuenta_folio_id = :f1 AND c.estado = "DEVENGADO"), 0.00) AS total_cargos_devengados,' . "\n" .
            '    COALESCE((SELECT SUM(pg.monto_total) FROM pagos_cuenta pg WHERE pg.cuenta_folio_id = :f2 AND pg.estado = "CONFIRMADO"), 0.00) AS total_pagos_confirmados,' . "\n" .
            '    COALESCE((SELECT SUM(pg.monto_aplicado) FROM pagos_cuenta pg WHERE pg.cuenta_folio_id = :f3 AND pg.estado = "CONFIRMADO"), 0.00) AS total_pagos_aplicados'
        );
        $stmt->execute(['f1' => $folioId, 'f2' => $folioId, 'f3' => $folioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $devengado = (string) ($row['total_cargos_devengados'] ?? '0.00');
        $confirmado = (string) ($row['total_pagos_confirmados'] ?? '0.00');
        $aplicado = (string) ($row['total_pagos_aplicados'] ?? '0.00');

        $pendiente = bcsub($devengado, $aplicado, 2);
        if (bccomp($pendiente, '0.00', 2) < 0) {
            $pendiente = '0.00';
        }

        $favor = bcsub($confirmado, $aplicado, 2);
        if (bccomp($favor, '0.00', 2) < 0) {
            $favor = '0.00';
        }

        return [
            'saldo_pendiente_folio_despues' => $pendiente,
            'saldo_favor_folio_despues' => $favor,
        ];
    }
}
