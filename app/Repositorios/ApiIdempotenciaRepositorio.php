<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ApiIdempotencia;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;
use PDOException;

/**
 * Repositorio de persistencia PDO para Idempotencia del perímetro API.
 * Gestiona el ciclo de vida y exclusión mutua de peticiones idempotentes.
 */
class ApiIdempotenciaRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscar(int $apiClienteId, string $ruta, string $clave): ?ApiIdempotencia
    {
        $sql = 'SELECT * FROM api_idempotencia 
                WHERE api_cliente_id = :api_cliente_id 
                  AND ruta = :ruta 
                  AND clave_idempotencia = :clave 
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'api_cliente_id' => $apiClienteId,
            'ruta' => $ruta,
            'clave' => $clave,
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function buscarPorId(int $id): ?ApiIdempotencia
    {
        $sql = 'SELECT * FROM api_idempotencia WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    /**
     * Intenta registrar una nueva operación en estado PROCESANDO con bloqueo temporal.
     * Retorna el ID creado o null si ya existe un registro activo con la misma clave.
     */
    public function registrarInicio(
        int $apiClienteId,
        string $ruta,
        string $metodo,
        string $clave,
        string $cuerpoHash,
        int $ttlSegundos = 60
    ): ?int {
        $bloqueadoHasta = date('Y-m-d H:i:s', time() + $ttlSegundos);

        $sql = 'INSERT INTO api_idempotencia (
                    api_cliente_id, clave_idempotencia, ruta, metodo, cuerpo_hash,
                    estado, bloqueado_hasta, creado_en, actualizado_en
                ) VALUES (
                    :api_cliente_id, :clave, :ruta, :metodo, :cuerpo_hash,
                    :estado, :bloqueado_hasta, NOW(), NOW()
                )';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'api_cliente_id' => $apiClienteId,
                'clave' => $clave,
                'ruta' => $ruta,
                'metodo' => strtoupper($metodo),
                'cuerpo_hash' => $cuerpoHash,
                'estado' => ApiIdempotencia::ESTADO_PROCESANDO,
                'bloqueado_hasta' => $bloqueadoHasta,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            // Error 23000 / 1062 es clave duplicada
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Marca la operación como completada guardando el resultado para repetición determinista.
     *
     * @param array<string, string> $cabeceras
     */
    public function completar(int $id, int $codigoHttp, string $respuestaJson, array $cabeceras = []): bool
    {
        $sql = 'UPDATE api_idempotencia SET
                    estado = :estado,
                    codigo_http = :codigo_http,
                    respuesta_json = :respuesta_json,
                    cabeceras_json = :cabeceras_json,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado' => ApiIdempotencia::ESTADO_COMPLETADO,
            'codigo_http' => $codigoHttp,
            'respuesta_json' => $respuestaJson,
            'cabeceras_json' => !empty($cabeceras) ? json_encode($cabeceras, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * Marca la operación como fallida o la elimina para permitir reintentos limpios.
     */
    public function marcarError(int $id): bool
    {
        $sql = 'UPDATE api_idempotencia SET
                    estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'estado' => ApiIdempotencia::ESTADO_ERROR,
        ]);
    }

    public function eliminar(int $id): bool
    {
        $sql = 'DELETE FROM api_idempotencia WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Limpia registros de idempotencia antiguos para evitar crecimiento indefinido.
     */
    public function limpiarAntiguos(int $dias = 7): int
    {
        $sql = 'DELETE FROM api_idempotencia WHERE creado_en < DATE_SUB(NOW(), INTERVAL :dias DAY)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dias' => $dias]);
        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $fila
     */
    public function mapearFila(array $fila): ApiIdempotencia
    {
        return new ApiIdempotencia(
            id: isset($fila['id']) ? (int) $fila['id'] : null,
            apiClienteId: (int) $fila['api_cliente_id'],
            claveIdempotencia: (string) $fila['clave_idempotencia'],
            ruta: (string) $fila['ruta'],
            metodo: (string) $fila['metodo'],
            cuerpoHash: (string) $fila['cuerpo_hash'],
            estado: (string) $fila['estado'],
            codigoHttp: isset($fila['codigo_http']) && $fila['codigo_http'] !== null ? (int) $fila['codigo_http'] : null,
            cabecerasJson: isset($fila['cabeceras_json']) ? (string) $fila['cabeceras_json'] : null,
            respuestaJson: isset($fila['respuesta_json']) ? (string) $fila['respuesta_json'] : null,
            bloqueadoHasta: (string) $fila['bloqueado_hasta'],
            creadoEn: isset($fila['creado_en']) ? (string) $fila['creado_en'] : null,
            actualizadoEn: isset($fila['actualizado_en']) ? (string) $fila['actualizado_en'] : null
        );
    }
}
