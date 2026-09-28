<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\GastoAplicacionPago;
use CamargoPMS\Modelos\PagoEgreso;
use PDO;

/**
 * Repositorio de persistencia para Pagos de Egreso e Imputaciones a Gastos.
 * GASTOS-1 / FINANCIERO-2 / D-086.
 */
class PagoEgresoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generarSiguienteCodigoPagoEgreso(): string
    {
        $ym = date('Ym');
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM pagos_egreso WHERE codigo LIKE :prefix ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefix' => "PAG-EGR-{$ym}-%"]);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/PAG-EGR-\d{6}-(\d{4})/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        } else {
            $siguiente = 1;
        }

        return sprintf('PAG-EGR-%s-%04d', $ym, $siguiente);
    }

    public function crearPagoEgreso(PagoEgreso $pago): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pagos_egreso (
                codigo, metodo_pago_id, monto_total, moneda_codigo, fecha_pago,
                sesion_caja_id, movimiento_caja_id, cuenta_bancaria_id, movimiento_bancario_id,
                referencia_operacion, estado, registrado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :metodo_pago_id, :monto_total, :moneda_codigo, :fecha_pago,
                :sesion_caja_id, :movimiento_caja_id, :cuenta_bancaria_id, :movimiento_bancario_id,
                :referencia_operacion, :estado, :registrado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $pago->obtenerCodigo(),
            'metodo_pago_id' => $pago->obtenerMetodoPagoId(),
            'monto_total' => $pago->obtenerMontoTotal(),
            'moneda_codigo' => $pago->obtenerMonedaCodigo(),
            'fecha_pago' => $pago->obtenerFechaPago(),
            'sesion_caja_id' => $pago->obtenerSesionCajaId(),
            'movimiento_caja_id' => $pago->obtenerMovimientoCajaId(),
            'cuenta_bancaria_id' => $pago->obtenerCuentaBancariaId(),
            'movimiento_bancario_id' => $pago->obtenerMovimientoBancarioId(),
            'referencia_operacion' => $pago->obtenerReferenciaOperacion(),
            'estado' => $pago->obtenerEstado(),
            'registrado_por_actor_id' => $pago->obtenerRegistradoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPagoEgresoPorId(int $id, bool $bloquear = false): ?PagoEgreso
    {
        $sql = 'SELECT * FROM pagos_egreso WHERE id = :id LIMIT 1';
        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? PagoEgreso::desdeArreglo($fila) : null;
    }

    public function obtenerPagoEgresoPorCodigo(string $codigo): ?PagoEgreso
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pagos_egreso WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? PagoEgreso::desdeArreglo($fila) : null;
    }

    public function actualizarEstadoPagoEgreso(
        int $id,
        string $estado,
        ?string $motivo = null,
        ?int $actorId = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE pagos_egreso SET
                estado = :estado,
                motivo_reverso = :motivo,
                reversado_en = NOW(),
                reversado_por_actor_id = :actor_id,
                actualizado_en = NOW()
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $id,
            'estado' => $estado,
            'motivo' => $motivo,
            'actor_id' => $actorId,
        ]);
    }

    // =========================================================================
    // APLICACIONES / IMPUTACIONES
    // =========================================================================

    public function generarSiguienteCodigoAplicacion(): string
    {
        $ym = date('Ym');
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM gasto_aplicaciones_pago WHERE codigo LIKE :prefix ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefix' => "APL-GST-{$ym}-%"]);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/APL-GST-\d{6}-(\d{4})/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        } else {
            $siguiente = 1;
        }

        return sprintf('APL-GST-%s-%04d', $ym, $siguiente);
    }

    public function crearAplicacion(GastoAplicacionPago $apl): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gasto_aplicaciones_pago (
                codigo, gasto_id, pago_egreso_id, monto_aplicado, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :gasto_id, :pago_egreso_id, :monto_aplicado, :estado, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $apl->obtenerCodigo(),
            'gasto_id' => $apl->obtenerGastoId(),
            'pago_egreso_id' => $apl->obtenerPagoEgresoId(),
            'monto_aplicado' => $apl->obtenerMontoAplicado(),
            'estado' => $apl->obtenerEstado(),
            'creado_por_actor_id' => $apl->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarEstadoAplicacion(
        int $id,
        string $estado,
        ?string $motivo = null,
        ?int $actorId = null
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE gasto_aplicaciones_pago SET
                estado = :estado,
                motivo_reverso = :motivo,
                reversado_en = NOW(),
                reversado_por_actor_id = :actor_id
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $id,
            'estado' => $estado,
            'motivo' => $motivo,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @return GastoAplicacionPago[]
     */
    public function listarAplicacionesPorPago(int $pagoEgresoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gasto_aplicaciones_pago WHERE pago_egreso_id = :pago_id ORDER BY id ASC'
        );
        $stmt->execute(['pago_id' => $pagoEgresoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($f) => GastoAplicacionPago::desdeArreglo($f), $filas);
    }

    public function obtenerTotalAplicadoActivoPorGasto(int $gastoId, bool $bloquear = false): string
    {
        $sql = 'SELECT COALESCE(SUM(monto_aplicado), 0.00)
                FROM gasto_aplicaciones_pago
                WHERE gasto_id = :gasto_id AND estado = "ACTIVO"';
        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['gasto_id' => $gastoId]);
        $val = $stmt->fetchColumn();
        return bcadd((string) $val, '0.00', 2);
    }

    public function obtenerTotalAplicadoActivoPorPago(int $pagoEgresoId, bool $bloquear = false): string
    {
        $sql = 'SELECT COALESCE(SUM(monto_aplicado), 0.00)
                FROM gasto_aplicaciones_pago
                WHERE pago_egreso_id = :pago_id AND estado = "ACTIVO"';
        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['pago_id' => $pagoEgresoId]);
        $val = $stmt->fetchColumn();
        return bcadd((string) $val, '0.00', 2);
    }
}
