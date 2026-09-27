<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;

/**
 * Excepción de dominio lanzada cuando una operación requiere un parámetro
 * de configuración operacional que no ha sido definido por el negocio (HTTP 422).
 *
 * Principio vinculante (RESERVAS-1A):
 * - No inventar valores por defecto ni fallbacks arbitrarios en el código.
 * - Si un parámetro es indispensable para la operación (ej. reservas.duracion_hold_minutos),
 *   se debe exigir su configuración explícita previa.
 */
class ConfiguracionFaltanteExcepcion extends Exception
{
    private string $claveParametro;

    public function __construct(string $claveParametro, string $mensaje = '', int $codigo = 422)
    {
        $this->claveParametro = $claveParametro;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "El parámetro de configuración operacional '{$claveParametro}' no ha sido definido por el negocio.";
        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerClaveParametro(): string
    {
        return $this->claveParametro;
    }
}
