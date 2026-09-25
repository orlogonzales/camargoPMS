<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando los datos de entrada violan las reglas de validación del dominio.
 */
class ValidacionExcepcion extends DomainException
{
    /** @var array<string, string> */
    private array $errores;

    /**
     * @param string $mensaje Mensaje descriptivo general.
     * @param array<string, string> $errores Mapa de campo => mensaje de error específico.
     * @param int $codigo Código de error HTTP/dominio.
     */
    public function __construct(string $mensaje, array $errores = [], int $codigo = 422)
    {
        parent::__construct($mensaje, $codigo);
        $this->errores = $errores;
    }

    /**
     * Obtiene la lista detallada de errores por campo.
     *
     * @return array<string, string>
     */
    public function obtenerErrores(): array
    {
        return $this->errores;
    }
}
