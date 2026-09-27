<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ArrendamientoGarantia;
use PDO;

/**
 * Repositorio para la custodia segregada de depósitos de garantía con saldo reconstructible.
 */
class ArrendamientoGarantiaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(ArrendamientoGarantia $garantia): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO arrendamiento_garantias (
                arrendamiento_id, monto_pactado, monto_recibido, monto_retenido_actual,
                monto_compensado_danos, monto_compensado_renta, monto_devuelto, estado, creado_en
            ) VALUES (
                :arrendamiento_id, :monto_pactado, :monto_recibido, :monto_retenido_actual,
                :monto_compensado_danos, :monto_compensado_renta, :monto_devuelto, :estado, NOW()
            )'
        );

        $stmt->execute([
            'arrendamiento_id' => $garantia->obtenerArrendamientoId(),
            'monto_pactado' => $garantia->obtenerMontoPactado(),
            'monto_recibido' => $garantia->obtenerMontoRecibido(),
            'monto_retenido_actual' => $garantia->obtenerMontoRetenidoActual(),
            'monto_compensado_danos' => $garantia->obtenerMontoCompensadoDanos(),
            'monto_compensado_renta' => $garantia->obtenerMontoCompensadoRenta(),
            'monto_devuelto' => $garantia->obtenerMontoDevuelto(),
            'estado' => $garantia->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorArrendamientoId(int $arrendamientoId, bool $bloquear = false): ?ArrendamientoGarantia
    {
        $sql = 'SELECT * FROM arrendamiento_garantias WHERE arrendamiento_id = :arrendamiento_id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? ArrendamientoGarantia::desdeArreglo($fila) : null;
    }

    public function actualizarSaldos(
        int $id,
        string $montoRecibido,
        string $montoRetenidoActual,
        string $montoCompensadoDanos,
        string $montoCompensadoRenta,
        string $montoDevuelto,
        string $estado
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE arrendamiento_garantias 
             SET monto_recibido = :monto_recibido,
                 monto_retenido_actual = :monto_retenido_actual,
                 monto_compensado_danos = :monto_compensado_danos,
                 monto_compensado_renta = :monto_compensado_renta,
                 monto_devuelto = :monto_devuelto,
                 estado = :estado,
                 actualizado_en = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'monto_recibido' => $montoRecibido,
            'monto_retenido_actual' => $montoRetenidoActual,
            'monto_compensado_danos' => $montoCompensadoDanos,
            'monto_compensado_renta' => $montoCompensadoRenta,
            'monto_devuelto' => $montoDevuelto,
            'estado' => $estado,
            'id' => $id,
        ]);
    }
}
