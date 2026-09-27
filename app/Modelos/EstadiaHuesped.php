<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa a un huésped u ocupante físico asignado a una estadía (ESTADÍAS-1).
 *
 * Principios vinculantes:
 * - Cada huésped está vinculado a una persona del registro central (persona_id).
 * - Exactamente 1 huésped responsable (es_responsable = 1) por estadía.
 * - Inmutabilidad histórica: cero borrado físico; integridad referencial ON DELETE RESTRICT.
 */
class EstadiaHuesped
{
    private ?int $id;
    private int $estadiaId;
    private int $personaId;
    private bool $esResponsable;
    private ?string $creadoEn;

    // Metadatos auxiliares de la persona (JOIN)
    private ?string $nombreCompleto = null;
    private ?string $tipoDocumento = null;
    private ?string $numeroDocumento = null;
    private ?string $email = null;
    private ?string $telefono = null;

    public function __construct(
        ?int $id,
        int $estadiaId,
        int $personaId,
        bool $esResponsable = false,
        ?string $creadoEn = null
    ) {
        $this->id = $id;
        $this->estadiaId = $estadiaId;
        $this->personaId = $personaId;
        $this->esResponsable = $esResponsable;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerEstadiaId(): int
    {
        return $this->estadiaId;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function esResponsable(): bool
    {
        return $this->esResponsable;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerNombreCompleto(): ?string
    {
        return $this->nombreCompleto;
    }

    public function obtenerTipoDocumento(): ?string
    {
        return $this->tipoDocumento;
    }

    public function obtenerNumeroDocumento(): ?string
    {
        return $this->numeroDocumento;
    }

    public function obtenerEmail(): ?string
    {
        return $this->email;
    }

    public function obtenerTelefono(): ?string
    {
        return $this->telefono;
    }

    public function asignarMetadatosPersona(
        ?string $nombreCompleto,
        ?string $tipoDocumento = null,
        ?string $numeroDocumento = null,
        ?string $email = null,
        ?string $telefono = null
    ): void {
        $this->nombreCompleto = $nombreCompleto;
        $this->tipoDocumento = $tipoDocumento;
        $this->numeroDocumento = $numeroDocumento;
        $this->email = $email;
        $this->telefono = $telefono;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $huesped = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['estadia_id'] ?? 0),
            (int) ($datos['persona_id'] ?? 0),
            !empty($datos['es_responsable']),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );

        $nombreCompleto = null;
        if (!empty($datos['nombre_completo'])) {
            $nombreCompleto = (string) $datos['nombre_completo'];
        } elseif (!empty($datos['persona_nombres']) || !empty($datos['persona_apellidos'])) {
            $nombres = trim((string) ($datos['persona_nombres'] ?? ''));
            $apellidos = trim((string) ($datos['persona_apellidos'] ?? ''));
            $nombreCompleto = trim("{$nombres} {$apellidos}");
        }

        $huesped->asignarMetadatosPersona(
            $nombreCompleto,
            isset($datos['tipo_documento']) ? (string) $datos['tipo_documento'] : ($datos['persona_tipo_documento'] ?? null),
            isset($datos['numero_documento']) ? (string) $datos['numero_documento'] : ($datos['persona_numero_documento'] ?? null),
            isset($datos['email']) ? (string) $datos['email'] : ($datos['persona_email'] ?? null),
            isset($datos['telefono']) ? (string) $datos['telefono'] : ($datos['persona_telefono'] ?? null)
        );

        return $huesped;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'estadia_id' => $this->estadiaId,
            'persona_id' => $this->personaId,
            'es_responsable' => $this->esResponsable,
            'creado_en' => $this->creadoEn,
            'nombre_completo' => $this->nombreCompleto,
            'tipo_documento' => $this->tipoDocumento,
            'numero_documento' => $this->numeroDocumento,
            'email' => $this->email,
            'telefono' => $this->telefono,
        ];
    }

    /**
     * Alias de haciaArreglo().
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
