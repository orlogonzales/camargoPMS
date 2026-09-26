<?php

declare(strict_types=1);

namespace CamargoPMS\Modelos;

/**
 * Catálogo canónico de tipos de actor del sistema.
 *
 * Principio Vinculante:
 * ACTOR != USUARIO
 * Un Usuario representa exclusivamente una cuenta humana interactiva.
 * Los Actores abarcan usuarios humanos, subsistemas internos, integraciones API y pasarelas de pago.
 */
final class TipoActor
{
    public const USUARIO = 'USUARIO';
    public const SISTEMA = 'SISTEMA';
    public const INTEGRACION = 'INTEGRACION';
    public const PROVEEDOR_PAGO = 'PROVEEDOR_PAGO';

    public const TODOS = [
        self::USUARIO,
        self::SISTEMA,
        self::INTEGRACION,
        self::PROVEEDOR_PAGO,
    ];

    /**
     * Evalúa si un tipo de actor provisto es válido dentro del catálogo oficial.
     *
     * @param string $tipo
     * @return bool
     */
    public static function esValido(string $tipo): bool
    {
        return in_array(strtoupper(trim($tipo)), self::TODOS, true);
    }
}
