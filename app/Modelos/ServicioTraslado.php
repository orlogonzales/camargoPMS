<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Modelo de dominio para la extensión operativa 1:1 de servicios de traslado y transfer (aeropuertos, terminales, etc.).
 */
class ServicioTraslado
{
    private ?int $id;
    private int $servicioContratadoId;
    private string $tipoTraslado; // 'LLEGADA' | 'SALIDA'
    private string $origen;
    private string $destino;
    private string $fechaHoraRecogida;
    private ?string $aerolineaEmpresa;
    private ?string $numeroVueloViaje;
    private int $cantidadPasajeros;
    private int $cantidadMaletas;
    private ?string $datosConductorVehiculo;
    private ?string $instruccionesRecogida;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        ?int $id,
        int $servicioContratadoId,
        string $tipoTraslado,
        string $origen,
        string $destino,
        string $fechaHoraRecogida,
        ?string $aerolineaEmpresa = null,
        ?string $numeroVueloViaje = null,
        int $cantidadPasajeros = 1,
        int $cantidadMaletas = 0,
        ?string $datosConductorVehiculo = null,
        ?string $instruccionesRecogida = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->id = $id;
        $this->servicioContratadoId = $servicioContratadoId;
        $this->tipoTraslado = strtoupper(trim($tipoTraslado)) === 'SALIDA' ? 'SALIDA' : 'LLEGADA';
        $this->origen = trim($origen);
        $this->destino = trim($destino);
        $this->fechaHoraRecogida = trim($fechaHoraRecogida);
        $this->aerolineaEmpresa = $aerolineaEmpresa !== null ? trim($aerolineaEmpresa) : null;
        $this->numeroVueloViaje = $numeroVueloViaje !== null ? trim(strtoupper($numeroVueloViaje)) : null;
        $this->cantidadPasajeros = max(1, $cantidadPasajeros);
        $this->cantidadMaletas = max(0, $cantidadMaletas);
        $this->datosConductorVehiculo = $datosConductorVehiculo !== null ? trim($datosConductorVehiculo) : null;
        $this->instruccionesRecogida = $instruccionesRecogida !== null ? trim($instruccionesRecogida) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerServicioContratadoId(): int
    {
        return $this->servicioContratadoId;
    }

    public function obtenerTipoTraslado(): string
    {
        return $this->tipoTraslado;
    }

    public function esLlegada(): bool
    {
        return $this->tipoTraslado === 'LLEGADA';
    }

    public function esSalida(): bool
    {
        return $this->tipoTraslado === 'SALIDA';
    }

    public function obtenerOrigen(): string
    {
        return $this->origen;
    }

    public function obtenerDestino(): string
    {
        return $this->destino;
    }

    public function obtenerFechaHoraRecogida(): string
    {
        return $this->fechaHoraRecogida;
    }

    public function obtenerAerolineaEmpresa(): ?string
    {
        return $this->aerolineaEmpresa;
    }

    public function obtenerNumeroVueloViaje(): ?string
    {
        return $this->numeroVueloViaje;
    }

    public function obtenerCantidadPasajeros(): int
    {
        return $this->cantidadPasajeros;
    }

    public function obtenerCantidadMaletas(): int
    {
        return $this->cantidadMaletas;
    }

    public function obtenerDatosConductorVehiculo(): ?string
    {
        return $this->datosConductorVehiculo;
    }

    public function obtenerInstruccionesRecogida(): ?string
    {
        return $this->instruccionesRecogida;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            (int) ($datos['servicio_contratado_id'] ?? 0),
            (string) ($datos['tipo_traslado'] ?? 'LLEGADA'),
            (string) ($datos['origen'] ?? ''),
            (string) ($datos['destino'] ?? ''),
            (string) ($datos['fecha_hora_recogida'] ?? ''),
            isset($datos['aerolinea_empresa']) ? (string) $datos['aerolinea_empresa'] : null,
            isset($datos['numero_vuelo_viaje']) ? (string) $datos['numero_vuelo_viaje'] : null,
            (int) ($datos['cantidad_pasajeros'] ?? 1),
            (int) ($datos['cantidad_maletas'] ?? 0),
            isset($datos['datos_conductor_vehiculo']) ? (string) $datos['datos_conductor_vehiculo'] : null,
            isset($datos['instrucciones_recogida']) ? (string) $datos['instrucciones_recogida'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function haciaArreglo(): array
    {
        return [
            'id' => $this->id,
            'servicio_contratado_id' => $this->servicioContratadoId,
            'tipo_traslado' => $this->tipoTraslado,
            'origen' => $this->origen,
            'destino' => $this->destino,
            'fecha_hora_recogida' => $this->fechaHoraRecogida,
            'aerolinea_empresa' => $this->aerolineaEmpresa,
            'numero_vuelo_viaje' => $this->numeroVueloViaje,
            'cantidad_pasajeros' => $this->cantidadPasajeros,
            'cantidad_maletas' => $this->cantidadMaletas,
            'datos_conductor_vehiculo' => $this->datosConductorVehiculo,
            'instrucciones_recogida' => $this->instruccionesRecogida,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return $this->haciaArreglo();
    }
}
