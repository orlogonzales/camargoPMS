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
        return $this->obtenerEstadoSoberano(30, 12, $ahora ? strtotime($ahora) : null) === 'ACTIVA';
    }

    /**
     * Calcula soberanamente la fecha y hora de expiración absoluta (12 horas fijas desde iniciada_en).
     */
    public function obtenerExpiracionAbsoluta(int $horasDuracionMaxima = 12): string
    {
        $tsIniciada = strtotime($this->iniciadaEn);
        return date('Y-m-d H:i:s', $tsIniciada + ($horasDuracionMaxima * 3600));
    }

    /**
     * Deriva el estado soberano de validez de la sesión según D-088:
     * - 'REVOCADA'
     * - 'EXPIRADA_ABSOLUTA'
     * - 'EXPIRADA_INACTIVIDAD'
     * - 'ACTIVA'
     */
    public function obtenerEstadoSoberano(
        int $minutosInactividad = 30,
        int $horasDuracionMaxima = 12,
        ?int $timestampAhora = null
    ): string {
        if ($this->estaRevocada()) {
            return 'REVOCADA';
        }

        $ahora = $timestampAhora ?? time();
        $tsIniciada = strtotime($this->iniciadaEn);
        $tsUltimaActividad = strtotime($this->ultimaActividadEn);

        // 1. Expiración absoluta soberana
        if (($ahora - $tsIniciada) > ($horasDuracionMaxima * 3600)) {
            return 'EXPIRADA_ABSOLUTA';
        }

        // 2. Expiración por inactividad
        if (($ahora - $tsUltimaActividad) > ($minutosInactividad * 60)) {
            return 'EXPIRADA_INACTIVIDAD';
        }

        return 'ACTIVA';
    }

    /**
     * Deriva el indicador heurístico de presencia HTTP reciente:
     * - 'PRESENCIA_RECIENTE' (actividad <= 15 min en sesión ACTIVA)
     * - 'SIN_ACTIVIDAD_RECIENTE' (actividad > 15 min o sesión no activa)
     */
    public function obtenerPresenciaReciente(
        int $minutosVentana = 15,
        int $minutosInactividad = 30,
        int $horasDuracionMaxima = 12,
        ?int $timestampAhora = null
    ): string {
        $estado = $this->obtenerEstadoSoberano($minutosInactividad, $horasDuracionMaxima, $timestampAhora);
        if ($estado !== 'ACTIVA') {
            return 'SIN_ACTIVIDAD_RECIENTE';
        }

        $ahora = $timestampAhora ?? time();
        $tsUltimaActividad = strtotime($this->ultimaActividadEn);

        if (($ahora - $tsUltimaActividad) <= ($minutosVentana * 60)) {
            return 'PRESENCIA_RECIENTE';
        }

        return 'SIN_ACTIVIDAD_RECIENTE';
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
     * Exporta la sesión a un arreglo seguro sin exponer secretos ni hashes (token_hash protegido).
     *
     * @param bool $incluirTokenHash Para uso estrictamente interno si se requiere
     * @return array<string, mixed>
     */
    public function aArreglo(bool $incluirTokenHash = false): array
    {
        $arreglo = [
            'id' => $this->id,
            'usuario_id' => $this->usuarioId,
            'iniciada_en' => $this->iniciadaEn,
            'ultima_actividad_en' => $this->ultimaActividadEn,
            'expira_en' => $this->expiraEn,
            'expiracion_absoluta_en' => $this->obtenerExpiracionAbsoluta(),
            'revocada_en' => $this->revocadaEn,
            'motivo_cierre' => $this->motivoCierre,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'creado_en' => $this->creadoEn,
            'usuario' => $this->usuario ? $this->usuario->aArreglo() : null,
            'estado_sesion' => $this->obtenerEstadoSoberano(),
            'presencia_reciente' => $this->obtenerPresenciaReciente(),
            'esta_activa' => $this->estaActiva(),
        ];

        if ($incluirTokenHash) {
            $arreglo['token_hash'] = $this->tokenHash;
        }

        return $arreglo;
    }
}
