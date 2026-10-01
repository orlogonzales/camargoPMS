<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\CuentaFolioTransferenciaCargo;
use PDO;

/**
 * Repositorio para la persistencia y auditoría inmutable de transferencias
 * y divisiones (splits) de cargos entre folios de Camargo PMS.
 */
class CuentaFolioTransferenciaRepositorio
{
    public function __construct(private PDO $pdo)
    {
    }

    public function crear(CuentaFolioTransferenciaCargo $t): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cuenta_folio_transferencias_cargos (
                codigo, cargo_origen_id, cargo_destino_id,
                cuenta_folio_origen_id, cuenta_folio_destino_id,
                monto_transferido, tipo_operacion, motivo,
                actor_id, transferido_en
            ) VALUES (
                :codigo, :cargo_origen_id, :cargo_destino_id,
                :cuenta_folio_origen_id, :cuenta_folio_destino_id,
                :monto_transferido, :tipo_operacion, :motivo,
                :actor_id, NOW()
            )'
        );
        $stmt->execute([
            'codigo' => $t->obtenerCodigo(),
            'cargo_origen_id' => $t->obtenerCargoOrigenId(),
            'cargo_destino_id' => $t->obtenerCargoDestinoId(),
            'cuenta_folio_origen_id' => $t->obtenerCuentaFolioOrigenId(),
            'cuenta_folio_destino_id' => $t->obtenerCuentaFolioDestinoId(),
            'monto_transferido' => $t->obtenerMontoTransferido(),
            'tipo_operacion' => $t->obtenerTipoOperacion(),
            'motivo' => $t->obtenerMotivo(),
            'actor_id' => $t->obtenerActorId(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id): ?CuentaFolioTransferenciaCargo
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuenta_folio_transferencias_cargos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolioTransferenciaCargo::desdeArreglo($fila) : null;
    }

    public function obtenerPorCodigo(string $codigo): ?CuentaFolioTransferenciaCargo
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cuenta_folio_transferencias_cargos WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? CuentaFolioTransferenciaCargo::desdeArreglo($fila) : null;
    }

    /**
     * @return array<CuentaFolioTransferenciaCargo>
     */
    public function listarPorFolio(int $folioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cuenta_folio_transferencias_cargos'
            . ' WHERE cuenta_folio_origen_id = :folio_origen OR cuenta_folio_destino_id = :folio_destino'
            . ' ORDER BY id DESC'
        );
        $stmt->execute([
            'folio_origen' => $folioId,
            'folio_destino' => $folioId,
        ]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CuentaFolioTransferenciaCargo::desdeArreglo($fila);
        }
        return $resultado;
    }

    /**
     * @return array<CuentaFolioTransferenciaCargo>
     */
    public function listarPorCargo(int $cargoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cuenta_folio_transferencias_cargos'
            . ' WHERE cargo_origen_id = :cargo_origen OR cargo_destino_id = :cargo_destino'
            . ' ORDER BY id DESC'
        );
        $stmt->execute([
            'cargo_origen' => $cargoId,
            'cargo_destino' => $cargoId,
        ]);
        $resultado = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[] = CuentaFolioTransferenciaCargo::desdeArreglo($fila);
        }
        return $resultado;
    }

    public function generarSiguienteCodigo(): string
    {
        $prefijo = 'TRC-' . date('Ymd') . '-';
        $stmt = $this->pdo->prepare(
            'SELECT codigo FROM cuenta_folio_transferencias_cargos WHERE codigo LIKE :prefijo ORDER BY id DESC LIMIT 1'
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
