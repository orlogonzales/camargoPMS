<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use InvalidArgumentException;

/**
 * Excepción lanzada cuando una ruta de menú es inválida, insegura o contiene esquemas no permitidos
 * (tales como javascript:, data:, vbscript: o URLs externas no autorizadas).
 */
class RutaInvalidaExcepcion extends InvalidArgumentException
{
    public function __construct(string $mensaje = 'La ruta proporcionada es inválida o insegura.')
    {
        parent::__construct($mensaje, 422);
    }
}
