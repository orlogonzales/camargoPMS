<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción base para errores ocurridos en el subsistema de auditoría y trazabilidad.
 */
class AuditoriaExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'Error en el sistema de auditoría.', int $codigo = 500, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigo, $anterior);
    }
}
