<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Throwable;

/**
 * Excepción lanzada al intentar registrar un actor con un código o usuario_id ya existente.
 */
class ActorDuplicadoExcepcion extends AuditoriaExcepcion
{
    public function __construct(string $campo = 'código', string $valor = '', ?Throwable $anterior = null)
    {
        $mensaje = "Ya existe un actor registrado con el {$campo} '{$valor}'.";
        parent::__construct($mensaje, 409, $anterior);
    }
}
