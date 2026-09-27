<?php

declare(strict_types=1);

namespace CamargoPMS\Excepciones;

use RuntimeException;

/**
 * Excepción lanzada cuando un importe monetario es negativo, nulo o tiene formato no numérico.
 */
class MontoInvalidoExcepcion extends RuntimeException
{
    public function __construct(string $campo = 'monto', string $valor = '')
    {
        $detalle = $valor !== '' ? " El valor proporcionado [{$valor}] es inválido." : '';
        parent::__construct("El importe monetario para [{$campo}] debe ser un valor decimal estrictamente positivo.{$detalle}", 422);
    }
}
