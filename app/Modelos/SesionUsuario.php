<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa una sesión persistente de usuario.
 *
 * Mantiene la trazabilidad y permite el control, expiración y revocación
 * administrativa de sesiones de aplicación.
 */
class SesionUsuario
{
    private ?int $id;
    private int $usuarioId;
    private string $tokenHash;
    private string $iniciadaEn;
    private string $ultimaActividadEn;
    private string $expiraEn;
    private ?string $revocadaEn;
    private ?string $motivoCierre;
    private ?string $ip;
    private ?string $userAgent;
    private ?string $creadoEn;

    /**
     * Usuario titular de la sesión si fue cargado por el repositorio.
     */
    private ?Usuario $usuario;

    public function __construct(
        ?int $id,
        int $usuarioId,
        string $tokenHash,
        string $iniciadaEn,
        string $ultimaActividadEn,
        string $expiraEn,
        ?string $revocadaEn = null,
        ?string $motivoCierre = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $creadoEn = null,
        ?Usuario $usuario = null
    ) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->tokenHash = $tokenHash;
        $this->iniciadaEn = $iniciadaEn;
        $this->ultimaActividadEn = $ultimaActividadEn;
        $this->expiraEn = $expiraEn;
        $this->revocadaEn = $revocadaEn;
        $this->motivoCierre = $motivoCierre;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->creadoEn = $creadoEn;
        $this->usuario = $usuario;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function obtenerTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function obtenerIniciadaEn(): string
    {
        return $this->iniciadaEn;
    }

    public function obtenerUltimaActividadEn(): string
    {
        return $this->ultimaActividadEn;
    }

    public function obtenerExpiraEn(): string
    {
        return $this->expiraEn;
    }

    public function obtenerRevocadaEn(): ?string
    {
        return $this->revocadaEn;
    }

    public function obtenerMotivoCierre(): ?string
    {
        return $this->motivoCierre;
    }

    public function obtenerIp(): ?string
    {
        return $this->ip;
    }

    public function obtenerUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function asignarUsuario(?Usuario $usuario): void
    {
        $this->usuario = $usuario;
    }

    public function estaRevocada(): bool
    {
        return $this->revocadaEn !== null;
    }

    public function haExpirado(?string $ahora = null): bool
    {
        $momento = $ahora ?? date('Y-m-d H:i:s');
        return $this->expiraEn <= $momento;
    }

    public function estaActiva(?string $ahora = null): bool
    {
        return !$this->estaRevocada() && !$this->haExpirado($ahora);
    }

    /**
     * @param array<string, mixed> $fila
     * @param Usuario|null $usuario
     * @return self
     */
    public static function desdeArreglo(array $fila, ?Usuario $usuario = null): self
    {
        return new self(
            isset($fila['id']) ? (int) $fila['id'] : null,
            (int) ($fila['usuario_id'] ?? 0),
            (string) ($fila['token_hash'] ?? ''),
            (string) ($fila['iniciada_en'] ?? ''),
            (string) ($fila['ultima_actividad_en'] ?? ''),
            (string) ($fila['expira_en'] ?? ''),
            isset($fila['revocada_en']) ? (string) $fila['revocada_en'] : null,
            isset($fila['motivo_cierre']) ? (string) $fila['motivo_cierre'] : null,
            isset($fila['ip']) ? (string) $fila['ip'] : null,
            isset($fila['user_agent']) ? (string) $fila['user_agent'] : null,
            isset($fila['creado_en']) ? (string) $fila['creado_en'] : null,
            $usuario
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuarioId,
            'token_hash' => $this->tokenHash,
            'iniciada_en' => $this->iniciadaEn,
            'ultima_actividad_en' => $this->ultimaActividadEn,
            'expira_en' => $this->expiraEn,
            'revocada_en' => $this->revocadaEn,
            'motivo_cierre' => $this->motivoCierre,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'creado_en' => $this->creadoEn,
            'usuario' => $this->usuario ? $this->usuario->aArreglo() : null,
            'esta_activa' => $this->estaActiva(),
        ];
    }
}
