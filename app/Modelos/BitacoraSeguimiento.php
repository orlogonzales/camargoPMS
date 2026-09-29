<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio que representa un seguimiento o enmienda en el Libro de Guardia.
 *
 * Sigue los principios de inmutabilidad y auditoría D-089 (BITÁCORA-1):
 * - Los eventos se registran de forma append-only.
 * - Los comentarios, enmiendas aclaratorias, cambios de estado y resoluciones
 *   quedan cronológicamente fijados.
 */
class BitacoraSeguimiento
{
    public const TIPO_COMENTARIO = 'COMENTARIO';
    public const TIPO_ENMIENDA = 'ENMIENDA';
    public const TIPO_CAMBIO_ESTADO = 'CAMBIO_ESTADO';
    public const TIPO_RESOLUCION = 'RESOLUCION';
    public const TIPO_REAPERTURA = 'REAPERTURA';
    public const TIPO_ANULACION = 'ANULACION';

    public const TIPOS_EVENTO_VALIDOS = [
        self::TIPO_COMENTARIO,
        self::TIPO_ENMIENDA,
        self::TIPO_CAMBIO_ESTADO,
        self::TIPO_RESOLUCION,
        self::TIPO_REAPERTURA,
        self::TIPO_ANULACION,
    ];

    private ?int $id;
    private int $entradaId;
    private int $usuarioId;
    private string $tipoEvento;
    private string $contenido;
    private ?string $estadoAnterior;
    private ?string $estadoNuevo;
    private ?string $creadoEn;

    // Metadatos presentacionales enriquecidos
    private ?string $usuarioNombre;

    public function __construct(
        ?int $id,
        int $entradaId,
        int $usuarioId,
        string $tipoEvento,
        string $contenido,
        ?string $estadoAnterior = null,
        ?string $estadoNuevo = null,
        ?string $creadoEn = null,
        ?string $usuarioNombre = null
    ) {
        $this->validarTipoEvento($tipoEvento);

        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El contenido del seguimiento no puede estar vacío.');
        }

        $this->id = $id;
        $this->entradaId = $entradaId;
        $this->usuarioId = $usuarioId;
        $this->tipoEvento = $tipoEvento;
        $this->contenido = trim($contenido);
        $this->estadoAnterior = $estadoAnterior;
        $this->estadoNuevo = $estadoNuevo;
        $this->creadoEn = $creadoEn;
        $this->usuarioNombre = $usuarioNombre;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerEntradaId(): int
    {
        return $this->entradaId;
    }

    public function obtenerUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function obtenerTipoEvento(): string
    {
        return $this->tipoEvento;
    }

    public function obtenerContenido(): string
    {
        return $this->contenido;
    }

    public function obtenerEstadoAnterior(): ?string
    {
        return $this->estadoAnterior;
    }

    public function obtenerEstadoNuevo(): ?string
    {
        return $this->estadoNuevo;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerUsuarioNombre(): ?string
    {
        return $this->usuarioNombre;
    }

    private function validarTipoEvento(string $tipoEvento): void
    {
        if (!in_array($tipoEvento, self::TIPOS_EVENTO_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Tipo de evento de seguimiento no válido: "%s"', $tipoEvento));
        }
    }

    public static function fromArray(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['entrada_id'] ?? 0),
            (int) ($datos['usuario_id'] ?? 0),
            (string) ($datos['tipo_evento'] ?? self::TIPO_COMENTARIO),
            (string) ($datos['contenido'] ?? ''),
            $datos['estado_anterior'] ?? null,
            $datos['estado_nuevo'] ?? null,
            $datos['creado_en'] ?? null,
            $datos['usuario_nombre'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entrada_id' => $this->entradaId,
            'usuario_id' => $this->usuarioId,
            'tipo_evento' => $this->tipoEvento,
            'contenido' => $this->contenido,
            'estado_anterior' => $this->estadoAnterior,
            'estadoNuevo' => $this->estadoNuevo,
            'estado_nuevo' => $this->estadoNuevo,
            'creado_en' => $this->creadoEn,
            'usuario_nombre' => $this->usuarioNombre,
        ];
    }
}
