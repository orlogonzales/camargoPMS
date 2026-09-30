<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción de dominio para conflictos de concurrencia en la sincronización de conexiones iCalendar.
 *
 * Se emite cuando una sincronización no puede iniciarse debido a que ya existe una
 * ejecución activa en curso o un bloqueo cooperativo MySQL GET_LOCK para la misma conexión.
 */
class ConexionIcalEnSincronizacionExcepcion extends RuntimeException
{
    protected $code = 409;
    private string $codigoError;

    public function __construct(
        string $mensaje = 'Ya existe una sincronización en curso para esta conexión.',
        string $codigoError = 'CONEXION_EN_SINCRONIZACION',
        int $code = 409,
        ?Throwable $previous = null
    ) {
        parent::__construct($mensaje, $code, $previous);
        $this->codigoError = $codigoError;
    }

    public function obtenerCodigoError(): string
    {
        return $this->codigoError;
    }
}
