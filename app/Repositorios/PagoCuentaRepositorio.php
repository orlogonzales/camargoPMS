<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\PagoCuenta;
use PDO;

/**
 * Repositorio para la gestión de pagos y cobros a cuentas/folios.
 */
class PagoCuentaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(PagoCuenta $pago): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pagos_cuenta (
                codigo, cuenta_folio_id, metodo_pago_id, monto_total, monto_aplicado, moneda_codigo,
                sesion_caja_id, cuenta_bancaria_id, referencia_operacion, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :cuenta_folio_id, :metodo_pago_id, :monto_total, :monto_aplicado, :moneda_codigo,
                :sesion_caja_id, :cuenta_bancaria_id, :referencia_operacion, :estado, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $pago->obtenerCodigo(),
            'cuenta_folio_id' => $pago->obtenerCuentaFolioId(),
            'metodo_pago_id' => $pago->obtenerMetodoPagoId(),
            'monto_total' => $pago->obtenerMontoTotal(),
            'monto_aplicado' => $pago->obtenerMontoAplicado(),
            'moneda_codigo' => $pago->obtenerMonedaCodigo(),
            'sesion_caja_id' => $pago->obtenerSesionCajaId(),
            'cuenta_bancaria_id' => $pago->obtenerCuentaBancariaId(),
            'referencia_operacion' => $pago->obtenerReferenciaOperacion(),
            'estado' => $pago->obtenerEstado(),
            'creado_por_actor_id' => $pago->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?PagoCuenta
    {
        $sql = 'SELECT * FROM pagos_cuenta WHERE id = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? PagoCuenta::desdeArreglo($fila) : null;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?PagoCuenta
    {
        $sql = 'SELECT * FROM pagos_cuenta WHERE codigo = :codigo LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? PagoCuenta::desdeArreglo($fila) : null;
    }

    /**
     * @return array<PagoCuenta>
     */
    public function listarPorFolio(int $folioId, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM pagos_cuenta WHERE cuenta_folio_id = :folio_id';
        $params = ['folio_id' => $folioId];
        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = PagoCuenta::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function actualizarMontoAplicado(int $pagoId, string $deltaAplicado): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pagos_cuenta 
             SET monto_aplicado = monto_aplicado + :delta,
                 actualizado_en = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['delta' => $deltaAplicado, 'id' => $pagoId]);
    }

    public function reversar(int $pagoId, string $motivo, int $actorId, string $reversadoEn): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pagos_cuenta 
             SET estado = "REVERSADO",
                 motivo_reverso = :motivo,
                 reversado_por_actor_id = :actor_id,
                 reversado_en = :reversado_en,
                 actualizado_en = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'motivo' => $motivo,
            'actor_id' => $actorId,
            'reversado_en' => $reversadoEn,
            'id' => $pagoId,
        ]);
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'PAG-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM pagos_cuenta WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
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
