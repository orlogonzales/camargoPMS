<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ApiClient;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para Clientes API.
 */
class ApiClientRepositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
    }

    public function buscarPorId(int $id): ?ApiClient
    {
        $sql = 'SELECT * FROM api_clientes WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function buscarPorCodigo(string $codigo): ?ApiClient
    {
        $sql = 'SELECT * FROM api_clientes WHERE codigo = :codigo LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => strtoupper(trim($codigo))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function buscarPorActorId(int $actorId): ?ApiClient
    {
        $sql = 'SELECT * FROM api_clientes WHERE actor_id = :actor_id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['actor_id' => $actorId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->mapearFila($fila) : null;
    }

    public function insertar(ApiClient $cliente): int
    {
        $sql = 'INSERT INTO api_clientes (
                    actor_id, codigo, nombre, descripcion, contacto_email,
                    ips_permitidas, limite_peticiones_minuto, estado, creado_en
                ) VALUES (
                    :actor_id, :codigo, :nombre, :descripcion, :contacto_email,
                    :ips_permitidas, :limite_peticiones_minuto, :estado, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'actor_id' => $cliente->obtenerActorId(),
            'codigo' => $cliente->obtenerCodigo(),
            'nombre' => $cliente->obtenerNombre(),
            'descripcion' => $cliente->obtenerDescripcion(),
            'contacto_email' => $cliente->obtenerContactoEmail(),
            'ips_permitidas' => $cliente->obtenerIpsPermitidas(),
            'limite_peticiones_minuto' => $cliente->obtenerLimitePeticionesMinuto(),
            'estado' => $cliente->obtenerEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizar(ApiClient $cliente): bool
    {
        $sql = 'UPDATE api_clientes SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    contacto_email = :contacto_email,
                    ips_permitidas = :ips_permitidas,
                    limite_peticiones_minuto = :limite_peticiones_minuto,
                    estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $cliente->obtenerId(),
            'nombre' => $cliente->obtenerNombre(),
            'descripcion' => $cliente->obtenerDescripcion(),
            'contacto_email' => $cliente->obtenerContactoEmail(),
            'ips_permitidas' => $cliente->obtenerIpsPermitidas(),
            'limite_peticiones_minuto' => $cliente->obtenerLimitePeticionesMinuto(),
            'estado' => $cliente->obtenerEstado(),
        ]);
    }

    /**
     * @return array<int, ApiClient>
     */
    public function listar(?string $estado = null): array
    {
        $sql = 'SELECT * FROM api_clientes';
        $params = [];

        if ($estado !== null) {
            $sql .= ' WHERE estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapearFila'], $filas);
    }

    /**
     * @param array<string, mixed> $fila
     */
    public function mapearFila(array $fila): ApiClient
    {
        return ApiClient::desdeArreglo($fila);
    }
}
