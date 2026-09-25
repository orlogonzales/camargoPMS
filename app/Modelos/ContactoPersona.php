<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un medio de contacto perteneciente a una persona natural.
 */
final class ContactoPersona
{
    private ?int $id;
    private int $personaId;
    private string $tipoContacto;
    private string $valor;
    private bool $esWhatsapp;
    private bool $esPrincipal;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        int $personaId,
        string $tipoContacto,
        string $valor,
        bool $esWhatsapp = false,
        bool $esPrincipal = false,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->personaId = $personaId;
        $this->tipoContacto = strtoupper(trim($tipoContacto));
        $this->valor = trim($valor);
        $this->esWhatsapp = $esWhatsapp;
        $this->esPrincipal = $esPrincipal;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['persona_id'] ?? 0),
            (string) ($datos['tipo_contacto'] ?? ''),
            (string) ($datos['valor'] ?? ''),
            (bool) ($datos['es_whatsapp'] ?? false),
            (bool) ($datos['es_principal'] ?? false),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerTipoContacto(): string
    {
        return $this->tipoContacto;
    }

    public function obtenerValor(): string
    {
        return $this->valor;
    }

    public function esWhatsapp(): bool
    {
        return $this->esWhatsapp;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'tipo_contacto' => $this->tipoContacto,
            'valor' => $this->valor,
            'es_whatsapp' => $this->esWhatsapp,
            'es_principal' => $this->esPrincipal,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
