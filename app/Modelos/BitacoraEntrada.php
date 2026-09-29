<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

use InvalidArgumentException;

/**
 * Modelo de dominio que representa una entrada en el Libro de Guardia y Bitácora Operacional.
 *
 * Sigue los principios de gobernanza D-089 (BITÁCORA-1):
 * - BITÁCORA ≠ AUDITORÍA TÉCNICA ≠ TURNO CAJA ≠ MANTENIMIENTO ≠ HOUSEKEEPING.
 * - Contenido original inmutable. Las correcciones se realizan como enmiendas append-only.
 * - ANULAR ≠ DELETE. Las anulaciones preservan la trazabilidad histórica supervisada.
 */
class BitacoraEntrada
{
    public const TIPO_NOVEDAD = 'NOVEDAD';
    public const TIPO_CONSIGNA = 'CONSIGNA';
    public const TIPO_INCIDENCIA = 'INCIDENCIA';
    public const TIPO_RELEVO = 'RELEVO';
    public const TIPO_AVISO_GENERAL = 'AVISO_GENERAL';

    public const TIPOS_VALIDOS = [
        self::TIPO_NOVEDAD,
        self::TIPO_CONSIGNA,
        self::TIPO_INCIDENCIA,
        self::TIPO_RELEVO,
        self::TIPO_AVISO_GENERAL,
    ];

    public const PRIORIDAD_BAJA = 'BAJA';
    public const PRIORIDAD_MEDIA = 'MEDIA';
    public const PRIORIDAD_ALTA = 'ALTA';
    public const PRIORIDAD_URGENTE = 'URGENTE';

    public const PRIORIDADES_VALIDAS = [
        self::PRIORIDAD_BAJA,
        self::PRIORIDAD_MEDIA,
        self::PRIORIDAD_ALTA,
        self::PRIORIDAD_URGENTE,
    ];

    public const TURNO_MANANA = 'MANANA';
    public const TURNO_TARDE = 'TARDE';
    public const TURNO_NOCHE = 'NOCHE';
    public const TURNO_GENERAL = 'GENERAL';

    public const TURNOS_VALIDOS = [
        self::TURNO_MANANA,
        self::TURNO_TARDE,
        self::TURNO_NOCHE,
        self::TURNO_GENERAL,
    ];

    public const ESTADO_REGISTRADA = 'REGISTRADA';
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_RESUELTA = 'RESUELTA';
    public const ESTADO_ANULADA = 'ANULADA';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_REGISTRADA,
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_PROCESO,
        self::ESTADO_RESUELTA,
        self::ESTADO_ANULADA,
    ];

    private ?int $id;
    private int $propiedadId;
    private int $usuarioCreadorId;
    private string $tipo;
    private string $prioridad;
    private string $titulo;
    private string $contenido;
    private string $turno;
    private string $fechaOperativa;
    private string $estado;

    private ?int $unidadId;
    private ?int $reservaId;
    private ?int $estadiaId;
    private ?int $mantenimientoId;
    private ?int $sesionCajaId;

    private ?int $resueltaPorUsuarioId;
    private ?string $resueltaEn;
    private ?string $notaResolucion;

    private ?int $anuladaPorUsuarioId;
    private ?string $anuladaEn;
    private ?string $motivoAnulacion;

    private ?string $creadoEn;
    private ?string $actualizadoEn;

    // Metadatos presentacionales enriquecidos
    private ?string $propiedadNombre;
    private ?string $usuarioCreadorNombre;
    private ?string $unidadNumero;
    private ?string $resueltaPorNombre;
    private ?string $anuladaPorNombre;

    /** @var BitacoraSeguimiento[] */
    private array $seguimientos = [];

    public function __construct(
        ?int $id,
        int $propiedadId,
        int $usuarioCreadorId,
        string $tipo,
        string $prioridad,
        string $titulo,
        string $contenido,
        string $turno,
        string $fechaOperativa,
        string $estado = self::ESTADO_REGISTRADA,
        ?int $unidadId = null,
        ?int $reservaId = null,
        ?int $estadiaId = null,
        ?int $mantenimientoId = null,
        ?int $sesionCajaId = null,
        ?int $resueltaPorUsuarioId = null,
        ?string $resueltaEn = null,
        ?string $notaResolucion = null,
        ?int $anuladaPorUsuarioId = null,
        ?string $anuladaEn = null,
        ?string $motivoAnulacion = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null,
        ?string $propiedadNombre = null,
        ?string $usuarioCreadorNombre = null,
        ?string $unidadNumero = null,
        ?string $resueltaPorNombre = null,
        ?string $anuladaPorNombre = null,
        array $seguimientos = []
    ) {
        $this->validarTipo($tipo);
        $this->validarPrioridad($prioridad);
        $this->validarTurno($turno);
        $this->validarEstado($estado);

        if (trim($titulo) === '') {
            throw new InvalidArgumentException('El título de la entrada de bitácora no puede estar vacío.');
        }

        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El contenido relato de la entrada de bitácora no puede estar vacío.');
        }

        $this->id = $id;
        $this->propiedadId = $propiedadId;
        $this->usuarioCreadorId = $usuarioCreadorId;
        $this->tipo = $tipo;
        $this->prioridad = $prioridad;
        $this->titulo = trim($titulo);
        $this->contenido = trim($contenido);
        $this->turno = $turno;
        $this->fechaOperativa = $fechaOperativa;
        $this->estado = $estado;
        $this->unidadId = $unidadId;
        $this->reservaId = $reservaId;
        $this->estadiaId = $estadiaId;
        $this->mantenimientoId = $mantenimientoId;
        $this->sesionCajaId = $sesionCajaId;
        $this->resueltaPorUsuarioId = $resueltaPorUsuarioId;
        $this->resueltaEn = $resueltaEn;
        $this->notaResolucion = $notaResolucion !== null ? trim($notaResolucion) : null;
        $this->anuladaPorUsuarioId = $anuladaPorUsuarioId;
        $this->anuladaEn = $anuladaEn;
        $this->motivoAnulacion = $motivoAnulacion !== null ? trim($motivoAnulacion) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
        $this->propiedadNombre = $propiedadNombre;
        $this->usuarioCreadorNombre = $usuarioCreadorNombre;
        $this->unidadNumero = $unidadNumero;
        $this->resueltaPorNombre = $resueltaPorNombre;
        $this->anuladaPorNombre = $anuladaPorNombre;
        $this->seguimientos = $seguimientos;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPropiedadId(): int
    {
        return $this->propiedadId;
    }

    public function obtenerUsuarioCreadorId(): int
    {
        return $this->usuarioCreadorId;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function obtenerPrioridad(): string
    {
        return $this->prioridad;
    }

    public function obtenerTitulo(): string
    {
        return $this->titulo;
    }

    public function obtenerContenido(): string
    {
        return $this->contenido;
    }

    public function obtenerTurno(): string
    {
        return $this->turno;
    }

    public function obtenerFechaOperativa(): string
    {
        return $this->fechaOperativa;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function obtenerUnidadId(): ?int
    {
        return $this->unidadId;
    }

    public function obtenerReservaId(): ?int
    {
        return $this->reservaId;
    }

    public function obtenerEstadiaId(): ?int
    {
        return $this->estadiaId;
    }

    public function obtenerMantenimientoId(): ?int
    {
        return $this->mantenimientoId;
    }

    public function obtenerSesionCajaId(): ?int
    {
        return $this->sesionCajaId;
    }

    public function obtenerResueltaPorUsuarioId(): ?int
    {
        return $this->resueltaPorUsuarioId;
    }

    public function obtenerResueltaEn(): ?string
    {
        return $this->resueltaEn;
    }

    public function obtenerNotaResolucion(): ?string
    {
        return $this->notaResolucion;
    }

    public function obtenerAnuladaPorUsuarioId(): ?int
    {
        return $this->anuladaPorUsuarioId;
    }

    public function obtenerAnuladaEn(): ?string
    {
        return $this->anuladaEn;
    }

    public function obtenerMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function obtenerPropiedadNombre(): ?string
    {
        return $this->propiedadNombre;
    }

    public function obtenerUsuarioCreadorNombre(): ?string
    {
        return $this->usuarioCreadorNombre;
    }

    public function obtenerUnidadNumero(): ?string
    {
        return $this->unidadNumero;
    }

    public function obtenerResueltaPorNombre(): ?string
    {
        return $this->resueltaPorNombre;
    }

    public function obtenerAnuladaPorNombre(): ?string
    {
        return $this->anuladaPorNombre;
    }

    /**
     * @return BitacoraSeguimiento[]
     */
    public function obtenerSeguimientos(): array
    {
        return $this->seguimientos;
    }

    public function asignarSeguimientos(array $seguimientos): void
    {
        $this->seguimientos = $seguimientos;
    }

    public function estaResuelta(): bool
    {
        return $this->estado === self::ESTADO_RESUELTA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    public function esUrgente(): bool
    {
        return $this->prioridad === self::PRIORIDAD_URGENTE;
    }

    public function permiteSeguimiento(): bool
    {
        return $this->estado !== self::ESTADO_ANULADA;
    }

    public function permiteResolucion(): bool
    {
        return in_array($this->estado, [self::ESTADO_REGISTRADA, self::ESTADO_PENDIENTE, self::ESTADO_EN_PROCESO], true);
    }

    public function permiteReapertura(): bool
    {
        return $this->estado === self::ESTADO_RESUELTA;
    }

    public function permiteAnulacion(): bool
    {
        return $this->estado !== self::ESTADO_ANULADA;
    }

    private function validarTipo(string $tipo): void
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Tipo de bitácora no válido: "%s"', $tipo));
        }
    }

    private function validarPrioridad(string $prioridad): void
    {
        if (!in_array($prioridad, self::PRIORIDADES_VALIDAS, true)) {
            throw new InvalidArgumentException(sprintf('Prioridad de bitácora no válida: "%s"', $prioridad));
        }
    }

    private function validarTurno(string $turno): void
    {
        if (!in_array($turno, self::TURNOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Turno de bitácora no válido: "%s"', $turno));
        }
    }

    private function validarEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException(sprintf('Estado de bitácora no válido: "%s"', $estado));
        }
    }

    public static function fromArray(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['propiedad_id'] ?? 0),
            (int) ($datos['usuario_creador_id'] ?? 0),
            (string) ($datos['tipo'] ?? self::TIPO_NOVEDAD),
            (string) ($datos['prioridad'] ?? self::PRIORIDAD_MEDIA),
            (string) ($datos['titulo'] ?? ''),
            (string) ($datos['contenido'] ?? ''),
            (string) ($datos['turno'] ?? self::TURNO_GENERAL),
            (string) ($datos['fecha_operativa'] ?? date('Y-m-d')),
            (string) ($datos['estado'] ?? self::ESTADO_REGISTRADA),
            isset($datos['unidad_id']) && $datos['unidad_id'] !== null ? (int) $datos['unidad_id'] : null,
            isset($datos['reserva_id']) && $datos['reserva_id'] !== null ? (int) $datos['reserva_id'] : null,
            isset($datos['estadia_id']) && $datos['estadia_id'] !== null ? (int) $datos['estadia_id'] : null,
            isset($datos['mantenimiento_id']) && $datos['mantenimiento_id'] !== null ? (int) $datos['mantenimiento_id'] : null,
            isset($datos['sesion_caja_id']) && $datos['sesion_caja_id'] !== null ? (int) $datos['sesion_caja_id'] : null,
            isset($datos['resuelta_por_usuario_id']) && $datos['resuelta_por_usuario_id'] !== null ? (int) $datos['resuelta_por_usuario_id'] : null,
            $datos['resuelta_en'] ?? null,
            $datos['nota_resolucion'] ?? null,
            isset($datos['anulada_por_usuario_id']) && $datos['anulada_por_usuario_id'] !== null ? (int) $datos['anulada_por_usuario_id'] : null,
            $datos['anulada_en'] ?? null,
            $datos['motivo_anulacion'] ?? null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null,
            $datos['propiedad_nombre'] ?? null,
            $datos['usuario_creador_nombre'] ?? null,
            $datos['unidad_numero'] ?? null,
            $datos['resuelta_por_nombre'] ?? null,
            $datos['anulada_por_nombre'] ?? null,
            $datos['seguimientos'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'propiedad_id' => $this->propiedadId,
            'usuario_creador_id' => $this->usuarioCreadorId,
            'tipo' => $this->tipo,
            'prioridad' => $this->prioridad,
            'titulo' => $this->titulo,
            'contenido' => $this->contenido,
            'turno' => $this->turno,
            'fecha_operativa' => $this->fechaOperativa,
            'estado' => $this->estado,
            'unidad_id' => $this->unidadId,
            'reserva_id' => $this->reservaId,
            'estadia_id' => $this->estadiaId,
            'mantenimiento_id' => $this->mantenimientoId,
            'sesion_caja_id' => $this->sesionCajaId,
            'resuelta_por_usuario_id' => $this->resueltaPorUsuarioId,
            'resuelta_en' => $this->resueltaEn,
            'nota_resolucion' => $this->notaResolucion,
            'anulada_por_usuario_id' => $this->anuladaPorUsuarioId,
            'anulada_en' => $this->anuladaEn,
            'motivo_anulacion' => $this->motivoAnulacion,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'propiedad_nombre' => $this->propiedadNombre,
            'usuario_creador_nombre' => $this->usuarioCreadorNombre,
            'unidad_numero' => $this->unidadNumero,
            'resuelta_por_nombre' => $this->resueltaPorNombre,
            'anulada_por_nombre' => $this->anuladaPorNombre,
            'seguimientos' => array_map(
                static fn(BitacoraSeguimiento $s) => $s->toArray(),
                $this->seguimientos
            ),
        ];
    }
}
