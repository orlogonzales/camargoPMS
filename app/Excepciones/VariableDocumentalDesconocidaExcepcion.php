<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando una plantilla contiene shortcodes no autorizados o desconocidos (D-079 #11).
 */
class VariableDocumentalDesconocidaExcepcion extends RuntimeException
{
    private string $variable;
    private string $plantillaCodigo;

    public function __construct(string $variable, string $plantillaCodigo = '', string $mensaje = '')
    {
        $this->variable = $variable;
        $this->plantillaCodigo = $plantillaCodigo;

        $msg = $mensaje !== ''
            ? $mensaje
            : "La variable documental [{{{$variable}}}] es desconocida o no está registrada en el catálogo de shortcodes autorizados" . ($plantillaCodigo ? " para la plantilla [{$plantillaCodigo}]." : ".");

        parent::__construct($msg, 422);
    }

    public function obtenerVariable(): string
    {
        return $this->variable;
    }

    public function obtenerPlantillaCodigo(): string
    {
        return $this->plantillaCodigo;
    }
}
