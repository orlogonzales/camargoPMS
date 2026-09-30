<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de Dominio que representa un registro de Idempotencia del perímetro API.
 *
 * Identidad lógica: api_cliente_id + ruta + clave_idempotencia.
 * Garantiza que peticiones reintentadas devuelvan el mismo resultado sin duplicar operaciones.
 */
class ApiIdempotencia
{
    public const ESTADO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_COMPLETADO = 'COMPLETADO';
    public const ESTADO_ERROR = 'ERROR';

    public function __construct(
        private ?int $id,
        private int $apiClienteId,
        private string $claveIdempotencia,
        private string $ruta,
        private string $metodo,
        private string $cuerpoHash,
        private string $estado,
        private ?int $codigoHttp,
        private ?string $cabecerasJson,
        private ?string $respuestaJson,
        private string $bloqueadoHasta,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerApiClientId(): int
    {
        return $this->apiClienteId;
    }

    public function obtenerClaveIdempotencia(): string
    {
        return $this->claveIdempotencia;
    }

    public function obtenerRuta(): string
    {
        return $this->ruta;
    }

    public function obtenerMetodo(): string
    {
        return $this->metodo;
    }

    public function obtenerCuerpoHash(): string
    {
        return $this->cuerpoHash;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaCompletado(): bool
    {
        return $this->estado === self::ESTADO_COMPLETADO;
    }

    public function estaEnProceso(): bool
    {
        return $this->estado === self::ESTADO_PROCESANDO;
    }

    public function estaBloqueadoActualmente(): bool
    {
        return $this->estaEnProceso() && $this->bloqueadoHasta > date('Y-m-d H:i:s');
    }

    public function obtenerCodigoHttp(): ?int
    {
        return $this->codigoHttp;
    }

    public function obtenerCabecerasJson(): ?string
    {
        return $this->cabecerasJson;
    }

    /**
     * @return array<string, string>
     */
    public function obtenerCabeceras(): array
    {
        if ($this->cabecerasJson === null || $this->cabecerasJson === '') {
            return [];
        }

        $decoded = json_decode($this->cabecerasJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function obtenerRespuestaJson(): ?string
    {
        return $this->respuestaJson;
    }

    public function obtenerBloqueadoHasta(): string
    {
        return $this->bloqueadoHasta;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }
}
