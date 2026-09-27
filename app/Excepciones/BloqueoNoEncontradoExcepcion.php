<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando no se localiza un registro de bloqueo de unidad por su ID.
 */
class BloqueoNoEncontradoExcepcion extends Exception
{
    private int $bloqueoId;

    public function __construct(int $bloqueoId, string $mensaje = '', int $codigoHttp = 404, ?Throwable $anterior = null)
    {
        $this->bloqueoId = $bloqueoId;
        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "No se encontró el bloqueo de unidad con ID {$bloqueoId}.";

        parent::__construct($mensajeFinal, $codigoHttp, $anterior);
    }

    public function obtenerBloqueoId(): int
    {
        return $this->bloqueoId;
    }
}
