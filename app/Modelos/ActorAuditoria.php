<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio que representa un Actor (emisor/principal) de operaciones en el sistema.
 *
 * Principio Vinculante:
 * ACTOR != USUARIO
 * Los actores representan tanto a usuarios humanos como a subsistemas internos,
 * aplicaciones integradas o pasarelas de pago, sin crear identidades humanas ficticias.
 */
class ActorAuditoria
{
    private ?int $id;
    private string $tipo;
    private string $codigo;
    private string $nombre;
    private ?int $usuarioId;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @param int|null $id
     * @param string $tipo Tipo de actor (USUARIO, SISTEMA, INTEGRACION, PROVEEDOR_PAGO)
     * @param string $codigo Identificador técnico único y estable (ej. 'CAMARGO_PMS', 'USR_1')
     * @param string $nombre Nombre descriptivo del actor
     * @param int|null $usuarioId ID de la cuenta de usuario humano si corresponde
     * @param string $estado 'ACTIVO' o 'INACTIVO'
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        ?int $id,
        string $tipo,
        string $codigo,
        string $nombre,
        ?int $usuarioId = null,
        string $estado = 'ACTIVO',
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->tipo = strtoupper(trim($tipo));
        $this->codigo = trim($codigo);
        $this->nombre = trim($nombre);
        $this->usuarioId = $usuarioId !== null && (int) $usuarioId > 0 ? (int) $usuarioId : null;
        $this->estado = strtoupper(trim($estado));
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerUsuarioId(): ?int
    {
        return $this->usuarioId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function esActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function estaActivo(): bool
    {
        return $this->esActivo();
    }

    public function esHumano(): bool
    {
        return $this->tipo === TipoActor::USUARIO;
    }

    public function esSistema(): bool
    {
        return $this->tipo === TipoActor::SISTEMA;
    }

    public function esIntegracion(): bool
    {
        return $this->tipo === TipoActor::INTEGRACION;
    }

    public function esProveedorPago(): bool
    {
        return $this->tipo === TipoActor::PROVEEDOR_PAGO;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * Serializa la entidad a un arreglo asociativo seguro sin credenciales.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'usuario_id' => $this->usuarioId,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
