<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\DevolucionCuenta;
use PDO;

/**
 * Repositorio para la gestión de devoluciones y reembolsos de cuentas.
 */
class DevolucionCuentaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(DevolucionCuenta $devolucion): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO devoluciones_cuenta (
                codigo, cuenta_folio_id, pago_origen_id, metodo_pago_id, sesion_caja_id, cuenta_bancaria_id,
                monto, moneda_codigo, motivo, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :cuenta_folio_id, :pago_origen_id, :metodo_pago_id, :sesion_caja_id, :cuenta_bancaria_id,
                :monto, :moneda_codigo, :motivo, :estado, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $devolucion->obtenerCodigo(),
            'cuenta_folio_id' => $devolucion->obtenerCuentaFolioId(),
            'pago_origen_id' => $devolucion->obtenerPagoOrigenId(),
            'metodo_pago_id' => $devolucion->obtenerMetodoPagoId(),
            'sesion_caja_id' => $devolucion->obtenerSesionCajaId(),
            'cuenta_bancaria_id' => $devolucion->obtenerCuentaBancariaId(),
            'monto' => $devolucion->obtenerMonto(),
            'moneda_codigo' => $devolucion->obtenerMonedaCodigo(),
            'motivo' => $devolucion->obtenerMotivo(),
            'estado' => $devolucion->obtenerEstado(),
            'creado_por_actor_id' => $devolucion->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id): ?DevolucionCuenta
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devoluciones_cuenta WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? DevolucionCuenta::desdeArreglo($fila) : null;
    }

    /**
     * @return array<DevolucionCuenta>
     */
    public function listarPorFolio(int $folioId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devoluciones_cuenta WHERE cuenta_folio_id = :folio_id ORDER BY id ASC');
        $stmt->execute(['folio_id' => $folioId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = DevolucionCuenta::desdeArreglo($fila);
        }
        return $resultado;
    }

    /**
     * @return array<DevolucionCuenta>
     */
    public function listarPorPago(int $pagoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devoluciones_cuenta WHERE pago_origen_id = :pago_id ORDER BY id ASC');
        $stmt->execute(['pago_id' => $pagoId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = DevolucionCuenta::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function sumarDevolucionesConfirmadasPorPago(int $pagoId): string
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(monto), "0.00") FROM devoluciones_cuenta WHERE pago_origen_id = :pago_id AND estado = "CONFIRMADA"');
        $stmt->execute(['pago_id' => $pagoId]);
        return (string) $stmt->fetchColumn();
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'DEV-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM devoluciones_cuenta WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
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
