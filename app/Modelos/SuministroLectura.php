<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Entidad de dominio que representa una lectura física inmutable de un medidor (SUMINISTROS-1 / D-081).
 *
 * Características:
 * - Append-only estricto: cero UPDATE sobre valor_lectura.
 * - Correcciones mediante encadenamiento referencial auditado con motivo obligatorio.
 * - Tipos de evento: ORDINARIA, CORTE_CONTRACTUAL, CORTE_TARIFARIO, INSTALACION, RETIRO, REINICIO_DIAL, CORRECCION.
 */
class SuministroLectura
{
    public const EVENTO_ORDINARIA = 'ORDINARIA';
    public const EVENTO_CORTE_CONTRACTUAL = 'CORTE_CONTRACTUAL';
    public const EVENTO_CORTE_TARIFARIO = 'CORTE_TARIFARIO';
    public const EVENTO_INSTALACION = 'INSTALACION';
    public const EVENTO_RETIRO = 'RETIRO';
    public const EVENTO_REINICIO_DIAL = 'REINICIO_DIAL';
    public const EVENTO_CORRECCION = 'CORRECCION';
    public const EVENTO_PERIODICA = self::EVENTO_ORDINARIA;
    public const EVENTO_CAMBIO_MEDIDOR = self::EVENTO_RETIRO;

    public const ESTADO_VALIDA = 'VALIDA';
    public const ESTADO_CORREGIDA = 'CORREGIDA';
    public const ESTADO_ANULADA = 'ANULADA';
    public const ESTADO_VIGENTE = self::ESTADO_VALIDA;

    public function __construct(
        private ?int $id,
        private int $medidorId,
        private string $fechaLectura,
        private ?string $horaLectura,
        private string $valorLectura,
        private string $tipoEvento = self::EVENTO_ORDINARIA,
        private ?int $lecturaReferenciaId = null,
        private ?string $motivo = null,
        private ?int $arrendamientoId = null,
        private string $estado = self::ESTADO_VALIDA,
        private ?int $registradoPorActorId = null,
        private ?string $creadoEn = null,
        // Proyecciones
        private ?string $medidorCodigo = null,
        private ?string $suministroNombre = null,
        private ?string $unidadCodigo = null,
        private ?string $registradoPorNombre = null
    ) {
    }

    public static function desdeArreglo(array $datos): self
    {
        $estado = (string) ($datos['estado'] ?? self::ESTADO_VALIDA);
        if ($estado === 'VIGENTE') {
            $estado = self::ESTADO_VALIDA;
        }

        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['medidor_id'] ?? 0),
            (string) ($datos['fecha_lectura'] ?? ''),
            isset($datos['hora_lectura']) && $datos['hora_lectura'] !== '' ? (string) $datos['hora_lectura'] : null,
            (string) ($datos['valor_lectura'] ?? '0.0000'),
            (string) ($datos['tipo_evento'] ?? $datos['evento'] ?? self::EVENTO_ORDINARIA),
            isset($datos['lectura_referencia_id']) && $datos['lectura_referencia_id'] !== '' ? (int) $datos['lectura_referencia_id'] : null,
            isset($datos['motivo']) && $datos['motivo'] !== '' ? (string) $datos['motivo'] : null,
            isset($datos['arrendamiento_id']) && $datos['arrendamiento_id'] !== '' ? (int) $datos['arrendamiento_id'] : null,
            $estado,
            isset($datos['registrado_por_actor_id']) ? (int) $datos['registrado_por_actor_id'] : (isset($datos['actor_id']) ? (int) $datos['actor_id'] : null),
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['medidor_codigo']) ? (string) $datos['medidor_codigo'] : null,
            isset($datos['suministro_nombre']) ? (string) $datos['suministro_nombre'] : null,
            isset($datos['unidad_codigo']) ? (string) $datos['unidad_codigo'] : null,
            isset($datos['registrado_por_nombre']) ? (string) $datos['registrado_por_nombre'] : null
        );
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'medidor_id' => $this->medidorId,
            'fecha_lectura' => $this->fechaLectura,
            'hora_lectura' => $this->horaLectura,
            'valor_lectura' => $this->valorLectura,
            'tipo_evento' => $this->tipoEvento,
            'lectura_referencia_id' => $this->lecturaReferenciaId,
            'motivo' => $this->motivo,
            'arrendamiento_id' => $this->arrendamientoId,
            'estado' => $this->estado,
            'registrado_por_actor_id' => $this->registradoPorActorId,
            'creado_en' => $this->creadoEn,
            'medidor_codigo' => $this->medidorCodigo,
            'suministro_nombre' => $this->suministroNombre,
            'unidad_codigo' => $this->unidadCodigo,
            'registrado_por_nombre' => $this->registradoPorNombre,
        ];
    }

    public function toArray(): array
    {
        return $this->aArreglo();
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerMedidorId(): int
    {
        return $this->medidorId;
    }

    public function obtenerFechaLectura(): string
    {
        return $this->fechaLectura;
    }

    public function obtenerHoraLectura(): ?string
    {
        return $this->horaLectura;
    }

    public function obtenerValorLectura(): string
    {
        return $this->valorLectura;
    }

    public function obtenerTipoEvento(): string
    {
        return $this->tipoEvento;
    }

    public function obtenerLecturaReferenciaId(): ?int
    {
        return $this->lecturaReferenciaId;
    }

    public function obtenerMotivo(): ?string
    {
        return $this->motivo;
    }

    public function obtenerArrendamientoId(): ?int
    {
        return $this->arrendamientoId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaValida(): bool
    {
        return $this->estado === self::ESTADO_VALIDA;
    }

    public function esVigente(): bool
    {
        return $this->estaValida();
    }

    public function estaCorregida(): bool
    {
        return $this->estado === self::ESTADO_CORREGIDA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    public function esCorteContractual(): bool
    {
        return $this->tipoEvento === self::EVENTO_CORTE_CONTRACTUAL;
    }

    public function esCorteTarifario(): bool
    {
        return $this->tipoEvento === self::EVENTO_CORTE_TARIFARIO;
    }

    public function esInstalacion(): bool
    {
        return $this->tipoEvento === self::EVENTO_INSTALACION;
    }

    public function esRetiro(): bool
    {
        return $this->tipoEvento === self::EVENTO_RETIRO;
    }

    public function esReinicioDial(): bool
    {
        return $this->tipoEvento === self::EVENTO_REINICIO_DIAL;
    }

    public function esCorreccion(): bool
    {
        return $this->tipoEvento === self::EVENTO_CORRECCION;
    }

    public function obtenerRegistradoPorActorId(): ?int
    {
        return $this->registradoPorActorId;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerMedidorCodigo(): ?string
    {
        return $this->medidorCodigo;
    }

    public function obtenerSuministroNombre(): ?string
    {
        return $this->suministroNombre;
    }

    public function obtenerUnidadCodigo(): ?string
    {
        return $this->unidadCodigo;
    }

    public function obtenerRegistradoPorNombre(): ?string
    {
        return $this->registradoPorNombre;
    }
}
