<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad asociativa que vincula una persona a un arrendamiento con su rol específico.
 * 
 * Reglas vinculantes (D-076):
 * - Tipo de relación: TITULAR (único por contrato), COTITULAR u OCUPANTE.
 * - Unicidad garantizada en base de datos mediante columna generada virtual uq_arrp_titular_unico.
 */
class ArrendamientoPersona
{
    private int $arrendamientoId;
    private int $personaId;
    private string $tipoRelacion; // 'TITULAR' | 'COTITULAR' | 'OCUPANTE'
    private ?string $observaciones;
    private ?string $creadoEn;

    // Metadatos auxiliares de la persona
    private ?string $nombreCompleto = null;
    private ?string $tipoDocumento = null;
    private ?string $numeroDocumento = null;
    private ?string $telefono = null;
    private ?string $correoElectronico = null;

    public function __construct(
        int $arrendamientoId,
        int $personaId,
        string $tipoRelacion,
        ?string $observaciones = null,
        ?string $creadoEn = null
    ) {
        $this->arrendamientoId = $arrendamientoId;
        $this->personaId = $personaId;
        $this->tipoRelacion = $tipoRelacion;
        $this->observaciones = $observaciones;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerArrendamientoId(): int { return $this->arrendamientoId; }
    public function obtenerPersonaId(): int { return $this->personaId; }
    public function obtenerTipoRelacion(): string { return $this->tipoRelacion; }
    public function obtenerObservaciones(): ?string { return $this->observaciones; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }

    public function esTitular(): bool { return $this->tipoRelacion === 'TITULAR'; }
    public function esCotitular(): bool { return $this->tipoRelacion === 'COTITULAR'; }
    public function esOcupante(): bool { return $this->tipoRelacion === 'OCUPANTE'; }

    // Metadatos auxiliares
    public function obtenerNombreCompleto(): ?string { return $this->nombreCompleto; }
    public function fijarNombreCompleto(?string $val): void { $this->nombreCompleto = $val; }
    public function obtenerTipoDocumento(): ?string { return $this->tipoDocumento; }
    public function fijarTipoDocumento(?string $val): void { $this->tipoDocumento = $val; }
    public function obtenerNumeroDocumento(): ?string { return $this->numeroDocumento; }
    public function fijarNumeroDocumento(?string $val): void { $this->numeroDocumento = $val; }
    public function obtenerTelefono(): ?string { return $this->telefono; }
    public function fijarTelefono(?string $val): void { $this->telefono = $val; }
    public function obtenerCorreoElectronico(): ?string { return $this->correoElectronico; }
    public function fijarCorreoElectronico(?string $val): void { $this->correoElectronico = $val; }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'arrendamiento_id' => $this->arrendamientoId,
            'persona_id' => $this->personaId,
            'tipo_relacion' => $this->tipoRelacion,
            'observaciones' => $this->observaciones,
            'creado_en' => $this->creadoEn,
            'nombre_completo' => $this->nombreCompleto,
            'tipo_documento' => $this->tipoDocumento,
            'numero_documento' => $this->numeroDocumento,
            'telefono' => $this->telefono,
            'correo_electronico' => $this->correoElectronico,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $rel = new self(
            (int) ($datos['arrendamiento_id'] ?? 0),
            (int) ($datos['persona_id'] ?? 0),
            (string) ($datos['tipo_relacion'] ?? 'OCUPANTE'),
            isset($datos['observaciones']) && $datos['observaciones'] !== null ? (string) $datos['observaciones'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );

        if (isset($datos['nombre_completo'])) {
            $rel->fijarNombreCompleto((string) $datos['nombre_completo']);
        }
        if (isset($datos['tipo_documento'])) {
            $rel->fijarTipoDocumento((string) $datos['tipo_documento']);
        }
        if (isset($datos['numero_documento'])) {
            $rel->fijarNumeroDocumento((string) $datos['numero_documento']);
        }
        if (isset($datos['telefono'])) {
            $rel->fijarTelefono((string) $datos['telefono']);
        }
        if (isset($datos['correo_electronico'])) {
            $rel->fijarCorreoElectronico((string) $datos['correo_electronico']);
        }

        return $rel;
    }
}
