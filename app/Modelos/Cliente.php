<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio para el Perfil Comercial de Clientes (CLIENTES-1).
 *
 * Principio vinculante: PERSONA ≠ CLIENTE pero CLIENTE -> PERSONA.
 * - Persona: Registro maestro y soberano de identidad humana civil y biológica.
 * - Cliente: Modela exclusivamente la relación comercial para Personas Naturales (1:1).
 * - Estados comerciales: ACTIVO, INACTIVO, BLOQUEADO.
 * - Bloqueo comercial: Exige motivo explícito y genera advertencia sin romper operaciones.
 */
class Cliente
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';
    public const ESTADO_BLOQUEADO = 'BLOQUEADO';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_ACTIVO,
        self::ESTADO_INACTIVO,
        self::ESTADO_BLOQUEADO,
    ];

    private ?int $id;
    private string $codigo;
    private int $personaId;
    private int $categoriaId;
    private string $estado;
    private ?string $motivoBloqueo;
    private ?string $canalCaptacion;
    private ?string $preferencias;
    private ?string $observaciones;
    private ?int $creadoPorActorId;
    private ?int $actualizadoPorActorId;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Relaciones enriquecidas
    private ?Persona $persona = null;
    private ?ClienteCategoria $categoria = null;

    public function __construct(
        ?int $id,
        string $codigo,
        int $personaId,
        int $categoriaId,
        string $estado = self::ESTADO_ACTIVO,
        ?string $motivoBloqueo = null,
        ?string $canalCaptacion = null,
        ?string $preferencias = null,
        ?string $observaciones = null,
        ?int $creadoPorActorId = null,
        ?int $actualizadoPorActorId = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?Persona $persona = null,
        ?ClienteCategoria $categoria = null
    ) {
        $codigoLimpio = strtoupper(trim($codigo));
        if ($codigoLimpio === '') {
            throw new InvalidArgumentException('El código de cliente no puede estar vacío.');
        }

        if ($personaId <= 0) {
            throw new InvalidArgumentException('El ID de persona debe ser un entero positivo.');
        }

        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('El ID de categoría debe ser un entero positivo.');
        }

        $estadoLimpio = strtoupper(trim($estado));
        if (!in_array($estadoLimpio, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Estado de cliente inválido: '{$estado}'.");
        }

        $motivoLimpio = $motivoBloqueo !== null ? trim($motivoBloqueo) : null;
        if ($estadoLimpio === self::ESTADO_BLOQUEADO && ($motivoLimpio === null || $motivoLimpio === '')) {
            throw new InvalidArgumentException('El motivo de bloqueo es obligatorio cuando el estado es BLOQUEADO.');
        }

        $this->id = $id;
        $this->codigo = $codigoLimpio;
        $this->personaId = $personaId;
        $this->categoriaId = $categoriaId;
        $this->estado = $estadoLimpio;
        $this->motivoBloqueo = $motivoLimpio;
        $this->canalCaptacion = $canalCaptacion !== null ? trim($canalCaptacion) : null;
        $this->preferencias = $preferencias !== null ? trim($preferencias) : null;
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        $this->creadoPorActorId = $creadoPorActorId;
        $this->actualizadoPorActorId = $actualizadoPorActorId;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->persona = $persona;
        $this->categoria = $categoria;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function fijarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function fijarCodigo(string $codigo): void
    {
        $codigoLimpio = strtoupper(trim($codigo));
        if ($codigoLimpio === '') {
            throw new InvalidArgumentException('El código de cliente no puede estar vacío.');
        }
        $this->codigo = $codigoLimpio;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function fijarPersonaId(int $personaId): void
    {
        if ($personaId <= 0) {
            throw new InvalidArgumentException('El ID de persona debe ser un entero positivo.');
        }
        $this->personaId = $personaId;
    }

    public function obtenerCategoriaId(): int
    {
        return $this->categoriaId;
    }

    public function fijarCategoriaId(int $categoriaId): void
    {
        if ($categoriaId <= 0) {
            throw new InvalidArgumentException('El ID de categoría debe ser un entero positivo.');
        }
        $this->categoriaId = $categoriaId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaBloqueado(): bool
    {
        return $this->estado === self::ESTADO_BLOQUEADO;
    }

    public function estaInactivo(): bool
    {
        return $this->estado === self::ESTADO_INACTIVO;
    }

    public function activar(): void
    {
        $this->estado = self::ESTADO_ACTIVO;
        $this->motivoBloqueo = null;
    }

    public function desactivar(): void
    {
        $this->estado = self::ESTADO_INACTIVO;
    }

    public function bloquear(string $motivo): void
    {
        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '') {
            throw new InvalidArgumentException('El motivo de bloqueo es obligatorio.');
        }
        $this->estado = self::ESTADO_BLOQUEADO;
        $this->motivoBloqueo = $motivoLimpio;
    }

    public function obtenerMotivoBloqueo(): ?string
    {
        return $this->motivoBloqueo;
    }

    public function fijarMotivoBloqueo(?string $motivo): void
    {
        $this->motivoBloqueo = $motivo !== null ? trim($motivo) : null;
    }

    public function obtenerCanalCaptacion(): ?string
    {
        return $this->canalCaptacion;
    }

    public function fijarCanalCaptacion(?string $canal): void
    {
        $this->canalCaptacion = $canal !== null ? trim($canal) : null;
    }

    public function obtenerPreferencias(): ?string
    {
        return $this->preferencias;
    }

    public function fijarPreferencias(?string $preferencias): void
    {
        $this->preferencias = $preferencias !== null ? trim($preferencias) : null;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function fijarObservaciones(?string $observaciones): void
    {
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
    }

    public function obtenerCreadoPorActorId(): ?int
    {
        return $this->creadoPorActorId;
    }

    public function fijarCreadoPorActorId(?int $actorId): void
    {
        $this->creadoPorActorId = $actorId;
    }

    public function obtenerActualizadoPorActorId(): ?int
    {
        return $this->actualizadoPorActorId;
    }

    public function fijarActualizadoPorActorId(?int $actorId): void
    {
        $this->actualizadoPorActorId = $actorId;
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

    public function fijarPersona(?Persona $persona): void
    {
        $this->persona = $persona;
    }

    public function obtenerCategoria(): ?ClienteCategoria
    {
        return $this->categoria;
    }

    public function fijarCategoria(?ClienteCategoria $categoria): void
    {
        $this->categoria = $categoria;
    }

    /**
     * Serializa la entidad a arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'persona_id' => $this->personaId,
            'categoria_id' => $this->categoriaId,
            'estado' => $this->estado,
            'motivo_bloqueo' => $this->motivoBloqueo,
            'canal_captacion' => $this->canalCaptacion,
            'preferencias' => $this->preferencias,
            'observaciones' => $this->observaciones,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'actualizado_por_actor_id' => $this->actualizadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'categoria_codigo' => $this->categoria !== null ? $this->categoria->obtenerCodigo() : null,
            'categoria_nombre' => $this->categoria !== null ? $this->categoria->obtenerNombre() : null,
            'persona' => $this->persona !== null ? $this->persona->aArreglo() : null,
            'categoria' => $this->categoria !== null ? $this->categoria->aArreglo() : null,
        ];
    }

    /**
     * Instancia un Cliente a partir de un arreglo asociativo.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['persona_id'] ?? 0),
            (int) ($datos['categoria_id'] ?? 0),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['motivo_bloqueo']) ? (string) $datos['motivo_bloqueo'] : null,
            isset($datos['canal_captacion']) ? (string) $datos['canal_captacion'] : null,
            isset($datos['preferencias']) ? (string) $datos['preferencias'] : null,
            isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            isset($datos['creado_por_actor_id']) && $datos['creado_por_actor_id'] !== null ? (int) $datos['creado_por_actor_id'] : null,
            isset($datos['actualizado_por_actor_id']) && $datos['actualizado_por_actor_id'] !== null ? (int) $datos['actualizado_por_actor_id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
