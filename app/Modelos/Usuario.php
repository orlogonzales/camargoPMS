<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio puro que representa la cuenta humana de acceso (Usuario).
 *
 * Principio vinculante:
 * PERSONA ≠ COLABORADOR ≠ USUARIO ≠ CARGO ≠ ROL
 *
 * Un Usuario se vincula directamente a una Persona humana ontológica.
 * No depende de la condición laboral (Colaborador) ni otorga permisos de software (Rol).
 */
class Usuario
{
    private ?int $id;
    private int $personaId;
    private string $nombreUsuario;
    private string $contrasenaHash;
    private string $estado;
    private ?string $ultimoAccesoEn;
    private ?string $contrasenaCambiadaEn;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * Objeto Persona asociado si fue cargado por el repositorio.
     */
    private ?Persona $persona;

    /**
     * @param int|null $id
     * @param int $personaId
     * @param string $nombreUsuario
     * @param string $contrasenaHash
     * @param string $estado 'ACTIVO', 'BLOQUEADO', 'INACTIVO'
     * @param string|null $ultimoAccesoEn
     * @param string|null $contrasenaCambiadaEn
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     * @param Persona|null $persona
     */
    public function __construct(
        ?int $id,
        int $personaId,
        string $nombreUsuario,
        string $contrasenaHash,
        string $estado = 'ACTIVO',
        ?string $ultimoAccesoEn = null,
        ?string $contrasenaCambiadaEn = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?Persona $persona = null
    ) {
        $this->id = $id;
        $this->personaId = $personaId;
        $this->nombreUsuario = $nombreUsuario;
        $this->contrasenaHash = $contrasenaHash;
        $this->estado = $estado;
        $this->ultimoAccesoEn = $ultimoAccesoEn;
        $this->contrasenaCambiadaEn = $contrasenaCambiadaEn;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->persona = $persona;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerNombreUsuario(): string
    {
        return $this->nombreUsuario;
    }

    public function obtenerContrasenaHash(): string
    {
        return $this->contrasenaHash;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerUltimoAccesoEn(): ?string
    {
        return $this->ultimoAccesoEn;
    }

    public function obtenerContrasenaCambiadaEn(): ?string
    {
        return $this->contrasenaCambiadaEn;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerPersona(): ?Persona
    {
        return $this->persona;
    }

    public function asignarPersona(?Persona $persona): void
    {
        $this->persona = $persona;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function estaBloqueado(): bool
    {
        return $this->estado === 'BLOQUEADO';
    }

    public function estaInactivo(): bool
    {
        return $this->estado === 'INACTIVO';
    }

    /**
     * Hidrata una instancia del modelo desde un array de base de datos.
     *
     * @param array<string, mixed> $fila
     * @param Persona|null $persona
     * @return self
     */
    public static function desdeArreglo(array $fila, ?Persona $persona = null): self
    {
        return new self(
            isset($fila['id']) ? (int) $fila['id'] : null,
            (int) ($fila['persona_id'] ?? 0),
            (string) ($fila['nombre_usuario'] ?? ''),
            (string) ($fila['contrasena_hash'] ?? ''),
            (string) ($fila['estado'] ?? 'ACTIVO'),
            isset($fila['ultimo_acceso_en']) ? (string) $fila['ultimo_acceso_en'] : null,
            isset($fila['contrasena_cambiada_en']) ? (string) $fila['contrasena_cambiada_en'] : null,
            isset($fila['creado_en']) ? (string) $fila['creado_en'] : null,
            isset($fila['actualizado_en']) ? (string) $fila['actualizado_en'] : null,
            $persona
        );
    }

    /**
     * Serializa la entidad a array asociativo sin exponer el hash de contraseña.
     *
     * @param bool $incluirHash Opcional, solo para persistencia en repositorios.
     * @return array<string, mixed>
     */
    public function aArreglo(bool $incluirHash = false): array
    {
        $datos = [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'nombre_usuario' => $this->nombreUsuario,
            'estado' => $this->estado,
            'ultimo_acceso_en' => $this->ultimoAccesoEn,
            'contrasena_cambiada_en' => $this->contrasenaCambiadaEn,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'persona' => $this->persona ? $this->persona->aArreglo() : null,
        ];

        if ($incluirHash) {
            $datos['contrasena_hash'] = $this->contrasenaHash;
        }

        return $datos;
    }
}
