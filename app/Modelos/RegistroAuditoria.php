<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa una entrada inmutable en el registro histórico de auditoría.
 */
class RegistroAuditoria
{
    private ?int $id;
    private int $actorId;
    private ?int $usuarioId;
    private string $accion;
    private string $modulo;
    private string $entidad;
    private ?string $entidadId;
    private ?string $descripcion;
    private ?array $valoresAnteriores;
    private ?array $valoresNuevos;
    private ?array $contexto;
    private ?string $ip;
    private ?string $userAgent;
    private ?string $correlacionId;
    private ?string $creadoEn;

    /**
     * @param int|null $id
     * @param int $actorId
     * @param int|null $usuarioId
     * @param string $accion
     * @param string $modulo
     * @param string $entidad
     * @param string|null $entidadId
     * @param string|null $descripcion
     * @param array<string, mixed>|null $valoresAnteriores
     * @param array<string, mixed>|null $valoresNuevos
     * @param array<string, mixed>|null $contexto
     * @param string|null $ip
     * @param string|null $userAgent
     * @param string|null $correlacionId
     * @param string|null $creadoEn
     */
    public function __construct(
        ?int $id,
        int $actorId,
        ?int $usuarioId,
        string $accion,
        string $modulo,
        string $entidad,
        ?string $entidadId = null,
        ?string $descripcion = null,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?array $contexto = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $correlacionId = null,
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->actorId = $actorId;
        $this->usuarioId = $usuarioId !== null && (int) $usuarioId > 0 ? (int) $usuarioId : null;
        $this->accion = strtoupper(trim($accion));
        $this->modulo = trim($modulo);
        $this->entidad = trim($entidad);
        $this->entidadId = $entidadId !== null ? trim((string) $entidadId) : null;
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->valoresAnteriores = $valoresAnteriores;
        $this->valoresNuevos = $valoresNuevos;
        $this->contexto = $contexto;
        $this->ip = $ip !== null ? trim($ip) : null;
        $this->userAgent = $userAgent !== null ? mb_substr(trim($userAgent), 0, 500) : null;
        $this->correlacionId = $correlacionId !== null ? trim($correlacionId) : null;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerUsuarioId(): ?int
    {
        return $this->usuarioId;
    }

    public function obtenerAccion(): string
    {
        return $this->accion;
    }

    public function obtenerModulo(): string
    {
        return $this->modulo;
    }

    public function obtenerEntidad(): string
    {
        return $this->entidad;
    }

    public function obtenerEntidadId(): ?string
    {
        return $this->entidadId;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerValoresAnteriores(): ?array
    {
        return $this->valoresAnteriores;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerValoresNuevos(): ?array
    {
        return $this->valoresNuevos;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerContexto(): ?array
    {
        return $this->contexto;
    }

    public function obtenerIp(): ?string
    {
        return $this->ip;
    }

    public function obtenerUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function obtenerCorrelacionId(): ?string
    {
        return $this->correlacionId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    /**
     * Serializa el registro a un arreglo asociativo seguro sin credenciales.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'actor_id' => $this->actorId,
            'usuario_id' => $this->usuarioId,
            'accion' => $this->accion,
            'modulo' => $this->modulo,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidadId,
            'descripcion' => $this->descripcion,
            'valores_anteriores' => $this->valoresAnteriores,
            'valores_nuevos' => $this->valoresNuevos,
            'contexto' => $this->contexto,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'correlacion_id' => $this->correlacionId,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * Alias en español según convenciones de Camargo PMS.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->aArray();
    }
}
