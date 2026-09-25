<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando se supera el umbral de intentos fallidos de autenticación (Throttling / Rate Limit).
 */
class DemasiadosIntentosExcepcion extends DomainException
{
    private int $segundosEspera;

    public function __construct(int $segundosEspera = 900, string $mensaje = '', int $codigo = 429)
    {
        $this->segundosEspera = $segundosEspera;
        $minutos = (int) ceil($segundosEspera / 60);

        $mensajeFinal = $mensaje !== ''
            ? $mensaje
            : "Demasiados intentos fallidos. Por seguridad, espere {$minutos} minuto(s) antes de reintentar.";

        parent::__construct($mensajeFinal, $codigo);
    }

    public function obtenerSegundosEspera(): int
    {
        return $this->segundosEspera;
    }
}
