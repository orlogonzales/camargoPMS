<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Data Transfer Object (DTO) Neutral para Eventos iCalendar Normalizados.
 *
 * Encapsula la información extraída de los VEVENTs sin acoplamiento a librerías
 * externas de calendario (Sabre/VObject).
 */
class EventoIcalDTO
{
    public function __construct(
        public readonly string $uid,
        public readonly string $fechaInicio, // 'YYYY-MM-DD'
        public readonly string $fechaFin,    // 'YYYY-MM-DD' semántica [inicio, fin)
        public readonly int $noches,
        public readonly string $resumen,
        public readonly ?string $descripcion = null,
        public readonly string $estadoEvento = 'ACTIVO', // 'ACTIVO', 'CANCELADO'
        public readonly ?string $ultimaModificacion = null, // 'YYYY-MM-DD HH:MM:SS'
        public readonly ?int $secuencia = null,
        public readonly bool $esRecurrente = false,
        public readonly ?string $recurrenciaRrule = null
    ) {
    }

    /**
     * Convierte el DTO a array asociativo estándar.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'uid' => $this->uid,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'noches' => $this->noches,
            'resumen' => $this->resumen,
            'descripcion' => $this->descripcion,
            'estado_evento' => $this->estadoEvento,
            'ultima_modificacion' => $this->ultimaModificacion,
            'secuencia' => $this->secuencia,
            'es_recurrente' => $this->esRecurrente,
            'recurrencia_rrule' => $this->recurrenciaRrule,
        ];
    }
}
