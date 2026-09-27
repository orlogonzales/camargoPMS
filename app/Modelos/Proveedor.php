<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa a un proveedor externo homologado (empresa o persona natural).
 *
 * Principio vinculante:
 * - Cero proveedor "INTERNO" ficticio: la operación interna se modela con es_operacion_interna = 1 y proveedor_id = NULL.
 */
class Proveedor
{
    private ?int $id;
    private string $codigo;
    private string $tipo; // 'EMPRESA' | 'PERSONA_NATURAL'
    private ?int $personaId;
    private string $razonSocial;
    private ?string $nombreComercial;
    private ?string $numeroDocumento;
    private ?string $email;
    private ?string $telefono;
    private ?string $direccion;
    private string $estado;
    private ?string $observaciones;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos auxiliares opcionales
    private ?string $personaNombreCompleto = null;

    public function __construct(
        ?int $id,
        string $codigo,
        string $tipo,
        ?int $personaId,
        string $razonSocial,
        ?string $nombreComercial = null,
        ?string $numeroDocumento = null,
        ?string $email = null,
        ?string $telefono = null,
        ?string $direccion = null,
        string $estado = 'ACTIVO',
        ?string $observaciones = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->codigo = trim(strtoupper($codigo));
        $this->tipo = strtoupper(trim($tipo)) === 'PERSONA_NATURAL' ? 'PERSONA_NATURAL' : 'EMPRESA';
        $this->personaId = $personaId;
        $this->razonSocial = trim($razonSocial);
        $this->nombreComercial = $nombreComercial !== null ? trim($nombreComercial) : null;
        $this->numeroDocumento = $numeroDocumento !== null ? trim($numeroDocumento) : null;
        $this->email = $email !== null ? trim(strtolower($email)) : null;
        $this->telefono = $telefono !== null ? trim($telefono) : null;
        $this->direccion = $direccion !== null ? trim($direccion) : null;
        $this->estado = trim(strtoupper($estado));
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function esEmpresa(): bool
    {
        return $this->tipo === 'EMPRESA';
    }

    public function esPersonaNatural(): bool
    {
        return $this->tipo === 'PERSONA_NATURAL';
    }

    public function obtenerPersonaId(): ?int
    {
        return $this->personaId;
    }

    public function obtenerRazonSocial(): string
    {
        return $this->razonSocial;
    }

    public function obtenerNombreComercial(): ?string
    {
        return $this->nombreComercial;
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

    public function obtenerDireccion(): ?string
    {
        return $this->direccion;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function asignarPersonaNombreCompleto(?string $nombre): void
    {
        $this->personaNombreCompleto = $nombre;
    }

    public function obtenerPersonaNombreCompleto(): ?string
    {
        return $this->personaNombreCompleto;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $proveedor = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['tipo'] ?? 'EMPRESA'),
            isset($datos['persona_id']) && $datos['persona_id'] !== '' ? (int) $datos['persona_id'] : null,
            (string) ($datos['razon_social'] ?? ''),
            isset($datos['nombre_comercial']) ? (string) $datos['nombre_comercial'] : null,
            isset($datos['numero_documento']) ? (string) $datos['numero_documento'] : null,
            isset($datos['email']) ? (string) $datos['email'] : null,
            isset($datos['telefono']) ? (string) $datos['telefono'] : null,
            isset($datos['direccion']) ? (string) $datos['direccion'] : null,
            (string) ($datos['estado'] ?? 'ACTIVO'),
            isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );

        if (isset($datos['persona_nombre_completo'])) {
            $proveedor->asignarPersonaNombreCompleto((string) $datos['persona_nombre_completo']);
        }

        return $proveedor;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'persona_id' => $this->personaId,
            'persona_nombre_completo' => $this->personaNombreCompleto,
            'razon_social' => $this->razonSocial,
            'nombre_comercial' => $this->nombreComercial,
            'numero_documento' => $this->numeroDocumento,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
