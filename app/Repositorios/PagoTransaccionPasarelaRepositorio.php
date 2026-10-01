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

    /**
     * Lista transacciones con filtros dinámicos, ordenamiento y paginación para el monitor Alina.
     *
     * @param array<string, mixed> $filtros
     * @param int $pagina
     * @param int $porPagina
     * @return array<int, array<string, mixed>>
     */
    public function listarConFiltros(array $filtros = [], int $pagina = 1, int $porPagina = 20): array
    {
        $condiciones = ['1=1'];
        $params = [];

        $this->aplicarCondicionesFiltro($condiciones, $params, $filtros);

        $offset = max(0, ($pagina - 1) * $porPagina);
        $limit = max(1, min(100, $porPagina));

        $sql = 'SELECT t.*, 
                       t.codigo AS codigo_transaccion,
                       t.proveedor_transaccion_id AS transaccion_id_externo,
                       GREATEST(0.00, COALESCE(t.monto_cobrado, 0) - t.monto_reembolsado) AS saldo_reembolsable,
                       CASE WHEN t.estado_conciliacion LIKE \'DISCREPANCIA_%\' THEN t.estado_conciliacion ELSE NULL END AS subtipo_discrepancia,
                       r.codigo AS reserva_codigo, 
                       r.estado AS reserva_estado,
                       r.expira_en AS reserva_expira_en,
                       f.codigo AS folio_codigo,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(t.metadatos_proveedor, \'$.client_name\')),
                           JSON_UNQUOTE(JSON_EXTRACT(t.metadatos_proveedor, \'$.pagador_nombre\')),
                           TRIM(CONCAT(COALESCE(p.nombres, \'\'), \' \', COALESCE(p.apellido_paterno, \'\')))
                       ) AS pagador_nombre,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(t.metadatos_proveedor, \'$.client_email\')),
                           JSON_UNQUOTE(JSON_EXTRACT(t.metadatos_proveedor, \'$.email\')),
                           JSON_UNQUOTE(JSON_EXTRACT(t.metadatos_proveedor, \'$.pagador_email\'))
                       ) AS pagador_email
                FROM pagos_transacciones_pasarela t
                LEFT JOIN reservas r ON t.reserva_id = r.id
                LEFT JOIN personas p ON r.persona_titular_id = p.id
                LEFT JOIN cuentas_folios f ON t.cuenta_folio_id = f.id
                WHERE ' . implode(' AND ', $condiciones) . '
                ORDER BY t.id DESC
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta total de transacciones que coinciden con los filtros aplicados.
     *
     * @param array<string, mixed> $filtros
     * @return int
     */
    public function contarConFiltros(array $filtros = []): int
    {
        $condiciones = ['1=1'];
        $params = [];

        $this->aplicarCondicionesFiltro($condiciones, $params, $filtros);

        $sql = 'SELECT COUNT(*) 
                FROM pagos_transacciones_pasarela t
                LEFT JOIN reservas r ON t.reserva_id = r.id
                LEFT JOIN personas p ON r.persona_titular_id = p.id
                WHERE ' . implode(' AND ', $condiciones);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene métricas agregadas operativas (KPIs) del monitor de pasarelas.
     *
     * @return array<string, mixed>
     */
    public function obtenerMetricasKpi(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total_transacciones,
                    COALESCE(SUM(CASE WHEN estado_pago = 'APROBADO' THEN 1 ELSE 0 END), 0) AS total_aprobadas,
                    COALESCE(SUM(CASE WHEN estado_pago = 'APROBADO' THEN COALESCE(monto_cobrado, monto_esperado, 0) ELSE 0 END), 0.00) AS monto_aprobado,
                    COALESCE(SUM(CASE WHEN estado_pago = 'APROBADO' AND estado_conciliacion != 'CONCILIADO' THEN 1 ELSE 0 END), 0) AS pendientes_conciliacion,
                    COALESCE(SUM(CASE WHEN estado_conciliacion = 'DISCREPANCIA_HOLD_EXPIRADO' THEN 1 ELSE 0 END), 0) AS discrepancias_hold_expirado,
                    COALESCE(SUM(CASE WHEN estado_conciliacion = 'DISCREPANCIA_MONTO' THEN 1 ELSE 0 END), 0) AS discrepancias_monto,
                    COALESCE(SUM(CASE WHEN estado_reembolso IN ('PENDIENTE', 'PROCESANDO') THEN 1 ELSE 0 END), 0) AS reembolsos_pendientes,
                    COALESCE(SUM(monto_reembolsado), 0.00) AS total_reembolsado
                FROM pagos_transacciones_pasarela";

        $stmt = $this->pdo->query($sql);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_transacciones' => (int) ($kpis['total_transacciones'] ?? 0),
            'total_aprobadas' => (int) ($kpis['total_aprobadas'] ?? 0),
            'monto_aprobado' => number_format((float) ($kpis['monto_aprobado'] ?? 0), 2, '.', ''),
            'pendientes_conciliacion' => (int) ($kpis['pendientes_conciliacion'] ?? 0),
            'discrepancias_hold_expirado' => (int) ($kpis['discrepancias_hold_expirado'] ?? 0),
            'discrepancias_monto' => (int) ($kpis['discrepancias_monto'] ?? 0),
            'reembolsos_pendientes' => (int) ($kpis['reembolsos_pendientes'] ?? 0),
            'total_reembolsado' => number_format((float) ($kpis['total_reembolsado'] ?? 0), 2, '.', ''),
        ];
    }

    /**
     * Aplica condiciones WHERE parametrizadas a partir del arreglo de filtros.
     *
     * @param list<string> $condiciones
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filtros
     */
    private function aplicarCondicionesFiltro(array &$condiciones, array &$params, array $filtros): void
    {
        if (!empty($filtros['proveedor'])) {
            $condiciones[] = 't.proveedor = :proveedor';
            $params['proveedor'] = trim((string) $filtros['proveedor']);
        }

        if (!empty($filtros['estado_pago'])) {
            $condiciones[] = 't.estado_pago = :estado_pago';
            $params['estado_pago'] = trim((string) $filtros['estado_pago']);
        }

        if (!empty($filtros['estado_conciliacion'])) {
            $condiciones[] = 't.estado_conciliacion = :estado_conciliacion';
            $params['estado_conciliacion'] = trim((string) $filtros['estado_conciliacion']);
        }

        if (!empty($filtros['estado_reembolso'])) {
            $condiciones[] = 't.estado_reembolso = :estado_reembolso';
            $params['estado_reembolso'] = trim((string) $filtros['estado_reembolso']);
        }

        if (!empty($filtros['fecha_desde'])) {
            $condiciones[] = 'DATE(t.creado_en) >= :fecha_desde';
            $params['fecha_desde'] = trim((string) $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $condiciones[] = 'DATE(t.creado_en) <= :fecha_hasta';
            $params['fecha_hasta'] = trim((string) $filtros['fecha_hasta']);
        }

        $terminoBusqueda = !empty($filtros['busqueda']) ? $filtros['busqueda'] : (!empty($filtros['buscar']) ? $filtros['buscar'] : null);
        if (!empty($terminoBusqueda)) {
            $busqueda = '%' . trim((string) $terminoBusqueda) . '%';
            $condiciones[] = '(t.codigo LIKE :busq1 OR r.codigo LIKE :busq2 OR t.proveedor_orden_id LIKE :busq3 OR t.proveedor_transaccion_id LIKE :busq4 OR t.proveedor_referencia LIKE :busq5 OR p.nombres LIKE :busq6 OR p.apellido_paterno LIKE :busq7)';
            $params['busq1'] = $busqueda;
            $params['busq2'] = $busqueda;
            $params['busq3'] = $busqueda;
            $params['busq4'] = $busqueda;
            $params['busq5'] = $busqueda;
            $params['busq6'] = $busqueda;
            $params['busq7'] = $busqueda;
        }
    }
}
