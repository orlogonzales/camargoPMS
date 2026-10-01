<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\PagoTransaccionPasarela;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para transacciones de pasarelas de pago externas.
 * Soporta bloqueos pesimistas SELECT ... FOR UPDATE para garantizar consistencia ACID.
 */
class PagoTransaccionPasarelaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    /**
     * Bloqueo pesimista FOR UPDATE para transacciones ACID de confirmación de pago.
     */
    public function buscarPorIdParaActualizar(int $id): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela WHERE id = :id LIMIT 1 FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    public function buscarPorProveedorYOrdenId(string $proveedor, string $proveedorOrdenId): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela 
                WHERE proveedor = :proveedor AND proveedor_orden_id = :orden_id 
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'proveedor' => $proveedor,
            'orden_id' => $proveedorOrdenId,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    public function buscarPorProveedorYOrdenIdParaActualizar(string $proveedor, string $proveedorOrdenId): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela 
                WHERE proveedor = :proveedor AND proveedor_orden_id = :orden_id 
                LIMIT 1 FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'proveedor' => $proveedor,
            'orden_id' => $proveedorOrdenId,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    public function buscarPorProveedorYTransaccionId(string $proveedor, string $proveedorTransaccionId): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela 
                WHERE proveedor = :proveedor AND proveedor_transaccion_id = :tx_id 
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'proveedor' => $proveedor,
            'tx_id' => $proveedorTransaccionId,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    /**
     * @return PagoTransaccionPasarela[]
     */
    public function buscarPorReservaId(int $reservaId): array
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela 
                WHERE reserva_id = :reserva_id 
                ORDER BY id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reserva_id' => $reservaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn(array $f) => PagoTransaccionPasarela::desdeArray($f), $filas);
    }

    public function buscarUltimaPorReservaId(int $reservaId): ?PagoTransaccionPasarela
    {
        $sql = 'SELECT * FROM pagos_transacciones_pasarela 
                WHERE reserva_id = :reserva_id 
                ORDER BY id DESC 
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reserva_id' => $reservaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoTransaccionPasarela::desdeArray($fila) : null;
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'TRX-' . date('Ym') . '-';
        $sql = "SELECT codigo FROM pagos_transacciones_pasarela WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['prefijo' => $prefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo) {
            $numero = (int) substr((string) $ultimo, strlen($prefijo)) + 1;
        } else {
            $numero = 1;
        }

        return $prefijo . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    public function crear(array $datos): int
    {
        $codigo = $datos['codigo'] ?? $this->generarSiguienteCodigo();
        $actorId = (int) ($datos['actor_id'] ?? 0);
        if ($actorId <= 0) {
            $actorId = (int) ($this->pdo->query("SELECT id FROM actores WHERE codigo = 'PASARELA_CULQI' OR tipo = 'PROVEEDOR_PAGO' LIMIT 1")->fetchColumn() ?: 1);
        }

        $sql = 'INSERT INTO pagos_transacciones_pasarela (
                    codigo,
                    reserva_id,
                    cuenta_folio_id,
                    pago_cuenta_id,
                    proveedor,
                    tipo_operacion,
                    proveedor_orden_id,
                    proveedor_transaccion_id,
                    proveedor_referencia,
                    estado_pago,
                    estado_conciliacion,
                    estado_reembolso,
                    moneda_codigo,
                    monto_esperado,
                    monto_cobrado,
                    monto_reembolsado,
                    metadatos_proveedor,
                    motivo_discrepancia,
                    motivo_reembolso,
                    actor_id
                ) VALUES (
                    :codigo,
                    :reserva_id,
                    :cuenta_folio_id,
                    :pago_cuenta_id,
                    :proveedor,
                    :tipo_operacion,
                    :proveedor_orden_id,
                    :proveedor_transaccion_id,
                    :proveedor_referencia,
                    :estado_pago,
                    :estado_conciliacion,
                    :estado_reembolso,
                    :moneda_codigo,
                    :monto_esperado,
                    :monto_cobrado,
                    :monto_reembolsado,
                    :metadatos_proveedor,
                    :motivo_discrepancia,
                    :motivo_reembolso,
                    :actor_id
                )';

        $metadatos = $datos['metadatos_proveedor'] ?? null;
        if (is_array($metadatos)) {
            $metadatos = json_encode($metadatos, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'codigo' => $codigo,
            'reserva_id' => $datos['reserva_id'],
            'cuenta_folio_id' => $datos['cuenta_folio_id'] ?? null,
            'pago_cuenta_id' => $datos['pago_cuenta_id'] ?? null,
            'proveedor' => $datos['proveedor'],
            'tipo_operacion' => $datos['tipo_operacion'] ?? PagoTransaccionPasarela::OPERACION_ORDEN_CHECKOUT,
            'proveedor_orden_id' => $datos['proveedor_orden_id'] ?? null,
            'proveedor_transaccion_id' => $datos['proveedor_transaccion_id'] ?? null,
            'proveedor_referencia' => $datos['proveedor_referencia'] ?? null,
            'estado_pago' => $datos['estado_pago'] ?? PagoTransaccionPasarela::ESTADO_PAGO_INICIADO,
            'estado_conciliacion' => $datos['estado_conciliacion'] ?? PagoTransaccionPasarela::ESTADO_CONCILIACION_PENDIENTE,
            'estado_reembolso' => $datos['estado_reembolso'] ?? PagoTransaccionPasarela::ESTADO_REEMBOLSO_NO_APLICA,
            'moneda_codigo' => $datos['moneda_codigo'] ?? ($datos['moneda'] ?? 'PEN'),
            'monto_esperado' => $datos['monto_esperado'] ?? ($datos['monto'] ?? '0.00'),
            'monto_cobrado' => $datos['monto_cobrado'] ?? null,
            'monto_reembolsado' => $datos['monto_reembolsado'] ?? '0.00',
            'metadatos_proveedor' => $metadatos,
            'motivo_discrepancia' => $datos['motivo_discrepancia'] ?? null,
            'motivo_reembolso' => $datos['motivo_reembolso'] ?? null,
            'actor_id' => $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $campos = [];
        $params = ['id' => $id];

        $permitidos = [
            'cuenta_folio_id',
            'pago_cuenta_id',
            'proveedor_orden_id',
            'proveedor_transaccion_id',
            'proveedor_referencia',
            'estado_pago',
            'estado_conciliacion',
            'estado_reembolso',
            'moneda_codigo',
            'monto_esperado',
            'monto_cobrado',
            'monto_reembolsado',
            'metadatos_proveedor',
            'motivo_discrepancia',
            'motivo_reembolso',
            'actor_id',
        ];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $valor = $datos[$campo];
                if ($campo === 'metadatos_proveedor' && is_array($valor)) {
                    $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
                }
                $campos[] = "$campo = :$campo";
                $params[$campo] = $valor;
            }
        }

        if (empty($campos)) {
            return false;
        }

        $sql = 'UPDATE pagos_transacciones_pasarela SET ' . implode(', ', $campos) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }
}
