<?php

declare(strict_types=1);

namespace CamargoPMS\Repositorios;

use CamargoPMS\Modelos\ApiCredencial;
use CamargoPMS\Nucleo\BaseDatos;
use PDO;

/**
 * Repositorio de persistencia PDO para Credenciales Técnicas de Clientes API.
 */
class ApiCredencialRepositorio
{
    private PDO $pdo;
    private ApiScopeRepositorio $scopeRepo;

    public function __construct(?PDO $pdo = null, ?ApiScopeRepositorio $scopeRepo = null)
    {
        $this->pdo = $pdo ?? BaseDatos::conexion();
        $this->scopeRepo = $scopeRepo ?? new ApiScopeRepositorio($this->pdo);
    }

    public function buscarPorId(int $id): ?ApiCredencial
    {
        $sql = 'SELECT * FROM api_credenciales WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $scopes = $this->scopeRepo->listarCodigosPorCredencialId($id);
        return $this->mapearFila($fila, $scopes);
    }

    public function buscarPorTokenHash(string $tokenHash): ?ApiCredencial
    {
        $sql = 'SELECT * FROM api_credenciales WHERE token_hash = :hash LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['hash' => $tokenHash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $scopes = $this->scopeRepo->listarCodigosPorCredencialId((int) $fila['id']);
        return $this->mapearFila($fila, $scopes);
    }

    public function buscarPorIdentificadorPublico(string $identificador): ?ApiCredencial
    {
        $sql = 'SELECT * FROM api_credenciales WHERE identificador_publico = :identificador LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['identificador' => trim($identificador)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $scopes = $this->scopeRepo->listarCodigosPorCredencialId((int) $fila['id']);
        return $this->mapearFila($fila, $scopes);
    }

    /**
     * @param array<int, int> $scopeIds
     */
    public function insertar(ApiCredencial $credencial, array $scopeIds = []): int
    {
        $sql = 'INSERT INTO api_credenciales (
                    api_cliente_id, identificador_publico, token_hash, token_prefijo,
                    nombre, estado, expira_en, creado_en
                ) VALUES (
                    :api_cliente_id, :identificador_publico, :token_hash, :token_prefijo,
                    :nombre, :estado, :expira_en, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'api_cliente_id' => $credencial->obtenerApiClientId(),
            'identificador_publico' => $credencial->obtenerIdentificadorPublico(),
            'token_hash' => $credencial->obtenerTokenHash(),
            'token_prefijo' => $credencial->obtenerTokenPrefijo(),
            'nombre' => $credencial->obtenerNombre(),
            'estado' => $credencial->obtenerEstado(),
            'expira_en' => $credencial->obtenerExpiraEn(),
        ]);

        $credencialId = (int) $this->pdo->lastInsertId();

        if (!empty($scopeIds)) {
            $this->asociarScopes($credencialId, $scopeIds);
        }

        return $credencialId;
    }

    /**
     * @param array<int, int> $scopeIds
     */
    public function asociarScopes(int $credencialId, array $scopeIds): void
    {
        if (empty($scopeIds)) {
            return;
        }

        $sql = 'INSERT IGNORE INTO api_credencial_scopes (api_credencial_id, api_scope_id) VALUES (:credencial_id, :scope_id)';
        $stmt = $this->pdo->prepare($sql);

        foreach ($scopeIds as $sId) {
            $stmt->execute([
                'credencial_id' => $credencialId,
                'scope_id' => (int) $sId,
            ]);
        }
    }

    public function actualizarUltimoUso(int $id): bool
    {
        $sql = 'UPDATE api_credenciales SET ultimo_uso_en = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    public function revocar(int $id): bool
    {
        $sql = "UPDATE api_credenciales SET estado = 'REVOCADO', revocado_en = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    public function expirar(int $id): bool
    {
        $sql = "UPDATE api_credenciales SET estado = 'EXPIRADO' WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    /**
     * @return array<int, ApiCredencial>
     */
    public function listarPorCliente(int $clienteId): array
    {
        $sql = 'SELECT * FROM api_credenciales WHERE api_cliente_id = :cliente_id ORDER BY id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cliente_id' => $clienteId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $fila) {
            $scopes = $this->scopeRepo->listarCodigosPorCredencialId((int) $fila['id']);
            $resultado[] = $this->mapearFila($fila, $scopes);
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $fila
     * @param array<int, string> $scopes
     */
    public function mapearFila(array $fila, array $scopes = []): ApiCredencial
    {
        return ApiCredencial::desdeArreglo($fila, $scopes);
    }
}
