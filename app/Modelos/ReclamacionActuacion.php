<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio para las Actuaciones Append-Only del Expediente de Reclamación (RECLAMACIONES-1).
 *
 * Principio regulatorio y arquitectónico:
 * EXPEDIENTE ≠ ACTUACIÓN
 * No existe un único campo mutable de respuesta. Cada actuación, nota, ofrecimiento de solución,
 * aceptación, rechazo o respuesta formal se asienta de manera inmutable y cronológica.
 */
class ReclamacionActuacion
{
    public const TIPO_RECEPCION_INICIAL = 'RECEPCION_INICIAL';
    public const TIPO_NOTA_INTERNA = 'NOTA_INTERNA';
    public const TIPO_OFRECIMIENTO_SOLUCION = 'OFRECIMIENTO_SOLUCION';
    public const TIPO_RESPUESTA_OFRECIMIENTO_ACEPTADO = 'RESPUESTA_OFRECIMIENTO_ACEPTADO';
    public const TIPO_RESPUESTA_OFRECIMIENTO_RECHAZADO = 'RESPUESTA_OFRECIMIENTO_RECHAZADO';
    public const TIPO_EXPIRACION_OFRECIMIENTO = 'EXPIRACION_OFRECIMIENTO';
    public const TIPO_RESPUESTA_FORMAL = 'RESPUESTA_FORMAL';
    public const TIPO_NOTIFICACION_ENVIADA = 'NOTIFICACION_ENVIADA';
    public const TIPO_ANULACION_SUPERVISADA = 'ANULACION_SUPERVISADA';

    public const TIPOS_VALIDOS = [
        self::TIPO_RECEPCION_INICIAL,
        self::TIPO_NOTA_INTERNA,
        self::TIPO_OFRECIMIENTO_SOLUCION,
        self::TIPO_RESPUESTA_OFRECIMIENTO_ACEPTADO,
        self::TIPO_RESPUESTA_OFRECIMIENTO_RECHAZADO,
        self::TIPO_EXPIRACION_OFRECIMIENTO,
        self::TIPO_RESPUESTA_FORMAL,
        self::TIPO_NOTIFICACION_ENVIADA,
        self::TIPO_ANULACION_SUPERVISADA,
    ];

    public const MEDIO_CORREO = 'CORREO_ELECTRONICO';
    public const MEDIO_NOTARIAL = 'CARTA_NOTARIAL';
    public const MEDIO_FISICO = 'FISICO_RECEPCION';
    public const MEDIO_WEB = 'SISTEMA_WEB';

    public const MEDIOS_VALIDOS = [
        self::MEDIO_CORREO,
        self::MEDIO_NOTARIAL,
        self::MEDIO_FISICO,
        self::MEDIO_WEB,
    ];

    private ?int $id;
    private int $reclamacionId;
    private int $actorId;
    private string $tipoActuacion;
    private string $descripcion;
    private ?string $medioNotificacion;
    private ?string $destinatarioNotificacion;
    private ?string $fechaNotificacion;
    private ?int $documentoEmitidoId;
    private ?string $archivoAdjuntoPath;
    private ?string $creadoEn;

    // Relación enriquecida
    private ?string $nombreActor = null;

    public function __construct(
        ?int $id,
        int $reclamacionId,
        int $actorId,
        string $tipoActuacion,
        string $descripcion,
        ?string $medioNotificacion = null,
        ?string $destinatarioNotificacion = null,
        ?string $fechaNotificacion = null,
        ?int $documentoEmitidoId = null,
        ?string $archivoAdjuntoPath = null,
        ?string $creadoEn = null
    ) {
        if ($reclamacionId <= 0) {
            throw new InvalidArgumentException("El ID de reclamación debe ser un entero positivo.");
        }
        if ($actorId <= 0) {
            throw new InvalidArgumentException("El ID de actor debe ser un entero positivo.");
        }

        if (!in_array($tipoActuacion, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException("Tipo de actuación inválido: {$tipoActuacion}");
        }

        $descLimpia = trim($descripcion);
        if ($descLimpia === '') {
            throw new InvalidArgumentException("La descripción de la actuación no puede estar vacía.");
        }

        if ($medioNotificacion !== null) {
            $mNorm = strtoupper(trim($medioNotificacion));
            if ($mNorm === 'EMAIL' || $mNorm === 'CORREO') {
                $medioNotificacion = self::MEDIO_CORREO;
            } elseif ($mNorm === 'WEB') {
                $medioNotificacion = self::MEDIO_WEB;
            } elseif ($mNorm === 'FISICO') {
                $medioNotificacion = self::MEDIO_FISICO;
            } elseif ($mNorm === 'NOTARIAL') {
                $medioNotificacion = self::MEDIO_NOTARIAL;
            }

            if (!in_array($medioNotificacion, self::MEDIOS_VALIDOS, true)) {
                throw new InvalidArgumentException("Medio de notificación inválido: {$medioNotificacion}");
            }
        }

        $this->id = $id;
        $this->reclamacionId = $reclamacionId;
        $this->actorId = $actorId;
        $this->tipoActuacion = $tipoActuacion;
        $this->descripcion = $descLimpia;
        $this->medioNotificacion = $medioNotificacion;
        $this->destinatarioNotificacion = $destinatarioNotificacion !== null ? trim($destinatarioNotificacion) : null;
        $this->fechaNotificacion = $fechaNotificacion;
        $this->documentoEmitidoId = $documentoEmitidoId;
        $this->archivoAdjuntoPath = $archivoAdjuntoPath !== null ? trim($archivoAdjuntoPath) : null;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerReclamacionId(): int
    {
        return $this->reclamacionId;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerTipoActuacion(): string
    {
        return $this->tipoActuacion;
    }

    public function obtenerDescripcion(): string
    {
        return $this->descripcion;
    }

    public function obtenerMedioNotificacion(): ?string
    {
        return $this->medioNotificacion;
    }

    public function obtenerDestinatarioNotificacion(): ?string
    {
        return $this->destinatarioNotificacion;
    }

    public function obtenerFechaNotificacion(): ?string
    {
        return $this->fechaNotificacion;
    }

    public function obtenerDocumentoEmitidoId(): ?int
    {
        return $this->documentoEmitidoId;
    }

    public function obtenerArchivoAdjuntoPath(): ?string
    {
        return $this->archivoAdjuntoPath;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerNombreActor(): ?string
    {
        return $this->nombreActor;
    }

    public function asignarNombreActor(?string $nombre): void
    {
        $this->nombreActor = $nombre;
    }

    /**
     * Serializa a arreglo asociativo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'reclamacion_id' => $this->reclamacionId,
            'actor_id' => $this->actorId,
            'nombre_actor' => $this->nombreActor,
            'tipo_actuacion' => $this->tipoActuacion,
            'descripcion' => $this->descripcion,
            'medio_notificacion' => $this->medioNotificacion,
            'destinatario_notificacion' => $this->destinatarioNotificacion,
            'fecha_notificacion' => $this->fechaNotificacion,
            'documento_emitido_id' => $this->documentoEmitidoId,
            'archivo_adjunto_path' => $this->archivoAdjuntoPath,
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * Reconstruye la entidad desde arreglo de base de datos.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        $act = new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['reclamacion_id'] ?? 0),
            (int) ($datos['actor_id'] ?? 0),
            (string) ($datos['tipo_actuacion'] ?? ''),
            (string) ($datos['descripcion'] ?? ''),
            isset($datos['medio_notificacion']) && $datos['medio_notificacion'] !== '' ? (string) $datos['medio_notificacion'] : null,
            isset($datos['destinatario_notificacion']) && $datos['destinatario_notificacion'] !== '' ? (string) $datos['destinatario_notificacion'] : null,
            isset($datos['fecha_notificacion']) ? (string) $datos['fecha_notificacion'] : null,
            isset($datos['documento_emitido_id']) && $datos['documento_emitido_id'] !== null ? (int) $datos['documento_emitido_id'] : null,
            isset($datos['archivo_adjunto_path']) && $datos['archivo_adjunto_path'] !== '' ? (string) $datos['archivo_adjunto_path'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null
        );

        if (!empty($datos['nombre_actor'])) {
            $act->asignarNombreActor((string) $datos['nombre_actor']);
        }

        return $act;
    }
}
