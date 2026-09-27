<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una plantilla contiene HTML inseguro, scripts, iframes o URLs no autorizadas (D-079 #3).
 */
class ContenidoDocumentalInseguroExcepcion extends RuntimeException
{
    private string $patronDetectado;

    public function __construct(string $patronDetectado, string $mensaje = '')
    {
        $this->patronDetectado = $patronDetectado;

        $msg = $mensaje !== ''
            ? $mensaje
            : "Contenido documental rechazado por motivos de seguridad: se detectó el patrón o elemento prohibido [{$patronDetectado}].";

        parent::__construct($msg, 422);
    }

    public function obtenerPatronDetectado(): string
    {
        return $this->patronDetectado;
    }
}
