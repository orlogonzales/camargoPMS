<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa un turno o sesión de arqueo de caja física.
 */
class SesionCaja
{
    public function __construct(
        private ?int $id,
        private int $cajaFisicaId,
        private int $actorAperturaId,
        private ?int $actorCierreId,
        private string $montoApertura,
        private string $totalIngresosEfectivo,
        private string $totalEgresosEfectivo,
        private ?string $montoEsperado,
        private ?string $montoContadoDeclarado,
        private ?string $diferencia,
        private ?string $resultadoArqueo,
        private string $estado,
        private string $abiertaEn,
        private ?string $cerradaEn = null,
        private ?string $observacionesApertura = null,
        private ?string $observacionesCierre = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerCajaFisicaId(): int
    {
        return $this->cajaFisicaId;
    }

    public function obtenerActorAperturaId(): int
    {
        return $this->actorAperturaId;
    }

    public function obtenerActorCierreId(): ?int
    {
        return $this->actorCierreId;
    }

    public function obtenerMontoApertura(): string
    {
        return $this->montoApertura;
    }

    public function obtenerTotalIngresosEfectivo(): string
    {
        return $this->totalIngresosEfectivo;
    }

    public function obtenerTotalEgresosEfectivo(): string
    {
        return $this->totalEgresosEfectivo;
    }

    public function obtenerMontoEsperado(): ?string
    {
        return $this->montoEsperado;
    }

    public function obtenerMontoContadoDeclarado(): ?string
    {
        return $this->montoContadoDeclarado;
    }

    public function obtenerDiferencia(): ?string
    {
        return $this->diferencia;
    }

    public function obtenerResultadoArqueo(): ?string
    {
        return $this->resultadoArqueo;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaAbierta(): bool
    {
        return $this->estado === 'ABIERTA';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'CERRADA';
    }

    public function obtenerAbiertaEn(): string
    {
        return $this->abiertaEn;
    }

    public function obtenerCerradaEn(): ?string
    {
        return $this->cerradaEn;
    }

    public function obtenerObservacionesApertura(): ?string
    {
        return $this->observacionesApertura;
    }

    public function obtenerObservacionesCierre(): ?string
    {
        return $this->observacionesCierre;
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'caja_fisica_id' => $this->cajaFisicaId,
            'actor_apertura_id' => $this->actorAperturaId,
            'actor_cierre_id' => $this->actorCierreId,
            'monto_apertura' => $this->montoApertura,
            'total_ingresos_efectivo' => $this->totalIngresosEfectivo,
            'total_egresos_efectivo' => $this->totalEgresosEfectivo,
            'monto_esperado' => $this->montoEsperado,
            'monto_contado_declarado' => $this->montoContadoDeclarado,
            'diferencia' => $this->diferencia,
            'resultado_arqueo' => $this->resultadoArqueo,
            'estado' => $this->estado,
            'abierta_en' => $this->abiertaEn,
            'cerrada_en' => $this->cerradaEn,
            'observaciones_apertura' => $this->observacionesApertura,
            'observaciones_cierre' => $this->observacionesCierre,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['caja_fisica_id'] ?? 0),
            (int) ($datos['actor_apertura_id'] ?? 0),
            isset($datos['actor_cierre_id']) ? (int) $datos['actor_cierre_id'] : null,
            (string) ($datos['monto_apertura'] ?? '0.00'),
            (string) ($datos['total_ingresos_efectivo'] ?? '0.00'),
            (string) ($datos['total_egresos_efectivo'] ?? '0.00'),
            isset($datos['monto_esperado']) ? (string) $datos['monto_esperado'] : null,
            isset($datos['monto_contado_declarado']) ? (string) $datos['monto_contado_declarado'] : null,
            isset($datos['diferencia']) ? (string) $datos['diferencia'] : null,
            isset($datos['resultado_arqueo']) ? (string) $datos['resultado_arqueo'] : null,
            (string) ($datos['estado'] ?? 'ABIERTA'),
            (string) ($datos['abierta_en'] ?? ''),
            isset($datos['cerrada_en']) ? (string) $datos['cerrada_en'] : null,
            isset($datos['observaciones_apertura']) ? (string) $datos['observaciones_apertura'] : null,
            isset($datos['observaciones_cierre']) ? (string) $datos['observaciones_cierre'] : null
        );
    }
}
