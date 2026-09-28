<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * DTO que proyecta la condición operacional derivada de una unidad física (D-083).
 * NO es una entidad soberana en base de datos. Se deriva en vivo a partir de:
 * ESTADO_COMERCIAL (unidades.estado)
 * + ESTADO_OCUPACION (estadias en curso)
 * + ESTADO_LIMPIEZA (housekeeping_unidades_limpieza)
 * + MANTENIMIENTO (inventario_diario_unidades / mantenimiento_ordenes)
 */
class HousekeepingDerivacionOperativa
{
    public const CONDICION_VR = 'VR';   // Vacant Ready (Apta para Check-in)
    public const CONDICION_VD = 'VD';   // Vacant Dirty
    public const CONDICION_VCL = 'VCL'; // Vacant Clean (Limpia por inspeccionar)
    public const CONDICION_OD = 'OD';   // Occupied Dirty
    public const CONDICION_OC = 'OC';   // Occupied Clean
    public const CONDICION_OOO = 'OOO'; // Out of Order (Mantenimiento bloqueante)
    public const CONDICION_OOS = 'OOS'; // Out of Service (Inactiva o Retoque crítico)

    public function __construct(
        private int $unidadId,
        private string $unidadNumero,
        private int $propiedadId,
        private string $propiedadNombre,
        private ?int $piso,
        private string $tipoUnidadNombre,
        private string $estadoUnidadComercial, // 'ACTIVO', 'INACTIVO', etc.
        private string $estadoOcupacion,        // 'DESOCUPADA', 'OCUPADA'
        private ?int $estadiaActivaId,
        private string $estadoLimpieza,         // 'SUCIA', 'EN_LIMPIEZA', 'LIMPIA_POR_INSPECCIONAR', 'LIMPIA_INSPECCIONADA', 'RETOQUE_REQUERIDO'
        private bool $tieneBloqueoMantenimiento,
        private ?string $motivoBloqueo,
        private string $condicionDerivada,       // 'VR', 'VD', 'VCL', 'OD', 'OC', 'OOO', 'OOS'
        private bool $aptaParaCheckin,
        private ?int $tareaActivaId = null,
        private ?string $tareaActivaCodigo = null,
        private ?string $tareaActivaEstado = null,
        private ?string $camareraNombre = null
    ) {
    }

    public static function derivar(
        array $unidad,
        ?array $limpieza,
        bool $tieneEstadiaActiva,
        ?int $estadiaId,
        bool $tieneBloqueoMantenimiento,
        ?string $motivoBloqueo = null,
        ?array $tareaActiva = null
    ): self {
        $estadoComercial = (string) ($unidad['estado'] ?? 'ACTIVO');
        $estadoLimpieza = (string) ($limpieza['estado_limpieza'] ?? HousekeepingEstadoLimpieza::ESTADO_SUCIA);
        $ocupacion = $tieneEstadiaActiva ? 'OCUPADA' : 'DESOCUPADA';

        $condicion = self::CONDICION_VD;
        $aptaCheckin = false;

        if ($estadoComercial !== 'ACTIVO') {
            $condicion = self::CONDICION_OOS;
            $aptaCheckin = false;
        } elseif ($tieneBloqueoMantenimiento) {
            $condicion = self::CONDICION_OOO;
            $aptaCheckin = false;
        } elseif ($tieneEstadiaActiva) {
            if ($estadoLimpieza === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA ||
                $estadoLimpieza === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR) {
                $condicion = self::CONDICION_OC;
            } else {
                $condicion = self::CONDICION_OD;
            }
            $aptaCheckin = false; // Ya está ocupada
        } else {
            // Desocupada
            if ($estadoLimpieza === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_INSPECCIONADA) {
                $condicion = self::CONDICION_VR;
                $aptaCheckin = true;
            } elseif ($estadoLimpieza === HousekeepingEstadoLimpieza::ESTADO_LIMPIA_POR_INSPECCIONAR) {
                $condicion = self::CONDICION_VCL;
                $aptaCheckin = false;
            } elseif ($estadoLimpieza === HousekeepingEstadoLimpieza::ESTADO_RETOQUE_REQUERIDO) {
                $condicion = self::CONDICION_VD;
                $aptaCheckin = false;
            } else {
                $condicion = self::CONDICION_VD;
                $aptaCheckin = false;
            }
        }

        return new self(
            (int) $unidad['id'],
            (string) ($unidad['numero'] ?? ''),
            (int) ($unidad['propiedad_id'] ?? 0),
            (string) ($unidad['propiedad_nombre'] ?? ''),
            isset($unidad['piso']) && $unidad['piso'] !== null ? (int) $unidad['piso'] : null,
            (string) ($unidad['tipo_unidad_nombre'] ?? ''),
            $estadoComercial,
            $ocupacion,
            $estadiaId,
            $estadoLimpieza,
            $tieneBloqueoMantenimiento,
            $motivoBloqueo,
            $condicion,
            $aptaCheckin,
            isset($tareaActiva['id']) ? (int) $tareaActiva['id'] : null,
            $tareaActiva['codigo'] ?? null,
            $tareaActiva['estado'] ?? null,
            $tareaActiva['camarera_nombre'] ?? null
        );
    }

    public function aArreglo(): array
    {
        return [
            'unidad_id' => $this->unidadId,
            'unidad_numero' => $this->unidadNumero,
            'propiedad_id' => $this->propiedadId,
            'propiedad_nombre' => $this->propiedadNombre,
            'piso' => $this->piso,
            'tipo_unidad_nombre' => $this->tipoUnidadNombre,
            'estado_unidad_comercial' => $this->estadoUnidadComercial,
            'estado_ocupacion' => $this->estadoOcupacion,
            'estadia_activa_id' => $this->estadiaActivaId,
            'estado_limpieza' => $this->estadoLimpieza,
            'tiene_bloqueo_mantenimiento' => $this->tieneBloqueoMantenimiento,
            'motivo_bloqueo' => $this->motivoBloqueo,
            'condicion_derivada' => $this->condicionDerivada,
            'apta_para_checkin' => $this->aptaParaCheckin,
            'tarea_activa_id' => $this->tareaActivaId,
            'tarea_activa_codigo' => $this->tareaActivaCodigo,
            'tarea_activa_estado' => $this->tareaActivaEstado,
            'camarera_nombre' => $this->camareraNombre,
        ];
    }

    public function obtenerUnidadId(): int { return $this->unidadId; }
    public function obtenerUnidadNumero(): string { return $this->unidadNumero; }
    public function obtenerPropiedadId(): int { return $this->propiedadId; }
    public function obtenerPiso(): ?int { return $this->piso; }
    public function obtenerCondicionDerivada(): string { return $this->condicionDerivada; }
    public function esAptaParaCheckin(): bool { return $this->aptaParaCheckin; }
    public function obtenerEstadoLimpieza(): string { return $this->estadoLimpieza; }
    public function obtenerEstadoOcupacion(): string { return $this->estadoOcupacion; }
}
