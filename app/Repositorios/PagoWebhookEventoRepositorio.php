<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\PagoWebhookEvento;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;
use PDOException;

/**
 * Repositorio de persistencia PDO para eventos webhook de pasarelas de pago.
 * Garantiza idempotencia multidimensional a nivel de base de datos mediante
 * índice único (proveedor, proveedor_evento_id).
 */
class PagoWebhookEventoRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?PagoWebhookEvento
    {
        $sql = 'SELECT * FROM pagos_webhooks_eventos WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoWebhookEvento::desdeArray($fila) : null;
    }

    public function buscarPorProveedorYEventoId(string $proveedor, string $proveedorEventoId): ?PagoWebhookEvento
    {
        $sql = 'SELECT * FROM pagos_webhooks_eventos 
                WHERE proveedor = :proveedor AND proveedor_evento_id = :evento_id 
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'proveedor' => $proveedor,
            'evento_id' => $proveedorEventoId,
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? PagoWebhookEvento::desdeArray($fila) : null;
    }

    /**
     * Intenta registrar un evento webhook.
     * Si ya existe un evento idéntico (proveedor + evento_id), retorna el registro existente con es_nuevo = false.
     * Retorna un array con ['id' => int, 'es_nuevo' => bool, 'evento' => PagoWebhookEvento].
     */
    public function registrarOObtener(
        string $proveedor,
        string $proveedorEventoId,
        string $tipoEvento,
        string $payloadRaw,
        ?array $cabeceras = null,
        ?int $transaccionPasarelaId = null
    ): array {
        $existente = $this->buscarPorProveedorYEventoId($proveedor, $proveedorEventoId);
        if ($existente !== null) {
            return [
                'id' => (int) $existente->obtenerId(),
                'es_nuevo' => false,
                'evento' => $existente,
            ];
        }

        $cuerpoHash = hash('sha256', $payloadRaw);

        $sql = 'INSERT INTO pagos_webhooks_eventos (
                    transaccion_pasarela_id,
                    proveedor,
                    proveedor_evento_id,
                    tipo_evento,
                    cuerpo_hash,
                    payload_raw,
                    cabeceras,
                    estado_procesamiento,
                    codigo_http_respuesta
                ) VALUES (
                    :transaccion_id,
                    :proveedor,
                    :proveedor_evento_id,
                    :tipo_evento,
                    :cuerpo_hash,
                    :payload_raw,
                    :cabeceras,
                    :estado_procesamiento,
                    200
                )';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'transaccion_id' => $transaccionPasarelaId,
                'proveedor' => $proveedor,
                'proveedor_evento_id' => $proveedorEventoId,
                'tipo_evento' => $tipoEvento,
                'cuerpo_hash' => $cuerpoHash,
                'payload_raw' => $payloadRaw,
                'cabeceras' => $cabeceras !== null ? json_encode($cabeceras, JSON_UNESCAPED_UNICODE) : null,
                'estado_procesamiento' => PagoWebhookEvento::ESTADO_PROCESADO,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $evento = $this->buscarPorId($id);

            return [
                'id' => $id,
                'es_nuevo' => true,
                'evento' => $evento,
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                $existente = $this->buscarPorProveedorYEventoId($proveedor, $proveedorEventoId);
                if ($existente !== null) {
                    return [
                        'id' => (int) $existente->obtenerId(),
                        'es_nuevo' => false,
                        'evento' => $existente,
                    ];
                }
            }
            throw $e;
        }
    }

    public function marcarProcesado(int $id, ?int $transaccionId = null): bool
    {
        $sql = 'UPDATE pagos_webhooks_eventos 
                SET estado_procesamiento = :estado,
                    transaccion_pasarela_id = COALESCE(:tx_id, transaccion_pasarela_id),
                    procesado_en = NOW(),
                    error_detalle = NULL
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'estado' => PagoWebhookEvento::ESTADO_PROCESADO,
            'tx_id' => $transaccionId,
        ]);
    }

    public function marcarDuplicadoOmitido(int $id): bool
    {
        $sql = 'UPDATE pagos_webhooks_eventos 
                SET estado_procesamiento = :estado,
                    procesado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'estado' => PagoWebhookEvento::ESTADO_DUPLICADO_OMITIDO,
        ]);
    }

    public function marcarError(int $id, string $error, int $codigoHttp = 422): bool
    {
        $sql = 'UPDATE pagos_webhooks_eventos 
                SET estado_procesamiento = :estado,
                    codigo_http_respuesta = :http,
                    error_detalle = :error,
                    procesado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'estado' => PagoWebhookEvento::ESTADO_ERROR_PROCESAMIENTO,
            'http' => $codigoHttp,
            'error' => mb_substr($error, 0, 500),
        ]);
    }
}
