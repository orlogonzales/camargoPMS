<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\Recibo;
use CamargoPMS\Modelos\ReciboLinea;
use DateTimeImmutable;
use PDO;

/**
 * Repositorio de persistencia relacional para Recibos y Líneas de Imputación (RECIBOS-1 / D-082).
 */
class ReciboRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function obtenerPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Genera el siguiente folio comercial REC-YYYYMM-XXXX con bloqueo pesimista.
     */
    public function generarSiguienteFolio(?DateTimeImmutable $fecha = null): string
    {
        $fecha = $fecha ?? new DateTimeImmutable();
        $periodoYm = $fecha->format('Ym');
        $tipo = 'RECIBO';

        $debeCerrarTx = !$this->pdo->inTransaction();
        if ($debeCerrarTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT ultimo_correlativo' . "\n" .
                'FROM documento_secuencias' . "\n" .
                'WHERE tipo_documento = :tipo AND periodo_ym = :ym' . "\n" .
                'FOR UPDATE'
            );
            $stmt->execute(['tipo' => $tipo, 'ym' => $periodoYm]);
            $correlativoActual = $stmt->fetchColumn();

            if ($correlativoActual === false) {
                $nuevoCorrelativo = 1;
                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO documento_secuencias (tipo_documento, periodo_ym, ultimo_correlativo) VALUES (:tipo, :ym, :correlativo)'
                );
                $stmtIns->execute([
                    'tipo' => $tipo,
                    'ym' => $periodoYm,
                    'correlativo' => $nuevoCorrelativo,
                ]);
            } else {
                $nuevoCorrelativo = ((int) $correlativoActual) + 1;
                $stmtUpd = $this->pdo->prepare(
                    'UPDATE documento_secuencias' . "\n" .
                    'SET ultimo_correlativo = :correlativo' . "\n" .
                    'WHERE tipo_documento = :tipo AND periodo_ym = :ym'
                );
                $stmtUpd->execute([
                    'correlativo' => $nuevoCorrelativo,
                    'tipo' => $tipo,
                    'ym' => $periodoYm,
                ]);
            }

            if ($debeCerrarTx) {
                $this->pdo->commit();
            }

            return sprintf('REC-%s-%04d', $periodoYm, $nuevoCorrelativo);
        } catch (\Throwable $e) {
            if ($debeCerrarTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inserta un nuevo recibo formal en la base de datos.
     */
    public function crear(Recibo $recibo): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recibos (
                codigo, cuenta_folio_id, pago_id, persona_id,
                persona_nombre_snapshot, persona_documento_tipo_snapshot, persona_documento_numero_snapshot,
                arrendamiento_id, reserva_id, documento_emitido_id,
                monto_recaudado, monto_imputado, monto_no_aplicado_pago,
                saldo_pendiente_folio_despues, saldo_favor_folio_despues,
                moneda_codigo, metodo_pago_nombre, referencia_cobro, concepto_general,
                notas, fecha_emision, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :cuenta_folio_id, :pago_id, :persona_id,
                :persona_nombre, :persona_tipo_doc, :persona_num_doc,
                :arrendamiento_id, :reserva_id, :documento_emitido_id,
                :monto_recaudado, :monto_imputado, :monto_no_aplicado_pago,
                :saldo_pendiente, :saldo_favor,
                :moneda_codigo, :metodo_pago, :referencia_cobro, :concepto_general,
                :notas, :fecha_emision, :estado, :creado_por, NOW()
            )'
        );

        $stmt->execute([
            'codigo' => $recibo->obtenerCodigo(),
            'cuenta_folio_id' => $recibo->obtenerCuentaFolioId(),
            'pago_id' => $recibo->obtenerPagoId(),
            'persona_id' => $recibo->obtenerPersonaId(),
            'persona_nombre' => $recibo->obtenerPersonaNombreSnapshot(),
            'persona_tipo_doc' => $recibo->obtenerPersonaDocumentoTipoSnapshot(),
            'persona_num_doc' => $recibo->obtenerPersonaDocumentoNumeroSnapshot(),
            'arrendamiento_id' => $recibo->obtenerArrendamientoId(),
            'reserva_id' => $recibo->obtenerReservaId(),
            'documento_emitido_id' => $recibo->obtenerDocumentoEmitidoId(),
            'monto_recaudado' => $recibo->obtenerMontoRecaudado(),
            'monto_imputado' => $recibo->obtenerMontoImputado(),
            'monto_no_aplicado_pago' => $recibo->obtenerMontoNoAplicadoPago(),
            'saldo_pendiente' => $recibo->obtenerSaldoPendienteFolioDespues(),
            'saldo_favor' => $recibo->obtenerSaldoFavorFolioDespues(),
            'moneda_codigo' => $recibo->obtenerMonedaCodigo(),
            'metodo_pago' => $recibo->obtenerMetodoPagoNombre(),
            'referencia_cobro' => $recibo->obtenerReferenciaCobro(),
            'concepto_general' => $recibo->obtenerConceptoGeneral(),
            'notas' => $recibo->obtenerNotas(),
            'fecha_emision' => $recibo->obtenerFechaEmision(),
            'estado' => $recibo->obtenerEstado(),
            'creado_por' => $recibo->obtenerCreadoPorActorId(),
        ]);

        $reciboId = (int) $this->pdo->lastInsertId();

        if (!empty($recibo->obtenerLineas())) {
            $this->insertarLineas($reciboId, $recibo->obtenerLineas());
        }

        return $reciboId;
    }

    /**
     * @param ReciboLinea[] $lineas
     */
    public function insertarLineas(int $reciboId, array $lineas): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recibo_lineas (
                recibo_id, numero_linea, aplicacion_id, cargo_id,
                cargo_codigo, cargo_concepto, cargo_origen_tipo,
                cargo_monto_total, monto_aplicado, cargo_saldo_restante, creado_en
            ) VALUES (
                :recibo_id, :numero_linea, :aplicacion_id, :cargo_id,
                :cargo_codigo, :cargo_concepto, :cargo_origen_tipo,
                :cargo_monto_total, :monto_aplicado, :cargo_saldo_restante, NOW()
            )'
        );

        foreach ($lineas as $l) {
            $stmt->execute([
                'recibo_id' => $reciboId,
                'numero_linea' => $l->obtenerNumeroLinea(),
                'aplicacion_id' => $l->obtenerAplicacionId(),
                'cargo_id' => $l->obtenerCargoId(),
                'cargo_codigo' => $l->obtenerCargoCodigo(),
                'cargo_concepto' => $l->obtenerCargoConcepto(),
                'cargo_origen_tipo' => $l->obtenerCargoOrigenTipo(),
                'cargo_monto_total' => $l->obtenerCargoMontoTotal(),
                'monto_aplicado' => $l->obtenerMontoAplicado(),
                'cargo_saldo_restante' => $l->obtenerCargoSaldoRestante(),
            ]);
        }
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?Recibo
    {
        $sql = 'SELECT * FROM recibos WHERE id = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $recibo = Recibo::desdeArreglo($fila);
        $recibo->asignarLineas($this->obtenerLineasPorReciboId((int) $fila['id']));

        return $recibo;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?Recibo
    {
        $sql = 'SELECT * FROM recibos WHERE codigo = :codigo LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $recibo = Recibo::desdeArreglo($fila);
        $recibo->asignarLineas($this->obtenerLineasPorReciboId((int) $fila['id']));

        return $recibo;
    }

    public function obtenerPorPagoId(int $pagoId, bool $soloActivo = true): ?Recibo
    {
        $sql = 'SELECT * FROM recibos WHERE pago_id = :pago_id';
        if ($soloActivo) {
            $sql .= ' AND estado = "EMITIDO"';
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['pago_id' => $pagoId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $recibo = Recibo::desdeArreglo($fila);
        $recibo->asignarLineas($this->obtenerLineasPorReciboId((int) $fila['id']));

        return $recibo;
    }

    /**
     * @return ReciboLinea[]
     */
    public function obtenerLineasPorReciboId(int $reciboId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM recibo_lineas WHERE recibo_id = :recibo_id ORDER BY numero_linea ASC'
        );
        $stmt->execute(['recibo_id' => $reciboId]);
        $resultado = [];

        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = ReciboLinea::desdeArreglo($fila);
        }

        return $resultado;
    }

    /**
     * @return Recibo[]
     */
    public function listar(?int $cuentaFolioId = null, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM recibos WHERE 1=1';
        $params = [];

        if ($cuentaFolioId !== null && $cuentaFolioId > 0) {
            $sql .= ' AND cuenta_folio_id = :cuenta_folio_id';
            $params['cuenta_folio_id'] = $cuentaFolioId;
        }

        if ($estado !== null && $estado !== '') {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $resultado = [];

        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recibo = Recibo::desdeArreglo($fila);
            $recibo->asignarLineas($this->obtenerLineasPorReciboId((int) $fila['id']));
            $resultado[] = $recibo;
        }

        return $resultado;
    }

    public function actualizarDocumentoEmitidoId(int $reciboId, int $documentoEmitidoId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recibos SET documento_emitido_id = :doc_id, actualizado_en = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'doc_id' => $documentoEmitidoId,
            'id' => $reciboId,
        ]);
    }

    public function anular(int $reciboId, string $motivo, int $actorId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE recibos SET estado = "ANULADO", motivo_anulacion = :motivo, anulado_en = NOW(), anulado_por_actor_id = :actor_id, actualizado_en = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'motivo' => $motivo,
            'actor_id' => $actorId,
            'id' => $reciboId,
        ]);
    }
}
