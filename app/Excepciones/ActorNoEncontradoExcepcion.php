<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use Throwable;

/**
 * Excepción lanzada cuando un actor de auditoría requerido no existe o no pudo ser resuelto.
 */
class ActorNoEncontradoExcepcion extends AuditoriaExcepcion
{
    public function __construct(string $identificador = '', ?Throwable $anterior = null)
    {
        $mensaje = $identificador !== ''
            ? "El actor de auditoría '{$identificador}' no existe o no se encuentra registrado."
            : 'Actor de auditoría requerido no especificado ni encontrado.';
        parent::__construct($mensaje, 404, $anterior);
    }
}
