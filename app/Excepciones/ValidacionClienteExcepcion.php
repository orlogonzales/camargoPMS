<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use DomainException;

/**
 * Excepción lanzada cuando fallan las validaciones de negocio e invariantes de Cliente o Categoría.
 */
class ValidacionClienteExcepcion extends DomainException
{
    /** @var array<string, string> */
    private array $errores;

    /**
     * @param string $mensaje
     * @param array<string, string> $errores
     * @param int $codigo
     */
    public function __construct(string $mensaje, array $errores = [], int $codigo = 422)
    {
        $this->errores = $errores;
        parent::__construct($mensaje, $codigo);
    }

    /**
     * @return array<string, string>
     */
    public function obtenerErrores(): array
    {
        return $this->errores;
    }
}
