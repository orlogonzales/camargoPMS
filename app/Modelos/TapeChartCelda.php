<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de datos en memoria para una celda individual del Tape Chart (TAPE-CHART-1 / D-084).
 *
 * Representa la proyección operacional de una unidad en una fecha hotelera específica.
 * Principio vinculante: TAPE CHART != FUENTE DE VERDAD.
 * La prioridad visual no destruye información secundaria ni conflictos.
 */
final class TapeChartCelda
{
    /** @var string Estados principales canónicos */
    public const ESTADO_VACANTE = 'VACANTE';
    public const ESTADO_ESTADIA = 'ESTADIA';
    public const ESTADO_RESERVA = 'RESERVA';
    public const ESTADO_ARRENDAMIENTO = 'ARRENDAMIENTO';
    public const ESTADO_MANTENIMIENTO_OOO = 'MANTENIMIENTO_OOO';
    public const ESTADO_BLOQUEO_MANUAL = 'BLOQUEO_MANUAL';
    public const ESTADO_HOLD_PENDIENTE = 'HOLD_PENDIENTE';

    /**
     * @param string $fecha Fecha hotelera local (Y-m-d)
     * @param string $estadoPrincipal Estado operacional dominante
     * @param string $subestado Calificador específico (VR, VD, VCL, ROTACION, etc.)
     * @param string|null $codigoReferencia Código visible (ej. EST-202609-0012, RES-202609-0045)
     * @param int|null $referenciaId ID primario de la entidad soberana
     * @param string|null $origenTipo Tipo de origen ('ESTADIA', 'RESERVA', 'ARRENDAMIENTO', etc.)
     * @param string|null $titularNombre Apellido y nombre abreviado del titular
     * @param string|null $titularDocumento Tipo y número de documento (DNI 12345678)
     * @param int $duracionNoches Cantidad total de noches de la estancia
     * @param int $nocheIndice Índice de noche (1-based: noche 1 de N)
     * @param bool $esInicioBloque Indica si la celda es el primer día visible del bloque
     * @param bool $esFinBloque Indica si la celda es el último día visible del bloque
     * @param bool $esArrival Indica si en esta fecha hay check-in previsto o realizado
     * @param bool $esDeparture Indica si en esta fecha hay check-out previsto o realizado
     * @param bool $esStayover Indica si en esta fecha el huésped permanece alojado (noche intermedia)
     * @param array<string> $indicadoresSecundarios Señales adicionales no dominantes
     * @param array<string> $conflictos Conflictos operacionales concurrentes
     * @param array<string> $accionesCandidatas Operaciones autorizadas según RBAC
     * @param string $claseColor Clases CSS de color suaves oficiales de Alina
     */
    public function __construct(
        private string $fecha,
        private string $estadoPrincipal,
        private string $subestado = '',
        private ?string $codigoReferencia = null,
        private ?int $referenciaId = null,
        private ?string $origenTipo = null,
        private ?string $titularNombre = null,
        private ?string $titularDocumento = null,
        private int $duracionNoches = 1,
        private int $nocheIndice = 1,
        private bool $esInicioBloque = true,
        private bool $esFinBloque = true,
        private bool $esArrival = false,
        private bool $esDeparture = false,
        private bool $esStayover = false,
        private array $indicadoresSecundarios = [],
        private array $conflictos = [],
        private array $accionesCandidatas = [],
        private string $claseColor = 'tape-celda-vacante'
    ) {
    }

    public function obtenerFecha(): string
    {
        return $this->fecha;
    }

    public function obtenerEstadoPrincipal(): string
    {
        return $this->estadoPrincipal;
    }

    public function obtenerSubestado(): string
    {
        return $this->subestado;
    }

    public function obtenerCodigoReferencia(): ?string
    {
        return $this->codigoReferencia;
    }

    public function obtenerReferenciaId(): ?int
    {
        return $this->referenciaId;
    }

    public function obtenerOrigenTipo(): ?string
    {
        return $this->origenTipo;
    }

    public function obtenerTitularNombre(): ?string
    {
        return $this->titularNombre;
    }

    public function obtenerTitularDocumento(): ?string
    {
        return $this->titularDocumento;
    }

    public function obtenerDuracionNoches(): int
    {
        return $this->duracionNoches;
    }

    public function obtenerNocheIndice(): int
    {
        return $this->nocheIndice;
    }

    public function esInicioBloque(): bool
    {
        return $this->esInicioBloque;
    }

    public function esFinBloque(): bool
    {
        return $this->esFinBloque;
    }

    public function esArrival(): bool
    {
        return $this->esArrival;
    }

    public function esDeparture(): bool
    {
        return $this->esDeparture;
    }

    public function esStayover(): bool
    {
        return $this->esStayover;
    }

    /**
     * @return array<string>
     */
    public function obtenerIndicadoresSecundarios(): array
    {
        return $this->indicadoresSecundarios;
    }

    /**
     * @return array<string>
     */
    public function obtenerConflictos(): array
    {
        return $this->conflictos;
    }

    public function tieneConflictos(): bool
    {
        return !empty($this->conflictos);
    }

    /**
     * @return array<string>
     */
    public function obtenerAccionesCandidatas(): array
    {
        return $this->accionesCandidatas;
    }

    public function obtenerClaseColor(): string
    {
        return $this->claseColor;
    }

    /**
     * Serializa la celda a un arreglo para respuesta JSON en el frontend.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'fecha' => $this->fecha,
            'estado_principal' => $this->estadoPrincipal,
            'subestado' => $this->subestado,
            'codigo_referencia' => $this->codigoReferencia,
            'referencia_id' => $this->referenciaId,
            'origen_tipo' => $this->origenTipo,
            'titular_nombre' => $this->titularNombre,
            'titular_documento' => $this->titularDocumento,
            'duracion_noches' => $this->duracionNoches,
            'noche_indice' => $this->nocheIndice,
            'es_inicio_bloque' => $this->esInicioBloque,
            'es_fin_bloque' => $this->esFinBloque,
            'es_arrival' => $this->esArrival,
            'es_departure' => $this->esDeparture,
            'es_stayover' => $this->esStayover,
            'indicadores_secundarios' => $this->indicadoresSecundarios,
            'conflictos' => $this->conflictos,
            'acciones_candidatas' => $this->accionesCandidatas,
            'clase_color' => $this->claseColor,
        ];
    }
}
