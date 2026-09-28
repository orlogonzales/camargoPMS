<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una Orden Operativa de Trabajo de Housekeeping.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingTarea
{
    public const TIPO_SALIDA = 'SALIDA';
    public const TIPO_ESTADIA = 'ESTADIA';
    public const TIPO_PROFUNDA = 'PROFUNDA';
    public const TIPO_RETOQUE = 'RETOQUE';

    public const PRIORIDAD_BAJA = 'BAJA';
    public const PRIORIDAD_MEDIA = 'MEDIA';
    public const PRIORIDAD_ALTA = 'ALTA';
    public const PRIORIDAD_URGENTE = 'URGENTE';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_ASIGNADA = 'ASIGNADA';
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_POR_INSPECCIONAR = 'POR_INSPECCIONAR';
    public const ESTADO_RECHAZADA = 'RECHAZADA';
    public const ESTADO_COMPLETADA = 'COMPLETADA';
    public const ESTADO_CANCELADA = 'CANCELADA';

    public const CONDICION_NINGUNA = 'NINGUNA';
    public const CONDICION_DND = 'DND';
    public const CONDICION_SIN_ACCESO = 'SIN_ACCESO';
    public const CONDICION_RECHAZO_HUESPED = 'RECHAZO_HUESPED';

    /**
     * @param HousekeepingChecklistItem[] $checklist
     * @param array $consumos
     */
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $propiedadId,
        private int $unidadId,
        private ?int $estadiaId = null,
        private string $tipoTarea = self::TIPO_SALIDA,
        private string $prioridad = self::PRIORIDAD_MEDIA,
        private string $estado = self::ESTADO_PENDIENTE,
        private ?int $camareraColaboradorId = null,
        private ?int $supervisorColaboradorId = null,
        private string $fechaProgramada = '',
        private ?string $iniciadoEn = null,
        private ?string $terminadoEn = null,
        private ?string $inspeccionadoEn = null,
        private string $condicionOperacional = self::CONDICION_NINGUNA,
        private ?string $notasOperario = null,
        private ?string $notasSupervisor = null,
        private ?string $motivoCancelacion = null,
        private int $creadoPorActorId = 1,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        private array $checklist = [],
        private array $consumos = []
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $checklist = [];
        if (isset($datos['checklist']) && is_array($datos['checklist'])) {
            foreach ($datos['checklist'] as $chk) {
                if ($chk instanceof HousekeepingChecklistItem) {
                    $checklist[] = $chk;
                } elseif (is_array($chk)) {
                    $checklist[] = HousekeepingChecklistItem::desdeArreglo($chk);
                }
            }
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['propiedad_id'] ?? 0),
            (int) ($datos['unidad_id'] ?? 0),
            isset($datos['estadia_id']) && $datos['estadia_id'] !== null ? (int) $datos['estadia_id'] : null,
            (string) ($datos['tipo_tarea'] ?? self::TIPO_SALIDA),
            (string) ($datos['prioridad'] ?? self::PRIORIDAD_MEDIA),
            (string) ($datos['estado'] ?? self::ESTADO_PENDIENTE),
            isset($datos['camarera_colaborador_id']) && $datos['camarera_colaborador_id'] !== null ? (int) $datos['camarera_colaborador_id'] : null,
            isset($datos['supervisor_colaborador_id']) && $datos['supervisor_colaborador_id'] !== null ? (int) $datos['supervisor_colaborador_id'] : null,
            (string) ($datos['fecha_programada'] ?? date('Y-m-d')),
            $datos['iniciado_en'] ?? null,
            $datos['terminado_en'] ?? null,
            $datos['inspeccionado_en'] ?? null,
            (string) ($datos['condicion_operacional'] ?? self::CONDICION_NINGUNA),
            $datos['notas_operario'] ?? null,
            $datos['notas_supervisor'] ?? null,
            $datos['motivo_cancelacion'] ?? null,
            (int) ($datos['creado_por_actor_id'] ?? 1),
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null,
            $checklist,
            $datos['consumos'] ?? []
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'propiedad_id' => $this->propiedadId,
            'unidad_id' => $this->unidadId,
            'estadia_id' => $this->estadiaId,
            'tipo_tarea' => $this->tipoTarea,
            'prioridad' => $this->prioridad,
            'estado' => $this->estado,
            'camarera_colaborador_id' => $this->camareraColaboradorId,
            'supervisor_colaborador_id' => $this->supervisorColaboradorId,
            'fecha_programada' => $this->fechaProgramada,
            'iniciado_en' => $this->iniciadoEn,
            'terminado_en' => $this->terminadoEn,
            'inspeccionado_en' => $this->inspeccionadoEn,
            'condicion_operacional' => $this->condicionOperacional,
            'notas_operario' => $this->notasOperario,
            'notas_supervisor' => $this->notasSupervisor,
            'motivo_cancelacion' => $this->motivoCancelacion,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'checklist' => array_map(fn($item) => $item instanceof HousekeepingChecklistItem ? $item->aArreglo() : $item, $this->checklist),
            'consumos' => $this->consumos,
        ];
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerPropiedadId(): int { return $this->propiedadId; }
    public function obtenerUnidadId(): int { return $this->unidadId; }
    public function obtenerEstadiaId(): ?int { return $this->estadiaId; }
    public function obtenerTipoTarea(): string { return $this->tipoTarea; }
    public function obtenerPrioridad(): string { return $this->prioridad; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerCamareraColaboradorId(): ?int { return $this->camareraColaboradorId; }
    public function obtenerSupervisorColaboradorId(): ?int { return $this->supervisorColaboradorId; }
    public function obtenerFechaProgramada(): string { return $this->fechaProgramada; }
    public function obtenerIniciadoEn(): ?string { return $this->iniciadoEn; }
    public function obtenerTerminadoEn(): ?string { return $this->terminadoEn; }
    public function obtenerInspeccionadoEn(): ?string { return $this->inspeccionadoEn; }
    public function obtenerCondicionOperacional(): string { return $this->condicionOperacional; }
    public function obtenerNotasOperario(): ?string { return $this->notasOperario; }
    public function obtenerNotasSupervisor(): ?string { return $this->notasSupervisor; }
    public function obtenerMotivoCancelacion(): ?string { return $this->motivoCancelacion; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }
    public function obtenerChecklist(): array { return $this->checklist; }
    public function obtenerConsumos(): array { return $this->consumos; }

    public function estaCompletada(): bool { return $this->estado === self::ESTADO_COMPLETADA; }
    public function estaCancelada(): bool { return $this->estado === self::ESTADO_CANCELADA; }
    public function estaEnProceso(): bool { return $this->estado === self::ESTADO_EN_PROCESO; }
}
