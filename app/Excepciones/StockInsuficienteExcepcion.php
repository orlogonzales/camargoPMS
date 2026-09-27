<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una operación intenta retirar o transferir más existencias de las disponibles.
 */
class StockInsuficienteExcepcion extends RuntimeException
{
    private string $articuloSku;
    private string $ubicacionCodigo;
    private string $cantidadSolicitada;
    private string $cantidadDisponible;

    public function __construct(
        string $articuloSku,
        string $ubicacionCodigo,
        string $cantidadSolicitada,
        string $cantidadDisponible,
        string $mensaje = ''
    ) {
        $this->articuloSku = $articuloSku;
        $this->ubicacionCodigo = $ubicacionCodigo;
        $this->cantidadSolicitada = $cantidadSolicitada;
        $this->cantidadDisponible = $cantidadDisponible;

        $msg = $mensaje !== ''
            ? $mensaje
            : "Stock insuficiente para el artículo [{$articuloSku}] en la ubicación [{$ubicacionCodigo}]. Solicitado: {$cantidadSolicitada}, Disponible: {$cantidadDisponible}.";

        parent::__construct($msg, 422);
    }

    public function obtenerArticuloSku(): string
    {
        return $this->articuloSku;
    }

    public function obtenerUbicacionCodigo(): string
    {
        return $this->ubicacionCodigo;
    }

    public function obtenerCantidadSolicitada(): string
    {
        return $this->cantidadSolicitada;
    }

    public function obtenerCantidadDisponible(): string
    {
        return $this->cantidadDisponible;
    }
}
