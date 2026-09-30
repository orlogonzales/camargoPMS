<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Credenciales Técnicas Bearer de Clientes API.
 *
 * Almacena exclusivamente el hash SHA-256 del secreto Bearer generado con CSPRNG.
 * Soporta rotación sin destruir el cliente API y revocación inmediata.
 */
class ApiCredencial
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_REVOCADO = 'REVOCADO';
    public const ESTADO_EXPIRADO = 'EXPIRADO';

    /**
     * @param array<int, string> $scopes Códigos de scopes asignados
     */
    public function __construct(
        private ?int $id,
        private int $apiClienteId,
        private string $identificadorPublico,
        private string $tokenHash,
        private string $tokenPrefijo,
        private string $nombre = 'Credencial Principal',
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $ultimoUsoEn = null,
        private ?string $expiraEn = null,
        private ?string $creadoEn = null,
        private ?string $revocadoEn = null,
        private array $scopes = []
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerApiClientId(): int
    {
        return $this->apiClienteId;
    }

    public function obtenerIdentificadorPublico(): string
    {
        return $this->identificadorPublico;
    }

    public function obtenerTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function obtenerTokenPrefijo(): string
    {
        return $this->tokenPrefijo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerUltimoUsoEn(): ?string
    {
        return $this->ultimoUsoEn;
    }

    public function obtenerExpiraEn(): ?string
    {
        return $this->expiraEn;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerRevocadoEn(): ?string
    {
        return $this->revocadoEn;
    }

    /**
     * @return array<int, string>
     */
    public function obtenerScopes(): array
    {
        return $this->scopes;
    }

    public function tieneScope(string $codigoScope): bool
    {
        return in_array(trim($codigoScope), $this->scopes, true);
    }

    public function estaActiva(): bool
    {
        if ($this->estado !== self::ESTADO_ACTIVO) {
            return false;
        }

        if ($this->expiraEn !== null && $this->expiraEn < date('Y-m-d H:i:s')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'api_cliente_id' => $this->apiClienteId,
            'identificador_publico' => $this->identificadorPublico,
            'token_prefijo' => $this->tokenPrefijo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'ultimo_uso_en' => $this->ultimoUsoEn,
            'expira_en' => $this->expiraEn,
            'creado_en' => $this->creadoEn,
            'revocado_en' => $this->revocadoEn,
            'scopes' => $this->scopes,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<int, string> $scopes
     */
    public static function desdeArreglo(array $datos, array $scopes = []): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            apiClienteId: (int) ($datos['api_cliente_id'] ?? 0),
            identificadorPublico: (string) ($datos['identificador_publico'] ?? ''),
            tokenHash: (string) ($datos['token_hash'] ?? ''),
            tokenPrefijo: (string) ($datos['token_prefijo'] ?? ''),
            nombre: (string) ($datos['nombre'] ?? 'Credencial Principal'),
            estado: (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            ultimoUsoEn: isset($datos['ultimo_uso_en']) ? (string) $datos['ultimo_uso_en'] : null,
            expiraEn: isset($datos['expira_en']) ? (string) $datos['expira_en'] : null,
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            revocadoEn: isset($datos['revocado_en']) ? (string) $datos['revocado_en'] : null,
            scopes: $scopes ?: ($datos['scopes'] ?? [])
        );
    }
}
