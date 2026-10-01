<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CargoCuenta;
use PDO;

/**
 * Repositorio para la gestión y persistencia de cargos a cuentas/folios.
 */
class CargoCuentaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(CargoCuenta $cargo): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cargos_cuenta (
                codigo, cuenta_folio_id, cargo_padre_id, origen_tipo, origen_id, estadia_id, concepto,
                cantidad, precio_unitario, subtotal, impuesto_total, total, monto_aplicado_acumulado,
                moneda_codigo, estado, devengado_en, creado_por_actor_id, creado_en
            ) VALUES (
                :codigo, :cuenta_folio_id, :cargo_padre_id, :origen_tipo, :origen_id, :estadia_id, :concepto,
                :cantidad, :precio_unitario, :subtotal, :impuesto_total, :total, :monto_aplicado_acumulado,
                :moneda_codigo, :estado, :devengado_en, :creado_por_actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $cargo->obtenerCodigo(),
            'cuenta_folio_id' => $cargo->obtenerCuentaFolioId(),
            'cargo_padre_id' => $cargo->obtenerCargoPadreId(),
            'origen_tipo' => $cargo->obtenerOrigenTipo(),
            'origen_id' => $cargo->obtenerOrigenId(),
            'estadia_id' => $cargo->obtenerEstadiaId(),
            'concepto' => $cargo->obtenerConcepto(),
            'cantidad' => $cargo->obtenerCantidad(),
            'precio_unitario' => $cargo->obtenerPrecioUnitario(),
            'subtotal' => $cargo->obtenerSubtotal(),
            'impuesto_total' => $cargo->obtenerImpuestoTotal(),
            'total' => $cargo->obtenerTotal(),
            'monto_aplicado_acumulado' => $cargo->obtenerMontoAplicadoAcumulado(),
            'moneda_codigo' => $cargo->obtenerMonedaCodigo(),
            'estado' => $cargo->obtenerEstado(),
            'devengado_en' => $cargo->obtenerDevengadoEn(),
            'creado_por_actor_id' => $cargo->obtenerCreadoPorActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<CargoCuenta>
     */
    public function listarHijos(int $cargoPadreId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cargos_cuenta WHERE cargo_padre_id = :padre_id ORDER BY id ASC');
        $stmt->execute(['padre_id' => $cargoPadreId]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CargoCuenta::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function obtenerPorId(int $id, bool $bloquear = false): ?CargoCuenta
    {
        $sql = 'SELECT * FROM cargos_cuenta WHERE id = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CargoCuenta::desdeArreglo($fila) : null;
    }

    public function obtenerPorCodigo(string $codigo, bool $bloquear = false): ?CargoCuenta
    {
        $sql = 'SELECT * FROM cargos_cuenta WHERE codigo = :codigo LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CargoCuenta::desdeArreglo($fila) : null;
    }

    public function obtenerPorOrigen(string $origenTipo, int $origenId, bool $bloquear = false): ?CargoCuenta
    {
        $sql = 'SELECT * FROM cargos_cuenta WHERE origen_tipo = :origen_tipo AND origen_id = :origen_id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['origen_tipo' => $origenTipo, 'origen_id' => $origenId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CargoCuenta::desdeArreglo($fila) : null;
    }

    /**
     * @return array<CargoCuenta>
     */
    public function listarPorFolio(int $folioId, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM cargos_cuenta WHERE cuenta_folio_id = :folio_id';
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
            $resultado[] = CargoCuenta::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function actualizarMontoAplicado(int $cargoId, string $deltaAplicado): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cargos_cuenta 
             SET monto_aplicado_acumulado = monto_aplicado_acumulado + :delta,
                 actualizado_en = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['delta' => $deltaAplicado, 'id' => $cargoId]);
    }

    public function actualizarEstado(
        int $cargoId,
        string $nuevoEstado,
        ?string $devengadoEn = null,
        ?string $motivoAnulacion = null,
        ?int $anuladoPorActorId = null,
        ?string $anuladoEn = null
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE cargos_cuenta 
             SET estado = :estado,
                 devengado_en = COALESCE(:devengado_en, devengado_en),
                 motivo_anulacion = COALESCE(:motivo_anulacion, motivo_anulacion),
                 anulado_por_actor_id = COALESCE(:anulado_por_actor_id, anulado_por_actor_id),
                 anulado_en = COALESCE(:anulado_en, anulado_en),
                 actualizado_en = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'estado' => $nuevoEstado,
            'devengado_en' => $devengadoEn,
            'motivo_anulacion' => $motivoAnulacion,
            'anulado_por_actor_id' => $anuladoPorActorId,
            'anulado_en' => $anuladoEn,
            'id' => $cargoId,
        ]);
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'CRG-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM cargos_cuenta WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
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
