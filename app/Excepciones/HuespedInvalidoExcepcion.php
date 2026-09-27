<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Exception;
use Throwable;

/**
 * Excepción lanzada cuando la lista de huéspedes o la designación del huésped responsable es inválida (HTTP 422).
 * Por ejemplo: 0 huéspedes, 0 responsables, >1 responsables, o huésped duplicado.
 */
class HuespedInvalidoExcepcion extends Exception
{
    private string $campo;

    public function __construct(string $mensaje, string $campo = 'huespedes', int $codigoHttp = 422, ?Throwable $anterior = null)
    {
        $this->campo = $campo;
        parent::__construct($mensaje, $codigoHttp, $anterior);
    }

    public function obtenerCampo(): string
    {
        return $this->campo;
    }
}
