<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CuentaFolio;
use PDO;

/**
 * Repositorio para la persistencia y consulta de cuentas/folios financieros de reservas.
 */
class CuentaFolioRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(CuentaFolio $folio): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cuentas_folios (
                codigo, reserva_id, arrendamiento_id, persona_titular_id,
                es_principal, etiqueta, folio_padre_id,
                moneda_codigo, estado, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :reserva_id, :arrendamiento_id, :persona_titular_id,
                :es_principal, :etiqueta, :folio_padre_id,
                :moneda_codigo, :estado, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $folio->obtenerCodigo(),
            'reserva_id' => $folio->obtenerReservaId(),
            'arrendamiento_id' => $folio->obtenerArrendamientoId(),
            'persona_titular_id' => $folio->obtenerPersonaTitularId(),
            'es_principal' => $folio->esPrincipal() ? 1 : 0,
            'etiqueta' => $folio->obtenerEtiqueta(),
            'folio_padre_id' => $folio->obtenerFolioPadreId(),
            'moneda_codigo' => $folio->obtenerMonedaCodigo(),
            'estado' => $folio->obtenerEstado(),
            'creado_por_actor_id' => $folio->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?CuentaFolio
    {
        $sql = 'SELECT * FROM cuentas_folios WHERE id = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolio::desdeArreglo($fila) : null;
    }

    public function obtenerPorReservaId(int $reservaId, bool $bloquear = false): ?CuentaFolio
    {
        $sql = 'SELECT * FROM cuentas_folios WHERE reserva_id = :reserva_id ORDER BY es_principal DESC, id ASC LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reserva_id' => $reservaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolio::desdeArreglo($fila) : null;
    }

    public function obtenerPrincipalPorReservaId(int $reservaId, bool $bloquear = false): ?CuentaFolio
    {
        $sql = 'SELECT * FROM cuentas_folios WHERE reserva_id = :reserva_id AND es_principal = 1 AND estado != "ANULADA" LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['reserva_id' => $reservaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolio::desdeArreglo($fila) : null;
    }

    /**
     * @return array<CuentaFolio>
     */
    public function listarPorReservaId(int $reservaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuentas_folios WHERE reserva_id = :reserva_id ORDER BY es_principal DESC, id ASC');
        $stmt->execute(['reserva_id' => $reservaId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CuentaFolio::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function obtenerPorArrendamientoId(int $arrendamientoId, bool $bloquear = false): ?CuentaFolio
    {
        $sql = 'SELECT * FROM cuentas_folios WHERE arrendamiento_id = :arrendamiento_id ORDER BY es_principal DESC, id ASC LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolio::desdeArreglo($fila) : null;
    }

    public function obtenerPrincipalPorArrendamientoId(int $arrendamientoId, bool $bloquear = false): ?CuentaFolio
    {
        $sql = 'SELECT * FROM cuentas_folios WHERE arrendamiento_id = :arrendamiento_id AND es_principal = 1 AND estado != "ANULADA" LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolio::desdeArreglo($fila) : null;
    }

    /**
     * @return array<CuentaFolio>
     */
    public function listarPorArrendamientoId(int $arrendamientoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuentas_folios WHERE arrendamiento_id = :arrendamiento_id ORDER BY es_principal DESC, id ASC');
        $stmt->execute(['arrendamiento_id' => $arrendamientoId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CuentaFolio::desdeArreglo($fila);
        }
        return $resultado;
    }

    /**
     * @return array<CuentaFolio>
     */
    public function listarHijos(int $folioPadreId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuentas_folios WHERE folio_padre_id = :padre_id ORDER BY id ASC');
        $stmt->execute(['padre_id' => $folioPadreId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CuentaFolio::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?CuentaFolio
    {
        $sql = 'SELECT * FROM cuentas_folios WHERE codigo = :codigo LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolio::desdeArreglo($fila) : null;
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->pdo->prepare('UPDATE cuentas_folios SET estado = :estado, actualizado_en = NOW() WHERE id = :id');
        $stmt->execute(['estado' => $estado, 'id' => $id]);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<array<string, mixed>>
     */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT 
                    cf.*,
                    COALESCE(r.codigo, arr.codigo) AS reserva_codigo,
                    COALESCE(r.codigo, "") AS solo_reserva_codigo,
                    COALESCE(arr.codigo, "") AS arrendamiento_codigo,
                    COALESCE(r.estado, arr.estado) AS reserva_estado,
                    COALESCE(r.fecha_entrada, arr.fecha_inicio) AS fecha_entrada,
                    COALESCE(r.fecha_salida, arr.fecha_fin) AS fecha_salida,
                    TRIM(CONCAT(p.nombres, " ", p.apellido_paterno, " ", COALESCE(p.apellido_materno, ""))) AS titular_nombre_completo,
                    COALESCE((SELECT pd.numero_documento FROM personas_documentos pd WHERE pd.persona_id = p.id LIMIT 1), "") AS titular_documento,
                    COALESCE((SELECT SUM(c.total) FROM cargos_cuenta c WHERE c.cuenta_folio_id = cf.id AND c.estado = "DEVENGADO"), 0.00) AS total_cargos_devengados,
                    COALESCE((SELECT SUM(c.total) FROM cargos_cuenta c WHERE c.cuenta_folio_id = cf.id AND c.estado = "PROVISIONAL"), 0.00) AS total_cargos_provisionales,
                    COALESCE((SELECT SUM(pg.monto_total) FROM pagos_cuenta pg WHERE pg.cuenta_folio_id = cf.id AND pg.estado = "CONFIRMADO"), 0.00) AS total_pagos_confirmados,
                    COALESCE((SELECT SUM(pg.monto_aplicado) FROM pagos_cuenta pg WHERE pg.cuenta_folio_id = cf.id AND pg.estado = "CONFIRMADO"), 0.00) AS total_pagos_aplicados,
                    COALESCE((SELECT SUM(d.monto) FROM devoluciones_cuenta d WHERE d.cuenta_folio_id = cf.id AND d.estado = "CONFIRMADA"), 0.00) AS total_devoluciones_confirmadas
                FROM cuentas_folios cf
                LEFT JOIN reservas r ON r.id = cf.reserva_id
                LEFT JOIN arrendamientos arr ON arr.id = cf.arrendamiento_id
                INNER JOIN personas p ON p.id = cf.persona_titular_id
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND cf.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['reserva_id'])) {
            $sql .= ' AND cf.reserva_id = :reserva_id';
            $params['reserva_id'] = (int) $filtros['reserva_id'];
        }

        if (!empty($filtros['arrendamiento_id'])) {
            $sql .= ' AND cf.arrendamiento_id = :arrendamiento_id';
            $params['arrendamiento_id'] = (int) $filtros['arrendamiento_id'];
        }

        if (!empty($filtros['q'])) {
            $sql .= ' AND (cf.codigo LIKE :q OR r.codigo LIKE :q OR arr.codigo LIKE :q OR p.nombres LIKE :q OR p.apellido_paterno LIKE :q)';
            $params['q'] = '%' . $filtros['q'] . '%';
        }

        $sql .= ' ORDER BY cf.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $devengado = (string) $f['total_cargos_devengados'];
            $aplicado = (string) $f['total_pagos_aplicados'];
            $pagado = (string) $f['total_pagos_confirmados'];
            $saldoPendiente = bcsub($devengado, $aplicado, 2);
            $disponible = bcsub($pagado, $aplicado, 2);

            $f['total_cargos_devengados'] = bcadd($devengado, '0.00', 2);
            $f['total_cargos_provisionales'] = bcadd((string) $f['total_cargos_provisionales'], '0.00', 2);
            $f['total_pagos_confirmados'] = bcadd($pagado, '0.00', 2);
            $f['total_pagos_aplicados'] = bcadd($aplicado, '0.00', 2);
            $f['total_devoluciones_confirmadas'] = bcadd((string) $f['total_devoluciones_confirmadas'], '0.00', 2);
            $f['saldo_pendiente'] = bccomp($saldoPendiente, '0.00', 2) > 0 ? $saldoPendiente : '0.00';
            $f['saldo_disponible'] = bccomp($disponible, '0.00', 2) > 0 ? $disponible : '0.00';

            $resultado[] = $f;
        }

        return $resultado;
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'FOL-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM cuentas_folios WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
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

