<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ArrendamientoCuota;
use PDO;

/**
 * Repositorio para la gestión de cuotas periódicas de arrendamiento e integración con cargos de cuenta.
 */
class ArrendamientoCuotaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(ArrendamientoCuota $cuota): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO arrendamiento_cuotas (
                arrendamiento_id, periodo_anio, periodo_mes, periodo_codigo, tipo_cuota,
                fecha_emision, fecha_vencimiento, monto_renta, cargo_cuenta_id, estado, creado_en
            ) VALUES (
                :arrendamiento_id, :periodo_anio, :periodo_mes, :periodo_codigo, :tipo_cuota,
                :fecha_emision, :fecha_vencimiento, :monto_renta, :cargo_cuenta_id, :estado, NOW()
            )'
        );

        $stmt->execute([
            'arrendamiento_id' => $cuota->obtenerArrendamientoId(),
            'periodo_anio' => $cuota->obtenerPeriodoAnio(),
            'periodo_mes' => $cuota->obtenerPeriodoMes(),
            'periodo_codigo' => $cuota->obtenerPeriodoCodigo(),
            'tipo_cuota' => $cuota->obtenerTipoCuota(),
            'fecha_emision' => $cuota->obtenerFechaEmision(),
            'fecha_vencimiento' => $cuota->obtenerFechaVencimiento(),
            'monto_renta' => $cuota->obtenerMontoRenta(),
            'cargo_cuenta_id' => $cuota->obtenerCargoCuentaId(),
            'estado' => $cuota->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id): ?ArrendamientoCuota
    {
        $sql = 'SELECT 
                    ac.*,
                    cc.codigo AS cargo_codigo,
                    cc.monto_aplicado_acumulado,
                    (cc.total - cc.monto_aplicado_acumulado) AS saldo_pendiente
                FROM arrendamiento_cuotas ac
                INNER JOIN cargos_cuenta cc ON cc.id = ac.cargo_cuenta_id
                WHERE ac.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ArrendamientoCuota::desdeArreglo($fila) : null;
    }

    public function obtenerPorPeriodoYTipo(
        int $arrendamientoId,
        int $anio,
        int $mes,
        string $tipoCuota
    ): ?ArrendamientoCuota {
        $sql = 'SELECT 
                    ac.*,
                    cc.codigo AS cargo_codigo,
                    cc.monto_aplicado_acumulado,
                    (cc.total - cc.monto_aplicado_acumulado) AS saldo_pendiente
                FROM arrendamiento_cuotas ac
                INNER JOIN cargos_cuenta cc ON cc.id = ac.cargo_cuenta_id
                WHERE ac.arrendamiento_id = :arrendamiento_id
                  AND ac.periodo_anio = :anio
                  AND ac.periodo_mes = :mes
                  AND ac.tipo_cuota = :tipo_cuota
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'arrendamiento_id' => $arrendamientoId,
            'anio' => $anio,
            'mes' => $mes,
            'tipo_cuota' => $tipoCuota,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ArrendamientoCuota::desdeArreglo($fila) : null;
    }

    public function obtenerPorCargoCuentaId(int $cargoCuentaId): ?ArrendamientoCuota
    {
        $sql = 'SELECT 
                    ac.*,
                    cc.codigo AS cargo_codigo,
                    cc.monto_aplicado_acumulado,
                    (cc.total - cc.monto_aplicado_acumulado) AS saldo_pendiente
                FROM arrendamiento_cuotas ac
                INNER JOIN cargos_cuenta cc ON cc.id = ac.cargo_cuenta_id
                WHERE ac.cargo_cuenta_id = :cargo_cuenta_id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cargo_cuenta_id' => $cargoCuentaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ArrendamientoCuota::desdeArreglo($fila) : null;
    }

    /**
     * @return array<ArrendamientoCuota>
     */
    public function listarPorArrendamientoId(int $arrendamientoId): array
    {
        $sql = 'SELECT 
                    ac.*,
                    cc.codigo AS cargo_codigo,
                    cc.monto_aplicado_acumulado,
                    (cc.total - cc.monto_aplicado_acumulado) AS saldo_pendiente
                FROM arrendamiento_cuotas ac
                INNER JOIN cargos_cuenta cc ON cc.id = ac.cargo_cuenta_id
                WHERE ac.arrendamiento_id = :arrendamiento_id
                ORDER BY ac.periodo_anio ASC, ac.periodo_mes ASC, ac.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = ArrendamientoCuota::desdeArreglo($fila);
        }

        return $resultado;
    }

    public function actualizarEstado(int $id, string $nuevoEstado): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE arrendamiento_cuotas SET estado = :estado WHERE id = :id'
        );
        $stmt->execute(['estado' => $nuevoEstado, 'id' => $id]);
    }
}
