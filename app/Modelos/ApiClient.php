<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio para Clientes API e Integraciones Externas.
 *
 * Separación de Arquitectura:
 * ACTOR INTEGRACION -> API_CLIENT -> CREDENCIAL TÉCNICA -> SCOPES
 */
class ApiClient
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public function __construct(
        private ?int $id,
        private int $actorId,
        private string $codigo,
        private string $nombre,
        private ?string $descripcion = null,
        private ?string $contactoEmail = null,
        private ?string $ipsPermitidas = null,
        private int $limitePeticionesMinuto = 60,
        private string $estado = self::ESTADO_ACTIVO,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerContactoEmail(): ?string
    {
        return $this->contactoEmail;
    }

    public function obtenerIpsPermitidas(): ?string
    {
        return $this->ipsPermitidas;
    }

    public function obtenerLimitePeticionesMinuto(): int
    {
        return $this->limitePeticionesMinuto;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function permiteIp(string $ip): bool
    {
        if ($this->ipsPermitidas === null || trim($this->ipsPermitidas) === '') {
            return true;
        }

        $ips = array_map('trim', explode(',', $this->ipsPermitidas));
        return in_array($ip, $ips, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'actor_id' => $this->actorId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'contacto_email' => $this->contactoEmail,
            'ips_permitidas' => $this->ipsPermitidas,
            'limite_peticiones_minuto' => $this->limitePeticionesMinuto,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            actorId: (int) ($datos['actor_id'] ?? 0),
            codigo: (string) ($datos['codigo'] ?? ''),
            nombre: (string) ($datos['nombre'] ?? ''),
            descripcion: isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            contactoEmail: isset($datos['contacto_email']) ? (string) $datos['contacto_email'] : null,
            ipsPermitidas: isset($datos['ips_permitidas']) ? (string) $datos['ips_permitidas'] : null,
            limitePeticionesMinuto: isset($datos['limite_peticiones_minuto']) ? (int) $datos['limite_peticiones_minuto'] : 60,
            estado: (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
