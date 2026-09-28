<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

class ValidacionReciboExcepcion extends RuntimeException
{
    protected $code = 422;

    /** @var array<string, string> */
    private array $errores;

    /**
     * @param string|array<string, string> $mensaje
     * @param array<string, string> $errores
     * @param int $code
     */
    public function __construct(string|array $mensaje = '', array $errores = [], int $code = 422)
    {
        if (is_array($mensaje)) {
            $this->errores = $mensaje;
            $msg = implode(', ', $mensaje);
        } else {
            $this->errores = $errores;
            $msg = $mensaje;
        }
        parent::__construct($msg, $code);
    }

    /**
     * @return array<string, string>
     */
    public function obtenerErrores(): array
    {
        return $this->errores;
    }
}
