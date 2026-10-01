<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\AplicacionPago;
use PDO;

/**
 * Repositorio para la gestión de aplicaciones e imputaciones formales entre pagos y cargos.
 */
class AplicacionPagoRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(AplicacionPago $aplicacion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO aplicaciones_pago (
                codigo, pago_id, cargo_id, monto_aplicado, moneda_codigo, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :pago_id, :cargo_id, :monto_aplicado, :moneda_codigo, :estado, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $aplicacion->obtenerCodigo(),
            'pago_id' => $aplicacion->obtenerPagoId(),
            'cargo_id' => $aplicacion->obtenerCargoId(),
            'monto_aplicado' => $aplicacion->obtenerMontoAplicado(),
            'moneda_codigo' => $aplicacion->obtenerMonedaCodigo(),
            'estado' => $aplicacion->obtenerEstado(),
            'creado_por_actor_id' => $aplicacion->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?AplicacionPago
    {
        $sql = 'SELECT * FROM aplicaciones_pago WHERE id = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? AplicacionPago::desdeArreglo($fila) : null;
    }

    /**
     * @return array<AplicacionPago>
     */
    public function listarPorPago(int $pagoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM aplicaciones_pago WHERE pago_id = :pago_id ORDER BY id ASC');
        $stmt->execute(['pago_id' => $pagoId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = AplicacionPago::desdeArreglo($fila);
        }
        return $resultado;
    }

    /**
     * @return array<AplicacionPago>
     */
    public function listarPorCargo(int $cargoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM aplicaciones_pago WHERE cargo_id = :cargo_id ORDER BY id ASC');
        $stmt->execute(['cargo_id' => $cargoId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = AplicacionPago::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function contarActivasPorCargo(int $cargoId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM aplicaciones_pago WHERE cargo_id = :cargo_id AND estado = "ACTIVA"');
        $stmt->execute(['cargo_id' => $cargoId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Revierte todas las aplicaciones activas vinculadas a un pago y retorna los detalles para desaplicar en los cargos.
     *
     * @return array<array{id: int, cargo_id: int, monto_aplicado: string}>
     */
    public function revertirPorPago(int $pagoId, int $actorId, string $revertidaEn): array
    {
        $stmtSelect = $this->pdo->prepare(
            'SELECT id, cargo_id, monto_aplicado 
             FROM aplicaciones_pago 
             WHERE pago_id = :pago_id AND estado = "ACTIVA" 
             FOR UPDATE'
        );
        $stmtSelect->execute(['pago_id' => $pagoId]);
        $aplicaciones = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($aplicaciones)) {
            $stmtUpdate = $this->pdo->prepare(
                'UPDATE aplicaciones_pago 
                 SET estado = "REVERTIDA", 
                     revertida_en = :revertida_en, 
                     revertida_por_actor_id = :actor_id 
                 WHERE pago_id = :pago_id AND estado = "ACTIVA"'
            );
            $stmtUpdate->execute([
                'revertida_en' => $revertidaEn,
                'actor_id' => $actorId,
                'pago_id' => $pagoId,
            ]);
        }

        return $aplicaciones;
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'APL-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM aplicaciones_pago WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefijo' => $prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo && preg_match('/-(\d{4})$/', (string) $ultimo, $m)) {
            $siguiente = (int) $m[1] + 1;
        } else {
            $siguiente = 1;
        }

        return $prefijo . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }
}
