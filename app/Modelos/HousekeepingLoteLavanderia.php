<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un Lote de Despacho y Retorno de Lavandería Textil.
 * HOUSEKEEPING-1 / D-083.
 */
class HousekeepingLoteLavanderia
{
    public const ESTADO_DESPACHADO = 'DESPACHADO';
    public const ESTADO_RETORNADO_TOTAL = 'RETORNADO_TOTAL';
    public const ESTADO_RETORNADO_PARCIAL = 'RETORNADO_PARCIAL';
    public const ESTADO_CON_DISCREPANCIA = 'CON_DISCREPANCIA';
    public const ESTADO_ANULADO = 'ANULADO';

    /**
     * @param HousekeepingLoteLinea[] $lineas
     */
    public function __construct(
        private ?int $id,
        private string $codigo,
        private int $propiedadId,
        private int $almacenOrigenId,
        private int $ubicacionLavanderiaId,
        private string $fechaDespacho,
        private ?string $fechaRetornoEstimada = null,
        private ?string $fechaRetornoReal = null,
        private string $estado = self::ESTADO_DESPACHADO,
        private ?string $notasDespacho = null,
        private ?string $notasRetorno = null,
        private int $creadoPorActorId = 1,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null,
        private array $lineas = []
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $lineas = [];
        if (isset($datos['lineas']) && is_array($datos['lineas'])) {
            foreach ($datos['lineas'] as $l) {
                if ($l instanceof HousekeepingLoteLinea) {
                    $lineas[] = $l;
                } elseif (is_array($l)) {
                    $lineas[] = HousekeepingLoteLinea::desdeArreglo($l);
                }
            }
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (string) ($datos['codigo'] ?? ''),
            (int) ($datos['propiedad_id'] ?? 0),
            (int) ($datos['almacen_origen_id'] ?? 0),
            (int) ($datos['ubicacion_lavanderia_id'] ?? 0),
            (string) ($datos['fecha_despacho'] ?? date('Y-m-d')),
            $datos['fecha_retorno_estimada'] ?? null,
            $datos['fecha_retorno_real'] ?? null,
            (string) ($datos['estado'] ?? self::ESTADO_DESPACHADO),
            $datos['notas_despacho'] ?? null,
            $datos['notas_retorno'] ?? null,
            (int) ($datos['creado_por_actor_id'] ?? 1),
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null,
            $lineas
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'propiedad_id' => $this->propiedadId,
            'almacen_origen_id' => $this->almacenOrigenId,
            'ubicacion_lavanderia_id' => $this->ubicacionLavanderiaId,
            'fecha_despacho' => $this->fechaDespacho,
            'fecha_retorno_estimada' => $this->fechaRetornoEstimada,
            'fecha_retorno_real' => $this->fechaRetornoReal,
            'estado' => $this->estado,
            'notas_despacho' => $this->notasDespacho,
            'notas_retorno' => $this->notasRetorno,
            'creado_por_actor_id' => $this->creadoPorActorId,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'lineas' => array_map(fn($linea) => $linea instanceof HousekeepingLoteLinea ? $linea->aArreglo() : $linea, $this->lineas),
        ];
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerCodigo(): string { return $this->codigo; }
    public function obtenerPropiedadId(): int { return $this->propiedadId; }
    public function obtenerAlmacenOrigenId(): int { return $this->almacenOrigenId; }
    public function obtenerUbicacionLavanderiaId(): int { return $this->ubicacionLavanderiaId; }
    public function obtenerFechaDespacho(): string { return $this->fechaDespacho; }
    public function obtenerFechaRetornoEstimada(): ?string { return $this->fechaRetornoEstimada; }
    public function obtenerFechaRetornoReal(): ?string { return $this->fechaRetornoReal; }
    public function obtenerEstado(): string { return $this->estado; }
    public function obtenerNotasDespacho(): ?string { return $this->notasDespacho; }
    public function obtenerNotasRetorno(): ?string { return $this->notasRetorno; }
    public function obtenerCreadoPorActorId(): int { return $this->creadoPorActorId; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
    public function obtenerActualizadoEn(): ?string { return $this->actualizadoEn; }
    public function obtenerLineas(): array { return $this->lineas; }
}
